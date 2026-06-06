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
            select.setAttribute('list', config.datalistId);
            
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

            // Cache selected text on change so we can reliably read it later
            select.addEventListener('change', function() {
                const text = this.options[this.selectedIndex]?.text?.trim() || '';
                this.setAttribute('data-selected-text', text);
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
    provinceSelect.setAttribute('data-selected-text', '');
    citySelect.innerHTML = '<option value="">Select City</option>';
    citySelect.disabled = true;
    citySelect.removeAttribute('data-parent-value');
    citySelect.setAttribute('data-selected-text', '');
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    barangaySelect.removeAttribute('data-parent-value');
    barangaySelect.setAttribute('data-selected-text', '');
    
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
    citySelect.setAttribute('data-selected-text', '');
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    barangaySelect.removeAttribute('data-parent-value');
    barangaySelect.setAttribute('data-selected-text', '');
    
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
    barangaySelect.setAttribute('data-selected-text', '');
    
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
    // Clear cached selected texts
    ['addRegionCode', 'addProvinceCode', 'addCityCode', 'addBarangayCode'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.setAttribute('data-selected-text', '');
    });
    // Reset wizard to step 1
    goToAddStep(1);
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

// Helper to get selected option text from a select element
function getSelectedText(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return '';

    const val = select.value;
    if (!val) return '';

    // Try cached text from change listener first
    const cached = select.getAttribute('data-selected-text');
    if (cached && !cached.startsWith('Select ')) {
        return cached;
    }

    // Fallback: find option by value
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === val) {
            const text = select.options[i].text.trim();
            return text.startsWith('Select ') ? '' : text;
        }
    }
    return '';
}

// Save branch
async function saveBranch() {
    const branchCode = document.getElementById('addBranchCode').value.trim();
    const branchName = document.getElementById('addBranchName').value.trim();
    const regionCode = document.getElementById('addRegionCode').value;
    const regionName = getSelectedText('addRegionCode');
    const provinceCode = document.getElementById('addProvinceCode').value;
    const provinceName = getSelectedText('addProvinceCode');
    const cityCode = document.getElementById('addCityCode').value;
    const cityName = getSelectedText('addCityCode');
    const barangayCode = document.getElementById('addBarangayCode').value;
    const barangayName = getSelectedText('addBarangayCode');
    const streetAddress = document.getElementById('addStreetAddress').value;
    const landmark = document.getElementById('addLandmark').value;
    const zipCode = document.getElementById('addZipCode').value;
    const contactNumber = document.getElementById('addContactNumber').value;
    const email = document.getElementById('addEmail').value;
    const status = document.getElementById('addStatus').checked ? 'active' : 'inactive';
    
    // Operating hours - with fallback for missing elements
    const getElementValue = (id, defaultValue) => {
        const el = document.getElementById(id);
        return el ? el.value : defaultValue;
    };
    
    const getElementChecked = (id, defaultValue) => {
        const el = document.getElementById(id);
        return el ? (el.checked ? 1 : 0) : defaultValue;
    };
    
    const mondayOpen = getElementValue('addMondayOpen', '08:00');
    const mondayClose = getElementValue('addMondayClose', '18:00');
    const mondayClosed = getElementChecked('addMondayClosed', 0);
    const mondayBreakStart = getElementValue('addMondayBreakStart', '');
    const mondayBreakEnd = getElementValue('addMondayBreakEnd', '');
    const tuesdayOpen = getElementValue('addTuesdayOpen', '08:00');
    const tuesdayClose = getElementValue('addTuesdayClose', '18:00');
    const tuesdayClosed = getElementChecked('addTuesdayClosed', 0);
    const tuesdayBreakStart = getElementValue('addTuesdayBreakStart', '');
    const tuesdayBreakEnd = getElementValue('addTuesdayBreakEnd', '');
    const wednesdayOpen = getElementValue('addWednesdayOpen', '08:00');
    const wednesdayClose = getElementValue('addWednesdayClose', '18:00');
    const wednesdayClosed = getElementChecked('addWednesdayClosed', 0);
    const wednesdayBreakStart = getElementValue('addWednesdayBreakStart', '');
    const wednesdayBreakEnd = getElementValue('addWednesdayBreakEnd', '');
    const thursdayOpen = getElementValue('addThursdayOpen', '08:00');
    const thursdayClose = getElementValue('addThursdayClose', '18:00');
    const thursdayClosed = getElementChecked('addThursdayClosed', 0);
    const thursdayBreakStart = getElementValue('addThursdayBreakStart', '');
    const thursdayBreakEnd = getElementValue('addThursdayBreakEnd', '');
    const fridayOpen = getElementValue('addFridayOpen', '08:00');
    const fridayClose = getElementValue('addFridayClose', '18:00');
    const fridayClosed = getElementChecked('addFridayClosed', 0);
    const fridayBreakStart = getElementValue('addFridayBreakStart', '');
    const fridayBreakEnd = getElementValue('addFridayBreakEnd', '');
    const saturdayOpen = getElementValue('addSaturdayOpen', '08:00');
    const saturdayClose = getElementValue('addSaturdayClose', '18:00');
    const saturdayClosed = getElementChecked('addSaturdayClosed', 0);
    const saturdayBreakStart = getElementValue('addSaturdayBreakStart', '');
    const saturdayBreakEnd = getElementValue('addSaturdayBreakEnd', '');
    const sundayOpen = getElementValue('addSundayOpen', '08:00');
    const sundayClose = getElementValue('addSundayClose', '18:00');
    const sundayClosed = getElementChecked('addSundayClosed', 0);
    const sundayBreakStart = getElementValue('addSundayBreakStart', '');
    const sundayBreakEnd = getElementValue('addSundayBreakEnd', '');
    const is24Hours = getElementChecked('addIs24Hours', 0);
    
    // Additional settings - with fallback
    const maxCapacity = getElementValue('addMaxCapacity', 100);
    const managerName = getElementValue('addManagerName', '');
    const managerContact = getElementValue('addManagerContact', '');
    const notes = getElementValue('addNotes', '');
    
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
        
        const requestBody = {
            branch_code: branchCode,
            branch_name: branchName,
            region_code: regionCode,
            region_name: regionName,
            province_code: provinceCode,
            province_name: provinceName,
            city_municipality_code: cityCode,
            city_municipality_name: cityName,
            barangay_code: barangayCode,
            barangay_name: barangayName,
            street_address: streetAddress,
            landmark: landmark,
            zip_code: zipCode,
            contact_number: contactNumber,
            email: email,
            status: status
        };

        console.log('saveBranch location values:', {
            regionCode, regionName, provinceCode, provinceName,
            cityCode, cityName, barangayCode, barangayName
        });

        // Only add operating hours if the elements exist
        if (document.getElementById('addMondayOpen')) {
            requestBody.monday_open = mondayOpen + ':00';
            requestBody.monday_close = mondayClose + ':00';
            requestBody.monday_closed = mondayClosed;
            requestBody.monday_break_start = mondayBreakStart ? mondayBreakStart + ':00' : null;
            requestBody.monday_break_end = mondayBreakEnd ? mondayBreakEnd + ':00' : null;
            requestBody.tuesday_open = tuesdayOpen + ':00';
            requestBody.tuesday_close = tuesdayClose + ':00';
            requestBody.tuesday_closed = tuesdayClosed;
            requestBody.tuesday_break_start = tuesdayBreakStart ? tuesdayBreakStart + ':00' : null;
            requestBody.tuesday_break_end = tuesdayBreakEnd ? tuesdayBreakEnd + ':00' : null;
            requestBody.wednesday_open = wednesdayOpen + ':00';
            requestBody.wednesday_close = wednesdayClose + ':00';
            requestBody.wednesday_closed = wednesdayClosed;
            requestBody.wednesday_break_start = wednesdayBreakStart ? wednesdayBreakStart + ':00' : null;
            requestBody.wednesday_break_end = wednesdayBreakEnd ? wednesdayBreakEnd + ':00' : null;
            requestBody.thursday_open = thursdayOpen + ':00';
            requestBody.thursday_close = thursdayClose + ':00';
            requestBody.thursday_closed = thursdayClosed;
            requestBody.thursday_break_start = thursdayBreakStart ? thursdayBreakStart + ':00' : null;
            requestBody.thursday_break_end = thursdayBreakEnd ? thursdayBreakEnd + ':00' : null;
            requestBody.friday_open = fridayOpen + ':00';
            requestBody.friday_close = fridayClose + ':00';
            requestBody.friday_closed = fridayClosed;
            requestBody.friday_break_start = fridayBreakStart ? fridayBreakStart + ':00' : null;
            requestBody.friday_break_end = fridayBreakEnd ? fridayBreakEnd + ':00' : null;
            requestBody.saturday_open = saturdayOpen + ':00';
            requestBody.saturday_close = saturdayClose + ':00';
            requestBody.saturday_closed = saturdayClosed;
            requestBody.saturday_break_start = saturdayBreakStart ? saturdayBreakStart + ':00' : null;
            requestBody.saturday_break_end = saturdayBreakEnd ? saturdayBreakEnd + ':00' : null;
            requestBody.sunday_open = sundayOpen + ':00';
            requestBody.sunday_close = sundayClose + ':00';
            requestBody.sunday_closed = sundayClosed;
            requestBody.sunday_break_start = sundayBreakStart ? sundayBreakStart + ':00' : null;
            requestBody.sunday_break_end = sundayBreakEnd ? sundayBreakEnd + ':00' : null;
            requestBody.is_24_hours = is24Hours;
            requestBody.max_capacity = maxCapacity;
            requestBody.manager_name = managerName;
            requestBody.manager_contact = managerContact;
            requestBody.notes = notes;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(requestBody)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'Branch created successfully');
            addBranchModal.hide();
            location.reload();
        } else {
            showToast('error', 'Error', result.message || result.error || 'Failed to create branch');
        }
    } catch (error) {
        console.error('Error saving branch:', error);
        showToast('error', 'Error', 'Failed to create branch: ' + error.message);
    }
}

// Edit branch
async function editBranch(branchId) {
    try {
        const encodedBranchId = IdEncoder.encode(branchId);
        const response = await fetch(`${window.BASE_URL}/api/business-branches?id=${encodedBranchId}`);
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
                        const editBarangaySelect = document.getElementById('editBarangayCode');
                        editBarangaySelect.value = branch.barangay_code || '';
                        if (!branch.barangay_code) {
                            editBarangaySelect.setAttribute('data-selected-text', '');
                        }
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
            
            // Operating hours - only populate if elements exist
            if (document.getElementById('editMondayOpen')) {
                document.getElementById('editMondayOpen').value = branch.monday_open ? branch.monday_open.substring(0, 5) : '08:00';
                document.getElementById('editMondayClose').value = branch.monday_close ? branch.monday_close.substring(0, 5) : '18:00';
                document.getElementById('editMondayClosed').checked = branch.monday_closed === 1;
                document.getElementById('editMondayBreakStart').value = branch.monday_break_start ? branch.monday_break_start.substring(0, 5) : '';
                document.getElementById('editMondayBreakEnd').value = branch.monday_break_end ? branch.monday_break_end.substring(0, 5) : '';
                document.getElementById('editTuesdayOpen').value = branch.tuesday_open ? branch.tuesday_open.substring(0, 5) : '08:00';
                document.getElementById('editTuesdayClose').value = branch.tuesday_close ? branch.tuesday_close.substring(0, 5) : '18:00';
                document.getElementById('editTuesdayClosed').checked = branch.tuesday_closed === 1;
                document.getElementById('editTuesdayBreakStart').value = branch.tuesday_break_start ? branch.tuesday_break_start.substring(0, 5) : '';
                document.getElementById('editTuesdayBreakEnd').value = branch.tuesday_break_end ? branch.tuesday_break_end.substring(0, 5) : '';
                document.getElementById('editWednesdayOpen').value = branch.wednesday_open ? branch.wednesday_open.substring(0, 5) : '08:00';
                document.getElementById('editWednesdayClose').value = branch.wednesday_close ? branch.wednesday_close.substring(0, 5) : '18:00';
                document.getElementById('editWednesdayClosed').checked = branch.wednesday_closed === 1;
                document.getElementById('editWednesdayBreakStart').value = branch.wednesday_break_start ? branch.wednesday_break_start.substring(0, 5) : '';
                document.getElementById('editWednesdayBreakEnd').value = branch.wednesday_break_end ? branch.wednesday_break_end.substring(0, 5) : '';
                document.getElementById('editThursdayOpen').value = branch.thursday_open ? branch.thursday_open.substring(0, 5) : '08:00';
                document.getElementById('editThursdayClose').value = branch.thursday_close ? branch.thursday_close.substring(0, 5) : '18:00';
                document.getElementById('editThursdayClosed').checked = branch.thursday_closed === 1;
                document.getElementById('editThursdayBreakStart').value = branch.thursday_break_start ? branch.thursday_break_start.substring(0, 5) : '';
                document.getElementById('editThursdayBreakEnd').value = branch.thursday_break_end ? branch.thursday_break_end.substring(0, 5) : '';
                document.getElementById('editFridayOpen').value = branch.friday_open ? branch.friday_open.substring(0, 5) : '08:00';
                document.getElementById('editFridayClose').value = branch.friday_close ? branch.friday_close.substring(0, 5) : '18:00';
                document.getElementById('editFridayClosed').checked = branch.friday_closed === 1;
                document.getElementById('editFridayBreakStart').value = branch.friday_break_start ? branch.friday_break_start.substring(0, 5) : '';
                document.getElementById('editFridayBreakEnd').value = branch.friday_break_end ? branch.friday_break_end.substring(0, 5) : '';
                document.getElementById('editSaturdayOpen').value = branch.saturday_open ? branch.saturday_open.substring(0, 5) : '08:00';
                document.getElementById('editSaturdayClose').value = branch.saturday_close ? branch.saturday_close.substring(0, 5) : '18:00';
                document.getElementById('editSaturdayClosed').checked = branch.saturday_closed === 1;
                document.getElementById('editSaturdayBreakStart').value = branch.saturday_break_start ? branch.saturday_break_start.substring(0, 5) : '';
                document.getElementById('editSaturdayBreakEnd').value = branch.saturday_break_end ? branch.saturday_break_end.substring(0, 5) : '';
                document.getElementById('editSundayOpen').value = branch.sunday_open ? branch.sunday_open.substring(0, 5) : '08:00';
                document.getElementById('editSundayClose').value = branch.sunday_close ? branch.sunday_close.substring(0, 5) : '18:00';
                document.getElementById('editSundayClosed').checked = branch.sunday_closed === 1;
                document.getElementById('editSundayBreakStart').value = branch.sunday_break_start ? branch.sunday_break_start.substring(0, 5) : '';
                document.getElementById('editSundayBreakEnd').value = branch.sunday_break_end ? branch.sunday_break_end.substring(0, 5) : '';
                document.getElementById('editIs24Hours').checked = branch.is_24_hours === 1;
                
                // Additional settings
                document.getElementById('editMaxCapacity').value = branch.max_capacity || 100;
                document.getElementById('editManagerName').value = branch.manager_name || '';
                document.getElementById('editManagerContact').value = branch.manager_contact || '';
                document.getElementById('editNotes').value = branch.notes || '';
                
                // Toggle operating hours visibility based on 24/7 setting
                toggleEditOperatingHours();
            }
            
            // Reset wizard to step 1
            goToEditStep(1);
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
    const regionName = getSelectedText('editRegionCode');
    const provinceCode = document.getElementById('editProvinceCode').value;
    const provinceName = getSelectedText('editProvinceCode');
    const cityCode = document.getElementById('editCityCode').value;
    const cityName = getSelectedText('editCityCode');
    const barangayCode = document.getElementById('editBarangayCode').value;
    const barangayName = getSelectedText('editBarangayCode');
    const streetAddress = document.getElementById('editStreetAddress').value;
    const landmark = document.getElementById('editLandmark').value;
    const zipCode = document.getElementById('editZipCode').value;
    const contactNumber = document.getElementById('editContactNumber').value;
    const email = document.getElementById('editEmail').value;
    const statusCheckbox = document.getElementById('editStatus');
    const status = statusCheckbox.checked ? 'active' : 'inactive';
    
    // Operating hours - with fallback for missing elements
    const getElementValue = (id, defaultValue) => {
        const el = document.getElementById(id);
        return el ? el.value : defaultValue;
    };
    
    const getElementChecked = (id, defaultValue) => {
        const el = document.getElementById(id);
        return el ? (el.checked ? 1 : 0) : defaultValue;
    };
    
    const mondayOpen = getElementValue('editMondayOpen', '08:00');
    const mondayClose = getElementValue('editMondayClose', '18:00');
    const mondayClosed = getElementChecked('editMondayClosed', 0);
    const mondayBreakStart = getElementValue('editMondayBreakStart', '');
    const mondayBreakEnd = getElementValue('editMondayBreakEnd', '');
    const tuesdayOpen = getElementValue('editTuesdayOpen', '08:00');
    const tuesdayClose = getElementValue('editTuesdayClose', '18:00');
    const tuesdayClosed = getElementChecked('editTuesdayClosed', 0);
    const tuesdayBreakStart = getElementValue('editTuesdayBreakStart', '');
    const tuesdayBreakEnd = getElementValue('editTuesdayBreakEnd', '');
    const wednesdayOpen = getElementValue('editWednesdayOpen', '08:00');
    const wednesdayClose = getElementValue('editWednesdayClose', '18:00');
    const wednesdayClosed = getElementChecked('editWednesdayClosed', 0);
    const wednesdayBreakStart = getElementValue('editWednesdayBreakStart', '');
    const wednesdayBreakEnd = getElementValue('editWednesdayBreakEnd', '');
    const thursdayOpen = getElementValue('editThursdayOpen', '08:00');
    const thursdayClose = getElementValue('editThursdayClose', '18:00');
    const thursdayClosed = getElementChecked('editThursdayClosed', 0);
    const thursdayBreakStart = getElementValue('editThursdayBreakStart', '');
    const thursdayBreakEnd = getElementValue('editThursdayBreakEnd', '');
    const fridayOpen = getElementValue('editFridayOpen', '08:00');
    const fridayClose = getElementValue('editFridayClose', '18:00');
    const fridayClosed = getElementChecked('editFridayClosed', 0);
    const fridayBreakStart = getElementValue('editFridayBreakStart', '');
    const fridayBreakEnd = getElementValue('editFridayBreakEnd', '');
    const saturdayOpen = getElementValue('editSaturdayOpen', '08:00');
    const saturdayClose = getElementValue('editSaturdayClose', '18:00');
    const saturdayClosed = getElementChecked('editSaturdayClosed', 0);
    const saturdayBreakStart = getElementValue('editSaturdayBreakStart', '');
    const saturdayBreakEnd = getElementValue('editSaturdayBreakEnd', '');
    const sundayOpen = getElementValue('editSundayOpen', '08:00');
    const sundayClose = getElementValue('editSundayClose', '18:00');
    const sundayClosed = getElementChecked('editSundayClosed', 0);
    const sundayBreakStart = getElementValue('editSundayBreakStart', '');
    const sundayBreakEnd = getElementValue('editSundayBreakEnd', '');
    const is24Hours = getElementChecked('editIs24Hours', 0);
    
    // Additional settings - with fallback
    const maxCapacity = getElementValue('editMaxCapacity', 100);
    const managerName = getElementValue('editManagerName', '');
    const managerContact = getElementValue('editManagerContact', '');
    const notes = getElementValue('editNotes', '');
    
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
        
        const requestBody = {
            branch_id: branchId,
            branch_code: branchCode,
            branch_name: branchName,
            region_code: regionCode,
            region_name: regionName,
            province_code: provinceCode,
            province_name: provinceName,
            city_municipality_code: cityCode,
            city_municipality_name: cityName,
            barangay_code: barangayCode,
            barangay_name: barangayName,
            street_address: streetAddress,
            landmark: landmark,
            zip_code: zipCode,
            contact_number: contactNumber,
            email: email,
            status: status
        };

        console.log('updateBranch location values:', {
            regionCode, regionName, provinceCode, provinceName,
            cityCode, cityName, barangayCode, barangayName
        });

        // Only add operating hours if the elements exist
        if (document.getElementById('editMondayOpen')) {
            requestBody.monday_open = mondayOpen + ':00';
            requestBody.monday_close = mondayClose + ':00';
            requestBody.monday_closed = mondayClosed;
            requestBody.monday_break_start = mondayBreakStart ? mondayBreakStart + ':00' : null;
            requestBody.monday_break_end = mondayBreakEnd ? mondayBreakEnd + ':00' : null;
            requestBody.tuesday_open = tuesdayOpen + ':00';
            requestBody.tuesday_close = tuesdayClose + ':00';
            requestBody.tuesday_closed = tuesdayClosed;
            requestBody.tuesday_break_start = tuesdayBreakStart ? tuesdayBreakStart + ':00' : null;
            requestBody.tuesday_break_end = tuesdayBreakEnd ? tuesdayBreakEnd + ':00' : null;
            requestBody.wednesday_open = wednesdayOpen + ':00';
            requestBody.wednesday_close = wednesdayClose + ':00';
            requestBody.wednesday_closed = wednesdayClosed;
            requestBody.wednesday_break_start = wednesdayBreakStart ? wednesdayBreakStart + ':00' : null;
            requestBody.wednesday_break_end = wednesdayBreakEnd ? wednesdayBreakEnd + ':00' : null;
            requestBody.thursday_open = thursdayOpen + ':00';
            requestBody.thursday_close = thursdayClose + ':00';
            requestBody.thursday_closed = thursdayClosed;
            requestBody.thursday_break_start = thursdayBreakStart ? thursdayBreakStart + ':00' : null;
            requestBody.thursday_break_end = thursdayBreakEnd ? thursdayBreakEnd + ':00' : null;
            requestBody.friday_open = fridayOpen + ':00';
            requestBody.friday_close = fridayClose + ':00';
            requestBody.friday_closed = fridayClosed;
            requestBody.friday_break_start = fridayBreakStart ? fridayBreakStart + ':00' : null;
            requestBody.friday_break_end = fridayBreakEnd ? fridayBreakEnd + ':00' : null;
            requestBody.saturday_open = saturdayOpen + ':00';
            requestBody.saturday_close = saturdayClose + ':00';
            requestBody.saturday_closed = saturdayClosed;
            requestBody.saturday_break_start = saturdayBreakStart ? saturdayBreakStart + ':00' : null;
            requestBody.saturday_break_end = saturdayBreakEnd ? saturdayBreakEnd + ':00' : null;
            requestBody.sunday_open = sundayOpen + ':00';
            requestBody.sunday_close = sundayClose + ':00';
            requestBody.sunday_closed = sundayClosed;
            requestBody.sunday_break_start = sundayBreakStart ? sundayBreakStart + ':00' : null;
            requestBody.sunday_break_end = sundayBreakEnd ? sundayBreakEnd + ':00' : null;
            requestBody.is_24_hours = is24Hours;
            requestBody.max_capacity = maxCapacity;
            requestBody.manager_name = managerName;
            requestBody.manager_contact = managerContact;
            requestBody.notes = notes;
        }
        
        const response = await fetch(`${window.BASE_URL}/api/business-branches`, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify(requestBody)
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

        const encodedBranchId = IdEncoder.encode(branchId);
        const response = await fetch(`${window.BASE_URL}/api/business-branches?id=${encodedBranchId}`, {
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

// Add Branch Wizard Functions
let addCurrentStep = 1;
const totalAddSteps = 3;

function goToAddStep(step) {
    // Update step indicators
    document.querySelectorAll('#addBranchModal .step-item').forEach(item => {
        const stepNum = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed');
        if (stepNum < step) {
            item.classList.add('completed');
        } else if (stepNum === step) {
            item.classList.add('active');
        }
    });
    
    // Update step content
    document.querySelectorAll('#addBranchModal .wizard-step').forEach(stepContent => {
        stepContent.classList.remove('active');
        if (parseInt(stepContent.dataset.step) === step) {
            stepContent.classList.add('active');
        }
    });
    
    // Update buttons
    const prevBtn = document.getElementById('addPrevStepBtn');
    const nextBtn = document.getElementById('addNextStepBtn');
    const saveBtn = document.getElementById('addSaveBranchBtn');
    
    if (step === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    } else if (step === totalAddSteps) {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    } else {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    }
    
    addCurrentStep = step;
}

function addNextStep() {
    if (addCurrentStep < totalAddSteps) {
        goToAddStep(addCurrentStep + 1);
    }
}

function addPrevStep() {
    if (addCurrentStep > 1) {
        goToAddStep(addCurrentStep - 1);
    }
}

function toggleAddOperatingHours() {
    const is24Hours = document.getElementById('addIs24Hours').checked;
    const container = document.getElementById('addOperatingHoursContainer');
    if (container) {
        container.style.display = is24Hours ? 'none' : 'block';
    }
}

function updateAddStatusLabel(checked) {
    const label = document.getElementById('addStatusLabel');
    if (label) {
        label.innerHTML = checked ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>';
    }
}

function copyAddMondayToAll() {
    const mondayOpen = document.getElementById('addMondayOpen').value;
    const mondayClose = document.getElementById('addMondayClose').value;
    const mondayClosed = document.getElementById('addMondayClosed').checked;
    const mondayBreakStart = document.getElementById('addMondayBreakStart').value;
    const mondayBreakEnd = document.getElementById('addMondayBreakEnd').value;
    
    const days = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    days.forEach(day => {
        const openEl = document.getElementById(`add${day}Open`);
        const closeEl = document.getElementById(`add${day}Close`);
        const closedEl = document.getElementById(`add${day}Closed`);
        const breakStartEl = document.getElementById(`add${day}BreakStart`);
        const breakEndEl = document.getElementById(`add${day}BreakEnd`);
        
        if (openEl) openEl.value = mondayOpen;
        if (closeEl) closeEl.value = mondayClose;
        if (closedEl) closedEl.checked = mondayClosed;
        if (breakStartEl) breakStartEl.value = mondayBreakStart;
        if (breakEndEl) breakEndEl.value = mondayBreakEnd;
    });
    
    showToast('success', 'Copied', 'Monday hours copied to all days');
}

// Edit Branch Wizard Functions
let editCurrentStep = 1;
const totalEditSteps = 3;

function goToEditStep(step) {
    // Update step indicators
    document.querySelectorAll('#editBranchModal .step-item').forEach(item => {
        const stepNum = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed');
        if (stepNum < step) {
            item.classList.add('completed');
        } else if (stepNum === step) {
            item.classList.add('active');
        }
    });
    
    // Update step content
    document.querySelectorAll('#editBranchModal .wizard-step').forEach(stepContent => {
        stepContent.classList.remove('active');
        if (parseInt(stepContent.dataset.step) === step) {
            stepContent.classList.add('active');
        }
    });
    
    // Update buttons
    const prevBtn = document.getElementById('editPrevStepBtn');
    const nextBtn = document.getElementById('editNextStepBtn');
    const saveBtn = document.getElementById('editSaveBranchBtn');
    
    if (step === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    } else if (step === totalEditSteps) {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    } else {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    }
    
    editCurrentStep = step;
}

function editNextStep() {
    if (editCurrentStep < totalEditSteps) {
        goToEditStep(editCurrentStep + 1);
    }
}

function editPrevStep() {
    if (editCurrentStep > 1) {
        goToEditStep(editCurrentStep - 1);
    }
}

function toggleEditOperatingHours() {
    const is24Hours = document.getElementById('editIs24Hours').checked;
    const container = document.getElementById('editOperatingHoursContainer');
    if (container) {
        container.style.display = is24Hours ? 'none' : 'block';
    }
}

function updateEditStatusLabel(checked) {
    const label = document.getElementById('editStatusLabel');
    if (label) {
        label.innerHTML = checked ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>';
    }
}

function copyEditMondayToAll() {
    const mondayOpen = document.getElementById('editMondayOpen').value;
    const mondayClose = document.getElementById('editMondayClose').value;
    const mondayClosed = document.getElementById('editMondayClosed').checked;
    const mondayBreakStart = document.getElementById('editMondayBreakStart').value;
    const mondayBreakEnd = document.getElementById('editMondayBreakEnd').value;
    
    const days = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    days.forEach(day => {
        const openEl = document.getElementById(`edit${day}Open`);
        const closeEl = document.getElementById(`edit${day}Close`);
        const closedEl = document.getElementById(`edit${day}Closed`);
        const breakStartEl = document.getElementById(`edit${day}BreakStart`);
        const breakEndEl = document.getElementById(`edit${day}BreakEnd`);
        
        if (openEl) openEl.value = mondayOpen;
        if (closeEl) closeEl.value = mondayClose;
        if (closedEl) closedEl.checked = mondayClosed;
        if (breakStartEl) breakStartEl.value = mondayBreakStart;
        if (breakEndEl) breakEndEl.value = mondayBreakEnd;
    });
    
    showToast('success', 'Copied', 'Monday hours copied to all days');
}

// Toggle operating hours visibility for edit modal
function toggleEditOperatingHours() {
    const is24Hours = document.getElementById('editIs24Hours').checked;
    const container = document.getElementById('editOperatingHoursContainer');
    if (container) {
        container.style.display = is24Hours ? 'none' : 'block';
    }
}

// Copy Monday hours to all days for edit modal
function copyEditMondayToAll() {
    const mondayOpen = document.getElementById('editMondayOpen').value;
    const mondayClose = document.getElementById('editMondayClose').value;
    const mondayClosed = document.getElementById('editMondayClosed').checked;
    const mondayBreakStart = document.getElementById('editMondayBreakStart').value;
    const mondayBreakEnd = document.getElementById('editMondayBreakEnd').value;
    
    const days = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    days.forEach(day => {
        const openEl = document.getElementById(`edit${day}Open`);
        const closeEl = document.getElementById(`edit${day}Close`);
        const closedEl = document.getElementById(`edit${day}Closed`);
        const breakStartEl = document.getElementById(`edit${day}BreakStart`);
        const breakEndEl = document.getElementById(`edit${day}BreakEnd`);
        
        if (openEl) openEl.value = mondayOpen;
        if (closeEl) closeEl.value = mondayClose;
        if (closedEl) closedEl.checked = mondayClosed;
        if (breakStartEl) breakStartEl.value = mondayBreakStart;
        if (breakEndEl) breakEndEl.value = mondayBreakEnd;
    });
    
    showToast('success', 'Copied', 'Monday hours copied to all days');
}
