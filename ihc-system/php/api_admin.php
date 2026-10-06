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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// This endpoint is read-only apart from one exception: `reassign`, which moves
// a contract from one clerk to another (an admin ownership operation, not a
// payment). Payments are only ever written by php/post-payment.php.
if ($_SERVER['REQUEST_METHOD'] !== 'GET'
    && !($_SERVER['REQUEST_METHOD'] === 'POST'
        && (string)($_GET['action'] ?? '') === 'reassign')) {
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

soa_ensure_schema($pdo);

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
            "SELECT id, full_name, role, email FROM officers ORDER BY (role='admin') ASC, full_name ASC"
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

        // Expiring holds table sorted nearest first (§12)
        $expSt=$pdo->prepare("SELECT hf.*, c.client_name, COALESCE(pu.display_label, c.property_address) AS unit_label FROM holding_fees hf JOIN contracts c ON c.id=hf.contract_id LEFT JOIN property_units pu ON pu.id=hf.property_unit_id WHERE hf.status='PAID' AND hf.expiration_date >= ? ORDER BY hf.expiration_date ASC LIMIT 20");
        $expSt->execute([$todayStr]);
        foreach($expSt->fetchAll() as $r){
            $expiringHoldsList[] = ['client'=>$r['client_name'],'unit'=>$r['unit_label'],'amount'=>(float)$r['amount'],'expiration'=>$r['expiration_date'],'holdingFeeId'=>(int)$r['id'],'contractId'=>(int)$r['contract_id']];
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
