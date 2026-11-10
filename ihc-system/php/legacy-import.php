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
    ];
    foreach ($add as $name => $def) {
        if (!isset($cols[$name])) $pdo->exec('ALTER TABLE contracts ADD COLUMN `' . $name . '` ' . $def);
    }
    if (!isset($cols['legacy_code'])) {
        $pdo->exec('ALTER TABLE contracts ADD KEY idx_contracts_legacy_code (legacy_code)');
    }
    $done = true;
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
