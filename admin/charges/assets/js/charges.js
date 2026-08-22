// Customer Charges Module

// Number formatter helper
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let collectPaymentModal, chargeHistoryModal, exemptionModal;
let currentCollectBalance = 0;
let currentCollectible = 0;
let currentBaseBalance = 0;
let currentFeeBalance = 0;
let currentServiceFeeMode = 'CUSTOMER';
let isCollectSubmitting = false;
let isExemptionSubmitting = false;

const CHARGES_REALTIME_EVENTS = [
    'pos.transaction.completed',
    'wallet.updated',
    'charge.updated',
    'bank.confirmation.updated',
    'refund.updated'
];

document.addEventListener('DOMContentLoaded', function () {
    collectPaymentModal  = new bootstrap.Modal(document.getElementById('collectPaymentModal'));
    chargeHistoryModal   = new bootstrap.Modal(document.getElementById('chargeHistoryModal'));
    const exemptionModalEl = document.getElementById('exemptionModal');
    if (exemptionModalEl) {
        exemptionModal = new bootstrap.Modal(exemptionModalEl);
    }
    startChargesRealtime();
});

async function refreshChargesData() {
    const stats = document.getElementById('chargesStats');
    const table = document.getElementById('chargesDataTable');
    if (!stats || !table) return;

    const url = new URL(window.location.href);
    url.searchParams.set('_realtime', Date.now().toString());

    const response = await fetch(url.toString(), {
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!response.ok) throw new Error(`Charges refresh failed (${response.status})`);

    const html = await response.text();
    const documentFragment = new DOMParser().parseFromString(html, 'text/html');
    const nextStats = documentFragment.getElementById('chargesStats');
    const nextTable = documentFragment.getElementById('chargesDataTable');
    if (!nextStats || !nextTable) throw new Error('Charges refresh returned invalid markup');

    const statsChanged = stats.outerHTML !== nextStats.outerHTML;
    const tableChanged = table.outerHTML !== nextTable.outerHTML;

    if (statsChanged) stats.replaceWith(nextStats);
    if (tableChanged) table.replaceWith(nextTable);
    if (statsChanged || tableChanged) applyFilters();

    const historyModal = document.getElementById('chargeHistoryModal');
    if (historyPassengerId && historyModal?.classList.contains('show')) {
        await viewHistory(historyPassengerId, historyPassengerName, historyCurrentPage, historyCurrentBranch, historyCurrentType);
    }
}

function startChargesRealtime() {
    const config = window.CHARGES_PUSHER_CONFIG || {};
    if (!window.TMSBranchRealtime) return;

    window.chargesRealtime = window.TMSBranchRealtime.start({
        config,
        branchIds: config.branchIds,
        events: CHARGES_REALTIME_EVENTS,
        statusElement: 'chargesRealtimeStatus',
        onUpdate: meta => {
            if (meta?.reason === 'initial' || meta?.reason === 'fallback') {
                return;
            }

            return refreshChargesData();
        }
    });
}

function toggleHowItWorks() {
    const c = document.getElementById('howItWorksContent');
    const i = document.getElementById('howItWorksIcon');
    const open = c.style.display === 'block';
    c.style.display = open ? 'none' : 'block';
    i.className = 'fas fa-chevron-' + (open ? 'down' : 'up');
}

function openCollectModal(passengerId, name, contact, balance, baseBalance, feeBalance, serviceFeeMode, branchId, branchName) {
    currentCollectBalance = parseFloat(balance) || 0;
    currentBaseBalance = parseFloat(baseBalance) || 0;
    currentFeeBalance = parseFloat(feeBalance) || 0;
    currentServiceFeeMode = serviceFeeMode || 'CUSTOMER';

    // Max collectible is the total balance (exempt customers may still owe pre-exemption fee).
    // Prefill the customer's current portion: full balance for CUSTOMER, base only for WAIVED/COMPANY.
    currentCollectible = currentCollectBalance;
    const prefill = currentServiceFeeMode === 'CUSTOMER' ? currentCollectBalance : currentBaseBalance;

    document.getElementById('collectPassengerId').value = passengerId;
    document.getElementById('collectCustomerName').textContent = name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
    document.getElementById('collectCustomerContact').textContent = contact;
    document.getElementById('collectBaseBalance').textContent = '₱' + fmt(currentBaseBalance);
    document.getElementById('collectFeeBalance').textContent = '₱' + fmt(currentFeeBalance);
    document.getElementById('collectBalance').textContent = '₱' + fmt(currentCollectBalance);
    document.getElementById('collectCollectible').textContent = '₱' + fmt(currentBaseBalance);

    const modeBadge = document.getElementById('collectModeBadge');
    if (currentServiceFeeMode === 'WAIVED') {
        modeBadge.textContent = 'Waived';
        modeBadge.className = 'badge bg-soft-info text-info ms-2';
    } else if (currentServiceFeeMode === 'COMPANY') {
        modeBadge.textContent = 'Company';
        modeBadge.className = 'badge bg-soft-primary text-primary ms-2';
    } else {
        modeBadge.textContent = 'Customer';
        modeBadge.className = 'badge bg-soft-secondary text-secondary ms-2';
    }

    const amountInput = document.getElementById('collectAmount');
    amountInput.value = prefill.toFixed(2);
    amountInput.max = currentCollectible > 0 ? currentCollectible.toFixed(2) : '';
    amountInput.classList.remove('is-invalid');
    document.getElementById('collectAmountFeedback').style.display = 'none';
    document.getElementById('collectBranchId').value = branchId || '';
    document.getElementById('collectBranchName').value = branchName || '';
    document.getElementById('collectMethodId').value = '';
    document.getElementById('collectBankAccountId').value = '';
    document.getElementById('collectRefNum').value = '';
    document.getElementById('collectNotes').value = '';
    document.getElementById('collectRefRow').style.display = 'none';
    document.getElementById('collectBankRow').style.display = 'none';
    document.getElementById('confirmationInfoBox').style.display = 'none';
    setCollectSubmitting(false);
    collectPaymentModal.show();
    setTimeout(() => amountInput.focus(), 100);
}

function setFullCollectAmount() {
    const amountInput = document.getElementById('collectAmount');
    amountInput.value = currentCollectible.toFixed(2);
    validateCollectAmount();
}

function validateCollectAmount() {
    const amountInput = document.getElementById('collectAmount');
    const feedback = document.getElementById('collectAmountFeedback');
    const amount = parseFloat(amountInput.value) || 0;
    if (currentCollectible > 0 && amount > currentCollectible) {
        amountInput.classList.add('is-invalid');
        feedback.textContent = 'Amount cannot exceed the collectible balance of ₱' + fmt(currentCollectible) + '. It will be clamped.';
        feedback.style.display = 'block';
    } else if (amount <= 0) {
        amountInput.classList.add('is-invalid');
        feedback.textContent = 'Enter a valid amount greater than 0.';
        feedback.style.display = 'block';
    } else {
        amountInput.classList.remove('is-invalid');
        feedback.style.display = 'none';
    }
}

function setCollectSubmitting(submitting) {
    isCollectSubmitting = submitting;
    const btn = document.getElementById('collectSubmitBtn');
    const icon = document.getElementById('collectSubmitIcon');
    const label = document.getElementById('collectSubmitLabel');
    if (submitting) {
        btn.disabled = true;
        icon.className = 'fas fa-spinner fa-spin me-1';
        label.textContent = 'Recording...';
    } else {
        btn.disabled = false;
        icon.className = 'fas fa-check-circle me-1';
        label.textContent = 'Record Payment';
    }
}

function toggleCollectRef() {
    const sel = document.getElementById('collectMethodId');
    const opt = sel.options[sel.selectedIndex];
    const reqRef = opt ? opt.dataset.reqRef === '1' : false;
    const reqBank = opt ? opt.dataset.reqBank === '1' : false;
    document.getElementById('collectRefRow').style.display = reqRef ? '' : 'none';
    document.getElementById('collectBankRow').style.display = reqBank ? '' : 'none';
    
    // Show/hide confirmation info box for bank/e-wallet payments
    const confirmationInfoBox = document.getElementById('confirmationInfoBox');
    if (reqBank && window.CHARGE_CONFIRMATION_REQUIRED) {
        confirmationInfoBox.style.display = 'block';
    } else {
        confirmationInfoBox.style.display = 'none';
    }
}

async function submitCollectPayment() {
    if (isCollectSubmitting) return;

    const passengerId = document.getElementById('collectPassengerId').value;
    let amount        = parseFloat(document.getElementById('collectAmount').value) || 0;
    const methodId    = document.getElementById('collectMethodId').value;
    const branchId    = document.getElementById('collectBranchId').value;
    const bankAcctId  = document.getElementById('collectBankAccountId').value;
    let refNum        = document.getElementById('collectRefNum').value.trim().toUpperCase();
    const notes       = document.getElementById('collectNotes').value.trim();

    validateCollectAmount();

    if (!methodId)  { showToast('danger', 'Validation Error', 'Select a payment method.'); return; }
    if (!branchId)  { showToast('danger', 'Validation Error', 'Branch information is required.'); return; }
    if (amount <= 0){ showToast('danger', 'Validation Error', 'Enter a valid amount.'); return; }

    // Clamp amount to collectible balance
    if (currentCollectible > 0 && amount > currentCollectible) {
        amount = currentCollectible;
        document.getElementById('collectAmount').value = amount.toFixed(2);
        showToast('warning', 'Amount Adjusted', `Amount was capped to the collectible balance of ₱${fmt(amount)}.`);
    }

    const sel = document.getElementById('collectMethodId');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.reqRef === '1' && !refNum) {
        showToast('danger', 'Reference Required', 'Please enter the reference number.'); return;
    }
    if (opt && opt.dataset.reqBank === '1' && !bankAcctId) {
        showToast('danger', 'Bank Account Required', 'Please select a bank account.'); return;
    }

    setCollectSubmitting(true);

    try {
        const res = await fetch(`${window.BASE_URL}/api/charges`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                passenger_id: passengerId,
                amount_paid: amount,
                payment_method_id: methodId,
                branch_id: branchId,
                bank_account_id: bankAcctId || null,
                reference_number: refNum || null,
                notes: notes || null
            })
        });
        const result = await res.json();
        if (result.success) {
            collectPaymentModal.hide();
            const codeMsg = result.payment_code ? ` (Ref: ${result.payment_code})` : '';
            showToast('success', 'Payment Recorded', `₱${fmt(amount)} collected${codeMsg}. New balance: ₱${fmt(result.new_balance)}`);
            if (result.payment_code) {
                printChargeReceipt(result.payment_code);
            }
            setTimeout(() => refreshChargesData(), 1500);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to record payment.');
            setCollectSubmitting(false);
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        setCollectSubmitting(false);
    }
}

function printChargeReceipt(paymentCode) {
    if (!paymentCode) return;
    window.open(`${window.BASE_URL}/admin/charges/views/print/receipt.php?code=${encodeURIComponent(paymentCode)}`, '_blank');
}

async function openExemptionModal(passengerId, name, serviceFeeMode, companyPassengerId) {
    document.getElementById('exemptionPassengerId').value = passengerId;
    document.getElementById('exemptionCustomerName').textContent = name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
    document.getElementById('exemptionMode').value = serviceFeeMode || 'CUSTOMER';
    document.getElementById('newCompanyName').value = '';
    document.getElementById('newCompanyMobile').value = '';
    setNewCompanySubmitting(false);

    // Load active passengers for company dropdown
    const companySelect = document.getElementById('exemptionCompanyPassengerId');
    companySelect.innerHTML = '<option value="">Select account</option><option value="__NEW__">＋ Create new company/CEO account</option>';
    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/passengers`);
        const result = await res.json();
        if (result.success && result.passengers) {
            result.passengers.forEach(p => {
                if (parseInt(p.passenger_id, 10) === parseInt(passengerId, 10)) return;
                const opt = document.createElement('option');
                opt.value = p.passenger_id;
                opt.textContent = `${p.fullname}${p.mobile_number ? ' - ' + p.mobile_number : ''}`;
                companySelect.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Failed to load passengers for exemption dropdown', e);
    }

    if (companyPassengerId) {
        companySelect.value = companyPassengerId;
    } else {
        companySelect.value = '';
    }

    toggleCompanySelect();
    toggleNewCompanyForm();
    setExemptionSubmitting(false);
    if (exemptionModal) exemptionModal.show();
}

function toggleCompanySelect() {
    const mode = document.getElementById('exemptionMode').value;
    const row = document.getElementById('exemptionCompanyRow');
    const companySelect = document.getElementById('exemptionCompanyPassengerId');
    if (mode === 'COMPANY') {
        row.style.display = '';
        companySelect.setAttribute('required', 'required');
    } else {
        row.style.display = 'none';
        companySelect.removeAttribute('required');
        document.getElementById('newCompanyForm').style.display = 'none';
    }
}

function toggleNewCompanyForm() {
    const companySelect = document.getElementById('exemptionCompanyPassengerId');
    const form = document.getElementById('newCompanyForm');
    if (companySelect && companySelect.value === '__NEW__') {
        form.style.display = '';
    } else {
        form.style.display = 'none';
    }
}

function setNewCompanySubmitting(submitting) {
    const btn = document.getElementById('newCompanySubmitBtn');
    const icon = document.getElementById('newCompanySubmitIcon');
    const label = document.getElementById('newCompanySubmitLabel');
    if (submitting) {
        btn.disabled = true;
        icon.className = 'fas fa-spinner fa-spin me-1';
        label.textContent = 'Saving...';
    } else {
        btn.disabled = false;
        icon.className = 'fas fa-save me-1';
        label.textContent = 'Save Account';
    }
}

async function createCompanyAccount() {
    const nameInput = document.getElementById('newCompanyName');
    const mobileInput = document.getElementById('newCompanyMobile');
    const fullname = nameInput.value.trim();
    const mobile = mobileInput.value.trim();

    if (!fullname) {
        showToast('danger', 'Validation Error', 'Enter the company/CEO account name.');
        return;
    }
    if (mobile && !/^09[0-9]{9}$/.test(mobile)) {
        showToast('danger', 'Validation Error', 'Mobile number must be 11 digits starting with 09.');
        return;
    }

    setNewCompanySubmitting(true);

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/passengers`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fullname: fullname,
                mobile_number: mobile || null
            })
        });
        const result = await res.json();
        if (result.success) {
            const companySelect = document.getElementById('exemptionCompanyPassengerId');
            const opt = document.createElement('option');
            opt.value = result.passenger_id;
            opt.textContent = `${result.fullname}${result.mobile_number ? ' - ' + result.mobile_number : ''}`;
            // Insert before the __NEW__ option
            const newOption = companySelect.querySelector('option[value="__NEW__"]');
            companySelect.insertBefore(opt, newOption);
            companySelect.value = result.passenger_id;
            nameInput.value = '';
            mobileInput.value = '';
            toggleNewCompanyForm();
            showToast('success', 'Account Created', `Company/CEO account “${result.fullname}” added.`);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to create account.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    } finally {
        setNewCompanySubmitting(false);
    }
}

function setExemptionSubmitting(submitting) {
    isExemptionSubmitting = submitting;
    const btn = document.getElementById('exemptionSubmitBtn');
    const icon = document.getElementById('exemptionSubmitIcon');
    const label = document.getElementById('exemptionSubmitLabel');
    if (submitting) {
        btn.disabled = true;
        icon.className = 'fas fa-spinner fa-spin me-1';
        label.textContent = 'Saving...';
    } else {
        btn.disabled = false;
        icon.className = 'fas fa-save me-1';
        label.textContent = 'Save';
    }
}

async function submitExemption() {
    if (isExemptionSubmitting) return;

    const passengerId = document.getElementById('exemptionPassengerId').value;
    const mode = document.getElementById('exemptionMode').value;
    const companySelect = document.getElementById('exemptionCompanyPassengerId');
    const companyPassengerId = companySelect ? companySelect.value : null;

    if (mode === 'COMPANY' && (!companyPassengerId || companyPassengerId === '__NEW__')) {
        showToast('danger', 'Validation Error', 'Select or create a company/CEO account.');
        return;
    }

    setExemptionSubmitting(true);

    try {
        const res = await fetch(`${window.BASE_URL}/api/charges`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                passenger_id: passengerId,
                service_fee_mode: mode,
                company_passenger_id: companyPassengerId || null
            })
        });
        const result = await res.json();
        if (result.success) {
            if (exemptionModal) exemptionModal.hide();
            showToast('success', 'Saved', 'Service fee mode updated.');
            setTimeout(() => refreshChargesData(), 1500);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update service fee mode.');
            setExemptionSubmitting(false);
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        setExemptionSubmitting(false);
    }
}

function printChargeStatement() {
    if (!historyPassengerId) return;
    window.open(`${window.BASE_URL}/admin/charges/views/print/statement.php?passenger_id=${encodeURIComponent(historyPassengerId)}`, '_blank');
}

let historyPassengerId = null;
let historyPassengerName = '';
let historyCurrentPage = 1;
let historyCurrentBranch = '';
let historyCurrentType = '';
let historyPerPage = 10;
let historyPagination = { total: 0, page: 1, per_page: 10, total_pages: 0 };

async function viewHistory(passengerId, name, page = 1, branch = '', type = '') {
    historyPassengerId = passengerId;
    historyPassengerName = name;
    historyCurrentPage = page;
    historyCurrentBranch = branch;
    historyCurrentType = type;

    document.getElementById('historyCustomerLabel').textContent = name;
    document.getElementById('chargeHistoryContent').innerHTML = '<div class="text-center py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading history...</div>';
    document.getElementById('historyBranchFilter').innerHTML = '<option value="">All Branches</option>';
    document.getElementById('chargeHistoryPager').innerHTML = '';
    chargeHistoryModal.show();

    try {
        const encodedPassengerId = IdEncoder.encode(passengerId);
        const params = new URLSearchParams({
            passenger_id: encodedPassengerId,
            page: String(page),
            per_page: String(historyPerPage)
        });
        if (branch) params.set('branch', branch);
        if (type) params.set('type', type);

        const res = await fetch(`${window.BASE_URL}/api/charges?${params.toString()}`);
        const result = await res.json();
        if (!result.success) {
            document.getElementById('chargeHistoryContent').innerHTML = '<p class="text-danger">' + (result.error || 'Failed to load history.') + '</p>';
            return;
        }

        const { entries, branches, pagination } = result.data;
        historyPagination = pagination || historyPagination;

        // Populate branch filter
        const branchSelect = document.getElementById('historyBranchFilter');
        branchSelect.innerHTML = '<option value="">All Branches</option>' +
            (branches || []).map(b => `<option value="${esc(b)}">${esc(b)}</option>`).join('');
        branchSelect.value = historyCurrentBranch;

        // Sync type filter
        document.getElementById('historyTypeFilter').value = historyCurrentType;

        renderHistoryEntries(entries);
        renderHistoryPagination();
    } catch (e) {
        document.getElementById('chargeHistoryContent').innerHTML = '<p class="text-danger">Failed to load history: ' + (e.message || 'Unexpected error') + '</p>';
    }
}

function applyHistoryFilters() {
    const branch = document.getElementById('historyBranchFilter').value;
    const type = document.getElementById('historyTypeFilter').value;
    viewHistory(historyPassengerId, historyPassengerName, 1, branch, type);
}

function goToHistoryPage(page) {
    if (page < 1 || page > historyPagination.total_pages) return;
    viewHistory(historyPassengerId, historyPassengerName, page, historyCurrentBranch, historyCurrentType);
}

function renderHistoryPagination() {
    const { total, page, total_pages } = historyPagination;
    const pager = document.getElementById('chargeHistoryPager');
    if (total_pages <= 1) {
        pager.innerHTML = '';
        return;
    }

    let html = '<nav aria-label="Charge history page" class="d-flex align-items-center gap-1">';
    html += `<span class="small text-muted me-2">${total} total</span>`;
    html += `<button class="btn btn-sm btn-link p-1" ${page === 1 ? 'disabled' : ''} onclick="goToHistoryPage(${page - 1})">Prev</button>`;

    // Show first, current, and last with ellipsis if needed
    for (let i = 1; i <= total_pages; i++) {
        if (i === 1 || i === total_pages || (i >= page - 1 && i <= page + 1)) {
            if (i === page) {
                html += `<span class="btn btn-sm btn-primary p-1">${i}</span>`;
            } else {
                html += `<button class="btn btn-sm btn-link p-1" onclick="goToHistoryPage(${i})">${i}</button>`;
            }
        } else if (i === page - 2 || i === page + 2) {
            html += '<span class="small text-muted">...</span>';
        }
    }

    html += `<button class="btn btn-sm btn-link p-1" ${page === total_pages ? 'disabled' : ''} onclick="goToHistoryPage(${page + 1})">Next</button>`;
    html += '</nav>';
    pager.innerHTML = html;
}

function renderHistoryEntries(entries) {
    let html = '';

    if (!entries || entries.length === 0) {
        html = '<p class="text-muted text-center py-3">No history found.</p>';
    } else {
        html = '<div class="list-unstyled mb-0">';
        entries.forEach(e => {
            const branchName = e.branch_name || '—';
            const branchInfo = `<div class="text-muted small"><span class="fas fa-building me-1"></span>${esc(branchName)}</div>`;
            const dateObj = new Date(e.created_at);
            const formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) + ' ' +
                                  dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const dateInfo = `<div class="text-muted" style="font-size:.75rem;">${formattedDate}</div>`;

            if (e.entry_type === 'charge') {
                const itemLabel = e.item_label || (e.source_type === 'TICKET_TRANSACTION' ? 'Ticket' : 'Service');
                const cashierInfo = e.cashier_name ? `<div class="text-muted small"><span class="fas fa-user me-1"></span>${esc(e.cashier_name)}</div>` : '';
                const ticketInfo = e.ticket_number ? `<div class="text-muted small"><span class="fas fa-ticket-alt me-1"></span>Ticket #: ${esc(e.ticket_number)}</div>` : '';
                const passengerInfo = e.transaction_passenger_name
                    ? `<div class="text-muted small"><span class="fas fa-user me-1"></span>Passenger: ${esc(e.transaction_passenger_name)}</div>`
                    : '';
                html += `<div class="history-entry charge mb-2">
                    <div class="d-flex justify-content-between">
                      <strong class="text-danger"><span class="fas fa-minus-circle me-1"></span>${esc(e.method_name || 'CHARGE')}</strong>
                      <strong class="text-danger">+₱${fmt(e.amount)}</strong>
                    </div>
                    <div class="text-muted small">${esc(itemLabel)} • ${esc(e.txn_code ?? '')}</div>
                    ${passengerInfo}
                    ${ticketInfo}
                    ${branchInfo}
                    ${cashierInfo}
                    ${dateInfo}
                </div>`;
            } else if (e.entry_type === 'reversal') {
                const cashierInfo = e.cashier_name ? `<div class="text-muted small"><span class="fas fa-user-check me-1"></span>Approved by: ${esc(e.cashier_name)}</div>` : '';
                const ticketInfo = e.ticket_number ? `<div class="text-muted small"><span class="fas fa-ticket-alt me-1"></span>Ticket #: ${esc(e.ticket_number)}</div>` : '';
                const passengerInfo = e.transaction_passenger_name
                    ? `<div class="text-muted small"><span class="fas fa-user me-1"></span>Passenger: ${esc(e.transaction_passenger_name)}</div>`
                    : '';
                const reversalLabel = e.operation_type === 'VOID' ? 'VOID / CHARGE REVERSAL' : 'CHARGE REVERSAL';
                html += `<div class="history-entry reversal mb-2">
                    <div class="d-flex justify-content-between">
                      <strong class="text-info"><span class="fas fa-undo-alt me-1"></span>${reversalLabel}</strong>
                      <strong class="text-info">-₱${fmt(e.amount)}</strong>
                    </div>
                    <div class="text-muted small">${esc(e.item_label)} • ${esc(e.txn_code ?? '')}</div>
                    ${passengerInfo}
                    ${ticketInfo}
                    <div class="text-warning small"><span class="fas fa-info-circle me-1"></span>Reversed from outstanding balance</div>
                    ${branchInfo}
                    ${cashierInfo}
                    ${dateInfo}
                </div>`;
            } else if (e.entry_type === 'payment') {
                // Check confirmation status
                const confStatus = e.confirmation_status || 'NOT_REQUIRED';
                let statusBadge = '';
                let balanceRestoredMsg = '';
                let remarksMsg = e.notes ? `<div class="text-muted small"><span class="fas fa-sticky-note me-1"></span>${esc(e.notes)}</div>` : '';

                if (confStatus === 'PENDING') {
                    statusBadge = '<span class="badge bg-soft-warning text-warning fs-10">PENDING</span>';
                } else if (confStatus === 'CONFIRMED') {
                    statusBadge = '<span class="badge bg-soft-success text-success fs-10">CONFIRMED</span>';
                    if (e.confirmed_by) {
                        remarksMsg += `<div class="text-muted small"><span class="fas fa-user-check me-1"></span>Confirmed by ${esc(e.confirmed_by)}</div>`;
                    }
                } else if (confStatus === 'REJECTED') {
                    statusBadge = '<span class="badge bg-soft-danger text-danger fs-10">REJECTED</span>';
                    balanceRestoredMsg = '<div class="text-info small"><span class="fas fa-undo me-1"></span>Balance restored</div>';
                    if (e.confirmed_by) {
                        remarksMsg += `<div class="text-muted small"><span class="fas fa-user-times me-1"></span>Rejected by ${esc(e.confirmed_by)}</div>`;
                    }
                }

                const statusHtml = confStatus !== 'NOT_REQUIRED' ? `<div class="mb-1">${statusBadge}</div>` : '';
                const cashierInfo = e.cashier_name ? `<div class="text-muted small"><span class="fas fa-user me-1"></span>${esc(e.cashier_name)}</div>` : '';
                const paymentCode = e.txn_code ? esc(e.txn_code) : '';
                const printReceiptLink = paymentCode
                    ? `<a href="javascript:void(0)" class="small text-primary" onclick="printChargeReceipt('${paymentCode}')"><span class="fas fa-print me-1"></span>Print Receipt</a>`
                    : '';

                html += `<div class="history-entry payment mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <strong class="text-success"><span class="fas fa-plus-circle me-1"></span>PAYMENT</strong>
                      <strong class="text-success">-₱${fmt(e.amount)}</strong>
                    </div>
                    ${statusHtml}
                    <div class="text-muted small">${esc(e.method_name ?? 'Cash')} ${e.reference_number ? '• Ref: ' + esc(e.reference_number) : ''} ${paymentCode ? '• ' + paymentCode : ''}</div>
                    <div class="text-muted small">Before: ₱${fmt(e.balance_before)} → After: ₱${fmt(e.balance_after)}</div>
                    ${balanceRestoredMsg}
                    ${remarksMsg}
                    ${branchInfo}
                    ${cashierInfo}
                    ${printReceiptLink ? `<div class="mt-1">${printReceiptLink}</div>` : ''}
                    ${dateInfo}
                </div>`;
            }
        });
        html += '</div>';
    }

    document.getElementById('chargeHistoryContent').innerHTML = html;
}

function esc(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const balanceRange = document.getElementById('filterBalanceRange').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const rows   = document.querySelectorAll('.charge-row');
    let visible  = 0;

    rows.forEach(row => {
        let show = true;
        if (search && !row.dataset.search.includes(search)) show = false;
        if (status && row.dataset.status !== status) show = false;

        const balance = parseFloat(row.dataset.balance) || 0;
        if (balanceRange === 'positive' && balance <= 0) show = false;
        if (balanceRange === 'zero' && balance > 0) show = false;
        if (balanceRange === 'high' && balance < 5000) show = false;

        const lastDate = row.dataset.lastDate;
        if (dateFrom && lastDate && new Date(lastDate) < new Date(dateFrom)) show = false;
        if (dateTo && lastDate && new Date(lastDate) > new Date(dateTo)) show = false;

        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const msg = document.getElementById('noResultsMsg');
    if (msg) msg.classList.toggle('d-none', visible > 0);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterBalanceRange').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    applyFilters();
}

function showToast(type, title, message) {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = `custom-toast alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top:80px;right:20px;z-index:9999;min-width:350px;max-width:450px;box-shadow:0 4px 12px rgba(0,0,0,.15);border-radius:8px;';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
    toast.innerHTML = `<div class="d-flex align-items-center"><span class="fas ${icon} me-3 fs-4"></span><div class="flex-grow-1"><strong class="d-block">${title}</strong><span class="d-block text-sm">${message}</span></div><button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 150); }, 5000);
}
