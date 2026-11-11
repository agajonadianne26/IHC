<?php
declare(strict_types=1);

/**
 * Schema self-heal for the collection-Excel import (php/api_import.php) and
 * the admin Staff modal's officer code (php/api_admin.php). Mirrors
 * backend/migrations/014_legacy_account_import_ihc.sql. DDL commits
 * implicitly in MySQL, so call these OUTSIDE any transaction.
 */

function ihc_ensure_officer_code(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM officers') as $c) $cols[$c['Field']] = true;
    if (!isset($cols['officer_code'])) {
        $pdo->exec('ALTER TABLE officers ADD COLUMN officer_code VARCHAR(20) NULL');
        $pdo->exec('ALTER TABLE officers ADD UNIQUE KEY uq_officers_code (officer_code)');
    }
    $done = true;
}

function ihc_ensure_legacy_contract_columns(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM contracts') as $c) $cols[$c['Field']] = true;
    $add = [
        'is_legacy'             => 'TINYINT(1) NOT NULL DEFAULT 0',
        'needs_completion'      => 'TINYINT(1) NOT NULL DEFAULT 0',
        'legacy_code'           => 'VARCHAR(30) NULL',
        'buyer_code'            => 'VARCHAR(30) NULL',
        'company'               => 'VARCHAR(20) NULL',
        'branch'                => 'VARCHAR(60) NULL',
        'financing_type'        => 'VARCHAR(60) NULL',
        'receivable_status'     => 'VARCHAR(60) NULL',
        'collection_type'       => 'VARCHAR(60) NULL',
        'sales_officer'         => 'VARCHAR(100) NULL',
        'rs_date'               => 'DATE NULL',
        'pdc'                   => 'VARCHAR(20) NULL',
        'account_status'        => 'VARCHAR(100) NULL',
        'account_remarks'       => 'VARCHAR(255) NULL',
        'monthly_amortization'  => 'DECIMAL(15,2) NULL',
        'opening_balance'       => 'DECIMAL(15,2) NULL',
        'opening_balance_as_of' => 'DATE NULL',
        'legacy_data'           => 'LONGTEXT NULL',
        'imported_at'           => 'DATETIME NULL',
        'imported_by'           => 'VARCHAR(100) NULL',
        'import_batch_id'       => 'INT NULL',
        // Imported without a usable monthly amount (or balance): no schedule,
        // no dues, no reminders until the clerk sets the monthly.
        'needs_monthly'         => 'TINYINT(1) NOT NULL DEFAULT 0',
        // The Excel's month-by-month projection {"2026-11": 10420.22, ...};
        // the "Expected (Excel)" figure in Dues & Performance sums these.
        'legacy_projection'     => 'LONGTEXT NULL',
    ];
    foreach ($add as $name => $def) {
        if (!isset($cols[$name])) $pdo->exec('ALTER TABLE contracts ADD COLUMN `' . $name . '` ' . $def);
    }
    if (!isset($cols['legacy_code'])) {
        $pdo->exec('ALTER TABLE contracts ADD KEY idx_contracts_legacy_code (legacy_code)');
    }
    if (!isset($cols['legacy_projection'])) ihc_backfill_projection($pdo);
    $done = true;
}

/**
 * Month columns of the collection Excel → {"YYYY-MM": amount}. The sheet has
 * JAN..DEC of the cutoff year, then JAN..DEC of the next year (saved in
 * legacy_data as "JAN (2)".."DEC (2)"). Text cells ("N/A", "advance") and
 * zeros are left out, exactly like the Excel's own SUM.
 */
function ihc_projection_from_raw(array $raw, int $year): array {
    $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $out = [];
    foreach ([0 => '', 1 => ' (2)'] as $yOff => $suffix) {
        foreach ($months as $i => $m) {
            $v = $raw[$m . $suffix] ?? null;
            if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', trim($v)))) {
                $n = (float)$v;
                if (abs($n) > 0.0001) $out[sprintf('%04d-%02d', $year + $yOff, $i + 1)] = $n;
            }
        }
    }
    return $out;
}

/** One-time fill of legacy_projection for accounts imported before the column existed. */
function ihc_backfill_projection(PDO $pdo): void {
    $rows = $pdo->query('SELECT id, legacy_data, opening_balance_as_of FROM contracts
                         WHERE legacy_data IS NOT NULL AND legacy_projection IS NULL')->fetchAll();
    $upd = $pdo->prepare('UPDATE contracts SET legacy_projection = ? WHERE id = ?');
    foreach ($rows as $r) {
        $raw = json_decode((string)$r['legacy_data'], true);
        if (!is_array($raw)) continue;
        $year = $r['opening_balance_as_of'] ? (int)substr((string)$r['opening_balance_as_of'], 0, 4) : (int)date('Y');
        $upd->execute([json_encode(ihc_projection_from_raw($raw, $year)), (int)$r['id']]);
    }
}

/**
 * One row per Import commit. A batch stays FOR_VERIFICATION until the clerk
 * confirms it: until then its accounts can be edited or the whole batch undone
 * (php/api_import.php), and automated reminders skip them. The id lists let an
 * undo remove exactly what the import created and restore units it relinked.
 */
function ihc_ensure_import_batches(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS import_batches (
            id INT NOT NULL AUTO_INCREMENT,
            officer_id VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'FOR_VERIFICATION',
            file_name VARCHAR(255) NULL,
            cutoff_label VARCHAR(40) NULL,
            as_of DATE NULL,
            contracts_count INT NOT NULL DEFAULT 0,
            payments_count INT NOT NULL DEFAULT 0,
            total_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_collected DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_ids_created TEXT NULL,
            unit_links TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME NULL,
            undone_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_import_batches_officer (officer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    // Added later: how many rows of this clerk the file had, and the ones not
    // imported (with the reason) so nothing is silently left behind.
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM import_batches') as $c) $cols[$c['Field']] = true;
    if (!isset($cols['file_rows'])) $pdo->exec('ALTER TABLE import_batches ADD COLUMN file_rows INT NULL');
    if (!isset($cols['skipped_rows'])) $pdo->exec('ALTER TABLE import_batches ADD COLUMN skipped_rows LONGTEXT NULL');
    if (!isset($cols['already_rows'])) $pdo->exec('ALTER TABLE import_batches ADD COLUMN already_rows INT NULL');
    $done = true;
}

/**
 * Month history from an older collection workbook (e.g. the October sheet when
 * the accounts were imported as of November). Reporting only: it never
 * touches balances or the installment schedule. One row per Excel row and
 * month — including accounts no longer in the system (fully paid/cancelled,
 * contract_id NULL) so the month's totals equal the workbook.
 */
function ihc_ensure_month_snapshots(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS legacy_month_snapshots (
            id INT NOT NULL AUTO_INCREMENT,
            officer_id VARCHAR(100) NOT NULL,
            month CHAR(7) NOT NULL,
            contract_id INT NULL,
            legacy_code VARCHAR(50) NULL,
            client_name VARCHAR(255) NOT NULL,
            property VARCHAR(500) NULL,
            account_status VARCHAR(100) NULL,
            ar_balance DECIMAL(20,6) NOT NULL DEFAULT 0,
            actual_due DECIMAL(20,6) NOT NULL DEFAULT 0,
            arrears DECIMAL(20,6) NOT NULL DEFAULT 0,
            total_due DECIMAL(20,6) NULL,
            collected DECIMAL(20,6) NOT NULL DEFAULT 0,
            due_date DATE NULL,
            pay_date DATE NULL,
            source_file VARCHAR(255) NULL,
            row_no INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lms_month (month, officer_id),
            KEY idx_lms_contract (contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $done = true;
}

/** Contract ids whose import batch is still awaiting the clerk's
 *  confirmation — automated reminders must skip them. Never throws. */
function ihc_unverified_import_contract_ids(PDO $pdo): array {
    try {
        $ids = [];
        $sql = "SELECT c.id FROM contracts c
                JOIN import_batches b ON b.id = c.import_batch_id
                WHERE b.status = 'FOR_VERIFICATION'";
        foreach ($pdo->query($sql) as $r) $ids[(int)$r['id']] = true;
        return $ids;
    } catch (Throwable $e) {
        return []; // tables/columns not created yet → nothing is pending
    }
}
