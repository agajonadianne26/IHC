<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Only POST requests are allowed.']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );

    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON request.');

    $contractId    = trim((string)($data['contractId'] ?? ''));
    $method        = trim((string)($data['method'] ?? ''));
    $dateCollected = trim((string)($data['dateCollected'] ?? ''));
    $orNumber      = trim((string)($data['orNumber'] ?? ''));
    $remarks       = trim((string)($data['remarks'] ?? ''));
    $amount        = $data['amount'] ?? null;
    $postedBy      = trim((string)($data['postedBy'] ?? ''));

    if ($contractId === '') throw new RuntimeException('Missing contract reference.');
    if ($method === '') throw new RuntimeException('Payment method is required.');
    if ($orNumber === '') throw new RuntimeException('OR / Reference number is required.');
    if ($amount === null || $amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        throw new RuntimeException('Invalid payment amount.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $dateCollected);
    if (!$date || $date->format('Y-m-d') !== $dateCollected) {
        throw new RuntimeException('Invalid date collected.');
    }

    // Kunin ang numeric id mula sa "CON-7" para ma-verify sa contracts table
    $numericId = null;
    if (preg_match('/(\d+)\s*$/', $contractId, $m)) {
        $numericId = (int)$m[1];
    }

    $check = $pdo->prepare('SELECT id FROM contracts WHERE id = ?');
    $check->execute([$numericId]);
    if (!$check->fetch()) throw new RuntimeException('Contract not found in database.');

    // I-save ang payment sa payments table
    $sql = 'INSERT INTO payments (contract_id, amount, payment_method, date_collected, or_number, remarks, posted_by)
            VALUES (:contract_id, :amount, :payment_method, :date_collected, :or_number, :remarks, :posted_by)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':contract_id'    => $contractId,
        ':amount'         => (float)$amount,
        ':payment_method' => $method,
        ':date_collected' => $dateCollected,
        ':or_number'      => $orNumber,
        ':remarks'        => $remarks !== '' ? $remarks : null,
        ':posted_by'      => $postedBy !== '' ? $postedBy : null,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment successfully posted to the ledger.',
        'id'      => (int)$pdo->lastInsertId()
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>