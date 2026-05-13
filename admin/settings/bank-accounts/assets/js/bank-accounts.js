// Bank Accounts Module

document.addEventListener('DOMContentLoaded', function() {
    console.log('Bank Accounts module initialized');
});

let addAccountModal, editAccountModal;

document.addEventListener('DOMContentLoaded', function() {
    addAccountModal = new bootstrap.Modal(document.getElementById('addAccountModal'));
    editAccountModal = new bootstrap.Modal(document.getElementById('editAccountModal'));
});

// Toggle How It Works
function toggleHowItWorks() {
    const content = document.getElementById('howItWorksContent');
    const icon = document.getElementById('howItWorksIcon');
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Open Add Modal
function openAddAccountModal() {
    document.getElementById('addBankName').value = '';
    document.getElementById('addAccountName').value = '';
    document.getElementById('addAccountNumber').value = '';
    document.getElementById('addAccountType').value = '';
    document.getElementById('addBranchId').value = '';
    document.getElementById('addPaymentMethodId').value = '';
    document.getElementById('addNotes').value = '';
    addAccountModal.show();
}

// Submit Add
async function submitAddAccount() {
    const bankName = document.getElementById('addBankName').value.trim();
    const accountName = document.getElementById('addAccountName').value.trim();
    const accountNumber = document.getElementById('addAccountNumber').value.trim();

    if (!bankName || !accountName || !accountNumber) {
        showToast('danger', 'Validation Error', 'Bank name, account name, and account number are required.');
        return;
    }

    const payload = {
        bank_name: bankName,
        account_name: accountName,
        account_number: accountNumber,
        account_type: document.getElementById('addAccountType').value.trim() || null,
        branch_id: document.getElementById('addBranchId').value || null,
        payment_method_id: document.getElementById('addPaymentMethodId').value || null,
        notes: document.getElementById('addNotes').value.trim() || null,
        is_active: 1
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            addAccountModal.hide();
            showToast('success', 'Account Added', `"${bankName}" has been added successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add bank account.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Edit Account - fetch and populate
async function editAccount(accountId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts?id=${accountId}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('danger', 'Error', 'Failed to fetch account details.');
            return;
        }
        const a = result.data;
        document.getElementById('editAccountId').value = a.bank_account_id;
        document.getElementById('editBankName').value = a.bank_name;
        document.getElementById('editAccountName').value = a.account_name;
        document.getElementById('editAccountNumber').value = a.account_number;
        document.getElementById('editAccountType').value = a.account_type || '';
        document.getElementById('editBranchId').value = a.branch_id || '';
        document.getElementById('editPaymentMethodId').value = a.payment_method_id || '';
        document.getElementById('editNotes').value = a.notes || '';
        document.getElementById('editIsActive').checked = !!parseInt(a.is_active);
        editAccountModal.show();
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Submit Edit
async function submitEditAccount() {
    const accountId = document.getElementById('editAccountId').value;
    const bankName = document.getElementById('editBankName').value.trim();
    const accountName = document.getElementById('editAccountName').value.trim();
    const accountNumber = document.getElementById('editAccountNumber').value.trim();

    if (!bankName || !accountName || !accountNumber) {
        showToast('danger', 'Validation Error', 'Bank name, account name, and account number are required.');
        return;
    }

    const payload = {
        bank_account_id: accountId,
        bank_name: bankName,
        account_name: accountName,
        account_number: accountNumber,
        account_type: document.getElementById('editAccountType').value.trim() || null,
        branch_id: document.getElementById('editBranchId').value || null,
        payment_method_id: document.getElementById('editPaymentMethodId').value || null,
        notes: document.getElementById('editNotes').value.trim() || null,
        is_active: document.getElementById('editIsActive').checked ? 1 : 0
    };

    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            editAccountModal.hide();
            showToast('success', 'Account Updated', `"${bankName}" has been updated successfully.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update bank account.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Toggle Status
async function toggleAccountStatus(accountId, isActive) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bank_account_id: accountId, is_active: isActive ? 1 : 0 })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Status Updated', `Bank account has been ${isActive ? 'activated' : 'deactivated'}.`);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to update status.');
            location.reload();
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        location.reload();
    }
}

// Delete Account
async function deleteAccount(accountId, accountLabel) {
    if (!confirm(`Are you sure you want to delete "${accountLabel}"?\n\nThis cannot be undone if this account is already used in transactions.`)) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bank_account_id: accountId })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Account Deleted', `"${accountLabel}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete bank account.');
        }
    } catch (err) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    }
}

// Apply Filters
function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const branch = document.getElementById('filterBranch').value;
    const status = document.getElementById('filterStatus').value;

    const rows = document.querySelectorAll('.account-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search');
        const rowBranch = row.getAttribute('data-branch');
        const rowStatus = row.getAttribute('data-status');

        let show = true;
        if (search && !rowSearch.includes(search)) show = false;
        if (branch) {
            if (branch === '__global__' && rowBranch !== '__global__') show = false;
            else if (branch !== '__global__' && rowBranch !== branch) show = false;
        }
        if (status && rowStatus !== status) show = false;

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const noResults = document.getElementById('noResultsMsg');
    if (noResults) noResults.classList.toggle('d-none', visibleCount > 0);
}

// Reset Filters
function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterBranch').value = '';
    document.getElementById('filterStatus').value = '';
    applyFilters();
}

// Toast notification
function showToast(type, title, message) {
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(t => t.remove());

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
        </div>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 4000);
}
