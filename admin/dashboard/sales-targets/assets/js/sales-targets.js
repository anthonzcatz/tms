/**
 * Sales Targets Module JavaScript
 * Comprehensive table-based editing structure
 */

const BASE_URL = window.location.origin + '/TMS';

let currentMode = 'same';
let tableRows = [];

// Toast notification function
function showToast(type, message) {
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Utility functions
function stripCommas(v) {
    return String(v || '').replace(/,/g, '').trim();
}

function formatMonthReadable(yyyyMm) {
    if (!/^\d{4}-\d{2}$/.test(yyyyMm)) return yyyyMm;
    const [y, m] = yyyyMm.split('-');
    const date = new Date(parseInt(y, 10), parseInt(m, 10) - 1, 1);
    return date.toLocaleString('en-US', { month: 'long', year: 'numeric' });
}

function formatDateWithDay(dateStr) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
    const d = new Date(dateStr);
    const dateFormatted = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const dayName = d.toLocaleDateString('en-US', { weekday: 'long' });
    const isSunday = d.getDay() === 0;
    const dayClass = isSunday ? 'sunday' : '';
    return `<div class="st-date-container">${dateFormatted}<div class="st-date-day ${dayClass}">${dayName}</div></div>`;
}

function formatWithCommas(v) {
    const s = stripCommas(v);
    if (s === '') return '';
    const n = Number(s);
    if (!isFinite(n)) return '';
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function sanitizeDecimalTextInput($input) {
    const raw = String($input.value || '');
    let v = raw.replace(/,/g, '');
    v = v.replace(/[^0-9.]/g, '');
    const firstDot = v.indexOf('.');
    if (firstDot !== -1) {
        v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
    }
    $input.value = v;
}

function pad2(n) { return String(n).padStart(2, '0'); }

function lastDayOfMonth(yyyyMm) {
    const parts = yyyyMm.split('-');
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    return new Date(y, m, 0).getDate();
}

function makeDate(yyyyMm, day) {
    return `${yyyyMm}-${pad2(day)}`;
}

function money(v) {
    const n = parseFloat(v || 0);
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// DOM Elements
const stBranch = document.getElementById('stBranch');
const stMonth = document.getElementById('stMonth');
const stMode = document.getElementById('stMode');
const stSameAmount = document.getElementById('stSameAmount');
const stSameNotes = document.getElementById('stSameNotes');
const stSameModeFields = document.getElementById('stSameModeFields');
const stManualModeFields = document.getElementById('stManualModeFields');
const stTable = document.getElementById('stTable');
const stSummary = document.getElementById('stSummary');
const stSelectedMonthBadge = document.getElementById('currentMonthDisplay');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    populateBranchSelector();
    setDefaultMonth();
    setMode('same');
    loadMonth();
    
    // Event listeners
    stMonth.addEventListener('change', loadMonth);
    stBranch.addEventListener('change', function() {
        stSameAmount.value = '';
        stSameNotes.value = '';
        loadMonth();
    });
    stMode.addEventListener('change', function() {
        setMode(this.value);
    });
    
    // Table input events
    stTable.addEventListener('input', function(e) {
        if (e.target.tagName === 'INPUT') {
            updateSummary();
        }
    });
    stSameAmount.addEventListener('input', function() {
        sanitizeDecimalTextInput(stSameAmount);
        applySamePreviewToTable();
    });
    stSameAmount.addEventListener('blur', function() {
        stSameAmount.value = formatWithCommas(stSameAmount.value);
        applySamePreviewToTable();
    });
    stTable.addEventListener('input', function(e) {
        if (e.target.classList.contains('st-table-input') && currentMode === 'manual') {
            sanitizeDecimalTextInput(e.target);
        }
    });
    stTable.addEventListener('blur', function(e) {
        if (e.target.classList.contains('st-table-input') && currentMode === 'manual') {
            e.target.value = formatWithCommas(e.target.value);
            updateSummary();
        }
    }, true);
    
    // Button events
    document.getElementById('stApplySameBtn').addEventListener('click', applySameToMonth);
    document.getElementById('stSaveManualBtn').addEventListener('click', saveManualChanges);
    document.getElementById('stClearMonthBtn').addEventListener('click', clearMonth);
    document.getElementById('stReloadBtn').addEventListener('click', loadMonth);
});

// Populate branch selector
async function populateBranchSelector() {
    try {
        const response = await fetch(`${BASE_URL}/api/analytics/branches.php`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const branchOptions = data.data.map(branch => 
                `<option value="${branch.branch_id}">${branch.branch_name}</option>`
            ).join('');
            
            stBranch.innerHTML = '<option value="">All Branches</option>' + branchOptions;
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Set default month
function setDefaultMonth() {
    const currentMonth = new Date().toISOString().slice(0, 7);
    stMonth.value = currentMonth;
    stSelectedMonthBadge.textContent = currentMonth;
}

// Render table
function renderTable(rows, yyyyMm) {
    const tbody = stTable.querySelector('tbody');
    tbody.innerHTML = '';

    const days = lastDayOfMonth(yyyyMm);
    const map = {};
    (rows || []).forEach(r => { map[r.target_date] = r; });

    tableRows = [];
    for (let d = 1; d <= days; d++) {
        const dt = makeDate(yyyyMm, d);
        const existing = map[dt] || null;
        const amount = existing ? existing.target_amount : '';
        const notes = existing ? (existing.notes || '') : '';

        tableRows.push({
            target_date: dt,
            target_amount: amount,
            notes: notes,
            exists: !!existing
        });

        tbody.innerHTML += `
            <tr data-date="${escapeHtml(dt)}">
                <td class="text-nowrap">${d}</td>
                <td class="text-nowrap">${formatDateWithDay(dt)}</td>
                <td>
                    <input type="text" class="form-control st-table-input" inputmode="decimal" autocomplete="off" value="${escapeHtml(formatWithCommas(amount))}" ${currentMode === 'manual' ? '' : 'disabled'}>
                </td>
                <td>
                    <input type="text" class="form-control" maxlength="255" value="${escapeHtml(notes)}" ${currentMode === 'manual' ? '' : 'disabled'}>
                </td>
            </tr>
        `;
    }

    updateSummary();
    document.getElementById('stSaveManualBtn').disabled = currentMode !== 'manual';

    if (currentMode === 'same' && String(stSameAmount.value || '').trim() !== '') {
        applySamePreviewToTable();
    }
}

// Update summary
function updateSummary() {
    const yyyyMm = stMonth.value;
    const days = lastDayOfMonth(yyyyMm);
    let filled = 0;
    let total = 0;
    stTable.querySelectorAll('tbody tr').forEach(function(row) {
        const amt = parseFloat(stripCommas(row.querySelector('td:nth-child(3) input').value || 0));
        if (!isNaN(amt) && amt > 0) filled++;
        total += (isNaN(amt) ? 0 : amt);
    });
    stSummary.textContent = `${filled}/${days} day(s) set | Total target: ₱ ${money(total)}`;
    stSelectedMonthBadge.textContent = yyyyMm;
}

// Apply same preview to table
function applySamePreviewToTable() {
    if (currentMode !== 'same') return;
    const formatted = formatWithCommas(stSameAmount.value);
    stTable.querySelectorAll('tbody tr').forEach(function(row) {
        row.querySelector('td:nth-child(3) input').value = formatted;
    });
    updateSummary();
}

// Set mode
function setMode(mode) {
    currentMode = mode;
    if (mode === 'same') {
        stSameModeFields.classList.remove('d-none');
        stManualModeFields.classList.add('d-none');
    } else {
        stSameModeFields.classList.add('d-none');
        stManualModeFields.classList.remove('d-none');
    }
    const yyyyMm = stMonth.value;
    renderTable(tableRows.filter(() => false), yyyyMm);
    loadMonth();
}

// Load month
async function loadMonth() {
    const yyyyMm = stMonth.value;
    const branchId = stBranch.value;
    const applyBtn = document.getElementById('stApplySameBtn');
    const saveBtn = document.getElementById('stSaveManualBtn');
    const clearBtn = document.getElementById('stClearMonthBtn');

    if (!branchId) {
        stTable.querySelector('tbody').innerHTML = '<tr><td colspan="4" class="text-center text-muted">Please select a branch to manage daily sales targets.</td></tr>';
        applyBtn.disabled = true;
        saveBtn.disabled = true;
        clearBtn.disabled = true;
        stSummary.textContent = '—';
        return;
    }

    applyBtn.disabled = false;
    saveBtn.disabled = currentMode !== 'manual';
    clearBtn.disabled = false;

    stTable.querySelector('tbody').innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';

    try {
        let url = `${BASE_URL}/api/sales-targets/targets.php?month=${yyyyMm}`;
        if (branchId) url += `&branch_id=${branchId}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            renderTable(result.targets || [], yyyyMm);
        } else {
            stTable.querySelector('tbody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading targets:', error);
        stTable.querySelector('tbody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load.</td></tr>';
    }
}

// Apply same to month
async function applySameToMonth() {
    const yyyyMm = stMonth.value;
    const branchId = stBranch.value;
    const amount = parseFloat(stripCommas(stSameAmount.value || 0));
    const notes = (stSameNotes.value || '').trim();

    if (!yyyyMm) {
        showToast('error', 'Please select a month first.');
        return;
    }

    if (isNaN(amount) || amount < 0) {
        showToast('error', 'Please enter a valid target sales amount.');
        return;
    }

    if (!branchId) {
        showToast('error', 'Please select a specific branch before applying updates.');
        return;
    }

    // Show confirmation modal
    const modalHtml = `
        <div class="modal fade" id="confirmApplyModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Apply to Whole Month</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>This will create/update targets for <strong>${formatMonthReadable(yyyyMm)}</strong> with amount <strong>₱ ${money(amount)}</strong>.</p>
                        ${notes ? `<p class="text-muted"><em>Note: ${notes}</em></p>` : ''}
                        <p class="mb-0">Do you want to continue?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmApplyBtn">Yes, Apply</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    const modal = new bootstrap.Modal(document.getElementById('confirmApplyModal'));
    modal.show();

    document.getElementById('confirmApplyBtn').addEventListener('click', async function() {
        modal.hide();
        
        // Get all days in the month
        const [year, monthNum] = yyyyMm.split('-');
        const daysInMonth = new Date(year, monthNum, 0).getDate();
        
        const targets = [];
        for (let day = 1; day <= daysInMonth; day++) {
            const date = `${year}-${monthNum}-${String(day).padStart(2, '0')}`;
            targets.push({
                branch_id: branchId || null,
                target_date: date,
                target_amount: amount,
                notes: notes
            });
        }

        try {
            const response = await fetch(`${BASE_URL}/api/sales-targets/batch.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ targets })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('success', 'Month targets saved successfully.');
                loadMonth();
            } else {
                showToast('error', 'Failed to save: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving targets:', error);
            showToast('error', 'Failed to save.');
        }

        modalContainer.remove();
    });

    document.getElementById('confirmApplyModal').addEventListener('hidden.bs.modal', function() {
        modalContainer.remove();
    });
}

// Save manual changes
async function saveManualChanges() {
    const yyyyMm = stMonth.value;
    const branchId = stBranch.value;
    
    if (!yyyyMm) {
        showToast('error', 'Please select a month first.');
        return;
    }

    if (!branchId) {
        showToast('error', 'Please select a specific branch before saving manual changes.');
        return;
    }

    const items = [];
    stTable.querySelectorAll('tbody tr').forEach(function(row) {
        const dt = row.getAttribute('data-date');
        const amt = stripCommas(row.querySelector('td:nth-child(3) input').value);
        const notes = row.querySelector('td:nth-child(4) input').value;
        items.push({
            target_date: dt,
            target_amount: amt,
            notes: notes
        });
    });

    // Show confirmation modal
    const modalHtml = `
        <div class="modal fade" id="confirmSaveModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Manual Changes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>This will update all days for <strong>${formatMonthReadable(yyyyMm)}</strong> based on your table values.</p>
                        <p class="mb-0">Do you want to continue?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="confirmSaveBtn">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    const modal = new bootstrap.Modal(document.getElementById('confirmSaveModal'));
    modal.show();

    document.getElementById('confirmSaveBtn').addEventListener('click', async function() {
        modal.hide();

        try {
            const response = await fetch(`${BASE_URL}/api/sales-targets/batch.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ targets: items.map(item => ({
                    branch_id: branchId || null,
                    target_date: item.target_date,
                    target_amount: parseFloat(item.target_amount) || 0,
                    notes: item.notes
                })), branch_id: branchId || null })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('success', 'Manual targets saved successfully.');
                loadMonth();
            } else {
                showToast('error', 'Failed to save: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving targets:', error);
            showToast('error', 'Failed to save.');
        }

        modalContainer.remove();
    });

    document.getElementById('confirmSaveModal').addEventListener('hidden.bs.modal', function() {
        modalContainer.remove();
    });
}

// Clear month
async function clearMonth() {
    const yyyyMm = stMonth.value;
    const branchId = stBranch.value;
    
    if (!yyyyMm) {
        showToast('error', 'Please select a month first.');
        return;
    }

    if (!branchId) {
        showToast('error', 'Please select a specific branch before clearing month targets.');
        return;
    }

    // Show confirmation modal
    const modalHtml = `
        <div class="modal fade" id="confirmClearModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Clear Month Targets</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">This will <strong>delete all daily targets</strong> for <strong>${formatMonthReadable(yyyyMm)}</strong>.</p>
                        <p class="text-danger mb-0">This action cannot be undone.</p>
                        <p class="mt-2 mb-0">Are you sure you want to continue?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmClearBtn">Yes, Clear All</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    const modal = new bootstrap.Modal(document.getElementById('confirmClearModal'));
    modal.show();

    document.getElementById('confirmClearBtn').addEventListener('click', async function() {
        modal.hide();

        try {
            // Get all targets for the month and delete them
            let url = `${BASE_URL}/api/sales-targets/targets.php?month=${yyyyMm}&branch_id=${branchId}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success && result.targets && result.targets.length > 0) {
                // Delete each target
                for (const target of result.targets) {
                    await fetch(`${BASE_URL}/api/sales-targets/delete.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        },
                        body: JSON.stringify({ id: target.id })
                    });
                }
                showToast('success', 'Month targets cleared successfully.');
                loadMonth();
            } else {
                showToast('success', 'No targets to clear.');
                loadMonth();
            }
        } catch (error) {
            console.error('Error clearing targets:', error);
            showToast('error', 'Failed to clear.');
        }

        modalContainer.remove();
    });

    document.getElementById('confirmClearModal').addEventListener('hidden.bs.modal', function() {
        modalContainer.remove();
    });
}
