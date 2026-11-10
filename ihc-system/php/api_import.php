<?php
declare(strict_types=1);
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/installment-schedule.php';
require_once __DIR__ . '/legacy-import.php';

/**
 * Collection-Excel import — migrates a clerk's existing (ongoing) accounts
 * from the collection officer workbook into `contracts`.
 *
 * The Excel has no TCP / downpayment / terms, so each account is imported as
 * an OPENING BALANCE at the workbook's cutoff month (the last
 * "AR Balance as of <Month> <Year>" column):
 *   total_contract_price = opening_balance = AR balance, downpayment = 0,
 *   monthly_amortization = that month's actual due (fallback: month column),
 *   installment_terms    = ceil(balance / monthly),
 *   first due            = cutoff due date minus the months in ARREARS
 *                          (or the month after Last Due Date Paid for
 *                          ADVANCE accounts),
 *   start_date           = first due minus one month (ihc_schedule() puts
 *                          installment #1 at start_date + 1 month).
 * Collections already recorded for the cutoff month are imported as payments.
 * Every original cell is kept in contracts.legacy_data (JSON).
 *
 * The browser parses the workbook (SheetJS) and posts {headers, rows}; ALL
 * mapping and validation happens here, and `commit` re-runs it rather than
 * trusting the preview. Rows are assigned to the uploading clerk and must
 * carry that clerk's officer_code in "Collection Officer".
 *
 * POST JSON {action, officerId, ...}
 *   preview          {headers:[...], rows:[{n, cells:[...]}]}
 *   commit           same + overrides:{ "<n>": {include, email, phone} }
 *   incomplete       → this clerk's imported accounts still missing contact info
 *   complete_contact {contractId, email, phone}
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

const IMP_MONTHS = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
const IMP_MAX_ROWS = 3000;

function imp_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function imp_norm_header($h): string {
    $h = preg_replace('/^\x{FEFF}/u', '', (string)$h); // CSV byte-order mark
    return strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
}

function imp_str($v): string {
    if ($v === null) return '';
    if (is_float($v) && floor($v) == $v && abs($v) < 1e15) return (string)(int)$v;
    return trim(preg_replace('/\s+/', ' ', (string)$v));
}

function imp_num($v): ?float {
    if ($v === null) return null;
    if (is_int($v) || is_float($v)) return (float)$v;
    // Tolerate stray characters typed into amount cells ("273,608.19  \", "₱ 5,000").
    $s = preg_replace('/[^0-9.\-]/', '', (string)$v);
    if ($s === '' || !is_numeric($s)) return null;
    return (float)$s;
}

function imp_date($v): ?string {
    $s = imp_str($v);
    if ($s === '') return null;
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
        [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $s, $m)) {
        [$mo, $d, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($y < 100) $y += 2000;
    } else {
        return null;
    }
    if ($y < 1990 || $y > 2100 || !checkdate($mo, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Y-m-d for (year, month, day) with the day clamped to the month's length;
 *  month may be outside 1..12 (it is normalized). */
function imp_ymd(int $y, int $m, int $d): string {
    $y += intdiv($m - 1 - (($m - 1) % 12 + 12) % 12, 12);
    $m = (($m - 1) % 12 + 12) % 12 + 1;
    $last = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $m, max(1, min($d, $last)));
}

/** "Block 7 Lot 5, Phase 1, The Mandara" — also the property_units label. */
function imp_property_address(string $blk, string $lot, string $phase, string $project): string {
    $blockLot = trim(($blk !== '' ? 'Block ' . $blk : '') . ($lot !== '' ? ' Lot ' . $lot : ''));
    $phaseText = $phase === '' ? '' : (preg_match('/^\d+[A-Z]?$/i', $phase) ? 'Phase ' . strtoupper($phase) : $phase);
    return mb_substr(implode(', ', array_filter([$blockLot, $phaseText, $project], fn($s) => $s !== '')), 0, 500);
}

function imp_key(string ...$parts): string {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', implode('|', $parts)));
}

function imp_financing(string $raw): string {
    $u = strtoupper(trim($raw));
    $u = str_replace('DEFFERED', 'DEFERRED', $u);
    if (in_array($u, ['IN HOUSE', 'INHOUSE', 'IN HOUSE FINANCING', 'IN-HOUSE FINANCING'], true)) return 'IN-HOUSE';
    if (in_array($u, ['PAG-IBIG', 'PAGIBIG', 'PAG-IBIG FINANCING', 'PAG IBIG'], true)) return 'HDMF';
    return $u;
}

function imp_bank(string $financing): ?string {
    if ($financing === '') return null;
    if ($financing === 'HDMF') return 'Pag-IBIG';
    if (strpos($financing, 'IN-HOUSE') !== false) return 'In-House';
    if (strpos($financing, 'DEFERRED') !== false) return 'Deferred';
    if ($financing === 'BANK') return 'Bank';
    return $financing;
}

function imp_phone(string $raw): ?string {
    $d = preg_replace('/\D/', '', $raw);
    if ($d === '') return '';
    if (strlen($d) === 12 && strpos($d, '63') === 0) $d = '0' . substr($d, 2);
    if (strlen($d) === 10 && $d[0] === '9') $d = '0' . $d;
    return (strlen($d) === 11 && strpos($d, '09') === 0) ? $d : null;
}

/** Load the uploading clerk; they must exist, be a clerk, and have a code. */
function imp_officer(PDO $pdo, string $officerId): array {
    if ($officerId === '') imp_fail('officerId is required.');
    $st = $pdo->prepare('SELECT id, full_name, role, officer_code FROM officers WHERE id = ? LIMIT 1');
    $st->execute([$officerId]);
    $o = $st->fetch();
    if (!$o) imp_fail('Your officer account was not found.', 403);
    if (strtolower(trim((string)$o['role'])) !== 'clerk') imp_fail('Only clerk accounts can import their collection accounts.', 403);
    return $o;
}

/**
 * Map + validate every row. Returns ['cutoff'=>..., 'rows'=>[...]] where each
 * row carries the computed contract values plus level ok|warn|error.
 */
/**
 * $overrides["<n>"] may carry clerk-entered fixes for a row: balance, monthly,
 * firstDue (Y-m-d). They replace what the Excel gave and the row is
 * re-validated, so a row blocked only for missing/odd amounts can be imported.
 */
function imp_analyze(PDO $pdo, array $officer, array $headers, array $rows, array $overrides = []): array {
    $code = strtoupper(trim((string)($officer['officer_code'] ?? '')));
    if ($code === '') {
        imp_fail('Your account has no Officer Code yet. Ask the admin to set it (Employees → Edit Clerk) to the code used in the Excel "Collection Officer" column, e.g. AJ.');
    }

    $H = array_map('imp_norm_header', $headers);
    $find = function (string $name, int $after = -1) use ($H): ?int {
        foreach ($H as $i => $h) if ($i > $after && $h === $name) return $i;
        return null;
    };

    // Cutoff = the LAST "AR Balance as of <Month> <Year>" column.
    $arIdx = null; $cutY = 0; $cutM = 0;
    foreach ($H as $i => $h) {
        if (preg_match('/^AR BALANCE AS OF ([A-Z]+)\.? (\d{4})$/', $h, $m)) {
            $mon = IMP_MONTHS[substr($m[1], 0, 3)] ?? 0;
            if ($mon) { $arIdx = $i; $cutY = (int)$m[2]; $cutM = $mon; }
        }
    }
    if ($arIdx === null) imp_fail('Could not find an "AR Balance as of <Month> <Year>" column. Is this the collection officer workbook?');

    $c = [
        'code'       => $find('AJ CODE'),
        'buyer'      => $find('BUYERS CODE'),
        'branch'     => $find('BRANCH'),
        'company'    => $find('COMPANY'),
        'officer'    => $find('COLLECTION OFFICER'),
        'rsDate'     => $find('RS DATE'),
        'recv'       => $find('RECEIVABLE STATUS'),
        'name'       => (function () use ($H) {
            foreach ($H as $i => $h) if (strpos($h, 'BUYER') === 0 && strpos($h, 'NAME') !== false) return $i;
            return null;
        })(),
        'financing'  => $find('FINANCING'),
        'project'    => $find('PROJECT'),
        'phase'      => $find('PHASE'),
        'blk'        => $find('BLK'),
        'lot'        => $find('LOT'),
        'dueDay'     => $find('DUE DATE'),
        'pdc'        => $find('PDC'),
        'sales'      => $find('SALES OFFICER'),
        'concern'    => $find('PAST DUE CONCERNS'),
        'status'     => $find('STATUS'),
        'remarks'    => $find('REMARKS'),
        'collType'   => $find('FOR COLLECTION'),
        'lastPaid'   => $find('LAST DUE DATE PAID'),
        'actualDue'  => null, 'arrears' => null, 'collected' => null, 'payDate' => null, 'invoice' => null,
    ];
    foreach ($H as $i => $h) {
        if ($i <= $arIdx) continue;
        if ($c['actualDue'] === null && strpos($h, 'ACTUAL DUE') === 0) $c['actualDue'] = $i;
        if ($c['arrears'] === null && $h === 'ARREARS') $c['arrears'] = $i;
        if ($c['collected'] === null && $h === 'AMOUNT COLLECTED') $c['collected'] = $i;
        if ($c['payDate'] === null && $h === 'DATE OF PAYMENT') $c['payDate'] = $i;
        if ($c['invoice'] === null && $h === 'INVOICE NO.') $c['invoice'] = $i;
    }
    foreach (['name', 'project', 'actualDue'] as $req) {
        if ($c[$req] === null) imp_fail('The workbook is missing a required column (' . $req . ').');
    }

    // Monthly columns JAN..DEC, repeated: the first set is the cutoff year.
    $monthCols = [];
    foreach ($H as $i => $h) {
        if (isset(IMP_MONTHS[$h]) && strlen($h) === 3) $monthCols[] = $i;
    }
    $cutoffMonthPos = $cutM - 1; // position inside $monthCols
    // "Actual due <earlier month>" columns (before the cutoff block).
    $prevDueCols = [];
    foreach ($H as $i => $h) if ($i < $arIdx && strpos($h, 'ACTUAL DUE') === 0) $prevDueCols[] = $i;

    // Unique display labels for the raw-row JSON (headers repeat).
    $labels = []; $seen = [];
    foreach ($headers as $i => $h) {
        $base = trim((string)$h) !== '' ? trim(preg_replace('/\s+/', ' ', (string)$h)) : ('Column ' . ($i + 1));
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        $labels[$i] = $seen[$base] > 1 ? $base . ' (' . $seen[$base] . ')' : $base;
    }

    // Existing contracts, for "already imported" detection.
    $existingByCode = []; $existingByKey = [];
    foreach ($pdo->query('SELECT id, client_name, property_address, legacy_code FROM contracts') as $e) {
        if (trim((string)$e['legacy_code']) !== '') $existingByCode[strtoupper(trim((string)$e['legacy_code']))] = (int)$e['id'];
        $existingByKey[imp_key((string)$e['client_name'], (string)$e['property_address'])] = (int)$e['id'];
    }

    // Bank default rates (snapshotted like the New Contract form does).
    $rates = [];
    try {
        foreach ($pdo->query('SELECT bank_name, annual_rate FROM bank_rates') as $r) $rates[strtolower((string)$r['bank_name'])] = (float)$r['annual_rate'];
    } catch (Throwable $e) { /* table may not exist yet */ }

    // Canonical project spelling inside this file ("VIA VERDE STO. TOMAS" ==
    // "Via Verde-Sto.Tomas"): prefer the first mixed-case spelling.
    $projectCanon = [];
    foreach ($rows as $r) {
        $p = imp_str($r['cells'][$c['project']] ?? '');
        if ($p === '') continue;
        $k = imp_key($p);
        $mixed = $p !== strtoupper($p);
        if (!isset($projectCanon[$k]) || ($mixed && !$projectCanon[$k]['mixed'])) {
            $projectCanon[$k] = ['name' => $mixed ? $p : ucwords(strtolower($p)), 'mixed' => $mixed];
        }
    }

    $cell = function (array $cells, ?int $idx) { return $idx === null ? null : ($cells[$idx] ?? null); };

    $out = [];
    $seenKeys = [];
    $otherOfficers = []; // code => row count (consolidated workbooks hold every clerk)
    foreach ($rows as $r) {
        $n = (int)($r['n'] ?? 0);
        $cells = is_array($r['cells'] ?? null) ? array_values($r['cells']) : [];
        $issues = []; $errors = []; $info = [];

        $name = imp_str($cell($cells, $c['name']));
        // Skip fully blank lines silently.
        $nonEmpty = array_filter($cells, fn($v) => imp_str($v) !== '');
        if (!$nonEmpty) continue;

        // Rows of other collection officers are not this clerk's: skip them
        // quietly (counted for the preview) instead of listing them as errors.
        $rowOfficerEarly = strtoupper(imp_str($cell($cells, $c['officer'])));
        if ($rowOfficerEarly !== '' && $rowOfficerEarly !== $code) {
            $otherOfficers[$rowOfficerEarly] = ($otherOfficers[$rowOfficerEarly] ?? 0) + 1;
            continue;
        }

        $legacyCode = strtoupper(imp_str($cell($cells, $c['code'])));
        $projectRaw = imp_str($cell($cells, $c['project']));
        $project = $projectRaw !== '' ? ($projectCanon[imp_key($projectRaw)]['name'] ?? $projectRaw) : '';
        $phase = imp_str($cell($cells, $c['phase']));
        $blk = imp_str($cell($cells, $c['blk']));
        $lot = imp_str($cell($cells, $c['lot']));
        $propertyAddress = imp_property_address($blk, $lot, $phase, $project);

        $financing = imp_financing(imp_str($cell($cells, $c['financing'])));
        $bank = imp_bank($financing);
        $rowOfficer = strtoupper(imp_str($cell($cells, $c['officer'])));
        $status = imp_str($cell($cells, $c['status']));
        $remarks = imp_str($cell($cells, $c['remarks']));
        $concern = imp_str($cell($cells, $c['concern']));

        $ov = is_array($overrides[(string)$n] ?? null) ? $overrides[(string)$n] : [];
        $manual = [];
        $soft = 0; // errors the clerk can fix by entering balance / monthly / first due

        $balance = imp_num($cell($cells, $arIdx));
        $ovBalance = imp_num($ov['balance'] ?? null);
        if ($ovBalance !== null && $ovBalance > 0) { $balance = $ovBalance; $manual['balance'] = round($ovBalance, 2); $info[] = 'Balance entered by you (Excel: ' . number_format((float)imp_num($cell($cells, $arIdx)), 2) . ').'; }
        $actualDue = imp_num($cell($cells, $c['actualDue'])) ?? 0.0;
        $arrears = imp_num($cell($cells, $c['arrears'])) ?? 0.0;
        $lastPaid = imp_date($cell($cells, $c['lastPaid']));
        $rsDate = imp_date($cell($cells, $c['rsDate']));

        if ($name === '') $errors[] = 'No buyer name.';
        if ($rowOfficer === '') {
            $issues[] = 'No Collection Officer — will be assigned to you.';
        }
        if ($balance === null || $balance <= 0.009) { $errors[] = 'No AR balance as of the cutoff (zero or blank) — enter the balance if there is still something to collect.'; $soft++; }
        if ($project === '') $issues[] = 'No project.';
        if ($actualDue < 0) { $issues[] = 'Negative Actual due ignored.'; $actualDue = 0.0; }
        if ($arrears < 0) { $issues[] = 'Negative ARREARS ignored.'; $arrears = 0.0; }

        // Monthly amortization.
        $monthVals = array_map(fn($i) => imp_num($cells[$i] ?? null) ?? 0.0, $monthCols);
        $monthCol = $monthVals[$cutoffMonthPos] ?? 0.0;
        $monthly = 0.0; $onHold = false;
        if ($actualDue > 0.009) {
            $monthly = $actualDue;
            if ($monthCol > 0.009 && abs($monthCol - $actualDue) > 1) {
                $info[] = 'Month column shows ' . number_format($monthCol, 2) . '; using the actual due ' . number_format($actualDue, 2) . '.';
            }
        } elseif ($monthCol > 0.009) {
            $monthly = $monthCol;
        } else {
            for ($i = $cutoffMonthPos + 1; $i < count($monthVals); $i++) {
                if ($monthVals[$i] > 0.009) { $monthly = $monthVals[$i]; break; }
            }
            // An earlier month's "Actual due" (e.g. October's) — accounts paid
            // ahead often have no amount in the cutoff month columns.
            if ($monthly <= 0.009) {
                foreach (array_reverse($prevDueCols) as $i) {
                    $v = imp_num($cells[$i] ?? null) ?? 0.0;
                    if ($v > 0.009) { $monthly = $v; $info[] = 'Monthly taken from an earlier month\'s actual due.'; break; }
                }
            }
            if ($monthly <= 0.009) {
                for ($i = min($cutoffMonthPos, count($monthVals) - 1); $i >= 0; $i--) {
                    if ($monthVals[$i] > 0.009) { $monthly = $monthVals[$i]; $onHold = true; break; }
                }
            }
        }
        $ovMonthly = imp_num($ov['monthly'] ?? null);
        if ($ovMonthly !== null && $ovMonthly > 0) {
            $monthly = $ovMonthly; $onHold = false;
            $manual['monthly'] = round($ovMonthly, 2);
            $info[] = 'Monthly entered by you.';
        }
        $monthly = round($monthly, 2);
        if ($monthly <= 0.009) { $errors[] = 'No monthly amount in the Excel — enter the monthly amount (and first due date) to import it.'; $soft++; }
        $paidAhead = $lastPaid !== null && $lastPaid >= imp_ymd($cutY, $cutM, 1);
        if ($onHold && !$paidAhead) {
            $issues[] = 'No dues since earlier this year' . ($status !== '' ? ' (' . $status . ')' : '') . '. It will become due monthly again from the first due date shown.';
        }
        // A lasting change of amount: the final amount in the month columns
        // differs and every later non-zero month keeps it. (The second year
        // often just repeats the first year's January bump — ignored.)
        $later = array_values(array_filter(array_slice($monthVals, $cutoffMonthPos + 1), fn($v) => $v > 0.009));
        if ($monthly > 0.009 && $later) {
            $final = end($later);
            $from = null; $runLength = 0;
            for ($i = count($monthVals) - 1; $i > $cutoffMonthPos; $i--) {
                if ($monthVals[$i] <= 0.009) continue;
                if (abs($monthVals[$i] - $final) > 0.01) break;
                $from = $i; $runLength++;
            }
            // A single trailing odd amount is just the last (balance) payment.
            if (abs($final - $monthly) > 1 && $runLength >= 2) {
                $fromLabel = $from !== null ? date('M Y', mktime(0, 0, 0, ($from % 12) + 1, 1, $cutY + intdiv($from, 12))) : 'later';
                $issues[] = 'The Excel changes the monthly amount to ' . number_format($final, 2) . ' from ' . $fromLabel . '. The system uses ' . number_format($monthly, 2) . ' for every month — update the account when it changes.';
            }
        }

        // Due day of month.
        $dueDayNum = imp_num($cell($cells, $c['dueDay']));
        $dueDay = ($dueDayNum !== null && $dueDayNum >= 1 && $dueDayNum <= 31) ? (int)$dueDayNum : 0;
        if ($dueDay === 0) {
            $dueDay = $lastPaid ? (int)substr($lastPaid, 8, 2) : ($rsDate ? (int)substr($rsDate, 8, 2) : 1);
            $issues[] = 'No due day; using day ' . $dueDay . '.';
        }

        // First unpaid installment.
        $cutoffDue = imp_ymd($cutY, $cutM, $dueDay);
        $arrearsMonths = 0;
        if ($actualDue > 0.009 || $arrears > 0.009) {
            if ($arrears > 0.009 && $monthly > 0.009) $arrearsMonths = max(1, (int)round($arrears / $monthly));
            $fy = $cutY; $fm = $cutM - $arrearsMonths;
            if ($arrearsMonths > 0) $info[] = 'Arrears of ' . number_format($arrears, 2) . ' ≈ ' . $arrearsMonths . ' month' . ($arrearsMonths > 1 ? 's' : '') . ' overdue.';
        } elseif ($lastPaid && $lastPaid >= $cutoffDue) {
            $fy = (int)substr($lastPaid, 0, 4); $fm = (int)substr($lastPaid, 5, 2) + 1;
            $info[] = 'Paid in advance up to ' . $lastPaid . '.';
        } else {
            $fy = $cutY; $fm = $cutM + 1;
        }
        $firstDue = imp_ymd($fy, $fm, $dueDay);
        $startDate = imp_ymd($fy, $fm - 1, $dueDay);
        $ovFirst = imp_date($ov['firstDue'] ?? null);
        if ($ovFirst) {
            [$oy, $om, $od] = array_map('intval', explode('-', $ovFirst));
            $firstDue = $ovFirst;
            $startDate = imp_ymd($oy, $om - 1, $od);
            $arrearsMonths = 0;
            $manual['firstDue'] = $ovFirst;
            $info[] = 'First due date entered by you.';
            if ($ovFirst < imp_ymd($cutY, $cutM, 1)) {
                $issues[] = 'The first due date you entered (' . $ovFirst . ') is before ' . date('F Y', mktime(0, 0, 0, $cutM, 1, $cutY))
                    . ' — every month from then until now will show as OVERDUE. Leave it blank to use the Excel\'s schedule.';
            }
        }

        $terms = 0;
        if ($balance !== null && $balance > 0.009 && $monthly > 0.009) {
            $terms = max(1, (int)ceil(round($balance / $monthly, 6)));
            if ($terms > 600) { $errors[] = 'Balance ÷ monthly is ' . $terms . ' months — correct the balance or the monthly amount.'; $soft++; }
            elseif ($terms > 360) $issues[] = 'Very long remaining term (' . $terms . ' months) — check if the balance includes a bank/Pag-IBIG loan.';
        }

        // Collection already recorded for the cutoff month.
        $payment = null;
        $collected = imp_num($cell($cells, $c['collected'])) ?? 0.0;
        if ($collected > 0.009) {
            $dates = array_filter(array_map('imp_date', preg_split('/\s*,\s*/', imp_str($cell($cells, $c['payDate'])))));
            $payDate = $dates ? end($dates) : null;
            if (!$payDate) { $payDate = $cutoffDue; $issues[] = 'Payment date unreadable; using ' . $cutoffDue . '.'; }
            $inv = imp_str($cell($cells, $c['invoice']));
            $orNumber = (preg_match('/\d/', $inv) && !preg_match('/for\s*or/i', $inv)) ? mb_substr($inv, 0, 100) : 'FOR OR';
            $payment = ['amount' => round($collected, 2), 'date' => $payDate, 'orNumber' => $orNumber];
            if ($balance !== null && $collected > $balance + 0.009) $errors[] = 'Collected amount is larger than the AR balance.';
        }

        // Duplicates: inside the file, then against the database.
        $dupKey = imp_key($name, $project, $blk, $lot);
        $existingId = null;
        if (isset($seenKeys[$dupKey])) {
            $errors[] = 'Duplicate of row ' . $seenKeys[$dupKey] . ' (same buyer and unit).';
        } else {
            $seenKeys[$dupKey] = $n;
        }
        if ($legacyCode !== '' && isset($existingByCode[$legacyCode])) $existingId = $existingByCode[$legacyCode];
        elseif (isset($existingByKey[imp_key($name, $propertyAddress)])) $existingId = $existingByKey[imp_key($name, $propertyAddress)];
        if ($existingId !== null) $errors[] = 'Already in the system as CON-' . $existingId . '.';

        // Original row, nothing dropped.
        $raw = [];
        foreach ($cells as $i => $v) {
            $s = imp_str($v);
            if ($s !== '') $raw[$labels[$i] ?? ('Column ' . ($i + 1))] = is_int($v) || is_float($v) ? $v : $s;
        }

        $level = $errors ? 'error' : ($issues ? 'warn' : 'ok');
        $out[] = [
            'n' => $n,
            'level' => $level,
            'errors' => $errors,
            'issues' => $issues,
            'info' => $info,
            'legacyCode' => $legacyCode !== '' ? mb_substr($legacyCode, 0, 30) : null,
            'buyerCode' => imp_str($cell($cells, $c['buyer'])) ?: null,
            'clientName' => mb_substr($name, 0, 255),
            'propertyAddress' => mb_substr($propertyAddress, 0, 500),
            'project' => $project,
            'phase' => $phase,
            'block' => $blk,
            'lot' => $lot,
            'company' => strtoupper(imp_str($cell($cells, $c['company']))) ?: null,
            'branch' => ($b = imp_str($cell($cells, $c['branch']))) !== '' ? ucwords(strtolower($b)) : null,
            'financing' => $financing !== '' ? $financing : null,
            'bank' => $bank,
            'annualRate' => $bank !== null && isset($rates[strtolower($bank)]) ? $rates[strtolower($bank)] : null,
            'receivableStatus' => imp_str($cell($cells, $c['recv'])) ?: null,
            'collectionType' => imp_str($cell($cells, $c['collType'])) ?: null,
            'salesOfficer' => imp_str($cell($cells, $c['sales'])) ?: null,
            'rsDate' => $rsDate,
            'pdc' => strtoupper(imp_str($cell($cells, $c['pdc']))) ?: null,
            'status' => $status !== '' ? mb_substr($status, 0, 100) : null,
            'remarks' => mb_substr(implode(' · ', array_filter([$remarks, $concern])), 0, 255) ?: null,
            'openingBalance' => $balance !== null ? round($balance, 2) : null,
            'monthly' => $monthly,
            'terms' => $terms,
            'arrears' => round($arrears, 2),
            'arrearsMonths' => $arrearsMonths,
            'firstDue' => $firstDue,
            'startDate' => $startDate,
            'payment' => $payment,
            'existingContractId' => $existingId,
            // Blocked only by amounts the clerk can type in the preview.
            'fixable' => $errors && count($errors) === $soft,
            'manual' => $manual ?: null,
            'collectionOfficer' => $rowOfficer !== '' ? $rowOfficer : null,
            'raw' => $raw,
        ];
    }

    return [
        'cutoff' => [
            'year' => $cutY,
            'month' => $cutM,
            'label' => date('F Y', mktime(0, 0, 0, $cutM, 1, $cutY)),
            'asOf' => sprintf('%04d-%02d-01', $cutY, $cutM),
        ],
        'rows' => $out,
        'otherOfficers' => $otherOfficers,
    ];
}

/** The officer's batch, or a 404. */
function imp_batch(PDO $pdo, int $batchId, string $officerId): array {
    $st = $pdo->prepare('SELECT * FROM import_batches WHERE id = ? LIMIT 1');
    $st->execute([$batchId]);
    $b = $st->fetch();
    if (!$b || (string)$b['officer_id'] !== $officerId || $b['status'] === 'UNDONE') imp_fail('Import batch not found.', 404);
    return $b;
}

/** Payments the import created for a contract (never regular postings). */
function imp_imported_payments(PDO $pdo, int $contractId): array {
    $st = $pdo->prepare(
        "SELECT id, amount, date_collected, or_number FROM payments
         WHERE contract_id IN (?, ?, ?) AND payment_method = 'Imported (Excel)' ORDER BY id"
    );
    $st->execute(['CON-' . $contractId, (string)$contractId, 'IHC-' . $contractId]);
    return $st->fetchAll();
}

/** Human-readable list of anything recorded for these contracts outside the
 *  import (an undo would silently destroy it). */
function imp_post_import_activity(PDO $pdo, array $ids): array {
    if (!$ids) return [];
    $found = [];
    $variants = [];
    foreach ($ids as $id) { $variants[] = 'CON-' . $id; $variants[] = (string)$id; $variants[] = 'IHC-' . $id; }
    $ph = implode(',', array_fill(0, count($variants), '?'));
    $st = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE contract_id IN ($ph) AND payment_method <> 'Imported (Excel)'");
    $st->execute($variants);
    if (($n = (int)$st->fetchColumn()) > 0) $found[] = $n . ' posted payment(s)';
    $in = implode(',', array_map('intval', $ids));
    foreach ([
        'additional_charges' => 'additional charge(s)',
        'additional_equity_payments' => 'additional equity payment(s)',
        'reservation_fees' => 'reservation fee(s)',
        'holding_fees' => 'holding fee(s)',
    ] as $table => $label) {
        try {
            $n = (int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE contract_id IN ($in)")->fetchColumn();
            if ($n > 0) $found[] = $n . ' ' . $label;
        } catch (Throwable $e) { /* table not present */ }
    }
    return $found;
}

/** Re-count a batch's accounts/payments/totals after an edit. */
function imp_refresh_batch_totals(PDO $pdo, int $batchId): void {
    $st = $pdo->prepare('SELECT id, total_contract_price FROM contracts WHERE import_batch_id = ?');
    $st->execute([$batchId]);
    $count = 0; $balance = 0.0; $payments = 0; $collected = 0.0;
    foreach ($st->fetchAll() as $c) {
        $count++;
        $balance += (float)$c['total_contract_price'];
        foreach (imp_imported_payments($pdo, (int)$c['id']) as $p) { $payments++; $collected += (float)$p['amount']; }
    }
    $pdo->prepare('UPDATE import_batches SET contracts_count = ?, payments_count = ?, total_balance = ?, total_collected = ? WHERE id = ?')
        ->execute([$count, $payments, round($balance, 2), round($collected, 2), $batchId]);
}

/** Move an imported account to a corrected property address: release the old
 *  unit exactly as the import found it, then link/create the new one, and keep
 *  the batch's undo bookkeeping in step. Runs inside the caller's transaction. */
function imp_relink_unit(PDO $pdo, array $batch, int $contractId, string $oldLabel, string $newLabel, array $parts): void {
    $batchId = (int)$batch['id'];
    $st = $pdo->prepare('SELECT unit_ids_created, unit_links FROM import_batches WHERE id = ? FOR UPDATE');
    $st->execute([$batchId]);
    $row = $st->fetch();
    $created = array_map('intval', json_decode((string)($row['unit_ids_created'] ?? '[]'), true) ?: []);
    $links = json_decode((string)($row['unit_links'] ?? '{}'), true) ?: [];

    if ($oldLabel !== '') {
        $st = $pdo->prepare('SELECT id FROM property_units WHERE display_label = ? AND current_contract_id = ? LIMIT 1');
        $st->execute([mb_substr($oldLabel, 0, 255), $contractId]);
        $oldId = $st->fetchColumn();
        if ($oldId) {
            $oldId = (int)$oldId;
            if (in_array($oldId, $created, true)) {
                $pdo->prepare('DELETE FROM property_units WHERE id = ?')->execute([$oldId]);
                $created = array_values(array_filter($created, fn($u) => $u !== $oldId));
            } elseif (array_key_exists((string)$oldId, $links)) {
                $prev = $links[(string)$oldId];
                $pdo->prepare('UPDATE property_units SET current_contract_id = ? WHERE id = ?')->execute([$prev !== null ? (int)$prev : null, $oldId]);
                unset($links[(string)$oldId]);
            }
        }
    }

    if ($newLabel !== '') {
        $label = mb_substr($newLabel, 0, 255);
        $st = $pdo->prepare('SELECT id, current_contract_id FROM property_units WHERE display_label = ? LIMIT 1');
        $st->execute([$label]);
        $unit = $st->fetch();
        if ($unit) {
            $uid = (int)$unit['id'];
            if (!in_array($uid, $created, true) && !array_key_exists((string)$uid, $links)) {
                $links[(string)$uid] = $unit['current_contract_id'] !== null ? (int)$unit['current_contract_id'] : null;
            }
            $pdo->prepare('UPDATE property_units SET current_contract_id = ? WHERE id = ?')->execute([$contractId, $uid]);
        } else {
            $pdo->prepare(
                "INSERT INTO property_units (display_label, project, phase, building, unit_number, status, current_contract_id)
                 VALUES (?,?,?,?,?,'SOLD',?)"
            )->execute([
                $label,
                $parts['project'] !== '' ? mb_substr($parts['project'], 0, 255) : null,
                $parts['phase'] !== '' ? mb_substr($parts['phase'], 0, 120) : null,
                $parts['block'] !== '' ? mb_substr($parts['block'], 0, 255) : null,
                $parts['lot'] !== '' ? mb_substr($parts['lot'], 0, 100) : null,
                $contractId,
            ]);
            $created[] = (int)$pdo->lastInsertId();
        }
    }

    $pdo->prepare('UPDATE import_batches SET unit_ids_created = ?, unit_links = ? WHERE id = ?')
        ->execute([json_encode($created), json_encode((object)$links), $batchId]);
}

/** What the Review table shows/edits for one imported contract. */
function imp_account_view(PDO $pdo, array $c): array {
    $cid = (int)$c['id'];
    $imported = imp_imported_payments($pdo, $cid);
    $schedulePayments = [];
    $st = $pdo->prepare('SELECT amount, date_collected FROM payments WHERE contract_id IN (?, ?, ?) ORDER BY date_collected, id');
    $st->execute(['CON-' . $cid, (string)$cid, 'IHC-' . $cid]);
    foreach ($st->fetchAll() as $p) $schedulePayments[] = ['amount' => (float)$p['amount'], 'date' => (string)$p['date_collected']];
    $sched = ihc_schedule($c, $schedulePayments);
    $next = $sched['next'];
    $first = $imported[0] ?? null;
    return [
        'contractId' => $cid,
        'legacyCode' => $c['legacy_code'],
        'clientName' => (string)$c['client_name'],
        'project' => (string)($c['project_name'] ?? ''),
        'phase' => (string)($c['project_phase'] ?? ''),
        'block' => (string)($c['block_no'] ?? ''),
        'lot' => (string)($c['lot_no'] ?? ''),
        'propertyAddress' => (string)($c['property_address'] ?? ''),
        'balance' => (float)$c['total_contract_price'],
        'monthly' => (float)($c['monthly_amortization'] ?? 0),
        'terms' => (int)$c['installment_terms'],
        'firstDue' => ihc_add_months((string)$c['start_date'], 1),
        'email' => (string)$c['email'],
        'phone' => (string)$c['cellphone_number'],
        'accountStatus' => $c['account_status'],
        'payment' => $first ? [
            'amount' => (float)$first['amount'],
            'date' => (string)$first['date_collected'],
            'orNumber' => (string)$first['or_number'],
        ] : null,
        'outstanding' => (float)$sched['outstanding'],
        'nextDue' => $next ? (string)$next['dueDate'] : null,
        'nextAmount' => $next ? (float)$next['amount'] : 0.0,
    ];
}

function imp_audit(PDO $pdo, string $action, string $actorId, string $actorName, array $details): void {
    try {
        $pdo->prepare(
            'INSERT INTO audit_logs (action, contract_id, from_status, to_status, actor_id, actor_name, details)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$action, null, null, null, $actorId, $actorName, json_encode($details)]);
    } catch (Throwable $e) { /* audit is best-effort */ }
}

try {
    $pdo = ihc_pdo();
    ihc_ensure_officer_code($pdo);
    ihc_ensure_legacy_contract_columns($pdo);
    ihc_ensure_import_batches($pdo);

    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 20 * 1024 * 1024) imp_fail('The upload is too large.');
    $data = json_decode($raw, true);
    if (!is_array($data)) imp_fail('Invalid JSON request.');

    $action = (string)($data['action'] ?? '');
    $officer = imp_officer($pdo, trim((string)($data['officerId'] ?? '')));
    $officerId = (string)$officer['id'];

    if ($action === 'incomplete') {
        $st = $pdo->prepare(
            "SELECT id, client_name, property_address, email, cellphone_number, legacy_code
             FROM contracts WHERE officer_id = ? AND needs_completion = 1 ORDER BY client_name"
        );
        $st->execute([$officerId]);
        $list = array_map(fn($r) => [
            'contractId' => (int)$r['id'],
            'clientName' => (string)$r['client_name'],
            'propertyAddress' => (string)($r['property_address'] ?? ''),
            'email' => (string)$r['email'],
            'phone' => (string)$r['cellphone_number'],
            'legacyCode' => $r['legacy_code'],
        ], $st->fetchAll());
        echo json_encode(['success' => true, 'accounts' => $list]);
        exit;
    }

    if ($action === 'complete_contact') {
        $contractId = (int)($data['contractId'] ?? 0);
        $st = $pdo->prepare('SELECT id, officer_id FROM contracts WHERE id = ? LIMIT 1');
        $st->execute([$contractId]);
        $row = $st->fetch();
        if (!$row || trim((string)$row['officer_id']) !== $officerId) imp_fail('Contract not found among your accounts.', 404);
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) imp_fail('Please enter a valid email address.');
        $phone = imp_phone((string)($data['phone'] ?? ''));
        if ($phone === null) imp_fail('Cellphone number must be an 11-digit mobile number (09XXXXXXXXX).');
        $needs = ($email === '' || $phone === '') ? 1 : 0;
        $pdo->prepare('UPDATE contracts SET email = ?, cellphone_number = ?, needs_completion = ? WHERE id = ?')
            ->execute([$email, $phone, $needs, $contractId]);
        echo json_encode(['success' => true, 'needsCompletion' => (bool)$needs, 'phone' => $phone, 'email' => $email]);
        exit;
    }

    // ---- Import batches: review / edit / undo / confirm ----------------
    if ($action === 'batches') {
        $st = $pdo->prepare("SELECT * FROM import_batches WHERE officer_id = ? AND status <> 'UNDONE' ORDER BY id DESC");
        $st->execute([$officerId]);
        $list = array_map(fn($b) => [
            'batchId' => (int)$b['id'],
            'status' => (string)$b['status'],
            'fileName' => $b['file_name'],
            'cutoffLabel' => $b['cutoff_label'],
            'contracts' => (int)$b['contracts_count'],
            'payments' => (int)$b['payments_count'],
            'totalBalance' => (float)$b['total_balance'],
            'totalCollected' => (float)$b['total_collected'],
            'createdAt' => (string)$b['created_at'],
            'confirmedAt' => $b['confirmed_at'],
            'fileRows' => $b['file_rows'] !== null ? (int)$b['file_rows'] : null,
            'alreadyRows' => (int)($b['already_rows'] ?? 0),
            'skipped' => $b['skipped_rows'] !== null ? (json_decode((string)$b['skipped_rows'], true) ?: []) : null,
        ], $st->fetchAll());
        echo json_encode(['success' => true, 'batches' => $list]);
        exit;
    }

    if ($action === 'batch_accounts') {
        $batch = imp_batch($pdo, (int)($data['batchId'] ?? 0), $officerId);
        $st = $pdo->prepare('SELECT * FROM contracts WHERE import_batch_id = ? ORDER BY client_name, id');
        $st->execute([(int)$batch['id']]);
        $accounts = array_map(fn($c) => imp_account_view($pdo, $c), $st->fetchAll());
        echo json_encode(['success' => true, 'batch' => ['batchId' => (int)$batch['id'], 'status' => $batch['status']], 'accounts' => $accounts]);
        exit;
    }

    if ($action === 'update_account') {
        $contractId = (int)($data['contractId'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $st->execute([$contractId]);
        $c = $st->fetch();
        if (!$c || trim((string)$c['officer_id']) !== $officerId || empty($c['import_batch_id'])) imp_fail('Imported account not found among your accounts.', 404);
        $batch = imp_batch($pdo, (int)$c['import_batch_id'], $officerId);
        if ($batch['status'] !== 'FOR_VERIFICATION') imp_fail('This batch is already confirmed and can no longer be edited here.', 409);

        $f = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        $name = trim(preg_replace('/\s+/', ' ', (string)($f['clientName'] ?? $c['client_name'])));
        $project = trim((string)($f['project'] ?? $c['project_name']));
        $phase = trim((string)($f['phase'] ?? $c['project_phase']));
        $blk = trim((string)($f['block'] ?? $c['block_no']));
        $lot = trim((string)($f['lot'] ?? $c['lot_no']));
        $balance = imp_num($f['balance'] ?? $c['total_contract_price']);
        $monthly = imp_num($f['monthly'] ?? $c['monthly_amortization']);
        $firstDue = imp_date($f['firstDue'] ?? ihc_add_months((string)$c['start_date'], 1));
        $email = strtolower(trim((string)($f['email'] ?? $c['email'])));
        $phone = imp_phone((string)($f['phone'] ?? $c['cellphone_number']));

        if ($name === '') imp_fail('Buyer name is required.');
        if ($balance === null || $balance <= 0) imp_fail('Balance must be more than zero.');
        if ($monthly === null || $monthly <= 0) imp_fail('Monthly amount must be more than zero.');
        if ($monthly > $balance + 0.009) $monthly = $balance;
        if (!$firstDue) imp_fail('First due date is not a valid date.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) imp_fail('Please enter a valid email address.');
        if ($phone === null) imp_fail('Cellphone number must be an 11-digit mobile number (09XXXXXXXXX).');
        $balance = round($balance, 2); $monthly = round($monthly, 2);
        $terms = max(1, (int)ceil(round($balance / $monthly, 6)));
        if ($terms > 600) imp_fail('Balance ÷ monthly is ' . $terms . ' months — check the amounts.');
        [$fy, $fm, $fd] = array_map('intval', explode('-', $firstDue));
        $startDate = imp_ymd($fy, $fm - 1, $fd);

        $pay = is_array($f['payment'] ?? null) ? $f['payment'] : null;
        if ($pay !== null) {
            $payAmount = imp_num($pay['amount'] ?? 0) ?? 0.0;
            if ($payAmount < 0) imp_fail('Collected amount cannot be negative.');
            if ($payAmount > $balance + 0.009) imp_fail('Collected amount is larger than the balance.');
            $payDate = $payAmount > 0 ? imp_date($pay['date'] ?? '') : null;
            if ($payAmount > 0 && !$payDate) imp_fail('Enter the date of the collected payment.');
            $payOr = mb_substr(trim((string)($pay['orNumber'] ?? '')), 0, 100);
            if ($payOr === '') $payOr = 'FOR OR';
        }

        $newAddress = imp_property_address($blk, $lot, $phase, $project);
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE contracts SET client_name = ?, project_name = ?, project_phase = ?, block_no = ?, lot_no = ?,
                        property_address = ?, total_contract_price = ?, opening_balance = ?, monthly_amortization = ?,
                        installment_terms = ?, start_date = ?, email = ?, cellphone_number = ?, needs_completion = ?
                 WHERE id = ?'
            )->execute([
                mb_substr($name, 0, 255),
                $project !== '' ? mb_substr($project, 0, 255) : null,
                $phase !== '' ? mb_substr($phase, 0, 120) : null,
                $blk !== '' ? mb_substr($blk, 0, 50) : null,
                $lot !== '' ? mb_substr($lot, 0, 50) : null,
                $newAddress !== '' ? $newAddress : null,
                $balance, $balance, $monthly, $terms, $startDate,
                $email, $phone, ($email === '' || $phone === '') ? 1 : 0,
                $contractId,
            ]);

            if ($newAddress !== (string)($c['property_address'] ?? '')) {
                imp_relink_unit($pdo, $batch, $contractId, (string)($c['property_address'] ?? ''), $newAddress,
                    ['project' => $project, 'phase' => $phase, 'block' => $blk, 'lot' => $lot]);
            }

            if ($pay !== null) {
                $existing = imp_imported_payments($pdo, $contractId);
                if ($payAmount <= 0.009) {
                    foreach ($existing as $p) $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$p['id']]);
                } elseif ($existing) {
                    $pdo->prepare('UPDATE payments SET amount = ?, date_collected = ?, or_number = ?, invoice_number = ? WHERE id = ?')
                        ->execute([round($payAmount, 2), $payDate, $payOr, $payOr !== 'FOR OR' ? $payOr : null, (int)$existing[0]['id']]);
                    for ($i = 1; $i < count($existing); $i++) $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$existing[$i]['id']]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO payments (contract_id, amount, payment_method, date_collected, or_number, remarks, posted_by,
                                               invoice_number, installment_kind, installment_no, receipt_requested)
                         VALUES (?,?,?,?,?,?,?,?,NULL,NULL,0)'
                    )->execute([
                        'CON-' . $contractId, round($payAmount, 2), 'Imported (Excel)', $payDate, $payOr,
                        'Imported from the collection Excel (' . $batch['cutoff_label'] . ' collection)' . ($payOr === 'FOR OR' ? ' — OR not yet issued' : ''),
                        $officerId, $payOr !== 'FOR OR' ? $payOr : null,
                    ]);
                }
            }

            imp_refresh_batch_totals($pdo, (int)$batch['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) imp_fail('Another unit already uses that address.', 409);
            throw $e;
        }

        $st = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $st->execute([$contractId]);
        echo json_encode(['success' => true, 'message' => 'Account updated.', 'account' => imp_account_view($pdo, $st->fetch())]);
        exit;
    }

    if ($action === 'undo_batch') {
        $batch = imp_batch($pdo, (int)($data['batchId'] ?? 0), $officerId);
        if ($batch['status'] !== 'FOR_VERIFICATION') imp_fail('Only batches still "For Verification" can be undone.', 409);
        $batchId = (int)$batch['id'];
        $ids = array_map('intval', $pdo->query('SELECT id FROM contracts WHERE import_batch_id = ' . $batchId)->fetchAll(PDO::FETCH_COLUMN));

        // Anything recorded in the system after the import would be lost.
        $blocking = imp_post_import_activity($pdo, $ids);
        if ($blocking) {
            imp_fail('Cannot undo: ' . implode('; ', $blocking) . ' was recorded in the system for these accounts after the import. Edit the affected accounts instead.', 409);
        }

        $created = json_decode((string)($batch['unit_ids_created'] ?? '[]'), true) ?: [];
        $links = json_decode((string)($batch['unit_links'] ?? '{}'), true) ?: [];
        $pdo->beginTransaction();
        try {
            $removedPayments = 0;
            foreach ($ids as $cid) {
                foreach (imp_imported_payments($pdo, $cid) as $p) {
                    $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$p['id']]);
                    $removedPayments++;
                }
            }
            if ($ids) {
                $in = implode(',', $ids);
                foreach ($links as $uid => $prev) {
                    $pdo->prepare("UPDATE property_units SET current_contract_id = ? WHERE id = ? AND current_contract_id IN ($in)")
                        ->execute([$prev !== null ? (int)$prev : null, (int)$uid]);
                }
                foreach ($created as $uid) {
                    $pdo->prepare("DELETE FROM property_units WHERE id = ? AND (current_contract_id IS NULL OR current_contract_id IN ($in))")
                        ->execute([(int)$uid]);
                }
                $pdo->exec('DELETE FROM contracts WHERE import_batch_id = ' . $batchId);
            }
            $pdo->prepare("UPDATE import_batches SET status = 'UNDONE', undone_at = NOW() WHERE id = ?")->execute([$batchId]);
            imp_audit($pdo, 'contracts.import_undone', $officerId, (string)$officer['full_name'],
                ['batchId' => $batchId, 'contracts' => count($ids), 'payments' => $removedPayments]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        echo json_encode(['success' => true, 'message' => 'Batch #' . $batchId . ' undone — ' . count($ids) . ' account(s) removed.']);
        exit;
    }

    if ($action === 'confirm_batch') {
        $batch = imp_batch($pdo, (int)($data['batchId'] ?? 0), $officerId);
        if ($batch['status'] !== 'FOR_VERIFICATION') imp_fail('This batch is not waiting for verification.', 409);
        $pdo->prepare("UPDATE import_batches SET status = 'CONFIRMED', confirmed_at = NOW() WHERE id = ?")->execute([(int)$batch['id']]);
        imp_audit($pdo, 'contracts.import_confirmed', $officerId, (string)$officer['full_name'],
            ['batchId' => (int)$batch['id'], 'contracts' => (int)$batch['contracts_count']]);
        echo json_encode(['success' => true, 'message' => 'Batch #' . $batch['id'] . ' confirmed. Its accounts are now final and reminders are enabled.']);
        exit;
    }

    if (!in_array($action, ['preview', 'commit'], true)) imp_fail('Unknown action.');

    $headers = is_array($data['headers'] ?? null) ? $data['headers'] : [];
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
    if (!$headers || !$rows) imp_fail('No rows found in the file.');
    if (count($rows) > IMP_MAX_ROWS) imp_fail('Too many rows (max ' . IMP_MAX_ROWS . ' per upload).');

    $overrides = is_array($data['overrides'] ?? null) ? $data['overrides'] : [];
    $result = imp_analyze($pdo, $officer, $headers, $rows, $overrides);

    if ($action === 'preview') {
        $summary = ['ok' => 0, 'warn' => 0, 'error' => 0];
        foreach ($result['rows'] as $r) $summary[$r['level']]++;
        // The raw row is only needed at commit time.
        $rowsOut = array_map(function ($r) { unset($r['raw']); return $r; }, $result['rows']);
        echo json_encode([
            'success' => true,
            'officer' => ['id' => $officerId, 'name' => $officer['full_name'], 'code' => $officer['officer_code']],
            'cutoff' => $result['cutoff'],
            'summary' => $summary,
            'otherOfficers' => $result['otherOfficers'],
            'rows' => $rowsOut,
        ]);
        exit;
    }

    // ---- commit ----
    $toImport = [];
    $problems = [];
    $skipped = []; // this clerk's rows that will NOT be imported, with the reason
    $alreadyIn = 0; // this clerk's rows that were imported earlier
    foreach ($result['rows'] as $r) {
        $ov = is_array($overrides[(string)$r['n']] ?? null) ? $overrides[(string)$r['n']] : [];
        $include = array_key_exists('include', $ov) ? (bool)$ov['include'] : $r['level'] !== 'error';
        // Rows already in the system are not "missing" — count them only.
        if ($r['existingContractId']) { $alreadyIn++; continue; }
        if (!$include || $r['level'] === 'error') {
            $skipped[] = [
                'n' => $r['n'],
                'legacyCode' => $r['legacyCode'],
                'clientName' => $r['clientName'],
                'propertyAddress' => $r['propertyAddress'],
                'status' => $r['status'],
                'balance' => $r['openingBalance'],
                'reason' => $r['level'] === 'error' ? implode(' ', $r['errors']) : 'Unchecked by you in the preview.',
            ];
        }
        if (!$include) continue;
        if ($r['level'] === 'error') { $problems[] = 'Row ' . $r['n'] . ': ' . implode(' ', $r['errors']); continue; }
        $email = strtolower(trim((string)($ov['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $problems[] = 'Row ' . $r['n'] . ': invalid email.'; continue; }
        $phone = imp_phone((string)($ov['phone'] ?? ''));
        if ($phone === null) { $problems[] = 'Row ' . $r['n'] . ': cellphone must be 09XXXXXXXXX.'; continue; }
        $r['email'] = $email;
        $r['phone'] = $phone;
        $toImport[] = $r;
    }
    if ($problems) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fix these rows first: ' . implode(' | ', array_slice($problems, 0, 8)) . (count($problems) > 8 ? ' …' : ''), 'problems' => $problems]);
        exit;
    }
    if (!$toImport) imp_fail('No rows selected for import.');
    if (($data['reconciled'] ?? false) !== true) {
        imp_fail('Check the totals against your Excel and tick "These totals match my Excel" before importing.');
    }

    $asOf = $result['cutoff']['asOf'];
    $now = date('Y-m-d H:i:s');
    $imported = 0; $paymentsAdded = 0; $incomplete = 0; $unitsCreated = 0;
    $totalBalance = 0.0; $totalCollected = 0.0;
    $createdUnitIds = [];   // units this batch inserted (deleted on undo)
    $unitLinks = [];        // existing unit id => its previous current_contract_id (restored on undo)
    $fileName = mb_substr(trim((string)($data['fileName'] ?? '')), 0, 255);

    $insContract = $pdo->prepare(
        'INSERT INTO contracts
            (client_name, email, cellphone_number, total_contract_price, downpayment, installment_terms, start_date,
             property_address, officer_id, dp_mode, dp_terms, bank_name, annual_interest_rate, discount_amount,
             project_name, project_phase, block_no, lot_no,
             is_legacy, needs_completion, legacy_code, buyer_code, company, branch, financing_type, receivable_status,
             collection_type, sales_officer, rs_date, pdc, account_status, account_remarks, monthly_amortization,
             opening_balance, opening_balance_as_of, legacy_data, imported_at, imported_by, import_batch_id)
         VALUES (?,?,?,?,0,?,?, ?,?,\'lump\',NULL,?,?,0, ?,?,?,?, 1,?,?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?,?,?)'
    );
    $findUnit = $pdo->prepare('SELECT id, current_contract_id FROM property_units WHERE display_label = ? LIMIT 1');
    $linkUnit = $pdo->prepare('UPDATE property_units SET current_contract_id = ? WHERE id = ?');
    $insUnit = $pdo->prepare(
        'INSERT INTO property_units (display_label, project, phase, building, unit_number, status, current_contract_id)
         VALUES (?,?,?,?,?,\'SOLD\',?)'
    );
    $insPayment = $pdo->prepare(
        'INSERT INTO payments (contract_id, amount, payment_method, date_collected, or_number, remarks, posted_by,
                               invoice_number, installment_kind, installment_no, receipt_requested)
         VALUES (?,?,?,?,?,?,?,?,NULL,NULL,0)'
    );

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO import_batches (officer_id, status, file_name, cutoff_label, as_of)
             VALUES (?, 'FOR_VERIFICATION', ?, ?, ?)"
        )->execute([$officerId, $fileName !== '' ? $fileName : null, $result['cutoff']['label'], $asOf]);
        $batchId = (int)$pdo->lastInsertId();

        foreach ($toImport as $r) {
            $needs = ($r['email'] === '' || $r['phone'] === '') ? 1 : 0;
            $insContract->execute([
                $r['clientName'], $r['email'], $r['phone'], $r['openingBalance'], $r['terms'], $r['startDate'],
                $r['propertyAddress'] !== '' ? $r['propertyAddress'] : null, $officerId,
                $r['bank'], $r['annualRate'],
                $r['project'] !== '' ? mb_substr($r['project'], 0, 255) : null,
                $r['phase'] !== '' ? mb_substr($r['phase'], 0, 120) : null,
                $r['block'] !== '' ? mb_substr($r['block'], 0, 50) : null,
                $r['lot'] !== '' ? mb_substr($r['lot'], 0, 50) : null,
                $needs, $r['legacyCode'], $r['buyerCode'] ? mb_substr($r['buyerCode'], 0, 30) : null,
                $r['company'] ? mb_substr($r['company'], 0, 20) : null,
                $r['branch'] ? mb_substr($r['branch'], 0, 60) : null,
                $r['financing'] ? mb_substr($r['financing'], 0, 60) : null,
                $r['receivableStatus'] ? mb_substr($r['receivableStatus'], 0, 60) : null,
                $r['collectionType'] ? mb_substr($r['collectionType'], 0, 60) : null,
                $r['salesOfficer'] ? mb_substr($r['salesOfficer'], 0, 100) : null,
                $r['rsDate'], $r['pdc'] ? mb_substr($r['pdc'], 0, 20) : null,
                $r['status'], $r['remarks'], $r['monthly'],
                $r['openingBalance'], $asOf,
                json_encode($r['raw'], JSON_UNESCAPED_UNICODE),
                $now, $officerId, $batchId,
            ]);
            $contractId = (int)$pdo->lastInsertId();
            $imported++;
            $totalBalance += (float)$r['openingBalance'];
            if ($needs) $incomplete++;

            // Register the unit (sold — it is an ongoing account) unless the
            // inventory already has it, in which case only link the contract.
            if ($r['propertyAddress'] !== '') {
                $label = mb_substr($r['propertyAddress'], 0, 255);
                $findUnit->execute([$label]);
                $unit = $findUnit->fetch();
                if ($unit) {
                    $uid = (int)$unit['id'];
                    if (!array_key_exists($uid, $unitLinks) && !in_array($uid, $createdUnitIds, true)) {
                        $unitLinks[$uid] = $unit['current_contract_id'] !== null ? (int)$unit['current_contract_id'] : null;
                    }
                    $linkUnit->execute([$contractId, $uid]);
                } else {
                    $insUnit->execute([
                        $label,
                        $r['project'] !== '' ? mb_substr($r['project'], 0, 255) : null,
                        $r['phase'] !== '' ? mb_substr($r['phase'], 0, 120) : null,
                        $r['block'] !== '' ? mb_substr($r['block'], 0, 255) : null,
                        $r['lot'] !== '' ? mb_substr($r['lot'], 0, 100) : null,
                        $contractId,
                    ]);
                    $unitsCreated++;
                    $createdUnitIds[] = (int)$pdo->lastInsertId();
                }
            }

            if ($r['payment']) {
                $p = $r['payment'];
                $insPayment->execute([
                    'CON-' . $contractId, $p['amount'], 'Imported (Excel)', $p['date'], $p['orNumber'],
                    'Imported from the collection Excel (' . $result['cutoff']['label'] . ' collection)'
                        . ($p['orNumber'] === 'FOR OR' ? ' — OR not yet issued' : ''),
                    $officerId,
                    $p['orNumber'] !== 'FOR OR' ? $p['orNumber'] : null,
                ]);
                $paymentsAdded++;
                $totalCollected += (float)$p['amount'];
            }
        }

        $pdo->prepare(
            'UPDATE import_batches SET contracts_count = ?, payments_count = ?, total_balance = ?, total_collected = ?,
                    unit_ids_created = ?, unit_links = ?, file_rows = ?, skipped_rows = ?, already_rows = ? WHERE id = ?'
        )->execute([
            $imported, $paymentsAdded, round($totalBalance, 2), round($totalCollected, 2),
            json_encode($createdUnitIds), json_encode((object)$unitLinks),
            count($result['rows']), json_encode($skipped, JSON_UNESCAPED_UNICODE), $alreadyIn, $batchId,
        ]);

        // Safety net — re-read what was just written and refuse the whole
        // import if any account is not this clerk's: wrong owner, or the
        // saved Excel row names a different Collection Officer.
        $check = $pdo->prepare('SELECT id, client_name, officer_id, legacy_data FROM contracts WHERE import_batch_id = ?');
        $check->execute([$batchId]);
        $wrong = [];
        foreach ($check->fetchAll() as $row) {
            $raw = json_decode((string)$row['legacy_data'], true) ?: [];
            $excelOfficer = strtoupper(trim((string)($raw['Collection Officer'] ?? '')));
            if ((string)$row['officer_id'] !== $officerId || ($excelOfficer !== '' && $excelOfficer !== strtoupper((string)$officer['officer_code']))) {
                $wrong[] = $row['client_name'] . ' (' . ($excelOfficer ?: '?') . ')';
            }
        }
        if ($wrong || count($toImport) !== $imported) {
            throw new RuntimeException('Safety check failed — ' . count($wrong) . ' account(s) did not belong to ' . $officer['officer_code']
                . ($wrong ? ': ' . implode(', ', array_slice($wrong, 0, 5)) : '') . '. Nothing was imported.');
        }

        try {
            $pdo->prepare(
                'INSERT INTO audit_logs (action, contract_id, from_status, to_status, actor_id, actor_name, details)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute(['contracts.imported', null, null, null, $officerId, (string)$officer['full_name'], json_encode([
                'batchId' => $batchId,
                'cutoff' => $result['cutoff']['label'],
                'imported' => $imported,
                'payments' => $paymentsAdded,
                'incomplete' => $incomplete,
            ])]);
        } catch (Throwable $e) { /* audit is best-effort */ }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'message' => $imported . ' account' . ($imported === 1 ? '' : 's') . ' imported as batch #' . $batchId . ' — review and confirm it below.',
        'batchId' => $batchId,
        'imported' => $imported,
        'payments' => $paymentsAdded,
        'incomplete' => $incomplete,
        'unitsCreated' => $unitsCreated,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
}
