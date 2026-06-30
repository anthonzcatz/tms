let addPositionModal, editPositionModal, deletePositionModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addPositionModal = new bootstrap.Modal(document.getElementById('addPositionModal'));
    editPositionModal = new bootstrap.Modal(document.getElementById('editPositionModal'));
    deletePositionModal = new bootstrap.Modal(document.getElementById('deletePositionModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));
    document.getElementById('positionSearch').addEventListener('input', filterPositions);
    document.getElementById('statusFilter').addEventListener('change', filterPositions);
    document.getElementById('addPositionModal').addEventListener('hidden.bs.modal', function() { document.getElementById('addPositionForm').reset(); });
});

function filterPositions() {
    const search = document.getElementById('positionSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#positionsTableBody tr');
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

async function savePosition() {
    const name = document.getElementById('addPositionName').value.trim();
    if (!name) { document.getElementById('addPositionName').classList.add('is-invalid'); return; }
    document.getElementById('addPositionName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/positions`, {
            method: 'POST', headers: getHeaders(),
            body: JSON.stringify({
                position_name: name,
                pos_code: document.getElementById('addPositionCode').value.trim(),
                status: document.getElementById('addPositionStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); addPositionModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to save'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function editPosition(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/positions?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const p = result.data;
        document.getElementById('editPositionId').value = p.pos_id;
        document.getElementById('editPositionName').value = p.position_name;
        document.getElementById('editPositionCode').value = p.pos_code || '';
        document.getElementById('editPositionStatus').value = p.status || 'active';
        editPositionModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function updatePosition() {
    const id = document.getElementById('editPositionId').value;
    const name = document.getElementById('editPositionName').value.trim();
    if (!name) { document.getElementById('editPositionName').classList.add('is-invalid'); return; }
    document.getElementById('editPositionName').classList.remove('is-invalid');
    try {
        const response = await fetch(`${window.BASE_URL}/api/positions`, {
            method: 'PUT', headers: getHeaders(),
            body: JSON.stringify({
                pos_id: id,
                position_name: name,
                pos_code: document.getElementById('editPositionCode').value.trim(),
                status: document.getElementById('editPositionStatus').value
            })
        });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); editPositionModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to update'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

function deletePosition(id, name) {
    document.getElementById('deletePositionId').value = id;
    document.getElementById('deletePositionName').textContent = name;
    deletePositionModal.show();
}

async function confirmDeletePosition() {
    const id = document.getElementById('deletePositionId').value;
    try {
        const response = await fetch(`${window.BASE_URL}/api/positions?id=${id}`, { method: 'DELETE', headers: getHeaders() });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); deletePositionModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to delete'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}
