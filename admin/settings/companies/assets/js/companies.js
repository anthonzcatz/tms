/**
 * Companies Management JavaScript
 */

let addCompanyModal, editCompanyModal, deleteCompanyModal, toast;

document.addEventListener('DOMContentLoaded', function() {
    addCompanyModal = new bootstrap.Modal(document.getElementById('addCompanyModal'));
    editCompanyModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
    deleteCompanyModal = new bootstrap.Modal(document.getElementById('deleteCompanyModal'));
    toast = new bootstrap.Toast(document.getElementById('toast'));

    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('companySearch').addEventListener('input', filterCompanies);
    document.getElementById('statusFilter').addEventListener('change', filterCompanies);

    document.getElementById('addCompanyModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addCompanyForm').reset();
    });
}

function filterCompanies() {
    const search = document.getElementById('companySearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#companiesTableBody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const matchSearch = name.includes(search);
        const matchStatus = !status || rowStatus === status;

        if (matchSearch && matchStatus) {
            row.classList.remove('d-none');
            visibleCount++;
        } else {
            row.classList.add('d-none');
        }
    });

    document.getElementById('emptyState').classList.toggle('d-none', visibleCount > 0);
}

function showToast(type, title, message) {
    const toastIcon = document.getElementById('toastIcon');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');

    toastIcon.innerHTML = type === 'success' ? '<span class="fas fa-check-circle text-success"></span>' : '<span class="fas fa-exclamation-circle text-danger"></span>';
    toastTitle.textContent = title;
    toastMessage.textContent = message;

    toast.show();
}

function getHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.CSRF_TOKEN
    };
}

async function saveCompany() {
    const companyName = document.getElementById('addCompanyName').value.trim();
    if (!companyName) {
        document.getElementById('addCompanyName').classList.add('is-invalid');
        return;
    }
    document.getElementById('addCompanyName').classList.remove('is-invalid');

    const data = {
        comp_name: companyName,
        comp_abbre: document.getElementById('addCompanyCode').value.trim(),
        company_address: document.getElementById('addCompanyAddress').value.trim(),
        company_contact: document.getElementById('addCompanyContact').value.trim(),
        company_email: document.getElementById('addCompanyEmail').value.trim(),
        comp_status: document.getElementById('addCompanyStatus').value
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/companies`, {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
            showToast('success', 'Success', result.message);
            addCompanyModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.error || 'Failed to save company');
        }
    } catch (error) {
        showToast('error', 'Error', 'Network error: ' + error.message);
    }
}

async function editCompany(companyId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/companies?id=${companyId}`);
        const result = await response.json();

        if (!result.success) {
            showToast('error', 'Error', result.error || 'Failed to load company');
            return;
        }

        const company = result.data;
        document.getElementById('editCompanyId').value = company.comp_id;
        document.getElementById('editCompanyName').value = company.comp_name;
        document.getElementById('editCompanyCode').value = company.comp_abbre || '';
        document.getElementById('editCompanyAddress').value = company.company_address || '';
        document.getElementById('editCompanyContact').value = company.company_contact || '';
        document.getElementById('editCompanyEmail').value = company.company_email || '';
        document.getElementById('editCompanyStatus').value = company.comp_status || 'active';

        editCompanyModal.show();
    } catch (error) {
        showToast('error', 'Error', 'Network error: ' + error.message);
    }
}

async function updateCompany() {
    const companyId = document.getElementById('editCompanyId').value;
    const companyName = document.getElementById('editCompanyName').value.trim();
    if (!companyName) {
        document.getElementById('editCompanyName').classList.add('is-invalid');
        return;
    }
    document.getElementById('editCompanyName').classList.remove('is-invalid');

    const data = {
        comp_id: companyId,
        comp_name: companyName,
        comp_abbre: document.getElementById('editCompanyCode').value.trim(),
        company_address: document.getElementById('editCompanyAddress').value.trim(),
        company_contact: document.getElementById('editCompanyContact').value.trim(),
        company_email: document.getElementById('editCompanyEmail').value.trim(),
        comp_status: document.getElementById('editCompanyStatus').value
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/companies`, {
            method: 'PUT',
            headers: getHeaders(),
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
            showToast('success', 'Success', result.message);
            editCompanyModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.error || 'Failed to update company');
        }
    } catch (error) {
        showToast('error', 'Error', 'Network error: ' + error.message);
    }
}

function deleteCompany(companyId, companyName) {
    document.getElementById('deleteCompanyId').value = companyId;
    document.getElementById('deleteCompanyName').textContent = companyName;
    deleteCompanyModal.show();
}

async function confirmDeleteCompany() {
    const companyId = document.getElementById('deleteCompanyId').value;

    try {
        const response = await fetch(`${window.BASE_URL}/api/companies?id=${companyId}`, {
            method: 'DELETE',
            headers: getHeaders()
        });

        const result = await response.json();
        if (result.success) {
            showToast('success', 'Success', result.message);
            deleteCompanyModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.error || 'Failed to delete company');
        }
    } catch (error) {
        showToast('error', 'Error', 'Network error: ' + error.message);
    }
}
