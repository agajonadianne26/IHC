'use strict';

document.addEventListener('DOMContentLoaded', () => {

  const DB_KEY = 'IHC_CONTRACTS_STORE';

  /* =========================
      ELEMENT REFERENCES
  ========================= */
  const startDateInput = document.getElementById('startDate');
  const contractForm = document.getElementById('contractForm');
  const scheduleContainer = document.getElementById('scheduleContainer');
  const scheduleBody = document.getElementById('scheduleBody');
  const clientSummary = document.getElementById('clientSummary');

  const totalInput = document.getElementById('totalPrice');
  const downpaymentInput = document.getElementById('downpayment');
  const termsInput = document.getElementById('terms');

  const summaryTotal = document.getElementById('summaryTotal');
  const summaryDownpayment = document.getElementById('summaryDownpayment');
  const summaryBalance = document.getElementById('summaryBalance');
  const summaryMonthly = document.getElementById('summaryMonthly');

  const ledgerTotal = document.getElementById('ledgerTotal');
  const ledgerDownpayment = document.getElementById('ledgerDownpayment');
  const ledgerBalance = document.getElementById('ledgerBalance');
  const ledgerTerms = document.getElementById('ledgerTerms');

  const paymentModal = document.getElementById('paymentModal');
  const paymentForm = document.getElementById('paymentForm');
  const modalSubTitle = document.getElementById('modalSubTitle');
  const modalDueDate = document.getElementById('modalDueDate');
  const modalAmount = document.getElementById('modalAmount');
  const paymentDateInput = document.getElementById('paymentDate');

  let currentContractId = null;
  let activeInstallmentIndex = null;
  let activeInstallmentAmount = 0;

  /* =========================
      STORAGE HELPERS
  ========================= */
  const getStoredContracts = () => {
    try {
      return JSON.parse(localStorage.getItem(DB_KEY)) || [];
    } catch {
      return [];
    }
  };

  const saveContracts = (contracts) => {
    localStorage.setItem(DB_KEY, JSON.stringify(contracts));
  };

  /* =========================
      DATE & FORMAT HELPERS
  ========================= */
  const formatLocalDate = (date) => {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  };

  const formatMoney = (amount) => {
    return `₱${Number(amount || 0).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    })}`;
  };

  const today = new Date();
  const formattedToday = formatLocalDate(today);

  if (startDateInput) {
    startDateInput.min = formattedToday;

    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() + 5);
    startDateInput.max = formatLocalDate(maxDate);

    const defaultDate = new Date();
    defaultDate.setMonth(defaultDate.getMonth() + 1);
    startDateInput.value = formatLocalDate(defaultDate);

    startDateInput.addEventListener('keydown', (e) => e.preventDefault());
  }

  /* =========================
      REAL-TIME SUMMARY CALCULATOR
  ========================= */
  const updateSummary = () => {
    const total = parseFloat(totalInput.value) || 0;
    const downpayment = parseFloat(downpaymentInput.value) || 0;
    const months = parseInt(termsInput.value, 10) || 1;

    const balance = Math.max(total - downpayment, 0);
    const monthly = balance / months;

    if (summaryTotal) summaryTotal.textContent = formatMoney(total);
    if (summaryDownpayment) summaryDownpayment.textContent = formatMoney(downpayment);
    if (summaryBalance) summaryBalance.textContent = formatMoney(balance);
    if (summaryMonthly) summaryMonthly.textContent = formatMoney(monthly);
  };

  if (totalInput) totalInput.addEventListener('input', updateSummary);
  if (downpaymentInput) downpaymentInput.addEventListener('input', updateSummary);
  if (termsInput) termsInput.addEventListener('change', updateSummary);

  updateSummary();

  /* =========================
      RENDER SCHEDULE / LEDGER
  ========================= */
  window.loadContractLedger = (contractId) => {
    const contracts = getStoredContracts();
    const contract = contracts.find(c => c.id === parseInt(contractId, 10));

    if (!contract) {
      alert('Could not find contract details in local storage.');
      return;
    }

    currentContractId = contract.id;

    if (ledgerTotal) ledgerTotal.textContent = formatMoney(contract.totalPrice);
    if (ledgerDownpayment) ledgerDownpayment.textContent = formatMoney(contract.downpayment);
    if (ledgerBalance) ledgerBalance.textContent = formatMoney(contract.remainingBalance);
    if (ledgerTerms) ledgerTerms.textContent = `${contract.terms} Months`;

    if (clientSummary) {
      clientSummary.textContent = `Buyer: ${contract.name} | TCP: ${formatMoney(contract.totalPrice)} | Amortization Terms: ${contract.terms} Months | Automated Notices: ${contract.email} & ${contract.phone}`;
    }

    scheduleBody.innerHTML = '';

    contract.installments.forEach((item, index) => {
      const isCleared = item.status === 'Cleared & Posted';
      const row = document.createElement('tr');
      row.id = `installment-row-${index + 1}`;

      row.innerHTML = `
        <td><strong>#${item.installmentNumber}</strong></td>
        <td><strong>${item.dueDate}</strong></td>
        <td><strong>${formatMoney(item.amountDue)}</strong></td>
        <td>
          ${item.noticeDate}
          <br><small>Notice Scheduled</small>
        </td>
        <td class="status-cell">
          <span class="status-badge ${isCleared ? 'paid' : ''}">${item.status}</span>
        </td>
        <td class="action-cell">
          ${isCleared
            ? `<button type="button" class="table-receipt-btn" onclick="alert('Receipt Details:\\nOR Number: ${item.receipt?.orNumber || 'N/A'}\\nChannel: ${item.receipt?.channel || 'N/A'}\\nNotes: ${item.receipt?.notes || 'None'}')">View OR Details</button>`
            : `<button type="button" class="table-pay-btn" onclick="openPostingModal(${index}, '${item.dueDate}', ${item.amountDue})">Post Payment</button>`
          }
        </td>
      `;

      scheduleBody.appendChild(row);
    });

    scheduleContainer.classList.remove('hidden');
    scheduleContainer.style.display = 'block';

    setTimeout(() => {
      scheduleContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  };

  /* =========================
      CONTRACT FORM SUBMISSION (FRONTEND ONLY)
  ========================= */
  contractForm.addEventListener('submit', (e) => {
    e.preventDefault();

    const selectedDate = startDateInput.value;

    if (!selectedDate) {
      alert('Officer Warning: Please select the first installment due date.');
      return;
    }

    if (selectedDate < formattedToday) {
      alert('Officer Warning: The first installment date cannot be backdated.');
      return;
    }

    const name = document.getElementById('clientName').value.trim();
    const email = document.getElementById('clientEmail').value.trim();
    const phone = document.getElementById('clientPhone').value.trim();

    const total = parseFloat(totalInput.value);
    const downpayment = parseFloat(downpaymentInput.value);
    const months = parseInt(termsInput.value, 10);

    if (isNaN(total) || isNaN(downpayment)) {
      alert('Officer Warning: Please verify contract figures.');
      return;
    }

    if (downpayment >= total) {
      alert('Validation Error: Downpayment/Equity cannot cover or exceed the Total Contract Price.');
      return;
    }

    if (downpayment < 0) {
      alert('Validation Error: Downpayment cannot be a negative amount.');
      return;
    }

    const remainingBalance = total - downpayment;
    const exactMonthly = remainingBalance / months;

    // Generate installments
    const [year, month, day] = selectedDate.split('-').map(Number);
    let installmentDate = new Date(year, month - 1, day);
    const installments = [];

    for (let i = 1; i <= months; i++) {
      const dueDate = new Date(installmentDate);
      const reminderDate = new Date(dueDate);
      reminderDate.setDate(reminderDate.getDate() - 3);

      let paymentAmount = (i === months)
        ? Math.round((remainingBalance - exactMonthly * (months - 1)) * 100) / 100
        : Math.round(exactMonthly * 100) / 100;

      installments.push({
        installmentNumber: i,
        dueDate: dueDate.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }),
        noticeDate: reminderDate.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }),
        amountDue: paymentAmount,
        status: 'Pending Payment',
        receipt: null
      });

      installmentDate.setDate(1);
      installmentDate.setMonth(installmentDate.getMonth() + 1);
      const lastDayOfMonth = new Date(installmentDate.getFullYear(), installmentDate.getMonth() + 1, 0).getDate();
      installmentDate.setDate(Math.min(day, lastDayOfMonth));
    }

    const contracts = getStoredContracts();
    const newContractId = contracts.length > 0 ? contracts[contracts.length - 1].id + 1 : 1;

    const newContract = {
      id: newContractId,
      accountCode: `IHC-2026-${String(newContractId).padStart(3, '0')}`,
      name: name,
      email: email,
      phone: phone,
      totalPrice: total,
      downpayment: downpayment,
      collectedInstallments: 0,
      remainingBalance: remainingBalance,
      terms: months,
      startDate: selectedDate,
      status: 'Active',
      installments: installments
    };

    contracts.push(newContract);
    saveContracts(contracts);

    alert(`Contract successfully booked under ID #${newContract.accountCode}!`);
    window.loadContractLedger(newContract.id);
  });

  /* =========================
      PAYMENT POSTING MODAL
  ========================= */
  window.openPostingModal = (index, dueDate, amount) => {
    activeInstallmentIndex = index;
    activeInstallmentAmount = amount;

    if (modalSubTitle) modalSubTitle.textContent = `Installment #${index + 1} Official Entry`;
    if (modalDueDate) modalDueDate.value = dueDate;
    if (modalAmount) modalAmount.value = formatMoney(amount);
    if (paymentDateInput) paymentDateInput.value = formatLocalDate(new Date());

    if (paymentModal) paymentModal.classList.remove('hidden');
  };

  const closePaymentModal = () => {
    if (paymentModal) paymentModal.classList.add('hidden');
    if (paymentForm) paymentForm.reset();
    activeInstallmentIndex = null;
    activeInstallmentAmount = 0;
  };

  const closeBtn = document.getElementById('closeModalBtn');
  const cancelBtn = document.getElementById('cancelModalBtn');
  if (closeBtn) closeBtn.addEventListener('click', closePaymentModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closePaymentModal);

  if (paymentForm) {
    paymentForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const orNumber = document.getElementById('orNumber').value.trim();
      const method = document.getElementById('paymentMethod').value;
      const notes = document.getElementById('cashierNotes').value.trim() || 'None';
      const payDate = paymentDateInput.value || formatLocalDate(new Date());

      const contracts = getStoredContracts();
      const target = contracts.find(c => c.id === currentContractId);

      if (!target || activeInstallmentIndex === null) {
        alert('Active payment target is missing.');
        return;
      }

      // Mark cleared
      target.installments[activeInstallmentIndex].status = 'Cleared & Posted';
      target.installments[activeInstallmentIndex].receipt = {
        orNumber: orNumber,
        channel: method,
        paymentDate: payDate,
        notes: notes
      };

      target.collectedInstallments = (target.collectedInstallments || 0) + activeInstallmentAmount;
      target.remainingBalance = Math.max(target.remainingBalance - activeInstallmentAmount, 0);

      saveContracts(contracts);

      alert(`Audit Entry Logged:\nInstallment #${activeInstallmentIndex + 1} marked as CLEARED.\nOfficial Receipt: ${orNumber}\nChannel: ${method}`);
      closePaymentModal();
      window.loadContractLedger(currentContractId);
    });
  }

  /* =========================
      NOTIFICATION ENGINE TOGGLE
  ========================= */
  const activateButton = document.getElementById('activateButton');
  if (activateButton) {
    activateButton.addEventListener('click', () => {
      alert('Notice Engine: Automatic SMS and Email billing reminders have been scheduled.');
      activateButton.textContent = '✓ Dispatch Engine Enabled';
      activateButton.disabled = true;
      activateButton.style.opacity = '0.7';
    });
  }

  /* =========================
      CHECK QUERY PARAMETERS
  ========================= */
  const urlParams = new URLSearchParams(window.location.search);
  const contractIdParam = urlParams.get('contractId');
  if (contractIdParam) {
    window.loadContractLedger(contractIdParam);
  }

});