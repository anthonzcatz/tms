// Ticket Providers Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Ticket Providers module initialized');
    applyFilters();
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
    const parentProviderId = document.getElementById('addParentProvider').value || null;
    
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
                status: status,
                parent_provider_id: parentProviderId
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
        const encodedProviderId = IdEncoder.encode(providerId);
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers?id=${encodedProviderId}`);
        const result = await response.json();

        if (result.success) {
            const provider = result.data;
            document.getElementById('editProviderId').value = provider.provider_id;
            document.getElementById('editProviderCode').value = provider.provider_code;
            document.getElementById('editProviderName').value = provider.provider_name;
            document.getElementById('editProviderType').value = provider.provider_type;
            document.getElementById('editStatus').checked = provider.status === 'active';
            document.getElementById('editParentProvider').value = provider.parent_provider_id || '';
            // Prevent selecting the provider as its own parent
            document.querySelectorAll('#editParentProvider option').forEach(option => {
                option.disabled = option.value === String(provider.provider_id);
            });
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
    const parentProviderId = document.getElementById('editParentProvider').value || null;
    
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
                status: status,
                parent_provider_id: parentProviderId
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

        const encodedProviderId = IdEncoder.encode(providerId);
        const response = await fetch(`${window.BASE_URL}/api/ticket-providers?id=${encodedProviderId}`, {
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

// Apply all provider filters
function applyFilters() {
    const search = document.getElementById('providerSearch')?.value.toLowerCase().trim() || '';
    const type = document.getElementById('providerTypeFilter')?.value || '';
    const walletOwner = document.getElementById('providerWalletOwnerFilter')?.value || '';
    const status = document.getElementById('providerStatusFilter')?.value || 'all';

    const rows = document.querySelectorAll('#providersTable tbody tr[data-status]');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowType = row.getAttribute('data-provider-type') || '';
        const rowWalletOwner = row.getAttribute('data-wallet-owner') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchesSearch = !search || rowSearch.includes(search);
        const matchesType = !type || rowType === type;
        const matchesStatus = status === 'all' || rowStatus === status;
        let matchesWalletOwner = true;
        if (walletOwner === 'standalone') {
            matchesWalletOwner = rowWalletOwner === '';
        } else if (walletOwner) {
            matchesWalletOwner = rowWalletOwner === walletOwner;
        }

        if (matchesSearch && matchesType && matchesWalletOwner && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const infoEl = document.getElementById('providerFilterInfo');
    if (infoEl) {
        infoEl.textContent = `Showing ${visibleCount} provider${visibleCount !== 1 ? 's' : ''}`;
    }
}

// Reset all provider filters
function resetProviderFilters() {
    const searchInput = document.getElementById('providerSearch');
    if (searchInput) searchInput.value = '';
    const typeFilter = document.getElementById('providerTypeFilter');
    if (typeFilter) typeFilter.value = '';
    const walletOwnerFilter = document.getElementById('providerWalletOwnerFilter');
    if (walletOwnerFilter) walletOwnerFilter.value = '';
    const statusFilter = document.getElementById('providerStatusFilter');
    if (statusFilter) statusFilter.value = 'all';
    applyFilters();
}

// Backward-compatible wrapper
function filterProviders(status) {
    const statusFilter = document.getElementById('providerStatusFilter');
    if (statusFilter) statusFilter.value = status;
    applyFilters();
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

// ============================================================
// Variant Management
// ============================================================
let manageVariantsModal;
let variantsListTab, variantsAddTab;

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('manageVariantsModal');
    if (modalEl) {
        manageVariantsModal = new bootstrap.Modal(modalEl);
    }
    const listTabEl = document.getElementById('variants-list-tab');
    const addTabEl = document.getElementById('variants-add-tab');
    if (listTabEl) {
        variantsListTab = new bootstrap.Tab(listTabEl);
    }
    if (addTabEl) {
        variantsAddTab = new bootstrap.Tab(addTabEl);
    }
});

function getVariantCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function getVariantHeaders() {
    const headers = { 'Content-Type': 'application/json' };
    const token = getVariantCsrfToken();
    if (token) {
        headers['X-CSRF-TOKEN'] = token;
    }
    return headers;
}

function openManageVariantsModal(button) {
    const providerId = button.dataset.providerId;
    const providerName = button.dataset.providerName || '';
    const providerCode = button.dataset.providerCode || '';

    document.getElementById('manageVariantProviderId').value = providerId;
    document.getElementById('manageVariantId').value = '';
    document.getElementById('manageVariantsModalLabel').innerHTML = '<span class="fas fa-palette me-2"></span>Manage Variants';
    const subtext = providerCode ? `${providerCode} - ${providerName}` : providerName;
    document.getElementById('manageVariantsProviderSubtext').textContent = subtext;

    resetVariantForm();
    showVariantsListTab();
    loadProviderVariants(providerId);
    manageVariantsModal.show();
}

function showVariantsListTab() {
    resetVariantForm();
    if (variantsListTab) {
        variantsListTab.show();
    }
}

function showAddVariantTab() {
    document.getElementById('manageVariantId').value = '';
    resetVariantForm();
    document.getElementById('saveVariantBtn').innerHTML = '<span class="fas fa-save me-1"></span>Save Variant';
    if (variantsAddTab) {
        variantsAddTab.show();
    }
}

function resetVariantForm() {
    document.getElementById('variantForm').reset();
    document.getElementById('manageVariantCode').value = '';
    document.getElementById('manageVariantName').value = '';
    document.getElementById('manageVariantDescription').value = '';
    document.getElementById('manageVariantColor').value = '#0d6efd';
    document.getElementById('manageVariantStockControlled').checked = true;
    document.getElementById('manageVariantRequiresTicketNumber').checked = false;
    document.getElementById('manageVariantActive').checked = true;
    document.getElementById('saveVariantBtn').innerHTML = '<span class="fas fa-save me-1"></span>Save Variant';
}

async function loadProviderVariants(providerId) {
    const tbody = document.querySelector('#variantsTable tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading variants...</td></tr>';

    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-variants?provider_id=${providerId}`);
        const result = await response.json();

        if (result.success) {
            renderVariantsTable(result.data, providerId);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${result.error || 'Failed to load variants'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading variants:', error);
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load variants</td></tr>';
    }
}

function renderVariantsTable(variants, providerId) {
    const tbody = document.querySelector('#variantsTable tbody');
    if (!variants || variants.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="empty-state">
                        <div class="empty-state-icon"><span class="fas fa-palette"></span></div>
                        <div class="empty-state-text">No variants found</div>
                        <div class="empty-state-subtext">Add a variant for this provider</div>
                    </div>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = variants.map(v => {
        const color = v.color_code || '#0d6efd';
        return `
        <tr>
            <td class="fw-bold">${escapeHtml(v.variant_code || '')}</td>
            <td>${escapeHtml(v.variant_name || '')}</td>
            <td>${escapeHtml(v.description || '')}</td>
            <td><span class="d-inline-block rounded" style="width:20px;height:20px;background:${color};border:1px solid #dee2e6;"></span></td>
            <td>${v.stock_controlled ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
            <td>${v.requires_ticket_number ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
            <td>${v.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'}</td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="editVariant(${v.variant_id})" title="Edit"><span class="fas fa-edit"></span></button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteVariant(${v.variant_id})" title="Delete"><span class="fas fa-trash"></span></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function editVariant(variantId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-variants?provider_id=0&variant_id=${variantId}`);
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            const v = result.data[0];
            document.getElementById('manageVariantId').value = v.variant_id;
            document.getElementById('manageVariantCode').value = v.variant_code || '';
            document.getElementById('manageVariantName').value = v.variant_name || '';
            document.getElementById('manageVariantDescription').value = v.description || '';
            document.getElementById('manageVariantColor').value = v.color_code || '#0d6efd';
            document.getElementById('manageVariantStockControlled').checked = !!v.stock_controlled;
            document.getElementById('manageVariantRequiresTicketNumber').checked = !!v.requires_ticket_number;
            document.getElementById('manageVariantActive').checked = !!v.is_active;
            document.getElementById('saveVariantBtn').innerHTML = '<span class="fas fa-save me-1"></span>Update Variant';

            if (variantsAddTab) {
                variantsAddTab.show();
            }
        } else {
            showToast('error', 'Error', result.error || 'Variant not found');
        }
    } catch (error) {
        console.error('Error loading variant:', error);
        showToast('error', 'Error', 'Failed to load variant details');
    }
}

async function saveVariant() {
    const variantId = document.getElementById('manageVariantId').value;
    const providerId = document.getElementById('manageVariantProviderId').value;
    const variantCode = document.getElementById('manageVariantCode').value.trim();
    const variantName = document.getElementById('manageVariantName').value.trim();
    const description = document.getElementById('manageVariantDescription').value.trim();
    const displayColor = document.getElementById('manageVariantColor').value;
    const stockControlled = document.getElementById('manageVariantStockControlled').checked ? 1 : 0;
    const requiresTicketNumber = document.getElementById('manageVariantRequiresTicketNumber').checked ? 1 : 0;
    const isActive = document.getElementById('manageVariantActive').checked ? 1 : 0;

    if (!providerId || !variantCode || !variantName) {
        showToast('warning', 'Warning', 'Provider, variant code and name are required');
        return;
    }

    const payload = {
        provider_id: parseInt(providerId, 10),
        variant_code: variantCode,
        variant_name: variantName,
        description: description || null,
        display_color: displayColor,
        stock_controlled: stockControlled,
        requires_ticket_number: requiresTicketNumber,
        is_active: isActive
    };

    const isEdit = !!variantId;
    if (isEdit) {
        payload.variant_id = parseInt(variantId, 10);
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-variants`, {
            method: isEdit ? 'PUT' : 'POST',
            headers: getVariantHeaders(),
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', isEdit ? 'Variant updated successfully' : 'Variant created successfully');
            resetVariantForm();
            showVariantsListTab();
            loadProviderVariants(providerId);
        } else {
            showToast('error', 'Error', result.error || 'Failed to save variant');
        }
    } catch (error) {
        console.error('Error saving variant:', error);
        showToast('error', 'Error', 'Failed to save variant: ' + error.message);
    }
}

async function deleteVariant(variantId) {
    if (!confirm('Are you sure you want to delete this variant? This action cannot be undone.')) {
        return;
    }

    const providerId = document.getElementById('manageVariantProviderId').value;

    try {
        const response = await fetch(`${window.BASE_URL}/api/ticket-variants?variant_id=${variantId}`, {
            method: 'DELETE',
            headers: getVariantHeaders()
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', 'Variant deleted successfully');
            loadProviderVariants(providerId);
        } else {
            showToast('error', 'Error', result.error || 'Failed to delete variant');
        }
    } catch (error) {
        console.error('Error deleting variant:', error);
        showToast('error', 'Error', 'Failed to delete variant: ' + error.message);
    }
}

