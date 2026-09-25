<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

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

require_once __DIR__ . '/installment-schedule.php';
require_once __DIR__ . '/payment-or-number.php';
require_once __DIR__ . '/soa-builder.php';

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=ihc;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );

    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON request.');

    $contractId        = trim((string)($data['contractId'] ?? ''));
    $method            = trim((string)($data['method'] ?? ''));
    $dateCollected     = trim((string)($data['dateCollected'] ?? ''));
    $remarks           = trim((string)($data['remarks'] ?? ''));
    $checkNumber       = trim((string)($data['checkNumber'] ?? ''));
    $externalReference = trim((string)($data['externalReference'] ?? ''));
    $applicationMode   = strtolower(trim((string)($data['applicationMode'] ?? 'auto')));
    $receiptRequested  = !array_key_exists('receiptRequested', $data)
        || filter_var($data['receiptRequested'], FILTER_VALIDATE_BOOLEAN);
    $amount            = $data['amount'] ?? null;
    $postedBy          = trim((string)($data['postedBy'] ?? ''));
    $requestedKind     = strtolower(trim((string)($data['paymentKind'] ?? '')));
    $expectedNoRaw     = $data['expectedInstallmentNo'] ?? null;
    $expectedInstallmentNo = ($expectedNoRaw === null || $expectedNoRaw === '') ? null : (int)$expectedNoRaw;
    $expectedSoaTotal = $data['expectedSoaTotal'] ?? null;
    $expectedPrincipalDue = $data['expectedPrincipalDue'] ?? null;
    if ($requestedKind === 'installment payment') $requestedKind = 'installment';
    if ($requestedKind !== '' && !in_array($requestedKind, ['downpayment', 'installment'], true)) {
        throw new RuntimeException('Invalid payment type.');
    }
    if (!in_array($applicationMode, ['auto', 'current'], true)) {
        throw new RuntimeException('Invalid ledger application mode.');
    }

    if ($contractId === '') throw new RuntimeException('Missing contract reference.');
    if ($method === '') throw new RuntimeException('Payment method is required.');
    if (mb_strlen($checkNumber) > 100) throw new RuntimeException('Check number must be 100 characters or fewer.');
    if (mb_strlen($externalReference) > 100) throw new RuntimeException('External reference must be 100 characters or fewer.');
    if ($method === 'Post-Dated Check (PDC)' && $checkNumber === '') {
        throw new RuntimeException('Check number is required for a Post-Dated Check.');
    }
    foreach (['expectedSoaTotal' => $expectedSoaTotal, 'expectedPrincipalDue' => $expectedPrincipalDue] as $label => $value) {
        if ($value !== null && $value !== '' && (!is_numeric($value) || (float)$value < 0)) {
            throw new RuntimeException('Invalid ' . $label . ' value.');
        }
    }
    if ($amount === null || $amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        throw new RuntimeException('Invalid payment amount.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $dateCollected);
    if (!$date || $date->format('Y-m-d') !== $dateCollected) {
        throw new RuntimeException('Invalid date collected.');
    }

    // Kunin ang numeric id mula sa "CON-7" para ma-verify sa contracts table.
    $numericId = null;
    if (preg_match('/(\d+)\s*$/', $contractId, $m)) {
        $numericId = (int)$m[1];
    }
    if ($numericId === null) {
        throw new RuntimeException('Contract not found in database.');
    }

    ihc_ensure_payment_or_counters($pdo);
    soa_ensure_schema($pdo);
    $pdo->beginTransaction();

    try {
        // Lock the contract while classifying the payment and allocating its OR.
        $check = $pdo->prepare('SELECT * FROM contracts WHERE id = ? FOR UPDATE');
        $check->execute([$numericId]);
        $contract = $check->fetch();
        if (!$contract) throw new RuntimeException('Contract not found in database.');

        // Use the same schedule allocator as every dashboard so the server, not
        // the browser, decides whether this transaction covers a downpayment or
        // an installment. Prefix legacy CON-7 / IHC-14 IDs in PHP as elsewhere.
        $existingPayments = [];
        $principalByPayment = [];
        foreach ($pdo->query('SELECT payment_id, SUM(principal_amount) AS principal_amount FROM payment_allocations GROUP BY payment_id') as $allocationRow) {
            $principalByPayment[(int)$allocationRow['payment_id']] = (float)$allocationRow['principal_amount'];
        }
        $paymentRows = $pdo->query(
            'SELECT id, contract_id, amount, date_collected, or_number, payment_method
             FROM payments ORDER BY date_collected, id'
        );
        foreach ($paymentRows as $payment) {
            if (!preg_match('/(\d+)\s*$/', (string)$payment['contract_id'], $paymentMatch)) continue;
            if ((int)$paymentMatch[1] !== $numericId) continue;
            $existingPayments[] = [
                'amount' => (float)$payment['amount'],
                'principalAmount' => $principalByPayment[(int)$payment['id']] ?? null,
                'date' => (string)$payment['date_collected'],
                'orNumber' => $payment['or_number'] !== null ? (string)$payment['or_number'] : null,
                'method' => $payment['payment_method'] !== null ? (string)$payment['payment_method'] : null,
            ];
        }

        $schedule = ihc_schedule($contract, $existingPayments);
        if ($schedule['next'] === null) {
            throw new RuntimeException('This contract is already settled in full.');
        }
        $paymentKind = (string)$schedule['next']['kind'];
        if ($requestedKind !== '' && $requestedKind !== $paymentKind) {
            throw new RuntimeException('This payment queue changed. Refresh the dashboard before posting again.');
        }
        $currentInstallmentNo = $schedule['next']['no'] !== null ? (int)$schedule['next']['no'] : null;
        if ($expectedInstallmentNo !== null && $expectedInstallmentNo !== $currentInstallmentNo) {
            throw new RuntimeException('This installment was already paid by another transaction. Refresh the dashboard before posting again.');
        }

        $currentPrincipalDue = round((float)$schedule['next']['amount'], 2);
        if ($expectedPrincipalDue !== null && $expectedPrincipalDue !== ''
            && abs((float)$expectedPrincipalDue - $currentPrincipalDue) > 0.009) {
            throw new RuntimeException('The SOA installment balance changed. Refresh the payment form before posting.');
        }
        $currentSoa = soa_build_document($pdo, $numericId, $postedBy);
        $currentSoaTotal = round((float)($currentSoa['amountDue']['total'] ?? 0), 2);
        if ($expectedSoaTotal !== null && $expectedSoaTotal !== ''
            && abs((float)$expectedSoaTotal - $currentSoaTotal) > 0.009) {
            throw new RuntimeException('The SOA total changed after the form was opened. Refresh before posting.');
        }
        if ($applicationMode === 'current' && (float)$amount > $currentPrincipalDue + 0.009) {
            throw new RuntimeException('This amount exceeds the current installment balance. Use Auto-apply to advance future installments.');
        }
        // Preserve the finance amounts assessed immediately before this
        // transaction. They are informational allocation components; the
        // existing ledger still applies only principal_amount to the schedule.
        $transactionInterest = round((float)($currentSoa['amountDue']['interest'] ?? 0), 2);
        $transactionPenalty = round((float)($currentSoa['amountDue']['penalty'] ?? 0), 2);

        $orNumber = ihc_allocate_payment_or_number($pdo, $paymentKind, (int)$date->format('Y'));

        // Preserve the exact payment-to-installment allocation. A single cash
        // transaction may cover several schedule rows, while any amount left
        // after the net contract price is recorded as additional equity.
        $remainingPayment = round((float)$amount, 2);
        $allocations = [];
        $allocatableInstallments = $schedule['installments'];
        if ($applicationMode === 'current') {
            $allocatableInstallments = array_values(array_filter(
                $allocatableInstallments,
                static fn(array $installment): bool =>
                    (string)$installment['kind'] === $paymentKind
                    && ($installment['no'] === null ? $currentInstallmentNo === null : (int)$installment['no'] === $currentInstallmentNo)
            ));
        }
        foreach ($allocatableInstallments as $installment) {
            if ($remainingPayment <= 0.009) break;
            $unpaid = max(0.0, (float)$installment['amount'] - (float)$installment['paidAmount']);
            if ($unpaid <= 0.009) continue;
            $allocated = round(min($remainingPayment, $unpaid), 2);
            $remainingPayment = round($remainingPayment - $allocated, 2);
            $allocations[] = [
                'kind' => (string)$installment['kind'],
                'no' => $installment['no'] !== null ? (int)$installment['no'] : null,
                'amount' => $allocated,
            ];
        }

        $primaryAllocation = $allocations[0] ?? [
            'kind' => $paymentKind,
            'no' => $schedule['next']['no'],
            'amount' => round((float)$amount, 2),
        ];
        $sql = 'INSERT INTO payments
                    (contract_id, amount, payment_method, date_collected, or_number,
                     remarks, posted_by, check_number, invoice_number, installment_kind, installment_no,
                     external_reference, receipt_requested)
                VALUES
                    (:contract_id, :amount, :payment_method, :date_collected, :or_number,
                     :remarks, :posted_by, :check_number, :invoice_number, :installment_kind, :installment_no,
                     :external_reference, :receipt_requested)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':contract_id'      => $contractId,
            ':amount'           => (float)$amount,
            ':payment_method'   => $method,
            ':date_collected'   => $dateCollected,
            ':or_number'        => $orNumber,
            ':remarks'          => $remarks !== '' ? $remarks : null,
            ':posted_by'        => $postedBy !== '' ? $postedBy : null,
            ':check_number'     => $checkNumber !== '' ? $checkNumber : null,
            ':invoice_number'   => $orNumber,
            ':installment_kind' => (string)$primaryAllocation['kind'],
            ':installment_no'   => $primaryAllocation['no'],
            ':external_reference' => $externalReference !== '' ? $externalReference : null,
            ':receipt_requested' => $receiptRequested ? 1 : 0,
        ]);

        $paymentId = (int)$pdo->lastInsertId();
        $allocationStmt = $pdo->prepare(
            'INSERT INTO payment_allocations
                (payment_id, contract_id, installment_kind, installment_no,
                 allocated_amount, principal_amount, interest_amount, penalty_amount)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        foreach ($allocations as $allocationIndex => $allocation) {
            $allocationStmt->execute([
                $paymentId,
                $numericId,
                $allocation['kind'],
                $allocation['no'],
                $allocation['amount'],
                $allocation['amount'],
                $allocationIndex === 0 ? $transactionInterest : 0,
                $allocationIndex === 0 ? $transactionPenalty : 0,
            ]);
        }
        $principalApplied = round((float)$amount - $remainingPayment, 2);
        $additionalEquityApplied = round($remainingPayment, 2);
        $allocationSummary = [
            'principalApplied' => $principalApplied,
            'additionalEquityApplied' => $additionalEquityApplied,
            'installmentsCovered' => count($allocations),
            'applicationMode' => $applicationMode,
            'interestAmount' => $transactionInterest,
            'penaltyAmount' => $transactionPenalty,
            'target' => $principalApplied > 0.009 ? (string)$primaryAllocation['kind'] : 'ADDITIONAL_EQUITY',
        ];
        if ($remainingPayment > 0.009) {
            $pdo->prepare(
                'INSERT INTO additional_equity_payments
                    (contract_id, payment_id, due_date, amount_due, amount_paid,
                     payment_date, or_number, status, remarks, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $numericId,
                $paymentId,
                $dateCollected,
                $remainingPayment,
                $remainingPayment,
                $dateCollected,
                $orNumber,
                'PAID',
                $remarks !== '' ? $remarks : 'Additional equity overpayment',
                $postedBy !== '' ? $postedBy : null,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Audit trail §17 — every payment insertion is logged (best-effort, never blocks ledger).
    try{
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
          id INT AUTO_INCREMENT PRIMARY KEY, action VARCHAR(80) NOT NULL, contract_id INT NULL, holding_fee_id INT NULL, reservation_fee_id INT NULL,
          property_unit_id INT NULL, from_status VARCHAR(30) NULL, to_status VARCHAR(30) NULL, actor_id VARCHAR(100) NULL, actor_name VARCHAR(255) NULL,
          details JSON NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_audit_contract (contract_id), KEY idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $actorName=null;
        if($postedBy!==''){
            $oSt=$pdo->prepare('SELECT full_name FROM officers WHERE id=? LIMIT 1');
            $oSt->execute([$postedBy]); $oRow=$oSt->fetch(); $actorName=$oRow ? $oRow['full_name'] : null;
        }
        $auditDetails=json_encode([
            'contractId'=>$contractId,
            'amount'=>(float)$amount,
            'method'=>$method,
            'orNumber'=>$orNumber,
            'paymentKind'=>$paymentKind,
            'checkNumber'=>$checkNumber !== '' ? $checkNumber : null,
            'externalReference'=>$externalReference !== '' ? $externalReference : null,
            'receiptRequested'=>$receiptRequested,
            'applicationMode'=>$applicationMode,
            'allocations'=>$allocations,
            'allocationSummary'=>$allocationSummary,
            'interestAmount'=>$transactionInterest,
            'penaltyAmount'=>$transactionPenalty,
            'dateCollected'=>$dateCollected,
            'paymentId'=>$paymentId
        ], JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO audit_logs (action,contract_id,actor_id,actor_name,details) VALUES (?,?,?,?,?)')
            ->execute(['payment.created', $numericId, $postedBy ?: null, $actorName, $auditDetails]);
        // If this payment corresponds to a property/unit, log unit context as well.
        try{
            $cRow=$pdo->prepare('SELECT property_address FROM contracts WHERE id=?'); $cRow->execute([$numericId]); $pAddr=$cRow->fetchColumn();
            if($pAddr){ $uSt=$pdo->prepare('SELECT id FROM property_units WHERE display_label=? LIMIT 1'); $uSt->execute([trim((string)$pAddr)]); $uId=$uSt->fetchColumn(); if($uId) $pdo->prepare('UPDATE audit_logs SET property_unit_id=? WHERE id=LAST_INSERT_ID()')->execute([$uId]); }
        }catch(Throwable $e){}
    }catch(Throwable $e){ /* audit must not break payment */ }

    // Build the post-transaction SOA summary for the dashboard confirmation.
    // Payment is already committed, so a read/calculation failure must not turn
    // a successful ledger write into a client-visible posting failure.
    $updatedSoaSummary = null;
    $paymentTransaction = null;
    try {
        $updatedDocument = soa_build_document($pdo, $numericId, $postedBy);
        $paymentTransaction = soa_build_payment_transaction($pdo, $numericId, $paymentId, $updatedDocument);
        $updatedSoaSummary = soa_build_email_summary($updatedDocument, $paymentTransaction);
    } catch (Throwable $e) {
        $updatedSoaSummary = null;
    }

    // The ledger entry is already committed. Send the email receipt afterwards
    // so a temporary mail outage never rejects a valid client payment.
    $receiptPaymentType = $paymentTransaction['paymentType'] ?? '';
    if ($receiptPaymentType === '') {
        $receiptLabels = [];
        foreach ($allocations as $allocation) {
            $label = soa_payment_type_label((string)$allocation['kind'], $allocation['no']);
            if ($label !== '' && !in_array($label, $receiptLabels, true)) $receiptLabels[] = $label;
        }
        $receiptPaymentType = $receiptLabels !== []
            ? implode(' + ', $receiptLabels)
            : (string)$allocationSummary['target'];
    }
    $receiptPrincipalAmount = $paymentTransaction['principalAmount'] ?? $principalApplied;
    $receiptAdditionalEquityAmount = $paymentTransaction['additionalEquityAmount'] ?? $additionalEquityApplied;
    $receiptEmailSent = false;
    $receiptSkipped = !$receiptRequested;
    if ($receiptRequested) {
        $receiptPayload = json_encode([
            'client' => $contract['client_name'],
            'recipientEmail' => $contract['email'],
            'contractId' => $contractId,
            'amount' => (float)$amount,
            'paymentDate' => $dateCollected,
            'method' => $method,
            'orNumber' => $orNumber,
            'checkNumber' => $checkNumber,
            'externalReference' => $externalReference,
            'paymentPurpose' => $receiptPaymentType,
            'paymentType' => $paymentTransaction['paymentType'] ?? $receiptPaymentType,
            'installmentNumber' => $paymentTransaction['installmentNumber'] ?? null,
            'amountDue' => $paymentTransaction['amountDue'] ?? $amount,
            'dueDate' => $paymentTransaction['dueDate'] ?? $dateCollected,
            'interest' => $paymentTransaction['interest'] ?? $transactionInterest,
            'penalty' => $paymentTransaction['penalty'] ?? $transactionPenalty,
            'principalAmount' => $receiptPrincipalAmount,
            'principal' => $receiptPrincipalAmount,
            'remainingBalance' => $paymentTransaction['remainingBalance'] ?? null,
            'totalAmountDue' => $paymentTransaction['totalAmountDue'] ?? null,
            'paymentStatus' => $paymentTransaction['paymentStatus'] ?? 'POSTED',
            'soaNumber' => $paymentTransaction['soaNumber'] ?? '',
            'soaAsOfDate' => $paymentTransaction['soaAsOfDate'] ?? '',
            'additionalEquityAmount' => $receiptAdditionalEquityAmount,
            'postedByName' => $paymentTransaction['postedByName'] ?? '',
            'paymentId' => $paymentId
        ]);
        $receiptServiceUrl = rtrim(getenv('REMINDER_SERVICE_URL') ?: 'http://127.0.0.1:3000', '/');
        $receiptContext = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($receiptPayload) . "\r\n",
            'content' => $receiptPayload,
            'timeout' => 8,
            'ignore_errors' => true
        ]]);
        $receiptResponse = @file_get_contents($receiptServiceUrl . '/api/payments/receipt', false, $receiptContext);
        $receiptResult = is_string($receiptResponse) ? json_decode($receiptResponse, true) : null;
        $receiptEmailSent = is_array($receiptResult) && !empty($receiptResult['success']);
    }

    $message = 'Payment posted successfully. OR / Reference No. ' . $orNumber . '.';
    if ($receiptSkipped) {
        $message .= ' Receipt email was not requested.';
    } else {
        $message .= $receiptEmailSent
            ? ' Receipt emailed; the updated SOA is available in the client dashboard.'
            : ' The receipt email could not be sent.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $paymentId,
        'orNumber' => $orNumber,
        'paymentKind' => $paymentKind,
        'allocations' => $allocations,
        'allocationSummary' => $allocationSummary,
        'payment' => [
            'amount' => round((float)$amount, 2),
            'paymentType' => $paymentTransaction['paymentType'] ?? $receiptPaymentType,
            'installmentNumber' => $paymentTransaction['installmentNumber'] ?? null,
            'amountDue' => $paymentTransaction['amountDue'] ?? $amount,
            'dueDate' => $paymentTransaction['dueDate'] ?? $dateCollected,
            'interest' => $paymentTransaction['interest'] ?? $transactionInterest,
            'penalty' => $paymentTransaction['penalty'] ?? $transactionPenalty,
            'principalAmount' => $receiptPrincipalAmount,
            'principal' => $receiptPrincipalAmount,
            'remainingBalance' => $paymentTransaction['remainingBalance'] ?? null,
            'paymentStatus' => $paymentTransaction['paymentStatus'] ?? 'POSTED',
            'additionalEquityAmount' => $receiptAdditionalEquityAmount,
            'method' => $method,
            'dateCollected' => $dateCollected,
            'checkNumber' => $checkNumber !== '' ? $checkNumber : null,
            'externalReference' => $externalReference !== '' ? $externalReference : null,
            'receiptRequested' => $receiptRequested,
            'postedBy' => $postedBy !== '' ? $postedBy : null,
        ],
        'updatedSoa' => $updatedSoaSummary,
        'paymentTransaction' => $paymentTransaction,
        'receiptRequested' => $receiptRequested,
        'receiptSkipped' => $receiptSkipped,
        'receiptEmailSent' => $receiptEmailSent
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
