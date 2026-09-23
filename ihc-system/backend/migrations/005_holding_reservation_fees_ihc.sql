-- 005 — Holding Fee & Reservation Fee management (IHC Payment & Schedule Management System)
-- Distinct transactions, property status state machine, configurable business rules, audit trail.
-- Safe to run repeatedly (IF NOT EXISTS / conditional inserts). Targets the live `ihc` database.
-- Run via: mysql -u root ihc < 005_holding_reservation_fees_ihc.sql  or phpMyAdmin.
-- Existing rows in contracts/payments/officers remain untouched.

-- Ensure we are on the live DB (sql dump uses `ihc`; 001 used `ihc_cms`).
CREATE DATABASE IF NOT EXISTS ihc CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ihc;

-- ------------------------------------------------------------
-- 1) Property / Unit inventory  (spec §2, §13, §16)
-- contracts.property_address stays as free-text for back-compat;
-- each distinct address lazily maps to one row here. New sales
-- should create a unit up-front.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS property_units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project VARCHAR(255) NULL,
  building VARCHAR(255) NULL,
  unit_number VARCHAR(100) NULL,
  display_label VARCHAR(255) NOT NULL,
  status ENUM('AVAILABLE','ON HOLD','RESERVED','SOLD') NOT NULL DEFAULT 'AVAILABLE',
  current_client_id INT NULL,
  current_contract_id INT NULL,
  current_holding_fee_id INT NULL,
  current_reservation_fee_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_property_units_label (display_label),
  KEY idx_property_units_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill one unit per distinct contracts.property_address (idempotent)
INSERT IGNORE INTO property_units (display_label, status, current_contract_id)
SELECT DISTINCT TRIM(property_address), 'AVAILABLE', id
FROM contracts
WHERE property_address IS NOT NULL AND TRIM(property_address) <> '';

-- ------------------------------------------------------------
-- 2) Holding Fees  (spec §3-§5)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS holding_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  client_id INT NULL,
  property_unit_id INT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(100) NOT NULL,
  payment_date DATE NOT NULL,
  reference_number VARCHAR(100) NULL,
  or_number VARCHAR(100) NULL,
  start_date DATE NOT NULL,
  expiration_date DATE NOT NULL,
  status ENUM('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  remarks TEXT NULL,
  proof_path VARCHAR(500) NULL,
  proof_name VARCHAR(255) NULL,
  processed_by VARCHAR(100) NULL,
  converted_to_reservation_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_hf_contract (contract_id),
  KEY idx_hf_unit (property_unit_id),
  KEY idx_hf_status_exp (status, expiration_date),
  KEY idx_hf_created (created_at),
  CONSTRAINT fk_hf_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- holding_fees -> property_units is intentionally NOT a FK (units may be backfilled lazily);
-- app enforces referential integrity so a missing unit row never blocks a fee insert.

-- ------------------------------------------------------------
-- 3) Reservation Fees  (spec §6-§7)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservation_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  property_unit_id INT NULL,
  client_id INT NULL,
  holding_fee_id INT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(100) NOT NULL,
  payment_date DATE NOT NULL,
  reference_number VARCHAR(100) NULL,
  or_number VARCHAR(100) NULL,
  status ENUM('PENDING','PAID','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  remarks TEXT NULL,
  proof_path VARCHAR(500) NULL,
  proof_name VARCHAR(255) NULL,
  processed_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rf_contract (contract_id),
  KEY idx_rf_unit (property_unit_id),
  KEY idx_rf_holding (holding_fee_id),
  CONSTRAINT fk_rf_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 4) Audit trail  (spec §17)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(80) NOT NULL,
  contract_id INT NULL,
  holding_fee_id INT NULL,
  reservation_fee_id INT NULL,
  property_unit_id INT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NULL,
  actor_id VARCHAR(100) NULL,
  actor_name VARCHAR(255) NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_contract (contract_id),
  KEY idx_audit_holding (holding_fee_id),
  KEY idx_audit_reservation (reservation_fee_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 5) Business rules / configurable policy  (spec §19)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS business_rules (
  rule_key VARCHAR(80) PRIMARY KEY,
  rule_value VARCHAR(255) NOT NULL,
  description TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO business_rules (rule_key, rule_value, description) VALUES
  ('holding_fee.default_days', '30', 'Default hold window in days when clerk omits expiration'),
  ('holding_fee.expire_makes_available', '1', '1 = EXPIRED holding reverts unit to AVAILABLE (requires IHC approval)'),
  ('holding_fee.convert_on_reservation', '1', '1 = reservation PAID auto-marks linked holding CONVERTED'),
  ('holding_fee.refundable', '0', '1 = holding fees may be refunded (policy flag, not auto-refund)'),
  ('reservation_fee.refundable', '1', '1 = reservation fees may be refunded per IHC policy')
ON DUPLICATE KEY UPDATE
  description = VALUES(description);

-- ------------------------------------------------------------
-- 6) Seed audit entry so the log view is never empty after migration
-- ------------------------------------------------------------
INSERT INTO audit_logs (action, details)
SELECT 'system.migration', JSON_OBJECT('migration','005_holding_reservation_fees_ihc','at', NOW())
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_logs WHERE action='system.migration');
