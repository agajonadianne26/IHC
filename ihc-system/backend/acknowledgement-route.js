const crypto = require('crypto');
const express = require('express');
const db = require('./db');
const { transporter, formatCurrency, formatDate } = require('./emailService');

const router = express.Router();
let contractColumnsPromise;
let notificationLogSchemaPromise;

async function getContractColumns() {
  if (!contractColumnsPromise) {
    contractColumnsPromise = db.query('SHOW COLUMNS FROM contracts')
      .then(columns => new Set(columns.map(column => String(column.Field).toLowerCase())));
  }
  return contractColumnsPromise;
}

async function getNotificationLogSchema() {
  if (!notificationLogSchemaPromise) {
    notificationLogSchemaPromise = (async () => {
      const tables = await db.query('SHOW TABLES');
      const table = tables.map(row => Object.values(row)[0]).find(name =>
        ['notification_logs', 'notifications_logs'].includes(String(name).toLowerCase())
      );
      if (!table) throw new Error('Notification log table is missing');
      const columns = await db.query(`SHOW COLUMNS FROM \`${table}\``);
      return {
        table: String(table),
        columns: new Set(columns.map(column => String(column.Field).toLowerCase()))
      };
    })();
  }
  return notificationLogSchemaPromise;
}

async function findAcknowledgement(contractId, installmentId) {
  const { table, columns } = await getNotificationLogSchema();
  const hasInstallmentId = columns.has('installment_id');
  const rows = await db.query(
    `SELECT id, sent_at AS acknowledged_at
     FROM \`${table}\`
     WHERE (contract_id = ? OR contract_id = CONCAT('CON-', ?))
       ${hasInstallmentId ? 'AND installment_id <=> ?' : ''}
       AND channel = 'ack_email' AND status = 'sent'
     ORDER BY sent_at DESC
     LIMIT 1`,
    hasInstallmentId ? [contractId, contractId, installmentId || null] : [contractId, contractId]
  );
  return rows;
}

async function recordAcknowledgement(contractId, installmentId, recipientEmail, subject) {
  const { table, columns } = await getNotificationLogSchema();
  const fields = ['contract_id'];
  const values = [contractId];
  if (columns.has('installment_id')) {
    fields.push('installment_id');
    values.push(installmentId || null);
  }
  fields.push('client_email', 'channel', 'subject', 'status', 'sent_at');
  values.push(recipientEmail, 'ack_email', subject, 'sent');
  await db.query(
    `INSERT INTO \`${table}\` (${fields.join(', ')}) VALUES (${fields.map((field, index) => index === fields.length - 1 ? 'NOW()' : '?').join(', ')})`,
    values
  );
}

// Older reminder emails used the display account code (for example, CON-7)
// while newer emails use the numeric database ID. Both refer to the same
// contract, so accept either form in a signed acknowledgement token.
function normalizeContractId(value) {
  const match = String(value || '').trim().match(/(\d+)$/);
  if (!match) return null;
  const id = Number(match[1]);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
}

async function findReminderAccount(contractId, recipientEmail, installmentId) {
  const columns = await getContractColumns();

  // The local PHP dashboard uses this legacy contracts schema. It does not
  // have a clients table, so querying through clients made valid links look
  // like the account did not exist.
  if (columns.has('client_name') && columns.has('email')) {
    return db.query(
      `SELECT c.id AS contract_id, c.client_name, c.email AS client_email,
              c.email AS recipient_email, NULL AS officer_email,
              NULL AS officer_name, c.downpayment AS amount_due,
              c.start_date AS due_date
       FROM contracts c
       WHERE c.id = ? AND LOWER(c.email) = LOWER(?)
       LIMIT 1`,
      [contractId, recipientEmail]
    );
  }

  return db.query(
    `SELECT c.id AS contract_id, c.client_id, cl.full_name AS client_name,
            cl.email AS client_email, cl.email AS recipient_email,
            o.email AS officer_email, o.name AS officer_name,
            s.amount_due, s.due_date
     FROM contracts c
     JOIN clients cl ON c.client_id = cl.id
     LEFT JOIN officers o ON c.officer_id = o.id
     LEFT JOIN installment_schedules s ON s.id = ? AND s.contract_id = c.id
     WHERE c.id = ? AND LOWER(cl.email) = LOWER(?)
     LIMIT 1`,
    [installmentId || 0, contractId, recipientEmail]
  );
}

function getAcknowledgementSecret() {
  if (!process.env.ACKNOWLEDGEMENT_SECRET) {
    throw new Error('ACKNOWLEDGEMENT_SECRET is required');
  }
  return process.env.ACKNOWLEDGEMENT_SECRET;
}

function verifyToken(token) {
  const [encodedPayload, signature] = String(token || '').split('.');
  if (!encodedPayload || !signature) return null;

  const expected = crypto
    .createHmac('sha256', getAcknowledgementSecret())
    .update(encodedPayload)
    .digest('base64url');
  const signaturesMatch = signature.length === expected.length &&
    crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected));
  if (!signaturesMatch) return null;

  try {
    const payload = JSON.parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
    if (!payload.contractId || !payload.recipientEmail ||
      !Number.isFinite(payload.expiresAt) || payload.expiresAt < Date.now()) return null;
    return payload;
  } catch {
    return null;
  }
}

router.get('/api/contracts/:id/acknowledgment-status', async (req, res) => {
  try {
    const contractId = normalizeContractId(req.params.id);
    if (!contractId) return res.status(400).json({ success: false, acknowledged: false });
    const rows = await findAcknowledgement(contractId, null);

    res.json({
      success: true,
      acknowledged: rows.length > 0,
      acknowledgedAt: rows[0] ? rows[0].acknowledged_at : null
    });
  } catch (error) {
    console.error('[ACKNOWLEDGEMENT STATUS] Failed:', error.message);
    res.status(500).json({ success: false, acknowledged: false });
  }
});

router.get('/api/reminders/acknowledge', async (req, res) => {
  const payload = verifyToken(req.query.token);
  const dashboardUrl = process.env.CLIENT_DASHBOARD_URL || 'http://localhost/ihc-system/html/client-dashboard.html';

  if (!payload) return res.status(400).send('This acknowledgment link is invalid.');

  const contractId = normalizeContractId(payload.contractId);
  if (!contractId) return res.status(400).send('This acknowledgment link has an invalid contract reference.');

  try {
    const rows = await findReminderAccount(contractId, payload.recipientEmail, payload.installmentId);

    if (!rows.length) return res.status(404).send('The reminder account could not be found.');
    const reminder = rows[0];

    const alreadyAcknowledged = await findAcknowledgement(contractId, payload.installmentId);

    if (!alreadyAcknowledged.length) {
      const subject = `Client acknowledged payment reminder for IHC-${reminder.contract_id}`;
      if (reminder.officer_email) {
        await transporter.sendMail({
          from: process.env.EMAIL_FROM,
          to: reminder.officer_email,
          subject,
          html: `<p>Dear ${reminder.officer_name || 'Billing Clerk'},</p>
            <p><strong>${reminder.client_name}</strong> has acknowledged the payment reminder for contract <strong>IHC-${reminder.contract_id}</strong>.</p>
            <p>Amount due: <strong>${formatCurrency(reminder.amount_due || 0)}</strong><br>
            Due date: <strong>${formatDate(reminder.due_date || new Date())}</strong></p>
            <p>This acknowledgment was recorded automatically.</p>`
        });
      }

      await recordAcknowledgement(contractId, payload.installmentId, payload.recipientEmail, subject);
    }

    res.redirect(303, dashboardUrl);
  } catch (error) {
    console.error('[ACKNOWLEDGEMENT] Failed:', error.message);
    res.status(500).send('The acknowledgment could not be recorded. Please try again.');
  }
});

module.exports = router;
