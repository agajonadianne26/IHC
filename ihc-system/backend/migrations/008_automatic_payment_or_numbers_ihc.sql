-- Automatic, separate Official Receipt series for contract payments.
-- Downpayment:  OR-DP-YYYY-#####
-- Installment:  OR-INS-YYYY-#####
--
-- php/post-payment.php allocates a value from this table while holding the
-- matching counter row lock. The counter update and payment INSERT run in the
-- same transaction, so concurrent clerks cannot receive the same number.

CREATE TABLE IF NOT EXISTS payment_or_counters (
  series_code VARCHAR(3) NOT NULL,
  series_year SMALLINT UNSIGNED NOT NULL,
  last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (series_code, series_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pre-create the current-year series. Both inserts are safe to repeat and also
-- add a new pair when this migration is first applied in a later year.
INSERT INTO payment_or_counters (series_code, series_year, last_number)
VALUES
  ('DP', YEAR(CURDATE()), 0),
  ('INS', YEAR(CURDATE()), 0)
ON DUPLICATE KEY UPDATE series_code = VALUES(series_code);
