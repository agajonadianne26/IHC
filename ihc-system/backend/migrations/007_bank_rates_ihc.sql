-- Financing Bank default annual interest rates (admin-editable, admin dashboard
-- "Financing Banks" section). The clerk New Contract form builds its bank list
-- and auto-fill default from here; each contract snapshots its own
-- contracts.annual_interest_rate, so editing a default only affects NEW
-- contracts. php/api_bank_rates.php self-heals this table on first use too,
-- so fresh DBs work even if this file has not been run.

CREATE TABLE IF NOT EXISTS bank_rates (
  id INT NOT NULL AUTO_INCREMENT,
  bank_name VARCHAR(120) NOT NULL,
  annual_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  display_label VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bank_rates_name (bank_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO bank_rates (bank_name, annual_rate, display_label, sort_order) VALUES
  ('In-House',      0.00, 'In-House',         0),
  ('Pag-IBIG',      5.50, 'Pag-IBIG Fund',    1),
  ('BDO',           6.50, 'BDO',              2),
  ('BPI',           6.50, 'BPI',              3),
  ('Metrobank',     6.50, 'Metrobank',        4),
  ('Security Bank', 6.75, 'Security Bank',    5),
  ('RCBC',          6.75, 'RCBC',             6),
  ('UnionBank',     7.00, 'UnionBank',        7),
  ('PNB',           7.00, 'PNB',              8);