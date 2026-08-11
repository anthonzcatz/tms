// Bank Accounts Module

document.addEventListener('DOMContentLoaded', function() {
    console.log('Bank Accounts module initialized');
});

let addAccountModal, editAccountModal, deleteAccountModal;
let pendingDeleteAccountId = null;
let pendingDeleteAccountName = '';

document.addEventListener('DOMContentLoaded', function() {
    addAccountModal = new bootstrap.Modal(document.getElementById('addAccountModal'));
    editAccountModal = new bootstrap.Modal(document.getElementById('editAccountModal'));
    deleteAccountModal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));

    const confirmDeleteBtn = document.getElementById('confirmDeleteAccountBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', confirmDeleteAccount);
    }
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
    document.getElementById('addCurrentBalance').value = '0.00';
    document.getElementById('addPaymentMethodId').value = '';
    document.getElementById('addNotes').value = '';

    // Pre-select branch based on the current user's assigned branch(es)
    const branchSelect = document.getElementById('addBranchId');
    if (window.USER_ROLE_CODE !== 'SUPER_ADMIN' && window.USER_BRANCH_ID) {
        const userBranches = window.USER_BRANCH_ID.split(',').filter(function(id) { return id.trim() !== ''; });
        // Pre-select the user's first assigned branch
        branchSelect.value = userBranches[0] || '';
    } else {
        branchSelect.value = '';
    }

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
        current_balance: parseFloat(document.getElementById('addCurrentBalance').value) || 0,
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
        const encodedAccountId = IdEncoder.encode(accountId);
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts?id=${encodedAccountId}`);
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
        document.getElementById('editCurrentBalance').value = '₱' + (a.current_balance || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
            await fetchUpdatedAccountRow(accountId);
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
function deleteAccount(accountId, accountLabel) {
    pendingDeleteAccountId = accountId;
    pendingDeleteAccountName = accountLabel;
    document.getElementById('deleteAccountName').textContent = accountLabel;
    deleteAccountModal.show();
}

async function confirmDeleteAccount() {
    const accountId = pendingDeleteAccountId;
    const accountLabel = pendingDeleteAccountName;
    if (!accountId) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/bank-accounts`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bank_account_id: accountId })
        });
        const result = await response.json();
        deleteAccountModal.hide();
        if (result.success) {
            showToast('success', 'Account Deleted', `"${accountLabel}" has been deleted.`);
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to delete bank account.');
        }
    } catch (err) {
        deleteAccountModal.hide();
        showToast('danger', 'Error', 'An unexpected error occurred.');
        console.error(err);
    } finally {
        pendingDeleteAccountId = null;
        pendingDeleteAccountName = '';
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

// Fetch the latest account data and update the matching row in real-time
async function fetchUpdatedAccountRow(accountId) {
    const numericId = parseInt(accountId, 10);
    if (!numericId) {
        throw new Error('Invalid account ID');
    }
    const encodedAccountId = IdEncoder.encode(numericId);
    const response = await fetch(`${window.BASE_URL}/api/bank-accounts?id=${encodedAccountId}`);
    const result = await response.json();
    if (!result.success || !result.data) {
        throw new Error('Failed to fetch updated account');
    }
    updateAccountRow(result.data);
    updateStats();
    applyFilters();
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateAccountRow(acc) {
    const tableBody = document.querySelector('#accountsTable tbody');
    if (!tableBody || !acc || typeof acc !== 'object' || !acc.bank_account_id) {
        console.error('Invalid account data for row update', acc);
        return;
    }

    const typeIcons = {
        'BANK_TRANSFER': 'fa-university',
        'E_WALLET': 'fa-mobile-alt',
        'OTHER': 'fa-ellipsis-h'
    };
    const typeColors = {
        'BANK_TRANSFER': 'primary',
        'E_WALLET': 'purple',
        'OTHER': 'secondary'
    };

    const iconClass = typeIcons[acc.method_type ?? ''] || 'fa-university';
    const typeColor = typeColors[acc.method_type ?? ''] || 'primary';
    const branchId = acc.branch_id ? String(acc.branch_id) : '__global__';
    const status = acc.is_active ? 'active' : 'inactive';
    const balance = '₱' + Number(acc.current_balance ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const accountLabel = escapeHtml(acc.bank_name + ' - ' + acc.account_name);
    const searchData = escapeHtml((acc.bank_name + ' ' + acc.account_name + ' ' + acc.account_number).toLowerCase());

    const accountTypeHtml = acc.account_type
        ? `<div class="text-muted" style="font-size:0.75rem;">${escapeHtml(acc.account_type)}</div>`
        : '';

    const methodHtml = acc.method_name
        ? `<span class="badge bg-soft-${typeColor} text-${typeColor}">${escapeHtml(acc.method_name)}</span>`
        : '<span class="text-muted small">—</span>';

    const branchHtml = acc.branch_name
        ? `<span class="badge bg-soft-secondary text-secondary"><span class="fas fa-building me-1"></span>${escapeHtml(acc.branch_name)}</span>`
        : `<span class="badge bg-soft-info text-info"><span class="fas fa-globe me-1"></span>Company-wide</span>`;

    const rowHtml = `
      <tr class="account-row"
          data-account-id="${acc.bank_account_id}"
          data-branch="${branchId}"
          data-status="${status}"
          data-search="${searchData}">
        <td class="ps-3 py-3">
          <div class="d-flex align-items-center">
            <div class="bank-icon bg-soft-${typeColor} text-${typeColor} me-3">
              <span class="fas ${iconClass}"></span>
            </div>
            <div>
              <div class="fw-semibold">${escapeHtml(acc.bank_name)}</div>
              <div class="text-muted small">${escapeHtml(acc.account_name)}</div>
              ${accountTypeHtml}
            </div>
          </div>
        </td>
        <td class="py-3"><code>${escapeHtml(acc.account_number)}</code></td>
        <td class="py-3">${methodHtml}</td>
        <td class="py-3">${branchHtml}</td>
        <td class="py-3"><div class="fw-bold text-success">${balance}</div></td>
        <td class="py-3">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox"
              ${acc.is_active ? 'checked' : ''}
              onchange="toggleAccountStatus(${acc.bank_account_id}, this.checked)"
              title="Toggle status">
          </div>
        </td>
        <td class="py-3 text-end pe-3">
          <button class="btn btn-sm btn-outline-secondary me-1" onclick="openBalanceAdjustmentModal(${acc.bank_account_id}, ${Number(acc.current_balance ?? 0)})" title="Balance Adjustment">
            <span class="fas fa-balance-scale"></span>
          </button>
          <button class="btn btn-sm btn-outline-info me-1" onclick="openViewTransactionsModal(${acc.bank_account_id})" title="View Transactions">
            <span class="fas fa-history"></span>
          </button>
          <button class="btn btn-sm btn-outline-warning me-1" onclick="editAccount(${acc.bank_account_id})" title="Edit">
            <span class="fas fa-edit"></span>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteAccount(${acc.bank_account_id}, '${accountLabel}')" title="Delete">
            <span class="fas fa-trash"></span>
          </button>
        </td>
      </tr>
    `;

    let row = tableBody.querySelector(`tr.account-row[data-account-id="${acc.bank_account_id}"]`);
    if (row) {
        row.outerHTML = rowHtml;
    } else {
        tableBody.insertAdjacentHTML('beforeend', rowHtml);
    }
}

function updateStats() {
    const rows = document.querySelectorAll('.account-row');
    let active = 0;
    let companyWide = 0;
    rows.forEach(row => {
        if (row.getAttribute('data-status') === 'active') active++;
        if (row.getAttribute('data-branch') === '__global__') companyWide++;
    });
    const total = rows.length;
    const inactive = total - active;

    const statTotal = document.getElementById('statTotal');
    if (statTotal) statTotal.textContent = total;
    const statActive = document.getElementById('statActive');
    if (statActive) statActive.textContent = active;
    const statInactive = document.getElementById('statInactive');
    if (statInactive) statInactive.textContent = inactive;
    const statCompanyWide = document.getElementById('statCompanyWide');
    if (statCompanyWide) statCompanyWide.textContent = companyWide;
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
