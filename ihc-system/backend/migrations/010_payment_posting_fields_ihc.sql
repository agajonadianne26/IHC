-- 010 — Payment posting reconciliation fields
-- Extends immutable payment rows with the operational references shown in the
-- Clerk Post Collection form and whether the client receipt email was requested.

ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS external_reference VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS receipt_requested TINYINT(1) NOT NULL DEFAULT 1;
