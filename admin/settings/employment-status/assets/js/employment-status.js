let addEmploymentStatusModal, editEmploymentStatusModal, deleteEmploymentStatusModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addEmploymentStatusModal = new bootstrap.Modal(document.getElementById('addEmploymentStatusModal'));
    editEmploymentStatusModal = new bootstrap.Modal(document.getElementById('editEmploymentStatusModal'));
    deleteEmploymentStatusModal = new bootstrap.Modal(document.getElementById('deleteEmploymentStatusModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));
    document.getElementById('statusSearch').addEventListener('input', filterStatuses);
    document.getElementById('statusFilter').addEventListener('change', filterStatuses);
    document.getElementById('addEmploymentStatusModal').addEventListener('hidden.bs.modal', function() { document.getElementById('addEmploymentStatusForm').reset(); });
});

function filterStatuses() {
    const search = document.getElementById('statusSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#statusesTableBody tr');
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

function getHeaders() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN }; }

async function saveEmploymentStatus() {
    const name = document.getElementById('addEmploymentStatusName').value.trim();
    if (!name) { document.getElementById('addEmploymentStatusName').classList.add('is-invalid'); return; }
    document.getElementById('addEmploymentStatusName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/employment-status`, {
            method: 'POST', headers: getHeaders(),
            body: JSON.stringify({
                emp_stat_name: name,
                status: document.getElementById('addEmploymentStatusStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); addEmploymentStatusModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to save'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function editEmploymentStatus(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/employment-status?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const es = result.data;
        document.getElementById('editEmploymentStatusId').value = es.emp_stat_id;
        document.getElementById('editEmploymentStatusName').value = es.emp_stat_name;
        document.getElementById('editEmploymentStatusStatus').value = es.status || 'active';
        editEmploymentStatusModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function updateEmploymentStatus() {
    const id = document.getElementById('editEmploymentStatusId').value;
    const name = document.getElementById('editEmploymentStatusName').value.trim();
    if (!name) { document.getElementById('editEmploymentStatusName').classList.add('is-invalid'); return; }
    document.getElementById('editEmploymentStatusName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/employment-status`, {
            method: 'PUT', headers: getHeaders(),
            body: JSON.stringify({
                emp_stat_id: id,
                emp_stat_name: name,
                status: document.getElementById('editEmploymentStatusStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); editEmploymentStatusModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to update'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

function deleteEmploymentStatus(id, name) {
    document.getElementById('deleteEmploymentStatusId').value = id;
    document.getElementById('deleteEmploymentStatusName').textContent = name;
    deleteEmploymentStatusModal.show();
}

async function confirmDeleteEmploymentStatus() {
    const id = document.getElementById('deleteEmploymentStatusId').value;
    try {
        const response = await fetch(`${window.BASE_URL}/api/employment-status?id=${id}`, { method: 'DELETE', headers: getHeaders() });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); deleteEmploymentStatusModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to delete'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}
