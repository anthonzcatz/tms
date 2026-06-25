let addEmployeeModal, editEmployeeModal, viewEmployeeModal, deleteEmployeeModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addEmployeeModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
    editEmployeeModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    viewEmployeeModal = new bootstrap.Modal(document.getElementById('viewEmployeeModal'));
    deleteEmployeeModal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));

    document.getElementById('employeeSearch').addEventListener('input', filterEmployees);
    document.getElementById('departmentFilter').addEventListener('change', filterEmployees);
    document.getElementById('positionFilter').addEventListener('change', filterEmployees);
    document.getElementById('addEmployeeModal').addEventListener('hidden.bs.modal', function() { document.getElementById('addEmployeeForm').reset(); });
});

function filterEmployees() {
    const search = document.getElementById('employeeSearch').value.toLowerCase();
    const dept = document.getElementById('departmentFilter').value;
    const pos = document.getElementById('positionFilter').value;
    const rows = document.querySelectorAll('#employeesTableBody tr');
    let visible = 0;
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const rowDept = row.getAttribute('data-dept') || '';
        const rowPos = row.getAttribute('data-pos') || '';
        const show = name.includes(search) && (!dept || rowDept === dept) && (!pos || rowPos === pos);
        row.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('emptyState').classList.toggle('d-none', visible > 0);
}

function loadSubDepartments(mode) {
    const deptId = parseInt(document.getElementById(mode + 'Department').value);
    const select = document.getElementById(mode + 'SubDepartment');
    select.innerHTML = '<option value="">Select Sub-Department</option>';
    if (!deptId) return;
    const filtered = (window.subDepartments || []).filter(sd => parseInt(sd.main_department_id) === deptId);
    filtered.forEach(sd => {
        select.innerHTML += `<option value="${sd.sub_depart_id}">${sd.sub_department_name}</option>`;
    });
}

function showToast(type, title, message) {
    document.getElementById('toastIcon').innerHTML = type === 'success' ? '<span class="fas fa-check-circle text-success"></span>' : '<span class="fas fa-exclamation-circle text-danger"></span>';
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMessage').textContent = message;
    toast.show();
}

function getHeaders() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN }; }

function collectEmployeeData(mode) {
    return {
        first_name: document.getElementById(mode + 'FirstName').value.trim(),
        last_name: document.getElementById(mode + 'LastName').value.trim(),
        middle_name: document.getElementById(mode + 'MiddleName').value.trim(),
        b_date: document.getElementById(mode + 'BDate').value,
        b_sex: document.getElementById(mode + 'Sex').value,
        b_civil_status: document.getElementById(mode + 'CivilStatus').value,
        b_citizenship: document.getElementById(mode + 'Citizenship').value.trim(),
        b_religion: document.getElementById(mode + 'Religion').value.trim(),
        b_placebirth: document.getElementById(mode + 'PlaceBirth').value.trim(),
        b_cont_no: document.getElementById(mode + 'ContactNo').value.trim(),
        b_email: document.getElementById(mode + 'Email').value.trim(),
        b_address: document.getElementById(mode + 'Address').value.trim(),
        job_title: document.getElementById(mode + 'Position').value,
        b_department_id: document.getElementById(mode + 'Department').value,
        b_sub_department_id: document.getElementById(mode + 'SubDepartment').value || 0,
        b_company_id: document.getElementById(mode + 'Company').value,
        b_employment_status_id: document.getElementById(mode + 'EmploymentStatus').value,
        branch_id: document.getElementById(mode + 'Branch').value,
        date_hired: document.getElementById(mode + 'DateHired').value,
        daily_rate: document.getElementById(mode + 'DailyRate').value,
        cola: document.getElementById(mode + 'Cola').value || 0,
        b_philhealth: document.getElementById(mode + 'PhilHealth').value.trim(),
        b_sss: document.getElementById(mode + 'SSS').value.trim(),
        b_pagibig: document.getElementById(mode + 'Pagibig').value.trim(),
        b_tinnumber: document.getElementById(mode + 'Tin').value.trim(),
        emergency_contact_name: document.getElementById(mode + 'EmergencyName').value.trim(),
        emergency_contact_relationship: document.getElementById(mode + 'EmergencyRelationship').value.trim(),
        emergency_contact_number: document.getElementById(mode + 'EmergencyNumber').value.trim(),
        remarks: document.getElementById(mode + 'Remarks').value.trim(),
        employment_remarks: document.getElementById(mode + 'EmploymentRemarks').value.trim()
    };
}

async function saveEmployee() {
    const data = collectEmployeeData('add');
    if (!data.first_name || !data.b_date || !data.b_cont_no || !data.b_email || !data.b_address || !data.date_hired || !data.daily_rate || !data.job_title || !data.b_department_id || !data.b_company_id || !data.b_employment_status_id) {
        showToast('error', 'Error', 'Please fill all required fields'); return;
    }
    try {
        const response = await fetch(`${window.BASE_URL}/api/employees`, { method: 'POST', headers: getHeaders(), body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); addEmployeeModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to save'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function editEmployee(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/employees?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const e = result.data;
        document.getElementById('editEmployeeId').value = e.emp_id;
        document.getElementById('editFirstName').value = e.first_name || '';
        document.getElementById('editLastName').value = e.last_name || '';
        document.getElementById('editMiddleName').value = e.middle_name || '';
        document.getElementById('editBDate').value = e.b_date || '';
        document.getElementById('editSex').value = e.b_sex || '';
        document.getElementById('editCivilStatus').value = e.b_civil_status || '';
        document.getElementById('editCitizenship').value = e.b_citizenship || '';
        document.getElementById('editReligion').value = e.b_religion || '';
        document.getElementById('editPlaceBirth').value = e.b_placebirth || '';
        document.getElementById('editContactNo').value = e.b_cont_no || '';
        document.getElementById('editEmail').value = e.b_email || '';
        document.getElementById('editAddress').value = e.b_address || '';
        document.getElementById('editPosition').value = e.job_title || '';
        document.getElementById('editDepartment').value = e.b_department_id || '';
        loadSubDepartments('edit');
        document.getElementById('editSubDepartment').value = e.b_sub_department_id || '';
        document.getElementById('editCompany').value = e.b_company_id || '';
        document.getElementById('editEmploymentStatus').value = e.b_employment_status_id || '';
        document.getElementById('editBranch').value = e.branch_id || '';
        document.getElementById('editDateHired').value = e.date_hired || '';
        document.getElementById('editDailyRate').value = e.daily_rate || '';
        document.getElementById('editCola').value = e.cola || 0;
        document.getElementById('editPhilHealth').value = e.b_philhealth || '';
        document.getElementById('editSSS').value = e.b_sss || '';
        document.getElementById('editPagibig').value = e.b_pagibig || '';
        document.getElementById('editTin').value = e.b_tinnumber || '';
        document.getElementById('editEmergencyName').value = e.emergency_contact_name || '';
        document.getElementById('editEmergencyRelationship').value = e.emergency_contact_relationship || '';
        document.getElementById('editEmergencyNumber').value = e.emergency_contact_number || '';
        document.getElementById('editRemarks').value = e.remarks || '';
        document.getElementById('editEmploymentRemarks').value = e.employment_remarks || '';
        editEmployeeModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function updateEmployee() {
    const id = document.getElementById('editEmployeeId').value;
    const data = collectEmployeeData('edit');
    data.emp_id = id;
    try {
        const response = await fetch(`${window.BASE_URL}/api/employees`, { method: 'PUT', headers: getHeaders(), body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); editEmployeeModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to update'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}

async function viewEmployee(id) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/employees?id=${id}`);
        const result = await response.json();
        if (!result.success) { showToast('error', 'Error', result.error); return; }
        const e = result.data;
        document.getElementById('viewFirstName').textContent = e.first_name || '-';
        document.getElementById('viewLastName').textContent = e.last_name || '-';
        document.getElementById('viewMiddleName').textContent = e.middle_name || '-';
        document.getElementById('viewBDate').textContent = e.b_date || '-';
        document.getElementById('viewSex').textContent = e.b_sex || '-';
        document.getElementById('viewCivilStatus').textContent = e.b_civil_status || '-';
        document.getElementById('viewContactNo').textContent = e.b_cont_no || '-';
        document.getElementById('viewEmail').textContent = e.b_email || '-';
        document.getElementById('viewAddress').textContent = e.b_address || '-';
        document.getElementById('viewPosition').textContent = e.position_name || '-';
        document.getElementById('viewDepartment').textContent = e.department_name || '-';
        document.getElementById('viewSubDepartment').textContent = e.sub_department_name || '-';
        document.getElementById('viewCompany').textContent = e.company_name || '-';
        document.getElementById('viewEmploymentStatus').textContent = e.emp_stat_name || '-';
        document.getElementById('viewBranch').textContent = e.branch_name || '-';
        document.getElementById('viewDateHired').textContent = e.date_hired || '-';
        document.getElementById('viewDailyRate').textContent = e.daily_rate || '-';
        document.getElementById('viewCola').textContent = e.cola || '0';
        document.getElementById('viewPhilHealth').textContent = e.b_philhealth || '-';
        document.getElementById('viewSSS').textContent = e.b_sss || '-';
        document.getElementById('viewPagibig').textContent = e.b_pagibig || '-';
        document.getElementById('viewTin').textContent = e.b_tinnumber || '-';
        document.getElementById('viewEmergencyName').textContent = e.emergency_contact_name || '-';
        document.getElementById('viewEmergencyRelationship').textContent = e.emergency_contact_relationship || '-';
        document.getElementById('viewEmergencyNumber').textContent = e.emergency_contact_number || '-';
        viewEmployeeModal.show();
    } catch (error) { showToast('error', 'Error', error.message); }
}

function deleteEmployee(id, name) {
    document.getElementById('deleteEmployeeId').value = id;
    document.getElementById('deleteEmployeeName').textContent = name;
    deleteEmployeeModal.show();
}

async function confirmDeleteEmployee() {
    const id = document.getElementById('deleteEmployeeId').value;
    try {
        const response = await fetch(`${window.BASE_URL}/api/employees?id=${id}`, { method: 'DELETE', headers: getHeaders() });
        const result = await response.json();
        if (result.success) { showToast('success', 'Success', result.message); deleteEmployeeModal.hide(); location.reload(); }
        else { showToast('error', 'Error', result.error || 'Failed to delete'); }
    } catch (error) { showToast('error', 'Error', error.message); }
}
