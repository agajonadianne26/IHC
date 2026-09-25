-- Unified staff authentication for the Admin + Clerk dashboards.
-- The login page previously verified admin/clerk credentials against a
-- hardcoded JS array; now php/api_auth.php verifies them here (bcrypt),
-- alongside the client accounts in client_accounts (migration 003).
-- Session mechanism is unchanged: all three dashboards keep sharing the
-- IHC_USER localStorage key.
--
-- officer_id mapping matches the demo credentials shown on log in.html:
--   admin@ihc.com   -> officers.id 1 (role admin, no contracts reference '1')
--   ana@ihc.com     -> officers.id 2 (owns every live contract: officer_id='2')
--   mark@ihc.com    -> officers.id 3
--   jessica@ihc.com -> officers.id 4

ALTER TABLE officers
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_officers_email ON officers (email);

-- Seed every staff account with an upsert. Using INSERT for all four IDs
-- (rather than UPDATE for IDs 1 and 2) also works when an older or partial
-- database has an empty officers table.
INSERT INTO officers (id, full_name, role, email, password_hash) VALUES
  (1, 'Jeremy Cantalejo', 'admin',  'admin@ihc.com',   '$2y$10$BL9dLew5Rpc5hX/HKk3l7O4/6iqVbAlm3J7ubMOYR4JwgjwIOfSBi'),
  (2, 'Ana Reyes',        'clerk', 'ana@ihc.com',     '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'),
  (3, 'Mark Cruz',        'clerk', 'mark@ihc.com',    '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'),
  (4, 'Jessica Lim',      'clerk', 'jessica@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name), role = VALUES(role),
  email = VALUES(email), password_hash = VALUES(password_hash);
