let variantModal;
let variantsData = [];

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('variantModal');
    if (modalEl) {
        variantModal = new bootstrap.Modal(modalEl);
    }
    loadVariants();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
}

function getHeaders() {
    const headers = { 'Content-Type': 'application/json' };
    const token = getCsrfToken();
    if (token) {
        headers['X-CSRF-TOKEN'] = token;
    }
    return headers;
}

function showToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + (type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger') + ' alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = '<strong>' + title + '</strong>: ' + message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.remove();
    }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

async function loadVariants() {
    const tbody = document.querySelector('#variantsTable tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading variants...</td></tr>';

    const providerFilter = document.getElementById('providerFilter').value;
    let url = window.BASE_URL + '/api/ticket-variants?include_inactive=1';
    if (providerFilter) {
        url += '&provider_id=' + encodeURIComponent(providerFilter);
    }

    try {
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            variantsData = result.data || [];
            filterVariants();
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(result.error || 'Failed to load variants') + '</td></tr>';
            updateStats([]);
        }
    } catch (error) {
        console.error('Error loading variants:', error);
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load variants</td></tr>';
        updateStats([]);
    }
}

function filterVariants() {
    const search = document.getElementById('variantSearch').value.toLowerCase();
    const filtered = variantsData.filter(function(v) {
        if (!search) return true;
        return (v.provider_code || '').toLowerCase().indexOf(search) !== -1 ||
               (v.provider_name || '').toLowerCase().indexOf(search) !== -1 ||
               (v.variant_code || '').toLowerCase().indexOf(search) !== -1 ||
               (v.variant_name || '').toLowerCase().indexOf(search) !== -1 ||
               (v.description || '').toLowerCase().indexOf(search) !== -1;
    });
    renderVariantsTable(filtered);
    updateStats(filtered);
}

function renderVariantsTable(data) {
    const tbody = document.querySelector('#variantsTable tbody');
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No variants found.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(function(v) {
        const color = v.color_code || '#0d6efd';
        return '<tr>' +
            '<td>' + escapeHtml((v.provider_code ? v.provider_code + ' - ' : '') + (v.provider_name || '')) + '</td>' +
            '<td class="fw-bold">' + escapeHtml(v.variant_code || '') + '</td>' +
            '<td>' + escapeHtml(v.variant_name || '') + '</td>' +
            '<td>' + escapeHtml(v.description || '') + '</td>' +
            '<td><span class="variant-color-swatch" style="background-color:' + escapeHtml(color) + ';"></span></td>' +
            '<td>' + (v.stock_controlled ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>' +
            '<td>' + (v.requires_ticket_number ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>' +
            '<td>' + (v.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>') + '</td>' +
            '<td class="text-end">' +
                '<div class="btn-group btn-group-sm">' +
                    '<button type="button" class="btn btn-outline-primary" onclick="editVariant(' + v.variant_id + ')" title="Edit"><span class="fas fa-edit"></span></button>' +
                    '<button type="button" class="btn btn-outline-danger" onclick="deleteVariant(' + v.variant_id + ')" title="Delete"><span class="fas fa-trash"></span></button>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }).join('');
}

function updateStats(data) {
    let total = 0, active = 0, inactive = 0, stockControlled = 0;
    if (data) {
        total = data.length;
        data.forEach(function(v) {
            if (v.is_active) active++;
            else inactive++;
            if (v.stock_controlled) stockControlled++;
        });
    }
    document.getElementById('statTotalVariants').textContent = total;
    document.getElementById('statActiveVariants').textContent = active;
    document.getElementById('statInactiveVariants').textContent = inactive;
    document.getElementById('statStockControlled').textContent = stockControlled;
}

function resetVariantForm() {
    document.getElementById('variantForm').reset();
    document.getElementById('variantId').value = '';
    document.getElementById('variantProviderId').disabled = false;
    document.getElementById('variantProviderId').value = '';
    document.getElementById('variantColor').value = '#0d6efd';
    document.getElementById('variantStockControlled').checked = true;
    document.getElementById('variantRequiresTicketNumber').checked = false;
    document.getElementById('variantActive').checked = true;
    document.getElementById('variantModalLabel').textContent = 'Add Variant';
}

async function editVariant(variantId) {
    try {
        const response = await fetch(window.BASE_URL + '/api/ticket-variants?variant_id=' + encodeURIComponent(variantId));
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            const v = result.data[0];
            document.getElementById('variantId').value = v.variant_id;
            document.getElementById('variantProviderId').value = v.provider_id || '';
            document.getElementById('variantProviderId').disabled = true;
            document.getElementById('variantCode').value = v.variant_code || '';
            document.getElementById('variantName').value = v.variant_name || '';
            document.getElementById('variantDescription').value = v.description || '';
            document.getElementById('variantColor').value = v.color_code || '#0d6efd';
            document.getElementById('variantStockControlled').checked = !!v.stock_controlled;
            document.getElementById('variantRequiresTicketNumber').checked = !!v.requires_ticket_number;
            document.getElementById('variantActive').checked = !!v.is_active;
            document.getElementById('variantModalLabel').textContent = 'Edit Variant';
            variantModal.show();
        } else {
            showToast('error', 'Error', result.error || 'Variant not found');
        }
    } catch (error) {
        console.error('Error loading variant:', error);
        showToast('error', 'Error', 'Failed to load variant details');
    }
}

async function saveVariant() {
    const variantId = document.getElementById('variantId').value;
    const providerId = document.getElementById('variantProviderId').value;
    const variantCode = document.getElementById('variantCode').value.trim();
    const variantName = document.getElementById('variantName').value.trim();
    const description = document.getElementById('variantDescription').value.trim();
    const displayColor = document.getElementById('variantColor').value;
    const stockControlled = document.getElementById('variantStockControlled').checked ? 1 : 0;
    const requiresTicketNumber = document.getElementById('variantRequiresTicketNumber').checked ? 1 : 0;
    const isActive = document.getElementById('variantActive').checked ? 1 : 0;

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
        const response = await fetch(window.BASE_URL + '/api/ticket-variants', {
            method: isEdit ? 'PUT' : 'POST',
            headers: getHeaders(),
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', isEdit ? 'Variant updated successfully' : 'Variant created successfully');
            variantModal.hide();
            resetVariantForm();
            loadVariants();
        } else {
            showToast('error', 'Error', result.error || 'Failed to save variant');
        }
    } catch (error) {
        console.error('Error saving variant:', error);
        showToast('error', 'Error', 'Failed to save variant: ' + error.message);
    }
}

async function deleteVariant(variantId) {
    if (!confirm('Are you sure you want to delete this variant?')) {
        return;
    }

    try {
        const response = await fetch(window.BASE_URL + '/api/ticket-variants?variant_id=' + encodeURIComponent(variantId), {
            method: 'DELETE',
            headers: getHeaders()
        });

        const result = await response.json();

        if (result.success) {
            showToast('success', 'Success', 'Variant deleted successfully');
            loadVariants();
        } else {
            showToast('error', 'Error', result.error || 'Failed to delete variant');
        }
    } catch (error) {
        console.error('Error deleting variant:', error);
        showToast('error', 'Error', 'Failed to delete variant: ' + error.message);
    }
}
