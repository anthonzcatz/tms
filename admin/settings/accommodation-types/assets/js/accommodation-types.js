let addAccommodationTypeModal;
let editAccommodationTypeModal;
let deleteAccommodationTypeModal;
let pendingDeleteAccommodationTypeId = null;
let pendingDeleteAccommodationTypeName = '';

document.addEventListener('DOMContentLoaded', function () {
    const addElement = document.getElementById('addAccommodationTypeModal');
    const editElement = document.getElementById('editAccommodationTypeModal');
    const deleteElement = document.getElementById('deleteAccommodationTypeModal');

    if (addElement) addAccommodationTypeModal = new bootstrap.Modal(addElement);
    if (editElement) editAccommodationTypeModal = new bootstrap.Modal(editElement);
    if (deleteElement) deleteAccommodationTypeModal = new bootstrap.Modal(deleteElement);

    const confirmDeleteButton = document.getElementById('confirmDeleteAccommodationTypeBtn');
    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', confirmDeleteAccommodationType);
    }
});

function requestHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
    };
}

function openAddAccommodationTypeModal() {
    document.getElementById('addAccommodationCode').value = '';
    document.getElementById('addAccommodationName').value = '';
    document.getElementById('addAccommodationIsDefault').checked = false;
    addAccommodationTypeModal.show();
}

async function submitAddAccommodationType() {
    const code = document.getElementById('addAccommodationCode').value.trim().toUpperCase();
    const name = document.getElementById('addAccommodationName').value.trim();

    if (!code || !name) {
        showToast('danger', 'Validation Error', 'Code and name are required.');
        return;
    }

    const payload = {
        code,
        name,
        is_default: document.getElementById('addAccommodationIsDefault').checked ? 1 : 0
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/accommodation-types`, {
            method: 'POST',
            headers: requestHeaders(),
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            addAccommodationTypeModal.hide();
            showToast('success', 'Accommodation Type Added', `"${name}" has been added successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add accommodation type.');
        }
    } catch (error) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(error);
    }
}

async function editAccommodationType(id) {
    try {
        const encodedId = IdEncoder.encode(id);
        const response = await fetch(`${window.BASE_URL}/api/accommodation-types?id=${encodeURIComponent(encodedId)}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('danger', 'Error', 'Failed to fetch accommodation type details.');
            return;
        }

        const accommodation = result.data;
        document.getElementById('editAccommodationTypeId').value = accommodation.accommodation_id;
        document.getElementById('editAccommodationCode').value = accommodation.code || '';
        document.getElementById('editAccommodationName').value = accommodation.name || '';
        document.getElementById('editAccommodationIsDefault').checked = parseInt(accommodation.is_default, 10) === 1;
        editAccommodationTypeModal.show();
    } catch (error) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(error);
    }
}

async function submitEditAccommodationType() {
    const id = document.getElementById('editAccommodationTypeId').value;
    const code = document.getElementById('editAccommodationCode').value.trim().toUpperCase();
    const name = document.getElementById('editAccommodationName').value.trim();

    if (!id || !code || !name) {
        showToast('danger', 'Validation Error', 'Code and name are required.');
        return;
    }

    const payload = {
        accommodation_id: id,
        code,
        name,
        is_default: document.getElementById('editAccommodationIsDefault').checked ? 1 : 0
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/accommodation-types`, {
            method: 'PUT',
            headers: requestHeaders(),
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            editAccommodationTypeModal.hide();
            showToast('success', 'Accommodation Type Updated', `"${name}" has been updated successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update accommodation type.');
        }
    } catch (error) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(error);
    }
}

function deleteAccommodationType(id, name) {
    pendingDeleteAccommodationTypeId = id;
    pendingDeleteAccommodationTypeName = name;
    document.getElementById('deleteAccommodationTypeName').textContent = name;
    deleteAccommodationTypeModal.show();
}

async function confirmDeleteAccommodationType() {
    const id = pendingDeleteAccommodationTypeId;
    const name = pendingDeleteAccommodationTypeName;
    if (!id) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/accommodation-types`, {
            method: 'DELETE',
            headers: requestHeaders(),
            body: JSON.stringify({ accommodation_id: id })
        });
        const result = await response.json();
        deleteAccommodationTypeModal.hide();
        if (result.success) {
            showToast('success', 'Deleted', `"${name}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete accommodation type.');
        }
    } catch (error) {
        deleteAccommodationTypeModal.hide();
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(error);
    } finally {
        pendingDeleteAccommodationTypeId = null;
        pendingDeleteAccommodationTypeName = '';
    }
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.accommodation-type-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
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

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[character]));
}

function showToast(type, title, message) {
    document.querySelectorAll('.custom-toast').forEach(toast => toast.remove());
    const toast = document.createElement('div');
    const alertType = type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
    toast.className = `custom-toast alert alert-${alertType} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 350px; max-width: 450px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px;';
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <span class="fas ${icon} me-3 fs-4"></span>
            <div class="flex-grow-1">
                <strong class="d-block">${escapeHtml(title)}</strong>
                <span class="d-block text-sm">${escapeHtml(message)}</span>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 4000);
}
