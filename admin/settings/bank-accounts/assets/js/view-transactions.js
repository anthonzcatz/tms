let transactionsCurrentPage = 1;
let transactionsPerPage = 20;
let transactionsTotalPages = 1;

/**
 * Open view transactions modal
 */
function openViewTransactionsModal(bankAccountId) {
    document.getElementById('viewBankAccountId').value = bankAccountId;
    transactionsCurrentPage = 1;
    
    // Set default date range to last 30 days
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];
    document.getElementById('filterDateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
    
    loadTransactions();
    
    const modal = new bootstrap.Modal(document.getElementById('viewTransactionsModal'));
    modal.show();
}

/**
 * Load transactions from API
 */
function loadTransactions() {
    const bankAccountId = document.getElementById('viewBankAccountId').value;
    const tbody = document.getElementById('transactionsTableBody');
    const txnType = document.getElementById('filterTxnType').value;
    const direction = document.getElementById('filterDirection').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-4">
                <span class="fas fa-spinner fa-spin"></span> Loading...
            </td>
        </tr>
    `;
    
    let url = `${window.BASE_URL}/api/bank-transactions?bank_account_id=${IdEncoder.encode(bankAccountId)}&page=${transactionsCurrentPage}&limit=${transactionsPerPage}`;
    
    if (txnType) url += `&txn_type=${txnType}`;
    if (direction) url += `&direction=${direction}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    
    fetch(url, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            renderTransactions(data.data.transactions);
            transactionsTotalPages = data.data.pagination.total_pages;
            updatePaginationInfo(data.data.pagination);
            renderPagination();
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        ${data.error || 'Failed to load transactions'}
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading transactions:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-danger">
                    Error loading transactions
                </td>
            </tr>
        `;
    });
}

/**
 * Render transactions table
 */
function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionsTableBody');
    
    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    No transactions found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = transactions.map(t => {
        const directionBadge = t.direction === 'IN' 
            ? '<span class="badge bg-success-subtle text-success">IN</span>'
            : '<span class="badge bg-danger-subtle text-danger">OUT</span>';
        
        const amountClass = t.direction === 'IN' ? 'text-success' : 'text-danger';
        const amountPrefix = t.direction === 'IN' ? '+' : '-';
        
        return `
            <tr>
                <td>${formatDateTime(t.created_at)}</td>
                <td><code>${t.txn_code || '-'}</code></td>
                <td>${t.txn_type}</td>
                <td>${directionBadge}</td>
                <td class="${amountClass} fw-bold">${amountPrefix}₱${parseFloat(t.amount).toFixed(2)}</td>
                <td>₱${parseFloat(t.balance_before).toFixed(2)}</td>
                <td>₱${parseFloat(t.balance_after).toFixed(2)}</td>
                <td class="text-truncate" style="max-width: 200px;">${t.remarks || '-'}</td>
                <td>${t.created_by_name || '-'}</td>
            </tr>
        `;
    }).join('');
}

/**
 * Update pagination info
 */
function updatePaginationInfo(pagination) {
    const info = document.getElementById('transactionsPaginationInfo');
    const start = (pagination.current_page - 1) * pagination.per_page + 1;
    const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
    info.textContent = `Showing ${start}-${end} of ${pagination.total} transactions`;
}

/**
 * Render pagination controls
 */
function renderPagination() {
    const nav = document.getElementById('transactionsPagination');
    const ul = nav.querySelector('ul');
    
    if (transactionsTotalPages <= 1) {
        ul.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<li class="page-item ${transactionsCurrentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="goToTransactionPage(${transactionsCurrentPage - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= transactionsTotalPages; i++) {
        if (i === 1 || i === transactionsTotalPages || (i >= transactionsCurrentPage - 1 && i <= transactionsCurrentPage + 1)) {
            html += `<li class="page-item ${i === transactionsCurrentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToTransactionPage(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === transactionsCurrentPage - 2 || i === transactionsCurrentPage + 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${transactionsCurrentPage === transactionsTotalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="goToTransactionPage(${transactionsCurrentPage + 1}); return false;">Next</a>
    </li>`;
    
    ul.innerHTML = html;
}

/**
 * Go to specific page
 */
function goToTransactionPage(page) {
    if (page < 1 || page > transactionsTotalPages) return;
    transactionsCurrentPage = page;
    loadTransactions();
}

/**
 * Apply filters
 */
function applyTransactionFilters() {
    transactionsCurrentPage = 1;
    loadTransactions();
}

/**
 * Clear filters
 */
function clearTransactionFilters() {
    document.getElementById('filterTxnType').value = '';
    document.getElementById('filterDirection').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    transactionsCurrentPage = 1;
    loadTransactions();
}

/**
 * Export transactions to CSV
 */
function exportTransactions() {
    const bankAccountId = document.getElementById('viewBankAccountId').value;
    const txnType = document.getElementById('filterTxnType').value;
    const direction = document.getElementById('filterDirection').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    let url = `${window.BASE_URL}/api/bank-transactions?bank_account_id=${IdEncoder.encode(bankAccountId)}&limit=1000`;
    
    if (txnType) url += `&txn_type=${txnType}`;
    if (direction) url += `&direction=${direction}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    
    fetch(url, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.transactions) {
            exportToCSV(data.data.transactions);
        } else {
            showToast('error', 'Error', 'Failed to export transactions');
        }
    })
    .catch(error => {
        console.error('Error exporting transactions:', error);
        showToast('error', 'Error', 'Failed to export transactions');
    });
}

/**
 * Export data to CSV
 */
function exportToCSV(transactions) {
    const headers = ['Date', 'Transaction Code', 'Type', 'Direction', 'Amount', 'Balance Before', 'Balance After', 'Remarks', 'Created By'];
    const rows = transactions.map(t => [
        t.created_at,
        t.txn_code || '',
        t.txn_type,
        t.direction,
        t.amount,
        t.balance_before,
        t.balance_after,
        t.remarks || '',
        t.created_by_name || ''
    ]);
    
    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
        csv += row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bank_transactions_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Format date/time
 */
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Open balance adjustment modal
 */
function openBalanceAdjustmentModal(bankAccountId, currentBalance) {
    document.getElementById('adjustBankAccountId').value = bankAccountId;
    document.getElementById('adjustCurrentBalance').value = currentBalance;
    document.getElementById('displayCurrentBalance').textContent = '₱' + parseFloat(currentBalance).toFixed(2);
    document.getElementById('adjustAmount').value = '';
    document.getElementById('adjustNewBalance').value = '';
    document.getElementById('adjustRemarks').value = '';
    
    // Reset direction radio buttons
    document.querySelectorAll('input[name="adjustmentDirection"]').forEach(rb => rb.checked = false);
    
    // Hide warning
    document.getElementById('adjustmentWarning').classList.add('d-none');
    
    const modal = new bootstrap.Modal(document.getElementById('balanceAdjustmentModal'));
    modal.show();
}

/**
 * Calculate new balance on input change
 */
document.addEventListener('DOMContentLoaded', function() {
    const directionInputs = document.querySelectorAll('input[name="adjustmentDirection"]');
    const amountInput = document.getElementById('adjustAmount');
    
    function calculateNewBalance() {
        const currentBalance = parseFloat(document.getElementById('adjustCurrentBalance').value) || 0;
        const amount = parseFloat(amountInput.value) || 0;
        const direction = document.querySelector('input[name="adjustmentDirection"]:checked')?.value;
        
        const warningDiv = document.getElementById('adjustmentWarning');
        const warningText = document.getElementById('adjustmentWarningText');
        
        if (!direction || amount === 0) {
            document.getElementById('adjustNewBalance').value = '';
            warningDiv.classList.add('d-none');
            return;
        }
        
        let newBalance;
        if (direction === 'IN') {
            newBalance = currentBalance + amount;
            warningDiv.classList.add('d-none');
        } else {
            newBalance = currentBalance - amount;
            if (newBalance < 0) {
                warningText.textContent = 'Warning: This will result in negative balance';
                warningDiv.classList.remove('d-none');
            } else {
                warningDiv.classList.add('d-none');
            }
        }
        
        document.getElementById('adjustNewBalance').value = newBalance.toFixed(2);
    }
    
    directionInputs.forEach(input => {
        input.addEventListener('change', calculateNewBalance);
    });
    
    amountInput.addEventListener('input', calculateNewBalance);
});

/**
 * Submit balance adjustment
 */
function submitBalanceAdjustment() {
    const bankAccountId = document.getElementById('adjustBankAccountId').value;
    const direction = document.querySelector('input[name="adjustmentDirection"]:checked')?.value;
    const amount = parseFloat(document.getElementById('adjustAmount').value);
    const remarks = document.getElementById('adjustRemarks').value;
    
    if (!direction) {
        showToast('error', 'Validation Error', 'Please select adjustment type (IN or OUT)');
        return;
    }
    
    if (!amount || amount <= 0) {
        showToast('error', 'Validation Error', 'Please enter a valid amount');
        return;
    }
    
    const currentBalance = parseFloat(document.getElementById('adjustCurrentBalance').value);
    const newBalance = parseFloat(document.getElementById('adjustNewBalance').value);
    
    if (direction === 'OUT' && newBalance < 0) {
        showToast('error', 'Validation Error', 'Cannot adjust to negative balance');
        return;
    }
    
    // Hide the Balance Adjustment modal before showing the confirmation modal
    const balanceModalEl = document.getElementById('balanceAdjustmentModal');
    const balanceModal = bootstrap.Modal.getInstance(balanceModalEl);

    const showConfirm = () => {
        window.adjustmentCompleted = false;
        showAdjustmentConfirmModal(currentBalance, amount, direction, newBalance, () => {
            performAdjustment(bankAccountId, direction, amount, remarks);
        });
    };

    if (balanceModal) {
        balanceModalEl.addEventListener('hidden.bs.modal', showConfirm, { once: true });
        balanceModal.hide();
    } else {
        showConfirm();
    }
}

/**
 * Show confirmation modal for balance adjustment
 */
function showAdjustmentConfirmModal(currentBalance, amount, direction, newBalance, onConfirm) {
    const directionLabel = direction === 'IN' ? 'Add' : 'Deduct';
    const directionClass = direction === 'IN' ? 'text-success' : 'text-danger';
    
    const modalHtml = `
        <div class="modal fade" id="adjustmentConfirmModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            <span class="fas fa-exclamation-triangle text-warning me-2"></span>
                            Confirm Balance Adjustment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td class="fw-bold">Current Balance:</td>
                                        <td class="text-end">₱${currentBalance.toFixed(2)}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Adjustment:</td>
                                        <td class="text-end ${directionClass}">${direction === 'IN' ? '+' : '-'}₱${amount.toFixed(2)}</td>
                                    </tr>
                                    <tr class="table-bordered">
                                        <td class="fw-bold bg-light">New Balance:</td>
                                        <td class="text-end fw-bold ${newBalance >= 0 ? 'text-success' : 'text-danger'} bg-light">₱${newBalance.toFixed(2)}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <span class="fas fa-info-circle me-2"></span>
                            This adjustment will be recorded in the transaction history.
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmAdjustment">
                            <label class="form-check-label" for="confirmAdjustment">
                                I confirm this balance adjustment is correct
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                            <span class="fas fa-times me-1"></span>Cancel
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="confirmAdjustmentBtn" disabled onclick="confirmAdjustmentAction()">
                            <span class="fas fa-check me-1"></span>Confirm Adjustment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('adjustmentConfirmModal');
    if (existingModal) existingModal.remove();
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modalElement = document.getElementById('adjustmentConfirmModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Enable confirm button when checkbox is checked
    document.getElementById('confirmAdjustment').addEventListener('change', function() {
        document.getElementById('confirmAdjustmentBtn').disabled = !this.checked;
    });
    
    // Store callback for confirm action
    window.onAdjustmentConfirmed = onConfirm;
    
    modal.show();
    
    // Cleanup on hide
    modalElement.addEventListener('hidden.bs.modal', function() {
        modalElement.remove();
        window.onAdjustmentConfirmed = null;

        // If adjustment was not completed, reopen the Balance Adjustment modal
        if (!window.adjustmentCompleted) {
            const balanceModalEl = document.getElementById('balanceAdjustmentModal');
            const balanceModal = bootstrap.Modal.getInstance(balanceModalEl) || new bootstrap.Modal(balanceModalEl);
            balanceModal.show();
        }
    });
}

/**
 * Perform the actual adjustment
 */
function performAdjustment(bankAccountId, direction, amount, remarks) {
    fetch(`${window.BASE_URL}/api/bank-transactions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            bank_account_id: bankAccountId,
            direction: direction,
            amount: amount,
            remarks: remarks
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Success', 'Balance adjustment recorded successfully');
            window.adjustmentCompleted = true;

            const confirmModalEl = document.getElementById('adjustmentConfirmModal');
            const balanceModalEl = document.getElementById('balanceAdjustmentModal');
            const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
            const balanceModal = bootstrap.Modal.getInstance(balanceModalEl);

            if (confirmModal) confirmModal.hide();
            if (balanceModal) balanceModal.hide();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('error', 'Error', data.error || 'Failed to record adjustment');
        }
    })
    .catch(error => {
        console.error('Error submitting adjustment:', error);
        showToast('error', 'Error', 'Failed to record adjustment');
    });
}

/**
 * Confirm adjustment action called from modal
 */
function confirmAdjustmentAction() {
    if (window.onAdjustmentConfirmed) {
        window.onAdjustmentConfirmed();
    }
}


