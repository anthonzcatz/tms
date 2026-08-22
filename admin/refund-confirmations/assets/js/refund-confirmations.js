/**
 * Refund Confirmations — AJAX-driven module
 */

let confirmCancellationModal;
let currentCancellationId = null;
let currentPage = 1;
let isLoading = false;

const REFUND_CONFIRMATION_REALTIME_EVENTS = [
    'pos.transaction.completed',
    'wallet.updated',
    'charge.updated',
    'bank.confirmation.updated',
    'refund.updated'
];

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
    startRefundConfirmationsRealtime();
});

function startRefundConfirmationsRealtime() {
    const config = window.REFUND_CONF_CONFIG || {};
    const pusherConfig = config.pusher || {};
    if (!window.TMSBranchRealtime) return;

    window.refundConfirmationsRealtime = window.TMSBranchRealtime.start({
        config: pusherConfig,
        branchIds: pusherConfig.branchIds,
        events: REFUND_CONFIRMATION_REALTIME_EVENTS,
        statusElement: 'refundConfirmationsRealtimeStatus',
        onUpdate: meta => {
            const payload = meta?.payload || {};
            if (payload.cancellation_id
                && currentCancellationId
                && Number(payload.cancellation_id) === Number(currentCancellationId)
                && payload.status !== 'pending') {
                invalidateCurrentCancellation('This cancellation was already processed by another user.');
            }
            return loadCancellations(currentPage, false);
        }
    });
}

function invalidateCurrentCancellation(message) {
    if (!currentCancellationId) return;
    currentCancellationId = null;
    if (confirmCancellationModal) confirmCancellationModal.hide();
    showToast('warning', 'Request Updated', message);
}

// ─── Data Loading ────────────────────────────────────────────────────────────

async function loadCancellations(page, showLoading = true) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page || 1;

    const tbody = document.getElementById('confirmationsTableBody');
    if (showLoading) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
            <span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>`;
        document.getElementById('tableInfo').textContent = 'Loading...';
    }

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
        _realtime: Date.now(),
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

// Handle review button click - parses data attribute and opens modal
function handleReviewClick(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-cancel'));
        openConfirmModal(
            data.id, data.code, data.ticketNumber, data.amount, data.type, data.reason,
            data.requestedBy, data.passenger, data.origin, data.destination, data.requestedAt,
            data.cashAmount, data.chargeAmount,
            data.providerName, data.branchName, data.variantName, data.walletId,
            data.isVariantWallet, data.paymentSources || [],
            data.operationType, data.reasonCategory, data.responsibility,
            data.responsibleUserId, data.responsibleCashierName,
            data.responsibilityAmount, data.grossRefundAmount, data.branchId,
            data.voidFee, data.voidServiceFee, data.lostSalesVoidFee, data.lostSalesServiceFee
        );
    } catch (e) {
        console.error('Failed to parse cancellation data:', e);
        showToast('danger', 'Error', 'Failed to load cancellation details');
    }
}

async function loadReviewCashiers(branchId, selectedUserId = null) {
    const select = document.getElementById('modalResponsibleCashier');
    const container = document.getElementById('modalResponsibleCashierContainer');
    if (!select || !container) return;

    select.innerHTML = '<option value="">Loading cashiers...</option>';
    select.disabled = true;
    if (!branchId) {
        select.innerHTML = '<option value="">Branch unavailable</option>';
        return;
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/cashiers?branch_id=${encodeURIComponent(branchId)}`);
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to load cashiers.');

        select.innerHTML = '<option value="">Select responsible cashier</option>';
        (result.data || []).forEach(cashier => {
            const option = document.createElement('option');
            const hasOpenSession = Boolean(cashier.session_id);
            option.value = cashier.user_id;
            option.textContent = `${cashier.display_name || cashier.fullname || cashier.username}${hasOpenSession ? ` — Session ${cashier.session_id}` : ' — No open session (charge recorded)'}`;
            select.appendChild(option);
        });
        if (selectedUserId) select.value = String(selectedUserId);
        select.disabled = false;
        if (!result.data?.length) {
            select.innerHTML = '<option value="">No cashier accounts found</option>';
            select.disabled = true;
        }
    } catch (error) {
        console.error('Failed to load review cashiers:', error);
        select.innerHTML = '<option value="">Unable to load cashiers</option>';
    }
}

// Open confirmation modal
function openConfirmModal(cancellationId, transactionCode, ticketNumber, refundAmount, cancellationType, reason, requestedBy, passenger, origin, destination, requestedAt, cashAmount, chargeAmount, providerName, branchName, variantName, walletId, isVariantWallet = false, paymentSources = [], operationType = 'REFUND', reasonCategory = 'OTHER', responsibility = 'NONE', responsibleUserId = null, responsibleCashierName = '', responsibilityAmount = 0, grossRefundAmount = refundAmount, branchId = null, voidFee = 0, voidServiceFee = 0, lostSalesVoidFee = 0, lostSalesServiceFee = 0) {
    currentCancellationId = cancellationId;

    const fmt = n => parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const cashAmt = parseFloat(cashAmount || 0);
    const chargeAmt = parseFloat(chargeAmount || 0);
    const isTechnicalIssueVoid = operationType === 'VOID' && reasonCategory === 'PRINTER_ERROR';
    const voidFeeAmt = operationType === 'VOID'
        ? parseFloat((isTechnicalIssueVoid ? lostSalesVoidFee : voidFee) || 0)
        : 0;
    const voidServiceFeeAmt = operationType === 'VOID' && !isTechnicalIssueVoid
        ? parseFloat(voidServiceFee || 0)
        : 0;

    const modalTitleEl = document.getElementById('confirmCancellationModalLabel');
    const modalSubtitleEl = modalTitleEl?.parentElement?.querySelector('p');
    if (modalTitleEl) {
        modalTitleEl.innerHTML = operationType === 'VOID'
            ? '<span class="fas fa-ban me-2"></span>Review Void Request'
            : '<span class="fas fa-money-bill-wave me-2"></span>Review Refund Request';
    }
    if (modalSubtitleEl) {
        modalSubtitleEl.textContent = operationType === 'VOID'
            ? 'Approve or reject this ticket void request'
            : 'Approve or reject this ticket refund request';
    }

    document.getElementById('modalTransactionCode').textContent = transactionCode;
    const ticketNumEl = document.getElementById('modalTicketNumber');
    if (ticketNumEl) {
        if (ticketNumber) {
            ticketNumEl.textContent = ticketNumber;
            ticketNumEl.parentElement.style.display = 'block';
        } else {
            ticketNumEl.parentElement.style.display = 'none';
        }
    }
    document.getElementById('modalRefundAmount').textContent = '₱' + fmt(refundAmount);
    const grossRefundEl = document.getElementById('modalGrossRefundAmount');
    if (grossRefundEl) grossRefundEl.textContent = '₱' + fmt(grossRefundAmount);
    const voidFeeEl = document.getElementById('modalVoidFee');
    const voidServiceFeeEl = document.getElementById('modalVoidServiceFee');
    const voidFeeContainer = document.getElementById('modalVoidFeeContainer');
    const voidServiceFeeContainer = document.getElementById('modalVoidServiceFeeContainer');
    const voidFeeLabel = voidFeeContainer?.querySelector('small');
    const voidServiceFeeLabel = voidServiceFeeContainer?.querySelector('small');
    if (voidFeeLabel) voidFeeLabel.textContent = isTechnicalIssueVoid ? 'Void Fee Lost Sales' : 'Void Fee Income';
    if (voidServiceFeeLabel) voidServiceFeeLabel.textContent = isTechnicalIssueVoid ? 'Service Fee Lost Sales' : 'Service Fee Income';
    if (voidFeeEl) voidFeeEl.textContent = '₱' + fmt(voidFeeAmt);
    if (voidServiceFeeEl) voidServiceFeeEl.textContent = '₱' + fmt(voidServiceFeeAmt);
    if (voidFeeContainer) voidFeeContainer.style.display = operationType === 'VOID' && responsibility === 'NONE' && voidFeeAmt > 0 ? '' : 'none';
    if (voidServiceFeeContainer) voidServiceFeeContainer.style.display = operationType === 'VOID' && responsibility === 'NONE' && voidServiceFeeAmt > 0 ? '' : 'none';
    document.getElementById('modalRefundAmountInline').textContent = '₱' + fmt(grossRefundAmount);
    document.getElementById('modalCancellationType').textContent = cancellationType;
    const operationEl = document.getElementById('modalOperationType');
    const responsibilityEl = document.getElementById('modalResponsibility');
    const responsibilityAmountEl = document.getElementById('modalResponsibilityAmount');
    const responsibilityContainer = document.getElementById('modalResponsibilityContainer');
    const responsibilityAmountContainer = document.getElementById('modalResponsibilityAmountContainer');
    const responsibleCashierContainer = document.getElementById('modalResponsibleCashierContainer');
    if (operationEl) operationEl.textContent = operationType === 'VOID' ? 'VOID — No Refund' : 'Refund';
    if (responsibilityEl) responsibilityEl.textContent = responsibility || 'NONE';
    if (responsibilityAmountEl) responsibilityAmountEl.textContent = '₱' + fmt(responsibilityAmount);
    if (responsibilityContainer) responsibilityContainer.style.display = responsibility === 'NONE' ? 'none' : '';
    if (responsibilityAmountContainer) responsibilityAmountContainer.style.display = responsibility === 'NONE' ? 'none' : '';
    if (responsibleCashierContainer) responsibleCashierContainer.style.display = responsibility === 'CASHIER' ? '' : 'none';
    if (responsibility === 'CASHIER') loadReviewCashiers(branchId, responsibleUserId);
    document.getElementById('modalPassenger').textContent = passenger;
    document.getElementById('modalRoute').textContent = (origin && destination) ? origin + ' → ' + destination : '-';
    document.getElementById('modalRequestedBy').textContent = requestedBy;
    document.getElementById('modalReason').textContent = reason || 'No reason provided';
    document.getElementById('modalRequestedAt').textContent = requestedAt;

    // Show refund breakdown in modal
    const refundBreakdownEl = document.getElementById('modalRefundBreakdown');
    if (refundBreakdownEl) {
        if (chargeAmt > 0) {
            refundBreakdownEl.innerHTML = `<div class="alert alert-info mb-0">
                <div class="d-flex justify-content-between"><span><i class="fas fa-hand-holding-usd me-1"></i>Cash to give:</span><span class="fw-bold">₱${fmt(cashAmt)}</span></div>
                <div class="d-flex justify-content-between"><span><i class="fas fa-file-invoice-dollar me-1"></i>Charge reversal:</span><span class="fw-bold">₱${fmt(chargeAmt)}</span></div>
            </div>`;
            refundBreakdownEl.style.display = 'block';
        } else {
            refundBreakdownEl.innerHTML = `<div class="alert alert-success mb-0">
                <div class="d-flex justify-content-between"><span><i class="fas fa-hand-holding-usd me-1"></i>Cash to give:</span><span class="fw-bold">₱${fmt(cashAmt)}</span></div>
            </div>`;
            refundBreakdownEl.style.display = 'block';
        }
    }

    // Show charge reversal warning
    const chargeWarningEl = document.getElementById('modalChargeReversalWarning');
    const chargeAmountEl = document.getElementById('modalChargeReversalAmount');
    if (chargeWarningEl && chargeAmountEl) {
        if (chargeAmt > 0) {
            chargeAmountEl.textContent = '₱' + fmt(chargeAmt);
            chargeWarningEl.style.display = 'block';
        } else {
            chargeWarningEl.style.display = 'none';
        }
    }

    // Show wallet-to-credit info
    const walletToCreditEl = document.getElementById('modalWalletToCredit');
    if (walletToCreditEl) {
        if (walletId) {
            let walletLabel = providerName || 'Provider wallet';
            if (variantName) walletLabel += ' - ' + variantName;
            if (branchName) walletLabel += ' (' + branchName + ')';
            walletToCreditEl.textContent = walletLabel;
            walletToCreditEl.parentElement.style.display = 'block';
        } else {
            walletToCreditEl.textContent = '—';
            walletToCreditEl.parentElement.style.display = 'block';
        }
    }

    const paymentSourcesEl = document.getElementById('modalPaymentSources');
    if (paymentSourcesEl) {
        paymentSourcesEl.innerHTML = paymentSources.length
            ? paymentSources.map(source => `<div class="d-flex justify-content-between small border-bottom py-1"><span>${esc(source.method_name || source.method_type || 'Payment')}</span><strong>₱${fmt(source.amount)}</strong></div>`).join('')
            : '<span class="text-muted small">No original payment lines found.</span>';
    }

    // Show/hide variant-specific wallet note
    const walletRestoreLine = document.getElementById('modalWalletRestoreLine');
    const variantNoCreditLine = document.getElementById('modalVariantNoCreditLine');
    const walletTxnRecordLine = document.getElementById('modalWalletTxnRecordLine');
    if (walletRestoreLine && variantNoCreditLine && walletTxnRecordLine) {
        if (isVariantWallet) {
            walletRestoreLine.classList.add('d-none');
            variantNoCreditLine.classList.remove('d-none');
            walletTxnRecordLine.classList.add('d-none');
        } else {
            walletRestoreLine.classList.remove('d-none');
            walletRestoreLine.innerHTML = operationType === 'VOID'
                ? '<small class="text-muted d-block">Provider wallet cost will be restored; no customer refund will be issued.</small>'
                : `Total refund amount <span id="modalRefundAmountInline" class="fw-bold text-success">₱${fmt(grossRefundAmount)}</span> will be <strong>restored to the provider wallet</strong>`;
            variantNoCreditLine.classList.add('d-none');
            walletTxnRecordLine.classList.remove('d-none');
        }
    }

    // Reset action and rejection reason (no default selection)
    document.getElementById('modalAction').value = '';
    document.getElementById('modalRejectionReason').value = '';
    document.getElementById('modalRemarks').value = '';
    document.getElementById('rejectionReasonDiv').style.display = 'none';
    document.getElementById('actionInfoAlert').classList.add('d-none');
    document.getElementById('rejectInfoAlert').classList.add('d-none');
    updateActionButtonStyles(null);

    confirmCancellationModal.show();
}

// Highlight the selected action button/card
function updateActionButtonStyles(selectedAction) {
    const btnApprove = document.getElementById('btnApprove');
    const btnReject = document.getElementById('btnReject');
    if (!btnApprove || !btnReject) return;

    if (selectedAction === 'approve') {
        btnApprove.classList.remove('btn-outline-success');
        btnApprove.classList.add('btn-success');
        btnReject.classList.remove('btn-danger');
        btnReject.classList.add('btn-outline-danger');
    } else if (selectedAction === 'reject') {
        btnReject.classList.remove('btn-outline-danger');
        btnReject.classList.add('btn-danger');
        btnApprove.classList.remove('btn-success');
        btnApprove.classList.add('btn-outline-success');
    } else {
        btnApprove.classList.remove('btn-success');
        btnApprove.classList.add('btn-outline-success');
        btnReject.classList.remove('btn-danger');
        btnReject.classList.add('btn-outline-danger');
    }
}

// Handle action button clicks in the refund-confirmations modal
function selectCancellationAction(action) {
    document.getElementById('modalAction').value = action;
    updateActionButtonStyles(action);

    const rejectionDiv = document.getElementById('rejectionReasonDiv');
    const infoAlert = document.getElementById('actionInfoAlert');
    const rejectAlert = document.getElementById('rejectInfoAlert');

    if (action === 'reject') {
        rejectionDiv.style.display = 'block';
        infoAlert.classList.add('d-none');
        rejectAlert.classList.remove('d-none');
    } else {
        rejectionDiv.style.display = 'none';
        infoAlert.classList.remove('d-none');
        rejectAlert.classList.add('d-none');
    }
}

// Submit cancellation decision
function submitCancellationDecision() {
    const action = document.getElementById('modalAction').value;
    if (!action) {
        showToast('danger', 'Error', 'Please select an action (Approve or Reject).');
        return;
    }

    const rejectionReason = document.getElementById('modalRejectionReason').value.trim();
    if (action === 'reject' && !rejectionReason) {
        showToast('danger', 'Error', 'Please provide a rejection reason.');
        return;
    }

    const responsibility = document.getElementById('modalResponsibility')?.textContent?.trim().toUpperCase() || 'NONE';
    const responsibleUserId = document.getElementById('modalResponsibleCashier')?.value || null;
    if (action === 'approve' && responsibility === 'CASHIER' && !responsibleUserId) {
        showToast('danger', 'Error', 'Select the responsible cashier before approving.');
        return;
    }

    const data = {
        cancellation_id: currentCancellationId,
        action: action,
        responsible_user_id: action === 'approve' && responsibility === 'CASHIER' ? responsibleUserId : null,
        rejection_reason: rejectionReason || null,
        remarks: document.getElementById('modalRemarks').value.trim() || null
    };
    
    const submitBtn = document.getElementById('modalSubmitDecision');
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
            currentCancellationId = null;
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
            <div>No refund requests found</div>
            <small>Try adjusting your filters</small>
        </div></td></tr>`;
        return;
    }
    const statusIcons  = { pending:'fa-clock', approved:'fa-check-circle', rejected:'fa-times-circle', completed:'fa-check-double' };
    const statusColors = { pending:'warning',  approved:'success',         rejected:'danger',          completed:'primary' };

    const fmt = n => parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });

    tbody.innerHTML = rows.map(c => {
        const color       = statusColors[c.status] || 'secondary';
        const icon        = statusIcons[c.status]  || 'fa-circle';
        const requestedAt = formatDateTime(c.requested_at);
        const approvedAt  = c.approved_at ? formatDateTime(c.approved_at) : null;
        const travelDate  = c.travel_date ? formatDate(c.travel_date) : null;
        const refundAmt   = parseFloat(c.refund_amount || 0);
        const chargeAmt   = parseFloat(c.charge_amount || 0);
        const cashAmt     = parseFloat(c.cash_refund_amount || 0);

        // Build refund breakdown display
        let refundBreakdown = `<div class="fw-semibold text-success fs-6">₱${fmt(refundAmt)}</div>`;
        if (chargeAmt > 0) {
            refundBreakdown += `<div class="small text-muted">
                <div><i class="fas fa-hand-holding-usd text-success me-1"></i>Cash: ₱${fmt(cashAmt)}</div>
                <div><i class="fas fa-file-invoice-dollar text-warning me-1"></i>Charge reversal: ₱${fmt(chargeAmt)}</div>
            </div>`;
        }

        const reviewBtn = c.status === 'pending'
            ? `<button class="btn btn-sm btn-success" title="Review" data-cancel='${JSON.stringify({
                   id: c.cancellation_id,
                   code: c.transaction_code,
                   ticketNumber: c.ticket_number || '',
                   amount: refundAmt.toFixed(2),
                   cashAmount: cashAmt.toFixed(2),
                   chargeAmount: chargeAmt.toFixed(2),
                   type: c.cancellation_type,
                   reason: c.reason || '',
                   requestedBy: c.requested_by_name || '—',
                   passenger: c.passenger_name || '—',
                   origin: c.origin || '',
                   destination: c.destination || '',
                   requestedAt: requestedAt,
                   providerName: c.provider_name || '—',
                   branchName: c.wallet_branch_name || c.branch_name || '—',
                   variantName: c.variant_name || '',
                   walletId: c.wallet_id || null,
                   isVariantWallet: parseInt(c.variant_id, 10) > 0 || parseInt(c.wallet_is_variant, 10) === 1,
                   paymentSources: c.payment_sources || [],
                   operationType: c.operation_type || 'REFUND',
                   reasonCategory: c.reason_category || 'OTHER',
                   responsibility: c.responsibility || 'NONE',
                   responsibleUserId: c.responsible_user_id || null,
                   responsibleCashierName: c.responsible_cashier_name || '',
                   responsibilityAmount: parseFloat(c.responsibility_amount || 0).toFixed(2),
                   grossRefundAmount: parseFloat(c.gross_refund_amount || c.refund_amount || 0).toFixed(2),
                   voidFee: parseFloat(c.void_fee || 0).toFixed(2),
                   voidServiceFee: parseFloat(c.void_service_fee || 0).toFixed(2),
                   lostSalesVoidFee: parseFloat(c.lost_sales_void_fee || 0).toFixed(2),
                   lostSalesServiceFee: parseFloat(c.lost_sales_service_fee || 0).toFixed(2),
                   branchId: c.branch_id || null
                 })}' onclick="handleReviewClick(this)"><span class="fas fa-check-double me-1"></span>Review</button>`
            : `<span class="text-muted small">Reviewed</span>`;

        const ticketNumberDisplay = c.ticket_number ? `<div class="small text-info"><i class="fas fa-ticket-alt me-1"></i>${esc(c.ticket_number)}</div>` : '';
        const amountDisplay = c.refund_amount ? `<div class="fw-semibold text-success">₱${parseFloat(c.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>` : '';
        const isTechnicalIssueVoid = c.operation_type === 'VOID' && c.reason_category === 'PRINTER_ERROR';
        const voidFeeAmount = parseFloat((isTechnicalIssueVoid ? c.lost_sales_void_fee : c.void_fee) || 0);
        const voidServiceFeeAmount = isTechnicalIssueVoid
            ? 0
            : parseFloat(c.void_service_fee || 0);
        const voidFeeLabel = isTechnicalIssueVoid ? 'Void fee lost sales' : 'Void fee income';
        const voidServiceFeeLabel = 'Service fee income';
        const voidFeeClass = isTechnicalIssueVoid ? 'text-danger' : 'text-warning';
        const voidIncomeDisplay = c.operation_type === 'VOID' && c.responsibility === 'NONE' && (voidFeeAmount > 0 || voidServiceFeeAmount > 0)
            ? `<div class="small ${voidFeeClass} mt-1">${voidFeeAmount > 0 ? `${voidFeeLabel}: ₱${fmt(voidFeeAmount)}` : ''}${voidFeeAmount > 0 && voidServiceFeeAmount > 0 ? '<br>' : ''}${voidServiceFeeAmount > 0 ? `${voidServiceFeeLabel}: ₱${fmt(voidServiceFeeAmount)}` : ''}</div>`
            : '';
        const operationDisplay = c.operation_type === 'VOID'
            ? '<span class="badge bg-soft-warning text-warning">VOID — No Refund</span>'
            : '<span class="badge bg-soft-primary text-primary">Refund</span>';
        const responsibilityDisplay = c.responsibility && c.responsibility !== 'NONE'
            ? `<div class="small text-danger mt-1">${esc(c.responsibility)}${parseFloat(c.responsibility_amount || 0) > 0 ? ` — ₱${fmt(c.responsibility_amount)}` : ''}${c.responsible_cashier_name ? ` (${esc(c.responsible_cashier_name)})` : ''}</div>`
            : '';
        return `<tr>
            <td class="ps-3 py-3">
                <div class="fw-semibold">${esc(c.transaction_code)}</div>
                ${ticketNumberDisplay}
            </td>
            <td class="py-3">
                <div class="fw-semibold small">${esc(c.passenger_name || '—')}</div>
            </td>
            <td class="py-3">
                ${amountDisplay}
                <div class="small mt-1">${operationDisplay}</div>
                ${voidIncomeDisplay}
                ${responsibilityDisplay}
                <div class="small mt-1"><span class="badge bg-soft-primary text-primary">${esc(c.cancellation_type)}</span></div>
            </td>
            <td class="py-3">
                <div class="fw-semibold small">${esc(c.requested_by_name || '—')}</div>
            </td>
            <td class="py-3">
                <span class="badge status-badge-${c.status} fs-10 px-3 py-2">
                    <span class="fas ${icon} me-1"></span>${c.status.charAt(0).toUpperCase() + c.status.slice(1)}
                </span>
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
    // Show total pending refund amount with breakdown
    const pendingTotal = parseFloat(stats.pending_total_amount || 0);
    const pendingCash = parseFloat(stats.pending_cash_amount || 0);
    const pendingCharge = parseFloat(stats.pending_charge_amount || 0);
    let amountText = '₱' + pendingTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    if (pendingCharge > 0 && pendingCash > 0) {
        // Mixed: both cash and charge reversal
        amountText += `<div class="text-muted" style="font-size: 0.7rem; margin-top: 2px;">
            <div>• Cash: ₱${pendingCash.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
            <div>• Charge reversal: ₱${pendingCharge.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
        </div>`;
    } else if (pendingCharge > 0) {
        // Only charge reversal
        amountText += `<div class="text-muted" style="font-size: 0.7rem; margin-top: 2px;">• Charge reversal: ₱${pendingCharge.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>`;
    } else if (pendingCash > 0) {
        // Only cash
        amountText += `<div class="text-muted" style="font-size: 0.7rem; margin-top: 2px;">• Cash: ₱${pendingCash.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>`;
    }
    document.getElementById('statPendingAmount').innerHTML = amountText;
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
