// Cashier Shift Reports Module

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

/**
 * Show a Bootstrap-styled confirmation modal instead of the native alert/confirm.
 * @param {string} title
 * @param {string} message - Newlines will be converted to <br>. Pass options.html = true if message already contains HTML.
 * @param {Object} options
 * @param {string} [options.icon='question'] - Font Awesome icon class suffix
 * @param {string} [options.iconColor='text-warning']
 * @param {string} [options.confirmBtnColor='btn-danger']
 * @param {string} [options.confirmBtnText='Confirm']
 * @param {string} [options.cancelBtnText='Cancel']
 * @param {boolean} [options.html=false]
 * @returns {Promise<boolean>}
 */
function showConfirm(title, message, options = {}) {
    return new Promise((resolve) => {
        const icon = options.icon || 'question';
        const iconColor = options.iconColor || 'text-warning';
        const confirmBtnColor = options.confirmBtnColor || 'btn-primary';
        const confirmBtnText = options.confirmBtnText || 'Confirm';
        const cancelBtnText = options.cancelBtnText || 'Cancel';
        const allowHtml = options.html === true;
        const modalId = 'customConfirmModal';

        const existing = document.getElementById(modalId);
        if (existing) {
            const existingModal = bootstrap.Modal.getInstance(existing);
            if (existingModal) existingModal.hide();
            existing.remove();
        }

        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-bottom-0 pb-0">
                            <h5 class="modal-title fw-bold">${escapeHtml(title)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-4">
                            <div class="d-flex align-items-start">
                                <div class="me-3 flex-shrink-0">
                                    <span class="fas fa-${icon} ${iconColor} fa-2x"></span>
                                </div>
                                <div class="fs-10">${allowHtml ? message.replace(/\n/g, '<br>') : escapeHtml(message).replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">${escapeHtml(cancelBtnText)}</button>
                            <button type="button" class="btn ${confirmBtnColor}" id="customConfirmBtn">
                                <span class="fas fa-check me-2"></span>${escapeHtml(confirmBtnText)}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const container = document.createElement('div');
        container.innerHTML = modalHtml;
        document.body.appendChild(container);

        const modalEl = document.getElementById(modalId);
        const modal = new bootstrap.Modal(modalEl);
        let confirmed = false;

        const handleConfirm = () => {
            confirmed = true;
            modal.hide();
        };

        document.getElementById('customConfirmBtn').addEventListener('click', handleConfirm, { once: true });
        modalEl.addEventListener('hidden.bs.modal', () => {
            resolve(confirmed);
            setTimeout(() => {
                if (container.parentNode) document.body.removeChild(container);
            }, 300);
        }, { once: true });

        modal.show();
    });
}

let sessionDetailModal;
let recordDepositModal;
let currentSessionId = null;
let currentSearch = '';
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function () {
    sessionDetailModal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));
    recordDepositModal = new bootstrap.Modal(document.getElementById('recordDepositModal'));
});

async function viewSessionDetail(sessionId) {
    currentSessionId = sessionId;
    currentSearch = '';
    currentPage = 1;
    loadSessionDetails();
}

async function loadSessionDetails() {
    if (!currentSessionId) return;
    document.getElementById('sessionDetailSubtitle').textContent = 'Loading...';
    document.getElementById('sessionDetailContent').innerHTML =
        '<div class="text-center py-5"><span class="fas fa-spinner fa-spin fs-3"></span><p class="mt-2 text-muted">Loading session details...</p></div>';
    sessionDetailModal.show();

    try {
        const encodedSessionId = IdEncoder.encode(currentSessionId);
        const url = `${window.BASE_URL}/api/shifts?session_id=${encodedSessionId}&page=${currentPage}&limit=20${currentSearch ? '&search=' + encodeURIComponent(currentSearch) : ''}`;
        const res = await fetch(url);
        const result = await res.json();
        if (!result.success) {
            document.getElementById('sessionDetailContent').innerHTML = '<p class="text-danger text-center py-3">Failed to load session details.</p>';
            return;
        }

        const s = result.data.session;
        const txns = result.data.transactions;
        const payments = result.data.payments;

        document.getElementById('sessionDetailSubtitle').textContent =
            `${s.cashier_name} · ${s.branch_name ?? '—'} · ${s.session_code || 'SES-' + s.session_id}`;

        let html = '';

        // Session info header
        const startedDate = new Date(s.started_at);
        const endedDate = s.ended_at ? new Date(s.ended_at) : null;
        const startedFmt = startedDate.toLocaleString('en-PH', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        const endedFmt = endedDate ? endedDate.toLocaleString('en-PH', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        }) : '—';

        const statusColor = {'OPEN': 'success', 'CLOSED': 'primary', 'RECONCILED': 'purple'}[s.status] || 'secondary';
        const statusIcon = {'OPEN': 'fa-circle', 'CLOSED': 'fa-check-circle', 'RECONCILED': 'fa-star'}[s.status] || 'fa-circle';

        html += `
        <div class="card mb-4">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-3">
                <div class="text-muted small mb-1">Status</div>
                <div class="fw-bold"><span class="badge bg-soft-${statusColor} text-${statusColor}"><span class="fas ${statusIcon} me-1"></span>${s.status}</span></div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small mb-1">Started</div>
                <div class="fw-bold">${startedFmt}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small mb-1">Ended</div>
                <div class="fw-bold">${endedFmt}</div>
              </div>
            </div>
          </div>
        </div>`;

        // Deposit status if closed
        if (s.status === 'CLOSED' && s.deposit_status) {
            const depositColor = {'PENDING': 'warning', 'DEPOSITED': 'success', 'NOT_APPLICABLE': 'secondary'}[s.deposit_status] || 'secondary';
            const depositIcon = {'PENDING': 'fa-clock', 'DEPOSITED': 'fa-check-circle', 'NOT_APPLICABLE': 'fa-ban'}[s.deposit_status] || 'fa-circle';
            html += `
            <div class="card mb-4">
              <div class="card-body py-3">
                <div class="text-muted small mb-1">Deposit Status</div>
                <div class="fw-bold"><span class="badge bg-soft-${depositColor} text-${depositColor}"><span class="fas ${depositIcon} me-1"></span>${s.deposit_status}</span></div>
                ${s.deposited_at ? `<div class="text-muted small mt-1">Deposited at: ${new Date(s.deposited_at).toLocaleString()}</div>` : ''}
              </div>
            </div>`;
        }

        // Session summary
        const variance = parseFloat(s.cash_variance ?? 0);
        const varText = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
        const varClass = variance > 0.005 ? 'text-warning' : variance < -0.005 ? 'text-danger' : 'text-success';
        const varLabel = Math.abs(variance) < 0.005 ? 'Balanced' : variance < 0 ? 'Short' : 'Over';

        // Calculate expected cash if not set (for OPEN sessions)
        const expectedCash = s.expected_cash != null ? parseFloat(s.expected_cash) || 0 : 0;

        html += `
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="d-flex align-items-center">
                  <div class="icon-circle icon-circle-primary me-3"><span class="fas fa-wallet text-primary"></span></div>
                  <div>
                    <div class="text-muted small mb-1">Opening Cash</div>
                    <div class="fw-bold fs-5">₱${fmt(s.starting_cash)}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="d-flex align-items-center">
                  <div class="icon-circle icon-circle-success me-3"><span class="fas fa-chart-line text-success"></span></div>
                  <div>
                    <div class="text-muted small mb-1">Total Sales</div>
                    <div class="fw-bold fs-5 text-success">₱${fmt(s.net_sales ?? s.total_sales)}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="text-muted small mb-1">Expected / Actual Cash</div>
                <div class="fw-bold">₱${fmt(expectedCash)} / ₱${fmt(s.actual_cash || 0)}</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="text-muted small mb-1">Variance</div>
                <div class="fw-bold fs-5 ${varClass}">${varText}
                  <span class="badge bg-soft-${Math.abs(variance) < 0.005 ? 'success text-success' : variance < 0 ? 'danger text-danger' : 'warning text-warning'} ms-1 small">${varLabel}</span>
                </div>
              </div>
            </div>
          </div>
        </div>`;

        // Payment type breakdown with include_in_expected_cash indicator
        if ((payments && payments.length > 0)
            || parseFloat(s.void_income || 0) > 0
            || parseFloat(s.technical_lost_sales_amount || 0) > 0) {
            html += '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
            html += '<div class="card mb-4"><div class="card-body py-3"><table class="table table-hover table-borderless fs-10 mb-0">';

            // Opening cash row
            html += `
              <tr class="border-bottom">
                <td class="ps-0 pt-0"><strong>Opening Cash</strong>
                  <div class="text-400 fw-normal fs-11 text-secondary">STARTING BALANCE</div>
                </td>
                <td class="pe-0 text-end pt-0"><strong>₱${fmt(s.starting_cash)}</strong></td>
              </tr>`;

            // Calculate refunds and cash change first for use in loop
            const refundedSalesAmount = parseFloat(s.refunded_sales_amount || 0);
            const approvedRefundsAmount = parseFloat(s.approved_refunds_amount ?? refundedSalesAmount);
            const totalCashChange = parseFloat(s.total_cash_change || 0);
            const totalCashAdjustments = parseFloat(s.total_cash_adjustments || 0);
            const voidIncome = parseFloat(s.void_income || 0);
            const voidLostSalesAmount = parseFloat(s.technical_lost_sales_amount || 0);

            // Payment methods from API with include_in_expected_cash indicator
            payments.forEach((p, index) => {
                const isLast = index === payments.length - 1 && approvedRefundsAmount <= 0 && totalCashChange <= 0 && totalCashAdjustments <= 0;
                const borderClass = !isLast ? 'border-bottom' : '';
                const totalAmount = parseFloat(p.total_amount ?? p.active_amount ?? 0);
                const inCashBadge = Number(p.include_in_expected_cash) === 1
                    ? '<span class="badge bg-soft-success text-success fs-11 ms-1"><span class="fas fa-cash-register me-1"></span>In Cash</span>'
                    : '<span class="badge bg-soft-secondary text-secondary fs-11 ms-1"><span class="fas fa-ban me-1"></span>Not Cash</span>';

                html += `
                  <tr class="${borderClass}">
                    <td class="ps-0">
                      <div class="fw-semibold">${p.method_name} ${inCashBadge}</div>
                      <div class="text-400 fw-normal fs-11 text-uppercase">${p.method_type}</div>
                    </td>
                    <td class="pe-0 text-end"><strong>₱${fmt(totalAmount)}</strong></td>
                  </tr>`;
            });

            if (voidIncome > 0) {
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0"><strong>Void Income</strong>
                      <div class="text-400 fw-normal fs-11 text-success">VOID FEE + SERVICE FEE</div>
                    </td>
                    <td class="pe-0 text-end text-success"><strong>+₱${fmt(voidIncome)}</strong></td>
                  </tr>`;
            }
            if (voidLostSalesAmount > 0) {
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0"><strong>Void Lost Sales</strong>
                      <div class="text-400 fw-normal fs-11 text-danger">DEDUCTED FROM EXPECTED CASH</div>
                    </td>
                    <td class="pe-0 text-end text-danger"><strong>-₱${fmt(voidLostSalesAmount)}</strong></td>
                  </tr>`;
            }

            // Show cash change row if any
            if (totalCashChange > 0) {
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0"><strong>Change (Cash Out)</strong>
                      <div class="text-400 fw-normal fs-11 text-warning">CASH GIVEN TO CUSTOMERS</div>
                    </td>
                    <td class="pe-0 text-end text-warning"><strong>-₱${fmt(totalCashChange)}</strong></td>
                  </tr>`;
            }

            // Show refunds row if there are refunds
            if (approvedRefundsAmount > 0) {
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0"><strong>Approved Refunds</strong>
                      <div class="text-400 fw-normal fs-11 text-danger">PAYMENT TOTALS</div>
                    </td>
                    <td class="pe-0 text-end text-danger"><strong>-₱${fmt(approvedRefundsAmount)}</strong></td>
                  </tr>`;
            }
            if (totalCashAdjustments > 0) {
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0"><strong>Cashier Responsibility Deductions</strong>
                      <div class="text-400 fw-normal fs-11 text-danger">ACCOUNTABILITY ADJUSTMENT</div>
                    </td>
                    <td class="pe-0 text-end text-danger"><strong>-₱${fmt(totalCashAdjustments)}</strong></td>
                  </tr>`;
            }

            // Show expected cash calculation
            const expectedCashCalc = payments
                .filter(p => Number(p.include_in_expected_cash) === 1)
                .reduce((sum, p) => sum + parseFloat(p.total_amount || 0), 0);
            const expectedCalcFormula = [
                'STARTING + IN CASH',
                approvedRefundsAmount > 0 ? '- REFUNDS' : null,
                voidIncome > 0 ? '+ VOID INCOME' : null,
                voidLostSalesAmount > 0 ? '- VOID LOST SALES' : null,
                '- CHANGE',
                '- CASHIER DEDUCTIONS'
            ].filter(Boolean).join(' ');

            html += `
              <tr class="table-light">
                <td class="ps-0 pb-0 pt-2"><strong>Expected Cash</strong>
                  <div class="text-400 fw-normal fs-11 text-success">${expectedCalcFormula}</div>
                </td>
                <td class="pe-0 text-end pb-0 pt-2 text-success"><strong>₱${fmt(s.expected_cash || 0)}</strong></td>
              </tr>
            </table>
          </div>
        </div>`;

            // Add collapsible info note about expected cash calculation
            const approvedRefundsText = approvedRefundsAmount > 0
                ? ' - Approved Refunds (₱' + fmt(approvedRefundsAmount) + ')'
                : '';
            const changeText = totalCashChange > 0 ? ' - Cash Change (₱' + fmt(totalCashChange) + ')' : '';
            const voidIncomeText = voidIncome > 0 ? ' + Void Fee / Service Fee Income (₱' + fmt(voidIncome) + ')' : '';
            const voidLostSalesText = voidLostSalesAmount > 0 ? ' - Void Lost Sales (₱' + fmt(voidLostSalesAmount) + ')' : '';
            const adjustmentText = totalCashAdjustments > 0 ? ' - Cashier Deductions (₱' + fmt(totalCashAdjustments) + ')' : '';
            html += `
            <div class="alert alert-info fs-10 mb-4">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fas fa-info-circle me-2"></span>
                <strong>How Expected Cash is calculated</strong>
                <button class="btn btn-link btn-sm p-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#expectedCashInfo${s.session_id}" aria-expanded="false">
                  <span class="fas fa-chevron-down" id="expectedCashInfoIcon${s.session_id}"></span>
                </button>
              </div>
              <div class="collapse mt-2" id="expectedCashInfo${s.session_id}">
                <small>Starting Cash (₱${fmt(s.starting_cash)}) + Payments marked "In Cash" (₱${fmt(expectedCashCalc)})${approvedRefundsText}${voidIncomeText}${voidLostSalesText}${changeText}${adjustmentText}</small>
              </div>
            </div>`;
        } else {
            html += '<p class="text-muted text-center py-3">No payments recorded in this session.</p>';
        }

        // Notes if any
        if (s.notes) {
            html += `
            <div class="alert alert-info mb-4">
              <div class="fw-bold mb-1"><span class="fas fa-sticky-note me-2"></span>Notes</div>
              <div class="small">${s.notes}</div>
            </div>`;
        }

        // Reviewed by info
        if (s.reviewed_by_name || s.reviewed_at) {
            const reviewedAt = s.reviewed_at ? new Date(s.reviewed_at).toLocaleString('en-PH', { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            }) : '—';
            html += `
            <div class="alert alert-success mb-4">
              <div class="fw-bold mb-1"><span class="fas fa-user-check me-2"></span>Reviewed</div>
              <div class="small">
                By: ${s.reviewed_by_name || '—'}<br>
                At: ${reviewedAt}
              </div>
            </div>`;
        }

        // Transactions with search and pagination
        const pagination = result.data.pagination || { current_page: 1, per_page: 20, total: 0, total_pages: 1 };
        html += `<h6 class="fw-bold mb-3"><span class="fas fa-receipt me-2 text-primary"></span>Transactions (${pagination.total})</h6>`;

        // Search input
        html += `
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><span class="fas fa-search"></span></span>
                    <input type="text" class="form-control" id="transactionSearch" placeholder="Search by order code or payment method..." value="${currentSearch || ''}" onkeyup="if(event.key === 'Enter') searchTransactions()">
                    <button class="btn btn-outline-secondary" type="button" onclick="searchTransactions()">Search</button>
                </div>
            </div>
        </div>`;

        if (txns && txns.length > 0) {
            html += '<div class="table-responsive"><table class="table table-hover mb-0"><thead class="bg-light"><tr><th class="ps-3">Order Code</th><th>Items</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="pe-3">Time</th></tr></thead><tbody>';
            txns.forEach(t => {
                html += `<tr>
                    <td class="ps-3"><code>${t.order_code}</code></td>
                    <td>${t.item_count || 0} item(s)</td>
                    <td class="text-end fw-semibold">₱${fmt(t.grand_total)}</td>
                    <td class="text-end text-success">₱${fmt(t.amount_paid)}</td>
                    <td class="text-muted small pe-3">${new Date(t.created_at).toLocaleTimeString()}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';

            // Pagination controls
            if (pagination.total_pages > 1) {
                html += `
                <nav class="d-flex justify-content-between align-items-center mt-3">
                    <div class="small text-muted">
                        Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total)} of ${pagination.total} transactions
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">Previous</a>
                        </li>`;
                for (let i = 1; i <= pagination.total_pages; i++) {
                    if (i === 1 || i === pagination.total_pages || (i >= pagination.current_page - 1 && i <= pagination.current_page + 1)) {
                        html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                        </li>`;
                    } else if (i === pagination.current_page - 2 || i === pagination.current_page + 2) {
                        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }
                }
                html += `<li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">Next</a>
                </li>
                    </ul>
                </nav>`;
            }
        } else {
            html += '<p class="text-muted text-center py-3">No transactions recorded in this session.</p>';
        }

        document.getElementById('sessionDetailContent').innerHTML = html;
    } catch (e) {
        document.getElementById('sessionDetailContent').innerHTML = '<p class="text-danger text-center py-3">Error loading session details.</p>';
    }
}

function searchTransactions() {
    const searchInput = document.getElementById('transactionSearch');
    if (searchInput) {
        currentSearch = searchInput.value.trim();
        currentPage = 1;
        loadSessionDetails();
    }
}

function changePage(page) {
    currentPage = page;
    loadSessionDetails();
}

// Record Deposit Functions
function openRecordDepositModal(sessionId, actualCash) {
    document.getElementById('depositSessionId').value = sessionId;
    document.getElementById('depositAmount').value = '₱' + fmt(actualCash);
    document.getElementById('depositCustomAmount').value = '';
    document.getElementById('depositBankAccountId').value = '';
    recordDepositModal.show();
}

async function submitRecordDeposit() {
    const sessionId = document.getElementById('depositSessionId').value;
    const bankAccountId = document.getElementById('depositBankAccountId').value;
    const customAmount = document.getElementById('depositCustomAmount').value;
    
    if (!bankAccountId) {
        showToast('danger', 'Validation Error', 'Please select a bank account.');
        return;
    }
    
    const payload = {
        session_id: sessionId,
        bank_account_id: bankAccountId
    };
    
    if (customAmount) {
        payload.deposit_amount = parseFloat(customAmount);
    }
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: sessionId,
                action: 'record_deposit',
                bank_account_id: bankAccountId,
                deposit_amount: customAmount ? parseFloat(customAmount) : null
            })
        });
        const result = await response.json();
        if (result.success) {
            recordDepositModal.hide();
            showToast('success', 'Deposit Recorded', 'Cash deposit has been recorded successfully.');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to record deposit.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Manager Session Control
let managerSessionModal;

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('managerSessionModal');
    if (modalEl) {
        managerSessionModal = new bootstrap.Modal(modalEl);
    }
});

let availableCashiers = []; // Store cashier data for branch lookup
let allCashiers = []; // Store all cashiers for filtering
let cashiersWithOpenSessions = new Set(); // Track cashiers who already have open sessions

async function openManagerSessionModal(action, preselectedSessionId = null) {
    if (!managerSessionModal) {
        showToast('danger', 'Error', 'Manager session control not available.');
        return;
    }

    // Reset form
    document.getElementById('managerSessionCashierId').value = '';
    document.getElementById('managerSessionCashierName').value = '';
    document.getElementById('managerOpenBranch').value = '';
    document.getElementById('managerOpenStartingCash').value = '';
    document.getElementById('managerOpenNotes').value = '';
    document.getElementById('managerOpenBranchDisplay').textContent = '—';
    document.getElementById('noBranchWarning').classList.add('d-none');
    document.getElementById('managerOpenSubmitBtn').disabled = false;

    try {
        if (action === 'open') {
            document.getElementById('openSessionSection').style.display = 'block';
            document.getElementById('closeSessionSection').style.display = 'none';
            document.getElementById('managerSessionModalLabel').innerHTML = '<span class="fas fa-play-circle me-2"></span>Open Session for Cashier';
            
            // Fetch both cashiers and open sessions in parallel
            const [usersRes, sessionsRes] = await Promise.all([
                fetch(`${window.BASE_URL}/api/users?role=CASHIER&status=active`),
                fetch(`${window.BASE_URL}/api/pos/sessions?status=open`)
            ]);
            
            const usersResult = await usersRes.json();
            const sessionsResult = await sessionsRes.json();
            
            // Track which cashiers already have open sessions
            cashiersWithOpenSessions = new Set();
            if (sessionsResult.success && sessionsResult.data) {
                sessionsResult.data.forEach(s => {
                    if (s.cashier_user_id) {
                        cashiersWithOpenSessions.add(String(s.cashier_user_id));
                    }
                });
            }
            
            // Store all cashiers with branches for filtering
            allCashiers = (usersResult.data || []).filter(c => c.branch_id !== null && c.branch_id !== '');
            
            // Filter cashiers: must have branch AND no open session
            availableCashiers = allCashiers.filter(c => {
                const hasBranch = c.branch_id !== null && c.branch_id !== '';
                const hasOpenSession = cashiersWithOpenSessions.has(String(c.user_id));
                return hasBranch && !hasOpenSession;
            });
            
            // Build cashier dropdown
            const cashierSelect = document.getElementById('managerOpenCashierSelect');
            let cashierOptions = '<option value="">Select Cashier</option>';
            
            // Add unavailable cashiers (with open sessions) as disabled options
            const unavailableCashiers = (usersResult.data || []).filter(c => {
                const hasBranch = c.branch_id !== null && c.branch_id !== '';
                const hasOpenSession = cashiersWithOpenSessions.has(String(c.user_id));
                return hasBranch && hasOpenSession;
            });
            
            if (availableCashiers.length === 0 && unavailableCashiers.length === 0) {
                cashierOptions = '<option value="">No available cashiers with assigned branches</option>';
                document.getElementById('managerOpenSubmitBtn').disabled = true;
            } else {
                // Add available cashiers
                availableCashiers.forEach(c => {
                    cashierOptions += `<option value="${c.user_id}" data-name="${c.fullname || c.username}" data-branch-id="${c.branch_id}" data-branch-name="${c.branch_name || 'Unknown'}" data-has-open-session="false">${c.fullname || c.username} - ${c.branch_name || 'Unknown Branch'}</option>`;
                });
                // Add unavailable cashiers (disabled)
                if (unavailableCashiers.length > 0) {
                    cashierOptions += '<optgroup label="Already has open session (disabled)">';
                    unavailableCashiers.forEach(c => {
                        cashierOptions += `<option value="${c.user_id}" disabled class="text-muted">${c.fullname || c.username} - ${c.branch_name || 'Unknown Branch'} (Session Active)</option>`;
                    });
                    cashierOptions += '</optgroup>';
                }
            }
            
            cashierSelect.innerHTML = cashierOptions;
            
        } else {
            document.getElementById('openSessionSection').style.display = 'none';
            document.getElementById('closeSessionSection').style.display = 'block';
            document.getElementById('managerSessionModalLabel').innerHTML = '<span class="fas fa-stop-circle me-2"></span>Close Session for Cashier';

            await loadCloseSessionData();

            // Pre-select the session if session ID is provided
            if (preselectedSessionId) {
                const select = document.getElementById('managerCloseSessionSelect');
                select.value = preselectedSessionId;
                onCloseSessionSelect(select);
            }
        }

        managerSessionModal.show();
    } catch (e) {
        showToast('danger', 'Error', 'Failed to load cashiers.');
    }
}

function switchToCloseSession() {
    // Reset the cashier select
    document.getElementById('managerOpenCashierSelect').value = '';
    onCashierSelect(document.getElementById('managerOpenCashierSelect'));
    
    // Hide open section, show close section
    document.getElementById('openSessionSection').style.display = 'none';
    document.getElementById('closeSessionSection').style.display = 'block';
    document.getElementById('managerSessionModalLabel').innerHTML = '<span class="fas fa-stop-circle me-2"></span>Close Session for Cashier';
    
    // Reload close session data
    loadCloseSessionData();
}

let allOpenSessions = []; // Store all open sessions for filtering
let currentSessionDetails = null; // Store selected session details

async function loadCloseSessionData() {
    try {
        const sessionRes = await fetch(`${window.BASE_URL}/api/pos/sessions?status=open`);
        const sessionResult = await sessionRes.json();
        
        if (sessionResult.success && sessionResult.data && sessionResult.data.length > 0) {
            allOpenSessions = sessionResult.data;
            renderSessionOptions(allOpenSessions);
        } else {
            allOpenSessions = [];
            document.getElementById('managerCloseSessionSelect').innerHTML = '<option value="">No open sessions found</option>';
            document.getElementById('closeSessionSummary').classList.add('d-none');
            document.getElementById('closeSessionEmptyState').classList.remove('d-none');
        }
    } catch (e) {
        allOpenSessions = [];
        document.getElementById('managerCloseSessionSelect').innerHTML = '<option value="">Error loading sessions</option>';
    }
}

function renderSessionOptions(sessions) {
    const select = document.getElementById('managerCloseSessionSelect');
    let sessionOptions = '<option value="">Select Cashier with Open Session</option>';
    
    sessions.forEach(s => {
        const startedTime = new Date(s.started_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
        sessionOptions += `<option value="${s.session_id}" data-branch-id="${s.branch_id}">${s.cashier_name || 'Unknown'} - ${s.branch_name || 'Unknown Branch'} (Started: ${startedTime})</option>`;
    });
    
    select.innerHTML = sessionOptions;
}

function filterCashierOptions() {
    const branchFilter = document.getElementById('managerOpenBranchFilter').value;
    const select = document.getElementById('managerOpenCashierSelect');
    
    // Filter available cashiers (no open session)
    let filteredAvailable = availableCashiers.filter(c => {
        // Handle multiple branch IDs (comma-separated) - check if branch_id contains the filter
        const cashierBranchIds = String(c.branch_id || '').split(',').map(id => id.trim());
        const matchesBranch = !branchFilter || cashierBranchIds.includes(branchFilter);
        return matchesBranch;
    });
    
    // Filter unavailable cashiers (with open sessions) for the optgroup
    let filteredUnavailable = (allCashiers || []).filter(c => {
        const hasOpenSession = cashiersWithOpenSessions.has(String(c.user_id));
        // Handle multiple branch IDs (comma-separated) - check if branch_id contains the filter
        const cashierBranchIds = String(c.branch_id || '').split(',').map(id => id.trim());
        const matchesBranch = !branchFilter || cashierBranchIds.includes(branchFilter);
        return hasOpenSession && matchesBranch;
    });
    
    let cashierOptions = '<option value="">Select Cashier</option>';
    
    if (filteredAvailable.length === 0 && filteredUnavailable.length === 0) {
        cashierOptions = '<option value="">No cashiers match filters</option>';
    } else {
        filteredAvailable.forEach(c => {
            cashierOptions += `<option value="${c.user_id}" data-name="${c.fullname || c.username}" data-branch-id="${c.branch_id}" data-branch-name="${c.branch_name || 'Unknown'}">${c.fullname || c.username} - ${c.branch_name || 'Unknown Branch'}</option>`;
        });
        if (filteredUnavailable.length > 0) {
            cashierOptions += '<optgroup label="Already has open session (disabled)">';
            filteredUnavailable.forEach(c => {
                cashierOptions += `<option value="${c.user_id}" disabled class="text-muted">${c.fullname || c.username} - ${c.branch_name || 'Unknown Branch'} (Session Active)</option>`;
            });
            cashierOptions += '</optgroup>';
        }
    }
    
    select.innerHTML = cashierOptions;
}

function filterCloseSessionOptions() {
    const branchFilter = document.getElementById('managerCloseBranchFilter').value;
    
    let filteredSessions = allOpenSessions.filter(s => {
        // Handle multiple branch IDs (comma-separated) - check if branch_id contains the filter
        const sessionBranchIds = String(s.branch_id || '').split(',').map(id => id.trim());
        const matchesBranch = !branchFilter || sessionBranchIds.includes(branchFilter);
        return matchesBranch;
    });
    
    renderSessionOptions(filteredSessions);
}

async function onCloseSessionSelect(select) {
    const sessionId = select.value;
    document.getElementById('managerCloseSessionId').value = sessionId;
    
    if (!sessionId) {
        document.getElementById('closeSessionSummary').classList.add('d-none');
        document.getElementById('closeSessionEmptyState').classList.remove('d-none');
        currentSessionDetails = null;
        return;
    }
    
    document.getElementById('closeSessionEmptyState').classList.add('d-none');
    document.getElementById('closeSessionSummary').classList.remove('d-none');
    
    try {
        // Fetch detailed session info including payments
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions?id=${sessionId}`);
        const result = await res.json();
        
        if (!result.success) {
            showToast('danger', 'Error', 'Failed to load session details');
            return;
        }
        
        currentSessionDetails = result.data;
        const s = currentSessionDetails.session;
        const payments = currentSessionDetails.payments || [];
        
        // Update summary cards
        document.getElementById('closeSummaryStarted').textContent = new Date(s.started_at).toLocaleString('en-PH', {
            weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        document.getElementById('closeSummaryBranch').textContent = s.branch_name || '—';
        document.getElementById('closeSummaryOpening').textContent = '₱' + fmt(s.starting_cash);
        document.getElementById('closeSummarySales').textContent = '₱' + fmt(s.net_sales ?? s.total_sales ?? 0);
        document.getElementById('closeSummaryExpected').textContent = '₱' + fmt(s.expected_cash || 0);
        document.getElementById('closeSummaryTxns').textContent = s.txn_count || 0;
        
        // Render payment breakdown
        renderPaymentBreakdown(payments, s);
        
        // Reset variance
        document.getElementById('managerCloseCash').value = '';
        document.getElementById('managerVarianceDisplay').textContent = '₱0.00';
        document.getElementById('managerVarianceDisplay').className = 'fw-bold fs-5';
        document.getElementById('managerVarianceStatus').innerHTML = '<span class="badge bg-soft-secondary text-secondary">Enter closing cash</span>';
        document.getElementById('managerVarianceCard').className = 'card';
        
    } catch (e) {
        showToast('danger', 'Error', 'Failed to load session details');
    }
}

function renderPaymentBreakdown(payments, session) {
    const container = document.getElementById('closePaymentBreakdown');
    const voidIncome = parseFloat(session.void_income || 0);
    const voidLostSalesAmount = parseFloat(session.technical_lost_sales_amount || 0);
    
    if ((!payments || payments.length === 0) && voidIncome <= 0 && voidLostSalesAmount <= 0) {
        container.innerHTML = '<div class="alert alert-info fs-10">No payments recorded in this session.</div>';
        return;
    }
    
    const refundedSalesAmount = parseFloat(session.refunded_sales_amount || 0);
    const approvedRefundsAmount = parseFloat(session.approved_refunds_amount ?? refundedSalesAmount);
    const pendingRefundsCash = parseFloat(session.pending_refunds_cash || 0);
    const showPendingRefunds = Boolean(session.show_pending_refunds_in_close_session) ||
        Boolean(window.CANCELLATION_SETTINGS?.show_pending_refunds_in_close_session);
    const displayedPendingRefunds = showPendingRefunds ? pendingRefundsCash : 0;
    const totalCashChange = parseFloat(session.total_cash_change || 0);
    const totalCashAdjustments = parseFloat(session.total_cash_adjustments || 0);
    const expectedCashCalc = payments
        .filter(p => Number(p.include_in_expected_cash) === 1)
        .reduce((sum, p) => sum + parseFloat(p.total_amount || 0), 0);
    
    let html = '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
    html += '<div class="card mb-3"><div class="card-body py-3"><table class="table table-hover table-borderless fs-10 mb-0">';
    
    // Opening cash row
    html += `
      <tr class="border-bottom">
        <td class="ps-0 pt-0"><strong>Opening Cash</strong>
          <div class="text-400 fw-normal fs-11 text-secondary">STARTING BALANCE</div>
        </td>
        <td class="pe-0 text-end pt-0"><strong>₱${fmt(session.starting_cash)}</strong></td>
      </tr>`;
    
    // Payment methods
    payments.forEach((p, index) => {
        const hasDeductions = totalCashChange > 0 || approvedRefundsAmount > 0 || displayedPendingRefunds > 0 || totalCashAdjustments > 0;
        const isLast = index === payments.length - 1 && !hasDeductions;
        const borderClass = !isLast || hasDeductions ? 'border-bottom' : '';
        const totalAmount = parseFloat(p.total_amount ?? p.active_amount ?? 0);
        const inCashBadge = Number(p.include_in_expected_cash) === 1
            ? '<span class="badge bg-soft-success text-success fs-11 ms-1"><span class="fas fa-cash-register me-1"></span>In Cash</span>'
            : '<span class="badge bg-soft-secondary text-secondary fs-11 ms-1"><span class="fas fa-ban me-1"></span>Not Cash</span>';
        
        html += `
          <tr class="${borderClass}">
            <td class="ps-0">
              <div class="fw-semibold">${p.method_name} ${inCashBadge}</div>
              <div class="text-400 fw-normal fs-11 text-uppercase">${p.method_type}</div>
            </td>
            <td class="pe-0 text-end"><strong>₱${fmt(totalAmount)}</strong></td>
          </tr>`;
    });

    if (voidIncome > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Void Income</strong>
              <div class="text-400 fw-normal fs-11 text-success">VOID FEE + SERVICE FEE</div>
            </td>
            <td class="pe-0 text-end text-success"><strong>+₱${fmt(voidIncome)}</strong></td>
          </tr>`;
    }
    if (voidLostSalesAmount > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Void Lost Sales</strong>
              <div class="text-400 fw-normal fs-11 text-danger">DEDUCTED FROM EXPECTED CASH</div>
            </td>
            <td class="pe-0 text-end text-danger"><strong>-₱${fmt(voidLostSalesAmount)}</strong></td>
          </tr>`;
    }

    // Cash change if any
    if (totalCashChange > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Change (Cash Out)</strong>
              <div class="text-400 fw-normal fs-11 text-warning">CASH GIVEN TO CUSTOMERS</div>
            </td>
            <td class="pe-0 text-end text-warning"><strong>-₱${fmt(totalCashChange)}</strong></td>
          </tr>`;
    }

    // Refunds if any
    if (approvedRefundsAmount > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Approved Refunds</strong>
              <div class="text-400 fw-normal fs-11 text-danger">PAYMENT TOTALS</div>
            </td>
            <td class="pe-0 text-end text-danger"><strong>-₱${fmt(approvedRefundsAmount)}</strong></td>
          </tr>`;
    }

    // Pending refunds if configured
    if (displayedPendingRefunds > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Pending Refunds</strong>
              <div class="text-400 fw-normal fs-11 text-warning">AWAITING APPROVAL</div>
            </td>
            <td class="pe-0 text-end text-warning"><strong>-₱${fmt(displayedPendingRefunds)}</strong></td>
          </tr>`;
    }
    if (totalCashAdjustments > 0) {
        html += `
          <tr class="border-bottom">
            <td class="ps-0"><strong>Cashier Responsibility Deductions</strong>
              <div class="text-400 fw-normal fs-11 text-danger">ACCOUNTABILITY ADJUSTMENT</div>
            </td>
            <td class="pe-0 text-end text-danger"><strong>-₱${fmt(totalCashAdjustments)}</strong></td>
          </tr>`;
    }

    // Expected cash row
    const expectedCalcFormula = [
        'STARTING + IN CASH',
        approvedRefundsAmount > 0 ? '- REFUNDS' : null,
        voidIncome > 0 ? '+ VOID INCOME' : null,
        voidLostSalesAmount > 0 ? '- VOID LOST SALES' : null,
        '- CHANGE',
        showPendingRefunds && displayedPendingRefunds > 0 ? '- PENDING REFUNDS' : null,
        totalCashAdjustments > 0 ? '- CASHIER DEDUCTIONS' : null
    ].filter(Boolean).join(' ');

    html += `
      <tr class="table-light">
        <td class="ps-0 pb-0 pt-2"><strong>Expected Cash</strong>
          <div class="text-400 fw-normal fs-11 text-success">${expectedCalcFormula}</div>
        </td>
        <td class="pe-0 text-end pb-0 pt-2 text-success"><strong>₱${fmt(session.expected_cash || 0)}</strong></td>
      </tr>
    </table></div></div>`;

    // Collapsible info note
    const approvedRefundsText = approvedRefundsAmount > 0
        ? ' - Approved Refunds (₱' + fmt(approvedRefundsAmount) + ')'
        : '';
    const changeText = totalCashChange > 0 ? ' - Cash Change (₱' + fmt(totalCashChange) + ')' : '';
    const pendingRefundsCalcNote = showPendingRefunds && displayedPendingRefunds > 0
        ? ' - Pending Refunds (₱' + fmt(displayedPendingRefunds) + ')'
        : '';
    const voidIncomeCalcNote = voidIncome > 0
        ? ' + Void Fee / Service Fee Income (₱' + fmt(voidIncome) + ')'
        : '';
    const voidLostSalesCalcNote = voidLostSalesAmount > 0
        ? ' - Void Lost Sales (₱' + fmt(voidLostSalesAmount) + ')'
        : '';
    const adjustmentCalcNote = totalCashAdjustments > 0
        ? ' - Cashier Deductions (₱' + fmt(totalCashAdjustments) + ')'
        : '';
    html += `
    <div class="alert alert-info fs-10 mb-3">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fas fa-info-circle me-2"></span>
        <strong>How Expected Cash is calculated</strong>
        <button class="btn btn-link btn-sm p-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#expectedCashInfoMgr${session.session_id}" aria-expanded="false">
          <span class="fas fa-chevron-down" id="expectedCashInfoIconMgr${session.session_id}"></span>
        </button>
      </div>
      <div class="collapse mt-2" id="expectedCashInfoMgr${session.session_id}">
        <small>Starting Cash (₱${fmt(session.starting_cash)}) + Payments marked "In Cash" (₱${fmt(expectedCashCalc)})${approvedRefundsText}${voidIncomeCalcNote}${voidLostSalesCalcNote}${changeText}${pendingRefundsCalcNote}${adjustmentCalcNote}</small>
      </div>
    </div>`;
    
    container.innerHTML = html;
}

function formatNumberInput(input) {
    let value = input.value;

    // Remove all non-numeric characters except commas and decimal point
    value = value.replace(/[^0-9.,]/g, '');

    // Remove commas for calculation
    const numericValue = value.replace(/,/g, '');
    if (numericValue === '') return;

    // Format with commas for display
    const formatted = numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    input.value = formatted;
}

function computeManagerCloseVariance() {
    if (!currentSessionDetails) return;
    
    const closingCashInput = document.getElementById('managerCloseCash');
    const closingCash = parseFloat(closingCashInput.value.replace(/,/g, '')) || 0;
    const expectedCash = parseFloat(currentSessionDetails.session.expected_cash || 0);
    const variance = closingCash - expectedCash;
    
    const display = document.getElementById('managerVarianceDisplay');
    const status = document.getElementById('managerVarianceStatus');
    const card = document.getElementById('managerVarianceCard');
    
    display.textContent = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
    
    if (Math.abs(variance) < 0.01) {
        display.className = 'fw-bold fs-5 text-success';
        status.innerHTML = '<span class="badge bg-soft-success text-success">Balanced</span>';
        card.className = 'card border-success';
    } else if (variance < 0) {
        display.className = 'fw-bold fs-5 text-danger';
        status.innerHTML = '<span class="badge bg-soft-danger text-danger">Short</span>';
        card.className = 'card border-danger';
    } else {
        display.className = 'fw-bold fs-5 text-warning';
        status.innerHTML = '<span class="badge bg-soft-warning text-warning">Over</span>';
        card.className = 'card border-warning';
    }
}

function onCashierSelect(select) {
    const selectedOption = select.options[select.selectedIndex];
    const cashierId = select.value;
    const cashierName = selectedOption.dataset.name || '';
    const branchId = selectedOption.dataset.branchId || '';
    const branchName = selectedOption.dataset.branchName || '';
    const hasOpenSession = selectedOption.dataset.hasOpenSession === 'true' || cashiersWithOpenSessions.has(String(cashierId));
    
    // Update hidden fields
    document.getElementById('managerSessionCashierId').value = cashierId;
    document.getElementById('managerSessionCashierName').value = cashierName;
    document.getElementById('managerOpenBranch').value = branchId;
    
    // Update branch display
    const branchDisplay = document.getElementById('managerOpenBranchDisplay');
    const noBranchWarning = document.getElementById('noBranchWarning');
    const submitBtn = document.getElementById('managerOpenSubmitBtn');
    
    // Check for open session warning
    let openSessionWarning = document.getElementById('openSessionWarning');
    if (!openSessionWarning) {
        openSessionWarning = document.createElement('div');
        openSessionWarning.id = 'openSessionWarning';
        openSessionWarning.className = 'alert alert-warning d-none mt-2';
        openSessionWarning.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <span><span class="fas fa-exclamation-circle me-1"></span><small>This cashier already has an active session.</small></span>
                <button type="button" class="btn btn-sm btn-warning ms-2" onclick="switchToCloseSession()">
                    <span class="fas fa-stop-circle me-1"></span>Close Session
                </button>
            </div>
        `;
        noBranchWarning.parentNode.insertBefore(openSessionWarning, noBranchWarning.nextSibling);
    }
    
    if (!cashierId) {
        branchDisplay.textContent = '—';
        noBranchWarning.classList.add('d-none');
        openSessionWarning.classList.add('d-none');
        submitBtn.disabled = false;
        return;
    }
    
    // Check if cashier has open session
    if (hasOpenSession) {
        branchDisplay.textContent = branchName || '—';
        branchDisplay.classList.remove('text-danger');
        noBranchWarning.classList.add('d-none');
        openSessionWarning.classList.remove('d-none');
        submitBtn.disabled = true;
        return;
    }
    
    if (!branchId || branchId === 'null' || branchId === '') {
        branchDisplay.textContent = 'Not Assigned';
        branchDisplay.classList.add('text-danger');
        noBranchWarning.classList.remove('d-none');
        openSessionWarning.classList.add('d-none');
        submitBtn.disabled = true;
    } else {
        branchDisplay.textContent = branchName;
        branchDisplay.classList.remove('text-danger');
        noBranchWarning.classList.add('d-none');
        openSessionWarning.classList.add('d-none');
        submitBtn.disabled = false;
    }
}

async function submitManagerOpenSession() {
    const cashierId = document.getElementById('managerSessionCashierId').value;
    const branchId = document.getElementById('managerOpenBranch').value;
    const startingCashInput = document.getElementById('managerOpenStartingCash');
    const startingCashRaw = startingCashInput.value.trim();
    const notes = document.getElementById('managerOpenNotes').value;

    if (!cashierId) {
        showToast('warning', 'Validation', 'Please select a cashier.');
        return;
    }

    if (!startingCashRaw) {
        startingCashInput.focus();
        showToast('warning', 'Validation', 'Starting cash is required.');
        return;
    }
    const startingCash = parseFloat(startingCashRaw.replace(/,/g, ''));
    if (isNaN(startingCash) || startingCash < 0) {
        startingCashInput.focus();
        showToast('warning', 'Validation', 'Please enter a valid starting cash amount.');
        return;
    }

    // Double-check if cashier already has open session
    try {
        const checkRes = await fetch(`${window.BASE_URL}/api/pos/sessions?status=open&cashier_id=${cashierId}`);
        const checkResult = await checkRes.json();
        if (checkResult.success && checkResult.data && checkResult.data.length > 0) {
            showToast('warning', 'Session Exists', 'This cashier already has an active session. Please close it first before opening a new one.');
            return;
        }
    } catch (e) {
        // Continue anyway, server will do final validation
    }

    if (!branchId || branchId === 'null' || branchId === '') {
        showToast('warning', 'Validation', 'Selected cashier has no assigned branch. Cannot open session.');
        return;
    }

    const branchName = document.getElementById('managerOpenBranch').selectedOptions[0]?.text?.trim() || 'this branch';
    const cashierName = document.getElementById('managerSessionCashierName')?.value || 'this cashier';
    const openConfirmed = await showConfirm(
        'Open Cashier Session',
        `Open session for ${cashierName} at ${branchName} with starting cash ₱${fmt(startingCash)}?\n\nMake sure the amount is correct before proceeding.`,
        { icon: 'play-circle', iconColor: 'text-success', confirmBtnColor: 'btn-success', confirmBtnText: 'Open Session' }
    );
    if (!openConfirmed) return;

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cashier_user_id: cashierId,
                branch_id: branchId,
                starting_cash: parseFloat(startingCash),
                notes: notes,
                opened_by_manager: true
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('success', 'Success', 'Session opened successfully for cashier.');
            managerSessionModal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to open session.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    }
}

async function submitManagerCloseSession() {
    const sessionId = document.getElementById('managerCloseSessionId').value;
    const closingCashInput = document.getElementById('managerCloseCash');
    const closingCashRaw = closingCashInput.value.trim();
    const notes = document.getElementById('managerCloseNotes').value;

    if (!sessionId) {
        showToast('warning', 'Validation', 'Please select a session to close.');
        return;
    }

    if (!closingCashRaw) {
        closingCashInput.focus();
        showToast('warning', 'Validation', 'Actual closing cash is required.');
        return;
    }
    const closingCash = parseFloat(closingCashRaw.replace(/,/g, ''));
    if (isNaN(closingCash) || closingCash < 0) {
        closingCashInput.focus();
        showToast('warning', 'Validation', 'Please enter a valid closing cash amount.');
        return;
    }

    const expectedCash = parseFloat(currentSessionDetails?.session?.expected_cash || 0);
    const variance = closingCash - expectedCash;
    const varianceText = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
    const statusText = Math.abs(variance) < 0.01 ? 'Balanced' : variance < 0 ? 'Short' : 'Over';
    const varianceColorClass = Math.abs(variance) < 0.01 ? 'text-success' : variance < 0 ? 'text-danger' : 'text-success';
    const varianceLine = `Variance: <span class="fw-bold ${varianceColorClass}">${varianceText} (${statusText})</span>`;

    const message = `Close session with actual cash ₱${fmt(closingCash)}?\n\n` +
                    `Expected cash: ₱${fmt(expectedCash)}\n` +
                    `${varianceLine}\n\n` +
                    `Make sure the physical cash count is correct before confirming.`;
    const closeConfirmed = await showConfirm(
        'Close Cashier Session',
        message,
        { icon: 'stop-circle', iconColor: 'text-danger', confirmBtnColor: 'btn-danger', confirmBtnText: 'Close Session', html: true }
    );
    if (!closeConfirmed) return;

    const btn = document.querySelector('#closeSessionSection .btn-danger');
    const originalText = btn ? btn.innerHTML : '<span class="fas fa-stop-circle me-1"></span>Close Session';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Closing...';
    }

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: sessionId,
                closing_cash_balance: parseFloat(closingCash),
                notes: notes,
                action: 'close',
                closed_by_manager: true
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('success', 'Success', 'Session closed successfully.');
            managerSessionModal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to close session.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
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
