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

    $client = is_array($data['client'] ?? null) ? $data['client'] : [];
    $contract = is_array($data['contract'] ?? null) ? $data['contract'] : [];

    $officerId = trim((string)($data['officerId'] ?? ''));
    $clientName = trim((string)($client['fullName'] ?? ''));
    $email = trim((string)($client['email'] ?? ''));
    $phone = trim((string)($client['phone'] ?? ''));
    $propertyAddress = trim((string)($contract['propertyAddress'] ?? ''));
    $totalPrice = $contract['totalPrice'] ?? null;
    $downpayment = $contract['downpayment'] ?? null;
    $terms = $contract['terms'] ?? null;
    $startDate = trim((string)($contract['startDate'] ?? ''));

    if ($clientName==='' || $email==='' || $phone==='' || $propertyAddress==='' || $totalPrice===null || $totalPrice==='' || $downpayment===null || $downpayment==='' || $terms===null || $terms==='' || $startDate==='') {
        throw new RuntimeException('All New Contract fields are required.');
    }
    if (!ctype_digit($phone) || strlen($phone) !== 11) {
        throw new RuntimeException('Cellphone number must be exactly 11 digits.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
    if (!is_numeric($totalPrice) || (float)$totalPrice < 0) throw new RuntimeException('Invalid total contract price.');
    if (!is_numeric($downpayment) || (float)$downpayment < 0) throw new RuntimeException('Invalid downpayment.');
    if ((float)$downpayment > (float)$totalPrice) throw new RuntimeException('Downpayment cannot exceed the total contract price.');
    if (!ctype_digit((string)$terms) || (int)$terms < 1) throw new RuntimeException('Installment terms must be a positive whole number.');
    $date = DateTime::createFromFormat('!Y-m-d', $startDate);
    if (!$date || $date->format('Y-m-d') !== $startDate) throw new RuntimeException('Invalid start date.');

    $portalPassword = (string)($client['portalPassword'] ?? '');
    if ($portalPassword === '') throw new RuntimeException('Client Portal Password is required so the buyer can sign in.');
    if (strlen($portalPassword) < 6) throw new RuntimeException('Portal password must be at least 6 characters.');

    // Add fields required by the New Contract form if an older contracts table lacks them.
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM contracts') as $column) $columns[strtolower($column['Field'])] = true;
    if (!isset($columns['property_address'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN property_address VARCHAR(500) NULL');
    if (!isset($columns['officer_id'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN officer_id VARCHAR(100) NULL');

    // Client portal login accounts (one row per email, so a repeat buyer's
    // credentials are updated instead of duplicated). Passwords are stored as
    // bcrypt hashes and verified by php/api_client.php at login. DDL must stay
    // outside the transaction below — MySQL commits implicitly on DDL.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS client_accounts (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            cellphone_number VARCHAR(50) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_client_accounts_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    // Contract and login account are written together: either both save or neither.
    $pdo->beginTransaction();
    try {
        $sql = 'INSERT INTO contracts (client_name,email,cellphone_number,property_address,total_contract_price,downpayment,installment_terms,start_date,officer_id) VALUES (:client_name,:email,:cellphone_number,:property_address,:total_contract_price,:downpayment,:installment_terms,:start_date,:officer_id)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':client_name'=>$clientName, ':email'=>$email, ':cellphone_number'=>$phone, ':property_address'=>$propertyAddress,
            ':total_contract_price'=>(float)$totalPrice, ':downpayment'=>(float)$downpayment, ':installment_terms'=>(int)$terms,
            ':start_date'=>$startDate, ':officer_id'=>$officerId !== '' ? $officerId : null
        ]);

        $contractId = (int)$pdo->lastInsertId();

        // Create the buyer's portal account, or update the existing one when
        // this email has signed up before (new password/name/phone win).
        $acctStmt = $pdo->prepare(
            'INSERT INTO client_accounts (email, password_hash, full_name, cellphone_number)
             VALUES (:email, :password_hash, :full_name, :cellphone_number)
             ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                full_name = VALUES(full_name),
                cellphone_number = VALUES(cellphone_number)'
        );
        $acctStmt->execute([
            ':email'=>$email,
            ':password_hash'=>password_hash($portalPassword, PASSWORD_DEFAULT),
            ':full_name'=>$clientName,
            ':cellphone_number'=>$phone
        ]);
        // MySQL row count for INSERT ... ON DUPLICATE KEY UPDATE:
        // 1 = account created, 2 = existing account updated, 0 = updated but unchanged.
        $acctRows = $acctStmt->rowCount();
        $accountAction = $acctRows === 1 ? 'created' : ($acctRows === 0 ? 'unchanged' : 'updated');

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Send the three-day reminder immediately when a clerk creates a contract
    // whose first payment is due in exactly three calendar days. This closes
    // the gap when the contract is entered after the daily scheduler ran.
    $today = new DateTimeImmutable('today');
    $daysUntilDue = (int)$today->diff($date)->format('%r%a');
    $reminderQueued = false;
    if ($daysUntilDue === 3) {
        $serviceUrl = rtrim(getenv('REMINDER_SERVICE_URL') ?: 'http://127.0.0.1:3000', '/');
        $payload = json_encode([
            'client' => $clientName,
            'amount' => (float)$downpayment,
            'dueDate' => $startDate,
            'recipientEmail' => $email,
            'recipientPhone' => $phone,
            'reminderType' => 'due_soon'
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true
        ]]);
        // A mail-service outage must never roll back a successfully saved contract.
        $response = @file_get_contents($serviceUrl . '/api/contracts/IHC-' . $contractId . '/send-reminder', false, $context);
        $responseData = is_string($response) ? json_decode($response, true) : null;
        $reminderQueued = is_array($responseData) && !empty($responseData['success']);
    }

    echo json_encode([
        'success'=>true,
        'message'=>'Contract created and saved to the IHC database.',
        'id'=>$contractId,
        'accountAction'=>$accountAction,
        'reminderQueued'=>$reminderQueued
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
