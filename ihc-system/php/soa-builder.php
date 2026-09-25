<?php
declare(strict_types=1);

require_once __DIR__ . '/installment-schedule.php';

function soa_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function soa_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

/** Self-heal the SOA additions on older installations. */
function soa_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS soa_settings (
          id TINYINT UNSIGNED NOT NULL,
          company_name VARCHAR(255) NOT NULL DEFAULT 'Imperial Homes',
          company_address VARCHAR(500) NULL,
          company_contact VARCHAR(255) NULL,
          logo_path VARCHAR(500) NULL,
          penalty_rate_percent DECIMAL(7,4) NOT NULL DEFAULT 0,
          important_notes MEDIUMTEXT NULL,
          noted_by_name VARCHAR(255) NULL,
          noted_by_position VARCHAR(255) NULL,
          noted_by_contact VARCHAR(255) NULL,
          validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
          updated_by VARCHAR(100) NULL,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_allocations (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          payment_id INT NOT NULL, contract_id INT NOT NULL,
          installment_kind VARCHAR(20) NOT NULL, installment_no INT NULL,
          allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
          principal_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
          interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
          penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_payment_allocation (payment_id, installment_kind, installment_no),
          KEY idx_payment_alloc_contract (contract_id),
          KEY idx_payment_alloc_schedule (contract_id, installment_kind, installment_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS additional_equity_payments (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT, contract_id INT NOT NULL, payment_id INT NULL,
          installment_no INT NULL, due_date DATE NULL,
          amount_due DECIMAL(15,2) NOT NULL DEFAULT 0, amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
          payment_date DATE NULL, or_number VARCHAR(100) NULL, status VARCHAR(20) NOT NULL DEFAULT 'UNPAID',
          remarks TEXT NULL, created_by VARCHAR(100) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id), KEY idx_additional_equity_contract (contract_id, status, due_date),
          KEY idx_additional_equity_payment (payment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS additional_charges (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT, contract_id INT NOT NULL,
          charge_type VARCHAR(100) NOT NULL, description VARCHAR(500) NULL,
          amount DECIMAL(15,2) NOT NULL DEFAULT 0, amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
          charge_date DATE NOT NULL, due_date DATE NULL, status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
          or_number VARCHAR(100) NULL, remarks TEXT NULL, created_by VARCHAR(100) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id), KEY idx_additional_charge_contract (contract_id, status, due_date),
          KEY idx_additional_charge_type (charge_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $contractColumns = [
        'discount_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        'loanable_amount' => 'DECIMAL(15,2) NULL',
        'approved_loan_amount' => 'DECIMAL(15,2) NULL',
        'early_move_in_amount' => 'DECIMAL(15,2) NULL',
        'project_name' => 'VARCHAR(255) NULL',
        'project_phase' => 'VARCHAR(120) NULL',
        'block_no' => 'VARCHAR(50) NULL',
        'lot_no' => 'VARCHAR(50) NULL',
        'model_type' => 'VARCHAR(120) NULL',
        'lot_area' => 'VARCHAR(100) NULL',
        'floor_area' => 'VARCHAR(100) NULL',
        'client_address' => 'VARCHAR(500) NULL',
        'equity_monthly_rate' => 'DECIMAL(7,4) NULL',
        'equity_penalty_rate' => 'DECIMAL(7,4) NULL',
        'loan_term_years' => 'DECIMAL(8,2) NULL',
    ];
    if (soa_table_exists($pdo, 'contracts')) {
        foreach ($contractColumns as $column => $definition) {
            if (!soa_column_exists($pdo, 'contracts', $column)) {
                $pdo->exec('ALTER TABLE contracts ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    }

    $paymentColumns = [
        'check_number' => 'VARCHAR(100) NULL',
        'invoice_number' => 'VARCHAR(100) NULL',
        'installment_kind' => 'VARCHAR(20) NULL',
        'installment_no' => 'INT NULL',
        'external_reference' => 'VARCHAR(100) NULL',
        'receipt_requested' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    if (soa_table_exists($pdo, 'payments')) {
        foreach ($paymentColumns as $column => $definition) {
            if (!soa_column_exists($pdo, 'payments', $column)) {
                $pdo->exec('ALTER TABLE payments ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    }

    $pdo->exec(
        "INSERT IGNORE INTO soa_settings
          (id,company_name,company_address,company_contact,logo_path,penalty_rate_percent,important_notes,noted_by_name,noted_by_position,noted_by_contact,validity_days)
         VALUES
          (1,'Imperial Homes','Imperial Homes Corporation','Contact the IHC Billing Office for assistance.','img/ihc logo.png',0,
           'Please settle this Statement of Account on or before the due date. All amounts are computed from the current IHC ledger. Penalties and interest, when applicable, follow the configured company rate and the payment status shown in this document.',
           'Authorized IHC Representative','Billing Manager','IHC Billing Office',7)"
    );
}

function soa_normalize_contract_id($value): ?int
{
    if (preg_match('/(\d+)\s*$/', (string)$value, $match)) {
        $id = (int)$match[1];
        return $id > 0 ? $id : null;
    }
    return null;
}

function soa_round(float|int|string $value): float
{
    return round((float)$value, 2);
}

function soa_text($value, string $fallback = '—'): string
{
    $text = trim((string)($value ?? ''));
    return $text !== '' ? $text : $fallback;
}

function soa_date_or_null($value): ?string
{
    $date = trim((string)($value ?? ''));
    return $date !== '' ? $date : null;
}

function soa_days_past_due(string $dueDate, string $today): int
{
    $due = DateTime::createFromFormat('!Y-m-d', $dueDate);
    $now = DateTime::createFromFormat('!Y-m-d', $today);
    if (!$due || !$now || $due >= $now) return 0;
    return (int)$due->diff($now)->format('%a');
}

function soa_extract_property_part(string $address, string $label): ?string
{
    $pattern = '/\b' . preg_quote($label, '/') . '\s*([A-Za-z0-9_-]+)/i';
    return preg_match($pattern, $address, $match) ? trim($match[1]) : null;
}

/**
 * Allocate real payment rows chronologically across schedule installments.
 * This mirrors ihc_schedule(), but retains every OR/check/invoice reference
 * and any amount left after the net contract price as additional equity.
 */
function soa_allocate_payments(array $installments, array $payments): array
{
    $rows = [];
    foreach ($installments as $index => $installment) {
        $rows[$index] = $installment + [
            'amountPaid' => 0.0,
            'paymentDate' => null,
            'paymentDates' => [],
            'orNumbers' => [],
            'checkNumbers' => [],
            'invoiceNumbers' => [],
            'externalReferences' => [],
            'allocations' => [],
        ];
    }

    $extras = [];
    foreach ($payments as $payment) {
        $remaining = ihc_schedule_payment_amount($payment);
        foreach ($rows as $index => &$row) {
            if ($remaining <= 0.009) break;
            $need = max(0.0, (float)$row['amount'] - (float)$row['amountPaid']);
            if ($need <= 0.009) continue;
            $take = min($remaining, $need);
            $remaining = soa_round($remaining - $take);
            $row['amountPaid'] = soa_round($row['amountPaid'] + $take);
            $allocation = [
                'paymentId' => (int)$payment['id'],
                'amount' => $take,
                'date' => (string)$payment['date'],
                'orNumber' => $payment['orNumber'],
                'checkNumber' => $payment['checkNumber'],
                'invoiceNumber' => $payment['invoiceNumber'],
                'externalReference' => $payment['externalReference'] ?? null,
            ];
            $row['allocations'][] = $allocation;
            if ($allocation['date'] !== '') {
                $row['paymentDates'][] = $allocation['date'];
                $row['paymentDate'] = $allocation['date'];
            }
            foreach ([
                'orNumbers' => $allocation['orNumber'],
                'checkNumbers' => $allocation['checkNumber'],
                'invoiceNumbers' => $allocation['invoiceNumber'] !== null && $allocation['invoiceNumber'] !== ''
                    ? $allocation['invoiceNumber'] : $allocation['orNumber'],
                'externalReferences' => $allocation['externalReference'] ?? null,
            ] as $field => $value) {
                if ($value !== null && $value !== '') $row[$field][] = (string)$value;
            }
        }
        unset($row);

        if ($remaining > 0.009) {
            $extras[] = [
                'paymentId' => (int)$payment['id'],
                'amount' => soa_round($remaining),
                'paymentDate' => (string)$payment['date'],
                'orNumber' => $payment['orNumber'],
            ];
        }
    }

    foreach ($rows as &$row) {
        foreach (['orNumbers', 'checkNumbers', 'invoiceNumbers', 'externalReferences', 'paymentDates'] as $field) {
            $row[$field] = array_values(array_unique(array_filter($row[$field], static fn($v) => $v !== null && $v !== '')));
        }
    }
    unset($row);

    return ['rows' => array_values($rows), 'additionalEquity' => $extras];
}

function soa_status(float $amountDue, float $amountPaid, string $dueDate, string $today): string
{
    if ($amountDue <= 0.009 || $amountPaid >= $amountDue - 0.009) return 'PAID';
    // Overdue takes precedence over partial coverage so partially paid past-due
    // rows still accrue days, interest, and penalty using the configured rates.
    if ($dueDate < $today) return 'OVERDUE';
    if ($amountPaid > 0.009) return 'PARTIALLY PAID';
    return 'UNPAID';
}

/**
 * Whitelisted, compact SOA payload for payment-notification email/SMS.
 * Full schedule rows, allocations, signatures, and detailed sections stay in
 * the dashboard document and are never copied into an email.
 */
function soa_build_email_summary(array $document): array
{
    $contract = is_array($document['contract'] ?? null) ? $document['contract'] : [];
    $summary = is_array($document['summary'] ?? null) ? $document['summary'] : [];
    $due = is_array($document['amountDue'] ?? null) ? $document['amountDue'] : [];
    $client = is_array($document['client'] ?? null) ? $document['client'] : [];
    $project = is_array($document['project'] ?? null) ? $document['project'] : [];
    $company = is_array($document['company'] ?? null) ? $document['company'] : [];
    $next = is_array($summary['nextPayment'] ?? null) ? $summary['nextPayment'] : null;
    $asOf = (string)($document['asOfDate'] ?? date('Y-m-d'));
    $nextDueDate = $next !== null ? (string)($next['dueDate'] ?? $asOf) : $asOf;

    return [
        'soaNumber' => (string)($document['soaNumber'] ?? ''),
        'asOfDate' => $asOf,
        'validUntil' => (string)($document['validUntil'] ?? $asOf),
        'companyName' => (string)($company['name'] ?? 'Imperial Homes'),
        'companyContact' => (string)($company['contact'] ?? ''),
        'clientName' => (string)($client['name'] ?? ''),
        'contractReference' => (string)($contract['reference'] ?? ''),
        'projectName' => (string)($project['projectName'] ?? ''),
        'phase' => (string)($project['phase'] ?? ''),
        'block' => (string)($project['block'] ?? ''),
        'lot' => (string)($project['lot'] ?? ''),
        'totalContractPrice' => soa_round($contract['grossPrice'] ?? 0),
        'discount' => soa_round($contract['discount'] ?? 0),
        'netContractPrice' => soa_round($contract['netPrice'] ?? 0),
        'equity' => soa_round($contract['equity'] ?? 0),
        'requiredDownpayment' => soa_round($contract['requiredDownpayment'] ?? 0),
        'totalEquity' => soa_round($contract['totalEquity'] ?? 0),
        'loanableAmount' => soa_round($contract['loanableAmount'] ?? 0),
        'totalPaymentsMade' => soa_round($summary['paymentsMade'] ?? 0),
        'remainingBalance' => soa_round($summary['remainingBalance'] ?? 0),
        'nextPaymentType' => $next !== null ? (string)($next['label'] ?? 'Scheduled Payment') : 'Settled in Full',
        'nextDueDate' => $nextDueDate,
        'currentPaymentDue' => $next !== null ? soa_round($next['amount'] ?? 0) : 0.0,
        'monthlyPayment' => soa_round($summary['monthlyPayment'] ?? 0),
        'annualInterestRate' => (float)($summary['annualInterestRate'] ?? 0),
        'interest' => soa_round($due['interest'] ?? 0),
        'penalty' => soa_round($due['penalty'] ?? 0),
        'reservationOutstanding' => soa_round($due['reservationOutstanding'] ?? 0),
        'additionalCharges' => soa_round($due['additionalCharges'] ?? 0),
        'additionalEquityOutstanding' => soa_round($due['additionalEquityOutstanding'] ?? 0),
        'totalAmountDue' => soa_round($due['total'] ?? 0),
        'status' => (float)($due['total'] ?? 0) <= 0.009
            ? 'SETTLED'
            : ($nextDueDate < $asOf ? 'OVERDUE' : 'DUE'),
    ];
}

function soa_resolve_actor(PDO $pdo, string $actorId, array $contract, string $actorEmail = ''): array
{
    $actor = null;
    if ($actorId !== '') {
        $st = $pdo->prepare('SELECT id, full_name, role FROM officers WHERE id = ? LIMIT 1');
        $st->execute([$actorId]);
        $actor = $st->fetch() ?: null;
    }
    if (!$actor && $actorEmail !== '') {
        $st = $pdo->prepare('SELECT id, full_name, role FROM officers WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$actorEmail]);
        $actor = $st->fetch() ?: null;
    }
    if (!$actor && !empty($contract['officer_id'])) {
        $st = $pdo->prepare('SELECT id, full_name, role FROM officers WHERE id = ? LIMIT 1');
        $st->execute([$contract['officer_id']]);
        $actor = $st->fetch() ?: null;
    }
    $role = strtolower((string)($actor['role'] ?? 'clerk'));
    return [
        'name' => soa_text($actor['full_name'] ?? null, 'IHC Staff'),
        'position' => $role === 'admin' ? 'Administrator' : 'Billing Clerk',
    ];
}

function soa_build_document(PDO $pdo, int $contractId, string $actorId = '', ?string $todayOverride = null, string $actorEmail = ''): array
{
    $today = $todayOverride ?? date('Y-m-d');

    $st = $pdo->prepare(
        'SELECT c.*, o.full_name AS assigned_clerk_name, o.role AS assigned_clerk_role
         FROM contracts c LEFT JOIN officers o ON o.id = c.officer_id
         WHERE c.id = ? LIMIT 1'
    );
    $st->execute([$contractId]);
    $contract = $st->fetch();
    if (!$contract) throw new RuntimeException('Contract not found.');

    $settings = $pdo->query('SELECT * FROM soa_settings WHERE id = 1 LIMIT 1')->fetch() ?: [];
    $propertyAddress = trim((string)($contract['property_address'] ?? ''));
    $unit = null;
    try {
        $unitSt = $pdo->prepare(
            'SELECT * FROM property_units
             WHERE current_contract_id = ? OR display_label = ?
             ORDER BY current_contract_id DESC LIMIT 1'
        );
        $unitSt->execute([$contractId, $propertyAddress]);
        $unit = $unitSt->fetch() ?: null;
    } catch (Throwable $e) {
        $unit = null;
    }

    $client = [
        'name' => (string)$contract['client_name'],
        'email' => (string)$contract['email'],
        'phone' => (string)$contract['cellphone_number'],
        'address' => soa_text($contract['client_address'] ?? $propertyAddress),
        'contact' => trim((string)$contract['cellphone_number']) !== ''
            ? trim((string)$contract['cellphone_number']) . ' • ' . (string)$contract['email']
            : (string)$contract['email'],
    ];
    $project = [
        'projectName' => soa_text($contract['project_name'] ?? ($unit['project'] ?? null), 'Imperial Homes'),
        'phase' => soa_text($contract['project_phase'] ?? null),
        'block' => soa_text($contract['block_no'] ?? ($unit['building'] ?? (soa_extract_property_part($propertyAddress, 'block') ?: null))),
        'lot' => soa_text($contract['lot_no'] ?? ($unit['unit_number'] ?? (soa_extract_property_part($propertyAddress, 'lot') ?: null))),
        'modelType' => soa_text($contract['model_type'] ?? null),
        'lotArea' => soa_text($contract['lot_area'] ?? null),
        'floorArea' => soa_text($contract['floor_area'] ?? null),
        'propertyAddress' => soa_text($propertyAddress),
    ];

    $payments = [];
    $principalByPayment = [];
    try {
        foreach ($pdo->query('SELECT payment_id, SUM(principal_amount) AS principal_amount FROM payment_allocations GROUP BY payment_id') as $allocationRow) {
            $principalByPayment[(int)$allocationRow['payment_id']] = (float)$allocationRow['principal_amount'];
        }
    } catch (Throwable $e) {
        $principalByPayment = [];
    }
    $paymentSql = 'SELECT id, contract_id, amount, date_collected, or_number, payment_method';
    foreach (['check_number', 'invoice_number', 'installment_kind', 'installment_no', 'external_reference', 'receipt_requested'] as $column) {
        if (soa_column_exists($pdo, 'payments', $column)) $paymentSql .= ', ' . $column;
    }
    $paymentSql .= ' FROM payments ORDER BY date_collected, id';
    foreach ($pdo->query($paymentSql) as $payment) {
        $id = soa_normalize_contract_id($payment['contract_id']);
        if ($id !== $contractId) continue;
        $orNumber = soa_date_or_null($payment['or_number']);
        $payments[] = [
            'id' => (int)$payment['id'],
            'amount' => (float)$payment['amount'],
            'principalAmount' => $principalByPayment[(int)$payment['id']] ?? null,
            'date' => (string)$payment['date_collected'],
            'orNumber' => $orNumber,
            'checkNumber' => $payment['check_number'] ?? null,
            'invoiceNumber' => $payment['invoice_number'] ?? ($orNumber ?: null),
            'method' => (string)$payment['payment_method'],
            'installmentKind' => $payment['installment_kind'] ?? null,
            'installmentNo' => isset($payment['installment_no']) ? $payment['installment_no'] : null,
            'externalReference' => $payment['external_reference'] ?? null,
            'receiptRequested' => !isset($payment['receipt_requested']) || (bool)$payment['receipt_requested'],
        ];
    }

    $grossPrice = max(0.0, (float)$contract['total_contract_price']);
    $discount = min($grossPrice, max(0.0, (float)($contract['discount_amount'] ?? 0)));
    $netPrice = soa_round($grossPrice - $discount);
    $downpayment = min($netPrice, max(0.0, (float)$contract['downpayment']));

    // Pass the original gross contract row to the shared allocator. It applies
    // discount_amount exactly once, keeping SOA balances aligned with every
    // other dashboard.
    $built = ihc_schedule($contract, $payments, $today);
    $allocation = soa_allocate_payments($built['installments'], $payments);

    $loanable = $contract['loanable_amount'] ?? null;
    $loanable = $loanable === null || $loanable === ''
        ? max(0.0, $netPrice - $downpayment)
        : min($netPrice, max(0.0, (float)$loanable));
    $approvedLoan = $contract['approved_loan_amount'] ?? null;
    $approvedLoan = $approvedLoan === null || $approvedLoan === ''
        ? $loanable
        : min($netPrice, max(0.0, (float)$approvedLoan));
    $baseEquity = max(0.0, $netPrice - $loanable);

    $reservationFees = [];
    $reservationBilled = 0.0;
    $reservationPaid = 0.0;
    $reservationOutstanding = 0.0;
    try {
        $resSt = $pdo->prepare(
            'SELECT id, amount, payment_date, or_number, reference_number, status, remarks
             FROM reservation_fees WHERE contract_id = ? ORDER BY payment_date, id'
        );
        $resSt->execute([$contractId]);
        foreach ($resSt->fetchAll() as $fee) {
            $status = strtoupper((string)$fee['status']);
            $amount = max(0.0, (float)$fee['amount']);
            $paid = $status === 'PAID' ? $amount : 0.0;
            $outstanding = in_array($status, ['PENDING', 'PARTIALLY_PAID'], true) ? $amount : 0.0;
            if (!in_array($status, ['CANCELLED', 'REFUNDED', 'VOID'], true)) {
                $reservationBilled += $amount;
                $reservationPaid += $paid;
                $reservationOutstanding += $outstanding;
            }
            $reservationFees[] = [
                'id' => (int)$fee['id'],
                'paymentDate' => soa_date_or_null($fee['payment_date']),
                'reference' => soa_text($fee['or_number'] ?? ($fee['reference_number'] ?? null), 'Not Yet Issued'),
                'amount' => soa_round($amount),
                'description' => soa_text($fee['remarks'] ?? null, 'Reservation fee / non-refundable'),
                'outstanding' => soa_round($outstanding),
                'status' => $status,
            ];
        }
    } catch (Throwable $e) {
        $reservationFees = [];
    }

    $additionalCharges = [];
    $chargesOutstanding = 0.0;
    $chargesPaid = 0.0;
    try {
        $chargeSt = $pdo->prepare(
            'SELECT * FROM additional_charges WHERE contract_id = ? ORDER BY charge_date, id'
        );
        $chargeSt->execute([$contractId]);
        foreach ($chargeSt->fetchAll() as $charge) {
            $status = strtoupper((string)$charge['status']);
            $amount = max(0.0, (float)$charge['amount']);
            $paid = min($amount, max(0.0, (float)$charge['amount_paid']));
            $outstanding = strtoupper($status) === 'VOID' ? 0.0 : max(0.0, $amount - $paid);
            $chargeDueDate = trim((string)($charge['due_date'] ?? ''));
            $derivedStatus = $outstanding <= 0.009
                ? 'PAID'
                : ($paid > 0.009 ? 'PARTIALLY PAID' : ($chargeDueDate !== '' && $chargeDueDate < $today ? 'OVERDUE' : 'UNPAID'));
            $additionalCharges[] = [
                'id' => (int)$charge['id'],
                'type' => (string)$charge['charge_type'],
                'description' => soa_text($charge['description'] ?? null, (string)$charge['charge_type']),
                'amount' => soa_round($amount),
                'amountPaid' => soa_round($paid),
                'outstanding' => soa_round($outstanding),
                'chargeDate' => soa_date_or_null($charge['charge_date']),
                'dueDate' => soa_date_or_null($charge['due_date']),
                'orNumber' => soa_date_or_null($charge['or_number']),
                'status' => $derivedStatus,
            ];
            if (strtoupper($status) !== 'VOID') {
                $chargesOutstanding += $outstanding;
                $chargesPaid += $paid;
            }
        }
    } catch (Throwable $e) {
        $additionalCharges = [];
    }

    $monthlyRate = ((float)($contract['annual_interest_rate'] ?? 0)) / 100 / 12;
    $penaltyRate = $contract['equity_penalty_rate'] ?? null;
    $penaltyRate = $penaltyRate === null || $penaltyRate === ''
        ? (float)($settings['penalty_rate_percent'] ?? 0)
        : (float)$penaltyRate;

    $scheduleRows = [];
    $runningOutstanding = $netPrice;
    $firstUncoveredSeen = false;
    $schedulePrincipalPaid = 0.0;
    $accruedInterest = 0.0;
    $accruedPenalty = 0.0;
    $nextSchedule = null;
    foreach ($allocation['rows'] as $row) {
        $amountDue = max(0.0, (float)$row['amount']);
        $amountPaid = min($amountDue, max(0.0, (float)$row['amountPaid']));
        $status = soa_status($amountDue, $amountPaid, (string)$row['dueDate'], $today);
        $daysPastDue = $status === 'OVERDUE' ? soa_days_past_due((string)$row['dueDate'], $today) : 0;
        $isFirstUncovered = !$firstUncoveredSeen && $status !== 'PAID';
        if ($status === 'PAID') {
            $firstUncoveredSeen = true;
        } elseif ($isFirstUncovered) {
            $firstUncoveredSeen = true;
        }

        $monthsPastDue = $daysPastDue > 0 ? max(1, (int)ceil($daysPastDue / 30)) : 0;
        $unpaid = max(0.0, $amountDue - $amountPaid);
        $interest = $isFirstUncovered && $daysPastDue > 0 ? soa_round($unpaid * $monthlyRate * $monthsPastDue) : 0.0;
        $penalty = $isFirstUncovered && $daysPastDue > 0 ? soa_round($unpaid * ($penaltyRate / 100) * $monthsPastDue) : 0.0;
        $accruedInterest += $interest;
        $accruedPenalty += $penalty;
        $schedulePrincipalPaid += $amountPaid;
        $runningOutstanding = max(0.0, soa_round($runningOutstanding - $amountPaid));
        if ($nextSchedule === null && $status !== 'PAID') {
            $nextSchedule = [
                'label' => $row['kind'] === 'downpayment' ? 'Downpayment' : 'Installment Payment #' . $row['no'],
                'amount' => soa_round($unpaid + $interest + $penalty),
                'dueDate' => (string)$row['dueDate'],
            ];
        }

        $scheduleRows[] = [
            'installmentNo' => $row['kind'] === 'downpayment' ? 'DP' : (int)$row['no'],
            'type' => $row['kind'] === 'downpayment' ? 'Downpayment' : 'Installment Payment #' . $row['no'],
            'paymentDate' => $row['paymentDate'],
            'dueDate' => (string)$row['dueDate'],
            'daysPastDue' => $daysPastDue,
            'checkNumber' => implode(', ', $row['checkNumbers']),
            'invoiceNumber' => implode(', ', $row['invoiceNumbers']),
            'externalReference' => implode(', ', $row['externalReferences']),
            'orNumber' => implode(', ', $row['orNumbers']),
            'amountDue' => soa_round($amountDue),
            'amountPaid' => soa_round($amountPaid),
            'penalty' => $penalty,
            'interest' => $interest,
            'principal' => soa_round($amountPaid),
            'outstandingBalance' => soa_round($runningOutstanding),
            'status' => $status,
        ];
    }

    $explicitAdditionalEquity = [];
    $recordedPaymentIds = [];
    try {
        $eqSt = $pdo->prepare(
            'SELECT * FROM additional_equity_payments WHERE contract_id = ? ORDER BY due_date, payment_date, id'
        );
        $eqSt->execute([$contractId]);
        foreach ($eqSt->fetchAll() as $equity) {
            if (strtoupper((string)$equity['status']) === 'VOID') continue;
            if ($equity['payment_id'] !== null) $recordedPaymentIds[(int)$equity['payment_id']] = true;
            $amountDue = max(0.0, (float)$equity['amount_due']);
            $amountPaid = min($amountDue, max(0.0, (float)$equity['amount_paid']));
            $equityDueDate = (string)($equity['due_date'] ?? $equity['payment_date'] ?? $today);
            $status = soa_status($amountDue, $amountPaid, $equityDueDate, $today);
            $daysPastDue = $status === 'OVERDUE' ? soa_days_past_due($equityDueDate, $today) : 0;
            $monthsPastDue = $daysPastDue > 0 ? max(1, (int)ceil($daysPastDue / 30)) : 0;
            $unpaid = max(0.0, $amountDue - $amountPaid);
            $interest = $daysPastDue > 0 ? soa_round($unpaid * $monthlyRate * $monthsPastDue) : 0.0;
            $penalty = $daysPastDue > 0 ? soa_round($unpaid * ($penaltyRate / 100) * $monthsPastDue) : 0.0;
            $explicitAdditionalEquity[] = [
                'id' => (int)$equity['id'],
                'installmentNo' => $equity['installment_no'] !== null ? (int)$equity['installment_no'] : 'AE',
                'dueDate' => soa_date_or_null($equity['due_date']),
                'paymentDate' => soa_date_or_null($equity['payment_date']),
                'daysPastDue' => $daysPastDue,
                'status' => $status,
                'orNumber' => soa_text($equity['or_number'] ?? null, 'Pending'),
                'amountDue' => soa_round($amountDue),
                'amountPaid' => soa_round($amountPaid),
                'penalty' => $penalty,
                'interest' => $interest,
                'principal' => soa_round($amountPaid),
                'outstandingBalance' => soa_round($unpaid + $interest + $penalty),
            ];
        }
    } catch (Throwable $e) {
        $explicitAdditionalEquity = [];
    }

    foreach ($allocation['additionalEquity'] as $extraIndex => $extra) {
        if (isset($recordedPaymentIds[(int)$extra['paymentId']])) continue;
        $explicitAdditionalEquity[] = [
            'id' => 'PAY-' . (int)$extra['paymentId'],
            'installmentNo' => 'AE' . ($extraIndex + 1),
            'dueDate' => $extra['paymentDate'],
            'paymentDate' => $extra['paymentDate'],
            'daysPastDue' => 0,
            'status' => 'PAID',
            'orNumber' => soa_text($extra['orNumber'] ?? null, 'Pending'),
            'amountDue' => soa_round($extra['amount']),
            'amountPaid' => soa_round($extra['amount']),
            'penalty' => 0.0,
            'interest' => 0.0,
            'principal' => soa_round($extra['amount']),
            'outstandingBalance' => 0.0,
        ];
    }
    $additionalEquityOutstanding = array_sum(array_map(
        static fn(array $row): float => (float)$row['outstandingBalance'],
        $explicitAdditionalEquity
    ));
    $additionalEquityPaid = array_sum(array_map(
        static fn(array $row): float => (float)$row['amountPaid'],
        $explicitAdditionalEquity
    ));

    $downpaymentScheduled = $scheduleRows[0]['amountDue'] ?? $downpayment;
    $downpaymentPaid = min($downpaymentScheduled, (float)($scheduleRows[0]['amountPaid'] ?? 0));
    $remainingDownpayment = max(0.0, soa_round($downpayment - $downpaymentPaid));
    $remainingBalance = soa_round($runningOutstanding);
    $requiredEquity = max($downpayment, $baseEquity);
    $totalEquity = soa_round($requiredEquity + $reservationBilled);
    $remainingEquity = soa_round(max(0.0, $totalEquity - $downpaymentPaid - $reservationPaid));
    $monthlyPayment = $nextSchedule !== null ? (float)$nextSchedule['amount'] : 0.0;
    $totalPaymentsMade = soa_round($schedulePrincipalPaid + $reservationPaid + $chargesPaid + $additionalEquityPaid);
    $amountForPayment = soa_round(max(
        0.0,
        $remainingBalance
        + $reservationOutstanding
        + $chargesOutstanding
        + $additionalEquityOutstanding
        + $accruedInterest
        + $accruedPenalty
    ));

    $loanTermYears = $contract['loan_term_years'] ?? null;
    $loanTermYears = $loanTermYears === null || $loanTermYears === ''
        ? soa_round((int)$contract['installment_terms'] / 12)
        : (float)$loanTermYears;
    $equityMonthlyRate = $contract['equity_monthly_rate'] ?? null;
    $equityMonthlyRate = $equityMonthlyRate === null || $equityMonthlyRate === '' ? 0.0 : (float)$equityMonthlyRate;
    $prepared = soa_resolve_actor($pdo, $actorId, $contract, $actorEmail);

    $rawNotes = trim((string)($settings['important_notes'] ?? ''));
    $notes = $rawNotes !== '' ? preg_split('/\r\n|\r|\n/', $rawNotes) : [];
    $notes = array_values(array_filter(array_map('trim', $notes ?: [])));

    $equityLimit = max(1, (int)($contract['dp_terms'] ?? 1));
    $equitySchedule = array_slice($scheduleRows, 0, 1 + ($contract['dp_mode'] === 'monthly' ? $equityLimit : 0));

    return [
        'success' => true,
        'generatedAt' => date('Y-m-d H:i:s'),
        'asOfDate' => $today,
        'soaNumber' => 'SOA-IHC-' . $contractId . '-' . date('Ymd', strtotime($today)),
        'validUntil' => date('Y-m-d', strtotime('+' . max(0, (int)($settings['validity_days'] ?? 7)) . ' days', strtotime($today))),
        'company' => [
            'name' => soa_text($settings['company_name'] ?? null, 'Imperial Homes'),
            'address' => soa_text($settings['company_address'] ?? null, ''),
            'contact' => soa_text($settings['company_contact'] ?? null, ''),
            'logoPath' => soa_text($settings['logo_path'] ?? null, 'img/ihc logo.png'),
        ],
        'client' => $client,
        'project' => $project,
        'contract' => [
            'id' => $contractId,
            'reference' => 'IHC-' . $contractId,
            'grossPrice' => soa_round($grossPrice),
            'discount' => soa_round($discount),
            'netPrice' => soa_round($netPrice),
            'equity' => soa_round($baseEquity),
            'reservationFee' => soa_round($reservationBilled),
            'requiredDownpayment' => soa_round($downpayment),
            'earlyMoveIn' => soa_round($contract['early_move_in_amount'] ?? 0),
            'totalEquity' => $totalEquity,
            'remainingDownpayment' => $remainingDownpayment,
            'loanableAmount' => soa_round($loanable),
            'approvedLoanAmount' => soa_round($approvedLoan),
            'bankName' => soa_text($contract['bank_name'] ?? null),
            'annualInterestRate' => (float)($contract['annual_interest_rate'] ?? 0),
        ],
        'financing' => [
            'equityMonthlyRate' => $equityMonthlyRate,
            'equityTermMonths' => $contract['dp_mode'] === 'monthly' ? (int)($contract['dp_terms'] ?? 0) : 0,
            'equityPenaltyRate' => $penaltyRate,
            'homeMonthlyRate' => $monthlyRate * 100,
            'homeTermYears' => $loanTermYears,
            'bankName' => soa_text($contract['bank_name'] ?? null),
        ],
        'schedule' => $scheduleRows,
        'equitySchedule' => $equitySchedule,
        'reservationFees' => $reservationFees,
        'additionalEquity' => $explicitAdditionalEquity,
        'additionalCharges' => $additionalCharges,
        'summary' => [
            'grossPrice' => soa_round($grossPrice),
            'discount' => soa_round($discount),
            'paymentsMade' => $totalPaymentsMade,
            'remainingBalance' => $remainingBalance,
            'approvedLoanAmount' => soa_round($approvedLoan),
            'remainingEquity' => $remainingEquity,
            'monthlyPayment' => soa_round($monthlyPayment),
            'annualInterestRate' => (float)($contract['annual_interest_rate'] ?? 0),
            'nextPayment' => $nextSchedule,
        ],
        'amountDue' => [
            'principalOutstanding' => $remainingBalance,
            'interest' => soa_round($accruedInterest),
            'penalty' => soa_round($accruedPenalty),
            'reservationOutstanding' => soa_round($reservationOutstanding),
            'additionalCharges' => soa_round($chargesOutstanding),
            'additionalEquityOutstanding' => soa_round($additionalEquityOutstanding),
            'total' => $amountForPayment,
        ],
        'notes' => $notes,
        'preparedBy' => $prepared,
        'notedBy' => [
            'name' => soa_text($settings['noted_by_name'] ?? null, 'Authorized IHC Representative'),
            'position' => soa_text($settings['noted_by_position'] ?? null, 'Billing Manager'),
            'contact' => soa_text($settings['noted_by_contact'] ?? null, 'IHC Billing Office'),
        ],
    ];
}
