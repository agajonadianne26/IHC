// One-off entry point for Windows Task Scheduler or another OS scheduler.
const path = require('path');
process.chdir(__dirname);
require('dotenv').config({ path: path.join(__dirname, '.env') });
process.env.RUN_REMINDERS_ONCE = 'true';

const db = require('./db');
const { runDailyReminders } = require('./cronJobs');

async function main() {
  try {
    await runDailyReminders();
  } catch (error) {
    console.error('[REMINDERS] Daily run failed:', error.message);
    process.exitCode = 1;
  } finally {
    await db.close();
  }
}

main();
