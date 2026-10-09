-- 012 — Prospect clients: buyers registered through a Holding Fee or
-- Reservation BEFORE a contract exists (New Contract → Select Client).
--
-- The live code self-heals all of this on every request (php/prospect-clients.php
-- via php/api_holding_reservation.php and backend/db.php), so this file is for
-- provisioning outside the app. Safe to run repeatedly.
--
-- Run: mysql -u root ihc < 012_prospect_clients_ihc.sql

USE ihc;

CREATE TABLE IF NOT EXISTS prospect_clients (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fee tables link to the client row and tolerate a NULL contract so the fee
-- can be recorded before the contract exists. (holding_fees.contract_id is
-- already nullable on legacy databases; reservation_fees is NOT NULL until now.)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'holding_fees' AND COLUMN_NAME = 'prospect_client_id');
SET @sql := IF(@col = 0, 'ALTER TABLE holding_fees ADD COLUMN prospect_client_id INT NULL AFTER contract_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'holding_fees' AND COLUMN_NAME = 'client_name');
SET @sql := IF(@col = 0, "ALTER TABLE holding_fees ADD COLUMN client_name VARCHAR(255) NOT NULL DEFAULT '' AFTER officer_id", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'holding_fees' AND COLUMN_NAME = 'property_address');
SET @sql := IF(@col = 0, "ALTER TABLE holding_fees ADD COLUMN property_address VARCHAR(500) NOT NULL DEFAULT '' AFTER client_name", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation_fees' AND COLUMN_NAME = 'prospect_client_id');
SET @sql := IF(@col = 0, 'ALTER TABLE reservation_fees ADD COLUMN prospect_client_id INT NULL AFTER contract_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @null_ok := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation_fees' AND COLUMN_NAME = 'contract_id');
SET @sql := IF(@null_ok = 'NO', 'ALTER TABLE reservation_fees MODIFY COLUMN contract_id INT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Indexes (ignore duplicate-key errors on re-run by checking first)
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'holding_fees' AND INDEX_NAME = 'idx_hf_prospect');
SET @sql := IF(@idx = 0, 'ALTER TABLE holding_fees ADD KEY idx_hf_prospect (prospect_client_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation_fees' AND INDEX_NAME = 'idx_rf_prospect');
SET @sql := IF(@idx = 0, 'ALTER TABLE reservation_fees ADD KEY idx_rf_prospect (prospect_client_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
