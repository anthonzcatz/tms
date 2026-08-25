(function () {
    let currentPage = 1;
    let isLoading = false;
    let historyRows = new Map();
    let detailModal = null;

    const events = [
        'pos.transaction.completed',
        'wallet.updated',
        'charge.updated',
        'bank.confirmation.updated',
        'refund.updated'
    ];

    const config = () => window.REFUND_HISTORY_CONFIG || {};

    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('refundHistoryDetailModal');
        if (modalElement && window.bootstrap) detailModal = new bootstrap.Modal(modalElement);

        setupDateRange();
        document.getElementById('applyFilters')?.addEventListener('click', () => loadHistory(1));
        document.getElementById('resetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('printRefundHistory')?.addEventListener('click', printRefundHistory);
        document.getElementById('perPageSelect')?.addEventListener('change', () => loadHistory(1));

        const search = document.getElementById('filterSearch');
        let searchTimer;
        search?.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadHistory(1), 500);
        });
        search?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                clearTimeout(searchTimer);
                loadHistory(1);
            }
        });

        loadHistory(1);
        startRealtime();
    });

    function setupDateRange() {
        const input = document.getElementById('filterDateRange');
        const fromInput = document.getElementById('filterDateFrom');
        const toInput = document.getElementById('filterDateTo');
        const today = new Date();
        const todayStr = formatDateInput(today);

        if (fromInput) fromInput.value = todayStr;
        if (toInput) toInput.value = todayStr;
        if (!input) return;
        if (typeof flatpickr === 'undefined') {
            input.value = todayStr;
            return;
        }

        flatpickr(input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            allowInput: true,
            defaultDate: [today, today],
            onChange: function (selectedDates) {
                fromInput.value = selectedDates[0] ? formatDateInput(selectedDates[0]) : '';
                toInput.value = selectedDates[1]
                    ? formatDateInput(selectedDates[1])
                    : (selectedDates[0] ? formatDateInput(selectedDates[0]) : '');
            }
        });

        fromInput.value = todayStr;
        toInput.value = todayStr;
        input.value = todayStr;
    }

    function formatDateInput(date) {
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }

    function startRealtime() {
        const pageConfig = config();
        const pusherConfig = pageConfig.pusher || {};
        if (!window.TMSBranchRealtime) return;

        window.refundHistoryRealtime = window.TMSBranchRealtime.start({
            config: pusherConfig,
            branchIds: pusherConfig.branchIds,
            events,
            statusElement: 'refundHistoryRealtimeStatus',
            onUpdate: () => loadHistory(currentPage, false)
        });
    }

    async function loadHistory(page, showLoading = true) {
        if (isLoading) return;
        isLoading = true;
        currentPage = page || 1;

        const tbody = document.getElementById('refundHistoryTableBody');
        if (showLoading && tbody) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>';
            setText('tableInfo', 'Loading...');
            setText('paginationInfo', 'Loading...');
        }

        try {
            const params = buildParams(currentPage);
            const response = await fetch(config().apiUrl + '?' + new URLSearchParams(params).toString(), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Unable to load refund history.');

            populateFilters(result.filters || {});
            renderSummary(result.summary || {});
            renderTable(result.data || []);
            renderPagination(result.pagination || {});
        } catch (error) {
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-danger"><span class="fas fa-exclamation-triangle me-2"></span>' + escapeHtml(error.message) + '</td></tr>';
            }
            setText('tableInfo', 'Error loading data');
            setText('paginationInfo', '');
        } finally {
            isLoading = false;
        }
    }

    function buildParams(page) {
        const params = {
            page,
            limit: document.getElementById('perPageSelect')?.value || 15,
            status: document.getElementById('filterStatus')?.value || 'completed',
            search: document.getElementById('filterSearch')?.value || '',
            branch_id: document.getElementById('filterBranch')?.value || '',
            cashier_id: document.getElementById('filterCashier')?.value || '',
            provider_id: document.getElementById('filterProvider')?.value || '',
            date_from: document.getElementById('filterDateFrom')?.value || '',
            date_to: document.getElementById('filterDateTo')?.value || '',
            _realtime: Date.now()
        };
        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null || typeof params[key] === 'undefined') delete params[key];
        });
        return params;
    }

    function populateFilters(filters) {
        populateSelect('filterBranch', filters.branches || [], 'branch_id', 'branch_name', 'All branches');
        populateSelect('filterProvider', filters.providers || [], 'provider_id', 'provider_name', 'All providers');
        populateSelect('filterCashier', (filters.cashiers || []).filter(item => item.user_id), 'user_id', 'cashier_name', 'All cashiers');
    }

    function populateSelect(id, items, valueKey, labelKey, defaultLabel) {
        const select = document.getElementById(id);
        if (!select) return;
        const selected = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(defaultLabel) + '</option>';
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[labelKey] || 'Unassigned';
            select.appendChild(option);
        });
        select.value = selected;
    }

    function renderSummary(summary) {
        setText('summaryCount', Number(summary.refund_count || 0).toLocaleString('en-PH'));
        setText('summaryTotal', formatMoney(summary.total_refund));
        setText('summaryCash', formatMoney(summary.total_cash));
        setText('summaryCharge', formatMoney(summary.total_charge));
    }

    function renderTable(rows) {
        const tbody = document.getElementById('refundHistoryTableBody');
        if (!tbody) return;
        historyRows = new Map();
        rows.forEach(row => historyRows.set(String(row.history_id || ('refund-' + row.refund_id)), row));

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><span class="fas fa-inbox fa-2x d-block mb-2"></span>No refunds found for the selected filters.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const historyId = String(row.history_id || ('refund-' + row.refund_id));
            const route = [row.origin, row.destination].filter(Boolean).join(' → ') || 'Route unavailable';
            return `<tr>
                <td class="ps-3 text-nowrap"><div class="fw-semibold">${escapeHtml(formatDateTime(row.refund_processed_at))}</div><small class="text-muted">${escapeHtml(row.refund_method || 'cash')}</small></td>
                <td><div class="fw-semibold text-nowrap">${escapeHtml(row.transaction_code || '—')}</div><small class="text-muted">Ticket ${escapeHtml(row.ticket_number || '—')}</small></td>
                <td class="text-nowrap"><div>${escapeHtml(formatDateTime(row.original_sale_at))}</div><small class="text-muted">${escapeHtml(row.order_code || 'Order unavailable')}</small></td>
                <td><div class="fw-semibold">${escapeHtml(row.passenger_name || 'Passenger unavailable')}</div><small class="text-muted">${escapeHtml(route)}</small></td>
                <td><div class="fw-semibold">${escapeHtml(row.provider_name || 'Unassigned')}</div><small class="text-muted">${escapeHtml(row.branch_name || 'Branch unavailable')}</small></td>
                <td>${escapeHtml(row.original_cashier_name || 'Unassigned')}</td>
                <td>${escapeHtml(row.refund_processor_name || 'Unassigned')}</td>
                <td class="text-end text-nowrap"><div class="fw-bold text-danger">${formatAmount(row.refund_amount)}</div><small class="text-muted">${escapeHtml(amountBreakdown(row))}</small></td>
                <td>${statusBadge(row.refund_status)}</td>
                <td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-primary" data-history-id="${escapeHtml(historyId)}" title="View refund details"><span class="fas fa-eye"></span><span class="d-none d-xl-inline ms-1">View</span></button></td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-history-id]').forEach(button => {
            button.addEventListener('click', () => openDetails(button.getAttribute('data-history-id')));
        });
    }

    function amountBreakdown(row) {
        const cash = Number(row.cash_amount || 0);
        const charge = Number(row.charge_reversal_amount || 0);
        if (cash > 0 && charge > 0) return 'Cash ' + formatAmount(cash) + ' · Charge ' + formatAmount(charge);
        if (charge > 0) return 'Charge reversal ' + formatAmount(charge);
        if (cash > 0) return 'Cash ' + formatAmount(cash);
        return 'No allocation detail';
    }

    function renderPagination(pagination) {
        const total = Number(pagination.total || 0);
        const from = Number(pagination.from || 0);
        const to = Number(pagination.to || 0);
        const current = Number(pagination.current_page || 1);
        const totalPages = Math.max(1, Number(pagination.total_pages || 1));
        setText('tableInfo', total.toLocaleString('en-PH') + ' refund' + (total === 1 ? '' : 's'));
        setText('paginationInfo', total ? `Showing ${from.toLocaleString('en-PH')}–${to.toLocaleString('en-PH')} of ${total.toLocaleString('en-PH')}` : 'No refunds to display');

        const paginationElement = document.getElementById('pagination');
        if (!paginationElement) return;
        if (totalPages <= 1) {
            paginationElement.innerHTML = '';
            return;
        }

        const firstPage = Math.max(1, Math.min(current - 2, totalPages - 4));
        const lastPage = Math.min(totalPages, firstPage + 4);
        const items = [];
        items.push(pageItem('‹', current - 1, current <= 1, 'Previous page'));
        for (let page = firstPage; page <= lastPage; page += 1) items.push(pageItem(String(page), page, false, 'Page ' + page, page === current));
        items.push(pageItem('›', current + 1, current >= totalPages, 'Next page'));
        paginationElement.innerHTML = items.join('');
        paginationElement.querySelectorAll('[data-page]').forEach(button => {
            button.addEventListener('click', () => loadHistory(Number(button.dataset.page)));
        });
    }

    function pageItem(label, page, disabled, ariaLabel, active = false) {
        return `<li class="page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}"><button type="button" class="page-link" data-page="${page}" aria-label="${escapeHtml(ariaLabel)}"${disabled ? ' disabled' : ''}>${escapeHtml(label)}</button></li>`;
    }

    function openDetails(refundId) {
        const row = historyRows.get(String(refundId));
        if (!row || !detailModal) return;

        setText('detailTransactionCode', [row.transaction_code, row.ticket_number ? 'Ticket ' + row.ticket_number : ''].filter(Boolean).join(' · '));
        document.getElementById('detailTicket').innerHTML = detailRows([
            ['Passenger', row.passenger_name],
            ['Route', [row.origin, row.destination].filter(Boolean).join(' → ')],
            ['Travel date', row.travel_date],
            ['Original sale', formatDateTime(row.original_sale_at)],
            ['Original ticket total', formatMoney(row.original_ticket_amount)],
            ['Original cashier', row.original_cashier_name],
            ['Provider', row.provider_name],
            ['Branch', row.branch_name],
            ['Order', row.order_code],
            ['Ticket status', row.ticket_status]
        ]);
        document.getElementById('detailProcessing').innerHTML = detailRows([
            ['Refund processed', formatDateTime(row.refund_processed_at)],
            ['Refund requested', formatDateTime(row.refund_requested_at || row.cancellation_requested_at)],
            ['Cancellation approved', formatDateTime(row.cancellation_approved_at)],
            ['Processed by', row.refund_processor_name],
            ['Requested by', row.requested_by_name],
            ['Approved by', row.approved_by_name],
            ['Refund method', row.refund_method],
            ['Status', row.refund_status],
            ['Cashier session', row.refund_cashier_session_id || '—']
        ]);
        document.getElementById('detailAmounts').innerHTML = '<div class="row g-3">' + [
            ['Refund amount', row.refund_amount, 'text-danger'],
            ['Gross refund', row.gross_refund_amount, ''],
            ['Cash returned', row.cash_amount, 'text-success'],
            ['Charge reversal', row.charge_reversal_amount, 'text-info'],
            ['Bank refund', row.bank_amount, ''],
            ['Other refund', row.other_amount, '']
        ].map(item => `<div class="col-6 col-md-4"><div class="text-muted small">${escapeHtml(item[0])}</div><div class="fs-6 fw-bold ${item[2]}">${formatMoney(item[1])}</div></div>`).join('') + '</div>';

        const allocations = Array.isArray(row.allocations) ? row.allocations : [];
        document.getElementById('detailAllocations').innerHTML = allocations.length
            ? allocations.map(allocation => `<tr><td>${escapeHtml(allocation.refund_route || '—')}</td><td>${escapeHtml(allocation.method_name || allocation.payment_method_type || '—')}</td><td>${escapeHtml(allocation.bank_name || '—')}</td><td class="text-end">${formatMoney(allocation.amount)}</td><td>${statusBadge(allocation.status, true)}</td></tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-3">No allocation records found.</td></tr>';

        document.getElementById('detailReason').innerHTML = detailRows([
            ['Category', row.reason_category],
            ['Cancellation type', row.cancellation_type],
            ['Reason', row.reason || 'No reason provided']
        ]);
        detailModal.show();
    }

    function detailRows(rows) {
        return rows.map(row => `<div class="detail-row"><span class="text-muted">${escapeHtml(row[0])}</span><strong>${escapeHtml(row[1] === null || typeof row[1] === 'undefined' || row[1] === '' ? '—' : row[1])}</strong></div>`).join('');
    }

    function resetFilters() {
        ['filterBranch', 'filterCashier', 'filterProvider'].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = '';
        });
        const status = document.getElementById('filterStatus');
        if (status) status.value = 'completed';
        const search = document.getElementById('filterSearch');
        if (search) search.value = '';

        const today = new Date();
        const todayStr = formatDateInput(today);
        const dateRange = document.getElementById('filterDateRange');
        if (dateRange?._flatpickr) {
            dateRange._flatpickr.setDate([today, today], true);
        }
        ['filterDateFrom', 'filterDateTo'].forEach(id => setValue(id, todayStr));
        loadHistory(1);
    }

    function statusBadge(status, compact = false) {
        const value = String(status || 'completed').toLowerCase();
        const label = value.charAt(0).toUpperCase() + value.slice(1);
        const classes = {
            completed: 'bg-soft-success text-success',
            processing: 'bg-soft-warning text-warning',
            pending: 'bg-soft-warning text-warning',
            failed: 'bg-soft-danger text-danger'
        };
        return `<span class="badge ${classes[value] || 'bg-soft-secondary text-secondary'}${compact ? ' badge-sm' : ''}">${escapeHtml(label)}</span>`;
    }

    function formatDateTime(value) {
        if (!value) return '—';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return String(value);
        return parsed.toLocaleString('en-PH', {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function formatMoney(value) {
        const amount = Number(value || 0);
        return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatAmount(value) {
        const amount = Number(value || 0);
        return amount === 0 ? '—' : formatMoney(amount);
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    function setValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.value = value;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[character]));
    }

    async function fetchAllRowsForPrint() {
        const rows = [];
        let page = 1;
        let lastResult = null;

        do {
            const params = buildParams(page);
            params.limit = 100;
            const response = await fetch(config().apiUrl + '?' + new URLSearchParams(params).toString(), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Unable to load refund history for printing.');
            lastResult = result;
            rows.push(...(result.data || []));
            const totalPages = Number(result.pagination?.total_pages || 1);
            page += 1;
            if (page > totalPages) break;
        } while (true);

        return { rows, summary: lastResult?.summary || {} };
    }

    function buildPrintTable(rows, totalRefund = 0) {
        const body = rows.length
            ? rows.map(row => {
                const route = [row.origin, row.destination].filter(Boolean).join(' → ') || 'Route unavailable';
                return `<tr>
                    <td>${escapeHtml(formatDateTime(row.refund_processed_at))}<br><small>${escapeHtml(row.refund_method || 'cash')}</small></td>
                    <td>${escapeHtml(row.transaction_code || '—')}<br><small>Ticket ${escapeHtml(row.ticket_number || '—')}</small></td>
                    <td>${escapeHtml(formatDateTime(row.original_sale_at))}<br><small>${escapeHtml(row.order_code || 'Order unavailable')}</small></td>
                    <td>${escapeHtml(row.passenger_name || 'Passenger unavailable')}<br><small>${escapeHtml(route)}</small></td>
                    <td>${escapeHtml(row.provider_name || 'Unassigned')}<br><small>${escapeHtml(row.branch_name || 'Branch unavailable')}</small></td>
                    <td>${escapeHtml(row.original_cashier_name || 'Unassigned')}</td>
                    <td>${escapeHtml(row.refund_processor_name || 'Unassigned')}</td>
                    <td class="amount">${escapeHtml(formatAmount(row.refund_amount))}<br><small>${escapeHtml(amountBreakdown(row))}</small></td>
                    <td>${escapeHtml(statusLabel(row.refund_status))}</td>
                </tr>`;
            }).join('')
            : '<tr><td colspan="9" class="empty">No refunds found for the selected filters.</td></tr>';

        return `<table class="print-table">
            <thead><tr>
                <th>Processed</th><th>Transaction</th><th>Original sale</th><th>Passenger / route</th>
                <th>Provider / branch</th><th>Original cashier</th><th>Processed by</th><th class="amount">Refund amount</th><th>Status</th>
            </tr></thead>
            <tbody>${body}</tbody>
            <tfoot><tr>
                <th colspan="7">Total refunds</th><th class="amount">${escapeHtml(formatMoney(totalRefund))}</th><th></th>
            </tr></tfoot>
        </table>`;
    }

    function statusLabel(status) {
        const value = String(status || 'completed').toLowerCase();
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    async function printRefundHistory() {
        const table = document.getElementById('refundHistoryTable');
        if (!table) return;

        const dateFrom = document.getElementById('filterDateFrom')?.value || '';
        const dateTo = document.getElementById('filterDateTo')?.value || '';
        const status = document.getElementById('filterStatus');
        const statusText = status ? status.options[status.selectedIndex].text : 'All statuses';
        const branch = document.getElementById('filterBranch');
        const branchText = branch && branch.value ? branch.options[branch.selectedIndex].text : 'All branches';
        const cashier = document.getElementById('filterCashier');
        const cashierText = cashier && cashier.value ? cashier.options[cashier.selectedIndex].text : 'All cashiers';
        const provider = document.getElementById('filterProvider');
        const providerText = provider && provider.value ? provider.options[provider.selectedIndex].text : 'All providers';
        const search = document.getElementById('filterSearch')?.value || '';

        const formatNiceDate = (dateStr) => {
            if (!dateStr) return '—';
            const [y, m, d] = dateStr.split('-').map(Number);
            if (!y || !m || !d) return dateStr;
            return new Date(y, m - 1, d).toLocaleDateString('en-PH', {
                month: 'short', day: 'numeric', year: 'numeric'
            });
        };

        const dateLabel = (dateFrom && dateTo)
            ? (dateFrom === dateTo ? formatNiceDate(dateFrom) : `${formatNiceDate(dateFrom)} - ${formatNiceDate(dateTo)}`)
            : (dateFrom ? `From ${formatNiceDate(dateFrom)}` : (dateTo ? `Until ${formatNiceDate(dateTo)}` : 'All dates'));

        let printData;
        try {
            printData = await fetchAllRowsForPrint();
        } catch (error) {
            window.alert(error.message || 'Unable to prepare the refund history for printing.');
            return;
        }

        const summaryData = printData.summary || {};
        const summary = {
            count: Number(summaryData.refund_count || 0).toLocaleString('en-PH'),
            total: formatMoney(summaryData.total_refund),
            cash: formatMoney(summaryData.total_cash),
            charge: formatMoney(summaryData.total_charge)
        };
        const printTable = buildPrintTable(printData.rows || [], summaryData.total_refund);

        const printConfig = config();
        const companyName = printConfig.companyName || window.systemName || 'TMS';
        const companyAddress = printConfig.companyAddress || window.companyAddress || '';
        const companyContact = printConfig.companyContact || '';
        const companyLogo = printConfig.companyLogo || '';
        const reportFooter = printConfig.reportFooter || window.reportFooter || 'System Generated Report';
        const reportTitle = 'Refund History';
        const generatedAt = new Date().toLocaleString('en-PH', {
            month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        const printHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>${escapeHtml(reportTitle)}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: "Century Gothic", "Apple Gothic", "URW Gothic", sans-serif; font-size: 8pt; line-height: 1.2; color: #000; background: #fff; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-sheet { width: 100%; margin: 0; }
        .print-heading { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #999; padding-bottom: 0.5rem; margin-bottom: 0.75rem; }
        .print-brand { display: flex; align-items: flex-start; gap: 0.5rem; }
        .print-logo { width: 58px; height: 58px; object-fit: contain; display: block; }
        .print-company-name { font-size: 10pt; font-weight: 700; }
        .print-company-meta { font-size: 7.5pt; color: #000; margin-top: 0.15rem; }
        .print-heading-text { text-align: right; }
        .print-title { font-size: 13pt; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
        .print-period { font-size: 8.5pt; margin-top: 0.2rem; font-weight: 700; }
        .print-scope { font-size: 7.5pt; margin-top: 0.2rem; }
        .print-section { border: 1px solid #999; margin-bottom: 0.6rem; break-inside: avoid; overflow: hidden; }
        .print-section-title { background: #f0f0f0; border-bottom: 1px solid #999; padding: 0.35rem 0.5rem; font-size: 7pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .print-summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid #999; border-left: 1px solid #999; }
        .print-summary-cell { border-right: 1px solid #999; border-bottom: 1px solid #999; padding: 0.5rem 0.35rem; text-align: center; break-inside: avoid; }
        .print-summary-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem; }
        .print-summary-value { font-size: 10pt; font-weight: 400; }
        .print-table { width: 100%; border-collapse: collapse; border: 0; }
        .print-table th, .print-table td { border: 1px solid #999; padding: 0.25rem 0.35rem; font-size: 7pt; vertical-align: top; text-align: left; }
        .print-table thead th { background: #f0f0f0; font-weight: 700; text-transform: uppercase; }
        .print-table tfoot th { background: #f0f0f0; font-weight: 700; }
        .print-table td { font-weight: normal; }
        .print-table .amount { text-align: right; white-space: nowrap; }
        .print-table .empty { text-align: center; padding: 1rem; }
        .print-table small { font-size: 6.5pt; color: #333; }
        .table-responsive { width: 100%; overflow: visible; }
        .print-footer { margin-top: 0.8rem; border-top: 1px solid #999; padding-top: 0.45rem; font-size: 7pt; color: #000; }
        @media print { .print-footer { position: relative; } }
    </style>
</head>
<body>
    <div class="print-sheet">
        <div class="print-heading">
            <div class="print-brand">
                <img src="${escapeHtml(companyLogo)}" alt="${escapeHtml(companyName)} logo" class="print-logo" onerror="this.style.display='none'">
                <div>
                    <div class="print-company-name">${escapeHtml(companyName)}</div>
                    ${companyAddress ? `<div class="print-company-meta">${escapeHtml(companyAddress).replace(/\r?\n/g, '<br>')}</div>` : ''}
                    ${companyContact ? `<div class="print-company-meta">${escapeHtml(companyContact)}</div>` : ''}
                </div>
            </div>
            <div class="print-heading-text">
                <div class="print-title">${escapeHtml(reportTitle)}</div>
                <div class="print-period">${escapeHtml(dateLabel)}</div>
                <div class="print-scope">Status: ${escapeHtml(statusText)} | Branch: ${escapeHtml(branchText)} | Processed by: ${escapeHtml(cashierText)} | Provider: ${escapeHtml(providerText)}${search ? ` | Search: ${escapeHtml(search)}` : ''}</div>
            </div>
        </div>

        <div class="print-section">
            <div class="print-section-title">Refund Summary</div>
            <div class="print-summary-grid">
                <div class="print-summary-cell"><div class="print-summary-label">Refunds</div><div class="print-summary-value">${escapeHtml(summary.count)}</div></div>
                <div class="print-summary-cell"><div class="print-summary-label">Total Refunded</div><div class="print-summary-value">${escapeHtml(summary.total)}</div></div>
                <div class="print-summary-cell"><div class="print-summary-label">Cash Returned</div><div class="print-summary-value">${escapeHtml(summary.cash)}</div></div>
                <div class="print-summary-cell"><div class="print-summary-label">Charge Reversals</div><div class="print-summary-value">${escapeHtml(summary.charge)}</div></div>
            </div>
        </div>
        
        <div class="print-section">
            <div class="print-section-title">Refund Details</div>
            <div class="table-responsive">
                ${printTable}
            </div>
        </div>

        <div class="print-footer">
            ${escapeHtml(companyName)}<br>
            ${escapeHtml(reportFooter).replace(/\r?\n/g, '<br>')}<br>
            Generated on ${escapeHtml(generatedAt)}<br>
            Refund history based on the selected processed-date and filter criteria.
        </div>
    </div>
</body>
</html>
        `;
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

        iframe.onload = function () {
            setTimeout(function () {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(() => {
                    if (iframe.parentNode) {
                        document.body.removeChild(iframe);
                    }
                }, 1000);
            }, 200);
        };
    }
})();
