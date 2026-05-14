// Payment Methods Module

document.addEventListener('DOMContentLoaded', function() {
    console.log('Payment Methods module initialized');
});

let addMethodModal, editMethodModal;

document.addEventListener('DOMContentLoaded', function() {
    addMethodModal = new bootstrap.Modal(document.getElementById('addMethodModal'));
    editMethodModal = new bootstrap.Modal(document.getElementById('editMethodModal'));
});

// Toggle How It Works
function toggleHowItWorks() {
    const content = document.getElementById('howItWorksContent');
    const icon = document.getElementById('howItWorksIcon');
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Open Add Modal
function openAddMethodModal() {
    document.getElementById('addMethodCode').value = '';
    document.getElementById('addMethodName').value = '';
    document.getElementById('addMethodType').value = '';
    document.getElementById('addDescription').value = '';
    document.getElementById('addIcon').value = '';
    document.getElementById('addSortOrder').value = '0';
    document.getElementById('addRequiresConfirmation').checked = false;
    document.getElementById('addRequiresCustomer').checked = false;
    document.getElementById('addRequiresReference').checked = false;
    document.getElementById('addIncludeInExpectedCash').checked = false;
    addMethodModal.show();
}

// Submit Add
async function submitAddMethod() {
    const methodCode = document.getElementById('addMethodCode').value.trim().toUpperCase();
    const methodName = document.getElementById('addMethodName').value.trim();
    const methodType = document.getElementById('addMethodType').value;

    if (!methodCode || !methodName || !methodType) {
        showToast('danger', 'Validation Error', 'Method Code, Name, and Type are required.');
        return;
    }

    const payload = {
        method_code: methodCode,
        method_name: methodName,
        method_type: methodType,
        description: document.getElementById('addDescription').value.trim() || null,
        icon: document.getElementById('addIcon').value.trim() || null,
        sort_order: parseInt(document.getElementById('addSortOrder').value) || 0,
        requires_confirmation: document.getElementById('addRequiresConfirmation').checked ? 1 : 0,
        requires_customer: document.getElementById('addRequiresCustomer').checked ? 1 : 0,
        requires_reference: document.getElementById('addRequiresReference').checked ? 1 : 0,
        include_in_expected_cash: document.getElementById('addIncludeInExpectedCash').checked ? 1 : 0,
        is_active: 1
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/payment-methods`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            addMethodModal.hide();
            showToast('success', 'Method Added', `"${methodName}" has been added successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add payment method.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Edit Method - fetch and populate
async function editMethod(methodId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/payment-methods?id=${methodId}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('danger', 'Error', 'Failed to fetch method details.');
            return;
        }
        const m = result.data;
        document.getElementById('editMethodId').value = m.method_id;
        document.getElementById('editMethodCode').value = m.method_code;
        document.getElementById('editMethodName').value = m.method_name;
        document.getElementById('editMethodType').value = m.method_type;
        document.getElementById('editDescription').value = m.description || '';
        document.getElementById('editIcon').value = m.icon || '';
        document.getElementById('editSortOrder').value = m.sort_order;
        document.getElementById('editRequiresConfirmation').checked = !!parseInt(m.requires_confirmation);
        document.getElementById('editRequiresCustomer').checked = !!parseInt(m.requires_customer);
        document.getElementById('editRequiresReference').checked = !!parseInt(m.requires_reference);
        document.getElementById('editIncludeInExpectedCash').checked = !!parseInt(m.include_in_expected_cash);
        document.getElementById('editIsActive').checked = !!parseInt(m.is_active);
        editMethodModal.show();
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Submit Edit
async function submitEditMethod() {
    const methodId = document.getElementById('editMethodId').value;
    const methodCode = document.getElementById('editMethodCode').value.trim().toUpperCase();
    const methodName = document.getElementById('editMethodName').value.trim();
    const methodType = document.getElementById('editMethodType').value;

    if (!methodCode || !methodName || !methodType) {
        showToast('danger', 'Validation Error', 'Method Code, Name, and Type are required.');
        return;
    }

    const payload = {
        method_id: methodId,
        method_code: methodCode,
        method_name: methodName,
        method_type: methodType,
        description: document.getElementById('editDescription').value.trim() || null,
        icon: document.getElementById('editIcon').value.trim() || null,
        sort_order: parseInt(document.getElementById('editSortOrder').value) || 0,
        requires_confirmation: document.getElementById('editRequiresConfirmation').checked ? 1 : 0,
        requires_customer: document.getElementById('editRequiresCustomer').checked ? 1 : 0,
        requires_reference: document.getElementById('editRequiresReference').checked ? 1 : 0,
        include_in_expected_cash: document.getElementById('editIncludeInExpectedCash').checked ? 1 : 0,
        is_active: document.getElementById('editIsActive').checked ? 1 : 0
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/payment-methods`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            editMethodModal.hide();
            showToast('success', 'Method Updated', `"${methodName}" has been updated successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update payment method.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Toggle Status
async function toggleMethodStatus(methodId, isActive) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/payment-methods`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method_id: methodId, is_active: isActive ? 1 : 0 })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Status Updated', `Payment method has been ${isActive ? 'activated' : 'deactivated'}.`);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update status.');
            location.reload();
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        location.reload();
    }
}

// Delete Method
async function deleteMethod(methodId, methodName) {
    if (!confirm(`Are you sure you want to delete "${methodName}"?\n\nThis cannot be undone if this method is already used in transactions.`)) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/payment-methods`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method_id: methodId })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Method Deleted', `"${methodName}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete payment method.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Apply Filters
function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;

    const rows = document.querySelectorAll('.method-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowName = row.getAttribute('data-name');
        const rowType = row.getAttribute('data-type');
        const rowStatus = row.getAttribute('data-status');

        let show = true;
        if (search && !rowName.includes(search)) show = false;
        if (type && rowType !== type) show = false;
        if (status && rowStatus !== status) show = false;

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const noResults = document.getElementById('noResultsMsg');
    if (noResults) noResults.classList.toggle('d-none', visibleCount > 0);
}

// Reset Filters
function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterStatus').value = '';
    applyFilters();
}

// Toast notification
function showToast(type, title, message) {
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(t => t.remove());

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
