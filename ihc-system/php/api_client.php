<?php
declare(strict_types=1);

// Client-portal live data bridge.
// The client dashboard historically read ONLY browser localStorage, so anything
// a clerk changed (new contracts, posted payments, emailed reminders/receipts)
// never reached the client. This endpoint lets the client dashboard sync from
// the same MySQL tables the clerk dashboards write.
//
//   ?action=lookup&email=...            -> contract(s) owned by that email (login)
//   ?action=ledger&contractId=..&email= -> contract + payments + notifications
//
// Auth is intentionally mock-grade (email ownership only), matching the rest of
// this system (hardcoded JS users, no server sessions). Do not treat this as
// real access control — replace with password/session auth before production.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Accept bare numeric ids as well as the CON-/IHC- prefixed codes the
// dashboards display; all three refer to the same contracts.id.
function normalizeContractId($value): ?int {
    if (!is_string($value) && !is_numeric($value)) return null;
    if (preg_match('/(\d+)\s*$/', (string)$value, $m)) {
        $id = (int)$m[1];
        return $id > 0 ? $id : null;
    }
    return null;
}

function contractVariants(int $id): array {
    return [(string)$id, 'CON-' . $id, 'IHC-' . $id];
}

function fetchContract(PDO $pdo, int $id): ?array {
    // officers.full_name only — the live ihc.officers table has no email
    // column, so there is no officer address to show or mail to here.
    $stmt = $pdo->prepare(
        'SELECT c.*, o.full_name AS officer_name
         FROM contracts c LEFT JOIN officers o ON o.id = c.officer_id
         WHERE c.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : 'ledger';

try {
    if ($action === 'lookup') {
        $email = trim((string)($_GET['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }
        $stmt = $pdo->prepare(
            'SELECT c.id, c.client_name, c.email FROM contracts c WHERE LOWER(c.email) = LOWER(?) ORDER BY c.id'
        );
        $stmt->execute([$email]);
        $matches = [];
        foreach ($stmt->fetchAll() as $row) {
            $matches[] = [
                'contractId' => (int)$row['id'],
                'accountCode' => 'CON-' . $row['id'],
                'name' => $row['client_name'],
                'email' => $row['email'],
            ];
        }
        echo json_encode(['success' => true, 'contracts' => $matches]);
        exit;
    }

    if ($action === 'ledger') {
        $id = normalizeContractId($_GET['contractId'] ?? null);
        $email = trim((string)($_GET['email'] ?? ''));
        if ($id === null) throw new RuntimeException('A valid contract reference is required.');
        if ($email === '') throw new RuntimeException('Email is required.');

        $contract = fetchContract($pdo, $id);
        if (!$contract) throw new RuntimeException('Contract not found.');
        if (strcasecmp(trim((string)$contract['email']), $email) !== 0) {
            throw new RuntimeException('This contract does not belong to that email address.');
        }

        // payments.contract_id stores the prefixed code (CON-<id>), so match
        // every known variant. Bound params coerce freely — no collation trap.
        $variants = contractVariants($id);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $payStmt = $pdo->prepare(
            "SELECT id, amount, payment_method, date_collected, or_number, remarks, posted_by, created_at
             FROM payments WHERE contract_id IN ($placeholders) ORDER BY date_collected, id"
        );
        $payStmt->execute($variants);
        $payments = [];
        foreach ($payStmt->fetchAll() as $p) {
            $payments[] = [
                'id' => (int)$p['id'],
                'amount' => (float)$p['amount'],
                'method' => $p['payment_method'],
                'date' => $p['date_collected'],
                'orNumber' => $p['or_number'],
                'remarks' => $p['remarks'],
                'postedBy' => $p['posted_by'],
            ];
        }

        // Server-sent client notifications (reminders + receipts). The ack rows
        // (channel ack_email) are clerk-facing and stay out of this feed.
        $notifStmt = $pdo->prepare(
            "SELECT id, reminder_type, subject, status, sent_at FROM notifications_logs
             WHERE contract_id IN ($placeholders) AND channel = 'email' AND status = 'sent'
             ORDER BY sent_at DESC LIMIT 20"
        );
        $notifStmt->execute($variants);
        $notifications = [];
        foreach ($notifStmt->fetchAll() as $n) {
            $type = (string)($n['reminder_type'] ?? '');
            $isReceipt = $type === 'payment_receipt';
            $notifications[] = [
                'logId' => (int)$n['id'],
                'kind' => $isReceipt ? 'payment' : 'reminder',
                'channel' => 'Email',
                'title' => $isReceipt ? 'Payment receipt emailed' : 'Payment reminder sent',
                'message' => (string)$n['subject'],
                'time' => $n['sent_at'],
            ];
        }

        echo json_encode([
            'success' => true,
            'contract' => [
                'contractId' => (int)$contract['id'],
                'accountCode' => 'CON-' . $contract['id'],
                'name' => $contract['client_name'],
                'email' => $contract['email'],
                'phone' => $contract['cellphone_number'],
                'propertyAddress' => $contract['property_address'],
                'totalPrice' => (float)$contract['total_contract_price'],
                'downpayment' => (float)$contract['downpayment'],
                'terms' => (int)$contract['installment_terms'],
                'startDate' => $contract['start_date'],
                'officerId' => $contract['officer_id'],
                'officerName' => $contract['officer_name'],
            ],
            'payments' => $payments,
            'notifications' => $notifications,
        ]);
        exit;
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
