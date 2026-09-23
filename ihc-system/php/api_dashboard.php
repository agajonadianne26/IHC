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

$stmt = $pdo->prepare('SELECT c.* FROM contracts c WHERE c.officer_id = ? ORDER BY c.id');
$stmt->execute([$officerId]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC); // FETCH_ASSOC keeps the array clean

// payments.contract_id stores prefixed codes ('CON-19', sometimes a bare '7'
// or 'IHC-14'), so fold the rows onto integer contract ids in PHP — never
// with a SQL JOIN against contracts (mixed collations blow up at runtime;
// see AGENTS.md). Ordering by date keeps the chronological allocation below
// identical to php/api_admin.php's schedule.
$paidByContract = [];   // contract id => [ ['date' => 'YYYY-MM-DD', 'amount' => float], ... ]
foreach ($pdo->query('SELECT contract_id, amount, date_collected FROM payments ORDER BY date_collected, id') as $p) {
    if (!preg_match('/(\d+)\s*$/', (string)$p['contract_id'], $m)) continue;
    $paidByContract[(int)$m[1]][] = ['date' => (string)$p['date_collected'], 'amount' => (float)$p['amount']];
}

// Same month arithmetic the Admin schedule uses: clamp the day to the last
// day of the target month (start_date 2026-01-31 + 1 month -> 2026-02-28).
$addMonths = function (string $date, int $months): string {
    [$y, $mo, $d] = array_map('intval', explode('-', $date));
    $mo += $months;
    $y += intdiv($mo - 1, 12);
    $mo = (($mo - 1) % 12 + 12) % 12 + 1;
    $lastDay = (int)date('t', mktime(0, 0, 0, $mo, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $mo, min($d, $lastDay));
};

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
    $tcp = (float)($c['total_contract_price'] ?? 0);
    $dp = (float)($c['downpayment'] ?? 0);
    $terms = max(1, (int)($c['installment_terms'] ?? 1));
    $start = (string)$c['start_date'];

    // Installment 1 = downpayment at start_date, then one amortization per
    // term month; the last one absorbs the rounding remainder. Keep this in
    // sync with php/api_admin.php ($action = 'schedule') so the clerk and
    // admin dashboards always agree on what is due.
    $installments = [['kind' => 'downpayment', 'no' => null, 'dueDate' => $start, 'amount' => round($dp, 2)]];
    $amortTotal = round($tcp - $dp, 2);
    $per = floor($amortTotal / $terms * 100) / 100;
    for ($i = 1; $i <= $terms; $i++) {
        $amt = ($i === $terms) ? round($amortTotal - $per * ($terms - 1), 2) : $per;
        $installments[] = ['kind' => 'installment', 'no' => $i, 'dueDate' => $addMonths($start, $i), 'amount' => $amt];
    }

    // Allocate posted payments chronologically: a paid downpayment rolls the
    // contract forward to its next installment, a partial payment keeps the
    // balance showing as the downpayment, and a fully covered contract is Paid.
    $paidTotal = round(array_sum(array_column($paidByContract[$cid] ?? [], 'amount')), 2);
    $paidLeft = $paidTotal;
    $next = null;
    foreach ($installments as $inst) {
        if ($paidLeft >= $inst['amount'] - 0.009) {
            $paidLeft -= $inst['amount'];
            continue;
        }
        $next = [
            'kind'    => $inst['kind'],
            'no'      => $inst['no'],
            'dueDate' => $inst['dueDate'],
            'amount'  => max(0.0, round($inst['amount'] - $paidLeft, 2)),
        ];
        break;
    }

    $formattedClients[] = [
        'accountCode'     => 'CON-' . $c['id'],          // Using the primary key 'id'
        'contractId'      => (int)$c['id'],
        'name'            => $c['client_name'],          // Matches your DB
        'email'           => $c['email'],                // Matches your DB
        'phone'           => $c['cellphone_number'],     // matches your DB
        'propertyAddress' => $c['property_address'] ?? null,
        'totalPrice'      => $tcp,
        'terms'           => $terms,
        'amountPaid'      => $paidTotal,
        'nextDueDate'     => $next ? $next['dueDate'] : $start,
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
