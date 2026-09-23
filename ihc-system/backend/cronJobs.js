const cron = require('node-cron');
const db = require('./db');
const { sendPaymentReminder } = require('./emailService');
const { sendPaymentReminderSms } = require('./smsService');
require('dotenv').config();

function dateInTimeZone(date, timeZone) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(date);
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  return `${values.year}-${values.month}-${values.day}`;
}

function daysUntilDue(dueDate, todayStr) {
  const dueDateStr = new Date(dueDate).toISOString().slice(0, 10);
  return Math.floor(
    (Date.parse(`${dueDateStr}T00:00:00Z`) - Date.parse(`${todayStr}T00:00:00Z`)) / 86400000
  );
}

// Dedup lives in notifications_logs and is evaluated per channel: a failed
// (or unconfigured) email must never suppress the SMS, and vice versa —
// each helper below binds nl.channel to one channel value. contract_id is
// folded onto integer contract ids inside the SQL text; never JOIN
// notifications_logs against contracts (mixed collations blow up).
async function fetchUpcomingRows(todayStr, channel) {
  return db.query(
    `SELECT
      c.id AS contract_id,
      c.start_date AS due_date,
      c.downpayment AS amount_due,
      CONCAT('IHC-', c.id) AS contract_code,
      c.client_name,
      c.email AS client_email,
      c.cellphone_number
     FROM contracts c
     WHERE DATE(c.start_date) BETWEEN ? AND DATE_ADD(?, INTERVAL 3 DAY)
      AND NOT EXISTS (
        SELECT 1
        FROM notifications_logs nl
        WHERE nl.contract_id IN (CAST(c.id AS CHAR) COLLATE utf8mb4_general_ci, CONCAT('IHC-', c.id) COLLATE utf8mb4_general_ci, CONCAT('CON-', c.id) COLLATE utf8mb4_general_ci)
         AND nl.channel = ?
         AND nl.reminder_type = 'due_soon'
         AND nl.status = 'sent'
      )`,
    [todayStr, todayStr, channel]
  );
}

async function fetchOverdueRows(todayStr, channel) {
  return db.query(
    `SELECT
      c.id AS contract_id,
      c.start_date AS due_date,
      c.downpayment AS amount_due,
      CONCAT('IHC-', c.id) AS contract_code,
      c.client_name,
      c.email AS client_email,
      c.cellphone_number
     FROM contracts c
     WHERE DATE(c.start_date) < ?
      AND NOT EXISTS (
        SELECT 1
        FROM notifications_logs nl
        WHERE nl.contract_id IN (CAST(c.id AS CHAR) COLLATE utf8mb4_general_ci, CONCAT('IHC-', c.id) COLLATE utf8mb4_general_ci, CONCAT('CON-', c.id) COLLATE utf8mb4_general_ci)
         AND nl.channel = ?
         AND nl.reminder_type = 'overdue'
         AND nl.status = 'sent'
         AND DATE(nl.sent_at) = ?
      )`,
    [todayStr, channel, todayStr]
  );
}

// Safe to call from the server scheduler or from run-reminders.js.
async function runDailyReminders() {
  console.log('[CRON] Running daily payment reminder check...');

  try {
    const today = new Date();
    const todayStr = dateInTimeZone(today, process.env.REMINDER_TIMEZONE || 'Asia/Manila');

    // db.query returns rows directly, rather than mysql2's [rows, fields] tuple.

    // Due within the next three days: one email AND one SMS per contract,
    // each tracked separately in notifications_logs (channel='email'/'sms').
    const upcomingEmailRows = await fetchUpcomingRows(todayStr, 'email');
    for (const row of upcomingEmailRows) {
      if (row.client_email) {
        const result = await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code,
          row.amount_due,
          row.due_date,
          daysUntilDue(row.due_date, todayStr),
          { reminderType: 'due_soon' }
        );
        if (result.success) {
          console.log(`[CRON] Reminder sent to ${row.client_email} for ${row.contract_code}`);
        }
      }
    }

    const upcomingSmsRows = await fetchUpcomingRows(todayStr, 'sms');
    for (const row of upcomingSmsRows) {
      if (row.cellphone_number) {
        const result = await sendPaymentReminderSms(
          row.cellphone_number,
          row.client_name,
          row.contract_code,
          row.amount_due,
          row.due_date,
          daysUntilDue(row.due_date, todayStr),
          { reminderType: 'due_soon' }
        );
        if (result.success) {
          console.log(`[CRON] SMS reminder sent to ${row.cellphone_number} for ${row.contract_code}`);
        }
      }
    }

    // Overdue notices stay email-only for now. To add SMS here, fetch with
    // channel 'sms' and call sendPaymentReminderSms exactly like above.
    const overdueRows = await fetchOverdueRows(todayStr, 'email');

    for (const row of overdueRows) {
      const daysUntil = daysUntilDue(row.due_date, todayStr);

      if (row.client_email) {
        const result = await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code,
          row.amount_due,
          row.due_date,
          daysUntil,
          { reminderType: 'overdue' }
        );
        if (result.success) {
          console.log(`[CRON] Overdue reminder sent to ${row.client_email} for ${row.contract_code}`);
        }
      }
    }

    console.log(`[CRON] Daily reminder check complete. Upcoming: ${upcomingEmailRows.length} email / ${upcomingSmsRows.length} SMS, Overdue: ${overdueRows.length}`);
  } catch (error) {
    console.error('[CRON] Error during daily reminder check:', error);
    throw error;
  }
}

// Long-running service option. For a Windows scheduled task, run the job
// once without creating a persistent cron timer.
if (process.env.RUN_REMINDERS_ONCE !== 'true') {
  cron.schedule('0 8 * * *', () => runDailyReminders().catch(() => {}), {
    noOverlap: true,
    timezone: process.env.REMINDER_TIMEZONE || 'Asia/Manila'
  });
  console.log('[CRON] Payment reminder scheduler initialized (runs daily at 8:00 AM)');
}

module.exports = { runDailyReminders };
