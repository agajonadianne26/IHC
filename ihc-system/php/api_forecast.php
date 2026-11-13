<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/installment-schedule.php';

try {
    $pdo = ihc_pdo();

    $granularity = strtolower(trim((string)($_GET['granularity'] ?? 'month')));
    if (!in_array($granularity, ['month', 'week'], true)) $granularity = 'month';

    // --- Expected collections: unpaid principal due per period. -------------
    $principalByPayment = [];
    try {
        foreach ($pdo->query('SELECT payment_id, SUM(principal_amount) AS principal_amount FROM payment_allocations GROUP BY payment_id') as $row) {
            $principalByPayment[(int)$row['payment_id']] = (float)$row['principal_amount'];
        }
    } catch (Throwable $e) { /* legacy DBs without allocations fall back to full amount */ }

    $paymentsByContract = [];
    foreach ($pdo->query('SELECT id, contract_id, amount, date_collected, or_number, payment_method FROM payments ORDER BY date_collected, id') as $p) {
        if (preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) {
            $paymentsByContract[(int)$m[1]][] = [
                'amount' => (float)$p['amount'],
                'principalAmount' => $principalByPayment[(int)$p['id']] ?? null,
                'date' => (string)$p['date_collected'],
                'orNumber' => $p['or_number'],
                'method' => $p['payment_method'],
            ];
        }
    }

    // --- Period buckets: previous 5 + current + next 11 ---------------------
    $now = new DateTimeImmutable('today');
    $periods = [];
    $labels = [];
    if ($granularity === 'week') {
        $start = $now->modify('monday this week');
        for ($i = -5; $i <= 11; $i++) {
            $d = $start->modify(($i >= 0 ? '+' : '') . $i . ' weeks');
            $periods[] = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
        }
    } else {
        $cursor = $now->modify('first day of this month');
        for ($i = -5; $i <= 11; $i++) {
            $d = $cursor->modify(($i >= 0 ? '+' : '') . $i . ' months');
            $periods[] = $d->format('Y-m');
            $labels[] = $d->format('M Y');
        }
    }
    $periodSet = array_fill_keys($periods, true);

    $periodFor = static function (string $date) use ($granularity): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        if (!$d) return null;
        return $granularity === 'week'
            ? $d->modify('monday this week')->format('Y-m-d')
            : $d->format('Y-m');
    };

    $expected = [];
    foreach ($pdo->query('SELECT * FROM contracts ORDER BY id') as $contract) {
        $cid = (int)$contract['id'];
        $schedule = ihc_schedule($contract, $paymentsByContract[$cid] ?? []);
        foreach ($schedule['installments'] as $inst) {
            $unpaid = max(0, (float)$inst['amount'] - (float)$inst['paidAmount']);
            if ($unpaid <= 0.009) continue;
            $key = $periodFor((string)$inst['dueDate']);
            if ($key === null || !isset($periodSet[$key])) continue;
            $expected[$key] = ($expected[$key] ?? 0) + $unpaid;
        }
    }

    // --- Actual collections: posted payments per period. --------------------
    $actual = [];
    foreach ($pdo->query("SELECT DATE(date_collected) AS d, SUM(amount) AS total FROM payments GROUP BY DATE(date_collected)") as $row) {
        $key = $periodFor((string)$row['d']);
        if ($key === null || !isset($periodSet[$key])) continue;
        $actual[$key] = ($actual[$key] ?? 0) + (float)$row['total'];
    }

    echo json_encode([
        'success' => true,
        'granularity' => $granularity,
        'periods' => $periods,
        'labels' => $labels,
        'months' => $labels,
        'actual' => array_map(static fn($p) => round($actual[$p] ?? 0, 2), $periods),
        'expected' => array_map(static fn($p) => round($expected[$p] ?? 0, 2), $periods),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
