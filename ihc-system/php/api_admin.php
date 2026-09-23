<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

try {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : 'overview';
    $today = date('Y-m-d');

    // payments.contract_id stores prefixed strings ('CON-19', sometimes bare
    // '7' or 'IHC-14') — fold them onto integer contract ids in PHP instead of
    // SQL JOINs (mixed collations between notifications/payments and contracts
    // blow up at runtime; see AGENTS.md).
    $paidByContract = [];   // contractId => float
    $paidMonthByContract = []; // contractId => ['YYYY-MM' => float]
    $paysByContract = [];   // contractId => [ ['date','amount'] ] chronological
    foreach ($pdo->query('SELECT contract_id, amount, date_collected FROM payments ORDER BY date_collected, id') as $p) {
        if (!preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) continue;
        $key = (int)$m[1];
        $amt = (float)$p['amount'];
        $paidByContract[$key] = ($paidByContract[$key] ?? 0.0) + $amt;
        $month = substr((string)$p['date_collected'], 0, 7);
        $paidMonthByContract[$key][$month] = ($paidMonthByContract[$key][$month] ?? 0.0) + $amt;
        $paysByContract[$key][] = ['date' => (string)$p['date_collected'], 'amount' => $amt];
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

    $daysUntil = function (string $from, string $to): int {
        return (int)floor((strtotime($to) - strtotime($from)) / 86400);
    };

    // Contract status from data the schema actually supports:
    //   paid      -> nothing outstanding
    //   overdue   -> first payment date (start_date) already passed
    //   due-soon  -> start_date within the next 3 days
    //   current   -> everything else
    $statusOf = function (float $outstanding, string $start) use ($today, $daysUntil): string {
        if ($outstanding <= 0.009) return 'paid';
        if ($start < $today) return 'overdue';
        if ($daysUntil($today, $start) <= 3) return 'due-soon';
        return 'current';
    };

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

        $addMonths = function (string $date, int $months): string {
            [$y, $mo, $d] = array_map('intval', explode('-', $date));
            $mo += $months;
            $y += intdiv($mo - 1, 12);
            $mo = (($mo - 1) % 12 + 12) % 12 + 1;
            $lastDay = (int)date('t', mktime(0, 0, 0, $mo, 1, $y));
            return sprintf('%04d-%02d-%02d', $y, $mo, min($d, $lastDay));
        };

        $tcp = (float)$contract['total_contract_price'];
        $dp = (float)$contract['downpayment'];
        $terms = max(1, (int)$contract['installment_terms']);
        $start = (string)$contract['start_date'];
        $paidLeft = $livePaid[$cid] ?? 0.0;

        $installments = [['no' => 1, 'type' => 'Downpayment', 'dueDate' => $start, 'amount' => round($dp, 2)]];
        $amortTotal = round($tcp - $dp, 2);
        $per = $terms > 0 ? floor($amortTotal / $terms * 100) / 100 : $amortTotal;
        for ($i = 1; $i <= $terms; $i++) {
            $amt = ($i === $terms) ? round($amortTotal - $per * ($terms - 1), 2) : $per;
            $installments[] = ['no' => $i + 1, 'type' => 'Monthly Amortization', 'dueDate' => $addMonths($start, $i), 'amount' => $amt];
        }

        // Allocate posted payments chronologically: fully covered = paid.
        $schedule = [];
        foreach ($installments as $inst) {
            $covered = $paidLeft >= $inst['amount'] - 0.009;
            if ($covered) $paidLeft -= $inst['amount'];
            if ($covered) {
                $st = 'paid';
            } elseif ($inst['dueDate'] < $today) {
                $st = 'overdue';
            } elseif ($daysUntil($today, $inst['dueDate']) <= 3) {
                $st = 'due-soon';
            } else {
                $st = 'current';
            }
            $schedule[] = [
                'no' => $inst['no'],
                'type' => $inst['type'],
                'dueDate' => $inst['dueDate'],
                'amount' => $inst['amount'],
                'status' => $st,
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
        $dp = (float)$c['downpayment'];
        $paid = round($livePaid[$id] ?? 0.0, 2);
        $outstanding = max(0.0, round($tcp - $paid, 2));
        $start = (string)$c['start_date'];
        $status = $statusOf($outstanding, $start);
        if ($status === 'due-soon') $dueSoonCount++;

        $paymentType = $outstanding <= 0.009
            ? 'Settled in Full'
            : ($paid >= $dp ? 'Contract Balance' : 'Downpayment');
        $clerk = $c['clerk_name'] !== null && $c['clerk_name'] !== '' ? (string)$c['clerk_name'] : 'Unassigned';
        $property = $c['property_address'] !== null && $c['property_address'] !== '' ? (string)$c['property_address'] : '—';
        $notif = $notifStatusOf($latestNotif[$id] ?? null);

        $ledger[] = [
            'id' => 'IHC-' . $id,
            'contractId' => $id,
            'client' => (string)$c['client_name'],
            'property' => $property,
            'paymentType' => $paymentType,
            'amount' => $outstanding,
            'dueDate' => $start,
            'status' => $status,
            'notification' => $notif,
            'clerk' => $clerk,
            'paid' => $paid,
            'tcp' => $tcp,
        ];
        $register[] = [
            'id' => 'IHC-' . $id,
            'contractId' => $id,
            'client' => (string)$c['client_name'],
            'property' => $property,
            'tcp' => $tcp,
            'downpayment' => $dp,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'terms' => (int)$c['installment_terms'],
            'status' => $status,
            'clerk' => $clerk,
        ];

        $expected += $tcp;
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
