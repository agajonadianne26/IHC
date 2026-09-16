# Payment Reminder Email — Prompt Design

This is meant to be called from your backend (the Node service at
`http://localhost:3000` that `sendReminder()` already POSTs to), using
whichever LLM API you have a key for (Anthropic, OpenAI, etc.). The model's
only job is to turn already-correct SOA data into well-written prose — it
must never be trusted to supply or adjust any number itself.

## 6. Automated three-day reminders

The Node service runs `cronJobs.js` at 8:00 AM Asia/Manila each day. It sends
one email for each pending installment whose `due_date` is exactly three days
away. It also sends one overdue notice per pending installment per day. Sent
notifications are stored in `notification_logs` with the installment ID so a
service restart does not resend the same day's message.

Before starting the service, apply `backend/migrations/001_init_schema.sql`.
It creates the `ihc_cms` database; set `DB_NAME=ihc_cms` in `backend/.env`
before starting the service. That migration defines the
`clients`, `contracts`, `installment_schedules`, and `notification_logs`
tables required by the scheduler. The legacy `database/ihc.sql` dump uses a
different contract schema and does not contain installment schedules, so it
cannot drive automated due-date reminders without a separate data migration.

Start the service from `backend/` with `npm start`. Confirm the startup log
shows both a successful MySQL connection and successful SMTP verification.
Reminder emails include an `Open Client Dashboard` button. Configure its login
destination with `CLIENT_LOGIN_URL` in `backend/.env` when the application is
deployed to a host other than the local XAMPP URL. The client's email is
prefilled and the dashboard opens after normal password authentication; the
button does not expose or embed passwords.

They also include an `Acknowledge Reminder` button. It opens a signed callback
on the Node service, verifies the client and contract, sends the assigned
officer an email immediately, records the acknowledgment, and redirects the
client to the dashboard. Set `ACKNOWLEDGEMENT_SECRET` to a long random value
before deploying; repeated clicks for the same reminder do not send duplicate
clerk notifications.
## 1. SOA data payload (extracted client-side, same fields as `printSOA()`)

```js
{
  scenario:      "overdue" | "due_soon",   // derived from row.status
  client:        "Juan Dela Cruz",
  contractId:    "CON-14",
  property:      "Block 1 Lot 2, Sample Subdivision",
  paymentType:   "Monthly Amortization #3",
  amountDue:     "₱12,500.00",             // pre-formatted, do not send raw floats
  dueDate:       "Sep 5, 2026",
  daysCount:     6,                        // days overdue, or days until due
  recipientEmail:"juan@gmail.com"
}
```

Scenario mapping from `row.status`:
- `row.status === 'overdue'` → `"overdue"`, `daysCount = daysDiff(TODAY, row.dueDate)`
- anything else (`due-soon` / `current`) → `"due_soon"`, `daysCount = daysDiff(row.dueDate, TODAY)`

## 2. System prompt

```
You are the billing communications assistant for Imperial Homes Corporation
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
object.
```

## 3. User (data) prompt — filled per send

```
Scenario: {{scenario}}
Client name: {{client}}
Contract / IHC Ref: {{contractId}}
Property: {{property}}
Payment type: {{paymentType}}
Amount due: {{amountDue}}
Due date: {{dueDate}}
Days {{scenario === "overdue" ? "overdue" : "until due"}}: {{daysCount}}
Recipient email: {{recipientEmail}}

Write the reminder email now, following the system instructions.
```

## 4. Expected model output (example — overdue scenario)

```json
{
  "subject": "Payment Reminder — CON-14 is 6 Days Overdue",
  "greeting": "Dear Juan Dela Cruz,",
  "body": "Our records show that your Monthly Amortization #3 payment of ₱12,500.00 for Block 1 Lot 2, Sample Subdivision was due on Sep 5, 2026, and is now 6 days overdue. Please settle this amount at your earliest convenience to keep your account in good standing.",
  "closing": "Thank you for your prompt attention to this matter.\n\nImperial Homes Corporation — Billing Department"
}
```

## 5. Guardrail note

Because a reminder email states a real amount owed, treat this the same
way you'd treat any other billing document: validate before sending.
A cheap, effective check your backend can run before dispatching mail —
confirm the amount and date strings the model echoed back match the
values you sent it verbatim; if they don't match, fall back to a fixed
template instead of the model's text, and log the mismatch.
