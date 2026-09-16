CREATE TABLE IF NOT EXISTS notification_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id VARCHAR(50),
  installment_id INT DEFAULT NULL,
  client_email VARCHAR(255),
  channel VARCHAR(10) DEFAULT 'email',
  subject VARCHAR(255),
  status VARCHAR(10) DEFAULT 'pending',
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  error_message TEXT DEFAULT NULL
);
