const express = require('express');
const router = express.Router();
const { sendPaymentReminder } = require('./emailService');
const { sendPaymentReminderSms } = require('./smsService');

router.post('/api/contracts/:id/send-reminder', async (req, res) => {
  const contractId = req.params.id;
  const {
    client,
    amount,
    dueDate,
    recipientEmail,
    recipientPhone,
    reminderType,
    installmentId,
    paymentId: requestedPaymentId,
    payment_id: snakePaymentId,
    notificationType,
    soaSummary
  } = req.body || {};
  const paymentId = requestedPaymentId ?? snakePaymentId;

  if (!client || amount == null || !dueDate) {
    return res.json({ success: false, message: 'Missing contract data — cannot build the reminder.' });
  }
  if (!recipientEmail && !recipientPhone) {
    return res.json({ success: false, message: 'No email or phone on file for this client — cannot send a reminder.' });
  }

  const msPerDay = 1000 * 60 * 60 * 24;
  const daysUntil = Math.ceil((new Date(dueDate) - new Date()) / msPerDay);
  // A posted-payment notification must carry the server-selected payment ID
  // and a matching compact transaction object. Ordinary reminders continue to
  // use the next unpaid schedule row.
  const compactSummary = soaSummary && typeof soaSummary === 'object' && !Array.isArray(soaSummary)
    ? soaSummary
    : null;
  const hasEmbeddedTransaction = compactSummary?.paymentTransaction?.source === 'POSTED_PAYMENT';
  const contractDigits = String(contractId || '').match(/(\d+)\s*$/)?.[1] || null;
  const summaryContractDigits = String(compactSummary?.contractReference || '').match(/(\d+)\s*$/)?.[1] || null;
  const summaryContractMatches = contractDigits && summaryContractDigits && contractDigits === summaryContractDigits;
  const embeddedPaymentId = Number(compactSummary?.paymentTransaction?.paymentId);
  const paymentIdMatches = paymentId != null
    && Number.isFinite(embeddedPaymentId)
    && Number(paymentId) === embeddedPaymentId;
  const postedTransaction = notificationType === 'payment_posted'
    && paymentIdMatches
    && hasEmbeddedTransaction
    && summaryContractMatches;
  if (hasEmbeddedTransaction && notificationType !== 'payment_posted') {
    return res.status(400).json({ success: false, message: 'Posted payment context must use the payment_posted notification type.' });
  }
  if (notificationType === 'payment_posted' && !postedTransaction) {
    return res.status(400).json({ success: false, message: 'The exact posted payment transaction is required for this notification.' });
  }
  if (paymentId != null && !postedTransaction) {
    return res.status(400).json({ success: false, message: 'A payment ID is only valid with an exact posted-payment notification.' });
  }
  if (postedTransaction && !recipientEmail) {
    return res.status(400).json({ success: false, message: 'A posted-payment notification requires a client email address.' });
  }

  const options = {
    reminderType: reminderType || (postedTransaction ? 'payment_posted' : 'manual'),
    installmentId: installmentId || null,
    paymentId: paymentId || null,
    // The Clerk sends only the API's whitelisted compact summary. Automated
    // reminders may omit it and retain the smaller legacy reminder layout.
    soaSummary: compactSummary
  };

  // Each channel is best-effort: an SMS failure must not cancel the email
  // (and vice versa). Success below means at least one channel delivered.
  const attempts = [];
  if (recipientEmail) {
    attempts.push({
      target: recipientEmail,
      result: await sendPaymentReminder(recipientEmail, client, contractId, amount, dueDate, daysUntil, options)
    });
  }
  if (recipientPhone) {
    attempts.push({
      target: recipientPhone,
      result: await sendPaymentReminderSms(recipientPhone, client, contractId, amount, dueDate, daysUntil, options)
    });
  }

  const delivered = attempts.filter((attempt) => attempt.result.success);
  if (delivered.length > 0) {
    return res.json({ success: true, message: `${postedTransaction ? 'Posted payment notification' : 'Reminder'} sent to ${delivered.map((d) => d.target).join(' and ')}.` });
  }
  return res.json({ success: false, message: attempts[0].result.error || 'Could not send the reminder.' });
});

module.exports = router;
