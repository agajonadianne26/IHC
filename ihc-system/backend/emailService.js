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
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function safeText(value, maxLength = 255) {
  return String(value == null ? '' : value).trim().slice(0, maxLength);
}

function safeNumber(value) {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

/**
 * Rebuild the SOA summary from an explicit whitelist. Even if a caller sends
 * a full document by mistake, detailed schedules/allocations can never reach
 * the payment-reminder email.
 */
function normalizeSoaSummary(summary) {
  if (!summary || typeof summary !== 'object' || Array.isArray(summary)) return null;
  const text = (key, max = 255) => safeText(summary[key], max);
  const amount = (key) => Math.max(0, safeNumber(summary[key]));

  return {
    soaNumber: text('soaNumber', 100),
    asOfDate: text('asOfDate', 10),
    validUntil: text('validUntil', 10),
    companyName: text('companyName', 255),
    companyContact: text('companyContact', 255),
    clientName: text('clientName', 255),
    contractReference: text('contractReference', 100),
    projectName: text('projectName', 255),
    phase: text('phase', 120),
    block: text('block', 50),
    lot: text('lot', 50),
    totalContractPrice: amount('totalContractPrice'),
    discount: amount('discount'),
    netContractPrice: amount('netContractPrice'),
    equity: amount('equity'),
    requiredDownpayment: amount('requiredDownpayment'),
    totalEquity: amount('totalEquity'),
    loanableAmount: amount('loanableAmount'),
    totalPaymentsMade: amount('totalPaymentsMade'),
    remainingBalance: amount('remainingBalance'),
    nextPaymentType: text('nextPaymentType', 120),
    nextDueDate: text('nextDueDate', 10),
    currentPaymentDue: amount('currentPaymentDue'),
    monthlyPayment: amount('monthlyPayment'),
    annualInterestRate: Math.max(0, safeNumber(summary.annualInterestRate)),
    interest: amount('interest'),
    penalty: amount('penalty'),
    reservationOutstanding: amount('reservationOutstanding'),
    additionalCharges: amount('additionalCharges'),
    additionalEquityOutstanding: amount('additionalEquityOutstanding'),
    totalAmountDue: amount('totalAmountDue'),
    status: text('status', 20).toUpperCase()
  };
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

function buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, recipientEmail, installmentId, summaryInput) {
  const summary = normalizeSoaSummary(summaryInput);
  const numericDays = Number.isFinite(Number(daysUntil)) ? Number(daysUntil) : 0;
  const urgency = numericDays < 0
    ? `<p style="color:#dc2626;font-weight:700;font-size:16px;">This payment is now <strong>${Math.abs(numericDays)} day(s) overdue</strong>.</p>`
    : `<p>Your upcoming payment is due in <strong>${numericDays} day(s)</strong>.</p>`;

  const safeClientName = escapeHtml(summary?.clientName || safeText(clientName));
  const safeCompanyName = escapeHtml(summary?.companyName || 'Imperial Homes Corporation');
  const displayContract = summary?.contractReference || safeText(contractId);
  const currentAmount = summary ? summary.currentPaymentDue : safeNumber(amountDue);
  const currentDueDate = summary?.nextDueDate || dueDate;

  const row = (label, value, options = {}) => `
    <tr${options.shaded ? ' style="background:#f8fafc;"' : ''}>
      <td style="padding:9px 14px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;width:48%;">${escapeHtml(label)}</td>
      <td style="padding:9px 14px;color:${options.color || '#1e293b'};font-weight:${options.bold ? 700 : 400};font-size:${options.fontSize || '14px'};text-align:right;border-bottom:1px solid #e2e8f0;">${escapeHtml(value)}</td>
    </tr>`;

  const legacyTable = `
    <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
      ${row('Contract ID', displayContract, { shaded: true })}
      ${row('Amount Due', formatCurrency(currentAmount), { color: '#dc2626', bold: true, fontSize: '16px' })}
      ${row('Due Date', formatDate(currentDueDate), { shaded: true })}
    </table>`;

  let summaryTable = legacyTable;
  if (summary) {
    const otherCharges = summary.reservationOutstanding + summary.additionalCharges + summary.additionalEquityOutstanding;
    const projectReference = [summary.projectName, summary.phase, summary.block ? `Block ${summary.block}` : '', summary.lot ? `Lot ${summary.lot}` : '']
      .filter(Boolean)
      .join(' • ');
    summaryTable = `
      <div style="margin:20px 0;padding:16px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px;">
          <div>
            <h3 style="color:#1e3a8a;margin:0;font-size:17px;">Statement of Account Summary</h3>
            <p style="color:#64748b;margin:4px 0 0;font-size:12px;">SOA ${escapeHtml(summary.soaNumber)}${summary.asOfDate ? ` • As of ${escapeHtml(formatDate(summary.asOfDate))}` : ''}${summary.validUntil ? ` • Valid until ${escapeHtml(formatDate(summary.validUntil))}` : ''}</p>
          </div>
          <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:${summary.status === 'OVERDUE' ? '#fee2e2' : summary.status === 'SETTLED' ? '#d1fae5' : '#dbeafe'};color:${summary.status === 'OVERDUE' ? '#b91c1c' : summary.status === 'SETTLED' ? '#047857' : '#1d4ed8'};font-size:10px;font-weight:800;">${escapeHtml(summary.status || 'DUE')}</span>
        </div>
        ${projectReference ? `<p style="color:#475569;margin:0 0 10px;font-size:12px;"><strong>Project:</strong> ${escapeHtml(projectReference)}</p>` : ''}
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #dbeafe;border-radius:8px;overflow:hidden;">
          ${row('Total Contract Price', formatCurrency(summary.totalContractPrice), { shaded: true })}
          ${row('Less: Discount', formatCurrency(summary.discount))}
          ${row('Net Contract Price', formatCurrency(summary.netContractPrice), { shaded: true, bold: true })}
          ${row('Equity', formatCurrency(summary.equity))}
          ${row('Required Downpayment', formatCurrency(summary.requiredDownpayment), { shaded: true })}
          ${row('Total Equity', formatCurrency(summary.totalEquity), { bold: true })}
          ${row('Loanable Amount', formatCurrency(summary.loanableAmount), { shaded: true })}
          ${row('Total Payments Made', formatCurrency(summary.totalPaymentsMade))}
          ${row('Remaining Principal / Equity', formatCurrency(summary.remainingBalance), { shaded: true, bold: true })}
          ${row('Current Payment', `${summary.nextPaymentType} — ${formatCurrency(summary.currentPaymentDue)}`)}
          ${row('Current Payment Due Date', formatDate(summary.nextDueDate), { shaded: true })}
          ${row('Interest', formatCurrency(summary.interest))}
          ${row('Penalty', formatCurrency(summary.penalty), { shaded: true })}
          ${row('Reservation / Other Charges', formatCurrency(otherCharges))}
          ${row('Monthly Payment', formatCurrency(summary.monthlyPayment), { shaded: true })}
          ${row('Applicable Interest Rate', `${Number(summary.annualInterestRate).toFixed(2)}% annual`)}
          ${row('TOTAL AMOUNT DUE', formatCurrency(summary.totalAmountDue), { color: '#1d4ed8', bold: true, fontSize: '18px' })}
        </table>
        <p style="color:#475569;margin:12px 0 0;font-size:12px;line-height:1.5;">
          This email contains only the summarized SOA. The complete detailed SOA, including the full installment schedule and transaction history, is available in your Client Dashboard.
        </p>
      </div>`;
  }

  return `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:28px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:22px;">${safeCompanyName}</h1>
        <p style="color:#bfdbfe;margin:6px 0 0;font-size:13px;">Billing &amp; Accounts Receivable</p>
        ${summary && summary.companyContact ? `<p style="color:#dbeafe;margin:3px 0 0;font-size:11px;">${escapeHtml(summary.companyContact)}</p>` : ''}
      </div>
      <div style="padding:32px;">
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:20px;">Payment Reminder</h2>
        <p style="color:#475569;margin:0 0 12px;">Dear <strong>${safeClientName}</strong>,</p>
        <p style="color:#475569;margin:0 0 16px;">This is a friendly reminder regarding your account with ${safeCompanyName}.</p>
        ${urgency}
        ${summaryTable}
        <p style="color:#475569;margin:0 0 8px;">Please ensure your payment is made on or before the due date to avoid any late fees or penalties.</p>
        <p style="color:#475569;margin:0 0 24px;">If you have already made this payment, please disregard this notice. Thank you!</p>
        <p style="text-align:center;margin:0 0 24px;">
          <a href="${escapeHtml(getClientAccessUrl(recipientEmail))}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Open Client Dashboard</a>
        </p>
        <p style="text-align:center;margin:0 0 24px;">
          <a href="${escapeHtml(getAcknowledgementUrl(contractId, recipientEmail, installmentId))}" style="display:inline-block;background:#059669;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Acknowledge Reminder</a>
        </p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 20px;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">This is a system-generated email from ${safeCompanyName}. For concerns, contact our Billing Department.</p>
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
  const summary = normalizeSoaSummary(options.soaSummary);
  const subjectPrefix = summary ? 'Payment Reminder / SOA Summary' : 'Payment Reminder';
  const subject = isOverdue
    ? `OVERDUE: ${subjectPrefix} — ${formatCurrency(amountDue)} for ${contractId} is past due`
    : `${subjectPrefix}: ${formatCurrency(amountDue)} for ${contractId} due in ${daysUntil} day(s)`;

  const html = buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, to, options.installmentId, summary);

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
  buildReminderEmail,
  normalizeSoaSummary,
  logNotification,
  formatCurrency,
  formatDate,
  transporter,
  getAcknowledgementUrl
};
