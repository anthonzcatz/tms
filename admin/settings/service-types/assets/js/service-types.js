// Service Types Module

document.addEventListener('DOMContentLoaded', function() {
    console.log('Service Types module initialized');
});

let addServiceTypeModal, editServiceTypeModal;

document.addEventListener('DOMContentLoaded', function() {
    addServiceTypeModal = new bootstrap.Modal(document.getElementById('addServiceTypeModal'));
    editServiceTypeModal = new bootstrap.Modal(document.getElementById('editServiceTypeModal'));
});

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

function openAddServiceTypeModal() {
    document.getElementById('addCode').value = '';
    document.getElementById('addName').value = '';
    document.getElementById('addDescription').value = '';
    document.getElementById('addDefaultAmount').value = '0.00';
    document.getElementById('addAllowCustomAmount').checked = true;
    document.getElementById('addRequiresWallet').checked = false;
    addServiceTypeModal.show();
}

async function submitAddServiceType() {
    const code = document.getElementById('addCode').value.trim().toUpperCase();
    const name = document.getElementById('addName').value.trim();

    if (!code || !name) {
        showToast('danger', 'Validation Error', 'Service code and name are required.');
        return;
    }

    const payload = {
        code,
        name,
        description: document.getElementById('addDescription').value.trim() || null,
        default_amount: parseFloat(document.getElementById('addDefaultAmount').value) || 0,
        allow_custom_amount: document.getElementById('addAllowCustomAmount').checked ? 1 : 0,
        requires_wallet: document.getElementById('addRequiresWallet').checked ? 1 : 0,
        is_active: 1
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/service-types`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            addServiceTypeModal.hide();
            showToast('success', 'Service Type Added', `"${name}" has been added successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add service type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function editServiceType(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/service-types?id=${id}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('danger', 'Error', 'Failed to fetch service type details.');
            return;
        }
        const s = result.data;
        document.getElementById('editServiceTypeId').value = s.service_type_id;
        document.getElementById('editCode').value = s.code;
        document.getElementById('editName').value = s.name;
        document.getElementById('editDescription').value = s.description || '';
        document.getElementById('editDefaultAmount').value = parseFloat(s.default_amount).toFixed(2);
        document.getElementById('editAllowCustomAmount').checked = !!parseInt(s.allow_custom_amount);
        document.getElementById('editRequiresWallet').checked = !!parseInt(s.requires_wallet);
        document.getElementById('editIsActive').checked = !!parseInt(s.is_active);
        editServiceTypeModal.show();
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function submitEditServiceType() {
    const id = document.getElementById('editServiceTypeId').value;
    const code = document.getElementById('editCode').value.trim().toUpperCase();
    const name = document.getElementById('editName').value.trim();

    if (!code || !name) {
        showToast('danger', 'Validation Error', 'Service code and name are required.');
        return;
    }

    const payload = {
        service_type_id: id,
        code,
        name,
        description: document.getElementById('editDescription').value.trim() || null,
        default_amount: parseFloat(document.getElementById('editDefaultAmount').value) || 0,
        allow_custom_amount: document.getElementById('editAllowCustomAmount').checked ? 1 : 0,
        requires_wallet: document.getElementById('editRequiresWallet').checked ? 1 : 0,
        is_active: document.getElementById('editIsActive').checked ? 1 : 0
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/service-types`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            editServiceTypeModal.hide();
            showToast('success', 'Service Type Updated', `"${name}" has been updated successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update service type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function toggleServiceTypeStatus(id, isActive) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/service-types`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ service_type_id: id, is_active: isActive ? 1 : 0 })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Status Updated', `Service type has been ${isActive ? 'activated' : 'deactivated'}.`);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update status.');
            location.reload();
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        location.reload();
    }
}

async function deleteServiceType(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"?\n\nThis cannot be undone if already used in transactions.`)) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/service-types`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ service_type_id: id })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Deleted', `"${name}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete service type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const wallet = document.getElementById('filterWallet').value;

    const rows = document.querySelectorAll('.service-type-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search');
        const rowStatus = row.getAttribute('data-status');
        const rowWallet = row.getAttribute('data-wallet');

        let show = true;
        if (search && !rowSearch.includes(search)) show = false;
        if (status && rowStatus !== status) show = false;
        if (wallet !== '' && rowWallet !== wallet) show = false;

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const noResults = document.getElementById('noResultsMsg');
    if (noResults) noResults.classList.toggle('d-none', visibleCount > 0);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterWallet').value = '';
    applyFilters();
}

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
