<?php
declare(strict_types=1);

// Shared installment-schedule math — the clerk and admin endpoints include
// this same file, so the client's ledger shows exactly what their officer sees.
require_once __DIR__ . '/installment-schedule.php';

// Client-portal live data bridge.
// The client dashboard historically read ONLY browser localStorage, so anything
// a clerk changed (new contracts, posted payments, emailed reminders/receipts)
// never reached the client. This endpoint lets the client dashboard sync from
// the same MySQL tables the clerk dashboards write.
//
//   ?action=lookup&email=..&password=.. -> verify portal account, return contract(s)
//   ?action=ledger&contractId=..&email= -> contract + payments + notifications
//
// Auth: lookup verifies the bcrypt password stored in client_accounts (rows
// are created/updated by the New Contract form in backend/db.php). ledger
// stays email-ownership based — the session was established at login. Both
// remain mock-grade: replace with real server sessions before production.
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
    // The client portal only needs the assigned officer's display name.
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

        // Verify the portal password against client_accounts (bcrypt). The
        // account row is created/updated by the New Contract form; contracts
        // enrolled before that feature have no row yet.
        $password = (string)($_GET['password'] ?? '');
        if ($password === '') throw new RuntimeException('Password is required.');

        $account = null;
        if ($pdo->query("SHOW TABLES LIKE 'client_accounts'")->fetch()) {
            $acctStmt = $pdo->prepare('SELECT password_hash FROM client_accounts WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $acctStmt->execute([$email]);
            $account = $acctStmt->fetch();
        }
        if (!$account) {
            throw new RuntimeException('No portal account is set up for this email yet. Please contact your IHC clerk to create one.');
        }
        if (!password_verify($password, (string)$account['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
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

        // Allocate those same payment rows onto the installment schedule the
        // clerk and admin dashboards render. The client's "Installment
        // Schedules" table is served from here, so all three views agree on
        // what is paid, what is due next, and the OR/date that cleared it.
        $scheduleRows = [];
        foreach ($payments as $p) {
            $scheduleRows[] = [
                'amount'   => $p['amount'],
                'date'     => $p['date'],
                'orNumber' => $p['orNumber'],
                'method'   => $p['method'],
            ];
        }
        $sched = ihc_schedule($contract, $scheduleRows);

        // Server-sent client notifications (email + SMS reminders, receipts).
        // The ack rows (channel ack_email) are clerk-facing and stay out of this feed.
        $notifStmt = $pdo->prepare(
            "SELECT id, reminder_type, subject, status, sent_at, channel FROM notifications_logs
             WHERE contract_id IN ($placeholders) AND channel IN ('email','sms') AND status = 'sent'
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
                'channel' => ($n['channel'] ?? 'email') === 'sms' ? 'SMS' : 'Email',
                'title' => $isReceipt ? 'Payment receipt emailed' : 'Payment reminder sent',
                'message' => (string)$n['subject'],
                'time' => $n['sent_at'],
            ];
        }

        // --- Holding Fees & Reservation Fees (unified transaction history §8) ---
        // Best-effort: tables may not exist yet if migration 005 not run.
        $holdingFees = [];
        $reservationFees = [];
        $propertyUnit = null;
        try {
            $hfStmt = $pdo->prepare('SELECT id, amount, payment_method, payment_date, reference_number, or_number, start_date, expiration_date, status, remarks, created_at FROM holding_fees WHERE contract_id=? ORDER BY payment_date, id');
            $hfStmt->execute([$id]);
            foreach ($hfStmt->fetchAll() as $r) {
                $holdingFees[] = [
                    'id' => (int)$r['id'],
                    'amount' => (float)$r['amount'],
                    'method' => $r['payment_method'],
                    'date' => $r['payment_date'],
                    'referenceNumber' => $r['reference_number'],
                    'orNumber' => $r['or_number'],
                    'startDate' => $r['start_date'],
                    'expirationDate' => $r['expiration_date'],
                    'status' => $r['status'],
                    'remarks' => $r['remarks'],
                    'createdAt' => $r['created_at'],
                ];
            }
        } catch (Throwable $e) { /* table missing -> empty */ }
        try {
            $rfStmt = $pdo->prepare('SELECT id, amount, payment_method, payment_date, reference_number, or_number, status, remarks, holding_fee_id, created_at FROM reservation_fees WHERE contract_id=? ORDER BY payment_date, id');
            $rfStmt->execute([$id]);
            foreach ($rfStmt->fetchAll() as $r) {
                $reservationFees[] = [
                    'id' => (int)$r['id'],
                    'amount' => (float)$r['amount'],
                    'method' => $r['payment_method'],
                    'date' => $r['payment_date'],
                    'referenceNumber' => $r['reference_number'],
                    'orNumber' => $r['or_number'],
                    'status' => $r['status'],
                    'remarks' => $r['remarks'],
                    'holdingFeeId' => $r['holding_fee_id'] ? (int)$r['holding_fee_id'] : null,
                    'createdAt' => $r['created_at'],
                ];
            }
        } catch (Throwable $e) { }
        // Property / unit status (§13)
        try {
            $uStmt = $pdo->prepare('SELECT id, display_label, status FROM property_units WHERE current_contract_id=? OR display_label=? LIMIT 1');
            $uStmt->execute([$id, (string)$contract['property_address']]);
            $uRow = $uStmt->fetch();
            if ($uRow) $propertyUnit = ['id'=>(int)$uRow['id'],'label'=>$uRow['display_label'],'status'=>$uRow['status']];
        } catch (Throwable $e) { }

        // Unified history chronologically (§8)
        $history = [];
        foreach ($holdingFees as $h) $history[] = ['type'=>'Holding Fee','transactionType'=>'HOLDING_FEE','date'=>$h['date'],'amount'=>$h['amount'],'status'=>$h['status'],'orNumber'=>$h['orNumber'],'referenceNumber'=>$h['referenceNumber'],'createdAt'=>$h['createdAt']];
        foreach ($reservationFees as $r) $history[] = ['type'=>'Reservation Fee','transactionType'=>'RESERVATION_FEE','date'=>$r['date'],'amount'=>$r['amount'],'status'=>$r['status'],'orNumber'=>$r['orNumber'],'referenceNumber'=>$r['referenceNumber'],'createdAt'=>$r['createdAt']];
        foreach ($payments as $p) $history[] = ['type'=>'Payment','transactionType'=>'PAYMENT','date'=>$p['date'],'amount'=>$p['amount'],'status'=>'PAID','orNumber'=>$p['orNumber'],'referenceNumber'=>$p['orNumber'],'createdAt'=>$p['date']];
        usort($history, function($a,$b){ $c=strcmp($a['date'],$b['date']); return $c!==0?$c:strcmp($a['createdAt'],$b['createdAt']); });
        $totalPaid = 0.0;
        foreach ($history as $h) if (in_array($h['status'],['PAID','CONVERTED'],true)) $totalPaid += (float)$h['amount'];

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
                'discountAmount' => (float)($contract['discount_amount'] ?? 0),
                'netPrice' => max(0, (float)$contract['total_contract_price'] - (float)($contract['discount_amount'] ?? 0)),
                'downpayment' => (float)$contract['downpayment'],
                'terms' => (int)$contract['installment_terms'],
                'startDate' => $contract['start_date'],
                // Downpayment plan + financing terms saved by the New Contract
                // form. Rows created before those columns existed have no value
                // yet, hence the defaults.
                'dpMode' => (isset($contract['dp_mode']) && $contract['dp_mode'] !== null && $contract['dp_mode'] !== '') ? (string)$contract['dp_mode'] : 'lump',
                'dpTerms' => (isset($contract['dp_terms']) && $contract['dp_terms'] !== null) ? (int)$contract['dp_terms'] : null,
                'bank' => isset($contract['bank_name']) ? (string)$contract['bank_name'] : '',
                'annualRate' => isset($contract['annual_interest_rate']) ? (float)$contract['annual_interest_rate'] : 0.0,
                'officerId' => $contract['officer_id'],
                'officerName' => $contract['officer_name'],
            ],
            'payments' => $payments,
            'schedule' => $sched['installments'],
            'totals' => [
                'paid'        => $sched['paid'],
                'outstanding' => $sched['outstanding'],
                'settled'     => $sched['settled'],
            ],
            'holdingFees' => $holdingFees,
            'reservationFees' => $reservationFees,
            'propertyUnit' => $propertyUnit,
            'history' => $history,
            'totalPaid' => round($totalPaid,2),
            'notifications' => $notifications,
        ]);
        exit;
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
