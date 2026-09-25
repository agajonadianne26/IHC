-- 009 — Database-driven Statement of Account
-- Adds contract/property fields, configurable SOA settings, additional
-- charges, explicit additional-equity records, and payment-to-installment
-- allocations used by both Clerk and Admin SOA generation.

CREATE TABLE IF NOT EXISTS soa_settings (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO soa_settings (
  id, company_name, company_address, company_contact, logo_path,
  penalty_rate_percent, important_notes, noted_by_name,
  noted_by_position, noted_by_contact, validity_days
) VALUES (
  1,
  'Imperial Homes',
  'Imperial Homes Corporation',
  'Contact the IHC Billing Office for assistance.',
  'img/ihc logo.png',
  0,
  'Please settle this Statement of Account on or before the due date. All amounts are computed from the current IHC ledger. Penalties and interest, when applicable, follow the configured company rate and the payment status shown in this document.',
  'Authorized IHC Representative',
  'Billing Manager',
  'IHC Billing Office',
  7
)
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

ALTER TABLE contracts
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS loanable_amount DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS approved_loan_amount DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS early_move_in_amount DECIMAL(15,2) NULL,
  ADD COLUMN IF NOT EXISTS project_name VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS project_phase VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS block_no VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS lot_no VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS model_type VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS lot_area VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS floor_area VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS client_address VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS equity_monthly_rate DECIMAL(7,4) NULL,
  ADD COLUMN IF NOT EXISTS equity_penalty_rate DECIMAL(7,4) NULL,
  ADD COLUMN IF NOT EXISTS loan_term_years DECIMAL(8,2) NULL;

ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS check_number VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS installment_kind VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS installment_no INT NULL;

CREATE TABLE IF NOT EXISTS payment_allocations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT NOT NULL,
  contract_id INT NOT NULL,
  installment_kind VARCHAR(20) NOT NULL,
  installment_no INT NULL,
  allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  principal_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_allocation (payment_id, installment_kind, installment_no),
  KEY idx_payment_alloc_contract (contract_id),
  KEY idx_payment_alloc_schedule (contract_id, installment_kind, installment_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS additional_equity_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id INT NOT NULL,
  payment_id INT NULL,
  installment_no INT NULL,
  due_date DATE NULL,
  amount_due DECIMAL(15,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
  payment_date DATE NULL,
  or_number VARCHAR(100) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'UNPAID',
  remarks TEXT NULL,
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_additional_equity_contract (contract_id, status, due_date),
  KEY idx_additional_equity_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS additional_charges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id INT NOT NULL,
  charge_type VARCHAR(100) NOT NULL,
  description VARCHAR(500) NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
  charge_date DATE NOT NULL,
  due_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  or_number VARCHAR(100) NULL,
  remarks TEXT NULL,
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_additional_charge_contract (contract_id, status, due_date),
  KEY idx_additional_charge_type (charge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
