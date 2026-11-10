-- 014: Legacy collection-account import (php/api_import.php)
--
-- * officers.officer_code  — the clerk's code as written in the collection
--   Excel's "Collection Officer" column (e.g. AJ). Set by the admin in the
--   Staff modal; the import only accepts rows whose code matches the
--   uploading clerk.
-- * contracts.* legacy columns — data the Excel carries that the New Contract
--   form never collected, plus the opening-balance snapshot the schedule is
--   built from (total_contract_price = opening_balance, downpayment = 0,
--   monthly_amortization drives the per-row amount in ihc_build_installments).
--   legacy_data keeps the complete original row as JSON so nothing is lost.
--
-- php/api_import.php self-heals the same columns on first use; this file is
-- for fresh databases / documentation.

ALTER TABLE officers
  ADD COLUMN IF NOT EXISTS officer_code VARCHAR(20) NULL,
  ADD UNIQUE KEY IF NOT EXISTS uq_officers_code (officer_code);

ALTER TABLE contracts
  ADD COLUMN IF NOT EXISTS is_legacy TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS needs_completion TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS legacy_code VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS buyer_code VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS company VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS branch VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS financing_type VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS receivable_status VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS collection_type VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS sales_officer VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS rs_date DATE NULL,
  ADD COLUMN IF NOT EXISTS pdc VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS account_status VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS account_remarks VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS monthly_amortization DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS opening_balance DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS opening_balance_as_of DATE NULL,
  ADD COLUMN IF NOT EXISTS legacy_data LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS imported_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS imported_by VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS import_batch_id INT NULL,
  ADD KEY IF NOT EXISTS idx_contracts_legacy_code (legacy_code);

-- One row per Import commit. FOR_VERIFICATION → editable / undoable, no
-- automated reminders; CONFIRMED → final; UNDONE → everything it created was
-- removed. unit_ids_created / unit_links (JSON) drive an exact undo.
CREATE TABLE IF NOT EXISTS import_batches (
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
  file_rows INT NULL,          -- rows of this clerk in the uploaded file
  skipped_rows LONGTEXT NULL,  -- JSON: this clerk's rows NOT imported + reason
  already_rows INT NULL,       -- this clerk's rows skipped because they were imported earlier
  PRIMARY KEY (id),
  KEY idx_import_batches_officer (officer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
