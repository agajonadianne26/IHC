/*
 * Dues & Performance — shared by the admin Reports page and the clerk
 * "Dues & Performance" page. Data: php/api_admin.php?action=dues, which runs
 * the same ihc_schedule() as every ledger, so a posted payment removes its
 * row automatically on the next refresh.
 *
 *   IHCDues.mount(containerEl, { apiBase, officerId?: string, showClerks?: bool })
 *     officerId  → clerk view: locked to that officer, no clerk column/filter
 *     showClerks → admin view: clerk filter + per-clerk performance table
 *   returns { refresh() }
 */
(function () {
  'use strict';

  const CSS = `
  .dues-wrap { display:flex; flex-direction:column; gap:14px; }
  .dues-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  .dues-seg { display:inline-flex; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
  .dues-seg button { border:0; background:#fff; padding:0 14px; height:34px; font:inherit; font-size:12px; font-weight:600; cursor:pointer; color:#475569; }
  .dues-seg button.on { background:#0f172a; color:#fff; }
  .dues-bar input, .dues-bar select { height:34px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font:inherit; font-size:12px; background:#fff; }
  .dues-bar label.chk { display:inline-flex; gap:6px; align-items:center; font-size:12px; font-weight:600; color:#334155; cursor:pointer; }
  .dues-bar .grow { flex:1; min-width:180px; }
  .dues-btn { height:34px; padding:0 14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; font:inherit; font-size:12px; font-weight:600; cursor:pointer; }
  .dues-range { font-size:12px; color:#64748b; font-weight:600; }
  .dues-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
  .dues-kpi { border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; background:#fff; }
  .dues-kpi small { display:block; font-size:10.5px; color:#64748b; text-transform:uppercase; letter-spacing:.4px; font-weight:700; }
  .dues-kpi strong { display:block; font-size:17px; margin-top:2px; }
  .dues-kpi span { font-size:11px; color:#64748b; }
  .dues-h { font-size:13px; font-weight:700; margin:4px 0 0; }
  .dues-table { width:100%; border-collapse:collapse; font-size:12px; min-width:900px; }
  .dues-table th { text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#64748b; background:#f8fafc; padding:9px 12px; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
  .dues-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
  .dues-scroll { overflow-x:auto; border:1px solid #e2e8f0; border-radius:10px; background:#fff; }
  .dues-sub { display:block; font-size:10.5px; color:#94a3b8; margin-top:2px; }
  .dues-pill { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; }
  .dues-pill.overdue, .dues-pill.Poor { background:#fff1f2; color:#9f1239; }
  .dues-pill.due-today, .dues-pill.Fair { background:#fffbeb; color:#92400e; }
  .dues-pill.upcoming { background:#eff6ff; color:#1e40af; }
  .dues-pill.Good { background:#ecfdf5; color:#065f46; }
  .dues-pill.New { background:#f1f5f9; color:#475569; }
  .dues-empty { text-align:center; padding:22px; color:#94a3b8; }
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
    const state = { period: 'week', from: '', to: '', includeOverdue: false, officer: o.officerId || '', perf: '', q: '', data: null, loading: false, page: 1 };

    root.innerHTML =
      '<div class="dues-wrap">' +
        '<div class="dues-bar">' +
          '<div class="dues-seg" data-el="seg">' +
            '<button type="button" data-p="week" class="on">This week</button>' +
            '<button type="button" data-p="month">This month</button>' +
            '<button type="button" data-p="custom">Custom</button>' +
          '</div>' +
          '<span data-el="custom" hidden><input type="date" data-el="from"> – <input type="date" data-el="to"></span>' +
          '<span class="dues-range" data-el="range"></span>' +
        '</div>' +
        '<div class="dues-bar">' +
          (o.showClerks ? '<select data-el="officer"><option value="">All clerks</option></select>' : '') +
          '<select data-el="perf"><option value="">All performance</option><option value="Good">Good payers</option><option value="Fair">Fair</option><option value="Poor">Poor</option><option value="New">New (no history)</option></select>' +
          '<label class="chk"><input type="checkbox" data-el="overdue"> Include overdue from earlier</label>' +
          '<input type="search" class="grow" data-el="q" placeholder="Search client, code or property">' +
          '<button type="button" class="dues-btn" data-el="refresh">Refresh</button>' +
          '<button type="button" class="dues-btn" data-el="csv">Export CSV</button>' +
        '</div>' +
        '<div class="dues-kpis" data-el="kpis"></div>' +
        (o.showClerks ? '<div><p class="dues-h">Collection performance per clerk</p><div class="dues-scroll" style="margin-top:8px;"><table class="dues-table" style="min-width:760px;"><thead><tr><th>Clerk</th><th>Due in period</th><th>Paid</th><th>Still unpaid</th><th>Collection rate</th><th>Overdue from earlier</th></tr></thead><tbody data-el="clerks"></tbody></table></div></div>' : '') +
        '<div><p class="dues-h" data-el="listTitle">Clients due</p><div class="dues-scroll" style="margin-top:8px;"><table class="dues-table"><thead><tr>' +
          '<th>Due date</th><th>Client</th><th>Property</th><th>Installment</th><th>Amount due</th><th>Status</th><th>Payment performance</th>' +
          (o.showClerks ? '<th>Clerk</th>' : '') +
        '</tr></thead><tbody data-el="rows"></tbody></table></div><div data-el="pager"></div></div>' +
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

    function render() {
      const d = state.data;
      if (!d) return;
      $('range').textContent = fmtDate(d.period.from) + ' – ' + fmtDate(d.period.to);
      const t = d.totals;
      const rate = t.collectionRate == null ? '—' : t.collectionRate + '%';
      $('kpis').innerHTML = [
        ['Due in period', t.dueCount + ' installment(s)', php(t.dueAmount)],
        ['Paid', t.paidCount + ' installment(s)', php(t.paidAmount)],
        ['Still unpaid', t.unpaidCount + ' installment(s)', php(t.unpaidAmount)],
        ['Collection rate', rate, 'of the amount due in the period'],
        ['Overdue from earlier', t.carriedCount + ' installment(s)', php(t.carriedAmount)]
      ].map(([k, a, b]) => '<div class="dues-kpi"><small>' + esc(k) + '</small><strong>' + esc(a) + '</strong><span>' + esc(b) + '</span></div>').join('');

      if (o.showClerks) {
        const sel = $('officer');
        const keep = state.officer;
        const opts = ['<option value="">All clerks</option>'].concat((d.allClerks || d.clerks).filter(c => c.officerId).map(c =>
          '<option value="' + esc(c.officerId) + '">' + esc(c.name) + '</option>'));
        sel.innerHTML = opts.join('');
        sel.value = keep;
        $('clerks').innerHTML = d.clerks.length ? d.clerks.map(c => {
          const r = c.collectionRate == null ? null : c.collectionRate;
          return '<tr><td><strong>' + esc(c.name) + '</strong></td>' +
            '<td>' + c.dueCount + '<span class="dues-sub">' + php(c.dueAmount) + '</span></td>' +
            '<td>' + c.paidCount + '<span class="dues-sub">' + php(c.paidAmount) + '</span></td>' +
            '<td>' + c.unpaidCount + '<span class="dues-sub">' + php(c.unpaidAmount) + '</span></td>' +
            '<td>' + (r == null ? '—' : r + '%<div class="dues-bar-rate"><i style="width:' + r + '%"></i></div>') + '</td>' +
            '<td>' + c.carriedCount + '<span class="dues-sub">' + php(c.carriedAmount) + '</span></td></tr>';
        }).join('') : '<tr><td colspan="6" class="dues-empty">No dues in this period.</td></tr>';
      }

      const all = filteredRows();
      const pages = Math.max(1, Math.ceil(all.length / PER_PAGE));
      if (state.page > pages) state.page = pages;
      const rows = all.slice((state.page - 1) * PER_PAGE, state.page * PER_PAGE);
      $('listTitle').textContent = 'Clients due — ' + all.length + ' unpaid installment(s)' + (state.includeOverdue ? ' (including overdue from earlier)' : '');
      $('pager').innerHTML = all.length > PER_PAGE
        ? '<div class="dues-pager"><span>Showing ' + ((state.page - 1) * PER_PAGE + 1) + '–' + Math.min(all.length, state.page * PER_PAGE) + ' of ' + all.length + '</span><div>' +
          '<button type="button" data-go="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + '>‹ Prev</button>' +
          '<span style="margin:0 6px;">Page ' + state.page + ' of ' + pages + '</span>' +
          '<button type="button" data-go="' + (state.page + 1) + '"' + (state.page >= pages ? ' disabled' : '') + '>Next ›</button></div></div>'
        : '';
      $('rows').innerHTML = rows.length ? rows.map(r => {
        const p = r.performance;
        const perfDetail = p.onTimeRate == null ? 'No payment history yet' : p.onTimeRate + '% on time (' + p.onTime + ' on time, ' + p.late + ' late, ' + p.missed + ' missed)';
        const contact = [r.phone, r.email].filter(Boolean).join(' • ') || 'No contact details';
        return '<tr>' +
          '<td style="white-space:nowrap;"><strong>' + esc(fmtDate(r.dueDate)) + '</strong>' + (r.carried ? '<span class="dues-sub">from earlier period</span>' : '') + '</td>' +
          '<td><strong>' + esc(r.client) + '</strong><span class="dues-sub">' + esc(r.accountCode) + (r.legacyCode ? ' • ' + esc(r.legacyCode) : '') + '</span><span class="dues-sub">' + esc(contact) + '</span></td>' +
          '<td>' + esc(r.property || '—') + '</td>' +
          '<td style="white-space:nowrap;">' + esc(r.installment) + '</td>' +
          '<td style="white-space:nowrap;"><strong>' + php(r.amountDue) + '</strong>' + (r.partial ? '<span class="dues-sub">partial — of ' + php(r.fullAmount) + '</span>' : '') + '</td>' +
          '<td><span class="dues-pill ' + r.status + '">' + esc(STATUS[r.status] || r.status) + '</span>' + (r.daysOverdue ? '<span class="dues-sub">' + r.daysOverdue + ' day(s) late</span>' : '') + '</td>' +
          '<td><span class="dues-pill ' + esc(p.label) + '">' + esc(p.label) + '</span><span class="dues-sub">' + esc(perfDetail) + '</span></td>' +
          (o.showClerks ? '<td>' + esc(r.clerk) + '</td>' : '') +
          '</tr>';
      }).join('') : '<tr><td colspan="' + cols + '" class="dues-empty">Nobody left to collect in this period' + (state.perf || state.q ? ' for this filter' : '') + '.</td></tr>';
    }

    async function refresh() {
      if (state.period === 'custom' && (!state.from || !state.to)) {
        $('rows').innerHTML = '<tr><td colspan="' + cols + '" class="dues-empty">Choose a From and To date.</td></tr>';
        return;
      }
      state.loading = true;
      state.page = 1;
      const qs = new URLSearchParams({ action: 'dues', period: state.period, includeOverdue: state.includeOverdue ? '1' : '0', _: String(Date.now()) });
      if (state.period === 'custom') { qs.set('from', state.from); qs.set('to', state.to); }
      if (state.officer) qs.set('officerId', state.officer);
      try {
        const res = await fetch(o.apiBase + '/php/api_admin.php?' + qs.toString());
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not load dues.');
        // Keep the full clerk list for the dropdown even while filtered to one clerk.
        if (o.showClerks) {
          if (!state.officer) state.allClerks = data.clerks;
          data.allClerks = state.allClerks || data.clerks;
        }
        state.data = data;
        render();
      } catch (err) {
        $('rows').innerHTML = '<tr><td colspan="' + cols + '" class="dues-empty">' + esc(err.message) + '</td></tr>';
      } finally {
        state.loading = false;
      }
    }

    function exportCsv() {
      const rows = filteredRows();
      const head = ['Due date', 'Client', 'Account', 'Excel code', 'Phone', 'Email', 'Property', 'Installment', 'Amount due', 'Status', 'Days late', 'Performance', 'On-time %', 'Clerk'];
      const lines = [head].concat(rows.map(r => [r.dueDate, r.client, r.accountCode, r.legacyCode || '', r.phone, r.email, r.property, r.installment,
        r.amountDue.toFixed(2), STATUS[r.status] || r.status, r.daysOverdue, r.performance.label,
        r.performance.onTimeRate == null ? '' : r.performance.onTimeRate, r.clerk]));
      const csv = lines.map(l => l.map(v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"').join(',')).join('\r\n');
      const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      const p = state.data ? state.data.period : { from: '', to: '' };
      a.href = URL.createObjectURL(blob);
      a.download = 'dues_' + p.from + '_to_' + p.to + '.csv';
      document.body.appendChild(a);
      a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
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
    $('overdue').addEventListener('change', (e) => { state.includeOverdue = e.target.checked; refresh(); });
    $('perf').addEventListener('change', (e) => { state.perf = e.target.value; state.page = 1; render(); });
    $('q').addEventListener('input', (e) => { state.q = e.target.value.trim(); state.page = 1; render(); });
    $('pager').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-go]');
      if (!b || b.disabled) return;
      state.page = Number(b.dataset.go);
      render();
      $('listTitle').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $('refresh').addEventListener('click', refresh);
    $('csv').addEventListener('click', exportCsv);
    if (o.showClerks) $('officer').addEventListener('change', (e) => { state.officer = e.target.value; refresh(); });

    refresh();
    return { refresh };
  }

  window.IHCDues = { mount };
})();
