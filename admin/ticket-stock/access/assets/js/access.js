const ticketStockAccessConfig = window.TICKET_STOCK_ACCESS_CONFIG || {};
let ticketStockAccessModal;

function ticketStockAccessEscape(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function ticketStockAccessHeaders() {
    const headers = { 'Content-Type': 'application/json' };
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    if (token) headers['X-CSRF-TOKEN'] = token;
    return headers;
}

function ticketStockAccessToast(type, message) {
    document.querySelectorAll('.ticket-stock-access-toast').forEach(element => element.remove());
    const alert = document.createElement('div');
    alert.className = `ticket-stock-access-toast alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed top-0 end-0 m-3 shadow`;
    alert.style.zIndex = '1100';
    alert.textContent = message;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3500);
}

function ticketStockAccessTypeLabel(type) {
    return type === 'APPROVER' ? 'Approver' : 'Receiver';
}

async function loadTicketStockAccessAssignments() {
    const table = document.querySelector('#ticketStockAccessTable tbody');
    if (!table) return;
    table.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading...</td></tr>';

    const params = new URLSearchParams();
    const branchId = document.getElementById('ticketStockAccessBranchFilter')?.value || '';
    const accessType = document.getElementById('ticketStockAccessTypeFilter')?.value || '';
    const status = document.getElementById('ticketStockAccessStatusFilter')?.value || '';
    if (branchId) params.set('branch_id', branchId);
    if (accessType) params.set('access_type', accessType);
    if (status !== '') params.set('is_active', status);

    try {
        const response = await fetch(`${ticketStockAccessConfig.apiUrl}?${params.toString()}`, {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to load Ticket Stock Access assignments.');
        const assignments = Array.isArray(result.data) ? result.data : [];
        table.innerHTML = assignments.length ? assignments.map(assignment => {
            const userName = assignment.full_name || assignment.username || 'Unknown user';
            const status = Number(assignment.is_active) === 1;
            return `<tr data-assignment-id="${Number(assignment.assignment_id)}" data-user-id="${Number(assignment.user_id)}" data-branch-id="${Number(assignment.branch_id)}" data-access-type="${ticketStockAccessEscape(assignment.access_type)}">
                <td>${ticketStockAccessEscape(assignment.branch_name)}</td>
                <td><strong>${ticketStockAccessEscape(userName)}</strong><small class="d-block text-muted">${ticketStockAccessEscape(assignment.username || '')}</small></td>
                <td>${ticketStockAccessEscape(assignment.role_name || assignment.role_code || '')}</td>
                <td><span class="badge ${assignment.access_type === 'APPROVER' ? 'bg-primary' : 'bg-info'}">${ticketStockAccessEscape(ticketStockAccessTypeLabel(assignment.access_type))}</span></td>
                <td><span class="badge ${status ? 'bg-success' : 'bg-secondary'}">${status ? 'Active' : 'Inactive'}</span></td>
                <td class="text-nowrap">${ticketStockAccessEscape(assignment.created_at || '')}</td>
                <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="editTicketStockAccessAssignment(this.closest('tr'))"><span class="fas fa-edit"></span></button><button class="btn btn-sm ${status ? 'btn-outline-danger' : 'btn-outline-success'}" type="button" onclick="toggleTicketStockAccessAssignment(${Number(assignment.assignment_id)}, ${status ? 0 : 1})"><span class="fas ${status ? 'fa-ban' : 'fa-check'}"></span> ${status ? 'Deactivate' : 'Activate'}</button></td>
            </tr>`;
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No assignments found.</td></tr>';
    } catch (error) {
        table.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${ticketStockAccessEscape(error.message)}</td></tr>`;
    }
}

function openTicketStockAccessModal() {
    document.getElementById('ticketStockAccessAssignmentId').value = '';
    document.getElementById('ticketStockAccessUser').value = '';
    document.getElementById('ticketStockAccessBranch').value = '';
    document.getElementById('ticketStockAccessType').value = 'APPROVER';
    document.getElementById('ticketStockAccessModalLabel').textContent = 'New Ticket Stock Access Assignment';
    ticketStockAccessModal = ticketStockAccessModal || bootstrap.Modal.getOrCreateInstance(document.getElementById('ticketStockAccessModal'));
    ticketStockAccessModal.show();
}

function editTicketStockAccessAssignment(row) {
    document.getElementById('ticketStockAccessAssignmentId').value = row.dataset.assignmentId || '';
    document.getElementById('ticketStockAccessUser').value = row.dataset.userId || '';
    document.getElementById('ticketStockAccessBranch').value = row.dataset.branchId || '';
    document.getElementById('ticketStockAccessType').value = row.dataset.accessType || 'APPROVER';
    document.getElementById('ticketStockAccessModalLabel').textContent = 'Edit Ticket Stock Access Assignment';
    ticketStockAccessModal = ticketStockAccessModal || bootstrap.Modal.getOrCreateInstance(document.getElementById('ticketStockAccessModal'));
    ticketStockAccessModal.show();
}

async function saveTicketStockAccessAssignment() {
    const assignmentId = document.getElementById('ticketStockAccessAssignmentId').value;
    const userId = document.getElementById('ticketStockAccessUser').value;
    const branchId = document.getElementById('ticketStockAccessBranch').value;
    const accessType = document.getElementById('ticketStockAccessType').value;
    if (!userId || !branchId || !accessType) {
        ticketStockAccessToast('error', 'User, branch, and access type are required.');
        return;
    }

    try {
        const response = await fetch(ticketStockAccessConfig.apiUrl, {
            method: assignmentId ? 'PUT' : 'POST',
            headers: ticketStockAccessHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                ...(assignmentId ? { assignment_id: Number(assignmentId) } : {}),
                user_id: Number(userId),
                branch_id: Number(branchId),
                access_type: accessType,
                is_active: 1
            })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to save the assignment.');
        ticketStockAccessModal?.hide();
        ticketStockAccessToast('success', 'Ticket Stock Access assignment saved.');
        loadTicketStockAccessAssignments();
    } catch (error) {
        ticketStockAccessToast('error', error.message);
    }
}

async function toggleTicketStockAccessAssignment(assignmentId, isActive) {
    const action = isActive ? 'activate' : 'deactivate';
    if (!window.confirm(`Are you sure you want to ${action} this assignment?`)) return;
    try {
        const response = await fetch(ticketStockAccessConfig.apiUrl, {
            method: 'PUT',
            headers: ticketStockAccessHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ assignment_id: assignmentId, is_active: isActive })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || `Unable to ${action} the assignment.`);
        ticketStockAccessToast('success', `Assignment ${action}d.`);
        loadTicketStockAccessAssignments();
    } catch (error) {
        ticketStockAccessToast('error', error.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ['ticketStockAccessBranchFilter', 'ticketStockAccessTypeFilter', 'ticketStockAccessStatusFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', loadTicketStockAccessAssignments);
    });
    loadTicketStockAccessAssignments();
});
