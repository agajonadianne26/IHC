<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// IMPORTANT: this used to be `require_once '../db.php';`, but db.php is the
// New-Contract POST handler — it exits immediately with a 405 error for any
// non-POST request (like this GET request), so the dashboard could never
// load any data at all. Connect directly here instead of reusing that file.
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// officer_id is stored as VARCHAR (it can be alphanumeric, e.g. "DEMO-001"),
// so don't force-cast it to (int) — that silently turns any non-numeric ID
// into 0 and the query would never match a real officer.
$officerId = isset($_GET['officerId']) ? trim((string)$_GET['officerId']) : '';

// Shared installment-schedule math — same file the admin and client endpoints
// use, so the three dashboards always agree on what is paid and what is due.
require_once __DIR__ . '/installment-schedule.php';

if ($officerId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid Officer ID']);
    exit;
}

$stmt = $pdo->prepare('SELECT c.* FROM contracts c WHERE c.officer_id = ? ORDER BY c.id');
$stmt->execute([$officerId]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC); // FETCH_ASSOC keeps the array clean

// payments.contract_id stores prefixed codes ('CON-19', sometimes a bare '7'
// or 'IHC-14'), so fold the rows onto integer contract ids in PHP — never
// with a SQL JOIN against contracts (mixed collations blow up at runtime;
// see AGENTS.md). Ordering by date keeps the chronological allocation in
// installment-schedule.php deterministic.
$paidByContract = [];   // contract id => chronological payment rows
foreach ($pdo->query('SELECT contract_id, amount, date_collected, or_number, payment_method FROM payments ORDER BY date_collected, id') as $p) {
    if (!preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) continue;
    $paidByContract[(int)$m[1]][] = [
        'amount'   => (float)$p['amount'],
        'date'     => (string)$p['date_collected'],
        'orNumber' => $p['or_number'] !== null ? (string)$p['or_number'] : null,
        'method'   => $p['payment_method'] !== null ? (string)$p['payment_method'] : null,
    ];
}

// Collections belong to the clerk who posted the payment, not merely to a
// contract that happens to be assigned to them. This makes the KPI persist
// across page reloads and reflect every successful Post to Ledger action.
$collectionsStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(amount), 0) AS total_collections FROM payments WHERE posted_by = ?'
);
$collectionsStmt->execute([$officerId]);
$totalCollections = (float)($collectionsStmt->fetch()['total_collections'] ?? 0);

// Map the database columns to the keys expected by the frontend, advancing
// each contract to its real next due: the downpayment is cleared first, then
// the monthly installments take over as the next payment.
$formattedClients = [];
foreach ($clients as $c) {
    $cid = (int)$c['id'];
    $terms = max(1, (int)($c['installment_terms'] ?? 1));

    $schedule = ihc_schedule($c, $paidByContract[$cid] ?? []);
    $next = $schedule['next'];

    $formattedClients[] = [
        'accountCode'     => 'CON-' . $c['id'],          // Using the primary key 'id'
        'contractId'      => (int)$c['id'],
        'name'            => $c['client_name'],          // Matches your DB
        'email'           => $c['email'],                // Matches your DB
        'phone'           => $c['cellphone_number'],     // matches your DB
        'propertyAddress' => $c['property_address'] ?? null,
        'totalPrice'      => isset($c['total_contract_price']) ? (float)$c['total_contract_price'] : null,
        'terms'           => $terms,
        'amountPaid'      => $schedule['paid'],
        'nextDueDate'     => $next ? $next['dueDate'] : (string)$c['start_date'],
        'nextAmount'      => $next ? $next['amount'] : 0,
        'nextStatus'      => $next ? 'Pending Payment' : 'Paid',
        'nextType'        => $next ? $next['kind'] : 'settled',
        'nextInstallmentNo' => $next ? $next['no'] : null,
    ];
}

echo json_encode([
    'success' => true,
    'clients' => $formattedClients,
    'totalCollections' => $totalCollections
]);
