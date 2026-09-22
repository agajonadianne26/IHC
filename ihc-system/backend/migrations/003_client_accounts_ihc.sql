-- Client portal login accounts.
-- Created/updated automatically by backend/db.php when a clerk submits the
-- New Contract form (email + Client Portal Password). Login verification
-- happens in php/api_client.php (?action=lookup) via password_verify().
-- db.php also self-creates this table with CREATE TABLE IF NOT EXISTS, so
-- this migration is for readability and for provisioning outside the app.

CREATE TABLE IF NOT EXISTS client_accounts (
  id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  cellphone_number VARCHAR(50) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_client_accounts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
