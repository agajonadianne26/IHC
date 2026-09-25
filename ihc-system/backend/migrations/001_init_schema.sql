-- IHC CMS - Full Schema Migration v1
-- Creates the ihc_cms database and all application tables.

CREATE DATABASE IF NOT EXISTS ihc_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ihc_cms;

-- Officers (admin & billing clerks)
CREATE TABLE IF NOT EXISTS officers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'clerk',
  email VARCHAR(255) NOT NULL
);

-- Clients / buyers
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  officer_id INT NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(50)
);

-- Contracts
CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  officer_id INT NOT NULL,
  total_contract_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  downpayment_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  remaining_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  installment_terms INT NOT NULL DEFAULT 1,
  start_date DATE NULL
);

-- Installment schedules / amortization
CREATE TABLE IF NOT EXISTS installment_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  installment_number INT NOT NULL,
  due_date DATE NOT NULL,
  amount_due DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending Payment',
  payment_method VARCHAR(100) DEFAULT NULL,
  payment_date DATE DEFAULT NULL,
  or_number VARCHAR(100) DEFAULT NULL,
  cashier_notes TEXT DEFAULT NULL
);

-- Notification logs (email / SMS dispatch history)
CREATE TABLE IF NOT EXISTS notifications_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id VARCHAR(50),
  client_email VARCHAR(255),
  channel VARCHAR(10) DEFAULT 'email',
  subject VARCHAR(255),
  status VARCHAR(10) DEFAULT 'pending',
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  error_message TEXT DEFAULT NULL
);

CREATE INDEX idx_notification_logs_installment_day
  ON notifications_logs (contract_id, channel, status, sent_at);
-- NOTE: this table is created as `notifications_logs` (plural) — every PHP API
-- and the Node service query the plural name. Older installs had a singular
-- `notification_logs`; rename it (RENAME TABLE notification_logs TO
-- notifications_logs) rather than re-creating.

-- Seed officers (ID 1 = admin, 2-4 = billing clerks, matching mock login)
INSERT INTO officers (id, name, role, email) VALUES
  (1, 'Jeremy Cantalejo', 'admin', 'admin@ihc.com'),
  (2, 'Ana Reyes',       'clerk', 'ana@ihc.com'),
  (3, 'Mark Cruz',        'clerk', 'mark@ihc.com'),
  (4, 'Jessica Lim',      'clerk', 'jessica@ihc.com')
ON DUPLICATE KEY UPDATE name = VALUES(name);