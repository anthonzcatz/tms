/**
 * POS Transactions Report — AJAX-driven module
 */

let txnDetailOffcanvas;
let currentPage = 1;
let isLoading   = false;
let filtersReady = false;
let allProviders = [];

const fmt = n => parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    txnDetailOffcanvas = new bootstrap.Offcanvas(document.getElementById('txnDetailModal'));

    // Initialize date range picker with current date as default (or restore from localStorage)
    const today = new Date();
    const defaultDateStr = today.toLocaleDateString('en-CA'); // YYYY-MM-DD in local time
    
    // Try to restore saved date from localStorage
    const savedDateFrom = localStorage.getItem('posTxnDateFrom');
    const savedDateTo = localStorage.getItem('posTxnDateTo');
    const dateStr = savedDateFrom || defaultDateStr;
    const dateToStr = savedDateTo || defaultDateStr;
    
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
            defaultDate: [dateStr, dateToStr],
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
        if (dateStr === dateToStr) {
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

    // Load initial data + populate dropdowns
    loadTransactions(1);
});

// ─── Data Loading ─────────────────────────────────────────────────────────────

async function loadTransactions(page) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page || 1;

    const tbody = document.getElementById('transactionsTableBody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
        <span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>`;
    document.getElementById('tableInfo').textContent = 'Loading...';

    const params = buildParams(currentPage);
    const url = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();

    try {
        const res    = await fetch(url);
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Failed to load');

        renderTable(result.data);
        renderPagination(result.pagination);
        updateStats(result.stats);

        // Populate dropdowns only once
        if (!filtersReady && result.filters) {
            populateDropdowns(result.filters);
            filtersReady = true;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">
            <span class="fas fa-exclamation-triangle me-2"></span>${esc(e.message)}</td></tr>`;
        document.getElementById('tableInfo').textContent = 'Error loading data';
    } finally {
        isLoading = false;
    }
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
    
    // Reset date to today (single date) and clear localStorage
    const today = new Date();
    const dateStr = today.toLocaleDateString('en-CA');
    document.getElementById('filterDateFrom').value = dateStr;
    document.getElementById('filterDateTo').value = dateStr;
    localStorage.removeItem('posTxnDateFrom');
    localStorage.removeItem('posTxnDateTo');
    const fp = document.getElementById('filterDateRange')._flatpickr;
    if (fp) fp.setDate([dateStr, dateStr]);
    else { const el = document.getElementById('filterDateRange'); if (el) el.value = dateStr; }
    
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
            opt.textContent = pt.provider_type;
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
        tbody.innerHTML = `<tr><td colspan="9"><div class="text-center py-5 text-muted d-flex flex-column align-items-center justify-content-center">
            <span class="fas fa-receipt fs-2 d-block mb-2 opacity-25"></span>
            <div>No transactions found</div>
            <small>Try adjusting your filters</small>
        </div></td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(txn => {
        const color = statusColors[txn.status] || 'secondary';
        const icon  = statusIcons[txn.status]  || 'fa-circle';
        const isTicket  = txn.ticket_count > 0;
        const isService = txn.service_count > 0;

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
        const ticketLine = txn.ticket_numbers
            ? `<div class="text-primary" style="font-size:.7rem"><span class="fas fa-ticket-alt me-1"></span>${esc(txn.ticket_numbers)}</div>`
            : '';
        const accommodationLine = txn.accommodation_names
            ? `<div class="text-muted" style="font-size:.7rem"><span class="fas fa-bed me-1"></span>${esc(txn.accommodation_names)}</div>`
            : '';
        const discountLine = txn.discount_names
            ? `<div class="text-success" style="font-size:.7rem"><span class="fas fa-percent me-1"></span>${esc(txn.discount_names)}</div>`
            : '';
        const providerLine = txn.provider_names
            ? `<div class="text-muted" style="font-size:.72rem">${esc(txn.provider_names)}</div>`
            : '';
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

        // Amount + refund
        let amountHtml = `<span class="fw-semibold">₱${fmt(txn.total_amount)}</span>`;
        const refunded = parseFloat(txn.total_refunded_amount || 0);
        if (refunded > 0) {
            amountHtml += `<div class="text-danger" style="font-size:.72rem">-₱${fmt(refunded)} refund</div>`;
        }

        // Cancellation badge
        let cancelBadge = '';
        if (txn.has_cancellation) {
            const cStatus = txn.cancellation_status || 'pending';
            const cColor  = cStatus === 'pending' ? 'warning' : (cStatus === 'approved' ? 'danger' : 'secondary');
            cancelBadge = `<div><span class="badge bg-soft-${cColor} text-${cColor}" style="font-size:.65rem">Cancellation ${cStatus}</span></div>`;
        }

        const dateStr = txn.created_at ? formatDate(txn.created_at) : '—';
        const timeStr = txn.created_at ? formatTime(txn.created_at) : '—';

        return `<tr class="txn-row" data-passenger-number="${esc(txn.passenger_numbers || '')}" onclick="viewTxnDetail('${esc(txn.order_id)}')" title="Click to view details">
            <td class="ps-3 py-2">
                <div class="fw-semibold small">${esc(txn.order_code)}</div>
                ${cancelBadge}
            </td>
            <td class="py-2">
                ${typePill}
                ${ticketLine}
            </td>
            <td class="py-2">
                ${passengerLine}
                ${accommodationLine}
                ${discountLine}
                ${providerLine}
                ${routeLine}
            </td>
            <td class="py-2">
                <div class="small">${esc(txn.cashier_full_name || txn.cashier_name || '—')}</div>
            </td>
            <td class="py-2">
                <div class="small text-muted">${esc(txn.branch_code || txn.branch_name || '—')}</div>
            </td>
            <td class="py-2">${payDisplay}</td>
            <td class="py-2 text-end">${amountHtml}</td>
            <td class="py-2">
                <span class="badge status-badge-${txn.status}">
                    <span class="fas ${icon} me-1"></span>${txn.status.charAt(0).toUpperCase() + txn.status.slice(1)}
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

    // Items
    let itemsHtml = '';
    items.forEach(item => {
        const isTicket = item.item_type === 'TICKET';
        itemsHtml += `<div class="card mb-2 border-light shadow-sm">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge ${isTicket ? 'bg-primary' : 'bg-info'} me-1" style="font-size:0.7rem">${esc(item.item_type)}</span>
                            <span class="fw-semibold small">${esc(item.transaction_code || '')}</span>
                        </div>
                        ${item.ticket_number ? `<div class="text-primary small mb-1"><span class="fas fa-ticket-alt me-1"></span>${esc(item.ticket_number)}</div>` : ''}
                        ${item.name ? `<div class="text-muted small mb-1"><span class="fas fa-user me-1"></span>${esc(item.name)}</div>` : ''}
                        ${item.origin && item.destination ? `<div class="text-muted small mb-1"><span class="fas fa-route me-1"></span>${esc(item.origin)} → ${esc(item.destination)}</div>` : ''}
                        ${item.travel_date ? `<div class="text-muted small mb-1"><span class="fas fa-calendar me-1"></span>Travel: ${formatDate(item.travel_date)}</div>` : ''}
                        ${item.accommodation_name ? `<div class="text-muted small mb-1"><span class="fas fa-bed me-1"></span>${esc(item.accommodation_name)}</div>` : ''}
                        ${item.discount_name ? `<div class="text-success small mb-1"><span class="fas fa-percent me-1"></span>${esc(item.discount_name)}</div>` : ''}
                        ${item.provider_name ? `<div class="text-muted small mb-1"><span class="fas fa-building me-1"></span>${esc(item.provider_name)}</div>` : ''}
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
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>₱${fmt(txn.subtotal)}</span>
                            </div>
                            ${parseFloat(txn.discount_total || 0) > 0 ? `<div class="d-flex justify-content-between mb-2 text-danger">
                                <span>Discount</span>
                                <span>-₱${fmt(txn.discount_total)}</span>
                            </div>` : ''}
                            <div class="d-flex justify-content-between mb-2">
                                <span>Cost</span>
                                <span>₱${fmt(txn.total_cost)}</span>
                            </div>
                            ${parseFloat(txn.total_service_fees || 0) > 0 ? `<div class="d-flex justify-content-between mb-2">
                                <span>Service Fee</span>
                                <span>₱${fmt(txn.total_service_fees)}</span>
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
                            ${txn.vat_amount != null ? `<div class="d-flex justify-content-between text-muted border-top pt-2 mt-2" style="font-size:.78rem">
                                <span>VAT (${esc(txn.vat_type === '12_percent' ? '12%' : txn.vat_type === 'zero_rated' ? 'Zero-rated' : 'Exempt')})</span>
                                <span>₱${fmt(txn.vat_amount)}</span>
                            </div>` : ''}
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small fw-bold text-uppercase mb-1">Payment</label>
                    <div>${paymentsHtml || '<span class="text-muted small">—</span>'}</div>
                    ${parseFloat(txn.amount_paid || 0) > 0 ? `<div class="text-muted small mt-2"><span class="fas fa-check-circle text-success me-1"></span>Paid: ₱${fmt(txn.amount_paid)} | Change: ₱${fmt(txn.change_amount)}</div>` : ''}
                </div>
            </div>
        </div>
        ${itemsHtml ? `<hr class="my-4">
        <div class="mb-3">
            <h6 class="fw-bold text-uppercase text-muted small"><span class="fas fa-list me-2"></span>Order Items (${items.length})</h6>
        </div>
        ${itemsHtml}` : ''}
    `;
}

// ─── Print ────────────────────────────────────────────────────────────────────

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
    if (status && status.value !== 'all') filterParts.push(`Status: ${status.options[status.selectedIndex].text}`);
    if (type && type.value !== 'all') filterParts.push(`Type: ${type.options[type.selectedIndex].text}`);
    if (branch && branch.value) filterParts.push(`Branch: ${branch.options[branch.selectedIndex].text}`);
    if (providerType && providerType.value) filterParts.push(`Provider Type: ${providerType.options[providerType.selectedIndex].text}`);
    if (provider && provider.value) filterParts.push(`Provider: ${provider.options[provider.selectedIndex].text}`);
    if (cashier && cashier.value) filterParts.push(`Cashier: ${cashier.options[cashier.selectedIndex].text}`);
    
    const filterDisplay = filterParts.length > 0 ? filterParts.join(' | ') : 'All records';

    // Get stats from the page
    const statTotal = document.getElementById('statTotal')?.textContent || '0';
    const statRevenue = document.getElementById('statRevenue')?.textContent || '₱0.00';
    const statRefunded = document.getElementById('statRefunded')?.textContent || '₱0.00';
    const statProfit = document.getElementById('statProfit')?.textContent || '₱0.00';

    // Build table HTML for print (only visible/filtered rows)
    let tableHTML = '';
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach((row) => {
        // Only include visible rows (not hidden by filter)
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                // Format cashier name: "Anthony C." (first name + middle initial)
                const cashierName = cells[3].textContent.trim();
                const formattedCashier = formatCashierName(cashierName);
                
                // Extract order code and cancellation badge from cell 0
                const orderCell = cells[0];
                const orderCode = orderCell.querySelector('.fw-semibold')?.textContent.trim() || orderCell.textContent.trim();
                const cancelBadge = orderCell.querySelector('.badge')?.outerHTML || '';
                
                // Extract type, provider, and passenger from cell 2
                const typeCell = cells[1].textContent.trim();
                const detailsCell = cells[2].textContent.trim();
                
                // Try to extract provider and passenger from the details
                // The details cell contains passenger names and provider names
                let providerName = '';
                let passengerName = '';
                let passengerNumber = '';
                
                // Simple parsing - provider is usually shown after passenger
                const lines = detailsCell.split('\n').map(l => l.trim()).filter(l => l);
                if (lines.length > 0) {
                    passengerName = lines[0];
                    if (lines.length > 1) {
                        providerName = lines[1];
                    }
                }
                
                // Get passenger number from data attribute if available
                const rowElement = row;
                if (rowElement.dataset && rowElement.dataset.passengerNumber) {
                    passengerNumber = rowElement.dataset.passengerNumber;
                }
                
                // Extract amount and refund from amount cell (cell 6)
                const amountCell = cells[6];
                const amountMain = amountCell.querySelector('.fw-semibold')?.textContent.trim() || amountCell.textContent.trim();
                const refundText = amountCell.querySelector('.text-danger')?.textContent.trim() || '';
                
                tableHTML += `
                    <tr>
                        <td>
                            <div style="font-weight:600; margin-bottom:2px;">${orderCode}</div>
                            ${cancelBadge ? `<div style="font-size:6.5pt;">${cancelBadge}</div>` : ''}
                        </td>
                        <td>
                            <div style="font-weight:600; margin-bottom:2px;">${typeCell}</div>
                            <div style="font-size:6.5pt; color:#000;">${providerName}</div>
                        </td>
                        <td>
                            <div style="font-weight:600; margin-bottom:2px;">${passengerName}</div>
                            <div style="font-size:6.5pt; color:#000;">${passengerNumber}</div>
                        </td>
                        <td>${formattedCashier}</td>
                        <td>${cells[4].textContent.trim()}</td>
                        <td>${cells[5].textContent.trim()}</td>
                        <td>
                            <div style="font-weight:600; margin-bottom:2px;">${amountMain}</div>
                            ${refundText ? `<div style="font-size:6.5pt; color:#dc3545;">${refundText}</div>` : ''}
                        </td>
                        <td>${cells[7].textContent.trim()}</td>
                    </tr>`;
            }
        }
    });

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

    // Build custom print HTML for POS transactions
    const printHTML = `
<!DOCTYPE html>
<html>
<head>
    <title>POS Transactions Report</title>
    <style>
        @media print {
            @page {
                size: letter;
                margin: 0.5cm;
            }
            body {
                font-family: 'Century Gothic', CenturyGothic, AppleGothic, Arial, sans-serif;
                margin: 0;
                padding: 0;
                font-size: 8pt;
                color: #000;
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
                border: 1px solid #000;
                padding: 3px 5px;
                text-align: left;
            }
            th {
                background: #f0f0f0;
                font-weight: 600;
                text-align: center;
                font-size: 7pt;
                color: #000;
                border-bottom: 1px solid #000;
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
            <h2 style="font-family: Arial, sans-serif; font-weight: normal;">POS TRANSACTIONS REPORT</h2>
            <div class="description">Comprehensive report of all point-of-sale transactions</div>
            <div class="meta">${filterDisplay}</div>
        </div>
    </div>
    <div style="display: flex; justify-content: space-between; gap: 10px; margin: 10px 0; border: 1px solid #000; padding: 8px;">
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">Total Orders</div>
            <div style="font-size: 11pt; font-weight: 700; color: #000;">${statTotal}</div>
        </div>
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">Total Revenue</div>
            <div style="font-size: 11pt; font-weight: 700; color: #000;">${statRevenue}</div>
        </div>
        <div style="flex: 1; text-align: center; border-right: 1px solid #000;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">Total Refunded</div>
            <div style="font-size: 11pt; font-weight: 700; color: #000;">${statRefunded}</div>
        </div>
        <div style="flex: 1; text-align: center;">
            <div style="font-size: 7pt; color: #000; margin-bottom: 2px;">Net Profit</div>
            <div style="font-size: 11pt; font-weight: 700; color: #000;">${statProfit}</div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Type / Provider</th>
                <th>Passenger</th>
                <th>Cashier</th>
                <th>Branch</th>
                <th>Payment</th>
                <th>Amount</th>
                <th>Status</th>
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
            ${window.companyAddress || ''}<br>
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

function exportTransactions() {
    const params = buildParams(1);
    params.limit  = 10000;
    params.page   = 1;
    const url = window.POS_TXN_CONFIG.apiUrl + '?' + new URLSearchParams(params).toString();

    fetch(url)
        .then(r => r.json())
        .then(result => {
            if (!result.success || !result.data.length) {
                alert('No data to export.');
                return;
            }
            const rows  = result.data;
            const header = ['Order Code','Status','Type','Passengers','Provider','Cashier','Branch','Payment','Total Amount','Refunded','Date'];
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
                    txn.provider_names  || '',
                    txn.cashier_full_name || txn.cashier_name || '',
                    txn.branch_name || '',
                    payments,
                    parseFloat(txn.total_amount || 0).toFixed(2),
                    parseFloat(txn.total_refunded_amount || 0).toFixed(2),
                    txn.created_at || ''
                ]);
            });

            const csv     = csvRows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
            const blob    = new Blob([csv], { type: 'text/csv' });
            const link    = document.createElement('a');
            link.href     = URL.createObjectURL(blob);
            link.download = `pos-transactions-${new Date().toISOString().slice(0,10)}.csv`;
            link.click();
        })
        .catch(() => alert('Export failed.'));
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
