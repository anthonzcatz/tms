// Provider Service Fees Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Provider Service Fees module initialized');
    loadProviders();
    loadBranches();
});

// Initialize Bootstrap modals
let addFeeModal, editFeeModal;

document.addEventListener('DOMContentLoaded', function() {
    addFeeModal = new bootstrap.Modal(document.getElementById('addFeeModal'));
    editFeeModal = new bootstrap.Modal(document.getElementById('editFeeModal'));
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

// Open add fee modal
function openAddFeeModal() {
    document.getElementById('addFeeForm').reset();
    addFeeModal.show();
}

// Save fee
async function saveFee() {
    const providerId = document.getElementById('addProviderId').value;
    const branchId = document.getElementById('addBranchId').value;
    const feeType = document.getElementById('addFeeType').value;
    const feeAmount = document.getElementById('addFeeAmount').value;
    const feePercentage = document.getElementById('addFeePercentage').value;
    const status = document.getElementById('addStatus').value;
    
    if (!providerId || !branchId || !feeType) {
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
        
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                provider_id: providerId,
                branch_id: branchId,
                fee_type: feeType,
                fee_amount: parseFloat(feeAmount) || 0,
                fee_percentage: parseFloat(feePercentage) || 0,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Service fee created successfully');
            addFeeModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to create service fee');
        }
    } catch (error) {
        console.error('Error saving fee:', error);
        showToast('error', 'Error', 'Failed to create service fee: ' + error.message);
    }
}

// Edit fee
async function editFee(feeId) {
    // Load providers and branches for edit modal
    await loadProvidersForEdit();
    await loadBranchesForEdit();
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees?id=${feeId}`);
        const result = await response.json();
        
        if (result.success) {
            const fee = result.data;
            document.getElementById('editFeeId').value = fee.fee_id;
            document.getElementById('editProviderId').value = fee.provider_id;
            document.getElementById('editBranchId').value = fee.branch_id;
            document.getElementById('editFeeType').value = fee.fee_type;
            document.getElementById('editFeeAmount').value = fee.fee_amount || '';
            document.getElementById('editFeePercentage').value = fee.fee_percentage || '';
            document.getElementById('editStatus').value = fee.is_active ? 'active' : 'inactive';
            
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

// Load providers for edit modal
async function loadProvidersForEdit() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`);
        const result = await response.json();
        
        if (result.success) {
            const select = document.getElementById('editProviderId');
            select.innerHTML = '<option value="">Select Provider</option>';
            result.data.providers.forEach(provider => {
                select.innerHTML += `<option value="${provider.provider_id}">${provider.provider_name}</option>`;
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
                select.innerHTML += `<option value="${branch.branch_id}">${branch.branch_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Update fee
async function updateFee() {
    const feeId = document.getElementById('editFeeId').value;
    const providerId = document.getElementById('editProviderId').value;
    const branchId = document.getElementById('editBranchId').value;
    const feeType = document.getElementById('editFeeType').value;
    const feeAmount = document.getElementById('editFeeAmount').value;
    const feePercentage = document.getElementById('editFeePercentage').value;
    const status = document.getElementById('editStatus').value;
    
    if (!providerId || !branchId || !feeType) {
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
        
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                fee_id: feeId,
                provider_id: providerId,
                branch_id: branchId,
                fee_type: feeType,
                fee_amount: parseFloat(feeAmount) || 0,
                fee_percentage: parseFloat(feePercentage) || 0,
                status: status
            })
        });
        
        const result = await response.json();
        
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
        
        const response = await fetch(`${window.BASE_URL}/api/provider-service-fees?id=${feeId}`, {
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
