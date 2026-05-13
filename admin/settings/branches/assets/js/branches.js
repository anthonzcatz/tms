// Branches Module
document.addEventListener('DOMContentLoaded', function() {
    console.log('Branches module initialized');
    loadRegions();
    initializeSearchDropdowns();
});

// Initialize Bootstrap modals
let addBranchModal, editBranchModal;

document.addEventListener('DOMContentLoaded', function() {
    addBranchModal = new bootstrap.Modal(document.getElementById('addBranchModal'));
    editBranchModal = new bootstrap.Modal(document.getElementById('editBranchModal'));
});

// Initialize search dropdowns
function initializeSearchDropdowns() {
    const searchConfigs = [
        { selectId: 'addRegionCode', datalistId: 'addRegionDatalist', api: 'regions', searchParam: '' },
        { selectId: 'editRegionCode', datalistId: 'editRegionDatalist', api: 'regions', searchParam: '' },
        { selectId: 'addProvinceCode', datalistId: 'addProvinceDatalist', api: 'provinces', searchParam: 'region_code' },
        { selectId: 'editProvinceCode', datalistId: 'editProvinceDatalist', api: 'provinces', searchParam: 'region_code' },
        { selectId: 'addCityCode', datalistId: 'addCityDatalist', api: 'cities', searchParam: 'province_code' },
        { selectId: 'editCityCode', datalistId: 'editCityDatalist', api: 'cities', searchParam: 'province_code' },
        { selectId: 'addBarangayCode', datalistId: 'addBarangayDatalist', api: 'barangays', searchParam: 'city_code' },
        { selectId: 'editBarangayCode', datalistId: 'editBarangayDatalist', api: 'barangays', searchParam: 'city_code' }
    ];

    searchConfigs.forEach(config => {
        const select = document.getElementById(config.selectId);
        if (select) {
            select.setAttribute('list', config.datalist);
            
            // Create datalist if it doesn't exist
            let datalist = document.getElementById(config.datalistId);
            if (!datalist) {
                datalist = document.createElement('datalist');
                datalist.id = config.datalistId;
                select.parentNode.appendChild(datalist);
            }
            
            // Add search functionality
            select.addEventListener('input', function() {
                handleSearchDropdown(this, datalist, config.api, config.searchParam);
            });
        }
    });
}

// Handle search dropdown
async function handleSearchDropdown(select, datalist, api, paramKey) {
    const searchValue = select.value.toLowerCase();
    
    if (searchValue.length < 2) {
        datalist.innerHTML = '';
        return;
    }
    
    let url = `${window.BASE_URL}/api/psgc?action=${api}&search=${searchValue}`;
    
    if (paramKey) {
        const paramValue = select.getAttribute('data-parent-value');
        if (paramValue) {
            url += `&${paramKey}=${paramValue}`;
        }
    }
    
    try {
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            const dataKey = api === 'cities' ? 'cities' : api === 'barangays' ? 'barangays' : api;
            const items = result.data[dataKey];
            
            datalist.innerHTML = '';
            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.region_code || item.province_code || item.city_municipality_code || item.barangay_code;
                option.textContent = item.region_name || item.province_name || item.city_municipality_name || item.barangay_name;
                datalist.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error searching:', error);
    }
}

// Load regions for dropdown
async function loadRegions() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/psgc?action=regions`);
        const result = await response.json();
        
        if (result.success) {
            const addSelect = document.getElementById('addRegionCode');
            const editSelect = document.getElementById('editRegionCode');
            
            addSelect.innerHTML = '<option value="">Select Region</option>';
            editSelect.innerHTML = '<option value="">Select Region</option>';
            
            result.data.regions.forEach(region => {
                addSelect.innerHTML += `<option value="${region.region_code}">${region.region_name}</option>`;
                editSelect.innerHTML += `<option value="${region.region_code}">${region.region_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading regions:', error);
    }
}

// Load provinces when region changes
document.addEventListener('DOMContentLoaded', function() {
    const addRegionSelect = document.getElementById('addRegionCode');
    const editRegionSelect = document.getElementById('editRegionCode');
    
    if (addRegionSelect) {
        addRegionSelect.addEventListener('change', function() {
            loadProvinces(this.value, 'addProvinceCode', 'addCityCode', 'addBarangayCode');
        });
    }
    
    if (editRegionSelect) {
        editRegionSelect.addEventListener('change', function() {
            loadProvinces(this.value, 'editProvinceCode', 'editCityCode', 'editBarangayCode');
        });
    }
});

// Load provinces
async function loadProvinces(regionCode, provinceSelectId, citySelectId, barangaySelectId) {
    const provinceSelect = document.getElementById(provinceSelectId);
    const citySelect = document.getElementById(citySelectId);
    const barangaySelect = document.getElementById(barangaySelectId);
    
    // Reset dependent selects
    provinceSelect.innerHTML = '<option value="">Select Province</option>';
    provinceSelect.disabled = !regionCode;
    provinceSelect.setAttribute('data-parent-value', regionCode);
    citySelect.innerHTML = '<option value="">Select City</option>';
    citySelect.disabled = true;
    citySelect.removeAttribute('data-parent-value');
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    barangaySelect.removeAttribute('data-parent-value');
    
    if (!regionCode) return;
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/psgc?action=provinces&region_code=${regionCode}`);
        const result = await response.json();
        
        if (result.success) {
            result.data.provinces.forEach(province => {
                provinceSelect.innerHTML += `<option value="${province.province_code}">${province.province_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading provinces:', error);
    }
}

// Load cities when province changes
document.addEventListener('DOMContentLoaded', function() {
    const addProvinceSelect = document.getElementById('addProvinceCode');
    const editProvinceSelect = document.getElementById('editProvinceCode');
    
    if (addProvinceSelect) {
        addProvinceSelect.addEventListener('change', function() {
            loadCities(this.value, 'addCityCode', 'addBarangayCode');
        });
    }
    
    if (editProvinceSelect) {
        editProvinceSelect.addEventListener('change', function() {
            loadCities(this.value, 'editCityCode', 'editBarangayCode');
        });
    }
});

// Load cities
async function loadCities(provinceCode, citySelectId, barangaySelectId) {
    const citySelect = document.getElementById(citySelectId);
    const barangaySelect = document.getElementById(barangaySelectId);
    
    // Reset dependent selects
    citySelect.innerHTML = '<option value="">Select City</option>';
    citySelect.disabled = !provinceCode;
    citySelect.setAttribute('data-parent-value', provinceCode);
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    barangaySelect.removeAttribute('data-parent-value');
    
    if (!provinceCode) return;
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/psgc?action=cities&province_code=${provinceCode}`);
        const result = await response.json();
        
        if (result.success) {
            result.data.cities.forEach(city => {
                citySelect.innerHTML += `<option value="${city.city_municipality_code}">${city.city_municipality_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

// Load barangays when city changes
document.addEventListener('DOMContentLoaded', function() {
    const addCitySelect = document.getElementById('addCityCode');
    const editCitySelect = document.getElementById('editCityCode');
    
    if (addCitySelect) {
        addCitySelect.addEventListener('change', function() {
            loadBarangays(this.value, 'addBarangayCode');
        });
    }
    
    if (editCitySelect) {
        editCitySelect.addEventListener('change', function() {
            loadBarangays(this.value, 'editBarangayCode');
        });
    }
});

// Load barangays
async function loadBarangays(cityCode, barangaySelectId) {
    const barangaySelect = document.getElementById(barangaySelectId);
    
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = !cityCode;
    barangaySelect.setAttribute('data-parent-value', cityCode);
    
    if (!cityCode) return;
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/psgc?action=barangays&city_code=${cityCode}`);
        const result = await response.json();
        
        if (result.success) {
            result.data.barangays.forEach(barangay => {
                barangaySelect.innerHTML += `<option value="${barangay.barangay_code}">${barangay.barangay_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading barangays:', error);
    }
}

// Open add branch modal
function openAddBranchModal() {
    document.getElementById('addBranchForm').reset();
    document.getElementById('addStatus').checked = true;
    updateAddStatusLabel(true);
    // Reset dependent selects
    document.getElementById('addProvinceCode').disabled = true;
    document.getElementById('addCityCode').disabled = true;
    document.getElementById('addBarangayCode').disabled = true;
    addBranchModal.show();
}

// Update add status label
function updateAddStatusLabel(isActive) {
    const label = document.getElementById('addStatusLabel');
    if (label) {
        label.innerHTML = isActive 
            ? '<span class="text-success fw-bold">Active</span>' 
            : '<span class="text-muted">Inactive</span>';
    }
}

// Handle add status switch change
document.addEventListener('DOMContentLoaded', function() {
    const addStatusSwitch = document.getElementById('addStatus');
    if (addStatusSwitch) {
        addStatusSwitch.addEventListener('change', function() {
            updateAddStatusLabel(this.checked);
        });
    }
    
    // Handle branch table switches for real-time toggle
    const branchSwitches = document.querySelectorAll('.branch-status-switch');
    branchSwitches.forEach(switchEl => {
        switchEl.addEventListener('change', async function() {
            const branchId = this.getAttribute('data-branch-id');
            const newStatus = this.checked ? 'active' : 'inactive';
            await toggleBranchStatus(branchId, newStatus, this);
        });
    });
});

// Save branch
async function saveBranch() {
    const branchCode = document.getElementById('addBranchCode').value.trim();
    const branchName = document.getElementById('addBranchName').value.trim();
    const regionCode = document.getElementById('addRegionCode').value;
    const provinceCode = document.getElementById('addProvinceCode').value;
    const cityCode = document.getElementById('addCityCode').value;
    const barangayCode = document.getElementById('addBarangayCode').value;
    const streetAddress = document.getElementById('addStreetAddress').value;
    const landmark = document.getElementById('addLandmark').value;
    const zipCode = document.getElementById('addZipCode').value;
    const contactNumber = document.getElementById('addContactNumber').value;
    const email = document.getElementById('addEmail').value;
    const status = document.getElementById('addStatus').checked ? 'active' : 'inactive';
    
    if (!branchCode || !branchName) {
        showToast('warning', 'Warning', 'Please fill in required fields');
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                branch_code: branchCode,
                branch_name: branchName,
                region_code: regionCode,
                province_code: provinceCode,
                city_municipality_code: cityCode,
                barangay_code: barangayCode,
                street_address: streetAddress,
                landmark: landmark,
                zip_code: zipCode,
                contact_number: contactNumber,
                email: email,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Branch created successfully');
            addBranchModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to create branch');
        }
    } catch (error) {
        console.error('Error saving branch:', error);
        showToast('error', 'Error', 'Failed to create branch: ' + error.message);
    }
}

// Edit branch
async function editBranch(branchId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/business-branches?id=${branchId}`);
        const result = await response.json();
        
        if (result.success) {
            const branch = result.data;
            document.getElementById('editBranchId').value = branch.branch_id;
            document.getElementById('editBranchCode').value = branch.branch_code;
            document.getElementById('editBranchName').value = branch.branch_name;
            document.getElementById('editRegionCode').value = branch.region_code;
            
            // Load dependent selects
            if (branch.region_code) {
                await loadProvinces(branch.region_code, 'editProvinceCode', 'editCityCode', 'editBarangayCode');
                document.getElementById('editProvinceCode').value = branch.province_code;
                
                if (branch.province_code) {
                    await loadCities(branch.province_code, 'editCityCode', 'editBarangayCode');
                    document.getElementById('editCityCode').value = branch.city_municipality_code;
                    
                    if (branch.city_municipality_code) {
                        await loadBarangays(branch.city_municipality_code, 'editBarangayCode');
                        document.getElementById('editBarangayCode').value = branch.barangay_code;
                    }
                }
            }
            
            document.getElementById('editStreetAddress').value = branch.street_address || '';
            document.getElementById('editLandmark').value = branch.landmark || '';
            document.getElementById('editZipCode').value = branch.zip_code || '';
            document.getElementById('editContactNumber').value = branch.contact_number || '';
            document.getElementById('editEmail').value = branch.email || '';
            document.getElementById('editStatus').checked = branch.status === 'active';
            updateEditStatusLabel(branch.status === 'active');
            editBranchModal.show();
        } else {
            showToast('error', 'Error', result.message || 'Failed to load branch');
        }
    } catch (error) {
        console.error('Error loading branch:', error);
        showToast('error', 'Error', 'Failed to load branch: ' + error.message);
    }
}

// Update edit status label
function updateEditStatusLabel(isActive) {
    const label = document.getElementById('editStatusLabel');
    if (label) {
        label.innerHTML = isActive 
            ? '<span class="text-success fw-bold">Active</span>' 
            : '<span class="text-muted">Inactive</span>';
    }
}

// Handle edit status switch change
document.addEventListener('DOMContentLoaded', function() {
    const editStatusSwitch = document.getElementById('editStatus');
    if (editStatusSwitch) {
        editStatusSwitch.addEventListener('change', function() {
            updateEditStatusLabel(this.checked);
        });
    }
});

// Update branch
async function updateBranch() {
    const branchId = document.getElementById('editBranchId').value;
    const branchCode = document.getElementById('editBranchCode').value.trim();
    const branchName = document.getElementById('editBranchName').value.trim();
    const regionCode = document.getElementById('editRegionCode').value;
    const provinceCode = document.getElementById('editProvinceCode').value;
    const cityCode = document.getElementById('editCityCode').value;
    const barangayCode = document.getElementById('editBarangayCode').value;
    const streetAddress = document.getElementById('editStreetAddress').value;
    const landmark = document.getElementById('editLandmark').value;
    const zipCode = document.getElementById('editZipCode').value;
    const contactNumber = document.getElementById('editContactNumber').value;
    const email = document.getElementById('editEmail').value;
    const statusCheckbox = document.getElementById('editStatus');
    const status = statusCheckbox.checked ? 'active' : 'inactive';
    
    if (!branchCode || !branchName) {
        showToast('warning', 'Warning', 'Please fill in required fields');
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                branch_id: branchId,
                branch_code: branchCode,
                branch_name: branchName,
                region_code: regionCode,
                province_code: provinceCode,
                city_municipality_code: cityCode,
                barangay_code: barangayCode,
                street_address: streetAddress,
                landmark: landmark,
                zip_code: zipCode,
                contact_number: contactNumber,
                email: email,
                status: status
            })
        });
        
        const result = await response.json();
        
        console.log('Update branch response:', result);
        
        if (result.success) {
            showToast('success', 'Success', 'Branch updated successfully');
            editBranchModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || result.error || 'Failed to update branch');
        }
    } catch (error) {
        console.error('Error updating branch:', error);
        showToast('error', 'Error', 'Failed to update branch: ' + error.message);
    }
}

// Toggle branch status in real-time
async function toggleBranchStatus(branchId, newStatus, switchElement) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                branch_id: branchId,
                status: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', `Branch ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
            
            // Update the label
            const label = switchElement.nextElementSibling;
            if (label) {
                label.textContent = newStatus === 'active' ? 'Active' : 'Inactive';
            }
            
            // Update stats
            updateStats();
        } else {
            showToast('error', 'Error', result.message || 'Failed to update branch status');
            // Revert switch on error
            switchElement.checked = !switchElement.checked;
        }
    } catch (error) {
        console.error('Error toggling branch status:', error);
        showToast('error', 'Error', 'Failed to update branch status: ' + error.message);
        // Revert switch on error
        switchElement.checked = !switchElement.checked;
    }
}

// Delete branch
async function deleteBranch(branchId) {
    if (!confirm('Are you sure you want to delete this branch? This action cannot be undone.')) {
        return;
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches?id=${branchId}`, {
            method: 'DELETE',
            headers: headers
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Branch deleted successfully');
            location.reload();
        } else {
            showToast('error', 'Error', result.message || 'Failed to delete branch');
        }
    } catch (error) {
        console.error('Error deleting branch:', error);
        showToast('error', 'Error', 'Failed to delete branch: ' + error.message);
    }
}

// Filter branches
function filterBranches(status) {
    const rows = document.querySelectorAll('#branchesTable tbody tr[data-status]');
    rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Update stats
async function updateStats() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/business-branches?action=stats`);
        const result = await response.json();
        
        if (result.success) {
            // Update total branches
            const totalEl = document.querySelectorAll('.card-body .fs-5')[0];
            if (totalEl) {
                totalEl.textContent = result.data.total_branches;
            }
            
            // Update active branches
            const activeEl = document.querySelectorAll('.card-body .fs-5')[1];
            if (activeEl) {
                activeEl.textContent = result.data.active_branches;
            }
            
            // Update inactive branches
            const inactiveEl = document.querySelectorAll('.card-body .fs-5')[2];
            if (inactiveEl) {
                inactiveEl.textContent = result.data.inactive_branches;
            }
        }
    } catch (error) {
        console.error('Error updating stats:', error);
    }
}

// Toast notification
function showToast(type, title, message) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(toast => toast.remove());
    
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
