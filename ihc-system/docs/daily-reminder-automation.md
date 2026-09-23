# Daily payment-reminder automation

`backend/cronJobs.js` uses the application's live `ihc.contracts` table. It
checks each contract's first-payment due date (`start_date`), emails the
client and sends them an SMS text message when that date is within the next
three calendar days, and stores each result in `ihc.notifications_logs`.
Each contract receives only one successful `due_soon` email and one
successful `due_soon` SMS; overdue notices are limited to once per day
(email only for now).

SMS goes through Semaphore (`backend/smsService.js`), a Philippine SMS
gateway (semaphore.co) delivering to Globe/Smart/Sun/DITO at about
PHP 0.56 per text — sign up free, grab your API key, and note that a
message costs one credit per 160 characters (the reminder text is ASCII
and bills as two segments). Phone numbers come from
`contracts.cellphone_number` (validated to E.164, sent in the local
`09XXXXXXXXX` form Semaphore documents). Set the `SEMAPHORE_*` values below
to enable it — while they are blank the SMS channel is skipped and email
continues alone. Dedup is per channel, so a failed email never suppresses
the SMS (and the other way around).

When a clerk creates a contract whose first payment is exactly three days away,
`backend/db.php` also calls the reminder service immediately with the client's
email and phone number. This prevents a contract created after the 8:00 AM job
from being missed.

Set these values in `backend/.env` before running it:

```env
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=ihc
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your-smtp-user
SMTP_PASS=your-smtp-password
EMAIL_FROM="Imperial Homes <billing@example.com>"
ACKNOWLEDGEMENT_SECRET=replace-with-a-long-random-secret
REMINDER_TIMEZONE=Asia/Manila
# Semaphore SMS reminders (semaphore.co) — leave blank to disable SMS
SEMAPHORE_API_KEY=
SEMAPHORE_SENDER_NAME=
```

Apply the schema updates once, then install packages and test a single run from
`backend/`:

```powershell
Get-Content .\migrations\002_reminder_automation_ihc.sql | C:\xampp\mysql\bin\mysql.exe -u root ihc
Get-Content .\migrations\005_holding_reservation_fees_ihc.sql | C:\xampp\mysql\bin\mysql.exe -u root ihc
Get-Content .\migrations\006_holding_fees_upgrade_legacy.sql | C:\xampp\mysql\bin\mysql.exe -u root ihc
npm install
npm run reminders:run
```

Holding fee expiration is now part of the same scheduler (§5). `backend/cronJobs.js` runs `runHoldingFeeExpiration()` daily at 12:05 AM `Asia/Manila` (and lazily on every `GET` to `php/api_holding_reservation.php` / `php/api_admin.php` via `expireStaleHolds()`). Rows with `status IN ('PENDING','PAID') AND expiration_date < CURDATE()` become `EXPIRED`; with `business_rules.holding_fee.expire_makes_available=1` the `property_units` row flips `ON HOLD → AVAILABLE` (audited, never deleted). Holding expiring notices (`reminder_type='holding_expiring'`) are sent like payment reminders 2 days before expiry (email + SMS, per-channel dedup in `notifications_logs`).

Admin holds dashboard (`php/api_admin.php?action=overview` → `holdingSummary`/`expiringHolds`) and clerk holding fees pane (`GET /php/api_holding_reservation.php?action=dashboard_summary`) both show `Active Holds` / `Expiring Holds (7d)` / `Reserved Units` / `Pending Payments` and the `Expiring Holds` list sorted nearest-first. Business rules (`holding_fee.default_days=30`, `expire_makes_available`, `convert_on_reservation`, `refundable`, `allow_direct_reservation`) are editable via `POST /php/api_holding_reservation.php` `action=business_rules_update` (admin UI).

## Windows Task Scheduler (recommended for XAMPP on Windows)

Create a task with a daily 8:00 AM trigger. Its action must be:

- **Program/script:** the full path to `node.exe` (find it with `(Get-Command node).Source`).
- **Add arguments:** `run-reminders.js`
- **Start in:** `C:\xampp\htdocs\ihc-system\backend`

Enable **Run whether user is logged on or not** (Windows will ask for the
account password) and **Run task as soon as possible after a scheduled start
is missed**. The task runs the check then exits. Do not also schedule
`npm start` at 8:00 AM.

## Alternative: keep the Node service running

The Node service only handles HTTP requests by default. To make its built-in
scheduler own the daily job instead, set `ENABLE_IN_PROCESS_CRON=true` in
`backend/.env` and keep `npm start` running continuously through a service
manager. Do not combine this setting with the Task Scheduler option.
