const express = require('express');
const router = express.Router();
const { sendPaymentReceipt } = require('./emailService');

router.post('/api/payments/receipt', async (req, res) => {
  const {
    client, recipientEmail, contractId, amount, paymentDate, method, orNumber, paymentId,
    checkNumber, externalReference, paymentPurpose, paymentType,
    installmentNumber, amountDue, dueDate, interest, penalty, principalAmount, principal,
    remainingBalance, totalAmountDue, paymentStatus, soaNumber, soaAsOfDate,
    additionalEquityAmount, postedByName
  } = req.body || {};
  if (!client || !recipientEmail || !contractId || amount == null || !paymentDate || !method || !orNumber || !paymentId) {
    return res.status(400).json({ success: false, message: 'Missing payment receipt details.' });
  }

  const result = await sendPaymentReceipt(
    recipientEmail, client, contractId, amount, paymentDate, method, orNumber, paymentId,
    checkNumber, externalReference, paymentPurpose, paymentType,
    principalAmount, additionalEquityAmount, postedByName,
    installmentNumber, amountDue, dueDate, interest, penalty, principal,
    remainingBalance, totalAmountDue, paymentStatus, soaNumber, soaAsOfDate
  );
  if (!result.success) return res.status(502).json({ success: false, message: result.error || 'Could not send payment receipt.' });
  return res.json({ success: true, message: `Payment receipt sent to ${recipientEmail}.` });
});

module.exports = router;
