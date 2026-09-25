<?php
declare(strict_types=1);

/**
 * Financing Bank default annual interest rates.
 *
 * The New Contract form (clerk dashboard) builds its "Financing Bank" list and
 * its auto-filled Annual Interest Rate from here; the admin dashboard edits the
 * per-bank default here. The value saved onto a contract (contracts.annual_interest_rate)
 * is snapshotted at creation time, so changing a bank's default only affects
 * NEW contracts.
 *
 *   GET  ?action=list   -> { bank, label, rate } ordered by sort_order
 *   POST {action:'update', rates:[{bank,label,rate}]} -> upserts, returns list
 *
 * Like api_holding_reservation.php, the table self-heals on first use so older
 * databases that never ran the migration file still work.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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

// ---- Seed data: the defaults the clerk form used before rates were editable.
// These show up only when the bank has never been configured (INSERT IGNORE),
// so an admin-edited rate is never overwritten.
const BANK_RATE_SEEDS = [
    ['In-House',       'In-House',         0.00],
    ['Pag-IBIG',       'Pag-IBIG Fund',    5.50],
    ['BDO',            'BDO',              6.50],
    ['BPI',            'BPI',              6.50],
    ['Metrobank',      'Metrobank',        6.50],
    ['Security Bank',  'Security Bank',    6.75],
    ['RCBC',           'RCBC',             6.75],
    ['UnionBank',      'UnionBank',        7.00],
    ['PNB',            'PNB',              7.00],
];

/** Create the table and backfill the seed banks if they are missing. */
function ensureBankRates(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bank_rates (
            id INT NOT NULL AUTO_INCREMENT,
            bank_name VARCHAR(120) NOT NULL,
            annual_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
            display_label VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bank_rates_name (bank_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $seed = $pdo->prepare(
        'INSERT IGNORE INTO bank_rates (bank_name, annual_rate, display_label, sort_order)
         VALUES (?, ?, ?, ?)'
    );
    $sort = 0;
    foreach (BANK_RATE_SEEDS as $row) {
        [$bank, $label, $rate] = $row;
        $seed->execute([$bank, $rate, $label, $sort]);
        $sort++;
    }
}

function listBankRates(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT bank_name, display_label, annual_rate, sort_order FROM bank_rates ORDER BY sort_order, bank_name'
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'bank'  => (string)$r['bank_name'],
            'label' => (string)$r['display_label'],
            'rate'  => (float)$r['annual_rate'],
        ];
    }
    return $out;
}

try {
    ensureBankRates($pdo);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        echo json_encode(['success' => true, 'rates' => listBankRates($pdo)]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
        exit;
    }

    $action = (string)($data['action'] ?? '');
    if ($action !== 'update') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
        exit;
    }

    $rates = $data['rates'] ?? null;
    if (!is_array($rates) || count($rates) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rates array is required.']);
        exit;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO bank_rates (bank_name, display_label, annual_rate)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE display_label = VALUES(display_label), annual_rate = VALUES(annual_rate)'
    );

    $order = 0;
    foreach ($rates as $item) {
        if (!is_array($item)) continue;
        $bank = trim((string)($item['bank'] ?? ''));
        $label = trim((string)($item['label'] ?? $bank));
        $rate = $item['rate'] ?? null;
        if ($bank === '') throw new RuntimeException('Bank name is required.');
        if (mb_strlen($bank) > 120) throw new RuntimeException('Bank name must be 120 characters or fewer.');
        if ($rate === null || $rate === '' || !is_numeric($rate) || (float)$rate < 0 || (float)$rate > 100) {
            throw new RuntimeException("Annual interest rate for {$bank} must be between 0 and 100.");
        }
        $upsert->execute([$bank, $label, round((float)$rate, 2)]);
        $pdo->exec("UPDATE bank_rates SET sort_order = {$order} WHERE bank_name = " . $pdo->quote($bank));
        $order++;
    }

    echo json_encode(['success' => true, 'rates' => listBankRates($pdo)]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}