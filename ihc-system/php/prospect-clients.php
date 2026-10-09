<?php
declare(strict_types=1);

/**
 * Prospect clients (php/prospect-clients.php)
 *
 * Buyers who register through a HOLDING FEE or a RESERVATION *before* a
 * contract exists. The clerk dashboard's New Contract → "Select Client"
 * dropdown reads this table so their details can be auto-filled into the
 * New Contract form, and backend/db.php links the row (plus its fees) to the
 * contract once it is created.
 *
 * Shared by php/api_holding_reservation.php (fee forms + client list) and
 * backend/db.php (contract creation), so keep it PDO-only: no headers, no
 * output, no side effects outside the database. Schema is self-healed on
 * every call (the AGENTS.md self-heal pattern).
 */

function prospect_ensure_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS prospect_clients (
      id INT AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(255) NOT NULL,
      email VARCHAR(255) NULL,
      cellphone_number VARCHAR(50) NULL,
      client_address VARCHAR(500) NULL,
      property_address VARCHAR(500) NULL,
      project_name VARCHAR(255) NULL,
      project_phase VARCHAR(120) NULL,
      block_no VARCHAR(50) NULL,
      lot_no VARCHAR(50) NULL,
      model_type VARCHAR(120) NULL,
      lot_area VARCHAR(100) NULL,
      floor_area VARCHAR(100) NULL,
      source ENUM('HOLDING','RESERVATION') NOT NULL DEFAULT 'HOLDING',
      holding_fee_id INT NULL,
      reservation_fee_id INT NULL,
      contract_id INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_prospect_email (email),
      KEY idx_prospect_contract (contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Fee tables gain a prospect link, and both must tolerate a NULL contract
    // so a Holding Fee / Reservation can be recorded before the contract
    // exists (holding_fees already ships nullable contract_id on legacy DBs).
    $addIfMissing = static function (PDO $pdo, string $table, string $column, string $definition, string $after = ''): void {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $table) as $c) $cols[strtolower($c['Field'])] = true;
        if (!isset($cols[strtolower($column)])) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition . ($after !== '' ? ' AFTER `' . $after . '`' : ''));
        }
    };
    $addIfMissing($pdo, 'holding_fees', 'prospect_client_id', 'INT NULL');
    $addIfMissing($pdo, 'holding_fees', 'client_name', "VARCHAR(255) NOT NULL DEFAULT ''");
    $addIfMissing($pdo, 'holding_fees', 'property_address', "VARCHAR(500) NOT NULL DEFAULT ''");
    $addIfMissing($pdo, 'holding_fees', 'officer_id', 'VARCHAR(100) NULL');
    $addIfMissing($pdo, 'reservation_fees', 'prospect_client_id', 'INT NULL');

    // reservation_fees.contract_id was NOT NULL before this feature — relax it
    // so pre-contract reservations can be stored (FKs, if any, allow NULL).
    $rfNullable = null;
    foreach ($pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation_fees' AND COLUMN_NAME = 'contract_id'") as $row) {
        $rfNullable = (string)$row['IS_NULLABLE'];
    }
    if ($rfNullable === 'NO') {
        $pdo->exec('ALTER TABLE reservation_fees MODIFY COLUMN contract_id INT NULL');
    }

    $hasIdx = static function (PDO $pdo, string $table, string $name): bool {
        foreach ($pdo->query('SHOW INDEX FROM `' . $table . '`') as $r) {
            if ($r['Key_name'] === $name) return true;
        }
        return false;
    };
    if (!$hasIdx($pdo, 'holding_fees', 'idx_hf_prospect')) $pdo->exec('ALTER TABLE holding_fees ADD KEY idx_hf_prospect (prospect_client_id)');
    if (!$hasIdx($pdo, 'reservation_fees', 'idx_rf_prospect')) $pdo->exec('ALTER TABLE reservation_fees ADD KEY idx_rf_prospect (prospect_client_id)');
}

function prospect_shape(array $r): array
{
    $holdingId = isset($r['holding_fee_id']) && $r['holding_fee_id'] !== null ? (int)$r['holding_fee_id'] : null;
    $resvId = isset($r['reservation_fee_id']) && $r['reservation_fee_id'] !== null ? (int)$r['reservation_fee_id'] : null;
    $sources = [];
    if ($holdingId !== null) $sources[] = 'Holding Fee';
    if ($resvId !== null) $sources[] = 'Reservation';
    if (!$sources) $sources[] = strtoupper((string)($r['source'] ?? 'HOLDING')) === 'RESERVATION' ? 'Reservation' : 'Holding Fee';

    return [
        'id' => (int)$r['id'],
        'fullName' => (string)$r['full_name'],
        'email' => $r['email'] !== null && $r['email'] !== '' ? (string)$r['email'] : null,
        'phone' => $r['cellphone_number'] !== null && $r['cellphone_number'] !== '' ? (string)$r['cellphone_number'] : null,
        'address' => $r['client_address'] !== null && $r['client_address'] !== '' ? (string)$r['client_address'] : null,
        'propertyAddress' => $r['property_address'] !== null && $r['property_address'] !== '' ? (string)$r['property_address'] : null,
        'projectName' => $r['project_name'] ?? null,
        'phase' => $r['project_phase'] ?? null,
        'blockNo' => $r['block_no'] ?? null,
        'lotNo' => $r['lot_no'] ?? null,
        'modelType' => $r['model_type'] ?? null,
        'lotArea' => $r['lot_area'] ?? null,
        'floorArea' => $r['floor_area'] ?? null,
        'source' => (string)($r['source'] ?? 'HOLDING'),
        'sourceLabel' => implode(' & ', $sources),
        'holdingFeeId' => $holdingId,
        'reservationFeeId' => $resvId,
        'contractId' => isset($r['contract_id']) && $r['contract_id'] !== null ? (int)$r['contract_id'] : null,
        'createdAt' => $r['created_at'] ?? null,
        'updatedAt' => $r['updated_at'] ?? null,
    ];
}

function prospect_find(PDO $pdo, int $id): ?array
{
    if ($id <= 0) return null;
    $st = $pdo->prepare('SELECT * FROM prospect_clients WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function prospect_list(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM prospect_clients ORDER BY full_name ASC, id ASC')->fetchAll();
    return array_map('prospect_shape', $rows);
}

// Match the given client against an existing row so repeat registrations
// never duplicate: by email first (case-insensitive), then by full name plus
// cellphone. Returns the matching row or null.
function prospect_match(PDO $pdo, string $name, string $email, string $phone): ?array
{
    if ($email !== '') {
        $st = $pdo->prepare('SELECT * FROM prospect_clients WHERE LOWER(email) = LOWER(?) ORDER BY id LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row) return $row;
    }
    if ($name === '') return null;
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    $st = $pdo->prepare('SELECT * FROM prospect_clients WHERE LOWER(full_name) = LOWER(?) ORDER BY id LIMIT 20');
    $st->execute([$name]);
    foreach ($st->fetchAll() as $row) {
        $stored = preg_replace('/\D/', '', (string)($row['cellphone_number'] ?? '')) ?? '';
        // Same name and either side has no phone yet → treat as the same buyer.
        if ($digits === '' || $stored === '' || $digits === $stored) return $row;
    }
    return null;
}

/**
 * Insert or update a prospect client (deduplicated). $in keys:
 *   fullName (required), email, phone, address, propertyAddress, projectName,
 *   phase, blockNo, lotNo, modelType, lotArea, floorArea, source,
 *   holdingFeeId, reservationFeeId.
 * Returns the stored row. Throws RuntimeException on invalid/incomplete input.
 */
function prospect_upsert(PDO $pdo, array $in): array
{
    $name = trim((string)($in['fullName'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $address = trim((string)($in['address'] ?? ''));
    $property = trim((string)($in['propertyAddress'] ?? ''));
    $source = strtoupper(trim((string)($in['source'] ?? 'HOLDING'))) === 'RESERVATION' ? 'RESERVATION' : 'HOLDING';
    $holdingFeeId = isset($in['holdingFeeId']) && $in['holdingFeeId'] !== null && $in['holdingFeeId'] !== '' ? (int)$in['holdingFeeId'] : null;
    $resvFeeId = isset($in['reservationFeeId']) && $in['reservationFeeId'] !== null && $in['reservationFeeId'] !== '' ? (int)$in['reservationFeeId'] : null;

    if ($name === '') throw new RuntimeException('Client name is required.');
    if (mb_strlen($name) > 255) throw new RuntimeException('Client name is too long.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid client email address.');
    if ($email !== '' && mb_strlen($email) > 255) throw new RuntimeException('Client email is too long.');
    if ($phone !== '') {
        $digits = preg_replace('/[\s\-().]/', '', $phone) ?? '';
        if (!preg_match('/^09\d{9}$/', $digits) && !preg_match('/^\+639\d{9}$/', $digits)) {
            throw new RuntimeException('Cellphone number must be a valid PH mobile (e.g., 0917 123 4567).');
        }
        $phone = $digits;
        if (strlen($phone) > 50) throw new RuntimeException('Cellphone number is too long.');
    }

    $existing = prospect_match($pdo, $name, $email, $phone);

    $set = static function (?string $current, string $incoming): ?string {
        $incoming = trim($incoming);
        if ($incoming === '') return $current !== null && $current !== '' ? $current : null;
        return mb_substr($incoming, 0, 500);
    };

    if ($existing) {
        // Latest non-empty value wins; a later reservation also upgrades source.
        $newSource = (string)$existing['source'];
        if ($resvFeeId !== null) $newSource = 'RESERVATION';
        elseif ($holdingFeeId !== null && $newSource !== 'RESERVATION') $newSource = 'HOLDING';
        $pdo->prepare(
            'UPDATE prospect_clients SET
                full_name = ?, email = ?, cellphone_number = ?, client_address = ?,
                property_address = ?, project_name = ?, project_phase = ?, block_no = ?, lot_no = ?,
                model_type = ?, lot_area = ?, floor_area = ?,
                source = ?, holding_fee_id = COALESCE(?, holding_fee_id), reservation_fee_id = COALESCE(?, reservation_fee_id),
                updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $name,
            $email !== '' ? $email : ($existing['email'] ?? null),
            $phone !== '' ? $phone : ($existing['cellphone_number'] ?? null),
            $set($existing['client_address'] ?? null, $address),
            $set($existing['property_address'] ?? null, $property),
            $set($existing['project_name'] ?? null, (string)($in['projectName'] ?? '')),
            $set($existing['project_phase'] ?? null, (string)($in['phase'] ?? '')),
            $set($existing['block_no'] ?? null, (string)($in['blockNo'] ?? '')),
            $set($existing['lot_no'] ?? null, (string)($in['lotNo'] ?? '')),
            $set($existing['model_type'] ?? null, (string)($in['modelType'] ?? '')),
            $set($existing['lot_area'] ?? null, (string)($in['lotArea'] ?? '')),
            $set($existing['floor_area'] ?? null, (string)($in['floorArea'] ?? '')),
            $newSource,
            $holdingFeeId,
            $resvFeeId,
            (int)$existing['id'],
        ]);
        $row = prospect_find($pdo, (int)$existing['id']);
        if (!$row) throw new RuntimeException('Client record could not be updated.');
        return $row;
    }

    // Name-only prospects are allowed (the Holding Fee form only requires
    // the name); email/phone/address are validated above whenever supplied.
    $pdo->prepare(
        'INSERT INTO prospect_clients
            (full_name, email, cellphone_number, client_address, property_address,
             project_name, project_phase, block_no, lot_no, model_type, lot_area, floor_area,
             source, holding_fee_id, reservation_fee_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $name,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $address !== '' ? mb_substr($address, 0, 500) : null,
        $property !== '' ? mb_substr($property, 0, 500) : null,
        ($in['projectName'] ?? '') !== '' ? mb_substr(trim((string)$in['projectName']), 0, 255) : null,
        ($in['phase'] ?? '') !== '' ? mb_substr(trim((string)$in['phase']), 0, 120) : null,
        ($in['blockNo'] ?? '') !== '' ? mb_substr(trim((string)$in['blockNo']), 0, 50) : null,
        ($in['lotNo'] ?? '') !== '' ? mb_substr(trim((string)$in['lotNo']), 0, 50) : null,
        ($in['modelType'] ?? '') !== '' ? mb_substr(trim((string)$in['modelType']), 0, 120) : null,
        ($in['lotArea'] ?? '') !== '' ? mb_substr(trim((string)$in['lotArea']), 0, 100) : null,
        ($in['floorArea'] ?? '') !== '' ? mb_substr(trim((string)$in['floorArea']), 0, 100) : null,
        $source,
        $holdingFeeId,
        $resvFeeId,
    ]);
    $row = prospect_find($pdo, (int)$pdo->lastInsertId());
    if (!$row) throw new RuntimeException('Client record could not be saved.');
    return $row;
}

/**
 * Link a prospect (and every still-unlinked Holding Fee / Reservation of
 * theirs) to a newly created contract. Safe to call once per contract; runs
 * inside the caller's transaction (backend/db.php). Returns true when the
 * row was linked now.
 */
function prospect_link_contract(PDO $pdo, int $prospectId, int $contractId): bool
{
    if ($prospectId <= 0 || $contractId <= 0) return false;
    $st = $pdo->prepare('SELECT contract_id FROM prospect_clients WHERE id = ? LIMIT 1');
    $st->execute([$prospectId]);
    $row = $st->fetch();
    if (!$row || $row['contract_id'] !== null) return false;
    $pdo->prepare('UPDATE prospect_clients SET contract_id = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$contractId, $prospectId]);
    $pdo->prepare('UPDATE holding_fees SET contract_id = ?, updated_at = NOW() WHERE prospect_client_id = ? AND contract_id IS NULL')
        ->execute([$contractId, $prospectId]);
    $pdo->prepare('UPDATE reservation_fees SET contract_id = ?, updated_at = NOW() WHERE prospect_client_id = ? AND contract_id IS NULL')
        ->execute([$contractId, $prospectId]);
    return true;
}
