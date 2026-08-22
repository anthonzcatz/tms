// Bank Transfer Confirmations Module

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let confirmPaymentModal;

const BANK_CONFIRMATION_REALTIME_EVENTS = [
    'pos.transaction.completed',
    'wallet.updated',
    'charge.updated',
    'bank.confirmation.updated',
    'refund.updated'
];

document.addEventListener('DOMContentLoaded', function () {
    confirmPaymentModal = new bootstrap.Modal(document.getElementById('confirmPaymentModal'));
    startBankConfirmationsRealtime();
});

async function refreshBankConfirmationData() {
    const stats = document.getElementById('bankConfirmationsStats');
    const table = document.getElementById('bankConfirmationsTable');
    if (!stats || !table) return;

    const url = new URL(window.location.href);
    const status = document.getElementById('filterStatus')?.value || 'PENDING';
    url.searchParams.set('status', status);
    url.searchParams.set('_realtime', Date.now().toString());

    const response = await fetch(url.toString(), {
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!response.ok) throw new Error(`Bank confirmations refresh failed (${response.status})`);

    const html = await response.text();
    const documentFragment = new DOMParser().parseFromString(html, 'text/html');
    const nextStats = documentFragment.getElementById('bankConfirmationsStats');
    const nextTable = documentFragment.getElementById('bankConfirmationsTable');
    if (!nextStats || !nextTable) throw new Error('Bank confirmations refresh returned invalid markup');

    stats.replaceWith(nextStats);
    table.replaceWith(nextTable);
    applyFilters();
}

function startBankConfirmationsRealtime() {
    const config = window.BANK_CONFIRMATIONS_PUSHER_CONFIG || {};
    if (!window.TMSBranchRealtime) return;

    window.bankConfirmationsRealtime = window.TMSBranchRealtime.start({
        config,
        branchIds: config.branchIds,
        events: BANK_CONFIRMATION_REALTIME_EVENTS,
        statusElement: 'bankConfirmationsRealtimeStatus',
        onUpdate: meta => {
            const payload = meta?.payload || {};
            const currentId = document.getElementById('confirmPaymentId')?.value;
            if (payload.item_id && currentId && Number(payload.item_id) === Number(currentId) && payload.action) {
                if (confirmPaymentModal) confirmPaymentModal.hide();
                showToast('warning', 'Payment Updated', 'This item was already reviewed by another user.');
            }
            return refreshBankConfirmationData();
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

function openConfirmModal(id, itemType, method, amount, refNum, bankAccount, cashier, date, service, branch) {
    document.getElementById('confirmPaymentId').value = id;
    document.getElementById('confirmItemType').value = itemType || 'PAYMENT';
    document.getElementById('cpMethodName').textContent = method;
    document.getElementById('cpAmount').textContent = '₱' + fmt(amount.replace(/,/g, ''));
    document.getElementById('cpRefNum').textContent = refNum;
    document.getElementById('cpBankAccount').textContent = bankAccount || '—';
    document.getElementById('cpCashier').textContent = cashier;
    document.getElementById('cpDate').textContent = date;
    document.getElementById('cpService').textContent = service;
    document.getElementById('cpBranch').textContent = branch;
    document.getElementById('cpNotes').value = '';
    
    // Update modal title, subtitle, and button based on item type
    const modalTitle = document.getElementById('confirmPaymentModalLabel');
    const modalSubtitle = document.getElementById('confirmPaymentModalSubtitle');
    const confirmBtn = document.querySelector('#confirmPaymentModal .btn-success');
    
    if (itemType === 'DEPOSIT') {
        modalTitle.innerHTML = '<span class="fas fa-university me-2"></span>Confirm Cash Deposit';
        modalSubtitle.textContent = 'Confirm or reject this cash deposit';
        if (confirmBtn) confirmBtn.innerHTML = '<span class="fas fa-check-circle me-1"></span>Confirm Deposit';
    } else if (itemType === 'CHARGE') {
        modalTitle.innerHTML = '<span class="fas fa-file-invoice-dollar me-2"></span>Confirm Charge Collection';
        modalSubtitle.textContent = 'Confirm or reject this charge collection payment from /admin/charges/';
        if (confirmBtn) confirmBtn.innerHTML = '<span class="fas fa-check-circle me-1"></span>Confirm Payment';
    } else {
        modalTitle.innerHTML = '<span class="fas fa-credit-card me-2"></span>Confirm Bank Transfer';
        modalSubtitle.textContent = 'Confirm or reject this bank transfer payment';
        if (confirmBtn) confirmBtn.innerHTML = '<span class="fas fa-check-circle me-1"></span>Confirm Transfer';
    }
    
    confirmPaymentModal.show();
}

// Store pending action for confirmation modal
let pendingAction = null;
let pendingItemId = null;
let pendingItemType = null;
let pendingNotes = null;

let actionConfirmModalInstance = null;

function showActionConfirmModal(action, itemType, itemId, notes) {
    pendingAction = action;
    pendingItemId = itemId;
    pendingItemType = itemType;
    pendingNotes = notes;
    
    // Hide the main review modal first to prevent z-index issues
    confirmPaymentModal.hide();
    
    const isConfirm = action === 'CONFIRMED';
    const modalEl = document.getElementById('actionConfirmModal');
    const titleEl = document.getElementById('actionConfirmTitle');
    const messageEl = document.getElementById('actionConfirmMessage');
    const btnEl = document.getElementById('actionConfirmBtn');
    
    let itemTypeLabel = 'Payment';
    if (itemType === 'DEPOSIT') itemTypeLabel = 'Deposit';
    if (itemType === 'CHARGE') itemTypeLabel = 'Charge Collection';
    
    if (isConfirm) {
        titleEl.innerHTML = '<span class="fas fa-check-circle text-success me-2"></span>Confirm ' + itemTypeLabel;
        btnEl.className = 'btn btn-success';
        btnEl.innerHTML = '<span class="fas fa-check me-1"></span>Yes, Confirm';
        
        if (itemType === 'CHARGE') {
            messageEl.innerHTML = 'Are you sure you want to <strong>confirm</strong> this charge collection payment?<br><br>' +
                '<span class="text-success"><span class="fas fa-check"></span> Bank account balance will be updated</span><br>' +
                '<span class="text-success"><span class="fas fa-check"></span> Payment will be finalized</span>';
        } else if (itemType === 'DEPOSIT') {
            messageEl.innerHTML = 'Are you sure you want to <strong>confirm</strong> this cash deposit?<br><br>' +
                '<span class="text-success"><span class="fas fa-check"></span> Bank account balance will be updated</span>';
        } else {
            messageEl.innerHTML = 'Are you sure you want to <strong>confirm</strong> this bank transfer payment?<br><br>' +
                '<span class="text-success"><span class="fas fa-check"></span> Bank account balance will be updated</span>';
        }
    } else {
        titleEl.innerHTML = '<span class="fas fa-exclamation-triangle text-danger me-2"></span>Reject ' + itemTypeLabel;
        btnEl.className = 'btn btn-danger';
        btnEl.innerHTML = '<span class="fas fa-times me-1"></span>Yes, Reject';
        
        if (itemType === 'CHARGE') {
            messageEl.innerHTML = 'Are you sure you want to <strong class="text-danger">reject</strong> this charge collection payment?<br><br>' +
                '<span class="text-danger"><span class="fas fa-exclamation-circle"></span> Customer balance will be restored</span><br>' +
                '<span class="text-danger"><span class="fas fa-exclamation-circle"></span> Customer will need to pay again</span><br>' +
                '<span class="text-muted"><span class="fas fa-info-circle"></span> Cashier will need to re-record the payment</span>';
        } else if (itemType === 'DEPOSIT') {
            messageEl.innerHTML = 'Are you sure you want to <strong class="text-danger">reject</strong> this cash deposit?<br><br>' +
                '<span class="text-muted"><span class="fas fa-info-circle"></span> Cashier will need to re-record the deposit</span>';
        } else {
            messageEl.innerHTML = 'Are you sure you want to <strong class="text-danger">reject</strong> this bank transfer payment?<br><br>' +
                '<span class="text-muted"><span class="fas fa-info-circle"></span> Cashier will need to re-record the payment</span>';
        }
    }
    
    // Create new modal instance
    actionConfirmModalInstance = new bootstrap.Modal(modalEl);
    
    // Handle cancel/close - restore the main modal
    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
        // Only reopen if no action was taken (pending still set)
        if (pendingAction && !pendingAction.startsWith('_')) {
            confirmPaymentModal.show();
        }
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
    }, { once: true });
    
    actionConfirmModalInstance.show();
}

function executePendingAction() {
    if (pendingAction && pendingItemId && pendingItemType) {
        // Mark as executed so cancel won't reopen the main modal
        pendingAction = '_' + pendingAction;
        performConfirmAction(pendingAction.replace('_', ''), pendingItemId, pendingItemType, pendingNotes);
    }
}

async function performConfirmAction(action, itemId, itemType, notes) {
    try {
        const payload = { action, notes };
        if (itemType === 'DEPOSIT') {
            payload.deposit_id = itemId;
        } else if (itemType === 'CHARGE') {
            payload.charge_payment_id = itemId;
        } else {
            payload.payment_id = itemId;
        }

        const res = await fetch(`${window.BASE_URL}/api/bank-confirmations`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            confirmPaymentModal.hide();
            const label = action === 'CONFIRMED' ? 'confirmed' : 'rejected';
            let itemTypeLabel;
            if (itemType === 'DEPOSIT') {
                itemTypeLabel = 'Deposit';
            } else if (itemType === 'CHARGE') {
                itemTypeLabel = 'Charge Collection';
            } else {
                itemTypeLabel = 'Payment';
            }
            showToast('success', itemTypeLabel + ' ' + label.charAt(0).toUpperCase() + label.slice(1), `${itemTypeLabel} has been ${label} successfully.`);
            setTimeout(() => refreshBankConfirmationData(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update item.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    }
}

async function submitConfirmPayment(action) {
    const itemId = document.getElementById('confirmPaymentId').value;
    const itemType = document.getElementById('confirmItemType').value || 'PAYMENT';
    const notes = document.getElementById('cpNotes').value.trim();
    
    // Show Bootstrap confirmation modal for both Confirm and Reject
    showActionConfirmModal(action, itemType, itemId, notes);
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const date   = document.getElementById('filterDate').value;
    const rows   = document.querySelectorAll('.payment-row');
    let visible  = 0;

    rows.forEach(row => {
        let show = true;
        if (search && !row.dataset.search.includes(search)) show = false;
        if (status && status !== 'ALL' && row.dataset.status !== status) show = false;
        if (date && row.dataset.date !== date) show = false;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const msg = document.getElementById('noResultsMsg');
    if (msg) msg.classList.toggle('d-none', visible > 0);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = 'PENDING';
    document.getElementById('filterDate').value = '';
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
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 150); }, 4000);
}
