# Daily payment-reminder automation

`backend/cronJobs.js` uses the application's live `ihc.contracts` table. It
checks each contract's first-payment due date (`start_date`), emails the
client when that date is within the next three calendar days, and stores the
result in `ihc.notifications_logs`. Each contract receives only one successful
`due_soon` email; overdue notices are limited to once per day.

When a clerk creates a contract whose first payment is exactly three days away,
`backend/db.php` also calls the reminder service immediately. This prevents a
contract created after the 8:00 AM job from being missed.

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
```

Apply the schema update once, then install packages and test a single run from
`backend/`:

```powershell
Get-Content .\migrations\002_reminder_automation_ihc.sql | C:\xampp\mysql\bin\mysql.exe -u root ihc
npm install
npm run reminders:run
```

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
