<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/soa-builder.php';

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    soa_ensure_schema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

function soa_request_data(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $requestData = $method === 'POST' ? soa_request_data() : [];
    $action = trim((string)($_GET['action'] ?? ($requestData['action'] ?? '')));

    if ($method === 'GET' && $action === 'settings') {
        $settings = $pdo->query('SELECT * FROM soa_settings WHERE id = 1 LIMIT 1')->fetch();
        echo json_encode(['success' => true, 'settings' => $settings ?: []]);
        exit;
    }

    if ($method === 'GET' && $action === 'document') {
        $contractId = soa_normalize_contract_id($_GET['contractId'] ?? '');
        if ($contractId === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid contract reference is required.']);
            exit;
        }
        $actorId = trim((string)($_GET['actorId'] ?? ''));
        $actorEmail = trim((string)($_GET['actorEmail'] ?? ''));
        echo json_encode(soa_build_document($pdo, $contractId, $actorId, null, $actorEmail));
        exit;
    }

    if ($method === 'POST' && $action === 'settings_update') {
        $data = $requestData;
        $companyName = trim((string)($data['companyName'] ?? ''));
        $companyAddress = trim((string)($data['companyAddress'] ?? ''));
        $companyContact = trim((string)($data['companyContact'] ?? ''));
        $logoPath = trim((string)($data['logoPath'] ?? 'img/ihc logo.png'));
        $notes = trim((string)($data['importantNotes'] ?? ''));
        $notedName = trim((string)($data['notedByName'] ?? ''));
        $notedPosition = trim((string)($data['notedByPosition'] ?? ''));
        $notedContact = trim((string)($data['notedByContact'] ?? ''));
        $penaltyRate = $data['penaltyRate'] ?? 0;
        $validityDays = $data['validityDays'] ?? 7;
        $updatedBy = trim((string)($data['updatedBy'] ?? ''));

        if ($companyName === '' || mb_strlen($companyName) > 255) {
            throw new RuntimeException('Company name is required and must be 255 characters or fewer.');
        }
        if (!is_numeric($penaltyRate) || (float)$penaltyRate < 0 || (float)$penaltyRate > 100) {
            throw new RuntimeException('Penalty rate must be between 0 and 100 percent.');
        }
        if (!ctype_digit((string)$validityDays) || (int)$validityDays < 0 || (int)$validityDays > 365) {
            throw new RuntimeException('SOA validity must be between 0 and 365 days.');
        }
        foreach ([
            'companyAddress' => [$companyAddress, 500],
            'companyContact' => [$companyContact, 255],
            'logoPath' => [$logoPath, 500],
            'notedByName' => [$notedName, 255],
            'notedByPosition' => [$notedPosition, 255],
            'notedByContact' => [$notedContact, 255],
        ] as $label => [$value, $maxLength]) {
            if (mb_strlen($value) > $maxLength) throw new RuntimeException($label . ' is too long.');
        }

        $stmt = $pdo->prepare(
            'UPDATE soa_settings SET
                company_name = ?, company_address = ?, company_contact = ?, logo_path = ?,
                penalty_rate_percent = ?, important_notes = ?, noted_by_name = ?,
                noted_by_position = ?, noted_by_contact = ?, validity_days = ?, updated_by = ?
             WHERE id = 1'
        );
        $stmt->execute([
            $companyName,
            $companyAddress !== '' ? $companyAddress : null,
            $companyContact !== '' ? $companyContact : null,
            $logoPath !== '' ? $logoPath : 'img/ihc logo.png',
            round((float)$penaltyRate, 4),
            $notes !== '' ? $notes : null,
            $notedName !== '' ? $notedName : null,
            $notedPosition !== '' ? $notedPosition : null,
            $notedContact !== '' ? $notedContact : null,
            (int)$validityDays,
            $updatedBy !== '' ? $updatedBy : null,
        ]);

        echo json_encode(['success' => true, 'message' => 'SOA settings updated.']);
        exit;
    }

    if ($method === 'POST' && $action === 'charge_save') {
        $data = $requestData;
        $contractId = soa_normalize_contract_id($data['contractId'] ?? '');
        if ($contractId === null) throw new RuntimeException('A valid contract reference is required.');
        $contract = $pdo->prepare('SELECT id FROM contracts WHERE id = ? LIMIT 1');
        $contract->execute([$contractId]);
        if (!$contract->fetch()) throw new RuntimeException('Contract not found.');

        $type = trim((string)($data['chargeType'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $amount = $data['amount'] ?? null;
        $amountPaid = $data['amountPaid'] ?? 0;
        $chargeDate = trim((string)($data['chargeDate'] ?? date('Y-m-d')));
        $dueDate = trim((string)($data['dueDate'] ?? ''));
        $status = strtoupper(trim((string)($data['status'] ?? 'PENDING')));
        $orNumber = trim((string)($data['orNumber'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));
        $createdBy = trim((string)($data['createdBy'] ?? ''));

        if ($type === '' || mb_strlen($type) > 100) throw new RuntimeException('Charge type is required and must be 100 characters or fewer.');
        if (mb_strlen($description) > 500 || mb_strlen($orNumber) > 100) throw new RuntimeException('Charge description or OR/reference number is too long.');
        if (!is_numeric($amount) || (float)$amount <= 0) throw new RuntimeException('Charge amount must be greater than zero.');
        if (!is_numeric($amountPaid) || (float)$amountPaid < 0 || (float)$amountPaid > (float)$amount) {
            throw new RuntimeException('Amount paid must be between zero and the charge amount.');
        }
        $date = DateTime::createFromFormat('!Y-m-d', $chargeDate);
        if (!$date || $date->format('Y-m-d') !== $chargeDate) throw new RuntimeException('Invalid charge date.');
        if ($dueDate !== '') {
            $due = DateTime::createFromFormat('!Y-m-d', $dueDate);
            if (!$due || $due->format('Y-m-d') !== $dueDate) throw new RuntimeException('Invalid charge due date.');
        }
        if (!in_array($status, ['PENDING', 'PARTIALLY_PAID', 'PAID', 'VOID'], true)) {
            throw new RuntimeException('Invalid additional-charge status.');
        }
        if ((float)$amountPaid >= (float)$amount) $status = 'PAID';

        $stmt = $pdo->prepare(
            'INSERT INTO additional_charges
                (contract_id,charge_type,description,amount,amount_paid,charge_date,due_date,status,or_number,remarks,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $contractId,
            $type,
            $description !== '' ? $description : null,
            round((float)$amount, 2),
            round((float)$amountPaid, 2),
            $chargeDate,
            $dueDate !== '' ? $dueDate : null,
            $status,
            $orNumber !== '' ? $orNumber : null,
            $remarks !== '' ? $remarks : null,
            $createdBy !== '' ? $createdBy : null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Additional charge saved.',
            'id' => (int)$pdo->lastInsertId(),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown or unsupported SOA action.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
