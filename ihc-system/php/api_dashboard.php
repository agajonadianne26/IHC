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

if ($officerId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid Officer ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM contracts WHERE officer_id = ?");
$stmt->execute([$officerId]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC); // FETCH_ASSOC keeps the array clean

// Map the database columns to the keys expected by the frontend
$formattedClients = [];
foreach ($clients as $c) {
    $formattedClients[] = [
        'accountCode'     => 'CON-' . $c['id'],          // Using the primary key 'id'
        'contractId'      => (int)$c['id'],
        'name'            => $c['client_name'],          // Matches your DB
        'email'           => $c['email'],                // Matches your DB
        'phone'           => $c['cellphone_number'],     // matches your DB
        'propertyAddress' => $c['property_address'] ?? null,
        'totalPrice'      => isset($c['total_contract_price']) ? (float)$c['total_contract_price'] : null,
        'terms'           => isset($c['installment_terms']) ? (int)$c['installment_terms'] : null,
        'nextDueDate'     => $c['start_date'],            // mapped to your DB
        'nextAmount'      => $c['downpayment'],           // mapped to your DB
        'nextStatus'      => 'Pending Payment'
    ];
}

echo json_encode(['success' => true, 'clients' => $formattedClients]);
