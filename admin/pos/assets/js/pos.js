// =============================================
// Cashier POS Module
// =============================================

// Number formatter helper
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let cart = [];            // Array of cart items
let paymentLines = [];    // Array of payment method entries
let activeServiceType = null;
let activePaymentMethod = null;
let openSessionModal, closeSessionModal, selectCustomerModal, addPassengerModal, switchTypeModal;
let selectedCustomerId = null;
let transactionType = localStorage.getItem('posTransactionType') || 'ticket'; // 'ticket' or 'service'
let ticketInCart = null; // Store the ticket object if in cart
let currentPassengerStep = 1;
let totalPassengerSteps = 3;
let viewPassengerStep = 1;
let totalViewPassengerSteps = 3;
let pendingTransactionType = null; // Store pending transaction type for confirmation

document.addEventListener('DOMContentLoaded', function() {
    openSessionModal  = new bootstrap.Modal(document.getElementById('openSessionModal'));
    closeSessionModal = new bootstrap.Modal(document.getElementById('closeSessionModal'));
    selectCustomerModal = new bootstrap.Modal(document.getElementById('selectCustomerModal'));
    addPassengerModal = new bootstrap.Modal(document.getElementById('addPassengerModal'));
    viewPassengerModal = new bootstrap.Modal(document.getElementById('viewPassengerModal'));
    switchTypeModal = new bootstrap.Modal(document.getElementById('switchTypeModal'));
    
    // Don't render passengers on initial load - wait for search
    renderCustomers();
    
    // Load discount types and accommodation types
    loadDiscountTypes();
    loadAccommodationTypes();
    loadProviderServiceFees();
    
    // Initialize transaction type UI
    updateTransactionTypeUI();
    
    // Add input event listeners for real-time validation
    const fullname = document.getElementById('newPassengerFullname');
    const mobile = document.getElementById('newPassengerMobile');
    const gender = document.getElementById('newPassengerGender');
    const region = document.getElementById('newPassengerRegion');
    const province = document.getElementById('newPassengerProvince');
    const city = document.getElementById('newPassengerCity');
    const barangay = document.getElementById('newPassengerBarangay');

    if (fullname) {
        fullname.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (mobile) {
        mobile.addEventListener('input', function() {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Enforce max length of 11
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
            
            if (this.value.trim()) {
                // Check if it matches PH mobile format (09 + 9 digits)
                const isValidFormat = /^09[0-9]{9}$/.test(this.value);
                if (isValidFormat) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    // Only show invalid if we have enough characters to judge
                    if (this.value.length >= 2) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (gender) {
        gender.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (region) {
        region.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (province) {
        province.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (city) {
        city.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (barangay) {
        barangay.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // F2 - Focus on service type selection
        if (e.key === 'F2' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            e.preventDefault();
            if (window.POS_HAS_SESSION) {
                document.querySelector('.service-type-card')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                openSessionModal.show();
            }
        }

        // F4 - Focus on payment section
        if (e.key === 'F4' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            e.preventDefault();
            if (cart.length > 0) {
                proceedToPayment();
            } else {
                showToast('warning', 'Cart Empty', 'Add items first.');
            }
        }

        // F9 - Confirm order
        if (e.key === 'F9' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            e.preventDefault();
            const confirmBtn = document.getElementById('confirmOrderBtn');
            if (confirmBtn && confirmBtn.offsetParent !== null) {
                confirmOrder();
            }
        }

        // ESC - Cancel current entry
        if (e.key === 'Escape' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            const itemEntry = document.getElementById('itemEntryCard');
            const paymentEntry = document.getElementById('paymentEntryRow');
            if (itemEntry && itemEntry.style.display !== 'none') {
                cancelItemEntry();
            } else if (paymentEntry && paymentEntry.style.display !== 'none') {
                cancelPaymentEntry();
            }
        }

        // Enter - Add item or payment
        if (e.key === 'Enter' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            const itemEntry = document.getElementById('itemEntryCard');
            const paymentEntry = document.getElementById('paymentEntryRow');
            if (itemEntry && itemEntry.style.display !== 'none') {
                addItemToCart();
            } else if (paymentEntry && paymentEntry.style.display !== 'none') {
                addPaymentLine();
            }
        }
    });

    // Check session duration and warn if too long
    checkSessionDuration();
});

// =============================================
// SESSION DURATION CHECK
// =============================================

function checkSessionDuration() {
    if (!window.POS_SESSION_START) return;

    const sessionStart = new Date(window.POS_SESSION_START);
    const now = new Date();
    const hoursOpen = (now - sessionStart) / (1000 * 60 * 60);

    // Warn if session is open for 12+ hours
    if (hoursOpen >= 12) {
        const hours = Math.floor(hoursOpen);
        showToast('warning', 'Long Session Warning',
            `Your cashier session has been open for ${hours} hour(s). Consider closing it to reconcile cash.`);
    }
}

// =============================================
// CUSTOMER SELECTION (for CHARGE payments)
// =============================================

function renderCustomers(searchTerm = '') {
    const tbody = document.getElementById('customersTableBody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';
    
    // Build URL with search parameter
    let url = `${window.BASE_URL}/api/pos/passengers`;
    if (searchTerm) {
        url += `?search=${encodeURIComponent(searchTerm)}`;
    }
    
    // Fetch passengers via AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No customers found. Start typing to search.</td></tr>';
                return;
            }

            data.data.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'customer-row';
                tr.dataset.search = (p.fullname + ' ' + (p.mobile_number || '')).toLowerCase();
                tr.dataset.passengerId = p.passenger_id;
                tr.innerHTML = `
                    <td class="fw-semibold">${p.fullname}</td>
                    <td>${p.mobile_number || '—'}</td>
                    <td class="text-end">₱0.00</td>
                    <td class="text-center">
                        <input type="radio" name="selectedCustomer" value="${p.passenger_id}" onchange="selectCustomerRadio(this)">
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Error fetching passengers:', error);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load customers.</td></tr>';
        });
}

function searchCustomers(searchTerm) {
    // Debounce the search to avoid too many API calls
    clearTimeout(window.customerSearchTimeout);
    window.customerSearchTimeout = setTimeout(() => {
        renderCustomers(searchTerm);
    }, 300);
}

function renderTicketPassengers(searchTerm = '') {
    const searchInput = document.getElementById('ticketPassengerSearch');
    const hiddenInput = document.getElementById('ticketPassenger');
    const dropdown = document.getElementById('ticketPassengerDropdown');
    
    console.log('Fetching passengers with search:', searchTerm);
    
    // Build URL with search parameter
    let url = `${window.BASE_URL}/api/pos/passengers`;
    if (searchTerm) {
        url += `?search=${encodeURIComponent(searchTerm)}`;
    }
    
    // Hide dropdown initially
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    
    // Fetch passengers via AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Passenger search results:', data);
            
            if (!data.success || !data.data || data.data.length === 0) {
                // Hide dropdown if no results
                dropdown.style.display = 'none';
                return;
            }

            // Build dropdown items with more details
            data.data.forEach((p, index) => {
                const item = document.createElement('div');
                item.className = 'dropdown-item passenger-dropdown-item';
                item.dataset.passengerId = p.passenger_id;
                item.dataset.index = index;
                
                // Build address string from available fields (use names instead of codes)
                const addressParts = [];
                if (p.street_address) addressParts.push(p.street_address);
                if (p.barangay_name) addressParts.push(p.barangay_name);
                if (p.city_municipality_name) addressParts.push(p.city_municipality_name);
                if (p.province_name) addressParts.push(p.province_name);
                const address = addressParts.length > 0 ? addressParts.join(', ') : 'No address on file';
                
                // Gender badge class
                const genderBadgeClass = p.gender === 'male' ? 'bg-primary' : 
                                       p.gender === 'female' ? 'bg-danger' : 'bg-secondary';
                const genderBadge = p.gender ? `<span class="badge ${genderBadgeClass}">${p.gender.toUpperCase()}</span>` : '';
                
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold text-primary">${p.fullname}</div>
                            <div class="text-muted small">${p.mobile_number || 'No mobile number'}</div>
                            <div class="text-muted small">${address}</div>
                        </div>
                        <div class="d-flex gap-2">
                            ${genderBadge}
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPassenger('${p.passenger_id}', event)" title="View/Edit">
                                <span class="fas fa-edit"></span>
                            </button>
                        </div>
                    </div>
                `;
                
                item.onclick = (e) => {
                    if (!e.target.closest('button')) {
                        selectTicketPassenger(p);
                    }
                };
                dropdown.appendChild(item);
            });
            
            // Show dropdown if there are results
            dropdown.style.display = 'block';
            
            // Set first item as active for keyboard navigation
            if (dropdown.firstChild) {
                dropdown.firstChild.classList.add('active');
            }
        })
        .catch(error => {
            console.error('Error fetching passengers:', error);
            dropdown.style.display = 'none';
        });
}

function selectTicketPassenger(passenger) {
    const searchInput = document.getElementById('ticketPassengerSearch');
    const hiddenInput = document.getElementById('ticketPassenger');
    const dropdown = document.getElementById('ticketPassengerDropdown');
    
    // Build address string for display (use names instead of codes)
    const addressParts = [];
    if (passenger.street_address) addressParts.push(passenger.street_address);
    if (passenger.barangay_name) addressParts.push(passenger.barangay_name);
    if (passenger.city_municipality_name) addressParts.push(passenger.city_municipality_name);
    if (passenger.province_name) addressParts.push(passenger.province_name);
    const address = addressParts.length > 0 ? addressParts.join(', ') : '';
    
    // Set values - show fullname, mobile, and address if available
    let displayValue = passenger.fullname;
    if (passenger.mobile_number) displayValue += ` - ${passenger.mobile_number}`;
    if (address) displayValue += ` (${address.substring(0, 30)}${address.length > 30 ? '...' : ''})`;
    
    searchInput.value = displayValue;
    hiddenInput.value = passenger.passenger_id;
    
    // Hide dropdown
    dropdown.style.display = 'none';
    
    // Trigger change event
    hiddenInput.dispatchEvent(new Event('change'));
}

function viewPassenger(passengerId, event) {
    if (event) event.stopPropagation();
    
    fetch(`${window.BASE_URL}/api/pos/passengers?passenger_id=${passengerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                populateViewPassengerForm(data.data);
                viewPassengerModal.show();
            } else {
                showToast('error', 'Error', data.error || 'Failed to load passenger details');
            }
        })
        .catch(error => {
            console.error('Error fetching passenger:', error);
            showToast('error', 'Error', 'Failed to load passenger details');
        });
}

function populateViewPassengerForm(passenger) {
    document.getElementById('viewPassengerId').value = passenger.passenger_id;
    document.getElementById('viewPassengerFullname').value = passenger.fullname || '';
    document.getElementById('viewPassengerMobile').value = passenger.mobile_number || '';
    document.getElementById('viewPassengerEmail').value = passenger.email || '';
    document.getElementById('viewPassengerGender').value = passenger.gender || '';
    document.getElementById('viewPassengerBirthDate').value = passenger.birth_date || '';
    document.getElementById('viewPassengerRegion').value = passenger.region_code || '';
    document.getElementById('viewPassengerProvince').value = passenger.province_code || '';
    document.getElementById('viewPassengerCity').value = passenger.city_municipality_code || '';
    document.getElementById('viewPassengerBarangay').value = passenger.barangay_code || '';
    document.getElementById('viewPassengerStreetAddress').value = passenger.street_address || '';
    document.getElementById('viewPassengerLandmark').value = passenger.landmark || '';
    document.getElementById('viewPassengerZipCode').value = passenger.zip_code || '';
    document.getElementById('viewPassengerNotes').value = passenger.notes || '';
    
    // Display timestamps
    document.getElementById('viewPassengerCreatedAt').textContent = passenger.created_at ? formatDate(passenger.created_at) : '-';
    document.getElementById('viewPassengerUpdatedAt').textContent = passenger.updated_at ? formatDate(passenger.updated_at) : '-';
    document.getElementById('viewPassengerCreatedBy').textContent = passenger.created_by_username || '-';
    
    // Calculate and display customer duration
    if (passenger.created_at) {
        const createdDate = new Date(passenger.created_at);
        const now = new Date();
        const diffTime = Math.abs(now - createdDate);
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);
        
        let duration = '';
        if (diffYears > 0) {
            duration = `${diffYears} year${diffYears > 1 ? 's' : ''}`;
        } else if (diffMonths > 0) {
            duration = `${diffMonths} month${diffMonths > 1 ? 's' : ''}`;
        } else if (diffDays > 0) {
            duration = `${diffDays} day${diffDays > 1 ? 's' : ''}`;
        } else {
            duration = 'Today';
        }
        document.getElementById('viewPassengerCreatedSince').textContent = duration;
    } else {
        document.getElementById('viewPassengerCreatedSince').textContent = '-';
    }
    
    // Calculate and display age
    if (passenger.birth_date) {
        const birthDate = new Date(passenger.birth_date);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        document.getElementById('viewPassengerAge').textContent = age + ' years old';
    } else {
        document.getElementById('viewPassengerAge').textContent = '-';
    }
    
    // Load address dropdowns
    if (passenger.region_code) {
        loadViewProvinces();
        if (passenger.province_code) {
            loadViewCities();
            if (passenger.city_municipality_code) {
                loadViewBarangays();
            }
        }
    }
    
    // Reset to step 1
    viewPassengerStep = 1;
    updateViewPassengerWizardUI();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function updateViewPassengerWizardUI() {
    // Update step indicators
    document.querySelectorAll('#viewPassengerModal .step-item').forEach((item, index) => {
        item.classList.toggle('active', index + 1 === viewPassengerStep);
        item.classList.toggle('completed', index + 1 < viewPassengerStep);
    });
    
    // Show/hide steps
    document.querySelectorAll('#viewPassengerModal .wizard-step').forEach((step, index) => {
        step.classList.toggle('active', index + 1 === viewPassengerStep);
    });
    
    // Update navigation buttons
    const prevBtn = document.getElementById('viewPassengerPrevBtn');
    const nextBtn = document.getElementById('viewPassengerNextBtn');
    const saveBtn = document.querySelector('#viewPassengerModal .modal-footer .btn-primary[onclick="updatePassenger()"]');
    
    prevBtn.style.display = viewPassengerStep === 1 ? 'none' : 'inline-block';
    
    if (viewPassengerStep === totalViewPassengerSteps) {
        nextBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    } else {
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    }
}

function nextViewPassengerStep() {
    if (viewPassengerStep < totalViewPassengerSteps) {
        viewPassengerStep++;
        updateViewPassengerWizardUI();
    }
}

function prevViewPassengerStep() {
    if (viewPassengerStep > 1) {
        viewPassengerStep--;
        updateViewPassengerWizardUI();
    }
}

function loadViewProvinces() {
    const regionCode = document.getElementById('viewPassengerRegion').value;
    const provinceSelect = document.getElementById('viewPassengerProvince');
    
    if (!regionCode) {
        provinceSelect.innerHTML = '<option value="">Select Province</option>';
        provinceSelect.disabled = true;
        return;
    }
    
    fetch(`${window.BASE_URL}/api/psgc?action=provinces&region_code=${regionCode}`)
        .then(response => response.json())
        .then(data => {
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            data.forEach(p => {
                const option = document.createElement('option');
                option.value = p.province_code;
                option.textContent = p.province_name;
                provinceSelect.appendChild(option);
            });
            provinceSelect.disabled = false;
            
            // Restore selected value
            const current = document.getElementById('viewPassengerProvince').dataset.current;
            if (current) provinceSelect.value = current;
        })
        .catch(error => console.error('Error loading provinces:', error));
}

function loadViewCities() {
    const provinceCode = document.getElementById('viewPassengerProvince').value;
    const citySelect = document.getElementById('viewPassengerCity');
    
    if (!provinceCode) {
        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        citySelect.disabled = true;
        return;
    }
    
    fetch(`${window.BASE_URL}/api/psgc?action=cities&province_code=${provinceCode}`)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            data.forEach(c => {
                const option = document.createElement('option');
                option.value = c.city_municipality_code;
                option.textContent = c.city_municipality_name;
                citySelect.appendChild(option);
            });
            citySelect.disabled = false;
            
            // Restore selected value
            const current = document.getElementById('viewPassengerCity').dataset.current;
            if (current) citySelect.value = current;
        })
        .catch(error => console.error('Error loading cities:', error));
}

function loadViewBarangays() {
    const cityCode = document.getElementById('viewPassengerCity').value;
    const barangaySelect = document.getElementById('viewPassengerBarangay');
    
    if (!cityCode) {
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        return;
    }
    
    fetch(`${window.BASE_URL}/api/psgc?action=barangays&city_code=${cityCode}`)
        .then(response => response.json())
        .then(data => {
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            data.forEach(b => {
                const option = document.createElement('option');
                option.value = b.barangay_code;
                option.textContent = b.barangay_name;
                barangaySelect.appendChild(option);
            });
            barangaySelect.disabled = false;
            
            // Restore selected value
            const current = document.getElementById('viewPassengerBarangay').dataset.current;
            if (current) barangaySelect.value = current;
        })
        .catch(error => console.error('Error loading barangays:', error));
}

function updatePassenger() {
    const form = document.getElementById('viewPassengerForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        passenger_id: document.getElementById('viewPassengerId').value,
        fullname: document.getElementById('viewPassengerFullname').value,
        mobile_number: document.getElementById('viewPassengerMobile').value,
        email: document.getElementById('viewPassengerEmail').value,
        gender: document.getElementById('viewPassengerGender').value,
        birth_date: document.getElementById('viewPassengerBirthDate').value,
        region_code: document.getElementById('viewPassengerRegion').value,
        province_code: document.getElementById('viewPassengerProvince').value,
        city_municipality_code: document.getElementById('viewPassengerCity').value,
        barangay_code: document.getElementById('viewPassengerBarangay').value,
        street_address: document.getElementById('viewPassengerStreetAddress').value,
        landmark: document.getElementById('viewPassengerLandmark').value,
        zip_code: document.getElementById('viewPassengerZipCode').value,
        notes: document.getElementById('viewPassengerNotes').value,
    };
    
    fetch(`${window.BASE_URL}/api/pos/passengers`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Success', data.message);
            viewPassengerModal.hide();
            // Refresh passenger search
            document.getElementById('ticketPassengerSearch').value = '';
            document.getElementById('ticketPassenger').value = '';
        } else {
            showToast('error', 'Error', data.error || 'Failed to update passenger');
        }
    })
    .catch(error => {
        console.error('Error updating passenger:', error);
        showToast('error', 'Error', 'Failed to update passenger');
    });
}

// Keyboard navigation for passenger dropdown
let currentActiveIndex = -1;

document.addEventListener('keydown', function(e) {
    const dropdown = document.getElementById('ticketPassengerDropdown');
    const searchInput = document.getElementById('ticketPassengerSearch');
    
    if (!dropdown || dropdown.style.display === 'none') return;
    if (document.activeElement !== searchInput) return;
    
    const items = dropdown.querySelectorAll('.passenger-dropdown-item');
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        currentActiveIndex = Math.min(currentActiveIndex + 1, items.length - 1);
        updateActiveItem(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        currentActiveIndex = Math.max(currentActiveIndex - 1, 0);
        updateActiveItem(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentActiveIndex >= 0 && items[currentActiveIndex]) {
            const passengerId = items[currentActiveIndex].dataset.passengerId;
            // Fetch full passenger data
            const passenger = {
                passenger_id: passengerId,
                fullname: items[currentActiveIndex].querySelector('.fw-bold').textContent,
                mobile_number: items[currentActiveIndex].querySelector('.text-muted').textContent
            };
            selectTicketPassenger(passenger);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        dropdown.style.display = 'none';
        currentActiveIndex = -1;
    }
});

function updateActiveItem(items) {
    items.forEach((item, index) => {
        item.classList.remove('active');
        if (index === currentActiveIndex) {
            item.classList.add('active');
            item.scrollIntoView({ block: 'nearest' });
        }
    });
}

function loadDiscountTypes() {
    const select = document.getElementById('ticketDiscount');
    fetch(`${window.BASE_URL}/api/discount-types`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                select.innerHTML = '<option value="0">No Discount</option>';
                data.data.forEach(d => {
                    const option = document.createElement('option');
                    option.value = d.discount_id;
                    option.textContent = `${d.name} (${d.code})`;
                    option.dataset.discountAmount = 0; // Will be updated if discount has amount
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading discount types:', error);
        });
}

function loadAccommodationTypes() {
    const select = document.getElementById('ticketAccommodation');
    fetch(`${window.BASE_URL}/api/accommodation-types`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                select.innerHTML = '<option value="">None</option>';
                data.data.forEach(a => {
                    const option = document.createElement('option');
                    option.value = a.accommodation_id;
                    option.textContent = `${a.name} (${a.code})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading accommodation types:', error);
        });
}

function loadProviderServiceFees() {
    fetch(`${window.BASE_URL}/api/provider-service-fees`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.fees) {
                // Store service fees for later use
                window.providerServiceFees = data.data.fees;
            }
            // Load all wallets (not filtered by service fee)
            loadWallets();
        })
        .catch(error => {
            console.error('Error loading provider service fees:', error);
            loadWallets(); // Fallback to loading all wallets
        });
}

function loadWallets(providerId = null, branchId = null) {
    const select = document.getElementById('ticketWallet');
    let url = `${window.BASE_URL}/api/wallets`;
    
    // Add filter parameters if provided
    if (providerId || branchId) {
        const params = [];
        if (providerId) params.push(`provider_id=${providerId}`);
        if (branchId) params.push(`branch_id=${branchId}`);
        url += `?${params.join('&')}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.wallets) {
                select.innerHTML = '<option value="">Select Wallet</option>';
                data.data.wallets.forEach(w => {
                    const option = document.createElement('option');
                    option.value = w.wallet_id;
                    option.dataset.providerId = w.provider_id;
                    option.dataset.branchId = w.branch_id;
                    option.textContent = `${w.wallet_name || 'Wallet #' + w.wallet_id} - ₱${parseFloat(w.current_balance).toFixed(2)} (${w.status})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading wallets:', error);
        });
}

function loadServiceFeeForWallet() {
    const walletSelect = document.getElementById('ticketWallet');
    const selectedOption = walletSelect.selectedOptions[0];
    const serviceFeeDisplay = document.getElementById('ticketServiceFeeDisplay');
    const serviceFeeInput = document.getElementById('ticketServiceFee');
    const baseAmountDisplay = document.getElementById('ticketBaseAmountDisplay');
    const baseAmountInput = document.getElementById('ticketBaseAmount');
    
    if (!selectedOption || !selectedOption.value) {
        serviceFeeDisplay.textContent = '-';
        serviceFeeInput.value = 0;
        baseAmountDisplay.textContent = '₱0.00';
        computeTicketTotal();
        return;
    }
    
    const providerId = selectedOption.dataset.providerId;
    const branchId = selectedOption.dataset.branchId;
    
    console.log('Loading service fee for wallet:', selectedOption.value, 'providerId:', providerId, 'branchId:', branchId);
    
    if (!providerId) {
        serviceFeeDisplay.textContent = '-';
        serviceFeeInput.value = 0;
        baseAmountDisplay.textContent = '₱0.00';
        computeTicketTotal();
        return;
    }
    
    // Fetch service fee for this provider and branch
    let url = `${window.BASE_URL}/api/provider-service-fees?provider_id=${providerId}`;
    if (branchId) {
        url += `&branch_id=${branchId}`;
    }
    
    console.log('Fetching service fee from:', url);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Service fee response:', data);
            if (data.success && data.data && data.data.fees && data.data.fees.length > 0) {
                const fee = data.data.fees[0];
                const feeType = fee.fee_type;
                const feeValue = parseFloat(fee.fee_value);
                
                // Update service fee input field
                serviceFeeInput.value = feeValue;
                
                // Update service fee display
                if (feeType === 'FIXED') {
                    serviceFeeDisplay.textContent = `₱${feeValue.toFixed(2)} (Fixed)`;
                } else if (feeType === 'PERCENT') {
                    serviceFeeDisplay.textContent = `${feeValue}% (Percent)`;
                    // For percent, calculate based on base amount
                    const baseAmount = parseFloat(baseAmountInput.value) || 0;
                    const calculatedFee = (baseAmount * feeValue) / 100;
                    serviceFeeInput.value = calculatedFee;
                    serviceFeeDisplay.textContent = `₱${calculatedFee.toFixed(2)} (${feeValue}% of Base)`;
                } else {
                    serviceFeeDisplay.textContent = `₱${feeValue.toFixed(2)}`;
                    serviceFeeInput.value = feeValue;
                }
                
                // Store fee data for use in ticket total calculation
                window.currentServiceFee = {
                    type: feeType,
                    value: feeValue
                };
                
                // Update base amount display
                const currentBaseAmount = parseFloat(baseAmountInput.value) || 0;
                baseAmountDisplay.textContent = `₱${currentBaseAmount.toFixed(2)}`;
                
                // Recalculate ticket total
                computeTicketTotal();
            } else {
                serviceFeeDisplay.textContent = 'No service fee configured';
                serviceFeeInput.value = 0;
                window.currentServiceFee = null;
                computeTicketTotal();
            }
        })
        .catch(error => {
            console.error('Error loading service fee:', error);
            serviceFeeDisplay.textContent = 'Error loading fee';
            serviceFeeInput.value = 0;
            computeTicketTotal();
        });
}

function searchTicketPassenger(searchTerm) {
    console.log('Searching for passenger:', searchTerm);
    // Reset active index when searching
    currentActiveIndex = -1;
    // Debounce the search to avoid too many API calls
    clearTimeout(window.ticketPassengerSearchTimeout);
    window.ticketPassengerSearchTimeout = setTimeout(() => {
        renderTicketPassengers(searchTerm);
    }, 300);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('ticketPassengerDropdown');
    const searchInput = document.getElementById('ticketPassengerSearch');
    if (dropdown && searchInput && !dropdown.contains(e.target) && e.target !== searchInput) {
        dropdown.style.display = 'none';
    }
});

function filterCustomers() {
    const search = document.getElementById('customerSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    let visible = 0;
    rows.forEach(row => {
        const match = row.dataset.search.includes(search);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('noCustomersMsg').style.display = visible === 0 ? '' : 'none';
}

function selectCustomerRadio(radio) {
    document.getElementById('confirmCustomerBtn').disabled = false;
}

function openCustomerModal() {
    selectedCustomerId = null;
    document.getElementById('customerSearch').value = '';
    document.querySelectorAll('input[name="selectedCustomer"]').forEach(r => r.checked = false);
    document.getElementById('confirmCustomerBtn').disabled = true;
    filterCustomers();
    selectCustomerModal.show();
}

function confirmCustomerSelection() {
    const selected = document.querySelector('input[name="selectedCustomer"]:checked');
    if (!selected) return;
    selectedCustomerId = selected.value;
    selectCustomerModal.hide();
    // Continue with payment entry
    document.getElementById('paymentEntryRow').style.display = '';
}

// =============================================
// SESSION MANAGEMENT
// =============================================

async function submitOpenSession() {
    const branchId = document.getElementById('sessionBranchId').value;
    const openingCash = parseFloat(document.getElementById('sessionOpeningCash').value) || 0;
    const notes = document.getElementById('sessionNotes').value.trim();

    if (!branchId) { showToast('danger', 'Error', 'Branch is required.'); return; }

    const btn = document.querySelector('#openSessionModal .btn-success');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Opening...';

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch_id: branchId, opening_cash_balance: openingCash, notes })
        });
        const result = await res.json();
        if (result.success) {
            openSessionModal.hide();
            showToast('success', 'Session Opened', 'Your cashier session is now active.');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to open session.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function openCloseSession() {
    document.getElementById('closingCash').value = '0.00';
    document.getElementById('closingNotes').value = '';
    document.getElementById('varianceDisplay').textContent = '₱0.00';
    document.getElementById('varianceDisplay').className = 'fw-bold text-muted';

    // Fetch session summary
    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions?id=${window.POS_SESSION_ID}`);
        const result = await res.json();
        if (result.success) {
            const s = result.data;
            const expected = parseFloat(s.expected_cash || 0);
            document.getElementById('expectedCash').textContent = '₱' + fmt(expected);
            document.getElementById('closeSummary').innerHTML =
                `Opening Cash: <strong>₱${fmt(s.starting_cash)}</strong> &nbsp;|&nbsp; ` +
                `Cash Received: <strong>₱${fmt(s.total_cash_paid)}</strong> &nbsp;|&nbsp; ` +
                `Transactions: <strong>${s.txn_count || 0}</strong>`;
        }
    } catch (e) {}

    closeSessionModal.show();
}

function computeVariance() {
    const actual = parseFloat(document.getElementById('closingCash').value) || 0;
    const expectedText = document.getElementById('expectedCash').textContent.replace(/[₱,]/g, '');
    const expected = parseFloat(expectedText) || 0;
    const variance = actual - expected;
    const display = document.getElementById('varianceDisplay');
    display.textContent = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
    display.className = 'fw-bold ' + (Math.abs(variance) < 0.01 ? 'text-success' : variance < 0 ? 'text-danger' : 'text-warning');
}

async function submitCloseSession() {
    const closingCash = parseFloat(document.getElementById('closingCash').value) || 0;
    const notes = document.getElementById('closingNotes').value.trim();

    const btn = document.querySelector('#closeSessionModal .btn-danger');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Closing...';

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: window.POS_SESSION_ID, closing_cash_balance: closingCash, notes, action: 'close' })
        });
        const result = await res.json();
        if (result.success) {
            closeSessionModal.hide();
            showToast('success', 'Session Closed', 'Your cashier session has been closed.');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to close session.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// =============================================
// TRANSACTION TYPE SWITCHING
// =============================================

function switchTransactionType(type) {
    if (type === transactionType) return;
    
    // Check if cart has items
    if (cart.length > 0) {
        // Store pending type and show confirmation modal
        pendingTransactionType = type;
        switchTypeModal.show();
        return;
    }
    
    // No items in cart, switch directly
    transactionType = type;
    localStorage.setItem('posTransactionType', type);
    updateTransactionTypeUI();
}

function confirmSwitchType() {
    if (!pendingTransactionType) return;
    
    transactionType = pendingTransactionType;
    localStorage.setItem('posTransactionType', transactionType);
    updateTransactionTypeUI();
    clearCart();
    pendingTransactionType = null;
    switchTypeModal.hide();
}

function cancelSwitchType() {
    pendingTransactionType = null;
    switchTypeModal.hide();
}

function updateTransactionTypeUI() {
    document.getElementById('btnTicketType').classList.toggle('active', transactionType === 'ticket');
    document.getElementById('btnServiceType').classList.toggle('active', transactionType === 'service');
    document.getElementById('ticketSection').style.display = transactionType === 'ticket' ? '' : 'none';
    document.getElementById('serviceSection').style.display = transactionType === 'service' ? '' : 'none';
}

// =============================================
// PASSENGER MANAGEMENT
// =============================================

function openAddPassengerModal() {
    const form = document.getElementById('addPassengerForm');
    form.reset();
    form.classList.remove('was-validated');

    // Remove all validation classes
    form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
        el.classList.remove('is-invalid');
        el.classList.remove('is-valid');
    });

    // Reset address dropdowns
    document.getElementById('newPassengerRegion').innerHTML = '<option value="">Select Region</option>';
    document.getElementById('newPassengerProvince').innerHTML = '<option value="">Select Province</option>';
    document.getElementById('newPassengerProvince').disabled = true;
    document.getElementById('newPassengerCity').innerHTML = '<option value="">Select City/Municipality</option>';
    document.getElementById('newPassengerCity').disabled = true;
    document.getElementById('newPassengerBarangay').innerHTML = '<option value="">Select Barangay</option>';
    document.getElementById('newPassengerBarangay').disabled = true;

    currentPassengerStep = 1;
    updatePassengerWizardUI();
    populateRegionSelect();
    addPassengerModal.show();
    
    // Auto focus Full Name field after modal is shown
    setTimeout(() => {
        document.getElementById('newPassengerFullname').focus();
    }, 300);
    
    // Refresh passenger lists after adding
    renderCustomers();
    // Clear ticket passenger search
    document.getElementById('ticketPassengerSearch').value = '';
    document.getElementById('ticketPassenger').value = '';
}

function nextPassengerStep() {
    if (!validatePassengerStep(currentPassengerStep)) return;
    
    if (currentPassengerStep < totalPassengerSteps) {
        currentPassengerStep++;
        updatePassengerWizardUI();
    }
}

function prevPassengerStep() {
    if (currentPassengerStep > 1) {
        currentPassengerStep--;
        updatePassengerWizardUI();
    }
}

function validatePassengerStep(step) {
    if (step === 1) {
        const fullname = document.getElementById('newPassengerFullname');
        const mobile = document.getElementById('newPassengerMobile');
        const gender = document.getElementById('newPassengerGender');
        let isValid = true;

        // Validate fullname
        if (!fullname.value.trim()) {
            fullname.classList.add('is-invalid');
            fullname.classList.remove('is-valid');
            isValid = false;
        } else {
            fullname.classList.remove('is-invalid');
            fullname.classList.add('is-valid');
        }

        // Validate mobile
        if (!mobile.value.trim()) {
            mobile.classList.add('is-invalid');
            mobile.classList.remove('is-valid');
            isValid = false;
        } else {
            // Check PH mobile format
            const isValidFormat = /^09[0-9]{9}$/.test(mobile.value);
            if (isValidFormat) {
                mobile.classList.remove('is-invalid');
                mobile.classList.add('is-valid');
            } else {
                mobile.classList.add('is-invalid');
                mobile.classList.remove('is-valid');
                isValid = false;
            }
        }

        // Validate gender
        if (!gender.value) {
            gender.classList.add('is-invalid');
            gender.classList.remove('is-valid');
            isValid = false;
        } else {
            gender.classList.remove('is-invalid');
            gender.classList.add('is-valid');
        }

        if (!isValid) {
            showToast('danger', 'Error', 'Please fill in all required fields.');
        }

        return isValid;
    }
    
    if (step === 2) {
        const region = document.getElementById('newPassengerRegion');
        const province = document.getElementById('newPassengerProvince');
        const city = document.getElementById('newPassengerCity');
        const barangay = document.getElementById('newPassengerBarangay');
        let isValid = true;

        // Validate region
        if (!region.value) {
            region.classList.add('is-invalid');
            region.classList.remove('is-valid');
            isValid = false;
        } else {
            region.classList.remove('is-invalid');
            region.classList.add('is-valid');
        }

        // Validate province
        if (!province.value) {
            province.classList.add('is-invalid');
            province.classList.remove('is-valid');
            isValid = false;
        } else {
            province.classList.remove('is-invalid');
            province.classList.add('is-valid');
        }

        // Validate city
        if (!city.value) {
            city.classList.add('is-invalid');
            city.classList.remove('is-valid');
            isValid = false;
        } else {
            city.classList.remove('is-invalid');
            city.classList.add('is-valid');
        }

        // Validate barangay
        if (!barangay.value) {
            barangay.classList.add('is-invalid');
            barangay.classList.remove('is-valid');
            isValid = false;
        } else {
            barangay.classList.remove('is-invalid');
            barangay.classList.add('is-valid');
        }

        if (!isValid) {
            showToast('danger', 'Error', 'Please fill in all required address fields.');
        }

        return isValid;
    }
    
    return true;
}

function updatePassengerWizardUI() {
    // Update step indicators
    document.querySelectorAll('#addPassengerModal .step-item').forEach(item => {
        const stepNum = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed');
        if (stepNum < currentPassengerStep) {
            item.classList.add('completed');
        } else if (stepNum === currentPassengerStep) {
            item.classList.add('active');
        }
    });

    // Show/hide wizard steps
    document.querySelectorAll('#addPassengerModal .wizard-step').forEach(step => {
        const stepNum = parseInt(step.dataset.step);
        step.classList.remove('active');
        if (stepNum === currentPassengerStep) {
            step.classList.add('active');
        }
    });

    // Update buttons
    const prevBtn = document.getElementById('prevPassengerStepBtn');
    const nextBtn = document.getElementById('nextPassengerStepBtn');
    const saveBtn = document.getElementById('savePassengerBtn');

    prevBtn.style.display = currentPassengerStep > 1 ? 'inline-block' : 'none';
    
    if (currentPassengerStep === totalPassengerSteps) {
        nextBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    } else {
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    }
}

function populateRegionSelect() {
    const select = document.getElementById('newPassengerRegion');
    select.innerHTML = '<option value="">Select Region</option>';
    
    // Fetch regions via AJAX
    fetch(`${window.BASE_URL}/api/psgc?action=regions`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.regions) {
                data.data.regions.forEach(r => {
                    const option = document.createElement('option');
                    option.value = r.region_code;
                    option.textContent = r.region_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching regions:', error);
            showToast('danger', 'Error', 'Failed to load regions');
        });
}

function loadProvinces() {
    const regionCode = document.getElementById('newPassengerRegion').value;
    const provinceSelect = document.getElementById('newPassengerProvince');
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!regionCode) {
        provinceSelect.innerHTML = '<option value="">Select Province</option>';
        provinceSelect.disabled = true;
        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        citySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        return;
    }

    // Fetch provinces via AJAX
    fetch(`${window.BASE_URL}/api/psgc?action=provinces&region_code=${regionCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.provinces) {
                provinceSelect.innerHTML = '<option value="">Select Province</option>';
                data.data.provinces.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.province_code;
                    option.textContent = p.province_name;
                    provinceSelect.appendChild(option);
                });
                provinceSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching provinces:', error);
            showToast('danger', 'Error', 'Failed to load provinces');
        });

    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    citySelect.disabled = true;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
}

function loadCities() {
    const provinceCode = document.getElementById('newPassengerProvince').value;
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!provinceCode) {
        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        citySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        return;
    }

    // Fetch cities via AJAX
    fetch(`${window.BASE_URL}/api/psgc?action=cities&province_code=${provinceCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.cities) {
                citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
                data.data.cities.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.city_municipality_code;
                    option.textContent = c.city_municipality_name;
                    citySelect.appendChild(option);
                });
                citySelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching cities:', error);
            showToast('danger', 'Error', 'Failed to load cities');
        });

    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
}

function loadBarangays() {
    const cityCode = document.getElementById('newPassengerCity').value;
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!cityCode) {
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        return;
    }

    // Fetch barangays via AJAX
    fetch(`${window.BASE_URL}/api/psgc?action=barangays&city_code=${cityCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.barangays) {
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                data.data.barangays.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.barangay_code;
                    option.textContent = b.barangay_name;
                    barangaySelect.appendChild(option);
                });
                barangaySelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching barangays:', error);
            showToast('danger', 'Error', 'Failed to load barangays');
        });
}

async function saveNewPassenger() {
    const fullname = document.getElementById('newPassengerFullname');
    const mobile = document.getElementById('newPassengerMobile');
    const gender = document.getElementById('newPassengerGender');
    const region = document.getElementById('newPassengerRegion');
    const province = document.getElementById('newPassengerProvince');
    const city = document.getElementById('newPassengerCity');
    const barangay = document.getElementById('newPassengerBarangay');
    const email = document.getElementById('newPassengerEmail').value.trim();
    const birthDate = document.getElementById('newPassengerBirthDate').value;
    const streetAddress = document.getElementById('newPassengerStreetAddress').value.trim();
    const landmark = document.getElementById('newPassengerLandmark').value.trim();
    const zipCode = document.getElementById('newPassengerZipCode').value.trim();
    const notes = document.getElementById('newPassengerNotes').value.trim();

    let isValid = true;

    // Validate fullname
    if (!fullname.value.trim()) {
        fullname.classList.add('is-invalid');
        fullname.classList.remove('is-valid');
        isValid = false;
    } else {
        fullname.classList.remove('is-invalid');
        fullname.classList.add('is-valid');
    }

    // Validate mobile
    if (!mobile.value.trim()) {
        mobile.classList.add('is-invalid');
        mobile.classList.remove('is-valid');
        isValid = false;
    } else {
        // Check PH mobile format
        const isValidFormat = /^09[0-9]{9}$/.test(mobile.value);
        if (isValidFormat) {
            mobile.classList.remove('is-invalid');
            mobile.classList.add('is-valid');
        } else {
            mobile.classList.add('is-invalid');
            mobile.classList.remove('is-valid');
            isValid = false;
        }
    }

    // Validate gender
    if (!gender.value) {
        gender.classList.add('is-invalid');
        gender.classList.remove('is-valid');
        isValid = false;
    } else {
        gender.classList.remove('is-invalid');
        gender.classList.add('is-valid');
    }

    // Validate region
    if (!region.value) {
        region.classList.add('is-invalid');
        region.classList.remove('is-valid');
        isValid = false;
    } else {
        region.classList.remove('is-invalid');
        region.classList.add('is-valid');
    }

    // Validate province
    if (!province.value) {
        province.classList.add('is-invalid');
        province.classList.remove('is-valid');
        isValid = false;
    } else {
        province.classList.remove('is-invalid');
        province.classList.add('is-valid');
    }

    // Validate city
    if (!city.value) {
        city.classList.add('is-invalid');
        city.classList.remove('is-valid');
        isValid = false;
    } else {
        city.classList.remove('is-invalid');
        city.classList.add('is-valid');
    }

    // Validate barangay
    if (!barangay.value) {
        barangay.classList.add('is-invalid');
        barangay.classList.remove('is-valid');
        isValid = false;
    } else {
        barangay.classList.remove('is-invalid');
        barangay.classList.add('is-valid');
    }

    if (!isValid) {
        showToast('danger', 'Error', 'Please fill in all required fields.');
        return;
    }

    const btn = document.getElementById('savePassengerBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/passengers`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fullname: fullname.value.trim(),
                mobile_number: mobile.value.trim(),
                email,
                gender: gender.value,
                birth_date: birthDate,
                region_code: region.value || null,
                province_code: province.value || null,
                city_municipality_code: city.value || null,
                barangay_code: barangay.value || null,
                street_address: streetAddress,
                landmark,
                zip_code: zipCode,
                notes
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('success', 'Passenger Added', `${result.fullname} has been added to the database.`);
            addPassengerModal.hide();
            // Refresh passenger dropdown
            await refreshPassengerDropdown();
            // Select the new passenger
            document.getElementById('ticketPassenger').value = result.passenger_id;
        } else {
            showToast('danger', 'Error', result.error || 'Failed to add passenger.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="fas fa-save me-2"></span>Save Passenger';
    }
}

async function refreshPassengerDropdown() {
    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/passengers/list`);
        const result = await res.json();
        if (result.success) {
            const select = document.getElementById('ticketPassenger');
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select Passenger</option>';
            result.passengers.forEach(p => {
                const option = document.createElement('option');
                option.value = p.passenger_id;
                option.dataset.balance = p.balance || 0;
                option.textContent = `${p.fullname} (₱${(p.balance || 0).toFixed(2)})`;
                select.appendChild(option);
            });
            select.value = currentValue;
        }
    } catch (e) {
        console.error('Failed to refresh passengers:', e);
    }
}

// =============================================
// TICKET SELECTION
// =============================================

function computeTicketTotal() {
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value) || 0;
    const serviceFee = parseFloat(document.getElementById('ticketServiceFee').value) || 0;
    const discountSelect = document.getElementById('ticketDiscount');
    const discountValue = discountSelect.value === '0' ? 0 : parseFloat(discountSelect.value) || 0;
    const total = baseAmount + serviceFee - discountValue;
    
    // Update displays
    document.getElementById('ticketBaseAmountDisplay').textContent = `₱${baseAmount.toFixed(2)}`;
    document.getElementById('ticketTotalDisplay').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addTicketToCart() {
    if (!window.POS_HAS_SESSION) {
        showToast('warning', 'No Session', 'Please open a cashier session first.');
        openSessionModal.show();
        return;
    }

    const passengerId = document.getElementById('ticketPassenger').value;
    const travelDate = document.getElementById('ticketTravelDate').value;
    const origin = document.getElementById('ticketOrigin').value.trim();
    const destination = document.getElementById('ticketDestination').value.trim();
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value) || 0;
    const serviceFee = parseFloat(document.getElementById('ticketServiceFee').value) || 0;
    const discount = parseFloat(document.getElementById('ticketDiscount').value) || 0;
    const total = baseAmount + serviceFee - discount;

    if (!passengerId) { showToast('danger', 'Error', 'Please select a passenger.'); return; }
    if (!travelDate) { showToast('danger', 'Error', 'Please enter travel date.'); return; }
    if (!origin) { showToast('danger', 'Error', 'Please enter origin.'); return; }
    if (!destination) { showToast('danger', 'Error', 'Please enter destination.'); return; }
    if (total <= 0) { showToast('danger', 'Error', 'Ticket total must be greater than 0.'); return; }

    // Get passenger name from select
    const passengerSelect = document.getElementById('ticketPassenger');
    const passengerName = passengerSelect.options[passengerSelect.selectedIndex].text.split(' (')[0];

    ticketInCart = {
        type: 'ticket',
        passengerId,
        passengerName,
        travelDate,
        origin,
        destination,
        baseAmount,
        serviceFee,
        discount,
        total
    };

    cart = [ticketInCart]; // Replace cart with ticket
    document.getElementById('serviceAddonsSection').style.display = '';
    renderCart();
    showToast('success', 'Ticket Added', 'Ticket added to cart. You can now add service add-ons.');
}

function toggleServiceAddons() {
    const body = document.getElementById('serviceAddonsBody');
    body.style.display = body.style.display === 'none' ? '' : 'none';
}

function selectServiceAddon(id, name, defaultAmount, allowCustom) {
    if (!ticketInCart) {
        showToast('warning', 'No Ticket', 'Please add a ticket first.');
        return;
    }

    activeServiceType = { id, name, defaultAmount, allowCustom };

    document.getElementById('itemServiceName').value = name;
    document.getElementById('itemUnitPrice').value = parseFloat(defaultAmount || 0).toFixed(2);
    document.getElementById('itemUnitPrice').readOnly = !allowCustom;
    document.getElementById('itemQty').value = 1;
    computeItemTotal();

    document.getElementById('itemEntryCard').style.display = '';
    document.getElementById('itemEntryCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// =============================================
// SERVICE TYPE SELECTION
// =============================================

function selectServiceType(id, name, defaultAmount, allowCustom, requiresWallet) {
    if (!window.POS_HAS_SESSION) {
        showToast('warning', 'No Session', 'Please open a cashier session first.');
        openSessionModal.show();
        return;
    }

    activeServiceType = { id, name, defaultAmount, allowCustom, requiresWallet };

    document.getElementById('itemServiceName').value = name;
    document.getElementById('itemUnitPrice').value = parseFloat(defaultAmount || 0).toFixed(2);
    document.getElementById('itemUnitPrice').readOnly = !allowCustom;
    document.getElementById('itemQty').value = 1;
    computeItemTotal();

    document.getElementById('itemEntryCard').style.display = '';
    document.getElementById('itemEntryCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function cancelItemEntry() {
    document.getElementById('itemEntryCard').style.display = 'none';
    activeServiceType = null;
}

function computeItemTotal() {
    const qty = parseInt(document.getElementById('itemQty').value) || 1;
    const price = parseFloat(document.getElementById('itemUnitPrice').value) || 0;
    const total = qty * price;
    document.getElementById('itemTotalDisplay').textContent = '₱' + fmt(total);
}

function addItemToCart() {
    if (!activeServiceType) return;
    const qty = parseInt(document.getElementById('itemQty').value) || 1;
    const unitPrice = parseFloat(document.getElementById('itemUnitPrice').value) || 0;
    const description = document.getElementById('itemDescription').value.trim();
    const total = qty * unitPrice;

    const serviceItem = {
        type: 'service',
        serviceTypeId: activeServiceType.id,
        serviceName: activeServiceType.name,
        requiresWallet: activeServiceType.requiresWallet,
        qty,
        unitPrice,
        total,
        description
    };

    if (transactionType === 'ticket' && ticketInCart) {
        // Add as addon to ticket
        cart.push(serviceItem);
    } else {
        // Service only mode
        cart.push(serviceItem);
    }

    document.getElementById('itemEntryCard').style.display = 'none';
    document.getElementById('itemDescription').value = '';
    activeServiceType = null;
    renderCart();
    showToast('success', 'Added', `"${serviceItem.serviceName}" added to cart.`);
}

// =============================================
// CART RENDERING
// =============================================

function renderCart() {
    const list = document.getElementById('cartItemsList');
    const emptyMsg = document.getElementById('emptyCartMsg');
    const totals = document.getElementById('cartTotals');
    const clearBtn = document.getElementById('clearCartBtn');
    const cartActions = document.getElementById('cartActions');
    const paySection = document.getElementById('paymentSection');

    if (cart.length === 0) {
        emptyMsg.style.display = '';
        list.innerHTML = '';
        totals.style.display = 'none';
        clearBtn.style.display = 'none';
        cartActions.style.display = 'none';
        paySection.style.display = 'none';
        ticketInCart = null;
        document.getElementById('serviceAddonsSection').style.display = 'none';
        return;
    }

    emptyMsg.style.display = 'none';
    clearBtn.style.display = '';
    cartActions.style.display = '';
    totals.style.display = '';

    let html = '';
    let subtotal = 0;
    cart.forEach((item, idx) => {
        subtotal += item.total;
        if (item.type === 'ticket') {
            html += `
            <div class="cart-item d-flex justify-content-between align-items-start border-start border-4 border-primary">
              <div class="flex-grow-1">
                <div class="fw-semibold small text-primary"><span class="fas fa-ticket-alt me-1"></span>${item.passengerName}</div>
                <div class="text-muted" style="font-size:0.75rem;">${item.origin} → ${item.destination}</div>
                <div class="text-muted" style="font-size:0.75rem;">${item.travelDate}</div>
                <div class="text-muted small">Base: ₱${fmt(item.baseAmount)} | Fee: ₱${fmt(item.serviceFee)}</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <strong class="text-success">₱${fmt(item.total)}</strong>
                <button class="btn btn-sm btn-outline-danger p-1" style="line-height:1;" onclick="removeCartItem(${idx})">
                  <span class="fas fa-times"></span>
                </button>
              </div>
            </div>`;
        } else {
            html += `
            <div class="cart-item d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="fw-semibold small">${item.serviceName}</div>
                ${item.description ? `<div class="text-muted" style="font-size:0.75rem;">${item.description}</div>` : ''}
                <div class="text-muted small">${item.qty} × ₱${fmt(item.unitPrice)}</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <strong class="text-success">₱${fmt(item.total)}</strong>
                <button class="btn btn-sm btn-outline-danger p-1" style="line-height:1;" onclick="removeCartItem(${idx})">
                  <span class="fas fa-times"></span>
                </button>
              </div>
            </div>`;
        }
    });

    list.innerHTML = html;
    document.getElementById('cartSubtotal').textContent = '₱' + fmt(subtotal);
    document.getElementById('cartTotal').textContent = '₱' + fmt(subtotal);
}

function removeCartItem(idx) {
    const item = cart[idx];
    if (item.type === 'ticket') {
        ticketInCart = null;
        cart = []; // Clear everything if ticket is removed
        document.getElementById('serviceAddonsSection').style.display = 'none';
    } else {
        cart.splice(idx, 1);
    }
    paymentLines = [];
    renderCart();
    renderPaymentLines();
    document.getElementById('paymentSection').style.display = cart.length > 0 ? '' : 'none';
    document.getElementById('confirmOrderSection').style.display = 'none';
}

function clearCart() {
    if (!confirm('Clear all items from cart?')) return;
    cart = [];
    paymentLines = [];
    ticketInCart = null;
    activeServiceType = null;
    renderCart();
    renderPaymentLines();
    document.getElementById('itemEntryCard').style.display = 'none';
    document.getElementById('paymentSection').style.display = 'none';
    document.getElementById('confirmOrderSection').style.display = 'none';
    document.getElementById('serviceAddonsSection').style.display = 'none';
}

// =============================================
// PAYMENT
// =============================================

function getCartTotal() {
    return cart.reduce((s, i) => s + i.total, 0);
}

function proceedToPayment() {
    if (cart.length === 0) { showToast('warning', 'Cart Empty', 'Add items first.'); return; }
    paymentLines = [];
    renderPaymentLines();
    document.getElementById('paymentSection').style.display = '';
    document.getElementById('confirmOrderSection').style.display = 'none';
    document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth' });
}

function selectPaymentMethod(el) {
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active-payment'));
    el.classList.add('active-payment');

    activePaymentMethod = {
        id: el.dataset.methodId,
        code: el.dataset.methodCode,
        name: el.dataset.methodName,
        type: el.dataset.methodType,
        requiresConfirmation: el.dataset.requiresConfirmation === '1',
        requiresCustomer: el.dataset.requiresCustomer === '1',
        requiresReference: el.dataset.requiresReference === '1',
    };

    const remaining = getCartTotal() - paymentLines.reduce((s, p) => s + p.amount, 0);
    document.getElementById('selectedMethodName').value = activePaymentMethod.name;
    document.getElementById('paymentAmount').value = Math.max(0, remaining).toFixed(2);
    document.getElementById('paymentAmountHint').textContent = remaining > 0 ? `Remaining: ₱${fmt(remaining)}` : 'Fully paid';
    document.getElementById('referenceRow').style.display = activePaymentMethod.requiresReference ? '' : 'none';
    document.getElementById('bankAccountRow').style.display = (activePaymentMethod.type === 'BANK_TRANSFER' || activePaymentMethod.type === 'E_WALLET') ? '' : 'none';
    document.getElementById('referenceNumber').value = '';

    // Filter bank accounts by payment method type
    const bankSelect = document.getElementById('bankAccountSelect');
    const options = bankSelect.querySelectorAll('option:not([value=""])');
    options.forEach(opt => {
        const methodType = opt.dataset.methodType || '';
        if (activePaymentMethod.type === 'BANK_TRANSFER') {
            opt.style.display = (methodType === 'BANK_TRANSFER' || methodType === '' || !methodType) ? '' : 'none';
        } else if (activePaymentMethod.type === 'E_WALLET') {
            opt.style.display = (methodType === 'E_WALLET' || methodType === '' || !methodType) ? '' : 'none';
        } else {
            opt.style.display = '';
        }
    });
    bankSelect.value = '';

    // If CHARGE payment, open customer selection modal
    if (activePaymentMethod.type === 'CHARGE') {
        openCustomerModal();
    } else {
        document.getElementById('paymentEntryRow').style.display = '';
    }
    computeChange();
}

function cancelPaymentEntry() {
    document.getElementById('paymentEntryRow').style.display = 'none';
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active-payment'));
    activePaymentMethod = null;
}

function addPaymentLine() {
    if (!activePaymentMethod) { showToast('warning', 'Select Method', 'Select a payment method first.'); return; }
    const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    if (amount <= 0) { showToast('danger', 'Invalid Amount', 'Enter a valid payment amount.'); return; }
    const refNum = document.getElementById('referenceNumber').value.trim();
    if (activePaymentMethod.requiresReference && !refNum) {
        showToast('danger', 'Reference Required', 'Please enter the reference number.'); return;
    }
    if (activePaymentMethod.type === 'CHARGE' && !selectedCustomerId) {
        showToast('danger', 'Customer Required', 'Please select a customer for CHARGE payment.'); return;
    }
    const bankAccountId = document.getElementById('bankAccountSelect').value || null;

    paymentLines.push({
        methodId: activePaymentMethod.id,
        methodName: activePaymentMethod.name,
        methodType: activePaymentMethod.type,
        requiresConfirmation: activePaymentMethod.requiresConfirmation,
        amount,
        referenceNumber: refNum || null,
        bankAccountId,
        passengerId: activePaymentMethod.type === 'CHARGE' ? selectedCustomerId : null
    });

    document.getElementById('paymentEntryRow').style.display = 'none';
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active-payment'));
    activePaymentMethod = null;
    selectedCustomerId = null;
    renderPaymentLines();
}

function removePaymentLine(idx) {
    paymentLines.splice(idx, 1);
    renderPaymentLines();
}

function renderPaymentLines() {
    const list = document.getElementById('paymentLinesList');
    const totals = document.getElementById('paymentTotals');
    const confirmSection = document.getElementById('confirmOrderSection');
    const total = getCartTotal();
    const paid = paymentLines.reduce((s, p) => s + p.amount, 0);
    const change = paid - total;

    if (paymentLines.length === 0) {
        list.innerHTML = '<p class="text-muted small mb-0">No payment lines yet. Select a method above.</p>';
        totals.style.display = 'none';
        confirmSection.style.display = 'none';
        return;
    }

    let html = '<div class="mb-2">';
    paymentLines.forEach((p, idx) => {
        html += `<div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <div>
                <span class="fas fa-check-circle text-success me-1"></span>
                <strong>${p.methodName}</strong>
                ${p.referenceNumber ? `<span class="text-muted small ms-2">Ref: ${p.referenceNumber}</span>` : ''}
                ${p.requiresConfirmation ? '<span class="badge bg-soft-warning text-warning ms-1 small">Needs Confirm</span>' : ''}
            </div>
            <div class="d-flex align-items-center gap-2">
                <strong class="text-success">₱${fmt(p.amount)}</strong>
                <button class="btn btn-sm btn-outline-danger p-1" style="line-height:1;" onclick="removePaymentLine(${idx})">
                    <span class="fas fa-times"></span>
                </button>
            </div>
        </div>`;
    });
    html += '</div>';
    list.innerHTML = html;

    totals.style.display = '';
    document.getElementById('ptTotalDue').textContent = '₱' + fmt(total);
    document.getElementById('ptTotalPaid').textContent = '₱' + fmt(paid);
    const changeEl = document.getElementById('ptChange');
    changeEl.textContent = (change < 0 ? '-₱' : '₱') + fmt(Math.abs(change));
    changeEl.className = change >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';

    confirmSection.style.display = paid >= total ? '' : 'none';
}

function computeChange() {
    const paid = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const total = getCartTotal();
    const totalPaidSoFar = paymentLines.reduce((s, p) => s + p.amount, 0);
    const remaining = total - totalPaidSoFar - paid;
    const hint = document.getElementById('paymentAmountHint');
    if (hint) {
        hint.textContent = remaining > 0.005
            ? `Still needed: ₱${fmt(remaining)}`
            : paid > (total - totalPaidSoFar) + 0.005
                ? `Change: ₱${fmt(paid - (total - totalPaidSoFar))}`
                : 'Amount covers balance';
        hint.className = 'form-text ' + (remaining > 0.005 ? 'text-danger' : 'text-success');
    }
}

function backToCart() {
    document.getElementById('confirmOrderSection').style.display = 'none';
    document.getElementById('paymentSection').style.display = '';
}

// =============================================
// CONFIRM ORDER
// =============================================

async function confirmOrder() {
    if (!window.POS_HAS_SESSION) {
        showToast('danger', 'No Session', 'Open a session first.'); return;
    }
    if (cart.length === 0) {
        showToast('danger', 'Cart Empty', 'Add items first.'); return;
    }
    const total = getCartTotal();
    const paid = paymentLines.reduce((s, p) => s + p.amount, 0);
    if (paid < total) {
        showToast('danger', 'Insufficient Payment', 'Total paid is less than the amount due.'); return;
    }

    const btn = document.getElementById('confirmOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Processing...';

    // Check if cart has ticket
    const hasTicket = cart.some(item => item.type === 'ticket');
    const apiUrl = hasTicket ? `${window.BASE_URL}/api/pos/tickets` : `${window.BASE_URL}/api/pos/transactions`;

    let payload;
    if (hasTicket) {
        const ticket = cart.find(item => item.type === 'ticket');
        const services = cart.filter(item => item.type === 'service');
        payload = {
            session_id: window.POS_SESSION_ID,
            branch_id: window.POS_BRANCH_ID,
            ticket: {
                passenger_id: ticket.passengerId,
                origin: ticket.origin,
                destination: ticket.destination,
                travel_date: ticket.travelDate,
                base_amount: ticket.baseAmount,
                service_fee: ticket.serviceFee,
                discount_amount: ticket.discount,
                total_amount: ticket.total
            },
            services: services.map(s => ({
                service_type_id: s.serviceTypeId,
                description: s.description || null,
                quantity: s.qty,
                unit_price: s.unitPrice,
                total_amount: s.total
            })),
            payments: paymentLines.map(p => ({
                payment_method_id: p.methodId,
                bank_account_id: p.bankAccountId || null,
                amount: p.amount,
                reference_number: p.referenceNumber || null,
                passenger_id: p.passengerId || null
            })),
            change_amount: paid - total
        };
    } else {
        payload = {
            session_id: window.POS_SESSION_ID,
            branch_id: window.POS_BRANCH_ID,
            items: cart.map(i => ({
                service_type_id: i.serviceTypeId,
                description: i.description || null,
                quantity: i.qty,
                unit_price: i.unitPrice,
                total_amount: i.total
            })),
            payments: paymentLines.map(p => ({
                payment_method_id: p.methodId,
                bank_account_id: p.bankAccountId || null,
                amount: p.amount,
                reference_number: p.referenceNumber || null,
                passenger_id: p.passengerId || null
            })),
            change_amount: paid - total
        };
    }

    try {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            showToast('success', 'Transaction Complete!',
                `Receipt #${result.transaction_code} processed. Change: ₱${fmt(paid - total)}`);
            cart = [];
            paymentLines = [];
            ticketInCart = null;
            renderCart();
            renderPaymentLines();
            document.getElementById('paymentSection').style.display = 'none';
            document.getElementById('confirmOrderSection').style.display = 'none';
            document.getElementById('itemEntryCard').style.display = 'none';
            document.getElementById('serviceAddonsSection').style.display = 'none';
        } else {
            showToast('danger', 'Transaction Failed', result.error || 'Unknown error.');
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="fas fa-check-circle me-2"></span>Confirm & Process';
    }
}

// =============================================
// TOAST
// =============================================

function showToast(type, title, message) {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());
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
        </div>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 150); }, 5000);
}
