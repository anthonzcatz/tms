let addEmployeeModal, editEmployeeModal, viewEmployeeModal, deleteEmployeeModal, toast;
let addWizardCurrentStep = 1;
let editWizardCurrentStep = 1;
const WIZARD_TOTAL_STEPS = 5;

document.addEventListener('DOMContentLoaded', function() {
    addEmployeeModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
    editEmployeeModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    viewEmployeeModal = new bootstrap.Modal(document.getElementById('viewEmployeeModal'));
    deleteEmployeeModal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));

    document.getElementById('employeeSearch').addEventListener('input', filterEmployees);
    document.getElementById('departmentFilter').addEventListener('change', filterEmployees);
    document.getElementById('positionFilter').addEventListener('change', filterEmployees);
    document.getElementById('subDepartmentFilter').addEventListener('change', filterEmployees);
    document.getElementById('employmentStatusFilter').addEventListener('change', filterEmployees);

    // Pre-select filters from URL query parameters
    const params = new URLSearchParams(window.location.search);
    const deptParam = params.get('department');
    const posParam = params.get('position');
    const subDeptParam = params.get('sub_department');
    const empStatusParam = params.get('employment_status');
    if (deptParam) { document.getElementById('departmentFilter').value = deptParam; }
    if (posParam) { document.getElementById('positionFilter').value = posParam; }
    if (subDeptParam) { document.getElementById('subDepartmentFilter').value = subDeptParam; }
    if (empStatusParam) { document.getElementById('employmentStatusFilter').value = empStatusParam; }
    if (deptParam || posParam || subDeptParam || empStatusParam) { filterEmployees(); }
    document.getElementById('addEmployeeModal').addEventListener('show.bs.modal', function() {
        loadRegions('add');
    });
    document.getElementById('addEmployeeModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addEmployeeForm').reset();
        resetAddWizard();
    });
    document.getElementById('editEmployeeModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('editEmployeeForm').reset();
        resetEditWizard();
    });
});

// ---- Wizard logic ----
function resetAddWizard() {
    addWizardCurrentStep = 1;
    renderWizard('add');
}

function resetEditWizard() {
    editWizardCurrentStep = 1;
    renderWizard('edit');
}

function renderWizard(mode) {
    const currentStep = mode === 'add' ? addWizardCurrentStep : editWizardCurrentStep;
    const prefix = mode === 'add' ? 'add' : 'edit';

    // Show/hide panes
    for (let i = 1; i <= WIZARD_TOTAL_STEPS; i++) {
        const pane = document.getElementById(prefix + 'WizardPane' + i);
        if (pane) pane.classList.toggle('d-none', i !== currentStep);

        // Update step indicators
        const step = document.getElementById(prefix + 'WizardStep' + i);
        if (step) {
            step.classList.remove('active', 'completed');
            if (i === currentStep) step.classList.add('active');
            else if (i < currentStep) step.classList.add('completed');
        }
    }

    // Update connector lines
    const lines = document.querySelectorAll('#' + prefix + 'WizardSteps .wizard-step-line');
    lines.forEach((line, idx) => {
        line.classList.toggle('completed', idx + 1 < currentStep);
    });

    // Prev button
    const prevBtn = document.getElementById(prefix + 'WizardPrevBtn');
    if (prevBtn) prevBtn.style.display = currentStep > 1 ? '' : 'none';

    // Next / Save buttons
    const nextBtn = document.getElementById(prefix + 'WizardNextBtn');
    const saveBtn = document.getElementById(prefix + 'WizardSaveBtn');
    const isLast = currentStep === WIZARD_TOTAL_STEPS;
    if (nextBtn) nextBtn.classList.toggle('d-none', isLast);
    if (saveBtn) saveBtn.classList.toggle('d-none', !isLast);
}

function wizardStep(mode, direction) {
    const currentStep = mode === 'add' ? addWizardCurrentStep : editWizardCurrentStep;

    // Validate current step before going forward
    if (direction === 1 && !validateWizardStep(currentStep, mode)) return;

    if (mode === 'add') {
        addWizardCurrentStep = Math.max(1, Math.min(WIZARD_TOTAL_STEPS, addWizardCurrentStep + direction));
    } else {
        editWizardCurrentStep = Math.max(1, Math.min(WIZARD_TOTAL_STEPS, editWizardCurrentStep + direction));
    }
    renderWizard(mode);
}

function validateWizardStep(step, mode) {
    const prefix = mode === 'add' ? 'add' : 'edit';
    const required = {
        1: [{id: prefix + 'FirstName', label: 'First Name'}, {id: prefix + 'BDate', label: 'Birthdate'}],
        2: [{id: prefix + 'ContactNo', label: 'Contact Number'}, {id: prefix + 'Email', label: 'Email'}, {id: prefix + 'Address', label: 'Address'}],
        3: [{id: prefix + 'Position', label: 'Position'}, {id: prefix + 'Department', label: 'Department'}, {id: prefix + 'Company', label: 'Company'}, {id: prefix + 'EmploymentStatus', label: 'Employment Status'}, {id: prefix + 'DateHired', label: 'Date Hired'}, {id: prefix + 'DailyRate', label: 'Daily Rate'}],
        4: [],
        5: [],
    };
    const fields = required[step] || [];
    for (const f of fields) {
        const el = document.getElementById(f.id);
        if (!el || !el.value.trim()) {
            el && el.classList.add('is-invalid');
            showToast('error', 'Required Field', f.label + ' is required.');
            el && el.focus();
            return false;
        }
        el.classList.remove('is-invalid');
    }
    return true;
}

function filterEmployees() {
    const search = document.getElementById('employeeSearch').value.toLowerCase();
    const dept = document.getElementById('departmentFilter').value;
    const pos = document.getElementById('positionFilter').value;
    const subDept = document.getElementById('subDepartmentFilter').value;
    const empStatus = document.getElementById('employmentStatusFilter').value;
    const rows = document.querySelectorAll('#employeesTableBody tr');
    let visible = 0;
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const rowDept = row.getAttribute('data-dept') || '';
        const rowPos = row.getAttribute('data-pos') || '';
        const rowSubDept = row.getAttribute('data-sub-dept') || '';
        const rowEmpStatus = row.getAttribute('data-emp-status') || '';
        const show = name.includes(search) &&
            (!dept || rowDept === dept) &&
            (!pos || rowPos === pos) &&
            (!subDept || rowSubDept === subDept) &&
            (!empStatus || rowEmpStatus === empStatus);
        row.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('emptyState').classList.toggle('d-none', visible > 0);
    const totalEl = document.getElementById('totalEmployees');
    if (totalEl) totalEl.textContent = visible;
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

// ---- Geocode cascading address helpers ----
async function loadRegions(mode) {
    const select = document.getElementById(mode + 'Region');
    if (!select) return;
    select.innerHTML = '<option value="">Select Region</option>';
    try {
        const res = await fetch(`${window.BASE_URL}/api/geocode?type=regions`);
        const result = await res.json();
        if (!result.success) return;
        result.data.forEach(r => {
            select.innerHTML += `<option value="${r.region_code}">${r.region_name}</option>`;
        });
    } catch (e) { console.error('loadRegions', e); }
}

async function loadProvinces(mode, selectedProvinceCode = '') {
    const regionCode = document.getElementById(mode + 'Region').value;
    const select = document.getElementById(mode + 'Province');
    select.innerHTML = '<option value="">Select Province</option>';
    document.getElementById(mode + 'City').innerHTML = '<option value="">Select City / Municipality</option>';
    document.getElementById(mode + 'Barangay').innerHTML = '<option value="">Select Barangay</option>';
    if (!regionCode) return;
    try {
        const res = await fetch(`${window.BASE_URL}/api/geocode?type=provinces&parent_code=${regionCode}`);
        const result = await res.json();
        if (!result.success) return;
        result.data.forEach(p => {
            select.innerHTML += `<option value="${p.province_code}" ${p.province_code === selectedProvinceCode ? 'selected' : ''}>${p.province_name}</option>`;
        });
    } catch (e) { console.error('loadProvinces', e); }
}

async function loadCities(mode, selectedCityCode = '') {
    const provinceCode = document.getElementById(mode + 'Province').value;
    const select = document.getElementById(mode + 'City');
    select.innerHTML = '<option value="">Select City / Municipality</option>';
    document.getElementById(mode + 'Barangay').innerHTML = '<option value="">Select Barangay</option>';
    if (!provinceCode) return;
    try {
        const res = await fetch(`${window.BASE_URL}/api/geocode?type=cities&parent_code=${provinceCode}`);
        const result = await res.json();
        if (!result.success) return;
        result.data.forEach(c => {
            select.innerHTML += `<option value="${c.city_municipality_code}" ${c.city_municipality_code === selectedCityCode ? 'selected' : ''}>${c.city_municipality_name}</option>`;
        });
    } catch (e) { console.error('loadCities', e); }
}

async function loadBarangays(mode, selectedBarangayCode = '') {
    const cityCode = document.getElementById(mode + 'City').value;
    const select = document.getElementById(mode + 'Barangay');
    select.innerHTML = '<option value="">Select Barangay</option>';
    if (!cityCode) return;
    try {
        const res = await fetch(`${window.BASE_URL}/api/geocode?type=barangays&parent_code=${cityCode}`);
        const result = await res.json();
        if (!result.success) return;
        result.data.forEach(b => {
            select.innerHTML += `<option value="${b.barangay_code}" ${b.barangay_code === selectedBarangayCode ? 'selected' : ''}>${b.barangay_name}</option>`;
        });
    } catch (e) { console.error('loadBarangays', e); }
}

function buildFullAddress(mode) {
    const street = document.getElementById(mode + 'StreetAddress').value.trim();
    const barangay = document.getElementById(mode + 'Barangay');
    const city = document.getElementById(mode + 'City');
    const province = document.getElementById(mode + 'Province');

    const parts = [];
    if (street) parts.push(street);
    if (barangay && barangay.value) parts.push(barangay.options[barangay.selectedIndex].text);
    if (city && city.value) parts.push(city.options[city.selectedIndex].text);
    if (province && province.value) parts.push(province.options[province.selectedIndex].text);

    document.getElementById(mode + 'Address').value = parts.join(', ');
}

async function restoreGeocodeAddress(mode, provinceCode, cityCode, barangayCode, streetAddress) {
    if (!provinceCode) return;
    try {
        const res = await fetch(`${window.BASE_URL}/api/geocode?type=full&province_code=${provinceCode}&city_municipality_code=${cityCode || ''}&barangay_code=${barangayCode || ''}`);
        const result = await res.json();
        if (!result.success || !result.data.province) return;

        const data = result.data;
        const regionCode = data.province.region_code;

        // Ensure regions are loaded
        if (document.getElementById(mode + 'Region').options.length <= 1) {
            await loadRegions(mode);
        }

        // Set region and cascade down
        document.getElementById(mode + 'Region').value = regionCode;
        await loadProvinces(mode, provinceCode);
        await loadCities(mode, cityCode);
        await loadBarangays(mode, barangayCode);

        if (streetAddress) {
            document.getElementById(mode + 'StreetAddress').value = streetAddress;
        }
        buildFullAddress(mode);
    } catch (e) { console.error('restoreGeocodeAddress', e); }
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
        b_permanent_address: document.getElementById(mode + 'Address').value.trim(),
        emp_street_address: document.getElementById(mode + 'StreetAddress').value.trim(),
        emp_province_code: document.getElementById(mode + 'Province').value || '',
        emp_city_code: document.getElementById(mode + 'City').value || '',
        emp_barangay_code: document.getElementById(mode + 'Barangay').value || '',
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
        document.getElementById('editStreetAddress').value = e.emp_street_address || '';
        document.getElementById('editAddress').value = e.b_permanent_address || e.b_address || '';
        await loadRegions('edit');
        await restoreGeocodeAddress('edit', e.emp_province_code || '', e.emp_city_code || '', e.emp_barangay_code || '', e.emp_street_address || '');
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
