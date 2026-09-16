-- Align automated reminders with the live IHC application schema.
-- Existing acknowledgement and manual-send log records remain intact.

ALTER TABLE notifications_logs
  ADD COLUMN IF NOT EXISTS reminder_type VARCHAR(30) NULL AFTER channel,
  ADD COLUMN IF NOT EXISTS due_date DATE NULL AFTER reminder_type;

CREATE INDEX IF NOT EXISTS idx_notifications_reminder_lookup
  ON notifications_logs (contract_id, channel, reminder_type, status, sent_at);
