<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db-config.php';

try {
    $pdo = ihc_pdo();

    // OR series continuity: gaps in the numeric sequence per series+year.
    $series = [];
    foreach ($pdo->query("SELECT or_number, contract_id, amount, date_collected FROM payments WHERE or_number LIKE 'OR-%' ORDER BY or_number") as $row) {
        if (preg_match('/^OR-([A-Z]+)-(\d{4})-(\d+)$/', (string)$row['or_number'], $m)) {
            $key = $m[1] . '-' . $m[2];
            $series[$key]['numbers'][(int)$m[3]] = true;
            $series[$key]['series'] = $m[1];
            $series[$key]['year'] = $m[2];
        }
    }
    $gaps = [];
    foreach ($series as $key => $s) {
        $nums = array_keys($s['numbers']);
        if (!$nums) continue;
        sort($nums);
        $max = max($nums);
        $present = array_fill_keys($nums, true);
        $missing = [];
        for ($n = 1; $n <= $max; $n++) {
            if (!isset($present[$n])) $missing[] = $n;
        }
        $gaps[] = [
            'series' => $s['series'],
            'year' => (int)$s['year'],
            'issuedCount' => count($nums),
            'highestNumber' => $max,
            'gaps' => $missing,
        ];
    }

    // Duplicate OR numbers (should be impossible — flag for review).
    $duplicates = [];
    foreach ($pdo->query("SELECT or_number, COUNT(*) c FROM payments WHERE or_number IS NOT NULL AND or_number <> '' GROUP BY or_number HAVING c > 1") as $row) {
        $duplicates[] = ['orNumber' => $row['or_number'], 'count' => (int)$row['c']];
    }

    // PDC payments missing their check number.
    $pdcMissing = [];
    foreach ($pdo->query("SELECT id, contract_id, amount, date_collected, or_number FROM payments WHERE payment_method = 'Post-Dated Check (PDC)' AND (check_number IS NULL OR check_number = '') ORDER BY date_collected DESC LIMIT 50") as $row) {
        $pdcMissing[] = [
            'paymentId' => (int)$row['id'],
            'contractId' => $row['contract_id'],
            'amount' => (float)$row['amount'],
            'dateCollected' => $row['date_collected'],
            'orNumber' => $row['or_number'],
        ];
    }

    echo json_encode([
        'success' => true,
        'seriesGaps' => $gaps,
        'duplicateOrNumbers' => $duplicates,
        'pdcMissingCheckNumber' => $pdcMissing,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
