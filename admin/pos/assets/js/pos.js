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
let openSessionModal, closeSessionModal, selectCustomerModal, addPassengerModal, viewPassengerModal, switchTypeModal, paymentModal, itemEntryModal, clearCartModal;
let selectedCustomerId = null;
let transactionType = localStorage.getItem('posTransactionType') || 'ticket'; // 'ticket' or 'service'
let ticketInCart = null; // Store the ticket object if in cart
let currentPassengerStep = 1;
let totalPassengerSteps = 2;
let viewPassengerStep = 1;
let totalViewPassengerSteps = 2;
let pendingTransactionType = null; // Store pending transaction type for confirmation

// Load cart from localStorage on page load
function loadCartFromStorage() {
    try {
        const savedCart = localStorage.getItem('posCart');
        const savedTicketInCart = localStorage.getItem('posTicketInCart');
        if (savedCart) {
            cart = JSON.parse(savedCart);
        }
        if (savedTicketInCart) {
            ticketInCart = JSON.parse(savedTicketInCart);
        }
    } catch (e) {
        console.error('Error loading cart from storage:', e);
    }
}

// Save cart to localStorage
function saveCartToStorage() {
    try {
        localStorage.setItem('posCart', JSON.stringify(cart));
        localStorage.setItem('posTicketInCart', JSON.stringify(ticketInCart));
    } catch (e) {
        console.error('Error saving cart to storage:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    openSessionModal  = new bootstrap.Modal(document.getElementById('openSessionModal'));
    closeSessionModal = new bootstrap.Modal(document.getElementById('closeSessionModal'));
    selectCustomerModal = new bootstrap.Modal(document.getElementById('selectCustomerModal'));
    addPassengerModal = new bootstrap.Modal(document.getElementById('addPassengerModal'));
    viewPassengerModal = new bootstrap.Modal(document.getElementById('viewPassengerModal'));
    switchTypeModal = new bootstrap.Modal(document.getElementById('switchTypeModal'));
    paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    itemEntryModal = new bootstrap.Modal(document.getElementById('itemEntryModal'));
    clearCartModal = new bootstrap.Modal(document.getElementById('clearCartModal'));

    // Toggle order summary collapse icon
    const orderSummaryCollapse = document.getElementById('paymentCartItemsCollapse');
    const orderSummaryToggleIcon = document.getElementById('orderSummaryToggleIcon');
    if (orderSummaryCollapse && orderSummaryToggleIcon) {
        orderSummaryCollapse.addEventListener('show.bs.collapse', () => {
            orderSummaryToggleIcon.classList.remove('fa-chevron-down');
            orderSummaryToggleIcon.classList.add('fa-chevron-up');
        });
        orderSummaryCollapse.addEventListener('hide.bs.collapse', () => {
            orderSummaryToggleIcon.classList.remove('fa-chevron-up');
            orderSummaryToggleIcon.classList.add('fa-chevron-down');
        });
    }

    // Toggle session banner collapse icon
    const sessionBannerCollapse = document.getElementById('sessionBannerCollapse');
    const sessionBannerToggleIcon = document.getElementById('sessionBannerToggleIcon');
    if (sessionBannerCollapse && sessionBannerToggleIcon) {
        sessionBannerCollapse.addEventListener('show.bs.collapse', () => {
            sessionBannerToggleIcon.classList.remove('fa-chevron-down');
            sessionBannerToggleIcon.classList.add('fa-chevron-up');
        });
        sessionBannerCollapse.addEventListener('hide.bs.collapse', () => {
            sessionBannerToggleIcon.classList.remove('fa-chevron-up');
            sessionBannerToggleIcon.classList.add('fa-chevron-down');
        });
    }

    // Load cart from localStorage
    loadCartFromStorage();
    if (cart.length > 0) {
        renderCart();
    }

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

    // Auto-convert fullname to title case on input
    if (fullname) {
        fullname.addEventListener('input', function() {
            const toTitleCase = (str) => {
                if (!str) return '';
                return str.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
            };
            // Get current cursor position
            const start = this.selectionStart;
            const end = this.selectionEnd;
            // Convert to title case
            this.value = toTitleCase(this.value);
            // Restore cursor position
            this.setSelectionRange(start, end);
            
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
                // Cart empty check - no toast to avoid distraction
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
            const paymentEntry = document.getElementById('paymentEntryRow');
            if (itemEntryModal._isShown) {
                cancelItemEntry();
            } else if (paymentEntry && paymentEntry.style.display !== 'none') {
                cancelPaymentEntry();
            }
        }

        // Enter - Add item or payment
        if (e.key === 'Enter' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            const paymentEntry = document.getElementById('paymentEntryRow');
            if (itemEntryModal._isShown) {
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
        // Long session warning - commented out to avoid distraction
            // showToast('warning', 'Long Session Warning',
            //     `Your cashier session has been open for ${hours} hour(s). Consider closing it to reconcile cash.`);
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
    
    console.log('API URL:', url);
    
    // Hide dropdown initially
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    
    // Fetch passengers via AJAX
    fetch(url)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Passenger search results:', data);
            
            if (!data.success || !data.data || data.data.length === 0) {
                // Hide dropdown if no results
                dropdown.style.display = 'none';
                console.log('No results found');
                return;
            }

            // Build dropdown items with more details
            data.data.forEach((p, index) => {
                const item = document.createElement('div');
                item.className = 'dropdown-item passenger-dropdown-item py-2';
                item.dataset.passengerId = p.passenger_id;
                item.dataset.index = index;
                
                // Helper function to capitalize first letter of each word
                const toTitleCase = (str) => {
                    if (!str) return '';
                    return str.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
                };
                
                // Build address string from available fields (use names instead of codes)
                const addressParts = [];
                if (p.street_address) addressParts.push(toTitleCase(p.street_address));
                if (p.barangay_name) addressParts.push(toTitleCase(p.barangay_name));
                if (p.city_municipality_name) addressParts.push(toTitleCase(p.city_municipality_name));
                if (p.province_name) addressParts.push(toTitleCase(p.province_name));
                const address = addressParts.length > 0 ? addressParts.join(', ') : 'No address on file';
                
                // Gender badge class with better visibility
                const genderBadgeClass = p.gender === 'male' ? 'bg-primary' : 
                                       p.gender === 'female' ? 'bg-danger' : 'bg-secondary';
                const genderIcon = p.gender === 'male' ? 'fa-mars' : p.gender === 'female' ? 'fa-venus' : 'fa-genderless';
                const genderBadge = p.gender ? `<span class="badge ${genderBadgeClass} ms-2"><span class="fas ${genderIcon} me-1"></span>${p.gender.charAt(0).toUpperCase() + p.gender.slice(1).toLowerCase()}</span>` : '';
                
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="fw-bold text-primary mb-1">${toTitleCase(p.fullname)}</div>
                            <div class="text-muted small mb-1">
                                <span class="fas fa-phone-alt me-1"></span>${p.mobile_number || 'No mobile number'}
                            </div>
                            <div class="text-muted small">
                                <span class="fas fa-map-marker-alt me-1"></span>${address}
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            ${genderBadge}
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPassenger('${p.passenger_id}', event)" title="View/Edit Passenger" style="min-width: 32px; height: 32px; padding: 4px;">
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
            console.log('Dropdown shown with', data.data.length, 'results');
            
            // Set first item as active for keyboard navigation
            currentActiveIndex = 0;
            updateActiveItem();
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
    
    // Helper function to capitalize first letter of each word
    const toTitleCase = (str) => {
        if (!str) return '';
        return str.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
    };
    
    // Set values - show only fullname in search input
    searchInput.value = toTitleCase(passenger.fullname);
    hiddenInput.value = passenger.passenger_id;
    
    // Hide dropdown
    dropdown.style.display = 'none';
    
    // Display selected passenger details in a separate area
    const passengerDetailsDiv = document.getElementById('selectedPassengerDetails');
    if (!passengerDetailsDiv) {
        // Create passenger details display area if it doesn't exist
        const detailsContainer = document.createElement('div');
        detailsContainer.id = 'selectedPassengerDetails';
        detailsContainer.className = 'card mb-3 border-0 shadow-sm';
        detailsContainer.innerHTML = `
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-primary mb-1" id="selectedPassengerName"></div>
                        <div class="text-muted small mb-1" id="selectedPassengerMobile"></div>
                        <div class="text-muted small" id="selectedPassengerAddress"></div>
                    </div>
                    <button class="btn btn-sm btn-outline-info" onclick="viewPassenger('${passenger.passenger_id}', event)">
                        <span class="fas fa-info-circle me-1"></span>View More Details
                    </button>
                </div>
            </div>
        `;
        // Insert after the passenger search input container
        const searchContainer = searchInput.closest('.col-md-6').parentElement;
        searchContainer.insertAdjacentElement('afterend', detailsContainer);
    }
    
    // Update passenger details display
    document.getElementById('selectedPassengerName').textContent = toTitleCase(passenger.fullname);
    document.getElementById('selectedPassengerMobile').innerHTML = `<span class="fas fa-phone-alt me-1"></span>${passenger.mobile_number || 'No mobile number'}`;
    
    // Build address string
    const addressParts = [];
    if (passenger.street_address) addressParts.push(toTitleCase(passenger.street_address));
    if (passenger.barangay_name) addressParts.push(toTitleCase(passenger.barangay_name));
    if (passenger.city_municipality_name) addressParts.push(toTitleCase(passenger.city_municipality_name));
    if (passenger.province_name) addressParts.push(toTitleCase(passenger.province_name));
    const address = addressParts.length > 0 ? addressParts.join(', ') : 'No address on file';
    document.getElementById('selectedPassengerAddress').innerHTML = `<span class="fas fa-map-marker-alt me-1"></span>${address}`;
    
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
    console.log('Populating passenger form with data:', passenger);
    
    document.getElementById('viewPassengerId').value = passenger.passenger_id;
    document.getElementById('viewPassengerFullname').value = passenger.fullname || '';
    document.getElementById('viewPassengerMobile').value = passenger.mobile_number || '';
    document.getElementById('viewPassengerEmail').value = passenger.email || '';
    document.getElementById('viewPassengerGender').value = passenger.gender || '';
    document.getElementById('viewPassengerBirthDate').value = passenger.birth_date || '';
    document.getElementById('viewPassengerStreetAddress').value = passenger.street_address || '';
    document.getElementById('viewPassengerNotes').value = passenger.notes || '';
    
    // Set address field values directly
    document.getElementById('viewPassengerRegion').value = passenger.region_code || '';
    document.getElementById('viewPassengerProvince').value = passenger.province_code || '';
    document.getElementById('viewPassengerCity').value = passenger.city_municipality_code || '';
    document.getElementById('viewPassengerBarangay').value = passenger.barangay_code || '';
    
    console.log('Address values set:', {
        region: passenger.region_code,
        province: passenger.province_code,
        city: passenger.city_municipality_code,
        barangay: passenger.barangay_code
    });
    
    // Load dropdowns in background after setting values
    if (passenger.region_code) {
        loadViewProvinces();
    }
    if (passenger.province_code) {
        loadViewCities();
    }
    if (passenger.city_municipality_code) {
        loadViewBarangays();
    }
    
    console.log('Address fields populated');
    
    // Display timestamps
    document.getElementById('viewPassengerCreatedAt').textContent = passenger.created_at ? formatDate(passenger.created_at) : '-';
    document.getElementById('viewPassengerUpdatedAt').textContent = passenger.updated_at ? formatDate(passenger.updated_at) : '-';
    document.getElementById('viewPassengerCreatedBy').textContent = passenger.created_by_name || '-';
    
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
        return Promise.resolve();
    }
    
    return fetch(`${window.BASE_URL}/api/psgc?action=provinces&region_code=${regionCode}`)
        .then(response => response.json())
        .then(data => {
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            if (data.success && data.data.provinces) {
                data.data.provinces.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.province_code;
                    option.textContent = p.province_name;
                    provinceSelect.appendChild(option);
                });
                provinceSelect.disabled = false;
            }
            
            // Restore selected value
            const current = document.getElementById('viewPassengerProvince').dataset.current;
            if (current) provinceSelect.value = current;
        })
        .catch(error => {
            console.error('Error loading provinces:', error);
            throw error;
        });
}

function loadViewCities() {
    const provinceCode = document.getElementById('viewPassengerProvince').value;
    const citySelect = document.getElementById('viewPassengerCity');
    
    if (!provinceCode) {
        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        citySelect.disabled = true;
        return Promise.resolve();
    }
    
    return fetch(`${window.BASE_URL}/api/psgc?action=cities&province_code=${provinceCode}`)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            if (data.success && data.data.cities) {
                data.data.cities.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.city_municipality_code;
                    option.textContent = c.city_municipality_name;
                    citySelect.appendChild(option);
                });
                citySelect.disabled = false;
            }
            
            // Restore selected value
            const current = document.getElementById('viewPassengerCity').dataset.current;
            if (current) citySelect.value = current;
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            throw error;
        });
}

function loadViewBarangays() {
    const cityCode = document.getElementById('viewPassengerCity').value;
    const barangaySelect = document.getElementById('viewPassengerBarangay');
    
    if (!cityCode) {
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        return Promise.resolve();
    }
    
    return fetch(`${window.BASE_URL}/api/psgc?action=barangays&city_code=${cityCode}`)
        .then(response => response.json())
        .then(data => {
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (data.success && data.data.barangays) {
                data.data.barangays.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.barangay_code;
                    option.textContent = b.barangay_name;
                    barangaySelect.appendChild(option);
                });
                barangaySelect.disabled = false;
            }
            
            // Restore selected value
            const current = document.getElementById('viewPassengerBarangay').dataset.current;
            if (current) barangaySelect.value = current;
        })
        .catch(error => {
            console.error('Error loading barangays:', error);
            throw error;
        });
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
                baseAmountDisplay.textContent = currentBaseAmount > 0 ? '₱' + currentBaseAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00';
                
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
    console.log('Searching for passenger:', searchTerm, 'Length:', searchTerm.length);
    
    // Clear passenger details if search is empty
    if (!searchTerm || searchTerm.trim() === '') {
        const passengerDetailsDiv = document.getElementById('selectedPassengerDetails');
        if (passengerDetailsDiv) {
            passengerDetailsDiv.remove();
        }
        const hiddenInput = document.getElementById('ticketPassenger');
        hiddenInput.value = '';
        console.log('Search empty, clearing details');
        return;
    }
    
    // Only search if at least 2 characters are typed
    if (searchTerm.length < 2) {
        const dropdown = document.getElementById('ticketPassengerDropdown');
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        console.log('Search term too short:', searchTerm.length);
        return;
    }
    
    // Reset active index when searching
    currentActiveIndex = -1;
    // Real-time search with minimal delay (100ms for better UX)
    clearTimeout(window.ticketPassengerSearchTimeout);
    window.ticketPassengerSearchTimeout = setTimeout(() => {
        console.log('Executing search for:', searchTerm);
        renderTicketPassengers(searchTerm);
    }, 100);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('ticketPassengerDropdown');
    const searchInput = document.getElementById('ticketPassengerSearch');
    if (dropdown && searchInput && !dropdown.contains(e.target) && e.target !== searchInput) {
        dropdown.style.display = 'none';
    }
});

// Add keyboard navigation for passenger dropdown
// Auto-convert fullname to title case on input for view passenger
document.addEventListener('DOMContentLoaded', function() {
    const viewFullnameInput = document.getElementById('viewPassengerFullname');
    if (viewFullnameInput) {
        viewFullnameInput.addEventListener('input', function(e) {
            const toTitleCase = (str) => {
                if (!str) return '';
                return str.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
            };
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = toTitleCase(this.value);
            this.setSelectionRange(start, end);
        });
    }

    const searchInput = document.getElementById('ticketPassengerSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            const dropdown = document.getElementById('ticketPassengerDropdown');
            const items = dropdown.querySelectorAll('.passenger-dropdown-item');
            
            if (dropdown.style.display === 'none' || items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentActiveIndex = Math.min(currentActiveIndex + 1, items.length - 1);
                updateActiveItem();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentActiveIndex = Math.max(currentActiveIndex - 1, 0);
                updateActiveItem();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentActiveIndex >= 0 && currentActiveIndex < items.length) {
                    const activeItem = items[currentActiveIndex];
                    const passengerId = activeItem.dataset.passengerId;
                    // Get passenger data from the items
                    fetch(`${window.BASE_URL}/api/pos/passengers?passenger_id=${passengerId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data) {
                                selectTicketPassenger(data.data);
                            }
                        });
                }
            }
        });
    }
});

function updateActiveItem() {
    const dropdown = document.getElementById('ticketPassengerDropdown');
    const items = dropdown.querySelectorAll('.passenger-dropdown-item');
    
    items.forEach((item, index) => {
        if (index === currentActiveIndex) {
            item.classList.add('active');
            item.style.backgroundColor = 'var(--bs-primary-bg-subtle)';
            // Scroll into view if needed
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('active');
            item.style.backgroundColor = '';
        }
    });
}

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
    document.getElementById('closingCash').value = '';
    document.getElementById('closingNotes').value = '';
    document.getElementById('varianceDisplay').textContent = '₱0.00';
    document.getElementById('varianceDisplay').className = 'fw-bold text-muted';

    // Fetch session summary
    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/sessions?id=${window.POS_SESSION_ID}`);
        const result = await response.json();
        if (result.success) {
            const s = result.data.session;
            const payments = result.data.payments || [];
            const expected = parseFloat(s.expected_cash || 0);
            document.getElementById('openingCash').textContent = '₱' + fmt(s.starting_cash);
            document.getElementById('expectedCash').textContent = '₱' + fmt(expected);
            document.getElementById('totalSales').textContent = '₱' + fmt(s.total_sales || 0);

            const startedDate = new Date(s.started_at);
            const startedFmt = startedDate.toLocaleString('en-PH', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            document.getElementById('closeSummary').innerHTML = `
                <div class="row g-3 align-items-center">
                  <div class="col-md-4">
                    <div class="text-muted small mb-1">Started</div>
                    <div class="fw-bold">${startedFmt}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small mb-1">Opening Cash</div>
                    <div class="fw-bold">₱${fmt(s.starting_cash)}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small mb-1">Transactions</div>
                    <div class="fw-bold">${s.txn_count || 0}</div>
                  </div>
                </div>`;

            // Payment type breakdown (Order Summary UI pattern)
            const paymentTypes = [
                { label: 'Cash', value: s.total_cash, color: 'text-success' },
                { label: 'Bank Transfer', value: s.total_bank_transfer, color: 'text-info' },
                { label: 'E-Wallet', value: s.total_e_wallet, color: 'text-primary' },
                { label: 'Charge', value: s.total_charge, color: 'text-warning' },
                { label: 'Other', value: s.total_other, color: 'text-secondary' }
            ];
            const activePayments = paymentTypes.filter(p => parseFloat(p.value) > 0);

            if (activePayments.length > 0) {
                let html = '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
                html += '<div class="card mb-4"><div class="card-body py-3"><table class="table table-borderless fs-10 mb-0">';
                
                activePayments.forEach((p, index) => {
                    const isLast = index === activePayments.length - 1;
                    const borderClass = !isLast ? 'border-bottom' : '';
                    const ptClass = index === 0 ? 'pt-0' : '';
                    const pbClass = isLast ? 'pb-0' : '';
                    
                    html += `
                      <tr class="${borderClass}">
                        <th class="ps-0 ${ptClass} ${pbClass}">${p.label}
                          <div class="text-400 fw-normal fs-11">${p.color.replace('text-', '').toUpperCase()}</div>
                        </th>
                        <th class="pe-0 text-end ${ptClass} ${pbClass}">₱${fmt(p.value)}</th>
                      </tr>`;
                });
                
                html += `
                      <tr>
                        <th class="ps-0 pb-0">Total</th>
                        <th class="pe-0 text-end pb-0 text-success">₱${fmt(s.total_sales)}</th>
                      </tr>
                    </table>
                  </div>
                </div>`;
                document.getElementById('paymentBreakdownSection').innerHTML = html;
            } else {
                document.getElementById('paymentBreakdownSection').innerHTML = '';
            }
        }
    } catch (e) {}

    closeSessionModal.show();

    // Focus on closing cash field after modal is fully shown using Bootstrap event
    const modalElement = document.getElementById('closeSessionModal');
    modalElement.addEventListener('shown.bs.modal', function focusClosingCash() {
        const closingCashField = document.getElementById('closingCash');
        closingCashField.focus();
        closingCashField.select();
        modalElement.removeEventListener('shown.bs.modal', focusClosingCash);
    });
}

function computeVariance() {
    const input = document.getElementById('closingCash');
    let value = input.value;

    // Remove all non-numeric characters except commas and decimal point
    value = value.replace(/[^0-9.,]/g, '');

    // Remove commas for calculation
    const numericValue = value.replace(/,/g, '');
    if (numericValue === '') numericValue = '0';

    // Format with commas for display
    const formatted = numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    input.value = formatted;

    const actual = parseFloat(numericValue) || 0;
    const expectedText = document.getElementById('expectedCash').textContent.replace(/[₱,]/g, '');
    const expected = parseFloat(expectedText) || 0;
    const variance = actual - expected;
    const display = document.getElementById('varianceDisplay');
    display.textContent = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
    display.className = 'fw-bold ' + (Math.abs(variance) < 0.01 ? 'text-success' : variance < 0 ? 'text-danger' : 'text-warning');
}

async function submitCloseSession() {
    const closingCash = parseFloat(document.getElementById('closingCash').value.replace(/,/g, '')) || 0;
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
    
    // For transactions tab, switch directly without checking cart
    if (type === 'transaction') {
        transactionType = type;
        localStorage.setItem('posTransactionType', type);
        updateTransactionTypeUI();
        // Clear filters and load transactions on page 1
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('filterStatus').value = '';
        const dateInput = document.getElementById('filterDate');
        if (dateInput._flatpickr) {
            dateInput._flatpickr.setDate('today');
        }
        loadRecentTransactions(1);
        return;
    }
    
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
    document.getElementById('btnTransactionType').classList.toggle('active', transactionType === 'transaction');
    document.getElementById('ticketSection').style.display = transactionType === 'ticket' ? '' : 'none';
    document.getElementById('serviceSection').style.display = transactionType === 'service' ? '' : 'none';
    document.getElementById('transactionSection').style.display = transactionType === 'transaction' ? '' : 'none';
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

        // Validate mobile (optional but must be valid format if provided)
        if (mobile.value.trim()) {
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
        } else {
            mobile.classList.remove('is-invalid');
            mobile.classList.remove('is-valid');
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

    // Validate mobile (optional but must be valid format if provided)
    if (mobile.value.trim()) {
        const isValidFormat = /^09[0-9]{9}$/.test(mobile.value);
        if (isValidFormat) {
            mobile.classList.remove('is-invalid');
            mobile.classList.add('is-valid');
        } else {
            mobile.classList.add('is-invalid');
            mobile.classList.remove('is-valid');
            isValid = false;
        }
    } else {
        mobile.classList.remove('is-invalid');
        mobile.classList.remove('is-valid');
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

    const formData = {
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
        notes
    };
    
    console.log('Saving passenger with data:', formData);

    try {
        const res = await fetch(`${window.BASE_URL}/api/pos/passengers`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await res.json();
        console.log('Save passenger result:', result);
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
    // const origin = document.getElementById('ticketOrigin').value.trim();
    // const destination = document.getElementById('ticketDestination').value.trim();
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value) || 0;
    const serviceFee = parseFloat(document.getElementById('ticketServiceFee').value) || 0;
    const discount = parseFloat(document.getElementById('ticketDiscount').value) || 0;
    const total = baseAmount + serviceFee - discount;
    
    // Get wallet and branch info
    const walletSelect = document.getElementById('ticketWallet');
    const walletId = walletSelect.value;
    const walletOption = walletSelect.selectedOptions[0];
    const walletBranchId = walletOption ? walletOption.dataset.branchId : null;
    const walletProviderId = walletOption ? walletOption.dataset.providerId : null;

    if (!passengerId) { showToast('danger', 'Error', 'Please select a passenger.'); return; }
    if (!travelDate) { showToast('danger', 'Error', 'Please enter travel date.'); return; }
    if (!walletId) { showToast('danger', 'Error', 'Please select a wallet.'); return; }
    // if (!origin) { showToast('danger', 'Error', 'Please enter origin.'); return; }
    // if (!destination) { showToast('danger', 'Error', 'Please enter destination.'); return; }
    if (total <= 0) { showToast('danger', 'Error', 'Ticket total must be greater than 0.'); return; }

    // Get passenger name from search input
    const passengerName = document.getElementById('ticketPassengerSearch').value.trim();

    ticketInCart = {
        type: 'ticket',
        passengerId,
        passengerName,
        travelDate,
        origin: null,
        destination: null,
        baseAmount,
        serviceFee,
        discount,
        total,
        walletId,
        branchId: walletBranchId,
        providerId: walletProviderId
    };

    cart = [ticketInCart]; // Replace cart with ticket
    document.getElementById('serviceAddonsSection').style.display = '';
    saveCartToStorage();
    renderCart();
    // Ticket added - no toast to avoid distraction
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

    itemEntryModal.show();

    // Focus on qty field after modal is fully shown using Bootstrap event
    const modalElement = document.getElementById('itemEntryModal');
    modalElement.addEventListener('shown.bs.modal', function focusQty() {
        const qtyField = document.getElementById('itemQty');
        qtyField.focus();
        qtyField.select();
        modalElement.removeEventListener('shown.bs.modal', focusQty);
    });
}

function quickAddServiceToCart(id, name, defaultAmount, allowCustom, requiresWallet) {
    if (!window.POS_HAS_SESSION) {
        showToast('warning', 'No Session', 'Please open a cashier session first.');
        openSessionModal.show();
        return;
    }

    const qty = 1;
    const unitPrice = parseFloat(defaultAmount || 0);
    const total = qty * unitPrice;

    const serviceItem = {
        type: 'service',
        serviceTypeId: id,
        serviceName: name,
        requiresWallet: requiresWallet,
        qty,
        unitPrice,
        total,
        description: ''
    };

    cart.push(serviceItem);
    saveCartToStorage();
    renderCart();
    // Service added - no toast to avoid distraction
}

function cancelItemEntry() {
    itemEntryModal.hide();
    activeServiceType = null;
    document.getElementById('itemDescription').value = '';
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

    itemEntryModal.hide();
    document.getElementById('itemDescription').value = '';
    activeServiceType = null;
    saveCartToStorage();
    renderCart();
    // Service item added - no toast to avoid distraction
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
    const payBtn = document.getElementById('payBtn');

    if (cart.length === 0) {
        emptyMsg.style.display = '';
        list.innerHTML = '';
        clearBtn.style.display = 'none';
        payBtn.disabled = true;
        ticketInCart = null;
        document.getElementById('serviceAddonsSection').style.display = 'none';
        document.getElementById('cartSubtotal').textContent = '₱0.00';
        document.getElementById('cartTotal').textContent = '₱0.00';
        return;
    }

    emptyMsg.style.display = 'none';
    clearBtn.style.display = '';
    payBtn.disabled = false;

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
    saveCartToStorage();
    renderCart();
    renderPaymentLines();
    paymentModal.hide();
}

function clearCart() {
    if (cart.length === 0) return;
    clearCartModal.show();
}

function confirmClearCart() {
    cart = [];
    paymentLines = [];
    ticketInCart = null;
    activeServiceType = null;
    saveCartToStorage();
    renderCart();
    renderPaymentLines();
    paymentModal.hide();
    document.getElementById('serviceAddonsSection').style.display = 'none';
    clearCartModal.hide();
    // Cart cleared - no toast to avoid distraction
}

// =============================================
// PAYMENT
// =============================================

function getCartTotal() {
    return cart.reduce((s, i) => s + i.total, 0);
}

function proceedToPayment() {
    if (cart.length === 0) { return; }
    paymentLines = [];
    renderPaymentLines();
    populatePaymentModalCart();
    paymentModal.show();
}

function populatePaymentModalCart() {
    const cartItemsContainer = document.getElementById('paymentCartItems');
    const totalDueElement = document.getElementById('paymentTotalDue');
    
    let html = '';
    let total = 0;
    
    cart.forEach((item, idx) => {
        total += item.total;
        if (item.type === 'ticket') {
            html += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold text-primary">
                            <span class="fas fa-ticket-alt me-2"></span>${item.passengerName}
                        </div>
                        <div class="text-muted small">
                            ${item.travelDate} • Base: ₱${fmt(item.baseAmount)}${item.serviceFee > 0 ? ' + Fee: ₱' + fmt(item.serviceFee) : ''}${item.discount > 0 ? ' - Discount: ₱' + fmt(item.discount) : ''}
                        </div>
                    </div>
                    <div class="fw-bold">₱${fmt(item.total)}</div>
                </div>
            `;
        } else {
            html += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold">
                            <span class="fas fa-concierge-bell me-2 text-success"></span>${item.serviceName || item.name || 'Service'}
                        </div>
                        <div class="text-muted small">
                            Qty: ${item.qty} @ ₱${fmt(item.unitPrice)}${item.description ? ' • ' + item.description : ''}
                        </div>
                    </div>
                    <div class="fw-bold">₱${fmt(item.total)}</div>
                </div>
            `;
        }
    });
    
    cartItemsContainer.innerHTML = html;
    totalDueElement.textContent = '₱' + fmt(total);
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

    // Adjust column widths for Bank Transfer to fit in one row
    const hasExtraField = activePaymentMethod.requiresReference || (activePaymentMethod.type === 'BANK_TRANSFER' || activePaymentMethod.type === 'E_WALLET');
    const paymentEntryRow = document.querySelector('#paymentEntryRow .row');
    const cols = paymentEntryRow.querySelectorAll('.col-md-4');
    cols.forEach(col => {
        if (hasExtraField) {
            col.classList.remove('col-md-4');
            col.classList.add('col-md-3');
        } else {
            col.classList.remove('col-md-3');
            col.classList.add('col-md-4');
        }
    });

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

    // If payment method requires customer, open customer selection modal
    if (activePaymentMethod.requiresCustomer) {
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
    const total = getCartTotal();
    const paid = paymentLines.reduce((s, p) => s + p.amount, 0);
    const change = paid - total;

    if (paymentLines.length === 0) {
        list.innerHTML = '<p class="text-muted small mb-0">No payment lines yet. Select a method above.</p>';
        totals.style.display = 'none';
        return;
    }

    let html = '<div class="list-group">';
    paymentLines.forEach((p, idx) => {
        html += `<div class="list-group-item payment-line-item d-flex justify-content-between align-items-center py-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fas fa-check-circle text-success"></span>
                <div>
                    <span class="fw-bold">${p.methodName}</span>
                    ${p.referenceNumber ? `<span class="text-muted small ms-2">Ref: ${p.referenceNumber}</span>` : ''}
                    ${p.requiresConfirmation ? '<span class="badge bg-soft-warning text-warning ms-2 small">Needs Confirm</span>' : ''}
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold text-success">₱${fmt(p.amount)}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="removePaymentLine(${idx})">
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
    paymentModal.hide();
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
                total_amount: ticket.total,
                wallet_id: ticket.walletId
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
            saveCartToStorage();
            renderCart();
            renderPaymentLines();
            paymentModal.hide();
            itemEntryModal.hide();
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
    toast.style.cssText = 'bottom: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 450px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px;';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <span class="fas ${icon} me-2 fs-5"></span>
            <div>
                <strong class="alert-heading">${title}</strong>
                <div class="small">${message}</div>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 3000);
}

function toggleSessionBanner() {
    const collapseElement = document.getElementById('sessionBannerCollapse');
    const collapseInstance = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement);
    collapseInstance.toggle();
    
    const toggleIcon = document.getElementById('sessionBannerToggleIcon');
    if (toggleIcon) {
        toggleIcon.classList.toggle('fa-chevron-down');
        toggleIcon.classList.toggle('fa-chevron-up');
    }
}

// =============================================
// RECENT TRANSACTIONS
// =============================================

let allTransactions = [];
let currentPage = 1;
let itemsPerPage = 10;
let totalPages = 1;
let totalItems = 0;

function loadRecentTransactions(page = 1) {
    currentPage = page;
    const list = document.getElementById('recentTransactionsList');
    list.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading transactions...</td></tr>';
    
    // Get filter values
    const search = document.getElementById('filterSearch').value.trim();
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const dateValue = document.getElementById('filterDate').value;
    
    // Save filter values to localStorage
    localStorage.setItem('pos_filter_search', search);
    localStorage.setItem('pos_filter_type', type);
    localStorage.setItem('pos_filter_status', status);
    localStorage.setItem('pos_filter_date', dateValue);
    
    // Calculate offset
    const offset = (currentPage - 1) * itemsPerPage;
    
    // Build query parameters
    const params = new URLSearchParams({ limit: itemsPerPage, offset: offset });
    if (search) params.append('search', search);
    if (type) params.append('type', type);
    if (status) params.append('status', status);
    
    // Handle date range (flatpickr returns "YYYY-MM-DD to YYYY-MM-DD" format)
    if (dateValue && dateValue.includes(' to ')) {
        const [startDate, endDate] = dateValue.split(' to ');
        params.append('start_date', startDate);
        params.append('end_date', endDate);
    } else if (dateValue) {
        params.append('date', dateValue);
    }
    
    fetch(`${window.BASE_URL}/api/pos/recent-transactions.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                allTransactions = data.data.transactions || [];
                const pagination = data.data.pagination || {};
                totalItems = pagination.total || 0;
                totalPages = pagination.total_pages || 1;
                currentPage = pagination.current_page || 1;
                
                renderTransactionsTable(allTransactions);
                updatePaginationUI();
            } else {
                list.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No transactions found.</td></tr>';
                updatePaginationUI();
            }
        })
        .catch(error => {
            console.error('Error loading transactions:', error);
            list.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Error loading transactions.</td></tr>';
        });
}

function renderTransactionsTable(transactions) {
    const list = document.getElementById('recentTransactionsList');
    
    if (transactions.length === 0) {
        list.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No transactions found.</td></tr>';
        return;
    }
    
    let html = '';
    transactions.forEach(txn => {
        const statusBadge = txn.status === 'booked' || txn.status === 'completed' 
            ? '<span class="badge bg-soft-success text-success">Active</span>'
            : txn.status === 'cancelled' 
                ? '<span class="badge bg-soft-danger text-danger">Cancelled</span>'
                : `<span class="badge bg-soft-secondary text-secondary">${txn.status}</span>`;
        
        const typeIcon = txn.transaction_type === 'TICKET' ? 'fa-ticket-alt text-primary' : 'fa-concierge-bell text-success';
        const passengerName = txn.passenger_name ? txn.passenger_name.charAt(0).toUpperCase() + txn.passenger_name.slice(1).toLowerCase() : '-';
        const branchName = txn.branch_name ? `<span class="badge bg-soft-primary text-primary">${txn.branch_name}</span>` : '-';
        const providerName = txn.provider_name ? (txn.provider_type ? 
            `<div>${txn.provider_name}</div><div><span class="badge bg-soft-info text-info" style="font-size: 0.75em;">${txn.provider_type}</span></div>` : 
            txn.provider_name) : '-';
        const travelDate = txn.travel_date ? new Date(txn.travel_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
        const originDest = (txn.origin && txn.destination) ? `${txn.origin} → ${txn.destination}` : '-';
        const typeBadge = txn.transaction_type === 'TICKET' 
            ? '<span class="badge bg-soft-primary text-primary">TICKET</span>' 
            : '<span class="badge bg-soft-success text-success">SERVICE</span>';
        
        html += `
            <tr>
                <td>
                    <div><strong>${txn.transaction_code}</strong></div>
                    <div>${typeBadge}</div>
                </td>
                <td class="small">${passengerName}</td>
                <td class="small">${branchName}</td>
                <td class="small">${providerName}</td>
                <td class="small">${travelDate}</td>
                <td class="small">${originDest}</td>
                <td>₱${fmt(txn.total_amount)}</td>
                <td>${statusBadge}</td>
                <td class="small">
                    <div>${new Date(txn.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })}</div>
                    <div class="text-muted" style="font-size: 0.85em;">${new Date(txn.created_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })}</div>
                </td>
                <td class="text-end">
                    ${txn.transaction_type === 'TICKET' && (txn.status === 'booked' || txn.status === 'completed') ? 
                        `<button class="btn btn-sm btn-outline-danger" onclick="openCancelTicketModal('${txn.transaction_code}', ${txn.base_amount}, ${txn.service_fee}, ${JSON.stringify(txn)})">
                            <span class="fas fa-times me-1"></span>Cancel
                        </button>` : 
                        '<span class="text-muted small">N/A</span>'}
                </td>
            </tr>
        `;
    });
    list.innerHTML = html;
}

function updatePaginationUI() {
    const start = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    
    document.getElementById('paginationStart').textContent = start;
    document.getElementById('paginationEnd').textContent = end;
    document.getElementById('paginationTotal').textContent = totalItems;
    document.getElementById('currentPage').textContent = currentPage;
    
    // Update pagination navigation
    const nav = document.getElementById('paginationNav');
    const firstBtn = nav.querySelector('li:nth-child(1)');
    const prevBtn = nav.querySelector('li:nth-child(2)');
    const nextBtn = nav.querySelector('li:nth-child(4)');
    const lastBtn = nav.querySelector('li:nth-child(5)');
    
    firstBtn.classList.toggle('disabled', currentPage === 1);
    prevBtn.classList.toggle('disabled', currentPage === 1);
    nextBtn.classList.toggle('disabled', currentPage === totalPages);
    lastBtn.classList.toggle('disabled', currentPage === totalPages);
}

function changePage(page) {
    if (page === 'prev' && currentPage > 1) {
        loadRecentTransactions(currentPage - 1);
    } else if (page === 'next' && currentPage < totalPages) {
        loadRecentTransactions(currentPage + 1);
    } else if (page === 'last') {
        loadRecentTransactions(totalPages);
    } else if (typeof page === 'number' && page >= 1 && page <= totalPages) {
        loadRecentTransactions(page);
    }
}

function filterTransactions() {
    loadRecentTransactions(1);
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterStatus').value = '';
    const dateInput = document.getElementById('filterDate');
    if (dateInput._flatpickr) {
        dateInput._flatpickr.clear();
    }
    // Clear localStorage filters
    localStorage.removeItem('pos_filter_search');
    localStorage.removeItem('pos_filter_type');
    localStorage.removeItem('pos_filter_status');
    localStorage.removeItem('pos_filter_date');
    loadRecentTransactions(1);
}

// Load recent transactions on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.POS_HAS_SESSION) {
        // Initialize date picker with custom configuration to fix single date display
        const dateInput = document.getElementById('filterDate');
        if (dateInput && window.flatpickr) {
            window.flatpickr(dateInput, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                disableMobile: true,
                position: 'below',
                onChange: function(selectedDates, dateStr, instance) {
                    // Trigger filter transactions when date changes
                    filterTransactions();
                }
            });
        }
        
        // Restore filter values from localStorage
        const savedSearch = localStorage.getItem('pos_filter_search');
        const savedType = localStorage.getItem('pos_filter_type');
        const savedStatus = localStorage.getItem('pos_filter_status');
        const savedDate = localStorage.getItem('pos_filter_date');
        
        if (savedSearch !== null) document.getElementById('filterSearch').value = savedSearch;
        if (savedType !== null) document.getElementById('filterType').value = savedType;
        if (savedStatus !== null) document.getElementById('filterStatus').value = savedStatus;
        
        // Restore date picker value
        if (savedDate) {
            const dateInput = document.getElementById('filterDate');
            if (dateInput._flatpickr) {
                if (savedDate.includes(' to ')) {
                    // It's a range
                    const [startDate, endDate] = savedDate.split(' to ');
                    dateInput._flatpickr.setDate([startDate, endDate]);
                } else {
                    // It's a single date
                    dateInput._flatpickr.setDate(savedDate);
                }
            }
        }
        
        if (transactionType === 'transaction') {
            loadRecentTransactions(1);
        }
    }
});

// =============================================
// TICKET CANCELLATION
// =============================================

let cancelTicketModal;

function openCancelTicketModal(txnCode = '', baseAmount = 0, serviceFee = 0, txnData = null) {
    cancelTicketModal = new bootstrap.Modal(document.getElementById('cancelTicketModal'));
    cancelTicketModal.show();
    
    // Set values if provided
    document.getElementById('cancelTicketCode').value = txnCode;
    document.getElementById('cancelRefundAmount').value = baseAmount > 0 ? baseAmount : '';
    document.getElementById('cancelReason').value = '';
    
    // Display service fee if provided
    const serviceFeeDisplay = document.getElementById('cancelServiceFeeDisplay');
    if (serviceFeeDisplay && serviceFee > 0) {
        serviceFeeDisplay.textContent = `₱${serviceFee.toFixed(2)}`;
        serviceFeeDisplay.style.display = 'inline';
    } else if (serviceFeeDisplay) {
        serviceFeeDisplay.style.display = 'none';
    }
    
    // Display ticket details if provided
    const detailsDiv = document.getElementById('cancelTicketDetails');
    if (detailsDiv && txnData) {
        document.getElementById('cancelPassengerName').textContent = txnData.passenger_name || '-';
        document.getElementById('cancelTravelDate').textContent = txnData.travel_date || '-';
        document.getElementById('cancelRoute').textContent = (txnData.origin && txnData.destination) ? `${txnData.origin} → ${txnData.destination}` : '-';
        document.getElementById('cancelProvider').textContent = txnData.provider_name || '-';
        document.getElementById('cancelBaseAmount').textContent = `₱${(parseFloat(txnData.base_amount) || 0).toFixed(2)}`;
        document.getElementById('cancelServiceFee').textContent = `₱${(parseFloat(txnData.service_fee) || 0).toFixed(2)}`;
        document.getElementById('cancelTotalAmount').textContent = `₱${(parseFloat(txnData.total_amount) || 0).toFixed(2)}`;
        document.getElementById('cancelStatus').textContent = txnData.status || '-';
        
        if (txnData.created_at) {
            const txnDate = new Date(txnData.created_at);
            document.getElementById('cancelTxnDate').textContent = txnDate.toLocaleDateString('en-PH', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } else {
            document.getElementById('cancelTxnDate').textContent = '-';
        }
        
        detailsDiv.style.display = 'block';
    } else if (detailsDiv) {
        detailsDiv.style.display = 'none';
    }
    
    // Disable transaction code if pre-filled
    document.getElementById('cancelTicketCode').readOnly = txnCode !== '';
}

function confirmCancelTicket() {
    const txnCode = document.getElementById('cancelTicketCode').value.trim();
    const refundAmount = parseFloat(document.getElementById('cancelRefundAmount').value) || 0;
    const reason = document.getElementById('cancelReason').value.trim();
    
    if (!txnCode) {
        showToast('danger', 'Error', 'Please enter a transaction code.');
        return;
    }
    
    if (refundAmount <= 0) {
        showToast('danger', 'Error', 'Please enter a valid refund amount.');
        return;
    }
    
    const btn = document.querySelector('#cancelTicketModal .btn-danger');
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Processing...';
    
    fetch(`${window.BASE_URL}/api/pos/ticket-cancel.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            transaction_code: txnCode,
            refund_amount: refundAmount,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span class="fas fa-check me-1"></span>Confirm Cancellation';
        
        if (data.success) {
            showToast('success', 'Success', data.message);
            cancelTicketModal.hide();
            loadRecentTransactions(); // Refresh transactions list
        } else {
            showToast('danger', 'Error', data.error || 'Cancellation failed.');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<span class="fas fa-check me-1"></span>Confirm Cancellation';
        showToast('danger', 'Error', 'An error occurred during cancellation.');
        console.error('Cancel error:', error);
    });
}
