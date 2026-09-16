// Entry point for the Node reminder/email service (port 3000).
// Run with: node server.js
// Requires: npm install express cors dotenv mysql2 nodemailer

require('dotenv').config();
const express = require('express');
const cors = require('cors');

const remindersRouter = require('./reminders-route');

const app = express();

// Your dashboard HTML is served from a different origin (PHP on localhost,
// this service on localhost:3000) — without CORS enabled here, the
// browser blocks every fetch() to this server before it even reaches your
// route, and the dashboard would just show "Server unreachable."
app.use(cors());
app.use(express.json());

app.use(remindersRouter);

app.get('/', (req, res) => res.json({ status: 'ok', service: 'ihc-reminder-service' }));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Reminder service listening on http://localhost:${PORT}`);
});
