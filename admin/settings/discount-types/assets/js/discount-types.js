// Discount Types Module

let addDiscountTypeModal, editDiscountTypeModal;

document.addEventListener('DOMContentLoaded', function () {
    addDiscountTypeModal = new bootstrap.Modal(document.getElementById('addDiscountTypeModal'));
    editDiscountTypeModal = new bootstrap.Modal(document.getElementById('editDiscountTypeModal'));
});

function openAddDiscountTypeModal() {
    document.getElementById('addCode').value = '';
    document.getElementById('addName').value = '';
    document.getElementById('addDescription').value = '';
    document.getElementById('addDiscountPercentage').value = '0';
    document.getElementById('addIsDefault').checked = false;
    addDiscountTypeModal.show();
}

async function submitAddDiscountType() {
    const code = document.getElementById('addCode').value.trim().toUpperCase();
    const name = document.getElementById('addName').value.trim();

    if (!code || !name) {
        showToast('danger', 'Validation Error', 'Code and name are required.');
        return;
    }

    const payload = {
        code,
        name,
        description: document.getElementById('addDescription').value.trim() || null,
        discount_percentage: parseFloat(document.getElementById('addDiscountPercentage').value) || 0,
        is_default: document.getElementById('addIsDefault').checked ? 1 : 0,
    };

    try {
        const res = await fetch(`${window.BASE_URL}/api/discount-types`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();
        if (result.success) {
            addDiscountTypeModal.hide();
            showToast('success', 'Discount Type Added', `"${name}" has been added successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add discount type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function editDiscountType(id) {
    try {
        const encodedId = IdEncoder.encode(id);
        const res = await fetch(`${window.BASE_URL}/api/discount-types?id=${encodedId}`);
        const result = await res.json();
        if (!result.success || !result.data) {
            showToast('danger', 'Error', 'Failed to fetch discount type details.');
            return;
        }
        const d = result.data;
        document.getElementById('editDiscountId').value = d.discount_id;
        document.getElementById('editCode').value = d.code;
        document.getElementById('editName').value = d.name;
        document.getElementById('editDescription').value = d.description || '';
        document.getElementById('editDiscountPercentage').value = parseFloat(d.discount_percentage || 0).toFixed(2);
        document.getElementById('editIsDefault').checked = parseInt(d.is_default) === 1;
        editDiscountTypeModal.show();
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function submitEditDiscountType() {
    const id = document.getElementById('editDiscountId').value;
    const code = document.getElementById('editCode').value.trim().toUpperCase();
    const name = document.getElementById('editName').value.trim();

    if (!code || !name) {
        showToast('danger', 'Validation Error', 'Code and name are required.');
        return;
    }

    const payload = {
        discount_id: id,
        code,
        name,
        description: document.getElementById('editDescription').value.trim() || null,
        discount_percentage: parseFloat(document.getElementById('editDiscountPercentage').value) || 0,
        is_default: document.getElementById('editIsDefault').checked ? 1 : 0,
    };

    try {
        const res = await fetch(`${window.BASE_URL}/api/discount-types`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();
        if (result.success) {
            editDiscountTypeModal.hide();
            showToast('success', 'Updated', `"${name}" has been updated successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update discount type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

async function deleteDiscountType(id, name) {
    const confirmed = await showConfirmToast(
        'Delete Discount Type',
        `Are you sure you want to delete "<strong>${name}</strong>"? Types used in transactions cannot be deleted.`
    );
    if (!confirmed) return;

    try {
        const res = await fetch(`${window.BASE_URL}/api/discount-types`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ discount_id: id }),
        });
        const result = await res.json();
        if (result.success) {
            showToast('success', 'Deleted', `"${name}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete discount type.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.discount-type-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search');
        const show = !search || rowSearch.includes(search);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const noResults = document.getElementById('noResultsMsg');
    if (noResults) noResults.classList.toggle('d-none', visibleCount > 0);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    applyFilters();
}

function showConfirmToast(title, message) {
    return new Promise(resolve => {
        document.querySelectorAll('.custom-confirm-toast').forEach(t => t.remove());
        const toast = document.createElement('div');
        toast.className = 'custom-confirm-toast position-fixed';
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 360px; max-width: 460px;';
        toast.innerHTML = `
            <div class="card shadow border-0" style="border-radius: 10px; overflow: hidden;">
                <div class="card-header bg-danger text-white py-2 px-3 d-flex align-items-center">
                    <span class="fas fa-exclamation-triangle me-2"></span>
                    <strong>${title}</strong>
                </div>
                <div class="card-body px-3 py-3">
                    <p class="mb-3" style="font-size:0.9rem;">${message}</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm btn-secondary" id="confirmToastCancel">
                            <span class="fas fa-times me-1"></span>Cancel
                        </button>
                        <button class="btn btn-sm btn-danger" id="confirmToastOk">
                            <span class="fas fa-trash me-1"></span>Delete
                        </button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(toast);
        toast.querySelector('#confirmToastOk').addEventListener('click', () => {
            toast.remove(); resolve(true);
        });
        toast.querySelector('#confirmToastCancel').addEventListener('click', () => {
            toast.remove(); resolve(false);
        });
    });
}

function showToast(type, title, message) {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());
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
        </div>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 150); }, 4000);
}
