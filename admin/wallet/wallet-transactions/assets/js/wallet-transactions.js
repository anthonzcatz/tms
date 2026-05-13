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

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('DOM loaded, initializing wallet transactions...');
        console.log('BASE_URL:', window.BASE_URL);
        
        initComponents();
        loadTransactions();
        setupEventListeners();
        
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
    document.getElementById('walletFilter').addEventListener('change', filterTransactions);
    document.getElementById('txnTypeFilter').addEventListener('change', filterTransactions);
    document.getElementById('directionFilter').addEventListener('change', filterTransactions);
    document.getElementById('dateFilter').addEventListener('change', filterTransactions);
    
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
async function loadTransactions() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions`);
        const result = await response.json();
        
        if (result.success) {
            transactionsData = result.data.transactions || [];
            updateStats(result.data.stats);
            filterTransactions();
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

// Update stats cards
function updateStats(stats) {
    if (!stats) return;
    
    document.getElementById('totalTransactions').textContent = stats.total || 0;
    document.getElementById('totalInflow').textContent = formatCurrency(stats.totalInflow || 0);
    document.getElementById('totalOutflow').textContent = formatCurrency(stats.totalOutflow || 0);
    document.getElementById('netBalance').textContent = formatCurrency(stats.netBalance || 0);
}

// Filter transactions
function filterTransactions() {
    const search = document.getElementById('transactionSearch').value.toLowerCase();
    const walletId = document.getElementById('walletFilter').value;
    const txnType = document.getElementById('txnTypeFilter').value;
    const direction = document.getElementById('directionFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    
    filteredTransactions = transactionsData.filter(txn => {
        // Search
        const matchesSearch = !search || 
            txn.txn_code?.toLowerCase().includes(search) ||
            txn.wallet_name?.toLowerCase().includes(search) ||
            txn.remarks?.toLowerCase().includes(search);
        
        // Wallet filter
        const matchesWallet = !walletId || txn.wallet_id == walletId;
        
        // Transaction type filter
        const matchesTxnType = !txnType || txn.txn_type === txnType;
        
        // Direction filter
        const matchesDirection = !direction || txn.direction === direction;
        
        // Date filter
        let matchesDate = true;
        if (dateFilter) {
            const txnDate = new Date(txn.created_at).toISOString().split('T')[0];
            matchesDate = txnDate === dateFilter;
        }
        
        return matchesSearch && matchesWallet && matchesTxnType && matchesDirection && matchesDate;
    });
    
    currentPage = 1;
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
                <span class="fw-bold">${txn.txn_code || '-'}</span>
            </td>
            <td>
                <div class="fw-bold">${txn.wallet_name || '-'}</div>
                <small class="text-muted">${txn.provider_name || ''}</small>
            </td>
            <td>
                <span class="txn-type-badge txn-type-${txn.txn_type}">${txn.txn_type}</span>
            </td>
            <td>
                <span class="direction-badge direction-${txn.direction}">${txn.direction}</span>
            </td>
            <td class="${txn.direction === 'IN' ? 'amount-in' : 'amount-out'}">
                ${txn.direction === 'OUT' ? '-' : ''}${formatCurrency(txn.amount)}
            </td>
            <td class="${txn.balance_after >= 0 ? 'balance-positive' : 'balance-negative'}">
                ${formatCurrency(txn.balance_after)}
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
            <td colspan="8">
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
    document.getElementById('txnTypeFilter').value = '';
    document.getElementById('directionFilter').value = '';
    document.getElementById('dateFilter').value = '';
    
    filterTransactions();
}

// Open add transaction modal
function openAddTransactionModal() {
    document.getElementById('addTransactionForm').reset();
    addTransactionModal.show();
}

// Save transaction
async function saveTransaction(walletId = null, txnType = null, direction = null, amount = null, remarks = null) {
    // If parameters not provided, get from form
    const formWalletId = walletId || document.getElementById('addWalletId').value;
    const formTxnType = txnType || document.getElementById('addTxnType').value;
    const formDirection = direction || document.getElementById('addDirection').value;
    const formAmountValue = amount || document.getElementById('addAmount').value;
    const formRemarks = remarks || document.getElementById('addRemarks').value;
    
    // Parse formatted amount (remove commas and convert to number)
    const formAmount = parseFormattedNumber(formAmountValue);
    
    if (!formWalletId || !formTxnType || !formDirection || !formAmount) {
        showToast('warning', 'Warning', 'Please fill in all required fields');
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
        
        if (result.success) {
            showToast('success', 'Success', 'Transaction added successfully');
            if (!walletId) {
                addTransactionModal.hide();
            }
            loadTransactions();
            if (walletManagementModal._isShown) {
                loadWallets();
            }
        } else {
            showToast('error', 'Error', result.message || 'Failed to add transaction');
        }
    } catch (error) {
        console.error('Error saving transaction:', error);
        showToast('error', 'Error', 'Failed to add transaction: ' + error.message);
    }
}

// View transaction details
async function viewTransaction(txnId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions/${txnId}`);
        const result = await response.json();
        
        if (result.success) {
            const txn = result.data;
            const details = document.getElementById('transactionDetails');
            
            details.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Transaction Code</label>
                        <div class="fs-5">${txn.txn_code || '-'}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Transaction Type</label>
                        <div>
                            <span class="badge bg-primary">${txn.txn_type}</span>
                            <span class="badge ${txn.direction === 'IN' ? 'bg-success' : 'bg-danger'}">${txn.direction}</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Wallet</label>
                        <div class="fs-5">${txn.wallet_name || '-'}</div>
                        <small class="text-muted">${txn.provider_name || ''} - ${txn.branch_name || ''}</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Amount</label>
                        <div class="fs-5 fw-bold ${txn.direction === 'IN' ? 'text-success' : 'text-danger'}">
                            ${txn.direction === 'IN' ? '+' : '-'}${formatCurrency(txn.amount)}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Balance Before</label>
                        <div>${formatCurrency(txn.balance_before)}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Balance After</label>
                        <div class="fw-bold">${formatCurrency(txn.balance_after)}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Created By</label>
                        <div>
                            <span class="fas fa-user me-2"></span>
                            ${txn.created_by_full_name || txn.created_by_username || 'System'}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold text-muted small">Created At</label>
                        <div>
                            <span class="fas fa-clock me-2"></span>
                            ${formatDateTime(txn.created_at)}
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="fw-bold text-muted small">Remarks</label>
                        <div class="p-2 bg-light rounded">${txn.remarks || '-'}</div>
                    </div>
                </div>
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
    showToast('info', 'Info', 'Export feature coming soon');
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
            <td>${wallet.branch_name || '-'}</td>
            <td class="${wallet.current_balance >= 0 ? 'balance-positive' : 'balance-negative'}">
                ${formatCurrency(wallet.current_balance)}
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
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="adjustWalletBalance(${wallet.wallet_id})" title="Adjust Balance">
                        <span class="fas fa-wallet"></span>
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
            <td colspan="6">
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
    
    // Close wallet management modal first
    walletManagementModal.hide();
    
    // Open add transaction modal with pre-filled wallet
    openAddTransactionModal();
    
    // Pre-fill the form
    document.getElementById('addWalletId').value = walletId;
    document.getElementById('addTxnType').value = 'ADJUSTMENT';
    document.getElementById('addDirection').value = '';
    document.getElementById('addAmount').value = '';
    document.getElementById('addRemarks').value = `Balance adjustment for ${wallet.wallet_name}. Current balance: ${formatCurrency(wallet.current_balance)}`;
    
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
