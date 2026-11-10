<?php
declare(strict_types=1);
require_once __DIR__ . '/db-config.php';

// All date()/CURDATE-fallback math must agree with the Node cron (Asia/Manila).
date_default_timezone_set('Asia/Manila');

// Shared installment-schedule math — the clerk and client endpoints include
// this same file, so all three dashboards interpret `payments` identically.
require_once __DIR__ . '/installment-schedule.php';
require_once __DIR__ . '/soa-builder.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// This endpoint is read-only apart from explicit admin write actions:
// `reassign` moves a contract between clerks, and the `staff_*` actions
// manage clerk accounts. Payments are only ever written by post-payment.php.
// Admin-only authorization for the staff actions runs below against officers.
if ($_SERVER['REQUEST_METHOD'] !== 'GET'
    && !($_SERVER['REQUEST_METHOD'] === 'POST'
        && in_array((string)($_GET['action'] ?? ''), ['reassign', 'staff_create', 'staff_update', 'staff_delete'], true))) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = ihc_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Holding/reservation rows can pre-date their contract; the summary below LEFT
// JOINs prospect_clients, so make sure the table is there (self-heal, cheap).
require_once __DIR__ . '/prospect-clients.php';
try { prospect_ensure_tables($pdo); } catch (Throwable $e) { /* keep serving */ }

soa_ensure_schema($pdo);

// officers.officer_code — the clerk's code in the collection Excel (php/api_import.php).
require_once __DIR__ . '/legacy-import.php';
try { ihc_ensure_officer_code($pdo); } catch (Throwable $e) { /* keep serving */ }

try {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : 'overview';
    $rawInput = file_get_contents('php://input') ?: '';
    $jsonInput = json_decode($rawInput, true);
    if (!is_array($jsonInput)) $jsonInput = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
        $action = isset($jsonInput['action']) ? (string)$jsonInput['action'] : '';
    }
    $today = date('Y-m-d');

    // payments.contract_id stores prefixed strings ('CON-19', sometimes bare
    // '7' or 'IHC-14') — fold them onto integer contract ids in PHP instead of
    // SQL JOINs (mixed collations between notifications/payments and contracts
    // blow up at runtime; see AGENTS.md).
    $principalByPayment = [];
    try {
        foreach ($pdo->query('SELECT payment_id, SUM(principal_amount) AS principal_amount FROM payment_allocations GROUP BY payment_id') as $allocation) {
            $principalByPayment[(int)$allocation['payment_id']] = (float)$allocation['principal_amount'];
        }
    } catch (Throwable $e) {
        $principalByPayment = [];
    }
    $paidByContract = [];   // contractId => float
    $paidMonthByContract = []; // contractId => ['YYYY-MM' => float]
    $paysByContract = [];   // contractId => [ ['date','amount'] ] chronological
    foreach ($pdo->query('SELECT id, contract_id, amount, date_collected FROM payments ORDER BY date_collected, id') as $p) {
        if (!preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) continue;
        $key = (int)$m[1];
        $amt = (float)$p['amount'];
        $principalAmt = $principalByPayment[(int)$p['id']] ?? $amt;
        $paidByContract[$key] = ($paidByContract[$key] ?? 0.0) + $amt;
        $month = substr((string)$p['date_collected'], 0, 7);
        $paidMonthByContract[$key][$month] = ($paidMonthByContract[$key][$month] ?? 0.0) + $amt;
        $paysByContract[$key][] = [
            'date' => (string)$p['date_collected'],
            'amount' => $principalAmt,
            'principalAmount' => $principalAmt,
        ];
    }

    // contracts + assigned clerk (o.id = c.officer_id implicit cast matches the
    // pattern already used by php/api_client.php)
    $contractRows = $pdo->query(
        'SELECT c.*, o.full_name AS clerk_name FROM contracts c LEFT JOIN officers o ON o.id = c.officer_id ORDER BY c.id'
    )->fetchAll();
    $existing = [];
    foreach ($contractRows as $c) $existing[(int)$c['id']] = true;
    // Drop orphan payments left behind by deleted contracts (e.g. CON-7).
    $livePaid = [];
    foreach ($paidByContract as $k => $v) if (isset($existing[$k])) $livePaid[$k] = $v;

    // notifications: latest row per contract + full history for the log view
    $notifRows = $pdo->query(
        'SELECT id, contract_id, client_email, channel, subject, status, sent_at FROM notifications_logs ORDER BY sent_at ASC, id ASC'
    )->fetchAll();
    $latestNotif = [];
    foreach ($notifRows as $n) {
        if (preg_match('/(\d+)\s*$/', (string)$n['contract_id'], $m)) $latestNotif[(int)$m[1]] = $n;
    }
    $notifStatusOf = function (?array $n): string {
        if (!$n) return 'pending';
        if ($n['status'] === 'sent') return 'sent';
        if ($n['status'] === 'failed') return 'failed';
        return 'pending';
    };

    // Status/type/due-date now all come from ihc_schedule() (see the overview
    // loop below) — the old start_date heuristic is gone so the admin ledger
    // and the clerk ledger describe the same next payment.

    // ---- Audit log ----------------------------------------------------
    if ($action === 'audit_logs') {
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));
        try {
            $st = $pdo->prepare(
                'SELECT id, action, contract_id, from_status, to_status, actor_id, actor_name, details, created_at
                 FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit
            );
            $st->execute();
            $logs = $st->fetchAll();
        } catch (Throwable $e) {
            $logs = [];
        }
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }

    // ---- Team: every clerk and the clients they enrolled -------------
    if ($action === 'team') {
        $officerRows = $pdo->query(
            "SELECT id, full_name, role, email, officer_code FROM officers ORDER BY (role='admin') ASC, full_name ASC"
        )->fetchAll();

        // Group the contract rows (already fetched above with clerk_name) by
        // their owning clerk. officer_id is VARCHAR and can be non-numeric
        // (e.g. 'DEMO-001'), so group on the raw string rather than casting.
        $byClerk = [];
        foreach ($contractRows as $c) {
            $byClerk[trim((string)($c['officer_id'] ?? ''))][] = $c;
        }

        $describe = function (array $c) use ($paysByContract, $today): array {
            $cid = (int)$c['id'];
            $schedule = ihc_schedule($c, $paysByContract[$cid] ?? []);
            $next = $schedule['next'];
            $total = (float)($c['total_contract_price'] ?? 0);
            $paid = (float)$schedule['paid'];
            $balance = max(0.0, $total - $paid);
            $status = 'current';
            if (!$next) $status = 'paid';
            elseif ((string)$next['dueDate'] < $today) $status = 'overdue';
            elseif ((string)$next['dueDate'] <= date('Y-m-d', strtotime('+3 days'))) $status = 'due-soon';
            return [
                'contractId'     => $cid,
                'accountCode'    => 'CON-' . $cid,
                'name'           => (string)$c['client_name'],
                'email'          => (string)$c['email'],
                'phone'          => (string)$c['cellphone_number'],
                'propertyAddress'=> $c['property_address'] ?? null,
                'totalPrice'     => $total,
                'paid'           => $paid,
                'balance'        => $balance,
                'terms'          => max(1, (int)($c['installment_terms'] ?? 1)),
                'nextDueDate'    => $next ? (string)$next['dueDate'] : null,
                'nextAmount'     => $next ? (float)$next['amount'] : 0.0,
                'nextType'       => $next ? (string)$next['kind'] : 'settled',
                'status'         => $status,
                'officerId'      => trim((string)($c['officer_id'] ?? '')),
            ];
        };

        $clerks = [];
        $matched = [];
        foreach ($officerRows as $o) {
            $oid = trim((string)$o['id']);
            $matched[$oid] = true;
            $rows = $byClerk[$oid] ?? [];
            $clients = array_map($describe, $rows);

            $portfolio = 0.0; $collected = 0.0; $overdue = 0;
            foreach ($clients as $cl) {
                $portfolio += $cl['totalPrice'];
                $collected += $cl['paid'];
                if ($cl['status'] === 'overdue') $overdue++;
            }
            $clerks[] = [
                'officerId'      => $oid,
                'name'           => (string)$o['full_name'],
                'email'          => (string)$o['email'],
                'officerCode'    => (string)($o['officer_code'] ?? ''),
                'role'           => (string)$o['role'],
                'clientCount'    => count($clients),
                'portfolio'      => round($portfolio, 2),
                'collected'      => round($collected, 2),
                'outstanding'    => round(max(0.0, $portfolio - $collected), 2),
                'collectionRate' => $portfolio > 0 ? round(($collected / $portfolio) * 100, 1) : 0.0,
                'overdueCount'   => $overdue,
                'clients'        => $clients,
            ];
        }

        // Contracts whose officer_id is empty or points at a deleted officer.
        // Surfaced explicitly so a client can never silently vanish from the
        // admin's view.
        $unassignedRows = [];
        foreach ($byClerk as $oid => $rows) {
            if ($oid === '' || !isset($matched[$oid])) $unassignedRows = array_merge($unassignedRows, $rows);
        }
        $unassigned = array_map($describe, $unassignedRows);

        echo json_encode([
            'success'    => true,
            'clerks'     => $clerks,
            'unassigned' => $unassigned,
        ]);
        exit;
    }

    // ---- Staff accounts: add / update / delete clerks (admin only) ----
    if (in_array($action, ['staff_create', 'staff_update', 'staff_delete'], true)) {
        $actorId   = trim((string)($jsonInput['actorId'] ?? ''));
        $actorName = trim((string)($jsonInput['actorName'] ?? ''));

        $actorStmt = $pdo->prepare('SELECT id, full_name, role FROM officers WHERE id = ? LIMIT 1');
        $actorStmt->execute([$actorId]);
        $actor = $actorStmt->fetch();
        if (!$actor || strtolower(trim((string)$actor['role'])) !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only an administrator can manage staff accounts.']);
            exit;
        }
        if ($actorName === '') $actorName = (string)$actor['full_name'];

        $staffId  = (int)($jsonInput['staffId'] ?? 0);
        $fullName = trim((string)($jsonInput['fullName'] ?? ''));
        $email    = strtolower(trim((string)($jsonInput['email'] ?? '')));
        $password = (string)($jsonInput['password'] ?? '');
        // Code used in the collection Excel's "Collection Officer" column (e.g. AJ).
        $officerCode = strtoupper(trim((string)($jsonInput['officerCode'] ?? '')));

        $findStaff = function (int $id) use ($pdo): ?array {
            $st = $pdo->prepare('SELECT id, full_name, role, email FROM officers WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch();
            return $row ?: null;
        };

        $logStaff = function (string $logAction, ?int $targetId, string $detail) use ($pdo, $actorId, $actorName): void {
            $pdo->prepare(
                'INSERT INTO audit_logs (action, contract_id, from_status, to_status, actor_id, actor_name, details)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$logAction, null, null, null, $actorId, $actorName, $detail]);
        };

        if ($action === 'staff_delete') {
            if ($staffId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'staffId is required.']);
                exit;
            }
            $target = $findStaff($staffId);
            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Clerk account not found.']);
                exit;
            }
            if (strtolower(trim((string)$target['role'])) !== 'clerk') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only clerk accounts can be deleted.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM officers WHERE id = ?')->execute([$staffId]);
                $logStaff('staff.deleted', $staffId, json_encode([
                    'name'  => (string)$target['full_name'],
                    'email' => (string)$target['email'],
                ]));
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            echo json_encode([
                'success' => true,
                'message' => (string)$target['full_name'] . '\'s account was deleted and can no longer sign in.',
            ]);
            exit;
        }

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name is required (max 100 characters).']);
            exit;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
            exit;
        }
        if ($action === 'staff_create' && mb_strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            exit;
        }
        if ($action === 'staff_update' && $password !== '' && mb_strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The new password must be at least 8 characters.']);
            exit;
        }

        $dupe = $pdo->prepare('SELECT id FROM officers WHERE LOWER(email) = ? AND id <> ? LIMIT 1');
        $dupe->execute([$email, $staffId]);
        if ($dupe->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Another staff account already uses that email address.']);
            exit;
        }
        if ($officerCode !== '' && !preg_match('/^[A-Z0-9][A-Z0-9 .\-]{0,19}$/', $officerCode)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Officer code may use letters, numbers, spaces, dots and dashes (max 20).']);
            exit;
        }
        if ($officerCode !== '') {
            $dupeCode = $pdo->prepare('SELECT full_name FROM officers WHERE UPPER(officer_code) = ? AND id <> ? LIMIT 1');
            $dupeCode->execute([$officerCode, $staffId]);
            if ($other = $dupeCode->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Officer code ' . $officerCode . ' is already used by ' . $other['full_name'] . '.']);
                exit;
            }
        }
        $officerCodeValue = $officerCode !== '' ? $officerCode : null;

        if ($action === 'staff_create') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO officers (full_name, role, email, password_hash, must_change_password, officer_code)
                     VALUES (?, ?, ?, ?, 0, ?)'
                )->execute([$fullName, 'clerk', $email, password_hash($password, PASSWORD_DEFAULT), $officerCodeValue]);
                $newId = (int)$pdo->lastInsertId();
                $logStaff('staff.created', $newId, json_encode([
                    'name'  => $fullName,
                    'email' => $email,
                    'role'  => 'clerk',
                ]));
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Clerk account created for ' . $fullName . '.',
                'staffId' => $newId,
            ]);
            exit;
        }

        // staff_update
        if ($staffId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'staffId is required.']);
            exit;
        }
        $target = $findStaff($staffId);
        if (!$target) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Clerk account not found.']);
            exit;
        }
        if (strtolower(trim((string)$target['role'])) !== 'clerk') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only clerk accounts can be edited here.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            if ($password !== '') {
                $pdo->prepare(
                    'UPDATE officers SET full_name = ?, email = ?, officer_code = ?, password_hash = ?, must_change_password = 0 WHERE id = ?'
                )->execute([$fullName, $email, $officerCodeValue, password_hash($password, PASSWORD_DEFAULT), $staffId]);
            } else {
                $pdo->prepare('UPDATE officers SET full_name = ?, email = ?, officer_code = ? WHERE id = ?')
                    ->execute([$fullName, $email, $officerCodeValue, $staffId]);
            }
            $logStaff('staff.updated', $staffId, json_encode([
                'name'          => $fullName,
                'email'         => $email,
                'officerCode'   => $officerCodeValue,
                'passwordReset' => $password !== '',
            ]));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Clerk account updated for ' . $fullName . '.',
        ]);
        exit;
    }

    // ---- Reassign a contract to another clerk ------------------------
    if ($action === 'reassign') {
        $contractId = (int)($jsonInput['contractId'] ?? 0);
        $toOfficer = trim((string)($jsonInput['officerId'] ?? ''));
        $actorId   = trim((string)($jsonInput['actorId'] ?? ''));
        $actorName = trim((string)($jsonInput['actorName'] ?? ''));

        if ($contractId <= 0 || $toOfficer === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'contractId and officerId are required']);
            exit;
        }
        $target = $pdo->prepare('SELECT id, full_name FROM officers WHERE id = ? LIMIT 1');
        $target->execute([$toOfficer]);
        $targetOfficer = $target->fetch();
        if (!$targetOfficer) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'That clerk does not exist']);
            exit;
        }
        $cur = $pdo->prepare('SELECT id, client_name, officer_id FROM contracts WHERE id = ? LIMIT 1');
        $cur->execute([$contractId]);
        $contract = $cur->fetch();
        if (!$contract) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Contract not found']);
            exit;
        }
        $fromOfficer = trim((string)($contract['officer_id'] ?? ''));

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE contracts SET officer_id = ? WHERE id = ?');
            $upd->execute([$toOfficer, $contractId]);
            $pdo->prepare(
                'INSERT INTO audit_logs (action, contract_id, from_status, to_status, actor_id, actor_name, details)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                'contract.reassigned',
                $contractId,
                $fromOfficer !== '' ? $fromOfficer : null,
                $toOfficer,
                $actorId !== '' ? $actorId : null,
                $actorName !== '' ? $actorName : null,
                json_encode([
                    'client'  => (string)$contract['client_name'],
                    'from'    => $fromOfficer,
                    'to'      => (string)$targetOfficer['full_name'],
                ]),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        echo json_encode([
            'success' => true,
            'message' => (string)$contract['client_name'] . ' is now assigned to ' . (string)$targetOfficer['full_name'] . '.',
        ]);
        exit;
    }

    if ($action === 'schedule') {
        // ---- Installment schedule for one contract --------------------
        $raw = isset($_GET['contractId']) ? (string)$_GET['contractId'] : '';
        if (!preg_match('/(\d+)/', $raw, $m)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'contractId is required']);
            exit;
        }
        $cid = (int)$m[1];
        $contract = null;
        foreach ($contractRows as $c) if ((int)$c['id'] === $cid) { $contract = $c; break; }
        if (!$contract) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Contract not found']);
            exit;
        }

        // Shared schedule + chronological payment allocation (same code the
        // clerk and client dashboards run), then relabel it into the shape the
        // admin schedule table has always rendered.
        $built = ihc_schedule($contract, $paysByContract[$cid] ?? [], $today);

        $schedule = [];
        foreach ($built['installments'] as $inst) {
            $schedule[] = [
                // Admin numbering has always been 1-based across the whole
                // schedule (Downpayment = 1, amortizations = 2..n+1).
                'no'      => $inst['kind'] === 'downpayment' ? 1 : ((int)$inst['no']) + 1,
                'type'    => $inst['kind'] === 'downpayment' ? 'Downpayment' : 'Monthly Amortization',
                'dueDate' => $inst['dueDate'],
                'amount'  => $inst['amount'],
                'status'  => $inst['status'],
            ];
        }

        echo json_encode([
            'success' => true,
            'contract' => ['id' => 'IHC-' . $cid, 'client' => $contract['client_name']],
            'notification' => $notifStatusOf($latestNotif[$cid] ?? null),
            'schedule' => $schedule,
        ]);
        exit;
    }

    // ---- Dues & performance: who is due this week / month -------------
    // GET ?action=dues&period=week|month|custom[&from=&to=][&officerId=][&includeOverdue=1]
    // Rows are the UNPAID installments falling in the period (straight from
    // ihc_schedule(), so a payment posted anywhere makes its row disappear),
    // optionally plus older unpaid ones. Each row carries the client's payment
    // performance; `clerks` summarises the period per collection officer.
    if ($action === 'dues') {
        $period = (string)($_GET['period'] ?? 'week');
        $todayD = new DateTimeImmutable($today);
        if ($period === 'month') {
            $from = $todayD->format('Y-m-01');
            $to = $todayD->format('Y-m-t');
        } elseif ($period === 'custom') {
            $from = (string)($_GET['from'] ?? '');
            $to = (string)($_GET['to'] ?? '');
            $okDate = fn($d) => ($x = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
            if (!$okDate($from) || !$okDate($to) || $from > $to) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Choose a valid From and To date.']);
                exit;
            }
        } else {
            $period = 'week'; // Monday – Sunday of the current week
            $monday = $todayD->modify('-' . ((int)$todayD->format('N') - 1) . ' days');
            $from = $monday->format('Y-m-d');
            $to = $monday->modify('+6 days')->format('Y-m-d');
        }
        $officerFilter = trim((string)($_GET['officerId'] ?? ''));
        $includeOverdue = (string)($_GET['includeOverdue'] ?? '0') === '1';

        $rows = [];
        $clerks = [];
        $totals = ['dueCount' => 0, 'dueAmount' => 0.0, 'paidCount' => 0, 'paidAmount' => 0.0,
                   'unpaidCount' => 0, 'unpaidAmount' => 0.0, 'carriedCount' => 0, 'carriedAmount' => 0.0];

        foreach ($contractRows as $c) {
            $oid = trim((string)($c['officer_id'] ?? ''));
            if ($officerFilter !== '' && $oid !== $officerFilter) continue;
            $cid = (int)$c['id'];
            $built = ihc_schedule($c, $paysByContract[$cid] ?? [], $today);

            // Client performance from the installments already due.
            $onTime = 0; $late = 0; $missed = 0;
            foreach ($built['installments'] as $inst) {
                if ($inst['amount'] <= 0.009 || $inst['dueDate'] > $today) continue;
                if ($inst['paid']) {
                    if ($inst['payDate'] !== null && $inst['payDate'] <= $inst['dueDate']) $onTime++; else $late++;
                } elseif ($inst['dueDate'] < $today) {
                    $missed++;
                }
            }
            $considered = $onTime + $late + $missed;
            $rate = $considered > 0 ? round($onTime / $considered * 100) : null;
            if ($considered === 0) $label = 'New';
            elseif ($missed >= 2 || $rate < 50) $label = 'Poor';
            elseif ($missed === 0 && $rate >= 90) $label = 'Good';
            else $label = 'Fair';
            $perf = ['label' => $label, 'onTimeRate' => $rate, 'onTime' => $onTime, 'late' => $late, 'missed' => $missed];

            $clerkKey = $oid !== '' ? $oid : '—';
            if (!isset($clerks[$clerkKey])) {
                $clerks[$clerkKey] = ['officerId' => $oid, 'name' => (string)($c['clerk_name'] ?? '') ?: 'Unassigned',
                    'dueCount' => 0, 'dueAmount' => 0.0, 'paidCount' => 0, 'paidAmount' => 0.0,
                    'unpaidCount' => 0, 'unpaidAmount' => 0.0, 'carriedCount' => 0, 'carriedAmount' => 0.0];
            }
            $k = &$clerks[$clerkKey];

            foreach ($built['installments'] as $inst) {
                if ($inst['amount'] <= 0.009) continue;
                $due = (string)$inst['dueDate'];
                $inPeriod = $due >= $from && $due <= $to;
                $remaining = round(max(0.0, $inst['amount'] - $inst['paidAmount']), 2);

                if ($inPeriod) {
                    $k['dueCount']++; $k['dueAmount'] += $inst['amount'];
                    $totals['dueCount']++; $totals['dueAmount'] += $inst['amount'];
                    if ($inst['paid']) {
                        $k['paidCount']++; $k['paidAmount'] += $inst['amount'];
                        $totals['paidCount']++; $totals['paidAmount'] += $inst['amount'];
                    } else {
                        // a partial payment still counts toward collected money
                        $k['paidAmount'] += $inst['paidAmount']; $totals['paidAmount'] += $inst['paidAmount'];
                        $k['unpaidCount']++; $k['unpaidAmount'] += $remaining;
                        $totals['unpaidCount']++; $totals['unpaidAmount'] += $remaining;
                    }
                } elseif (!$inst['paid'] && $due < $from) {
                    $k['carriedCount']++; $k['carriedAmount'] += $remaining;
                    $totals['carriedCount']++; $totals['carriedAmount'] += $remaining;
                }

                $show = !$inst['paid'] && ($inPeriod || ($includeOverdue && $due < $from));
                if (!$show) continue;
                $daysOverdue = $due < $today ? (int)((strtotime($today) - strtotime($due)) / 86400) : 0;
                $rows[] = [
                    'contractId' => $cid,
                    'accountCode' => 'CON-' . $cid,
                    'legacyCode' => $c['legacy_code'] ?? null,
                    'client' => (string)$c['client_name'],
                    'phone' => (string)$c['cellphone_number'],
                    'email' => (string)$c['email'],
                    'property' => (string)($c['property_address'] ?? ''),
                    'officerId' => $oid,
                    'clerk' => (string)($c['clerk_name'] ?? '') ?: 'Unassigned',
                    'installment' => $inst['kind'] === 'downpayment' ? 'Downpayment' : 'Installment #' . $inst['no'],
                    'dueDate' => $due,
                    'amountDue' => $remaining,
                    'fullAmount' => round($inst['amount'], 2),
                    'partial' => $inst['paidAmount'] > 0.009,
                    'status' => $due < $today ? 'overdue' : ($due === $today ? 'due-today' : 'upcoming'),
                    'daysOverdue' => $daysOverdue,
                    'carried' => !$inPeriod,
                    'performance' => $perf,
                ];
            }
            unset($k);
        }

        usort($rows, fn($a, $b) => [$a['dueDate'], $a['client']] <=> [$b['dueDate'], $b['client']]);
        $clerkList = array_values($clerks);
        foreach ($clerkList as &$cl) {
            foreach (['dueAmount', 'paidAmount', 'unpaidAmount', 'carriedAmount'] as $f) $cl[$f] = round($cl[$f], 2);
            $cl['collectionRate'] = $cl['dueAmount'] > 0 ? round(min(100, $cl['paidAmount'] / $cl['dueAmount'] * 100), 1) : null;
        }
        unset($cl);
        usort($clerkList, fn($a, $b) => strcmp($a['name'], $b['name']));
        foreach (['dueAmount', 'paidAmount', 'unpaidAmount', 'carriedAmount'] as $f) $totals[$f] = round($totals[$f], 2);
        $totals['collectionRate'] = $totals['dueAmount'] > 0 ? round(min(100, $totals['paidAmount'] / $totals['dueAmount'] * 100), 1) : null;

        echo json_encode([
            'success' => true,
            'today' => $today,
            'period' => ['type' => $period, 'from' => $from, 'to' => $to],
            'totals' => $totals,
            'clerks' => $clerkList,
            'rows' => $rows,
        ]);
        exit;
    }

    // ---- Portfolio report: aging + monthly collections + collection log ----
    // GET ?action=portfolio_report[&officerId=][&from=Y-m-d&to=Y-m-d]
    // Clerk Reports page (officerId = the clerk); admin may omit it for all.
    if ($action === 'portfolio_report') {
        $officerFilter = trim((string)($_GET['officerId'] ?? ''));
        $okDate = fn($d) => ($x = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $todayD = new DateTimeImmutable($today);
        $from = (string)($_GET['from'] ?? $todayD->format('Y-m-01'));
        $to = (string)($_GET['to'] ?? $todayD->format('Y-m-t'));
        if (!$okDate($from) || !$okDate($to) || $from > $to) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Choose a valid From and To date.']);
            exit;
        }

        $buckets = [
            'current' => ['label' => 'Current (not yet due)', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            '1-30'    => ['label' => '1–30 days', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            '31-60'   => ['label' => '31–60 days', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            '61-90'   => ['label' => '61–90 days', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            '91-120'  => ['label' => '91–120 days', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            '120+'    => ['label' => 'Over 120 days', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
            'settled' => ['label' => 'Fully paid', 'count' => 0, 'amount' => 0.0, 'balance' => 0.0],
        ];
        $agingRows = [];
        $mine = [];
        $kpi = ['accounts' => 0, 'outstanding' => 0.0, 'overdueAccounts' => 0, 'overdueAmount' => 0.0];

        // Six months back through the current month.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $todayD->modify('first day of this month')->modify("-$i months");
            $months[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'due' => 0.0, 'collected' => 0.0];
        }

        foreach ($contractRows as $c) {
            $oid = trim((string)($c['officer_id'] ?? ''));
            if ($officerFilter !== '' && $oid !== $officerFilter) continue;
            $cid = (int)$c['id'];
            $mine[$cid] = $c;
            $built = ihc_schedule($c, $paysByContract[$cid] ?? [], $today);
            $kpi['accounts']++;
            $kpi['outstanding'] += $built['outstanding'];

            $oldest = null; $overdueAmt = 0.0; $overdueCount = 0;
            foreach ($built['installments'] as $inst) {
                if ($inst['amount'] <= 0.009) continue;
                $ym = substr((string)$inst['dueDate'], 0, 7);
                if (isset($months[$ym])) $months[$ym]['due'] += $inst['amount'];
                if (!$inst['paid'] && $inst['dueDate'] < $today) {
                    $overdueAmt += $inst['amount'] - $inst['paidAmount'];
                    $overdueCount++;
                    if ($oldest === null) $oldest = $inst['dueDate'];
                }
            }
            if ($built['settled']) $key = 'settled';
            elseif ($oldest === null) $key = 'current';
            else {
                $days = (int)((strtotime($today) - strtotime($oldest)) / 86400);
                $key = $days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : ($days <= 120 ? '91-120' : '120+')));
                $kpi['overdueAccounts']++;
                $kpi['overdueAmount'] += $overdueAmt;
                $agingRows[] = [
                    'contractId' => $cid,
                    'accountCode' => 'CON-' . $cid,
                    'legacyCode' => $c['legacy_code'] ?? null,
                    'client' => (string)$c['client_name'],
                    'phone' => (string)$c['cellphone_number'],
                    'property' => (string)($c['property_address'] ?? ''),
                    'bucket' => $key,
                    'oldestDue' => $oldest,
                    'daysOverdue' => $days,
                    'installmentsOverdue' => $overdueCount,
                    'overdueAmount' => round($overdueAmt, 2),
                    'balance' => round($built['outstanding'], 2),
                ];
            }
            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += $overdueAmt;
            $buckets[$key]['balance'] += $built['outstanding'];
        }
        usort($agingRows, fn($a, $b) => $b['daysOverdue'] <=> $a['daysOverdue']);

        // Cash actually received (raw payment amounts), per month and in range.
        $collections = [];
        $rangeTotal = 0.0;
        $byMethod = [];
        foreach ($pdo->query('SELECT id, contract_id, amount, payment_method, date_collected, or_number, remarks FROM payments ORDER BY date_collected DESC, id DESC') as $p) {
            if (!preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) continue;
            $cid = (int)$m[1];
            if (!isset($mine[$cid])) continue;
            $d = (string)$p['date_collected'];
            $amt = (float)$p['amount'];
            $ym = substr($d, 0, 7);
            if (isset($months[$ym])) $months[$ym]['collected'] += $amt;
            if ($d < $from || $d > $to) continue;
            $rangeTotal += $amt;
            $method = (string)$p['payment_method'];
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amt;
            $collections[] = [
                'paymentId' => (int)$p['id'],
                'date' => $d,
                'contractId' => $cid,
                'accountCode' => 'CON-' . $cid,
                'client' => (string)$mine[$cid]['client_name'],
                'amount' => round($amt, 2),
                'method' => $method,
                'orNumber' => (string)$p['or_number'],
                'remarks' => (string)($p['remarks'] ?? ''),
            ];
        }

        $monthList = array_values(array_map(function ($m) {
            $m['due'] = round($m['due'], 2);
            $m['collected'] = round($m['collected'], 2);
            $m['rate'] = $m['due'] > 0 ? round(min(100, $m['collected'] / $m['due'] * 100), 1) : null;
            return $m;
        }, $months));
        foreach ($buckets as &$b) { $b['amount'] = round($b['amount'], 2); $b['balance'] = round($b['balance'], 2); }
        unset($b);
        arsort($byMethod);

        echo json_encode([
            'success' => true,
            'today' => $today,
            'kpi' => [
                'accounts' => $kpi['accounts'],
                'outstanding' => round($kpi['outstanding'], 2),
                'overdueAccounts' => $kpi['overdueAccounts'],
                'overdueAmount' => round($kpi['overdueAmount'], 2),
                'collectedThisMonth' => round($months[$todayD->format('Y-m')]['collected'] ?? 0, 2),
            ],
            'aging' => ['buckets' => $buckets, 'rows' => $agingRows],
            'months' => $monthList,
            'collections' => [
                'from' => $from, 'to' => $to,
                'total' => round($rangeTotal, 2),
                'count' => count($collections),
                'byMethod' => array_map(fn($k, $v) => ['method' => $k, 'amount' => round($v, 2)], array_keys($byMethod), array_values($byMethod)),
                'rows' => $collections,
            ],
        ]);
        exit;
    }

    if ($action !== 'overview') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }

    // ---- Overview: ledger rows, register, notifications, KPIs, cash flow ----
    $ledger = [];
    $register = [];
    $expected = 0.0;
    $dueSoonCount = 0;
    $emailToName = [];
    $idToName = [];

    foreach ($contractRows as $c) {
        $id = (int)$c['id'];
        $tcp = (float)$c['total_contract_price'];
        $discount = min($tcp, max(0.0, (float)($c['discount_amount'] ?? 0)));
        $netTcp = max(0.0, $tcp - $discount);
        $dp = (float)$c['downpayment'];
        $start = (string)$c['start_date'];

        // Same schedule + payment allocation the clerk and client dashboards
        // use: the ledger now describes the contract's real next payment, so
        // an admin and a clerk looking at one contract always see the same
        // type, amount, due date and status.
        $built = ihc_schedule($c, $paysByContract[$id] ?? [], $today);
        $next = $built['next'];
        $paid = $built['paid'];
        $outstanding = $built['outstanding'];
        $status = $next === null ? 'paid' : ihc_due_status($next['dueDate'], $today);
        if ($status === 'due-soon') $dueSoonCount++;

        $paymentType = $next === null
            ? 'Settled in Full'
            : ($next['kind'] === 'downpayment' ? 'Downpayment' : 'Installment Payment #' . $next['no']);
        $amountDue = $next === null ? 0.0 : $next['amount'];
        $dueDate = $next === null ? $start : $next['dueDate'];
        $clerk = $c['clerk_name'] !== null && $c['clerk_name'] !== '' ? (string)$c['clerk_name'] : 'Unassigned';
        $property = $c['property_address'] !== null && $c['property_address'] !== '' ? (string)$c['property_address'] : '—';
        $notif = $notifStatusOf($latestNotif[$id] ?? null);

        $ledger[] = [
            'id' => 'IHC-' . $id,
            'contractId' => $id,
            'client' => (string)$c['client_name'],
            'property' => $property,
            // Contact details so the admin can send the same payment reminder
            // the clerk sends — the Node route needs a recipient to deliver to.
            'email' => $c['email'] !== null ? (string)$c['email'] : '',
            'phone' => $c['cellphone_number'] !== null ? (string)$c['cellphone_number'] : '',
            'paymentType' => $paymentType,
            'amount' => $amountDue,
            'dueDate' => $dueDate,
            'status' => $status,
            'notification' => $notif,
            'clerk' => $clerk,
            'paid' => $paid,
            'tcp' => $tcp,
            'discount' => $discount,
            'netTcp' => $netTcp,
            // Whole-contract balance (the SOA "TOTAL DUE"), which is distinct
            // from 'amount' = what is due on the next installment.
            'outstanding' => $outstanding,
        ];
        $register[] = [
            'id' => 'IHC-' . $id,
            'contractId' => $id,
            'client' => (string)$c['client_name'],
            'property' => $property,
            'tcp' => $tcp,
            'discount' => $discount,
            'netTcp' => $netTcp,
            'downpayment' => $dp,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'terms' => (int)$c['installment_terms'],
            'status' => $status,
            'clerk' => $clerk,
        ];

        $expected += $netTcp;
        $emailToName[strtolower((string)$c['email'])] = (string)$c['client_name'];
        $idToName[$id] = (string)$c['client_name'];
    }

    // Monthly collection totals across live contracts (this + previous month)
    $monthSumLive = function (string $month) use ($paidMonthByContract, $existing): float {
        $s = 0.0;
        foreach ($paidMonthByContract as $k => $months) {
            if (isset($existing[$k])) $s += $months[$month] ?? 0.0;
        }
        return $s;
    };
    $collectedMonth = round($monthSumLive(date('Y-m')), 2);
    $prevMonth = round($monthSumLive(date('Y-m', strtotime('-1 month'))), 2);
    $collectedTotal = round(array_sum($livePaid), 2);
    $efficiency = $expected > 0 ? round($collectedTotal / $expected * 100, 1) : 0;

    // Cash flow: last 6 months, actual = live payments per month
    $labels = [];
    $actual = [];
    $monthStart = strtotime(date('Y-m-01'));
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i month", $monthStart));
        $labels[] = date('M', strtotime($m . '-01'));
        $actual[] = round($monthSumLive($m), 2);
    }

    // Notifications log view rows (newest first, capped)
    $notifications = [];
    foreach (array_reverse($notifRows) as $n) {
        if (count($notifications) >= 100) break;
        $cidDisplay = (string)$n['contract_id'];
        if (preg_match('/(\d+)\s*$/', $cidDisplay, $m) && isset($existing[(int)$m[1]])) {
            $cidDisplay = 'IHC-' . $m[1];
        }
        $email = strtolower((string)$n['client_email']);
        $channel = (string)$n['channel'];
        $recipient = (string)$n['client_email'];
        // SMS rows store the phone number in client_email (there is no phone
        // column), so resolve their client name through the contract id.
        $displayName = $emailToName[$email] ?? null;
        if ($displayName === null && preg_match('/(\d+)\s*$/', $cidDisplay, $mNum) && isset($idToName[(int)$mNum[1]])) {
            $displayName = $idToName[(int)$mNum[1]];
        }
        $notifications[] = [
            'time' => date('M j, H:i', strtotime((string)$n['sent_at'])),
            'client' => $displayName ?? $recipient,
            'contractId' => $cidDisplay,
            'channel' => $channel === 'ack_email' ? 'Acknowledgement' : ($channel === 'sms' ? 'SMS' : 'Email'),
            'recipient' => $recipient,
            'subject' => (string)$n['subject'],
            'status' => $n['status'] === '' ? 'pending' : (string)$n['status'],
        ];
    }

    // Holding / Reservation summary (§12) — best-effort if tables missing
    $holdingSummary = ['activeHolds'=>0,'expiringHolds'=>0,'reservedUnits'=>0,'pendingPayments'=>0];
    $expiringHoldsList = [];
    try {
        // Expire sweep (lazy)
        try {
            $expireMakesAvail = '1';
            try { $r=$pdo->query("SELECT rule_value FROM business_rules WHERE rule_key='holding_fee.expire_makes_available' LIMIT 1")->fetch(); if($r) $expireMakesAvail=(string)$r['rule_value']; } catch(Throwable $e){}
            $todayStr = date('Y-m-d');
            $sweepSt=$pdo->prepare("SELECT * FROM holding_fees WHERE status IN ('PENDING','PAID') AND expiration_date < ? AND converted_to_reservation_id IS NULL");
            $sweepSt->execute([$todayStr]);
            foreach($sweepSt->fetchAll() as $hf){
                $pdo->prepare("UPDATE holding_fees SET status='EXPIRED' WHERE id=?")->execute([$hf['id']]);
                if($expireMakesAvail==='1' && !empty($hf['property_unit_id'])){
                    $pdo->prepare("UPDATE property_units SET status='AVAILABLE', current_holding_fee_id=NULL WHERE id=? AND status='ON HOLD'")->execute([$hf['property_unit_id']]);
                }
            }
        } catch(Throwable $e){}

        $countSt = function(string $sql, array $params=[]) use ($pdo): int {
            if(!$params){ try { return (int)($pdo->query($sql)->fetch()['c'] ?? 0); } catch(Throwable $e){ return 0; } }
            try { $st=$pdo->prepare($sql); $st->execute($params); return (int)($st->fetch()['c'] ?? 0); } catch(Throwable $e){ return 0; }
        };
        $holdingSummary['activeHolds']     = $countSt("SELECT COUNT(*) c FROM holding_fees WHERE status='PAID' AND expiration_date >= ?", [$todayStr]);
        $holdingSummary['expiringHolds']   = $countSt("SELECT COUNT(*) c FROM holding_fees WHERE status='PAID' AND expiration_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)", [$todayStr, $todayStr]);
        $holdingSummary['reservedUnits']   = $countSt("SELECT COUNT(*) c FROM property_units WHERE status='RESERVED'");
        $holdingSummary['pendingPayments'] = $countSt("SELECT COUNT(*) c FROM holding_fees WHERE status='PENDING'");
        $holdingSummary['pendingPayments'] += $countSt("SELECT COUNT(*) c FROM reservation_fees WHERE status='PENDING'");

        // Expiring holds table sorted nearest first (§12). LEFT JOIN so
        // pre-contract holds (contract_id NULL) still surface with their client.
        $expSt=$pdo->prepare("SELECT hf.*, COALESCE(c.client_name, hf.client_name, p.full_name) AS client_name, COALESCE(pu.display_label, hf.property_address, c.property_address) AS unit_label FROM holding_fees hf LEFT JOIN contracts c ON c.id=hf.contract_id LEFT JOIN prospect_clients p ON p.id=hf.prospect_client_id LEFT JOIN property_units pu ON pu.id=hf.property_unit_id WHERE hf.status='PAID' AND hf.expiration_date >= ? ORDER BY hf.expiration_date ASC LIMIT 20");
        $expSt->execute([$todayStr]);
        foreach($expSt->fetchAll() as $r){
            $expiringHoldsList[] = ['client'=>$r['client_name'],'unit'=>$r['unit_label'],'amount'=>(float)$r['amount'],'expiration'=>$r['expiration_date'],'holdingFeeId'=>(int)$r['id'],'contractId'=>$r['contract_id']!==null?(int)$r['contract_id']:null];
        }
    } catch(Throwable $e){ /* tables missing -> keep defaults */ }

    echo json_encode([
        'success' => true,
        'today' => $today,
        'kpis' => [
            'expected' => round($expected, 2),
            'collectedMonth' => $collectedMonth,
            'collectedPrevMonth' => $prevMonth,
            'collectedTotal' => $collectedTotal,
            'efficiency' => $efficiency,
            'dueSoon' => $dueSoonCount,
        ],
        'holdingSummary' => $holdingSummary,
        'expiringHolds' => $expiringHoldsList,
        'cashFlow' => ['labels' => $labels, 'actual' => $actual],
        'rows' => $ledger,
        'contracts' => $register,
        'notifications' => $notifications,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Request failed: ' . $e->getMessage()]);
    exit;
}
