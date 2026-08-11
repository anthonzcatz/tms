// Provider Wallets Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Provider Wallets module initialized');
    loadProviders();
    loadBranches();
    initCurrencyInputs();
});

// Initialize Bootstrap modals
let addWalletModal, editWalletModal, adjustBalanceModal, viewWalletModal, manageProviderWalletsModal;
let addWalletPrefill = null; // holds variant_id to preselect when opening Add Wallet

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
    const initialBalanceInput = document.getElementById('addInitialBalance');
    const minBalanceInput = document.getElementById('addMinBalance');
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

    if (existing) {
        if (walletIdInput) walletIdInput.value = existing.wallet_id;
        if (initialBalanceInput) {
            initialBalanceInput.value = formatNumberValue(existing.current_balance);
            initialBalanceInput.disabled = true;
        }
        if (minBalanceInput) minBalanceInput.value = formatNumberValue(existing.min_balance);
        if (statusSelect) statusSelect.value = existing.status;

        setAddWalletKeyFieldsDisabled(true);

        if (alertEl && messageEl) {
            messageEl.textContent = `An existing wallet with a balance of ₱${formatNumberValue(existing.current_balance)} was found. Saving will update its settings.`;
            alertEl.classList.remove('d-none');
        }
    } else {
        if (walletIdInput) walletIdInput.value = '';
        if (initialBalanceInput) {
            initialBalanceInput.disabled = false;
            initialBalanceInput.value = '0.00';
        }
        if (minBalanceInput) minBalanceInput.value = '1,000.00';
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
    const initialBalanceInput = document.getElementById('addInitialBalance');
    const minBalanceInput = document.getElementById('addMinBalance');
    const statusSelect = document.getElementById('addStatus');
    const walletIdInput = document.getElementById('addWalletId');
    const alertEl = document.getElementById('addExistingWalletAlert');

    // Re-enable all key fields and clear any previous existing-wallet state
    if (branchSelect) branchSelect.disabled = false;
    if (providerSelect) providerSelect.disabled = true;
    if (variantSelect) variantSelect.disabled = true;
    if (initialBalanceInput) {
        initialBalanceInput.disabled = false;
        initialBalanceInput.value = '0.00';
    }
    if (minBalanceInput) minBalanceInput.value = '1,000.00';
    if (statusSelect) statusSelect.value = 'active';
    if (walletIdInput) walletIdInput.value = '';
    if (alertEl) alertEl.classList.add('d-none');

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
    const initialBalance = document.getElementById('addInitialBalance').value;
    const minBalance = document.getElementById('addMinBalance').value;
    const status = document.getElementById('addStatus').value;
    const existingWalletId = walletIdInput ? walletIdInput.value : '';

    if (!providerId || !branchId) {
        showToast('warning', 'Warning', 'Please select provider and branch');
        return;
    }

    // If a pre-existing wallet is detected, skip duplicate checks and update it
    if (!existingWalletId) {
        const providerOption = providerSelect.options[providerSelect.selectedIndex];
        if (providerOption && providerOption.disabled) {
            showToast('warning', 'Warning', 'Selected provider has no available wallets for this branch');
            return;
        }

        if (variantSelect) {
            const variantOption = variantSelect.options[variantSelect.selectedIndex];
            if (variantOption && variantOption.disabled) {
                showToast('warning', 'Warning', 'Selected variant already has a wallet for this branch');
                return;
            }
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
                min_balance: parseFloat(String(minBalance).replace(/,/g, '')) || 1000,
                status: status
            });
        } else {
            method = 'POST';
            successMessage = 'Wallet created successfully';
            body = JSON.stringify({
                provider_id: providerId,
                branch_id: branchId,
                variant_id: variantId || null,
                initial_balance: parseFloat(String(initialBalance).replace(/,/g, '')) || 0,
                min_balance: parseFloat(String(minBalance).replace(/,/g, '')) || 1000,
                status: status
            });
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
            location.reload();
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
            const balance = parseFloat(wallet.current_balance);
            document.getElementById('editCurrentBalance').textContent = isNaN(balance)
                ? '0.00'
                : balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('editMinBalance').value = formatNumberValue(wallet.min_balance || 0);

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
        if (minBalance !== '') {
            requestBody.min_balance = parseFloat(String(minBalance).replace(/,/g, ''));
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
            
            // Update the card switch in real-time
            const cardSwitch = document.getElementById(`walletSwitch${walletId}`);
            if (cardSwitch) {
                cardSwitch.checked = statusCheckbox.checked;
                const cardLabel = cardSwitch.nextElementSibling;
                if (cardLabel) {
                    cardLabel.textContent = statusCheckbox.checked ? 'Active' : 'Inactive';
                }
            }
            
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
            
            // Update the label
            const label = switchElement.nextElementSibling;
            if (label) {
                label.textContent = newStatus === 'active' ? 'Active' : 'Inactive';
            }
            
            // Update stats cards
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
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets/stats`);
        const result = await response.json();
        
        if (result.success) {
            // Update total wallets
            const totalEl = document.querySelector('.card-body .fs-5');
            if (totalEl) {
                totalEl.textContent = result.data.total_wallets;
            }
            
            // Update active wallets
            const activeEl = document.querySelectorAll('.card-body .fs-5')[1];
            if (activeEl) {
                activeEl.textContent = result.data.active_wallets;
            }
            
            // Update inactive wallets
            const inactiveEl = document.querySelectorAll('.card-body .fs-5')[2];
            if (inactiveEl) {
                inactiveEl.textContent = result.data.inactive_wallets;
            }
            
            // Update total balance
            const balanceEl = document.querySelectorAll('.card-body .fs-5')[3];
            if (balanceEl) {
                balanceEl.textContent = `₱${parseFloat(result.data.total_balance).toFixed(2)}`;
            }
        }
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

// Adjust balance
async function adjustBalance(walletId) {
    document.getElementById('adjustWalletId').value = walletId;

    const amountInput = document.getElementById('adjustAmount');
    if (amountInput) amountInput.value = '0.00';
    document.getElementById('adjustDirection').value = '';
    document.getElementById('adjustRemarks').value = '';

    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await response.json();

        if (result.success && result.data) {
            const wallet = result.data;
            document.getElementById('adjustProviderName').textContent = wallet.provider_name || '-';
            document.getElementById('adjustBranchName').textContent = wallet.branch_name || '-';
            const variantLabel = wallet.variant_name
                ? `${wallet.variant_name}${wallet.variant_code ? ` (${wallet.variant_code})` : ''}`
                : 'Provider-level (no variant)';
            document.getElementById('adjustVariantName').textContent = variantLabel;
            const balance = parseFloat(wallet.current_balance);
            document.getElementById('adjustCurrentBalance').textContent = isNaN(balance)
                ? '0.00'
                : balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            adjustBalanceModal.show();
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
    
    if (!direction || !amount) {
        showToast('warning', 'Warning', 'Please fill direction and amount');
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
        
        const response = await fetch(`${window.BASE_URL}/api/wallet-transactions`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                wallet_id: walletId,
                txn_type: 'ADJUSTMENT',
                direction: direction,
                amount: parseFloat(String(amount).replace(/,/g, '')),
                remarks: remarks
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Balance adjusted successfully');
            adjustBalanceModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to adjust balance');
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
                variantHtml = `<div class="table-responsive"><table class="table table-sm table-borderless mb-0"><tbody>` + variants.map(v => `
                    <tr>
                        <td style="width:30px;"><span class="d-inline-block rounded" style="width:16px;height:16px;background:${v.color_code || '#0d6efd'};border:1px solid #dee2e6;"></span></td>
                        <td class="fw-semibold">${v.variant_name} <small class="text-muted">(${v.variant_code || '-'})</small></td>
                        <td class="text-end"><span class="badge ${v.is_active ? 'bg-success' : 'bg-secondary'}">${v.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td class="text-end small text-muted">On hand: ${v.on_hand_qty || 0}</td>
                        <td class="text-end small text-muted">Reserved: ${v.reserved_qty || 0}</td>
                        <td class="text-end small text-muted">Avail: ${v.available_qty || 0}</td>
                    </tr>
                `).join('') + `</tbody></table></div>`;
            } else {
                variantHtml = `<span class="text-muted">No variants configured</span>`;
            }

            let childrenHtml = '';
            if (w.child_provider_names) {
                childrenHtml = `<div class="d-flex flex-wrap gap-1">` + w.child_provider_names.split(',').map(n => `<span class="badge bg-light text-dark border">${n.trim()}</span>`).join('') + `</div>`;
            } else {
                childrenHtml = `<span class="text-muted">No linked sub-providers</span>`;
            }

            const providerType = w.provider_type ? w.provider_type.charAt(0).toUpperCase() + w.provider_type.slice(1) : '-';

            body.innerHTML = `
              <div class="row g-3">
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-3"><span class="fas fa-wallet me-2"></span>Wallet</h6>
                  <div class="row g-2 small">
                    <div class="col-5 text-muted">Wallet Name</div><div class="col-7 fw-semibold">${w.wallet_name || '-'}</div>
                    <div class="col-5 text-muted">Branch</div><div class="col-7 fw-semibold">${w.branch_name || '-'}</div>
                    <div class="col-5 text-muted">Current Balance</div><div class="col-7 fw-bold ${(w.current_balance || 0) >= 0 ? 'text-success' : 'text-danger'}">${formatCurrency(w.current_balance)}</div>
                    <div class="col-5 text-muted">Min. Balance</div><div class="col-7 fw-semibold">${formatCurrency(w.min_balance)}</div>
                    <div class="col-5 text-muted">Status</div><div class="col-7"><span class="badge ${w.status === 'active' ? 'bg-success' : 'bg-danger'}">${w.status || '-'}</span></div>
                    <div class="col-5 text-muted">Created</div><div class="col-7 fw-semibold">${formatDate(w.created_at)}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-3"><span class="fas fa-building me-2"></span>Provider</h6>
                  <div class="row g-2 small">
                    <div class="col-5 text-muted">Provider Name</div><div class="col-7 fw-semibold">${w.provider_name || '-'}</div>
                    <div class="col-5 text-muted">Provider Code</div><div class="col-7 fw-semibold">${w.provider_code || '-'}</div>
                    <div class="col-5 text-muted">Type</div><div class="col-7 fw-semibold">${providerType}</div>
                    <div class="col-5 text-muted">Contact Person</div><div class="col-7 fw-semibold">${w.contact_person || '-'}</div>
                    <div class="col-5 text-muted">Email</div><div class="col-7 fw-semibold">${w.email || '-'}</div>
                    <div class="col-5 text-muted">Phone</div><div class="col-7 fw-semibold">${w.phone || '-'}</div>
                    <div class="col-5 text-muted">Address</div><div class="col-7 fw-semibold">${w.address || '-'}</div>
                  </div>
                </div>
                <div class="col-12"><hr class="my-2"></div>
                <div class="col-md-6">
                  <h6 class="text-primary fw-bold mb-2"><span class="fas fa-sitemap me-2"></span>Main / Sub-providers</h6>
                  ${w.parent_provider_id ? `<p class="mb-1 small"><span class="text-muted">Main Provider:</span> <span class="fw-semibold">${w.parent_provider_name || 'Main Provider'}</span></p>` : ''}
                  <p class="mb-0 small"><span class="text-muted">Linked Sub-providers:</span></p>
                  ${childrenHtml}
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
    const balanceHtml = hasWallet ? formatCurrency(wallet.current_balance) : '<span class="text-muted">-</span>';
    const minBalanceHtml = hasWallet ? formatCurrency(wallet.min_balance) : '<span class="text-muted">-</span>';
    const colorStyle = variantCode ? `style="background:${color};color:#fff;"` : '';
    const variantBadge = variantCode ? `<span class="badge rounded-pill me-1" ${colorStyle}>${variantCode}</span>` : '';

    let actions = '';
    if (hasWallet) {
        actions = `
            <button type="button" class="btn btn-sm btn-outline-info" onclick="adjustWalletFromManage(${wallet.wallet_id})" title="Adjust Balance"><span class="fas fa-exchange-alt"></span></button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewWalletFromManage(${wallet.wallet_id})" title="View Wallet"><span class="fas fa-eye"></span></button>
        `;
    } else {
        actions = `
            <button type="button" class="btn btn-sm btn-outline-success" onclick="createWalletForVariantFromManage(${providerId}, ${branchId}, ${variantId === null ? 'null' : variantId})" title="Create Wallet"><span class="fas fa-plus"></span></button>
        `;
    }

    const balanceClass = hasWallet ? (wallet.current_balance >= 0 ? 'text-success' : 'text-danger') : '';

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
        const branch = card.querySelector('.fa-building')?.parentElement?.textContent?.trim() || '';
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

function initCurrencyInputs() {
    formatCurrencyInput(document.getElementById('addInitialBalance'));
    formatCurrencyInput(document.getElementById('addMinBalance'));
    formatCurrencyInput(document.getElementById('adjustAmount'));
    formatCurrencyInput(document.getElementById('editMinBalance'));
}
