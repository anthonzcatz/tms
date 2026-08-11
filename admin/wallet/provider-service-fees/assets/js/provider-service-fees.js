// Provider Service Fees Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Provider Service Fees module initialized');
    loadProviders();
    loadBranches();
});

// Initialize Bootstrap modals
let addFeeModal, editFeeModal;
let currentEditFeeId = null; // Store current fee ID being edited

document.addEventListener('DOMContentLoaded', function() {
    addFeeModal = new bootstrap.Modal(document.getElementById('addFeeModal'));
    editFeeModal = new bootstrap.Modal(document.getElementById('editFeeModal'));

    ['addBranchId', 'addProviderId'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', checkExistingFee);
    });

    const addFeeTypeSelect = document.getElementById('addFeeType');
    if (addFeeTypeSelect) {
        addFeeTypeSelect.addEventListener('change', function() {
            updateAddFeeTypeUI(this.value);
            checkExistingFee();
        });
    }

    const editFeeTypeSelect = document.getElementById('editFeeType');
    if (editFeeTypeSelect) {
        editFeeTypeSelect.addEventListener('change', function() {
            updateEditFeeTypeUI(this.value);
        });
    }

    const addStatusSwitch = document.getElementById('addStatus');
    if (addStatusSwitch) {
        addStatusSwitch.addEventListener('change', function() {
            updateAddStatusLabel(this.checked);
        });
    }

    const editStatusSwitch = document.getElementById('editStatus');
    if (editStatusSwitch) {
        editStatusSwitch.addEventListener('change', function() {
            updateEditStatusLabel(this.checked);
        });
    }
});

// Load main providers for dropdown (sub-providers do not have their own service fees)
async function loadProviders() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`);
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('addProviderId');
            select.innerHTML = '<option value="">Select Main Provider</option>';
            result.data.providers.forEach(provider => {
                if (provider.parent_provider_id) return;
                const encodedId = IdEncoder.encode(provider.provider_id);
                select.innerHTML += `<option value="${encodedId}">${provider.provider_name}</option>`;
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
                const encodedId = IdEncoder.encode(branch.branch_id);
                select.innerHTML += `<option value="${encodedId}">${branch.branch_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Open add fee modal
function openAddFeeModal() {
    // Clear form fields manually since form tag was removed
    document.getElementById('addProviderId').value = '';
    document.getElementById('addBranchId').value = '';
    document.getElementById('addFeeType').value = 'FIXED';
    document.getElementById('addFeeAmount').value = '';

    const statusSwitch = document.getElementById('addStatus');
    if (statusSwitch) {
        statusSwitch.checked = true;
        updateAddStatusLabel(true);
    }

    updateAddFeeTypeUI('FIXED');

    const alertEl = document.getElementById('addExistingFeeAlert');
    if (alertEl) alertEl.classList.add('d-none');

    addFeeModal.show();
}

// Update add status label based on switch state
function updateAddStatusLabel(isActive) {
    const label = document.getElementById('addStatusLabel');
    if (label) {
        label.innerHTML = isActive
            ? '<span class="text-success fw-bold">Active</span>'
            : '<span class="text-muted">Inactive</span>';
    }
}

// Update edit status label based on switch state
function updateEditStatusLabel(isActive) {
    const label = document.getElementById('editStatusLabel');
    if (label) {
        label.innerHTML = isActive
            ? '<span class="text-success fw-bold">Active</span>'
            : '<span class="text-muted">Inactive</span>';
    }
}

// Update Add modal Fee Amount label/placeholder based on fee type
function updateAddFeeTypeUI(feeType) {
    const label = document.getElementById('addFeeAmountLabel');
    const input = document.getElementById('addFeeAmount');
    if (!label || !input) return;
    if (feeType === 'PERCENT') {
        label.textContent = 'Fee Percentage (%)';
        input.placeholder = '0';
    } else {
        label.textContent = 'Fee Amount';
        input.placeholder = '0.00';
    }
}

// Update Edit modal Fee Amount label/placeholder based on fee type
function updateEditFeeTypeUI(feeType) {
    const label = document.getElementById('editFeeAmountLabel');
    const input = document.getElementById('editFeeAmount');
    if (!label || !input) return;
    if (feeType === 'PERCENT') {
        label.textContent = 'Fee Percentage (%)';
        input.placeholder = '0';
    } else {
        label.textContent = 'Fee Amount';
        input.placeholder = '0.00';
    }
}

// Check if a fee already exists for the selected branch/provider/type and pre-fill it
async function checkExistingFee() {
    const providerId = document.getElementById('addProviderId').value;
    const branchId = document.getElementById('addBranchId').value;
    const feeType = document.getElementById('addFeeType').value;

    updateAddFeeTypeUI(feeType);

    const alertEl = document.getElementById('addExistingFeeAlert');
    const messageEl = document.getElementById('addExistingFeeMessage');

    if (!providerId || !branchId || !feeType) {
        if (alertEl) alertEl.classList.add('d-none');

        const amountInput = document.getElementById('addFeeAmount');
        if (amountInput) amountInput.value = '';

        const statusSwitch = document.getElementById('addStatus');
        if (statusSwitch) {
            statusSwitch.checked = true;
            updateAddStatusLabel(true);
        }
        return;
    }

    try {
        const params = new URLSearchParams({ provider_id: providerId, branch_id: branchId, fee_type: feeType, include_inactive: '1' });
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees?${params.toString()}`);
        const result = await response.json();

        if (result.success && result.data && result.data.fees && result.data.fees.length > 0) {
            const fee = result.data.fees[0];
            document.getElementById('addFeeAmount').value = (fee.fee_value !== null && fee.fee_value !== undefined) ? fee.fee_value : '';

            const statusSwitch = document.getElementById('addStatus');
            if (statusSwitch) {
                statusSwitch.checked = !!fee.is_active;
                updateAddStatusLabel(!!fee.is_active);
            }

            if (alertEl && messageEl) {
                const displayValue = (fee.fee_value !== null && fee.fee_value !== undefined) ? fee.fee_value : 0;
                messageEl.textContent = `An existing ${fee.fee_type.toLowerCase()} fee of ${fee.fee_type === 'PERCENT' ? displayValue + '%' : '₱' + (parseFloat(displayValue).toFixed(2))} was found. Saving will update it.`;
                alertEl.classList.remove('d-none');
            }
        } else {
            if (alertEl) alertEl.classList.add('d-none');

            // No existing fee for the new combination — clear pre-filled amount and reset status
            const amountInput = document.getElementById('addFeeAmount');
            if (amountInput) amountInput.value = '';

            const statusSwitch = document.getElementById('addStatus');
            if (statusSwitch) {
                statusSwitch.checked = true;
                updateAddStatusLabel(true);
            }
        }
    } catch (error) {
        console.error('Error checking existing fee:', error);
    }
}

// Save fee
async function saveFee() {
    const providerId = document.getElementById('addProviderId').value;
    const branchId = document.getElementById('addBranchId').value;
    const feeType = document.getElementById('addFeeType').value;
    const amountInput = document.getElementById('addFeeAmount');
    const rawAmount = String(amountInput.value).replace(/,/g, '');
    const feeAmount = rawAmount ? parseFloat(rawAmount) : 0;
    const status = document.getElementById('addStatus').checked ? 'active' : 'inactive';

    if (!providerId || !branchId || !feeType) {
        showToast('warning', 'Warning', 'Please select branch, provider and fee type');
        return;
    }

    if (rawAmount !== '' && isNaN(parseFloat(rawAmount))) {
        showToast('warning', 'Warning', 'Please enter a valid fee amount');
        amountInput.focus();
        return;
    }

    if (feeAmount < 0) {
        showToast('warning', 'Warning', 'Fee amount cannot be negative');
        amountInput.focus();
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

        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                provider_id: providerId,
                branch_id: branchId,
                fee_type: feeType,
                fee_value: feeAmount,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', result.message || 'Service fee saved successfully');
            addFeeModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to save service fee');
        }
    } catch (error) {
        console.error('Error saving fee:', error);
        showToast('error', 'Error', 'Failed to save service fee: ' + error.message);
    }
}

// Edit fee
async function editFee(feeId) {
    if (!feeId) {
        showToast('error', 'Error', 'Missing fee ID');
        return;
    }

    // Load providers and branches for edit modal
    await loadProvidersForEdit();
    await loadBranchesForEdit();

    try {
        const encodedFeeId = IdEncoder.encode(feeId);
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees?id=${encodedFeeId}`);
        const result = await response.json();

        if (result.success) {
            const fee = result.data;
            console.log('Loaded fee data:', fee);
            console.log('fee.fee_id:', fee.fee_id);
            console.log('fee.fee_id type:', typeof fee.fee_id);

            // Store feeId in global variable
            currentEditFeeId = fee.fee_id;
            console.log('Set currentEditFeeId to:', currentEditFeeId);

            const encodedProviderId = IdEncoder.encode(fee.provider_id);
            const encodedBranchId = IdEncoder.encode(fee.branch_id);
            document.getElementById('editProviderId').value = encodedProviderId;
            document.getElementById('editBranchId').value = encodedBranchId;
            document.getElementById('editFeeType').value = fee.fee_type;
            document.getElementById('editFeeAmount').value = (fee.fee_value !== null && fee.fee_value !== undefined) ? fee.fee_value : '';

            updateEditFeeTypeUI(fee.fee_type);

            const editStatusSwitch = document.getElementById('editStatus');
            if (editStatusSwitch) {
                editStatusSwitch.checked = fee.is_active ? true : false;
                updateEditStatusLabel(!!fee.is_active);
            }

            // Display current provider and branch names
            document.getElementById('editCurrentProviderName').textContent = fee.provider_name || '-';
            document.getElementById('editCurrentBranchName').textContent = fee.branch_name || '-';

            editFeeModal.show();
        } else {
            showToast('error', 'Error', result.message || 'Failed to load fee');
        }
    } catch (error) {
        console.error('Error loading fee:', error);
        showToast('error', 'Error', 'Failed to load fee: ' + error.message);
    }
}

// Load main providers for edit modal (sub-providers do not have their own service fees)
async function loadProvidersForEdit() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`);
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('editProviderId');
            select.innerHTML = '<option value="">Select Main Provider</option>';
            result.data.providers.forEach(provider => {
                if (provider.parent_provider_id) return;
                const encodedId = IdEncoder.encode(provider.provider_id);
                select.innerHTML += `<option value="${encodedId}">${provider.provider_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
}

// Load branches for edit modal
async function loadBranchesForEdit() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/business-branches`);
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('editBranchId');
            select.innerHTML = '<option value="">Select Branch</option>';
            result.data.branches.forEach(branch => {
                const encodedId = IdEncoder.encode(branch.branch_id);
                select.innerHTML += `<option value="${encodedId}">${branch.branch_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Update fee
async function updateFee() {
    const feeId = currentEditFeeId;
    const providerId = document.getElementById('editProviderId').value;
    const branchId = document.getElementById('editBranchId').value;
    const feeType = document.getElementById('editFeeType').value;
    const amountInput = document.getElementById('editFeeAmount');
    const rawAmount = String(amountInput.value).replace(/,/g, '');
    const feeAmount = rawAmount ? parseFloat(rawAmount) : 0;
    const status = document.getElementById('editStatus').checked ? 'active' : 'inactive';

    if (!feeId) {
        showToast('warning', 'Warning', 'Missing fee ID');
        return;
    }

    if (rawAmount !== '' && isNaN(parseFloat(rawAmount))) {
        showToast('warning', 'Warning', 'Please enter a valid fee amount');
        amountInput.focus();
        return;
    }

    if (feeAmount < 0) {
        showToast('warning', 'Warning', 'Fee amount cannot be negative');
        amountInput.focus();
        return;
    }

    if (!providerId || !branchId || !feeType || providerId === '' || branchId === '' || feeType === '') {
        showToast('warning', 'Warning', 'Please select provider, branch and enter fee type');
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

        const encodedFeeId = IdEncoder.encode(feeId);
        console.log('updateFee - encodedFeeId:', encodedFeeId);

        const requestBody = {
            fee_id: encodedFeeId,
            provider_id: providerId,
            branch_id: branchId,
            fee_type: feeType,
            fee_value: parseFloat(feeAmount) || 0,
            status: status
        };
        console.log('updateFee - request body:', requestBody);

        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify(requestBody)
        });

        const result = await response.json();
        console.log('updateFee - response:', result);

        if (result.success) {
            showToast('success', 'Success', 'Service fee updated successfully');
            editFeeModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update service fee');
        }
    } catch (error) {
        console.error('Error updating fee:', error);
        showToast('error', 'Error', 'Failed to update service fee: ' + error.message);
    }
}

// Delete fee
async function deleteFee(feeId) {
    if (!confirm('Are you sure you want to delete this service fee?')) {
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

        const encodedFeeId = IdEncoder.encode(feeId);
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees?id=${encodedFeeId}`, {
            method: 'DELETE',
            headers: headers
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', 'Service fee deleted successfully');
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to delete service fee');
        }
    } catch (error) {
        console.error('Error deleting fee:', error);
        showToast('error', 'Error', 'Failed to delete service fee: ' + error.message);
    }
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

// Apply filters
function applyFilters() {
    const providerFilter = document.getElementById('filterProvider').value.toLowerCase();
    const branchFilter = document.getElementById('filterBranch').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
    const feeTypeFilter = document.getElementById('filterFeeType').value.toLowerCase();
    const searchFilter = document.getElementById('filterSearch').value.toLowerCase();
    
    const feeCards = document.querySelectorAll('.fee-card');
    
    feeCards.forEach(card => {
        const provider = card.getAttribute('data-provider').toLowerCase();
        const branch = card.getAttribute('data-branch').toLowerCase();
        const status = card.getAttribute('data-status').toLowerCase();
        const feeType = card.getAttribute('data-fee-type').toLowerCase();
        
        let showCard = true;
        
        if (providerFilter && provider !== providerFilter) {
            showCard = false;
        }
        
        if (branchFilter && branch !== branchFilter) {
            showCard = false;
        }
        
        if (statusFilter && status !== statusFilter) {
            showCard = false;
        }
        
        if (feeTypeFilter && feeType !== feeTypeFilter) {
            showCard = false;
        }
        
        // Search across all fields (provider, branch, fee type)
        if (searchFilter) {
            const searchableText = `${provider} ${branch} ${feeType}`;
            if (!searchableText.includes(searchFilter)) {
                showCard = false;
            }
        }
        
        card.style.display = showCard ? '' : 'none';
    });
}

// Reset filters
function resetFilters() {
    document.getElementById('filterProvider').value = '';
    document.getElementById('filterBranch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterFeeType').value = '';
    document.getElementById('filterSearch').value = '';
    
    const feeCards = document.querySelectorAll('.fee-card');
    feeCards.forEach(card => {
        card.style.display = '';
    });
}
