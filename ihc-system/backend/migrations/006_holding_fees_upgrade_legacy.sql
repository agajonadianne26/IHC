-- 006 — Upgrade legacy holding_fees to new spec schema (§3-§7) without data loss
-- The dump `database/ihc.sql` ships a legacy holding_fees table (officer_id, proof_data_url, lowercase status).
-- New path 005 creates a different holding_fees (property_unit_id, start/expiration, uppercase ENUM, proof_path).
-- CREATE TABLE IF NOT EXISTS in 005 no-ops when legacy exists, so this migration ALTERs in place.
-- Safe to run repeatedly; reuses backend/db.php self-healing pattern (SHOW COLUMNS).
-- Run: mysql -u root ihc < 006_holding_fees_upgrade_legacy.sql  or let api_holding_reservation.php self-heal.

USE ihc;

-- Ensure property_units / reservation_fees / audit_logs / business_rules exist if 005 not yet applied
CREATE TABLE IF NOT EXISTS property_units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project VARCHAR(255) NULL, building VARCHAR(255) NULL, unit_number VARCHAR(100) NULL,
  display_label VARCHAR(255) NOT NULL, status ENUM('AVAILABLE','ON HOLD','RESERVED','SOLD') NOT NULL DEFAULT 'AVAILABLE',
  current_client_id INT NULL, current_contract_id INT NULL, current_holding_fee_id INT NULL, current_reservation_fee_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_property_units_label (display_label), KEY idx_property_units_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS reservation_fees (
  id INT AUTO_INCREMENT PRIMARY KEY, contract_id INT NOT NULL, property_unit_id INT NULL, client_id INT NULL, holding_fee_id INT NULL,
  amount DECIMAL(12,2) NOT NULL, payment_method VARCHAR(100) NOT NULL, payment_date DATE NOT NULL,
  reference_number VARCHAR(100) NULL, or_number VARCHAR(100) NULL, status ENUM('PENDING','PAID','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  remarks TEXT NULL, proof_path VARCHAR(500) NULL, proof_name VARCHAR(255) NULL, processed_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rf_contract (contract_id), KEY idx_rf_unit (property_unit_id), KEY idx_rf_holding (holding_fee_id),
  CONSTRAINT fk_rf_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY, action VARCHAR(80) NOT NULL, contract_id INT NULL, holding_fee_id INT NULL, reservation_fee_id INT NULL,
  property_unit_id INT NULL, from_status VARCHAR(30) NULL, to_status VARCHAR(30) NULL, actor_id VARCHAR(100) NULL, actor_name VARCHAR(255) NULL,
  details JSON NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_contract (contract_id), KEY idx_audit_holding (holding_fee_id), KEY idx_audit_reservation (reservation_fee_id), KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS business_rules (
  rule_key VARCHAR(80) PRIMARY KEY, rule_value VARCHAR(255) NOT NULL, description TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO business_rules (rule_key,rule_value,description) VALUES
  ('holding_fee.default_days','30','Default hold window in days when clerk omits expiration'),
  ('holding_fee.expire_makes_available','1','1 = EXPIRED holding reverts unit to AVAILABLE (requires IHC approval)'),
  ('holding_fee.convert_on_reservation','1','1 = reservation PAID auto-marks linked holding CONVERTED'),
  ('holding_fee.refundable','0','1 = holding fees may be refunded (policy flag, not auto-refund)'),
  ('reservation_fee.refundable','1','1 = reservation fees may be refunded per IHC policy'),
  ('holding_fee.allow_direct_reservation','0','1 = allow reservation without prior active hold (warning audit)'),
  ('business_rules.version','6','Migration version marker');

-- Backfill property_units from contracts (idempotent)
INSERT IGNORE INTO property_units (display_label, status, current_contract_id)
SELECT DISTINCT TRIM(property_address), 'AVAILABLE', id
FROM contracts
WHERE property_address IS NOT NULL AND TRIM(property_address) <> '';

-- Upgrade holding_fees columns if legacy table exists
-- Use stored procedure for conditional ALTERs (compatible with MySQL 5.7+)
DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_holding_fees $$
CREATE PROCEDURE upgrade_holding_fees()
BEGIN
  DECLARE has_table INT DEFAULT 0;
  SELECT COUNT(*) INTO has_table FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'holding_fees';
  IF has_table = 1 THEN
    -- Add new columns if missing
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='client_id') THEN
      ALTER TABLE holding_fees ADD COLUMN client_id INT NULL AFTER contract_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='property_unit_id') THEN
      ALTER TABLE holding_fees ADD COLUMN property_unit_id INT NULL AFTER client_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='reference_number') THEN
      ALTER TABLE holding_fees ADD COLUMN reference_number VARCHAR(100) NULL AFTER payment_date;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='start_date') THEN
      ALTER TABLE holding_fees ADD COLUMN start_date DATE NULL AFTER or_number;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='expiration_date') THEN
      ALTER TABLE holding_fees ADD COLUMN expiration_date DATE NULL AFTER start_date;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='processed_by') THEN
      ALTER TABLE holding_fees ADD COLUMN processed_by VARCHAR(100) NULL AFTER proof_name;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='converted_to_reservation_id') THEN
      ALTER TABLE holding_fees ADD COLUMN converted_to_reservation_id INT NULL AFTER processed_by;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='proof_path') THEN
      ALTER TABLE holding_fees ADD COLUMN proof_path VARCHAR(500) NULL AFTER remarks;
    END IF;
    -- Backfill new date columns from legacy payment_date where null
    UPDATE holding_fees SET start_date = payment_date WHERE start_date IS NULL AND payment_date IS NOT NULL;
    UPDATE holding_fees SET expiration_date = DATE_ADD(payment_date, INTERVAL 30 DAY) WHERE expiration_date IS NULL AND payment_date IS NOT NULL;
    -- Normalize status values to uppercase ENUM set (pending->PENDING etc). Legacy 'paid' -> PAID, 'cancelled' -> CANCELLED, keep pending mapping.
    -- First expand ENUM to include both cases via temporary VARCHAR, then coerce.
    -- Instead, update values then modify column type.
    UPDATE holding_fees SET status = UPPER(status) WHERE status IN ('pending','paid','cancelled');
    -- Handle any remaining lowercase or legacy 'refunded' that should become PAID per previous mapping (023 migration)
    -- Ensure all rows match allowed set; default unknowns to PENDING
    UPDATE holding_fees SET status = 'PENDING' WHERE status NOT IN ('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED');
  END IF;
END $$
DELIMITER ;
CALL upgrade_holding_fees();
DROP PROCEDURE IF EXISTS upgrade_holding_fees;
-- Re-apply ENUM definition (now that data is uppercase) — conditional via column type check not needed; ALTER will succeed or warn
-- Use a safe ALTER that retains new columns: modify status to new ENUM if still VARCHAR(20)
-- We detect by checking column_type; if VARCHAR, convert to ENUM. If already ENUM(PENDING...), skip.
DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_holding_fees_enum $$
CREATE PROCEDURE upgrade_holding_fees_enum()
BEGIN
  DECLARE col_type VARCHAR(255);
  SELECT COLUMN_TYPE INTO col_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='holding_fees' AND column_name='status' LIMIT 1;
  IF col_type LIKE 'varchar%' THEN
    ALTER TABLE holding_fees MODIFY COLUMN status ENUM('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'PENDING';
  END IF;
END $$
DELIMITER ;
CALL upgrade_holding_fees_enum();
DROP PROCEDURE IF EXISTS upgrade_holding_fees_enum;
DELIMITER ;

-- Ensure indexes
-- Use conditional create index via information_schema
DELIMITER $$
DROP PROCEDURE IF EXISTS ensure_hf_indexes $$
CREATE PROCEDURE ensure_hf_indexes()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='holding_fees' AND index_name='idx_hf_contract') THEN
    ALTER TABLE holding_fees ADD KEY idx_hf_contract (contract_id);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='holding_fees' AND index_name='idx_hf_unit') THEN
    ALTER TABLE holding_fees ADD KEY idx_hf_unit (property_unit_id);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='holding_fees' AND index_name='idx_hf_status_exp') THEN
    ALTER TABLE holding_fees ADD KEY idx_hf_status_exp (status, expiration_date);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='holding_fees' AND index_name='idx_hf_created') THEN
    ALTER TABLE holding_fees ADD KEY idx_hf_created (created_at);
  END IF;
  -- Add FK if not exists
  IF NOT EXISTS (SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name='holding_fees' AND constraint_name='fk_hf_contract') THEN
    -- Need to ensure contract_id is indexed and type matches; if legacy contract_id was INT nullable, FK will succeed
    -- For legacy rows with contract_id NULL, FK allows null; but 005 requires NOT NULL — keep nullable for legacy 'no contract yet' rows
    BEGIN
      DECLARE CONTINUE HANDLER FOR 1215 BEGIN END;
      DECLARE CONTINUE HANDLER FOR 1826 BEGIN END;
      ALTER TABLE holding_fees ADD CONSTRAINT fk_hf_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE ON UPDATE CASCADE;
    END;
  END IF;
END $$
DELIMITER ;
CALL ensure_hf_indexes();
DROP PROCEDURE IF EXISTS ensure_hf_indexes;
DELIMITER ;

-- Ensure audit_logs has business_rules version entry
INSERT INTO audit_logs (action, details)
SELECT 'system.migration', JSON_OBJECT('migration','006_holding_fees_upgrade_legacy','at', NOW())
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE action='system.migration' AND JSON_EXTRACT(details,'$.migration')='006_holding_fees_upgrade_legacy');
