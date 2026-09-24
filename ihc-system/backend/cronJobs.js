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

async function fetchHoldingExpiringRows(todayStr, channel) {
  try{
    return await db.query(
      `SELECT hf.id AS holding_id, hf.expiration_date AS due_date, hf.amount AS amount_due,
              hf.contract_id, CONCAT('IHC-', hf.contract_id) AS contract_code,
              c.client_name, c.email AS client_email, c.cellphone_number,
              COALESCE(pu.display_label, c.property_address) AS unit_label
       FROM holding_fees hf
       JOIN contracts c ON c.id=hf.contract_id
       LEFT JOIN property_units pu ON pu.id=hf.property_unit_id
       WHERE hf.status='PAID' AND hf.expiration_date BETWEEN DATE_ADD(?, INTERVAL 2 DAY) AND DATE_ADD(?, INTERVAL 3 DAY)
        AND NOT EXISTS (
          SELECT 1 FROM notifications_logs nl
          WHERE nl.contract_id IN (CAST(hf.contract_id AS CHAR) COLLATE utf8mb4_general_ci, CONCAT('IHC-', hf.contract_id) COLLATE utf8mb4_general_ci, CONCAT('CON-', hf.contract_id) COLLATE utf8mb4_general_ci)
           AND nl.channel = ? AND nl.reminder_type='holding_expiring' AND nl.status='sent'
           AND DATE(nl.sent_at)=?
           AND nl.subject LIKE CONCAT('%HF-', hf.id ,'%')
        )`,
      [todayStr, channel, todayStr]
    );
  }catch(e){ return []; }
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

    // Holding fee expiring in 2 days §18 — notify clerk and client if holding is PAID
    const holdingExpiringEmail = await fetchHoldingExpiringRows(todayStr, 'email');
    for(const row of holdingExpiringEmail){
      if(row.client_email){
        const result = await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code + ' ('+row.unit_label+' HF-'+row.holding_id+')',
          row.amount_due,
          row.due_date,
          daysUntilDue(row.due_date, todayStr),
          { reminderType: 'holding_expiring' }
        );
        if(result.success) console.log(`[CRON] Holding expiring notice sent to ${row.client_email} for HF-${row.holding_id} (${row.unit_label})`);
      }
    }
    const holdingExpiringSms = await fetchHoldingExpiringRows(todayStr, 'sms');
    for(const row of holdingExpiringSms){
      if(row.cellphone_number){
        const result = await sendPaymentReminderSms(
          row.cellphone_number,
          row.client_name,
          row.contract_code + ' ('+row.unit_label+' HF-'+row.holding_id+')',
          row.amount_due,
          row.due_date,
          daysUntilDue(row.due_date, todayStr),
          { reminderType: 'holding_expiring' }
        );
        if(result.success) console.log(`[CRON] Holding expiring SMS sent to ${row.cellphone_number} for HF-${row.holding_id}`);
      }
    }

    console.log(`[CRON] Daily reminder check complete. Upcoming: ${upcomingEmailRows.length} email / ${upcomingSmsRows.length} SMS, Overdue: ${overdueRows.length}, HoldingExpiring: ${holdingExpiringEmail.length} email / ${holdingExpiringSms.length} SMS`);
  } catch (error) {
    console.error('[CRON] Error during daily reminder check:', error);
    throw error;
  }
}

// ------------------------------------------------------------------
// Holding Fee expiration sweeper (§5) — respects business_rules
// Converts stale PAID/PENDING holds (expiration < today) to EXPIRED
// and optionally reverts property_units ON HOLD -> AVAILABLE.
// Runs alongside payment reminders so Windows Task Scheduler covers it.
// ------------------------------------------------------------------
async function runHoldingFeeExpiration() {
  console.log('[CRON] Checking holding fee expirations...');
  try {
    let expireMakesAvailable = true;
    try {
      const rows = await db.query("SELECT rule_value FROM business_rules WHERE rule_key='holding_fee.expire_makes_available' LIMIT 1");
      if (rows && rows.length) expireMakesAvailable = String(rows[0].rule_value) === '1';
    } catch (e) { /* table missing -> default true */ }

    const todayStr = dateInTimeZone(new Date(), process.env.REMINDER_TIMEZONE || 'Asia/Manila');
    let expiredRows = [];
    try {
      expiredRows = await db.query(
        "SELECT id, contract_id, property_unit_id, status, expiration_date, amount FROM holding_fees WHERE status IN ('PENDING','PAID') AND expiration_date < ? AND converted_to_reservation_id IS NULL",
        [todayStr]
      );
    } catch (e) {
      console.log('[CRON] holding_fees table not present, skipping expiration.');
      return { expired: 0 };
    }

    let count = 0;
    for (const r of expiredRows) {
      await db.query("UPDATE holding_fees SET status='EXPIRED', updated_at=NOW() WHERE id=?", [r.id]);
      try {
        await db.query(
          "INSERT INTO audit_logs (action, contract_id, holding_fee_id, property_unit_id, from_status, to_status, details) VALUES ('holding_fee.expired', ?, ?, ?, ?, 'EXPIRED', JSON_OBJECT('expiration_date', ?, 'amount', ?))",
          [r.contract_id, r.id, r.property_unit_id || null, r.status, String(r.expiration_date), String(r.amount)]
        );
      } catch (e) { /* audit best-effort */ }
      if (expireMakesAvailable && r.property_unit_id) {
        await db.query("UPDATE property_units SET status='AVAILABLE', current_holding_fee_id=NULL, updated_at=NOW() WHERE id=? AND status='ON HOLD'", [r.property_unit_id]);
        try {
          await db.query(
            "INSERT INTO audit_logs (action, contract_id, holding_fee_id, property_unit_id, from_status, to_status, details) VALUES ('unit.status_changed', ?, ?, ?, 'ON HOLD', 'AVAILABLE', JSON_OBJECT('reason','holding expired','holding_id', ?))",
            [r.contract_id, r.id, r.property_unit_id, String(r.id)]
          );
        } catch (e) {}
        console.log(`[CRON] Holding fee ${r.id} expired — unit ${r.property_unit_id} -> AVAILABLE`);
      } else {
        console.log(`[CRON] Holding fee ${r.id} expired`);
      }
      count++;
    }
    console.log(`[CRON] Holding fee expiration complete. Expired: ${count}`);
    return { expired: count };
  } catch (error) {
    console.error('[CRON] Error during holding fee expiration:', error);
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
  cron.schedule('5 0 * * *', () => runHoldingFeeExpiration().catch(() => {}), {
    noOverlap: true,
    timezone: process.env.REMINDER_TIMEZONE || 'Asia/Manila'
  });
  console.log('[CRON] Holding fee expiration scheduler initialized (runs daily at 12:05 AM)');
}

module.exports = { runDailyReminders, runHoldingFeeExpiration };
