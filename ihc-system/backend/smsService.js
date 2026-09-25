const db = require('./db');
const { formatCurrency, formatDate, logNotification } = require('./emailService');
require('dotenv').config();

// Semaphore (semaphore.co, by Sombra Inc.) SMS companion to emailService.js.
// Philippine SMS gateway delivering to Globe/Smart/Sun/DITO at ~PHP 0.56/text.
// Credentials (backend/.env):
//   SEMAPHORE_API_KEY     — required, from your semaphore.co account
//   SEMAPHORE_SENDER_NAME — optional registered Sender ID (max 11 chars);
//                           Semaphore rejects the send if neither this nor a
//                           default Sender Name is set on the account.
// When the key is missing the SMS channel is skipped entirely, so an
// email-only deployment keeps working unchanged.
//
// API contract (https://semaphore.co/docs): form-encoded POST to
// api.semaphore.co/api/v4/messages with apikey, number, message (+ optional
// sendername). Reply is a JSON array with a status per message
// (Queued/Pending = accepted for delivery, Failed = rejected by the network).
// Messages must not start with the word "TEST" (silently dropped).

const SEMAPHORE_URL = 'https://api.semaphore.co/api/v4/messages';

function isSmsConfigured() {
  return Boolean(process.env.SEMAPHORE_API_KEY);
}

// The clerk form stores PH mobiles as 09XXXXXXXXX. Validate to E.164 first
// so malformed seed numbers are rejected here instead of reaching the API.
function normalizePhoneNumber(raw) {
  if (!raw) return null;
  const trimmed = String(raw).trim();
  const digits = trimmed.replace(/\D/g, '');
  if (!digits) return null;
  if (digits.startsWith('0')) return /^09\d{9}$/.test(digits) ? '+63' + digits.slice(1) : null;
  if (/^63\d{10}$/.test(digits)) return '+' + digits;
  if (trimmed.startsWith('+')) return /^\+\d{10,15}$/.test(trimmed) ? trimmed : null;
  if (/^\d{10,15}$/.test(digits)) return '+' + digits;
  return null;
}

// Semaphore's documented examples all use the local 09XXXXXXXXX form.
function toLocalNumber(e164) {
  return e164.startsWith('+63') ? '0' + e164.slice(3) : e164;
}

// Kept ASCII on purpose: the email uses the peso sign, but a non-ASCII char
// would switch the SMS to UCS-2 encoding and triple the segment count
// (each segment is billed). "PHP" keeps this to two billed segments.
function smsAmount(amountDue) {
  return 'PHP ' + Number(amountDue).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function buildReminderSms(clientName, contractId, amountDue, dueDate, daysUntil) {
  const name = String(clientName || '').trim() || 'Client';
  const timing = daysUntil < 0
    ? `was due on ${formatDate(dueDate)} and is now overdue`
    : `is due on ${formatDate(dueDate)} (in ${daysUntil} day${daysUntil === 1 ? '' : 's'})`;
  return `Hi ${name}, Imperial Homes Corporation reminder: your payment of ${smsAmount(amountDue)} for contract ${contractId} ${timing}. Please make your payment on time to avoid late charges. Thank you!`;
}

function buildPostedPaymentSms(clientName, contractId, transaction) {
  const name = String(clientName || '').trim() || 'Client';
  const baseType = String(transaction?.paymentType || 'Scheduled Payment').trim();
  const type = transaction?.installmentNumber != null
    ? `${baseType} #${transaction.installmentNumber}`
    : baseType;
  const amount = Number(transaction?.amount) || 0;
  const paymentDate = transaction?.dateCollected || '';
  const orNumber = String(transaction?.orNumber || '').trim();
  const reference = orNumber ? ` Reference ${orNumber}` : '';
  return `Hi ${name}, Imperial Homes Corporation: your ${type} payment of ${smsAmount(amount)} for contract ${contractId} was posted on ${formatDate(paymentDate)}${reference}. Thank you!`;
}

// Mirrors sendPaymentReminder in emailService.js: the message goes out
// first, and the notifications_logs row (channel='sms') is best-effort —
// a logging problem never flips a delivered SMS back to a failure.
async function sendPaymentReminderSms(to, clientName, contractId, amountDue, dueDate, daysUntil, options = {}) {
  const transaction = options.soaSummary?.paymentTransaction || null;
  const message = transaction
    ? buildPostedPaymentSms(clientName, contractId, transaction)
    : buildReminderSms(clientName, contractId, amountDue, dueDate, daysUntil);
  // notifications_logs.subject is VARCHAR(255).
  const subject = message.slice(0, 255);
  const logFields = {
    contractId,
    subject,
    reminderType: transaction ? 'payment_posted' : options.reminderType,
    dueDate: transaction ? transaction.dateCollected : dueDate,
    channel: 'sms'
  };

  if (!isSmsConfigured()) {
    // Nothing was attempted, so nothing is logged — this keeps both the
    // audit trail and the once-per-contract dedup (status='sent') clean.
    console.log(`SMS reminder skipped for ${contractId} — SEMAPHORE_API_KEY is not set in .env.`);
    return { success: false, skipped: true, error: 'SMS is not configured (SEMAPHORE_API_KEY missing in .env).' };
  }

  const phone = normalizePhoneNumber(to);
  if (!phone) {
    const error = `Invalid cellphone number: ${to || '(empty)'}`;
    console.error(`Failed to send SMS for ${contractId}: ${error}`);
    await logNotification({ ...logFields, to: String(to || ''), status: 'failed', errorMessage: error });
    return { success: false, error };
  }

  const number = toLocalNumber(phone);
  const form = new URLSearchParams({
    apikey: process.env.SEMAPHORE_API_KEY,
    number,
    message
  });
  if (process.env.SEMAPHORE_SENDER_NAME) {
    form.set('sendername', process.env.SEMAPHORE_SENDER_NAME);
  }

  let response;
  let bodyText;
  try {
    response = await fetch(SEMAPHORE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: form.toString()
    });
    bodyText = await response.text();
  } catch (error) {
    // Network-level failure (Semaphore unreachable, DNS, timeout).
    console.error(`Failed to send SMS to ${number}:`, error.message);
    await logNotification({ ...logFields, to: number, status: 'failed', errorMessage: error.message });
    return { success: false, error: error.message };
  }

  let body;
  try {
    body = JSON.parse(bodyText);
  } catch (e) {
    body = null;
  }
  const entry = Array.isArray(body) ? body[0] : body;
  const status = entry && typeof entry.status === 'string' ? entry.status : '';
  const rejection =
    (entry && entry.error) ||
    status.toLowerCase() === 'failed' ||
    (entry && !entry.message_id) ||
    !response.ok ||
    !body;

  if (rejection) {
    const error =
      (entry && (entry.error || entry.message)) ||
      `Semaphore rejected the send (HTTP ${response.status}${status ? `, status ${status}` : ''}): ${bodyText.slice(0, 200)}`;
    console.error(`Failed to send SMS to ${number}: ${error}`);
    await logNotification({ ...logFields, to: number, status: 'failed', errorMessage: String(error) });
    return { success: false, error: String(error) };
  }

  // Queued/Pending means Semaphore accepted it for delivery.
  console.log(`SMS accepted by Semaphore for ${number}: message_id ${entry.message_id} (${status || 'Queued'})`);
  await logNotification({ ...logFields, to: number, status: 'sent' });
  return { success: true, messageId: entry.message_id, status };
}

module.exports = {
  sendPaymentReminderSms,
  buildReminderSms,
  buildPostedPaymentSms,
  normalizePhoneNumber,
  isSmsConfigured
};
