(function () {
  'use strict';

  const API_BASE = 'http://localhost/ihc-system';

  const escapeHtml = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const money = (value) => '\u20B1' + Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  const number = (value, digits) => Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits
  });

  const date = (value) => {
    if (!value) return '\u2014';
    const parsed = new Date(String(value).length === 10 ? value + 'T00:00:00' : value);
    if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
    return parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const dateTime = (value) => {
    if (!value) return '\u2014';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
    return parsed.toLocaleString('en-US', {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
  };

  const dash = (value) => value === null || value === undefined || String(value).trim() === ''
    ? '<span class="muted">\u2014</span>'
    : escapeHtml(value);

  const references = (value) => dash(value && String(value).trim() ? value : '');

  const logoUrl = (path) => {
    const raw = String(path || 'img/ihc logo.png').trim();
    if (/^https?:\/\//i.test(raw)) return escapeHtml(raw);
    if (raw.startsWith('/')) return escapeHtml(raw);
    const normalized = raw.replace(/^(\.\.?\/)+/, '').replace(/^\/+/, '');
    return escapeHtml('/ihc-system/' + normalized.split('/').map(encodeURIComponent).join('/'));
  };

  const statusBadge = (status) => {
    const normalized = String(status || 'UNPAID').toUpperCase();
    let css = 'unpaid';
    if (normalized === 'PAID') css = 'paid';
    else if (normalized === 'PARTIALLY PAID' || normalized === 'PARTIAL') css = 'partial';
    else if (normalized === 'OVERDUE') css = 'overdue';
    return `<span class="ihc-soa-status ${css}">${escapeHtml(normalized)}</span>`;
  };

  const rowClass = (status) => {
    const normalized = String(status || '').toUpperCase();
    if (normalized === 'PAID') return 'paid-row';
    if (normalized === 'OVERDUE') return 'overdue-row';
    return '';
  };

  const field = (label, value, full) => `
    <div class="ihc-soa-field${full ? ' full' : ''}">
      <label>${escapeHtml(label)}</label>
      <span>${dash(value)}</span>
    </div>`;

  const kvRows = (rows) => rows.map((row) => `
    <tr class="${row.className || ''}">
      <th>${escapeHtml(row.label)}</th>
      <td>${row.html || escapeHtml(row.value == null ? '\u2014' : row.value)}</td>
    </tr>`).join('');

  const sectionHeading = (title, note) => `
    <div class="ihc-soa-section-heading">
      <h3>${escapeHtml(title)}</h3>
      ${note ? `<span>${escapeHtml(note)}</span>` : ''}
    </div>`;

  function invoiceCell(row) {
    const invoice = row.invoiceNumber || row.orNumber || '';
    const orNumber = row.orNumber || '';
    if (!invoice) return '<span class="muted">\u2014</span>';
    if (orNumber && invoice !== orNumber) {
      return `${escapeHtml(invoice)}<br><small>OR: ${escapeHtml(orNumber)}</small>`;
    }
    const external = row.externalReference ? `<br><small>Ref: ${escapeHtml(row.externalReference)}</small>` : '';
    return escapeHtml(invoice) + external;
  }

  function scheduleTable(rows) {
    const body = rows.length ? rows.map((row) => `
      <tr class="${rowClass(row.status)}">
        <td><strong>${escapeHtml(row.installmentNo)}</strong><br><small>${escapeHtml(row.type)}</small></td>
        <td>${date(row.paymentDate)}</td>
        <td>${date(row.dueDate)}</td>
        <td>${Number(row.daysPastDue || 0) > 0 ? Number(row.daysPastDue).toLocaleString('en-PH') : '\u2014'}</td>
        <td>${references(row.checkNumber)}</td>
        <td>${invoiceCell(row)}</td>
        <td class="money">${money(row.amountDue)}</td>
        <td class="money">${money(row.penalty)}</td>
        <td class="money">${money(row.interest)}</td>
        <td class="money">${money(row.principal)}</td>
        <td class="money"><strong>${money(row.outstandingBalance)}</strong></td>
      </tr>`).join('') : '<tr><td class="ihc-soa-empty" colspan="11">No payment schedule is available.</td></tr>';

    return `
      <div class="ihc-soa-table-wrap">
        <table class="ihc-soa-table">
          <thead>
            <tr>
              <th>Installment No.</th>
              <th>Date of Payment</th>
              <th>Due Date</th>
              <th>No. of Days Past Due</th>
              <th>Check No.</th>
              <th>Invoice / OR No.</th>
              <th>Amount</th>
              <th>Penalty</th>
              <th>Interest</th>
              <th>Principal</th>
              <th>Outstanding Balance</th>
            </tr>
          </thead>
          <tbody>${body}</tbody>
        </table>
      </div>`;
  }

  function equityTable(rows) {
    const body = rows.length ? rows.map((row) => `
      <tr class="${rowClass(row.status)}">
        <td><strong>${escapeHtml(row.installmentNo)}</strong></td>
        <td>${date(row.dueDate)}</td>
        <td>${date(row.paymentDate)}</td>
        <td class="money">${money(row.amountDue)}</td>
        <td class="money">${money(row.amountPaid)}</td>
        <td class="money">${money(row.penalty)}</td>
        <td class="money">${money(row.interest)}</td>
        <td class="money">${money(row.principal)}</td>
        <td class="money">${money(row.outstandingBalance)}</td>
        <td>${statusBadge(row.status)}</td>
      </tr>`).join('') : '<tr><td class="ihc-soa-empty" colspan="10">No equity schedule is available.</td></tr>';

    return `
      <div class="ihc-soa-table-wrap">
        <table class="ihc-soa-table">
          <thead>
            <tr>
              <th>Installment Number</th><th>Due Date</th><th>Payment Date</th>
              <th>Amount Due</th><th>Amount Paid</th><th>Penalty</th>
              <th>Interest</th><th>Principal</th><th>Remaining Balance</th><th>Payment Status</th>
            </tr>
          </thead>
          <tbody>${body}</tbody>
        </table>
      </div>`;
  }

  function reservationTable(rows) {
    const body = rows.length ? rows.map((row) => `
      <tr>
        <td>${date(row.paymentDate)}</td>
        <td>${references(row.reference)}</td>
        <td class="money">${money(row.amount)}</td>
        <td>${escapeHtml(row.description)}</td>
        <td class="money">${money(row.outstanding)}</td>
        <td>${statusBadge(row.status)}</td>
      </tr>`).join('') : '<tr><td class="ihc-soa-empty" colspan="6">No reservation fee is recorded for this contract.</td></tr>';

    return `
      <div class="ihc-soa-table-wrap">
        <table class="ihc-soa-table">
          <thead><tr><th>Payment Date</th><th>Invoice / OR Number</th><th>Reservation Fee Amount</th><th>Description</th><th>Remaining Balance</th><th>Status</th></tr></thead>
          <tbody>${body}</tbody>
        </table>
      </div>`;
  }

  function additionalEquityTable(rows) {
    const body = rows.length ? rows.map((row) => `
      <tr class="${rowClass(row.status)}">
        <td><strong>${escapeHtml(row.installmentNo)}</strong></td>
        <td>${date(row.dueDate)}</td>
        <td>${date(row.paymentDate)}</td>
        <td>${Number(row.daysPastDue || 0) > 0 ? Number(row.daysPastDue).toLocaleString('en-PH') : '\u2014'}</td>
        <td>${statusBadge(row.status)}</td>
        <td>${references(row.orNumber)}</td>
        <td class="money">${money(row.amountDue)}</td>
        <td class="money">${money(row.penalty)}</td>
        <td class="money">${money(row.interest)}</td>
        <td class="money">${money(row.principal)}</td>
        <td class="money"><strong>${money(row.outstandingBalance)}</strong></td>
      </tr>`).join('') : '<tr><td class="ihc-soa-empty" colspan="11">No additional equity payment is recorded.</td></tr>';

    return `
      <div class="ihc-soa-table-wrap">
        <table class="ihc-soa-table">
          <thead>
            <tr>
              <th>Installment Number</th><th>Due Date</th><th>Payment Date</th>
              <th>Days Past Due</th><th>Payment Status</th><th>Invoice / OR Number</th>
              <th>Amount</th><th>Penalty</th><th>Interest</th><th>Principal</th><th>Outstanding Balance</th>
            </tr>
          </thead>
          <tbody>${body}</tbody>
        </table>
      </div>`;
  }

  function chargesTable(rows) {
    const body = rows.length ? rows.map((row) => `
      <tr>
        <td>${escapeHtml(row.type)}</td>
        <td>${escapeHtml(row.description)}</td>
        <td>${date(row.chargeDate)}</td>
        <td>${references(row.orNumber)}</td>
        <td>${statusBadge(row.status)}</td>
        <td class="money">${money(row.amount)}</td>
        <td class="money">${money(row.amountPaid)}</td>
        <td class="money"><strong>${money(row.outstanding)}</strong></td>
      </tr>`).join('') : '<tr><td class="ihc-soa-empty" colspan="8">No additional charges are recorded.</td></tr>';

    return `
      <div class="ihc-soa-table-wrap">
        <table class="ihc-soa-table">
          <thead><tr><th>Charge Type</th><th>Description</th><th>Charge Date</th><th>Invoice / OR No.</th><th>Status</th><th>Amount</th><th>Paid</th><th>Outstanding</th></tr></thead>
          <tbody>${body}</tbody>
        </table>
      </div>`;
  }

  function render(data) {
    const c = data.contract || {};
    const f = data.financing || {};
    const s = data.summary || {};
    const due = data.amountDue || {};
    const prepared = data.preparedBy || {};
    const noted = data.notedBy || {};
    const client = data.client || {};
    const project = data.project || {};
    const company = data.company || {};
    const next = s.nextPayment;

    const notes = (data.notes || []).length
      ? (data.notes || []).map((note) => `<li>${escapeHtml(note)}</li>`).join('')
      : '<li>Please contact the IHC Billing Office for assistance.</li>';

    return `
      <article class="ihc-soa-document">
        <header class="ihc-soa-header">
          <div class="ihc-soa-brand">
            <img src="${logoUrl(company.logoPath)}" alt="Imperial Homes logo">
            <div>
              <h1>${escapeHtml(company.name || 'Imperial Homes')}</h1>
              <p>${escapeHtml(company.address || '')}</p>
              <p>${escapeHtml(company.contact || '')}</p>
            </div>
          </div>
          <div class="ihc-soa-title">
            <h2>Statement of Account</h2>
            <p><strong>SOA No.:</strong> ${escapeHtml(data.soaNumber)}</p>
            <p><strong>Contract Ref:</strong> ${escapeHtml(c.reference)}</p>
            <p><strong>As of:</strong> ${date(data.asOfDate)} &nbsp; <strong>Valid until:</strong> ${date(data.validUntil)}</p>
          </div>
        </header>

        <div class="ihc-soa-meta-grid">
          <section class="ihc-soa-panel">
            <div class="ihc-soa-panel-title">Client / Buyer Information</div>
            <div class="ihc-soa-panel-body">
              <div class="ihc-soa-field-grid">
                ${field('Buyer Name', client.name)}
                ${field('Email Address', client.email)}
                ${field('Contact Number', client.phone)}
                ${field('Client Address', client.address, true)}
              </div>
            </div>
          </section>
          <section class="ihc-soa-panel">
            <div class="ihc-soa-panel-title">Project / Property</div>
            <div class="ihc-soa-panel-body">
              <div class="ihc-soa-field-grid">
                ${field('Project Name', project.projectName)}
                ${field('Phase', project.phase)}
                ${field('Block', project.block)}
                ${field('Lot', project.lot)}
                ${field('Model Type', project.modelType)}
                ${field('Property Address', project.propertyAddress, true)}
              </div>
            </div>
          </section>
          <section class="ihc-soa-panel">
            <div class="ihc-soa-panel-title">Area / Loan Reference</div>
            <div class="ihc-soa-panel-body">
              <div class="ihc-soa-field-grid">
                ${field('Lot Area', project.lotArea)}
                ${field('Floor Area', project.floorArea)}
                ${field('Financing Bank', c.bankName)}
                ${field('Date Generated', dateTime(data.generatedAt), true)}
              </div>
            </div>
          </section>
        </div>

        <section class="ihc-soa-section">
          ${sectionHeading('Contract Amount', 'All figures are calculated from the current contract and ledger')}
          <div class="ihc-soa-columns">
            <table class="ihc-soa-kv">
              <tbody>
                ${kvRows([
                  { label: 'Total Contract Price (TCP)', value: money(c.grossPrice) },
                  { label: 'Less: Discount', value: money(c.discount) },
                  { label: 'Net Total Contract Price', value: money(c.netPrice), className: 'emphasis' },
                  { label: 'Equity', value: money(c.equity) },
                  { label: 'Reservation Fee', value: money(c.reservationFee) },
                  { label: 'Required Downpayment / Early Move-in', value: money(Number(c.requiredDownpayment || 0) + Number(c.earlyMoveIn || 0)) },
                  { label: 'Total Equity', value: money(c.totalEquity), className: 'emphasis' },
                  { label: 'Remaining Downpayment', value: money(c.remainingDownpayment) },
                  { label: 'Loanable Amount', value: money(c.loanableAmount), className: 'total' }
                ])}
              </tbody>
            </table>
            <div>
              <div class="ihc-soa-finance-card">
                <div class="ihc-soa-finance-head"><span>Equity Mortgage</span><span>Cash Equity</span></div>
                <div class="ihc-soa-finance-grid">
                  <div class="ihc-soa-finance-item"><label>Monthly Interest Rate</label><strong>${number(f.equityMonthlyRate, 4)}%</strong></div>
                  <div class="ihc-soa-finance-item"><label>Term</label><strong>${Number(f.equityTermMonths || 0) ? number(f.equityTermMonths, 0) + ' months' : 'At settlement'}</strong></div>
                  <div class="ihc-soa-finance-item"><label>Penalty</label><strong>${number(f.equityPenaltyRate, 2)}% / month</strong></div>
                </div>
              </div>
              <div class="ihc-soa-finance-card">
                <div class="ihc-soa-finance-head"><span>Home Financing</span><span>${escapeHtml(f.bankName || '')}</span></div>
                <div class="ihc-soa-finance-grid">
                  <div class="ihc-soa-finance-item"><label>Monthly Rate</label><strong>${number(f.homeMonthlyRate, 4)}%</strong></div>
                  <div class="ihc-soa-finance-item"><label>Term</label><strong>${number(f.homeTermYears, 2)} years</strong></div>
                  <div class="ihc-soa-finance-item"><label>Approved Loan</label><strong>${money(c.approvedLoanAmount)}</strong></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="ihc-soa-section">
          ${sectionHeading('Payment / Installment Schedule', 'Principal schedule follows the shared IHC ledger allocation')}
          ${scheduleTable(data.schedule || [])}
        </section>

        <section class="ihc-soa-section compact">
          ${sectionHeading('Reservation Fee', 'Linked reservation-fee records')}
          ${reservationTable(data.reservationFees || [])}
        </section>

        <section class="ihc-soa-section">
          ${sectionHeading('Equity Schedule', 'Payment status is derived from posted records and the as-of date')}
          ${equityTable(data.equitySchedule || [])}
        </section>

        <section class="ihc-soa-section">
          ${sectionHeading('Additional Equity', 'Payments exceeding the net contract schedule are shown separately')}
          ${additionalEquityTable(data.additionalEquity || [])}
        </section>

        <section class="ihc-soa-section">
          ${sectionHeading('Additional Charges', 'Move-in, taxes, utilities, construction bond, fees and other receivables')}
          ${chargesTable(data.additionalCharges || [])}
        </section>

        <section class="ihc-soa-section">
          ${sectionHeading('Payment Summary', 'As of ' + date(data.asOfDate))}
          <div class="ihc-soa-summary-grid">
            <div class="ihc-soa-summary-card">
              <h3>Contract Reconciliation</h3>
              <table class="ihc-soa-kv"><tbody>
                ${kvRows([
                  { label: 'TCP / Total Contract Price', value: money(s.grossPrice) },
                  { label: 'Less: Discount', value: money(s.discount) },
                  { label: 'Less: Payments Made', value: money(s.paymentsMade) },
                  { label: 'Remaining Balance', value: money(s.remainingBalance), className: 'emphasis' },
                  { label: 'Approved Loan Amount', value: money(s.approvedLoanAmount) }
                ])}
              </tbody></table>
            </div>
            <div class="ihc-soa-summary-card">
              <h3>Equity / Monthly Payment</h3>
              <table class="ihc-soa-kv"><tbody>
                ${kvRows([
                  { label: 'Remaining Equity', value: money(s.remainingEquity) },
                  { label: 'Monthly Payment', value: money(s.monthlyPayment) },
                  { label: 'Applicable Interest Rate', value: number(s.annualInterestRate, 2) + '% annual' },
                  { label: 'Next Payment Due', value: next ? date(next.dueDate) : '\u2014' },
                  { label: 'Next Scheduled Amount', value: next ? money(next.amount) : money(0), className: 'emphasis' }
                ])}
              </tbody></table>
            </div>
          </div>
        </section>

        <section class="ihc-soa-due">
          <div>
            <h3>For Payment / Total Amount Due</h3>
            <p>Outstanding principal/equity ${money(due.principalOutstanding)} + interest ${money(due.interest)} + penalties ${money(due.penalty)} + reservation ${money(due.reservationOutstanding)} + charges ${money(due.additionalCharges)} + additional equity ${money(due.additionalEquityOutstanding)}</p>
          </div>
          <div class="ihc-soa-due-total">
            <span>Total Amount Due</span>
            <strong>${money(due.total)}</strong>
          </div>
        </section>

        <section class="ihc-soa-notes">
          <h3>Important Notes</h3>
          <ol>${notes}</ol>
        </section>

        <section class="ihc-soa-signatures">
          <div class="ihc-soa-signature">
            <h3>Prepared By:</h3>
            <div class="ihc-soa-signature-line">
              <strong>${escapeHtml(prepared.name || '')}</strong>
              <span>${escapeHtml(prepared.position || '')}</span>
            </div>
          </div>
          <div class="ihc-soa-signature">
            <h3>Noted By:</h3>
            <div class="ihc-soa-signature-line">
              <strong>${escapeHtml(noted.name || '')}</strong>
              <span>${escapeHtml(noted.position || '')}</span>
              <span>${escapeHtml(noted.contact || '')}</span>
            </div>
          </div>
        </section>

        <footer class="ihc-soa-footer">
          This is a system-generated Statement of Account based on the current IHC database ledger.
          &nbsp;|&nbsp; SOA ${escapeHtml(data.soaNumber)} &nbsp;|&nbsp; Generated ${dateTime(data.generatedAt)}
        </footer>
      </article>`;
  }

  async function open(options) {
    const config = options || {};
    const modal = document.getElementById(config.modalId || 'soaModal');
    const target = document.getElementById(config.documentId || 'soaDocument');
    if (!modal || !target) throw new Error('SOA modal is not available on this page.');

    target.innerHTML = '<div class="ihc-soa-loading"><h2>Generating Statement of Account\u2026</h2><p>Loading the latest contract, payment, OR, fee, and charge records.</p></div>';
    modal.classList.add('visible');

    const apiBase = config.apiBase || API_BASE;
    const params = new URLSearchParams({
      action: 'document',
      contractId: String(config.contractId || ''),
      actorId: String(config.actorId || ''),
      actorEmail: String(config.actorEmail || '')
    });

    try {
      const response = await fetch(apiBase + '/php/api_soa.php?' + params.toString(), {
        headers: { 'Accept': 'application/json' }
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'The Statement of Account could not be generated.');
      }
      target.innerHTML = render(data);
      if (typeof window.lucide !== 'undefined') window.lucide.createIcons();
    } catch (error) {
      target.innerHTML = `<div class="ihc-soa-error"><h2>Unable to generate SOA</h2><p>${escapeHtml(error.message || 'Unexpected error')}</p></div>`;
      if (typeof config.onError === 'function') config.onError(error);
    }
  }

  window.IHCSoa = { open, render };
})();
