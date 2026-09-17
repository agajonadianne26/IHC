/* ============================================================
   IHC CLIENT PORTAL — FRONT-END DATA STORE (localStorage)
   Demo/bridge layer only. Keeps client accounts, per-contract
   ledgers and notifications so the client portal works without
   a server. Swap with server APIs at integration time.
============================================================ */
(function (global) {
  'use strict';

  const CLIENTS_KEY = 'IHC_CLIENTS';
  const PORTAL_PREFIX = 'IHC_PORTAL_';

  function readJSON(key, fallback) {
    try {
      const raw = global.localStorage.getItem(key);
      if (!raw) return fallback;
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function writeJSON(key, value) {
    global.localStorage.setItem(key, JSON.stringify(value));
  }

  function readClients() {
    const arr = readJSON(CLIENTS_KEY, []);
    return Array.isArray(arr) ? arr : [];
  }

  function persistClients(clients) {
    writeJSON(CLIENTS_KEY, clients);
  }

  /* Demo accounts matching the seeded sample contracts */
  const SEED = [
    {
      contractId: 7,
      name: 'Marzia Hernandez',
      email: 'agajonadianne@gmail.com',
      phone: '0909690140',
      password: 'client123',
      propertyAddress: 'Las Piñas',
      totalPrice: 2500000,
      downpayment: 1000000,
      terms: 60,
      startDate: '2026-09-17',
      officerId: 2,
      officerName: 'Ana Reyes',
      officerEmail: 'ana@ihc.com'
    },
    {
      contractId: 8,
      name: 'Jimmy Fuentes',
      email: 'jeremypaulcantalejo28@gmail.com',
      phone: '0909337463746',
      password: 'client123',
      propertyAddress: 'Laguna',
      totalPrice: 3500000,
      downpayment: 2000000,
      terms: 60,
      startDate: '2026-09-24',
      officerId: 2,
      officerName: 'Ana Reyes',
      officerEmail: 'ana@ihc.com'
    }
  ];

  function seedDemoClients() {
    let clients = readClients();
    if (clients.length === 0) {
      clients = SEED.map(c => Object.assign({}, c));
      persistClients(clients);
    } else {
      // Backfill officer info on demo records created before this field existed
      let changed = false;
      clients = clients.map(c => {
        const seed = SEED.find(s => Number(s.contractId) === Number(c.contractId));
        if (seed && !c.officerName) {
          changed = true;
          return Object.assign({}, c, {
            officerId: seed.officerId,
            officerName: seed.officerName,
            officerEmail: seed.officerEmail
          });
        }
        return c;
      });
      if (changed) persistClients(clients);
    }
    return clients;
  }

  function getClientRecord(contractId) {
    const id = Number(contractId);
    return readClients().find(c => Number(c.contractId) === id) || null;
  }

  function portalKey(contractId) {
    return PORTAL_PREFIX + Number(contractId);
  }

  function readPortal(contractId) {
    return readJSON(portalKey(contractId), null);
  }

  function savePortal(contractId, data) {
    writeJSON(portalKey(contractId), data);
    return data;
  }

  function fmtPeso(n) {
    return '\u20B1' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function addMonths(dateStr, months) {
    const d = new Date(dateStr + 'T00:00:00');
    const day = d.getDate();
    const target = new Date(d.getFullYear(), d.getMonth() + months, 1);
    const last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    target.setDate(Math.min(day, last));
    return target.getFullYear() + '-' + String(target.getMonth() + 1).padStart(2, '0') + '-' + String(target.getDate()).padStart(2, '0');
  }

  /* Monthly amortization payment using the reducing-balance method */
  function amortize(principal, annualRatePct, months) {
    const r = (Number(annualRatePct) || 0) / 100 / 12;
    if (r === 0) return principal / months;
    const factor = Math.pow(1 + r, months);
    return principal * r * factor / (factor - 1);
  }

  /* Two-phase schedule: a deposit phase covering the monthly downpayment
     (0% interest, optional) followed by the house amortization with the
     chosen bank's annual interest applied to the financing amount. */
  function buildSchedules(client) {
    const totalPrice = Number(client.totalPrice) || 0;
    const downpayment = Number(client.downpayment) || 0;
    const financing = Math.max(0, totalPrice - downpayment);
    const terms = Number(client.terms) || 0;
    const dpMode = client.dpMode === 'monthly' ? 'monthly' : 'lump';
    const dpTerms = Math.max(0, Number(client.dpTerms) || 0);
    const annualRate = Number(client.annualRate != null ? client.annualRate : client.annualInterestRate) || 0;

    const schedules = [];
    let no = 1;

    // Phase 1: monthly downpayment / deposit (spread over the deposit term, 0% interest)
    if (dpMode === 'monthly' && downpayment > 0 && dpTerms > 0) {
      const depositMonthly = Math.round((downpayment / dpTerms) * 100) / 100;
      for (let i = 1; i <= dpTerms; i++) {
        const amount = i === dpTerms ? Math.round((downpayment - depositMonthly * (dpTerms - 1)) * 100) / 100 : depositMonthly;
        schedules.push({
          no: no++,
          phase: 'deposit',
          dueDate: addMonths(client.startDate, i),
          amount: amount,
          paid: false,
          payDate: null,
          orNumber: null,
          method: null
        });
      }
    }

    // Phase 2: house amortization with interest, begins right after the deposit phase
    if (financing > 0 && terms > 0) {
      const monthly = Math.round(amortize(financing, annualRate, terms) * 100) / 100;
      const startIdx = schedules.length;
      for (let i = 1; i <= terms; i++) {
        schedules.push({
          no: no++,
          phase: 'amortization',
          dueDate: addMonths(client.startDate, startIdx + i),
          amount: monthly,
          paid: false,
          payDate: null,
          orNumber: null,
          method: null
        });
      }
    }

    return schedules;
  }

  function ensurePortal(client) {
    const existing = readPortal(client.contractId);
    if (existing && Array.isArray(existing.schedules)) return existing;

    const data = {
      contractId: Number(client.contractId),
      schedules: buildSchedules(client),
      notifications: [{
        kind: 'welcome',
        title: 'Welcome to the IHC Portal',
        message: 'Your account is active. Your payment schedule and ledger are now available to view.',
        channel: 'Portal',
        time: new Date().toISOString()
      }]
    };
    return savePortal(client.contractId, data);
  }

  function registerClient(record) {
    const clients = readClients();
    const idx = clients.findIndex(c => Number(c.contractId) === Number(record.contractId));
    if (idx >= 0) clients[idx] = record;
    else clients.push(record);
    persistClients(clients);
    return ensurePortal(record);
  }

  /* Called by the clerk when a payment is posted */
  function postPortalPayment(contractId, payment) {
    const client = getClientRecord(contractId);
    if (!client) return null;
    let data = readPortal(contractId);
    if (!data) data = ensurePortal(client);

    const schedule =
      data.schedules.find(s => !s.paid && Math.abs(Number(s.amount) - Number(payment.amount)) < 0.01) ||
      data.schedules.find(s => !s.paid);

    if (schedule) {
      schedule.paid = true;
      schedule.payDate = payment.date || new Date().toISOString().slice(0, 10);
      schedule.orNumber = payment.orNumber || null;
      schedule.method = payment.method || null;
    }

    data.notifications.unshift({
      kind: 'payment',
      title: 'Payment recorded in your ledger',
      message: 'Your ' + fmtPeso(payment.amount) + ' payment has been posted' +
        (payment.orNumber ? ' with OR/Ref #' + payment.orNumber : '') + '.',
      channel: 'Portal',
      time: new Date().toISOString()
    });

    return savePortal(contractId, data);
  }

  function findClientByLogin(email, password) {
    const clients = seedDemoClients();
    return clients.find(c =>
      String(c.email).toLowerCase() === String(email).toLowerCase() &&
      String(c.password) === String(password)) || null;
  }

  global.portalStore = {
    CLIENT_ROLE: 'client',
    readClients,
    persistClients,
    seedDemoClients,
    getClientRecord,
    readPortal,
    savePortal,
    ensurePortal,
    registerClient,
    postPortalPayment,
    findClientByLogin,
    fmtPeso,
    addMonths,
    amortize,
    buildSchedules
  };
})(window);