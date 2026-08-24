/**
 * Wallet Transactions JavaScript
 * Handles client-side functionality for wallet transaction management
 */

// Global state
let transactionsData = [];
let filteredTransactions = [];
let currentPage = 1;
let perPage = 10;
let addTransactionModal = null;
let viewTransactionModal = null;
let walletManagementModal = null;
let walletsData = [];

function isTicketStockWallet(wallet) {
    return Boolean(wallet && (wallet.variant_id || wallet.variant_id === 0));
}

function formatTicketStock(walletOrData) {
    const quantity = Number(walletOrData?.on_hand_qty || 0);
    const available = Number(walletOrData?.available_qty);
    const suffix = Number.isFinite(available) && available !== quantity
        ? ` (${available} available)`
        : '';
    return `${quantity.toLocaleString('en-PH', { maximumFractionDigits: 0 })} tickets${suffix}`;
}

function formatTicketCount(value) {
    return Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

function openTicketStockBalances(wallet) {
    if (!wallet?.branch_id || !wallet?.provider_id || !wallet?.variant_id) {
        showToast('error', 'Error', 'Ticket stock identity is incomplete.');
        return;
    }
    const query = new URLSearchParams({
        branch_id: wallet.branch_id,
        provider_id: wallet.provider_id,
        variant_id: wallet.variant_id
    });
    window.location.href = `${window.BASE_URL}/admin/ticket-stock/balances?${query.toString()}`;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('DOM loaded, initializing wallet transactions...');
        console.log('BASE_URL:', window.BASE_URL);
        
        initComponents();
        setupEventListeners();
        loadTransactions();
        startTransactionPolling();
        
        console.log('Wallet transactions initialized successfully');
    } catch (error) {
        console.error('Error initializing wallet transactions:', error);
        showToast('error', 'Error', 'Failed to initialize wallet transactions: ' + error.message);
    }
});

// Initialize Bootstrap modals
function initComponents() {
    addTransactionModal = new bootstrap.Modal(document.getElementById('addTransactionModal'));
    viewTransactionModal = new bootstrap.Modal(document.getElementById('viewTransactionModal'));
    walletManagementModal = new bootstrap.Modal(document.getElementById('walletManagementModal'));
}

// Setup event listeners
function setupEventListeners() {
    // Search
    document.getElementById('transactionSearch').addEventListener('input', debounce(filterTransactions, 300));

    // Filters
    document.getElementById('walletFilter').addEventListener('change', function() {
        filterTransactions();
        updateCurrentBalance();
    });
    document.getElementById('operatingProviderFilter').addEventListener('change', filterTransactions);
    document.getElementById('txnTypeFilter').addEventListener('change', filterTransactions);
    document.getElementById('directionFilter').addEventListener('change', filterTransactions);

    // Add Transaction Modal - wallet selection
    const addWalletId = document.getElementById('addWalletId');
    if (addWalletId) {
        addWalletId.addEventListener('change', function() {
            updateAddTransactionBalanceDisplay();
        });
    }

    // Flatpickr date range filter — default to current month
    if (typeof flatpickr !== 'undefined') {
        const today = new Date();
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        flatpickr('#dateFilter', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: [startOfMonth, today],
            onChange: function(selectedDates) {
                filterTransactions();
            }
        });
    } else {
        // No flatpickr: leave date empty so the list is not filtered to a single day
        document.getElementById('dateFilter').value = '';
        document.getElementById('dateFilter').addEventListener('change', filterTransactions);
    }
    
    // Amount input auto-formatting
    const amountInput = document.getElementById('addAmount');
    if (amountInput) {
        let previousValue = '';
        
        amountInput.addEventListener('input', function(e) {
            const currentValue = e.target.value;
            
            // Only format if the value has changed and contains numbers
            if (currentValue !== previousValue && /[0-9]/.test(currentValue)) {
                // Remove commas temporarily to get the raw number
                const rawValue = currentValue.replace(/,/g, '');
                
                // Only format if it's a valid number
                if (!isNaN(rawValue) && rawValue !== '') {
                    const formattedValue = formatNumberWithCommas(rawValue);
                    e.target.value = formattedValue;
                    previousValue = formattedValue;
                } else {
                    previousValue = currentValue;
                }
            } else {
                previousValue = currentValue;
            }
        });
        
        // Format on blur to ensure proper decimal places
        amountInput.addEventListener('blur', function(e) {
            const numericValue = parseFormattedNumber(e.target.value);
            if (numericValue > 0) {
                e.target.value = formatNumberWithCommas(numericValue.toFixed(2));
                previousValue = e.target.value;
            } else {
                e.target.value = '';
                previousValue = '';
            }
        });
    }
}

// Load transactions from API
async function loadTransactions(options = {}) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions`, {
            cache: 'no-store'
        });
        const result = await response.json();
        
        if (result.success) {
            transactionsData = result.data.transactions || [];
            filterTransactions(options.preservePage);
        } else {
            showToast('error', 'Error', result.message || 'Failed to load transactions');
            renderEmptyState();
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        showToast('error', 'Error', 'Failed to load transactions: ' + error.message);
        renderEmptyState();
    }
}

// Real-time polling for the transaction history table
function startTransactionPolling() {
    if (window.transactionPollingInterval) {
        return;
    }

    let isRefreshing = false;

    const refresh = async () => {
        if (document.hidden || isRefreshing) {
            return;
        }

        isRefreshing = true;
        try {
            await loadTransactions({ preservePage: true });
            await updateCurrentBalance();
        } catch (error) {
            console.warn('[Transaction history polling] Refresh failed:', error);
        } finally {
            isRefreshing = false;
        }
    };

    window.transactionPollingInterval = setInterval(refresh, 5000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
}

// Update stats cards from the currently filtered transactions
function updateStats() {
    const monetaryTransactions = filteredTransactions.filter(t => !t.wallet_variant_id);
    const total = monetaryTransactions.length;
    const totalInflow = monetaryTransactions
        .filter(t => t.direction === 'IN')
        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0);
    const totalOutflow = monetaryTransactions
        .filter(t => t.direction === 'OUT')
        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0);
    const netBalance = totalInflow - totalOutflow;

    document.getElementById('totalTransactions').textContent = total;
    document.getElementById('totalInflow').textContent = formatCurrency(totalInflow);
    document.getElementById('totalOutflow').textContent = formatCurrency(totalOutflow);
    // netBalance here is sum of filtered transactions, NOT the real wallet balance
    document.getElementById('netBalance').textContent = formatCurrency(netBalance);
}

// Update Current Balance from actual provider_wallets
async function updateCurrentBalance() {
    const walletId = document.getElementById('walletFilter').value;
    const el = document.getElementById('netBalance');
    if (!walletId) {
        return;
    }
    try {
        const res = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await res.json();
        if (result.success && result.data) {
            el.textContent = isTicketStockWallet(result.data)
                ? formatTicketStock(result.data)
                : formatCurrency(result.data.current_balance ?? result.data.wallet?.current_balance ?? 0);
        }
    } catch (e) {
        console.error('Failed to fetch wallet balance:', e);
    }
}

// Update balance display in add transaction modal
function updateAddTransactionBalanceDisplay() {
    const walletSelect = document.getElementById('addWalletId');
    const selectedOption = walletSelect.options[walletSelect.selectedIndex];
    const alertEl = document.getElementById('currentBalanceAlert');
    const balanceEl = document.getElementById('displayCurrentBalance');
    const balanceLabel = document.getElementById('displayBalanceLabel');
    const walletNameEl = document.getElementById('displayWalletName');
    const balanceHint = document.getElementById('balanceRefreshHint');
    const stockNotice = document.getElementById('stockWalletNotice');

    if (!walletSelect.value) {
        alertEl.style.display = 'none';
        stockNotice?.style.setProperty('display', 'none');
        if (balanceHint) balanceHint.textContent = 'Balance is checked in real-time at submission. Click refresh to get latest balance.';
        ['addTxnType', 'addDirection', 'addAmount'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.disabled = false;
        });
        return;
    }

    const isStockWallet = Boolean(selectedOption.dataset.variantId);
    ['addTxnType', 'addDirection', 'addAmount'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.disabled = isStockWallet;
    });
    const walletName = selectedOption.getAttribute('data-name');
    if (isStockWallet) {
        balanceLabel.textContent = 'Current Ticket Stock:';
        balanceEl.textContent = formatTicketStock({
            on_hand_qty: selectedOption.dataset.onHandQty,
            available_qty: selectedOption.dataset.availableQty
        });
        stockNotice?.style.setProperty('display', 'block');
        if (balanceHint) balanceHint.textContent = 'Ticket stock is managed through the stock ledger, not monetary wallet transactions.';
    } else {
        balanceLabel.textContent = 'Current Balance:';
        balanceEl.textContent = formatCurrency(selectedOption.dataset.balance ?? 0);
        stockNotice?.style.setProperty('display', 'none');
        if (balanceHint) balanceHint.textContent = 'Balance is checked in real-time at submission. Click refresh to get latest balance.';
    }
    walletNameEl.textContent = walletName || '-';
    alertEl.style.display = 'block';
}

// Refresh wallet balance from API (real-time check)
async function refreshWalletBalance() {
    const walletId = document.getElementById('addWalletId').value;
    if (!walletId) {
        showToast('warning', 'Warning', 'Please select a wallet first');
        return;
    }

    const balanceEl = document.getElementById('displayCurrentBalance');
    const refreshBtn = document.querySelector('#currentBalanceAlert button');

    // Show loading state
    balanceEl.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';
    if (refreshBtn) refreshBtn.disabled = true;

    try {
        const res = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await res.json();
        if (result.success && result.data) {
            const walletSelect = document.getElementById('addWalletId');
            const selectedOption = walletSelect.options[walletSelect.selectedIndex];
            if (isTicketStockWallet(result.data)) {
                balanceEl.textContent = formatTicketStock(result.data);
                if (selectedOption) {
                    selectedOption.dataset.onHandQty = result.data.on_hand_qty ?? 0;
                    selectedOption.dataset.availableQty = result.data.available_qty ?? 0;
                }
            } else {
                const newBalance = result.data.current_balance ?? 0;
                balanceEl.textContent = formatCurrency(newBalance);
                if (selectedOption) selectedOption.setAttribute('data-balance', newBalance);
            }

            updateAddTransactionBalanceDisplay();
            showToast('success', 'Success', 'Balance refreshed successfully');
        } else {
            showToast('error', 'Error', 'Failed to refresh balance');
            // Revert to original display
            updateAddTransactionBalanceDisplay();
        }
    } catch (e) {
        console.error('Failed to refresh wallet balance:', e);
        showToast('error', 'Error', 'Failed to refresh balance: ' + e.message);
        // Revert to original display
        updateAddTransactionBalanceDisplay();
    } finally {
        if (refreshBtn) refreshBtn.disabled = false;
    }
}

// Filter transactions
function filterTransactions(preservePage = false) {
    const search = document.getElementById('transactionSearch').value.toLowerCase();
    const walletId = document.getElementById('walletFilter').value;
    const operatingProviderId = document.getElementById('operatingProviderFilter').value;
    const txnType = document.getElementById('txnTypeFilter').value;
    const direction = document.getElementById('directionFilter').value;
    const dateFilterEl = document.getElementById('dateFilter');
    const dateFilterVal = dateFilterEl._flatpickr ? dateFilterEl._flatpickr.selectedDates : null;
    
    filteredTransactions = transactionsData.filter(txn => {
        // Search
        const matchesSearch = !search || 
            txn.txn_code?.toLowerCase().includes(search) ||
            txn.wallet_name?.toLowerCase().includes(search) ||
            txn.wallet_provider_name?.toLowerCase().includes(search) ||
            txn.operating_provider_name?.toLowerCase().includes(search) ||
            txn.remarks?.toLowerCase().includes(search) ||
            txn.ticket_txn_code?.toLowerCase().includes(search) ||
            txn.passenger_name?.toLowerCase().includes(search) ||
            txn.variant_code?.toLowerCase().includes(search) ||
            txn.variant_name?.toLowerCase().includes(search) ||
            txn.origin?.toLowerCase().includes(search) ||
            txn.destination?.toLowerCase().includes(search);
        
        // Wallet filter
        const matchesWallet = !walletId || txn.wallet_id == walletId;
        
        // Operating provider filter
        const matchesOperatingProvider = !operatingProviderId || txn.operating_provider_id == operatingProviderId;

        // Transaction type filter
        const matchesTxnType = !txnType || txn.txn_type === txnType;
        
        // Direction filter
        const matchesDirection = !direction || txn.direction === direction;
        
        // Date range filter (flatpickr range or plain date)
        let matchesDate = true;
        if (dateFilterVal && dateFilterVal.length >= 1) {
            const txnDate = new Date(txn.created_at);
            txnDate.setHours(0,0,0,0);
            const from = new Date(dateFilterVal[0]); from.setHours(0,0,0,0);
            if (dateFilterVal.length >= 2) {
                const to = new Date(dateFilterVal[1]); to.setHours(23,59,59,999);
                matchesDate = txnDate >= from && txnDate <= to;
            } else {
                matchesDate = txnDate.getTime() === from.getTime();
            }
        } else if (!dateFilterVal && dateFilterEl.value) {
            const txnDate = new Date(txn.created_at).toISOString().split('T')[0];
            matchesDate = txnDate === dateFilterEl.value;
        }
        
        return matchesSearch && matchesWallet && matchesOperatingProvider && matchesTxnType && matchesDirection && matchesDate;
    });
    
    const totalPages = Math.ceil(filteredTransactions.length / perPage);
    currentPage = preservePage ? Math.min(currentPage, Math.max(1, totalPages)) : 1;
    updateStats();
    renderTransactions();
}

// Render transactions table
function renderTransactions() {
    const tbody = document.getElementById('transactionsTableBody');
    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    const pageData = filteredTransactions.slice(start, end);
    
    if (pageData.length === 0) {
        renderEmptyState();
        updatePagination(0, 0, 0);
        return;
    }
    
    tbody.innerHTML = pageData.map(txn => `
        <tr>
            <td>
                <span class="fw-medium small">${txn.txn_code || '-'}</span>
            </td>
            <td>
                <div class="fw-medium">${txn.operating_provider_name || txn.wallet_provider_name || '-'}</div>
            </td>
            <td>
                ${txn.variant_name ? `
                <div class="d-flex align-items-center">
                    <span class="d-inline-block rounded me-2" style="width:12px;height:12px;background:${txn.variant_color || '#0d6efd'};border:1px solid #dee2e6;"></span>
                    <span class="fw-medium small">${txn.variant_name}</span>
                </div>
                ${txn.variant_code ? `<small class="text-muted d-block ms-4">${txn.variant_code}</small>` : ''}
                ` : '<span class="text-muted">-</span>'}
            </td>
            <td>
                <div class="fw-medium small">${txn.wallet_provider_name || '-'}</div>
                <small class="text-muted">${txn.wallet_name || '-'}</small>
            </td>
            <td>
                <span class="txn-type-badge txn-type-${txn.txn_type}">${txn.txn_type}</span>
            </td>
            <td>
                <span class="direction-badge direction-${txn.direction}">${txn.direction}</span>
            </td>
            <td class="${txn.direction === 'IN' ? 'amount-in' : 'amount-out'}">
                ${txn.direction === 'OUT' ? '-' : ''}${txn.wallet_variant_id ? formatTicketCount(txn.amount) + ' tickets' : formatCurrency(txn.amount)}
            </td>
            <td class="${txn.balance_after >= 0 ? 'balance-positive' : 'balance-negative'}">
                ${txn.wallet_variant_id ? formatTicketCount(txn.balance_after) + ' tickets' : formatCurrency(txn.balance_after)}
            </td>
            <td>
                <small>${formatDateTime(txn.created_at)}</small>
            </td>
            <td>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewTransaction(${txn.wallet_txn_id})">
                        <span class="fas fa-eye"></span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    updatePagination(start + 1, Math.min(end, filteredTransactions.length), filteredTransactions.length);
}

// Render empty state
function renderEmptyState() {
    const tbody = document.getElementById('transactionsTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="10">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="fas fa-receipt"></span>
                    </div>
                    <div class="empty-state-text">No transactions found</div>
                    <div class="empty-state-subtext">Try adjusting your filters or add a new transaction</div>
                </div>
            </td>
        </tr>
    `;
}

// Update pagination
function updatePagination(start, end, total) {
    document.getElementById('showingStart').textContent = start;
    document.getElementById('showingEnd').textContent = end;
    document.getElementById('totalRecords').textContent = total;
    
    const totalPages = Math.ceil(total / perPage);
    const pagination = document.getElementById('pagination');
    
    let html = '';
    
    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Previous</a>
        </li>
    `;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Next</a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

// Change page
function changePage(page) {
    const totalPages = Math.ceil(filteredTransactions.length / perPage);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderTransactions();
}

// Reset filters
function resetFilters() {
    document.getElementById('transactionSearch').value = '';
    document.getElementById('walletFilter').value = '';
    document.getElementById('operatingProviderFilter').value = '';
    document.getElementById('txnTypeFilter').value = '';
    document.getElementById('directionFilter').value = '';
    const dateEl = document.getElementById('dateFilter');
    // Reset date to current month instead of a single day
    const today = new Date();
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    if (dateEl._flatpickr) {
        dateEl._flatpickr.setDate([startOfMonth, today]);
    } else {
        dateEl.value = '';
    }
    filterTransactions();
}

// Open add transaction modal
function openAddTransactionModal() {
    document.getElementById('addTransactionForm').reset();
    // Hide balance display initially
    document.getElementById('currentBalanceAlert').style.display = 'none';
    document.getElementById('stockWalletNotice')?.style.setProperty('display', 'none');
    ['addTxnType', 'addDirection', 'addAmount'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.disabled = false;
    });
    const balanceLabel = document.getElementById('displayBalanceLabel');
    if (balanceLabel) balanceLabel.textContent = 'Current Balance:';
    const balanceHint = document.getElementById('balanceRefreshHint');
    if (balanceHint) balanceHint.textContent = 'Balance is checked in real-time at submission. Click refresh to get latest balance.';
    addTransactionModal.show();
}

function getTransactionDirectionMessage(txnType, direction) {
    const labels = {
        TOPUP: 'Top-up',
        SALE: 'Sale',
        REFUND: 'Refund'
    };

    if (['SALE', 'REFUND'].includes(txnType)) {
        return `${labels[txnType]} transactions cannot use IN or OUT directions.`;
    }

    const expectedDirection = {
        TOPUP: 'IN'
    }[txnType];

    if (!expectedDirection || expectedDirection === direction) {
        return null;
    }

    return `${labels[txnType]} transactions cannot use the ${direction} direction.`;
}

// Save transaction
async function saveTransaction(walletId = null, txnType = null, direction = null, amount = null, remarks = null) {
    // If parameters not provided, get from form
    const formWalletId = walletId || document.getElementById('addWalletId').value;
    const formTxnType = txnType || document.getElementById('addTxnType').value;
    const formDirection = direction || document.getElementById('addDirection').value;
    const formAmountValue = amount || document.getElementById('addAmount').value;
    const formRemarks = remarks || document.getElementById('addRemarks').value;
    const walletSelect = document.getElementById('addWalletId');
    const selectedWalletOption = walletSelect?.options[walletSelect.selectedIndex];

    if (selectedWalletOption?.dataset.variantId) {
        showToast('error', 'Ticket Stock Wallet', 'Use Ticket Stock Balances or Stock Requests to top up ticket quantities.');
        return;
    }

    // Parse formatted amount (remove commas and convert to number)
    const formAmount = parseFormattedNumber(formAmountValue);
    
    if (!formWalletId || !formTxnType || !formDirection || !formAmount) {
        showToast('warning', 'Warning', 'Please fill in all required fields');
        return;
    }

    const directionMessage = getTransactionDirectionMessage(formTxnType, formDirection);
    if (directionMessage) {
        showToast('error', 'Not Allowed', directionMessage);
        return;
    }
    
    try {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                          document.querySelector('input[name="_token"]')?.value;
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        // Add CSRF token to headers if available
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                wallet_id: formWalletId,
                txn_type: formTxnType,
                direction: formDirection,
                amount: parseFloat(formAmount),
                remarks: formRemarks
            })
        });
        
        const result = await response.json();

        // Refresh CSRF token if returned by server
        if (result.csrf_token) {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) csrfMeta.setAttribute('content', result.csrf_token);
        }

        if (result.success) {
            showToast('success', 'Success', 'Transaction added successfully');
            if (!walletId) {
                addTransactionModal.hide();
            }
            await loadTransactions();
            updateCurrentBalance();
            if (walletManagementModal._isShown) {
                loadWallets();
            }
        } else {
            const serverMessage = result.error || result.message || 'Failed to add transaction';
            const isDirectionNotAllowed = /^(TOPUP|SALE|REFUND) must use the (IN|OUT) direction\.$/i.test(serverMessage);
            const message = directionMessage || serverMessage;
            const title = directionMessage || isDirectionNotAllowed ? 'Not Allowed' : 'Error';
            showToast('error', title, message);
        }
    } catch (error) {
        console.error('Error saving transaction:', error);
        showToast('error', 'Error', 'Failed to add transaction: ' + error.message);
    }
}

// View transaction details
async function viewTransaction(txnId) {
    try {
        const encodedTxnId = IdEncoder.encode(txnId);
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions?id=${encodedTxnId}`);
        const result = await response.json();
        
        if (result.success) {
            const txn = result.data;
            const details = document.getElementById('transactionDetails');
            
            // Build ticket details section if this txn references a ticket_transaction
            const hasTicket = txn.reference_table === 'ticket_transactions' && txn.ticket_txn_code;
            const ticketSection = hasTicket ? `
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="fw-bold text-primary mb-0"><span class="fas fa-ticket-alt me-2"></span>Linked Ticket Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Ticket Code</label>
                                <div class="fw-semibold">${txn.ticket_txn_code}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Ticket Status</label>
                                <div><span class="badge ${txn.ticket_status === 'cancelled' ? 'bg-danger' : txn.ticket_status === 'refunded' ? 'bg-warning' : 'bg-success'}">${txn.ticket_status || '-'}</span></div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Variant</label>
                                <div>
                                    ${txn.variant_name ? `<span class="d-inline-block rounded me-1" style="width:14px;height:14px;background:${txn.variant_color || '#0d6efd'};border:1px solid #dee2e6;"></span><span class="fw-semibold">${txn.variant_name}</span>${txn.variant_code ? ` <small class="text-muted">(${txn.variant_code})</small>` : ''}` : '-'}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Passenger</label>
                                <div>${txn.passenger_name || '-'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Travel Date</label>
                                <div>${txn.travel_date ? new Date(txn.travel_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) : '-'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Route</label>
                                <div>${txn.origin && txn.destination ? txn.origin + ' → ' + txn.destination : '-'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Original Amount</label>
                                <div class="fw-semibold">${txn.ticket_total_amount ? formatCurrency(txn.ticket_total_amount) : '-'}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold text-muted small mb-1">Base Amount</label>
                                <div>${txn.base_amount ? formatCurrency(txn.base_amount) : '-'}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold text-muted small mb-1">Service Fee</label>
                                <div>${txn.service_fee ? formatCurrency(txn.service_fee) : '-'}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold text-muted small mb-1">Discount</label>
                                <div>${txn.discount_amount ? formatCurrency(txn.discount_amount) : '-'}</div>
                            </div>
                        </div>
                    </div>
                </div>` : '';

            // Format amount with sign
            const isStockTxn = Boolean(txn.wallet_variant_id);
            const amountPrefix = txn.direction === 'IN' ? '+' : '-';
            const amountDisplay = isStockTxn
                ? `${amountPrefix}${formatTicketCount(txn.amount)} tickets`
                : `${amountPrefix}${formatCurrency(txn.amount)}`;
            const amountClass = txn.direction === 'IN' ? 'text-success' : 'text-danger';

            details.innerHTML = `
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3 px-3">
                        <!-- Transaction Code -->
                        <div class="mb-3 pb-3 border-bottom">
                            <label class="fw-bold text-muted small mb-1 d-block">Transaction Code</label>
                            <div class="txn-code">${txn.txn_code || '-'}</div>
                        </div>

                        <!-- Type / Direction / Date -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Type / Direction</label>
                                <div>
                                    <span class="badge bg-primary me-1">${txn.txn_type}</span>
                                    <span class="badge ${txn.direction === 'IN' ? 'bg-success' : 'bg-danger'}">${txn.direction === 'IN' ? '↓ IN' : '↑ OUT'}</span>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <label class="fw-bold text-muted small mb-1 d-block">Date / Time</label>
                                <div class="small"><span class="fas fa-clock me-1 text-muted"></span>${formatDateTime(txn.created_at)}</div>
                            </div>
                        </div>

                        <!-- Wallet and Amount -->
                        <div class="row g-3 mb-3 align-items-end">
                            <div class="col-md-6">
                                <label class="fw-bold text-muted small mb-1">Wallet</label>
                                <div class="fw-semibold">${txn.wallet_name || '-'}</div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <label class="fw-bold text-muted small mb-1 d-block">Amount</label>
                                <div class="txn-amount ${amountClass}">
                                    ${amountDisplay}
                                </div>
                            </div>
                        </div>

                        <!-- Balance info -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center">
                                    <div class="text-muted small mb-1">Balance Before</div>
                                    <div class="fw-semibold">${isStockTxn ? formatTicketCount(txn.balance_before) + ' tickets' : formatCurrency(txn.balance_before)}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center">
                                    <div class="text-muted small mb-1">Balance After</div>
                                    <div class="fw-semibold ${txn.balance_after >= 0 ? 'text-success' : 'text-danger'}">${isStockTxn ? formatTicketCount(txn.balance_after) + ' tickets' : formatCurrency(txn.balance_after)}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Processed By -->
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="fw-bold text-muted small mb-1">Processed By</label>
                                <div class="small"><span class="fas fa-user me-1 text-muted"></span>${txn.created_by_full_name || txn.created_by_username || 'System'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light py-2 px-3">
                        <h6 class="fw-bold text-muted small mb-0">Remarks</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="small">${txn.remarks || '<span class="text-muted">No remarks</span>'}</div>
                    </div>
                </div>
                ${ticketSection}
            `;
            
            viewTransactionModal.show();
        } else {
            showToast('error', 'Error', result.message || 'Failed to load transaction details');
        }
    } catch (error) {
        console.error('Error loading transaction details:', error);
        showToast('error', 'Error', 'Failed to load transaction details: ' + error.message);
    }
}

// Export transactions
function exportTransactions() {
    if (!filteredTransactions.length) {
        showToast('warning', 'Warning', 'No transactions to export');
        return;
    }

    const header = ['Transaction Code', 'Sub-provider', 'Variant', 'Main Provider', 'Wallet', 'Type', 'Direction', 'Amount', 'Balance After', 'Remarks', 'Date'];
    const rows = filteredTransactions.map(txn => [
        txn.txn_code || '',
        txn.operating_provider_name || txn.wallet_provider_name || '',
        txn.variant_name || '',
        txn.wallet_provider_name || '',
        txn.wallet_name || '',
        txn.txn_type || '',
        txn.direction || '',
        Number(txn.amount || 0).toFixed(2),
        Number(txn.balance_after || 0).toFixed(2),
        txn.remarks || '',
        txn.created_at || ''
    ]);
    const csv = [header, ...rows]
        .map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(','))
        .join('\n');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    link.download = `wallet-transactions-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

// Open wallet management modal
function openWalletManagementModal() {
    loadWallets();
    walletManagementModal.show();
}

// Load wallets for management
async function loadWallets() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets`);
        const result = await response.json();
        
        if (result.success) {
            walletsData = result.data.wallets || [];
            renderWalletsTable();
        } else {
            showToast('error', 'Error', result.message || 'Failed to load wallets');
            renderEmptyWalletsState();
        }
    } catch (error) {
        console.error('Error loading wallets:', error);
        showToast('error', 'Error', 'Failed to load wallets: ' + error.message);
        renderEmptyWalletsState();
    }
}

// Render wallets table
function renderWalletsTable() {
    const tbody = document.getElementById('walletsTableBody');
    
    if (walletsData.length === 0) {
        renderEmptyWalletsState();
        return;
    }
    
    tbody.innerHTML = walletsData.map(wallet => `
        <tr>
            <td><strong>${wallet.wallet_name || '-'}</strong></td>
            <td>${wallet.provider_name || '-'}</td>
            <td>
                ${wallet.variant_name ? `
                <div class="d-flex align-items-center">
                    <span class="d-inline-block rounded me-2" style="width:12px;height:12px;background:${wallet.variant_color || '#0d6efd'};border:1px solid #dee2e6;"></span>
                    <span class="small">${wallet.variant_name}</span>
                </div>
                ${wallet.variant_code ? `<small class="text-muted d-block ms-4">${wallet.variant_code}</small>` : ''}
                ` : '<span class="text-muted">-</span>'}
            </td>
            <td>${wallet.branch_name || '-'}</td>
            <td class="${isTicketStockWallet(wallet) ? (Number(wallet.on_hand_qty || 0) >= 0 ? 'balance-positive' : 'balance-negative') : (wallet.current_balance >= 0 ? 'balance-positive' : 'balance-negative')}" title="${isTicketStockWallet(wallet) ? 'Ticket stock quantity' : 'Monetary wallet balance'}">
                ${isTicketStockWallet(wallet) ? formatTicketStock(wallet) : formatCurrency(wallet.current_balance)}
            </td>
            <td>
                <span class="badge ${wallet.status === 'active' ? 'bg-success' : 'bg-danger'}">
                    ${wallet.status}
                </span>
            </td>
            <td>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewWalletTransactions(${wallet.wallet_id})" title="View Transactions">
                        <span class="fas fa-eye"></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="adjustWalletBalance(${wallet.wallet_id})" title="${isTicketStockWallet(wallet) ? 'Manage Ticket Stock' : 'Adjust Balance'}">
                        <span class="fas ${isTicketStockWallet(wallet) ? 'fa-boxes' : 'fa-wallet'}"></span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Render empty wallets state
function renderEmptyWalletsState() {
    const tbody = document.getElementById('walletsTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="fas fa-wallet"></span>
                    </div>
                    <div class="empty-state-text">No wallets found</div>
                    <div class="empty-state-subtext">Create a wallet to get started</div>
                </div>
            </td>
        </tr>
    `;
}

// View transactions for specific wallet
function viewWalletTransactions(walletId) {
    // Set the wallet filter and trigger filter
    const walletFilter = document.getElementById('walletFilter');
    if (walletFilter) {
        walletFilter.value = walletId;
        filterTransactions();
    }
    // Close the wallet management modal
    walletManagementModal.hide();
    showToast('success', 'Success', 'Filtering transactions for selected wallet');
}

// Adjust wallet balance
function adjustWalletBalance(walletId) {
    const wallet = walletsData.find(w => w.wallet_id === walletId);
    if (!wallet) {
        showToast('error', 'Error', 'Wallet not found');
        return;
    }

    if (isTicketStockWallet(wallet)) {
        openTicketStockBalances(wallet);
        return;
    }

    // Close wallet management modal first
    walletManagementModal.hide();

    // Open add transaction modal with pre-filled wallet
    openAddTransactionModal();

    // Pre-fill the form
    const addWalletId = document.getElementById('addWalletId');
    addWalletId.value = walletId;
    document.getElementById('addTxnType').value = 'ADJUSTMENT';
    document.getElementById('addDirection').value = '';
    document.getElementById('addAmount').value = '';

    // Use the add transaction dropdown display name if available
    const selectedOption = addWalletId.options[addWalletId.selectedIndex];
    const displayWalletName = selectedOption?.getAttribute('data-name') || wallet.wallet_name || '-';
    document.getElementById('addRemarks').value = `Balance adjustment for ${displayWalletName}`;

    // Update the balance display using the wallet data we already have
    const alertEl = document.getElementById('currentBalanceAlert');
    const balanceEl = document.getElementById('displayCurrentBalance');
    const walletNameEl = document.getElementById('displayWalletName');
    balanceEl.textContent = formatCurrency(wallet.current_balance ?? 0);
    walletNameEl.textContent = displayWalletName;
    alertEl.style.display = 'block';

    showToast('info', 'Info', 'Please select direction and enter adjustment amount');
}

// Utility functions
function formatCurrency(amount) {
    return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format number with commas for display (for input fields)
function formatNumberWithCommas(value) {
    if (!value) return '';
    
    // Remove non-numeric characters except decimal point
    const numericValue = value.replace(/[^0-9.]/g, '');
    
    if (!numericValue) return '';
    
    // Split into integer and decimal parts
    const parts = numericValue.split('.');
    let integerPart = parts[0];
    const decimalPart = parts.length > 1 ? '.' + parts[1] : '';

    // Add commas to integer part
    if (integerPart) {
        // Handle leading zeros
        integerPart = integerPart.replace(/^0+/, '') || '0';
        integerPart = parseInt(integerPart).toLocaleString('en-US');
    }

    return integerPart + decimalPart;
}

// Parse formatted number back to plain number
function parseFormattedNumber(formattedValue) {
    // Remove commas and convert to number
    return parseFloat(formattedValue.replace(/,/g, '')) || 0;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Toast notification
function showToast(type, title, message) {
    // Check if toast container exists
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 
                    type === 'error' ? 'bg-danger' : 
                    type === 'warning' ? 'bg-warning' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong>: ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}
