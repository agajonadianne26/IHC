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
    soaSummary
  } = req.body || {};

  if (!client || amount == null || !dueDate) {
    return res.json({ success: false, message: 'Missing contract data — cannot build the reminder.' });
  }
  if (!recipientEmail && !recipientPhone) {
    return res.json({ success: false, message: 'No email or phone on file for this client — cannot send a reminder.' });
  }

  const msPerDay = 1000 * 60 * 60 * 24;
  const daysUntil = Math.ceil((new Date(dueDate) - new Date()) / msPerDay);
  const options = {
    reminderType: reminderType || 'manual',
    installmentId: installmentId || null,
    // The Clerk sends only the API's whitelisted compact summary. Automated
    // reminders may omit it and retain the smaller legacy reminder layout.
    soaSummary: soaSummary && typeof soaSummary === 'object' && !Array.isArray(soaSummary)
      ? soaSummary
      : null
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
    return res.json({ success: true, message: `Reminder sent to ${delivered.map((d) => d.target).join(' and ')}.` });
  }
  return res.json({ success: false, message: attempts[0].result.error || 'Could not send the reminder.' });
});

module.exports = router;
