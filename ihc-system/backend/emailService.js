const nodemailer = require('nodemailer');
const crypto = require('crypto');
const db = require('./db');
require('dotenv').config();

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST,
  port: parseInt(process.env.SMTP_PORT, 10),
  secure: false,
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS
  }
});

function formatCurrency(amount) {
  return '\u20B1' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function getClientAccessUrl(recipientEmail) {
  const loginUrl = process.env.CLIENT_LOGIN_URL || 'http://localhost/ihc-system/log%20in.html';
  const url = new URL(loginUrl);
  url.searchParams.set('redirect', 'client-dashboard');
  url.searchParams.set('email', recipientEmail);
  return url.toString();
}

function getAcknowledgementSecret() {
  if (!process.env.ACKNOWLEDGEMENT_SECRET) {
    throw new Error('ACKNOWLEDGEMENT_SECRET is required');
  }
  return process.env.ACKNOWLEDGEMENT_SECRET;
}

function getAcknowledgementUrl(contractId, recipientEmail, installmentId) {
  const payload = JSON.stringify({
    contractId: String(contractId),
    recipientEmail,
    installmentId: installmentId || null,
    expiresAt: Date.now() + (30 * 24 * 60 * 60 * 1000)
  });
  const encodedPayload = Buffer.from(payload).toString('base64url');
  const signature = crypto
    .createHmac('sha256', getAcknowledgementSecret())
    .update(encodedPayload)
    .digest('base64url');
  const url = new URL(process.env.ACKNOWLEDGEMENT_URL || 'http://localhost:3000/api/reminders/acknowledge');
  url.searchParams.set('token', encodedPayload + '.' + signature);
  return url.toString();
}

function buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, recipientEmail, installmentId) {
  const urgency = daysUntil < 0
    ? `<p style="color:#dc2626;font-weight:700;font-size:16px;">This payment is now <strong>${Math.abs(daysUntil)} day(s) overdue</strong>.</p>`
    : `<p>Your upcoming installment is due in <strong>${daysUntil} day(s)</strong>.</p>`;

  return `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:28px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:22px;">Imperial Homes Corporation</h1>
        <p style="color:#bfdbfe;margin:6px 0 0;font-size:13px;">Billing &amp; Accounts Receivable</p>
      </div>
      <div style="padding:32px;">
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:20px;">Payment Reminder</h2>
        <p style="color:#475569;margin:0 0 12px;">Dear <strong>${clientName}</strong>,</p>
        <p style="color:#475569;margin:0 0 16px;">This is a friendly reminder regarding your account with Imperial Homes Corporation.</p>
        ${urgency}
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
          <tr style="background:#f1f5f9;">
            <td style="padding:10px 16px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;">Contract ID</td>
            <td style="padding:10px 16px;color:#1e293b;border-bottom:1px solid #e2e8f0;">${contractId}</td>
          </tr>
          <tr>
            <td style="padding:10px 16px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;">Amount Due</td>
            <td style="padding:10px 16px;color:#dc2626;font-weight:700;font-size:16px;border-bottom:1px solid #e2e8f0;">${formatCurrency(amountDue)}</td>
          </tr>
          <tr style="background:#f1f5f9;">
            <td style="padding:10px 16px;font-weight:600;color:#475569;">Due Date</td>
            <td style="padding:10px 16px;color:#1e293b;">${formatDate(dueDate)}</td>
          </tr>
        </table>
        <p style="color:#475569;margin:0 0 8px;">Please ensure your payment is made on or before the due date to avoid any late fees or penalties.</p>
        <p style="color:#475569;margin:0 0 24px;">If you have already made this payment, please disregard this notice. Thank you!</p>
        <p style="text-align:center;margin:0 0 24px;">
          <a href="${getClientAccessUrl(recipientEmail)}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Open Client Dashboard</a>
        </p>
        <p style="text-align:center;margin:0 0 24px;">
          <a href="${getAcknowledgementUrl(contractId, recipientEmail, installmentId)}" style="display:inline-block;background:#059669;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Acknowledge Reminder</a>
        </p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 20px;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">This is a system-generated email from Imperial Homes Corporation. For concerns, contact our Billing Department.</p>
      </div>
    </div>
  `;
}

// Best-effort audit log — a broken/missing notification_logs table should
// never be allowed to make a successfully-sent message look like a failure.
// Shared by emailService ('email') and smsService ('sms'); there is no phone
// column, so for SMS rows `to` (the number) goes in client_email and the
// channel value is what disambiguates the two.
async function logNotification(fields) {
  try {
    await db.query(
      `INSERT INTO notifications_logs
        (contract_id, client_email, channel, reminder_type, due_date, subject, status, sent_at, error_message)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)`,
      [
        fields.contractId,
        fields.to,
        fields.channel || 'email',
        fields.reminderType || 'manual',
        fields.dueDate || null,
        fields.subject,
        fields.status,
        fields.errorMessage || null
      ]
    );
  } catch (logErr) {
    console.error('notification_logs insert failed (email itself was unaffected):', logErr.message);
  }
}

async function sendPaymentReminder(to, clientName, contractId, amountDue, dueDate, daysUntil, options = {}) {
  const isOverdue = daysUntil < 0;
  const subject = isOverdue
    ? `OVERDUE: Payment of ${formatCurrency(amountDue)} for ${contractId} is past due`
    : `Reminder: Payment of ${formatCurrency(amountDue)} for ${contractId} due in ${daysUntil} day(s)`;

  const html = buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, to, options.installmentId);

  let info;
  try {
    info = await transporter.sendMail({
      from: process.env.EMAIL_FROM,
      to,
      subject,
      html
    });
    console.log(`Email sent to ${to}: ${info.messageId}`);
  } catch (error) {
    // Only an actual send failure lands here.
    console.error(`Failed to send email to ${to}:`, error.message);
    await logNotification({
      contractId,
      to,
      subject,
      status: 'failed',
      errorMessage: error.message,
      reminderType: options.reminderType,
      dueDate
    });
    return { success: false, error: error.message };
  }

  // The email is already out — a logging problem past this point must not
  // flip the result back to failure.
  await logNotification({
    contractId,
    to,
    subject,
    status: 'sent',
    reminderType: options.reminderType,
    dueDate
  });
  return { success: true, messageId: info.messageId };
}

function buildPaymentReceiptEmail(clientName, contractId, amount, paymentDate, method, orNumber, paymentId, recipientEmail) {
  return `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#065f46,#059669);padding:28px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:22px;">Imperial Homes Corporation</h1>
        <p style="color:#d1fae5;margin:6px 0 0;font-size:13px;">Official Payment Receipt</p>
      </div>
      <div style="padding:32px;color:#475569;">
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:20px;">Payment received</h2>
        <p>Dear <strong>${clientName}</strong>,</p>
        <p>Thank you. We have recorded your payment. This email serves as your payment receipt.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border:1px solid #e2e8f0;">
          <tr><td style="padding:10px 16px;font-weight:600;">Contract ID</td><td style="padding:10px 16px;">${contractId}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Receipt / OR No.</td><td style="padding:10px 16px;">${orNumber}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Payment date</td><td style="padding:10px 16px;">${formatDate(paymentDate)}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Payment method</td><td style="padding:10px 16px;">${method}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Amount received</td><td style="padding:10px 16px;color:#047857;font-size:17px;font-weight:700;">${formatCurrency(amount)}</td></tr>
        </table>
        <p style="margin-bottom:24px;">Receipt reference: <strong>PAY-${paymentId}</strong></p>
        <p style="text-align:center;"><a href="${getClientAccessUrl(recipientEmail)}" style="display:inline-block;background:#059669;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:6px;">Open Client Dashboard</a></p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 16px;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">This is a system-generated receipt from Imperial Homes Corporation.</p>
      </div>
    </div>`;
}

async function sendPaymentReceipt(to, clientName, contractId, amount, paymentDate, method, orNumber, paymentId) {
  const subject = `Payment Receipt: ${formatCurrency(amount)} received for ${contractId}`;
  const html = buildPaymentReceiptEmail(clientName, contractId, amount, paymentDate, method, orNumber, paymentId, to);

  try {
    const info = await transporter.sendMail({ from: process.env.EMAIL_FROM, to, subject, html });
    await logNotification({ contractId, to, subject, status: 'sent', reminderType: 'payment_receipt', dueDate: paymentDate });
    console.log(`Payment receipt sent to ${to}: ${info.messageId}`);
    return { success: true, messageId: info.messageId };
  } catch (error) {
    console.error(`Failed to send payment receipt to ${to}:`, error.message);
    await logNotification({ contractId, to, subject, status: 'failed', errorMessage: error.message, reminderType: 'payment_receipt', dueDate: paymentDate });
    return { success: false, error: error.message };
  }
}

module.exports = {
  sendPaymentReminder,
  sendPaymentReceipt,
  logNotification,
  formatCurrency,
  formatDate,
  transporter,
  getAcknowledgementUrl
};
