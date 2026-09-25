<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
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

    if ($method === 'GET' && $action === 'reminder_queue') {
        $mode = strtolower(trim((string)($_GET['mode'] ?? 'upcoming')));
        $channel = strtolower(trim((string)($_GET['channel'] ?? 'email')));
        $today = trim((string)($_GET['today'] ?? date('Y-m-d')));
        $daysRaw = (string)($_GET['days'] ?? '3');
        if (!in_array($mode, ['upcoming', 'overdue'], true)) throw new RuntimeException('Invalid reminder queue mode.');
        if (!in_array($channel, ['email', 'sms'], true)) throw new RuntimeException('Invalid reminder channel.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if (!$date || $date->format('Y-m-d') !== $today) throw new RuntimeException('Invalid reminder queue date.');
        if (!ctype_digit($daysRaw)) throw new RuntimeException('Invalid reminder queue window.');
        $days = max(0, min(31, (int)$daysRaw));
        $until = $date->modify('+' . $days . ' days');
        $rows = [];

        // This queue deliberately uses the same document/schedule allocator as
        // the dashboards. The old cron query selected start_date/downpayment
        // directly, which sent installment reminders with downpayment values.
        foreach ($pdo->query('SELECT id FROM contracts ORDER BY id') as $contractRow) {
            $contractId = (int)$contractRow['id'];
            try {
                $document = soa_build_document($pdo, $contractId, '', $today);
            } catch (Throwable $e) {
                // One malformed legacy contract must not suppress reminders
                // for every other contract in the daily queue.
                continue;
            }
            $next = is_array($document['summary']['nextPayment'] ?? null)
                ? $document['summary']['nextPayment']
                : null;
            if ($next === null) continue;
            $dueDate = (string)($next['dueDate'] ?? '');
            if ($mode === 'upcoming' && ($dueDate < $today || $dueDate > $until->format('Y-m-d'))) continue;
            if ($mode === 'overdue' && $dueDate >= $today) continue;

            $reminderType = $mode === 'upcoming' ? 'due_soon' : 'overdue';
            try {
                $dedupSql = "SELECT COUNT(*) FROM notifications_logs
                    WHERE contract_id IN (?, ?, ?)
                      AND channel = ? AND reminder_type = ? AND status = 'sent'";
                // Pass all three ID variants as UTF-8 strings. This avoids
                // mixing a native-prepared binary CAST/CONCAT expression with
                // the utf8mb4 notifications_logs column.
                $dedupParams = [
                    (string)$contractId,
                    'IHC-' . $contractId,
                    'CON-' . $contractId,
                    $channel,
                    $reminderType,
                ];
                if ($mode === 'overdue') {
                    $dedupSql .= ' AND DATE(sent_at) = ?';
                    $dedupParams[] = $today;
                } else {
                    // A contract has many installment due dates. Suppress a
                    // duplicate only for this exact schedule row, rather than
                    // allowing an old downpayment notice to block every later
                    // installment notice forever.
                    $dedupSql .= ' AND due_date = ?';
                    $dedupParams[] = $dueDate;
                }
                $dedup = $pdo->prepare($dedupSql);
                $dedup->execute($dedupParams);
                if ((int)$dedup->fetchColumn() > 0) continue;
            } catch (Throwable $e) {
                // A missing legacy notifications_logs table must not prevent
                // delivery; logNotification already treats logging as best effort.
            }

            $summary = soa_build_email_summary($document);
            $rows[] = [
                'contract_id' => $contractId,
                'contract_code' => 'IHC-' . $contractId,
                'client_name' => (string)($document['client']['name'] ?? ''),
                'client_email' => (string)($document['client']['email'] ?? ''),
                'cellphone_number' => (string)($document['client']['phone'] ?? ''),
                'payment_type' => (string)($next['label'] ?? 'Scheduled Payment'),
                'amount_due' => soa_round($next['amount'] ?? 0),
                'due_date' => $dueDate,
                'soa_summary' => $summary,
            ];
        }

        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'channel' => $channel,
            'today' => $today,
            'rows' => $rows,
        ]);
        exit;
    }

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

    if ($method === 'GET' && $action === 'summary') {
        $contractId = soa_normalize_contract_id($_GET['contractId'] ?? '');
        if ($contractId === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid contract reference is required.']);
            exit;
        }
        $actorId = trim((string)($_GET['actorId'] ?? ''));
        $actorEmail = trim((string)($_GET['actorEmail'] ?? ''));
        $document = soa_build_document($pdo, $contractId, $actorId, null, $actorEmail);
        $paymentIdRaw = trim((string)($_GET['paymentId'] ?? $_GET['payment_id'] ?? ''));
        $paymentTransaction = null;
        if ($paymentIdRaw !== '') {
            if (!ctype_digit($paymentIdRaw) || (int)$paymentIdRaw <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid payment transaction id.']);
                exit;
            }
            $paymentTransaction = soa_build_payment_transaction($pdo, $contractId, (int)$paymentIdRaw, $document);
            if ($paymentTransaction === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Payment transaction was not found for this contract.']);
                exit;
            }
        }
        echo json_encode([
            'success' => true,
            'paymentId' => $paymentTransaction['paymentId'] ?? null,
            'paymentTransaction' => $paymentTransaction,
            'summary' => soa_build_email_summary($document, $paymentTransaction),
        ]);
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
