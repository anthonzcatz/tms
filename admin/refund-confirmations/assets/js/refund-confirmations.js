/**
 * Refund Confirmations — AJAX-driven module
 */

let confirmCancellationModal;
let currentCancellationId = null;
let currentPage = 1;
let isLoading = false;

// ─── Init ───────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    confirmCancellationModal = new bootstrap.Modal(document.getElementById('confirmCancellationModal'));

    // Flatpickr date range
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#filterDateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            onChange: function (selectedDates) {
                document.getElementById('filterDateFrom').value = selectedDates[0]
                    ? selectedDates[0].toISOString().slice(0, 10) : '';
                document.getElementById('filterDateTo').value = selectedDates[1]
                    ? selectedDates[1].toISOString().slice(0, 10) : '';
            }
        });
    }

    // Search: debounce on Enter or 500ms idle
    const searchEl = document.getElementById('filterSearch');
    if (searchEl) {
        let searchTimer;
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadCancellations(1), 500);
        });
        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(searchTimer); loadCancellations(1); }
        });
    }

    // Load initial data
    loadCancellations(1);
});

// ─── Data Loading ────────────────────────────────────────────────────────────

async function loadCancellations(page) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page || 1;

    const tbody = document.getElementById('confirmationsTableBody');
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
        <span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>`;
    document.getElementById('tableInfo').textContent = 'Loading...';

    const params = buildParams(currentPage);
    const url = window.REFUND_CONF_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();

    try {
        const res = await fetch(url);
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Failed to load');

        renderTable(result.data);
        renderPagination(result.pagination);
        updateStats(result.stats);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">
            <span class="fas fa-exclamation-triangle me-2"></span>${e.message}</td></tr>`;
        document.getElementById('tableInfo').textContent = 'Error loading data';
    } finally {
        isLoading = false;
    }
}

function buildParams(page) {
    const p = {
        page:      page,
        limit:     document.getElementById('perPageSelect')?.value || 15,
        status:    document.getElementById('filterStatus')?.value  || 'pending',
        search:    document.getElementById('filterSearch')?.value  || '',
        cashier:   document.getElementById('filterCashier')?.value || '',
        date_from: document.getElementById('filterDateFrom')?.value || '',
        date_to:   document.getElementById('filterDateTo')?.value   || '',
    };
    const branch = document.getElementById('filterBranch');
    if (branch) p.branch_id = branch.value || '';
    const wallet = document.getElementById('filterWallet');
    if (wallet) p.wallet_id = wallet.value || '';
    // Remove empty
    Object.keys(p).forEach(k => { if (p[k] === '' || p[k] === null) delete p[k]; });
    return p;
}

// Open confirmation modal
function openConfirmModal(cancellationId, transactionCode, refundAmount, cancellationType, reason, requestedBy, passenger, origin, destination, requestedAt) {
    currentCancellationId = cancellationId;

    document.getElementById('modalTransactionCode').textContent = transactionCode;
    document.getElementById('modalRefundAmount').textContent = '₱' + refundAmount;
    document.getElementById('modalRefundAmountInline').textContent = '₱' + refundAmount;
    document.getElementById('modalCancellationType').textContent = cancellationType;
    document.getElementById('modalPassenger').textContent = passenger;
    document.getElementById('modalRoute').textContent = (origin && destination) ? origin + ' → ' + destination : '-';
    document.getElementById('modalRequestedBy').textContent = requestedBy;
    document.getElementById('modalReason').textContent = reason || 'No reason provided';
    document.getElementById('modalRequestedAt').textContent = requestedAt;

    // Reset action and rejection reason
    document.getElementById('modalAction').value = 'approve';
    document.getElementById('modalRejectionReason').value = '';
    document.getElementById('modalRemarks').value = '';
    document.getElementById('rejectionReasonDiv').style.display = 'none';
    document.getElementById('actionInfoAlert').classList.remove('d-none');
    document.getElementById('rejectInfoAlert').classList.add('d-none');

    // Add event listener to action select
    document.getElementById('modalAction').onchange = function() {
        const rejectionDiv = document.getElementById('rejectionReasonDiv');
        const infoAlert = document.getElementById('actionInfoAlert');
        const rejectAlert = document.getElementById('rejectInfoAlert');

        if (this.value === 'reject') {
            rejectionDiv.style.display = 'block';
            infoAlert.classList.add('d-none');
            rejectAlert.classList.remove('d-none');
        } else {
            rejectionDiv.style.display = 'none';
            infoAlert.classList.remove('d-none');
            rejectAlert.classList.add('d-none');
        }
    };

    confirmCancellationModal.show();
}

// Submit cancellation decision
function submitCancellationDecision() {
    const action = document.getElementById('modalAction').value;
    const rejectionReason = document.getElementById('modalRejectionReason').value.trim();
    
    if (action === 'reject' && !rejectionReason) {
        showToast('danger', 'Error', 'Please provide a rejection reason.');
        return;
    }
    
    const data = {
        cancellation_id: currentCancellationId,
        action: action,
        rejection_reason: rejectionReason || null,
        remarks: document.getElementById('modalRemarks').value.trim() || null
    };
    
    const submitBtn = document.querySelector('#confirmCancellationModal .btn-success');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="fas fa-spinner fa-spin me-1"></span>Processing...'; }

    fetch(window.BASE_URL + '/api/pos/cancellation-approval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('success', 'Success', result.message);
            confirmCancellationModal.hide();
            loadCancellations(currentPage);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to process cancellation decision.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('danger', 'Error', 'Failed to process cancellation decision.');
    })
    .finally(() => {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span class="fas fa-check me-1"></span>Submit Decision'; }
    });
}

// ─── Render & Helpers ────────────────────────────────────────────────────────

function renderTable(rows) {
    const tbody = document.getElementById('confirmationsTableBody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6"><div class="text-center py-5 text-muted">
            <span class="fas fa-check-double fs-2 d-block mb-2 opacity-50"></span>
            <div>No cancellation requests found</div>
            <small>Try adjusting your filters</small>
        </div></td></tr>`;
        return;
    }
    const statusIcons  = { pending:'fa-clock', approved:'fa-check-circle', rejected:'fa-times-circle', completed:'fa-check-double' };
    const statusColors = { pending:'warning',  approved:'success',         rejected:'danger',          completed:'primary' };

    tbody.innerHTML = rows.map(c => {
        const color       = statusColors[c.status] || 'secondary';
        const icon        = statusIcons[c.status]  || 'fa-circle';
        const requestedAt = formatDateTime(c.requested_at);
        const approvedAt  = c.approved_at ? formatDateTime(c.approved_at) : null;
        const travelDate  = c.travel_date ? formatDate(c.travel_date) : null;
        const amount      = parseFloat(c.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });

        const reviewBtn = c.status === 'pending'
            ? `<button class="btn btn-sm btn-success" title="Review"
                 onclick="openConfirmModal(
                   ${c.cancellation_id},
                   ${JSON.stringify(c.transaction_code)},
                   ${JSON.stringify(parseFloat(c.refund_amount).toFixed(2))},
                   ${JSON.stringify(c.cancellation_type)},
                   ${JSON.stringify(c.reason || '')},
                   ${JSON.stringify(c.requested_by_name || '—')},
                   ${JSON.stringify(c.passenger_name || '—')},
                   ${JSON.stringify(c.origin || '')},
                   ${JSON.stringify(c.destination || '')},
                   ${JSON.stringify(requestedAt)}
                 )"><span class="fas fa-check-double me-1"></span>Review</button>`
            : `<span class="text-muted small">Reviewed</span>`;

        return `<tr>
            <td class="ps-3 py-3">
                <div class="fw-semibold">${esc(c.transaction_code)}</div>
                <div class="text-muted small">${esc(c.provider_name || '—')}</div>
                <div class="text-muted" style="font-size:.75rem">${esc(c.branch_name || '—')}</div>
                <div class="text-muted" style="font-size:.75rem">${requestedAt}</div>
            </td>
            <td class="py-3">
                <div class="fw-semibold small">${esc(c.passenger_name || '—')}</div>
                ${c.origin && c.destination ? `<div class="text-muted small">${esc(c.origin)} → ${esc(c.destination)}</div>` : ''}
                ${travelDate ? `<div class="text-muted" style="font-size:.75rem">Travel: ${travelDate}</div>` : ''}
            </td>
            <td class="py-3">
                <div class="fw-semibold text-success fs-6">₱${amount}</div>
                <div class="small"><span class="badge bg-soft-primary text-primary">${esc(c.cancellation_type)}</span></div>
                ${c.reason ? `<div class="mt-1 text-muted small text-truncate" style="max-width:200px" title="${esc(c.reason)}">${esc(c.reason)}</div>` : '<div class="text-muted small">No reason</div>'}
            </td>
            <td class="py-3">
                <div class="fw-semibold small">${esc(c.requested_by_name || '—')}</div>
                ${approvedAt ? `<div class="text-muted" style="font-size:.75rem">By: ${esc(c.approved_by_name || '—')}</div><div class="text-muted" style="font-size:.75rem">${approvedAt}</div>` : ''}
            </td>
            <td class="py-3">
                <span class="badge status-badge-${c.status} fs-10 px-3 py-2">
                    <span class="fas ${icon} me-1"></span>${c.status.charAt(0).toUpperCase() + c.status.slice(1)}
                </span>
                ${c.rejection_reason ? `<div class="text-muted small mt-1 text-truncate" style="max-width:120px" title="${esc(c.rejection_reason)}">${esc(c.rejection_reason)}</div>` : ''}
            </td>
            <td class="py-3 text-end pe-3">${reviewBtn}</td>
        </tr>`;
    }).join('');
}

function renderPagination(pg) {
    if (!pg) return;
    const { total, per_page, current_page, total_pages, from, to } = pg;
    document.getElementById('tableInfo').textContent = total > 0
        ? `Showing ${from}–${to} of ${total} request${total !== 1 ? 's' : ''}`
        : 'No requests found';
    document.getElementById('paginationInfo').textContent = total > 0
        ? `Page ${current_page} of ${total_pages}` : '';

    const ul = document.getElementById('pagination');
    if (total_pages <= 1) { ul.innerHTML = ''; return; }

    let html = `<li class="page-item ${current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadCancellations(${current_page - 1});return false;">‹</a></li>`;

    for (let i = 1; i <= total_pages; i++) {
        if (i === 1 || i === total_pages || (i >= current_page - 1 && i <= current_page + 1)) {
            html += `<li class="page-item ${i === current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadCancellations(${i});return false;">${i}</a></li>`;
        } else if (i === current_page - 2 || i === current_page + 2) {
            html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
    }
    html += `<li class="page-item ${current_page === total_pages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadCancellations(${current_page + 1});return false;">›</a></li>`;
    ul.innerHTML = html;
}

function updateStats(stats) {
    if (!stats) return;
    document.getElementById('statPending').textContent       = stats.pending_count  || 0;
    document.getElementById('statApproved').textContent      = stats.approved_count || 0;
    document.getElementById('statRejected').textContent      = stats.rejected_count || 0;
    document.getElementById('statPendingAmount').textContent = '₱' + parseFloat(stats.pending_amount || 0)
        .toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

function resetFilters() {
    document.getElementById('filterSearch').value   = '';
    document.getElementById('filterStatus').value   = 'pending';
    document.getElementById('filterCashier').value  = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    const fp = document.getElementById('filterDateRange')?._flatpickr;
    if (fp) fp.clear();
    else { const el = document.getElementById('filterDateRange'); if (el) el.value = ''; }
    const branch = document.getElementById('filterBranch');
    if (branch) branch.value = '';
    const wallet = document.getElementById('filterWallet');
    if (wallet) wallet.value = '';
    loadCancellations(1);
}

function toggleHowItWorks() {
    const content = document.getElementById('howItWorksContent');
    const icon    = document.getElementById('howItWorksIcon');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDateTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
}

// Show toast notification
function showToast(type, title, message) {
    // Check if toast container exists, if not create it
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-primary';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-times-circle' : 'fa-info-circle';
    
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <span class="fas ${icon} me-2"></span>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}
