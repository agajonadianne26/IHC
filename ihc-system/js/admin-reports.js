/*
 * Admin Reports & Exports
 *
 * Builds the four report workbooks from live API data instead of the old
 * hidden HTML/PDF templates:
 *   - Collection Summary
 *   - Cash Flow Forecast (weekly or monthly)
 *   - Aging of Accounts
 *   - Due & Overdue Register
 *
 * Each card downloads a real .xlsx (CSV fallback when SheetJS is unavailable)
 * and "Export All" puts the four sheets into one workbook.
 */
(function () {
  'use strict';

  const PERIOD_KEY = 'IHC_ADMIN_REPORT_PERIOD';
  const BUCKETS = ['current', '1-30', '31-60', '61-90', '91-120', '120+', 'settled'];
  const BUCKET_LABELS = {
    current: 'Current (not yet due)',
    '1-30': '1-30 days',
    '31-60': '31-60 days',
    '61-90': '61-90 days',
    '91-120': '91-120 days',
    '120+': 'Over 120 days',
    settled: 'Fully paid'
  };
  const STATUS_LABELS = { overdue: 'Overdue', 'due-today': 'Due today', upcoming: 'Upcoming' };

  let mounted = false;
  let opts = {};
  let busy = false;
  const state = { period: 'month' };

  const pad = (n) => String(n).padStart(2, '0');
  const localISO = (d) => {
    const x = d instanceof Date ? d : new Date();
    return x.getFullYear() + '-' + pad(x.getMonth() + 1) + '-' + pad(x.getDate());
  };
  const parseISO = (s) => {
    if (!s) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s));
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
  };
  const addDays = (d, n) => {
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    x.setDate(x.getDate() + n);
    return x;
  };
  const mondayOf = (d) => {
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = x.getDay();
    return addDays(x, day === 0 ? -6 : 1 - day);
  };
  const fmtDate = (s) => {
    const d = s instanceof Date ? s : parseISO(s);
    return d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
  };
  const fmtDateTime = (d) => (d instanceof Date ? d : new Date()).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
  const money = (n) => (Number(n) || 0).toFixed(2);
  const pct = (n, d) => (Number(d) > 0 ? (Number(n) / Number(d) * 100).toFixed(2) + '%' : '');
  const daysBetween = (a, b) => Math.round((a.getTime() - b.getTime()) / 86400000);

  const fallbackCell = (v) => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
  const fallbackLine = (arr) => arr.map(fallbackCell).join(',');
  function csv() {
    const c = window.IHCDues && window.IHCDues.csv;
    return {
      cell: c && c.cell ? c.cell : fallbackCell,
      line: c && c.line ? c.line : fallbackLine,
      money: c && c.money ? c.money : money
    };
  }
  function line(arr) { return csv().line(arr); }
  function cell(v) { return csv().cell(v); }

  function notify(message, type) {
    if (typeof opts.notify === 'function') opts.notify(message, type || 'success');
    else if (window.console) console.log(message);
  }
  function getCompany() {
    const value = typeof opts.company === 'function' ? opts.company() : opts.company;
    if (value && String(value).trim()) return String(value).trim();
    const el = document.getElementById('soaCompanyName');
    if (el && el.value && el.value.trim()) return el.value.trim();
    return 'IHC System';
  }
  function rangeLabel(period) {
    if (!period) return '';
    return fmtDate(period.from) + ' \u2013 ' + fmtDate(period.to);
  }
  function localPeriodLabel(period) {
    const now = new Date();
    if (period === 'week') {
      const mon = mondayOf(now);
      return fmtDate(mon) + ' \u2013 ' + fmtDate(addDays(mon, 6));
    }
    return now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }
  function periodKind() { return state.period === 'week' ? 'Week' : 'Month'; }
  function periodTag(dues) {
    const from = dues && dues.period && dues.period.from ? dues.period.from : localISO(new Date());
    return state.period === 'week' ? from : from.slice(0, 7);
  }
  function filename(kind, dues) {
    return kind + '-' + periodKind() + '-' + periodTag(dues) + '.xlsx';
  }
  function setLabel(text) {
    const el = document.getElementById('reportPeriodLabel');
    if (el) el.textContent = text || localPeriodLabel(state.period);
  }
  function syncPeriodButtons() {
    document.querySelectorAll('#reportPeriod [data-period]').forEach(btn => {
      const on = btn.dataset.period === state.period;
      btn.classList.toggle('on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    setLabel(localPeriodLabel(state.period));
  }
  function setBusy(btn, value) {
    if (!btn) return;
    if (value) {
      btn.dataset.reportOrig = btn.innerHTML;
      btn.disabled = true;
      btn.textContent = 'Exporting...';
    } else {
      btn.disabled = false;
      if (btn.dataset.reportOrig) {
        btn.innerHTML = btn.dataset.reportOrig;
        delete btn.dataset.reportOrig;
      }
      if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    }
  }

  async function fetchJson(url) {
    const res = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' } });
    let data;
    try { data = await res.json(); } catch (e) { throw new Error('The server returned an invalid response.'); }
    if (!res.ok || !data || !data.success) throw new Error((data && data.message) || ('Request failed (' + res.status + ')'));
    return data;
  }
  function api(path) {
    const base = String(opts.apiBase || '').replace(/\/$/, '');
    return base + path;
  }
  async function fetchDues() {
    const qs = new URLSearchParams({
      action: 'dues',
      period: state.period === 'week' ? 'week' : 'month',
      includeOverdue: '1',
      _: String(Date.now())
    });
    return fetchJson(api('/php/api_admin.php?' + qs.toString()));
  }
  async function fetchPortfolio(dues) {
    const qs = new URLSearchParams({
      action: 'portfolio_report',
      from: dues.period.from,
      to: dues.period.to,
      _: String(Date.now())
    });
    return fetchJson(api('/php/api_admin.php?' + qs.toString()));
  }
  async function fetchForecast() {
    const qs = new URLSearchParams({
      granularity: state.period === 'week' ? 'week' : 'month',
      _: String(Date.now())
    });
    return fetchJson(api('/php/api_forecast.php?' + qs.toString()));
  }

  function rowTotal(rows, field) {
    return (rows || []).reduce((sum, r) => sum + (Number(r[field]) || 0), 0);
  }
  function statusLabel(status) {
    return STATUS_LABELS[status] || status || '';
  }
  function bucketLabel(key) {
    return BUCKET_LABELS[key] || key || '';
  }
  function partialLabel(r) {
    return r && r.partial ? 'Yes' : 'No';
  }

  function buildCollectionSheet(dues, portfolio) {
    const L = [];
    const t = dues.totals || {};
    const rows = dues.rows || [];
    const receipts = (portfolio.collections && portfolio.collections.rows) || [];
    const methods = (portfolio.collections && portfolio.collections.byMethod) || [];
    const receiptsTotal = (portfolio.collections && portfolio.collections.total) || 0;

    L.push(line(['COLLECTION SUMMARY REPORT']));
    L.push(line(['Company', getCompany()]));
    L.push(line(['Period', rangeLabel(dues.period)]));
    L.push(line(['Granularity', state.period === 'week' ? 'Weekly' : 'Monthly']));
    L.push(line(['Generated', fmtDateTime(new Date())]));
    L.push('');

    L.push(line(['SUMMARY', 'Count', 'Amount (PHP)']));
    L.push(line(['Actual due (scheduled in period)', '', money(t.actualDue)]));
    L.push(line(['Arrears (unpaid from earlier)', '', money(t.arrears)]));
    L.push(line(['Total due', '', money(t.totalDue)]));
    L.push(line(['Collected in period', t.collectedCount || 0, money(t.collected)]));
    L.push(line(['Still unpaid', '', money(t.stillUnpaid)]));
    L.push(line(['Collection rate', '', t.collectedRate == null ? '' : t.collectedRate + '%']));
    L.push(line(['Unpaid installments', rows.length, money(rowTotal(rows, 'amountDue'))]));
    L.push(line(['Carried from earlier', t.carriedCount || 0, money(t.carriedAmount)]));
    L.push(line(['Expected (Excel projection)', t.expectedCount || 0, money(t.expectedAmount)]));
    L.push(line(['Floating account (no monthly yet)', t.needsMonthlyCount || 0, money(t.needsMonthlyBalance)]));
    L.push('');

    L.push(line(['PER CLERK']));
    L.push(line(['Clerk', 'Actual due (PHP)', 'Arrears (PHP)', 'Total due (PHP)', 'Collected (PHP)', 'Collection rate', 'Still unpaid (PHP)', 'Expected Excel (PHP)', 'Floating account (PHP)']));
    if ((dues.clerks || []).length) {
      dues.clerks.forEach(c => L.push(line([
        c.name || 'Unassigned', money(c.actualDue), money(c.arrears), money(c.totalDue),
        money(c.collected), c.collectedRate == null ? '' : c.collectedRate + '%',
        money(c.stillUnpaid), money(c.expectedAmount), money(c.needsMonthlyBalance)
      ])));
    } else {
      L.push(line(['No clerk data for this period.']));
    }
    L.push('');

    L.push(line(['COLLECTIONS BY METHOD']));
    L.push(line(['Method', 'Amount (PHP)']));
    if (methods.length) methods.forEach(m => L.push(line([m.method || 'Unspecified', money(m.amount)])));
    else L.push(line(['No receipts in this period.']));
    L.push('');

    L.push(line(['RECEIPTS IN PERIOD']));
    L.push(line(['No.', 'Date', 'OR No.', 'Client', 'Account No.', 'Amount (PHP)', 'Method', 'Remarks']));
    receipts.forEach((r, i) => L.push(line([
      i + 1, fmtDate(r.date), r.orNumber || '', r.client || '', r.accountCode || '',
      money(r.amount), r.method || '', r.remarks || ''
    ])));
    if (!receipts.length) L.push(line(['No receipts in this period.']));
    L.push(line(['', '', '', 'TOTAL', '', money(receiptsTotal)]));
    L.push('');

    L.push(line(['UNPAID INSTALLMENTS IN PERIOD']));
    L.push(line(['No.', 'Due Date', 'Client', 'Account No.', 'Property', 'Installment', 'Amount Due (PHP)', 'Full Installment (PHP)', 'Partial', 'Status', 'Days Late', 'From Earlier Period', 'Clerk']));
    rows.forEach((r, i) => L.push(line([
      i + 1, fmtDate(r.dueDate), r.client || '', r.accountCode || '', r.property || '',
      r.installment || '', money(r.amountDue), money(r.fullAmount), partialLabel(r),
      statusLabel(r.status), r.daysOverdue || 0, r.carried ? 'Yes' : 'No', r.clerk || ''
    ])));
    if (!rows.length) L.push(line(['No unpaid installments in this period.']));
    L.push(line(['', '', '', '', '', 'TOTAL', money(rowTotal(rows, 'amountDue'))]));
    L.push('');
    L.push(line(['Expected = unpaid amounts due in the period. Actual = posted collections in the period.']));

    return { name: 'Collection Summary', lines: L };
  }

  function buildCashflowSheet(forecast) {
    const L = [];
    const labels = forecast.labels || forecast.months || [];
    const periods = forecast.periods || [];
    const expected = forecast.expected || [];
    const actual = forecast.actual || [];
    let totalExpected = 0;
    let totalActual = 0;

    L.push(line(['CASH FLOW FORECAST REPORT']));
    L.push(line(['Company', getCompany()]));
    L.push(line(['Report period', localPeriodLabel(state.period)]));
    L.push(line(['Granularity', forecast.granularity === 'week' ? 'Weekly' : 'Monthly']));
    L.push(line(['Generated', fmtDateTime(new Date())]));
    L.push('');
    L.push(line(['Period', 'Expected (Unpaid Due) (PHP)', 'Actual Collected (PHP)', 'Variance (PHP)', 'Variance %', 'Status']));

    labels.forEach((label, i) => {
      const e = Number(expected[i]) || 0;
      const a = Number(actual[i]) || 0;
      const v = a - e;
      const p = e > 0 ? (v / e * 100).toFixed(2) + '%' : '';
      const shown = forecast.granularity === 'week' && periods[i]
        ? 'Week of ' + fmtDate(periods[i])
        : label;
      totalExpected += e;
      totalActual += a;
      L.push(line([shown, money(e), money(a), money(v), p, v >= 0 ? 'On/above forecast' : 'Below forecast']));
    });

    L.push(line(['TOTAL', money(totalExpected), money(totalActual), money(totalActual - totalExpected),
      totalExpected > 0 ? ((totalActual - totalExpected) / totalExpected * 100).toFixed(2) + '%' : '', '']));
    L.push('');
    L.push(line(['Expected = unpaid amounts due in each period. Actual = posted collections in each period.']));

    return { name: 'Cash Flow Forecast', lines: L };
  }

  function buildAgingSheet(dues, portfolio) {
    const L = [];
    const aging = (portfolio.aging && portfolio.aging.buckets) || {};
    const rows = (portfolio.aging && portfolio.aging.rows) || [];
    const overdueKeys = BUCKETS.filter(k => k !== 'current' && k !== 'settled');
    const totalOverdueAccounts = overdueKeys.reduce((s, k) => s + (Number(aging[k] && aging[k].count) || 0), 0);
    const totalOverdueAmount = overdueKeys.reduce((s, k) => s + (Number(aging[k] && aging[k].amount) || 0), 0);
    const totalOverdueBalance = overdueKeys.reduce((s, k) => s + (Number(aging[k] && aging[k].balance) || 0), 0);
    const totalAccounts = BUCKETS.reduce((s, k) => s + (Number(aging[k] && aging[k].count) || 0), 0);

    L.push(line(['AGING OF ACCOUNTS REPORT']));
    L.push(line(['Company', getCompany()]));
    L.push(line(['As of', fmtDate(portfolio.today || dues.today)]));
    L.push(line(['Report period', rangeLabel(dues.period)]));
    L.push(line(['Generated', fmtDateTime(new Date())]));
    L.push('');

    L.push(line(['AGING SUMMARY']));
    L.push(line(['Bucket', 'Accounts', 'Overdue Amount (PHP)', 'Outstanding Balance (PHP)', '% of Overdue Accounts', '% of Overdue Amount']));
    BUCKETS.forEach(k => {
      const b = aging[k] || {};
      const isOverdue = overdueKeys.indexOf(k) !== -1;
      L.push(line([
        bucketLabel(k), b.count || 0, money(b.amount), money(b.balance),
        isOverdue ? pct(b.count, totalOverdueAccounts) : '',
        isOverdue ? pct(b.amount, totalOverdueAmount) : ''
      ]));
    });
    L.push(line(['TOTAL OVERDUE', totalOverdueAccounts, money(totalOverdueAmount), money(totalOverdueBalance),
      totalOverdueAccounts > 0 ? '100.00%' : '', totalOverdueAmount > 0 ? '100.00%' : '']));
    L.push(line(['TOTAL ACCOUNTS', totalAccounts, '', '', '', '']));
    L.push('');

    L.push(line(['AGING DETAILS']));
    L.push(line(['No.', 'Client', 'Account No.', 'Excel Code', 'Property', 'Bucket', 'Oldest Due', 'Days Overdue', 'Installments Overdue', 'Overdue Amount (PHP)', 'Outstanding Balance (PHP)', 'Clerk']));
    rows.forEach((r, i) => L.push(line([
      i + 1, r.client || '', r.accountCode || '', r.legacyCode || '', r.property || '',
      bucketLabel(r.bucket), fmtDate(r.oldestDue), r.daysOverdue || 0, r.installmentsOverdue || 0,
      money(r.overdueAmount), money(r.balance), r.clerk || ''
    ])));
    if (!rows.length) L.push(line(['No overdue accounts as of today.']));
    L.push(line(['', '', '', '', '', 'TOTAL', '', '', '', money(rowTotal(rows, 'overdueAmount')), money(rowTotal(rows, 'balance'))]));
    L.push('');
    L.push(line(['Aging is a snapshot as of today. The report period is shown for reference.']));

    return { name: 'Aging of Accounts', lines: L };
  }

  function buildDueOverdueSheet(dues) {
    const L = [];
    const rows = dues.rows || [];
    const today = parseISO(dues.today || localISO(new Date())) || new Date();
    const dueRows = rows.filter(r => r.status !== 'overdue');
    const overdueRows = rows.filter(r => r.status === 'overdue');
    const dueTotal = rowTotal(dueRows, 'amountDue');
    const overdueTotal = rowTotal(overdueRows, 'amountDue');

    L.push(line(['DUE & OVERDUE REGISTER']));
    L.push(line(['Company', getCompany()]));
    L.push(line(['Period', rangeLabel(dues.period)]));
    L.push(line(['Granularity', state.period === 'week' ? 'Weekly' : 'Monthly']));
    L.push(line(['Generated', fmtDateTime(new Date())]));
    L.push('');
    L.push(line(['SUMMARY', 'Count', 'Amount (PHP)']));
    L.push(line(['Due in period', dueRows.length, money(dueTotal)]));
    L.push(line(['Overdue', overdueRows.length, money(overdueTotal)]));
    L.push(line(['Total', rows.length, money(dueTotal + overdueTotal)]));
    L.push('');

    L.push(line(['DUE IN PERIOD']));
    L.push(line(['No.', 'Due Date', 'Client', 'Account No.', 'Property', 'Installment', 'Amount Due (PHP)', 'Full Installment (PHP)', 'Partial', 'Status', 'Days Until Due', 'Clerk']));
    dueRows.forEach((r, i) => {
      const due = parseISO(r.dueDate);
      const daysUntil = due ? Math.max(0, daysBetween(due, today)) : 0;
      L.push(line([
        i + 1, fmtDate(r.dueDate), r.client || '', r.accountCode || '', r.property || '',
        r.installment || '', money(r.amountDue), money(r.fullAmount), partialLabel(r),
        statusLabel(r.status), daysUntil, r.clerk || ''
      ]));
    });
    if (!dueRows.length) L.push(line(['No accounts due in this period.']));
    L.push(line(['', '', '', '', '', 'TOTAL', money(dueTotal)]));
    L.push('');

    L.push(line(['OVERDUE']));
    L.push(line(['No.', 'Due Date', 'Client', 'Account No.', 'Property', 'Installment', 'Amount Due (PHP)', 'Full Installment (PHP)', 'Partial', 'Status', 'Days Overdue', 'From Earlier Period', 'Clerk']));
    overdueRows.forEach((r, i) => {
      const due = parseISO(r.dueDate);
      const daysOverdue = Number(r.daysOverdue) || (due ? Math.max(0, daysBetween(today, due)) : 0);
      L.push(line([
        i + 1, fmtDate(r.dueDate), r.client || '', r.accountCode || '', r.property || '',
        r.installment || '', money(r.amountDue), money(r.fullAmount), partialLabel(r),
        statusLabel(r.status), daysOverdue, r.carried ? 'Yes' : 'No', r.clerk || ''
      ]));
    });
    if (!overdueRows.length) L.push(line(['No overdue accounts.']));
    L.push(line(['', '', '', '', '', 'TOTAL', money(overdueTotal)]));
    L.push('');
    L.push(line(['Due in period includes upcoming and due-today installments. Overdue includes all unpaid installments before today.']));

    return { name: 'Due & Overdue Register', lines: L };
  }

  async function loadFor(type) {
    if (type === 'cashflow') {
      return { forecast: await fetchForecast() };
    }
    const dues = await fetchDues();
    if (type === 'dueoverdue') return { dues };
    const portfolio = await fetchPortfolio(dues);
    return { dues, portfolio };
  }
  async function loadAll() {
    const dues = await fetchDues();
    const [portfolio, forecast] = await Promise.all([fetchPortfolio(dues), fetchForecast()]);
    return { dues, portfolio, forecast };
  }

  function sheetFor(type, data) {
    if (type === 'collection') return buildCollectionSheet(data.dues, data.portfolio);
    if (type === 'cashflow') return buildCashflowSheet(data.forecast);
    if (type === 'aging') return buildAgingSheet(data.dues, data.portfolio);
    if (type === 'dueoverdue') return buildDueOverdueSheet(data.dues);
    throw new Error('Unknown report type.');
  }
  function reportBaseName(type, dues) {
    if (type === 'collection') return filename('Collection-Summary', dues);
    if (type === 'cashflow') return filename('Cash-Flow-Forecast', dues);
    if (type === 'aging') return filename('Aging-of-Accounts', dues);
    if (type === 'dueoverdue') return filename('Due-and-Overdue-Register', dues);
    return filename('Report', dues);
  }

  function saveCsvFallback(base, sheets) {
    (sheets || []).forEach((s, i) => {
      const suffix = sheets.length > 1 ? '-' + String(s.name || ('Sheet' + (i + 1))).replace(/[^A-Za-z0-9]+/g, '-') : '';
      const blob = new Blob(['\uFEFF' + (s.lines || []).join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = base.replace(/\.(xlsx|csv)$/i, '') + suffix + '.csv';
      document.body.appendChild(a);
      a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
    });
  }
  async function saveWorkbook(base, sheets) {
    const c = window.IHCDues && window.IHCDues.csv;
    if (c && typeof c.workbook === 'function') {
      await c.workbook(base, sheets);
      return;
    }
    if (c && typeof c.download === 'function') {
      for (const s of sheets) await c.download(base.replace(/\.xlsx$/i, '') + '-' + s.name, s.lines);
      return;
    }
    saveCsvFallback(base, sheets);
  }

  async function exportOne(type, btn) {
    if (busy) return;
    busy = true;
    setBusy(btn, true);
    try {
      const data = await loadFor(type);
      const sheet = sheetFor(type, data);
      const dues = data.dues || null;
      await saveWorkbook(reportBaseName(type, dues), [sheet]);
      setLabel(dues && dues.period ? rangeLabel(dues.period) : localPeriodLabel(state.period));
      notify(sheet.name + ' exported as Excel.', 'success');
    } catch (err) {
      console.error('Report export failed:', err);
      notify('Export failed: ' + (err.message || 'unknown error'), 'info');
    } finally {
      busy = false;
      setBusy(btn, false);
    }
  }

  async function exportAll(btn) {
    if (busy) return;
    busy = true;
    setBusy(btn, true);
    try {
      const data = await loadAll();
      const sheets = [
        buildCollectionSheet(data.dues, data.portfolio),
        buildCashflowSheet(data.forecast),
        buildAgingSheet(data.dues, data.portfolio),
        buildDueOverdueSheet(data.dues)
      ];
      await saveWorkbook(filename('IHC-Reports', data.dues), sheets);
      setLabel(rangeLabel(data.dues.period));
      notify('All four reports exported in one Excel workbook.', 'success');
    } catch (err) {
      console.error('Combined report export failed:', err);
      notify('Export failed: ' + (err.message || 'unknown error'), 'info');
    } finally {
      busy = false;
      setBusy(btn, false);
    }
  }

  function bind() {
    document.querySelectorAll('.report-card [data-report]').forEach(btn => {
      btn.addEventListener('click', () => exportOne(btn.dataset.report, btn));
    });
    document.querySelectorAll('#reportPeriod [data-period]').forEach(btn => {
      btn.addEventListener('click', () => {
        const period = btn.dataset.period === 'week' ? 'week' : 'month';
        state.period = period;
        try { localStorage.setItem(PERIOD_KEY, period); } catch (e) {}
        syncPeriodButtons();
      });
    });
    const all = document.getElementById('generateAllReportsBtn');
    if (all) all.addEventListener('click', () => exportAll(all));
  }

  function mount(options) {
    if (mounted) return;
    if (!document.querySelector('[data-view="reports"]')) return;
    mounted = true;
    opts = options || {};
    try {
      const stored = localStorage.getItem(PERIOD_KEY);
      if (stored === 'week' || stored === 'month') state.period = stored;
    } catch (e) {}
    syncPeriodButtons();
    bind();
  }

  window.IHCAdminReports = { mount };
})();
