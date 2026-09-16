// Example implementation for: POST /api/contracts/:id/send-reminder
// (the Node service your dashboard already calls on http://localhost:3000)
//
// Adjust the two marked sections — the LLM call and the mail transport —
// to match whichever provider/library you're actually using.

const express = require('express');
const router = express.Router();

const SYSTEM_PROMPT = `You are the billing communications assistant for Imperial Homes Corporation
(IHC). Write a short, professional payment reminder email to a real-estate
installment client, using ONLY the account data you are given in the user
message. Every name, amount, date, and ID in your email must be copied
exactly from that data — never invent, round, estimate, or recompute any
figure. If a field is missing, omit the sentence that would have used it
rather than guessing a value.

Two possible scenarios:
- "due_soon": the payment is upcoming, not yet late. Tone: friendly,
  informative, no pressure. Mention the number of days until due.
- "overdue": the due date has passed. Tone: firm but respectful — state
  the number of days overdue and ask for prompt settlement — never use
  threatening or legal language.

Output strict JSON only, with exactly these keys: "subject", "greeting",
"body", "closing". No markdown, no code fences, no text outside the JSON
object.`;

function buildUserPrompt(soa) {
  const daysLabel = soa.scenario === 'overdue' ? 'Days overdue' : 'Days until due';
  return [
    `Scenario: ${soa.scenario}`,
    `Client name: ${soa.client}`,
    `Contract / IHC Ref: ${soa.contractId}`,
    `Property: ${soa.property}`,
    `Payment type: ${soa.paymentType}`,
    `Amount due: ${soa.amountDue}`,
    `Due date: ${soa.dueDate}`,
    `${daysLabel}: ${soa.daysCount}`,
    `Recipient email: ${soa.recipientEmail}`,
    '',
    'Write the reminder email now, following the system instructions.'
  ].join('\n');
}

router.post('/api/contracts/:id/send-reminder', async (req, res) => {
  const soa = req.body; // { scenario, client, contractId, property, paymentType, amountDue, dueDate, daysCount, recipientEmail }

  if (!soa || !soa.recipientEmail) {
    return res.json({ success: false, message: 'Missing client email on file — cannot send a reminder.' });
  }

  try {
    // ---- 1) CALL YOUR LLM PROVIDER HERE -----------------------------------
    // Below assumes the Anthropic Messages API; swap for OpenAI/etc. as needed.
    const llmRes = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': process.env.ANTHROPIC_API_KEY, // never expose this client-side
        'anthropic-version': '2023-06-01'
      },
      body: JSON.stringify({
        model: 'claude-sonnet-4-6',
        max_tokens: 500,
        system: SYSTEM_PROMPT,
        messages: [{ role: 'user', content: buildUserPrompt(soa) }]
      })
    });
    const llmData = await llmRes.json();
    const raw = (llmData.content || []).map(b => b.text || '').join('');
    const email = JSON.parse(raw.replace(/```json|```/g, '').trim());

    // ---- Guardrail: the model must not have drifted on the numbers --------
    if (!email.body.includes(soa.amountDue) || !email.body.includes(soa.dueDate)) {
      throw new Error('Generated email did not echo the exact amount/date — refusing to send.');
    }

    // ---- 2) SEND THE EMAIL HERE --------------------------------------------
    // e.g. with nodemailer:
    // await mailer.sendMail({
    //   to: soa.recipientEmail,
    //   subject: email.subject,
    //   text: `${email.greeting}\n\n${email.body}\n\n${email.closing}`
    // });

    return res.json({ success: true, message: `Reminder sent to ${soa.recipientEmail}.` });
  } catch (err) {
    console.error('send-reminder error:', err);
    return res.json({ success: false, message: 'Could not generate or send the reminder email.' });
  }
});

module.exports = router;
