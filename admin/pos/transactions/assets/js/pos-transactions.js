/**
 * POS Transactions Report — AJAX-driven module
 */

let txnDetailOffcanvas;
let currentPage = 1;
let isLoading   = false;
let filtersReady = false;
let allProviders = [];
let txnRealtimeTimer = null;
let txnRealtimeMarker = null;
let txnRealtimeCheckInFlight = false;
let financialReportData = null;
let financialReportSignatoryModal = null;
let financialReportSignatories = [];
let financialReportSignatoryBranchId = null;
let financialReportEmployees = [];
let financialReportPositions = [];
let financialReportSignatoryRequestId = 0;
let financialReportSignatoryLoading = false;
let financialReportSignatoryLoadError = '';
let selectingSignatoryEmployee = false;
let signatorySettingsTrigger = null;

const defaultSignatoryLabels = [
    'Prepared by',
    'Cash Received by',
    'Checked by',
    'Reviewed/Recorded by',
    'Cash Validated by',
    'Verified by',
    'Approved by'
];

const fmt = n => parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const isApprovedVoid = txn => txn.adjustment_type === 'VOID' && txn.adjustment_approval_status === 'APPROVED';
const isTechnicalIssueVoid = txn => isApprovedVoid(txn) && txn.adjustment_reason_category === 'PRINTER_ERROR';
const getTechnicalLostSalesAmount = txn => (Number(txn.lost_sales_void_fee) || 0)
    + (Number(txn.lost_sales_service_fee) || 0);
const isCashierResponsibilityVoid = txn => txn.adjustment_type === 'VOID'
    && String(txn.adjustment_responsibility || '').toUpperCase() === 'CASHIER';
const getTransactionDisplayAmount = txn => {
    if (isCashierResponsibilityVoid(txn)) {
        return Number(txn.adjustment_amount) || 0;
    }
    if (isApprovedVoid(txn)) {
        return isTechnicalIssueVoid(txn)
            ? -getTechnicalLostSalesAmount(txn)
            : (Number(txn.void_fee) || 0) + (Number(txn.void_service_fee) || 0);
    }
    const total = Number(txn.total_amount) || 0;
    const refunded = Number(txn.total_refunded_amount) || 0;
    return total - refunded;
};

function buildProviderWallet(item) {
    const providerName = item.provider_name || '';
    const parentName = item.parent_provider_name || '';
    const variantName = item.variant_name || '';
    const walletProvider = item.wallet_provider_name || '';
    const walletVariant = item.wallet_variant_name || '';

    let providerDisplay = providerName;
    if (variantName) {
        providerDisplay = variantName;
    } else if (parentName) {
        providerDisplay = `${parentName} - ${providerName}`;
    }

    let walletDisplay = walletProvider;
    if (walletVariant) {
        walletDisplay = walletDisplay ? `${walletDisplay} - ${walletVariant}` : walletVariant;
    }

    let html = esc(providerDisplay);
    if (walletDisplay && walletDisplay !== providerDisplay) {
        html += ` <span class="text-muted">(${esc(walletDisplay)})</span>`;
    }
    return html;
}

function getTicketActionLabel(action) {
    const normalizedAction = String(action || '').trim().toUpperCase();
    const labels = {
        REBOOKING: 'Rebooking',
        REVALIDATE: 'Revalidate',
        RESCHEDULE: 'Reschedule'
    };
    return labels[normalizedAction] || normalizedAction;
}

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    txnDetailOffcanvas = new bootstrap.Offcanvas(document.getElementById('txnDetailModal'));
    const signatoryModalEl = document.getElementById('financialReportSignatoryModal');
    if (signatoryModalEl) {
        financialReportSignatoryModal = new bootstrap.Modal(signatoryModalEl);
        signatoryModalEl.addEventListener('hidden.bs.modal', () => {
            signatorySettingsTrigger?.focus();
            signatorySettingsTrigger = null;
        });
    }

    // Initialize date range picker; default to today so the report does not load all historical data
    // Try to restore saved date from localStorage; fall back to current date
    const savedDateFrom = localStorage.getItem('posTxnDateFrom');
    const savedDateTo = localStorage.getItem('posTxnDateTo');
    const today = new Date();
    const todayDateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const dateStr = savedDateFrom || todayDateStr;
    const dateToStr = savedDateTo || todayDateStr;

    document.getElementById('filterDateFrom').value = dateStr;
    document.getElementById('filterDateTo').value = dateToStr;
    
    // Helper function to format date in local timezone (YYYY-MM-DD)
    const formatLocalDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    const dateRangeEl = document.getElementById('filterDateRange');
    if (dateRangeEl && typeof flatpickr !== 'undefined') {
        // Create custom confirm button element
        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'btn btn-sm btn-primary w-100 mt-2';
        confirmBtn.textContent = 'Apply Date';
        confirmBtn.onclick = function() {
            const fp = dateRangeEl._flatpickr;
            if (fp) {
                fp.close();
            }
        };
        
        const fp = flatpickr(dateRangeEl, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: dateStr && dateToStr ? [dateStr, dateToStr] : [],
            clickOpens: true,
            showMonths: 1,
            appendTo: document.body,
            closeOnSelect: false,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates && selectedDates.length >= 1) {
                    const fromDate = formatLocalDate(selectedDates[0]);
                    document.getElementById('filterDateFrom').value = fromDate;
                    
                    if (selectedDates.length >= 2) {
                        const toDate = formatLocalDate(selectedDates[1]);
                        document.getElementById('filterDateTo').value = toDate;
                        if (fromDate === toDate) {
                            dateRangeEl.value = fromDate;
                        } else {
                            dateRangeEl.value = `${fromDate} to ${toDate}`;
                        }
                    } else {
                        // Single date selected - use same date for both
                        document.getElementById('filterDateTo').value = fromDate;
                        dateRangeEl.value = fromDate;
                    }
                    
                    // Save to localStorage
                    localStorage.setItem('posTxnDateFrom', document.getElementById('filterDateFrom').value);
                    localStorage.setItem('posTxnDateTo', document.getElementById('filterDateTo').value);
                }
            },
            onClose: function(selectedDates, dateStr, instance) {
                // Apply filter when closing the picker (only via button)
                if (selectedDates && selectedDates.length >= 1) {
                    loadTransactions(1);
                }
            },
            onOpen: function(selectedDates, dateStr, instance) {
                // Add confirm button to calendar
                const calendarContainer = instance.calendarContainer;
                if (calendarContainer && !calendarContainer.querySelector('.fp-confirm-btn')) {
                    const btnContainer = document.createElement('div');
                    btnContainer.className = 'fp-confirm-btn p-2';
                    btnContainer.appendChild(confirmBtn);
                    calendarContainer.appendChild(btnContainer);
                }
            }
        });
        // Show single date if from and to are the same, otherwise show range
        if (!dateStr || !dateToStr) {
            dateRangeEl.value = '';
        } else if (dateStr === dateToStr) {
            dateRangeEl.value = dateStr;
        } else {
            dateRangeEl.value = `${dateStr} to ${dateToStr}`;
        }
    }

    // Search debounce
    const searchEl = document.getElementById('filterSearch');
    if (searchEl) {
        let timer;
        searchEl.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => loadTransactions(1), 500);
        });
        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(timer); loadTransactions(1); }
        });
    }

    const financialTab = document.getElementById('financial-report-tab');
    if (financialTab) {
        financialTab.addEventListener('shown.bs.tab', function () {
            renderFinancialReport();
        });
    }

    // Load initial data + populate dropdowns
    loadTransactions(1);
    startTransactionRealtimePolling();

    // Close searchable employee dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (event.target.closest('.signatory-employee-search, .signatory-employee-results')) return;
        hideAllSignatoryEmployeeResults();
    });
});

// ─── Data Loading ─────────────────────────────────────────────────────────────

async function loadTransactions(page, options = {}) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page || 1;

    const isBackgroundRefresh = options.background === true;
    const tbody = document.getElementById('transactionsTableBody');
    if (!isBackgroundRefresh) {
        txnRealtimeMarker = null;
        tbody.innerHTML = `<tr><td colspan="13" class="text-center py-5 text-muted">
            <span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>`;
        document.getElementById('tableInfo').textContent = 'Loading...';
    }

    const params = buildParams(currentPage);
    const url = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();

    try {
        const res    = await fetch(url, { cache: 'no-store' });
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Failed to load');

        if (result.marker) txnRealtimeMarker = result.marker;
        renderTable(result.data);
        renderPagination(result.pagination);
        updateStats(result.stats);
        if (result.financial_report) {
            financialReportData = result.financial_report;
            renderFinancialReport();
        }
        setRealtimeStatus(`Live updates • ${new Date().toLocaleTimeString('en-PH')}`);

        // Populate dropdowns only once
        if (!filtersReady && result.filters) {
            populateDropdowns(result.filters);
            filtersReady = true;
        }
    } catch (e) {
        if (!isBackgroundRefresh) {
            tbody.innerHTML = `<tr><td colspan="13" class="text-center py-4 text-danger">
                <span class="fas fa-exclamation-triangle me-2"></span>${esc(e.message)}</td></tr>`;
            document.getElementById('tableInfo').textContent = 'Error loading data';
        }
        setRealtimeStatus('Live update unavailable; showing last known data', 'danger');
    } finally {
        isLoading = false;
    }
}

function setRealtimeStatus(message, tone = 'muted') {
    const realtimeStatus = document.getElementById('txnRealtimeStatus');
    if (!realtimeStatus) return;
    realtimeStatus.className = `${tone === 'danger' ? 'text-danger' : 'text-muted'} small`;
    realtimeStatus.textContent = message;
}

function transactionMarkersEqual(left, right) {
    const fields = [
        'total_orders',
        'max_order_id',
        'latest_created_at',
        'latest_updated_at',
        'latest_adjustment_at',
        'latest_adjustment_approved_at',
        'latest_cancellation_requested_at',
        'latest_cancellation_approved_at',
        'latest_cancellation_processed_at'
    ];
    return fields.every(field => String(left?.[field] ?? '') === String(right?.[field] ?? ''));
}

async function checkForTransactionUpdates() {
    if (txnRealtimeCheckInFlight || isLoading || !txnRealtimeMarker) return;
    txnRealtimeCheckInFlight = true;

    try {
        const params = buildParams(currentPage);
        params.check_only = '1';
        const url = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();
        const res = await fetch(url, { cache: 'no-store' });
        const result = await res.json();
        if (!result.success || !result.marker) throw new Error(result.error || 'Update check failed');

        if (!transactionMarkersEqual(txnRealtimeMarker, result.marker)) {
            await loadTransactions(currentPage, { background: true });
        } else {
            setRealtimeStatus(`Live updates • ${new Date().toLocaleTimeString('en-PH')}`);
        }
    } catch (e) {
        setRealtimeStatus('Live update check unavailable; showing current data', 'danger');
    } finally {
        txnRealtimeCheckInFlight = false;
    }
}

function startTransactionRealtimePolling() {
    if (txnRealtimeTimer) return;

    const refresh = () => {
        if (document.visibilityState !== 'visible' || isLoading) return;
        if (txnRealtimeMarker) checkForTransactionUpdates();
        else loadTransactions(currentPage, { background: true });
    };

    txnRealtimeTimer = setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh();
    });
}

function buildParams(page) {
    const p = {
        page:       page,
        limit:      document.getElementById('perPageSelect')?.value || 20,
        status:     document.getElementById('filterStatus')?.value  || 'all',
        type:       document.getElementById('filterType')?.value    || 'all',
        search:     document.getElementById('filterSearch')?.value  || '',
        date_from:  document.getElementById('filterDateFrom')?.value || '',
        date_to:    document.getElementById('filterDateTo')?.value   || '',
        include_financial_report: '1',
    };
    const branch   = document.getElementById('filterBranch');
    if (branch)   p.branch_id   = branch.value   || '';
    const provider = document.getElementById('filterProvider');
    if (provider) p.provider_id = provider.value || '';
    const providerType = document.getElementById('filterProviderType');
    if (providerType) p.provider_type = providerType.value || '';
    const cashier  = document.getElementById('filterCashier');
    if (cashier)  p.cashier_id  = cashier.value  || '';
    // Remove empty values
    Object.keys(p).forEach(k => { if (p[k] === '' || p[k] === null) delete p[k]; });
    return p;
}

function applyFilters() { filtersReady = true; loadTransactions(1); }

function resetFilters() {
    document.getElementById('filterSearch').value  = '';
    document.getElementById('filterStatus').value  = 'all';
    document.getElementById('filterType').value    = 'all';
    
    // Reset date filter and clear localStorage
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    localStorage.removeItem('posTxnDateFrom');
    localStorage.removeItem('posTxnDateTo');
    const fp = document.getElementById('filterDateRange')._flatpickr;
    if (fp) fp.clear();
    else { const el = document.getElementById('filterDateRange'); if (el) el.value = ''; }
    
    const branch = document.getElementById('filterBranch');
    if (branch) branch.value = '';
    const provider = document.getElementById('filterProvider');
    if (provider) provider.value = '';
    const providerType = document.getElementById('filterProviderType');
    if (providerType) providerType.value = '';
    const cashier = document.getElementById('filterCashier');
    if (cashier) cashier.value = '';
    loadTransactions(1);
}

// ─── Dropdown Populate ────────────────────────────────────────────────────────

function populateDropdowns(filters) {
    const branchEl = document.getElementById('filterBranch');
    if (branchEl && filters.branches) {
        filters.branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.branch_id;
            opt.textContent = b.branch_name;
            branchEl.appendChild(opt);
        });
    }

    const signatoryBranchEl = document.getElementById('signatoryBranchSelect');
    if (signatoryBranchEl && filters.branches) {
        signatoryBranchEl.innerHTML = '<option value="">Select branch...</option>';
        filters.branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.branch_id;
            opt.textContent = b.branch_name;
            signatoryBranchEl.appendChild(opt);
        });
    }

    const providerEl = document.getElementById('filterProvider');
    if (providerEl && filters.providers) {
        allProviders = filters.providers;
        filters.providers.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.provider_id;
            opt.textContent = p.provider_type ? `${p.provider_name} (${p.provider_type.toLowerCase()})` : p.provider_name;
            providerEl.appendChild(opt);
        });
    }

    const providerTypeEl = document.getElementById('filterProviderType');
    if (providerTypeEl && filters.provider_types) {
        filters.provider_types.forEach(pt => {
            const opt = document.createElement('option');
            opt.value = pt.provider_type;
            opt.textContent = pt.provider_type ? (pt.provider_type.charAt(0).toUpperCase() + pt.provider_type.slice(1)) : pt.provider_type;
            providerTypeEl.appendChild(opt);
        });

        // Dynamic filtering: when provider type changes, update provider dropdown
        providerTypeEl.addEventListener('change', function() {
            const selectedType = this.value;
            const provEl = document.getElementById('filterProvider');
            if (!provEl) return;
            const currentVal = provEl.value;
            provEl.innerHTML = '<option value="">All Providers</option>';
            allProviders.forEach(p => {
                if (!selectedType || p.provider_type === selectedType) {
                    const opt = document.createElement('option');
                    opt.value = p.provider_id;
                    opt.textContent = selectedType ? p.provider_name : (p.provider_type ? `${p.provider_name} (${p.provider_type.toLowerCase()})` : p.provider_name);
                    provEl.appendChild(opt);
                }
            });
            // Restore previous value if still valid
            if (currentVal && provEl.querySelector(`option[value="${currentVal}"]`)) {
                provEl.value = currentVal;
            }
        });
    }

    const cashierEl = document.getElementById('filterCashier');
    if (cashierEl && filters.cashiers) {
        filters.cashiers.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.user_id;
            opt.textContent = c.cashier_name;
            cashierEl.appendChild(opt);
        });
    }
}

// ─── Render ───────────────────────────────────────────────────────────────────

const statusColors = { completed:'success', pending:'warning', cancelled:'danger', refunded:'secondary' };
const statusIcons  = { completed:'fa-check-circle', pending:'fa-clock', cancelled:'fa-times-circle', refunded:'fa-undo' };

function renderTable(rows) {
    const tbody = document.getElementById('transactionsTableBody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="13"><div class="text-center py-5 text-muted d-flex flex-column align-items-center justify-content-center">
            <span class="fas fa-receipt fs-2 d-block mb-2 opacity-25"></span>
            <div>No transactions found</div>
            <small>Try adjusting your filters</small>
        </div></td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(txn => {
        const isTicket  = txn.ticket_count > 0;
        const isService = txn.service_count > 0;

        // Resolve a single, non-conflicting final status.
        const hasPending = txn.adjustment_approval_status === 'PENDING' ||
            (txn.has_cancellation && (txn.cancellation_status || '').toLowerCase() === 'pending');
        const isVoid = isApprovedVoid(txn);
        const isRefund = (txn.adjustment_type === 'REFUND' && txn.adjustment_approval_status === 'APPROVED') ||
            txn.status === 'refunded';

        let finalStatus = txn.status || 'completed';
        if (hasPending) {
            finalStatus = 'pending';
        } else if (isVoid) {
            finalStatus = 'voided';
        } else if (isRefund) {
            finalStatus = 'refunded';
        } else if (txn.status === 'cancelled') {
            finalStatus = 'cancelled';
        }

        const statusMap = {
            pending:   { color: 'warning', icon: 'fa-clock',             text: 'Pending Cancel' },
            voided:    { color: 'warning', icon: 'fa-ban',               text: 'Voided' },
            refunded:  { color: 'info',    icon: 'fa-hand-holding-usd',  text: 'Refunded' },
            cancelled: { color: 'danger',  icon: 'fa-times-circle',      text: 'Cancelled' },
            completed: { color: 'primary', icon: 'fa-check-circle',      text: 'Completed' },
            booked:    { color: 'success', icon: 'fa-check-circle',      text: 'Booked' }
        };
        const statusInfo = statusMap[finalStatus] || { color: 'secondary', icon: 'fa-circle', text: finalStatus };
        const isPartiallyCancelled = finalStatus === 'completed' && txn.has_cancellation && !isRefund && !isVoid;

        // Type pill
        let typePill = '';
        if (isTicket && isService) {
            typePill = `<span class="badge type-pill-ticket me-1">Ticket</span><span class="badge type-pill-service">Service</span>`;
        } else if (isTicket) {
            typePill = `<span class="badge type-pill-ticket">Ticket</span>`;
        } else {
            typePill = `<span class="badge type-pill-service">Service</span>`;
        }

        // Passenger / Provider
        const passengerLine = txn.passenger_names
            ? `<div class="small text-truncate" style="max-width:160px" title="${esc(txn.passenger_names)}">${esc(txn.passenger_names)}</div>`
            : '';
        const accommodationLine = txn.accommodation_names
            ? `<div class="text-muted" style="font-size:.7rem"><span class="fas fa-bed me-1"></span>${esc(txn.accommodation_names)}</div>`
            : '';
        const discountLine = txn.discount_names
            ? `<div class="text-success" style="font-size:.7rem"><span class="fas fa-percent me-1"></span>${esc(txn.discount_names)}</div>`
            : '';
        const providerLine = txn.provider_names
            ? `<div class="text-muted" style="font-size:.72rem">${esc(txn.provider_names)}</div>`
            : '<span class="text-muted small">—</span>';
        const walletProviderLine = txn.wallet_provider_names
            ? `<div class="text-muted" style="font-size:.72rem">${esc(txn.wallet_provider_names)}</div>`
            : '<span class="text-muted small">—</span>';
        const routeLine = txn.routes
            ? `<div class="text-muted" style="font-size:.7rem">${esc(txn.routes)}</div>`
            : '';

        // Payment display
        let payDisplay = '';
        if (txn.payments && txn.payments.length > 0) {
            payDisplay = txn.payments.map(p => `<span class="payment-chip">${esc(p.method_name || p.method_code || '')}</span>`).join('');
        } else if (txn.payment_method) {
            payDisplay = `<span class="payment-chip">${esc(txn.payment_method)}</span>`;
        } else {
            payDisplay = '<span class="text-muted small">—</span>';
        }

        // Cost, Service Fee, Lost Sales, and Amount + refund
        const costHtml = isVoid
            ? '<span class="text-muted">—</span>'
            : `<span class="fw-semibold">₱${fmt(txn.total_cost)}</span>`;
        const voidServiceFee = Number(txn.void_service_fee) || 0;
        const serviceFeeAmount = isVoid ? voidServiceFee : (Number(txn.total_service_fees) || 0);
        const serviceFeeHtml = serviceFeeAmount > 0
            ? `<span class="fw-semibold">₱${fmt(serviceFeeAmount)}</span>`
            : '<span class="text-muted">—</span>';
        const technicalVoidAmount = getTechnicalLostSalesAmount(txn);
        const displayAmount = getTransactionDisplayAmount(txn);
        let amountHtml = displayAmount < 0
            ? `<span class="fw-semibold text-danger">-₱${fmt(Math.abs(displayAmount))}</span>`
            : `<span class="fw-semibold">₱${fmt(displayAmount)}</span>`;
        if (isTechnicalIssueVoid(txn) && technicalVoidAmount > 0) {
            amountHtml += `<div class="text-danger transaction-lost-sales" style="font-size:.72rem">Lost Sales -₱${fmt(technicalVoidAmount)}</div>`;
        }
        if (isVoid && !isTechnicalIssueVoid(txn)) {
            const voidFee = Number(txn.void_fee) || 0;
            const voidParts = [];
            if (voidFee > 0) voidParts.push(`Void Fee ₱${fmt(voidFee)}`);
            if (voidServiceFee > 0) voidParts.push(`service fee ₱${fmt(voidServiceFee)}`);
            if (voidParts.length > 0) {
                amountHtml += `<div class="text-muted" style="font-size:.72rem">${voidParts.join(' + ')}</div>`;
            }
        }
        const refunded = parseFloat(txn.total_refunded_amount || 0);
        if (refunded > 0) {
            amountHtml += `<div class="text-danger" style="font-size:.72rem">-₱${fmt(refunded)} refund</div>`;
        }

        // Extra badges beside the ticket number (responsibility / partial cancellation only).
        let cancelBadge = '';
        if (isPartiallyCancelled) {
            cancelBadge = `<div><span class="badge bg-soft-warning text-warning" style="font-size:.65rem"><i class="fas fa-exclamation-circle me-1"></i>Partially Cancelled</span></div>`;
        }
        const responsibility = String(txn.adjustment_responsibility || '').toLowerCase();
        if (responsibility && responsibility !== 'none' && txn.adjustment_approval_status !== 'PENDING') {
            const responsibilityLabel = responsibility === 'cashier' ? 'Cashier responsibility' : 'Customer responsibility';
            const responsibleCashier = responsibility === 'cashier' && txn.adjustment_responsible_cashier
                ? `: ${esc(txn.adjustment_responsible_cashier)}`
                : '';
            const printHiddenAttribute = responsibility === 'customer' ? ' data-print-hidden="true"' : '';
            cancelBadge += `<div><span class="badge bg-soft-danger text-danger"${printHiddenAttribute} style="font-size:.65rem"><i class="fas fa-user-shield me-1"></i>${responsibilityLabel}${responsibleCashier}</span></div>`;
        }

        const dateStr = txn.created_at ? formatDate(txn.created_at) : '—';
        const timeStr = txn.created_at ? formatTime(txn.created_at) : '—';

        return `<tr class="txn-row" data-passenger-number="${esc(txn.passenger_numbers || '')}" data-ticket-number="${esc(txn.ticket_numbers || '')}" onclick="viewTxnDetail('${esc(txn.order_id)}')" title="Click to view details">
            <td class="ps-3 py-2">
                <div class="fw-semibold small">${esc(txn.ticket_numbers || '—')}</div>
                <div class="d-none">${cancelBadge}</div>
            </td>
            <td class="py-2">
                ${typePill}
            </td>
            <td class="py-2">
                ${passengerLine}
                ${accommodationLine}
                ${discountLine}
                ${routeLine}
            </td>
            <td class="py-2">${providerLine}</td>
            <td class="py-2">${walletProviderLine}</td>
            <td class="py-2">
                <div class="small">${esc(txn.cashier_full_name || txn.cashier_name || '—')}</div>
            </td>
            <td class="py-2">
                <div class="small text-muted">${esc(txn.branch_code || txn.branch_name || '—')}</div>
            </td>
            <td class="py-2">${payDisplay}</td>
            <td class="py-2 text-end">${costHtml}</td>
            <td class="py-2 text-end">${serviceFeeHtml}</td>
            <td class="py-2 text-end">${amountHtml}</td>
            <td class="py-2">
                <span class="badge bg-soft-${statusInfo.color} text-${statusInfo.color}">
                    <span class="fas ${statusInfo.icon} me-1"></span>${statusInfo.text}
                </span>
            </td>
            <td class="py-2 text-end pe-3">
                <div style="font-size:.75rem; white-space:nowrap">${dateStr}</div>
                <div style="font-size:.7rem; color:#6c757d; white-space:nowrap">${timeStr}</div>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination(pg) {
    if (!pg) return;
    const { total, per_page, current_page, total_pages, from, to } = pg;
    document.getElementById('tableInfo').textContent = total > 0
        ? `Showing ${from}–${to} of ${total} transaction${total !== 1 ? 's' : ''}`
        : 'No transactions found';
    document.getElementById('paginationInfo').textContent = total > 0
        ? `Page ${current_page} of ${total_pages}` : '';

    const ul = document.getElementById('pagination');
    if (total_pages <= 1) { ul.innerHTML = ''; return; }

    let html = `<li class="page-item ${current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTransactions(${current_page - 1});return false;">‹</a></li>`;

    for (let i = 1; i <= total_pages; i++) {
        if (i === 1 || i === total_pages || (i >= current_page - 1 && i <= current_page + 1)) {
            html += `<li class="page-item ${i === current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadTransactions(${i});return false;">${i}</a></li>`;
        } else if (i === current_page - 2 || i === current_page + 2) {
            html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
    }
    html += `<li class="page-item ${current_page === total_pages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTransactions(${current_page + 1});return false;">›</a></li>`;
    ul.innerHTML = html;
}

function updateStats(stats) {
    if (!stats) return;
    document.getElementById('statTotal').textContent    = stats.total_orders    || 0;
    document.getElementById('statRevenue').textContent  = '₱' + fmt(stats.total_revenue);
    document.getElementById('statRefunded').textContent = '₱' + fmt(stats.total_refunded);
    document.getElementById('statProfit').textContent   = '₱' + fmt(stats.total_profit);
}

function formatFinancialDate(value) {
    if (!value) return '';
    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return String(value);
    return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString('en-PH', {
        month: 'long', day: 'numeric', year: 'numeric'
    });
}

function formatFinancialDateWithWeekday(value) {
    if (!value) return '';
    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return String(value);
    return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString('en-PH', {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
    });
}

function parseFinancialDate(value) {
    if (!value) return null;
    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    return isNaN(date.getTime()) ? null : date;
}

function formatFinancialPeriod(from, to) {
    const fromDate = parseFinancialDate(from);
    const toDate = parseFinancialDate(to);
    if (!fromDate || !toDate) return `${formatFinancialDate(from)} – ${formatFinancialDate(to)}`;

    const fromYear = fromDate.getFullYear();
    const toYear = toDate.getFullYear();
    const fromMonth = fromDate.getMonth();
    const toMonth = toDate.getMonth();
    const fromDay = fromDate.getDate();
    const toDay = toDate.getDate();

    const toMonthName = toDate.toLocaleDateString('en-PH', { month: 'long' });
    if (fromYear === toYear && fromMonth === toMonth) {
        return `${toMonthName} ${fromDay}-${toDay}, ${toYear}`;
    }
    if (fromYear === toYear) {
        const fromMonthName = fromDate.toLocaleDateString('en-PH', { month: 'long' });
        return `${fromMonthName} ${fromDay} - ${toMonthName} ${toDay}, ${toYear}`;
    }
    return `${formatFinancialDate(from)} – ${formatFinancialDate(to)}`;
}

function financialReportPeriodLabel(data) {
    const from = data?.date_from || '';
    const to = data?.date_to || '';
    if (!from && !to) return 'All dates';
    if (!to || from === to) return formatFinancialDateWithWeekday(from || to);
    if (!from) return `Up to ${formatFinancialDateWithWeekday(to)}`;
    return formatFinancialPeriod(from, to);
}

function financialReportScopeLabel(data = financialReportData) {
    const parts = [];
    const branch = document.getElementById('filterBranch');
    const cashier = document.getElementById('filterCashier');
    const provider = document.getElementById('filterProvider');
    const providerType = document.getElementById('filterProviderType');
    const status = document.getElementById('filterStatus');
    const type = document.getElementById('filterType');
    if (branch?.value && branch.options[branch.selectedIndex]) {
        parts.push(`Branch: ${branch.options[branch.selectedIndex].text}`);
    } else if (data?.signatory_branch_id && branch?.options) {
        const reportBranch = Array.from(branch.options).find(option => Number(option.value) === Number(data.signatory_branch_id));
        if (reportBranch) parts.push(`Branch: ${reportBranch.text}`);
    } else if (window.POS_TXN_CONFIG?.isSuperAdmin === false && branch) {
        parts.push('All accessible branches');
    } else {
        parts.push('All branches');
    }
    if (cashier?.value && cashier.options[cashier.selectedIndex]) parts.push(`Cashier: ${cashier.options[cashier.selectedIndex].text}`);
    if (providerType?.value && providerType.options[providerType.selectedIndex]) parts.push(`Provider type: ${providerType.options[providerType.selectedIndex].text}`);
    if (provider?.value && provider.options[provider.selectedIndex]) parts.push(`Provider: ${provider.options[provider.selectedIndex].text}`);
    if (status?.value && status.value !== 'all') parts.push(`Status: ${status.options[status.selectedIndex].text}`);
    if (type?.value && type.value !== 'all') parts.push(`Type: ${type.options[type.selectedIndex].text}`);
    return parts.join(' · ') + (cashier?.value ? '' : ' · All cashiers');
}

function financialReportStorageKey(name, data) {
    const scope = ['filterBranch', 'filterCashier', 'filterProvider', 'filterProviderType', 'filterStatus', 'filterType']
        .map(id => document.getElementById(id)?.value || '')
        .join(':');
    return `posFinancialReport:${name}:${data?.date_from || ''}:${data?.date_to || ''}:${scope}`;
}

function renderFinancialReport() {
    const content = document.getElementById('financialReportContent');
    if (!content) return;
    if (!financialReportData) {
        content.innerHTML = '<div class="text-center py-5 text-muted"><span class="fas fa-spinner fa-spin me-2"></span>Loading financial report...</div>';
        return;
    }

    const data = financialReportData;
    const periodLabel = financialReportPeriodLabel(data);
    const scopeLabel = financialReportScopeLabel();
    const periodEl = document.getElementById('financialReportPeriod');
    if (periodEl) periodEl.textContent = `Period: ${periodLabel}`;

    const providerSales = Array.isArray(data.provider_sales) ? data.provider_sales : [];
    const providerRows = providerSales.length
        ? providerSales.map(row => `
            <tr class="${row.is_service ? 'financial-report-service-row' : ''}">
              <td>${esc(row.provider_name || 'Unassigned')}</td>
              <td class="text-end">${Number(row.tickets || 0).toLocaleString('en-PH')}</td>
              <td class="text-end">${reportMoney(row.total_cost)}</td>
              <td class="text-end">${reportMoney(row.service_fee_income)}</td>
              <td class="text-end">${reportMoney(row.total_amount)}</td>
            </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted">No ticket sales found for the selected filters.</td></tr>';

    const paymentSummary = Array.isArray(data.payment_summary) ? data.payment_summary : [];
    const paymentRows = paymentSummary.length
        ? paymentSummary.map(row => `
            <tr>
              <td>${esc(row.method_name || 'Other')}</td>
              <td class="text-end">${Number(row.order_count || 0).toLocaleString('en-PH')}</td>
              <td class="text-end">${reportMoney(row.amount)}</td>
            </tr>`).join('')
        : '<tr><td colspan="3" class="text-center text-muted">No payment records found.</td></tr>';

    const refundRows = Array.isArray(data.sales_refunds) ? data.sales_refunds : [];
    const refundMap = new Map(refundRows.map(row => [String(row.provider_id || 0), row]));
    const refundProviders = [];
    providerSales.filter(row => !row.is_service).forEach(row => {
        const key = String(row.provider_id || 0);
        if (!refundProviders.some(item => item.key === key)) {
            refundProviders.push({ key, name: row.provider_name || 'Unassigned' });
        }
    });
    refundRows.forEach(row => {
        const key = String(row.provider_id || 0);
        if (!refundProviders.some(item => item.key === key)) {
            refundProviders.push({ key, name: row.provider_name || 'Unassigned' });
        }
    });
    const renderedRefundRows = refundProviders.length
        ? refundProviders.map(provider => {
            const refund = refundMap.get(provider.key);
            return `
            <tr>
              <td>${esc(provider.name)}</td>
              <td class="text-end">${reportMoney(refund?.amount || 0)}</td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="2" class="text-center text-muted">No sales refunds found.</td></tr>';

    const totalSales = Number(data.total_sales || 0);
    const totalSalesRefunds = Number(data.total_sales_refunds || 0);
    const netDeposit = Number(data.net_amount_for_deposit ?? Math.max(0, totalSales - totalSalesRefunds));
    const reportInput = (id, label, type, key, placeholder = '') => `
      <label class="financial-report-field" for="${id}">
        <span>${esc(label)}</span>
        <input id="${id}" type="${type}" value="" placeholder="${esc(placeholder)}" autocomplete="off" data-financial-report-key="${key}">
      </label>`;

    const currentUserName = window.POS_TXN_CONFIG?.currentUserName || '';
    const signatoryConfig = getReportSignatories(data);
    const signatures = signatoryConfig.map((sig, index) => `
      <div class="financial-signature-row">
        <span class="financial-signature-label">${esc(sig.label)}</span>
        <span class="financial-signature-name">${esc(formatSignatoryName(sig.name || (!data.signatory_configured && index === 0 ? currentUserName : '')))}</span>
        <span class="financial-signature-position">${esc(sig.position_name || '')}</span>
        <span class="financial-signature-date-label financial-signature-date-label-text">Date</span>
        <span class="financial-signature-line financial-signature-date-line"></span>
        <span class="financial-signature-date-label financial-signature-sig-label-text">Signature</span>
        <span class="financial-signature-line financial-signature-sig-line financial-signature-mark"></span>
      </div>`).join('');

    const companyInfo = window.COMPANY_INFO || {};
    const companyName = companyInfo.name || window.systemName || 'TMS';
    const companyAddress = String(companyInfo.address || '').trim();
    const companyContact = [companyInfo.contact, companyInfo.email].filter(Boolean).join(' · ');
    const companyTin = String(companyInfo.tin || '').trim();
    const logoUrl = String(companyInfo.logo || '').trim();
    const logoHtml = logoUrl
        ? `<img class="financial-report-logo" src="${esc(logoUrl)}" alt="${esc(companyName)} logo" onerror="this.style.display='none'; this.dataset.error='1';">`
        : '';

    const generatedAt = new Date();
    const generatedDate = formatFinancialDate(`${generatedAt.getFullYear()}-${String(generatedAt.getMonth() + 1).padStart(2, '0')}-${String(generatedAt.getDate()).padStart(2, '0')}`);
    const generatedTime = generatedAt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const generatedLabel = `${generatedDate}, ${generatedTime}`;

    content.innerHTML = `
      <div class="financial-report-sheet">
        <div class="financial-report-heading">
          <div class="financial-report-brand">
            ${logoHtml}
            <div class="financial-report-company">
              <div class="financial-report-company-name">${esc(companyName)}</div>
              ${companyAddress ? `<div class="financial-report-company-meta">${esc(companyAddress).replace(/\r?\n/g, '<br>')}</div>` : ''}
              ${companyContact ? `<div class="financial-report-company-meta">${esc(companyContact)}</div>` : ''}
              ${companyTin ? `<div class="financial-report-company-meta">TIN: ${esc(companyTin)}</div>` : ''}
            </div>
          </div>
          <div class="financial-report-heading-text">
            <div class="financial-report-kicker">ACCOUNTING DOCUMENT</div>
            <div class="financial-report-title">FINANCIAL REPORT</div>
            <div class="financial-report-date">${esc(periodLabel)}</div>
            <div class="financial-report-scope">${esc(scopeLabel)}</div>
          </div>
        </div>

        <div class="financial-report-section">
          <div class="financial-report-section-title">TICKET SALES SUMMARY</div>
          <div class="financial-report-total-ticket">
            <span>TOTAL NO. OF TICKETS SOLD</span>
            <strong>${Number(data.total_tickets || 0).toLocaleString('en-PH')}</strong>
          </div>
          <div class="table-responsive">
            <table class="table table-sm financial-report-table mb-0">
              <thead>
                <tr>
                  <th>Provider</th>
                  <th class="text-end">Tickets Sold</th>
                  <th class="text-end">Total Cost</th>
                  <th class="text-end">Service Fee Income</th>
                  <th class="text-end">Total Amount</th>
                </tr>
              </thead>
              <tbody>${providerRows}</tbody>
              <tfoot>
                <tr>
                  <th>Total</th>
                  <th class="text-end">${Number(data.total_tickets || 0).toLocaleString('en-PH')}</th>
                  <th class="text-end">${reportMoney(data.total_cost)}</th>
                  <th class="text-end">${reportMoney(data.total_service_fee_income)}</th>
                  <th class="text-end">${reportMoney(data.total_amount)}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="financial-report-columns">
          <div class="financial-report-section">
            <div class="financial-report-section-title">SALES BY PAYMENT METHOD</div>
            <div class="table-responsive">
              <table class="table table-sm financial-report-table mb-0">
                <thead><tr><th>Payment Method</th><th class="text-end">No. of Sales</th><th class="text-end">Amount</th></tr></thead>
                <tbody>${paymentRows}</tbody>
                <tfoot><tr><th>TOTAL SALES</th><th class="text-end">${paymentSummary.reduce((sum, row) => sum + Number(row.order_count || 0), 0).toLocaleString('en-PH')}</th><th class="text-end">${reportMoney(totalSales)}</th></tr></tfoot>
              </table>
            </div>
          </div>
          <div class="financial-report-section">
            <div class="financial-report-section-title">SALES REFUND</div>
            <div class="table-responsive">
              <table class="table table-sm financial-report-table mb-0">
                <thead><tr><th>Provider</th><th class="text-end">Amount</th></tr></thead>
                <tbody>${renderedRefundRows}</tbody>
                <tfoot><tr><th>TOTAL</th><th class="text-end">${reportMoney(totalSalesRefunds)}</th></tr></tfoot>
              </table>
            </div>
          </div>
        </div>

        <div class="financial-report-deposit">
          <div class="financial-report-section-title">DEPOSIT SUMMARY</div>
          <div class="financial-report-total-row"><span>TOTAL CASH SALES</span><strong>${reportMoney(totalSales)}</strong></div>
          <div class="financial-report-total-row financial-report-refund-row"><span>TOTAL SALES RETURN</span><strong>${reportMoney(totalSalesRefunds)}</strong></div>
          <div class="financial-report-total-row financial-report-net-row"><span>NET AMOUNT FOR DEPOSIT</span><strong>${reportMoney(netDeposit)}</strong></div>
          <div class="financial-report-input-grid">
            ${reportInput('financialCashSalesForwarded', 'CASH SALES FORWARDED', 'number', 'cash_sales_forwarded')}
            ${reportInput('financialBtCashForwarded', 'BT PRINT CASH FORWARDED', 'number', 'bt_print_cash_forwarded')}
            ${reportInput('financialDepositDate', 'Date Deposit', 'date', 'deposit_date')}
            ${reportInput('financialDepositReference', 'Reference No.', 'text', 'deposit_reference')}
          </div>
        </div>

        <div class="financial-report-signatures">${signatures}</div>
        <div class="financial-report-generated">Generated ${esc(generatedLabel)}</div>
      </div>`;

    ['financialCashSalesForwarded', 'financialBtCashForwarded', 'financialDepositDate', 'financialDepositReference'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        const key = input.dataset.financialReportKey;
        const storageKey = financialReportStorageKey(key, data);
        const savedValue = localStorage.getItem(storageKey);
        if (savedValue !== null) {
            input.value = savedValue;
        }
        input.setAttribute('value', input.value);
        input.addEventListener('input', () => {
            localStorage.setItem(storageKey, input.value);
            input.setAttribute('value', input.value);
        });
    });
}

function reportMoney(value) {
    return `₱${fmt(value)}`;
}

function refreshFinancialReport() {
    if (!isLoading) loadTransactions(1);
}

function printFinancialReport() {
    if (!financialReportData) {
        showToast('warning', 'Report unavailable', 'The financial report is still loading.');
        return;
    }

    let printStarted = false;
    let fallbackTimer = null;
    const cleanup = () => {
        if (fallbackTimer) window.clearTimeout(fallbackTimer);
        document.body.classList.remove('printing-financial-report');
    };
    const doPrint = () => {
        if (printStarted) return;
        printStarted = true;
        if (fallbackTimer) window.clearTimeout(fallbackTimer);
        window.addEventListener('afterprint', cleanup, { once: true });
        window.requestAnimationFrame(() => window.print());
        fallbackTimer = window.setTimeout(cleanup, 3000);
    };

    document.body.classList.add('printing-financial-report');
    const img = document.querySelector('.financial-report-logo');
    if (img && !img.complete && img.src) {
        img.addEventListener('load', doPrint, { once: true });
        img.addEventListener('error', doPrint, { once: true });
        fallbackTimer = window.setTimeout(doPrint, 1500);
    } else {
        doPrint();
    }
}

function getDefaultSignatories() {
    return defaultSignatoryLabels.map((label, index) => ({
        label,
        name: index === 0 ? (window.POS_TXN_CONFIG?.currentUserName || '') : '',
        employee_id: 0,
        position_id: 0,
        position_name: '',
        sort_order: index,
        is_active: 1
    }));
}

function normalizeSignatoryConfig(config, includeInactive = false, fallbackToDefaults = true) {
    if (!Array.isArray(config) || config.length === 0) {
        return fallbackToDefaults ? getDefaultSignatories() : [];
    }
    const normalized = config
        .filter(s => s && typeof s === 'object')
        .map(s => ({
            label: String(s.label || '').trim(),
            name: String(s.name || '').trim(),
            employee_id: Number.isFinite(Number(s.employee_id)) ? Number(s.employee_id) : 0,
            position_id: Number.isFinite(Number(s.position_id)) ? Number(s.position_id) : 0,
            position_name: String(s.position_name || '').trim(),
            employee_available: Number(s.employee_available) !== 0 ? 1 : 0,
            sort_order: Number.isFinite(Number(s.sort_order)) ? Number(s.sort_order) : 0,
            is_active: Number(s.is_active) !== 0 ? 1 : 0
        }))
        .filter(s => s.label !== '')
        .sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label));
    if (!includeInactive) {
        const active = normalized.filter(s => s.is_active && s.employee_available !== 0);
        return active.length > 0 || !fallbackToDefaults ? active : getDefaultSignatories();
    }
    return normalized;
}

function getReportSignatories(data) {
    if (data?.signatory_configured === true) {
        return normalizeSignatoryConfig(data.signatory_config, false, false);
    }
    return getDefaultSignatories();
}

function resolveSignatoryBranchId() {
    const branchSelect = document.getElementById('filterBranch');
    const signatoryBranchSelect = document.getElementById('signatoryBranchSelect');
    const filteredBranch = branchSelect?.value || '';
    if (filteredBranch) return Number(filteredBranch);

    const options = Array.from(signatoryBranchSelect?.options || []).filter(option => option.value);
    const previouslySelected = String(financialReportSignatoryBranchId || '');
    if (previouslySelected && options.some(option => option.value === previouslySelected)) {
        return Number(previouslySelected);
    }
    if (options.length === 1) return Number(options[0].value);

    const defaultBranchId = String(window.POS_TXN_CONFIG?.currentUserBranchId || '');
    if (defaultBranchId && options.some(option => option.value === defaultBranchId)) {
        return Number(defaultBranchId);
    }
    return null;
}

function getEmployeeById(employeeId) {
    return financialReportEmployees.find(e => Number(e.employee_id) === Number(employeeId));
}

function formatSignatoryName(name) {
    return String(name || '').replace(/\s+/g, ' ').trim();
}

function getEmployeeFullName(emp) {
    if (!emp) return '';
    return formatSignatoryName(emp.full_name || `${emp.first_name || ''} ${emp.middle_name || ''} ${emp.last_name || ''}`);
}

function getPositionById(positionId) {
    return financialReportPositions.find(position => Number(position.position_id) === Number(positionId));
}

function syncSignatoryFormToData() {
    const rows = document.querySelectorAll('#signatoryList .signatory-row');
    rows.forEach((row, index) => {
        const signatory = financialReportSignatories[index];
        if (!signatory) return;

        signatory.label = row.querySelector('.signatory-label-input')?.value?.trim() || '';
        signatory.employee_id = Number(row.querySelector('.signatory-employee-id')?.value) || 0;
        signatory.is_active = row.querySelector('.signatory-active-input')?.checked ? 1 : 0;

        const employee = signatory.employee_id ? getEmployeeById(signatory.employee_id) : null;
        if (employee) {
            signatory.name = getEmployeeFullName(employee);
            signatory.position_id = Number(employee.position_id) || 0;
            signatory.position_name = employee.position_name || '';
            signatory.employee_available = 1;
            return;
        }

        signatory.name = row.querySelector('.signatory-name-input')?.value?.trim() || '';
        const positionSelect = row.querySelector('.signatory-position-select');
        signatory.position_id = Number(positionSelect?.value) || 0;
        const position = getPositionById(signatory.position_id);
        signatory.position_name = position?.position_name || '';
    });
}

function hideAllSignatoryEmployeeResults() {
    document.querySelectorAll('.signatory-employee-results').forEach(el => {
        el.style.display = 'none';
        el.innerHTML = '';
        el.closest('.signatory-employee-search')?.querySelector('.signatory-employee-input')?.setAttribute('aria-expanded', 'false');
    });
}

function filterSignatoryEmployees(searchText, limit = 8) {
    const term = (searchText || '').toLowerCase().trim();
    if (!term) {
        return financialReportEmployees.slice(0, limit);
    }
    return financialReportEmployees.filter(e => {
        const full = getEmployeeFullName(e).toLowerCase();
        const first = (e.first_name || '').toLowerCase();
        const last = (e.last_name || '').toLowerCase();
        const pos = (e.position_name || '').toLowerCase();
        return full.includes(term) || first.includes(term) || last.includes(term) || pos.includes(term);
    }).slice(0, limit);
}

function renderSignatoryEmployeeResults(input, index) {
    const row = input.closest('.signatory-row');
    if (!row) return;
    const resultsEl = row.querySelector('.signatory-employee-results');
    if (!resultsEl) return;

    const employees = filterSignatoryEmployees(input.value, 8);
    if (employees.length === 0) {
        resultsEl.innerHTML = '<div class="signatory-employee-result text-muted">No employees found</div>';
    } else {
        resultsEl.innerHTML = employees.map((emp, resultIndex) => `
            <div class="signatory-employee-result"
                 role="option"
                 tabindex="0"
                 aria-selected="false"
                 data-employee-id="${esc(emp.employee_id)}"
                 onmousedown="onSignatoryEmployeeResultMouseDown(this, ${index}); event.preventDefault();"
                 onkeydown="onSignatoryEmployeeResultKeydown(event, this, ${index}, ${resultIndex});">
                <span>${esc(getEmployeeFullName(emp))}</span>
                ${emp.position_name ? `<small>${esc(emp.position_name)}</small>` : ''}
            </div>`).join('');
    }
    input.setAttribute('aria-expanded', 'true');
    resultsEl.style.display = 'block';
}

function onSignatoryEmployeeSearch(input, index) {
    if (selectingSignatoryEmployee) return;
    renderSignatoryEmployeeResults(input, index);
}

function onSignatoryEmployeeSearchFocus(input, index) {
    if (selectingSignatoryEmployee) return;
    renderSignatoryEmployeeResults(input, index);
}

function onSignatoryEmployeeSearchKeydown(event, input, index) {
    if (event.key === 'Escape') {
        hideSignatoryEmployeeResults(input);
        return;
    }
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
    event.preventDefault();
    const row = input.closest('.signatory-row');
    const results = Array.from(row?.querySelectorAll('.signatory-employee-result[role="option"]') || []);
    if (results.length === 0) {
        renderSignatoryEmployeeResults(input, index);
        row?.querySelector('.signatory-employee-result[role="option"]')?.focus();
        return;
    }
    const targetIndex = event.key === 'ArrowDown' ? 0 : results.length - 1;
    results[targetIndex]?.focus();
}

function onSignatoryEmployeeResultMouseDown(resultEl, index) {
    selectingSignatoryEmployee = true;
    onSignatoryEmployeeResultSelect(resultEl, index);
    setTimeout(() => { selectingSignatoryEmployee = false; }, 100);
}

function onSignatoryEmployeeResultKeydown(event, resultEl, index, resultIndex) {
    const results = Array.from(resultEl.closest('.signatory-employee-results')?.querySelectorAll('[role="option"]') || []);
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        selectingSignatoryEmployee = true;
        onSignatoryEmployeeResultSelect(resultEl, index);
        setTimeout(() => { selectingSignatoryEmployee = false; }, 100);
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const nextIndex = event.key === 'ArrowDown' ? resultIndex + 1 : resultIndex - 1;
        results[nextIndex]?.focus();
    }
}

function onSignatoryEmployeeResultSelect(resultEl, index) {
    const employeeId = Number(resultEl.dataset.employeeId) || 0;
    const emp = getEmployeeById(employeeId);
    const sig = financialReportSignatories[index];
    if (!sig) return;

    sig.employee_id = employeeId;
    sig.position_id = emp ? (emp.position_id || 0) : 0;
    sig.position_name = emp ? (emp.position_name || '') : '';
    sig.name = emp ? getEmployeeFullName(emp) : (sig.name || '');
    sig.employee_available = emp ? 1 : 0;

    updateSignatoryEmployeeRow(index);
}

function updateSignatoryEmployeeRow(index) {
    if (!financialReportSignatories[index]) return;
    renderSignatoryList();
    document.querySelector(`.signatory-row[data-index="${index}"] .signatory-employee-input`)?.focus();
}

function hideSignatoryEmployeeResults(input) {
    const row = input?.closest('.signatory-row');
    if (!row) return;
    const resultsEl = row.querySelector('.signatory-employee-results');
    if (resultsEl) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
    }
    input?.setAttribute('aria-expanded', 'false');
}

function onSignatoryEmployeeSearchBlur(input, index) {
    // Skip blur handling while a result is being selected (mousedown on result)
    if (selectingSignatoryEmployee) return;

    const sig = financialReportSignatories[index];
    const searchTerm = input.value.trim().toLowerCase();
    const currentEmp = sig?.employee_id ? getEmployeeById(sig.employee_id) : null;
    const currentName = currentEmp ? getEmployeeFullName(currentEmp).toLowerCase() : '';

    // If the user cleared the search and the current employee name is gone, clear the employee
    if (!searchTerm) {
        if (sig?.employee_id) {
            sig.employee_id = 0;
            sig.position_id = 0;
            sig.position_name = '';
            sig.name = '';
            sig.employee_available = 1;
            updateSignatoryEmployeeRow(index);
        }
        hideSignatoryEmployeeResults(input);
        return;
    }

    if (!currentEmp || currentName !== searchTerm) {
        // The user typed something but did not select a result; restore the current employee name if any.
        input.value = currentEmp ? getEmployeeFullName(currentEmp) : '';
    }

    hideSignatoryEmployeeResults(input);
}

function openSignatorySettings() {
    if (window.POS_TXN_CONFIG?.canManageSignatories === false) {
        showToast('warning', 'Permission denied', 'You are not authorized to manage report signatories.');
        return;
    }
    signatorySettingsTrigger = document.activeElement;
    const branchId = resolveSignatoryBranchId();
    const signatoryBranchSelect = document.getElementById('signatoryBranchSelect');
    if (signatoryBranchSelect) {
        signatoryBranchSelect.value = branchId || '';
    }
    financialReportSignatoryBranchId = branchId;
    loadSignatories(branchId);
    if (financialReportSignatoryModal) {
        financialReportSignatoryModal.show();
        window.setTimeout(() => signatoryBranchSelect?.focus(), 150);
    }
}

function onSignatoryBranchChange() {
    const signatoryBranchSelect = document.getElementById('signatoryBranchSelect');
    financialReportSignatoryBranchId = signatoryBranchSelect?.value ? Number(signatoryBranchSelect.value) : null;
    loadSignatories(financialReportSignatoryBranchId);
}

async function loadSignatories(branchId) {
    const requestId = ++financialReportSignatoryRequestId;
    financialReportSignatoryLoading = Boolean(branchId);
    financialReportSignatoryLoadError = '';
    financialReportSignatories = [];
    financialReportEmployees = [];
    financialReportPositions = [];
    renderSignatoryList();

    if (!branchId) {
        financialReportSignatoryLoading = false;
        renderSignatoryList();
        return;
    }

    try {
        const baseUrl = `${window.POS_TXN_CONFIG.apiUrl}/signatories`;
        const [signRes, posRes, empRes] = await Promise.all([
            fetch(`${baseUrl}?branch_id=${encodeURIComponent(branchId)}`, { cache: 'no-store', credentials: 'same-origin' }),
            fetch(`${baseUrl}?positions=1`, { cache: 'no-store', credentials: 'same-origin' }),
            fetch(`${baseUrl}?employees=1&branch_id=${encodeURIComponent(branchId)}`, { cache: 'no-store', credentials: 'same-origin' })
        ]);
        const signResult = await signRes.json();
        const posResult = await posRes.json();
        const empResult = await empRes.json();

        if (!signResult.success) throw new Error(signResult.error || 'Failed to load signatories');
        if (!posResult.success) throw new Error(posResult.error || 'Failed to load positions');
        if (!empResult.success) throw new Error(empResult.error || 'Failed to load employees');
        if (requestId !== financialReportSignatoryRequestId) return;

        financialReportPositions = posResult.positions || [];
        financialReportEmployees = (empResult.employees || []).map(e => ({
            ...e,
            full_name: getEmployeeFullName(e)
        }));
        financialReportSignatories = normalizeSignatoryConfig(signResult.signatories, true, false);
        financialReportSignatoryBranchId = Number(signResult.branch_id);
    } catch (e) {
        if (requestId !== financialReportSignatoryRequestId) return;
        financialReportSignatoryLoadError = e.message || 'Unable to load signatories.';
    } finally {
        if (requestId === financialReportSignatoryRequestId) {
            financialReportSignatoryLoading = false;
            renderSignatoryList();
        }
    }
}

function setSignatoryControlsState() {
    const hasBranch = Boolean(financialReportSignatoryBranchId);
    const canManage = window.POS_TXN_CONFIG?.canManageSignatories !== false;
    const disabled = !hasBranch || financialReportSignatoryLoading || Boolean(financialReportSignatoryLoadError) || !canManage;
    const addButton = document.getElementById('addSignatoryBtn');
    const saveButton = document.getElementById('saveSignatoriesBtn');
    if (addButton) addButton.disabled = disabled;
    if (saveButton) saveButton.disabled = disabled;
}

function renderPositionOptions(positionId) {
    const selectedId = Number(positionId) || 0;
    return `<option value="">Not specified</option>${financialReportPositions.map(position => `
        <option value="${esc(position.position_id)}" ${Number(position.position_id) === selectedId ? 'selected' : ''}>${esc(position.position_name)}</option>`).join('')}`;
}

function onSignatoryPositionChange(select, index) {
    const signatory = financialReportSignatories[index];
    if (!signatory) return;
    signatory.position_id = Number(select.value) || 0;
    signatory.position_name = getPositionById(signatory.position_id)?.position_name || '';
}

function renderSignatoryList() {
    const container = document.getElementById('signatoryList');
    if (!container) return;
    const status = document.getElementById('signatoryBranchStatus');
    const branchSelect = document.getElementById('signatoryBranchSelect');
    const selectedBranch = branchSelect?.selectedOptions?.[0];
    if (status) {
        status.textContent = financialReportSignatoryBranchId && selectedBranch
            ? `Editing: ${selectedBranch.textContent}`
            : 'No branch selected';
        status.className = `badge rounded-pill ${financialReportSignatoryBranchId ? 'bg-subtle-success text-success' : 'bg-subtle-secondary text-secondary'}`;
    }
    setSignatoryControlsState();

    if (financialReportSignatoryLoading) {
        container.innerHTML = '<div class="signatory-list-message"><span class="fas fa-spinner fa-spin me-2" aria-hidden="true"></span>Loading branch signatories...</div>';
        return;
    }
    if (!financialReportSignatoryBranchId) {
        container.innerHTML = '<div class="signatory-list-message"><span class="fas fa-building me-2" aria-hidden="true"></span>Select a branch to manage its signatories.</div>';
        return;
    }
    if (financialReportSignatoryLoadError) {
        container.innerHTML = `<div class="signatory-list-message text-danger"><span class="fas fa-exclamation-triangle me-2" aria-hidden="true"></span>${esc(financialReportSignatoryLoadError)}<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-danger" onclick="loadSignatories(${Number(financialReportSignatoryBranchId) || 0})">Retry</button></div></div>`;
        return;
    }
    if (financialReportSignatories.length === 0) {
        container.innerHTML = '<div class="signatory-list-message"><span class="fas fa-user-plus me-2" aria-hidden="true"></span>No signatories configured. Add the roles that should appear on this branch\'s report.</div>';
        return;
    }

    container.innerHTML = financialReportSignatories.map((sig, index) => {
        const emp = sig.employee_id ? getEmployeeById(sig.employee_id) : null;
        const employeeName = emp ? getEmployeeFullName(emp) : (sig.employee_id ? (sig.name || '') : '');
        const positionName = emp ? (emp.position_name || '') : (sig.position_name || '');
        const employeeAvailable = sig.employee_available !== 0;
        const positionControl = emp && sig.employee_id > 0
            ? `<div class="signatory-position-display">${esc(positionName || 'Position not assigned')}</div>`
            : `<select class="form-select form-select-sm signatory-position-select" onchange="onSignatoryPositionChange(this, ${index})" aria-label="Position for signatory ${index + 1}">${renderPositionOptions(sig.position_id)}</select>`;
        const staleEmployeeMessage = sig.employee_id > 0 && !employeeAvailable
            ? '<div class="signatory-validation-message text-danger"><span class="fas fa-exclamation-circle me-1" aria-hidden="true"></span>Employee unavailable for this branch. Select a replacement.</div>'
            : '';

        return `
        <div class="signatory-row" data-index="${index}">
            <div class="signatory-col signatory-col-order">
                <span class="signatory-order-number">${index + 1}</span>
            </div>
            <div class="signatory-col signatory-col-main">
                <label class="visually-hidden" for="signatory-label-${index}">Role or label</label>
                <input id="signatory-label-${index}" type="text" class="form-control form-control-sm signatory-label-input" value="${esc(sig.label)}" placeholder="e.g. Prepared by" maxlength="100">
                ${sig.employee_id > 0 ? '' : `<label class="visually-hidden" for="signatory-name-${index}">Manual display name</label><input id="signatory-name-${index}" type="text" class="form-control form-control-sm signatory-name-input" value="${esc(sig.name)}" placeholder="Manual display name" maxlength="150">`}
            </div>
            <div class="signatory-col signatory-col-employee">
                <input type="hidden" class="signatory-employee-id" value="${esc(sig.employee_id)}">
                <div class="signatory-employee-search w-100">
                    <label class="visually-hidden" for="signatory-employee-${index}">Employee</label>
                    <input id="signatory-employee-${index}" type="text"
                           class="form-control form-control-sm signatory-employee-input"
                           value="${esc(employeeName)}"
                           placeholder="Search employee..."
                           role="combobox"
                           aria-autocomplete="list"
                           aria-expanded="false"
                           aria-controls="signatory-employee-results-${index}"
                           autocomplete="off"
                           onkeydown="onSignatoryEmployeeSearchKeydown(event, this, ${index})"
                           oninput="onSignatoryEmployeeSearch(this, ${index})"
                           onfocus="onSignatoryEmployeeSearchFocus(this, ${index})"
                           onblur="onSignatoryEmployeeSearchBlur(this, ${index})">
                    <div class="signatory-employee-results" id="signatory-employee-results-${index}" role="listbox"></div>
                </div>
                ${staleEmployeeMessage}
            </div>
            <div class="signatory-col signatory-col-position">
                ${positionControl}
            </div>
            <div class="signatory-col signatory-col-active">
                <label class="signatory-active-label" title="Show in report" for="signatory-active-${index}">
                    <input id="signatory-active-${index}" type="checkbox" class="form-check-input signatory-active-input" ${sig.is_active ? 'checked' : ''}>
                    <span class="small">Active</span>
                </label>
            </div>
            <div class="signatory-col signatory-col-actions">
                <button type="button" class="btn btn-sm btn-link text-secondary" onclick="moveSignatoryRow(${index}, -1)" title="Move up" aria-label="Move signatory ${index + 1} up" ${index === 0 ? 'disabled' : ''}>
                    <span class="fas fa-chevron-up" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-sm btn-link text-secondary" onclick="moveSignatoryRow(${index}, 1)" title="Move down" aria-label="Move signatory ${index + 1} down" ${index === financialReportSignatories.length - 1 ? 'disabled' : ''}>
                    <span class="fas fa-chevron-down" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="removeSignatoryRow(${index})" title="Remove" aria-label="Remove signatory ${index + 1}">
                    <span class="fas fa-trash-alt" aria-hidden="true"></span>
                </button>
            </div>
        </div>`;
    }).join('');
}

function addSignatoryRow() {
    if (!financialReportSignatoryBranchId) {
        showToast('warning', 'No branch selected', 'Please select a branch before adding a signatory.');
        return;
    }
    syncSignatoryFormToData();
    const nextOrder = financialReportSignatories.length;
    financialReportSignatories.push({
        label: '',
        name: '',
        employee_id: 0,
        position_id: 0,
        position_name: '',
        employee_available: 1,
        sort_order: nextOrder,
        is_active: 1
    });
    renderSignatoryList();
    const inputs = document.querySelectorAll('.signatory-label-input');
    if (inputs.length > 0) inputs[inputs.length - 1].focus();
}

function removeSignatoryRow(index) {
    if (!confirm('Remove this signatory?')) return;
    syncSignatoryFormToData();
    financialReportSignatories.splice(index, 1);
    financialReportSignatories.forEach((signatory, order) => { signatory.sort_order = order; });
    renderSignatoryList();
}

function moveSignatoryRow(index, direction) {
    syncSignatoryFormToData();
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= financialReportSignatories.length) return;
    const item = financialReportSignatories[index];
    financialReportSignatories.splice(index, 1);
    financialReportSignatories.splice(newIndex, 0, item);
    financialReportSignatories.forEach((signatory, order) => { signatory.sort_order = order; });
    renderSignatoryList();
}

function collectSignatoriesFromForm() {
    syncSignatoryFormToData();
    return financialReportSignatories
        .filter(signatory => (signatory.label || '').trim() !== '')
        .map((signatory, index) => ({
            label: signatory.label.trim(),
            name: signatory.name.trim(),
            employee_id: Number(signatory.employee_id) || 0,
            position_id: Number(signatory.position_id) || 0,
            sort_order: index,
            is_active: Number(signatory.is_active) !== 0 ? 1 : 0
        }));
}

async function saveSignatories() {
    const branchId = financialReportSignatoryBranchId;
    if (!branchId) {
        showToast('warning', 'No branch selected', 'Please select a branch for these signatories.');
        return;
    }
    const signatories = collectSignatoriesFromForm();
    const btn = document.getElementById('saveSignatoriesBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="fas fa-spinner fa-spin me-1" aria-hidden="true"></span>Saving...';
    }
    try {
        const url = `${window.POS_TXN_CONFIG.apiUrl}/signatories`;
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({ branch_id: branchId, signatories })
        });
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Failed to save signatories');
        financialReportSignatories = normalizeSignatoryConfig(result.signatories, true, false);
        financialReportSignatoryLoadError = '';
        renderSignatoryList();
        if (financialReportData && Number(financialReportData.signatory_branch_id) === Number(branchId)) {
            financialReportData.signatory_config = result.signatories || [];
            financialReportData.signatory_configured = true;
        }
        if (financialReportSignatoryModal) financialReportSignatoryModal.hide();
        if (document.getElementById('financialReportTab')?.classList.contains('active')) {
            renderFinancialReport();
        }
        showToast('success', 'Signatories saved', 'The financial report signatories have been updated.');
    } catch (e) {
        showToast('danger', 'Save failed', esc(e.message));
    } finally {
        if (btn) {
            btn.innerHTML = '<span class="fas fa-save me-1" aria-hidden="true"></span>Save Signatories';
        }
        setSignatoryControlsState();
    }
}

// ─── Transaction Detail Modal ─────────────────────────────────────────────────

async function viewTxnDetail(orderId) {
    document.getElementById('txnDetailContent').innerHTML =
        '<div class="text-center py-4"><span class="fas fa-spinner fa-spin fs-3"></span></div>';
    txnDetailOffcanvas.show();

    try {
        const res    = await fetch(`${window.BASE_URL}/api/pos/transaction.php?id=${orderId}`);
        const result = await res.json();
        if (!result.success && !result.order_id && !result.order_code) throw new Error('Failed to load');

        const txn = result.transaction || result;
        renderDetailModal(txn);
    } catch (e) {
        document.getElementById('txnDetailContent').innerHTML =
            `<div class="text-danger"><span class="fas fa-exclamation-triangle me-1"></span>Could not load transaction details.</div>`;
    }
}

function renderDetailModal(txn) {
    const items = txn.order_items || txn.items || [];

    // Payment methods
    let paymentsHtml = '';
    const payments = txn.payment_methods_json
        ? (typeof txn.payment_methods_json === 'string' ? JSON.parse(txn.payment_methods_json) : txn.payment_methods_json)
        : (txn.payments || []);
    if (payments && payments.length > 0) {
        paymentsHtml = payments.map(p =>
            `<span class="badge bg-light text-dark border me-1 mb-1">${esc(p.method_name || p.method_code || '')} — ₱${fmt(p.amount)}</span>`
        ).join('');
    } else if (txn.payment_method) {
        paymentsHtml = `<span class="badge bg-light text-dark border">${esc(txn.payment_method)}</span>`;
    }

    const adjustments = txn.adjustments || [];
    const latestAdjustment = adjustments[0] || {};
    const isVoided = isApprovedVoid(txn) || (
        String(latestAdjustment.type || '').toUpperCase() === 'VOID'
        && String(latestAdjustment.approval_status || '').toUpperCase() === 'APPROVED'
    );
    const technicalLostSalesAmount = getTechnicalLostSalesAmount(latestAdjustment);
    const adjustmentsHtml = adjustments.length
        ? `<hr class="my-4"><div class="mb-3"><h6 class="fw-bold text-uppercase text-muted small"><span class="fas fa-user-shield me-2"></span>Adjustments & Responsibility</h6>${adjustments.map(adjustment => `
            <div class="card border-light mb-2">
                <div class="card-body p-2 small">
                    <div class="d-flex justify-content-between">
                        <strong>${esc(adjustment.type || 'Adjustment')}</strong>
                        <span>₱${fmt(adjustment.amount)}</span>
                    </div>
                    <div class="text-muted">${esc(adjustment.reason || 'No reason provided')}</div>
                    ${adjustment.reason_category ? `<div class="text-muted">Category: ${esc(adjustment.reason_category)}</div>` : ''}
                    ${adjustment.type === 'VOID' && adjustment.reason_category === 'PRINTER_ERROR' && adjustment.cancellation_status === 'completed' && getTechnicalLostSalesAmount(adjustment) > 0 ? `<div class="text-danger">Lost Sales: -₱${fmt(getTechnicalLostSalesAmount(adjustment))}</div>` : ''}
                    ${adjustment.type === 'VOID' && parseFloat(adjustment.void_fee || 0) > 0 ? `<div class="text-warning">Void Fee Income: ₱${fmt(adjustment.void_fee)}</div>` : ''}
                    ${adjustment.type === 'VOID' && parseFloat(adjustment.void_service_fee || 0) > 0 ? `<div class="text-info">Service Fee Income: ₱${fmt(adjustment.void_service_fee)}</div>` : ''}
                    <div class="text-muted">Responsible: ${esc(adjustment.charged_to || 'none')}${adjustment.responsible_cashier_name ? ` — ${esc(adjustment.responsible_cashier_name)}` : ''}</div>
                    <div class="text-muted">Approval: ${esc(adjustment.approval_status || '—')} · Settlement: ${esc(adjustment.settlement_status || '—')}</div>
                </div>
            </div>`).join('')}</div>`
        : '';

    // Items
    let itemsHtml = '';
    items.forEach(item => {
        const isTicket = item.item_type === 'TICKET';
        const ticketAction = isTicket ? getTicketActionLabel(item.ticket_action ?? item.ticketAction) : '';
        itemsHtml += `<div class="card mb-2 border-light shadow-sm">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge ${isTicket ? 'bg-primary' : 'bg-info'} me-1" style="font-size:0.7rem">${esc(item.item_type)}</span>
                            <span class="fw-semibold small">${esc(item.transaction_code || '')}</span>
                        </div>
                        ${item.ticket_number ? `<div class="text-primary small mb-1"><span class="fas fa-ticket-alt me-1"></span>${esc(item.ticket_number)}</div>` : ''}
                        ${ticketAction ? `<div class="text-info small mb-1"><span class="fas fa-exchange-alt me-1"></span>Special Action: ${esc(ticketAction)}</div>` : ''}
                        ${item.name ? `<div class="text-muted small mb-1"><span class="fas fa-user me-1"></span>${esc(item.name)}</div>` : ''}
                        ${item.origin && item.destination ? `<div class="text-muted small mb-1"><span class="fas fa-route me-1"></span>${esc(item.origin)} → ${esc(item.destination)}</div>` : ''}
                        ${item.travel_date ? `<div class="text-muted small mb-1"><span class="fas fa-calendar me-1"></span>Travel: ${formatDate(item.travel_date)}</div>` : ''}
                        ${item.accommodation_name ? `<div class="text-muted small mb-1"><span class="fas fa-bed me-1"></span>${esc(item.accommodation_name)}</div>` : ''}
                        ${item.discount_name ? `<div class="text-success small mb-1"><span class="fas fa-percent me-1"></span>${esc(item.discount_name)}</div>` : ''}
                        ${item.provider_name || item.parent_provider_name || item.variant_name ? `<div class="text-muted small mb-1"><span class="fas fa-building me-1"></span>${buildProviderWallet(item)}</div>` : ''}
                        ${item.service_name ? `<div class="text-muted small mb-1"><span class="fas fa-cog me-1"></span>${esc(item.service_name)}</div>` : ''}
                        ${item.ticket_status ? `<span class="badge status-badge-${item.ticket_status} mt-1" style="font-size:0.65rem">${esc(item.ticket_status)}</span>` : ''}
                    </div>
                    <div class="ms-2 text-end">
                        <div class="fw-bold small">₱${fmt(item.total_amount)}</div>
                    </div>
                </div>
            </div>
        </div>`;
    });

    const refunded = parseFloat(txn.total_refunded_amount || 0);

    document.getElementById('txnDetailContent').innerHTML = `
        <div class="row g-4">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Order Code</label>
                    <div class="fw-bold text-primary small">${esc(txn.order_code || txn.transaction_code || '—')}</div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Cashier</label>
                    <div class="d-flex align-items-center">
                        <span class="fas fa-user-circle me-2 text-muted"></span>
                        <span class="small">${esc(txn.cashier_name || '—')}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Branch</label>
                    <div class="d-flex align-items-center">
                        <span class="fas fa-building me-2 text-muted"></span>
                        <span class="small">${esc(txn.branch_name || '—')}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Date</label>
                    <div class="d-flex align-items-center">
                        <span class="fas fa-clock me-2 text-muted"></span>
                        <span class="small">${txn.created_at ? formatDateTime(txn.created_at) : '—'}</span>
                    </div>
                </div>
                ${txn.or_full_number ? `<div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Official Receipt No.</label>
                    <div class="d-flex align-items-center">
                        <span class="fas fa-file-invoice me-2 text-success"></span>
                        <span class="small fw-semibold text-success">${esc(txn.or_full_number)}</span>
                    </div>
                </div>` : ''}
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Payment</label>
                    <div>${paymentsHtml || '<span class="text-muted small">—</span>'}</div>
                    ${parseFloat(txn.amount_paid || 0) > 0 ? `<div class="text-muted small mt-2">
                        <div><span class="fas fa-check-circle text-success me-1"></span>Paid: ₱${fmt(txn.amount_paid)}</div>
                        <div class="mt-1"><span class="fas fa-check-circle text-success me-1"></span>Change: ₱${fmt(txn.change_amount)}</div>
                    </div>` : ''}
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Status</label>
                    <div>
                        <span class="badge status-badge-${txn.status || 'completed'}">${esc((txn.status || 'completed').charAt(0).toUpperCase() + (txn.status || '').slice(1))}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Amount Breakdown</label>
                    <div class="card bg-light border-0 txn-amount-breakdown">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>₱${fmt(txn.subtotal)}</span>
                            </div>
                            ${parseFloat(txn.discount_total || 0) > 0 ? `<div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Discount</span>
                                <span>-₱${fmt(txn.discount_total)}</span>
                            </div>` : ''}
                            ${!isVoided ? `<div class="d-flex justify-content-between mb-2">
                                <span>Base fare</span>
                                <span>₱${fmt(txn.total_cost)}</span>
                            </div>` : ''}
                            ${!isVoided && parseFloat(txn.total_service_fees || 0) > 0 ? `<div class="d-flex justify-content-between mb-2">
                                <span>Service Fee</span>
                                <span>₱${fmt(txn.total_service_fees)}</span>
                            </div>` : ''}
                            ${isVoided && String(latestAdjustment.reason_category || '').toUpperCase() === 'PRINTER_ERROR' && String(latestAdjustment.cancellation_status || '').toLowerCase() === 'completed' && technicalLostSalesAmount > 0 ? `<div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Lost Sales</span>
                                <span>-₱${fmt(technicalLostSalesAmount)}</span>
                            </div>` : ''}
                            ${parseFloat(txn.total_add_ons || 0) > 0 ? `<div class="d-flex justify-content-between mb-2">
                                <span>Add-ons</span>
                                <span>₱${fmt(txn.total_add_ons)}</span>
                            </div>` : ''}
                            <div class="d-flex justify-content-between fw-bold border-top pt-2">
                                <span>Total</span>
                                <span>₱${fmt(txn.total_amount || txn.grand_total)}</span>
                            </div>
                            ${parseFloat(txn.total_profit || 0) > 0 ? `<div class="d-flex justify-content-between text-success mt-2">
                                <span>Profit</span>
                                <span>₱${fmt(txn.total_profit)}</span>
                            </div>` : ''}
                            ${refunded > 0 ? `<div class="d-flex justify-content-between text-danger mt-2">
                                <span>Refunded</span>
                                <span>-₱${fmt(refunded)}</span>
                            </div>` : ''}

                        </div>
                    </div>
                </div>
            </div>
        </div>
        ${adjustmentsHtml}
        ${itemsHtml ? `<hr class="my-4">
        <div class="mb-3">
            <h6 class="fw-bold text-uppercase text-muted small"><span class="fas fa-list me-2"></span>Order Items (${items.length})</h6>
        </div>
        ${itemsHtml}` : ''}
    `;
}

// ─── Print ────────────────────────────────────────────────────────────────────

function getPrintStatusColor(statusText) {
    if (!statusText) return '#000';
    const s = statusText.toLowerCase();
    if (s.includes('voided') || s.includes('cancelled')) return '#dc3545';
    if (s.includes('completed') || s.includes('booked')) return '#198754';
    if (s.includes('refunded')) return '#0d6efd';
    if (s.includes('pending')) return '#fd7e14';
    return '#000';
}

// Helper function to format cashier name as "Anthony C." (first name + middle initial)
function formatCashierName(fullName) {
    if (!fullName) return '';
    const parts = fullName.trim().split(/\s+/);
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0];
    
    const firstName = parts[0];
    const middleInitial = parts.length > 2 ? parts[1].charAt(0).toUpperCase() + '.' : '';
    const lastName = parts.length > 2 ? parts[parts.length - 1] : parts[1];
    
    if (middleInitial) {
        return `${firstName} ${middleInitial}`;
    }
    return firstName;
}

function printTransactions() {
    const table = document.getElementById('transactionsTable');
    if (!table) return;

    // Get filter info
    const status = document.getElementById('filterStatus');
    const type = document.getElementById('filterType');
    const dateFrom = document.getElementById('filterDateFrom');
    const dateTo = document.getElementById('filterDateTo');
    const branch = document.getElementById('filterBranch');
    const provider = document.getElementById('filterProvider');
    const providerType = document.getElementById('filterProviderType');
    const cashier = document.getElementById('filterCashier');

    // Determine selected branch/cashier/provider-type labels and whether they are filtered
    const selectedBranchText = (branch && branch.value && branch.options[branch.selectedIndex])
        ? branch.options[branch.selectedIndex].text : '';
    const selectedCashierText = (cashier && cashier.value && cashier.options[cashier.selectedIndex])
        ? cashier.options[cashier.selectedIndex].text : '';
    const selectedProviderTypeText = (providerType && providerType.value && providerType.options[providerType.selectedIndex])
        ? providerType.options[providerType.selectedIndex].text : '';
    const isBranchFiltered = !!selectedBranchText;
    const isCashierFiltered = !!selectedCashierText;
    const isProviderTypeFiltered = !!selectedProviderTypeText;

    // Build comprehensive filter info for print display
    let filterParts = [];
    
    // Helper function to format date nicely
    const formatNiceDate = (dateStr) => {
        const date = new Date(dateStr);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[date.getMonth()];
        const day = date.getDate();
        const year = date.getFullYear();
        return `${month} ${day}, ${year}`;
    };
    
    // Date filter - always show with cleaner format
    if (dateFrom && dateFrom.value && dateTo && dateTo.value) {
        if (dateFrom.value === dateTo.value) {
            filterParts.push(`Date: ${formatNiceDate(dateFrom.value)}`);
        } else {
            const fromDate = new Date(dateFrom.value);
            const toDate = new Date(dateTo.value);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            if (fromDate.getMonth() === toDate.getMonth() && fromDate.getFullYear() === toDate.getFullYear()) {
                // Same month and year: May 26-28, 2026
                filterParts.push(`Date Range: ${months[fromDate.getMonth()]} ${fromDate.getDate()}-${toDate.getDate()}, ${fromDate.getFullYear()}`);
            } else {
                // Different months or years: May 26, 2026 - Jun 28, 2026
                filterParts.push(`Date Range: ${formatNiceDate(dateFrom.value)} - ${formatNiceDate(dateTo.value)}`);
            }
        }
    } else if (dateFrom && dateFrom.value) {
        filterParts.push(`From: ${formatNiceDate(dateFrom.value)}`);
    }
    
    // Other filters - only show if not "all" with cleaner format
    // Branch and cashier are intentionally shown in the dedicated header section
    // Provider type is intentionally shown in the report title
    if (status && status.value !== 'all') filterParts.push(`Status: ${status.options[status.selectedIndex].text}`);
    if (type && type.value !== 'all') filterParts.push(`Type: ${type.options[type.selectedIndex].text}`);
    if (provider && provider.value) filterParts.push(`Provider: ${provider.options[provider.selectedIndex].text}`);

    const filterDisplay = filterParts.length > 0
        ? filterParts.join(' | ')
        : (isBranchFiltered || isCashierFiltered || isProviderTypeFiltered ? 'Filtered transactions' : 'All records');

    const formatProviderType = (text) => /line(s)?$/i.test(text)
        ? text.toUpperCase()
        : `${text.toUpperCase()} LINES`;
    let providerTypeTitle;
    if (isProviderTypeFiltered) {
        providerTypeTitle = formatProviderType(selectedProviderTypeText);
    } else if (providerType && providerType.options && providerType.options.length > 1) {
        const allProviderTypes = Array.from(providerType.options)
            .slice(1)
            .filter(o => o.value)
            .map(o => formatProviderType(o.text));
        providerTypeTitle = allProviderTypes.join(' & ');
    } else {
        providerTypeTitle = '';
    }
    const printTitle = providerTypeTitle
        ? `${providerTypeTitle} DAILY CASHIER REPORT`
        : 'TICKETING TRANSACTIONS REPORT';

    // Header filter info: display selected branch and/or cashier at the top of the print page
    let headerFilterHtml = '';
    if (isBranchFiltered || isCashierFiltered) {
        const headerParts = [];
        if (isBranchFiltered) headerParts.push(`<strong>Branch:</strong> ${esc(selectedBranchText)}`);
        if (isCashierFiltered) headerParts.push(`<strong>Cashier / Sales Person:</strong> ${esc(selectedCashierText)}`);
        headerFilterHtml = `
            <div style="margin: 8px 0 4px 0; padding: 6px 8px; border: 1px solid #000; font-size: 8pt; background: #f9f9f9;">
                ${headerParts.join(' &nbsp;&nbsp;|&nbsp;&nbsp; ')}
            </div>`;
    }

    // Top summary values are computed from the visible printed rows.

    // Build table HTML for print (only visible/filtered rows)
    const columns = [
        { key: 'no', header: 'NO.' },
        { key: 'ticket_number', header: 'TICKET NO.' },
        { key: 'vessel_name', header: 'VESSEL NAME' },
        { key: 'passenger', header: 'PASSENGER' },
        { key: 'cashier', header: 'CASHIER', hide: isCashierFiltered },
        { key: 'branch', header: 'BRANCH', hide: isBranchFiltered },
        { key: 'cost', header: 'COST' },
        { key: 'service_fee', header: 'SERVICE FEE' },
        { key: 'amount', header: 'AMOUNT' },
        { key: 'payment', header: 'PAYMENT' },
        { key: 'status', header: 'STATUS' }
    ].filter(c => !c.hide);

    const tableHeaders = columns.map(c => `<th>${c.header}</th>`).join('');

    let tableHTML = '';
    let totalCost = 0;
    let totalServiceFee = 0;
    let totalTechnicalVoid = 0;
    const parsePeso = s => parseFloat(String(s).replace(/[^\d.-]/g, '')) || 0;
    const rows = table.querySelectorAll('tbody tr');
    const sortedRows = Array.from(rows)
        .filter(row => row.style.display !== 'none')
        .sort((a, b) => {
            const aCell = a.querySelectorAll('td')[4];
            const bCell = b.querySelectorAll('td')[4];
            const aName = aCell ? aCell.textContent.trim().toLowerCase() : '';
            const bName = bCell ? bCell.textContent.trim().toLowerCase() : '';
            return aName.localeCompare(bName);
        });
    let rowNo = 0;
    sortedRows.forEach((row) => {
        const cells = row.querySelectorAll('td');
            if (cells.length >= 13) { // 13 columns in the visible table
                rowNo++;
                // Extract responsibility/cancellation badges from cell 0
                const orderCell = cells[0];
                const cancelBadge = Array.from(orderCell.querySelectorAll('.badge'))
                    .filter(badge => badge.dataset.printHidden !== 'true'
                        && !/responsibility/i.test(badge.textContent))
                    .map(badge => `<div style="font-size:6.5pt; color:#000; margin-top:2px;">${esc(badge.textContent.trim())}</div>`)
                    .join('');

                // Ticket number from row data; fallback to the first visible cell
                const ticketNumber = row.dataset?.ticketNumber?.trim()
                    || cells[0].querySelector('.fw-semibold')?.textContent.trim()
                    || '—';

                // Vessel name (wallet owner / complete provider) from cell 4
                const vesselName = cells[4].textContent.trim() || '—';

                // Passenger from cell 2
                const detailsCell = cells[2].textContent.trim();
                const passengerName = detailsCell.split('\n').map(l => l.trim()).filter(l => l)[0] || '';
                const passengerNumber = row.dataset?.passengerNumber || '';

                // Cashier is cell 5, Branch cell 6, Payment cell 7, Cost cell 8, Service Fee cell 9, Amount cell 10, Status cell 11
                const cashierName = cells[5].textContent.trim();
                const formattedCashier = formatCashierName(cashierName);
                const branchName = cells[6].textContent.trim();

                // Cost and Service Fee from cells 8 and 9
                const costText = cells[8].textContent.trim();
                const serviceFeeText = cells[9].textContent.trim();

                // Amount and breakdown from cell 10
                const amountCell = cells[10];
                const amountMain = amountCell.querySelector('.fw-semibold')?.textContent.trim() || amountCell.textContent.trim();
                const refundText = amountCell.querySelector('.text-danger:not(.fw-semibold):not(.transaction-lost-sales)')?.textContent.trim() || '';
                const lostSalesText = amountCell.querySelector('.transaction-lost-sales')?.textContent.trim() || '';

                // Accumulate totals for the visible rows
                totalCost += parsePeso(costText);
                totalServiceFee += parsePeso(serviceFeeText);
                totalTechnicalVoid += Math.abs(parsePeso(lostSalesText));

                const statusText = cells[11].textContent.trim();

                const colHtml = {
                    no: `<td style="text-align:center;">${rowNo}</td>`,
                    ticket_number: `<td>
                        <div style="margin-bottom:2px;">${esc(ticketNumber)}</div>
                        ${cancelBadge}
                    </td>`,
                    vessel_name: `<td>
                        <div style="margin-bottom:2px;">${vesselName}</div>
                    </td>`,
                    passenger: `<td>
                        <div style="margin-bottom:2px;">${passengerName}</div>
                        ${passengerNumber ? `<div style="font-size:6.5pt; color:#000;">${passengerNumber}</div>` : ''}
                    </td>`,
                    cashier: `<td>${formattedCashier}</td>`,
                    branch: `<td>${branchName}</td>`,
                    payment: `<td>${cells[7].textContent.trim()}</td>`,
                    cost: `<td>${costText}</td>`,
                    service_fee: `<td>${serviceFeeText}</td>`,
                    amount: `<td>
                        <div style="margin-bottom:2px;">${amountMain}</div>
                        ${refundText ? `<div style="font-size:6.5pt; color:#dc3545;">${refundText}</div>` : ''}
                        ${lostSalesText ? `<div style="font-size:6.5pt; color:#dc3545;">${lostSalesText}</div>` : ''}
                    </td>`,
                    status: `<td style="color:${getPrintStatusColor(statusText)};">${statusText}</td>`
                };

                tableHTML += `<tr>${columns.map(c => colHtml[c.key]).join('')}</tr>`;
            }
        });

    // Net sale total (Cost + Service Fee - Lost Sales)
    const netSale = totalCost + totalServiceFee - totalTechnicalVoid;

    // Format date nicely
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-PH', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    const timeStr = now.toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });

    // Determine address based on address source setting
    let printAddress = '';
    if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.addressSource === 'branch' && window.POS_BRANCH_INFO) {
        const bi = window.POS_BRANCH_INFO;
        const addrParts = [];
        if (bi.street_address) addrParts.push(bi.street_address);
        if (bi.barangay_name) addrParts.push(bi.barangay_name);
        if (bi.city_municipality_name) addrParts.push(bi.city_municipality_name);
        if (bi.province_name) addrParts.push(bi.province_name);
        if (bi.region_name) addrParts.push(bi.region_name);
        if (bi.zip_code) addrParts.push(bi.zip_code);
        printAddress = addrParts.join(', ');
    } else if (window.COMPANY_INFO && window.COMPANY_INFO.address) {
        printAddress = window.COMPANY_INFO.address;
    }

    // Build custom print HTML for POS transactions
    const printHTML = `
<!DOCTYPE html>
<html>
<head>
    <title>POS Transactions Report</title>
    <style>
        @media print {
            @page {
                size: A4 portrait;
            }
            body {
                font-family: 'Century Gothic', CenturyGothic, AppleGothic, Arial, sans-serif;
                margin: 0;
                padding: 0;
                font-size: 8pt;
                color: #000;
            }
            .fas, .fa, .far, .fab {
                display: none !important;
            }
            .print-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 12px;
                border-bottom: 1px solid #000;
                padding-bottom: 8px;
            }
            .print-header .logo-section {
                flex: 0 0 auto;
                text-align: left;
            }
            .print-header img {
                max-height: 50px;
                max-width: 120px;
                object-fit: contain;
            }
            .print-header .text-section {
                flex: 1;
                text-align: right;
                padding-left: 20px;
            }
            .print-header h2 {
                margin: 0;
                font-size: 14pt;
                font-weight: 700;
                color: #000;
                font-family: 'Arial Black', 'Helvetica Neue', Arial, sans-serif;
                letter-spacing: 0.5px;
            }
            .print-header .description {
                margin-top: 4px;
                font-size: 8pt;
                color: #000;
                font-style: italic;
            }
            .print-header .meta {
                margin-top: 4px;
                font-size: 7pt;
                color: #000;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
            }
            th, td {
                border: 0.5pt solid #000;
                padding: 3px 5px;
                text-align: left;
            }
            th {
                background: #f0f0f0;
                font-weight: normal;
                text-align: center;
                font-size: 7pt;
                color: #000;
                border-bottom: 0.5pt solid #000;
            }
            td {
                font-size: 7pt;
                color: #000;
            }
            .text-end {
                text-align: right !important;
            }
            .print-footer {
                margin-top: 25px;
                border-top: 1px solid #000;
                padding-top: 12px;
            }
            .footer-company {
                margin-bottom: 8px;
                font-size: 7pt;
                color: #000;
                text-align: center;
            }
            .footer-report-footer {
                margin-bottom: 8px;
                font-size: 7pt;
                color: #000;
                text-align: center;
                font-style: italic;
            }
            .footer-info {
                margin-top: 15px;
                font-size: 6pt;
                color: #666;
                text-align: center;
            }
            .signatures {
                display: flex;
                justify-content: space-between;
                gap: 15px;
            }
            .sig-block {
                flex: 1;
                text-align: center;
            }
            .sig-line {
                border-bottom: 1px solid #000;
                height: 25px;
                margin-bottom: 4px;
            }
            .sig-label {
                font-size: 7pt;
                color: #000;
            }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <div class="logo-section">
            <img src="${window.BASE_URL}/api/images/logo/logo_1779670787_4364a51c.png" alt="Logo" onerror="this.style.display='none'" />
        </div>
        <div class="text-section">
            <h2 style="font-family: Arial, sans-serif; font-weight: normal;">${printTitle}</h2>
            <div class="description" style="font-style: normal;">${esc(filterDisplay)}</div>
        </div>
    </div>
    ${headerFilterHtml}
    <div style="display: flex; justify-content: space-between; gap: 10px; margin: 10px 0;">
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">TOTAL COST</div>
            <div style="font-size: 9pt; font-weight: normal; color: #000;">₱${fmt(totalCost)}</div>
        </div>
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">TOTAL SERVICE FEE</div>
            <div style="font-size: 9pt; font-weight: normal; color: #000;">₱${fmt(totalServiceFee)}</div>
        </div>
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">LOST SALES</div>
            <div style="font-size: 9pt; font-weight: normal; color: #000;">-₱${fmt(totalTechnicalVoid)}</div>
        </div>
        <div style="flex: 1; text-align: center;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">NET SALE</div>
            <div style="font-size: 9pt; font-weight: normal; color: #000;">₱${fmt(netSale)}</div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                ${tableHeaders}
            </tr>
        </thead>
        <tbody>
            ${tableHTML}
        </tbody>
    </table>
    <div class="print-footer">
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Prepared by</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Verified by</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Approved by</div>
            </div>
        </div>
        <div class="footer-info">
            <strong>${window.systemName || 'TMS'}</strong><br>
            ${printAddress}<br>
            ${window.reportFooter || 'System Generated Report'}<br>
            Generated on ${dateStr} at ${timeStr}
        </div>
    </div>
</body>
</html>`;

    // Create iframe and print
    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.left = '-9999px';
    iframe.style.top = '-9999px';
    iframe.style.width = '0';
    iframe.style.height = '0';
    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(printHTML);
    iframeDoc.close();

    iframe.onload = function() {
        setTimeout(function() {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(() => {
                document.body.removeChild(iframe);
            }, 1000);
        }, 200);
    };
}

// ─── Export ───────────────────────────────────────────────────────────────────

async function exportTransactions() {
    const pageSize = 100;
    const firstParams = buildParams(1);
    firstParams.limit = pageSize;

    try {
        const firstUrl = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(firstParams).toString();
        const firstResult = await fetch(firstUrl, { cache: 'no-store' }).then(response => response.json());
        if (!firstResult.success || !firstResult.data?.length) {
            alert('No data to export.');
            return;
        }

        const rows = [...firstResult.data];
        const totalPages = Math.min(firstResult.pagination?.total_pages || 1, 100);
        for (let page = 2; page <= totalPages; page += 1) {
            const params = buildParams(page);
            params.limit = pageSize;
            const url = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();
            const result = await fetch(url, { cache: 'no-store' }).then(response => response.json());
            if (!result.success) throw new Error(result.error || 'Export failed.');
            rows.push(...(result.data || []));
        }

        const header = ['Order Code','Status','Type','Passengers','Operating Provider','Vessel Name','Cashier','Branch','Payment','Lost Sales','Total Amount','Refunded','Operation','Responsibility','Responsible Cashier','Responsibility Amount','Date'];
        const csvRows = [header];
        rows.forEach(txn => {
            const isTicket = txn.ticket_count > 0;
            const type = isTicket && txn.service_count > 0 ? 'Ticket + Service' : (isTicket ? 'Ticket' : 'Service');
            const payments = txn.payments && txn.payments.length > 0
                ? txn.payments.map(p => p.method_name || p.method_code || '').join(' + ')
                : (txn.payment_method || '');
            csvRows.push([
                txn.order_code,
                txn.status,
                type,
                txn.passenger_names || '',
                txn.provider_names || '',
                txn.wallet_provider_names || '',
                txn.cashier_full_name || txn.cashier_name || '',
                txn.branch_name || '',
                payments,
                parseFloat(getTechnicalLostSalesAmount(txn) || 0).toFixed(2),
                parseFloat(getTransactionDisplayAmount(txn) || 0).toFixed(2),
                parseFloat(txn.total_refunded_amount || 0).toFixed(2),
                txn.adjustment_type || '',
                txn.adjustment_responsibility || '',
                txn.adjustment_responsible_cashier || '',
                parseFloat(txn.adjustment_amount || 0).toFixed(2),
                txn.created_at || ''
            ]);
        });

        const csv = csvRows.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `pos-transactions-${new Date().toISOString().slice(0,10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    } catch (error) {
        console.error('Export failed:', error);
        alert('Export failed.');
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDateTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
}

function formatTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
}

function showToast(type, title, message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const id  = 'toast-' + Date.now();
    const bg  = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-primary';
    const ico = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bg} text-white">
                <span class="fas ${ico} me-2"></span>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        </div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
