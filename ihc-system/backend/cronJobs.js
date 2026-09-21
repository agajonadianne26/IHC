const cron = require('node-cron');
const db = require('./db');
const { sendPaymentReminder } = require('./emailService');
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

// Safe to call from the server scheduler or from run-reminders.js.
async function runDailyReminders() {
  console.log('[CRON] Running daily payment reminder check...');

  try {
    const today = new Date();
    const todayStr = dateInTimeZone(today, process.env.REMINDER_TIMEZONE || 'Asia/Manila');

    // db.query returns rows directly, rather than mysql2's [rows, fields] tuple.
    const upcomingRows = await db.query(
      `SELECT
        c.id AS contract_id,
        c.start_date AS due_date,
        c.downpayment AS amount_due,
        CONCAT('IHC-', c.id) AS contract_code,
        c.client_name,
        c.email AS client_email
       FROM contracts c
       WHERE DATE(c.start_date) BETWEEN ? AND DATE_ADD(?, INTERVAL 3 DAY)
        AND NOT EXISTS (
          SELECT 1
          FROM notifications_logs nl
          WHERE nl.contract_id IN (CAST(c.id AS CHAR), CONCAT('IHC-', c.id), CONCAT('CON-', c.id))
           AND nl.channel = 'email'
           AND nl.reminder_type = 'due_soon'
           AND nl.status = 'sent'
        )`,
      [todayStr, todayStr]
    );

    for (const row of upcomingRows) {
      if (row.client_email) {
        const result = await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code,
          row.amount_due,
        row.due_date,
          Math.floor(
            (Date.parse(`${new Date(row.due_date).toISOString().slice(0, 10)}T00:00:00Z`) - Date.parse(`${todayStr}T00:00:00Z`)) / 86400000
          ),
          { reminderType: 'due_soon' }
        );
        if (result.success) {
          console.log(`[CRON] Reminder sent to ${row.client_email} for ${row.contract_code}`);
        }
      }
    }

    const overdueRows = await db.query(
      `SELECT
        c.id AS contract_id,
        c.start_date AS due_date,
        c.downpayment AS amount_due,
        CONCAT('IHC-', c.id) AS contract_code,
        c.client_name,
        c.email AS client_email
       FROM contracts c
       WHERE DATE(c.start_date) < ?
        AND NOT EXISTS (
          SELECT 1
          FROM notifications_logs nl
          WHERE nl.contract_id IN (CAST(c.id AS CHAR), CONCAT('IHC-', c.id), CONCAT('CON-', c.id))
           AND nl.channel = 'email'
           AND nl.reminder_type = 'overdue'
           AND nl.status = 'sent'
           AND DATE(nl.sent_at) = ?
        )`,
      [todayStr, todayStr]
    );

    for (const row of overdueRows) {
      const dueDateStr = new Date(row.due_date).toISOString().slice(0, 10);
      const daysUntil = Math.floor(
        (Date.parse(`${dueDateStr}T00:00:00Z`) - Date.parse(`${todayStr}T00:00:00Z`)) / 86400000
      );

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

    console.log(`[CRON] Daily reminder check complete. Upcoming: ${upcomingRows.length}, Overdue: ${overdueRows.length}`);
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
