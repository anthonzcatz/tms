// Customer Charges Module

// Number formatter helper
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let collectPaymentModal, chargeHistoryModal;

document.addEventListener('DOMContentLoaded', function () {
    collectPaymentModal  = new bootstrap.Modal(document.getElementById('collectPaymentModal'));
    chargeHistoryModal   = new bootstrap.Modal(document.getElementById('chargeHistoryModal'));
});

function toggleHowItWorks() {
    const c = document.getElementById('howItWorksContent');
    const i = document.getElementById('howItWorksIcon');
    const open = c.style.display === 'block';
    c.style.display = open ? 'none' : 'block';
    i.className = 'fas fa-chevron-' + (open ? 'down' : 'up');
}

function openCollectModal(passengerId, name, contact, balance) {
    document.getElementById('collectPassengerId').value = passengerId;
    document.getElementById('collectCustomerName').textContent = name;
    document.getElementById('collectCustomerContact').textContent = contact;
    document.getElementById('collectBalance').textContent = '₱' + fmt(balance);
    document.getElementById('collectAmount').value = parseFloat(balance).toFixed(2);
    document.getElementById('collectMethodId').value = '';
    document.getElementById('collectRefNum').value = '';
    document.getElementById('collectNotes').value = '';
    document.getElementById('collectRefRow').style.display = 'none';
    collectPaymentModal.show();
}

function toggleCollectRef() {
    const sel = document.getElementById('collectMethodId');
    const opt = sel.options[sel.selectedIndex];
    const req = opt ? opt.dataset.reqRef === '1' : false;
    document.getElementById('collectRefRow').style.display = req ? '' : 'none';
}

async function submitCollectPayment() {
    const passengerId = document.getElementById('collectPassengerId').value;
    const amount      = parseFloat(document.getElementById('collectAmount').value) || 0;
    const methodId    = document.getElementById('collectMethodId').value;
    const refNum      = document.getElementById('collectRefNum').value.trim();
    const notes       = document.getElementById('collectNotes').value.trim();

    if (!methodId)  { showToast('danger', 'Validation Error', 'Select a payment method.'); return; }
    if (amount <= 0){ showToast('danger', 'Validation Error', 'Enter a valid amount.'); return; }

    const sel = document.getElementById('collectMethodId');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.reqRef === '1' && !refNum) {
        showToast('danger', 'Reference Required', 'Please enter the reference number.'); return;
    }

    try {
        const res = await fetch(`${window.BASE_URL}/api/charges`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ passenger_id: passengerId, amount_paid: amount, payment_method_id: methodId, reference_number: refNum || null, notes: notes || null })
        });
        const result = await res.json();
        if (result.success) {
            collectPaymentModal.hide();
            showToast('success', 'Payment Recorded', `₱${fmt(amount)} collected. New balance: ₱${fmt(result.new_balance)}`);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to record payment.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    }
}

async function viewHistory(passengerId, name) {
    document.getElementById('historyCustomerLabel').textContent = name;
    document.getElementById('chargeHistoryContent').innerHTML = '<div class="text-center py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading history...</div>';
    chargeHistoryModal.show();

    try {
        const res = await fetch(`${window.BASE_URL}/api/charges?passenger_id=${passengerId}`);
        const result = await res.json();
        if (!result.success) { document.getElementById('chargeHistoryContent').innerHTML = '<p class="text-danger">Failed to load history.</p>'; return; }

        const { charges, payments } = result.data;
        let html = '';

        if (charges.length === 0 && payments.length === 0) {
            html = '<p class="text-muted text-center py-3">No history found.</p>';
        } else {
            // Merge and sort by date desc
            const entries = [
                ...charges.map(c => ({ ...c, _type: 'charge', _date: c.created_at })),
                ...payments.map(p => ({ ...p, _type: 'payment', _date: p.created_at }))
            ].sort((a, b) => new Date(b._date) - new Date(a._date));

            html = '<div class="list-unstyled mb-0">';
            entries.forEach(e => {
                if (e._type === 'charge') {
                    html += `<div class="history-entry charge mb-2">
                        <div class="d-flex justify-content-between">
                          <strong class="text-danger"><span class="fas fa-minus-circle me-1"></span>CHARGE</strong>
                          <strong class="text-danger">+₱${fmt(e.amount)}</strong>
                        </div>
                        <div class="text-muted small">${e.service_type_name ?? 'Service'} • ${e.txn_code ?? ''}</div>
                        <div class="text-muted" style="font-size:.75rem;">${new Date(e._date).toLocaleString()}</div>
                    </div>`;
                } else {
                    html += `<div class="history-entry payment mb-2">
                        <div class="d-flex justify-content-between">
                          <strong class="text-success"><span class="fas fa-plus-circle me-1"></span>PAYMENT</strong>
                          <strong class="text-success">-₱${fmt(e.amount_paid)}</strong>
                        </div>
                        <div class="text-muted small">${e.method_name ?? 'Cash'} ${e.reference_number ? '• Ref: ' + e.reference_number : ''}</div>
                        <div class="text-muted small">Before: ₱${fmt(e.balance_before)} → After: ₱${fmt(e.balance_after)}</div>
                        <div class="text-muted" style="font-size:.75rem;">${new Date(e._date).toLocaleString()}</div>
                    </div>`;
                }
            });
            html += '</div>';
        }

        document.getElementById('chargeHistoryContent').innerHTML = html;
    } catch (e) {
        document.getElementById('chargeHistoryContent').innerHTML = '<p class="text-danger">Failed to load history.</p>';
    }
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const rows   = document.querySelectorAll('.charge-row');
    let visible  = 0;
    rows.forEach(row => {
        let show = true;
        if (search && !row.dataset.search.includes(search)) show = false;
        if (status && row.dataset.status !== status) show = false;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const msg = document.getElementById('noResultsMsg');
    if (msg) msg.classList.toggle('d-none', visible > 0);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
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
