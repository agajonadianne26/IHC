// Node-side DB connection used by emailService.js (db.query(...)).
// This is separate from your PHP backend's PDO connection — same MySQL
// database, but a different driver on the Node side.
require('dotenv').config();
const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'ihc',
  waitForConnections: true,
  connectionLimit: 10
});

module.exports = {
  // mysql2/promise's execute() returns [rows, fields] — expose a thin
  // query() wrapper so emailService.js's `db.query(sql, params)` calls work.
  query: (sql, params) => pool.execute(sql, params)
};
