// Ticket Providers Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Ticket Providers module initialized');
});

// Initialize Bootstrap modals
let addProviderModal, editProviderModal;

document.addEventListener('DOMContentLoaded', function() {
    addProviderModal = new bootstrap.Modal(document.getElementById('addProviderModal'));
    editProviderModal = new bootstrap.Modal(document.getElementById('editProviderModal'));
});

// Open add provider modal
function openAddProviderModal() {
    document.getElementById('addProviderForm').reset();
    document.getElementById('addStatus').checked = true;
    updateAddStatusLabel(true);
    addProviderModal.show();
}

// Update add status label
function updateAddStatusLabel(isActive) {
    const label = document.getElementById('addStatusLabel');
    if (label) {
        label.innerHTML = isActive 
            ? '<span class="text-success fw-bold">Active</span>' 
            : '<span class="text-muted">Inactive</span>';
    }
}

// Handle add status switch change
document.addEventListener('DOMContentLoaded', function() {
    const addStatusSwitch = document.getElementById('addStatus');
    if (addStatusSwitch) {
        addStatusSwitch.addEventListener('change', function() {
            updateAddStatusLabel(this.checked);
        });
    }
});

// Save provider
async function saveProvider() {
    const providerCode = document.getElementById('addProviderCode').value.trim();
    const providerName = document.getElementById('addProviderName').value.trim();
    const providerType = document.getElementById('addProviderType').value;
    const status = document.getElementById('addStatus').checked ? 'active' : 'inactive';
    
    if (!providerCode || !providerName || !providerType) {
        showToast('warning', 'Warning', 'Please fill in all required fields');
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                provider_code: providerCode,
                provider_name: providerName,
                provider_type: providerType,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Provider created successfully');
            addProviderModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to create provider');
        }
    } catch (error) {
        console.error('Error saving provider:', error);
        showToast('error', 'Error', 'Failed to create provider: ' + error.message);
    }
}

// Edit provider
async function editProvider(providerId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers?id=${providerId}`);
        const result = await response.json();
        
        if (result.success) {
            const provider = result.data;
            document.getElementById('editProviderId').value = provider.provider_id;
            document.getElementById('editProviderCode').value = provider.provider_code;
            document.getElementById('editProviderName').value = provider.provider_name;
            document.getElementById('editProviderType').value = provider.provider_type;
            document.getElementById('editStatus').checked = provider.status === 'active';
            updateEditStatusLabel(provider.status === 'active');
            editProviderModal.show();
        } else {
            showToast('error', 'Error', result.message || 'Failed to load provider');
        }
    } catch (error) {
        console.error('Error loading provider:', error);
        showToast('error', 'Error', 'Failed to load provider: ' + error.message);
    }
}

// Update edit status label
function updateEditStatusLabel(isActive) {
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
            updateEditStatusLabel(this.checked);
        });
    }
    
    // Handle provider table switches for real-time toggle
    const providerSwitches = document.querySelectorAll('.provider-status-switch');
    providerSwitches.forEach(switchEl => {
        switchEl.addEventListener('change', async function() {
            const providerId = this.getAttribute('data-provider-id');
            const newStatus = this.checked ? 'active' : 'inactive';
            await toggleProviderStatus(providerId, newStatus, this);
        });
    });
});

// Update provider
async function updateProvider() {
    const providerId = document.getElementById('editProviderId').value;
    const providerCode = document.getElementById('editProviderCode').value.trim();
    const providerName = document.getElementById('editProviderName').value.trim();
    const providerType = document.getElementById('editProviderType').value;
    const statusCheckbox = document.getElementById('editStatus');
    const status = statusCheckbox.checked ? 'active' : 'inactive';
    
    if (!providerCode || !providerName || !providerType) {
        showToast('warning', 'Warning', 'Please fill in all required fields');
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                provider_id: providerId,
                provider_code: providerCode,
                provider_name: providerName,
                provider_type: providerType,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Provider updated successfully');
            editProviderModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update provider');
        }
    } catch (error) {
        console.error('Error updating provider:', error);
        showToast('error', 'Error', 'Failed to update provider: ' + error.message);
    }
}

// Toggle provider status in real-time
async function toggleProviderStatus(providerId, newStatus, switchElement) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                provider_id: providerId,
                status: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', `Provider ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
            
            // Update the label
            const label = switchElement.nextElementSibling;
            if (label) {
                label.textContent = newStatus === 'active' ? 'Active' : 'Inactive';
            }
            
            // Update stats
            updateStats();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update provider status');
            // Revert switch on error
            switchElement.checked = !switchElement.checked;
        }
    } catch (error) {
        console.error('Error toggling provider status:', error);
        showToast('error', 'Error', 'Failed to update provider status: ' + error.message);
        // Revert switch on error
        switchElement.checked = !switchElement.checked;
    }
}

// Delete provider
async function deleteProvider(providerId) {
    if (!confirm('Are you sure you want to delete this provider? This action cannot be undone.')) {
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers?id=${providerId}`, {
            method: 'DELETE',
            headers: headers
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Provider deleted successfully');
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to delete provider');
        }
    } catch (error) {
        console.error('Error deleting provider:', error);
        showToast('error', 'Error', 'Failed to delete provider: ' + error.message);
    }
}

// Filter providers
function filterProviders(status) {
    const rows = document.querySelectorAll('#providersTable tbody tr[data-status]');
    rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Update stats
async function updateStats() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers?action=stats`);
        const result = await response.json();
        
        if (result.success) {
            // Update total providers
            const totalEl = document.querySelector('.card-body .fs-5');
            if (totalEl) {
                totalEl.textContent = result.data.total_providers;
            }
            
            // Update active providers
            const activeEl = document.querySelectorAll('.card-body .fs-5')[1];
            if (activeEl) {
                activeEl.textContent = result.data.active_providers;
            }
            
            // Update inactive providers
            const inactiveEl = document.querySelectorAll('.card-body .fs-5')[2];
            if (inactiveEl) {
                inactiveEl.textContent = result.data.inactive_providers;
            }
        }
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

// Toast notification
function showToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        <strong>${title}</strong>: ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
