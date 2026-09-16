'use strict';

document.addEventListener('DOMContentLoaded', () => {

  const DB_KEY = 'IHC_CONTRACTS_STORE';

  /* ==========================================
     SESSION GUARD
  ========================================== */
  const stored = localStorage.getItem('IHC_USER');
  let currentUser = null;

  if (stored) {
    try {
      currentUser = JSON.parse(stored);
    } catch {}
  }

  if (!currentUser || !currentUser.name) {
    window.location.href = '../log in.html';
    return;
  }

  const currentOfficer = {
    id: currentUser.id ? 'IH-' + currentUser.id : 'IH-4821',
    name: currentUser.name,
    role: currentUser.role === 'clerk' ? 'Billing Clerk' : 'Accounts Receivable Specialist',
    avatar: currentUser.avatar || currentUser.name.split(' ').map(n => n[0]).join('')
  };

  // Populate Header Identity
  const officerNameEl = document.getElementById('officerName');
  const officerRoleEl = document.getElementById('officerRole');
  const officerAvatarEl = document.getElementById('officerAvatar');

  if (officerNameEl) officerNameEl.textContent = currentOfficer.name;
  if (officerRoleEl) officerRoleEl.textContent = `${currentOfficer.role} • ID #${currentOfficer.id}`;
  if (officerAvatarEl) officerAvatarEl.textContent = currentOfficer.avatar;

  // Table & KPI Elements
  const clientTableBody = document.getElementById('clientTableBody');
  const searchInput = document.getElementById('clientSearchInput');
  const statusFilter = document.getElementById('statusFilter');

  const kpiClientCount = document.getElementById('kpiClientCount');
  const kpiTotalTCP = document.getElementById('kpiTotalTCP');
  const kpiCollected = document.getElementById('kpiCollected');
  const kpiReceivables = document.getElementById('kpiReceivables');

  const formatMoney = (amount) => {
    return `₱${Number(amount || 0).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    })}`;
  };

  const getContracts = () => {
    try {
      return JSON.parse(localStorage.getItem(DB_KEY)) || [];
    } catch {
      return [];
    }
  };

  // Update KPI Metrics Cards
  const updateKPIs = (data) => {
    let totalTCP = 0;
    let totalCollected = 0;

    data.forEach((c) => {
      totalTCP += (c.totalPrice || c.tcp || 0);
      totalCollected += ((c.downpayment || 0) + (c.collectedInstallments || 0));
    });

    const totalReceivables = Math.max(totalTCP - totalCollected, 0);

    if (kpiClientCount) kpiClientCount.textContent = data.length;
    if (kpiTotalTCP) kpiTotalTCP.textContent = formatMoney(totalTCP);
    if (kpiCollected) kpiCollected.textContent = formatMoney(totalCollected);
    if (kpiReceivables) kpiReceivables.textContent = formatMoney(totalReceivables);
  };

  // Render Table Rows from local storage
  const renderTable = (data) => {
    clientTableBody.innerHTML = '';

    if (!data || data.length === 0) {
      clientTableBody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align:center; padding: 24px; color: var(--muted);">
            No buyer accounts found in records.
          </td>
        </tr>
      `;
      return;
    }

    data.forEach((client) => {
      const tcp = client.totalPrice || client.tcp || 0;
      const collected = (client.downpayment || 0) + (client.collectedInstallments || 0);
      const outstanding = Math.max(tcp - collected, 0);
      const isDueSoon = client.status === 'Due Soon';

      const row = document.createElement('tr');
      row.innerHTML = `
        <td><strong>${client.accountCode || client.id}</strong></td>
        <td><strong>${client.name}</strong></td>
        <td>
          ${client.email}<br>
          <small style="color: var(--muted);">${client.phone || 'N/A'}</small>
        </td>
        <td><strong>${formatMoney(tcp)}</strong></td>
        <td style="color: var(--success); font-weight: 600;">${formatMoney(collected)}</td>
        <td style="color: var(--primary); font-weight: 600;">${formatMoney(outstanding)}</td>
        <td>
          <span class="status-badge ${isDueSoon ? 'duesoon' : 'active'}">
            ${client.status || 'Active'}
          </span>
        </td>
        <td>
          <button 
            type="button" 
            class="btn-open-ledger"
            onclick="viewLedger(${client.id})"
          >
            Open Ledger &rarr;
          </button>
        </td>
      `;
      clientTableBody.appendChild(row);
    });
  };

  // Filter Handler
  const filterData = () => {
    const term = searchInput.value.toLowerCase().trim();
    const filter = statusFilter.value;
    const contracts = getContracts();

    const filtered = contracts.filter((client) => {
      const code = (client.accountCode || String(client.id)).toLowerCase();
      const matchesSearch = (client.name && client.name.toLowerCase().includes(term)) ||
                            (client.email && client.email.toLowerCase().includes(term)) ||
                            code.includes(term);

      const matchesStatus = filter === 'all' || (client.status || 'Active') === filter;

      return matchesSearch && matchesStatus;
    });

    renderTable(filtered);
  };

  if (searchInput) searchInput.addEventListener('input', filterData);
  if (statusFilter) statusFilter.addEventListener('change', filterData);

  // Navigate to application page with the saved contract ID
  window.viewLedger = (contractId) => {
    window.location.href = `application.html?contractId=${contractId}`;
  };

  // Logout handler
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      if (confirm('Confirm sign out of billing terminal?')) {
        localStorage.removeItem('IHC_USER');
        window.location.href = '../log in.html';
      }
    });
  }

  // Load from local storage on start
  const allContracts = getContracts();
  updateKPIs(allContracts);
  renderTable(allContracts);

});