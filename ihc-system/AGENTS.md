# IHC System — Agent Notes

No build, no tests, no lint, no CI. Plain XAMPP (Apache + PHP + MySQL) + a Node email/reminder service.

## Run

- Frontend: static files, no bundler. Entry is `log in.html` (note the space — quote paths, URL-encode as `%20`):
  `http://localhost/ihc-system/log%20in.html`
- PHP APIs need XAMPP Apache + MySQL with DB `ihc` (credentials `root`/empty are hardcoded in each PHP file). Base: `http://localhost/ihc-system`
- Node service (from `backend/` only): `npm install` then `npm start` (port 3000). Config comes from `backend/.env` — never commit it, never print secrets. `server.js` prints MySQL/SMTP diagnosis on boot; trust that output.
- One-off reminder check (from `backend/`): `npm run reminders:run`

## Architecture: two halves share MySQL `ihc` but auth is fake

- `html/` + `js/` + `css/` = role dashboards (admin / clerk / client). Login in `js/log in.js` checks hardcoded `MOCK_USERS`, then `js/portal-store.js` (localStorage demo store, `IHC_USER` / `IHC_CLIENTS` / `IHC_PORTAL_*` keys). There is no server-side auth — don't "fix" login by adding backend auth without asking.
- `php/api_dashboard.php` (GET `?officerId=`) is the live clerk-dashboard endpoint. `php/api_dashboard1.php` and `php/api_insert.php` are dead legacy — nothing references them, and `api_dashboard1.php` requires a nonexistent `../db.php`. Leave them alone.
- `backend/db.php` is NOT a shared DB include — it is the POST-only New-Contract handler (405s on GET). Dashboard/payment PHP files connect with their own inline PDO; don't consolidate them into `require db.php`.

## PHP ↔ Node contract (don't break)

- Hardcoded bases: PHP at `http://localhost/ihc-system`, Node at `http://localhost:3000` (`127.0.0.1:3000` from PHP). `backend/db.php` → `POST /api/contracts/IHC-<id>/send-reminder`; `php/post-payment.php` → `POST /api/payments/receipt`. Both treat email as best-effort: ledger/contract writes commit first, a mail failure only flips `reminderQueued` / `receiptEmailSent` to false.
- `officer_id` is VARCHAR (can be `DEMO-001`) — never `(int)`-cast it. `payments.contract_id` stores the prefixed string (`CON-<id>`); `post-payment.php` extracts the trailing digits to verify against `contracts.id`.
- `docs/daily-reminder-automation.md` is the authoritative reminder runbook; `docs/reminder-email-prompt.md §6` is partly stale (it describes an `ihc_cms` schema and `001_init_schema.sql` flow the live code doesn't use — `cronJobs.js` queries live `ihc.contracts` + `notifications_logs`).

## Reminders: pick ONE scheduler

- Windows Task Scheduler → `run-reminders.js` daily 8:00 AM Asia/Manila (Start in: `backend/`, args: `run-reminders.js`), OR in-process cron via `ENABLE_IN_PROCESS_CRON=true` + long-running `npm start`. Never both — double sends.
- Dedup lives in `notifications_logs`: `due_soon` sent once ever per contract, `overdue` once per day. Schema needs `migrations/002_reminder_automation_ihc.sql` applied to `ihc` (`CREATE TABLE` in `create_notification_logs.sql` is the fallback for fresh DBs).
- `ACKNOWLEDGEMENT_SECRET` must be set (long random) or ack links throw; email `Open Client Dashboard` / ack URLs default to localhost — override `CLIENT_LOGIN_URL` / `CLIENT_DASHBOARD_URL` / `ACKNOWLEDGEMENT_URL` in `.env` when deployed.
- Ack signal is a transient modal only: clerk dashboard polls `GET /api/acknowledgments/recent?officerId=&since=` (local wall-clock `YYYY-MM-DD HH:mm:ss`, cursor in `localStorage`) every 5 min and pops one confirm modal per new `ack_email` row. No ack column/KPI/badges on the dashboard. SQL joining `notifications_logs.contract_id` to `contracts.id` needs explicit `COLLATE utf8mb4_general_ci` (mixed collations error at runtime).
