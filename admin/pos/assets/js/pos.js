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
let openSessionModal, closeSessionModal, selectCustomerModal, addPassengerModal, viewPassengerModal, switchTypeModal, paymentModal, itemEntryModal, clearCartModal, cancelTicketModal, reprintReceiptModal;
let selectedCustomerId = null;
let currentReprintTransaction = null;
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
        // Ensure sync: if cart is empty, clear ticketInCart; if cart has ticket, ensure ticketInCart is set
        const hasTicketInCart = cart.some(i => i.type === 'ticket');
        if (cart.length === 0) {
            ticketInCart = null;
        } else if (hasTicketInCart && !ticketInCart) {
            // Cart has ticket but ticketInCart is null - resync
            ticketInCart = cart.find(i => i.type === 'ticket');
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
    const cancelTicketModalElement = document.getElementById('cancelTicketModal');
    if (cancelTicketModalElement) {
        cancelTicketModal = new bootstrap.Modal(cancelTicketModalElement);
    } else {
        console.error('cancelTicketModal element not found');
    }
    reprintReceiptModal = new bootstrap.Modal(document.getElementById('reprintReceiptModal'));

    // Reprint reason dropdown handler
    const reprintReasonSelect = document.getElementById('reprintReason');
    const reprintReasonOtherContainer = document.getElementById('reprintReasonOtherContainer');
    const confirmReprintBtn = document.getElementById('confirmReprintBtn');

    if (reprintReasonSelect) {
        reprintReasonSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                reprintReasonOtherContainer.style.display = 'block';
            } else {
                reprintReasonOtherContainer.style.display = 'none';
            }
            confirmReprintBtn.disabled = !this.value;
        });
    }

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

    // Initialize PosPrinter module if enabled
    if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.enabled && window.PosPrinter) {
        console.log('[POS] Initializing PosPrinter with settings:', window.PRINTER_SETTINGS);
        window.PosPrinter.init({
            config: window.PRINTER_SETTINGS,
            companyInfo: window.COMPANY_INFO
        });
        // Auto-connect to QZ Tray if previously connected
        window.PosPrinter.autoConnect();
        console.log('[POS] PosPrinter initialized, status:', window.PosPrinter.getStatus());
    } else {
        console.log('[POS] Printer not enabled or PosPrinter not available');
        console.log('[POS] PRINTER_SETTINGS:', window.PRINTER_SETTINGS);
        console.log('[POS] PosPrinter:', window.PosPrinter);
    }

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
            } else if (window.POS_CAN_OPEN) {
                openSessionModal.show();
            } else {
                showToast('warning', 'Not Allowed', 'You cannot open a session. Please contact your manager.');
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
                
                const mobileHtml = p.mobile_number
                    ? `<div class="text-muted small"><span class="fas fa-phone-alt me-1"></span>${p.mobile_number}</div>`
                    : '';
                const addressHtml = `<div class="text-muted small"><span class="fas fa-map-marker-alt me-1"></span>${addressParts.length > 0 ? addressParts.join(', ') : '<em>No address on file</em>'}</div>`;

                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <div class="fw-bold text-primary mb-1">${toTitleCase(p.fullname)}</div>
                            ${mobileHtml}
                            ${addressHtml}
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            ${genderBadge}
                            <button class="btn btn-sm btn-outline-primary mt-1" onclick="viewPassenger('${p.passenger_id}', event)" title="Edit Passenger" style="min-width:32px; height:28px; padding:2px 6px;">
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

    const toTitleCase = (str) => {
        if (!str) return '';
        return str.replace(/\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
    };

    // Set hidden passenger ID value, clear the visible search input
    hiddenInput.value = passenger.passenger_id;
    searchInput.value = '';

    // Hide dropdown and hint
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    const hint = document.getElementById('passengerSearchHint');
    if (hint) hint.style.display = 'none';

    // Hide search group, show selected display
    const searchGroup = document.getElementById('passengerSearchGroup');
    if (searchGroup) searchGroup.style.display = 'none';

    // Populate and show the selected passenger inside the input area
    const detailsContainer = document.getElementById('selectedPassengerDetails');
    const nameEl = document.getElementById('selectedPassengerName');
    const mobileEl = document.getElementById('selectedPassengerMobile');

    nameEl.textContent = toTitleCase(passenger.fullname);
    mobileEl.textContent = passenger.mobile_number ? `· ${passenger.mobile_number}` : '';

    detailsContainer.style.display = 'flex';

    // Trigger change event
    hiddenInput.dispatchEvent(new Event('change'));
}

function resetPassengerField() {
    const searchInput = document.getElementById('ticketPassengerSearch');
    if (searchInput) searchInput.value = '';
    const hiddenInput = document.getElementById('ticketPassenger');
    if (hiddenInput) hiddenInput.value = '';

    const nameEl = document.getElementById('selectedPassengerName');
    const mobileEl = document.getElementById('selectedPassengerMobile');
    if (nameEl) nameEl.textContent = '';
    if (mobileEl) mobileEl.textContent = '';

    const details = document.getElementById('selectedPassengerDetails');
    if (details) details.style.display = 'none';
    const searchGroup = document.getElementById('passengerSearchGroup');
    if (searchGroup) searchGroup.style.display = 'flex';

    const hint = document.getElementById('passengerSearchHint');
    if (hint) hint.style.display = '';

    const dropdown = document.getElementById('ticketPassengerDropdown');
    if (dropdown) { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }
}

function clearSelectedPassenger() {
    resetPassengerField();
    document.getElementById('ticketPassengerSearch').focus();
}

function viewPassenger(passengerId, event) {
    if (event) event.stopPropagation();

    const encodedPassengerId = IdEncoder.encode(parseInt(passengerId, 10));
    fetch(`${window.BASE_URL}/api/pos/passengers?passenger_id=${encodedPassengerId}`)
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

async function populateViewPassengerForm(passenger) {
    console.log('Populating passenger form with data:', passenger);

    document.getElementById('viewPassengerId').value = passenger.passenger_id;
    document.getElementById('viewPassengerFullname').value = passenger.fullname || '';
    document.getElementById('viewPassengerMobile').value = passenger.mobile_number || '';
    document.getElementById('viewPassengerEmail').value = passenger.email || '';
    document.getElementById('viewPassengerGender').value = passenger.gender || '';
    document.getElementById('viewPassengerBirthDate').value = passenger.birth_date || '';
    document.getElementById('viewPassengerStreetAddress').value = passenger.street_address || '';
    document.getElementById('viewPassengerNotes').value = passenger.notes || '';

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

    // Load address dropdowns sequentially - must await each before setting next value
    try {
        // 1. Load regions and set value
        await populateViewRegionSelect();
        if (passenger.region_code) {
            document.getElementById('viewPassengerRegion').value = passenger.region_code;

            // 2. Load provinces for selected region, then set value
            await loadViewProvinces();
            if (passenger.province_code) {
                document.getElementById('viewPassengerProvince').value = passenger.province_code;

                // 3. Load cities for selected province, then set value
                await loadViewCities();
                if (passenger.city_municipality_code) {
                    document.getElementById('viewPassengerCity').value = passenger.city_municipality_code;

                    // 4. Load barangays for selected city, then set value
                    await loadViewBarangays();
                    if (passenger.barangay_code) {
                        document.getElementById('viewPassengerBarangay').value = passenger.barangay_code;
                    }
                }
            }
        }
    } catch (err) {
        console.error('Error loading address dropdowns:', err);
    }

    // Reset to step 1
    viewPassengerStep = 1;
    updateViewPassengerWizardUI();
}

function populateViewRegionSelect() {
    const select = document.getElementById('viewPassengerRegion');
    select.innerHTML = '<option value="">Select Region</option>';

    return fetch(`${window.BASE_URL}/api/psgc?action=regions`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.regions) {
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
        });
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
            resetPassengerField();
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
            items[currentActiveIndex].click();
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
                    const discountText = d.discount_percentage > 0 ? ` (${d.discount_percentage}%)` : '';
                    option.textContent = `${d.name}${discountText}`;
                    option.dataset.discountPercentage = d.discount_percentage || 0;
                    // Mark as default if is_default is set to 1
                    if (d.is_default === 1) {
                        option.dataset.isDefault = 'true';
                    }
                    select.appendChild(option);
                });
                // Select default discount if available
                const defaultOption = select.querySelector('[data-is-default="true"]');
                if (defaultOption) {
                    select.value = defaultOption.value;
                    // Trigger recalculation
                    computeTicketTotal();
                }
            } else if (data.error && data.error.includes('Permission denied')) {
                showAlert('error', data.error);
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
            } else if (data.error && data.error.includes('Permission denied')) {
                showAlert('error', data.error);
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
            } else if (data.error && data.error.includes('Permission denied')) {
                // Show permission error in UI
                showAlert('error', data.error);
            }
            // Load wallets filtered by user's assigned branch
            loadWallets(null, window.POS_BRANCH_ID);
        })
        .catch(error => {
            console.error('Error loading provider service fees:', error);
            loadWallets(null, window.POS_BRANCH_ID); // Fallback loading with user's branch
        });
}

function refreshWallets() {
    loadWallets(null, window.POS_BRANCH_ID);
}

function loadWallets(providerId = null, branchId = null) {
    const select = document.getElementById('ticketWallet');
    let url = `${window.BASE_URL}/api/wallets`;

    // Use user's assigned branch by default if not provided
    const userBranchId = branchId || window.POS_BRANCH_ID || null;

    // Add filter parameters if provided
    if (providerId || userBranchId) {
        const params = [];
        if (providerId) params.push(`provider_id=${IdEncoder.encode(providerId)}`);
        if (userBranchId) params.push(`branch_id=${IdEncoder.encode(userBranchId)}`);
        url += `?${params.join('&')}`;
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.wallets) {
                select.innerHTML = '<option value="">Select Wallet</option>';
                // Filter only active wallets
                const activeWallets = data.data.wallets.filter(w => w.status === 'active');
                
                activeWallets.forEach(w => {
                    const option = document.createElement('option');
                    option.value = w.wallet_id;
                    option.dataset.providerId = w.provider_id;
                    option.dataset.branchId = w.branch_id;
                    option.dataset.providerType = w.provider_type;
                    
                    const typeLabel = w.provider_type ? w.provider_type.toUpperCase() : 'OTHER';
                    option.textContent = `${w.wallet_name || 'Wallet #' + w.wallet_id} • ₱${fmt(parseFloat(w.current_balance))} • [${typeLabel}]`;
                    select.appendChild(option);
                });
                
                // If no active wallets
                if (activeWallets.length === 0) {
                    select.innerHTML = '<option value="">No active wallets available</option>';
                    select.disabled = true;
                }
            } else if (data.error && data.error.includes('Permission denied')) {
                // Show permission error in UI
                select.innerHTML = '<option value="">Wallet access restricted (Permission denied)</option>';
                select.disabled = true;
                showAlert('error', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading wallets:', error);
            select.innerHTML = '<option value="">Error loading wallets</option>';
            select.disabled = true;
        });
}

function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'warning'} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
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

    // Convert to numbers and validate
    const numericProviderId = providerId ? parseInt(providerId, 10) : null;
    const numericBranchId = branchId ? parseInt(branchId, 10) : null;

    console.log('Numeric providerId:', numericProviderId, 'Numeric branchId:', numericBranchId);

    if (!numericProviderId || numericProviderId < 1) {
        console.log('Invalid providerId, skipping service fee fetch');
        serviceFeeDisplay.textContent = '-';
        serviceFeeInput.value = 0;
        baseAmountDisplay.textContent = '₱0.00';
        computeTicketTotal();
        return;
    }
    
    // Fetch service fee for this provider and branch
    const encodedProviderId = IdEncoder.encode(numericProviderId);
    let url = `${window.BASE_URL}/api/provider-service-fees`;

    // Only add parameters if they have valid values
    if (encodedProviderId) {
        url += `?provider_id=${encodedProviderId}`;
    }
    if (numericBranchId) {
        const encodedBranchId = IdEncoder.encode(numericBranchId);
        if (encodedBranchId) {
            url += (encodedProviderId ? '&' : '?') + `branch_id=${encodedBranchId}`;
        }
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
    
    // If search is empty, just hide dropdown — do not destroy the selected passenger display
    if (!searchTerm || searchTerm.trim() === '') {
        const dropdown = document.getElementById('ticketPassengerDropdown');
        if (dropdown) { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }
        console.log('Search empty');
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
                    items[currentActiveIndex].click();
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
    const openingCash = parseFloat(document.getElementById('sessionOpeningCash').value.replace(/,/g, '')) || 0;
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
    // Reset form
    document.getElementById('closingCash').value = '';
    document.getElementById('closingNotes').value = '';
    document.getElementById('varianceDisplay').textContent = '₱0.00';
    document.getElementById('varianceDisplay').className = 'fw-bold text-muted';

    // Check permission and set view-only mode if needed
    const canClose = window.POS_CAN_CLOSE;
    const warningDiv = document.getElementById('closePermissionWarning');
    const submitBtn = document.getElementById('closeModalSubmitBtn');
    const disabledBtn = document.getElementById('closeModalDisabledBtn');
    const closingCashInput = document.getElementById('closingCash');
    const closingNotesInput = document.getElementById('closingNotes');

    if (!canClose) {
        // Show view-only mode
        warningDiv.classList.remove('d-none');
        submitBtn.classList.add('d-none');
        disabledBtn.classList.remove('d-none');
        closingCashInput.disabled = true;
        closingNotesInput.disabled = true;
    } else {
        // Normal mode
        warningDiv.classList.add('d-none');
        submitBtn.classList.remove('d-none');
        disabledBtn.classList.add('d-none');
        closingCashInput.disabled = false;
        closingNotesInput.disabled = false;
    }

    // Fetch session summary
    try {
        const encodedSessionId = IdEncoder.encode(window.POS_SESSION_ID);
        const response = await fetch(`${window.BASE_URL}/api/pos/sessions?id=${encodedSessionId}`);
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
                    <div class="text-muted small mb-1">Cashier</div>
                    <div class="fw-bold">${s.cashier_name || '—'}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted small mb-1">Transactions</div>
                    <div class="fw-bold">${s.txn_count || 0}</div>
                  </div>
                </div>`;

            // Payment type breakdown with include_in_expected_cash indicator
            const totalRefunds = parseFloat(s.total_refunds || 0);
            const expectedCashFromAPI = parseFloat(s.expected_cash || 0);

            if (payments.length > 0) {
                let html = '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
                html += '<div class="card mb-4"><div class="card-body py-3"><table class="table table-borderless fs-10 mb-0">';

                // Opening cash row
                html += `
                  <tr class="border-bottom">
                    <td class="ps-0 pt-0"><strong>Opening Cash</strong>
                      <div class="text-400 fw-normal fs-11 text-secondary">STARTING BALANCE</div>
                    </td>
                    <td class="pe-0 text-end pt-0"><strong>₱${fmt(s.starting_cash)}</strong></td>
                  </tr>`;

                // Payment methods from API with include_in_expected_cash indicator
                payments.forEach((p, index) => {
                    const isLast = index === payments.length - 1;
                    const borderClass = !isLast || totalRefunds > 0 ? 'border-bottom' : '';
                    const inCashBadge = p.include_in_expected_cash
                        ? '<span class="badge bg-soft-success text-success fs-11 ms-1"><span class="fas fa-cash-register me-1"></span>In Cash</span>'
                        : '<span class="badge bg-soft-secondary text-secondary fs-11 ms-1"><span class="fas fa-ban me-1"></span>Not Cash</span>';

                    html += `
                      <tr class="${borderClass}">
                        <td class="ps-0">${p.method_name}${inCashBadge}
                          <div class="text-400 fw-normal fs-11 text-uppercase">${p.method_type}</div>
                        </td>
                        <td class="pe-0 text-end">₱${fmt(p.total_amount)}</td>
                      </tr>`;
                });

                // Show refunds if any
                if (totalRefunds > 0) {
                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0"><strong>Refunds (Cash Out)</strong>
                          <div class="text-400 fw-normal fs-11 text-danger">CASH OUT</div>
                        </td>
                        <td class="pe-0 text-end text-danger"><strong>-₱${fmt(totalRefunds)}</strong></td>
                      </tr>`;
                }

                // Show expected cash calculation
                const expectedCashCalc = payments
                    .filter(p => p.include_in_expected_cash)
                    .reduce((sum, p) => sum + parseFloat(p.total_amount), 0);
                const netTotal = parseFloat(s.starting_cash || 0) + expectedCashCalc - totalRefunds;

                html += `
                  <tr class="table-light">
                    <td class="ps-0 pb-0 pt-2"><strong>Expected Cash</strong>
                      <div class="text-400 fw-normal fs-11 text-success">STARTING + IN CASH - REFUNDS</div>
                    </td>
                    <td class="pe-0 text-end pb-0 pt-2 text-success"><strong>₱${fmt(netTotal)}</strong></td>
                  </tr>
                </table>
              </div>
            </div>`;

                // Add info note about expected cash calculation
                html += `
                <div class="alert alert-info fs-10 mb-4">
                  <span class="fas fa-info-circle me-2"></span>
                  <strong>How Expected Cash is calculated:</strong><br>
                  <small>Starting Cash (₱${fmt(s.starting_cash)}) + Payments marked "In Cash" (₱${fmt(expectedCashCalc)}) - Refunds (₱${fmt(totalRefunds)})</small>
                </div>`;

                // Add refund info note if there are refunds
                if (totalRefunds > 0) {
                    html += `
                    <div class="alert alert-warning fs-10 mb-4">
                      <span class="fas fa-exclamation-triangle me-2"></span>
                      <strong>Note:</strong> Refunds of ₱${fmt(totalRefunds)} have been processed from your cash drawer.
                    </div>`;
                }

                document.getElementById('paymentBreakdownSection').innerHTML = html;
            } else {
                document.getElementById('paymentBreakdownSection').innerHTML = `
                    <div class="alert alert-info fs-10 mb-4">
                        <span class="fas fa-info-circle me-2"></span>No payments recorded for this session.
                    </div>`;
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

function formatNumberInput(input) {
    let value = input.value;

    // Remove all non-numeric characters except commas and decimal point
    value = value.replace(/[^0-9.,]/g, '');

    // Remove commas for calculation
    const numericValue = value.replace(/,/g, '');
    if (numericValue === '') return;

    // Format with commas for display
    const formatted = numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    input.value = formatted;
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
    // Double-check permission before submitting
    if (!window.POS_CAN_CLOSE) {
        showToast('warning', 'Not Allowed', 'You are not authorized to close this session. Please contact your manager.');
        return;
    }

    const closingCash = parseFloat(document.getElementById('closingCash').value.replace(/,/g, '')) || 0;
    const notes = document.getElementById('closingNotes').value.trim();
    const bankAccountId = document.getElementById('depositBankAccountId').value || null;
    const depositNow = document.getElementById('depositNow').checked;

    const btn = document.querySelector('#closeSessionModal .btn-danger');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Closing...';

    try {
        const payload = {
            session_id: window.POS_SESSION_ID,
            closing_cash_balance: closingCash,
            notes,
            action: 'close'
        };
        
        if (bankAccountId) {
            payload.cash_deposit_bank_id = bankAccountId;
            payload.deposit_now = depositNow;
        }

        const res = await fetch(`${window.BASE_URL}/api/pos/sessions`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
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
    
    // For transactions tab - just switch view, don't clear cart (it's just for viewing)
    if (type === 'transaction') {
        transactionType = type;
        localStorage.setItem('posTransactionType', type);
        updateTransactionTypeUI();
        
        // Set date to today if not already set
        const dateInput = document.getElementById('filterDate');
        if (dateInput && dateInput._flatpickr) {
            const currentValue = dateInput.value;
            if (!currentValue) {
                const today = new Date();
                dateInput._flatpickr.setDate([today, today]);
            }
        }
        
        // Load transactions on page 1
        loadRecentTransactions(1);
        return;
    }

    // Switching between ticket and service modes - allow freely, cart can hold both
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
    
    // Focus on Full Name field after modal is fully shown using Bootstrap event
    const modalElement = document.getElementById('addPassengerModal');
    modalElement.addEventListener('shown.bs.modal', function focusFullname() {
        const fullnameField = document.getElementById('newPassengerFullname');
        fullnameField.focus();
        modalElement.removeEventListener('shown.bs.modal', focusFullname);
    });
    
    // Refresh passenger lists after adding
    renderCustomers();
    resetPassengerField();
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
    const discountPercentage = discountSelect.value === '0' ? 0 : parseFloat(discountSelect.options[discountSelect.selectedIndex].dataset.discountPercentage) || 0;
    const discountAmount = (baseAmount * discountPercentage) / 100;
    const total = baseAmount + serviceFee - discountAmount;
    
    // Update displays
    document.getElementById('ticketBaseAmountDisplay').textContent = `₱${baseAmount.toFixed(2)}`;
    document.getElementById('ticketTotalDisplay').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addTicketToCart() {
    if (!window.POS_HAS_SESSION) {
        showToast('warning', 'No Session', 'Please open a cashier session first.');
        if (window.POS_CAN_OPEN) {
            openSessionModal.show();
        } else {
            showToast('warning', 'Not Allowed', 'You cannot open a session. Please contact your manager.');
        }
        return;
    }

    const passengerId = document.getElementById('ticketPassenger').value;
    const ticketNumber = document.getElementById('ticketNumber').value.trim();
    // const origin = document.getElementById('ticketOrigin').value.trim();
    // const destination = document.getElementById('ticketDestination').value.trim();
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value) || 0;
    const serviceFee = parseFloat(document.getElementById('ticketServiceFee').value) || 0;
    const discountSelect = document.getElementById('ticketDiscount');
    const discountPercentage = discountSelect.value === '0' ? 0 : parseFloat(discountSelect.options[discountSelect.selectedIndex].dataset.discountPercentage) || 0;
    const discountAmount = (baseAmount * discountPercentage) / 100;
    const total = baseAmount + serviceFee - discountAmount;
    const accommodationId = document.getElementById('ticketAccommodation').value || null;
    
    // Get wallet and branch info
    const walletSelect = document.getElementById('ticketWallet');
    const walletId = walletSelect.value;
    const walletOption = walletSelect.selectedOptions[0];
    const walletBranchId = walletOption ? walletOption.dataset.branchId : null;
    const walletProviderId = walletOption ? walletOption.dataset.providerId : null;

    if (!passengerId) { showToast('danger', 'Error', 'Please select a passenger.'); return; }
    if (!ticketNumber) { showToast('danger', 'Error', 'Please enter ticket number.'); return; }
    if (!walletId) { showToast('danger', 'Error', 'Please select a wallet.'); return; }
    // if (!origin) { showToast('danger', 'Error', 'Please enter origin.'); return; }
    // if (!destination) { showToast('danger', 'Error', 'Please enter destination.'); return; }
    if (total <= 0) { showToast('danger', 'Error', 'Ticket total must be greater than 0.'); return; }

    // Get passenger name from selected passenger display
    const passengerName = (document.getElementById('selectedPassengerName') || {}).textContent?.trim() || document.getElementById('ticketPassengerSearch').value.trim();

    const ticketItem = {
        type: 'ticket',
        passengerId,
        passengerName,
        ticketNumber,
        origin: null,
        destination: null,
        baseAmount,
        serviceFee,
        discountId: discountSelect.value === '0' ? null : discountSelect.value,
        discountPercentage,
        discountAmount,
        accommodationId,
        total,
        walletId,
        branchId: walletBranchId,
        providerId: walletProviderId
    };

    // Only one ticket allowed per cart
    if (cart.some(i => i.type === 'ticket')) {
        showToast('warning', 'Ticket Already Added', 'Only one ticket is allowed per cart. Remove the existing ticket first.');
        return;
    }

    ticketInCart = ticketItem;
    cart.unshift(ticketItem); // Ticket always first in cart
    saveCartToStorage();
    renderCart();

    // Clear ticket form after adding to cart
    resetPassengerField();
    document.getElementById('ticketNumber').value = '';
    document.getElementById('ticketBaseAmount').value = '';
    document.getElementById('ticketDiscount').value = '0';
    document.getElementById('ticketServiceFee').value = '0';
    document.getElementById('ticketServiceFeeDisplay').textContent = '-';
    document.getElementById('ticketBaseAmountDisplay').textContent = '₱0.00';
    document.getElementById('ticketTotalDisplay').textContent = '₱0.00';
    window.currentServiceFee = null;
    // Ticket added - no toast to avoid distraction
}

// =============================================
// SERVICE TYPE SELECTION
// =============================================

function selectServiceType(id, name, defaultAmount, allowCustom, requiresWallet) {
    if (!window.POS_HAS_SESSION) {
        showToast('warning', 'No Session', 'Please open a cashier session first.');
        if (window.POS_CAN_OPEN) {
            openSessionModal.show();
        } else {
            showToast('warning', 'Not Allowed', 'You cannot open a session. Please contact your manager.');
        }
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
        if (window.POS_CAN_OPEN) {
            openSessionModal.show();
        } else {
            showToast('warning', 'Not Allowed', 'You cannot open a session. Please contact your manager.');
        }
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

    cart.push(serviceItem);

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
            <div class="cart-item d-flex align-items-center gap-2 py-2 px-1 border-start border-4 border-primary">
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-primary" style="font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.passengerName || '-'}</div>
                <div class="text-muted" style="font-size:0.72rem;">
                  Ticket #${item.ticketNumber}
                  &nbsp;·&nbsp;Cost: ₱${fmt(item.baseAmount)}
                  &nbsp;·&nbsp;Fee: ₱${fmt(item.serviceFee)}
                </div>
              </div>
              <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <span class="fw-bold text-success" style="font-size:0.88rem; white-space:nowrap;">₱${fmt(item.total)}</span>
                <button class="btn btn-sm btn-link text-danger p-0" style="width:24px; height:24px; line-height:1; flex-shrink:0;" onclick="removeCartItem(${idx})" title="Remove">
                  <span class="fas fa-times-circle" style="font-size:1rem;"></span>
                </button>
              </div>
            </div>`;
        } else {
            html += `
            <div class="cart-item d-flex align-items-center gap-2 py-2 px-1">
              <span class="fas fa-concierge-bell text-secondary" style="font-size:1.1rem; flex-shrink:0;"></span>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-dark" style="font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.serviceName}</div>
                <div class="text-muted" style="font-size:0.72rem;">
                  ${item.qty} × ₱${fmt(item.unitPrice)}
                  ${item.description ? `&nbsp;·&nbsp;${item.description}` : ''}
                </div>
              </div>
              <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <span class="fw-bold text-success" style="font-size:0.88rem; white-space:nowrap;">₱${fmt(item.total)}</span>
                <button class="btn btn-sm btn-link text-danger p-0" style="width:24px; height:24px; line-height:1; flex-shrink:0;" onclick="removeCartItem(${idx})" title="Remove">
                  <span class="fas fa-times-circle" style="font-size:1rem;"></span>
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
        cart = []; // Clear everything when ticket is removed (services may depend on it)
        saveCartToStorage();
        renderCart();
        return;
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
                            Ticket #: ${item.ticketNumber} • Cost: ₱${fmt(item.baseAmount)}${item.serviceFee > 0 ? ' + Fee: ₱' + fmt(item.serviceFee) : ''}${item.discountAmount > 0 ? ' - Discount: ₱' + fmt(item.discountAmount) : ''}
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
        tracksCredit: el.dataset.tracksCredit === '1',
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

    // If method tracks credit/billing, determine passenger
    if (activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) {
        // If there's a ticket in cart, auto-use that passenger
        const ticketItem = cart.find(i => i.type === 'ticket');
        if (ticketItem && ticketItem.passengerId) {
            selectedCustomerId = ticketItem.passengerId;
            document.getElementById('paymentEntryRow').style.display = '';
        } else {
            // No ticket passenger — open customer selector
            openCustomerModal();
        }
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
    if ((activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) && !selectedCustomerId) {
        showToast('danger', 'Customer Required', 'Please select a customer for this payment method.'); return;
    }
    const bankAccountId = document.getElementById('bankAccountSelect').value || null;

    paymentLines.push({
        methodId: activePaymentMethod.id,
        methodName: activePaymentMethod.name,
        methodType: activePaymentMethod.type,
        requiresConfirmation: activePaymentMethod.requiresConfirmation,
        tracksCredit: activePaymentMethod.tracksCredit,
        amount,
        referenceNumber: refNum || null,
        bankAccountId,
        passengerId: (activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) ? selectedCustomerId : null
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
                    ${p.tracksCredit && p.passengerId ? '<span class="badge bg-soft-danger text-danger ms-2 small"><span class="fas fa-file-invoice-dollar me-1"></span>Billed to Account</span>' : ''}
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
            tickets: [{
                passenger_id: ticket.passengerId,
                origin: ticket.origin,
                destination: ticket.destination,
                ticket_number: ticket.ticketNumber,
                base_amount: ticket.baseAmount,
                service_fee: ticket.serviceFee,
                discount_id: ticket.discountId,
                discount_amount: ticket.discountAmount,
                accommodation_id: ticket.accommodationId || null,
                total_amount: ticket.total,
                wallet_id: ticket.walletId
            }],
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

            // Print receipt if enabled
            console.log('[POS] Checking printer settings for receipt print...');
            console.log('[POS] PRINTER_SETTINGS:', window.PRINTER_SETTINGS);
            console.log('[POS] PosPrinter available:', !!window.PosPrinter);

            if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.enabled && window.PosPrinter) {
                try {
                    const printerStatus = window.PosPrinter.getStatus();
                    console.log('[POS] Printer status:', printerStatus);

                    if (printerStatus.ready) {
                        // Build transaction data for receipt
                        const transactionData = {
                            id: result.transaction_id || result.order_id || result.id,
                            transaction_code: result.transaction_code,
                            branch_name: window.POS_BRANCH_NAME || '',
                            cashier_name: window.POS_USER_NAME || '',
                            payment_method: paymentLines.length > 0 ? paymentLines[0].methodName : '',
                            subtotal: total,
                            discount: cart.reduce((s, i) => s + parseFloat(i.discountAmount || 0), 0),
                            tax: 0,
                            total: total,
                            amount_tendered: paid,
                            change_amount: paid - total,
                            items: cart.map(item => ({
                                name: (item.type === 'ticket' ? item.ticketNumber : null) || item.description || item.passengerName || item.serviceName || item.type,
                                quantity: item.qty || 1,
                                price: item.unitPrice || item.total || 0,
                                base_amount: parseFloat(item.baseAmount || item.unitPrice || item.total || 0),
                                service_fee: parseFloat(item.serviceFee || 0),
                                discount_amount: parseFloat(item.discountAmount || 0),
                                total: item.total
                            }))
                        };

                        // Print based on settings
                        if (window.PRINTER_SETTINGS.showPreview) {
                            const shouldPrint = await window.PosPrinter.showPreview(transactionData);
                            if (shouldPrint) {
                                await window.PosPrinter.printReceipt(transactionData);
                            }
                        } else if (window.PRINTER_SETTINGS.autoPrint) {
                            await window.PosPrinter.printReceipt(transactionData);
                        }
                    } else {
                        console.warn('Printer not ready:', printerStatus);
                    }
                } catch (printError) {
                    console.error('Receipt printing error:', printError);
                    // Don't show error toast to avoid blocking transaction
                }
            }

            cart = [];
            paymentLines = [];
            ticketInCart = null;
            saveCartToStorage();
            renderCart();
            renderPaymentLines();
            paymentModal.hide();
            itemEntryModal.hide();
            // Refresh wallet balances to reflect updated balance after payment
            loadWallets(null, window.POS_BRANCH_ID);
            // Refresh recent transactions list
            loadRecentTransactions();
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
    list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading transactions...</td></tr>';
    
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
            } else if (data.error && data.error.includes('Permission denied')) {
                showAlert('error', data.error);
                list.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Permission denied. You do not have access to view transactions.</td></tr>';
                updatePaginationUI();
            } else {
                list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No transactions found.</td></tr>';
                updatePaginationUI();
            }
        })
        .catch(error => {
            console.error('Error loading transactions:', error);
            list.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Error loading transactions.</td></tr>';
        });
}

function renderTransactionsTable(transactions) {
    const list = document.getElementById('recentTransactionsList');

    if (transactions.length === 0) {
        list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No transactions found.</td></tr>';
        return;
    }

    let html = '';
    transactions.forEach(txn => {
        const hasPendingCancellation = txn.pending_cancellation_id ? true : false;
        const isOrderBased = !!txn.order_id; // new grouped order
        const orderItems = txn.order_items || [];

        // Check for cancelled items in order
        const cancelledTicketCount = parseInt(txn.cancelled_ticket_count || 0);
        const cancelledServiceCount = parseInt(txn.cancelled_service_count || 0);
        const hasCancelledItems = cancelledTicketCount > 0 || cancelledServiceCount > 0;
        const totalItems = parseInt(txn.ticket_count || 0) + parseInt(txn.service_count || 0);
        const allItemsCancelled = hasCancelledItems && (cancelledTicketCount + cancelledServiceCount) >= totalItems;

        let statusBadge;
        if (txn.status === 'booked') {
            statusBadge = '<span class="badge bg-soft-success text-success">Booked</span>';
        } else if (txn.status === 'completed') {
            if (allItemsCancelled) {
                statusBadge = '<span class="badge bg-soft-danger text-danger">Cancelled</span>';
            } else if (hasCancelledItems) {
                statusBadge = '<span class="badge bg-soft-primary text-primary">Completed</span>';
            } else {
                statusBadge = '<span class="badge bg-soft-primary text-primary">Completed</span>';
            }
        } else if (txn.status === 'cancelled') {
            statusBadge = '<span class="badge bg-soft-danger text-danger">Cancelled</span>';
        } else if (txn.status === 'refunded') {
            statusBadge = '<span class="badge bg-soft-warning text-warning">Refunded</span>';
        } else {
            statusBadge = `<span class="badge bg-soft-secondary text-secondary">${txn.status}</span>`;
        }

        // Add pending cancellation indicator
        if (hasPendingCancellation) {
            statusBadge += ` <span class="badge bg-soft-warning text-warning ms-1" title="Cancellation requested by ${txn.cancellation_requested_by || 'Unknown'}"><i class="fas fa-clock me-1"></i>Pending Cancel</span>`;
        }

        // Add cancelled items indicator
        if (hasCancelledItems && !hasPendingCancellation) {
            if (allItemsCancelled) {
                statusBadge += ` <span class="badge bg-soft-danger text-danger ms-1" title="${cancelledTicketCount} ticket(s) and ${cancelledServiceCount} service(s) cancelled"><i class="fas fa-times-circle me-1"></i>All Cancelled</span>`;
            } else {
                statusBadge += ` <span class="badge bg-soft-warning text-warning ms-1" title="${cancelledTicketCount} ticket(s) and ${cancelledServiceCount} service(s) cancelled"><i class="fas fa-exclamation-circle me-1"></i>Partially Cancelled</span>`;
            }
        }

        const branchName = txn.branch_name ? `<span class="badge bg-soft-primary text-primary">${txn.branch_name}</span>` : '-';
        const ticketNumber = txn.ticket_number || '-';
        const createdAt = new Date(txn.created_at);

        // Build type badge(s)
        let typeBadge = '';
        if (isOrderBased) {
            const tCount = parseInt(txn.ticket_count || 0);
            const sCount = parseInt(txn.service_count || 0);
            if (tCount > 0) typeBadge += `<span class="badge bg-soft-primary text-primary me-1"><i class="fas fa-ticket-alt me-1"></i>${tCount > 1 ? tCount + '×' : ''}Ticket</span>`;
            if (sCount > 0) typeBadge += `<span class="badge bg-soft-success text-success"><i class="fas fa-concierge-bell me-1"></i>${sCount > 1 ? sCount + '×' : ''}Service</span>`;
        } else {
            typeBadge = txn.transaction_type === 'TICKET'
                ? '<span class="badge bg-soft-primary text-primary">TICKET</span>'
                : '<span class="badge bg-soft-success text-success">SERVICE</span>';
        }

        // Passenger / description cell
        let passengerCell = '-';
        if (isOrderBased && orderItems.length > 0) {
            const names = [...new Set(orderItems.filter(i => i.item_type === 'TICKET' && i.passenger_name).map(i => i.passenger_name.charAt(0).toUpperCase() + i.passenger_name.slice(1).toLowerCase()))];
            passengerCell = names.length > 0 ? names.join('<br>') : (txn.passenger_name || '-');
        } else if (txn.passenger_name) {
            passengerCell = txn.passenger_name.charAt(0).toUpperCase() + txn.passenger_name.slice(1).toLowerCase();
        }

        // Provider cell
        let providerCell = '-';
        if (isOrderBased && orderItems.length > 0) {
            const providers = [...new Set(orderItems.filter(i => i.provider_name).map(i => i.provider_name))];
            providerCell = providers.length > 0 ? providers.join('<br>') : '-';
        } else if (txn.provider_name) {
            providerCell = txn.provider_type
                ? `<div>${txn.provider_name}</div><div><span class="badge bg-soft-info text-info" style="font-size:0.75em;">${txn.provider_type}</span></div>`
                : txn.provider_name;
        }

        // Origin/Dest cell
        let routeCell = '-';
        if (isOrderBased && orderItems.length > 0) {
            const routes = [...new Set(orderItems.filter(i => i.item_type === 'TICKET' && i.origin && i.destination).map(i => `${i.origin} → ${i.destination}`))];
            routeCell = routes.length > 0 ? routes.join('<br>') : '-';
        } else {
            routeCell = (txn.origin && txn.destination) ? `${txn.origin} → ${txn.destination}` : '-';
        }

        // Build payment method cell — shows each payment line e.g. Cash ₱1,000 + Charge ₱500
        let paymentCell = '-';
        if (isOrderBased && txn.payments && txn.payments.length > 0) {
            const methodTypeIcon = { CASH: 'fa-money-bill-wave', BANK_TRANSFER: 'fa-university', E_WALLET: 'fa-mobile-alt', CHARGE: 'fa-file-invoice-dollar', OTHER: 'fa-receipt' };
            const methodTypeColor = { CASH: 'text-success', BANK_TRANSFER: 'text-primary', E_WALLET: 'text-info', CHARGE: 'text-warning', OTHER: 'text-secondary' };
            paymentCell = txn.payments.map(p => {
                const icon  = methodTypeIcon[p.method_type]  || 'fa-credit-card';
                const color = methodTypeColor[p.method_type] || 'text-secondary';
                return `<div><i class="fas ${icon} ${color} me-1" style="font-size:0.75rem;"></i><span class="small">${p.method_name}</span> <span class="fw-semibold small">₱${fmt(p.amount)}</span></div>`;
            }).join('');
        } else if (!isOrderBased && txn.payment_method) {
            paymentCell = `<span class="small">${txn.payment_method}</span>`;
        }

        // Items breakdown (collapsible) for order-based
        let itemsBreakdown = '';
        if (isOrderBased && orderItems.length > 0) {
            const rowId = `order-items-${txn.order_id}`;
            let itemRows = '';
            orderItems.forEach(item => {
                // Check if item is cancelled/refunded
                const isTicketCancelled = item.item_type === 'TICKET' && ['cancelled', 'refunded'].includes(item.ticket_status);
                const isServiceCancelled = item.item_type === 'SERVICE' && ['cancelled', 'refunded'].includes(item.service_status);
                const isCancelled = isTicketCancelled || isServiceCancelled;
                const cancelledBadge = isCancelled ? '<span class="badge bg-danger text-white ms-1" style="font-size:0.6rem;">CANCELLED</span>' : '';
                const rowClass = isCancelled ? 'table-secondary text-muted' : 'table-light';
                const textStyle = isCancelled ? 'text-decoration: line-through; opacity: 0.7;' : '';

                if (item.item_type === 'TICKET') {
                    const td = item.travel_date ? new Date(item.travel_date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
                    const iconColor = isCancelled ? 'text-muted' : 'text-primary';
                    itemRows += `<tr class="${rowClass}" style="font-size:0.8em;${textStyle}">
                        <td colspan="2"><i class="fas fa-ticket-alt ${iconColor} me-1"></i>${item.passenger_name || '-'}${cancelledBadge}</td>
                        <td colspan="2">${item.provider_name || '-'}</td>
                        <td>${td}</td>
                        <td>${item.origin && item.destination ? item.origin + ' → ' + item.destination : '-'}</td>
                        <td>₱${fmt(item.total_amount)}</td>
                        <td colspan="3">${item.transaction_code || ''}</td>
                    </tr>`;
                } else {
                    const svcName = item.service_type_name || item.service_name || 'Service';
                    const svcDesc = item.description || item.service_name || '-';
                    const iconColor = isCancelled ? 'text-muted' : 'text-success';
                    itemRows += `<tr class="${rowClass}" style="font-size:0.8em;${textStyle}">
                        <td colspan="2"><i class="fas fa-concierge-bell ${iconColor} me-1"></i>${svcName}${cancelledBadge}</td>
                        <td colspan="2">${svcDesc !== svcName ? svcDesc : '-'}</td>
                        <td>-</td><td>-</td>
                        <td>₱${fmt(item.total_amount)}</td>
                        <td colspan="3">${item.transaction_code || ''}</td>
                    </tr>`;
                }
            });
            itemsBreakdown = `<tr id="${rowId}" style="display:none;">
                <td colspan="9" class="p-0">
                  <table class="table table-sm mb-0 border-top">
                    <thead class="table-secondary"><tr style="font-size:0.75em;">
                      <th colspan="2">Item / Passenger</th><th colspan="2">Provider / Description</th>
                      <th>Travel Date</th><th>Route</th><th>Amount</th><th colspan="3">Code</th>
                    </tr></thead>
                    <tbody>${itemRows}</tbody>
                  </table>
                </td>
              </tr>`;
        }

        // Cancel button - for legacy single-ticket or orders containing tickets
        let cancelButton = '<span class="text-muted small">N/A</span>';
        // Only count active tickets (not cancelled/refunded)
        const hasActiveTicketsInOrder = isOrderBased && orderItems.some(i =>
            i.item_type === 'TICKET' && !['cancelled', 'refunded'].includes(i.ticket_status)
        );
        const canCancelTicket = (!isOrderBased && txn.transaction_type === 'TICKET') || hasActiveTicketsInOrder;

        if (canCancelTicket && (txn.status === 'booked' || txn.status === 'completed')) {
            if (hasPendingCancellation) {
                cancelButton = `<span class="badge bg-soft-warning text-warning small" title="Cancellation requested by ${txn.cancellation_requested_by || 'Unknown'}"><i class="fas fa-clock me-1"></i>Cancel Pending</span>`;
            } else {
                // For orders, use the first active (non-cancelled) ticket's transaction code
                const ticketItem = hasActiveTicketsInOrder
                    ? orderItems.find(i => i.item_type === 'TICKET' && !['cancelled', 'refunded'].includes(i.ticket_status))
                    : null;
                
                // Calculate base amount and service fee correctly
                // For order items: total = base + service_fee, so base = total - service_fee
                const cancelTxnCode = ticketItem ? ticketItem.transaction_code : txn.transaction_code;
                let cancelBaseAmount, cancelServiceFee, cancelTotalAmount;
                if (ticketItem) {
                    const itemTotal = parseFloat(ticketItem.total_amount) || 0;
                    const itemServiceFee = parseFloat(ticketItem.service_fee) || 0;
                    cancelServiceFee = itemServiceFee;
                    cancelBaseAmount = itemTotal - itemServiceFee; // Base amount without service fee
                    cancelTotalAmount = itemTotal;
                } else {
                    cancelBaseAmount = parseFloat(txn.base_amount) || 0;
                    cancelServiceFee = parseFloat(txn.service_fee) || 0;
                    cancelTotalAmount = parseFloat(txn.total_amount) || (cancelBaseAmount + cancelServiceFee);
                }

                // Build cancel data object
                const cancelData = ticketItem ? {
                    transaction_code: ticketItem.transaction_code,
                    base_amount: cancelBaseAmount,
                    service_fee: cancelServiceFee,
                    total_amount: cancelTotalAmount,
                    passenger_name: ticketItem.passenger_name,
                    provider_name: ticketItem.provider_name,
                    origin: ticketItem.origin,
                    destination: ticketItem.destination,
                    travel_date: ticketItem.travel_date,
                    status: 'booked'
                } : txn;

                cancelButton = `<button class="btn btn-sm btn-outline-danger"
                        data-txn-code="${cancelTxnCode}"
                        data-base-amount="${cancelBaseAmount}"
                        data-service-fee="${cancelServiceFee}"
                        data-txn-data="${encodeURIComponent(JSON.stringify(cancelData))}"
                        onclick="openCancelTicketModalFromButton(this)">
                    <span class="fas fa-times me-1"></span>Cancel
                </button>`;
            }
        }

        // Toggle button for order items
        const toggleBtn = isOrderBased && orderItems.length > 0
            ? `<button class="btn btn-xs btn-outline-secondary p-1 ms-1" style="font-size:0.7rem;" onclick="toggleOrderItems('order-items-${txn.order_id}', this)" title="View items"><i class="fas fa-list"></i></button>`
            : '';

        // Reprint receipt button - only if printing is enabled
        let reprintButton = '';
        if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.enabled && window.PosPrinter) {
            reprintButton = `<button class="btn btn-sm btn-outline-info me-1"
                    data-txn-id="${txn.id || txn.order_id}"
                    data-txn-code="${txn.transaction_code}"
                    onclick="reprintTransactionReceipt(this)">
                <span class="fas fa-print"></span>
            </button>`;
        }

        // Display amount - show original amount and pending/refunded amount if applicable
        const amountDisplay = hasPendingCancellation && txn.pending_refund_amount
            ? `₱${fmt(txn.total_amount)} <span class="text-danger small">(₱${fmt(txn.pending_refund_amount)} refund pending)</span>`
            : (txn.total_refunded_amount && parseFloat(txn.total_refunded_amount) > 0
                ? `₱${fmt(txn.total_amount)} <span class="text-danger small">(₱${fmt(txn.total_refunded_amount)} refunded)</span>`
                : `₱${fmt(txn.total_amount)}`);

        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <strong>${txn.transaction_code}</strong>${toggleBtn}
                    </div>
                    <div>${typeBadge} ${txn.cashier_name ? `<span class="text-muted small ms-1">by ${txn.cashier_name}</span>` : ''}</div>
                </td>
                <td class="small">${passengerCell}</td>
                <td class="small">${branchName}</td>
                <td class="small">${providerCell}</td>
                <td class="small">${paymentCell}</td>
                <td>${amountDisplay}</td>
                <td>${statusBadge}</td>
                <td class="small">
                    <div>${createdAt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })}</div>
                    <div class="text-muted" style="font-size:0.85em;">${createdAt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })}</div>
                </td>
                <td class="text-end">${reprintButton}${cancelButton}</td>
            </tr>
            ${itemsBreakdown}
        `;
    });
    list.innerHTML = html;
}

function toggleOrderItems(rowId, btn) {
    const row = document.getElementById(rowId);
    if (!row) return;
    const hidden = row.style.display === 'none';
    row.style.display = hidden ? '' : 'none';
    btn.innerHTML = hidden ? '<i class="fas fa-chevron-up"></i>' : '<i class="fas fa-list"></i>';
}

async function reprintTransactionReceipt(btn) {
    if (!window.PRINTER_SETTINGS || !window.PRINTER_SETTINGS.enabled || !window.PosPrinter) {
        showToast('warning', 'Printing Disabled', 'Receipt printing is not enabled in system settings.');
        return;
    }

    const txnId = btn.dataset.txnId;
    const txnCode = btn.dataset.txnCode;

    if (!txnId && !txnCode) {
        showToast('error', 'Error', 'Transaction ID not found.');
        return;
    }

    // Check printer status
    const printerStatus = window.PosPrinter.getStatus();
    if (!printerStatus.ready) {
        showToast('warning', 'Printer Not Ready', 'Please configure the printer via Printer Setup page.');
        return;
    }

    // Store transaction data for confirmation
    currentReprintTransaction = { txnId, txnCode };

    // Show confirmation modal
    document.getElementById('reprintTxnCode').textContent = txnCode || txnId;
    document.getElementById('reprintReason').value = '';
    document.getElementById('reprintReasonOther').value = '';
    document.getElementById('reprintReasonOtherContainer').style.display = 'none';
    document.getElementById('confirmReprintBtn').disabled = true;

    reprintReceiptModal.show();
}

async function confirmReprintReceipt() {
    const reasonSelect = document.getElementById('reprintReason');
    const reasonOther = document.getElementById('reprintReasonOther').value;
    let reason = reasonSelect.value;

    if (reason === 'Other') {
        reason = reasonOther || 'Other';
    }

    if (!reason) {
        showToast('warning', 'Required', 'Please select a reason for reprint.');
        return;
    }

    const { txnId, txnCode } = currentReprintTransaction;

    try {
        // Fetch transaction details
        // Prefer code-based lookup (always available); fall back to encoded ID path
        const validTxnId = txnId && txnId !== 'undefined' && txnId !== '' ? txnId : null;
        const apiUrl = txnCode
            ? `${window.BASE_URL}/api/pos/transaction?code=${encodeURIComponent(txnCode)}`
            : `${window.BASE_URL}/api/pos/transaction/${IdEncoder.encode(validTxnId)}`;

        const res = await fetch(apiUrl);
        const result = await res.json();

        if (!result.success || !result.data) {
            showToast('error', 'Error', 'Failed to fetch transaction details.');
            reprintReceiptModal.hide();
            return;
        }

        const txn = result.data;

        // Build transaction data for receipt
        const orderSvcFeeTotal = parseFloat(txn.total_service_fees || 0);
        const transactionData = {
            id: txn.order_id || txn.transaction_id || txn.id,
            transaction_code: txn.order_code || txn.transaction_code,
            branch_name: txn.branch_name || '',
            cashier_name: txn.cashier_name || '',
            payment_method: txn.payment_method || '',
            subtotal: parseFloat(txn.subtotal || txn.grand_total || txn.total_amount || 0),
            discount: parseFloat(txn.discount_total || txn.discount_amount || 0),
            tax: parseFloat(txn.tax || 0),
            total: parseFloat(txn.grand_total || txn.total_amount || 0),
            amount_tendered: parseFloat(txn.amount_paid || txn.grand_total || txn.total_amount || 0),
            change_amount: parseFloat(txn.change_amount || 0),
            items: txn.items || []
        };

        // If order-based, build items from order_items
        if (txn.order_items && txn.order_items.length > 0) {
            const itemCount = txn.order_items.length;
            transactionData.items = txn.order_items.map((item, idx) => {
                const itemSvcFee   = parseFloat(item.service_fee || 0);
                const itemTotal    = parseFloat(item.total_amount || 0);
                // Distribute order-level service fee across items when item-level is zero
                const svcFee = itemSvcFee > 0 ? itemSvcFee
                    : (orderSvcFeeTotal > 0 && idx === 0 ? orderSvcFeeTotal : 0);
                const baseAmt = parseFloat(item.unit_price || 0) > 0
                    ? parseFloat(item.unit_price)
                    : (itemTotal - svcFee - parseFloat(item.discount_amount || 0));
                const name = item.item_type === 'TICKET'
                    ? (item.ticket_number || item.passenger_name || item.description || item.transaction_code || 'Ticket')
                    : (item.service_type_name || item.description || 'Service');
                return {
                    name,
                    quantity: parseInt(item.quantity) || 1,
                    price: itemTotal,
                    base_amount: baseAmt,
                    service_fee: svcFee,
                    discount_amount: parseFloat(item.discount_amount || 0),
                    total: itemTotal
                };
            });
        }

        // Guard: printer must be enabled and configured
        if (!window.PRINTER_SETTINGS || !window.PRINTER_SETTINGS.enabled || !window.PosPrinter) {
            showToast('warning', 'Printer Disabled', 'Receipt printing is not enabled.');
            reprintReceiptModal.hide();
            return;
        }

        const printerStatus = window.PosPrinter.getStatus();
        if (!printerStatus.ready) {
            showToast('warning', 'Printer Not Ready', 'No printer configured. Please visit Printer Setup first.');
            reprintReceiptModal.hide();
            return;
        }

        // Print with preview if enabled
        if (window.PRINTER_SETTINGS.showPreview) {
            const shouldPrint = await window.PosPrinter.showPreview(transactionData);
            if (shouldPrint) {
                await window.PosPrinter.reprint(transactionData, reason);
                showToast('success', 'Receipt Reprinted', `Receipt #${txnCode} has been reprinted.`);
            }
        } else {
            await window.PosPrinter.reprint(transactionData, reason);
            showToast('success', 'Receipt Reprinted', `Receipt #${txnCode} has been reprinted.`);
        }

        reprintReceiptModal.hide();

    } catch (error) {
        console.error('Reprint error:', error);
        showToast('danger', 'Reprint Failed', error.message || 'An error occurred while reprinting the receipt.');
    }
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
        // Wait for theme to initialize flatpickr via datetimepicker class
        setTimeout(function() {
            const dateInput = document.getElementById('filterDate');
            if (dateInput && dateInput._flatpickr) {
                // Hook into flatpickr onChange — fires for both manual date picks AND predefined range buttons
                dateInput._flatpickr.config.onChange.push(function(selectedDates, dateStr, instance) {
                    // Only fire when both start and end are selected (full range)
                    if (selectedDates.length === 2 || (selectedDates.length === 1 && instance.config.mode !== 'range')) {
                        filterTransactions();
                    }
                });

                // Restore filter values from localStorage
                const savedSearch = localStorage.getItem('pos_filter_search');
                const savedType = localStorage.getItem('pos_filter_type');
                let savedStatus = localStorage.getItem('pos_filter_status');
                const savedDate = localStorage.getItem('pos_filter_date');

                // Validate status - 'booked' is not valid for orders, clear it
                if (savedStatus === 'booked') {
                    savedStatus = '';
                    localStorage.removeItem('pos_filter_status');
                }

                if (savedSearch !== null) document.getElementById('filterSearch').value = savedSearch;
                if (savedType !== null) document.getElementById('filterType').value = savedType;
                if (savedStatus !== null && savedStatus !== '') document.getElementById('filterStatus').value = savedStatus;

                // Restore date picker value or set default to today
                if (savedDate) {
                    if (savedDate.includes(' to ')) {
                        const [startDate, endDate] = savedDate.split(' to ');
                        dateInput._flatpickr.setDate([startDate, endDate]);
                    } else {
                        dateInput._flatpickr.setDate(savedDate);
                    }
                } else {
                    // No saved date, set default to today (single day range)
                    const today = new Date();
                    dateInput._flatpickr.setDate([today, today]);
                    localStorage.setItem('pos_filter_date', today.toISOString().split('T')[0] + ' to ' + today.toISOString().split('T')[0]);
                }

                // Only load transactions if we're on the transaction tab
                if (transactionType === 'transaction') {
                    loadRecentTransactions(1);
                } else {
                    const list = document.getElementById('recentTransactionsList');
                    if (list) {
                        list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Switch to Transaction History to view transactions.</td></tr>';
                    }
                }
            }
        }, 300); // Wait for theme to initialize flatpickr
    }
});

// =============================================
// TICKET CANCELLATION
// =============================================

function displayCancellationPolicy() {
    const settings = window.CANCELLATION_SETTINGS || {};
    console.log('Cancellation settings:', settings);

    const processingEl = document.getElementById('cancelPolicyProcessing');
    const approvalEl = document.getElementById('cancelPolicyApproval');

    if (processingEl) {
        const days = settings.refund_processing_days !== undefined ? settings.refund_processing_days : 0;
        if (days === 0) {
            processingEl.textContent = 'Processing: Refund will be processed immediately';
        } else {
            processingEl.textContent = `Processing: Refund will be processed within ${days} day${days > 1 ? 's' : ''}`;
        }
    }

    if (approvalEl) {
        if (settings.requires_confirmation) {
            approvalEl.textContent = 'Approval: Cancellation requires approval';
            approvalEl.className = 'text-warning';
        } else {
            approvalEl.textContent = 'Approval: Cancellation is auto-approved';
            approvalEl.className = 'text-success';
        }
    }
}

function openCancelTicketModalFromButton(button) {
    const txnCode = button.dataset.txnCode;
    const baseAmount = parseFloat(button.dataset.baseAmount);
    const serviceFee = parseFloat(button.dataset.serviceFee);
    const txnData = JSON.parse(decodeURIComponent(button.dataset.txnData));
    openCancelTicketModal(txnCode, baseAmount, serviceFee, txnData);
}

async function openCancelTicketModal(txnCode = '', baseAmount = 0, serviceFee = 0, txnData = null) {
    console.log('openCancelTicketModal called with:', { txnCode, baseAmount, serviceFee, txnData });
    console.log('cancelTicketModal:', cancelTicketModal);

    if (!cancelTicketModal) {
        console.error('cancelTicketModal is not initialized');
        const modalElement = document.getElementById('cancelTicketModal');
        if (modalElement) {
            cancelTicketModal = new bootstrap.Modal(modalElement);
        } else {
            console.error('cancelTicketModal element not found in DOM');
            return;
        }
    }

    // Display cancellation policy based on settings
    displayCancellationPolicy();

    // Reset pending cancellation UI
    const pendingAlert = document.getElementById('pendingCancellationAlert');
    const importantAlert = document.getElementById('cancelImportantAlert');
    const confirmBtn = document.getElementById('confirmCancelBtn');
    const inputs = document.querySelectorAll('#cancelTicketModal input, #cancelTicketModal textarea');

    if (pendingAlert) { pendingAlert.style.display = 'none'; pendingAlert.classList.add('d-none'); }
    if (importantAlert) { importantAlert.style.display = ''; importantAlert.classList.remove('d-none'); }
    if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.style.display = 'inline-block';
    }
    inputs.forEach(input => input.disabled = false);

    // Check for pending cancellation if we have a transaction code
    // Use a request token to discard stale async responses (race condition fix)
    const checkToken = txnCode;
    openCancelTicketModal._lastCheckToken = checkToken;

    if (txnCode) {
        try {
            const response = await fetch(`${window.BASE_URL}/api/pos/check-cancellation-status?transaction_code=${encodeURIComponent(txnCode)}`);
            const result = await response.json();

            // Discard if a newer modal open superseded this request
            if (openCancelTicketModal._lastCheckToken !== checkToken) return;

            if (result.success && result.has_pending_cancellation && result.pending_cancellation) {
                // Show pending cancellation alert
                if (pendingAlert) {
                    pendingAlert.style.display = '';
                    pendingAlert.classList.remove('d-none');
                    document.getElementById('pendingRequestedBy').textContent = result.pending_cancellation.requested_by_name || 'Unknown';
                    const requestedDate = new Date(result.pending_cancellation.requested_at);
                    document.getElementById('pendingRequestedAt').textContent = requestedDate.toLocaleString('en-PH', {
                        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                }

                // Hide important alert
                if (importantAlert) { importantAlert.style.display = 'none'; importantAlert.classList.add('d-none'); }

                // Disable confirm button
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.style.display = 'none';
                }

                // Disable inputs
                inputs.forEach(input => input.disabled = true);
            }
        } catch (e) {
            console.error('Error checking cancellation status:', e);
        }
    }

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

        // Show ticket number
        document.getElementById('cancelTravelDate').textContent = txnData.ticket_number || '-';

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

    // Fetch and display payment breakdown
    const paymentBreakdownDiv = document.getElementById('cancelPaymentBreakdown');
    const refundBreakdownDiv = document.getElementById('cancelRefundBreakdown');
    const paymentMethodsDiv = document.getElementById('cancelPaymentMethods');
    const refundMethodsDiv = document.getElementById('cancelRefundMethods');

    if (txnCode && paymentBreakdownDiv && refundBreakdownDiv) {
        try {
            const response = await fetch(`${window.BASE_URL}/api/pos/transaction-payments.php?transaction_code=${encodeURIComponent(txnCode)}`);
            const result = await response.json();

            if (result.success && result.data && result.data.payments) {
                const payments = result.data.payments;
                const totalAmount = parseFloat(result.data.total_amount) || 0;

                // Display original payment breakdown
                if (payments.length > 0) {
                    paymentMethodsDiv.innerHTML = payments.map(p => {
                        const isCharge = parseInt(p.tracks_credit) === 1;
                        const chargeLabel = isCharge ? ' <span class="badge bg-soft-warning text-warning" style="font-size:0.65rem;">DEBT</span>' : '';
                        return `<div class="d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fas fa-credit-card me-1 text-muted"></i>${p.method_name}${chargeLabel}</span>
                            <span class="fw-semibold">₱${fmt(p.amount)}</span>
                        </div>`;
                    }).join('');
                    paymentBreakdownDiv.style.display = 'block';
                } else {
                    paymentBreakdownDiv.style.display = 'none';
                }

                // Calculate refund distribution based on refund amount
                const refundInput = document.getElementById('cancelRefundAmount');
                const updateRefundBreakdown = () => {
                    const refundAmount = parseFloat(refundInput.value) || 0;
                    if (refundAmount <= 0 || payments.length === 0) {
                        refundBreakdownDiv.style.display = 'none';
                        return;
                    }

                    // User-friendly logic: First fully reverse CHARGE debt, then remaining from cash
                    let remainingRefund = refundAmount;
                    const refundBreakdown = [];

                    // Process CHARGE payments first (debt reversal)
                    const chargePayments = payments.filter(p => parseInt(p.tracks_credit) === 1);
                    for (const p of chargePayments) {
                        const originalAmount = parseFloat(p.amount) || 0;
                        const refundForMethod = Math.min(originalAmount, remainingRefund);
                        if (refundForMethod > 0) {
                            refundBreakdown.push({
                                method_name: p.method_name,
                                tracks_credit: p.tracks_credit,
                                amount: refundForMethod,
                                note: '<span class="text-muted small">(reverses debt)</span>'
                            });
                            remainingRefund -= refundForMethod;
                        }
                    }

                    // Then process other payments (cash, bank, etc.)
                    const otherPayments = payments.filter(p => parseInt(p.tracks_credit) !== 1);
                    for (const p of otherPayments) {
                        if (remainingRefund <= 0) break;
                        const originalAmount = parseFloat(p.amount) || 0;
                        const refundForMethod = Math.min(originalAmount, remainingRefund);
                        if (refundForMethod > 0) {
                            refundBreakdown.push({
                                method_name: p.method_name,
                                tracks_credit: p.tracks_credit,
                                amount: refundForMethod,
                                note: '<span class="text-muted small">(cash refund)</span>'
                            });
                            remainingRefund -= refundForMethod;
                        }
                    }

                    refundMethodsDiv.innerHTML = refundBreakdown.map(r => {
                        const isCharge = parseInt(r.tracks_credit) === 1;
                        const chargeLabel = isCharge ? ' <span class="badge bg-soft-warning text-warning" style="font-size:0.65rem;">DEBT</span>' : '';
                        const cashIcon = !isCharge ? '<i class="fas fa-money-bill-wave text-success me-1"></i>' : '';
                        return `<div class="d-flex justify-content-between align-items-center mb-1">
                            <span>${cashIcon}${r.method_name}${chargeLabel} ${r.note}</span>
                            <span class="fw-semibold">₱${fmt(r.amount)}</span>
                        </div>`;
                    }).join('');

                    // Calculate total cash to give (exclude CHARGE reversals)
                    const totalCashToGive = refundBreakdown
                        .filter(r => parseInt(r.tracks_credit) !== 1)
                        .reduce((sum, r) => sum + r.amount, 0);

                    // Add summary line for cash to give
                    if (totalCashToGive > 0) {
                        refundMethodsDiv.innerHTML += `<div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center fw-bold">
                            <span><i class="fas fa-hand-holding-usd text-success me-1"></i>Total cash to give:</span>
                            <span class="text-success">₱${fmt(totalCashToGive)}</span>
                        </div>`;
                    }

                    refundBreakdownDiv.style.display = 'block';
                };

                // Update refund breakdown on input change
                refundInput.addEventListener('input', updateRefundBreakdown);
                // Initial calculation
                updateRefundBreakdown();
            } else {
                paymentBreakdownDiv.style.display = 'none';
                refundBreakdownDiv.style.display = 'none';
            }
        } catch (e) {
            console.error('Error fetching payment breakdown:', e);
            paymentBreakdownDiv.style.display = 'none';
            refundBreakdownDiv.style.display = 'none';
        }
    } else {
        if (paymentBreakdownDiv) paymentBreakdownDiv.style.display = 'none';
        if (refundBreakdownDiv) refundBreakdownDiv.style.display = 'none';
    }
}

async function confirmCancelTicket() {
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
    
    if (!reason) {
        showToast('danger', 'Error', 'Please enter a reason for cancellation.');
        return;
    }
    
    // Fetch payment breakdown to calculate cash vs debt reversal
    let paymentBreakdown = [];
    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/transaction-payments.php?transaction_code=${encodeURIComponent(txnCode)}`);
        const result = await response.json();
        if (result.success && result.data && result.data.payments) {
            const payments = result.data.payments;
            const totalAmount = parseFloat(result.data.total_amount) || 0;
            
            // Calculate refund distribution (same logic as modal display)
            let remainingRefund = refundAmount;
            
            // Process CHARGE payments first
            const chargePayments = payments.filter(p => parseInt(p.tracks_credit) === 1);
            for (const p of chargePayments) {
                const originalAmount = parseFloat(p.amount) || 0;
                const refundForMethod = Math.min(originalAmount, remainingRefund);
                if (refundForMethod > 0) {
                    paymentBreakdown.push({
                        method_name: p.method_name,
                        tracks_credit: p.tracks_credit,
                        amount: refundForMethod,
                        type: 'charge'
                    });
                    remainingRefund -= refundForMethod;
                }
            }
            
            // Then other payments
            const otherPayments = payments.filter(p => parseInt(p.tracks_credit) !== 1);
            for (const p of otherPayments) {
                if (remainingRefund <= 0) break;
                const originalAmount = parseFloat(p.amount) || 0;
                const refundForMethod = Math.min(originalAmount, remainingRefund);
                if (refundForMethod > 0) {
                    paymentBreakdown.push({
                        method_name: p.method_name,
                        tracks_credit: p.tracks_credit,
                        amount: refundForMethod,
                        type: 'cash'
                    });
                    remainingRefund -= refundForMethod;
                }
            }
        }
    } catch (e) {
        console.error('Error fetching payment breakdown for confirmation:', e);
    }
    
    // Show refund confirmation modal with breakdown
    showRefundConfirmModal(txnCode, refundAmount, reason, paymentBreakdown);
}

function showRefundConfirmModal(txnCode, refundAmount, reason, paymentBreakdown) {
    // Calculate totals
    const totalCash = paymentBreakdown
        .filter(p => p.type === 'cash')
        .reduce((sum, p) => sum + p.amount, 0);
    const totalChargeReversal = paymentBreakdown
        .filter(p => p.type === 'charge')
        .reduce((sum, p) => sum + p.amount, 0);
    
    // Build breakdown HTML
    let breakdownHtml = '';
    if (paymentBreakdown.length > 0) {
        breakdownHtml = `<div class="card border-0 bg-soft-info mb-3">
            <div class="card-body p-3">
                <h6 class="card-title mb-2"><span class="fas fa-list me-2"></span>Refund Breakdown</h6>`;
        
        if (totalChargeReversal > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center mb-1">
                <span><i class="fas fa-file-invoice-dollar text-warning me-1"></i>Charge Debt Reversal (System)</span>
                <span class="fw-semibold text-warning">₱${fmt(totalChargeReversal)}</span>
            </div>
            <div class="small text-muted mb-2">Customer's outstanding balance will be reduced by this amount.</div>`;
        }
        
        if (totalCash > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2">
                <span><i class="fas fa-hand-holding-usd text-success me-1"></i><strong>Total Cash to Give</strong></span>
                <span class="fw-bold text-success">₱${fmt(totalCash)}</span>
            </div>`;
        }
        
        breakdownHtml += `</div></div>`;
    }
    
    // Build confirmation text
    let confirmText = '';
    if (totalCash > 0 && totalChargeReversal > 0) {
        confirmText = `I confirm that I will give ₱${fmt(totalCash)} cash to the passenger, and the system will reverse ₱${fmt(totalChargeReversal)} from their outstanding charge/debt balance.`;
    } else if (totalCash > 0) {
        confirmText = `I confirm that I will give ₱${fmt(totalCash)} cash to the passenger from the cash drawer.`;
    } else if (totalChargeReversal > 0) {
        confirmText = `I confirm that the system will reverse ₱${fmt(totalChargeReversal)} from the passenger's outstanding charge/debt balance (no cash refund).`;
    } else {
        confirmText = `I confirm that I will process this cancellation with refund amount of ₱${fmt(refundAmount)}.`;
    }
    
    const modalHtml = `
        <div class="modal fade" id="refundConfirmModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            <span class="fas fa-money-bill-wave text-warning me-2"></span>
                            Confirm Cancellation & Refund
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <span class="fas fa-exclamation-triangle me-2"></span>
                            <strong>Important:</strong> This action will deduct cash from your drawer and update the customer's charge balance.
                        </div>
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td class="fw-bold">Transaction Code:</td>
                                        <td class="text-end">${txnCode}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Total Refund Amount:</td>
                                        <td class="text-end text-danger fw-bold">₱${fmt(refundAmount)}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Reason:</td>
                                        <td class="text-end">${reason || 'N/A'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        ${breakdownHtml}
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmRefund">
                            <label class="form-check-label" for="confirmRefund">
                                ${confirmText}
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <span class="fas fa-times me-1"></span>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmRefundBtn" disabled
                            data-txn-code="${txnCode.replace(/"/g, '&quot;')}"
                            data-refund-amount="${refundAmount}"
                            data-reason="${reason.replace(/"/g, '&quot;')}">
                            <span class="fas fa-check me-1"></span>Confirm & Cancel Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('refundConfirmModal');
    if (existingModal) existingModal.remove();
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modalElement = document.getElementById('refundConfirmModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Enable confirm button when checkbox is checked
    document.getElementById('confirmRefund').addEventListener('change', function() {
        document.getElementById('confirmRefundBtn').disabled = !this.checked;
    });

    // Attach click handler using data attributes to safely handle special characters in reason
    document.getElementById('confirmRefundBtn').addEventListener('click', function() {
        const code   = this.dataset.txnCode;
        const amount = parseFloat(this.dataset.refundAmount);
        const rsn    = this.dataset.reason;
        executeTicketCancellation(code, amount, rsn);
    });
    
    modal.show();
    
    // Cleanup on hide
    modalElement.addEventListener('hidden.bs.modal', function() {
        modalElement.remove();
    });
}

function executeTicketCancellation(txnCode, refundAmount, reason) {
    // Hide the confirmation modal first
    const refundConfirmModalEl = document.getElementById('refundConfirmModal');
    if (refundConfirmModalEl) {
        const refundConfirmModalInstance = bootstrap.Modal.getInstance(refundConfirmModalEl);
        if (refundConfirmModalInstance) refundConfirmModalInstance.hide();
    }

    // Use the confirmCancelBtn in the cancel ticket modal
    const btn = document.getElementById('confirmCancelBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Processing...';
    }

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
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="fas fa-check me-1"></span>Confirm Cancellation';
        }

        if (data.success) {
            showToast('success', 'Success', data.message);
            if (cancelTicketModal) cancelTicketModal.hide();
            loadRecentTransactions();
            loadWallets(null, window.POS_BRANCH_ID);
        } else {
            showToast('danger', 'Error', data.error || 'Cancellation failed.');
        }
    })
    .catch(error => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="fas fa-check me-1"></span>Confirm Cancellation';
        }
        showToast('danger', 'Error', 'An error occurred during cancellation.');
        console.error('Cancel error:', error);
    });
}
