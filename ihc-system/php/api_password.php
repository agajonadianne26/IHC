<?php
declare(strict_types=1);
require_once __DIR__ . '/db-config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function out(array $p, int $code = 200): void { http_response_code($code); echo json_encode($p); exit; }

try {
    $pdo = ihc_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        account_type VARCHAR(10) NOT NULL,
        account_name VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        resolved_by VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Staff only (admin + clerk): flag that forces a password change at next sign-in.
    foreach (['officers'] as $t) {
        try {
            if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE 'must_change_password'")->fetch()) {
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Throwable $e) { /* table may not exist yet */ }
    }
} catch (Throwable $e) {
    out(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

function findAccount(PDO $pdo, string $emailLower): ?array {
    foreach (['staff' => 'officers'] as $type => $table) {
        try {
            $s = $pdo->prepare("SELECT id, full_name, password_hash FROM `$table` WHERE LOWER(email) = ? LIMIT 1");
            $s->execute([$emailLower]);
            $r = $s->fetch();
            if ($r) return ['type' => $type, 'table' => $table, 'id' => (int)$r['id'], 'name' => (string)$r['full_name'], 'hash' => (string)$r['password_hash']];
        } catch (Throwable $e) { /* skip missing table */ }
    }
    return null;
}
function setPassword(PDO $pdo, array $acct, string $hash, int $must): void {
    $pdo->prepare("UPDATE `{$acct['table']}` SET password_hash = ?, must_change_password = ? WHERE id = ?")
        ->execute([$hash, $must, $acct['id']]);
}
function requireAdmin(PDO $pdo, $actorId): void {
    $s = $pdo->prepare('SELECT role FROM officers WHERE id = ? LIMIT 1');
    $s->execute([(string)$actorId]);
    $r = $s->fetch();
    if (!$r || strtolower(trim((string)$r['role'])) !== 'admin') out(['success' => false, 'message' => 'Admin access required.'], 403);
}

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) $in = [];
$action = (string)($_GET['action'] ?? $in['action'] ?? '');

try {
    // --- Public: staff member asks for a reset ------------------------
    if ($action === 'request_reset') {
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['success' => false, 'message' => 'Enter a valid email address.'], 400);
        $acct = findAccount($pdo, $email);
        if ($acct) {
            $dup = $pdo->prepare("SELECT id FROM password_reset_requests WHERE LOWER(email) = ? AND status = 'pending' LIMIT 1");
            $dup->execute([$email]);
            if (!$dup->fetch()) {
                $pdo->prepare('INSERT INTO password_reset_requests (email, account_type, account_name) VALUES (?, ?, ?)')
                    ->execute([$email, $acct['type'], $acct['name']]);
            }
        }
        // Same answer whether or not the account exists.
        out(['success' => true, 'message' => 'If that email belongs to a staff account, a reset request has been sent to the administrator.']);
    }

    // --- Admin: list pending requests ---------------------------------
    if ($action === 'list_requests') {
        requireAdmin($pdo, $_GET['actorId'] ?? '');
        $rows = $pdo->query("SELECT id, email, account_type, account_name, requested_at FROM password_reset_requests WHERE status = 'pending' ORDER BY id")->fetchAll();
        out(['success' => true, 'requests' => array_map(fn($r) => [
            'id' => (int)$r['id'], 'email' => $r['email'], 'type' => $r['account_type'],
            'name' => $r['account_name'], 'requestedAt' => $r['requested_at'],
        ], $rows)]);
    }

    // --- Admin: approve (temporary password) or reject ----------------
    if ($action === 'resolve_request') {
        $actorId = $in['actorId'] ?? '';
        requireAdmin($pdo, $actorId);
        $id = (int)($in['id'] ?? 0);
        $decision = (string)($in['decision'] ?? '');
        $s = $pdo->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending' LIMIT 1");
        $s->execute([$id]);
        $req = $s->fetch();
        if (!$req) out(['success' => false, 'message' => 'Request not found or already handled.'], 404);

        if ($decision === 'reject') {
            $pdo->prepare("UPDATE password_reset_requests SET status = 'rejected', resolved_at = NOW(), resolved_by = ? WHERE id = ?")
                ->execute([(string)$actorId, $id]);
            out(['success' => true, 'message' => 'Request rejected.']);
        }
        if ($decision !== 'approve') out(['success' => false, 'message' => 'Invalid decision.'], 400);

        $acct = findAccount($pdo, strtolower((string)$req['email']));
        if (!$acct) out(['success' => false, 'message' => 'The account no longer exists.'], 404);

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $temp = '';
        for ($i = 0; $i < 10; $i++) $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];

        $pdo->beginTransaction();
        setPassword($pdo, $acct, password_hash($temp, PASSWORD_DEFAULT), 1);
        $pdo->prepare("UPDATE password_reset_requests SET status = 'approved', resolved_at = NOW(), resolved_by = ? WHERE id = ?")
            ->execute([(string)$actorId, $id]);
        $pdo->commit();
        out(['success' => true, 'message' => 'Temporary password generated.', 'tempPassword' => $temp, 'name' => $acct['name'], 'email' => $req['email']]);
    }

    // --- Public: staff member replaces the temporary password ---------
    if ($action === 'change_password') {
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $current = (string)($in['currentPassword'] ?? '');
        $new = (string)($in['newPassword'] ?? '');
        if (strlen($new) < 8) out(['success' => false, 'message' => 'New password must be at least 8 characters.'], 400);
        if ($new === $current) out(['success' => false, 'message' => 'New password must be different from the temporary one.'], 400);
        $acct = findAccount($pdo, $email);
        if (!$acct || $acct['hash'] === '' || !password_verify($current, $acct['hash'])) {
            out(['success' => false, 'message' => 'Current password is incorrect.'], 401);
        }
        setPassword($pdo, $acct, password_hash($new, PASSWORD_DEFAULT), 0);
        out(['success' => true, 'message' => 'Password changed.']);
    }

    out(['success' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    out(['success' => false, 'message' => 'Request failed: ' . $e->getMessage()], 500);
}