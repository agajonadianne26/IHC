-- Notification logs (email / SMS dispatch history).
-- Table name MUST stay `notifications_logs` (plural): every PHP API and the
-- Node service query the plural name. Older installs kept a singular
-- `notification_logs` table that silently broke login/dashboard endpoints —
-- migrate by renaming it (RENAME TABLE notification_logs TO notifications_logs).
CREATE TABLE IF NOT EXISTS notifications_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id VARCHAR(50) NOT NULL,
  client_email VARCHAR(255) NOT NULL,
  channel VARCHAR(50) NOT NULL DEFAULT 'email',
  reminder_type VARCHAR(30) DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('sent','failed','pending','') NOT NULL DEFAULT 'pending',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  error_message TEXT DEFAULT NULL,
  INDEX idx_notifications_reminder_lookup (contract_id, channel, reminder_type, status, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
