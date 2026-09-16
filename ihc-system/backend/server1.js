const express = require('express');
const cors = require('cors');
const dns = require('dns').promises;
const db = require('./db');
const { sendPaymentReminder } = require('./emailService');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());

// 1. GET OFFICER DASHBOARD METRICS & ASSIGNED CLIENTS
app.get('/api/officers/:id/dashboard', async (req, res) => {
  const officerId = req.params.id;

  try {
    const [officerRows] = await db.query(
      'SELECT id, name, role, email FROM officers WHERE id = ?',
      [officerId]
    );

    if (officerRows.length === 0) {
      return res.status(404).json({ success: false, message: 'Officer not found' });
    }

    const [clientContracts] = await db.query(
      `SELECT 
        c.id AS contract_id,
        CONCAT('IHC-2026-', LPAD(c.id, 3, '0')) AS account_code,
        cl.full_name AS name,
        cl.email,
        cl.phone,
        c.total_contract_price AS tcp,
        c.downpayment_paid AS downpayment,
        c.remaining_balance AS outstanding,
        c.installment_terms AS terms,
        COALESCE(SUM(CASE WHEN s.status = 'Cleared & Posted' THEN s.amount_due ELSE 0 END), 0) AS cleared_installments,
        next_s.installment_number AS next_installment_no,
        next_s.due_date AS next_due_date,
        next_s.amount_due AS next_amount,
        next_s.status AS next_status
       FROM contracts c
       JOIN clients cl ON c.client_id = cl.id
       LEFT JOIN installment_schedules s ON c.id = s.contract_id
       LEFT JOIN (
         SELECT ps.contract_id, ps.installment_number, ps.due_date, ps.amount_due, ps.status
         FROM installment_schedules ps
         JOIN (
           SELECT contract_id, MIN(due_date) AS min_due
           FROM installment_schedules
           WHERE status = 'Pending Payment'
           GROUP BY contract_id
         ) m ON ps.contract_id = m.contract_id AND ps.due_date = m.min_due AND ps.status = 'Pending Payment'
       ) next_s ON c.id = next_s.contract_id
       WHERE c.officer_id = ?
       GROUP BY c.id, cl.full_name, cl.email, cl.phone, c.total_contract_price,
                c.downpayment_paid, c.remaining_balance, c.installment_terms,
                next_s.installment_number, next_s.due_date, next_s.amount_due, next_s.status`,
      [officerId]
    );

    let totalTCP = 0;
    let totalCollected = 0;

    const formattedClients = clientContracts.map((row) => {
      const collected = parseFloat(row.downpayment) + parseFloat(row.cleared_installments);
      const tcp = parseFloat(row.tcp);
      const outstanding = parseFloat(row.outstanding);

      totalTCP += tcp;
      totalCollected += collected;

      return {
        contractId: row.contract_id,
        accountCode: row.account_code,
        name: row.name,
        email: row.email,
        phone: row.phone,
        tcp: tcp,
        collected: collected,
        outstanding: outstanding,
        terms: row.terms,
        nextInstallmentNo: row.next_installment_no,
        nextDueDate: row.next_due_date,
        nextAmount: row.next_amount,
        nextStatus: row.next_status,
        status: outstanding === 0 ? 'Fully Settled' : 'Active'
      };
    });

    const totalReceivables = Math.max(totalTCP - totalCollected, 0);

    return res.status(200).json({
      success: true,
      officer: officerRows[0],
      kpis: {
        assignedClientCount: formattedClients.length,
        totalTCP: totalTCP,
        totalCollected: totalCollected,
        totalReceivables: totalReceivables
      },
      clients: formattedClients
    });

  } catch (error) {
    console.error('Dashboard Error:', error);
    return res.status(500).json({ success: false, error: error.message });
  }
});

// 2. POST VALIDATE EMAIL (format + MX record check)
app.post('/api/validate-email', async (req, res) => {
  const { email } = req.body;

  if (!email || typeof email !== 'string') {
    return res.status(400).json({ success: false, valid: false, reason: 'Email is required.' });
  }

  const normalized = email.trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!emailRegex.test(normalized)) {
    return res.status(200).json({ success: true, valid: false, reason: 'Invalid email format.' });
  }

  const domain = normalized.split('@')[1];

  try {
    const records = await dns.resolveMx(domain);
    if (records && records.length > 0) {
      return res.status(200).json({ success: true, valid: true, reason: 'Email domain is valid and can receive mail.' });
    }
    return res.status(200).json({ success: true, valid: false, reason: 'Email domain has no mail server (MX record).' });
  } catch (err) {
    return res.status(200).json({ success: true, valid: false, reason: 'Email domain does not exist or has no mail server.' });
  }
});

// 3. POST CREATE NEW CONTRACT & INITIALIZE AMORTIZATION SCHEDULE
app.post('/api/contracts', async (req, res) => {
  const { officerId, client, contract } = req.body;

  if (!officerId || !client || !contract) {
    return res.status(400).json({ success: false, message: 'Incomplete data provided.' });
  }

  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    const [clientRes] = await connection.query(
      `INSERT INTO clients (officer_id, full_name, email, phone) VALUES (?, ?, ?, ?)`,
      [officerId, client.fullName, client.email, client.phone]
    );
    const clientId = clientRes.insertId;

    const totalPrice = parseFloat(contract.totalPrice);
    const downpayment = parseFloat(contract.downpayment);
    const terms = parseInt(contract.terms, 10);
    const remainingBalance = totalPrice - downpayment;

    const [contractRes] = await connection.query(
      `INSERT INTO contracts 
       (client_id, officer_id, total_contract_price, downpayment_paid, remaining_balance, installment_terms, start_date) 
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [clientId, officerId, totalPrice, downpayment, remainingBalance, terms, contract.startDate]
    );
    const contractId = contractRes.insertId;

    const installments = [];
    const exactMonthly = remainingBalance / terms;
    const [year, month, day] = contract.startDate.split('-').map(Number);
    let installmentDate = new Date(year, month - 1, day);

    for (let i = 1; i <= terms; i++) {
      let paymentAmount = i === terms
        ? Math.round((remainingBalance - exactMonthly * (terms - 1)) * 100) / 100
        : Math.round(exactMonthly * 100) / 100;

      const y = installmentDate.getFullYear();
      const m = String(installmentDate.getMonth() + 1).padStart(2, '0');
      const d = String(installmentDate.getDate()).padStart(2, '0');
      const formattedDueDate = `${y}-${m}-${d}`;

      installments.push([
        contractId,
        i,
        formattedDueDate,
        paymentAmount,
        'Pending Payment'
      ]);

      installmentDate.setDate(1);
      installmentDate.setMonth(installmentDate.getMonth() + 1);
      const lastDay = new Date(installmentDate.getFullYear(), installmentDate.getMonth() + 1, 0).getDate();
      installmentDate.setDate(Math.min(day, lastDay));
    }

    await connection.query(
      `INSERT INTO installment_schedules 
       (contract_id, installment_number, due_date, amount_due, status) 
       VALUES ?`,
      [installments]
    );

    await connection.commit();

    return res.status(201).json({
      success: true,
      message: 'Contract and schedule generated successfully.',
      contractId: contractId
    });

  } catch (error) {
    await connection.rollback();
    console.error('Contract Creation Error:', error);
    return res.status(500).json({ success: false, error: error.message });
  } finally {
    connection.release();
  }
});

// 4. GET CONTRACT DETAILS & LEDGER SCHEDULE BY ID
app.get('/api/contracts/:id/ledger', async (req, res) => {
  const contractId = req.params.id;

  try {
    const [contractRows] = await db.query(
      `SELECT c.*, cl.full_name, cl.email, cl.phone 
       FROM contracts c 
       JOIN clients cl ON c.client_id = cl.id 
       WHERE c.id = ?`,
      [contractId]
    );

    if (contractRows.length === 0) {
      return res.status(404).json({ success: false, message: 'Contract not found.' });
    }

    const [schedules] = await db.query(
      `SELECT * FROM installment_schedules WHERE contract_id = ? ORDER BY installment_number ASC`,
      [contractId]
    );

    return res.status(200).json({
      success: true,
      contract: contractRows[0],
      schedule: schedules
    });

  } catch (error) {
    return res.status(500).json({ success: false, error: error.message });
  }
});

// 5. POST RECORD / CLEAR INSTALLMENT PAYMENT
app.post('/api/installments/:id/payments', async (req, res) => {
  const installmentId = req.params.id;
  const { contractId, paymentMethod, paymentDate, orNumber, cashierNotes, amountPaid } = req.body;

  if (!contractId || !orNumber || !paymentMethod || !paymentDate) {
    return res.status(400).json({ success: false, message: 'Missing required settlement parameters.' });
  }

  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    const [updateRes] = await connection.query(
      `UPDATE installment_schedules 
       SET status = 'Cleared & Posted', 
           payment_method = ?, 
           payment_date = ?, 
           or_number = ?, 
           cashier_notes = ? 
       WHERE id = ?`,
      [paymentMethod, paymentDate, orNumber, cashierNotes || '', installmentId]
    );

    if (updateRes.affectedRows === 0) {
      throw new Error('Installment not found or already settled.');
    }

    await connection.query(
      `UPDATE contracts 
       SET remaining_balance = GREATEST(remaining_balance - ?, 0) 
       WHERE id = ?`,
      [amountPaid, contractId]
    );

    await connection.commit();

    return res.status(200).json({
      success: true,
      message: `Installment #${installmentId} cleared under OR #${orNumber}.`
    });

  } catch (error) {
    await connection.rollback();
    console.error('Payment Error:', error);
    return res.status(500).json({ success: false, error: error.message });
  } finally {
    connection.release();
  }
});

// 6. POST SEND PAYMENT REMINDER EMAIL
app.post('/api/contracts/:id/send-reminder', async (req, res) => {
  const contractCode = req.params.id;

  try {
    const numericId = parseInt(contractCode.replace(/^IHC-\d{4}-0*/, ''), 10);

    const [contractRows] = await db.query(
      `SELECT c.*, cl.full_name, cl.email
       FROM contracts c
       JOIN clients cl ON c.client_id = cl.id
       WHERE c.id = ?`,
      [numericId]
    );

    if (contractRows.length === 0) {
      return res.status(404).json({ success: false, message: 'Contract not found.' });
    }

    const contract = contractRows[0];

    if (!contract.email) {
      return res.status(400).json({ success: false, message: 'Client has no email address on file.' });
    }

    const [pendingInstallments] = await db.query(
      `SELECT * FROM installment_schedules
       WHERE contract_id = ? AND status = 'Pending Payment'
       ORDER BY due_date ASC
       LIMIT 1`,
      [numericId]
    );

    if (pendingInstallments.length === 0) {
      return res.status(400).json({ success: false, message: 'No pending installments for this contract.' });
    }

    const installment = pendingInstallments[0];
    const today = new Date();
    const dueDate = new Date(installment.due_date);
    const daysUntil = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));

    const result = await sendPaymentReminder(
      contract.email,
      contract.full_name,
      contractCode,
      installment.amount_due,
      installment.due_date,
      daysUntil
    );

    if (result.success) {
      return res.status(200).json({
        success: true,
        message: `Payment reminder sent to ${contract.full_name} (${contract.email})`
      });
    } else {
      return res.status(500).json({
        success: false,
        message: 'Failed to send reminder email.',
        error: result.error
      });
    }

  } catch (error) {
    console.error('Send Reminder Error:', error);
    return res.status(500).json({ success: false, error: error.message });
  }
});

const server = app.listen(PORT, () => {
  console.log(`IHC CMS Backend running on http://localhost:${PORT}`);
});

require('./cronJobs');

// Keep process active and prevent premature exit
setInterval(() => {}, 1000 << 16);

process.on('uncaughtException', (err) => {
  console.error('CRITICAL UNCAUGHT EXCEPTION:', err);
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('CRITICAL UNHANDLED REJECTION:', reason);
});
