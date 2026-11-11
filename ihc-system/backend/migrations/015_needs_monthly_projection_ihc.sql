-- 015: "Needs monthly" imports + Excel projection (php/api_import.php)
--
-- * contracts.needs_monthly — 1 for an account imported from the collection
--   Excel without a usable monthly amount (none in the Excel, or balance ÷
--   monthly over 600 months). ihc_schedule() returns no installments for it
--   (no dues, aging or reminders, payment posting refused) until the clerk
--   sets the monthly on the Import page (update_account clears the flag).
-- * contracts.legacy_projection — the Excel's monthly projection columns
--   (JAN..DEC = cutoff year, "JAN (2)".."DEC (2)" = next year) as JSON
--   {"YYYY-MM": amount}. Summed by api_admin.php?action=dues into the
--   "Expected (Excel)" card, which matches the workbook's top total row.
--
-- php/legacy-import.php self-heals both columns (and backfills the projection
-- from legacy_data once); this file is for fresh databases / documentation.

ALTER TABLE contracts ADD COLUMN IF NOT EXISTS needs_monthly TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS legacy_projection LONGTEXT NULL;
