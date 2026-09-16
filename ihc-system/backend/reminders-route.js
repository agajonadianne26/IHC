const express = require('express');
const router = express.Router();
const { sendPaymentReminder } = require('./emailService');

router.post('/api/contracts/:id/send-reminder', async (req, res) => {
  const contractId = req.params.id;
  const { client, amount, dueDate, recipientEmail, reminderType } = req.body || {};

  if (!recipientEmail) {
    return res.json({ success: false, message: 'No email on file for this client — cannot send a reminder.' });
  }
  if (!client || amount == null || !dueDate) {
    return res.json({ success: false, message: 'Missing contract data — cannot build the reminder email.' });
  }

  const msPerDay = 1000 * 60 * 60 * 24;
  const daysUntil = Math.ceil((new Date(dueDate) - new Date()) / msPerDay);

  const result = await sendPaymentReminder(
    recipientEmail,
    client,
    contractId,
    amount,
    dueDate,
    daysUntil,
    { reminderType: reminderType || 'manual' }
  );

  if (result.success) {
    return res.json({ success: true, message: `Reminder sent to ${recipientEmail}.` });
  }
  return res.json({ success: false, message: result.error || 'Could not send the reminder email.' });
});

module.exports = router;
