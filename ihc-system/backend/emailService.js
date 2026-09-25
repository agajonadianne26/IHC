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

function normalizePaymentTransaction(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  const paymentId = Number(value.paymentId);
  const paymentType = safeText(value.paymentType, 120);
  if (!Number.isFinite(paymentId) && !paymentType) return null;
  const amount = Math.max(0, safeNumber(value.amount));
  const installmentNo = value.installmentNo == null || value.installmentNo === ''
    ? (value.installmentNumber == null || value.installmentNumber === '' ? null : Math.max(0, Math.floor(safeNumber(value.installmentNumber))))
    : Math.max(0, Math.floor(safeNumber(value.installmentNo)));
  const principalAmount = Math.min(amount, Math.max(0, safeNumber(
    value.principalAmount != null ? value.principalAmount : value.principal
  )));
  const paymentDate = safeText(value.paymentDate || value.dateCollected, 10);
  const paymentMethod = safeText(value.paymentMethod || value.method, 100);
  const dueDate = safeText(value.installmentDueDate || value.dueDate, 10);
  return {
    source: 'POSTED_PAYMENT',
    paymentId: Number.isFinite(paymentId) ? paymentId : null,
    transactionReference: safeText(value.transactionReference, 100),
    contractId: safeText(value.contractId, 30),
    contractReference: safeText(value.contractReference, 100),
    paymentType: paymentType || 'Scheduled Payment',
    applicationSummary: safeText(value.applicationSummary, 240) || paymentType || 'Scheduled Payment',
    installmentKind: safeText(value.installmentKind, 40),
    installmentId: safeText(value.installmentId, 80),
    installmentNo,
    installmentNumber: installmentNo,
    amountDue: Math.max(0, safeNumber(value.amountDue)),
    amountPaid: Math.max(0, safeNumber(value.amountPaid != null ? value.amountPaid : amount)),
    amount,
    principal: principalAmount,
    principalAmount,
    additionalEquityAmount: Math.min(amount, Math.max(0, safeNumber(value.additionalEquityAmount))),
    dueDate,
    installmentDueDate: dueDate,
    paymentDate,
    dateCollected: paymentDate,
    paymentMethod,
    method: paymentMethod,
    orNumber: safeText(value.orNumber || value.orReference, 100),
    orReference: safeText(value.orNumber || value.orReference, 100),
    invoiceNumber: safeText(value.invoiceNumber, 100),
    checkNumber: safeText(value.checkNumber, 100),
    externalReference: safeText(value.externalReference, 100),
    interest: Math.max(0, safeNumber(value.interest)),
    penalty: Math.max(0, safeNumber(value.penalty)),
    remainingBalance: Math.max(0, safeNumber(value.remainingBalance)),
    totalAmountDue: Math.max(0, safeNumber(value.totalAmountDue)),
    paymentStatus: safeText(value.paymentStatus || value.status, 30).toUpperCase() || 'POSTED',
    status: safeText(value.paymentStatus || value.status, 30).toUpperCase() || 'POSTED',
    soaNumber: safeText(value.soaNumber, 100),
    soaAsOfDate: safeText(value.soaAsOfDate, 10),
    soaValidUntil: safeText(value.soaValidUntil, 10),
    postedBy: safeText(value.postedBy, 100),
    postedByName: safeText(value.postedByName, 255),
    receiptRequested: value.receiptRequested !== false
  };
}

/**
 * Rebuild the SOA summary from an explicit whitelist. Even if a caller sends
 * a full document by mistake, detailed schedules/allocations can never reach
 * the payment-reminder email. A compact posted-payment object is accepted only
 * as a normalized transaction context, never as a schedule.
 */
function normalizeSoaSummary(summary) {
  if (!summary || typeof summary !== 'object' || Array.isArray(summary)) return null;
  const text = (key, max = 255) => safeText(summary[key], max);
  const amount = (key) => Math.max(0, safeNumber(summary[key]));
  const paymentTransaction = normalizePaymentTransaction(summary.paymentTransaction);

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
    status: text('status', 20).toUpperCase(),
    paymentTransaction
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

function paymentTransactionLabel(transaction) {
  if (!transaction) return 'Payment';
  const type = safeText(transaction.paymentType, 80) || 'Payment';
  return transaction.installmentNumber != null
    ? `${type} #${transaction.installmentNumber}`
    : type;
}

function buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, recipientEmail, installmentId, summaryInput) {
  const summary = normalizeSoaSummary(summaryInput);
  const transaction = summary?.paymentTransaction || null;
  const isPostedPayment = Boolean(transaction);
  const numericDays = Number.isFinite(Number(daysUntil)) ? Number(daysUntil) : 0;
  const urgency = isPostedPayment
    ? `<p style="color:#047857;font-weight:700;font-size:16px;">The payment below was posted to the IHC ledger.</p>`
    : numericDays < 0
      ? `<p style="color:#dc2626;font-weight:700;font-size:16px;">This payment is now <strong>${Math.abs(numericDays)} day(s) overdue</strong>.</p>`
      : `<p>Your upcoming payment is due in <strong>${numericDays} day(s)</strong>.</p>`;

  const safeClientName = escapeHtml(summary?.clientName || safeText(clientName));
  const safeCompanyName = escapeHtml(summary?.companyName || 'Imperial Homes Corporation');
  const displayContract = summary?.contractReference || safeText(contractId);
  const currentAmount = summary ? summary.currentPaymentDue : safeNumber(amountDue);
  const currentDueDate = summary?.nextDueDate || dueDate;
  const showDownpaymentTerm = !summary || /downpayment/i.test(summary.nextPaymentType || '');

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

  const transactionTable = transaction ? `
    <div style="margin:0 0 14px;padding:14px;border:1px solid #a7f3d0;border-radius:9px;background:#ecfdf5;">
      <h3 style="color:#065f46;margin:0 0 9px;font-size:16px;">Posted Payment Transaction</h3>
      <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #a7f3d0;border-radius:7px;overflow:hidden;">
        ${row('Transaction reference', transaction.transactionReference || `PAY-${transaction.paymentId}`, { shaded: true })}
        ${row('Payment type', transaction.installmentNumber != null ? `${transaction.paymentType} #${transaction.installmentNumber}` : transaction.paymentType, { bold: true })}
        ${transaction.installmentNumber != null ? row('Installment no.', transaction.installmentNumber, { shaded: true }) : ''}
        ${row(transaction.installmentNumber != null ? 'Installment Due Date' : 'Downpayment Due Date', formatDate(transaction.dueDate), { shaded: transaction.installmentNumber == null })}
        ${row(transaction.installmentNumber != null ? 'Installment Amount Due' : 'Downpayment Amount Due', formatCurrency(transaction.amountDue), { bold: true })}
        ${row(transaction.installmentNumber != null ? 'Installment Amount Paid' : 'Downpayment Amount Paid', formatCurrency(transaction.amountPaid), { color: '#047857', bold: true, fontSize: '16px', shaded: true })}
        ${row('Payment date', formatDate(transaction.paymentDate), {})}
        ${row('Payment method', transaction.paymentMethod, { shaded: true })}
        ${row('OR / reference number', transaction.orNumber || transaction.invoiceNumber, { bold: true })}
        ${row('Penalty', formatCurrency(transaction.penalty), { shaded: true })}
        ${row('Interest', formatCurrency(transaction.interest), {})}
        ${row('Principal', formatCurrency(transaction.principal), { shaded: true })}
        ${row('Additional equity / advance', formatCurrency(transaction.additionalEquityAmount), {})}
        ${row('Remaining balance', formatCurrency(transaction.remainingBalance), { bold: true, shaded: true })}
        ${row('Updated account total due', formatCurrency(transaction.totalAmountDue), { bold: true })}
        ${row('Payment status', transaction.paymentStatus, { bold: true, shaded: true })}
        ${transaction.soaNumber ? row('Updated SOA', `${transaction.soaNumber}${transaction.soaAsOfDate ? ` • As of ${formatDate(transaction.soaAsOfDate)}` : ''}`, { shaded: true }) : ''}
        ${transaction.checkNumber ? row('Check number', transaction.checkNumber) : ''}
        ${transaction.externalReference ? row('Bank / wallet reference', transaction.externalReference, { shaded: true }) : ''}
        ${transaction.postedByName ? row('Posted by', transaction.postedByName) : ''}
      </table>
    </div>` : '';

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
            <h3 style="color:#1e3a8a;margin:0;font-size:17px;">${isPostedPayment ? 'Updated SOA Summary' : 'Statement of Account Summary'}</h3>
            <p style="color:#64748b;margin:4px 0 0;font-size:12px;">SOA ${escapeHtml(summary.soaNumber)}${summary.asOfDate ? ` • As of ${escapeHtml(formatDate(summary.asOfDate))}` : ''}${summary.validUntil ? ` • Valid until ${escapeHtml(formatDate(summary.validUntil))}` : ''}</p>
          </div>
          <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:${summary.status === 'OVERDUE' ? '#fee2e2' : summary.status === 'SETTLED' ? '#d1fae5' : '#dbeafe'};color:${summary.status === 'OVERDUE' ? '#b91c1c' : summary.status === 'SETTLED' ? '#047857' : '#1d4ed8'};font-size:10px;font-weight:800;">${escapeHtml(summary.status || 'DUE')}</span>
        </div>
        ${projectReference ? `<p style="color:#475569;margin:0 0 10px;font-size:12px;"><strong>Project:</strong> ${escapeHtml(projectReference)}</p>` : ''}
        ${transactionTable}
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #dbeafe;border-radius:8px;overflow:hidden;">
          ${row('Total Contract Price', formatCurrency(summary.totalContractPrice), { shaded: true })}
          ${row('Less: Discount', formatCurrency(summary.discount))}
          ${row('Net Contract Price', formatCurrency(summary.netContractPrice), { shaded: true, bold: true })}
          ${row('Equity', formatCurrency(summary.equity))}
          ${isPostedPayment || !showDownpaymentTerm ? '' : row('Required Downpayment', formatCurrency(summary.requiredDownpayment), { shaded: true })}
          ${row('Total Equity', formatCurrency(summary.totalEquity), { bold: true })}
          ${row('Loanable Amount', formatCurrency(summary.loanableAmount), { shaded: true })}
          ${row('Total Payments Made', formatCurrency(summary.totalPaymentsMade))}
          ${row('Remaining Principal / Equity', formatCurrency(summary.remainingBalance), { shaded: true, bold: true })}
          ${isPostedPayment ? '' : row('Current Payment', `${summary.nextPaymentType} — ${formatCurrency(summary.currentPaymentDue)}`)}
          ${isPostedPayment ? '' : row('Current Payment Due Date', formatDate(summary.nextDueDate), { shaded: true })}
          ${row('Interest', formatCurrency(summary.interest))}
          ${row('Penalty', formatCurrency(summary.penalty), { shaded: true })}
          ${row('Reservation / Other Charges', formatCurrency(otherCharges))}
          ${row('Monthly Payment', formatCurrency(summary.monthlyPayment), { shaded: true })}
          ${row('Applicable Interest Rate', `${Number(summary.annualInterestRate).toFixed(2)}% annual`)}
          ${row('TOTAL AMOUNT DUE', formatCurrency(summary.totalAmountDue), { color: '#1d4ed8', bold: true, fontSize: '18px' })}
        </table>
        <p style="color:#475569;margin:12px 0 0;font-size:12px;line-height:1.5;">
          ${isPostedPayment
            ? 'The transaction above is the actual payment record selected from the IHC ledger. This email contains only the compact SOA summary; the complete detailed SOA and transaction history are available in your Client Dashboard.'
            : 'This email contains only the summarized SOA. The complete detailed SOA, including the full installment schedule and transaction history, is available in your Client Dashboard.'}
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
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:20px;">${isPostedPayment ? 'Payment Posted' : 'Payment Reminder'}</h2>
        <p style="color:#475569;margin:0 0 12px;">Dear <strong>${safeClientName}</strong>,</p>
        <p style="color:#475569;margin:0 0 16px;">${isPostedPayment ? 'This notification confirms the payment transaction recorded by the IHC billing office.' : 'This is a friendly reminder regarding your account with ' + safeCompanyName + '.'}</p>
        ${urgency}
        ${summaryTable}
        ${isPostedPayment
          ? '<p style="color:#475569;margin:0 0 8px;">Please retain this notification for your records. The amount and payment type above reflect the posted ledger transaction.</p>'
          : '<p style="color:#475569;margin:0 0 8px;">Please ensure your payment is made on or before the due date to avoid any late fees or penalties.</p>'}
        ${isPostedPayment ? '' : '<p style="color:#475569;margin:0 0 24px;">If you have already made this payment, please disregard this notice. Thank you!</p>'}
        <p style="text-align:center;margin:0 0 24px;">
          <a href="${escapeHtml(getClientAccessUrl(recipientEmail))}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Open Client Dashboard</a>
        </p>
        ${isPostedPayment ? '' : `<p style="text-align:center;margin:0 0 24px;">
          <a href="${escapeHtml(getAcknowledgementUrl(contractId, recipientEmail, installmentId))}" style="display:inline-block;background:#059669;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:6px;">Acknowledge Reminder</a>
        </p>`}
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
  const transaction = summary?.paymentTransaction || null;
  const subjectPrefix = transaction
    ? 'Payment Posted'
    : (summary ? 'Payment Reminder / SOA Summary' : 'Payment Reminder');
  const subjectPaymentType = transaction
    ? paymentTransactionLabel(transaction)
    : (summary?.nextPaymentType || 'Payment');
  const subject = transaction
    ? `${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(transaction.amount)}${transaction.orNumber ? ` — ${transaction.orNumber}` : ''}`
    : isOverdue
      ? `OVERDUE: ${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(amountDue)} for ${contractId} is past due`
      : `${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(amountDue)} for ${contractId} due in ${daysUntil} day(s)`;

  const html = buildReminderEmail(clientName, contractId, amountDue, dueDate, daysUntil, to, options.installmentId, summary);
  const notificationType = transaction ? 'payment_posted' : (options.reminderType || 'manual');

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
      reminderType: notificationType,
      dueDate: transaction ? transaction.dateCollected : dueDate
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
    reminderType: notificationType,
    dueDate: transaction ? transaction.dateCollected : dueDate
  });
  return { success: true, messageId: info.messageId };
}

function buildPaymentReceiptEmail(
  clientName, contractId, amount, paymentDate, method, orNumber, paymentId, recipientEmail,
  checkNumber, externalReference, paymentPurpose, paymentType = paymentPurpose,
  principalAmount = amount, additionalEquityAmount = 0, postedByName = '',
  installmentNumber = null, amountDue = amount, dueDate = '', interest = 0,
  penalty = 0, principal = principalAmount, remainingBalance = 0,
  totalAmountDue = amount, paymentStatus = 'POSTED', soaNumber = '', soaAsOfDate = ''
) {
  const referenceRow = (label, value) => value
    ? `<tr><td style="padding:10px 16px;font-weight:600;">${escapeHtml(label)}</td><td style="padding:10px 16px;">${escapeHtml(value)}</td></tr>`
    : '';
  const purpose = safeText(paymentType || paymentPurpose, 120).replace(/_/g, ' ');
  const appliedPrincipal = Math.min(safeNumber(amount), Math.max(0, safeNumber(principal)));
  const appliedAdditional = Math.min(safeNumber(amount), Math.max(0, safeNumber(additionalEquityAmount)));
  const typeLabel = installmentNumber == null || installmentNumber === ''
    ? (purpose || 'Scheduled Payment')
    : `${purpose || 'Installment'} #${installmentNumber}`;
  return `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#065f46,#059669);padding:28px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:22px;">Imperial Homes Corporation</h1>
        <p style="color:#d1fae5;margin:6px 0 0;font-size:13px;">Official Payment Receipt</p>
      </div>
      <div style="padding:32px;color:#475569;">
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:20px;">Payment received</h2>
        <p>Dear <strong>${escapeHtml(clientName)}</strong>,</p>
        <p>Thank you. We have recorded your payment and updated the contract ledger. This email serves as your payment receipt; the current SOA summary is available in your Client Dashboard.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border:1px solid #e2e8f0;">
          <tr><td style="padding:10px 16px;font-weight:600;">Contract ID</td><td style="padding:10px 16px;">${escapeHtml(contractId)}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Payment type</td><td style="padding:10px 16px;">${escapeHtml(typeLabel)}</td></tr>
          ${installmentNumber != null && installmentNumber !== '' ? `<tr><td style="padding:10px 16px;font-weight:600;">Installment no.</td><td style="padding:10px 16px;">${escapeHtml(installmentNumber)}</td></tr>` : ''}
          <tr><td style="padding:10px 16px;font-weight:600;">${installmentNumber != null && installmentNumber !== '' ? 'Installment Due Date' : 'Downpayment Due Date'}</td><td style="padding:10px 16px;">${escapeHtml(formatDate(dueDate || paymentDate))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">${installmentNumber != null && installmentNumber !== '' ? 'Installment Amount Due' : 'Downpayment Amount Due'}</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(amountDue))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">${installmentNumber != null && installmentNumber !== '' ? 'Installment Amount Paid' : 'Downpayment Amount Paid'}</td><td style="padding:10px 16px;color:#047857;font-size:17px;font-weight:700;">${escapeHtml(formatCurrency(amount))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Payment date</td><td style="padding:10px 16px;">${escapeHtml(formatDate(paymentDate))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Payment method</td><td style="padding:10px 16px;">${escapeHtml(method)}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">OR / reference number</td><td style="padding:10px 16px;">${escapeHtml(orNumber)}</td></tr>
          ${referenceRow('Check number', checkNumber)}
          ${referenceRow('Bank / wallet reference', externalReference)}
          <tr><td style="padding:10px 16px;font-weight:600;">Penalty</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(penalty))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Interest</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(interest))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Principal</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(appliedPrincipal))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Additional equity / advance</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(appliedAdditional))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Remaining balance</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(remainingBalance))}</td></tr>
          <tr><td style="padding:10px 16px;font-weight:600;">Updated account total due</td><td style="padding:10px 16px;">${escapeHtml(formatCurrency(totalAmountDue))}</td></tr>
           <tr><td style="padding:10px 16px;font-weight:600;">Payment status</td><td style="padding:10px 16px;">${escapeHtml(paymentStatus || 'POSTED')}</td></tr>
          ${soaNumber ? `<tr><td style="padding:10px 16px;font-weight:600;">Updated SOA</td><td style="padding:10px 16px;">${escapeHtml(soaNumber)}${soaAsOfDate ? ` • As of ${escapeHtml(formatDate(soaAsOfDate))}` : ''}</td></tr>` : ''}
          ${postedByName ? `<tr><td style="padding:10px 16px;font-weight:600;">Posted by</td><td style="padding:10px 16px;">${escapeHtml(postedByName)}</td></tr>` : ''}
        </table>
        <p style="margin-bottom:24px;">Receipt reference: <strong>PAY-${escapeHtml(paymentId)}</strong></p>
        <p style="text-align:center;"><a href="${escapeHtml(getClientAccessUrl(recipientEmail))}" style="display:inline-block;background:#059669;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:6px;">Open Client Dashboard / SOA Summary</a></p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 16px;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">This is a system-generated receipt from Imperial Homes Corporation.</p>
      </div>
    </div>`;
}

async function sendPaymentReceipt(
  to, clientName, contractId, amount, paymentDate, method, orNumber, paymentId,
  checkNumber, externalReference, paymentPurpose, paymentType = paymentPurpose,
  principalAmount = amount, additionalEquityAmount = 0, postedByName = '',
  installmentNumber = null, amountDue = amount, dueDate = '', interest = 0,
  penalty = 0, principal = principalAmount, remainingBalance = 0,
  totalAmountDue = amount, paymentStatus = 'POSTED', soaNumber = '', soaAsOfDate = ''
) {
  const receiptType = installmentNumber == null || installmentNumber === ''
    ? (paymentType || paymentPurpose || 'Payment')
    : `${paymentType || paymentPurpose || 'Installment'} #${installmentNumber}`;
  const subject = `Payment Receipt ${orNumber}: ${receiptType} — ${formatCurrency(amount)}`;
  const html = buildPaymentReceiptEmail(
    clientName, contractId, amount, paymentDate, method, orNumber, paymentId, to,
    checkNumber, externalReference, paymentPurpose, paymentType, principalAmount, additionalEquityAmount, postedByName,
    installmentNumber, amountDue, dueDate, interest, penalty, principal,
    remainingBalance, totalAmountDue, paymentStatus, soaNumber, soaAsOfDate
  );

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
  buildPaymentReceiptEmail,
  normalizeSoaSummary,
  logNotification,
  formatCurrency,
  formatDate,
  transporter,
  getAcknowledgementUrl
};
