<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/**
 * Holding fees — the clerk dashboard's "Payments › Holding Fees" tab.
 *
 * This feature used to live ONLY in localStorage (key IHC_HOLDING_FEES_<officer>),
 * so a fee recorded in one browser was invisible everywhere else. It now reads
 * and writes MySQL here; the browser keeps its localStorage copy purely as the
 * offline cache, exactly like the client portal does.
 *
 *   GET  ?officerId=2                     -> { success, fees: [...] }
 *   POST { action:'save', officerId, fee } -> { success, fee: <row> }  (insert or update)
 *
 * A holding fee is linked to the client through `contract_id` (nullable: the
 * form allows "New buyer (no contract yet)"), and scoped to the officer who
 * recorded it.
 */

const HF_STATUSES = ['pending', 'paid', 'cancelled'];
const HF_METHODS = ['Bank Wire / Online Deposit', 'Over-the-Counter Cashier', 'Post-Dated Check (PDC)', 'GCash / Maya'];
const HF_MAX_PROOF_CHARS = 1200000; // 600 KB file → base64 ≈ 800 KB, with headroom.

function hf_connect(): PDO {
    return new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
}

// Self-healing DDL so a fresh or older database works without a manual
// migration step (same pattern as backend/db.php's client_accounts table).
function hf_ensure_table(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS holding_fees (
            id INT NOT NULL AUTO_INCREMENT,
            officer_id VARCHAR(100) NULL,
            contract_id INT NULL,
            client_name VARCHAR(255) NOT NULL,
            property_address VARCHAR(500) NOT NULL DEFAULT "",
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(100) NOT NULL,
            payment_date DATE NOT NULL,
            or_number VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            proof_name VARCHAR(255) NULL,
            proof_data_url MEDIUMTEXT NULL,
            proof_is_image TINYINT(1) NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_holding_fees_officer (officer_id),
            KEY idx_holding_fees_contract (contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
}

/** DB row -> the exact shape the clerk dashboard keeps in `holdingFees`. */
function hf_to_public(array $r): array {
    $proof = null;
    if (($r['proof_data_url'] ?? '') !== '' && $r['proof_data_url'] !== null) {
        $proof = [
            'name' => (string)($r['proof_name'] ?: 'proof'),
            'dataUrl' => (string)$r['proof_data_url'],
            'isImage' => (bool)$r['proof_is_image'],
        ];
    }
    return [
        'id' => (int)$r['id'],
        'officerId' => $r['officer_id'] !== null ? (string)$r['officer_id'] : null,
        'contractId' => $r['contract_id'] !== null ? (int)$r['contract_id'] : null,
        'clientName' => (string)$r['client_name'],
        'propertyAddress' => (string)$r['property_address'],
        'amount' => (float)$r['amount'],
        'paymentMethod' => (string)$r['payment_method'],
        'paymentDate' => (string)$r['payment_date'],
        'orNumber' => $r['or_number'] !== null ? (string)$r['or_number'] : '',
        'status' => (string)$r['status'],
        'proof' => $proof,
        'remarks' => $r['remarks'] !== null ? (string)$r['remarks'] : '',
        'createdAt' => str_replace(' ', 'T', (string)$r['created_at']),
    ];
}

/**
 * Validate one fee payload against the same rules the modal enforces, and
 * normalise it into bindable values.
 */
function hf_validate(array $fee): array {
    $clientName = trim((string)($fee['clientName'] ?? ''));
    if ($clientName === '') throw new RuntimeException('Client name is required.');
    if (mb_strlen($clientName) > 255) throw new RuntimeException('Client name is too long.');

    $property = trim((string)($fee['propertyAddress'] ?? ''));
    if (mb_strlen($property) > 500) throw new RuntimeException('Property / unit is too long.');

    $contractId = $fee['contractId'] ?? null;
    $contractId = ($contractId === null || $contractId === '' || !is_numeric($contractId)) ? null : (int)$contractId;
    if ($contractId === null && $property === '') {
        throw new RuntimeException('Property / unit is required for a buyer with no contract yet.');
    }

    $amount = $fee['amount'] ?? null;
    if (!is_numeric($amount) || (float)$amount <= 0) throw new RuntimeException('Enter a valid holding fee amount.');

    $method = trim((string)($fee['paymentMethod'] ?? ''));
    if ($method === '' || !in_array($method, HF_METHODS, true)) throw new RuntimeException('Select the payment method.');

    $date = trim((string)($fee['paymentDate'] ?? ''));
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('Invalid payment date.');

    $status = trim((string)($fee['status'] ?? 'pending'));
    if (!in_array($status, HF_STATUSES, true)) throw new RuntimeException('Invalid payment status.');

    $orNumber = trim((string)($fee['orNumber'] ?? ''));
    if (mb_strlen($orNumber) > 100) throw new RuntimeException('OR / reference number is too long.');
    if ($status === 'paid' && $orNumber === '') throw new RuntimeException('OR / reference number is required for paid fees.');

    $remarks = trim((string)($fee['remarks'] ?? ''));

    // Proof of payment: { name, dataUrl, isImage } or null.
    $proof = $fee['proof'] ?? null;
    $proofName = null;
    $proofData = null;
    $proofIsImage = null;
    if (is_array($proof) && !empty($proof['dataUrl'])) {
        $dataUrl = (string)$proof['dataUrl'];
        if (strlen($dataUrl) > HF_MAX_PROOF_CHARS) {
            throw new RuntimeException('Proof / receipt is too large — keep it under 600 KB.');
        }
        if (strpos($dataUrl, 'data:') !== 0) throw new RuntimeException('Proof / receipt must be an uploaded file.');
        $proofName = mb_substr(trim((string)($proof['name'] ?: 'proof')), 0, 255);
        $proofData = $dataUrl;
        $proofIsImage = !empty($proof['isImage']) ? 1 : 0;
    }

    return [
        'contractId' => $contractId,
        'clientName' => $clientName,
        'propertyAddress' => $property,
        'amount' => round((float)$amount, 2),
        'paymentMethod' => $method,
        'paymentDate' => $date,
        'orNumber' => $orNumber !== '' ? $orNumber : null,
        'status' => $status,
        'proofName' => $proofName,
        'proofData' => $proofData,
        'proofIsImage' => $proofIsImage,
        'remarks' => $remarks !== '' ? $remarks : null,
    ];
}

try {
    $pdo = hf_connect();
    hf_ensure_table($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $officerId = trim((string)($_GET['officerId'] ?? ''));
        if ($officerId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM holding_fees WHERE officer_id = ? ORDER BY created_at DESC, id DESC');
            $stmt->execute([$officerId]);
        } else {
            $stmt = $pdo->query('SELECT * FROM holding_fees ORDER BY created_at DESC, id DESC');
        }
        $fees = [];
        foreach ($stmt->fetchAll() as $row) $fees[] = hf_to_public($row);
        echo json_encode(['success' => true, 'fees' => $fees]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only GET or POST requests are allowed.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON request.');

    $action = trim((string)($data['action'] ?? 'save'));
    if ($action !== 'save') throw new RuntimeException('Unknown action.');

    $officerId = trim((string)($data['officerId'] ?? ''));
    $fee = is_array($data['fee'] ?? null) ? $data['fee'] : [];
    $values = hf_validate($fee);

    // A link to a contract must be a real one — otherwise the fee would point
    // at a client that does not exist.
    if ($values['contractId'] !== null) {
        $chk = $pdo->prepare('SELECT id FROM contracts WHERE id = ?');
        $chk->execute([$values['contractId']]);
        if (!$chk->fetch()) throw new RuntimeException('Contract not found in database.');
    }

    $params = [
        ':officer_id' => $officerId !== '' ? $officerId : null,
        ':contract_id' => $values['contractId'],
        ':client_name' => $values['clientName'],
        ':property_address' => $values['propertyAddress'],
        ':amount' => $values['amount'],
        ':payment_method' => $values['paymentMethod'],
        ':payment_date' => $values['paymentDate'],
        ':or_number' => $values['orNumber'],
        ':status' => $values['status'],
        ':proof_name' => $values['proofName'],
        ':proof_data_url' => $values['proofData'],
        ':proof_is_image' => $values['proofIsImage'],
        ':remarks' => $values['remarks'],
    ];

    $localId = $fee['id'] ?? null;
    $updated = false;
    if (is_numeric($localId) && (int)$localId > 0) {
        // Scope the update to this officer so an offline row's guessed id can
        // never overwrite someone else's record; 0 matching rows -> insert.
        // Each bound value is used exactly once (PDO rejects a repeated
        // named placeholder when emulation is off).
        $setSql = 'contract_id=:contract_id, client_name=:client_name, property_address=:property_address,
                   amount=:amount, payment_method=:payment_method, payment_date=:payment_date, or_number=:or_number,
                   status=:status, proof_name=:proof_name, proof_data_url=:proof_data_url, proof_is_image=:proof_is_image,
                   remarks=:remarks';
        $whereSql = ' WHERE id=:id';
        $bind = $params + [':id' => (int)$localId];
        if ($officerId !== '') {
            $setSql = 'officer_id=:officer_id, ' . $setSql;
            $whereSql .= ' AND officer_id=:scope_officer_id';
            $bind[':scope_officer_id'] = $officerId;
        }
        $stmt = $pdo->prepare('UPDATE holding_fees SET ' . $setSql . $whereSql);
        $stmt->execute($bind);
        $updated = $stmt->rowCount() > 0;
        $id = $updated ? (int)$localId : null;
    }

    if (!$updated) {
        $sql = 'INSERT INTO holding_fees (officer_id,contract_id,client_name,property_address,amount,payment_method,
                payment_date,or_number,status,proof_name,proof_data_url,proof_is_image,remarks)
                VALUES (:officer_id,:contract_id,:client_name,:property_address,:amount,:payment_method,
                :payment_date,:or_number,:status,:proof_name,:proof_data_url,:proof_is_image,:remarks)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = (int)$pdo->lastInsertId();
    }

    $rowStmt = $pdo->prepare('SELECT * FROM holding_fees WHERE id = ?');
    $rowStmt->execute([$id]);
    $row = $rowStmt->fetch();
    if (!$row) throw new RuntimeException('Holding fee could not be saved.');

    echo json_encode([
        'success' => true,
        'message' => $updated ? 'Holding fee updated in the database.' : 'Holding fee saved to the database.',
        'fee' => hf_to_public($row),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
