<?php
declare(strict_types=1);

// Clerk-side "Set portal password" handler — creates or updates a client
// login account (ihc.client_accounts) for an email that already owns a
// contract. This provisions contracts enrolled before the New Contract form
// started creating accounts automatically, and lets a clerk re-issue a
// password. Same upsert + bcrypt rules as backend/db.php; verification
// happens at login in php/api_client.php (?action=lookup).
// Clerk-side only and unauthenticated like the rest of this system.

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

    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
    if ($password === '') throw new RuntimeException('Client Portal Password is required.');
    if (strlen($password) < 6) throw new RuntimeException('Portal password must be at least 6 characters.');

    // An account can only be provisioned for an email the system already
    // knows. Backfill name/phone from that client's newest contract.
    $stmt = $pdo->prepare('SELECT client_name, cellphone_number FROM contracts WHERE LOWER(email) = LOWER(?) ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $contract = $stmt->fetch();
    if (!$contract) throw new RuntimeException('No contract is registered for this email address.');

    // Same self-healing DDL as backend/db.php — kept in both places because
    // neither file is a shared include in this codebase.
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
        ':password_hash'=>password_hash($password, PASSWORD_DEFAULT),
        ':full_name'=>(string)$contract['client_name'],
        ':cellphone_number'=>(string)$contract['cellphone_number']
    ]);
    // MySQL row count for INSERT ... ON DUPLICATE KEY UPDATE:
    // 1 = account created, 2 = existing account updated, 0 = updated but unchanged.
    $acctRows = $acctStmt->rowCount();
    $accountAction = $acctRows === 1 ? 'created' : ($acctRows === 0 ? 'unchanged' : 'updated');

    echo json_encode([
        'success'=>true,
        'message'=>$accountAction === 'created'
            ? 'Portal account created for ' . $email . ' — hand the password to the buyer.'
            : 'Portal password updated for ' . $email . '.',
        'accountAction'=>$accountAction
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
