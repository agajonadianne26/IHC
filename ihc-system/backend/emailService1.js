const nodemailer = require('nodemailer');
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

function buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil) {
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
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 20px;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">This is a system-generated email from Imperial Homes Corporation. For concerns, contact our Billing Department.</p>
      </div>
    </div>
  `;
}

async function sendPaymentReminder(to, clientName, contractId, amountDue, dueDate, daysUntil) {
  const isOverdue = daysUntil < 0;
  const subject = isOverdue
    ? `OVERDUE: Payment of ${formatCurrency(amountDue)} for ${contractId} is past due`
    : `Reminder: Payment of ${formatCurrency(amountDue)} for ${contractId} due in ${daysUntil} day(s)`;

  const html = buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil);

  try {
    const info = await transporter.sendMail({
      from: process.env.EMAIL_FROM,
      to,
      subject,
      html
    });

    console.log(`Email sent to ${to}: ${info.messageId}`);

    await db.query(
      `INSERT INTO notification_logs (contract_id, client_email, channel, subject, status, sent_at)
       VALUES (?, ?, 'email', ?, 'sent', NOW())`,
      [contractId, to, subject]
    );

    return { success: true, messageId: info.messageId };
  } catch (error) {
    console.error(`Failed to send email to ${to}:`, error.message);

    await db.query(
      `INSERT INTO notification_logs (contract_id, client_email, channel, subject, status, sent_at, error_message)
       VALUES (?, ?, 'email', ?, 'failed', NOW(), ?)`,
      [contractId, to, subject, error.message]
    );

    return { success: false, error: error.message };
  }
}

module.exports = { sendPaymentReminder, formatCurrency, formatDate };
