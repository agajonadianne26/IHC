/*
 * Clerk Reports — one page, three tabs, all scoped to the signed-in clerk:
 *   Dues & Performance  → js/dues-report.js (api_admin.php?action=dues)
 *   Aging of Accounts   → api_admin.php?action=portfolio_report (aging)
 *   Collections         → same endpoint (monthly due vs collected + receipt log)
 *
 *   IHCClerkReports.mount(el, { apiBase, officerId }) → { refresh(), show(tab) }
 * Needs js/dues-report.js loaded first (it also injects the shared styles).
 */
(function () {
  'use strict';

  const CSS = `
  .crep-tabs { display:flex; gap:4px; border-bottom:1px solid #e2e8f0; margin-bottom:16px; flex-wrap:wrap; }
  .crep-tabs button { border:0; background:none; padding:10px 14px; font:inherit; font-size:13px; font-weight:600; color:#64748b; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; }
  .crep-tabs button.on { color:#0f172a; border-bottom-color:#10b981; }
  .crep-pane[hidden] { display:none; }
  .crep-grid2 { display:grid; grid-template-columns: minmax(0,1.2fr) minmax(0,1fr); gap:14px; }
  @media (max-width: 1100px) { .crep-grid2 { grid-template-columns: 1fr; } }
  .crep-card { border:1px solid #e2e8f0; border-radius:10px; background:#fff; padding:14px; }
  .crep-card h4 { margin:0 0 10px; font-size:13px; }
  .crep-bars { display:flex; flex-direction:column; gap:8px; }
  .crep-bar { display:grid; grid-template-columns: 130px 1fr 150px; gap:10px; align-items:center; font-size:12px; cursor:pointer; padding:4px 6px; border-radius:6px; }
  .crep-bar:hover, .crep-bar.on { background:#f1f5f9; }
  .crep-bar .track { height:10px; background:#f1f5f9; border-radius:5px; overflow:hidden; }
  .crep-bar .fill { height:100%; border-radius:5px; }
  .crep-bar .num { text-align:right; color:#334155; }
  .crep-bar .num small { display:block; color:#94a3b8; font-size:10.5px; }
  .crep-months { display:flex; align-items:flex-end; gap:12px; height:170px; padding:8px 4px 0; }
  .crep-month { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; font-size:11px; color:#64748b; height:100%; justify-content:flex-end; }
  .crep-month .pair { display:flex; gap:3px; align-items:flex-end; height:120px; }
  .crep-month .pair i { width:14px; border-radius:3px 3px 0 0; display:block; }
  .crep-legend { display:flex; gap:14px; font-size:11px; color:#64748b; margin-top:8px; }
  .crep-legend span::before { content:''; display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:5px; vertical-align:-1px; background:var(--c); }
  .crep-pager { display:flex; justify-content:space-between; align-items:center; padding:10px 4px 0; font-size:12px; color:#64748b; flex-wrap:wrap; gap:8px; }
  .crep-pager button { min-width:32px; height:30px; padding:0 10px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; font:inherit; font-size:12px; font-weight:600; cursor:pointer; margin-left:4px; }
  .crep-pager button:disabled { opacity:.4; cursor:not-allowed; }
  `;
  const COLORS = { current: '#10b981', '1-30': '#f59e0b', '31-60': '#fb923c', '61-90': '#f97316', '91-120': '#ef4444', '120+': '#b91c1c', settled: '#94a3b8' };
  const PER_PAGE = 25;

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const php = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtDate = (d) => {
    if (!d) return '—';
    const [y, m, day] = d.split('-').map(Number);
    return new Date(y, m - 1, day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };
  const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

  function downloadCsv(name, rows) {
    const csv = rows.map(l => l.map(v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"').join(',')).join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  function pager(total, page, onGo) {
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (total <= PER_PAGE) return '';
    const a = (page - 1) * PER_PAGE + 1, b = Math.min(total, page * PER_PAGE);
    setTimeout(onGo, 0);
    return '<div class="crep-pager"><span>Showing ' + a + '–' + b + ' of ' + total + '</span><div>' +
      '<button type="button" data-go="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>‹ Prev</button>' +
      '<span style="margin:0 6px;">Page ' + page + ' of ' + pages + '</span>' +
      '<button type="button" data-go="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>Next ›</button></div></div>';
  }

  function mount(root, opts) {
    if (!document.getElementById('ihc-crep-css')) {
      const st = document.createElement('style');
      st.id = 'ihc-crep-css';
      st.textContent = CSS;
      document.head.appendChild(st);
    }
    const o = Object.assign({ apiBase: '', officerId: '' }, opts || {});
    const now = new Date();
    const state = {
      tab: 'dues', data: null, bucket: '', agingPage: 1, collPage: 1,
      from: ymd(new Date(now.getFullYear(), now.getMonth(), 1)),
      to: ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
    };

    root.innerHTML =
      '<div class="crep-tabs" data-el="tabs">' +
        '<button type="button" data-t="dues" class="on">Dues &amp; Performance</button>' +
        '<button type="button" data-t="aging">Aging of Accounts</button>' +
        '<button type="button" data-t="collections">Collections</button>' +
      '</div>' +
      '<div class="crep-pane" data-pane="dues"></div>' +
      '<div class="crep-pane" data-pane="aging" hidden>' +
        '<div class="dues-kpis" data-el="agingKpis" style="margin-bottom:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));"></div>' +
        '<div class="crep-card"><h4>Accounts by days overdue <span style="font-weight:400;color:#94a3b8;">(click a bracket to filter the list below)</span></h4><div class="crep-bars" data-el="bars"></div>' +
          '<p style="font-size:11px;color:#94a3b8;line-height:1.5;margin:10px 0 0;">Each account is placed by its <strong>oldest unpaid installment</strong>. Overdue amount = everything past due today; Balance = whole remaining balance. Accounts return to Current once their overdue installments are paid.</p></div>' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 8px;gap:8px;flex-wrap:wrap;">' +
          '<p class="dues-h" data-el="agingTitle" style="margin:0;"></p>' +
          '<div class="dues-bar"><input type="search" data-el="agingQ" placeholder="Search client or code"><button type="button" class="dues-btn" data-el="agingRefresh">↻ Refresh</button><button type="button" class="dues-btn" data-el="agingCsv">Export Excel</button></div>' +
        '</div>' +
        '<div class="dues-scroll"><table class="dues-table"><thead><tr><th>Client</th><th>Property</th><th>Oldest unpaid due</th><th>Days overdue</th><th>Installments overdue</th><th>Overdue amount</th><th>Balance</th></tr></thead><tbody data-el="agingRows"></tbody></table></div>' +
        '<div data-el="agingPager"></div>' +
      '</div>' +
      '<div class="crep-pane" data-pane="collections" hidden>' +
        '<div class="crep-grid2" style="margin-bottom:14px;">' +
          '<div class="crep-card"><h4>Due vs collected — last 6 months</h4><div class="crep-months" data-el="months"></div>' +
            '<div class="crep-legend"><span style="--c:#cbd5e1">Due</span><span style="--c:#10b981">Collected</span></div></div>' +
          '<div class="crep-card"><h4>Monthly summary</h4><div class="dues-scroll" style="border:0;"><table class="dues-table" style="min-width:0;"><thead><tr><th>Month</th><th>Due</th><th>Collected</th><th>Rate</th></tr></thead><tbody data-el="monthRows"></tbody></table></div></div>' +
        '</div>' +
        '<div class="dues-bar" style="margin-bottom:10px;">' +
          '<span class="dues-range">Receipts from</span><input type="date" data-el="from"><span class="dues-range">to</span><input type="date" data-el="to">' +
          '<input type="search" class="grow" data-el="collQ" placeholder="Search client, code or OR">' +
          '<button type="button" class="dues-btn" data-el="collRefresh">↻ Refresh</button>' +
          '<button type="button" class="dues-btn" data-el="collCsv">Export Excel</button>' +
        '</div>' +
        '<div class="dues-kpis" data-el="collKpis" style="margin-bottom:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));"></div>' +
        '<div class="dues-scroll"><table class="dues-table"><thead><tr><th>Date</th><th>Client</th><th>OR / Invoice</th><th>Method</th><th>Amount</th><th>Remarks</th></tr></thead><tbody data-el="collRows"></tbody></table></div>' +
        '<div data-el="collPager"></div>' +
      '</div>';

    const $ = (n) => root.querySelector('[data-el="' + n + '"]');
    const pane = (t) => root.querySelector('[data-pane="' + t + '"]');
    let dues = null;
    $('from').value = state.from;
    $('to').value = state.to;

    function agingFiltered() {
      const d = state.data;
      if (!d) return [];
      const q = ($('agingQ').value || '').trim().toLowerCase();
      return d.aging.rows.filter(r => (!state.bucket || r.bucket === state.bucket) &&
        (!q || [r.client, r.accountCode, r.legacyCode].some(v => String(v || '').toLowerCase().includes(q))));
    }
    function collFiltered() {
      const d = state.data;
      if (!d) return [];
      const q = ($('collQ').value || '').trim().toLowerCase();
      return d.collections.rows.filter(r => !q || [r.client, r.accountCode, r.orNumber].some(v => String(v || '').toLowerCase().includes(q)));
    }

    function renderAging() {
      const d = state.data;
      if (!d) return;
      const k = d.kpi;
      $('agingKpis').innerHTML = [
        ['Accounts', String(k.accounts), php(k.outstanding) + ' outstanding'],
        ['Overdue accounts', String(k.overdueAccounts), php(k.overdueAmount) + ' past due'],
        ['Current', String(d.aging.buckets.current.count), 'no overdue installment'],
        ['Over 90 days', String((d.aging.buckets['91-120'].count || 0) + (d.aging.buckets['120+'].count || 0)), 'need follow-up'],
      ].map(([a, b, c]) => '<div class="dues-kpi"><small>' + esc(a) + '</small><strong>' + esc(b) + '</strong><span>' + esc(c) + '</span></div>').join('');

      const order = ['current', '1-30', '31-60', '61-90', '91-120', '120+', 'settled'];
      const max = Math.max(1, ...order.map(key => d.aging.buckets[key].count));
      $('bars').innerHTML = order.map(key => {
        const b = d.aging.buckets[key];
        const pct = Math.round(b.count / max * 100);
        const clickable = key !== 'current' && key !== 'settled';
        return '<div class="crep-bar' + (state.bucket === key ? ' on' : '') + '" data-b="' + (clickable ? key : '') + '" style="' + (clickable ? '' : 'cursor:default;') + '">' +
          '<span>' + esc(b.label) + '</span>' +
          '<div class="track"><div class="fill" style="width:' + pct + '%;background:' + COLORS[key] + ';"></div></div>' +
          '<div class="num"><strong>' + b.count + '</strong> acct(s)' + (b.amount > 0 ? '<small>' + php(b.amount) + ' overdue</small>' : '') + '</div>' +
        '</div>';
      }).join('');

      const rows = agingFiltered();
      const pages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
      if (state.agingPage > pages) state.agingPage = pages;
      const slice = rows.slice((state.agingPage - 1) * PER_PAGE, state.agingPage * PER_PAGE);
      $('agingTitle').textContent = (state.bucket ? d.aging.buckets[state.bucket].label + ' — ' : 'All overdue accounts — ') + rows.length + ' account(s)' + (state.bucket ? '  (click the bracket again to clear)' : '');
      $('agingRows').innerHTML = slice.length ? slice.map(r =>
        '<tr><td><strong>' + esc(r.client) + '</strong><span class="dues-sub">' + esc(r.accountCode) + (r.legacyCode ? ' • ' + esc(r.legacyCode) : '') + (r.phone ? ' • ' + esc(r.phone) : '') + '</span></td>' +
        '<td>' + esc(r.property || '—') + '</td>' +
        '<td style="white-space:nowrap;">' + esc(fmtDate(r.oldestDue)) + '</td>' +
        '<td><span class="dues-pill ' + (r.daysOverdue > 90 ? 'Poor' : r.daysOverdue > 30 ? 'due-today' : 'upcoming') + '">' + r.daysOverdue + ' days</span></td>' +
        '<td>' + r.installmentsOverdue + '</td>' +
        '<td style="white-space:nowrap;"><strong>' + php(r.overdueAmount) + '</strong></td>' +
        '<td style="white-space:nowrap;">' + php(r.balance) + '</td></tr>').join('')
        : '<tr><td colspan="7" class="dues-empty">No overdue accounts' + (state.bucket ? ' in this bracket' : '') + '. 🎉</td></tr>';
      $('agingPager').innerHTML = pager(rows.length, state.agingPage, () => {
        $('agingPager').querySelectorAll('button[data-go]').forEach(b => b.onclick = () => { state.agingPage = Number(b.dataset.go); renderAging(); });
      });
    }

    function renderCollections() {
      const d = state.data;
      if (!d) return;
      const max = Math.max(1, ...d.months.map(m => Math.max(m.due, m.collected)));
      $('months').innerHTML = d.months.map(m =>
        '<div class="crep-month" title="' + esc(m.label + ': due ' + php(m.due) + ', collected ' + php(m.collected)) + '">' +
          '<div class="pair"><i style="height:' + Math.round(m.due / max * 120) + 'px;background:#cbd5e1;"></i><i style="height:' + Math.round(m.collected / max * 120) + 'px;background:#10b981;"></i></div>' +
          '<span>' + esc(m.label.split(' ')[0]) + '</span></div>').join('');
      $('monthRows').innerHTML = d.months.slice().reverse().map(m =>
        '<tr><td><strong>' + esc(m.label) + '</strong></td><td>' + php(m.due) + '</td><td>' + php(m.collected) + '</td><td>' + (m.rate == null ? '—' : m.rate + '%') + '</td></tr>').join('');

      const c = d.collections;
      const rows = collFiltered();
      const total = rows.reduce((s, r) => s + r.amount, 0);
      $('collKpis').innerHTML = [['Receipts', String(rows.length), fmtDate(c.from) + ' – ' + fmtDate(c.to)], ['Total collected', php(total), 'cash received in the range']]
        .concat(c.byMethod.slice(0, 2).map(m => [m.method, php(m.amount), 'by payment method']))
        .map(([a, b, x]) => '<div class="dues-kpi"><small>' + esc(a) + '</small><strong>' + esc(b) + '</strong><span>' + esc(x) + '</span></div>').join('');
      const pages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
      if (state.collPage > pages) state.collPage = pages;
      const slice = rows.slice((state.collPage - 1) * PER_PAGE, state.collPage * PER_PAGE);
      $('collRows').innerHTML = slice.length ? slice.map(r =>
        '<tr><td style="white-space:nowrap;">' + esc(fmtDate(r.date)) + '</td>' +
        '<td><strong>' + esc(r.client) + '</strong><span class="dues-sub">' + esc(r.accountCode) + '</span></td>' +
        '<td>' + esc(r.orNumber || '—') + '</td><td>' + esc(r.method) + '</td>' +
        '<td style="white-space:nowrap;"><strong>' + php(r.amount) + '</strong></td>' +
        '<td style="max-width:320px;">' + esc(r.remarks || '') + '</td></tr>').join('')
        : '<tr><td colspan="6" class="dues-empty">No payments in this range.</td></tr>';
      $('collPager').innerHTML = pager(rows.length, state.collPage, () => {
        $('collPager').querySelectorAll('button[data-go]').forEach(b => b.onclick = () => { state.collPage = Number(b.dataset.go); renderCollections(); });
      });
    }

    let portSeq = 0;
    async function loadPortfolio() {
      const seq = ++portSeq;
      const btns = [$('agingRefresh'), $('collRefresh')];
      btns.forEach(b => { b.classList.add('busy'); b.textContent = '↻ Refreshing…'; });
      const qs = new URLSearchParams({ action: 'portfolio_report', officerId: o.officerId, from: state.from, to: state.to, _: String(Date.now()) });
      try {
        const res = await fetch(o.apiBase + '/php/api_admin.php?' + qs.toString(), { cache: 'no-store' });
        const data = await res.json();
        if (seq !== portSeq) return;
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not load the report.');
        state.data = data;
        renderAging();
        renderCollections();
      } catch (err) {
        if (seq !== portSeq) return;
        $('agingRows').innerHTML = '<tr><td colspan="7" class="dues-empty">' + esc(err.message) + '</td></tr>';
        $('collRows').innerHTML = '<tr><td colspan="6" class="dues-empty">' + esc(err.message) + '</td></tr>';
      } finally {
        if (seq === portSeq) btns.forEach(b => { b.classList.remove('busy'); b.textContent = '↻ Refresh'; });
      }
    }

    function show(tab) {
      state.tab = tab;
      $('tabs').querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.t === tab));
      ['dues', 'aging', 'collections'].forEach(t => { pane(t).hidden = t !== tab; });
      if (tab === 'dues') {
        if (!dues && window.IHCDues) dues = window.IHCDues.mount(pane('dues'), { apiBase: o.apiBase, officerId: o.officerId });
      } else if (!state.data) {
        loadPortfolio();
      }
    }

    function refresh() {
      if (dues) dues.refresh();
      state.data = null;
      if (state.tab !== 'dues') loadPortfolio();
    }

    $('agingRefresh').addEventListener('click', () => { state.data = null; loadPortfolio(); });
    $('collRefresh').addEventListener('click', () => { state.data = null; loadPortfolio(); });
    $('tabs').addEventListener('click', (e) => { const b = e.target.closest('button[data-t]'); if (b) show(b.dataset.t); });
    $('bars').addEventListener('click', (e) => {
      const b = e.target.closest('.crep-bar[data-b]');
      if (!b || !b.dataset.b) return;
      state.bucket = state.bucket === b.dataset.b ? '' : b.dataset.b;
      state.agingPage = 1;
      renderAging();
    });
    $('agingQ').addEventListener('input', () => { state.agingPage = 1; renderAging(); });
    $('collQ').addEventListener('input', () => { state.collPage = 1; renderCollections(); });
    const onRange = () => {
      if (!$('from').value || !$('to').value) return;
      state.from = $('from').value; state.to = $('to').value; state.collPage = 1;
      loadPortfolio();
    };
    $('from').addEventListener('change', onRange);
    $('to').addEventListener('change', onRange);
    // Report-style CSVs (same helpers as Dues & Performance): title, summary,
    // then every row of the current filter with a total line.
    const C = window.IHCDues && window.IHCDues.csv;
    const stamp = () => new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    $('agingCsv').addEventListener('click', () => {
      const d = state.data;
      if (!d || !C) return;
      const rows = agingFiltered();
      const L = [C.line(['AGING OF ACCOUNTS REPORT']), C.line(['As of', fmtDate(d.today)]),
        C.line(['Bracket', state.bucket ? d.aging.buckets[state.bucket].label : 'All overdue accounts']), C.line(['Generated', stamp()]), '',
        C.line(['SUMMARY', 'Accounts', 'Overdue amount (PHP)', 'Balance (PHP)'])];
      ['current', '1-30', '31-60', '61-90', '91-120', '120+', 'settled'].forEach(k => {
        const b = d.aging.buckets[k];
        L.push(C.line([b.label, b.count, C.money(b.amount), C.money(b.balance)]));
      });
      L.push('', C.line(['OVERDUE ACCOUNTS (' + rows.length + ')']),
        C.line(['No.', 'Client', 'Account no.', 'Excel code', 'Contact no.', 'Property', 'Bracket', 'Oldest unpaid due', 'Days overdue', 'Installments overdue', 'Overdue amount (PHP)', 'Balance (PHP)']));
      let tOver = 0, tBal = 0;
      rows.forEach((r, i) => {
        tOver += r.overdueAmount; tBal += r.balance;
        L.push([C.cell(i + 1), C.cell(r.client), C.cell(r.accountCode), C.cell(r.legacyCode || ''), r.phone ? C.asText(r.phone) : C.cell(''), C.cell(r.property),
          C.cell(d.aging.buckets[r.bucket].label), C.cell(fmtDate(r.oldestDue)), C.cell(r.daysOverdue), C.cell(r.installmentsOverdue),
          C.cell(C.money(r.overdueAmount)), C.cell(C.money(r.balance))].join(','));
      });
      L.push(C.line(['', 'TOTAL', '', '', '', '', '', '', '', '', C.money(tOver), C.money(tBal)]));
      C.download('aging_' + d.today + (state.bucket ? '_' + state.bucket : '') + '.csv', L);
    });
    $('collCsv').addEventListener('click', () => {
      const d = state.data;
      if (!d || !C) return;
      const rows = collFiltered();
      const L = [C.line(['COLLECTIONS REPORT']), C.line(['Receipts from', fmtDate(d.collections.from) + ' – ' + fmtDate(d.collections.to)]),
        C.line(['Search', ($('collQ').value || '').trim() || '(none)']), C.line(['Generated', stamp()]), '',
        C.line(['MONTHLY SUMMARY', 'Due (PHP)', 'Collected (PHP)', 'Collection rate'])];
      d.months.forEach(m => L.push(C.line([m.label, C.money(m.due), C.money(m.collected), m.rate == null ? '' : m.rate + '%'])));
      L.push('', C.line(['BY PAYMENT METHOD', 'Amount (PHP)']));
      d.collections.byMethod.forEach(m => L.push(C.line([m.method, C.money(m.amount)])));
      L.push('', C.line(['RECEIPTS (' + rows.length + ')']), C.line(['No.', 'Date', 'Client', 'Account no.', 'OR / Invoice', 'Method', 'Amount (PHP)', 'Remarks']));
      let tot = 0;
      rows.forEach((r, i) => {
        tot += r.amount;
        L.push(C.line([i + 1, fmtDate(r.date), r.client, r.accountCode, r.orNumber, r.method, C.money(r.amount), r.remarks]));
      });
      L.push(C.line(['', 'TOTAL', '', '', '', '', C.money(tot)]));
      C.download('collections_' + state.from + '_to_' + state.to + '.csv', L);
    });

    show('dues');
    return { refresh, show };
  }

  window.IHCClerkReports = { mount };
})();
