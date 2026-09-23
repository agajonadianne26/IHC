// Entry point for the Node reminder/email service (port 3000).
// Run with: node server.js
// Requires: npm install express cors dotenv mysql2 nodemailer
//
// This version prints an explicit diagnosis for the most common reasons
// this service fails to come up, instead of crashing silently or leaving
// you with a bare stack trace.

require('dotenv').config();

console.log('--- Reminder service starting ---');

// 1) Can we even load the required packages?
let express, cors;
try {
  express = require('express');
  cors = require('cors');
} catch (err) {
  console.error('\n[STARTUP FAILED] A required package is not installed.');
  console.error('Run this in the SAME folder as this file:');
  console.error('  npm install express cors dotenv mysql2 nodemailer\n');
  console.error('Original error:', err.message);
  process.exit(1);
}

let db, emailService;
try {
  db = require('./db');
  emailService = require('./emailService');
} catch (err) {
  console.error('\n[STARTUP FAILED] Could not load db.js or emailService.js.');
  console.error('Check that both files are in this same folder, and that');
  console.error('.env exists here with DB_HOST/DB_USER/DB_PASS/DB_NAME and');
  console.error('SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS/EMAIL_FROM set.\n');
  console.error('Original error:', err.message);
  process.exit(1);
}

const app = express();
app.use(cors());
app.use(express.json());
app.use(require('./reminders-route'));
app.use(require('./payment-receipt-route'));
app.use(require('./acknowledgement-route'));
// Daily dispatch is owned by Windows Task Scheduler (`run-reminders.js`).
// Set ENABLE_IN_PROCESS_CRON=true only when a process manager keeps this
// service alive continuously and no OS-level reminder task is configured.
if (process.env.ENABLE_IN_PROCESS_CRON === 'true') {
  require('./cronJobs');
}
app.get('/', (req, res) => res.json({ status: 'ok', service: 'ihc-reminder-service' }));

const PORT = process.env.PORT || 3000;

app.listen(PORT, async () => {
  console.log(`[OK] HTTP server listening on http://localhost:${PORT}`);

  // 2) Can we reach MySQL?
  try {
    await db.query('SELECT 1');
    console.log('[OK] MySQL connection succeeded.');
  } catch (err) {
    console.error('[WARNING] MySQL connection FAILED:', err.message);
    console.error('  -> Check DB_HOST/DB_USER/DB_PASS/DB_NAME in .env, and that MySQL is running.');
  }

  // 3) Can we reach the SMTP server with these credentials?
  try {
    await emailService.transporter.verify();
    console.log('[OK] SMTP connection verified — this account can send mail.');
  } catch (err) {
    console.error('[WARNING] SMTP verification FAILED:', err.message);
    console.error('  -> Check SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS in .env.');
  }

  // 4) Is the SMS (Semaphore) channel configured?
  try {
    const { isSmsConfigured } = require('./smsService');
    if (isSmsConfigured()) {
      console.log('[OK] Semaphore API key found — SMS reminders enabled.');
    } else {
      console.log('[WARNING] SMS reminders DISABLED — set SEMAPHORE_API_KEY (and SEMAPHORE_SENDER_NAME if your account has no default Sender Name) in .env to enable them. Email reminders are unaffected.');
    }
  } catch (err) {
    console.error('[WARNING] Could not load smsService.js:', err.message);
  }

  console.log('--- Startup checks complete ---');
});

// Surface anything that would otherwise crash the process silently.
process.on('unhandledRejection', (err) => console.error('[UNHANDLED REJECTION]', err));
process.on('uncaughtException', (err) => console.error('[UNCAUGHT EXCEPTION]', err));
