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
    const encodedWalletId = IdEncoder.encode(walletId);
    window.location.href = `${window.BASE_URL}/admin/wallet/wallet-transactions?wallet_id=${encodedWalletId}`;
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
