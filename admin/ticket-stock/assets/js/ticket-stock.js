const ticketStockPage = window.TICKET_STOCK_PAGE || '';

function ticketStockCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
}

function ticketStockHeaders() {
    const headers = { 'Content-Type': 'application/json' };
    const token = ticketStockCsrf();
    if (token) headers['X-CSRF-TOKEN'] = token;
    return headers;
}

async function ticketStockGetResponse(action, params = {}) {
    const query = new URLSearchParams({ action, ...params, _realtime: Date.now() });
    const response = await fetch(`${window.BASE_URL}/api/ticket-stock?${query.toString()}`, {
        credentials: 'same-origin',
        cache: 'no-store'
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'Ticket stock request failed.');
    return result;
}

async function ticketStockGet(action, params = {}) {
    const result = await ticketStockGetResponse(action, params);
    return result.data;
}

async function ticketStockMutate(payload) {
    const response = await fetch(`${window.BASE_URL}/api/ticket-stock`, {
        method: 'POST',
        headers: ticketStockHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'Ticket stock update failed.');
    return result.data;
}

function ticketStockToast(type, message) {
    document.querySelectorAll('.ticket-stock-toast').forEach(el => el.remove());
    const el = document.createElement('div');
    el.className = `ticket-stock-toast alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = '1100';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function ticketStockEscape(value) {
    const el = document.createElement('div');
    el.textContent = value == null ? '' : String(value);
    return el.innerHTML;
}

function ticketStockQty(value) {
    return Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

function ticketStockMoney(value) {
    return `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function ticketStockFormatLabel(value) {
    return String(value || '').replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, character => character.toUpperCase());
}

function ticketStockFormatDateTime(value) {
    const raw = String(value || '').trim();
    const datePart = raw.slice(0, 10);
    const dateValue = new Date(`${datePart}T00:00:00`);
    const formattedDate = /^\d{4}-\d{2}-\d{2}$/.test(datePart) && !Number.isNaN(dateValue.getTime())
        ? dateValue.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
        : raw || '—';
    const timePart = raw.slice(11, 16);
    let formattedTime = '';
    if (/^\d{2}:\d{2}$/.test(timePart)) {
        const [hours, minutes] = timePart.split(':').map(Number);
        formattedTime = new Date(1970, 0, 1, hours, minutes).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    }
    return { date: formattedDate, time: formattedTime };
}

function ticketStockLocalDate() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${day}`;
}

function ticketStockMovementMeta(type) {
    return {
        OPENING_BALANCE: { color: 'info', icon: 'fa-flag-checkered' },
        POS_SALE: { color: 'success', icon: 'fa-shopping-cart' },
        POS_SALE_REVERSAL: { color: 'warning', icon: 'fa-undo' },
        DISPATCH: { color: 'primary', icon: 'fa-truck' },
        RECEIPT: { color: 'success', icon: 'fa-inbox' },
        ADJUSTMENT: { color: 'secondary', icon: 'fa-sliders-h' },
        DAMAGE_OR_VOID: { color: 'danger', icon: 'fa-ban' }
    }[String(type || '').toUpperCase()] || { color: 'secondary', icon: 'fa-exchange-alt' };
}

function ticketStockShowError(error, selector) {
    const target = document.querySelector(selector);
    if (!target) return;
    const message = ticketStockEscape(error.message || error);
    if (target.tagName === 'TABLE') {
        const tbody = target.querySelector('tbody');
        const colspan = target.querySelectorAll('thead th').length || 1;
        if (tbody) tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-5"><span class="fas fa-exclamation-circle text-danger me-2"></span>${message}</td></tr>`;
        return;
    }
    target.innerHTML = `<div class="alert alert-danger m-3">${message}</div>`;
}

let ticketStockBalanceVersion = null;
let ticketStockBalanceFilterKey = '';
let ticketStockBalanceLoadInFlight = null;
let ticketStockBalanceLoadQueued = false;

function ticketStockBalanceParams() {
    return {
        branch_id: document.getElementById('balanceBranch')?.value || '',
        provider_id: document.getElementById('balanceProvider')?.value || '',
        variant_id: document.getElementById('balanceVariant')?.value || ''
    };
}

function ticketStockBalanceFilterKeyFor(params) {
    return JSON.stringify(params);
}

function renderTicketStockBalances(rows) {
    const tbody = document.querySelector('#ticketStockBalances tbody');
    if (!tbody) return;

    document.getElementById('balanceTotal')?.replaceChildren(document.createTextNode(String(rows.length)));
    document.getElementById('balanceOnHand')?.replaceChildren(document.createTextNode(ticketStockQty(rows.reduce((sum, row) => sum + Number(row.on_hand_qty || 0), 0))));
    document.getElementById('balanceReserved')?.replaceChildren(document.createTextNode(ticketStockQty(rows.reduce((sum, row) => sum + Number(row.reserved_qty || 0), 0))));
    document.getElementById('balanceAvailable')?.replaceChildren(document.createTextNode(ticketStockQty(rows.reduce((sum, row) => sum + Number(row.available_qty || 0), 0))));
    tbody.innerHTML = rows.length ? rows.map(row => `<tr>
        <td>${ticketStockEscape(row.branch_name)}</td>
        <td>${ticketStockEscape(`${row.provider_code || ''} - ${row.provider_name || ''}`)}</td>
        <td>${ticketStockEscape(`${row.variant_code || ''} - ${row.variant_name || ''}`)}</td>
        <td class="text-end">${ticketStockQty(row.on_hand_qty)}</td>
        <td class="text-end">${ticketStockQty(row.reserved_qty)}</td>
        <td class="text-end fw-bold">${ticketStockQty(row.available_qty)}</td>
        <td class="text-end">${ticketStockQty(row.reorder_level)}</td>
        <td>${Number(row.available_qty || 0) <= Number(row.reorder_level || 0) ? '<span class="badge bg-warning text-dark">Reorder</span>' : '<span class="badge bg-success">OK</span>'}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="openStockAdjustment(${row.branch_id}, ${row.provider_id}, ${row.variant_id}, ${row.on_hand_qty})"><span class="fas fa-edit"></span></button></td>
    </tr>`).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No stock rows found.</td></tr>';
}

async function loadTicketStockBalances({ force = true, showLoading = force } = {}) {
    const tbody = document.querySelector('#ticketStockBalances tbody');
    if (!tbody) return;
    if (ticketStockBalanceLoadInFlight) {
        if (force) ticketStockBalanceLoadQueued = true;
        return ticketStockBalanceLoadInFlight;
    }

    const params = ticketStockBalanceParams();
    const filterKey = ticketStockBalanceFilterKeyFor(params);
    if (showLoading) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">Loading...</td></tr>';
    }

    ticketStockBalanceLoadInFlight = (async () => {
        try {
            const result = await ticketStockGetResponse('balances', params);
            const rows = Array.isArray(result.data) ? result.data : [];
            renderTicketStockBalances(rows);
            ticketStockBalanceVersion = result.version || null;
            ticketStockBalanceFilterKey = filterKey;
        } catch (error) {
            if (!ticketStockBalanceVersion) {
                ticketStockShowError(error, '#ticketStockBalances');
            } else {
                console.warn('[Ticket stock realtime] Balance refresh failed:', error);
            }
        } finally {
            ticketStockBalanceLoadInFlight = null;
            if (ticketStockBalanceLoadQueued) {
                ticketStockBalanceLoadQueued = false;
                loadTicketStockBalances({ force: true, showLoading: false });
            }
        }
    })();

    return ticketStockBalanceLoadInFlight;
}

async function refreshTicketStockBalancesIfChanged() {
    const params = ticketStockBalanceParams();
    const filterKey = ticketStockBalanceFilterKeyFor(params);
    if (!ticketStockBalanceVersion) {
        return loadTicketStockBalances({ force: true, showLoading: true });
    }
    if (ticketStockBalanceFilterKey !== filterKey) return;

    const versionData = await ticketStockGet('balances_version', params);
    if (versionData?.version && versionData.version !== ticketStockBalanceVersion) {
        return loadTicketStockBalances({ force: false, showLoading: false });
    }
}

function startTicketStockBalancesRealtime() {
    const realtimeConfig = window.TICKET_STOCK_REALTIME_CONFIG || {};
    const pusherConfig = realtimeConfig.pusher || {};
    const onUpdate = () => refreshTicketStockBalancesIfChanged();
    const canVerifyWhileConnected = Boolean(
        pusherConfig.enabled
        && typeof Pusher !== 'undefined'
        && Array.isArray(pusherConfig.branchIds)
        && pusherConfig.branchIds.length
    );

    if (window.TMSBranchRealtime) {
        window.ticketStockBalancesRealtime = window.TMSBranchRealtime.start({
            config: pusherConfig,
            branchIds: pusherConfig.branchIds,
            events: ['ticket_stock.updated'],
            statusElement: 'ticketStockRealtimeStatus',
            interval: 10000,
            onUpdate
        });

        if (canVerifyWhileConnected && !window.ticketStockBalancesVersionTimer) {
            window.ticketStockBalancesVersionTimer = setInterval(() => {
                if (document.hidden || window.ticketStockBalancesRealtime?.usingFallback) return;
                onUpdate().catch(error => console.warn('[Ticket stock realtime] Version check failed:', error));
            }, 10000);
        }
        return;
    }

    loadTicketStockBalances({ force: true, showLoading: true });
}


function openStockAdjustment(branchId, providerId, variantId, currentQty) {
    document.getElementById('adjustStockBranch').value = branchId;
    document.getElementById('adjustStockProvider').value = providerId;
    document.getElementById('adjustStockVariant').value = variantId;
    document.getElementById('adjustStockCurrentOnHand').value = currentQty;
    document.getElementById('adjustStockCurrent').textContent = ticketStockQty(currentQty);
    document.getElementById('adjustStockNewOnHand').value = currentQty;
    document.getElementById('adjustStockReason').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('stockAdjustmentModal')).show();
}

async function submitStockAdjustment() {
    const currentOnHand = Number(document.getElementById('adjustStockCurrentOnHand').value);
    const newOnHand = Number(document.getElementById('adjustStockNewOnHand').value);
    if (!Number.isFinite(newOnHand)) {
        ticketStockToast('error', 'Please enter a valid new on-hand quantity.');
        return;
    }
    const delta = newOnHand - currentOnHand;
    const payload = {
        action: 'adjust',
        branch_id: document.getElementById('adjustStockBranch').value,
        provider_id: document.getElementById('adjustStockProvider').value,
        variant_id: document.getElementById('adjustStockVariant').value,
        delta: delta,
        reason: document.getElementById('adjustStockReason').value.trim(),
        new_on_hand: newOnHand
    };
    try {
        await ticketStockMutate(payload);
        bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal'))?.hide();
        ticketStockToast('success', 'Stock adjusted successfully.');
        loadTicketStockBalances();
    } catch (error) { ticketStockToast('error', error.message); }
}

function resetTicketStockMovementFilters() {
    const defaultDate = window.TICKET_STOCK_DEFAULT_DATE || ticketStockLocalDate();
    const dateInput = document.getElementById('movementDate');
    if (dateInput) dateInput.value = defaultDate;
    ['movementBranch', 'movementProvider', 'movementType'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });
    const limit = document.getElementById('movementLimit');
    if (limit) limit.value = '50';
    loadTicketStockMovements();
}

async function loadTicketStockMovements() {
    const tbody = document.querySelector('#ticketStockMovements tbody');
    if (!tbody) return;
    const date = document.getElementById('movementDate')?.value || '';
    const limit = document.getElementById('movementLimit')?.value || 50;
    const count = document.getElementById('movementResultCount');
    const summary = document.getElementById('movementResultSummary');
    const footer = document.getElementById('movementResultFooter');
    if (count) count.textContent = '…';
    if (summary) summary.textContent = date ? `Loading movements for ${ticketStockFormatDateTime(`${date} 00:00:00`).date}...` : 'Loading movements across all dates...';
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5"><span class="fas fa-circle-notch fa-spin me-2"></span>Loading movements...</td></tr>';
    try {
        const data = await ticketStockGet('movements', {
            branch_id: document.getElementById('movementBranch')?.value || '',
            provider_id: document.getElementById('movementProvider')?.value || '',
            movement_type: document.getElementById('movementType')?.value || '',
            date_from: date,
            date_to: date,
            limit
        });
        const rows = Array.isArray(data) ? data : [];
        if (count) count.textContent = String(rows.length);
        if (summary) summary.textContent = date
            ? `${rows.length.toLocaleString('en-PH')} movement${rows.length === 1 ? '' : 's'} recorded on ${ticketStockFormatDateTime(`${date} 00:00:00`).date}`
            : `${rows.length.toLocaleString('en-PH')} movement${rows.length === 1 ? '' : 's'} across all dates`;
        if (footer) footer.textContent = `Showing ${rows.length.toLocaleString('en-PH')} movement${rows.length === 1 ? '' : 's'}${rows.length >= Number(limit) ? ` (limited to ${limit})` : ''}`;
        tbody.innerHTML = rows.length ? rows.map(row => {
            const movementType = String(row.movement_type || '').toUpperCase();
            const movementMeta = ticketStockMovementMeta(movementType);
            const timestamp = ticketStockFormatDateTime(row.created_at);
            const delta = Number(row.quantity_delta || 0);
            const deltaColor = delta < 0 ? 'danger' : delta > 0 ? 'success' : 'secondary';
            const deltaPrefix = delta > 0 ? '+' : '';
            const providerCode = row.provider_code ? `<code class="d-block text-muted fs-11">${ticketStockEscape(row.provider_code)}</code>` : '';
            const variantCode = row.variant_code ? `<code class="d-block text-muted fs-11">${ticketStockEscape(row.variant_code)}</code>` : '';
            const reference = row.reference_type
                ? `<code class="ticket-stock-reference">${ticketStockEscape(ticketStockFormatLabel(row.reference_type))}</code>${row.reference_id ? ` <span class="text-muted">#${ticketStockEscape(row.reference_id)}</span>` : ''}`
                : '<span class="text-muted">—</span>';
            const remarks = row.remarks ? ticketStockEscape(row.remarks) : '<span class="text-muted">—</span>';
            const performer = row.performed_by_username || 'System';
            return `<tr>
                <td class="ps-3 text-nowrap">
                    <div class="fw-semibold text-900">${ticketStockEscape(timestamp.date)}</div>
                    ${timestamp.time ? `<div class="text-muted fs-11">${ticketStockEscape(timestamp.time)}</div>` : ''}
                </td>
                <td class="text-nowrap"><span class="badge badge-subtle-${movementMeta.color} ticket-stock-movement-badge"><span class="fas ${movementMeta.icon} me-1"></span>${ticketStockEscape(ticketStockFormatLabel(movementType))}</span></td>
                <td><div class="fw-semibold text-900 text-nowrap">${ticketStockEscape(row.branch_name || '—')}</div><small class="text-muted">Branch</small></td>
                <td><div class="fw-semibold text-900 text-nowrap">${ticketStockEscape(row.provider_name || '—')}</div>${providerCode}</td>
                <td><div class="fw-semibold text-900 text-nowrap">${ticketStockEscape(row.variant_name || '—')}</div>${variantCode}</td>
                <td class="text-end text-nowrap"><span class="badge badge-subtle-${deltaColor} ticket-stock-quantity-badge">${deltaPrefix}${ticketStockQty(delta)}</span></td>
                <td class="text-end text-nowrap"><div class="d-inline-flex align-items-center gap-2"><span class="text-muted">${ticketStockQty(row.balance_before)}</span><span class="fas fa-long-arrow-alt-right text-300"></span><strong class="text-900">${ticketStockQty(row.balance_after)}</strong></div><small class="d-block text-muted">before → after</small></td>
                <td class="text-nowrap">${reference}</td>
                <td class="ticket-stock-remarks" title="${ticketStockEscape(row.remarks || '')}">${remarks}</td>
                <td class="pe-3 text-nowrap"><span class="fas fa-user-circle text-400 me-1"></span>${ticketStockEscape(performer)}</td>
            </tr>`;
        }).join('') : '<tr><td colspan="10" class="text-center text-muted py-5"><span class="fas fa-inbox fs-3 d-block mb-2 text-300"></span>No movements found for the selected filters.</td></tr>';
    } catch (error) {
        if (count) count.textContent = '0';
        if (summary) summary.textContent = 'Unable to load movements.';
        if (footer) footer.textContent = 'No movements loaded';
        ticketStockShowError(error, '#ticketStockMovements');
    }
}

function requestItemVariantSelections(excludeSelect = null) {
    return new Set([...document.querySelectorAll('.request-item-variant')]
        .filter(select => select !== excludeSelect)
        .map(select => select.value)
        .filter(Boolean));
}

function updateRequestVariantOptions(providerId, preferredVariantId = '') {
    [...document.querySelectorAll('.request-item-variant')].forEach(select => {
        const selectedByOtherRows = requestItemVariantSelections(select);
        [...select.options].forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            const providerMismatch = Boolean(providerId) && option.dataset.providerId !== String(providerId);
            option.hidden = providerMismatch || selectedByOtherRows.has(option.value);
        });

        if (preferredVariantId && [...select.options].some(option => option.value === String(preferredVariantId) && !option.hidden)) {
            select.value = String(preferredVariantId);
        } else if (select.selectedOptions[0]?.hidden) {
            select.value = '';
        }

        // If no preferred variant and only one variant is visible, auto-select it
        if (!select.value) {
            const visible = [...select.options].filter(option => option.value && !option.hidden);
            if (visible.length === 1) {
                select.value = visible[0].value;
            }
        }
    });
}

function handleRequestItemVariantChange(select) {
    const value = select?.value || '';
    if (value && requestItemVariantSelections(select).has(value)) {
        select.value = '';
        ticketStockToast('error', 'This variant is already added. Please choose another variant.');
    }
    updateRequestVariantOptions(document.getElementById('createProvider')?.value || '');
}

function syncSourceWalletSelection() {
    const walletSelect = document.getElementById('createSourceWallet');
    const providerSelect = document.getElementById('createProvider');
    const sourceBranchSelect = document.getElementById('createSourceBranch');
    const info = document.getElementById('sourceWalletInfo');
    if (!walletSelect || !providerSelect || !sourceBranchSelect) return;

    const option = walletSelect.options[walletSelect.selectedIndex];
    const walletId = walletSelect.value;
    if (!walletId) {
        providerSelect.disabled = false;
        sourceBranchSelect.disabled = false;
        info?.classList.add('d-none');
        updateRequestVariantOptions(providerSelect.value || '');
        return;
    }

    providerSelect.value = option.dataset.providerId || '';
    sourceBranchSelect.value = option.dataset.branchId || '';
    providerSelect.disabled = true;
    sourceBranchSelect.disabled = true;
    document.getElementById('sourceWalletBalance').textContent = ticketStockMoney(option.dataset.balance || 0);
    document.getElementById('sourceWalletName').textContent = option.dataset.name || '-';
    info?.classList.remove('d-none');
    updateRequestVariantOptions(option.dataset.providerId || '', option.dataset.variantId || '');
}

function limitRequestDestinationBranches() {
    const select = document.getElementById('createDestinationBranch');
    if (!select || !Array.isArray(window.DESTINATION_BRANCH_IDS)) return;
    const allowedBranchIds = new Set(window.DESTINATION_BRANCH_IDS.map(String));
    [...select.options].forEach(option => {
        if (option.value && !allowedBranchIds.has(option.value)) option.remove();
    });
    if (select.value && !allowedBranchIds.has(select.value)) select.value = '';
}

function resetNewStockRequestForm() {
    const walletSelect = document.getElementById('createSourceWallet');
    if (walletSelect) walletSelect.value = '';
    const providerSelect = document.getElementById('createProvider');
    const sourceBranchSelect = document.getElementById('createSourceBranch');
    const destinationBranchSelect = document.getElementById('createDestinationBranch');
    if (providerSelect) {
        providerSelect.value = '';
        providerSelect.removeAttribute('disabled');
    }
    if (sourceBranchSelect) {
        sourceBranchSelect.value = '';
        sourceBranchSelect.removeAttribute('disabled');
    }
    if (destinationBranchSelect) {
        destinationBranchSelect.value = window.DEFAULT_DESTINATION_BRANCH_ID ? String(window.DEFAULT_DESTINATION_BRANCH_ID) : '';
    }
    document.getElementById('sourceWalletInfo')?.classList.add('d-none');
    updateRequestVariantOptions('');
}

async function loadTicketStockRequests() {
    const tbody = document.querySelector('#ticketStockRequests tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4">Loading...</td></tr>';
    try {
        const data = await ticketStockGet('requests', { status: document.getElementById('requestStatus')?.value || '' });
        tbody.innerHTML = data.length ? data.map(row => `<tr>
            <td><strong>${ticketStockEscape(row.request_code)}</strong></td>
            <td>${row.wallet_name ? `<span class="fas fa-wallet text-primary me-1"></span>${ticketStockEscape(row.wallet_name)}` : '<span class="text-muted">External</span>'}</td>
            <td>${ticketStockEscape(row.source_branch_name || 'External')}</td>
            <td>${ticketStockEscape(row.destination_branch_name)}</td>
            <td>${ticketStockEscape(row.provider_name)}</td>
            <td>${ticketStockEscape(row.request_reason)}</td>
            <td>${ticketStockQty(row.requested_qty)} / ${ticketStockQty(row.received_qty)}</td>
            <td><span class="badge bg-${requestStatusColor(row.status)}">${ticketStockEscape(row.status)}</span></td>
            <td>${ticketStockEscape(row.requested_by_username || '')}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="viewStockRequest(${row.stock_request_id})"><span class="fas fa-eye"></span></button></td>
        </tr>`).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No stock requests found.</td></tr>';
    } catch (error) { ticketStockShowError(error, '#ticketStockRequests'); }
}

function requestStatusColor(status) {
    return { DRAFT: 'secondary', SUBMITTED: 'warning text-dark', APPROVED: 'info text-dark', DISPATCHED: 'primary', RECEIVED: 'success', PARTIALLY_RECEIVED: 'warning text-dark', REJECTED: 'danger', DISPUTED: 'danger', CLOSED: 'dark', CANCELLED: 'secondary' }[status] || 'secondary';
}

async function viewStockRequest(requestId) {
    try {
        const request = await ticketStockGet('request', { id: requestId });
        const items = (request.items || []).map(item => `<tr><td>${ticketStockEscape(item.variant_name)}</td><td>${ticketStockQty(item.requested_qty)}</td><td>${ticketStockQty(item.approved_qty)}</td><td>${ticketStockQty(item.dispatched_qty)}</td><td>${ticketStockQty(item.received_qty)}</td></tr>`).join('');
        document.getElementById('stockRequestDetails').innerHTML = `<div class="row g-2 mb-3"><div class="col-md-6"><strong>${ticketStockEscape(request.request_code)}</strong></div><div class="col-md-6 text-md-end"><span class="badge bg-${requestStatusColor(request.status)}">${ticketStockEscape(request.status)}</span></div><div class="col-md-6">Source Wallet: ${ticketStockEscape(request.wallet_name || 'External')}</div><div class="col-md-6">From: ${ticketStockEscape(request.source_branch_name || 'External')}</div><div class="col-md-6">To: ${ticketStockEscape(request.destination_branch_name)}</div><div class="col-md-6">Provider: ${ticketStockEscape(request.provider_name)}</div><div class="col-md-6">Reason: ${ticketStockEscape(request.request_reason)}</div></div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Variant</th><th>Requested</th><th>Approved</th><th>Dispatched</th><th>Received</th></tr></thead><tbody>${items}</tbody></table></div>`;
        document.getElementById('requestActionId').value = request.stock_request_id;
        document.getElementById('requestActionStatus').value = request.status;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('stockRequestModal')).show();
    } catch (error) { ticketStockToast('error', error.message); }
}

async function transitionStockRequest(status) {
    const requestId = document.getElementById('requestActionId').value;
    const reason = document.getElementById('requestActionReason').value.trim();
    try {
        await ticketStockMutate({ action: 'request_transition', stock_request_id: requestId, status, reason });
        bootstrap.Modal.getInstance(document.getElementById('stockRequestModal'))?.hide();
        ticketStockToast('success', 'Stock request updated.');
        loadTicketStockRequests();
    } catch (error) { ticketStockToast('error', error.message); }
}

async function loadTicketStockDiscrepancies() {
    const tbody = document.querySelector('#ticketStockDiscrepancies tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">Loading...</td></tr>';
    try {
        const data = await ticketStockGet('discrepancies', { status: document.getElementById('discrepancyStatus')?.value || '' });
        tbody.innerHTML = data.length ? data.map(row => `<tr><td>${ticketStockEscape(row.created_at)}</td><td>${ticketStockEscape(row.request_code)}</td><td>${ticketStockEscape(row.variant_name || '—')}</td><td>${ticketStockEscape(row.discrepancy_type)}</td><td>${ticketStockQty(row.expected_qty)}</td><td>${ticketStockQty(row.actual_qty)}</td><td><span class="badge bg-${requestStatusColor(row.status)}">${ticketStockEscape(row.status)}</span></td><td class="text-end">${['RESOLVED', 'WRITTEN_OFF'].includes(row.status) ? '' : `<button class="btn btn-sm btn-outline-success" onclick="resolveTicketStockDiscrepancy(${row.discrepancy_id})"><span class="fas fa-check"></span></button>`}</td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No discrepancies found.</td></tr>';
    } catch (error) { ticketStockShowError(error, '#ticketStockDiscrepancies'); }
}

async function resolveTicketStockDiscrepancy(id) {
    const notes = window.prompt('Resolution notes:');
    if (notes === null) return;
    try {
        await ticketStockMutate({ action: 'discrepancy_resolve', discrepancy_id: id, status: 'RESOLVED', notes });
        ticketStockToast('success', 'Discrepancy resolved.');
        loadTicketStockDiscrepancies();
    } catch (error) { ticketStockToast('error', error.message); }
}

function bindTicketStockMovementFilters() {
    ['movementDate', 'movementBranch', 'movementProvider', 'movementType', 'movementLimit'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', loadTicketStockMovements);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    limitRequestDestinationBranches();
    const destinationBranchSelect = document.getElementById('createDestinationBranch');
    if (destinationBranchSelect && window.DEFAULT_DESTINATION_BRANCH_ID) {
        destinationBranchSelect.value = String(window.DEFAULT_DESTINATION_BRANCH_ID);
    }
    document.getElementById('createSourceWallet')?.addEventListener('change', syncSourceWalletSelection);
    document.getElementById('createProvider')?.addEventListener('change', event => updateRequestVariantOptions(event.target.value));
    document.getElementById('createRequestItems')?.addEventListener('change', event => {
        if (event.target.matches('.request-item-variant')) handleRequestItemVariantChange(event.target);
    });
    document.getElementById('createStockRequestModal')?.addEventListener('hidden.bs.modal', resetNewStockRequestForm);
    if (ticketStockPage === 'balances') startTicketStockBalancesRealtime();
    if (ticketStockPage === 'movements') {
        bindTicketStockMovementFilters();
        const movementDate = document.getElementById('movementDate');
        if (movementDate && !movementDate.value) movementDate.value = window.TICKET_STOCK_DEFAULT_DATE || ticketStockLocalDate();
        loadTicketStockMovements();
    }
    if (ticketStockPage === 'requests') loadTicketStockRequests();
    if (ticketStockPage === 'discrepancies') loadTicketStockDiscrepancies();
});
