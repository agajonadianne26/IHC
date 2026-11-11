/*
 * Dues & Performance — shared by the admin Reports page and the clerk
 * Reports page. Data: php/api_admin.php?action=dues, which runs the same
 * ihc_schedule() as every ledger, so a posted payment removes its row on the
 * next refresh.
 *
 *   IHCDues.mount(containerEl, { apiBase, officerId?: string, showClerks?: bool })
 *     officerId  → clerk view: locked to that officer, no clerk column/filter
 *     showClerks → admin view: clerk filter + per-clerk performance table
 *   returns { refresh() }
 *
 * "Include overdue from earlier" folds the older unpaid installments into the
 * cards (server-side: Due / Still unpaid / Collection rate) and the list.
 */
(function () {
  'use strict';

  const CSS = `
  .dues-wrap { display:flex; flex-direction:column; gap:16px; }
  .dues-top { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  .dues-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  .dues-seg { display:inline-flex; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
  .dues-seg button { border:0; background:#fff; padding:0 16px; height:36px; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; color:#475569; }
  .dues-seg button + button { border-left:1px solid #e2e8f0; }
  .dues-seg button.on { background:#0f172a; color:#fff; }
  .dues-bar input, .dues-bar select { height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font:inherit; font-size:12.5px; background:#fff; color:#0f172a; }
  .dues-bar label.chk { display:inline-flex; gap:7px; align-items:center; height:36px; padding:0 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:12.5px; font-weight:600; color:#334155; cursor:pointer; background:#fff; user-select:none; }
  .dues-bar label.chk.on { border-color:#f43f5e; background:#fff1f2; color:#9f1239; }
  .dues-bar .grow { flex:1; min-width:200px; }
  .dues-btn { height:36px; padding:0 14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; color:#0f172a; }
  .dues-btn:hover { background:#f8fafc; }
  .dues-btn.primary { background:#10b981; border-color:#10b981; color:#fff; }
  .dues-btn.primary:hover { background:#059669; }
  .dues-range { display:inline-flex; align-items:center; height:36px; padding:0 12px; border-radius:8px; background:#f1f5f9; font-size:12.5px; color:#334155; font-weight:600; }
  .dues-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
  @media (max-width:1280px) { .dues-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); } }
  @media (max-width:760px) { .dues-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  .dues-kpi { position:relative; border:1px solid #e2e8f0; border-radius:12px; padding:14px 14px 12px; background:#fff; overflow:hidden; }
  .dues-kpi::before { content:''; position:absolute; left:0; top:0; right:0; height:3px; background:var(--k,#94a3b8); }
  .dues-kpi small { display:block; font-size:10.5px; color:#64748b; text-transform:uppercase; letter-spacing:.5px; font-weight:700; }
  .dues-kpi strong { display:block; font-size:19px; margin-top:6px; color:#0f172a; letter-spacing:-.3px; }
  .dues-kpi span { display:block; font-size:11.5px; color:#64748b; margin-top:3px; }
  .dues-kpi em { display:inline-block; font-style:normal; font-size:10px; font-weight:700; padding:1px 6px; border-radius:999px; background:#fff1f2; color:#9f1239; margin-left:4px; vertical-align:1px; }
  .dues-kpi .meter { height:6px; border-radius:3px; background:#f1f5f9; overflow:hidden; margin-top:8px; }
  .dues-kpi .meter i { display:block; height:100%; background:var(--k); }
  .dues-note { font-size:11.5px; color:#9f1239; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; padding:7px 10px; }
  .dues-h { font-size:13px; font-weight:700; margin:0; color:#0f172a; }
  .dues-sec-head { display:flex; justify-content:space-between; align-items:baseline; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
  .dues-sec-head span { font-size:11.5px; color:#64748b; }
  .dues-table { width:100%; border-collapse:collapse; font-size:12px; min-width:960px; }
  .dues-table th { text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#64748b; background:#f8fafc; padding:10px 12px; border-bottom:1px solid #e2e8f0; white-space:nowrap; position:sticky; top:0; }
  .dues-table td { padding:11px 12px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
  .dues-table tbody tr:hover td { background:#f8fafc; }
  .dues-table td.num, .dues-table th.num { text-align:right; white-space:nowrap; }
  .dues-table tfoot td { font-weight:700; background:#f8fafc; border-top:1px solid #e2e8f0; }
  .dues-scroll { overflow-x:auto; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
  .dues-sub { display:block; font-size:10.5px; color:#94a3b8; margin-top:2px; }
  .dues-pill { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; }
  .dues-pill.overdue, .dues-pill.Poor { background:#fff1f2; color:#9f1239; }
  .dues-pill.due-today, .dues-pill.Fair { background:#fffbeb; color:#92400e; }
  .dues-pill.upcoming { background:#eff6ff; color:#1e40af; }
  .dues-pill.Good { background:#ecfdf5; color:#065f46; }
  .dues-pill.New { background:#f1f5f9; color:#475569; }
  .dues-tag { display:inline-block; font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:#fff1f2; color:#9f1239; margin-top:3px; }
  .dues-empty { text-align:center; padding:26px; color:#94a3b8; }
  .dues-bar-rate { height:6px; border-radius:3px; background:#f1f5f9; overflow:hidden; margin-top:4px; min-width:80px; }
  .dues-bar-rate i { display:block; height:100%; background:#10b981; }
  .dues-pager { display:flex; justify-content:space-between; align-items:center; padding:10px 4px 0; font-size:12px; color:#64748b; flex-wrap:wrap; gap:8px; }
  .dues-pager button { min-width:32px; height:30px; padding:0 10px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; font:inherit; font-size:12px; font-weight:600; cursor:pointer; margin-left:4px; }
  .dues-pager button:disabled { opacity:.4; cursor:not-allowed; }
  @media (max-width: 720px) { .dues-bar .grow { min-width:100%; } }
  `;

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const php = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtDate = (d) => {
    if (!d) return '—';
    const [y, m, day] = d.split('-').map(Number);
    return new Date(y, m - 1, day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };
  const STATUS = { overdue: 'Overdue', 'due-today': 'Due today', upcoming: 'Upcoming' };
  const PER_PAGE = 25;

  // ---- CSV helpers (Excel-friendly: BOM, CRLF, numbers without ₱/commas,
  // phone numbers kept as text so the leading 0 survives) ----
  const csvCell = (v) => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
  const csvLine = (arr) => arr.map(csvCell).join(',');
  const money = (n) => (Number(n) || 0).toFixed(2);
  const asText = (v) => (v ? '="' + String(v).replace(/"/g, '') + '"' : '');
  function saveBlob(filename, blob) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  // Reports are built as CSV lines, then saved as a real .xlsx (CSV cannot
  // carry column widths, so spreadsheets open it all squeezed). Falls back to
  // plain CSV if the SheetJS library cannot be loaded.
  const XLSX_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
  let xlsxLoading = null;
  function loadXlsx() {
    if (window.XLSX) return Promise.resolve(window.XLSX);
    if (!xlsxLoading) {
      xlsxLoading = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = XLSX_SRC;
        s.onload = () => (window.XLSX ? resolve(window.XLSX) : reject(new Error('XLSX missing')));
        s.onerror = () => { xlsxLoading = null; reject(new Error('XLSX load failed')); };
        document.head.appendChild(s);
      });
    }
    return xlsxLoading;
  }

  function parseCsvLine(line) {
    const out = [];
    let i = 0;
    const n = line.length;
    while (i <= n) {
      let v = '';
      if (line[i] === '"') {
        i++;
        while (i < n) {
          if (line[i] === '"') {
            if (line[i + 1] === '"') { v += '"'; i += 2; continue; }
            i++; break;
          }
          v += line[i++];
        }
        while (i < n && line[i] !== ',') i++;
      } else {
        const j = line.indexOf(',', i);
        const end = j === -1 ? n : j;
        v = line.slice(i, end);
        i = end;
      }
      out.push(v);
      i++; // skip comma
    }
    return out;
  }

  // CSV text value -> typed spreadsheet cell
  function toCell(v) {
    const t = /^="(.*)"$/.exec(v);
    if (t) return { t: 's', v: t[1] };                                   // forced text (phone)
    if (/^-?(0|[1-9]\d*)$/.test(v)) return { t: 'n', v: Number(v) };       // counts
    if (/^-?(0|[1-9]\d*)\.\d+$/.test(v)) return { t: 'n', v: Number(v), z: '#,##0.00' }; // money
    const p = /^(-?\d+(?:\.\d+)?)%$/.exec(v);
    if (p) return { t: 'n', v: Number(p[1]) / 100, z: '0.0%' };
    return { t: 's', v: v };
  }

  function cellWidth(c) {
    if (c.t === 'n') {
      if (c.z === '#,##0.00') return Math.abs(c.v).toFixed(2).length + Math.floor(String(Math.trunc(Math.abs(c.v))).length / 3) + 1;
      if (c.z === '0.0%') return 7;
      return String(c.v).length;
    }
    return String(c.v).length;
  }

  function buildXlsx(XLSX, lines, sheetName) {
    const rows = lines.map(l => parseCsvLine(l).map(toCell));
    const ws = {};
    const widths = [];
    let maxC = 0;
    rows.forEach((row, r) => {
      // Single-cell rows (titles like "CLIENTS DUE (90 …)") may spill into
      // the next columns, so they don't widen column A.
      const filled = row.filter(c => c.v !== '').length;
      row.forEach((c, ci) => {
        if (c.v === '') return;
        ws[XLSX.utils.encode_cell({ r, c: ci })] = c;
        maxC = Math.max(maxC, ci);
        if (filled > 1) widths[ci] = Math.max(widths[ci] || 0, cellWidth(c));
      });
    });
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: Math.max(rows.length - 1, 0), c: maxC } });
    ws['!cols'] = Array.from({ length: maxC + 1 }, (_, i) => ({ wch: Math.min(45, Math.max(8, (widths[i] || 8) + 2)) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, (sheetName || 'Report').slice(0, 31));
    return XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
  }

  function downloadCsv(filename, lines) {
    const base = filename.replace(/\.csv$/i, '');
    const csvFallback = () => saveBlob(base + '.csv',
      new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    return loadXlsx().then(XLSX => {
      const sheet = /^(\w+)/.exec(base);
      const data = buildXlsx(XLSX, lines, sheet ? sheet[1].charAt(0).toUpperCase() + sheet[1].slice(1) : 'Report');
      saveBlob(base + '.xlsx', new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
    }).catch(csvFallback);
  }

  function injectCss() {
    if (document.getElementById('ihc-dues-css')) return;
    const st = document.createElement('style');
    st.id = 'ihc-dues-css';
    st.textContent = CSS;
    document.head.appendChild(st);
  }

  function mount(root, opts) {
    injectCss();
    const o = Object.assign({ apiBase: '', officerId: '', showClerks: false }, opts || {});
    const state = { period: 'week', from: '', to: '', includeOverdue: false, officer: o.officerId || '', perf: '', q: '', data: null, page: 1, allClerks: null };

    root.innerHTML =
      '<div class="dues-wrap">' +
        '<div class="dues-top">' +
          '<div class="dues-bar">' +
            '<div class="dues-seg" data-el="seg">' +
              '<button type="button" data-p="week" class="on">This week</button>' +
              '<button type="button" data-p="month">This month</button>' +
              '<button type="button" data-p="custom">Custom</button>' +
            '</div>' +
            '<span data-el="custom" hidden><input type="date" data-el="from" aria-label="From"> – <input type="date" data-el="to" aria-label="To"></span>' +
            '<span class="dues-range" data-el="range">…</span>' +
          '</div>' +
          '<div class="dues-bar">' +
            '<button type="button" class="dues-btn" data-el="refresh">↻ Refresh</button>' +
            '<button type="button" class="dues-btn primary" data-el="csv">⭳ Export Excel</button>' +
          '</div>' +
        '</div>' +
        '<div class="dues-bar">' +
          (o.showClerks ? '<select data-el="officer" aria-label="Clerk"><option value="">All clerks</option></select>' : '') +
          '<select data-el="perf" aria-label="Payment performance"><option value="">All performance</option><option value="Good">Good payers</option><option value="Fair">Fair</option><option value="Poor">Poor</option><option value="New">New (no history)</option></select>' +
          '<label class="chk" data-el="overdueLbl"><input type="checkbox" data-el="overdue"> Include overdue from earlier</label>' +
          '<input type="search" class="grow" data-el="q" placeholder="Search client, account no., Excel code or property">' +
        '</div>' +
        '<div class="dues-kpis" data-el="kpis"></div>' +
        '<div class="dues-note" data-el="note" hidden></div>' +
        (o.showClerks ? '<div><div class="dues-sec-head"><p class="dues-h">Collection performance per clerk</p><span data-el="clerksSub"></span></div><div class="dues-scroll"><table class="dues-table" style="min-width:820px;"><thead><tr><th>Clerk</th><th class="num">To collect</th><th class="num">Paid</th><th class="num">Still unpaid</th><th>Collection rate</th><th class="num">Overdue from earlier</th></tr></thead><tbody data-el="clerks"></tbody></table></div></div>' : '') +
        '<div><div class="dues-sec-head"><p class="dues-h" data-el="listTitle">Clients due</p><span data-el="listSub"></span></div><div class="dues-scroll"><table class="dues-table"><thead><tr>' +
          '<th>Due date</th><th>Client</th><th>Property</th><th>Installment</th><th class="num">Amount due</th><th>Status</th><th>Payment performance</th>' +
          (o.showClerks ? '<th>Clerk</th>' : '') +
        '</tr></thead><tbody data-el="rows"></tbody><tfoot data-el="foot"></tfoot></table></div><div data-el="pager"></div></div>' +
      '</div>';

    const $ = (n) => root.querySelector('[data-el="' + n + '"]');
    const cols = o.showClerks ? 8 : 7;

    function filteredRows() {
      const d = state.data;
      if (!d) return [];
      const q = state.q.toLowerCase();
      return d.rows.filter(r =>
        (!state.perf || r.performance.label === state.perf) &&
        (!q || [r.client, r.accountCode, r.legacyCode, r.property].some(v => String(v || '').toLowerCase().includes(q))));
    }

    function periodLabel() {
      const d = state.data;
      return d ? fmtDate(d.period.from) + ' – ' + fmtDate(d.period.to) : '';
    }

    function render() {
      const d = state.data;
      if (!d) return;
      $('range').textContent = periodLabel();
      const t = d.totals;
      const inc = !!t.includesOverdue;
      const rate = t.collectionRate == null ? null : t.collectionRate;
      $('kpis').innerHTML = [
        { k: '#3b82f6', label: inc ? 'To collect (incl. overdue)' : 'Due in period', amount: php(t.dueAmount),
          sub: t.dueCount + ' installment(s)' + (inc ? ' • ' + php(t.periodDueAmount) + ' this period + ' + php(t.carriedAmount) + ' overdue' : '') },
        { k: '#10b981', label: 'Paid', amount: php(t.paidAmount), sub: t.paidCount + ' installment(s) fully paid' },
        { k: '#f59e0b', label: 'Still unpaid', amount: php(t.unpaidAmount), sub: t.unpaidCount + ' installment(s)' + (inc ? ' incl. overdue' : '') },
        { k: '#8b5cf6', label: 'Collection rate', amount: rate == null ? '—' : rate + '%', sub: 'paid ÷ ' + (inc ? 'to collect' : 'due in period'), meter: rate },
        { k: '#f43f5e', label: 'Overdue from earlier', amount: php(t.carriedAmount), sub: t.carriedCount + ' installment(s)', tag: inc ? 'included' : '' },
      ].map(c => '<div class="dues-kpi" style="--k:' + c.k + ';"><small>' + esc(c.label) + (c.tag ? '<em>' + esc(c.tag) + '</em>' : '') + '</small>' +
        '<strong>' + esc(c.amount) + '</strong><span>' + esc(c.sub) + '</span>' +
        (c.meter != null ? '<div class="meter"><i style="width:' + Math.min(100, c.meter) + '%"></i></div>' : '') + '</div>').join('');

      $('note').hidden = !inc;
      if (inc) $('note').textContent = 'Overdue installments from before ' + fmtDate(d.period.from) + ' are included in the cards and the list (' + t.carriedCount + ' installment(s), ' + php(t.carriedAmount) + ').';

      if (o.showClerks) {
        const sel = $('officer');
        const keep = state.officer;
        sel.innerHTML = ['<option value="">All clerks</option>'].concat((state.allClerks || d.clerks).filter(c => c.officerId).map(c =>
          '<option value="' + esc(c.officerId) + '">' + esc(c.name) + '</option>')).join('');
        sel.value = keep;
        $('clerksSub').textContent = periodLabel() + (inc ? ' • overdue from earlier included' : '');
        $('clerks').innerHTML = d.clerks.length ? d.clerks.map(c => {
          const r = c.collectionRate;
          return '<tr><td><strong>' + esc(c.name) + '</strong></td>' +
            '<td class="num"><strong>' + php(c.dueAmount) + '</strong><span class="dues-sub">' + c.dueCount + ' inst.</span></td>' +
            '<td class="num">' + php(c.paidAmount) + '<span class="dues-sub">' + c.paidCount + ' inst.</span></td>' +
            '<td class="num">' + php(c.unpaidAmount) + '<span class="dues-sub">' + c.unpaidCount + ' inst.</span></td>' +
            '<td>' + (r == null ? '—' : '<strong>' + r + '%</strong><div class="dues-bar-rate"><i style="width:' + Math.min(100, r) + '%"></i></div>') + '</td>' +
            '<td class="num">' + php(c.carriedAmount) + '<span class="dues-sub">' + c.carriedCount + ' inst.' + (inc ? ' • included' : '') + '</span></td></tr>';
        }).join('') : '<tr><td colspan="6" class="dues-empty">No dues in this period.</td></tr>';
      }

      const all = filteredRows();
      const pages = Math.max(1, Math.ceil(all.length / PER_PAGE));
      if (state.page > pages) state.page = pages;
      const rows = all.slice((state.page - 1) * PER_PAGE, state.page * PER_PAGE);
      const totalDue = all.reduce((s, r) => s + r.amountDue, 0);
      $('listTitle').textContent = 'Clients due — ' + all.length + ' unpaid installment(s)';
      $('listSub').textContent = (inc ? 'including overdue from earlier • ' : '') + (state.perf || state.q ? 'filtered • ' : '') + 'total ' + php(totalDue);
      $('pager').innerHTML = all.length > PER_PAGE
        ? '<div class="dues-pager"><span>Showing ' + ((state.page - 1) * PER_PAGE + 1) + '–' + Math.min(all.length, state.page * PER_PAGE) + ' of ' + all.length + '</span><div>' +
          '<button type="button" data-go="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + '>‹ Prev</button>' +
          '<span style="margin:0 6px;">Page ' + state.page + ' of ' + pages + '</span>' +
          '<button type="button" data-go="' + (state.page + 1) + '"' + (state.page >= pages ? ' disabled' : '') + '>Next ›</button></div></div>'
        : '';
      $('foot').innerHTML = all.length
        ? '<tr><td colspan="4">Total (' + all.length + ' installment' + (all.length === 1 ? '' : 's') + (pages > 1 ? ', all pages' : '') + ')</td><td class="num">' + php(totalDue) + '</td><td colspan="' + (cols - 5) + '"></td></tr>'
        : '';
      $('rows').innerHTML = rows.length ? rows.map(r => {
        const p = r.performance;
        const perfDetail = p.onTimeRate == null ? 'No payment history yet' : p.onTimeRate + '% on time • ' + p.onTime + ' on time, ' + p.late + ' late, ' + p.missed + ' missed';
        const contact = [r.phone, r.email].filter(Boolean).join(' • ') || 'No contact details';
        return '<tr>' +
          '<td style="white-space:nowrap;"><strong>' + esc(fmtDate(r.dueDate)) + '</strong>' + (r.carried ? '<br><span class="dues-tag">from earlier</span>' : '') + '</td>' +
          '<td><strong>' + esc(r.client) + '</strong><span class="dues-sub">' + esc(r.accountCode) + (r.legacyCode ? ' • ' + esc(r.legacyCode) : '') + '</span><span class="dues-sub">' + esc(contact) + '</span></td>' +
          '<td style="max-width:260px;">' + esc(r.property || '—') + '</td>' +
          '<td style="white-space:nowrap;">' + esc(r.installment) + '</td>' +
          '<td class="num"><strong>' + php(r.amountDue) + '</strong>' + (r.partial ? '<span class="dues-sub">partial — of ' + php(r.fullAmount) + '</span>' : '') + '</td>' +
          '<td><span class="dues-pill ' + r.status + '">' + esc(STATUS[r.status] || r.status) + '</span>' + (r.daysOverdue ? '<span class="dues-sub">' + r.daysOverdue + ' day(s) late</span>' : '') + '</td>' +
          '<td><span class="dues-pill ' + esc(p.label) + '">' + esc(p.label) + '</span><span class="dues-sub">' + esc(perfDetail) + '</span></td>' +
          (o.showClerks ? '<td>' + esc(r.clerk) + '</td>' : '') +
          '</tr>';
      }).join('') : '<tr><td colspan="' + cols + '" class="dues-empty">Nobody left to collect in this period' + (state.perf || state.q ? ' for this filter' : '') + '. 🎉</td></tr>';
    }

    async function refresh() {
      if (state.period === 'custom' && (!state.from || !state.to)) {
        $('rows').innerHTML = '<tr><td colspan="' + cols + '" class="dues-empty">Choose a From and To date.</td></tr>';
        $('range').textContent = 'Choose dates';
        return;
      }
      state.page = 1;
      $('range').textContent = 'Loading…';
      const qs = new URLSearchParams({ action: 'dues', period: state.period, includeOverdue: state.includeOverdue ? '1' : '0', _: String(Date.now()) });
      if (state.period === 'custom') { qs.set('from', state.from); qs.set('to', state.to); }
      if (state.officer) qs.set('officerId', state.officer);
      try {
        const res = await fetch(o.apiBase + '/php/api_admin.php?' + qs.toString());
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not load dues.');
        // Keep the full clerk list for the dropdown while filtered to one clerk.
        if (o.showClerks && !state.officer) state.allClerks = data.clerks;
        state.data = data;
        render();
      } catch (err) {
        $('range').textContent = '—';
        $('rows').innerHTML = '<tr><td colspan="' + cols + '" class="dues-empty">' + esc(err.message) + '</td></tr>';
      }
    }

    // A report-style CSV: title + filters, summary, per-clerk (admin), then
    // every client row (all pages, current filters) with a total line.
    function exportCsv() {
      const d = state.data;
      if (!d) return;
      const t = d.totals;
      const inc = !!t.includesOverdue;
      const rows = filteredRows();
      const clerkName = o.showClerks
        ? (state.officer ? ((state.allClerks || d.clerks).find(c => c.officerId === state.officer) || {}).name || state.officer : 'All clerks')
        : ((d.clerks[0] && d.clerks[0].name) || '');
      const now = new Date();
      const L = [];
      L.push(csvLine(['DUES & PERFORMANCE REPORT']));
      L.push(csvLine(['Period', periodLabel()]));
      L.push(csvLine(['Clerk', clerkName]));
      L.push(csvLine(['Overdue from earlier', inc ? 'Included' : 'Not included']));
      L.push(csvLine(['Filters', 'Performance: ' + (state.perf || 'All') + (state.q ? '; Search: ' + state.q : '')]));
      L.push(csvLine(['Generated', now.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })]));
      L.push('');
      L.push(csvLine(['SUMMARY', 'Installments', 'Amount (PHP)']));
      L.push(csvLine([inc ? 'To collect (period + overdue)' : 'Due in period', t.dueCount, money(t.dueAmount)]));
      if (inc) L.push(csvLine(['   of which due in period', t.periodDueCount, money(t.periodDueAmount)]));
      L.push(csvLine([inc ? '   of which overdue from earlier' : 'Overdue from earlier (not included)', t.carriedCount, money(t.carriedAmount)]));
      L.push(csvLine(['Paid', t.paidCount, money(t.paidAmount)]));
      L.push(csvLine(['Still unpaid', t.unpaidCount, money(t.unpaidAmount)]));
      L.push(csvLine(['Collection rate', '', t.collectionRate == null ? '' : t.collectionRate + '%']));
      if (o.showClerks && d.clerks.length) {
        L.push('');
        L.push(csvLine(['PER CLERK', 'To collect (inst.)', 'To collect (PHP)', 'Paid (inst.)', 'Paid (PHP)', 'Unpaid (inst.)', 'Unpaid (PHP)', 'Collection rate', 'Overdue from earlier (inst.)', 'Overdue from earlier (PHP)']));
        d.clerks.forEach(c => L.push(csvLine([c.name, c.dueCount, money(c.dueAmount), c.paidCount, money(c.paidAmount), c.unpaidCount, money(c.unpaidAmount),
          c.collectionRate == null ? '' : c.collectionRate + '%', c.carriedCount, money(c.carriedAmount)])));
      }
      L.push('');
      L.push(csvLine(['CLIENTS DUE (' + rows.length + ' unpaid installment' + (rows.length === 1 ? '' : 's') + ')']));
      L.push(csvLine(['No.', 'Due date', 'Client', 'Account no.', 'Excel code', 'Contact no.', 'Email', 'Property', 'Installment', 'Amount due (PHP)', 'Full installment (PHP)',
        'Status', 'Days late', 'From earlier period', 'Performance', 'On-time %', 'On time', 'Late', 'Missed'].concat(o.showClerks ? ['Clerk'] : [])));
      let total = 0;
      rows.forEach((r, i) => {
        total += r.amountDue;
        const p = r.performance;
        L.push([csvCell(i + 1), csvCell(fmtDate(r.dueDate)), csvCell(r.client), csvCell(r.accountCode), csvCell(r.legacyCode || ''),
          r.phone ? asText(r.phone) : csvCell(''), csvCell(r.email), csvCell(r.property), csvCell(r.installment),
          csvCell(money(r.amountDue)), csvCell(money(r.fullAmount)), csvCell(STATUS[r.status] || r.status), csvCell(r.daysOverdue || 0),
          csvCell(r.carried ? 'Yes' : 'No'), csvCell(p.label), csvCell(p.onTimeRate == null ? '' : p.onTimeRate + '%'),
          csvCell(p.onTime), csvCell(p.late), csvCell(p.missed)].concat(o.showClerks ? [csvCell(r.clerk)] : []).join(','));
      });
      L.push(csvLine(['', 'TOTAL', '', '', '', '', '', '', '', money(total)]));
      const who = clerkName ? '_' + clerkName.replace(/[^A-Za-z0-9]+/g, '-') : '';
      downloadCsv('dues' + who + '_' + d.period.from + '_to_' + d.period.to + (inc ? '_incl-overdue' : '') + '.csv', L);
    }

    $('seg').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-p]');
      if (!b) return;
      state.period = b.dataset.p;
      $('seg').querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
      $('custom').hidden = state.period !== 'custom';
      refresh();
    });
    $('from').addEventListener('change', (e) => { state.from = e.target.value; refresh(); });
    $('to').addEventListener('change', (e) => { state.to = e.target.value; refresh(); });
    $('overdue').addEventListener('change', (e) => {
      state.includeOverdue = e.target.checked;
      $('overdueLbl').classList.toggle('on', e.target.checked);
      refresh();
    });
    $('perf').addEventListener('change', (e) => { state.perf = e.target.value; state.page = 1; render(); });
    $('q').addEventListener('input', (e) => { state.q = e.target.value.trim(); state.page = 1; render(); });
    $('refresh').addEventListener('click', refresh);
    $('csv').addEventListener('click', exportCsv);
    $('pager').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-go]');
      if (!b || b.disabled) return;
      state.page = Number(b.dataset.go);
      render();
      $('listTitle').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    if (o.showClerks) $('officer').addEventListener('change', (e) => { state.officer = e.target.value; refresh(); });

    refresh();
    return { refresh };
  }

  window.IHCDues = { mount, csv: { cell: csvCell, line: csvLine, money, asText, download: downloadCsv } };
})();
