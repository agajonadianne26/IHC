<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Self-heal: officers gained email/password_hash in migration 004. Older DBs
// may not have run it yet — add the columns on first use instead of failing.
try {
    foreach (['email' => 'VARCHAR(255) NULL', 'password_hash' => 'VARCHAR(255) NULL'] as $col => $def) {
        $exists = $pdo->query("SHOW COLUMNS FROM officers LIKE " . $pdo->quote($col))->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE officers ADD COLUMN `$col` $def");
        }
    }
} catch (Throwable $e) {
    // Non-fatal: if ALTERs are not permitted the login queries below will
    // surface the real error with a message pointing at migration 004.
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
        exit;
    }

    $email = isset($data['email']) ? trim((string)$data['email']) : '';
    $password = isset($data['password']) ? (string)$data['password'] : '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
        exit;
    }
    if ($password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }

    $emailLower = strtolower($email);

    // --- 1) Staff accounts (admin + clerk) live in ihc.officers ---------
    $staff = null;
    try {
        $stmt = $pdo->prepare('SELECT id, full_name, role, email, password_hash FROM officers WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$emailLower]);
        $staff = $stmt->fetch();
    } catch (Throwable $e) {
        // Missing columns on a fresh DB — self-heal above should have fixed
        // this; rethrow so the outer handler reports it once.
        throw $e;
    }

    if ($staff && $staff['password_hash'] !== null && $staff['password_hash'] !== ''
        && password_verify($password, $staff['password_hash'])) {
        $role = strtolower(trim((string)$staff['role']));
        if ($role !== 'admin') $role = 'clerk'; // officers.role is 'clerk'|'admin'
        $name = (string)$staff['full_name'];
        $user = [
            'role' => $role,
            'name' => $name,
            'email' => (string)$staff['email'],
            'avatar' => initialsOf($name, (string)$staff['email']),
        ];
        if ($role === 'clerk') {
            $user['officerId'] = (int)$staff['id']; // matches contracts.officer_id '2'
        }
        echo json_encode(['success' => true, 'user' => $user]);
        exit;
    }

    // --- 2) Client portal accounts (bcrypt, migration 003) --------------
    $client = null;
    $clientTableMissing = false;
    try {
        $stmt = $pdo->prepare('SELECT id, email, full_name, password_hash FROM client_accounts WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$emailLower]);
        $client = $stmt->fetch();
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '42S02') { // table doesn't exist yet
            $clientTableMissing = true;
        } else {
            throw $e;
        }
    }

    if ($client && password_verify($password, $client['password_hash'])) {
        $stmt = $pdo->prepare('SELECT id, client_name FROM contracts WHERE LOWER(email) = ? ORDER BY id');
        $stmt->execute([$emailLower]);
        $contracts = $stmt->fetchAll();

        if (count($contracts) > 1) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Multiple contracts found for this email. Please contact billing for your account code.']);
            exit;
        }
        if (count($contracts) === 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Your portal account has no active contract yet. Please contact IHC.']);
            exit;
        }

        $contract = $contracts[0];
        $name = $client['full_name'] !== null && $client['full_name'] !== ''
            ? (string)$client['full_name'] : (string)$contract['client_name'];
        echo json_encode(['success' => true, 'user' => [
            'role' => 'client',
            'name' => $name,
            'email' => (string)$client['email'],
            'avatar' => initialsOf($name, (string)$client['email']),
            'contractId' => (int)$contract['id'],
        ]]);
        exit;
    }

    // --- Neither credential matched: explain why, without leaking hashes -
    http_response_code(401);
    if ($staff || $client) {
        // Account exists — the password simply didn't verify.
        echo json_encode(['success' => false, 'message' => 'Invalid email or password. Please try again.']);
    } else {
        // Email is unknown to both officers and client_accounts.
        echo json_encode(['success' => false, 'message' => 'No account is set up for this email yet. Please contact your IHC clerk.']);
    }
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage() . ' (if officers.email is missing, run backend/migrations/004_staff_accounts_ihc.sql)']);
    exit;
}

function initialsOf(string $name, string $email): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($email, 0, 1));
    }
    return $initials;
}
