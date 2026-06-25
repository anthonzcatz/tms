let addDepartmentModal, editDepartmentModal, deleteDepartmentModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addDepartmentModal = new bootstrap.Modal(document.getElementById('addDepartmentModal'));
    editDepartmentModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
    deleteDepartmentModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));

    document.getElementById('departmentSearch').addEventListener('input', filterDepartments);
    document.getElementById('statusFilter').addEventListener('change', filterDepartments);
    document.getElementById('addDepartmentModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addDepartmentForm').reset();
    });
});

function filterDepartments() {
    const search = document.getElementById('departmentSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#departmentsTableBody tr');
    let visible = 0;
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const show = name.includes(search) && (!status || rowStatus === status);
        row.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('emptyState').classList.toggle('d-none', visible > 0);
}

function showToast(type, title, message) {
    document.getElementById('toastIcon').innerHTML = type === 'success' ? '<span class="fas fa-check-circle text-success"></span>' : '<span class="fas fa-exclamation-circle text-danger"></span>';
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMessage').textContent = message;
    toast.show();
}

function getHeaders() {
    return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN };
}

async function saveDepartment() {
    const name = document.getElementById('addDepartmentName').value.trim();
    if (!name) {
        document.getElementById('addDepartmentName').classList.add('is-invalid');
        return;
    }
    document.getElementById('addDepartmentName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/departments`, {
            method: 'POST', headers: getHeaders(),
            body: JSON.stringify({
                department_name: name,
                department_code: document.getElementById('addDepartmentCode').value.trim(),
                status: document.getElementById('addDepartmentStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); addDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to save'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function editDepartment(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/departments?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const d = result.data;
        document.getElementById('editDepartmentId').value = d.dept_id;
        document.getElementById('editDepartmentName').value = d.department_name;
        document.getElementById('editDepartmentCode').value = d.department_code || '';
        document.getElementById('editDepartmentStatus').value = d.status || 'active';
        editDepartmentModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function updateDepartment() {
    const id = document.getElementById('editDepartmentId').value;
    const name = document.getElementById('editDepartmentName').value.trim();
    if (!name) { document.getElementById('editDepartmentName').classList.add('is-invalid'); return; }
    document.getElementById('editDepartmentName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/departments`, {
            method: 'PUT', headers: getHeaders(),
            body: JSON.stringify({
                dept_id: id, department_name: name,
                department_code: document.getElementById('editDepartmentCode').value.trim(),
                status: document.getElementById('editDepartmentStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); editDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to update'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

function deleteDepartment(id, name) {
    document.getElementById('deleteDepartmentId').value = id;
    document.getElementById('deleteDepartmentName').textContent = name;
    deleteDepartmentModal.show();
}

async function confirmDeleteDepartment() {
    const id = document.getElementById('deleteDepartmentId').value;
    try {
        const response = await fetch(`${window.BASE_URL}/api/departments?id=${id}`, { method: 'DELETE', headers: getHeaders() });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); deleteDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to delete'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}
