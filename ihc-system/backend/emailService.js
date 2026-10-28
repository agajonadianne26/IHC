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
  const safeClientName = escapeHtml(summary?.clientName || safeText(clientName));
  const safeCompanyName = escapeHtml(summary?.companyName || 'Imperial Homes Corporation');
  const displayContract = summary?.contractReference || safeText(contractId);
  const currentAmount = summary ? summary.currentPaymentDue : safeNumber(amountDue);
  const currentDueDate = summary?.nextDueDate || dueDate;
  const monthlyPayment = summary ? summary.monthlyPayment : 0;
  const projectReference = summary
    ? [summary.projectName, summary.phase, summary.block ? `Block ${summary.block}` : '', summary.lot ? `Lot ${summary.lot}` : ''].filter(Boolean).join(' • ')
    : '';

  // Clear, soft status indicators (tinted pills — no heavy color blocks).
  const isOverdue = !isPostedPayment && numericDays < 0;
  const status = isPostedPayment ? 'posted' : isOverdue ? 'overdue' : 'upcoming';
  const statusPill = {
    upcoming: { bg: '#fff7ed', text: '#b45309', border: '#fed7aa', label: numericDays > 0 ? `Due in ${numericDays} day(s)` : 'Due today' },
    overdue: { bg: '#fef2f2', text: '#b91c1c', border: '#fecaca', label: `${Math.abs(numericDays)} day(s) overdue` },
    posted: { bg: '#ecfdf5', text: '#047857', border: '#a7f3d0', label: 'Payment received' }
  }[status];
  const amountColor = isOverdue ? '#b91c1c' : '#0f172a';
  const dueDateLabel = isOverdue ? 'Overdue since' : 'Due date';

  // Simple two-column receipt row (posted-payment variant only).
  const rrow = (label, value, shaded) => `
    <tr${shaded ? ' style="background:#f8fafc;"' : ''}>
      <td style="padding:10px 14px;color:#64748b;font-weight:600;border-bottom:1px solid #f1f5f9;width:52%;">${escapeHtml(label)}</td>
      <td style="padding:10px 14px;color:#0f172a;font-weight:700;text-align:right;border-bottom:1px solid #f1f5f9;">${escapeHtml(value)}</td>
    </tr>`;

  // Focal point: the one payment the client needs to act on, in large type.
  const focalAmount = isPostedPayment ? transaction.amountPaid : currentAmount;
  const focalLabel = isPostedPayment ? 'Amount paid' : 'Current payment due';
  const focalColor = isPostedPayment ? '#047857' : amountColor;
  const heroCard = `
    <div style="margin:0 0 18px;padding:26px 24px;border:1px solid #e2e8f0;border-radius:12px;background:#ffffff;text-align:center;">
      <p style="margin:0;color:#64748b;font-size:12px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;">${escapeHtml(focalLabel)}</p>
      <p style="margin:8px 0 0;color:${focalColor};font-size:40px;font-weight:800;line-height:1.05;">${escapeHtml(formatCurrency(focalAmount))}</p>
      <p style="margin:18px 0 0;">
        <span style="display:inline-block;padding:10px 20px;border-radius:10px;background:${statusPill.bg};color:${statusPill.text};border:1px solid ${statusPill.border};font-size:15px;font-weight:700;">${escapeHtml(isPostedPayment ? `Paid on ${formatDate(transaction.paymentDate)}` : `${dueDateLabel}: ${formatDate(currentDueDate)}`)}</span>
      </p>
      <p style="margin:12px 0 0;color:#64748b;font-size:13px;font-weight:600;">${escapeHtml(statusPill.label)}</p>
      ${!isPostedPayment && monthlyPayment > 0 ? `
      <p style="margin:20px 0 0;padding-top:16px;border-top:1px solid #eef2f7;color:#64748b;font-size:13px;font-weight:600;">Monthly payment
        <strong style="color:#0f172a;font-size:17px;margin-left:6px;">${escapeHtml(formatCurrency(monthlyPayment))}</strong>
      </p>` : ''}
    </div>`;

  // Slim receipt for the posted-payment variant only — kept minimal on purpose.
  const receipt = isPostedPayment ? `
    <div style="margin:0 0 18px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;background:#ffffff;">
        ${rrow('OR / Reference no.', transaction.orNumber || transaction.invoiceNumber || transaction.transactionReference || `PAY-${transaction.paymentId}`)}
        ${transaction.paymentMethod ? rrow('Payment method', transaction.paymentMethod, true) : ''}
        ${rrow('Remaining balance', formatCurrency(transaction.remainingBalance), !transaction.paymentMethod)}
      </table>
    </div>` : '';

  return `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;background:#f4f6f9;border:1px solid #e5e9f0;border-radius:14px;overflow:hidden;">
      <div style="background:#1f2a44;padding:26px 32px;text-align:center;border-bottom:4px solid #f7941e;">
        <h1 style="color:#ffffff;margin:0;font-size:22px;letter-spacing:0.2px;">${safeCompanyName}</h1>
        <p style="color:#c3ccdd;margin:6px 0 0;font-size:13px;">Billing &amp; Accounts Receivable</p>
        ${summary && summary.companyContact ? `<p style="color:#94a1bd;margin:3px 0 0;font-size:11px;">${escapeHtml(summary.companyContact)}</p>` : ''}
      </div>
      <div style="padding:32px;">
        <h2 style="color:#1f2937;margin:0 0 16px;font-size:20px;">${isPostedPayment ? 'Payment Received' : 'Payment Reminder'}</h2>
        <p style="color:#4b5563;margin:0 0 12px;">Dear <strong>${safeClientName}</strong>,</p>
        <p style="color:#4b5563;margin:0 0 20px;">${isPostedPayment ? 'This confirms the payment recorded by the IHC billing office. Thank you!' : 'This is a friendly reminder regarding your account with ' + safeCompanyName + '.'}</p>
        ${heroCard}
        ${receipt}
        ${projectReference ? `<p style="color:#6b7280;margin:0 0 20px;font-size:12px;"><strong>Project:</strong> ${escapeHtml(projectReference)}${displayContract ? ` &nbsp;•&nbsp; Contract ${escapeHtml(displayContract)}` : ''}</p>` : (displayContract ? `<p style="color:#6b7280;margin:0 0 20px;font-size:12px;">Contract ${escapeHtml(displayContract)}</p>` : '')}
        <p style="text-align:center;margin:4px 0 8px;">
          <a href="${escapeHtml(getClientAccessUrl(recipientEmail))}" style="display:inline-block;background:#f7941e;color:#1f2a44;text-decoration:none;font-weight:700;font-size:15px;padding:14px 30px;border-radius:8px;">View Statement &amp; Pay Online</a>
        </p>
        <p style="text-align:center;margin:0 0 4px;font-size:12px;color:#9aa3b2;">Full statement, schedule &amp; history are in your client dashboard.</p>
        ${isPostedPayment ? '' : `<p style="text-align:center;margin:18px 0 0;font-size:13px;">
          <a href="${escapeHtml(getAcknowledgementUrl(contractId, recipientEmail, installmentId))}" style="color:#6b7280;text-decoration:underline;">I have already paid — acknowledge</a>
        </p>`}
        <hr style="border:none;border-top:1px solid #e5e9f0;margin:24px 0 16px;">
        <p style="color:#9aa3b2;font-size:12px;margin:0;text-align:center;">This is a system-generated email from ${safeCompanyName}. For concerns, contact our Billing Department.</p>
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
      ? (Math.abs(daysUntil) >= 30
        ? `FINAL NOTICE — OVERDUE: ${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(amountDue)} for ${contractId} is past due`
        : Math.abs(daysUntil) >= 8
          ? `SECOND NOTICE — OVERDUE: ${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(amountDue)} for ${contractId} is past due`
          : `OVERDUE: ${subjectPrefix}: ${subjectPaymentType} — ${formatCurrency(amountDue)} for ${contractId} is past due`)
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
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#fbf5ed;border:1px solid #e7dccd;border-radius:12px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#065f46,#059669);padding:28px 32px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:22px;">Imperial Homes Corporation</h1>
        <p style="color:#d1fae5;margin:6px 0 0;font-size:13px;">Official Payment Receipt</p>
      </div>
      <div style="padding:32px;color:#443a2f;">
        <h2 style="color:#1e1710;margin:0 0 16px;font-size:20px;">Payment received</h2>
        <p>Dear <strong>${escapeHtml(clientName)}</strong>,</p>
        <p>Thank you. We have recorded your payment and updated the contract ledger. This email serves as your payment receipt; the current SOA summary is available in your Client Dashboard.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border:1px solid #e7dccd;">
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
        <hr style="border:none;border-top:1px solid #e7dccd;margin:24px 0 16px;">
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
