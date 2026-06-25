let addSubDepartmentModal, editSubDepartmentModal, deleteSubDepartmentModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addSubDepartmentModal = new bootstrap.Modal(document.getElementById('addSubDepartmentModal'));
    editSubDepartmentModal = new bootstrap.Modal(document.getElementById('editSubDepartmentModal'));
    deleteSubDepartmentModal = new bootstrap.Modal(document.getElementById('deleteSubDepartmentModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));
    document.getElementById('subDepartmentSearch').addEventListener('input', filterSubDepartments);
    document.getElementById('departmentFilter').addEventListener('change', filterSubDepartments);
    document.getElementById('addSubDepartmentModal').addEventListener('hidden.bs.modal', function() { document.getElementById('addSubDepartmentForm').reset(); });
});

function filterSubDepartments() {
    const search = document.getElementById('subDepartmentSearch').value.toLowerCase();
    const dept = document.getElementById('departmentFilter').value;
    const rows = document.querySelectorAll('#subDepartmentsTableBody tr');
    let visible = 0;
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const rowDept = row.getAttribute('data-dept') || '';
        const show = name.includes(search) && (!dept || rowDept === dept);
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

async function saveSubDepartment() {
    const name = document.getElementById('addSubDepartmentName').value.trim();
    const dept = document.getElementById('addSubDepartmentDept').value;
    if (!dept) { document.getElementById('addSubDepartmentDept').classList.add('is-invalid'); return; }
    document.getElementById('addSubDepartmentDept').classList.remove('is-invalid');
    if (!name) { document.getElementById('addSubDepartmentName').classList.add('is-invalid'); return; }
    document.getElementById('addSubDepartmentName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/sub-departments`, {
            method: 'POST', headers: getHeaders(),
            body: JSON.stringify({ sub_department_name: name, main_department_id: dept })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); addSubDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to save'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function editSubDepartment(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/sub-departments?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const sd = result.data;
        document.getElementById('editSubDepartmentId').value = sd.sub_depart_id;
        document.getElementById('editSubDepartmentDept').value = sd.main_department_id;
        document.getElementById('editSubDepartmentName').value = sd.sub_department_name;
        editSubDepartmentModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function updateSubDepartment() {
    const id = document.getElementById('editSubDepartmentId').value;
    const name = document.getElementById('editSubDepartmentName').value.trim();
    const dept = document.getElementById('editSubDepartmentDept').value;
    if (!dept) { document.getElementById('editSubDepartmentDept').classList.add('is-invalid'); return; }
    document.getElementById('editSubDepartmentDept').classList.remove('is-invalid');
    if (!name) { document.getElementById('editSubDepartmentName').classList.add('is-invalid'); return; }
    document.getElementById('editSubDepartmentName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/sub-departments`, {
            method: 'PUT', headers: getHeaders(),
            body: JSON.stringify({ sub_depart_id: id, sub_department_name: name, main_department_id: dept })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); editSubDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to update'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

function deleteSubDepartment(id, name) {
    document.getElementById('deleteSubDepartmentId').value = id;
    document.getElementById('deleteSubDepartmentName').textContent = name;
    deleteSubDepartmentModal.show();
}

async function confirmDeleteSubDepartment() {
    const id = document.getElementById('deleteSubDepartmentId').value;
    try {
        const response = await fetch(`${window.BASE_URL}/api/sub-departments?id=${id}`, { method: 'DELETE', headers: getHeaders() });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); deleteSubDepartmentModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to delete'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}
