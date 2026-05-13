// Bank Transfer Confirmations Module

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let confirmPaymentModal;

document.addEventListener('DOMContentLoaded', function () {
    confirmPaymentModal = new bootstrap.Modal(document.getElementById('confirmPaymentModal'));
});

function toggleHowItWorks() {
    const c = document.getElementById('howItWorksContent');
    const i = document.getElementById('howItWorksIcon');
    const open = c.style.display === 'block';
    c.style.display = open ? 'none' : 'block';
    i.className = 'fas fa-chevron-' + (open ? 'down' : 'up');
}

function openConfirmModal(id, method, amount, refNum, bankAccount, cashier, date, service, branch) {
    document.getElementById('confirmPaymentId').value = id;
    document.getElementById('cpMethodName').textContent = method;
    document.getElementById('cpAmount').textContent = '₱' + fmt(amount.replace(/,/g, ''));
    document.getElementById('cpRefNum').textContent = refNum;
    document.getElementById('cpBankAccount').textContent = bankAccount || '—';
    document.getElementById('cpCashier').textContent = cashier;
    document.getElementById('cpDate').textContent = date;
    document.getElementById('cpService').textContent = service;
    document.getElementById('cpBranch').textContent = branch;
    document.getElementById('cpNotes').value = '';
    confirmPaymentModal.show();
}

async function submitConfirmPayment(action) {
    const paymentId = document.getElementById('confirmPaymentId').value;
    const notes = document.getElementById('cpNotes').value.trim();

    try {
        const res = await fetch(`${window.BASE_URL}/api/bank-confirmations`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId, action, notes })
        });
        const result = await res.json();
        if (result.success) {
            confirmPaymentModal.hide();
            const label = action === 'CONFIRMED' ? 'confirmed' : 'rejected';
            showToast('success', 'Payment ' + label.charAt(0).toUpperCase() + label.slice(1), `Bank transfer has been ${label} successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update payment.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    }
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
