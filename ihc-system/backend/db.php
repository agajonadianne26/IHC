<?php
declare(strict_types=1);
require_once __DIR__ . '/../php/db-config.php';

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

try {
    $pdo = ihc_pdo();

    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON request.');

    $client = is_array($data['client'] ?? null) ? $data['client'] : [];
    $contract = is_array($data['contract'] ?? null) ? $data['contract'] : [];

    $officerId = trim((string)($data['officerId'] ?? ''));
    $clientName = trim((string)($client['fullName'] ?? ''));
    $email = trim((string)($client['email'] ?? ''));
    $phone = trim((string)($client['phone'] ?? ''));
    $clientAddress = trim((string)($client['address'] ?? ''));
    $propertyAddress = trim((string)($contract['propertyAddress'] ?? ''));
    $projectName = trim((string)($contract['projectName'] ?? ''));
    $projectPhase = trim((string)($contract['phase'] ?? ''));
    $blockNo = trim((string)($contract['blockNo'] ?? ''));
    $lotNo = trim((string)($contract['lotNo'] ?? ''));
    $modelType = trim((string)($contract['modelType'] ?? ''));
    $lotArea = trim((string)($contract['lotArea'] ?? ''));
    $floorArea = trim((string)($contract['floorArea'] ?? ''));
    $discountAmount = $contract['discountAmount'] ?? 0;
    $loanableAmount = $contract['loanableAmount'] ?? null;
    $approvedLoanAmount = $contract['approvedLoanAmount'] ?? null;
    $earlyMoveInAmount = $contract['earlyMoveInAmount'] ?? 0;
    $equityMonthlyRate = $contract['equityMonthlyRate'] ?? 0;
    $equityPenaltyRate = $contract['equityPenaltyRate'] ?? null;
    $loanTermYears = $contract['loanTermYears'] ?? null;
    $totalPrice = $contract['totalPrice'] ?? null;
    $downpayment = $contract['downpayment'] ?? null;
    $terms = $contract['terms'] ?? null;
    $startDate = trim((string)($contract['startDate'] ?? ''));

    if ($clientName==='' || $email==='' || $phone==='' || $propertyAddress==='' || $totalPrice===null || $totalPrice==='' || $downpayment===null || $downpayment==='' || $terms===null || $terms==='' || $startDate==='') {
        throw new RuntimeException('All New Contract fields are required.');
    }
    if (!ctype_digit($phone) || strlen($phone) !== 11) {
        throw new RuntimeException('Cellphone number must be exactly 11 digits.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
    if (!is_numeric($totalPrice) || (float)$totalPrice < 0) throw new RuntimeException('Invalid total contract price.');
    if (!is_numeric($downpayment) || (float)$downpayment < 0) throw new RuntimeException('Invalid downpayment.');
    if ((float)$downpayment > (float)$totalPrice) throw new RuntimeException('Downpayment cannot exceed the total contract price.');
    if (!ctype_digit((string)$terms) || (int)$terms < 1) throw new RuntimeException('Installment terms must be a positive whole number.');

    if ($discountAmount === '' || $discountAmount === null) $discountAmount = 0;
    if (!is_numeric($discountAmount) || (float)$discountAmount < 0 || (float)$discountAmount > (float)$totalPrice) {
        throw new RuntimeException('Discount must be between zero and the total contract price.');
    }
    $discountAmount = round((float)$discountAmount, 2);
    $normalizeOptionalAmount = static function ($value, float $maximum, string $label): ?float {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value) || (float)$value < 0 || (float)$value > $maximum) {
            throw new RuntimeException($label . ' is outside the valid amount range.');
        }
        return round((float)$value, 2);
    };
    $loanableAmount = $normalizeOptionalAmount($loanableAmount, (float)$totalPrice, 'Loanable amount');
    $approvedLoanAmount = $normalizeOptionalAmount($approvedLoanAmount, (float)$totalPrice, 'Approved loan amount');
    $earlyMoveInAmount = $normalizeOptionalAmount($earlyMoveInAmount, (float)$totalPrice, 'Early move-in amount') ?? 0.0;
    $reservationFee = $contract['reservationFee'] ?? null;
    if ($reservationFee === '' || $reservationFee === null) $reservationFee = 0;
    if (!is_numeric($reservationFee) || (float)$reservationFee < 0 || (float)$reservationFee > (float)$totalPrice) {
        throw new RuntimeException('Reservation fee must be between zero and the total contract price.');
    }
    $reservationFee = round((float)$reservationFee, 2);
    $holdingFee = $contract['holdingFee'] ?? null;
    if ($holdingFee === '' || $holdingFee === null) $holdingFee = 0;
    if (!is_numeric($holdingFee) || (float)$holdingFee < 0 || (float)$holdingFee > (float)$totalPrice) {
        throw new RuntimeException('Holding fee must be between zero and the total contract price.');
    }
    $holdingFee = round((float)$holdingFee, 2);
    $equityMonthlyRate = $normalizeOptionalAmount($equityMonthlyRate, 100, 'Equity monthly interest rate') ?? 0.0;
    $equityPenaltyRate = $normalizeOptionalAmount($equityPenaltyRate, 100, 'Equity penalty rate');
    $loanTermYears = $normalizeOptionalAmount($loanTermYears, 120, 'Loan term in years');
    foreach ([
        'Client address' => [$clientAddress, 500],
        'Project name' => [$projectName, 255],
        'Project phase' => [$projectPhase, 120],
        'Block number' => [$blockNo, 50],
        'Lot number' => [$lotNo, 50],
        'Model type' => [$modelType, 120],
        'Lot area' => [$lotArea, 100],
        'Floor area' => [$floorArea, 100],
    ] as $label => [$value, $maxLength]) {
        if (mb_strlen($value) > $maxLength) throw new RuntimeException($label . ' is too long.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $startDate);
    if (!$date || $date->format('Y-m-d') !== $startDate) throw new RuntimeException('Invalid start date.');

    // Downpayment plan + financing terms — the rest of the New Contract form.
    // These used to be collected and then silently dropped (they only ever
    // reached localStorage), so the database never knew a contract's bank or
    // deposit mode. Validation mirrors the form's own rules.
    $dpMode = trim((string)($contract['dpMode'] ?? 'lump'));
    if ($dpMode === '') $dpMode = 'lump';
    if (!in_array($dpMode, ['lump', 'monthly'], true)) throw new RuntimeException('Downpayment mode must be Lump Sum or Monthly.');

    $dpTermsRaw = $contract['dpTerms'] ?? null;
    if ($dpMode === 'monthly') {
        if ($dpTermsRaw === null || $dpTermsRaw === '' || !ctype_digit((string)$dpTermsRaw) || (int)$dpTermsRaw < 1 || (int)$dpTermsRaw > 60) {
            throw new RuntimeException('Deposit term must be between 1 and 60 months when the downpayment is spread monthly.');
        }
        $dpTerms = (int)$dpTermsRaw;
    } else {
        $dpTerms = null; // Lump sum has no deposit term.
    }

    $bank = trim((string)($contract['bank'] ?? ''));
    if ($bank === '') throw new RuntimeException('Financing bank is required.');
    if (mb_strlen($bank) > 120) throw new RuntimeException('Bank name must be 120 characters or fewer.');

    $annualRateRaw = $contract['annualRate'] ?? 0;
    if ($annualRateRaw === null || $annualRateRaw === '') $annualRateRaw = 0;
    if (!is_numeric($annualRateRaw) || (float)$annualRateRaw < 0 || (float)$annualRateRaw > 100) {
        throw new RuntimeException('Annual interest rate must be between 0 and 100.');
    }
    $annualRate = round((float)$annualRateRaw, 2);

    $portalPassword = (string)($client['portalPassword'] ?? '');
    if ($portalPassword === '') throw new RuntimeException('Client Portal Password is required so the buyer can sign in.');
    if (strlen($portalPassword) < 6) throw new RuntimeException('Portal password must be at least 6 characters.');

    // Add fields required by the New Contract form if an older contracts table lacks them.
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM contracts') as $column) $columns[strtolower($column['Field'])] = true;
    if (!isset($columns['property_address'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN property_address VARCHAR(500) NULL');
    if (!isset($columns['officer_id'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN officer_id VARCHAR(100) NULL');
    if (!isset($columns['dp_mode'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN dp_mode VARCHAR(10) NULL');
    if (!isset($columns['dp_terms'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN dp_terms INT NULL');
    if (!isset($columns['bank_name'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN bank_name VARCHAR(120) NULL');
    if (!isset($columns['annual_interest_rate'])) $pdo->exec('ALTER TABLE contracts ADD COLUMN annual_interest_rate DECIMAL(5,2) NULL');
    $soaColumns = [
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
    foreach ($soaColumns as $column => $definition) {
        if (!isset($columns[$column])) $pdo->exec('ALTER TABLE contracts ADD COLUMN `' . $column . '` ' . $definition);
    }

    // Property inventory (property_units) powers the New Contract
    // address <-> phase/block/lot autofill and the admin Property Inventory
    // editor. A first-time address is inserted here so the next clerk finds
    // it; a known address only gets its contract link refreshed. DDL must
    // stay outside the transaction below — MySQL commits implicitly on DDL.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS property_units (
            id INT NOT NULL AUTO_INCREMENT,
            project VARCHAR(255) NULL,
            phase VARCHAR(120) NULL,
            building VARCHAR(255) NULL,
            unit_number VARCHAR(100) NULL,
            model_type VARCHAR(120) NULL,
            lot_area VARCHAR(100) NULL,
            floor_area VARCHAR(100) NULL,
            display_label VARCHAR(255) NOT NULL,
            status ENUM('AVAILABLE','ON HOLD','RESERVED','SOLD') NOT NULL DEFAULT 'AVAILABLE',
            current_client_id INT NULL,
            current_contract_id INT NULL,
            current_holding_fee_id INT NULL,
            current_reservation_fee_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_property_units_label (display_label),
            KEY idx_property_units_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $unitColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM property_units') as $column) $unitColumns[strtolower($column['Field'])] = true;
    $unitAlter = [
        'phase' => 'VARCHAR(120) NULL',
        'model_type' => 'VARCHAR(120) NULL',
        'lot_area' => 'VARCHAR(100) NULL',
        'floor_area' => 'VARCHAR(100) NULL',
    ];
    foreach ($unitAlter as $column => $definition) {
        if (!isset($unitColumns[$column])) $pdo->exec('ALTER TABLE property_units ADD COLUMN `' . $column . '` ' . $definition);
    }

    // Client portal login accounts (one row per email, so a repeat buyer's
    // credentials are updated instead of duplicated). Passwords are stored as
    // bcrypt hashes and verified by php/api_client.php at login. DDL must stay
    // outside the transaction below — MySQL commits implicitly on DDL.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS client_accounts (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            cellphone_number VARCHAR(50) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_client_accounts_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    // Contract and login account are written together: either both save or neither.
    $pdo->beginTransaction();
    try {
        $sql = 'INSERT INTO contracts
                    (client_name,email,cellphone_number,property_address,total_contract_price,downpayment,
                     installment_terms,start_date,officer_id,dp_mode,dp_terms,bank_name,annual_interest_rate,
                     discount_amount,loanable_amount,approved_loan_amount,early_move_in_amount,
                     project_name,project_phase,block_no,lot_no,model_type,lot_area,floor_area,client_address,
                     equity_monthly_rate,equity_penalty_rate,loan_term_years)
                VALUES
                    (:client_name,:email,:cellphone_number,:property_address,:total_contract_price,:downpayment,
                     :installment_terms,:start_date,:officer_id,:dp_mode,:dp_terms,:bank_name,:annual_interest_rate,
                     :discount_amount,:loanable_amount,:approved_loan_amount,:early_move_in_amount,
                     :project_name,:project_phase,:block_no,:lot_no,:model_type,:lot_area,:floor_area,:client_address,
                     :equity_monthly_rate,:equity_penalty_rate,:loan_term_years)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':client_name'=>$clientName, ':email'=>$email, ':cellphone_number'=>$phone, ':property_address'=>$propertyAddress,
            ':total_contract_price'=>(float)$totalPrice, ':downpayment'=>(float)$downpayment, ':installment_terms'=>(int)$terms,
            ':start_date'=>$startDate, ':officer_id'=>$officerId !== '' ? $officerId : null,
            ':dp_mode'=>$dpMode, ':dp_terms'=>$dpTerms, ':bank_name'=>$bank, ':annual_interest_rate'=>$annualRate,
            ':discount_amount'=>$discountAmount, ':loanable_amount'=>$loanableAmount,
            ':approved_loan_amount'=>$approvedLoanAmount, ':early_move_in_amount'=>$earlyMoveInAmount,
            ':project_name'=>$projectName !== '' ? $projectName : null,
            ':project_phase'=>$projectPhase !== '' ? $projectPhase : null,
            ':block_no'=>$blockNo !== '' ? $blockNo : null,
            ':lot_no'=>$lotNo !== '' ? $lotNo : null,
            ':model_type'=>$modelType !== '' ? $modelType : null,
            ':lot_area'=>$lotArea !== '' ? $lotArea : null,
            ':floor_area'=>$floorArea !== '' ? $floorArea : null,
            ':client_address'=>$clientAddress !== '' ? $clientAddress : null,
            ':equity_monthly_rate'=>$equityMonthlyRate,
            ':equity_penalty_rate'=>$equityPenaltyRate,
            ':loan_term_years'=>$loanTermYears,
        ]);

        $contractId = (int)$pdo->lastInsertId();

        // Register the property so the New Contract autofill and the admin
        // Property Inventory editor can find it next time. A brand-new
        // address is inserted with the clerk's phase/block/lot; an address
        // that already exists keeps its curated details and only gains the
        // contract link (plus any part the clerk actually supplied).
        $unitLabel = mb_substr(trim($propertyAddress), 0, 255);
        $unitLookup = $pdo->prepare('SELECT * FROM property_units WHERE display_label=? LIMIT 1');
        $unitLookup->execute([$unitLabel]);
        $existingUnit = $unitLookup->fetch();
        $propertyCreated = false;
        $propertyUnitId = $existingUnit ? (int)$existingUnit['id'] : null;
        if ($existingUnit) {
            $pdo->prepare(
                'UPDATE property_units SET
                    current_contract_id = :contract_id,
                    project     = COALESCE(:project, project),
                    phase       = COALESCE(:phase, phase),
                    building    = COALESCE(:building, building),
                    unit_number = COALESCE(:unit_number, unit_number),
                    model_type  = COALESCE(:model_type, model_type),
                    lot_area    = COALESCE(:lot_area, lot_area),
                    floor_area  = COALESCE(:floor_area, floor_area)
                 WHERE id = :id'
            )->execute([
                ':contract_id'=>$contractId,
                ':project'    =>$projectName    !== '' ? mb_substr($projectName, 0, 255) : null,
                ':phase'      =>$projectPhase  !== '' ? mb_substr($projectPhase, 0, 120) : null,
                ':building'   =>$blockNo       !== '' ? mb_substr($blockNo, 0, 255) : null,
                ':unit_number'=>$lotNo         !== '' ? mb_substr($lotNo, 0, 100) : null,
                ':model_type' =>$modelType     !== '' ? mb_substr($modelType, 0, 120) : null,
                ':lot_area'   =>$lotArea       !== '' ? mb_substr($lotArea, 0, 100) : null,
                ':floor_area' =>$floorArea     !== '' ? mb_substr($floorArea, 0, 100) : null,
                ':id'         =>(int)$existingUnit['id'],
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO property_units
                    (display_label,project,phase,building,unit_number,model_type,lot_area,floor_area,status,current_contract_id)
                 VALUES (?,?,?,?,?,?,?,?,\'AVAILABLE\',?)'
            )->execute([
                $unitLabel,
                $projectName   !== '' ? mb_substr($projectName, 0, 255) : null,
                $projectPhase  !== '' ? mb_substr($projectPhase, 0, 120) : null,
                $blockNo       !== '' ? mb_substr($blockNo, 0, 255) : null,
                $lotNo         !== '' ? mb_substr($lotNo, 0, 100) : null,
                $modelType     !== '' ? mb_substr($modelType, 0, 120) : null,
                $lotArea       !== '' ? mb_substr($lotArea, 0, 100) : null,
                $floorArea     !== '' ? mb_substr($floorArea, 0, 100) : null,
                $contractId,
            ]);
            $propertyCreated = true;
            $propertyUnitId = (int)$pdo->lastInsertId();
        }

        // Persist any reservation/holding fees collected with this contract so
        // they land in the buyer's ledger, the SOA (reservation fee section and
        // totals), and the holding/reservation modules for that unit.
        $holdingFeeId = null;
        if ($holdingFee > 0) {
            $pdo->prepare(
                'INSERT INTO holding_fees
                    (officer_id, contract_id, property_unit_id, client_name, property_address,
                     amount, payment_method, payment_date, status, remarks, processed_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $officerId !== '' ? $officerId : null,
                $contractId,
                $propertyUnitId,
                $clientName,
                mb_substr($unitLabel, 0, 500),
                $holdingFee,
                'To be collected',
                $startDate,
                'PENDING',
                'Set at contract creation.',
                $officerId !== '' ? $officerId : null,
            ]);
            $holdingFeeId = (int)$pdo->lastInsertId();
        }
        if ($reservationFee > 0) {
            $pdo->prepare(
                'INSERT INTO reservation_fees
                    (contract_id, property_unit_id, holding_fee_id, amount, payment_method,
                     payment_date, status, remarks, processed_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $contractId,
                $propertyUnitId,
                $holdingFeeId,
                $reservationFee,
                'To be collected',
                $startDate,
                'PENDING',
                'Set at contract creation.',
                $officerId !== '' ? $officerId : null,
            ]);
        }

        // Create the buyer's portal account, or update the existing one when
        // this email has signed up before (new password/name/phone win).
        $acctStmt = $pdo->prepare(
            'INSERT INTO client_accounts (email, password_hash, full_name, cellphone_number)
             VALUES (:email, :password_hash, :full_name, :cellphone_number)
             ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                full_name = VALUES(full_name),
                cellphone_number = VALUES(cellphone_number)'
        );
        $acctStmt->execute([
            ':email'=>$email,
            ':password_hash'=>password_hash($portalPassword, PASSWORD_DEFAULT),
            ':full_name'=>$clientName,
            ':cellphone_number'=>$phone
        ]);
        // MySQL row count for INSERT ... ON DUPLICATE KEY UPDATE:
        // 1 = account created, 2 = existing account updated, 0 = updated but unchanged.
        $acctRows = $acctStmt->rowCount();
        $accountAction = $acctRows === 1 ? 'created' : ($acctRows === 0 ? 'unchanged' : 'updated');

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Send the three-day reminder immediately when a clerk creates a contract
    // whose first payment is due in exactly three calendar days. This closes
    // the gap when the contract is entered after the daily scheduler ran.
    $today = new DateTimeImmutable('today');
    $daysUntilDue = (int)$today->diff($date)->format('%r%a');
    $reminderQueued = false;
    if ($daysUntilDue === 3) {
        $serviceUrl = rtrim(getenv('REMINDER_SERVICE_URL') ?: 'http://127.0.0.1:3000', '/');
        $payload = json_encode([
            'client' => $clientName,
            'amount' => (float)$downpayment,
            'dueDate' => $startDate,
            'recipientEmail' => $email,
            'recipientPhone' => $phone,
            'reminderType' => 'due_soon'
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true
        ]]);
        // A mail-service outage must never roll back a successfully saved contract.
        $response = @file_get_contents($serviceUrl . '/api/contracts/IHC-' . $contractId . '/send-reminder', false, $context);
        $responseData = is_string($response) ? json_decode($response, true) : null;
        $reminderQueued = is_array($responseData) && !empty($responseData['success']);
    }

    echo json_encode([
        'success'=>true,
        'message'=>'Contract created and saved to the IHC database.',
        'id'=>$contractId,
        'accountAction'=>$accountAction,
        'propertyCreated'=>$propertyCreated,
        'reminderQueued'=>$reminderQueued,
        // Echo the stored terms so the clerk's local mirror matches the row.
        'dpMode'=>$dpMode,
        'dpTerms'=>$dpTerms,
        'bank'=>$bank,
        'annualRate'=>$annualRate,
        'discountAmount'=>$discountAmount,
        'loanableAmount'=>$loanableAmount,
        'approvedLoanAmount'=>$approvedLoanAmount
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
