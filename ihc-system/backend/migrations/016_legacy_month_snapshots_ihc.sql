-- 016: Month history from older collection workbooks (reporting only)
--
-- Filled by php/api_import.php action=month_snapshot (one call per clerk):
-- every row of the workbook's "AR Balance as of <Month> <Year>" block —
-- actual due, arrears, total due, amount collected, payment date — including
-- accounts that are no longer in the system (contract_id NULL), so the
-- Dues & Performance figures for that month equal the Excel. Balances and
-- installment schedules are never changed by it.
--
-- php/legacy-import.php (ihc_ensure_month_snapshots) self-heals this table.

CREATE TABLE IF NOT EXISTS legacy_month_snapshots (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
