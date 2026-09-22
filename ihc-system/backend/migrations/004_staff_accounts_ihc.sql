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

-- id 1: repurposed as the admin account (contracts only ever used officer_id '2')
UPDATE officers SET full_name = 'Jeremy Cantalejo', role = 'admin',
  email = 'admin@ihc.com',
  password_hash = '$2y$10$BL9dLew5Rpc5hX/HKk3l7O4/6iqVbAlm3J7ubMOYR4JwgjwIOfSBi'
WHERE id = 1;

-- id 2: renamed to match the login page's Ana Reyes (officerId 2) and to fix
-- the client-ledger officer name for every live contract.
UPDATE officers SET full_name = 'Ana Reyes', role = 'clerk',
  email = 'ana@ihc.com',
  password_hash = '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'
WHERE id = 2;

INSERT INTO officers (id, full_name, role, email, password_hash) VALUES
  (3, 'Mark Cruz',    'clerk', 'mark@ihc.com',    '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'),
  (4, 'Jessica Lim',  'clerk', 'jessica@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name), role = VALUES(role),
  email = VALUES(email), password_hash = VALUES(password_hash);
