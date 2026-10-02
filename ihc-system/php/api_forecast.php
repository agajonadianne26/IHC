<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/installment-schedule.php';

try {
    $pdo = ihc_pdo();

    // --- Expected collections: unpaid principal due per month, next 12 months.
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

    $expected = []; // month => amount
    foreach ($pdo->query('SELECT * FROM contracts ORDER BY id') as $contract) {
        $cid = (int)$contract['id'];
        $schedule = ihc_schedule($contract, $paymentsByContract[$cid] ?? []);
        foreach ($schedule['installments'] as $inst) {
            $unpaid = max(0, (float)$inst['amount'] - (float)$inst['paidAmount']);
            if ($unpaid <= 0.009) continue;
            $month = substr((string)$inst['dueDate'], 0, 7);
            $expected[$month] = ($expected[$month] ?? 0) + $unpaid;
        }
    }

    // --- Actual collections: posted payments per month, last 6 months.
    $actual = [];
    foreach ($pdo->query("SELECT DATE_FORMAT(date_collected, '%Y-%m') AS m, SUM(amount) AS total FROM payments GROUP BY m") as $row) {
        $actual[(string)$row['m']] = (float)$row['total'];
    }

    $months = [];
    $cursor = new DateTimeImmutable('first day of this month');
    for ($i = -5; $i <= 11; $i++) {
        $months[] = $cursor->modify(($i >= 0 ? '+' : '') . $i . ' months')->format('Y-m');
    }

    echo json_encode([
        'success' => true,
        'months' => $months,
        'actual' => array_map(static fn($m) => round($actual[$m] ?? 0, 2), $months),
        'expected' => array_map(static fn($m) => round($expected[$m] ?? 0, 2), $months),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
