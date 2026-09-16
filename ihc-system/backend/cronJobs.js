const cron = require('node-cron');
const db = require('./db');
const { sendPaymentReminder } = require('./emailService');
require('dotenv').config();

cron.schedule('0 8 * * *', async () => {
  console.log('[CRON] Running daily payment reminder check...');

  try {
    const today = new Date();
    const todayStr = today.toISOString().slice(0, 10);

    const [upcomingRows] = await db.query(
      `SELECT
        s.id AS installment_id,
        s.contract_id,
        s.due_date,
        s.amount_due,
        CONCAT('IHC-2026-', LPAD(c.id, 3, '0')) AS contract_code,
        cl.full_name AS client_name,
        cl.email AS client_email
       FROM installment_schedules s
       JOIN contracts c ON s.contract_id = c.id
       JOIN clients cl ON c.client_id = cl.id
       WHERE s.status = 'Pending Payment'
        AND DATE(s.due_date) = DATE_ADD(?, INTERVAL 3 DAY)
        AND NOT EXISTS (
          SELECT 1
          FROM notification_logs nl
          WHERE nl.installment_id = s.id
           AND nl.channel = 'email'
           AND nl.status = 'sent'
           AND DATE(nl.sent_at) = ?
        )`,
      [todayStr, todayStr]
    );

    for (const row of upcomingRows) {
      const dueDate = new Date(row.due_date);
      const daysUntil = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));

      if (row.client_email) {
        await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code,
          row.amount_due,
          row.due_date,
          daysUntil,
          row.installment_id
        );
        console.log(`[CRON] Reminder sent to ${row.client_email} for ${row.contract_code}`);
      }
    }

    const [overdueRows] = await db.query(
      `SELECT
        s.id AS installment_id,
        s.contract_id,
        s.due_date,
        s.amount_due,
        CONCAT('IHC-2026-', LPAD(c.id, 3, '0')) AS contract_code,
        cl.full_name AS client_name,
        cl.email AS client_email
       FROM installment_schedules s
       JOIN contracts c ON s.contract_id = c.id
       JOIN clients cl ON c.client_id = cl.id
       WHERE s.status = 'Pending Payment'
        AND DATE(s.due_date) < ?
        AND NOT EXISTS (
          SELECT 1
          FROM notification_logs nl
          WHERE nl.installment_id = s.id
           AND nl.channel = 'email'
           AND nl.status = 'sent'
           AND DATE(nl.sent_at) = ?
        )`,
      [todayStr, todayStr]
    );

    for (const row of overdueRows) {
      const dueDate = new Date(row.due_date);
      const daysUntil = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));

      if (row.client_email) {
        await sendPaymentReminder(
          row.client_email,
          row.client_name,
          row.contract_code,
          row.amount_due,
          row.due_date,
          daysUntil,
          row.installment_id
        );
        console.log(`[CRON] Overdue reminder sent to ${row.client_email} for ${row.contract_code}`);
      }
    }

    console.log(`[CRON] Daily reminder check complete. Upcoming: ${upcomingRows.length}, Overdue: ${overdueRows.length}`);
  } catch (error) {
    console.error('[CRON] Error during daily reminder check:', error);
  }
}, {
  timezone: 'Asia/Manila'
});

console.log('[CRON] Payment reminder scheduler initialized (runs daily at 8:00 AM)');
