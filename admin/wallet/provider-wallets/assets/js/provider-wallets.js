// Provider Wallets Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Provider Wallets module initialized');
    loadProviders();
    loadBranches();
    initCurrencyInputs();
    startProviderWalletRealtime();
    startProviderWalletPolling();
});

function startProviderWalletRealtime() {
    const config = window.PROVIDER_WALLET_PUSHER_CONFIG || window.TMS_PUSHER_CONFIG;
    const branchIds = config?.branchIds || window.PROVIDER_WALLET_PUSHER_CONFIG?.branchIds || [];

    if (!config?.enabled || typeof Pusher === 'undefined' || !branchIds.length || window.providerWalletPusher) {
        return;
    }

    try {
        const pusher = new Pusher(config.key, {
            cluster: config.cluster,
            forceTLS: true,
            authEndpoint: config.authEndpoint,
            auth: { withCredentials: true }
        });

        branchIds.forEach(branchId => {
            const channel = pusher.subscribe(`private-pos-branch-${branchId}`);
            channel.bind('wallet.updated', wallet => {
                if (wallet?.wallet_id) {
                    updateWalletDisplay(wallet.wallet_id, wallet);
                    updateStats();
                }
            });
            channel.bind('ticket_stock.updated', () => {
                refreshProviderWalletDisplays().catch(error => {
                    console.warn('[Provider wallets realtime] Stock refresh failed:', error);
                });
            });
            channel.bind('pusher:subscription_error', status => {
                console.warn('[Provider wallets realtime] Subscription failed:', status);
            });
        });

        pusher.connection.bind('state_change', states => {
            if (['disconnected', 'unavailable', 'failed'].includes(states.current)) {
                console.warn('[Provider wallets realtime] Connection state:', states.current);
            }
        });

        window.providerWalletPusher = pusher;
    } catch (error) {
        console.error('[Provider wallets realtime] Pusher initialization failed:', error);
    }
}

async function refreshProviderWalletDisplays() {
    const response = await fetch(`${window.BASE_URL}/api/wallets?action=balances&_t=${Date.now()}`, {
        cache: 'no-store'
    });
    const result = await response.json();

    if (result.success && Array.isArray(result.data?.wallets)) {
        result.data.wallets.forEach(wallet => updateWalletDisplay(wallet.wallet_id, wallet));
        updateStats();
    }
}

// Lightweight polling fallback so balances stay current even when Pusher is not configured.
function startProviderWalletPolling() {
    if (window.providerWalletBalanceRefreshInterval) {
        return;
    }

    let isRefreshing = false;

    const refresh = async () => {
        if (document.hidden || isRefreshing) {
            return;
        }

        isRefreshing = true;
        try {
            await refreshProviderWalletDisplays();
        } catch (error) {
            console.warn('[Provider wallets polling] Refresh failed:', error);
        } finally {
            isRefreshing = false;
        }
    };

    window.providerWalletBalanceRefreshInterval = setInterval(refresh, 5000);
    refresh();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
}

// Initialize Bootstrap modals
let addWalletModal, editWalletModal, adjustBalanceModal, viewWalletModal, manageProviderWalletsModal;
let addWalletPrefill = null; // holds variant_id to preselect when opening Add Wallet
let currentAdjustWallet = null;

document.addEventListener('DOMContentLoaded', function() {
    addWalletModal = new bootstrap.Modal(document.getElementById('addWalletModal'));
    editWalletModal = new bootstrap.Modal(document.getElementById('editWalletModal'));
    adjustBalanceModal = new bootstrap.Modal(document.getElementById('adjustBalanceModal'));
    viewWalletModal = new bootstrap.Modal(document.getElementById('viewWalletModal'));
    const manageProviderWalletsEl = document.getElementById('manageProviderWalletsModal');
    if (manageProviderWalletsEl) {
        manageProviderWalletsModal = new bootstrap.Modal(manageProviderWalletsEl);
    }

    const variantSelect = document.getElementById('addVariantId');
    if (variantSelect) {
        variantSelect.addEventListener('change', checkExistingWallet);
    }
});

// Helpers for Add Wallet duplicate prevention
function getExistingWalletsFor(providerId, branchId) {
    if (!window.providerWalletData || !window.providerWalletData.existingWallets) return [];
    return window.providerWalletData.existingWallets.filter(w =>
        w.provider_id == providerId && w.branch_id == branchId
    );
}

function findExistingWallet(branchId, providerId, variantId) {
    if (!window.providerWalletData || !window.providerWalletData.existingWallets) return null;
    const selectedVariant = variantId ? parseInt(variantId, 10) : null;
    return window.providerWalletData.existingWallets.find(w =>
        w.branch_id == branchId &&
        w.provider_id == providerId &&
        (w.variant_id ? w.variant_id === selectedVariant : selectedVariant === null)
    ) || null;
}

function setAddWalletKeyFieldsDisabled(disabled) {
    ['addBranchId', 'addProviderId', 'addVariantId', 'addInitialBalance'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = disabled;
    });
}

function checkExistingWallet() {
    const branchSelect = document.getElementById('addBranchId');
    const providerSelect = document.getElementById('addProviderId');
    const variantSelect = document.getElementById('addVariantId');
    const statusSelect = document.getElementById('addStatus');
    const walletIdInput = document.getElementById('addWalletId');
    const alertEl = document.getElementById('addExistingWalletAlert');
    const messageEl = document.getElementById('addExistingWalletMessage');

    if (!branchSelect || !providerSelect || !variantSelect) return;

    const branchId = branchSelect.value;
    const providerId = providerSelect.value;
    const variantId = variantSelect.value || null;

    if (!branchId || !providerId) {
        if (alertEl) alertEl.classList.add('d-none');
        setAddWalletKeyFieldsDisabled(false);
        if (walletIdInput) walletIdInput.value = '';
        return;
    }

    const existing = findExistingWallet(branchId, providerId, variantId);

    const isVariant = Boolean(variantId);
    const initialBalanceInput = setAddWalletInitialInputMode(isVariant);
    const minBalanceInput = setInputNumberMode('addMinBalance', isVariant, {
        name: 'min_balance',
        label: 'Min Balance Threshold',
        labelVariant: 'Min Stock Threshold',
        symbol: '₱',
        symbolVariant: '',
        placeholder: '1,000.00',
        placeholderVariant: '0',
        help: 'Alert when balance falls below this amount',
        helpVariant: 'Alert when stock falls below this quantity'
    });

    if (existing) {
        if (walletIdInput) walletIdInput.value = existing.wallet_id;
        if (initialBalanceInput) {
            initialBalanceInput.value = isVariant
                ? String(Math.max(0, parseInt(existing.on_hand_qty || 0, 10)))
                : formatNumberValue(existing.current_balance);
            initialBalanceInput.disabled = true;
        }
        if (minBalanceInput) {
            minBalanceInput.value = isVariant
                ? String(Math.max(0, parseInt(existing.min_balance || 0, 10)))
                : formatNumberValue(existing.min_balance);
        }
        if (statusSelect) statusSelect.value = existing.status;

        setAddWalletKeyFieldsDisabled(true);

        if (alertEl && messageEl) {
            const balanceText = isVariant
                ? `${Math.max(0, parseInt(existing.on_hand_qty || 0, 10))} tickets`
                : `₱${formatNumberValue(existing.current_balance)}`;
            messageEl.textContent = `An existing wallet with ${balanceText} was found. Saving will update its settings.`;
            alertEl.classList.remove('d-none');
        }
    } else {
        if (walletIdInput) walletIdInput.value = '';
        if (initialBalanceInput) {
            initialBalanceInput.disabled = false;
            initialBalanceInput.value = isVariant ? '0' : '0.00';
        }
        if (minBalanceInput) {
            minBalanceInput.value = isVariant ? '0' : '1,000.00';
        }
        if (statusSelect) statusSelect.value = 'active';

        setAddWalletKeyFieldsDisabled(false);
        // Keep provider disabled when no branch? handleAddBranchChange will manage it.
        if (!branchId) providerSelect.disabled = true;
        if (!providerId) variantSelect.disabled = true;

        if (alertEl) alertEl.classList.add('d-none');
    }
}

function handleAddBranchChange() {
    const branchSelect = document.getElementById('addBranchId');
    const providerSelect = document.getElementById('addProviderId');
    const variantSelect = document.getElementById('addVariantId');
    if (!branchSelect || !providerSelect || !variantSelect) return;

    const branchId = branchSelect.value;
    providerSelect.value = '';
    variantSelect.innerHTML = '<option value="">Provider-level wallet (no variant)</option>';
    variantSelect.disabled = true;

    const placeholder = providerSelect.querySelector('option[value=""]');
    if (!branchId) {
        providerSelect.disabled = true;
        if (placeholder) placeholder.textContent = 'Select Branch first';
        return;
    }

    providerSelect.disabled = false;
    if (placeholder) placeholder.textContent = 'Select Provider';

    const usedCounts = {};
    const existing = window.providerWalletData ? window.providerWalletData.existingWallets.filter(w => w.branch_id == branchId) : [];
    existing.forEach(w => {
        usedCounts[w.provider_id] = (usedCounts[w.provider_id] || 0) + 1;
    });

    Array.from(providerSelect.options).forEach(option => {
        if (!option.value) return;
        const variantCount = parseInt(option.dataset.variantCount || '0', 10);
        const possible = variantCount + 1;
        const used = usedCounts[option.value] || 0;
        option.disabled = used >= possible;
        option.textContent = option.textContent.replace(/ \(no available wallets\)$/, '') + (used >= possible ? ' (no available wallets)' : '');
    });
}

async function handleAddProviderChange() {
    const branchSelect = document.getElementById('addBranchId');
    const providerSelect = document.getElementById('addProviderId');
    const variantSelect = document.getElementById('addVariantId');
    if (!branchSelect || !providerSelect || !variantSelect) return;

    const providerId = providerSelect.value;
    const branchId = branchSelect.value;
    variantSelect.innerHTML = '<option value="">Provider-level wallet (no variant)</option>';
    variantSelect.disabled = true;
    if (!providerId || !branchId) return;

    const existing = getExistingWalletsFor(providerId, branchId);
    const usedVariantIds = existing
        .filter(w => w.variant_id !== null && w.variant_id !== undefined && w.variant_id !== '')
        .map(w => parseInt(w.variant_id, 10));
    const hasProviderLevel = existing.some(w => w.variant_id === null || w.variant_id === undefined || w.variant_id === '');

    if (hasProviderLevel) {
        variantSelect.options[0].disabled = true;
        variantSelect.options[0].textContent += ' (already created)';
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-variants?provider_id=${encodeURIComponent(providerId)}&branch_id=${encodeURIComponent(branchId)}`);
        const result = await response.json();

        if (result.success && result.data && result.data.length) {
            result.data.forEach(variant => {
                const variantId = parseInt(variant.variant_id, 10);
                const used = usedVariantIds.includes(variantId);
                const label = `${variant.variant_name} (${variant.variant_code})${used ? ' (already created)' : ''}`;
                variantSelect.innerHTML += `<option value="${variantId}" ${used ? 'disabled' : ''}>${label}</option>`;
            });
            variantSelect.disabled = false;
        }

        if (addWalletPrefill !== null) {
            variantSelect.value = addWalletPrefill;
            addWalletPrefill = null;
        }

        if (!variantSelect.dataset.variantListener) {
            variantSelect.dataset.variantListener = '1';
            variantSelect.addEventListener('change', checkExistingWallet);
        }

        checkExistingWallet();
    } catch (error) {
        console.error('Error loading variants:', error);
    }
}

// Load providers for dropdown
async function loadProviders() {
    const select = document.getElementById('addProviderId');
    if (!select) return;

    if (select.options.length > 1) {
        if (!select.dataset.variantListener) {
            select.dataset.variantListener = '1';
            select.addEventListener('change', handleAddProviderChange);
        }
        return;
    }

    select.innerHTML = '<option value="">Select Branch first</option>';
    select.disabled = true;
}

// Load branches for dropdown
async function loadBranches() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/business-branches`);
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('addBranchId');
            select.innerHTML = '<option value="">Select Branch</option>';
            result.data.branches.forEach(branch => {
                select.innerHTML += `<option value="${branch.branch_id}">${branch.branch_name}</option>`;
            });

            if (!select.dataset.branchListener) {
                select.dataset.branchListener = '1';
                select.addEventListener('change', handleAddBranchChange);
            }
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Open add wallet modal
async function openAddWalletModal(prefillProviderId, prefillBranchId, prefillVariantId) {
    document.getElementById('addWalletForm').reset();

    const providerSelect = document.getElementById('addProviderId');
    const branchSelect = document.getElementById('addBranchId');
    const variantSelect = document.getElementById('addVariantId');
    const statusSelect = document.getElementById('addStatus');
    const walletIdInput = document.getElementById('addWalletId');
    const alertEl = document.getElementById('addExistingWalletAlert');

    // Re-enable all key fields and clear any previous existing-wallet state
    if (branchSelect) branchSelect.disabled = false;
    if (providerSelect) providerSelect.disabled = true;
    if (variantSelect) variantSelect.disabled = true;
    if (statusSelect) statusSelect.value = 'active';
    if (walletIdInput) walletIdInput.value = '';
    if (alertEl) alertEl.classList.add('d-none');

    // Start in provider-level (money) mode
    const initialBalanceInput = setAddWalletInitialInputMode(false);
    if (initialBalanceInput) {
        initialBalanceInput.disabled = false;
        initialBalanceInput.value = '0.00';
    }

    const addMinInput = setInputNumberMode('addMinBalance', false, {
        name: 'min_balance',
        label: 'Min Balance Threshold',
        labelVariant: 'Min Stock Threshold',
        symbol: '₱',
        symbolVariant: '',
        placeholder: '1,000.00',
        placeholderVariant: '0',
        help: 'Alert when balance falls below this amount',
        helpVariant: 'Alert when stock falls below this quantity'
    });
    if (addMinInput) addMinInput.value = '1,000.00';

    await loadBranches();

    if (prefillBranchId && branchSelect) {
        branchSelect.value = prefillBranchId;
        handleAddBranchChange();
        if (prefillProviderId && providerSelect) {
            providerSelect.value = prefillProviderId;
            addWalletPrefill = prefillVariantId !== undefined ? prefillVariantId : null;
            await handleAddProviderChange();
        }
    }

    addWalletModal.show();
}

// Save wallet
async function saveWallet() {
    const providerSelect = document.getElementById('addProviderId');
    const branchSelect = document.getElementById('addBranchId');
    const variantSelect = document.getElementById('addVariantId');
    const walletIdInput = document.getElementById('addWalletId');
    const providerId = providerSelect.value;
    const branchId = branchSelect.value;
    const variantId = variantSelect?.value || '';
    const isVariant = Boolean(variantId);
    const initialBalance = document.getElementById('addInitialBalance').value;
    const minBalance = document.getElementById('addMinBalance').value;
    const status = document.getElementById('addStatus').value;
    const existingWalletId = walletIdInput ? walletIdInput.value : '';
    const parsedMinBalance = minBalance.trim() === '' ? null : parseFloat(String(minBalance).replace(/,/g, ''));

    if (!providerId || !branchId) {
        showToast('warning', 'Warning', 'Please select provider and branch');
        return;
    }
    if (parsedMinBalance !== null && (!Number.isFinite(parsedMinBalance) || parsedMinBalance < 0)) {
        showToast('warning', 'Warning', 'Please enter a valid non-negative minimum threshold');
        return;
    }
    const minBalanceValue = parsedMinBalance === null ? (isVariant ? 0 : 1000) : parsedMinBalance;

    // If a pre-existing wallet is detected, skip duplicate checks and update it
    if (!existingWalletId && variantSelect) {
        const variantOption = variantSelect.options[variantSelect.selectedIndex];
        if (variantOption && variantOption.disabled) {
            showToast('warning', 'Warning', 'Selected variant already has a wallet for this branch');
            return;
        }
    }

    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const headers = {
            'Content-Type': 'application/json'
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        let body, method, successMessage;
        if (existingWalletId) {
            method = 'PUT';
            successMessage = 'Wallet updated successfully';
            body = JSON.stringify({
                wallet_id: existingWalletId,
                min_balance: minBalanceValue,
                status: status
            });
        } else {
            method = 'POST';
            successMessage = 'Wallet created successfully';
            const createBody = {
                provider_id: providerId,
                branch_id: branchId,
                variant_id: variantId || null,
                min_balance: minBalanceValue,
                status: status
            };
            if (isVariant) {
                createBody.initial_ticket_count = parseInt(String(initialBalance).replace(/,/g, ''), 10) || 0;
            } else {
                createBody.initial_balance = parseFloat(String(initialBalance).replace(/,/g, '')) || 0;
            }
            body = JSON.stringify(createBody);
        }

        const response = await fetch(`${window.BASE_URL}/api/wallets`, {
            method: method,
            headers: headers,
            body: body
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', successMessage);
            addWalletModal.hide();

            if (existingWalletId) {
                updateWalletDisplay(existingWalletId, {
                    status: result.data?.status ?? status,
                    min_balance: result.data?.min_balance ?? minBalanceValue,
                    current_balance: result.data?.current_balance
                });
                updateStats();
            } else {
                location.reload();
            }
        } else {
            showToast('error', 'Error', result.message || 'Failed to save wallet');
        }
    } catch (error) {
        console.error('Error saving wallet:', error);
        showToast('error', 'Error', 'Failed to save wallet: ' + error.message);
    }
}

// Edit wallet
async function editWallet(walletId) {
    document.getElementById('editWalletId').value = walletId;

    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await response.json();

        if (result.success && result.data) {
            const wallet = result.data;
            document.getElementById('editProviderName').textContent = wallet.provider_name || '-';
            document.getElementById('editBranchName').textContent = wallet.branch_name || '-';
            const variantLabel = wallet.variant_name
                ? `${wallet.variant_name}${wallet.variant_code ? ` (${wallet.variant_code})` : ''}`
                : 'Provider-level (no variant)';
            document.getElementById('editVariantName').textContent = variantLabel;
            const isVariant = Boolean(wallet.variant_id);
            const balance = isVariant ? parseInt(wallet.on_hand_qty || 0, 10) : parseFloat(wallet.current_balance);
            const balanceText = isVariant
                ? `${balance} tickets`
                : (isNaN(parseFloat(wallet.current_balance))
                    ? '0.00'
                    : parseFloat(wallet.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            document.getElementById('editCurrentBalance').textContent = balanceText;

            const editMinInput = setInputNumberMode('editMinBalance', isVariant, {
                name: 'min_balance',
                label: 'Min Balance Threshold',
                labelVariant: 'Min Stock Threshold',
                symbol: '₱',
                symbolVariant: '',
                placeholder: '1,000.00',
                placeholderVariant: '0',
                help: 'Alert when balance falls below this amount',
                helpVariant: 'Alert when stock falls below this quantity'
            });
            if (editMinInput) {
                editMinInput.value = isVariant
                    ? String(Math.max(0, parseInt(wallet.min_balance || 0, 10)))
                    : formatNumberValue(wallet.min_balance || 0);
            }

            const editBalanceLabel = document.getElementById('editCurrentBalanceLabel');
            if (editBalanceLabel) editBalanceLabel.textContent = isVariant ? 'Current Ticket Stock:' : 'Current Balance:';

            // Set current status
            document.getElementById('editStatus').checked = wallet.status === 'active';
            updateStatusLabel(wallet.status === 'active');

            editWalletModal.show();
        } else {
            showToast('error', 'Error', result.error || 'Failed to load wallet details');
        }
    } catch (error) {
        console.error('Error loading wallet details:', error);
        showToast('error', 'Error', 'Failed to load wallet details: ' + error.message);
    }
}

// Update status label text
function updateStatusLabel(isActive) {
    const label = document.getElementById('editStatusLabel');
    if (label) {
        label.innerHTML = isActive 
            ? '<span class="text-success fw-bold">Active</span>' 
            : '<span class="text-muted">Inactive</span>';
    }
}

// Handle edit status switch change
document.addEventListener('DOMContentLoaded', function() {
    const editStatusSwitch = document.getElementById('editStatus');
    if (editStatusSwitch) {
        editStatusSwitch.addEventListener('change', function() {
            updateStatusLabel(this.checked);
        });
    }
    
    // Handle wallet card switches for real-time toggle
    const walletSwitches = document.querySelectorAll('.wallet-status-switch');
    walletSwitches.forEach(switchEl => {
        switchEl.addEventListener('change', async function() {
            const walletId = this.getAttribute('data-wallet-id');
            const newStatus = this.checked ? 'active' : 'inactive';
            await toggleWalletStatus(walletId, newStatus, this);
        });
    });
});

// Update wallet
async function updateWallet() {
    const walletId = document.getElementById('editWalletId').value;
    const statusCheckbox = document.getElementById('editStatus');
    const status = statusCheckbox.checked ? 'active' : 'inactive';
    const minBalance = document.getElementById('editMinBalance').value;
    const parsedMinBalance = minBalance === '' ? null : parseFloat(String(minBalance).replace(/,/g, ''));

    if (minBalance !== '' && (!Number.isFinite(parsedMinBalance) || parsedMinBalance < 0)) {
        showToast('warning', 'Warning', 'Please enter a valid non-negative minimum balance');
        return;
    }
    
    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const requestBody = {
            wallet_id: walletId,
            status: status
        };
        
        // Only include min_balance if it has a value
        if (parsedMinBalance !== null) {
            requestBody.min_balance = parsedMinBalance;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/wallets`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify(requestBody)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Wallet updated successfully');
            editWalletModal.hide();

            const minBalanceValue = parsedMinBalance === null ? undefined : parsedMinBalance;

            updateWalletDisplay(walletId, {
                status: result.data?.status ?? status,
                min_balance: result.data?.min_balance ?? minBalanceValue,
                current_balance: result.data?.current_balance
            });

            // Update stats cards
            updateStats();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update wallet');
            // Revert switch on error
            statusCheckbox.checked = !statusCheckbox.checked;
            updateStatusLabel(statusCheckbox.checked);
        }
    } catch (error) {
        console.error('Error updating wallet:', error);
        showToast('error', 'Error', 'Failed to update wallet: ' + error.message);
        // Revert switch on error
        statusCheckbox.checked = !statusCheckbox.checked;
        updateStatusLabel(statusCheckbox.checked);
    }
}

// Toggle wallet status in real-time
async function toggleWalletStatus(walletId, newStatus, switchElement) {
    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/wallets`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                wallet_id: walletId,
                status: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', `Wallet ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
            updateWalletDisplay(walletId, {
                status: result.data?.status ?? newStatus,
                min_balance: result.data?.min_balance
            });
            updateStats();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update wallet status');
            // Revert switch on error
            switchElement.checked = !switchElement.checked;
        }
    } catch (error) {
        console.error('Error toggling wallet status:', error);
        showToast('error', 'Error', 'Failed to update wallet status: ' + error.message);
        // Revert switch on error
        switchElement.checked = !switchElement.checked;
    }
}

// Update stats cards
async function updateStats() {
    const wallets = window.providerWalletData?.existingWallets;
    if (Array.isArray(wallets)) {
        const totalWallets = wallets.length;
        const activeWallets = wallets.filter(wallet => wallet.status === 'active').length;
        const inactiveWallets = wallets.filter(wallet => wallet.status === 'inactive').length;
        const totalBalance = wallets.filter(wallet => !wallet.variant_id).reduce((sum, wallet) => {
            const balance = Number(wallet.current_balance);
            return sum + (Number.isFinite(balance) ? balance : 0);
        }, 0);

        renderWalletStats({
            total_wallets: totalWallets,
            active_wallets: activeWallets,
            inactive_wallets: inactiveWallets,
            total_balance: totalBalance
        });
        return;
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?action=stats`, { cache: 'no-store' });
        const result = await response.json();
        if (result.success && result.data) {
            renderWalletStats(result.data);
        }
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

function renderWalletStats(stats) {
    const totalWallets = Number(stats.total_wallets);
    const activeWallets = Number(stats.active_wallets);
    const inactiveWallets = Number(stats.inactive_wallets);
    const totalBalance = Number(stats.total_balance);
    const safeTotal = Number.isFinite(totalWallets) ? totalWallets : 0;
    const safeActive = Number.isFinite(activeWallets) ? activeWallets : 0;
    const safeInactive = Number.isFinite(inactiveWallets) ? inactiveWallets : 0;
    const safeBalance = Number.isFinite(totalBalance) ? totalBalance : 0;
    const activePercentage = safeTotal > 0 ? ((safeActive / safeTotal) * 100).toFixed(1) : '0.0';
    const inactivePercentage = safeTotal > 0 ? ((safeInactive / safeTotal) * 100).toFixed(1) : '0.0';
    const maxBalance = Math.max(10000, safeBalance);
    const balancePercentage = maxBalance > 0 ? Math.min(100, (safeBalance / maxBalance) * 100) : 0;

    const values = {
        walletStatTotal: safeTotal,
        walletStatActive: safeActive,
        walletStatInactive: safeInactive,
        walletStatActivePercentage: activePercentage,
        walletStatInactivePercentage: inactivePercentage,
        walletStatTotalBalance: `₱${safeBalance.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    };

    Object.entries(values).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });

    const progress = document.getElementById('walletStatBalanceProgress');
    if (progress) {
        progress.style.width = `${balancePercentage}%`;
        progress.setAttribute('aria-valuenow', String(balancePercentage));
    }
}

function updateWalletDisplay(walletId, changes) {
    const listRow = document.querySelector(`#walletListTable tr[data-wallet-id="${walletId}"]`);

    if (changes.min_balance !== undefined) {
        const listMin = document.getElementById(`walletListMinBalance${walletId}`);
        const cardMin = document.getElementById(`walletCardMinBalance${walletId}`);
        const isVariant = isVariantWallet(walletId);
        const minBalanceValue = Math.max(0, parseInt(changes.min_balance || 0, 10));
        if (listMin) {
            listMin.textContent = isVariant ? `${minBalanceValue} tickets` : '₱' + formatNumberValue(changes.min_balance);
        }
        if (cardMin) {
            cardMin.innerHTML = isVariant
                ? `<span class="fas fa-exclamation-triangle me-1"></span>Min Stock: ${minBalanceValue} tickets`
                : `<span class="fas fa-exclamation-triangle me-1"></span>Min: ₱${formatNumberValue(changes.min_balance)}`;
        }
    }

    if (changes.status !== undefined) {
        const isActive = changes.status === 'active';

        const listSwitch = listRow ? listRow.querySelector('.wallet-status-switch') : null;
        if (listSwitch) {
            listSwitch.checked = isActive;
        }

        const cardSwitch = document.getElementById(`walletSwitch${walletId}`);
        if (cardSwitch) {
            cardSwitch.checked = isActive;
            const cardLabel = cardSwitch.nextElementSibling;
            if (cardLabel) {
                cardLabel.textContent = isActive ? 'Active' : 'Inactive';
            }
        }
    }

    if (changes.current_balance !== undefined) {
        updateWalletBalanceDisplay(walletId, changes.current_balance);
    }
    if (changes.on_hand_qty !== undefined) {
        updateWalletStockDisplay(walletId, changes.on_hand_qty);
    }

    if (window.providerWalletData && window.providerWalletData.existingWallets) {
        const w = window.providerWalletData.existingWallets.find(w => w.wallet_id == walletId);
        if (w) {
            if (changes.min_balance !== undefined) w.min_balance = parseFloat(changes.min_balance) || 0;
            if (changes.current_balance !== undefined) w.current_balance = parseFloat(changes.current_balance) || 0;
            if (changes.on_hand_qty !== undefined) w.on_hand_qty = parseInt(changes.on_hand_qty, 10) || 0;
            if (changes.reserved_qty !== undefined) w.reserved_qty = parseInt(changes.reserved_qty, 10) || 0;
            if (changes.available_qty !== undefined) w.available_qty = parseInt(changes.available_qty, 10) || 0;
            if (changes.status !== undefined) w.status = changes.status;
        }
    }
}

function isVariantWallet(walletId) {
    if (!window.providerWalletData?.existingWallets) return false;
    const w = window.providerWalletData.existingWallets.find(w => w.wallet_id == walletId);
    return Boolean(w && w.variant_id);
}

function updateWalletStockDisplay(walletId, onHandQty) {
    if (!isVariantWallet(walletId)) return;

    const stockQty = parseInt(onHandQty || 0, 10);
    const stockText = `${stockQty} tickets`;
    const stockClass = stockQty >= 0 ? 'text-success' : 'text-danger';
    const listBalance = document.getElementById(`walletListBalance${walletId}`);
    if (listBalance) {
        listBalance.textContent = stockText;
        listBalance.classList.remove('text-success', 'text-danger');
        listBalance.classList.add(stockClass);
    }

    const cardBalance = document.getElementById(`walletCardBalance${walletId}`);
    if (cardBalance) {
        cardBalance.textContent = stockText;
        cardBalance.classList.remove('text-success', 'text-danger');
        cardBalance.classList.add(stockClass);
    }
}

function updateWalletBalanceDisplay(walletId, currentBalance) {
    if (isVariantWallet(walletId)) return; // variant balance is stock, not money

    const listBalance = document.getElementById(`walletListBalance${walletId}`);
    if (listBalance) {
        listBalance.textContent = '₱' + formatNumberValue(currentBalance);
        listBalance.classList.remove('text-success', 'text-danger');
        listBalance.classList.add(parseFloat(currentBalance) >= 0 ? 'text-success' : 'text-danger');
    }

    const cardBalance = document.getElementById(`walletCardBalance${walletId}`);
    if (cardBalance) {
        cardBalance.textContent = '₱' + formatNumberValue(currentBalance);
        cardBalance.classList.remove('text-success', 'text-danger');
        cardBalance.classList.add(parseFloat(currentBalance) >= 0 ? 'text-success' : 'text-danger');
    }

    if (window.providerWalletData && window.providerWalletData.existingWallets) {
        const w = window.providerWalletData.existingWallets.find(w => w.wallet_id == walletId);
        if (w) w.current_balance = parseFloat(currentBalance) || 0;
    }
}

// Adjust balance
async function adjustBalance(walletId) {
    document.getElementById('adjustWalletId').value = walletId;

    document.getElementById('adjustDirection').value = '';
    document.getElementById('adjustRemarks').value = '';

    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await response.json();

        if (result.success && result.data) {
            const wallet = result.data;
            currentAdjustWallet = wallet;
            document.getElementById('adjustProviderName').textContent = wallet.provider_name || '-';
            document.getElementById('adjustBranchName').textContent = wallet.branch_name || '-';
            const variantLabel = wallet.variant_name
                ? `${wallet.variant_name}${wallet.variant_code ? ` (${wallet.variant_code})` : ''}`
                : 'Provider-level (no variant)';
            document.getElementById('adjustVariantName').textContent = variantLabel;
            const isAdjustVariant = Boolean(wallet.variant_id);
            const adjustBalanceValue = isAdjustVariant ? parseInt(wallet.on_hand_qty || 0, 10) : parseFloat(wallet.current_balance);
            const adjustBalanceText = isAdjustVariant
                ? `${adjustBalanceValue} tickets`
                : (isNaN(parseFloat(wallet.current_balance))
                    ? '0.00'
                    : parseFloat(wallet.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            document.getElementById('adjustCurrentBalance').textContent = adjustBalanceText;

            const adjustInput = setInputNumberMode('adjustAmount', isAdjustVariant, {
                name: 'amount',
                label: 'Amount',
                labelVariant: 'Quantity',
                symbol: '₱',
                symbolVariant: '',
                placeholder: '0.00',
                placeholderVariant: '0'
            });
            if (adjustInput) adjustInput.value = '0';

            adjustBalanceModal.show();

            const adjustBalanceLabel = document.getElementById('adjustCurrentBalanceLabel');
            if (adjustBalanceLabel) adjustBalanceLabel.textContent = isAdjustVariant ? 'Current Ticket Stock:' : 'Current Balance:';
        } else {
            showToast('error', 'Error', result.error || 'Failed to load wallet details');
        }
    } catch (error) {
        console.error('Error loading wallet details:', error);
        showToast('error', 'Error', 'Failed to load wallet details: ' + error.message);
    }
}

// Save adjustment
async function saveAdjustment() {
    const walletId = document.getElementById('adjustWalletId').value;
    const direction = document.getElementById('adjustDirection').value;
    const amount = document.getElementById('adjustAmount').value;
    const remarks = document.getElementById('adjustRemarks').value;
    const parsedAmount = parseFloat(String(amount).replace(/,/g, ''));

    if (!direction || !amount || !Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        showToast('warning', 'Warning', 'Please fill direction and enter a valid amount');
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

        if (currentAdjustWallet && Number(currentAdjustWallet.wallet_id) === Number(walletId) && currentAdjustWallet.variant_id) {
            const quantity = Math.trunc(parsedAmount);
            if (quantity <= 0 || quantity !== parsedAmount) {
                showToast('warning', 'Warning', 'Ticket stock quantity must be a whole number greater than 0');
                return;
            }

            const response = await fetch(`${window.BASE_URL}/api/ticket-stock`, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'adjust',
                    branch_id: currentAdjustWallet.branch_id,
                    provider_id: currentAdjustWallet.provider_id,
                    variant_id: currentAdjustWallet.variant_id,
                    delta: direction === 'IN' ? quantity : -quantity,
                    reason: remarks || 'Manual ticket stock adjustment'
                })
            });
            const result = await response.json();
            if (!result.success) {
                showToast('error', 'Error', result.error || 'Failed to adjust ticket stock');
                return;
            }

            showToast('success', 'Success', 'Ticket stock adjusted successfully');
            adjustBalanceModal.hide();
            updateWalletDisplay(walletId, { on_hand_qty: result.data?.on_hand_after });
            updateStats();
            return;
        }

        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions`, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                wallet_id: walletId,
                txn_type: 'ADJUSTMENT',
                direction,
                amount: parsedAmount,
                remarks
            })
        });
        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', 'Balance adjusted successfully');
            adjustBalanceModal.hide();
            if (result.data && typeof result.data.current_balance !== 'undefined') {
                updateWalletBalanceDisplay(walletId, result.data.current_balance);
                updateStats();
            } else {
                location.reload();
            }
        } else {
            showToast('error', 'Error', result.error || result.message || 'Failed to adjust balance');
        }
    } catch (error) {
        console.error('Error adjusting balance:', error);
        showToast('error', 'Error', 'Failed to adjust balance: ' + error.message);
    }
}

// Format helpers for wallet detail modal
function formatCurrency(value) {
    return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function escapeWalletHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

// View wallet
function viewWallet(walletId) {
    if (!viewWalletModal) {
        const modalEl = document.getElementById('viewWalletModal');
        if (modalEl) viewWalletModal = new bootstrap.Modal(modalEl);
    }
    if (!viewWalletModal) return;

    viewWalletModal.show();
    const body = document.getElementById('viewWalletModalBody');
    body.innerHTML = `<div class="text-center py-4"><span class="fas fa-spinner fa-spin fa-2x text-primary"></span><p class="text-muted mt-2">Loading wallet details...</p></div>`;

    const encodedWalletId = (typeof IdEncoder !== 'undefined' && IdEncoder.encode) ? IdEncoder.encode(walletId) : walletId;

    fetch(`${window.BASE_URL}/api/wallets?id=${encodedWalletId}`)
        .then(response => response.json())
        .then(async result => {
            if (!result.success || !result.data) {
                body.innerHTML = `<div class="alert alert-danger">${result.message || 'Wallet not found'}</div>`;
                return;
            }
            const w = result.data;

            // Load variants for this provider/branch
            let variants = [];
            try {
                const vres = await fetch(`${window.BASE_URL}/api/ticket-variants?provider_id=${w.provider_id}&branch_id=${w.branch_id}`);
                const vjson = await vres.json();
                if (vjson.success) variants = vjson.data || [];
            } catch (e) {
                console.error('Failed to load variants', e);
            }

            let variantHtml = '';
            if (variants.length) {
                variantHtml = `<div class="table-responsive"><table class="table table-sm table-borderless mb-0"><tbody>` + variants.map(v => {
                    const variantColor = /^#[0-9a-f]{3,8}$/i.test(String(v.color_code || '')) ? v.color_code : '#0d6efd';
                    const variantStatus = v.is_active ? 'Active' : 'Inactive';
                    return `
                    <tr>
                        <td style="width:30px;"><span class="d-inline-block rounded" style="width:16px;height:16px;background:${variantColor};border:1px solid #dee2e6;"></span></td>
                        <td class="fw-semibold">${escapeWalletHtml(v.variant_name || '-')} <small class="text-muted">(${escapeWalletHtml(v.variant_code || '-')})</small></td>
                        <td class="text-end"><span class="badge ${v.is_active ? 'bg-success' : 'bg-secondary'}">${variantStatus}</span></td>
                        <td class="text-end small text-muted">On hand: ${Number(v.on_hand_qty || 0)}</td>
                        <td class="text-end small text-muted">Reserved: ${Number(v.reserved_qty || 0)}</td>
                        <td class="text-end small text-muted">Avail: ${Number(v.available_qty || 0)}</td>
                    </tr>`;
                }).join('') + `</tbody></table></div>`;
            } else {
                variantHtml = `<span class="text-muted">No variants configured</span>`;
            }

            const isSubProvider = Boolean(w.parent_provider_id);
            const mainProviderName = w.parent_provider_name || w.provider_name || '-';
            const linkedSubProviders = w.child_provider_names
                ? w.child_provider_names.split(',').map(name => name.trim()).filter(Boolean)
                : [];
            const childrenHtml = linkedSubProviders.length
                ? `<div class="d-flex flex-wrap gap-1">${linkedSubProviders.map(name => `<span class="badge bg-light text-dark border"><span class="fas fa-sitemap me-1 text-primary"></span>${escapeWalletHtml(name)}</span>`).join('')}</div>`
                : `<span class="text-muted">No linked sub-providers</span>`;
            const hierarchyLabel = isSubProvider ? 'Sub-provider' : linkedSubProviders.length ? 'Main provider with sub-providers' : 'Standalone provider';
            const typeOptions = window.PROVIDER_TYPE_OPTIONS || {};
            const fallbackType = w.provider_type ? w.provider_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '-';
            const providerType = typeOptions[w.provider_type] || fallbackType;
            const typeColor = (window.PROVIDER_TYPE_COLORS || {})[w.provider_type] || 'bg-secondary';
            const walletDisplayName = [w.provider_name, w.variant_name].filter(Boolean).join(' - ') || w.wallet_name || '-';
            const walletStatus = w.status === 'active' ? 'Active' : w.status === 'inactive' ? 'Inactive' : '-';

            body.innerHTML = `
              <div class="row g-3">
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-3"><span class="fas fa-wallet me-2"></span>Wallet</h6>
                  <div class="row g-2 small">
                    <div class="col-5 text-muted">Wallet Name</div><div class="col-7 fw-semibold">${escapeWalletHtml(walletDisplayName)}</div>
                    <div class="col-5 text-muted">Branch</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.branch_name || '-')}</div>
                    <div class="col-5 text-muted">${w.variant_id ? 'Current Ticket Stock' : 'Current Balance'}</div><div class="col-7 fw-bold ${(w.variant_id ? Number(w.on_hand_qty || 0) : Number(w.current_balance || 0)) >= 0 ? 'text-success' : 'text-danger'}">${w.variant_id ? (Number(w.on_hand_qty || 0) + ' tickets') : formatCurrency(w.current_balance)}</div>
                    <div class="col-5 text-muted">${w.variant_id ? 'Min. Stock' : 'Min. Balance'}</div><div class="col-7 fw-semibold">${w.variant_id ? (Number(w.min_balance || 0) + ' tickets') : formatCurrency(w.min_balance)}</div>
                    <div class="col-5 text-muted">Status</div><div class="col-7"><span class="badge ${w.status === 'active' ? 'bg-success' : w.status === 'inactive' ? 'bg-danger' : 'bg-secondary'}">${walletStatus}</span></div>
                    <div class="col-5 text-muted">Created</div><div class="col-7 fw-semibold">${escapeWalletHtml(formatDate(w.created_at))}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-3"><span class="fas fa-building me-2"></span>Provider</h6>
                  <div class="row g-2 small">
                    <div class="col-5 text-muted">Provider Name</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.provider_name || '-')}</div>
                    <div class="col-5 text-muted">Provider Code</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.provider_code || '-')}</div>
                    <div class="col-5 text-muted">Type</div><div class="col-7 fw-semibold"><span class="badge ${typeColor}">${escapeWalletHtml(providerType)}</span></div>
                    <div class="col-5 text-muted">Contact Person</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.contact_person || '-')}</div>
                    <div class="col-5 text-muted">Email</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.email || '-')}</div>
                    <div class="col-5 text-muted">Phone</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.phone || '-')}</div>
                    <div class="col-5 text-muted">Address</div><div class="col-7 fw-semibold">${escapeWalletHtml(w.address || '-')}</div>
                  </div>
                </div>
                <div class="col-12"><hr class="my-2"></div>
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-2"><span class="fas fa-sitemap me-2"></span>Main / Sub-providers</h6>
                  <div class="border rounded-3 bg-light-subtle p-3">
                    <p class="mb-2 small"><span class="text-muted">Relationship:</span> <span class="badge ${isSubProvider ? 'bg-success' : linkedSubProviders.length ? 'bg-primary' : 'bg-secondary'}">${hierarchyLabel}</span></p>
                    <p class="mb-2 small"><span class="text-muted">Main Provider:</span> <span class="fw-semibold">${escapeWalletHtml(mainProviderName)}</span></p>
                    ${isSubProvider ? `<p class="mb-2 small"><span class="text-muted">Current Provider:</span> <span class="fw-semibold">${escapeWalletHtml(w.provider_name || '-')}</span></p>` : ''}
                    <p class="mb-1 small"><span class="text-muted">Linked Sub-providers:</span></p>
                    ${childrenHtml}
                  </div>
                </div>
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-2"><span class="fas fa-palette me-2"></span>Ticket Variants (${w.variant_count || 0})</h6>
                  ${variantHtml}
                </div>
              </div>
            `;

            // Wire footer action buttons
            const txBtn = document.getElementById('viewWalletTransactionsBtn');
            const editBtn = document.getElementById('editWalletFromViewBtn');
            const adjBtn = document.getElementById('adjustWalletFromViewBtn');
            if (txBtn) txBtn.onclick = () => {
                viewWalletModal.hide();
                window.location.href = `${window.BASE_URL}/admin/wallet/wallet-transactions`;
            };
            if (editBtn) editBtn.onclick = () => { viewWalletModal.hide(); editWallet(walletId); };
            if (adjBtn) adjBtn.onclick = () => { viewWalletModal.hide(); adjustBalance(walletId); };
        })
        .catch(error => {
            console.error('Error loading wallet details:', error);
            body.innerHTML = `<div class="alert alert-danger">Failed to load wallet details: ${error.message}</div>`;
        });
}

// Manage Provider Wallets modal
function openManageProviderWallets(providerId, branchId, providerName, branchName) {
    if (!manageProviderWalletsModal) {
        const el = document.getElementById('manageProviderWalletsModal');
        if (el) manageProviderWalletsModal = new bootstrap.Modal(el);
    }
    if (!manageProviderWalletsModal) return;

    document.getElementById('manageProviderWalletsProviderId').value = providerId;
    document.getElementById('manageProviderWalletsBranchId').value = branchId;
    document.getElementById('manageProviderWalletsModalLabel').innerHTML = '<span class="fas fa-wallet me-2"></span>Manage Provider Wallets';
    document.getElementById('manageProviderWalletsSubtext').textContent = (providerName || 'Provider') + ' / ' + (branchName || 'Branch');

    manageProviderWalletsModal.show();
    loadManageProviderWalletsTable(providerId, branchId);
}

async function loadManageProviderWalletsTable(providerId, branchId) {
    const tbody = document.querySelector('#manageProviderWalletsTable tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading wallets...</td></tr>';

    try {
        const [variantsRes, walletsRes] = await Promise.all([
            fetch(`${window.BASE_URL}/api/ticket-variants?provider_id=${encodeURIComponent(providerId)}`),
            fetch(`${window.BASE_URL}/api/wallets?provider_id=${encodeURIComponent(providerId)}&branch_id=${encodeURIComponent(branchId)}`)
        ]);

        const variantsResult = await variantsRes.json();
        const walletsResult = await walletsRes.json();

        const variants = (variantsResult.success && variantsResult.data) ? variantsResult.data : [];
        const wallets = (walletsResult.success && walletsResult.data && walletsResult.data.wallets) ? walletsResult.data.wallets : [];

        renderManageProviderWalletsTable(providerId, branchId, variants, wallets);
    } catch (error) {
        console.error('Error loading provider wallets table:', error);
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Failed to load wallets: ${error.message}</td></tr>`;
    }
}

function renderManageProviderWalletsTable(providerId, branchId, variants, wallets) {
    const tbody = document.querySelector('#manageProviderWalletsTable tbody');
    tbody.innerHTML = '';

    const walletMap = {};
    wallets.forEach(w => {
        const key = w.variant_id === null ? 'provider-level' : String(w.variant_id);
        walletMap[key] = w;
    });

    // Provider-level wallet row
    const providerWallet = walletMap['provider-level'];
    tbody.insertAdjacentHTML('beforeend', renderManageWalletRow(providerId, branchId, null, 'Provider-level wallet (no variant)', '#6c757d', null, providerWallet));

    // Variant wallet rows
    variants.forEach(v => {
        const key = String(v.variant_id);
        const wallet = walletMap[key];
        const label = v.variant_name ? `${v.variant_name} (${v.variant_code})` : v.variant_code;
        tbody.insertAdjacentHTML('beforeend', renderManageWalletRow(providerId, branchId, v.variant_id, label, v.display_color || '#0d6efd', v.variant_code, wallet));
    });

    if (variants.length === 0) {
        tbody.insertAdjacentHTML('beforeend', '<tr><td colspan="5" class="text-center text-muted py-4">No configured variants for this provider.</td></tr>');
    }
}

function renderManageWalletRow(providerId, branchId, variantId, variantLabel, color, variantCode, wallet) {
    const hasWallet = !!wallet;
    const statusBadge = hasWallet
        ? (wallet.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>')
        : '<span class="badge bg-light text-muted border">No wallet</span>';
    const isRowVariant = hasWallet && wallet.variant_id;
    const balanceHtml = hasWallet
        ? (isRowVariant ? `${Number(wallet.on_hand_qty || 0)} tickets` : formatCurrency(wallet.current_balance))
        : '<span class="text-muted">-</span>';
    const minBalanceHtml = hasWallet
        ? (isRowVariant ? `${Number(wallet.min_balance || 0)} tickets` : formatCurrency(wallet.min_balance))
        : '<span class="text-muted">-</span>';
    const colorStyle = variantCode ? `style="background:${color};color:#fff;"` : '';
    const variantBadge = variantCode ? `<span class="badge rounded-pill me-1" ${colorStyle}>${variantCode}</span>` : '';

    let actions = '';
    if (hasWallet) {
        actions = `
            <button type="button" class="btn btn-sm btn-outline-info" onclick="adjustWalletFromManage(${wallet.wallet_id})" title="${isRowVariant ? 'Adjust Stock' : 'Adjust Balance'}"><span class="fas fa-exchange-alt"></span></button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewWalletFromManage(${wallet.wallet_id})" title="View Wallet"><span class="fas fa-eye"></span></button>
        `;
    } else {
        actions = `
            <button type="button" class="btn btn-sm btn-outline-success" onclick="createWalletForVariantFromManage(${providerId}, ${branchId}, ${variantId === null ? 'null' : variantId})" title="Create Wallet"><span class="fas fa-plus"></span></button>
        `;
    }

    const balanceClass = hasWallet
        ? ((isRowVariant ? Number(wallet.on_hand_qty || 0) : Number(wallet.current_balance || 0)) >= 0 ? 'text-success' : 'text-danger')
        : '';

    return `
        <tr>
            <td>${variantBadge}${variantLabel}</td>
            <td>${statusBadge}</td>
            <td class="text-end fw-semibold ${balanceClass}">${balanceHtml}</td>
            <td class="text-end">${minBalanceHtml}</td>
            <td class="text-end">${actions}</td>
        </tr>
    `;
}

function adjustWalletFromManage(walletId) {
    if (manageProviderWalletsModal) manageProviderWalletsModal.hide();
    adjustBalance(walletId);
}

function viewWalletFromManage(walletId) {
    if (manageProviderWalletsModal) manageProviderWalletsModal.hide();
    viewWallet(walletId);
}

function createWalletForVariantFromManage(providerId, branchId, variantId) {
    if (manageProviderWalletsModal) manageProviderWalletsModal.hide();
    openAddWalletModal(providerId, branchId, variantId);
}

function createProviderLevelWalletFromManage() {
    const providerId = document.getElementById('manageProviderWalletsProviderId').value;
    const branchId = document.getElementById('manageProviderWalletsBranchId').value;
    if (!providerId || !branchId) return;
    if (manageProviderWalletsModal) manageProviderWalletsModal.hide();
    openAddWalletModal(providerId, branchId, '');
}

// Print wallets report
function printWallets() {
    // Get filter info
    const provider = document.querySelector('select[name="provider"]');
    const branch = document.querySelector('select[name="branch"]');
    const status = document.querySelector('select[name="status"]');
    const search = document.getElementById('walletSearch');

    let filterParts = [];
    if (provider && provider.value) filterParts.push(`Provider: ${provider.options[provider.selectedIndex].text}`);
    if (branch && branch.value) filterParts.push(`Branch: ${branch.options[branch.selectedIndex].text}`);
    if (status && status.value) filterParts.push(`Status: ${status.options[status.selectedIndex].text}`);
    if (search && search.value) filterParts.push(`Search: ${search.value}`);
    const filterDisplay = filterParts.length > 0 ? filterParts.join(' | ') : 'All wallets';

    // Get stats from page
    const statCards = document.querySelectorAll('.card-body .fs-5, .card-body .fs-5.fw-bold');
    const statTotal = statCards[0]?.textContent?.trim() || '0';
    const statActive = statCards[1]?.textContent?.trim() || '0';
    const statInactive = statCards[2]?.textContent?.trim() || '0';
    const statBalance = statCards[3]?.textContent?.trim() || '₱0.00';

    // Build table from visible wallet cards
    let tableHTML = '';
    let visibleCount = 0;
    const cards = document.querySelectorAll('#walletCardsContainer > .col-sm-6, #walletCardsContainer > .col-md-4');
    cards.forEach(card => {
        if (card.style.display === 'none') return;
        visibleCount++;
        const walletName = card.querySelector('h6')?.textContent?.trim() || '';
        const balance = card.querySelector('.display-4.fs-5')?.textContent?.trim() || '₱0.00';
        const branch = card.querySelector('.wallet-card-branch')?.dataset.branchName
            || card.querySelector('.wallet-card-branch')?.textContent?.trim()
            || '';
        const statusSwitch = card.querySelector('.wallet-status-switch');
        const statusLabel = statusSwitch?.nextElementSibling?.textContent?.trim() || 'Active';
        const statusBadge = statusSwitch?.checked 
            ? '<span style="color: #198754; font-weight: 600;">Active</span>' 
            : '<span style="color: #dc3545; font-weight: 600;">Inactive</span>';

        tableHTML += `
            <tr>
                <td>${walletName}</td>
                <td>${branch}</td>
                <td style="text-align: right; font-weight: 600;">${balance}</td>
                <td style="text-align: center;">${statusBadge}</td>
            </tr>`;
    });

    if (visibleCount === 0) {
        tableHTML = `<tr><td colspan="4" style="text-align: center; padding: 20px;">No wallets to display</td></tr>`;
    }

    // Current date and time
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true });

    // Company info
    const company = window.COMPANY_INFO || {};
    const logoUrl = company.logo || `${window.BASE_URL}/api/images/logo/logo_1779670787_4364a51c.png`;

    const printHTML = `<!DOCTYPE html>
<html>
<head>
    <title>Provider Wallets Report</title>
    <style>
        @media print {
            @page { size: letter; margin: 0.5cm; }
            body {
                font-family: 'Century Gothic', CenturyGothic, AppleGothic, Arial, sans-serif;
                margin: 0; padding: 0; font-size: 8pt; color: #000;
            }
            .print-header {
                display: flex; align-items: center; justify-content: space-between;
                margin-bottom: 12px; border-bottom: 1px solid #000; padding-bottom: 8px;
            }
            .print-header .logo-section { flex: 0 0 auto; text-align: left; }
            .print-header img { max-height: 50px; max-width: 120px; object-fit: contain; }
            .print-header .text-section { flex: 1; text-align: right; padding-left: 20px; }
            .print-header h2 { margin: 0; font-size: 14pt; font-weight: 700; color: #000; }
            .print-header .description { margin-top: 4px; font-size: 8pt; color: #000; font-style: italic; }
            .print-header .meta { margin-top: 4px; font-size: 7pt; color: #000; }
            .stats-row {
                display: flex; justify-content: space-between; gap: 10px;
                margin: 10px 0; border: 1px solid #000; padding: 8px;
            }
            .stat-box { flex: 1; text-align: center; border-right: 1px solid #000; }
            .stat-box:last-child { border-right: none; }
            .stat-label { font-size: 7pt; color: #000; margin-bottom: 2px; }
            .stat-value { font-size: 11pt; font-weight: 700; color: #000; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #000; padding: 3px 5px; text-align: left; }
            th { background: #f0f0f0; font-weight: 600; text-align: center; font-size: 7pt; color: #000; }
            td { font-size: 7pt; color: #000; }
            .text-end { text-align: right !important; }
            .text-center { text-align: center !important; }
            .print-footer { margin-top: 25px; border-top: 1px solid #000; padding-top: 12px; }
            .footer-info { margin-top: 15px; font-size: 6pt; color: #666; text-align: center; }
            .signatures { display: flex; justify-content: space-between; gap: 15px; }
            .sig-block { flex: 1; text-align: center; }
            .sig-line { border-bottom: 1px solid #000; height: 25px; margin-bottom: 4px; }
            .sig-label { font-size: 7pt; color: #000; }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <div class="logo-section">
            <img src="${logoUrl}" alt="Logo" onerror="this.style.display='none'" />
        </div>
        <div class="text-section">
            <h2>PROVIDER WALLETS REPORT</h2>
            <div class="description">Summary of all provider wallets and balances</div>
            <div class="meta">${filterDisplay}</div>
        </div>
    </div>
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Total Wallets</div>
            <div class="stat-value">${statTotal}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Active</div>
            <div class="stat-value">${statActive}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Inactive</div>
            <div class="stat-value">${statInactive}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Balance</div>
            <div class="stat-value">${statBalance}</div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Wallet</th>
                <th>Branch</th>
                <th class="text-end">Balance</th>
                <th class="text-center">Status</th>
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
            <strong>${company.name || 'TMS'}</strong><br>
            ${company.address || ''}<br>
            ${company.contact ? 'Contact: ' + company.contact : ''}${company.tin ? ' | TIN: ' + company.tin : ''}<br>
            Generated on ${dateStr} at ${timeStr}
        </div>
    </div>
</body>
</html>`;

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

// Filter wallets
function filterWallets(status) {
    const rows = document.querySelectorAll('#walletsTable tbody tr');
    rows.forEach(row => {
        const statusCell = row.querySelector('td:nth-child(5) .badge');
        if (status === 'all' || statusCell.textContent.toLowerCase() === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Toast notification
function showToast(type, title, message) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `custom-toast alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 350px; max-width: 450px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px;';
    
    const icon = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
    
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <span class="fas ${icon} me-3 fs-4"></span>
            <div class="flex-grow-1">
                <strong class="d-block">${title}</strong>
                <span class="d-block text-sm">${message}</span>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 4000);
}

// Currency formatting helpers
function formatNumberValue(value) {
    const raw = String(value || '').replace(/,/g, '');
    const num = parseFloat(raw);
    if (isNaN(num)) return '0.00';
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrencyInput(input) {
    if (!input) return;
    let isFormatting = false;

    function formatValue(cursorPos) {
        isFormatting = true;
        let value = input.value;
        if (value === '') {
            if (typeof cursorPos !== 'number') {
                input.value = '0.00';
            }
            isFormatting = false;
            return;
        }

        let raw = value.replace(/[^0-9.]/g, '');
        const firstDot = raw.indexOf('.');
        if (firstDot !== -1) {
            raw = raw.substring(0, firstDot + 1) + raw.substring(firstDot + 1).replace(/\./g, '');
        }

        const intPart = firstDot === -1 ? raw : raw.substring(0, firstDot);
        let cleanInt = intPart.replace(/^0+(?=\d)/, '');
        if (cleanInt === '') cleanInt = '0';
        const decPart = firstDot === -1 ? '' : raw.substring(firstDot, firstDot + 3);
        const formattedInt = parseInt(cleanInt, 10).toLocaleString('en-US');
        const newValue = formattedInt + decPart;
        input.value = newValue;

        if (typeof cursorPos === 'number') {
            const before = value.substring(0, Math.min(cursorPos, value.length));
            const digitCount = (before.match(/\d/g) || []).length;
            const dotCount = (before.match(/\./g) || []).length;
            let seenDigits = 0;
            let seenDots = 0;
            let newCursor = newValue.length;
            for (let i = 0; i < newValue.length; i++) {
                if (/\d/.test(newValue[i])) seenDigits++;
                if (newValue[i] === '.') seenDots++;
                if (seenDigits === digitCount && seenDots === dotCount) {
                    newCursor = i + 1;
                    break;
                }
            }
            input.setSelectionRange(newCursor, newCursor);
        } else {
            input.value = formatNumberValue(input.value);
        }

        isFormatting = false;
    }

    input.addEventListener('focus', function() {
        if (isFormatting) return;
        isFormatting = true;
        input.value = input.value.replace(/,/g, '');
        isFormatting = false;
    });

    input.addEventListener('input', function() {
        if (isFormatting) return;
        formatValue(input.selectionStart);
    });

    input.addEventListener('blur', function() {
        if (isFormatting) return;
        formatValue(null);
    });

    formatValue(input.value.length);
}

function formatIntegerInput(input) {
    if (!input) return;
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

function setAddWalletInitialInputMode(isVariant) {
    const input = document.getElementById('addInitialBalance');
    const label = document.getElementById('addInitialBalanceLabel');
    const symbol = document.getElementById('addInitialBalanceSymbol');
    if (!input || !label || !symbol) return;

    // Replace the input to strip old currency listeners
    const parent = input.parentElement;
    const newInput = document.createElement('input');
    newInput.id = 'addInitialBalance';
    newInput.name = isVariant ? 'initial_ticket_count' : 'initial_balance';
    newInput.type = 'text';
    newInput.className = 'form-control ' + (isVariant ? '' : 'number-format');
    newInput.inputMode = isVariant ? 'numeric' : 'decimal';
    newInput.placeholder = isVariant ? '0' : '0.00';
    newInput.value = isVariant ? '0' : '0.00';
    newInput.autocomplete = 'off';
    parent.replaceChild(newInput, input);

    if (isVariant) {
        label.textContent = 'Initial Ticket Count';
        symbol.textContent = '';
        formatIntegerInput(newInput);
    } else {
        label.textContent = 'Initial Balance';
        symbol.textContent = '₱';
        formatCurrencyInput(newInput);
    }
    return newInput;
}

function setInputNumberMode(inputId, isVariant, config) {
    const input = document.getElementById(inputId);
    if (!input) return null;
    const parent = input.parentElement;
    if (!parent) return null;
    const symbol = parent.querySelector('.input-group-text');
    const label = document.querySelector('label[for="' + inputId + '"]');
    const help = parent.parentElement?.querySelector('.form-text');

    const newInput = document.createElement('input');
    newInput.id = inputId;
    newInput.name = config.name || input.name || inputId;
    newInput.type = 'text';
    newInput.className = 'form-control ' + (isVariant ? '' : 'number-format');
    newInput.inputMode = isVariant ? 'numeric' : 'decimal';
    newInput.placeholder = isVariant ? (config.placeholderVariant || '0') : (config.placeholder || '0.00');
    newInput.autocomplete = 'off';
    parent.replaceChild(newInput, input);

    if (label) label.textContent = isVariant ? (config.labelVariant || config.label) : config.label;
    if (symbol) symbol.textContent = isVariant ? (config.symbolVariant || '') : (config.symbol || '');
    if (help) help.textContent = isVariant ? (config.helpVariant || config.help || '') : (config.help || '');

    if (isVariant) {
        formatIntegerInput(newInput);
    } else {
        formatCurrencyInput(newInput);
    }
    return newInput;
}

function initCurrencyInputs() {
    formatCurrencyInput(document.getElementById('addInitialBalance'));
    formatCurrencyInput(document.getElementById('addMinBalance'));
    formatCurrencyInput(document.getElementById('adjustAmount'));
    formatCurrencyInput(document.getElementById('editMinBalance'));
}
