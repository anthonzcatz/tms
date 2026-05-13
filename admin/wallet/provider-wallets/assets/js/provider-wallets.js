// Provider Wallets Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Provider Wallets module initialized');
    loadProviders();
    loadBranches();
});

// Initialize Bootstrap modals
let addWalletModal, editWalletModal, adjustBalanceModal;

document.addEventListener('DOMContentLoaded', function() {
    addWalletModal = new bootstrap.Modal(document.getElementById('addWalletModal'));
    editWalletModal = new bootstrap.Modal(document.getElementById('editWalletModal'));
    adjustBalanceModal = new bootstrap.Modal(document.getElementById('adjustBalanceModal'));
});

// Load providers for dropdown
async function loadProviders() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`);
        const result = await response.json();
        
        if (result.success) {
            const select = document.getElementById('addProviderId');
            select.innerHTML = '<option value="">Select Provider</option>';
            result.data.providers.forEach(provider => {
                select.innerHTML += `<option value="${provider.provider_id}">${provider.provider_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
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
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Open add wallet modal
function openAddWalletModal() {
    document.getElementById('addWalletForm').reset();
    addWalletModal.show();
}

// Save wallet
async function saveWallet() {
    const providerId = document.getElementById('addProviderId').value;
    const branchId = document.getElementById('addBranchId').value;
    const initialBalance = document.getElementById('addInitialBalance').value;
    const status = document.getElementById('addStatus').value;
    
    if (!providerId || !branchId) {
        showToast('warning', 'Warning', 'Please select provider and branch');
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
        
        const response = await fetch(`${window.BASE_URL}/api/wallets`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                provider_id: providerId,
                branch_id: branchId,
                initial_balance: parseFloat(initialBalance) || 0,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Wallet created successfully');
            addWalletModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to create wallet');
        }
    } catch (error) {
        console.error('Error saving wallet:', error);
        showToast('error', 'Error', 'Failed to create wallet: ' + error.message);
    }
}

// Edit wallet
async function editWallet(walletId) {
    document.getElementById('editWalletId').value = walletId;
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await response.json();
        
        if (result.success) {
            const wallet = result.data;
            document.getElementById('editProviderName').textContent = wallet.provider_name || '-';
            document.getElementById('editBranchName').textContent = wallet.branch_name || '-';
            document.getElementById('editCurrentBalance').textContent = parseFloat(wallet.current_balance).toFixed(2);
            
            // Set current status
            document.getElementById('editStatus').checked = wallet.status === 'active';
            updateStatusLabel(wallet.status === 'active');
        }
    } catch (error) {
        console.error('Error loading wallet details:', error);
        
        // Fallback to card switch if API fails
        const cardSwitch = document.getElementById(`walletSwitch${walletId}`);
        if (cardSwitch) {
            const isCurrentlyActive = cardSwitch.checked;
            document.getElementById('editStatus').checked = isCurrentlyActive;
            updateStatusLabel(isCurrentlyActive);
        }
    }
    
    editWalletModal.show();
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
                status: status
            })
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
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/wallets?id=${walletId}`);
        const result = await response.json();
        
        if (result.success) {
            const wallet = result.data;
            document.getElementById('adjustProviderName').textContent = wallet.provider_name || '-';
            document.getElementById('adjustBranchName').textContent = wallet.branch_name || '-';
            document.getElementById('adjustCurrentBalance').textContent = parseFloat(wallet.current_balance).toFixed(2);
        }
    } catch (error) {
        console.error('Error loading wallet details:', error);
    }
    
    adjustBalanceModal.show();
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
                amount: parseFloat(amount),
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

// View wallet
function viewWallet(walletId) {
    window.location.href = `${window.BASE_URL}/admin/wallet/wallet-transactions?wallet_id=${walletId}`;
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
