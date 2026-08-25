// =============================================
// Cashier POS Module
// =============================================

// Number formatter helper
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function isTechnicalIssueReason(reasonCategory) {
    return ['PRINTER_ERROR', 'SYSTEM_ERROR'].includes(String(reasonCategory || '').toUpperCase());
}

function getTicketProviderDetails(providerId, mainProviderId) {
    const providers = window.allTicketProviders || [];
    const operatingProvider = providers.find(provider => String(provider.provider_id) === String(providerId));
    const mainProvider = providers.find(provider => String(provider.provider_id) === String(mainProviderId))
        || providers.find(provider => String(provider.provider_id) === String(operatingProvider?.parent_provider_id));

    return {
        mainProviderId: mainProvider?.provider_id || mainProviderId || providerId || null,
        mainProviderName: mainProvider?.provider_name || operatingProvider?.provider_name || '',
        subProviderName: operatingProvider?.parent_provider_id ? operatingProvider.provider_name : ''
    };
}

function buildTicketDetailBadges(item) {
    const badges = [];
    if (item.mainProviderName) badges.push(`<span class="cart-detail-badge"><span class="fas fa-building me-1"></span>${escapeHtml(item.mainProviderName)}</span>`);
    if (item.subProviderName) badges.push(`<span class="cart-detail-badge cart-detail-badge-sub"><span class="fas fa-sitemap me-1"></span>${escapeHtml(item.subProviderName)}</span>`);
    if (item.variantName) badges.push(`<span class="cart-detail-badge cart-detail-badge-variant"><span class="fas fa-ticket-alt me-1"></span>${escapeHtml(item.variantName)}</span>`);
    if (item.accommodationName) badges.push(`<span class="cart-detail-badge"><span class="fas fa-bed me-1"></span>${escapeHtml(item.accommodationName)}</span>`);
    return badges.join('');
}

function buildTransactionProviderWallet(item) {
    const providerName = item.provider_name || '';
    const parentName = item.parent_provider_name || '';
    const variantName = item.variant_name || '';
    const walletProvider = item.wallet_provider_name || '';
    const walletVariant = item.wallet_variant_name || '';
    const providerType = item.provider_type || '';

    let providerDisplay = providerName;
    if (variantName) {
        providerDisplay = variantName;
    } else if (parentName) {
        providerDisplay = `${parentName} - ${providerName}`;
    }

    let walletDisplay = walletProvider;
    if (walletVariant) {
        walletDisplay = walletDisplay ? `${walletDisplay} - ${walletVariant}` : walletVariant;
    }

    let html = escapeHtml(providerDisplay);
    if (providerType) {
        const typeOptions = window.PROVIDER_TYPE_OPTIONS || {};
        const typeLabel = item.provider_type_label || typeOptions[providerType] || providerType;
        html += ` <span class="badge bg-soft-info text-info" style="font-size:0.75em;">${escapeHtml(typeLabel)}</span>`;
    }
    if (walletDisplay && walletDisplay !== providerDisplay) {
        html += ` <span class="text-muted">(${escapeHtml(walletDisplay)})</span>`;
    }
    return html;
}

/**
 * Show a nice confirmation modal asking if the user wants to print the receipt.
 * Used in manual print mode (when autoPrint is disabled).
 * @param {string} transactionCode
 * @param {number} changeAmount
 * @returns {Promise<{shouldPrint: boolean, copies: number}>}
 */
function showPrintConfirmationModal(transactionCode, changeAmount) {
    return new Promise((resolve) => {
        const defaultCopies = window.PRINTER_SETTINGS?.copies || 1;
        const copiesText = defaultCopies > 1 ? ` (${defaultCopies} copies)` : '';

        const modalHtml = `
            <div class="modal fade" id="printConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><span class="fas fa-check-circle me-2"></span>Transaction Complete</h5>
                        </div>
                        <div class="modal-body py-4">
                            <div class="text-center mb-3">
                                <span class="fas fa-receipt text-success fa-3x mb-2"></span>
                                <h5 class="fw-bold mb-1">Receipt #${transactionCode}</h5>
                                <p class="text-muted small mb-0">Transaction processed successfully.</p>
                                <p class="text-muted small">Change: <span class="fw-bold text-success">₱${fmt(changeAmount)}</span></p>
                            </div>
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">
                                        <span class="fas fa-copy me-1"></span>Number of Copies
                                    </label>
                                    <select class="form-select" id="printConfirmCopies">
                                        <option value="1" ${defaultCopies === 1 ? 'selected' : ''}>1 copy</option>
                                        <option value="2" ${defaultCopies === 2 ? 'selected' : ''}>2 copies</option>
                                        <option value="3" ${defaultCopies === 3 ? 'selected' : ''}>3 copies</option>
                                    </select>
                                    <small class="text-muted">Default from System Settings: ${defaultCopies}.</small>
                                </div>
                                <p class="mb-0 text-center fw-semibold">Would you like to print the receipt?</p>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center border-0">
                            <button type="button" class="btn btn-outline-secondary px-4" onclick="window._printConfirmResult(false)">
                                <span class="fas fa-times me-2"></span>No, Skip
                            </button>
                            <button type="button" class="btn btn-success px-4" onclick="window._printConfirmResult(true)">
                                <span class="fas fa-print me-2"></span>Yes, Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const container = document.createElement('div');
        container.innerHTML = modalHtml;
        document.body.appendChild(container);

        window._printConfirmResult = (shouldPrint) => {
            const copiesEl = document.getElementById('printConfirmCopies');
            const copies = copiesEl ? parseInt(copiesEl.value) || 1 : 1;
            const modalEl = document.getElementById('printConfirmModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            setTimeout(() => {
                if (container.parentNode) document.body.removeChild(container);
                delete window._printConfirmResult;
            }, 300);
            resolve({ shouldPrint, copies });
        };

        const modal = new bootstrap.Modal(document.getElementById('printConfirmModal'));
        modal.show();
    });
}

function hideModalBeforeConfirmation(modal) {
    const element = modal?._element;
    if (!element || !element.classList.contains('show')) return Promise.resolve(false);

    return new Promise(resolve => {
        element.addEventListener('hidden.bs.modal', () => resolve(true), { once: true });
        modal.hide();
    });
}

/**
 * Show a Bootstrap-styled confirmation modal instead of the native alert/confirm.
 * @param {string} title
 * @param {string} message - Newlines will be converted to <br>. Pass options.html = true if message already contains HTML.
 * @param {Object} options
 * @param {string} [options.icon='question'] - Font Awesome icon class suffix (e.g. 'question', 'exclamation-triangle')
 * @param {string} [options.iconColor='text-warning']
 * @param {string} [options.confirmBtnColor='btn-danger']
 * @param {string} [options.confirmBtnText='Confirm']
 * @param {string} [options.cancelBtnText='Cancel']
 * @param {boolean} [options.html=false]
 * @returns {Promise<boolean>}
 */
function showConfirm(title, message, options = {}) {
    return new Promise((resolve) => {
        const icon = options.icon || 'question';
        const iconColor = options.iconColor || 'text-warning';
        const confirmBtnColor = options.confirmBtnColor || 'btn-primary';
        const confirmBtnText = options.confirmBtnText || 'Confirm';
        const cancelBtnText = options.cancelBtnText || 'Cancel';
        const allowHtml = options.html === true;
        const modalId = 'customConfirmModal';

        const existing = document.getElementById(modalId);
        if (existing) {
            const existingModal = bootstrap.Modal.getInstance(existing);
            if (existingModal) existingModal.hide();
            existing.remove();
        }

        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-bottom-0 pb-0">
                            <h5 class="modal-title fw-bold">${escapeHtml(title)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-4">
                            <div class="d-flex align-items-start">
                                <div class="me-3 flex-shrink-0">
                                    <span class="fas fa-${icon} ${iconColor} fa-2x"></span>
                                </div>
                                <div class="fs-10">${allowHtml ? message.replace(/\n/g, '<br>') : escapeHtml(message).replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">${escapeHtml(cancelBtnText)}</button>
                            <button type="button" class="btn ${confirmBtnColor}" id="customConfirmBtn">
                                <span class="fas fa-check me-2"></span>${escapeHtml(confirmBtnText)}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const container = document.createElement('div');
        container.innerHTML = modalHtml;
        document.body.appendChild(container);

        const modalEl = document.getElementById(modalId);
        const modal = new bootstrap.Modal(modalEl);
        let confirmed = false;

        const handleConfirm = () => {
            confirmed = true;
            modal.hide();
        };

        document.getElementById('customConfirmBtn').addEventListener('click', handleConfirm, { once: true });
        modalEl.addEventListener('hidden.bs.modal', () => {
            resolve(confirmed);
            setTimeout(() => {
                if (container.parentNode) document.body.removeChild(container);
            }, 300);
        }, { once: true });

        modal.show();
    });
}

let cart = [];            // Array of cart items
let paymentLines = [];    // Array of payment method entries
let activeServiceType = null;
let activePaymentMethod = null;
let openSessionModal, closeSessionModal, selectCustomerModal, addPassengerModal, viewPassengerModal, switchTypeModal, paymentModal, itemEntryModal, clearCartModal, cancelTicketModal, reprintReceiptModal;
let restoreCancelTicketModal = false;
let selectedCustomerId = null;
let selectedCustomerName = null;
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
    openSessionModal._element.addEventListener('shown.bs.modal', loadCashierTransportAccess);
    closeSessionModal = new bootstrap.Modal(document.getElementById('closeSessionModal'));
    const selectCustomerModalElement = document.getElementById('selectCustomerModal');
    selectCustomerModal = new bootstrap.Modal(selectCustomerModalElement);
    if (selectCustomerModalElement) {
        selectCustomerModalElement.addEventListener('show.bs.modal', function() {
            const visibleModalCount = document.querySelectorAll('.modal.show').length;
            const modalZIndex = 1055 + (visibleModalCount * 20);
            this.style.zIndex = String(modalZIndex);

            window.requestAnimationFrame(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                const latestBackdrop = backdrops[backdrops.length - 1];
                if (latestBackdrop) latestBackdrop.style.zIndex = String(modalZIndex - 5);
            });
        });
        selectCustomerModalElement.addEventListener('hidden.bs.modal', function() {
            this.style.removeProperty('z-index');
        });
    }
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
    setupPosAdjustmentDropdownPortal();
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

    // Toggle order summary collapse icon - initialize when payment modal is shown
    paymentModal._element.addEventListener('shown.bs.modal', function() {
        const orderSummaryCollapse = document.getElementById('paymentCartItemsCollapse');
        const orderSummaryToggleIcon = document.getElementById('orderSummaryToggleIcon');
        if (orderSummaryCollapse && orderSummaryToggleIcon) {
            // Remove existing listeners to avoid duplicates
            orderSummaryCollapse.removeEventListener('show.bs.collapse', handleCollapseShow);
            orderSummaryCollapse.removeEventListener('hide.bs.collapse', handleCollapseHide);
            // Add listeners
            orderSummaryCollapse.addEventListener('show.bs.collapse', handleCollapseShow);
            orderSummaryCollapse.addEventListener('hide.bs.collapse', handleCollapseHide);
        }
    });

    function handleCollapseShow() {
        const icon = document.getElementById('orderSummaryToggleIcon');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    }

    function handleCollapseHide() {
        const icon = document.getElementById('orderSummaryToggleIcon');
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
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

    // Load discount types, accommodation types and providers
    loadDiscountTypes();
    loadAccommodationTypes();
    loadProviders();

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
    updateCustomerSearchClearButton(searchTerm);
    if (!searchTerm.trim()) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Search by account name or mobile number to load accounts.</td></tr>';
        document.getElementById('confirmCustomerBtn').disabled = true;
        return;
    }
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
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No accounts found. Start typing to search.</td></tr>';
                document.getElementById('confirmCustomerBtn').disabled = true;
                return;
            }

            let hasSelectedCustomer = false;
            data.data.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'customer-row';
                tr.style.cursor = 'pointer';
                tr.tabIndex = 0;
                tr.setAttribute('role', 'button');
                tr.dataset.search = (p.fullname + ' ' + (p.mobile_number || '')).toLowerCase();
                tr.dataset.passengerId = p.passenger_id;
                const isSelected = selectedCustomerId && String(p.passenger_id) === String(selectedCustomerId);
                if (isSelected) hasSelectedCustomer = true;
                tr.innerHTML = `
                    <td class="fw-semibold">${p.fullname}</td>
                    <td>${p.mobile_number || '—'}</td>
                    <td class="text-end">₱0.00</td>
                    <td class="text-center">
                        <input type="radio" name="selectedCustomer" value="${p.passenger_id}" ${isSelected ? 'checked' : ''} onchange="selectCustomerRadio(this)">
                    </td>
                `;
                if (isSelected) tr.classList.add('table-primary');
                const selectRow = () => {
                    const radio = tr.querySelector('input[name="selectedCustomer"]');
                    if (!radio) return;
                    radio.checked = true;
                    selectCustomerRadio(radio);
                };
                tr.addEventListener('click', selectRow);
                tr.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectRow();
                    }
                });
                tbody.appendChild(tr);
            });
            document.getElementById('confirmCustomerBtn').disabled = !hasSelectedCustomer;
        })
        .catch(error => {
            console.error('Error fetching passengers:', error);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load accounts.</td></tr>';
            document.getElementById('confirmCustomerBtn').disabled = true;
        });
}

function updateCustomerSearchClearButton(searchTerm = null) {
    const searchInput = document.getElementById('customerSearch');
    const clearButton = document.getElementById('clearCustomerSearchBtn');
    if (!searchInput || !clearButton) return;

    const value = searchTerm === null ? searchInput.value : searchTerm;
    clearButton.style.display = value.trim() ? '' : 'none';
}

function clearCustomerSearch() {
    const searchInput = document.getElementById('customerSearch');
    if (!searchInput) return;

    clearTimeout(window.customerSearchTimeout);
    searchInput.value = '';
    updateCustomerSearchClearButton('');
    renderCustomers('');
    searchInput.focus();
}

function searchCustomers(searchTerm) {
    updateCustomerSearchClearButton(searchTerm);
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
                select.innerHTML = '';
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
                select.innerHTML = '';
                data.data.forEach(a => {
                    const option = document.createElement('option');
                    option.value = a.accommodation_id;
                    option.textContent = `${a.name}`;
                    if (a.is_default === 1) {
                        option.dataset.isDefault = 'true';
                    }
                    select.appendChild(option);
                });
                // Select default accommodation
                const defaultOption = select.querySelector('[data-is-default="true"]');
                if (defaultOption) {
                    select.value = defaultOption.value;
                }
            } else if (data.error && data.error.includes('Permission denied')) {
                showAlert('error', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading accommodation types:', error);
        });
}

function getPosProviderSignature(providers) {
    return JSON.stringify((providers || []).map(provider => [
        provider.provider_id,
        provider.provider_code,
        provider.provider_name,
        provider.provider_type,
        provider.parent_provider_id,
        provider.status,
        provider.variant_count
    ]));
}

function loadProviders() {
    const mainSelect = document.getElementById('ticketMainProvider');
    const subSelect = document.getElementById('ticketSubProvider');
    const operatingInput = document.getElementById('ticketProvider');
    const walletSelect = document.getElementById('ticketWallet');

    destroyMainProviderChoices();
    destroySubProviderChoices();

    const branchId = Number(window.POS_BRANCH_ID) > 0 ? parseInt(window.POS_BRANCH_ID, 10) : null;
    const providersPromise = fetch(`${window.BASE_URL}/api/ticket-providers`).then(r => r.json());
    const walletsUrl = branchId
        ? `${window.BASE_URL}/api/wallets?branch_id=${IdEncoder.encode(branchId)}`
        : null;

    const walletsPromise = branchId
        ? fetch(walletsUrl).then(r => r.json())
        : Promise.resolve({ success: true, data: { wallets: [] } });

    console.log('[POS loadProviders] POS_BRANCH_ID:', window.POS_BRANCH_ID, 'walletsUrl:', walletsUrl || 'not loaded');

    return Promise.all([providersPromise, walletsPromise])
        .then(([providersData, walletsData]) => {
            console.log('[POS loadProviders] walletsData:', walletsData);
            const walletBalances = {};
            window.providerHasVariantWallet = {};
            if (walletsData.success && walletsData.data && Array.isArray(walletsData.data.wallets)) {
                walletsData.data.wallets.forEach(w => {
                    if (!branchId || String(w.branch_id) !== String(branchId) || w.status !== 'active') return;
                    const pid = w.ticket_provider_id || w.provider_id;
                    if (pid) {
                        walletBalances[pid] = (walletBalances[pid] || 0) + (parseFloat(w.current_balance) || 0);
                        if (w.status === 'active' && w.variant_id) {
                            window.providerHasVariantWallet[String(pid)] = true;
                        }
                    }
                });
            }

            if (providersData.success && providersData.data && providersData.data.providers) {
                window.allTicketProviders = providersData.data.providers.filter(p => p.status === 'active');
                window.posProviderSignature = getPosProviderSignature(window.allTicketProviders);

                mainSelect.innerHTML = '<option value="">Select Main Provider</option>';
                window.allTicketProviders
                    .filter(p => !p.parent_provider_id)
                    .forEach(p => {
                        let hasWallet = false;
                        let totalBalance = 0;

                        if (walletBalances[p.provider_id] !== undefined) {
                            hasWallet = true;
                            totalBalance += walletBalances[p.provider_id];
                        }

                        window.allTicketProviders
                            .filter(v => v.parent_provider_id == p.provider_id)
                            .forEach(v => {
                                if (walletBalances[v.provider_id] !== undefined) {
                                    hasWallet = true;
                                    totalBalance += walletBalances[v.provider_id];
                                }
                            });

                        let balanceText;
                        if (hasWallet) {
                            balanceText = `₱${fmt(totalBalance)}`;
                        } else {
                            balanceText = 'No wallet';
                        }
                        const option = document.createElement('option');
                        option.value = p.provider_id;
                        option.dataset.providerCode = p.provider_code || '';
                        option.dataset.providerType = p.provider_type || '';
                        option.text = p.provider_name + '\u001F' + balanceText;
                        mainSelect.appendChild(option);
                    });
            } else if (providersData.error && providersData.error.includes('Permission denied')) {
                showAlert('error', providersData.error);
            }

            // Reset dependent dropdowns
            subSelect.innerHTML = '<option value="">Select Main Provider First</option>';
            subSelect.disabled = true;
            document.getElementById('subProviderWrapper').classList.add('d-none');
            operatingInput.value = '';
            walletSelect.innerHTML = '<option value="">Select Provider First</option>';
            walletSelect.disabled = true;
            const mainProviderBalanceText = document.getElementById('mainProviderBalanceText');
            if (mainProviderBalanceText) mainProviderBalanceText.textContent = '';
            refreshMainProviderChoices();
            destroySubProviderChoices();
        })
        .catch(error => {
            console.error('Error loading providers:', error);
            mainSelect.innerHTML = '<option value="">Error loading providers</option>';
            subSelect.innerHTML = '<option value="">Error</option>';
            walletSelect.innerHTML = '<option value="">Error</option>';
            walletSelect.disabled = true;
            const mainProviderBalanceText = document.getElementById('mainProviderBalanceText');
            if (mainProviderBalanceText) mainProviderBalanceText.textContent = '';
            refreshMainProviderChoices();
            destroySubProviderChoices();
        });
}

let posWalletRefreshInFlight = null;
let posProviderRefreshInFlight = null;

function refreshPosSubProviderOptions(mainId, selectedSubId) {
    const subSelect = document.getElementById('ticketSubProvider');
    const subWrapper = document.getElementById('subProviderWrapper');
    const operatingInput = document.getElementById('ticketProvider');
    if (!subSelect || !subWrapper || !operatingInput) return;

    const subs = (window.allTicketProviders || []).filter(provider => String(provider.parent_provider_id) === String(mainId));
    if (subs.length > 0) {
        subWrapper.classList.remove('d-none');
        subSelect.innerHTML = '<option value="">Select Sub-provider</option>';
        subs.forEach(provider => {
            const option = document.createElement('option');
            option.value = provider.provider_id;
            option.textContent = provider.provider_name;
            subSelect.appendChild(option);
        });
        subSelect.disabled = false;
        const nextSubId = subs.some(provider => String(provider.provider_id) === String(selectedSubId))
            ? String(selectedSubId)
            : '';
        subSelect.value = nextSubId;
        operatingInput.value = nextSubId;
        refreshSubProviderChoices();
        if (nextSubId && subProviderChoices) subProviderChoices.setChoiceByValue(nextSubId);
    } else {
        subWrapper.classList.add('d-none');
        subSelect.innerHTML = '<option value="">No sub-providers</option>';
        subSelect.disabled = true;
        destroySubProviderChoices();
        operatingInput.value = mainId || '';
    }
}

function refreshPosProviderOptions() {
    if (posProviderRefreshInFlight) return posProviderRefreshInFlight;

    const mainSelect = document.getElementById('ticketMainProvider');
    if (!mainSelect) return Promise.resolve();

    const selectedMainId = mainSelect.value || '';
    const selectedSubId = document.getElementById('ticketSubProvider')?.value || '';
    const providersUrl = `${window.BASE_URL}/api/ticket-providers?_realtime=${Date.now()}`;

    posProviderRefreshInFlight = fetch(providersUrl, { cache: 'no-store' })
        .then(response => {
            if (!response.ok) throw new Error(`Provider refresh failed (${response.status})`);
            return response.json();
        })
        .then(data => {
            if (!data.success || !data.data || !Array.isArray(data.data.providers)) {
                throw new Error(data.error || 'Unable to load providers.');
            }

            const providers = data.data.providers.filter(provider => provider.status === 'active');
            const providerSignature = getPosProviderSignature(providers);
            const providerListChanged = providerSignature !== window.posProviderSignature;
            const selectedMainExists = !selectedMainId
                || providers.some(provider => String(provider.provider_id) === String(selectedMainId));
            const nextMainId = selectedMainExists ? selectedMainId : '';
            window.allTicketProviders = providers;
            if (!providerListChanged) return;
            window.posProviderSignature = providerSignature;

            destroyMainProviderChoices();
            mainSelect.innerHTML = '<option value="">Select Main Provider</option>';
            providers
                .filter(provider => !provider.parent_provider_id)
                .forEach(provider => {
                    const option = document.createElement('option');
                    option.value = provider.provider_id;
                    option.dataset.providerCode = provider.provider_code || '';
                    option.dataset.providerType = provider.provider_type || '';
                    option.text = provider.provider_name + '\u001F' + 'No wallet';
                    mainSelect.appendChild(option);
                });

            if (nextMainId) mainSelect.value = nextMainId;
            refreshMainProviderChoices();
            if (nextMainId && mainProviderChoices) mainProviderChoices.setChoiceByValue(nextMainId);

            if (selectedMainId && !nextMainId) {
                onMainProviderChanged();
            } else {
                refreshPosSubProviderOptions(nextMainId, selectedSubId);
            }
        })
        .finally(() => {
            posProviderRefreshInFlight = null;
        });

    return posProviderRefreshInFlight;
}

function refreshPosProviderAndWallet() {
    return refreshPosProviderOptions()
        .catch(error => {
            console.error('[POS realtime] Provider refresh failed:', error);
        })
        .then(() => refreshPosWalletBalances());
}

function setPosRealtimeStatus(state, message) {
    const status = document.getElementById('posRealtimeStatus');
    if (!status) return;
    status.classList.toggle('text-danger', state === 'error');
    status.classList.toggle('text-warning', state === 'warning');
    status.classList.toggle('text-muted', state !== 'error' && state !== 'warning');
    status.textContent = message;
}

function updateDisplayedWalletBalances(wallets) {
    const mainSelect = document.getElementById('ticketMainProvider');
    const providers = window.allTicketProviders || [];
    if (!mainSelect || !providers.length) return;

    const balances = {};
    const stockTotals = {};
    const branchId = window.POS_BRANCH_ID || '';
    (wallets || []).forEach(wallet => {
        if (!branchId || String(wallet.branch_id) !== String(branchId) || wallet.status !== 'active') return;
        const providerId = wallet.ticket_provider_id || wallet.provider_id;
        if (providerId) {
            balances[String(providerId)] = (balances[String(providerId)] || 0) + (parseFloat(wallet.current_balance) || 0);
            stockTotals[String(providerId)] = (stockTotals[String(providerId)] || 0) + (parseInt(wallet.on_hand_qty, 10) || 0);
        }
    });

    Array.from(mainSelect.options).forEach(option => {
        if (!option.value) return;
        const provider = providers.find(item => String(item.provider_id) === String(option.value));
        if (!provider) return;

        let hasWallet = Object.prototype.hasOwnProperty.call(balances, String(provider.provider_id));
        let totalBalance = balances[String(provider.provider_id)] || 0;
        let totalStock = stockTotals[String(provider.provider_id)] || 0;
        providers.filter(item => String(item.parent_provider_id) === String(provider.provider_id)).forEach(child => {
            if (Object.prototype.hasOwnProperty.call(balances, String(child.provider_id))) {
                hasWallet = true;
                totalBalance += balances[String(child.provider_id)];
            }
            if (Object.prototype.hasOwnProperty.call(stockTotals, String(child.provider_id))) {
                totalStock += stockTotals[String(child.provider_id)];
            }
        });

        const balanceText = !hasWallet
            ? 'No wallet'
            : (parseInt(provider.variant_count, 10) > 0 ? `${totalStock} tickets` : `₱${fmt(totalBalance)}`);
        option.text = provider.provider_name + '\u001F' + balanceText;
    });

    const selectedMain = mainSelect.value;
    if (mainProviderChoices) {
        refreshMainProviderChoices();
        if (selectedMain) mainProviderChoices.setChoiceByValue(selectedMain);
    }
}

function refreshPosWalletBalances() {
    if (posWalletRefreshInFlight) return posWalletRefreshInFlight;

    const branchId = Number(window.POS_BRANCH_ID) > 0 ? parseInt(window.POS_BRANCH_ID, 10) : null;
    if (!branchId) return Promise.resolve();

    const url = `${window.BASE_URL}/api/wallets?branch_id=${IdEncoder.encode(branchId)}&_realtime=${Date.now()}`;
    posWalletRefreshInFlight = fetch(url, { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.data || !Array.isArray(data.data.wallets)) {
                throw new Error(data.error || 'Unable to load wallet balances.');
            }
            window.providerHasVariantWallet = {};
            data.data.wallets.forEach(wallet => {
                if (!branchId || String(wallet.branch_id) !== String(branchId)) return;
                const providerId = wallet.ticket_provider_id || wallet.provider_id;
                if (providerId && wallet.status === 'active' && wallet.variant_id) {
                    window.providerHasVariantWallet[String(providerId)] = true;
                }
            });
            updateDisplayedWalletBalances(data.data.wallets);
            const providerId = document.getElementById('ticketProvider')?.value;
            const variantId = document.getElementById('ticketVariant')?.value;
            if (providerId) return loadWallets(providerId, window.POS_BRANCH_ID, variantId || null);
        })
        .then(() => {
            setPosRealtimeStatus('ok', `Live updates • ${new Date().toLocaleTimeString('en-PH')}`);
        })
        .catch(error => {
            console.error('[POS realtime] Wallet refresh failed:', error);
            setPosRealtimeStatus('error', 'Live update unavailable; showing last known data');
        })
        .finally(() => {
            posWalletRefreshInFlight = null;
        });

    return posWalletRefreshInFlight;
}

function stopPosRealtimePolling() {
    if (window.posRealtimeTimer) {
        clearInterval(window.posRealtimeTimer);
        window.posRealtimeTimer = null;
    }
}

function refreshPosRealtimeData(includeProviders = false) {
    if (document.visibilityState !== 'visible') return;
    if (includeProviders) {
        refreshPosProviderAndWallet();
    } else {
        refreshPosWalletBalances();
    }
    // Also refresh variant balances if a provider is selected so the Ticket
    // Details wallet display stays current (e.g. after a cancellation is approved).
    const providerId = document.getElementById('ticketProvider')?.value;
    if (providerId) loadTicketVariants();
    if (transactionType === 'transaction') loadRecentTransactions(currentPage, false);
}

function startPosRealtimeFallbackPolling() {
    if (!window.POS_HAS_SESSION || window.posRealtimeTimer) return;

    window.posRealtimeTimer = setInterval(() => refreshPosRealtimeData(true), 15000);
    if (!window.posRealtimeVisibilityHandler) {
        window.posRealtimeVisibilityHandler = () => {
            if (document.visibilityState === 'visible') refreshPosRealtimeData(true);
        };
        document.addEventListener('visibilitychange', window.posRealtimeVisibilityHandler);
    }
    refreshPosRealtimeData(true);
}

function startPosRealtimeChannels() {
    if (!window.POS_HAS_SESSION || window.posRealtimeChannel || !window.PUSHER_CONFIG?.enabled || typeof Pusher === 'undefined' || !window.POS_BRANCH_ID) {
        return;
    }

    const startFallback = () => {
        window.posRealtimeChannelConnected = false;
        setPosRealtimeStatus('error', 'Live updates unavailable; polling fallback active');
        startPosRealtimeFallbackPolling();
    };

    try {
        const pusher = new Pusher(window.PUSHER_CONFIG.key, {
            cluster: window.PUSHER_CONFIG.cluster,
            forceTLS: true,
            authEndpoint: window.PUSHER_CONFIG.authEndpoint,
            auth: { withCredentials: true }
        });
        const channel = pusher.subscribe(`private-pos-branch-${window.POS_BRANCH_ID}`);
        channel.bind('pusher:subscription_succeeded', () => {
            window.posRealtimeChannel = channel;
            window.posRealtimeChannelConnected = true;
            stopPosRealtimePolling();
            setPosRealtimeStatus('ok', 'Live updates connected');
        });
        channel.bind('pos.transaction.completed', () => {
            if (!window.posRealtimeChannelConnected) return;
            refreshPosRealtimeData();
        });
        channel.bind('provider.updated', () => {
            if (!window.posRealtimeChannelConnected) return;
            refreshPosProviderAndWallet();
        });
        channel.bind('pusher:subscription_error', (status) => {
            console.warn('[POS realtime] Pusher subscription failed:', status);
            startFallback();
        });
        pusher.connection.bind('state_change', (states) => {
            if (states.current === 'connected') {
                window.posRealtimeChannelConnected = true;
                stopPosRealtimePolling();
                setPosRealtimeStatus('ok', 'Live updates connected');
            } else if (['disconnected', 'unavailable', 'failed'].includes(states.current)) {
                startFallback();
            }
        });
        window.posRealtimePusher = pusher;
    } catch (error) {
        console.error('[POS realtime] Pusher initialization failed:', error);
        startFallback();
    }
}

function startPosRealtimePolling() {
    if (!window.POS_HAS_SESSION) {
        setPosRealtimeStatus('warning', window.POS_CAN_OPEN ? 'Open a cashier session to enable live updates' : 'Live updates paused — no active session');
        return;
    }

    const pusherAvailable = window.PUSHER_CONFIG?.enabled && typeof Pusher !== 'undefined';
    if (pusherAvailable) {
        setPosRealtimeStatus('ok', 'Live updates connecting...');
        refreshPosRealtimeData();
        startPosRealtimeChannels();
    } else {
        setPosRealtimeStatus('ok', 'Live updates starting...');
        startPosRealtimeFallbackPolling();
    }
}

function onMainProviderChanged() {
    const mainSelect = document.getElementById('ticketMainProvider');
    const subSelect = document.getElementById('ticketSubProvider');
    const subWrapper = document.getElementById('subProviderWrapper');
    const operatingInput = document.getElementById('ticketProvider');
    const variantWrapper = document.getElementById('ticketVariantWrapper');
    const variantSelect = document.getElementById('ticketVariant');
    const mainId = mainSelect.value;
    destroySubProviderChoices();

    // Reset everything
    const walletWrapper = document.getElementById('walletWrapper');

    if (!mainId) {
        subWrapper.classList.add('d-none');
        subSelect.innerHTML = '<option value="">Select Main Provider First</option>';
        operatingInput.value = '';
        variantWrapper.classList.add('d-none');
        if (variantSelect) variantSelect.value = '';
        if (walletWrapper) walletWrapper.classList.remove('d-none');
        destroyVariantChoices();
        destroySubProviderChoices();
        onProviderChanged();
        return;
    }

    const subs = (window.allTicketProviders || []).filter(p => String(p.parent_provider_id) === mainId);
    const mainProvider = (window.allTicketProviders || []).find(p => String(p.provider_id) === mainId);
    const hasVariants = parseInt(mainProvider?.variant_count, 10) > 0;

    if (subs.length > 0) {
        // Main has sub-providers → show sub dropdown, hide variant
        subWrapper.classList.remove('d-none');
        subSelect.innerHTML = '<option value="">Select Sub-provider</option>';
        subs.forEach(p => {
            const option = document.createElement('option');
            option.value = p.provider_id;
            option.textContent = p.provider_name;
            subSelect.appendChild(option);
        });
        subSelect.disabled = false;
        refreshSubProviderChoices();
        operatingInput.value = '';
        if (variantSelect) variantSelect.value = '';
        variantWrapper.classList.add('d-none');
        destroyVariantChoices();
        // Load main provider wallet and service fee immediately
        if (walletWrapper) walletWrapper.classList.add('d-none');
        loadWallets(mainId, null, null).then(() => {
            const wallet = window.selectedResolvedWallet;
            const walletBranchId = wallet ? wallet.branch_id : null;
            loadServiceFeeForProvider(mainId, walletBranchId);
        });
    } else {
        // Main has no sub-providers → hide sub, load variants
        subWrapper.classList.add('d-none');
        subSelect.innerHTML = '<option value="">No sub-providers</option>';
        subSelect.disabled = true;
        destroySubProviderChoices();
        operatingInput.value = mainId;

        if (hasVariants) {
            // Variants exist → show variant dropdown, hide wallet (balance shown per variant)
            if (walletWrapper) walletWrapper.classList.add('d-none');
            onProviderChanged();
        } else {
            // No variants → show wallet dropdown
            if (walletWrapper) walletWrapper.classList.remove('d-none');
            if (variantSelect) {
                variantSelect.value = '';
                variantSelect.innerHTML = '<option value="">No variants available</option>';
                variantSelect.disabled = true;
                destroyVariantChoices();
            }
            variantWrapper.classList.add('d-none');
            loadWallets().then(() => {
                const wallet = window.selectedResolvedWallet;
                const walletBranchId = wallet ? wallet.branch_id : null;
                loadServiceFeeForProvider(null, walletBranchId);
            });
        }
    }
}

function onSubProviderChanged() {
    const mainSelect = document.getElementById('ticketMainProvider');
    const subSelect = document.getElementById('ticketSubProvider');
    const operatingInput = document.getElementById('ticketProvider');
    const variantWrapper = document.getElementById('ticketVariantWrapper');
    const variantSelect = document.getElementById('ticketVariant');
    const walletWrapper = document.getElementById('walletWrapper');

    operatingInput.value = subSelect.value || mainSelect.value || '';

    // Sub-providers don't have variants and share the main wallet — hide both
    if (variantWrapper) variantWrapper.classList.add('d-none');
    if (variantSelect) variantSelect.value = '';
    if (walletWrapper) walletWrapper.classList.add('d-none');
    destroyVariantChoices();

    // Update balance text using main wallet (WalletResolver falls back to parent)
    loadWallets().then(() => {
        const wallet = window.selectedResolvedWallet;
        const walletBranchId = wallet ? wallet.branch_id : null;
        loadServiceFeeForProvider(null, walletBranchId);
    });
}

function refreshWallets() {
    const providerId = document.getElementById('ticketProvider')?.value;
    const variantId = document.getElementById('ticketVariant')?.value;
    loadWallets(providerId || null, window.POS_BRANCH_ID, variantId || null);
}

function loadWallets(providerId = null, branchId = null, variantId = null) {
    const select = document.getElementById('ticketWallet');
    window.selectedResolvedWallet = null;

    // Use the currently selected provider/variant if none passed
    if (!providerId) {
        providerId = document.getElementById('ticketProvider')?.value || null;
    }
    if (!variantId) {
        variantId = document.getElementById('ticketVariant')?.value || null;
    }

    const userBranchId = branchId || window.POS_BRANCH_ID || null;
    const mainProviderBalanceText = document.getElementById('mainProviderBalanceText');

    if (!providerId || !userBranchId) {
        select.innerHTML = '<option value="">Select Provider First</option>';
        select.disabled = true;
        if (mainProviderBalanceText) mainProviderBalanceText.textContent = '';
        return Promise.resolve();
    }

    const hasSubs = (window.allTicketProviders || []).some(p => String(p.parent_provider_id) === String(providerId));
    if (!variantId && window.providerHasVariantWallet?.[String(providerId)] && !hasSubs) {
        select.innerHTML = '<option value="">Select Variant First</option>';
        select.disabled = true;
        window.selectedResolvedWallet = null;
        if (mainProviderBalanceText) mainProviderBalanceText.textContent = 'Select a variant to see wallet';
        return Promise.resolve();
    }

    const providerIdNum = parseInt(providerId, 10);
    const branchIdNum = userBranchId ? parseInt(userBranchId, 10) : null;
    const variantIdNum = variantId ? parseInt(variantId, 10) : null;

    let url = `${window.BASE_URL}/api/wallets?resolve=1&provider_id=${IdEncoder.encode(providerIdNum)}`;
    if (branchIdNum) {
        url += `&branch_id=${IdEncoder.encode(branchIdNum)}`;
    }
    if (variantIdNum) {
        url += `&variant_id=${IdEncoder.encode(variantIdNum)}`;
    }

    console.log('[POS loadWallets] provider:', providerIdNum, 'branch:', userBranchId, 'variant:', variantIdNum, 'url:', url);

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('[POS loadWallets] response:', data);
            if (data.success && data.data && data.data.resolved && data.data.wallet) {
                const w = data.data.wallet;
                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = w.wallet_id;
                option.dataset.providerId = w.provider_id;
                option.dataset.branchId = w.branch_id;
                option.dataset.providerType = w.provider_type || '';
                option.dataset.balance = w.current_balance;
                option.dataset.onHandQty = w.on_hand_qty || 0;
                option.textContent = w.variant_id
                    ? `${w.wallet_name || 'Wallet #' + w.wallet_id} • ${Number(w.on_hand_qty || 0)} tickets`
                    : `${w.wallet_name || 'Wallet #' + w.wallet_id} • ₱${fmt(parseFloat(w.current_balance))}`;
                select.appendChild(option);
                select.disabled = true;
                window.selectedResolvedWallet = w;
                if (mainProviderBalanceText) {
                    mainProviderBalanceText.textContent = w.variant_id
                        ? `Stock: ${Number(w.on_hand_qty || 0)} tickets`
                        : `Balance: ₱${fmt(parseFloat(w.current_balance))}`;
                }
            } else if (data.error) {
                const branchLabel = userBranchId ? ` (branch ${userBranchId})` : '';
                select.innerHTML = '<option value="">' + (data.error.includes('Permission denied') ? 'Wallet access restricted' : ('No wallet found' + branchLabel)) + '</option>';
                select.disabled = true;
                window.selectedResolvedWallet = null;
                if (mainProviderBalanceText) mainProviderBalanceText.textContent = '';
                if (data.error.includes('Permission denied')) {
                    showAlert('error', data.error);
                }
            }
        })
        .catch(error => {
            console.error('Error loading wallets:', error);
            select.innerHTML = '<option value="">Error loading wallets</option>';
            select.disabled = true;
            window.selectedResolvedWallet = null;
            if (mainProviderBalanceText) mainProviderBalanceText.textContent = '';
        });
}

function onProviderChanged() {
    const variantSelect = document.getElementById('ticketVariant');
    if (variantSelect) variantSelect.value = '';
    loadTicketVariants();
    loadWallets().then(() => {
        const wallet = window.selectedResolvedWallet;
        const walletBranchId = wallet ? wallet.branch_id : null;
        loadServiceFeeForProvider(null, walletBranchId);
    });
}

let mainProviderChoices = null;
let subProviderChoices = null;

function destroyMainProviderChoices() {
    if (mainProviderChoices) {
        try { mainProviderChoices.destroy(); } catch(e) {}
        mainProviderChoices = null;
    }
}

function destroySubProviderChoices() {
    if (subProviderChoices) {
        try { subProviderChoices.destroy(); } catch(e) {}
        subProviderChoices = null;
    }
}

function refreshSubProviderChoices() {
    const select = document.getElementById('ticketSubProvider');
    if (!select || typeof Choices === 'undefined' || select.disabled) {
        destroySubProviderChoices();
        return;
    }
    destroySubProviderChoices();
    subProviderChoices = new Choices(select, {
        searchEnabled: false,
        shouldSort: false,
        itemSelectText: '',
        allowHTML: false,
        removeItemButton: false,
        position: 'auto',
        resetScrollPosition: false
    });
}

function refreshMainProviderChoices() {
    const select = document.getElementById('ticketMainProvider');
    if (!select) return;
    destroyMainProviderChoices();

    mainProviderChoices = new Choices(select, {
        searchEnabled: false,
        shouldSort: false,
        itemSelectText: '',
        allowHTML: true,
        removeItemButton: false,
        position: 'auto',
        resetScrollPosition: false,
        callbackOnCreateTemplates: function() {
            function buildTwoRow(config, data, isChoice, itemSelectText, oneRow) {
                const label = data.label || '';
                const parts = label.split('\u001F');
                const name = parts[0] || label;
                const amount = parts[1] || '';

                const classes = [].concat(config.classNames.item);
                if (isChoice) classes.push(...config.classNames.itemChoice);
                classes.push(...config.classNames.itemSelectable);
                if (data.selected) classes.push(...config.classNames.selectedState);
                if (data.disabled) classes.push(...config.classNames.itemDisabled);
                if (data.placeholder) classes.push(...config.classNames.placeholder);

                const div = document.createElement('div');
                div.className = classes.filter(Boolean).join(' ');

                if (isChoice) {
                    div.id = data.elementId;
                    div.dataset.choice = '';
                    div.dataset.selectText = itemSelectText || '';
                    div.setAttribute('role', data.group ? 'treeitem' : 'option');
                } else {
                    div.dataset.item = '';
                    if (data.active) div.setAttribute('aria-selected', 'true');
                }

                div.dataset.id = data.id;
                div.dataset.value = data.value;
                if (data.disabled) div.setAttribute('aria-disabled', 'true');

                if (oneRow) {
                    const separator = amount ? `<span style='flex:0 0 auto;'>&nbsp;•&nbsp;</span>` : '';
                    div.innerHTML = `<div style='display:flex;align-items:center;gap:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'><div class='mp-row-name' style='flex:0 1 auto;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>${escapeHtml(name)}</div>${separator}<div class='mp-row-amount' style='flex:0 1 auto;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:"Century Gothic",CenturyGothic,AppleGothic,Arial,sans-serif;font-size:0.65rem !important;font-weight:700 !important;line-height:1.3 !important;margin-top:2px !important;'>${escapeHtml(amount)}</div></div>`;
                } else {
                    div.innerHTML = `<div class='mp-row-name'>${escapeHtml(name)}</div>${amount ? `<div class='mp-row-amount' style='font-family:"Century Gothic",CenturyGothic,AppleGothic,Arial,sans-serif;font-size:0.65rem !important;font-weight:700 !important;line-height:1.3 !important;margin-top:2px !important;'>${escapeHtml(amount)}</div>` : ''}`;
                }

                return div;
            }

            return {
                item: function(config, data) {
                    return buildTwoRow(config, data, false, null, true);
                },
                choice: function(config, data, itemSelectText) {
                    return buildTwoRow(config, data, true, itemSelectText, false);
                }
            };
        }
    });
}

let bankAccountChoices = null;
let bankAccountChoiceData = [];

function getBankAccountChoiceData(select) {
    return Array.from(select.options).map(option => ({
        value: option.value,
        label: option.textContent.trim(),
        selected: option.selected,
        disabled: option.disabled,
        customProperties: {
            methodType: option.dataset.methodType || '',
            bankName: option.dataset.bankName || '',
            accountName: option.dataset.accountName || '',
            accountType: option.dataset.accountType || '',
            balance: option.dataset.balance || ''
        }
    }));
}

function getBankAccountDetails(select, data) {
    const props = data.customProperties || {};
    const option = Array.from(select.options).find(item => String(item.value) === String(data.value));
    const fallbackParts = (data.label || '').split('\u001F');
    const fallbackNameParts = (fallbackParts[0] || '').split(' — ');

    return {
        bankName: props.bankName || option?.dataset.bankName || fallbackNameParts[0] || data.label || '',
        accountName: props.accountName || option?.dataset.accountName || fallbackNameParts[1] || fallbackParts[1] || '',
        accountType: props.accountType || option?.dataset.accountType || fallbackParts[2] || '',
        balance: props.balance || option?.dataset.balance || fallbackParts[3] || '₱0.00'
    };
}

function createBankAccountChoices(select) {
    return new Choices(select, {
        searchEnabled: false,
        shouldSort: false,
        itemSelectText: '',
        allowHTML: true,
        removeItemButton: false,
        position: 'auto',
        resetScrollPosition: false,
        callbackOnCreateTemplates: function() {
            function buildBankAccountRow(config, data, isChoice, itemSelectText, compact) {
                const classes = [].concat(config.classNames.item);
                if (isChoice) classes.push(...config.classNames.itemChoice);
                classes.push(...config.classNames.itemSelectable);
                if (data.selected) classes.push(...config.classNames.selectedState);
                if (data.disabled) classes.push(...config.classNames.itemDisabled);
                if (data.placeholder) classes.push(...config.classNames.placeholder);

                const div = document.createElement('div');
                div.className = classes.filter(Boolean).join(' ');

                if (isChoice) {
                    div.id = data.elementId;
                    div.dataset.choice = '';
                    div.dataset.selectText = itemSelectText || '';
                    div.setAttribute('role', data.group ? 'treeitem' : 'option');
                } else {
                    div.dataset.item = '';
                    if (data.active) div.setAttribute('aria-selected', 'true');
                }

                div.dataset.id = data.id;
                div.dataset.value = data.value || '';
                if (data.disabled) div.setAttribute('aria-disabled', 'true');

                if (data.placeholder || !data.value) {
                    div.innerHTML = `<span class='ba-placeholder'>${escapeHtml(data.label || 'Select Account')}</span>`;
                    return div;
                }

                const details = getBankAccountDetails(select, data);
                const bankName = details.bankName || 'Bank Account';
                const accountName = details.accountName || 'Unnamed account';
                const selectedAccountName = accountName.trim().split(/\s+/)[0] || accountName;
                const accountType = String(details.accountType || 'Account').trim().replace(/[_\s]+/g, '-').toLowerCase();
                const accountTypeLabel = accountType ? accountType.charAt(0).toUpperCase() + accountType.slice(1) : 'Account';

                if (compact) {
                    div.innerHTML = `<div class='ba-selected-row'><span class='ba-selected-name'>${escapeHtml(bankName)}${selectedAccountName ? ` <span class='ba-selected-separator'>—</span> ${escapeHtml(selectedAccountName)}` : ''}</span></div>`;
                } else {
                    div.innerHTML = `<div class='ba-account-option'><div class='ba-row ba-row-bank'>${escapeHtml(bankName)}</div><div class='ba-row ba-row-account'>${escapeHtml(accountName)}</div><div class='ba-row-meta'><span class='ba-row-type'><span class='ba-type-badge'>${escapeHtml(accountTypeLabel)}</span></span></div></div>`;
                }

                return div;
            }

            return {
                item: function(config, data) {
                    return buildBankAccountRow(config, data, false, null, true);
                },
                choice: function(config, data, itemSelectText) {
                    return buildBankAccountRow(config, data, true, itemSelectText, false);
                }
            };
        }
    });
}

function ensureBankAccountChoices() {
    const select = document.getElementById('bankAccountSelect');
    if (!select || typeof Choices === 'undefined') return;

    if (!bankAccountChoices) {
        bankAccountChoiceData = getBankAccountChoiceData(select);
        bankAccountChoices = createBankAccountChoices(select);
    }
}

function refreshBankAccountChoices(methodType = '') {
    const select = document.getElementById('bankAccountSelect');
    if (!select || typeof Choices === 'undefined') return;

    ensureBankAccountChoices();
    if (!bankAccountChoices) return;

    const availableChoices = bankAccountChoiceData.filter(choice => {
        if (!choice.value || !methodType) return true;
        const linkedMethodType = choice.customProperties?.methodType || '';
        return !linkedMethodType || linkedMethodType === methodType;
    });

    bankAccountChoices.setChoices(availableChoices, 'value', 'label', true);
    bankAccountChoices.setChoiceByValue('');
}

function resetBankAccountChoice() {
    const select = document.getElementById('bankAccountSelect');
    if (!select) return;

    if (bankAccountChoices) {
        bankAccountChoices.setChoiceByValue('');
    } else {
        select.value = '';
    }
}

let variantChoices = null;

function destroyVariantChoices() {
    if (variantChoices) {
        try { variantChoices.destroy(); } catch(e) {}
        variantChoices = null;
    }
}

function refreshVariantChoices() {
    const select = document.getElementById('ticketVariant');
    if (!select) return;

    if (select.disabled) {
        destroyVariantChoices();
        return;
    }

    destroyVariantChoices();

    variantChoices = new Choices(select, {
        searchEnabled: false,
        shouldSort: false,
        itemSelectText: '',
        allowHTML: true,
        removeItemButton: false,
        position: 'auto',
        resetScrollPosition: false,
        callbackOnCreateTemplates: function() {
            function buildVariantTwoRow(config, data, isChoice, itemSelectText, oneRow) {
                const label = data.label || '';
                const parts = label.split('\u001F');
                const name = parts[0] || label;
                const rawDesc = parts[1] || '';
                const descParts = rawDesc.split(' • ');
                const props = data.customProperties || {};
                const stockPart = props.stockText || descParts[0] || '';
                const balancePart = props.balanceText || descParts[1] || '';

                const color = props.colorCode || '';
                const stockColor = props.stockColor || '#6c757d';
                const swatch = color
                    ? `<span style="display:inline-block;width:12px;height:12px;background-color:${escapeHtml(color)};border-radius:2px;flex:0 0 auto;border:1px solid #ccc;"></span>`
                    : '';

                const classes = [].concat(config.classNames.item);
                if (isChoice) classes.push(...config.classNames.itemChoice);
                classes.push(...config.classNames.itemSelectable);
                if (data.selected) classes.push(...config.classNames.selectedState);
                if (data.disabled) classes.push(...config.classNames.itemDisabled);
                if (data.placeholder) classes.push(...config.classNames.placeholder);

                const div = document.createElement('div');
                div.className = classes.filter(Boolean).join(' ');

                if (isChoice) {
                    div.id = data.elementId;
                    div.dataset.choice = '';
                    div.dataset.selectText = itemSelectText || '';
                    div.setAttribute('role', data.group ? 'treeitem' : 'option');
                } else {
                    div.dataset.item = '';
                    if (data.active) div.setAttribute('aria-selected', 'true');
                }

                div.dataset.id = data.id;
                div.dataset.value = data.value;
                if (data.disabled) div.setAttribute('aria-disabled', 'true');

                const isWalletVariant = (props.hasWallet === true) || (balancePart && /₱/.test(balancePart));
                const secondaryText = balancePart === 'No wallet'
                    ? balancePart
                    : ((isWalletVariant ? balancePart : stockPart) || '');

                if (oneRow) {
                    div.innerHTML = `<div class='vr-selected-row' style='display:flex;align-items:center;gap:6px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>${swatch}<span class='vr-row-name' style='flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;'>${escapeHtml(name)}</span>${secondaryText ? `<span class='vr-row-separator' aria-hidden='true' style='flex:0 0 auto;color:#6c757d;'>|</span><span class='vr-row-available' style='flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:${escapeHtml(stockColor)};font-weight:600;'>${escapeHtml(secondaryText)}</span>` : ''}</div>`;
                } else {
                    div.innerHTML = `<div class='vr-row-name' style='display:flex;align-items:center;gap:6px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>${swatch}<span style='flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;'>${escapeHtml(name)}</span></div>` +
                        (secondaryText ? `<div class='vr-row-desc' style='min-width:0;'><span class='vr-row-available' style='color:${escapeHtml(stockColor)};font-weight:600;'>${escapeHtml(secondaryText)}</span></div>` : '');
                }

                return div;
            }

            return {
                item: function(config, data) {
                    return buildVariantTwoRow(config, data, false, null, true);
                },
                choice: function(config, data, itemSelectText) {
                    return buildVariantTwoRow(config, data, true, itemSelectText, false);
                }
            };
        }
    });
}

function onTicketVariantChanged() {
    window.selectedResolvedWallet = null;
    loadWallets().then(() => {
        const wallet = window.selectedResolvedWallet;
        const walletBranchId = wallet ? wallet.branch_id : null;
        loadServiceFeeForProvider(null, walletBranchId);
    });
}

function loadTicketVariants() {
    const providerSelect = document.getElementById('ticketProvider');
    const variantSelect = document.getElementById('ticketVariant');
    const variantWrapper = document.getElementById('ticketVariantWrapper');
    const availabilityText = document.getElementById('variantAvailabilityText');
    const warningText = document.getElementById('negativeStockWarningText');
    const variantRequiredMarker = document.getElementById('variantRequiredMarker');

    const providerId = providerSelect ? providerSelect.value : null;

    if (!providerId) {
        variantSelect.innerHTML = '<option value="">Select Provider First</option>';
        variantSelect.disabled = true;
        variantWrapper.classList.add('d-none');
        return Promise.resolve();
    }

    // Remember the currently selected variant so we can restore it after refresh
    const selectedVariantId = variantSelect ? variantSelect.value : '';

    variantWrapper.classList.remove('d-none');
    variantRequiredMarker.classList.add('d-none');
    variantSelect.disabled = true;
    variantSelect.innerHTML = '<option value="">Loading variants...</option>';

    const branchId = window.POS_BRANCH_ID || '';
    if (!branchId) {
        variantWrapper.classList.add('d-none');
        variantSelect.innerHTML = '<option value="">No wallet</option>';
        variantSelect.disabled = true;
        destroyVariantChoices();
        return Promise.resolve();
    }

    const variantUrl = `${window.BASE_URL}/api/ticket-variants?provider_id=${encodeURIComponent(providerId)}&branch_id=${encodeURIComponent(branchId)}`;
    console.log('[POS loadTicketVariants] url:', variantUrl);
    return fetch(variantUrl)
        .then(response => response.json())
        .then(data => {
            console.log('[POS loadTicketVariants] response:', data);
            if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                const allowNegative = window.POS_SETTINGS && window.POS_SETTINGS.allow_negative_ticket_stock;
                let optionsHtml = '<option value="">Select Variant (required)</option>';

                // Sort: in-stock / wallet-funded first, out-of-stock last
                const sorted = [...data.data].sort((a, b) => {
                    const aHasWallet = (a.wallet_id && a.wallet_id !== '') || (a.main_wallet_id && a.main_wallet_id !== '');
                    const bHasWallet = (b.wallet_id && b.wallet_id !== '') || (b.main_wallet_id && b.main_wallet_id !== '');
                    const aBalance = aHasWallet
                        ? (a.wallet_id && a.wallet_id !== '' ? parseFloat(a.wallet_balance || 0) : parseFloat(a.main_wallet_balance || 0))
                        : 0;
                    const bBalance = bHasWallet
                        ? (b.wallet_id && b.wallet_id !== '' ? parseFloat(b.wallet_balance || 0) : parseFloat(b.main_wallet_balance || 0))
                        : 0;
                    const aQty = aBalance > 0 ? 1 : (parseInt(a.available_qty, 10) || 0);
                    const bQty = bBalance > 0 ? 1 : (parseInt(b.available_qty, 10) || 0);
                    if (aQty > 0 && bQty <= 0) return -1;
                    if (aQty <= 0 && bQty > 0) return 1;
                    return 0;
                });

                sorted.forEach(v => {
                    const hasVariantWallet = v.wallet_id && v.wallet_id !== '';
                    const hasMainWallet = v.main_wallet_id && v.main_wallet_id !== '';
                    const walletBalance = hasVariantWallet
                        ? parseFloat(v.wallet_balance || 0)
                        : parseFloat(v.main_wallet_balance || 0);
                    let available = parseInt(v.available_qty, 10) || 0;
                    let stockText, stockColor, balanceText;
                    const isWalletBacked = hasVariantWallet || hasMainWallet;
                    const walletHasMoney = isWalletBacked && walletBalance > 0;

                    if (hasVariantWallet) {
                        // Variant wallets track physical stock, not money
                        stockText = available > 0 ? `${available} available` : (allowNegative ? 'negative stock allowed' : 'out of stock');
                        stockColor = available > 0 ? '#28a745' : '#dc3545';
                        balanceText = 'Stock wallet';
                    } else if (hasMainWallet) {
                        // For main-wallet-backed variants, stock is primary; show both
                        stockText = available > 0 ? `${available} available` : (allowNegative ? 'negative stock allowed' : 'out of stock');
                        stockColor = available > 0 ? '#28a745' : '#dc3545';
                        balanceText = `Main wallet: ₱${fmt(walletBalance)}`;
                    } else {
                        stockText = available > 0 ? `${available} available` : (allowNegative ? 'negative stock allowed' : 'out of stock');
                        stockColor = available > 0 ? '#28a745' : '#dc3545';
                        balanceText = 'No wallet';
                    }
                    const safeName = escapeHtml(v.variant_name);
                    const props = escapeHtml(JSON.stringify({ colorCode: v.color_code || '', stockColor: stockColor, stockText: stockText, balanceText: balanceText, hasWallet: walletHasMoney }));
                    const optionText = safeName + '\u001F' + escapeHtml(stockText) + ' • ' + escapeHtml(balanceText);
                    const walletBalanceAttribute = isWalletBacked ? ` data-wallet-balance="${walletBalance}"` : '';
                    optionsHtml += `<option value="${v.variant_id}" data-available="${available}" data-stock-controlled="${v.stock_controlled}" data-variant-name="${escapeHtml(v.variant_name)}" data-variant-code="${escapeHtml(v.variant_code || '')}"${walletBalanceAttribute} data-custom-properties="${props}">${optionText}</option>`;
                });

                variantSelect.innerHTML = optionsHtml;
                // Restore the previously selected variant if it still exists
                if (selectedVariantId && Array.from(variantSelect.options).some(opt => opt.value === selectedVariantId)) {
                    variantSelect.value = selectedVariantId;
                }
                variantSelect.disabled = false;
                variantRequiredMarker.classList.remove('d-none');
                availabilityText.textContent = 'Select a variant (required).';
                refreshVariantChoices();
            } else {
                variantWrapper.classList.add('d-none');
                variantSelect.innerHTML = '<option value="">No variants found</option>';
                variantSelect.disabled = true;
                destroyVariantChoices();
            }
        })
        .catch(error => {
            console.error('Error loading ticket variants:', error);
            variantWrapper.classList.add('d-none');
            variantSelect.innerHTML = '<option value="">Error loading variants</option>';
            variantSelect.disabled = true;
            destroyVariantChoices();
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

function loadServiceFeeForProvider(overrideProviderId = null, overrideBranchId = null) {
    const providerSelect = document.getElementById('ticketProvider');
    const providerId = overrideProviderId || (providerSelect ? providerSelect.value : null);
    const serviceFeeDisplay = document.getElementById('ticketServiceFeeDisplay');
    const serviceFeeInput = document.getElementById('ticketServiceFee');
    const baseAmountDisplay = document.getElementById('ticketBaseAmountDisplay');
    const baseAmountInput = document.getElementById('ticketBaseAmount');

    if (!providerId) {
        serviceFeeDisplay.textContent = '-';
        serviceFeeInput.value = 0;
        baseAmountDisplay.textContent = '₱0.00';
        computeTicketTotal();
        return Promise.resolve();
    }

    const provider = (window.allTicketProviders || []).find(p => String(p.provider_id) === String(providerId));
    const parentProviderId = provider ? provider.parent_provider_id : '';
    const numericProviderId = parentProviderId
        ? parseInt(parentProviderId, 10)
        : parseInt(providerId, 10);

    // Use the active POS branch as the authoritative branch for service fees.
    let numericBranchId = null;
    if (window.POS_BRANCH_ID) {
        numericBranchId = parseInt(window.POS_BRANCH_ID, 10);
    } else if (overrideBranchId !== null && overrideBranchId !== undefined) {
        numericBranchId = parseInt(overrideBranchId, 10) || null;
    }

    if (!numericProviderId || numericProviderId < 1) {
        serviceFeeDisplay.textContent = '-';
        serviceFeeInput.value = 0;
        baseAmountDisplay.textContent = '₱0.00';
        computeTicketTotal();
        return Promise.resolve();
    }

    const encodedProviderId = IdEncoder.encode(numericProviderId);
    let url = `${window.BASE_URL}/api/provider-service-fees`;

    if (encodedProviderId) {
        url += `?provider_id=${encodedProviderId}`;
    }
    if (numericBranchId) {
        const encodedBranchId = IdEncoder.encode(numericBranchId);
        if (encodedBranchId) {
            url += (encodedProviderId ? '&' : '?') + `branch_id=${encodedBranchId}`;
        }
    }

    console.log('[POS loadServiceFeeForProvider] url:', url);

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('[POS loadServiceFeeForProvider] response:', data);
            if (data.success && data.data && data.data.fees && data.data.fees.length > 0) {
                const fee = data.data.fees[0];
                const feeType = fee.fee_type;
                const feeValue = parseFloat(fee.fee_value);

                serviceFeeInput.value = feeValue;

                if (feeType === 'FIXED') {
                    serviceFeeDisplay.textContent = `₱${feeValue.toFixed(2)} (Fixed)`;
                } else if (feeType === 'PERCENT') {
                    const baseAmount = parseFloat(baseAmountInput.value) || 0;
                    const calculatedFee = (baseAmount * feeValue) / 100;
                    serviceFeeInput.value = calculatedFee;
                    serviceFeeDisplay.textContent = `₱${calculatedFee.toFixed(2)} (${feeValue}% of Base)`;
                } else {
                    serviceFeeDisplay.textContent = `₱${feeValue.toFixed(2)}`;
                    serviceFeeInput.value = feeValue;
                }

                window.currentServiceFee = {
                    type: feeType,
                    value: feeValue,
                    branch_id: fee.branch_id || null,
                    fee_id: fee.fee_id || null
                };

                const currentBaseAmount = parseFloat(baseAmountInput.value) || 0;
                baseAmountDisplay.textContent = currentBaseAmount > 0 ? '₱' + currentBaseAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00';

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

function loadServiceFeeForMainProvider(mainProviderId) {
    return loadServiceFeeForProvider(mainProviderId);
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

function setChargeAccountSelection(passengerId, passengerName = '') {
    selectedCustomerId = passengerId ? String(passengerId) : null;
    selectedCustomerName = passengerName ? passengerName.trim() : null;

    const idInput = document.getElementById('selectedChargeAccountId');
    const nameInput = document.getElementById('selectedChargeAccountName');
    if (idInput) idInput.value = selectedCustomerId || '';
    if (nameInput) nameInput.value = selectedCustomerName || '';
}

function showChargeAccountSelection() {
    const row = document.getElementById('chargeAccountRow');
    if (row) row.style.display = '';
}

function resetChargeAccountSelection() {
    setChargeAccountSelection(null, '');
    const row = document.getElementById('chargeAccountRow');
    if (row) row.style.display = 'none';
}

function openChargeAccountPicker() {
    const ticketItem = cart.find(item => item.type === 'ticket');
    const defaultName = selectedCustomerName || ticketItem?.passengerName || '';
    openCustomerModal(selectedCustomerId, defaultName);
}

function selectCustomerRadio(radio) {
    document.querySelectorAll('.customer-row').forEach(row => row.classList.remove('table-primary'));
    radio.closest('.customer-row')?.classList.add('table-primary');
    document.getElementById('confirmCustomerBtn').disabled = false;
}

function openCustomerModal(defaultPassengerId = null, defaultPassengerName = '') {
    selectedCustomerId = defaultPassengerId ? String(defaultPassengerId) : null;
    selectedCustomerName = defaultPassengerName ? defaultPassengerName.trim() : null;
    const searchInput = document.getElementById('customerSearch');
    searchInput.value = defaultPassengerName || '';
    updateCustomerSearchClearButton();
    document.querySelectorAll('input[name="selectedCustomer"]').forEach(r => r.checked = false);
    document.getElementById('confirmCustomerBtn').disabled = true;
    if (defaultPassengerName) {
        renderCustomers(defaultPassengerName);
    } else {
        filterCustomers();
    }
    selectCustomerModal.show();
}

function confirmCustomerSelection() {
    const selected = document.querySelector('input[name="selectedCustomer"]:checked');
    if (!selected) return;

    const row = selected.closest('.customer-row');
    const name = row?.querySelector('td')?.textContent?.trim() || '';
    setChargeAccountSelection(selected.value, name);
    showChargeAccountSelection();
    selectCustomerModal.hide();
    // Continue with payment entry
    document.getElementById('paymentEntryRow').style.display = '';
}

// =============================================
// SESSION MANAGEMENT
// =============================================

let cashierTransportAccessState = null;

function cashierTransportAccessHeaders() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
    return headers;
}

function renderTransportAccessBadges(types, labels) {
    if (!Array.isArray(types) || types.length === 0) return '';
    return types.map(type => {
        const label = (labels && labels[type]) || type;
        return `<span class="badge bg-100 text-600 fs-10 me-1">${label}</span>`;
    }).join('');
}

async function loadCashierTransportAccess() {
    const section = document.getElementById('cashierTransportAccessSection');
    const form = document.getElementById('cashierTransportTypeForm');
    const status = document.getElementById('cashierTransportAccessStatus');
    const mode = document.getElementById('cashierTransportAccessMode');
    if (!section || !form || !status || !mode) return;

    section.style.display = 'none';
    form.style.display = 'none';
    status.textContent = 'Loading your current access...';

    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/transport-access`, { credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) {
            if (response.status === 403) return;
            throw new Error(result.error || 'Unable to load transportation access.');
        }

        const access = result.data || {};
        cashierTransportAccessState = access;
        renderCashierTransportAccessSection(access, section, form, status, mode, true);
        renderCashierTransportAccessClose(access);
    } catch (error) {
        console.error('Error loading cashier transportation access:', error);
        section.style.display = '';
        if (mode) mode.textContent = 'Unavailable';
        if (status) status.textContent = error.message || 'Unable to load transportation access.';
    }
}

function renderCashierTransportAccessSection(access, section, form, status, mode, editable) {
    const labels = access.allowed_types || access.labels || {};
    const selectedTypes = Array.isArray(access.transport_types) ? access.transport_types : [];
    const modeLabel = access.mode === 'provider'
        ? 'Specific Providers'
        : access.mode === 'transport_type'
            ? 'By Transportation Type'
            : 'All Types';
    if (mode) mode.textContent = modeLabel;

    if (editable && document.querySelectorAll('.cashier-transport-type').length) {
        document.querySelectorAll('.cashier-transport-type').forEach(input => {
            input.checked = selectedTypes.includes(input.value);
            input.disabled = access.mode !== 'transport_type';
        });
    }

    if (!access.restricted || access.mode === 'all') {
        if (status) status.textContent = 'You currently have access to all transportation types. Type restrictions are managed in Users settings.';
        if (editable && form) form.style.display = 'none';
    } else if (access.mode === 'provider') {
        if (status) status.textContent = 'Your access is assigned by specific provider. Ask an administrator to update provider access in Users settings.';
        if (editable && form) form.style.display = 'none';
    } else {
        const currentLabels = selectedTypes.map(type => labels[type] || type).join(', ');
        if (status) status.textContent = currentLabels
            ? `Current access: ${currentLabels}`
            : 'No transportation types are currently selected.';
        if (editable && form) form.style.display = '';
    }

    if (section) section.style.display = '';
}

function renderCashierTransportAccessClose(access) {
    const section = document.getElementById('closeTransportAccessSection');
    const status = document.getElementById('closeTransportAccessStatus');
    const mode = document.getElementById('closeTransportAccessMode');
    const typesEl = document.getElementById('closeTransportAccessTypes');
    if (!section || !status || !mode || !typesEl) return;

    const labels = access.allowed_types || access.labels || {};
    const selectedTypes = Array.isArray(access.transport_types) ? access.transport_types : [];

    mode.textContent = access.mode === 'provider'
        ? 'Specific Providers'
        : access.mode === 'transport_type'
            ? 'By Transportation Type'
            : 'All Types';

    if (!access.restricted || access.mode === 'all') {
        status.textContent = 'You currently have access to all transportation types.';
        typesEl.innerHTML = renderTransportAccessBadges(Object.keys(labels), labels);
    } else if (access.mode === 'provider') {
        status.textContent = 'Your access is assigned by specific provider.';
        typesEl.innerHTML = '';
    } else {
        const currentLabels = selectedTypes.map(type => labels[type] || type).join(', ');
        status.textContent = currentLabels
            ? `Current access: ${currentLabels}`
            : 'No transportation types are currently selected.';
        typesEl.innerHTML = renderTransportAccessBadges(selectedTypes, labels);
    }

    section.style.display = '';
}

async function loadCloseSessionTransportAccess() {
    const section = document.getElementById('closeTransportAccessSection');
    const status = document.getElementById('closeTransportAccessStatus');
    const mode = document.getElementById('closeTransportAccessMode');
    const typesEl = document.getElementById('closeTransportAccessTypes');
    if (!section || !status || !mode || !typesEl) return;

    section.style.display = 'none';
    status.textContent = 'Loading your current access...';
    mode.textContent = 'Loading...';
    typesEl.innerHTML = '';

    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/transport-access`, { credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) {
            if (response.status === 403) return;
            throw new Error(result.error || 'Unable to load transportation access.');
        }

        const access = result.data || {};
        cashierTransportAccessState = access;
        renderCashierTransportAccessSection(access, document.getElementById('cashierTransportAccessSection'), document.getElementById('cashierTransportTypeForm'), document.getElementById('cashierTransportAccessStatus'), document.getElementById('cashierTransportAccessMode'), true);
        renderCashierTransportAccessClose(access);
    } catch (error) {
        console.error('Error loading close session transportation access:', error);
        section.style.display = '';
        mode.textContent = 'Unavailable';
        status.textContent = error.message || 'Unable to load transportation access.';
    }
}

async function saveCashierTransportAccess() {
    const state = cashierTransportAccessState || {};
    if (state.mode !== 'transport_type') {
        return { success: true };
    }

    const transportTypes = Array.from(document.querySelectorAll('.cashier-transport-type:checked'))
        .map(input => input.value);
    if (!transportTypes.length) {
        showToast('warning', 'Select Access', 'Select at least one transportation type.');
        return { success: false };
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/transport-access`, {
            method: 'PUT',
            headers: cashierTransportAccessHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ transport_types: transportTypes })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to update transportation access.');

        await loadCashierTransportAccess();
        if (typeof loadProviders === 'function') await loadProviders();
        return { success: true, data: result.data };
    } catch (error) {
        console.error('Error updating cashier transportation access:', error);
        showToast('danger', 'Access Update Failed', error.message || 'Unable to update transportation access.');
        return { success: false, error: error.message };
    }
}

async function submitOpenSession() {
    const branchId = document.getElementById('sessionBranchId').value;
    const openingCashInput = document.getElementById('sessionOpeningCash');
    const openingCashRaw = openingCashInput.value.trim();
    const notes = document.getElementById('sessionNotes').value.trim();

    if (!branchId) { showToast('danger', 'Error', 'Branch is required.'); return; }
    if (!openingCashRaw) {
        openingCashInput.focus();
        showToast('danger', 'Error', 'Opening cash balance is required.'); return;
    }
    const openingCash = parseFloat(openingCashRaw.replace(/,/g, ''));
    if (isNaN(openingCash) || openingCash < 0) {
        openingCashInput.focus();
        showToast('danger', 'Error', 'Enter a valid opening cash amount.'); return;
    }

    const state = cashierTransportAccessState || {};
    const selectedTypes = Array.from(document.querySelectorAll('.cashier-transport-type:checked')).map(input => input.value);
    if (state.mode === 'transport_type') {
        const originalSelected = Array.isArray(state.transport_types) ? state.transport_types : [];
        const typesChanged = selectedTypes.length !== originalSelected.length ||
            selectedTypes.some(type => !originalSelected.includes(type)) ||
            originalSelected.some(type => !selectedTypes.includes(type));
        if (typesChanged) {
            const accessUpdated = await saveCashierTransportAccess();
            if (!accessUpdated.success) return;
        }
    }

    const branchName = document.getElementById('sessionBranchId').selectedOptions[0]?.text?.trim() || 'this branch';
    const shouldRestoreOpenModal = await hideModalBeforeConfirmation(openSessionModal);
    const restoreOpenModal = () => {
        if (shouldRestoreOpenModal) openSessionModal.show();
    };
    const confirmed = await showConfirm(
        'Open Cashier Session',
        `Open cashier session for ${branchName} with opening cash ₱${fmt(openingCash)}?\n\nMake sure the amount is correct before proceeding.`,
        { icon: 'play-circle', iconColor: 'text-success', confirmBtnColor: 'btn-success', confirmBtnText: 'Open Session' }
    );
    if (!confirmed) {
        restoreOpenModal();
        return;
    }

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
            restoreOpenModal();
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        restoreOpenModal();
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function openCloseSession() {
    console.log('openCloseSession() called');

    // Load transportation type access for close session
    loadCloseSessionTransportAccess();

    // Reset form
    const closingCashEl = document.getElementById('closingCash');
    const closingNotesEl = document.getElementById('closingNotes');
    const varianceDisplayEl = document.getElementById('varianceDisplay');
    const closeAdjustmentCardsEl = document.getElementById('closeAdjustmentCards');
    const closeSummaryEl = document.getElementById('closeSummary');
    
    if (closingCashEl) closingCashEl.value = '';
    if (closeAdjustmentCardsEl) {
        closeAdjustmentCardsEl.innerHTML = '';
        closeAdjustmentCardsEl.classList.add('d-none');
    }
    if (closingNotesEl) closingNotesEl.value = '';
    if (varianceDisplayEl) {
        varianceDisplayEl.textContent = '₱0.00';
        varianceDisplayEl.className = 'fw-bold text-muted';
    }
    
    console.log('Elements found:', {
        closingCash: !!closingCashEl,
        closingNotes: !!closingNotesEl,
        varianceDisplay: !!varianceDisplayEl,
        closeSummary: !!closeSummaryEl
    });

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
        console.log('Loading close session data for POS_SESSION_ID:', window.POS_SESSION_ID);
        const encodedSessionId = IdEncoder.encode(window.POS_SESSION_ID);
        console.log('Encoded session ID:', encodedSessionId);
        const response = await fetch(`${window.BASE_URL}/api/pos/sessions?id=${encodedSessionId}`);
        const result = await response.json();
        console.log('Session API response:', result);
        
        if (result.success) {
            console.log('API returned success, updating UI...');
            const s = result.data.session;
            const payments = result.data.payments || [];
            const expected = parseFloat(s.expected_cash || 0);
            const refundedSalesAmount = parseFloat(s.refunded_sales_amount || 0);
            const approvedRefundsAmount = parseFloat(s.approved_refunds_amount ?? refundedSalesAmount);
            const voidFee = parseFloat(s.void_fee || 0);
            const voidServiceFee = parseFloat(s.void_service_fee || 0);
            const lostSalesVoidFee = parseFloat(s.lost_sales_void_fee || 0);
            const lostSalesServiceFee = parseFloat(s.lost_sales_service_fee || 0);
            const voidFeeTotal = voidFee + voidServiceFee;
            const voidIncome = parseFloat(s.void_income ?? voidFeeTotal);
            const technicalLostSalesAmount = parseFloat(
                s.technical_lost_sales_amount ?? (lostSalesVoidFee + lostSalesServiceFee)
            );
            const includedPaymentSales = payments
                .filter(p => Number(p.include_in_expected_cash) === 1)
                .reduce((sum, p) => sum + parseFloat(p.total_amount), 0);
            const grossSales = s.total_sales != null ? (parseFloat(s.total_sales) || 0) : includedPaymentSales;
            const voidedSalesAmount = parseFloat(s.voided_sales_amount || 0);
            const netSales = parseFloat(s.net_sales ?? Math.max(
                0,
                grossSales
                    - refundedSalesAmount
                    - voidedSalesAmount
                    + voidIncome
            ));
            
            const expectedCashEl = document.getElementById('expectedCash');
            const totalSalesEl = document.getElementById('totalSales');
            const closeAdjustmentCardsEl = document.getElementById('closeAdjustmentCards');
            const closeSummaryEl = document.getElementById('closeSummary');
            
            console.log('Updating elements:', { expectedCash: expected, totalSales: s.total_sales });
            
            if (expectedCashEl) expectedCashEl.textContent = '₱' + fmt(expected);
            if (totalSalesEl) totalSalesEl.textContent = '₱' + fmt(netSales);

            const adjustmentCards = [
                { label: 'Refunded', amount: approvedRefundsAmount, borderClass: 'border-warning', iconClass: 'icon-circle-warning', icon: 'fa-receipt', textClass: 'text-warning' },
                { label: 'Void Income', amount: voidIncome, borderClass: 'border-success', iconClass: 'icon-circle-success', icon: 'fa-coins', textClass: 'text-success' },
                { label: 'Void Lost Sales', amount: technicalLostSalesAmount, borderClass: 'border-danger', iconClass: 'icon-circle-danger', icon: 'fa-exclamation-triangle', textClass: 'text-danger' }
            ].filter(card => card.amount > 0);

            if (closeAdjustmentCardsEl) {
                closeAdjustmentCardsEl.innerHTML = adjustmentCards.map(card => `
                  <div class="col-6 p-1">
                    <div class="card h-100 ${card.borderClass}" style="min-height: 80px;">
                      <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center h-100">
                          <div class="icon-circle ${card.iconClass} me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                            <span class="fas ${card.icon} ${card.textClass}"></span>
                          </div>
                          <div class="flex-grow-1 min-width-0">
                            <div class="text-muted small mb-0">${card.label}</div>
                            <div class="fw-bold ${card.textClass} fs-6">₱${fmt(card.amount)}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>`).join('');
                closeAdjustmentCardsEl.classList.toggle('d-none', adjustmentCards.length === 0);
            }

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

            if (closeSummaryEl) {
                closeSummaryEl.innerHTML = `
                <div class="row g-3 align-items-center">
                  <div class="col-6 col-md-3">
                    <div class="text-muted small mb-1">Started</div>
                    <div class="fw-bold">${startedFmt}</div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="text-muted small mb-1">Cashier</div>
                    <div class="fw-bold">${s.cashier_name || '—'}</div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="text-muted small mb-1">Transactions</div>
                    <div class="fw-bold">${s.txn_count || 0}</div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="text-muted small mb-1">Voided Tickets</div>
                    <div class="fw-bold text-warning">${s.voided_ticket_count || 0}</div>
                  </div>
                </div>`;
            }

            // Payment type breakdown with include_in_expected_cash indicator
            const pendingRefundsCash = parseFloat(s.pending_refunds_cash || 0);
            const showPendingRefunds = Boolean(s.show_pending_refunds_in_close_session) ||
                Boolean(window.CANCELLATION_SETTINGS?.show_pending_refunds_in_close_session);
            const displayedPendingRefunds = showPendingRefunds ? pendingRefundsCash : 0;
            const totalCashChange = parseFloat(s.total_cash_change || 0);
            const totalCashAdjustments = parseFloat(s.total_cash_adjustments || 0);
            const voidedTicketCount = parseInt(s.voided_ticket_count || 0, 10);
            const technicalVoidCount = parseInt(s.technical_void_count || 0, 10);
            const pendingVoidCount = parseInt(s.pending_void_count || 0, 10);
            const pendingVoidAmount = parseFloat(s.pending_void_amount || 0);
            const expectedCashFromAPI = parseFloat(s.expected_cash || 0);

            if (payments.length > 0 || voidedTicketCount > 0 || technicalVoidCount > 0 || voidFeeTotal > 0 || pendingVoidCount > 0) {
                let html = '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
                html += '<div class="card mb-4"><div class="card-body py-3"><table class="table table-borderless fs-10 mb-0 table-hover">';

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
                    const hasDeductions = totalCashChange > 0 || approvedRefundsAmount > 0 || displayedPendingRefunds > 0 || totalCashAdjustments > 0
                        || voidedTicketCount > 0 || technicalVoidCount > 0 || voidFeeTotal > 0 || pendingVoidCount > 0;
                    const borderClass = !isLast || hasDeductions ? 'border-bottom' : '';
                    const totalAmount = parseFloat(p.total_amount ?? p.active_amount ?? 0);
                    const inCashBadge = Number(p.include_in_expected_cash) === 1
                        ? '<span class="badge bg-soft-success text-success fs-11 ms-1"><span class="fas fa-cash-register me-1"></span>In Cash</span>'
                        : '<span class="badge bg-soft-secondary text-secondary fs-11 ms-1"><span class="fas fa-ban me-1"></span>Not Cash</span>';

                    html += `
                      <tr class="${borderClass}" style="cursor: default;">
                        <td class="ps-0">
                          <div class="fw-semibold">${p.method_name} ${inCashBadge}</div>
                          <div class="text-400 fw-normal fs-11 text-uppercase">${p.method_type}</div>
                        </td>
                        <td class="pe-0 text-end"><strong>₱${fmt(totalAmount)}</strong></td>
                      </tr>`;
                });

                // Show cash change if any
                if (totalCashChange > 0) {
                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0"><strong>Change (Cash Out)</strong>
                          <div class="text-400 fw-normal fs-11 text-warning">CASH GIVEN TO CUSTOMERS</div>
                        </td>
                        <td class="pe-0 text-end text-warning"><strong>-₱${fmt(totalCashChange)}</strong></td>
                      </tr>`;
                }

                // Show refunds if any
                if (approvedRefundsAmount > 0) {
                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0"><strong>Approved Refunds</strong>
                          <div class="text-400 fw-normal fs-11 text-danger">PAYMENT TOTALS</div>
                        </td>
                        <td class="pe-0 text-end text-danger"><strong>-₱${fmt(approvedRefundsAmount)}</strong></td>
                      </tr>`;
                }

                // Show pending refunds if configured
                if (displayedPendingRefunds > 0) {
                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0"><strong>Pending Refunds</strong>
                          <div class="text-400 fw-normal fs-11 text-warning">AWAITING APPROVAL</div>
                        </td>
                        <td class="pe-0 text-end text-warning"><strong>-₱${fmt(displayedPendingRefunds)}</strong></td>
                      </tr>`;
                }

                if (totalCashAdjustments > 0) {
                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0"><strong>Cashier Responsibility Deductions</strong>
                          <div class="text-400 fw-normal fs-11 text-danger">IMMEDIATE ACCOUNTABILITY ADJUSTMENT</div>
                        </td>
                        <td class="pe-0 text-end text-danger"><strong>-₱${fmt(totalCashAdjustments)}</strong></td>
                      </tr>`;
                }

                const hasVoidBreakdown = technicalVoidCount > 0 || voidFeeTotal > 0
                    || technicalLostSalesAmount > 0 || pendingVoidCount > 0;
                if (hasVoidBreakdown) {
                    const voidBreakdownRows = [];
                    const voidAmountRows = [];

                    if (technicalVoidCount > 0 && technicalLostSalesAmount > 0) {
                        voidBreakdownRows.push(`
                          <div class="d-flex align-items-center gap-2">
                            <span><span class="fas fa-exclamation-triangle me-1 text-danger"></span>Technical Issue Lost Sales <span class="text-muted">(${technicalVoidCount})</span></span>
                          </div>`);
                        voidAmountRows.push(`<div class="text-danger">Void Lost Sales <span class="small">-₱${fmt(technicalLostSalesAmount)}</span></div>`);
                    }

                    if (voidFeeTotal > 0) {
                        const feeBreakdownRows = [];
                        if (voidFee > 0) {
                            feeBreakdownRows.push(`<span>Void Fee ₱${fmt(voidFee)}</span>`);
                        }
                        if (voidServiceFee > 0) {
                            feeBreakdownRows.push(`<span>Service Fee ₱${fmt(voidServiceFee)}</span>`);
                        }
                        voidBreakdownRows.push(`
                          <div class="d-flex align-items-center gap-2">
                            <span><span class="fas fa-coins me-1 text-success"></span>Void income</span>
                          </div>`);
                        voidAmountRows.push(`
                          <div class="text-success">+₱${fmt(voidFeeTotal)} fees</div>
                          <div class="text-muted small">${feeBreakdownRows.join(' <span class="mx-1">+</span> ')}</div>`);
                    }

                    if (pendingVoidCount > 0) {
                        voidBreakdownRows.push(`
                          <div class="d-flex align-items-center gap-2">
                            <span><span class="fas fa-clock me-1 text-info"></span>Pending ticket voids <span class="text-muted">(${pendingVoidCount})</span></span>
                          </div>`);
                        voidAmountRows.push(`<div class="text-info">${pendingVoidCount} pending <span class="small">₱${fmt(pendingVoidAmount)}</span> <small class="text-muted">(no cash effect)</small></div>`);
                    }

                    html += `
                      <tr class="border-bottom">
                        <td class="ps-0 pe-3">
                          <strong>Voids</strong>
                          <div class="text-400 fw-normal fs-11 text-secondary">TICKET / CASH / FEE BREAKDOWN</div>
                          <div class="small mt-2">${voidBreakdownRows.join('')}</div>
                        </td>
                        <td class="pe-0 ps-3 text-end align-top border-start" style="min-width: 190px;">${voidAmountRows.join('')}</td>
                      </tr>`;
                }

                // Show expected cash calculation
                const expectedCashCalc = payments
                    .filter(p => Number(p.include_in_expected_cash) === 1)
                    .reduce((sum, p) => sum + parseFloat(p.total_amount || 0), 0);

                const expectedCalcFormula = [
                    'STARTING + IN CASH',
                    approvedRefundsAmount > 0 ? '- REFUNDS' : null,
                    voidIncome > 0 ? '+ VOID INCOME' : null,
                    technicalLostSalesAmount > 0 ? '- VOID LOST SALES' : null,
                    '- CHANGE',
                    showPendingRefunds && displayedPendingRefunds > 0 ? '- PENDING REFUNDS' : null,
                    totalCashAdjustments > 0 ? '- CASHIER DEDUCTIONS' : null
                ].filter(Boolean).join(' ');

                html += `
                  <tr class="table-light">
                    <td class="ps-0 pb-0 pt-2"><strong>Expected Cash</strong>
                      <div class="text-400 fw-normal fs-11 text-success">${expectedCalcFormula}</div>
                    </td>
                    <td class="pe-0 text-end pb-0 pt-2 text-success"><strong>₱${fmt(expectedCashFromAPI)}</strong></td>
                  </tr>
                </table>
              </div>
            </div>`;

                // Add collapsible info note about expected cash calculation
                const approvedRefundsCalcNote = approvedRefundsAmount > 0
                    ? ` - Approved Refunds (₱${fmt(approvedRefundsAmount)})`
                    : '';
                const pendingRefundsCalcNote = showPendingRefunds && displayedPendingRefunds > 0
                    ? ` - Pending Refunds (₱${fmt(displayedPendingRefunds)})`
                    : '';
                const voidIncomeCalcNote = voidIncome > 0
                    ? ` + Void Fee / Service Fee Income (₱${fmt(voidIncome)})`
                    : '';
                const voidLostSalesCalcNote = technicalLostSalesAmount > 0
                    ? ` - Void Lost Sales (₱${fmt(technicalLostSalesAmount)})`
                    : '';
                const cashAdjustmentsCalcNote = totalCashAdjustments > 0
                    ? ` - Cashier Deductions (₱${fmt(totalCashAdjustments)})`
                    : '';

                html += `
                <div class="mb-4">
                  <button class="btn btn-link btn-sm text-decoration-none text-muted fs-10 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#expectedCashCalcInfo" aria-expanded="false">
                    <span class="fas fa-info-circle me-1"></span>Show how Expected Cash is calculated
                  </button>
                  <div class="collapse mt-2" id="expectedCashCalcInfo">
                    <div class="alert alert-info fs-10 mb-0">
                      <strong>How Expected Cash is calculated:</strong><br>
                      <small>Starting Cash (₱${fmt(s.starting_cash)}) + Payments marked "In Cash" (₱${fmt(expectedCashCalc)})${approvedRefundsCalcNote}${voidIncomeCalcNote}${voidLostSalesCalcNote} - Cash Change (₱${fmt(totalCashChange)})${pendingRefundsCalcNote}${cashAdjustmentsCalcNote}</small>
                    </div>
                  </div>
                </div>`;

                // Add change info note if there is cash change
                if (totalCashChange > 0) {
                    html += `
                    <div class="alert alert-warning fs-10 mb-4">
                      <span class="fas fa-exclamation-triangle me-2"></span>
                      <strong>Note:</strong> Cash change of ₱${fmt(totalCashChange)} has been given to customers and is deducted from the expected cash.
                    </div>`;
                }

                // Add refund info note if there are refunds
                if (approvedRefundsAmount > 0) {
                    html += `
                    <div class="alert alert-warning fs-10 mb-4">
                      <span class="fas fa-exclamation-triangle me-2"></span>
                      <strong>Note:</strong> Approved refunds of ₱${fmt(approvedRefundsAmount)} are deducted from Expected Cash.
                    </div>`;
                }

                if (displayedPendingRefunds > 0) {
                    html += `
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-info-circle me-2"></span>
                      <strong>Note:</strong> Pending refunds of ₱${fmt(displayedPendingRefunds)} are reserved and deducted from expected cash.
                    </div>`;
                }

                if (totalCashAdjustments > 0) {
                    html += `
                    <div class="alert alert-danger fs-10 mb-4">
                      <span class="fas fa-user-shield me-2"></span>
                      <strong>Note:</strong> Cashier responsibility deductions of ₱${fmt(totalCashAdjustments)} are included in expected cash.
                    </div>`;
                }
                if (technicalVoidCount > 0 || voidFeeTotal > 0
                    || technicalLostSalesAmount > 0 || pendingVoidCount > 0) {
                    const voidNotes = [];
                    if (technicalLostSalesAmount > 0) {
                        voidNotes.push(`Void Lost Sales of ₱${fmt(technicalLostSalesAmount)} are deducted from expected cash and sales.`);
                    }
                    if (voidFeeTotal > 0) {
                        const feeParts = [];
                        if (voidFee > 0) feeParts.push(`Void Fee ₱${fmt(voidFee)}`);
                        if (voidServiceFee > 0) feeParts.push(`Service Fee ₱${fmt(voidServiceFee)}`);
                        voidNotes.push(`Void income is ₱${fmt(voidFeeTotal)} (${feeParts.join(' + ')}).`);
                    }
                    if (pendingVoidCount > 0) {
                        voidNotes.push('Pending voids have no cash effect yet.');
                    }
                    html += `
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-ban me-2"></span>
                      <strong>Note:</strong> ${voidNotes.join(' ')}
                    </div>`;
                }

                const paymentBreakdownSectionEl = document.getElementById('paymentBreakdownSection');
                if (paymentBreakdownSectionEl) paymentBreakdownSectionEl.innerHTML = html;
            } else {
                const paymentBreakdownSectionEl = document.getElementById('paymentBreakdownSection');
                if (paymentBreakdownSectionEl) paymentBreakdownSectionEl.innerHTML = `
                    <div class="alert alert-info fs-10 mb-4">
                        <span class="fas fa-info-circle me-2"></span>No payments recorded for this session.
                    </div>`;
            }
        } else {
            console.log('API returned success=false, error:', result.error);
            const closeSummaryErrorEl = document.getElementById('closeSummary');
            if (closeSummaryErrorEl) {
                closeSummaryErrorEl.innerHTML = `
                    <div class="alert alert-danger fs-10 mb-0">
                        <span class="fas fa-exclamation-circle me-2"></span>
                        ${result.error || 'Failed to load session data'}
                    </div>`;
            }
        }
    } catch (e) {
        console.error('Error loading close session data:', e);
        const closeSummaryErrorEl = document.getElementById('closeSummary');
        if (closeSummaryErrorEl) {
            closeSummaryErrorEl.innerHTML = `
                <div class="alert alert-danger fs-10 mb-0">
                    <span class="fas fa-exclamation-circle me-2"></span>
                    Failed to load session data. Please try again.
                </div>`;
        }
    }

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

    const closingCashInput = document.getElementById('closingCash');
    const closingCashRaw = closingCashInput.value.trim();
    const notes = document.getElementById('closingNotes').value.trim();
    const bankAccountEl = document.getElementById('depositBankAccountId');
    const bankAccountId = bankAccountEl ? bankAccountEl.value : null;
    const depositNowEl = document.getElementById('depositNow');
    const depositNow = depositNowEl ? depositNowEl.checked : false;

    if (!closingCashRaw) {
        closingCashInput.focus();
        showToast('danger', 'Error', 'Actual closing cash is required.'); return;
    }
    const closingCash = parseFloat(closingCashRaw.replace(/,/g, ''));
    if (isNaN(closingCash) || closingCash < 0) {
        closingCashInput.focus();
        showToast('danger', 'Error', 'Enter a valid closing cash amount.'); return;
    }

    const expectedText = document.getElementById('expectedCash').textContent.replace(/[₱,]/g, '');
    const expectedCash = parseFloat(expectedText) || 0;
    const variance = closingCash - expectedCash;
    const varianceText = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
    const statusText = Math.abs(variance) < 0.01 ? 'Balanced' : variance < 0 ? 'Short' : 'Over';
    const varianceColorClass = Math.abs(variance) < 0.01 ? 'text-success' : variance < 0 ? 'text-danger' : 'text-success';
    const varianceLine = `Variance: <span class="fw-bold ${varianceColorClass}">${varianceText} (${statusText})</span>`;

    const message = `Close session with actual cash ₱${fmt(closingCash)}?\n\n` +
                    `Expected cash: ₱${fmt(expectedCash)}\n` +
                    `${varianceLine}\n\n` +
                    `Make sure the physical cash count is correct before confirming.`;
    const shouldRestoreCloseModal = await hideModalBeforeConfirmation(closeSessionModal);
    const restoreCloseModal = () => {
        if (shouldRestoreCloseModal) closeSessionModal.show();
    };
    const confirmed = await showConfirm(
        'Close Cashier Session',
        message,
        { icon: 'stop-circle', iconColor: 'text-danger', confirmBtnColor: 'btn-danger', confirmBtnText: 'Close Session', html: true }
    );
    if (!confirmed) {
        restoreCloseModal();
        return;
    }

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
            restoreCloseModal();
        }
    } catch (e) {
        showToast('danger', 'Error', 'An unexpected error occurred.');
        restoreCloseModal();
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

    // Reset address dropdowns and search inputs
    resetAddPassengerAddressFields();

    currentPassengerStep = 1;
    updatePassengerWizardUI();
    populateRegionSelect(() => {
        // Pre-fill the last used address so repeat passengers are faster to register
        restoreLastPassengerAddress();
    });
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

// Choices.js instances for address dropdowns in the Add Passenger modal
let addressChoices = {};

/**
 * Initialize Choices.js on an address select if it hasn't been initialized yet.
 * Disabled selects are left as native selects so the placeholder is clearly shown.
 */
function initAddressChoices(select) {
    if (addressChoices[select.id]) {
        return;
    }
    if (select.disabled) {
        return;
    }
    addressChoices[select.id] = new Choices(select, {
        searchEnabled: true,
        shouldSort: false,
        searchPlaceholderValue: 'Search...',
        placeholder: true,
        placeholderValue: '',
        itemSelectText: '',
        allowHTML: false,
        removeItemButton: false,
        searchResultLimit: 50,
        searchFloor: 1,
        position: 'auto',
        resetScrollPosition: false
    });
}

/**
 * Sync the Choices.js instance with the current native select options.
 * Destroy the instance if the select is disabled, or create it if it is enabled.
 */
function refreshAddressChoices(select) {
    const instance = addressChoices[select.id];
    if (select.disabled) {
        if (instance) {
            instance.destroy();
            delete addressChoices[select.id];
        }
        return;
    }
    if (!instance) {
        initAddressChoices(select);
        return;
    }
    // Replace choices in place without destroying the instance (avoids UI delay)
    const choices = Array.from(select.options).map(opt => ({
        value: opt.value,
        label: opt.textContent,
        selected: opt.selected,
        disabled: opt.disabled
    }));
    instance.setChoices(choices, 'value', 'label', true);
}

function populateRegionSelect(onComplete = null) {
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
                refreshAddressChoices(select);
                if (typeof onComplete === 'function') {
                    onComplete();
                }
            }
        })
        .catch(error => {
            console.error('Error fetching regions:', error);
            showToast('danger', 'Error', 'Failed to load regions');
        });
}

function loadProvinces(targetProvinceCode = null) {
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
        refreshAddressChoices(provinceSelect);
        refreshAddressChoices(citySelect);
        refreshAddressChoices(barangaySelect);
        return Promise.resolve();
    }

    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    citySelect.disabled = true;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    refreshAddressChoices(citySelect);
    refreshAddressChoices(barangaySelect);

    // Fetch provinces via AJAX
    return fetch(`${window.BASE_URL}/api/psgc?action=provinces&region_code=${regionCode}`)
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
                if (targetProvinceCode && provinceSelect.querySelector(`option[value="${targetProvinceCode}"]`)) {
                    provinceSelect.value = targetProvinceCode;
                }
                refreshAddressChoices(provinceSelect);
            }
        })
        .catch(error => {
            console.error('Error fetching provinces:', error);
            showToast('danger', 'Error', 'Failed to load provinces');
        });
}

function loadCities(targetCityCode = null) {
    const provinceCode = document.getElementById('newPassengerProvince').value;
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!provinceCode) {
        citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
        citySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        refreshAddressChoices(citySelect);
        refreshAddressChoices(barangaySelect);
        return Promise.resolve();
    }

    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;
    refreshAddressChoices(barangaySelect);

    // Fetch cities via AJAX
    return fetch(`${window.BASE_URL}/api/psgc?action=cities&province_code=${provinceCode}`)
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
                if (targetCityCode && citySelect.querySelector(`option[value="${targetCityCode}"]`)) {
                    citySelect.value = targetCityCode;
                }
                refreshAddressChoices(citySelect);
            }
        })
        .catch(error => {
            console.error('Error fetching cities:', error);
            showToast('danger', 'Error', 'Failed to load cities');
        });
}

function loadBarangays(targetBarangayCode = null) {
    const cityCode = document.getElementById('newPassengerCity').value;
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!cityCode) {
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        refreshAddressChoices(barangaySelect);
        return Promise.resolve();
    }

    // Fetch barangays via AJAX
    return fetch(`${window.BASE_URL}/api/psgc?action=barangays&city_code=${cityCode}`)
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
                if (targetBarangayCode && barangaySelect.querySelector(`option[value="${targetBarangayCode}"]`)) {
                    barangaySelect.value = targetBarangayCode;
                }
                refreshAddressChoices(barangaySelect);
            }
        })
        .catch(error => {
            console.error('Error fetching barangays:', error);
            showToast('danger', 'Error', 'Failed to load barangays');
        });
}

/**
 * Save the address used in the Add Passenger modal to localStorage.
 */
function saveLastPassengerAddress() {
    const regionSelect = document.getElementById('newPassengerRegion');
    const provinceSelect = document.getElementById('newPassengerProvince');
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!regionSelect.value) return;

    const address = {
        region_code: regionSelect.value,
        region_name: regionSelect.options[regionSelect.selectedIndex]?.textContent || '',
        province_code: provinceSelect.value,
        province_name: provinceSelect.options[provinceSelect.selectedIndex]?.textContent || '',
        city_municipality_code: citySelect.value,
        city_municipality_name: citySelect.options[citySelect.selectedIndex]?.textContent || '',
        barangay_code: barangaySelect.value,
        barangay_name: barangaySelect.options[barangaySelect.selectedIndex]?.textContent || ''
    };
    localStorage.setItem('posLastPassengerAddress', JSON.stringify(address));
}

/**
 * Restore the last saved address into the Add Passenger modal.
 */
async function restoreLastPassengerAddress() {
    const saved = localStorage.getItem('posLastPassengerAddress');
    if (!saved) return;

    const address = JSON.parse(saved);
    const regionSelect = document.getElementById('newPassengerRegion');
    const provinceSelect = document.getElementById('newPassengerProvince');
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    if (!address.region_code || !regionSelect.querySelector(`option[value="${address.region_code}"]`)) return;

    regionSelect.value = address.region_code;
    refreshAddressChoices(regionSelect);

    await loadProvinces(address.province_code);
    if (address.province_code && provinceSelect.value === address.province_code) {
        await loadCities(address.city_municipality_code);
    }
    if (address.city_municipality_code && citySelect.value === address.city_municipality_code) {
        await loadBarangays(address.barangay_code);
    }
}

/**
 * Reset address dropdowns and their Choices.js instances in the Add Passenger modal.
 */
function resetAddPassengerAddressFields() {
    const regionSelect = document.getElementById('newPassengerRegion');
    const provinceSelect = document.getElementById('newPassengerProvince');
    const citySelect = document.getElementById('newPassengerCity');
    const barangaySelect = document.getElementById('newPassengerBarangay');

    // Destroy existing instances first so they don't fight with the reset
    [regionSelect, provinceSelect, citySelect, barangaySelect].forEach(select => {
        if (addressChoices[select.id]) {
            addressChoices[select.id].destroy();
            delete addressChoices[select.id];
        }
    });

    regionSelect.innerHTML = '<option value="">Select Region</option>';
    provinceSelect.innerHTML = '<option value="">Select Province</option>';
    provinceSelect.disabled = true;
    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    citySelect.disabled = true;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    barangaySelect.disabled = true;

    initAddressChoices(regionSelect);
    initAddressChoices(provinceSelect);
    initAddressChoices(citySelect);
    initAddressChoices(barangaySelect);
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
            // Select the new passenger in the ticket passenger field so the user doesn't need to search again
            selectTicketPassenger({
                passenger_id: result.passenger_id,
                fullname: result.fullname,
                mobile_number: result.mobile_number || ''
            });
            // Remember the address for the next passenger registration
            saveLastPassengerAddress();
            // Reset the add passenger form for next use
            document.getElementById('addPassengerForm').reset();
            resetAddPassengerAddressFields();
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

// =============================================
// TICKET SELECTION
// =============================================

function formatBaseAmountInput() {
    const input = document.getElementById('ticketBaseAmount');
    if (!input) return;
    let raw = input.value.replace(/[^0-9.]/g, '');
    if (raw === '') { input.value = ''; return; }
    const parts = raw.split('.');
    const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? `${intPart}.${parts[1].slice(0, 2)}` : intPart;
}

function computeTicketTotal() {
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value.replace(/,/g, '')) || 0;
    const discountSelect = document.getElementById('ticketDiscount');
    const discountPercentage = (!discountSelect.value || discountSelect.value === '0') ? 0 : parseFloat(discountSelect.options[discountSelect.selectedIndex].dataset.discountPercentage) || 0;
    const discountAmount = (baseAmount * discountPercentage) / 100;

    // Compute service fee from the current fee configuration
    let serviceFee = 0;
    if (window.currentServiceFee) {
        if (window.currentServiceFee.type === 'PERCENT') {
            serviceFee = (baseAmount * window.currentServiceFee.value) / 100;
        } else {
            serviceFee = window.currentServiceFee.value;
        }
    }

    const serviceFeeInput = document.getElementById('ticketServiceFee');
    if (serviceFeeInput) {
        serviceFeeInput.value = serviceFee.toFixed(2);
    }

    const serviceFeeDisplay = document.getElementById('ticketServiceFeeDisplay');
    if (serviceFeeDisplay) {
        if (window.currentServiceFee) {
            if (window.currentServiceFee.type === 'PERCENT') {
                serviceFeeDisplay.textContent = `₱${serviceFee.toFixed(2)} (${window.currentServiceFee.value}% of Base)`;
            } else if (window.currentServiceFee.type === 'FIXED') {
                serviceFeeDisplay.textContent = `₱${serviceFee.toFixed(2)} (Fixed)`;
            } else {
                serviceFeeDisplay.textContent = `₱${serviceFee.toFixed(2)}`;
            }
        } else {
            serviceFeeDisplay.textContent = 'No service fee configured';
        }
    }

    const total = baseAmount + serviceFee - discountAmount;

    // Update displays
    document.getElementById('ticketBaseAmountDisplay').textContent = `₱${baseAmount.toFixed(2)}`;
    document.getElementById('ticketTotalDisplay').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showInsufficientWalletBalance(required, available = null) {
    const balanceDetails = Number.isFinite(available)
        ? ` Available: ₱${fmt(available)}. Required: ₱${fmt(required)}.`
        : ` Required: ₱${fmt(required)}.`;
    showToast(
        'danger',
        'Insufficient Wallet Balance',
        `The selected wallet balance is insufficient to cover the ticket base fare.${balanceDetails} Please top up the wallet or select another wallet.`
    );
}

function toggleTicketSpecialAction() {
    const wrapper = document.getElementById('ticketSpecialActionWrapper');
    const toggleRow = document.getElementById('ticketSpecialActionToggleRow');
    if (wrapper) wrapper.classList.remove('d-none');
    if (toggleRow) toggleRow.classList.add('d-none');
}

function clearTicketSpecialAction() {
    const radios = document.querySelectorAll('input[name="ticketSpecialAction"]');
    radios.forEach(r => r.checked = false);
    const wrapper = document.getElementById('ticketSpecialActionWrapper');
    const toggleRow = document.getElementById('ticketSpecialActionToggleRow');
    if (wrapper) wrapper.classList.add('d-none');
    if (toggleRow) toggleRow.classList.remove('d-none');
}

function getTicketSpecialActionLabel(value) {
    const map = {
        REBOOKING: 'Rebooking',
        REVALIDATE: 'Revalidate',
        RESCHEDULE: 'Reschedule'
    };
    return map[value] || '';
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
    const baseAmount = parseFloat(document.getElementById('ticketBaseAmount').value.replace(/,/g, '')) || 0;
    const serviceFee = parseFloat(document.getElementById('ticketServiceFee').value) || 0;
    const discountSelect = document.getElementById('ticketDiscount');
    const discountPercentage = (!discountSelect.value || discountSelect.value === '0') ? 0 : parseFloat(discountSelect.options[discountSelect.selectedIndex].dataset.discountPercentage) || 0;
    const discountAmount = (baseAmount * discountPercentage) / 100;
    const total = baseAmount + serviceFee - discountAmount;
    const selectedActionEl = document.querySelector('input[name="ticketSpecialAction"]:checked');
    const ticketAction = selectedActionEl ? selectedActionEl.value : '';
    const ticketActionLabel = getTicketSpecialActionLabel(ticketAction);
    const accommodationSelect = document.getElementById('ticketAccommodation');
    const accommodationId = accommodationSelect.value || null;
    const accommodationName = accommodationSelect.selectedOptions[0]?.textContent.trim() || '';
    const variantSelect = document.getElementById('ticketVariant');
    const variantId = variantSelect ? variantSelect.value || null : null;
    const variantOption = variantSelect?.selectedOptions[0];
    const variantName = variantOption?.dataset.variantName || '';
    const variantCode = variantOption?.dataset.variantCode || '';

    // Get operating provider and resolved wallet
    const providerSelect = document.getElementById('ticketProvider');
    const providerId = providerSelect ? providerSelect.value : null;
    const mainProviderId = document.getElementById('ticketMainProvider')?.value || providerId;
    const providerDetails = getTicketProviderDetails(providerId, mainProviderId);
    const walletSelect = document.getElementById('ticketWallet');
    let walletId = walletSelect ? walletSelect.value : null;
    const walletOption = walletSelect ? walletSelect.selectedOptions[0] : null;
    let walletBranchId = walletOption ? walletOption.dataset.branchId : null;

    if (!passengerId) { showToast('danger', 'Error', 'Please select a passenger.'); return; }
    if (window.POS_SETTINGS?.ticket_number_required && !ticketNumber) {
        showToast('danger', 'Error', 'Please enter ticket number.');
        return;
    }
    if (!baseAmount || baseAmount <= 0) { showToast('danger', 'Error', 'Please enter a valid base fare.'); return; }
    if (!discountSelect.value) { showToast('danger', 'Error', 'Please select a discount.'); return; }
    if (!accommodationId) { showToast('danger', 'Error', 'Please select an accommodation.'); return; }
    if (!providerId) { showToast('danger', 'Error', 'Please select a provider.'); return; }
    const variantWrapper = document.getElementById('ticketVariantWrapper');
    if (variantWrapper && !variantWrapper.classList.contains('d-none') && !variantSelect.value) { showToast('danger', 'Error', 'Please select a ticket variant.'); return; }

    const resolvedWallet = window.selectedResolvedWallet;
    const hasActiveVariant = variantWrapper && !variantWrapper.classList.contains('d-none');
    if (hasActiveVariant) {
        walletId = resolvedWallet?.wallet_id || null;
        walletBranchId = resolvedWallet?.branch_id || null;
    }

    let walletBalance = resolvedWallet && resolvedWallet.wallet_id
        ? parseFloat(resolvedWallet.current_balance)
        : NaN;
    if (!Number.isFinite(walletBalance)) {
        const optionBalance = hasActiveVariant ? variantOption?.dataset.walletBalance : walletOption?.dataset.balance;
        walletBalance = optionBalance === undefined || optionBalance === '' ? NaN : parseFloat(optionBalance);
    }
    if (!hasActiveVariant && !window.POS_SETTINGS?.allow_insufficient_wallet && Number.isFinite(walletBalance) && Math.round((baseAmount - walletBalance) * 100) > 0) {
        showInsufficientWalletBalance(baseAmount, walletBalance);
        return;
    }

    if (!walletId) {
        // When variants are shown, wallet dropdown is hidden but resolved wallet is stored
        if (resolvedWallet && resolvedWallet.wallet_id) {
            walletId = resolvedWallet.wallet_id;
            walletBranchId = resolvedWallet.branch_id || null;
        } else {
            showToast('danger', 'Wallet Required', 'Please select or resolve an active wallet before adding this ticket.');
            return;
        }
    }
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
        accommodationName,
        variantId,
        variantName,
        variantCode,
        stockControlled: variantOption ? variantOption.dataset.stockControlled !== '0' : false,
        ...providerDetails,
        total,
        providerId,
        walletId,
        ticketAction,
        ticketActionLabel,
        branchId: walletBranchId || window.POS_BRANCH_ID
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
    clearTicketSpecialAction();
    document.getElementById('ticketNumber').value = '';
    document.getElementById('ticketBaseAmount').value = '';
    document.getElementById('ticketDiscount').value = '';
    document.getElementById('ticketAccommodation').value = '';
    document.getElementById('ticketMainProvider').value = '';
    refreshMainProviderChoices();
    document.getElementById('ticketSubProvider').innerHTML = '<option value="">Select Main Provider First</option>';
    document.getElementById('ticketSubProvider').disabled = true;
    document.getElementById('subProviderWrapper').classList.add('d-none');
    document.getElementById('ticketProvider').value = '';
    document.getElementById('ticketVariant').innerHTML = '<option value="">Select Provider First</option>';
    document.getElementById('ticketVariant').disabled = true;
    document.getElementById('ticketVariantWrapper').classList.add('d-none');
    document.getElementById('variantRequiredMarker').classList.add('d-none');
    destroyVariantChoices();
    document.getElementById('mainProviderBalanceText').textContent = '';
    const walletWrapper = document.getElementById('walletWrapper');
    if (walletWrapper) walletWrapper.classList.remove('d-none');
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
            const ticketAttributes = [
                item.mainProviderName ? { label: 'Provider', value: item.mainProviderName, icon: 'fa-building', className: 'provider' } : null,
                item.subProviderName ? { label: 'Sub-provider', value: item.subProviderName, icon: 'fa-route', className: 'sub-provider' } : null,
                item.variantName ? { label: 'Ticket type', value: item.variantName, icon: 'fa-ticket-alt', className: 'variant' } : null,
                item.accommodationName ? { label: 'Accommodation', value: item.accommodationName, icon: 'fa-bed', className: 'accommodation' } : null
            ].filter(Boolean);
            const attributesHtml = ticketAttributes.length
                ? `<div class="cart-ticket-attributes">${ticketAttributes.map(attribute => `
                    <div class="cart-ticket-attribute cart-ticket-attribute-${attribute.className}">
                      <span class="fas ${attribute.icon}"></span>
                      <div><span>${attribute.label}</span><strong>${escapeHtml(attribute.value)}</strong></div>
                    </div>`).join('')}</div>`
                : '';
            html += `
            <article class="cart-item cart-ticket-item">
              <div class="cart-ticket-header">
                <div class="cart-ticket-identity min-width-0">
                  <span class="cart-ticket-icon"><span class="fas fa-ticket-alt"></span></span>
                  <div class="min-width-0">
                    <div class="cart-ticket-passenger">${escapeHtml(item.passengerName || '-')}</div>
                    <div class="cart-ticket-number">Ticket #${escapeHtml(item.ticketNumber || '-')}</div>
                  </div>
                </div>
                <div class="cart-ticket-actions">
                  <span class="cart-ticket-total">₱${fmt(item.total)}</span>
                  <button class="cart-ticket-remove" onclick="removeCartItem(${idx})" title="Remove ticket" aria-label="Remove ticket">
                    <span class="fas fa-times"></span>
                  </button>
                </div>
              </div>
              ${attributesHtml}
              <div class="cart-ticket-pricing">
                <div><span>Base fare</span><strong>₱${fmt(item.baseAmount)}</strong></div>
                <div><span>Service fee</span><strong>₱${fmt(item.serviceFee)}</strong></div>
                ${item.discountAmount > 0 ? `<div class="cart-ticket-discount"><span>Discount</span><strong>-₱${fmt(item.discountAmount)}</strong></div>` : ''}
              </div>
              ${item.ticketAction ? `<div class="cart-ticket-action"><span class="badge bg-soft-info text-info">${escapeHtml(item.ticketActionLabel)}</span></div>` : ''}
            </article>`;
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
}

function resetTicketEntryForm() {
    resetPassengerField();
    ['ticketNumber', 'ticketBaseAmount', 'ticketServiceFee', 'ticketProvider'].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });

    ['ticketDiscount', 'ticketAccommodation'].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });

    const mainSelect = document.getElementById('ticketMainProvider');
    const subSelect = document.getElementById('ticketSubProvider');
    const variantSelect = document.getElementById('ticketVariant');
    const walletSelect = document.getElementById('ticketWallet');
    const subWrapper = document.getElementById('subProviderWrapper');
    const variantWrapper = document.getElementById('ticketVariantWrapper');
    const walletWrapper = document.getElementById('walletWrapper');

    if (mainProviderChoices) {
        mainProviderChoices.setChoiceByValue('');
    } else if (mainSelect) {
        mainSelect.value = '';
    }
    if (subSelect) {
        subSelect.innerHTML = '<option value="">Select Main Provider First</option>';
        subSelect.value = '';
        subSelect.disabled = true;
    }
    if (variantSelect) {
        variantSelect.innerHTML = '<option value="">Select Provider First</option>';
        variantSelect.value = '';
        variantSelect.disabled = true;
    }
    if (walletSelect) {
        walletSelect.innerHTML = '<option value="">Select Provider First</option>';
        walletSelect.value = '';
        walletSelect.disabled = true;
    }
    if (subWrapper) subWrapper.classList.add('d-none');
    if (variantWrapper) variantWrapper.classList.add('d-none');
    if (walletWrapper) walletWrapper.classList.remove('d-none');

    const balanceText = document.getElementById('mainProviderBalanceText');
    if (balanceText) balanceText.textContent = '';
    window.selectedResolvedWallet = null;
    destroyVariantChoices();
    applyTicketNumberRequirement();
}

// =============================================
// PAYMENT
// =============================================

function getCartTotal() {
    return cart.reduce((s, i) => s + i.total, 0);
}

async function fetchTicketWalletBalance(ticket) {
    const walletId = parseInt(ticket?.walletId, 10);
    if (!walletId) throw new Error('No wallet is associated with this ticket.');

    const encodedWalletId = typeof IdEncoder !== 'undefined' ? IdEncoder.encode(walletId) : walletId;
    const response = await fetch(`${window.BASE_URL}/api/wallets?id=${encodeURIComponent(encodedWalletId)}&_realtime=${Date.now()}`, {
        cache: 'no-store'
    });
    const data = await response.json();
    if (!response.ok || !data.success || !data.data) {
        throw new Error(data.error || 'Unable to verify the wallet balance.');
    }

    const balance = parseFloat(data.data.current_balance);
    if (!Number.isFinite(balance)) throw new Error('Unable to verify the wallet balance.');
    return balance;
}

async function proceedToPayment() {
    if (cart.length === 0) { return; }

    const ticket = cart.find(item => item.type === 'ticket');
    if (ticket && !ticket.variantId && !window.POS_SETTINGS?.allow_insufficient_wallet) {
        let walletBalance;
        try {
            walletBalance = await fetchTicketWalletBalance(ticket);
        } catch (error) {
            console.error('[POS] Wallet balance verification failed:', error);
            showToast('danger', 'Wallet Verification Required', 'Unable to verify the selected wallet balance. Please refresh the wallet and try again.');
            return;
        }

        const baseAmount = parseFloat(ticket.baseAmount) || 0;
        if (Math.round((baseAmount - walletBalance) * 100) > 0) {
            showInsufficientWalletBalance(baseAmount, walletBalance);
            return;
        }
    }

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
            const detailBadges = buildTicketDetailBadges(item);
            html += `
                <div class="payment-cart-ticket d-flex justify-content-between align-items-start py-2 ${idx > 0 ? 'border-top' : ''}">
                    <div class="min-width-0 pe-2">
                        <div class="fw-semibold text-dark">${escapeHtml(item.passengerName || '-')}</div>
                        <div class="text-muted small">Ticket #${escapeHtml(item.ticketNumber || '-')}</div>
                        ${detailBadges ? `<div class="cart-detail-badges mt-1">${detailBadges}</div>` : ''}
                        <div class="text-muted small mt-1">Base fare ₱${fmt(item.baseAmount)} • Fee ₱${fmt(item.serviceFee)}${item.discountAmount > 0 ? ` • Discount ₱${fmt(item.discountAmount)}` : ''}</div>
                    </div>
                    <div class="fw-bold flex-shrink-0">₱${fmt(item.total)}</div>
                </div>
            `;
        } else {
            html += `
                <div class="d-flex justify-content-between align-items-start py-2 ${idx > 0 ? 'border-top' : ''}">
                    <div>
                        <div class="fw-semibold text-dark">${item.serviceName || item.name || 'Service'}</div>
                        <div class="text-muted small">
                            ${item.qty > 1 ? item.qty + ' × ' : ''}₱${fmt(item.unitPrice)}${item.description ? ' • ' + item.description : ''}
                        </div>
                    </div>
                    <div class="fw-bold">₱${fmt(item.total)}</div>
                </div>
            `;
        }
    });

    cartItemsContainer.innerHTML = html || '<div class="text-muted text-center py-3">No items in cart</div>';
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

    // Adjust payment entry proportions without changing the underlying fields or process.
    const hasBankAccount = activePaymentMethod.type === 'BANK_TRANSFER' || activePaymentMethod.type === 'E_WALLET';
    const paymentEntryRow = document.getElementById('paymentEntryRow');
    paymentEntryRow.classList.toggle('payment-has-bank-account', hasBankAccount);
    paymentEntryRow.classList.toggle('payment-has-reference', activePaymentMethod.requiresReference);
    paymentEntryRow.classList.toggle('payment-has-charge-account', activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer);

    // Filter bank accounts by payment method type while keeping the native select in sync.
    const bankSelect = document.getElementById('bankAccountSelect');
    ensureBankAccountChoices();
    const options = bankSelect.querySelectorAll('option:not([value=""])');
    options.forEach(opt => {
        const methodType = opt.dataset.methodType || '';
        const isAvailable = !methodType || methodType === activePaymentMethod.type;
        opt.style.display = isAvailable ? '' : 'none';
    });
    bankSelect.value = '';
    refreshBankAccountChoices(activePaymentMethod.type);

    // If method tracks credit/billing, default the charge account to the ticket passenger
    // while allowing the cashier to choose a different account for this payment line.
    if (activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) {
        const ticketItem = cart.find(i => i.type === 'ticket');
        if (ticketItem && ticketItem.passengerId) {
            setChargeAccountSelection(ticketItem.passengerId, ticketItem.passengerName || '');
            showChargeAccountSelection();
            document.getElementById('paymentEntryRow').style.display = '';
        } else {
            // Service-only transactions still require the cashier to choose the account.
            setChargeAccountSelection(null, '');
            showChargeAccountSelection();
            openCustomerModal();
        }
    } else {
        resetChargeAccountSelection();
        document.getElementById('paymentEntryRow').style.display = '';
    }
    computeChange();
}

function cancelPaymentEntry() {
    document.getElementById('paymentEntryRow').style.display = 'none';
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active-payment'));
    activePaymentMethod = null;
    resetChargeAccountSelection();
    resetBankAccountChoice();
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
        showToast('danger', 'Charge Account Required', 'Please select the account to charge for this payment line.'); return;
    }
    const bankAccountId = document.getElementById('bankAccountSelect').value || null;
    if ((activePaymentMethod.type === 'BANK_TRANSFER' || activePaymentMethod.type === 'E_WALLET') && !bankAccountId) {
        showToast('danger', 'Bank Account Required', 'Please select a bank account.'); return;
    }

    paymentLines.push({
        methodId: activePaymentMethod.id,
        methodName: activePaymentMethod.name,
        methodType: activePaymentMethod.type,
        requiresConfirmation: activePaymentMethod.requiresConfirmation,
        tracksCredit: activePaymentMethod.tracksCredit,
        requiresCustomer: activePaymentMethod.requiresCustomer,
        amount,
        referenceNumber: refNum || null,
        bankAccountId,
        passengerId: (activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) ? selectedCustomerId : null,
        passengerName: (activePaymentMethod.tracksCredit || activePaymentMethod.requiresCustomer) ? selectedCustomerName : null
    });

    document.getElementById('paymentEntryRow').style.display = 'none';
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active-payment'));
    activePaymentMethod = null;
    resetChargeAccountSelection();
    resetBankAccountChoice();
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
                    ${(p.tracksCredit || p.requiresCustomer) && p.passengerId ? `<span class="badge bg-soft-danger text-danger ms-2 small"><span class="fas fa-file-invoice-dollar me-1"></span>Billed to: ${escapeHtml(p.passengerName || 'Selected account')}</span>` : ''}
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
// TICKET STOCK RESERVATION + CONFIRM ORDER
// =============================================

async function reservePosTicketStock(ticket) {
    if (!ticket?.variantId || ticket.stockControlled === false) {
        return null;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

    const response = await fetch(`${window.BASE_URL}/api/ticket-stock`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'reserve',
            branch_id: window.POS_BRANCH_ID,
            provider_id: ticket.providerId,
            variant_id: ticket.variantId,
            qty: 1,
            session_id: String(window.POS_SESSION_ID),
            expires_at: new Date(Date.now() + 10 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ')
        })
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'Unable to reserve ticket stock.');
    return { branchId: window.POS_BRANCH_ID, providerId: ticket.providerId, variantId: ticket.variantId, qty: 1, sessionId: String(window.POS_SESSION_ID) };
}

async function releasePosTicketStock(reservation) {
    if (!reservation) return;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        await fetch(`${window.BASE_URL}/api/ticket-stock`, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'release', ...reservation })
        });
    } catch (error) {
        console.error('[POS stock] Failed to release reservation:', error);
    }
}

async function confirmOrder() {
    if (!window.POS_HAS_SESSION) {
        showToast('danger', 'No Session', 'Open a session first.'); return;
    }
    if (cart.length === 0) {
        showToast('danger', 'Cart Empty', 'Add items first.'); return;
    }

    // Auto-add the pending payment method/amount if the cashier clicked
    // Confirm & Process without pressing "Add Payment" first.
    if (activePaymentMethod) {
        const pendingAmount = parseFloat(document.getElementById('paymentAmount')?.value) || 0;
        if (pendingAmount > 0) {
            addPaymentLine();
            if (activePaymentMethod) return; // addPaymentLine failed validation
        }
    }

    const total = getCartTotal();
    const paid = paymentLines.reduce((s, p) => s + p.amount, 0);
    if (paid < total) {
        showToast('danger', 'Insufficient Payment', 'Total paid is less than the amount due.'); return;
    }

    // Reserve physical stock before checkout for stock-controlled variants.
    const hasTicket = cart.some(item => item.type === 'ticket');
    let reservedStock = null;
    if (hasTicket) {
        try {
            reservedStock = await reservePosTicketStock(cart.find(item => item.type === 'ticket'));
        } catch (error) {
            showToast('danger', 'Stock unavailable', error.message);
            return;
        }
    }

    const btn = document.getElementById('confirmOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Processing...';

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
                variant_id: ticket.variantId || null,
                total_amount: ticket.total,
                provider_id: ticket.providerId,
                wallet_id: ticket.walletId,
                ticket_action: ticket.ticketAction || null
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

            const postTransactionRefresh = Promise.allSettled([
                refreshPosWalletBalances(),
                Promise.resolve(loadRecentTransactions(1, false))
            ]);

            // Print receipt if enabled
            console.log('[POS] Checking printer settings for receipt print...');
            console.log('[POS] PRINTER_SETTINGS:', window.PRINTER_SETTINGS);
            console.log('[POS] PosPrinter available:', !!window.PosPrinter);

            if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.enabled && window.PosPrinter) {
                try {
                    // Reload saved config so terminal overrides (auto print, copies, printer) are current
                    window.PosPrinter.loadSavedConfig();

                    const printerStatus = window.PosPrinter.getStatus();
                    console.log('[POS] Printer status:', printerStatus);

                    if (printerStatus.printerName) {
                        // Build transaction data for receipt
                        const transactionData = {
                            id: result.transaction_id || result.order_id || result.id,
                            transaction_code: result.transaction_code,
                            branch_name: window.POS_BRANCH_NAME || '',
                            cashier_name: window.POS_USER_NAME || '',
                            payment_method: paymentLines.length > 0 ? paymentLines[0].methodName : '',
                            subtotal: cart.reduce((sum, item) => sum + (item.type === 'ticket'
                                ? parseFloat(item.baseAmount || 0) + parseFloat(item.serviceFee || 0)
                                : parseFloat(item.total || 0)), 0),
                            discount: cart.reduce((s, i) => s + parseFloat(i.discountAmount || 0), 0),
                            tax: 0,
                            total: total,
                            amount_tendered: paid,
                            change_amount: paid - total,
                            // BIR: OR number and VAT data
                            or_number: result.or_number || null,
                            vat_data: result.vat_data || null,
                            items: cart.map(item => ({
                                name: item.type === 'ticket'
                                    ? `Ticket #${item.ticketNumber || '-'} - ${item.passengerName || 'Passenger'}`
                                    : item.description || item.serviceName || item.type,
                                details: item.type === 'ticket'
                                    ? [
                                        item.mainProviderName ? `Provider: ${item.mainProviderName}` : '',
                                        item.subProviderName ? `Sub-provider: ${item.subProviderName}` : '',
                                        item.variantName ? `Variant: ${item.variantName}${item.variantCode ? ` (${item.variantCode})` : ''}` : '',
                                        item.accommodationName ? `Accommodation: ${item.accommodationName}` : ''
                                    ].filter(Boolean)
                                    : [],
                                quantity: item.qty || 1,
                                price: item.unitPrice || item.total || 0,
                                base_amount: parseFloat(item.baseAmount || item.unitPrice || item.total || 0),
                                service_fee: parseFloat(item.serviceFee || 0),
                                discount_amount: parseFloat(item.discountAmount || 0),
                                total: item.total
                            }))
                        };

                        // Print based on settings
                        // autoPrint takes precedence: if enabled, print immediately (no preview)
                        if (window.PRINTER_SETTINGS.autoPrint) {
                            await window.PosPrinter.printReceipt(transactionData);
                        } else if (window.PRINTER_SETTINGS.showPreview) {
                            // Preview mode: show receipt preview with Print / Cancel
                            const shouldPrint = await window.PosPrinter.showPreview(transactionData);
                            if (shouldPrint) {
                                await window.PosPrinter.printReceipt(transactionData);
                            }
                        } else {
                            // Manual mode: show nice confirmation popup with copies selector
                            const printConfirm = await showPrintConfirmationModal(result.transaction_code, paid - total);
                            if (printConfirm.shouldPrint) {
                                await window.PosPrinter.printReceipt(transactionData, { copies: printConfirm.copies });
                            }
                        }
                    } else {
                        console.warn('[POS] No printer configured:', printerStatus);
                        if (window.PRINTER_SETTINGS.autoPrint) {
                            showToast('warning', 'Printer Not Configured', 'Auto-print is enabled but no printer is set up. Please visit Printer Setup.');
                        }
                    }
                } catch (printError) {
                    console.error('Receipt printing error:', printError);
                    if (window.PRINTER_SETTINGS.autoPrint) {
                        showToast('warning', 'Auto-Print Failed', 'Could not print receipt automatically. Please check QZ Tray or the printer setup.');
                    }
                }
            }

            cart = [];
            paymentLines = [];
            ticketInCart = null;
            saveCartToStorage();
            renderCart();
            renderPaymentLines();
            resetTicketEntryForm();
            paymentModal.hide();
            itemEntryModal.hide();
            await postTransactionRefresh;
            setPosRealtimeStatus('ok', `Updated after transaction • ${new Date().toLocaleTimeString('en-PH')}`);
        } else {
            await releasePosTicketStock(reservedStock);
            const errorMessage = result.error || 'Unknown error.';
            const walletInsufficient = result.code === 'INSUFFICIENT_WALLET_BALANCE' || /insufficient wallet balance/i.test(errorMessage);
            showToast(
                'danger',
                walletInsufficient ? 'Insufficient Wallet Balance' : 'Transaction Failed',
                walletInsufficient
                    ? 'The selected wallet balance is insufficient to cover the ticket base fare. Please top up the wallet or select another wallet.'
                    : errorMessage
            );
        }
    } catch (e) {
        await releasePosTicketStock(reservedStock);
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

function positionPosAdjustmentDropdown(toggle, menu) {
    if (!toggle || !menu || !menu.classList.contains('show')) return;

    menu.style.setProperty('position', 'fixed', 'important');
    menu.style.setProperty('transform', 'none', 'important');
    menu.style.setProperty('right', 'auto', 'important');
    menu.style.setProperty('left', '0px', 'important');
    menu.style.setProperty('top', '0px', 'important');

    const toggleRect = toggle.getBoundingClientRect();
    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;
    const edge = 8;
    let left = toggleRect.right - menuWidth;
    let top = toggleRect.bottom + 4;

    if (left < edge) left = toggleRect.left;
    if (left + menuWidth > window.innerWidth - edge) left = window.innerWidth - menuWidth - edge;
    if (top + menuHeight > window.innerHeight - edge && toggleRect.top > menuHeight + edge) {
        top = toggleRect.top - menuHeight - 4;
    }

    menu.style.setProperty('left', `${Math.max(edge, left)}px`, 'important');
    menu.style.setProperty('top', `${Math.max(edge, top)}px`, 'important');
}

function restorePosAdjustmentDropdown(menu) {
    if (!menu || !menu._posAdjustmentPlaceholder) return;

    const placeholder = menu._posAdjustmentPlaceholder;
    if (placeholder.parentNode) placeholder.parentNode.insertBefore(menu, placeholder);
    placeholder.remove();

    menu.classList.remove('pos-adjust-dropdown-menu-portal');
    menu.style.removeProperty('position');
    menu.style.removeProperty('transform');
    menu.style.removeProperty('right');
    menu.style.removeProperty('left');
    menu.style.removeProperty('top');
    menu.style.removeProperty('z-index');
    menu.removeAttribute('data-bs-popper');
    window.removeEventListener('scroll', menu._posAdjustmentReposition, true);
    window.removeEventListener('resize', menu._posAdjustmentReposition);
    delete menu._posAdjustmentPlaceholder;
    delete menu._posAdjustmentToggle;
    delete menu._posAdjustmentReposition;
}

function setupPosAdjustmentDropdownPortal() {
    if (window.posAdjustmentDropdownPortalReady) return;
    window.posAdjustmentDropdownPortalReady = true;

    document.addEventListener('show.bs.dropdown', event => {
        const toggle = event.target.closest?.('.pos-adjust-dropdown-toggle');
        if (!toggle) return;

        const wrapper = toggle.closest('.pos-adjust-dropdown');
        const menu = wrapper?.querySelector('.dropdown-menu');
        if (!menu || menu._posAdjustmentPlaceholder) return;

        menu._posAdjustmentPlaceholder = document.createComment('pos-adjust-dropdown-placeholder');
        menu._posAdjustmentToggle = toggle;
        menu._posAdjustmentReposition = () => positionPosAdjustmentDropdown(toggle, menu);
        menu.parentNode.insertBefore(menu._posAdjustmentPlaceholder, menu);
        document.body.appendChild(menu);
        menu.classList.add('pos-adjust-dropdown-menu-portal');
        window.addEventListener('scroll', menu._posAdjustmentReposition, true);
        window.addEventListener('resize', menu._posAdjustmentReposition);
    });

    document.addEventListener('shown.bs.dropdown', event => {
        const toggle = event.target.closest?.('.pos-adjust-dropdown-toggle');
        if (!toggle) return;
        const menu = toggle._posAdjustmentMenu || document.querySelector('.pos-adjust-dropdown-menu-portal.show');
        if (menu) {
            toggle._posAdjustmentMenu = menu;
            positionPosAdjustmentDropdown(toggle, menu);
        }
    });

    document.addEventListener('hide.bs.dropdown', event => {
        const toggle = event.target.closest?.('.pos-adjust-dropdown-toggle');
        if (toggle?._posAdjustmentMenu) restorePosAdjustmentDropdown(toggle._posAdjustmentMenu);
    });

    document.addEventListener('hidden.bs.dropdown', event => {
        const toggle = event.target.closest?.('.pos-adjust-dropdown-toggle');
        if (toggle?._posAdjustmentMenu) {
            restorePosAdjustmentDropdown(toggle._posAdjustmentMenu);
            delete toggle._posAdjustmentMenu;
        }
    });
}

let allTransactions = [];
let currentPage = 1;
let itemsPerPage = 10;
let totalPages = 1;
let totalItems = 0;

const isApprovedVoid = txn => txn.adjustment_type === 'VOID' && txn.adjustment_approval_status === 'APPROVED';
const isTechnicalIssueVoid = txn => isApprovedVoid(txn) && isTechnicalIssueReason(txn.adjustment_reason_category);
const getTechnicalLostSalesAmount = txn => Number(txn.lost_sales_void_fee) || 0;
const isCashierResponsibilityVoid = txn => txn.adjustment_type === 'VOID'
    && String(txn.adjustment_responsibility || '').toUpperCase() === 'CASHIER';
const getTransactionDisplayAmount = txn => isTechnicalIssueVoid(txn)
    ? 0
    : isCashierResponsibilityVoid(txn)
        ? (Number(txn.adjustment_amount) || 0)
        : isApprovedVoid(txn)
            ? (Number(txn.void_fee) || 0) + (Number(txn.void_service_fee) || 0)
            : txn.total_amount;

function loadRecentTransactions(page = 1, showLoading = true) {
    currentPage = page;
    const list = document.getElementById('recentTransactionsList');
    if (showLoading) {
        list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading transactions...</td></tr>';
    }
    
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
        list.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><div class="d-flex flex-column align-items-center"><span class="fas fa-inbox mb-3" style="font-size: 2.5rem; color: #adb5bd;"></span><span class="fw-medium" style="color: #6c757d;">No transactions found</span><small class="text-muted mt-1">Try adjusting your filters or click Refresh</small></div></td></tr>';
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

        // Build a single primary state badge and small extras only when they add info.
        const isPending = hasPendingCancellation || txn.adjustment_approval_status === 'PENDING';
        const isVoid = txn.adjustment_type === 'VOID' && txn.adjustment_approval_status === 'APPROVED';
        const isRefund = (txn.adjustment_type === 'REFUND' && txn.adjustment_approval_status === 'APPROVED') || txn.status === 'refunded';

        let statusBadge;
        if (isPending) {
            statusBadge = '<span class="badge bg-soft-warning text-warning"><i class="fas fa-clock me-1"></i>Pending Cancel</span>';
        } else if (isVoid) {
            statusBadge = '<span class="badge bg-soft-warning text-warning"><i class="fas fa-ban me-1"></i>Voided</span>';
        } else if (isRefund) {
            statusBadge = '<span class="badge bg-soft-info text-info"><i class="fas fa-hand-holding-usd me-1"></i>Refunded</span>';
        } else if (txn.status === 'cancelled' || allItemsCancelled) {
            statusBadge = '<span class="badge bg-soft-danger text-danger"><i class="fas fa-times-circle me-1"></i>Cancelled</span>';
        } else if (txn.status === 'completed' && hasCancelledItems) {
            statusBadge = '<span class="badge bg-soft-primary text-primary"><i class="fas fa-check-circle me-1"></i>Completed</span>';
            statusBadge += ` <span class="badge bg-soft-warning text-warning ms-1" title="${cancelledTicketCount} ticket(s) and ${cancelledServiceCount} service(s) cancelled"><i class="fas fa-exclamation-circle me-1"></i>Partially Cancelled</span>`;
        } else if (txn.status === 'completed') {
            statusBadge = '<span class="badge bg-soft-primary text-primary"><i class="fas fa-check-circle me-1"></i>Completed</span>';
        } else if (txn.status === 'booked') {
            statusBadge = '<span class="badge bg-soft-success text-success"><i class="fas fa-check-circle me-1"></i>Booked</span>';
        } else {
            statusBadge = `<span class="badge bg-soft-secondary text-secondary">${txn.status}</span>`;
        }

        const adjustmentResponsibility = String(txn.adjustment_responsibility || '').toLowerCase();
        const isCashierVoid = Boolean(txn.void_responsible_cashier);
        const responsibleCashierName = isCashierVoid
            ? String(txn.void_responsible_cashier)
            : String(txn.adjustment_responsible_cashier || '');
        if (isCashierVoid) {
            statusBadge += ` <span class="badge bg-soft-danger text-danger ms-1"><i class="fas fa-user-shield me-1"></i>Responsible Cashier: ${escapeHtml(responsibleCashierName)}</span>`;
        } else if (adjustmentResponsibility && adjustmentResponsibility !== 'none' && txn.adjustment_approval_status !== 'PENDING') {
            const responsibilityLabel = adjustmentResponsibility === 'cashier' ? 'Cashier charge' : 'Customer responsibility';
            const responsibleCashier = adjustmentResponsibility === 'cashier' && responsibleCashierName
                ? `: ${escapeHtml(responsibleCashierName)}`
                : '';
            statusBadge += ` <span class="badge bg-soft-danger text-danger ms-1"><i class="fas fa-user-shield me-1"></i>${responsibilityLabel}${responsibleCashier}</span>`;
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

        // Provider cell (operating provider / wallet provider)
        let providerCell = '-';
        if (isOrderBased && orderItems.length > 0) {
            const providers = [...new Set(orderItems.filter(i => i.provider_name || i.parent_provider_name || i.variant_name).map(i => buildTransactionProviderWallet(i)))];
            providerCell = providers.length > 0 ? providers.join('<br>') : '-';
        } else if (txn.provider_name || txn.parent_provider_name || txn.variant_name) {
            providerCell = buildTransactionProviderWallet(txn);
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
        const paymentTotal = txn.payments && txn.payments.length > 0
            ? txn.payments.reduce((sum, payment) => sum + (parseFloat(payment.amount) || 0), 0)
            : (parseFloat(txn.amount_paid) || 0);
        let paymentCell = '-';
        if (isOrderBased && txn.payments && txn.payments.length > 0) {
            const methodTypeIcon = { CASH: 'fa-money-bill-wave', BANK_TRANSFER: 'fa-university', E_WALLET: 'fa-mobile-alt', CHARGE: 'fa-file-invoice-dollar', OTHER: 'fa-receipt' };
            const methodTypeColor = { CASH: 'text-success', BANK_TRANSFER: 'text-primary', E_WALLET: 'text-info', CHARGE: 'text-warning', OTHER: 'text-secondary' };
            paymentCell = txn.payments.map(p => {
                const icon  = methodTypeIcon[p.method_type]  || 'fa-credit-card';
                const color = methodTypeColor[p.method_type] || 'text-secondary';
                const displayedPaymentAmount = isVoid ? 0 : (parseFloat(p.amount) || 0);
                return `<div><i class="fas ${icon} ${color} me-1" style="font-size:0.75rem;"></i><span class="small">${p.method_name}</span> <span class="fw-semibold small">₱${fmt(displayedPaymentAmount)}</span></div>`;
            }).join('');
        } else if (!isOrderBased && txn.payment_method) {
            paymentCell = `<span class="small">${txn.payment_method}${isVoid ? ' ₱0.00' : ''}</span>`;
        }

        // Items breakdown (collapsible) for order-based
        let itemsBreakdown = '';
        if (isOrderBased && orderItems.length > 0) {
            const rowId = `order-items-${txn.order_id}`;
            const ticketItems = orderItems.filter(item => item.item_type === 'TICKET');
            const originalTicketTotal = ticketItems.reduce((sum, item) => sum
                + (parseFloat(item.ticket_total_amount) || parseFloat(item.total_amount) || 0), 0);
            const getTransactionItemAmount = item => {
                if (!isVoid || item.item_type !== 'TICKET') {
                    return parseFloat(item.total_amount) || 0;
                }
                const originalItemAmount = parseFloat(item.ticket_total_amount) || parseFloat(item.total_amount) || 0;
                if (ticketItems.length === 1) return paymentTotal;
                return originalTicketTotal > 0
                    ? paymentTotal * (originalItemAmount / originalTicketTotal)
                    : 0;
            };
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
                        <td colspan="2">${item.provider_name || item.parent_provider_name || item.variant_name ? buildTransactionProviderWallet(item) : '-'}</td>
                        <td>${td}</td>
                        <td>${item.origin && item.destination ? item.origin + ' → ' + item.destination : '-'}</td>
                        <td>₱${fmt(getTransactionItemAmount(item))}</td>
                        <td>${escapeHtml(item.ticket_number || '-')}</td>
                        <td colspan="2">${escapeHtml(item.transaction_code || '')}</td>
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
                        <td>-</td>
                        <td colspan="2">${escapeHtml(item.transaction_code || '')}</td>
                    </tr>`;
                }
            });
            itemsBreakdown = `<tr id="${rowId}" style="display:none;">
                <td colspan="9" class="p-0">
                  <table class="table table-sm mb-0 border-top">
                    <thead class="table-secondary"><tr style="font-size:0.75em;">
                      <th colspan="2">Item / Passenger</th><th colspan="2">Provider / Description</th>
                      <th>Travel Date</th><th>Route</th><th>Amount</th><th>Ticket Number</th><th colspan="2">Code</th>
                    </tr></thead>
                    <tbody>${itemRows}</tbody>
                  </table>
                </td>
              </tr>`;
        }

        // Cancel button - for legacy single-ticket or orders containing tickets
        let adjustmentActions = '';
        // Only count active tickets (not cancelled/refunded)
        const hasActiveTicketsInOrder = isOrderBased && orderItems.some(i =>
            i.item_type === 'TICKET' && !['cancelled', 'refunded'].includes(i.ticket_status)
        );
        const canCancelTicket = (!isOrderBased && txn.transaction_type === 'TICKET') || hasActiveTicketsInOrder;

        if (canCancelTicket && (txn.status === 'booked' || txn.status === 'completed')) {
            if (hasPendingCancellation) {
                adjustmentActions = `<span class="badge bg-soft-warning text-warning small" title="Cancellation requested by ${txn.cancellation_requested_by || 'Unknown'}"><i class="fas fa-clock me-1"></i>Cancel Pending</span>`;
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

                cancelData.branch_id = cancelData.branch_id || txn.branch_id || window.POS_BRANCH_ID || null;
                cancelData.variant_id = cancelData.variant_id || ticketItem?.variant_id || txn.variant_id || null;
                const encodedCancelData = encodeURIComponent(JSON.stringify(cancelData));
                const buildAdjustmentAction = (operationType, label, iconClass, buttonClass) => `<button type="button" class="dropdown-item ${buttonClass}"
                        data-txn-code="${cancelTxnCode}"
                        data-txn-type="TICKET"
                        data-operation-type="${operationType}"
                        data-base-amount="${operationType === 'VOID' ? 0 : cancelBaseAmount}"
                        data-service-fee="${cancelServiceFee}"
                        data-txn-data="${encodedCancelData}"
                        onclick="event.stopPropagation(); openCancelTicketModalFromButton(this)">
                    <span class="fas ${iconClass} me-2"></span>${label}
                </button>`;

                adjustmentActions = `${buildAdjustmentAction('REFUND', 'Refund', 'fa-hand-holding-usd', 'text-danger')}
                    ${buildAdjustmentAction('VOID', 'Void', 'fa-ban', 'text-warning')}`;
            }
        }

        // Toggle button for order items
        const toggleBtn = isOrderBased && orderItems.length > 0
            ? `<button class="btn btn-xs btn-outline-secondary p-1 ms-1" style="font-size:0.7rem;" onclick="toggleOrderItems('order-items-${txn.order_id}', this)" title="View items"><i class="fas fa-list"></i></button>`
            : '';

        // Reprint receipt button - only if printing is enabled
        let reprintButton = '';
        if (window.PRINTER_SETTINGS && window.PRINTER_SETTINGS.enabled && window.PosPrinter) {
            reprintButton = `<button type="button" class="dropdown-item text-info"
                    data-txn-id="${txn.id || txn.order_id}"
                    data-txn-code="${txn.transaction_code}"
                    onclick="event.stopPropagation(); closePosAdjustmentDropdownFromButton(this); reprintTransactionReceipt(this)">
                <span class="fas fa-print me-2"></span>Print
            </button>`;
        }

        const actionItems = `${reprintButton}${adjustmentActions}`;
        const actionButton = actionItems
            ? `<div class="dropdown d-inline-block pos-adjust-dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle pos-adjust-dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Transaction actions">
                    <span class="fas fa-ellipsis-v me-1"></span>Actions
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    ${actionItems}
                </div>
            </div>`
            : '<span class="text-muted small">N/A</span>';

        // Display amount - show original amount and pending/refunded amount if applicable
        // Use stored cash_refund_amount from database
        const pendingRefundAmount = parseFloat(txn.pending_refund_amount || 0);
        const pendingChargeAmount = parseFloat(txn.pending_charge_amount || 0);
        const pendingCashRefundAmount = parseFloat(txn.pending_cash_refund_amount || 0);
        const displayAmount = getTransactionDisplayAmount(txn);

        let amountDisplay = `₱${fmt(displayAmount)}`;
        if (isApprovedVoid(txn)) {
            if (isTechnicalIssueVoid(txn)) {
                const lostSalesAmount = getTechnicalLostSalesAmount(txn);
                if (lostSalesAmount > 0) {
                    amountDisplay += `<div class="text-danger" style="font-size:.72rem">Lost Sales -₱${fmt(lostSalesAmount)}</div>`;
                }
            } else {
                const voidFee = Number(txn.void_fee) || 0;
                const voidServiceFee = Number(txn.void_service_fee) || 0;
                const voidParts = [];
                if (voidFee > 0) voidParts.push(`Void Fee ₱${fmt(voidFee)}`);
                if (voidServiceFee > 0) voidParts.push(`service fee ₱${fmt(voidServiceFee)}`);
                if (voidParts.length > 0) {
                    amountDisplay += `<div class="text-muted" style="font-size:.72rem">${voidParts.join(' + ')}</div>`;
                }
            }
        }

        if (hasPendingCancellation && pendingRefundAmount > 0) {
            // Show detailed refund breakdown using stored values
            let refundBreakdown = '';
            if (pendingChargeAmount > 0 && pendingCashRefundAmount > 0) {
                // Mixed: both charge reversal and cash refund
                refundBreakdown = `<div class="small text-danger mt-1">
                    <div>Refund: ₱${fmt(pendingRefundAmount)}</div>
                    <div class="text-muted">• Charge reversal (debt): ₱${fmt(pendingChargeAmount)}</div>
                    <div class="fw-semibold">• Cash to give: ₱${fmt(pendingCashRefundAmount)}</div>
                </div>`;
            } else if (pendingChargeAmount > 0) {
                // Only charge reversal (no cash)
                refundBreakdown = `<div class="small text-danger mt-1">
                    <div>Refund: ₱${fmt(pendingRefundAmount)}</div>
                    <div class="text-muted">• Charge reversal (debt): ₱${fmt(pendingChargeAmount)}</div>
                    <div class="text-muted">• Cash to give: ₱0.00</div>
                </div>`;
            } else {
                // Only cash refund
                refundBreakdown = `<div class="small text-danger mt-1">
                    <div>Refund: ₱${fmt(pendingRefundAmount)}</div>
                    <div class="fw-semibold">• Cash to give: ₱${fmt(pendingCashRefundAmount)}</div>
                </div>`;
            }
            amountDisplay += ` ${refundBreakdown}`;
        } else if (txn.total_refunded_amount && parseFloat(txn.total_refunded_amount) > 0) {
            amountDisplay += ` <span class="text-danger small">(₱${fmt(txn.total_refunded_amount)} refunded)</span>`;
        }

        const cashierDisplay = isCashierVoid && txn.void_responsible_cashier
            ? `<span class="text-danger small ms-1">Responsible Cashier: ${escapeHtml(txn.void_responsible_cashier)}</span>`
            : txn.cashier_name
                ? `<span class="text-muted small ms-1">by ${escapeHtml(txn.cashier_name)}</span>`
                : '';

        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <strong>${escapeHtml(txn.transaction_code)}</strong>${toggleBtn}
                    </div>
                    <div>${typeBadge} ${cashierDisplay}</div>
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
                <td class="text-end">${actionButton}</td>
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

        if (!result.success || (!result.data && !result.transaction)) {
            showToast('error', 'Error', 'Failed to fetch transaction details.');
            reprintReceiptModal.hide();
            return;
        }

        const txn = result.transaction || result.data;

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
            // Branch address for reprint (original transaction's branch)
            street_address: txn.street_address || '',
            barangay_name: txn.barangay_name || '',
            city_municipality_name: txn.city_municipality_name || '',
            province_name: txn.province_name || '',
            region_name: txn.region_name || '',
            zip_code: txn.zip_code || '',
            landmark: txn.landmark || '',
            branch_contact: txn.branch_contact || '',
            // BIR: OR number and VAT data
            or_number: txn.or_full_number || txn.or_number || null,
            vat_data: txn.vat_data || null,
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
                const isTicket = item.item_type === 'TICKET';
                const name = isTicket
                    ? `Ticket #${item.ticket_number || item.transaction_code || '-'} - ${item.name || 'Passenger'}`
                    : (item.service_type_name || item.description || 'Service');
                const details = isTicket
                    ? [
                        item.parent_provider_name ? `Provider: ${item.parent_provider_name}` : (item.provider_name ? `Provider: ${item.provider_name}` : ''),
                        item.parent_provider_name && item.provider_name ? `Sub-provider: ${item.provider_name}` : '',
                        item.variant_name ? `Variant: ${item.variant_name}${item.variant_code ? ` (${item.variant_code})` : ''}` : '',
                        item.accommodation_name ? `Accommodation: ${item.accommodation_name}` : ''
                    ].filter(Boolean)
                    : [];
                return {
                    name,
                    details,
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

function getPosToday() {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${today.getFullYear()}-${month}-${day}`;
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterStatus').value = '';
    const dateInput = document.getElementById('filterDate');
    const today = getPosToday();
    if (dateInput?._flatpickr) {
        dateInput._flatpickr.setDate(today, false);
    } else if (dateInput) {
        dateInput.value = today;
    }
    localStorage.removeItem('pos_filter_search');
    localStorage.removeItem('pos_filter_type');
    localStorage.removeItem('pos_filter_status');
    localStorage.setItem('pos_filter_date', today);
    loadRecentTransactions(1);
}

function applyTicketNumberRequirement() {
    const input = document.getElementById('ticketNumber');
    const marker = document.getElementById('ticketNumberRequiredMark');
    const required = Boolean(window.POS_SETTINGS?.ticket_number_required);
    if (input) {
        input.required = required;
        input.placeholder = required ? 'Enter ticket number for tracking' : 'Optional ticket number';
    }
    if (marker) marker.classList.toggle('d-none', !required);
}

// Load recent transactions on page load
document.addEventListener('DOMContentLoaded', function() {
    applyTicketNumberRequirement();
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
                const filterStorageVersion = 'all-history-v1';
                const savedFilterStorageVersion = localStorage.getItem('pos_filter_storage_version');
                if (savedFilterStorageVersion !== filterStorageVersion) {
                    localStorage.removeItem('pos_filter_date');
                    localStorage.setItem('pos_filter_storage_version', filterStorageVersion);
                }
                const savedDate = localStorage.getItem('pos_filter_date') || getPosToday();
                localStorage.setItem('pos_filter_date', savedDate);

                // Validate status - 'booked' is not valid for orders, clear it
                if (savedStatus === 'booked') {
                    savedStatus = '';
                    localStorage.removeItem('pos_filter_status');
                }

                if (savedSearch !== null) document.getElementById('filterSearch').value = savedSearch;
                if (savedType !== null) document.getElementById('filterType').value = savedType;
                if (savedStatus !== null && savedStatus !== '') document.getElementById('filterStatus').value = savedStatus;

                // Restore date picker value when a date filter was previously selected
                if (savedDate) {
                    if (savedDate.includes(' to ')) {
                        const [startDate, endDate] = savedDate.split(' to ');
                        dateInput._flatpickr.setDate([startDate, endDate]);
                    } else {
                        dateInput._flatpickr.setDate(savedDate);
                    }
                } else {
                    dateInput._flatpickr.clear();
                    localStorage.removeItem('pos_filter_date');
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
    startPosRealtimePolling();
});

// =============================================
// TICKET CANCELLATION
// =============================================

function displayCancellationPolicy(operationType = null) {
    const settings = window.CANCELLATION_SETTINGS || {};
    console.log('Cancellation settings:', settings);

    const operation = operationType || document.getElementById('cancelOperationType')?.value || 'REFUND';
    const isVoid = operation === 'VOID';
    const cancellationRequiresConfirmation = settings.requires_confirmation !== undefined
        ? Boolean(settings.requires_confirmation)
        : true;
    const returnRequiresConfirmation = settings.return_requires_confirmation !== undefined
        ? Boolean(settings.return_requires_confirmation)
        : cancellationRequiresConfirmation;
    const requiresConfirmation = isVoid
        ? (settings.void_requires_confirmation !== undefined
            ? Boolean(settings.void_requires_confirmation)
            : cancellationRequiresConfirmation)
        : returnRequiresConfirmation;
    const processingEl = document.getElementById('cancelPolicyProcessing');
    const approvalEl = document.getElementById('cancelPolicyApproval');

    if (processingEl) {
        if (isVoid) {
            processingEl.textContent = 'Processing: No cash or bank refund will be issued';
        } else {
            const days = settings.refund_processing_days !== undefined ? settings.refund_processing_days : 0;
            if (days === 0) {
                processingEl.textContent = 'Processing: Refund will be processed immediately';
            } else {
                processingEl.textContent = `Processing: Refund will be processed within ${days} day${days > 1 ? 's' : ''}`;
            }
        }
    }

    if (approvalEl) {
        const operationLabel = isVoid ? 'Void' : 'Refund';
        approvalEl.textContent = requiresConfirmation
            ? `Approval: ${operationLabel} requires approval`
            : `Approval: ${operationLabel} is auto-approved`;
        approvalEl.className = requiresConfirmation ? 'text-warning' : 'text-success';
    }
}

async function loadResponsibleCashiers(branchId, selectedUserId = '') {
    const select = document.getElementById('cancelResponsibleCashier');
    if (!select) return;

    select.innerHTML = '<option value="">Loading cashiers...</option>';
    select.disabled = true;
    if (!branchId) {
        select.innerHTML = '<option value="">Branch is required</option>';
        return;
    }

    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/cashiers?branch_id=${encodeURIComponent(branchId)}`);
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Failed to load cashiers.');

        select.innerHTML = '<option value="">Select responsible cashier</option>';
        (result.data || []).forEach(cashier => {
            const option = document.createElement('option');
            const hasOpenSession = Boolean(cashier.session_id);
            const displayName = cashier.display_name || cashier.fullname || cashier.username;
            option.value = cashier.user_id;
            option.textContent = hasOpenSession ? displayName : `${displayName} — No open session (charge recorded)`;
            if (hasOpenSession) option.dataset.sessionId = cashier.session_id;
            select.appendChild(option);
        });
        if (selectedUserId) select.value = String(selectedUserId);
        select.disabled = false;
        if (!result.data?.length) {
            select.innerHTML = '<option value="">No cashier accounts found</option>';
            select.disabled = true;
        }
    } catch (error) {
        console.error('Error loading responsible cashiers:', error);
        select.innerHTML = '<option value="">Unable to load cashiers</option>';
    }
}

function updateCancellationReasonOptions(operationType = null) {
    const select = document.getElementById('cancelReasonCategory');
    if (!select) return;

    const operation = operationType || document.getElementById('cancelOperationType')?.value || 'REFUND';
    const options = operation === 'VOID'
        ? [
            ['CUSTOMER_REQUEST', 'Customer Request / Error'],
            ['CASHIER_ERROR', "Cashier's Negligence"],
            ['PRINTER_ERROR', 'Technical Issue (System/Printer)']
        ]
        : [
            ['CUSTOMER_REQUEST', 'Customer requested'],
            ['CUSTOMER_ERROR', 'Customer error'],
            ['CASHIER_ERROR', 'Cashier error'],
            ['OTHER', 'Other']
        ];
    const currentValue = select.value;
    const hasCurrentValue = options.some(([value]) => value === currentValue);

    select.innerHTML = options
        .map(([value, label]) => `<option value="${value}">${label}</option>`)
        .join('');
    select.value = hasCurrentValue ? currentValue : 'CUSTOMER_REQUEST';
}

function syncTicketResponsibilityFromReason() {
    const reasonCategory = document.getElementById('cancelReasonCategory')?.value || 'OTHER';
    const responsibilitySelect = document.getElementById('cancelResponsibility');
    if (!responsibilitySelect) return;

    const reasonResponsibilityMap = {
        CUSTOMER_REQUEST: 'NONE',
        CUSTOMER_ERROR:   'CUSTOMER',
        CASHIER_ERROR:    'CASHIER',
        PRINTER_ERROR:    'NONE',
        SYSTEM_ERROR:     'NONE',
        OTHER:            'NONE'
    };

    responsibilitySelect.value = reasonResponsibilityMap[reasonCategory] || 'NONE';
}

function toggleTicketAdjustmentFields() {
    const operation = document.getElementById('cancelOperationType')?.value || 'REFUND';
    updateCancellationReasonOptions(operation);
    const reasonCategorySelect = document.getElementById('cancelReasonCategory');
    const responsibilitySelect = document.getElementById('cancelResponsibility');
    const refundRow = document.getElementById('cancelRefundAmountRow');
    const refundInput = document.getElementById('cancelRefundAmount');
    const refundBreakdown = document.getElementById('cancelRefundBreakdown');
    const operationHint = document.getElementById('cancelOperationHint');
    const reasonCategoryRow = document.getElementById('cancelReasonCategoryRow');
    const responsibilitySelectRow = document.getElementById('cancelResponsibilityRow');
    const responsibilityRow = document.getElementById('cancelResponsibilityAmountRow');
    const responsibilityInput = document.getElementById('cancelResponsibilityAmount');
    const responsibilityHint = document.getElementById('cancelResponsibilityHint');
    const responsibilityAmountHint = document.getElementById('cancelResponsibilityAmountHint');
    const cashierRow = document.getElementById('cancelResponsibleCashierRow');
    const voidFeeSection = document.getElementById('cancelVoidFeeSection');
    const voidFeeToggle = document.getElementById('cancelVoidFeeEnabled');
    const voidFeeRow = document.getElementById('cancelVoidFeeRow');
    const voidFeeInput = document.getElementById('cancelVoidFee');
    const voidServiceFeeSection = document.getElementById('cancelVoidServiceFeeSection');
    const voidServiceFeeToggle = document.getElementById('cancelVoidServiceFeeEnabled');
    const voidServiceFeeRow = document.getElementById('cancelVoidServiceFeeRow');
    const voidServiceFeeInput = document.getElementById('cancelVoidServiceFee');
    const cancellationSettings = window.CANCELLATION_SETTINGS || {};

    const isVoid = operation === 'VOID';
    if (!isVoid) {
        if (reasonCategorySelect) {
            reasonCategorySelect.value = 'OTHER';
            reasonCategorySelect.disabled = true;
        }
        if (responsibilitySelect) {
            responsibilitySelect.value = 'NONE';
            responsibilitySelect.disabled = true;
        }
        if (responsibilityInput) responsibilityInput.value = '0.00';
    }
    const reasonCategory = isVoid ? (reasonCategorySelect?.value || 'OTHER') : 'OTHER';
    const responsibility = isVoid ? (responsibilitySelect?.value || 'NONE') : 'NONE';
    const isTechnicalIssueVoid = isVoid && isTechnicalIssueReason(reasonCategory);
    displayCancellationPolicy(operation);
    const voidFeeAvailable = cancellationSettings.void_fee_enabled !== false;
    const voidServiceFeeAvailable = cancellationSettings.void_service_fee_enabled !== false;
    if (refundRow) refundRow.style.display = isVoid ? 'none' : '';
    if (refundBreakdown) refundBreakdown.style.display = isVoid ? 'none' : refundBreakdown.style.display;
    if (refundInput && isVoid) refundInput.value = '0';
    if (refundInput && !isVoid) refundInput.dispatchEvent(new Event('input'));
    if (reasonCategoryRow) reasonCategoryRow.style.display = isVoid ? '' : 'none';
    if (responsibilitySelectRow) responsibilitySelectRow.style.display = isVoid ? '' : 'none';
    if (voidFeeSection) voidFeeSection.style.display = isVoid && voidFeeAvailable ? '' : 'none';
    if (voidFeeToggle) {
        const voidFeeBlocked = !isVoid || !voidFeeAvailable;
        voidFeeToggle.disabled = voidFeeBlocked;
        if (voidFeeBlocked) {
            voidFeeToggle.checked = false;
        } else if (isTechnicalIssueVoid) {
            voidFeeToggle.checked = true;
        }
    }
    const voidFeeEnabled = isVoid && voidFeeAvailable && Boolean(voidFeeToggle?.checked);
    if (voidFeeRow) voidFeeRow.style.display = voidFeeEnabled ? '' : 'none';
    if (voidFeeInput) {
        if (!voidFeeEnabled) voidFeeInput.value = '0.00';
        voidFeeInput.required = false;
    }
    if (voidServiceFeeSection) voidServiceFeeSection.style.display = isVoid && voidServiceFeeAvailable ? '' : 'none';
    if (voidServiceFeeToggle) {
        const serviceFeeBlocked = !isVoid || !voidServiceFeeAvailable || isTechnicalIssueVoid;
        voidServiceFeeToggle.disabled = serviceFeeBlocked;
        if (serviceFeeBlocked) voidServiceFeeToggle.checked = false;
    }
    const voidServiceFeeEnabled = isVoid && voidServiceFeeAvailable && !isTechnicalIssueVoid && Boolean(voidServiceFeeToggle?.checked);
    if (voidServiceFeeRow) voidServiceFeeRow.style.display = voidServiceFeeEnabled ? '' : 'none';
    if (voidServiceFeeInput && !voidServiceFeeEnabled) voidServiceFeeInput.value = '0.00';
    const voidResponsibilityAmount = (voidFeeEnabled ? (parseFloat(voidFeeInput?.value) || 0) : 0)
        + (voidServiceFeeEnabled ? (parseFloat(voidServiceFeeInput?.value) || 0) : 0);
    if (operationHint) {
        operationHint.textContent = isTechnicalIssueVoid
            ? 'No cash or bank refund will be issued. The original ticket amount and any entered fees will be recorded separately as Lost Sales.'
            : isVoid
                ? 'No cash or bank refund will be issued. Original CHARGE debt will be reversed; configured Void and Service Fees are recorded as income.'
                : 'Refund the eligible amount through the original payment sources.';
    }

    const hasResponsibility = isVoid && responsibility !== 'NONE';
    if (responsibilityRow) responsibilityRow.style.display = hasResponsibility ? '' : 'none';
    if (cashierRow) cashierRow.style.display = isVoid && responsibility === 'CASHIER' ? '' : 'none';

    // Responsibility select is only user-editable when the reason implies a chargeable party.
    const reasonLockedResponsibility = ['PRINTER_ERROR', 'SYSTEM_ERROR', 'OTHER'];
    const customerRequestLocked = reasonCategory === 'CUSTOMER_REQUEST' && !isVoid;
    if (reasonCategorySelect) reasonCategorySelect.disabled = !isVoid;
    if (responsibilitySelect) {
        responsibilitySelect.disabled = !isVoid || customerRequestLocked || reasonLockedResponsibility.includes(reasonCategory);
    }

    if (responsibilityHint) {
        responsibilityHint.textContent = responsibility === 'CUSTOMER'
            ? (isVoid ? 'Customer responsibility is recorded for audit only on a no-refund Void.' : 'Customer responsibility is deducted from the refund.')
            : responsibility === 'CASHIER'
                ? 'The amount is assigned to the selected cashier and deducted from an open session when available. Approval follows the configured Return/VOID confirmation settings.'
                : 'No responsibility charge will be applied.';
    }
    if (responsibilityAmountHint) {
        responsibilityAmountHint.textContent = responsibility === 'CUSTOMER' && !isVoid
            ? 'This amount will be deducted from the gross refund.'
            : isVoid && hasResponsibility
                ? voidResponsibilityAmount > 0
                    ? `Auto-calculated as Void Fee + Service Fee (₱${voidResponsibilityAmount.toFixed(2)}). This is read-only.`
                    : 'Auto-calculated as Void Fee + Service Fee. Add a fee to create a responsibility amount.'
                : responsibility === 'CASHIER'
                    ? 'Defaults to the eligible refund amount; the approving manager may adjust it.'
                    : 'Optional amount for this adjustment.';
    }

    if (isVoid && hasResponsibility && responsibilityInput) {
        responsibilityInput.value = voidResponsibilityAmount.toFixed(2);
        responsibilityInput.dataset.calculatedFromVoidFees = 'true';
        responsibilityInput.readOnly = true;
    } else if (responsibilityInput) {
        responsibilityInput.readOnly = false;
        delete responsibilityInput.dataset.calculatedFromVoidFees;
        responsibilityInput.value = '0.00';
    }
    if (reasonCategory === 'CASHIER_ERROR' && responsibility === 'NONE') {
        if (responsibilityHint) responsibilityHint.textContent = 'Select Cashier responsibility and a target cashier.';
    }
}

function closePosAdjustmentDropdownFromButton(button) {
    const menu = button?.closest?.('.dropdown-menu');
    if (!menu) return;

    const toggle = menu._posAdjustmentToggle
        || menu.parentElement?.querySelector('.pos-adjust-dropdown-toggle');
    if (!toggle) return;

    bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
}

function openCancelTicketModalFromButton(button) {
    closePosAdjustmentDropdownFromButton(button);

    const txnCode = button.dataset.txnCode;
    const txnType = button.dataset.txnType || 'TICKET';
    const operationType = button.dataset.operationType || 'REFUND';
    const baseAmount = parseFloat(button.dataset.baseAmount);
    const serviceFee = parseFloat(button.dataset.serviceFee);
    const txnData = JSON.parse(decodeURIComponent(button.dataset.txnData));
    openCancelTicketModal(txnCode, txnType, baseAmount, serviceFee, txnData, operationType);
}

async function openCancelTicketModal(txnCode = '', txnType = 'TICKET', baseAmount = 0, serviceFee = 0, txnData = null, operationType = 'REFUND') {
    console.log('openCancelTicketModal called with:', { txnCode, baseAmount, serviceFee, txnData });
    console.log('cancelTicketModal:', cancelTicketModal);

    // Keep txn details available for the confirmation/execution flow
    window.currentCancelTxnData = txnData;

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
    const inputs = document.querySelectorAll('#cancelTicketModal input, #cancelTicketModal textarea, #cancelTicketModal select');

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

    const isService = txnType === 'SERVICE';
    const defaultVoidServiceFee = Number.isFinite(serviceFee) && serviceFee > 0
        ? serviceFee
        : (parseFloat(txnData?.service_fee) || 0);

    // Set values if provided
    const txnTypeInput = document.getElementById('cancelTxnType');
    if (txnTypeInput) txnTypeInput.value = txnType;
    document.getElementById('cancelTicketCode').value = txnCode;
    const displayTxnCodeEl = document.getElementById('cancelDisplayTxnCode');
    if (displayTxnCodeEl) displayTxnCodeEl.textContent = txnCode || '-';
    document.getElementById('cancelRefundAmount').value = baseAmount > 0 ? baseAmount : '';
    document.getElementById('cancelOperationType').value = operationType === 'VOID' ? 'VOID' : 'REFUND';
    document.getElementById('cancelReasonCategory').value = 'CUSTOMER_REQUEST';
    syncTicketResponsibilityFromReason();
    document.getElementById('cancelResponsibilityAmount').value = '0.00';
    const voidFeeEnabledInput = document.getElementById('cancelVoidFeeEnabled');
    const voidFeeInput = document.getElementById('cancelVoidFee');
    const voidServiceFeeEnabledInput = document.getElementById('cancelVoidServiceFeeEnabled');
    const voidServiceFeeInput = document.getElementById('cancelVoidServiceFee');
    if (voidFeeEnabledInput) voidFeeEnabledInput.checked = false;
    if (voidFeeInput) voidFeeInput.value = '0.00';
    if (voidServiceFeeEnabledInput) voidServiceFeeEnabledInput.checked = false;
    if (voidServiceFeeInput) voidServiceFeeInput.value = '0.00';
    document.getElementById('cancelResponsibleCashier').value = '';
    document.getElementById('cancelReason').value = '';
    toggleTicketAdjustmentFields();
    await loadResponsibleCashiers(txnData?.branch_id || window.POS_BRANCH_ID);

    // Adjust modal header and helper text for the transaction type
    const modalTitleEl      = document.getElementById('cancelTicketModalLabel');
    const modalSubtitleEl   = document.getElementById('cancelTicketModalSubtitle');
    const refundHintEl      = document.getElementById('cancelRefundHint');
    const serviceFeeContainer = document.getElementById('cancelServiceFeeContainer');
    const detailsTitleEl    = document.getElementById('cancelDetailsTitle');
    const detailsIconEl     = document.getElementById('cancelDetailsIcon');
    const travelDateLabelEl = document.getElementById('cancelTravelDateLabel');
    const routeLabelEl      = document.getElementById('cancelRouteLabel');
    const baseAmountLabelEl = document.getElementById('cancelBaseAmountLabel');

    const operationRow = document.getElementById('cancelOperationTypeRow');
    if (operationRow) operationRow.style.display = 'none';

    if (modalTitleEl) {
        modalTitleEl.innerHTML = isService
            ? '<span class="fas fa-times-circle me-2"></span>Cancel Service'
            : operationType === 'VOID'
                ? '<span class="fas fa-ban me-2"></span>Void Ticket'
                : '<span class="fas fa-times-circle me-2"></span>Cancel Ticket';
    }
    if (modalSubtitleEl) {
        modalSubtitleEl.textContent = isService
            ? 'Process service cancellation with refund'
            : operationType === 'VOID'
                ? 'Void ticket without a cash or bank refund'
                : 'Process ticket cancellation with refund';
    }
    if (detailsTitleEl)   detailsTitleEl.textContent  = isService ? 'Service Details' : 'Ticket Details';
    if (detailsIconEl)    detailsIconEl.className     = isService ? 'fas fa-concierge-bell me-2' : 'fas fa-info-circle me-2';
    if (travelDateLabelEl) travelDateLabelEl.textContent = isService ? 'Service Name' : 'Ticket Number';
    if (routeLabelEl)     routeLabelEl.textContent    = isService ? 'Description' : 'Route';
    if (baseAmountLabelEl) baseAmountLabelEl.textContent = isService ? 'Amount' : 'Base fare';
    if (serviceFeeContainer) serviceFeeContainer.style.display = isService ? 'none' : '';
    if (refundHintEl) {
        refundHintEl.innerHTML = isService
            ? 'Refund will be given from cashier cash. Service amount is refundable.'
            : 'Refund will be given from cashier cash. Service Fee is non-refundable: <span id="cancelServiceFeeDisplay" style="display: none;">₱0.00</span>.';
    }

    // Display service fee if provided
    const serviceFeeDisplay = document.getElementById('cancelServiceFeeDisplay');
    if (serviceFeeDisplay && serviceFee > 0) {
        serviceFeeDisplay.textContent = `₱${serviceFee.toFixed(2)}`;
        serviceFeeDisplay.style.display = 'inline';
    } else if (serviceFeeDisplay) {
        serviceFeeDisplay.style.display = 'none';
    } 
    // Display ticket/service details if provided
    const detailsDiv = document.getElementById('cancelTicketDetails');
    const detailData = txnData && typeof txnData === 'object' ? txnData : {
        base_amount: baseAmount,
        service_fee: serviceFee,
        total_amount: (parseFloat(baseAmount) || 0) + (parseFloat(serviceFee) || 0),
        status: '-'
    };
    if (detailsDiv) {
        document.getElementById('cancelPassengerName').textContent = detailData.passenger_name || '-';

        document.getElementById('cancelTravelDate').textContent = isService
            ? (detailData.service_name || detailData.description || detailData.service_type_name || '-')
            : (detailData.ticket_number || '-');

        document.getElementById('cancelRoute').textContent = isService
            ? (detailData.service_name || detailData.description || '-')
            : ((detailData.origin && detailData.destination) ? `${detailData.origin} → ${detailData.destination}` : '-');
        document.getElementById('cancelProvider').textContent = detailData.provider_name || '-';
        document.getElementById('cancelBaseAmount').textContent = `₱${(parseFloat(detailData.base_amount) || 0).toFixed(2)}`;
        document.getElementById('cancelServiceFee').textContent = `₱${defaultVoidServiceFee.toFixed(2)}`;
        document.getElementById('cancelTotalAmount').textContent = `₱${(parseFloat(detailData.total_amount) || 0).toFixed(2)}`;
        document.getElementById('cancelStatus').textContent = detailData.status || '-';

        if (detailData.created_at) {
            const txnDate = new Date(detailData.created_at);
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
                        const accountLabel = isCharge && p.charged_to_passenger_name
                            ? ` <span class="text-muted small">(${escapeHtml(p.charged_to_passenger_name)})</span>`
                            : '';
                        return `<div class="d-flex justify-content-between align-items-center mb-1">
                            <span><i class="fas fa-credit-card me-1 text-muted"></i>${p.method_name}${chargeLabel}${accountLabel}</span>
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

function hideCancelTicketModalForConfirmation() {
    const modalElement = document.getElementById('cancelTicketModal');
    if (!modalElement || !modalElement.classList.contains('show')) {
        return Promise.resolve();
    }

    restoreCancelTicketModal = true;

    return new Promise(resolve => {
        modalElement.addEventListener('hidden.bs.modal', resolve, { once: true });
        if (cancelTicketModal) {
            cancelTicketModal.hide();
        } else {
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
        }
    });
}

function reopenCancelTicketModal() {
    const modalElement = document.getElementById('cancelTicketModal');
    if (!modalElement) return;

    if (!cancelTicketModal) {
        cancelTicketModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    }
    cancelTicketModal.show();
}

async function confirmCancelTicket() {
    const txnCode = document.getElementById('cancelTicketCode').value.trim();
    const txnType = (document.getElementById('cancelTxnType')?.value || 'TICKET').toUpperCase();
    const operationType = document.getElementById('cancelOperationType')?.value || 'REFUND';
    const isVoid = operationType === 'VOID';
    const reasonCategory = isVoid ? (document.getElementById('cancelReasonCategory')?.value || 'OTHER') : 'OTHER';
    const isTechnicalIssueVoid = isVoid && isTechnicalIssueReason(reasonCategory);
    const responsibility = isVoid ? (document.getElementById('cancelResponsibility')?.value || 'NONE') : 'NONE';
    const grossRefundAmount = isVoid
        ? 0
        : parseFloat(document.getElementById('cancelRefundAmount').value) || 0;
    const voidFeeEnabled = isVoid && Boolean(document.getElementById('cancelVoidFeeEnabled')?.checked);
    const voidServiceFeeEnabled = isVoid
        && !isTechnicalIssueVoid
        && Boolean(document.getElementById('cancelVoidServiceFeeEnabled')?.checked);
    const rawVoidFee = parseFloat(document.getElementById('cancelVoidFee')?.value);
    const rawVoidServiceFee = parseFloat(document.getElementById('cancelVoidServiceFee')?.value);
    const voidFee = voidFeeEnabled && Number.isFinite(rawVoidFee) ? Math.max(0, rawVoidFee) : 0;
    const voidServiceFee = voidServiceFeeEnabled && Number.isFinite(rawVoidServiceFee) ? Math.max(0, rawVoidServiceFee) : 0;
    const responsibilityAmount = isVoid
        ? parseFloat(document.getElementById('cancelResponsibilityAmount')?.value) || 0
        : 0;
    const responsibleUserId = isVoid && responsibility === 'CASHIER'
        ? (document.getElementById('cancelResponsibleCashier')?.value || null)
        : null;
    const netRefundAmount = operationType === 'REFUND'
        ? Math.max(0, grossRefundAmount - (responsibility === 'CUSTOMER' ? responsibilityAmount : 0))
        : 0;
    const reason = document.getElementById('cancelReason').value.trim();

    if (!txnCode) {
        showToast('danger', 'Error', 'Please enter a transaction code.');
        return;
    }
    if (operationType === 'REFUND' && grossRefundAmount <= 0) {
        showToast('danger', 'Error', 'Please enter a valid refund amount.');
        return;
    }
    if (responsibility === 'CUSTOMER' && operationType === 'REFUND' && responsibilityAmount >= grossRefundAmount) {
        showToast('danger', 'Error', 'Customer responsibility must be less than the refund amount.');
        return;
    }
    if (responsibility === 'CASHIER' && !responsibleUserId) {
        showToast('danger', 'Error', 'Select the responsible cashier.');
        return;
    }
    if (operationType === 'VOID' && responsibility === 'CASHIER' && responsibilityAmount <= 0) {
        showToast('danger', 'Error', 'Enter a Responsibility Amount greater than zero for the selected cashier.');
        return;
    }
    if (!reason) {
        showToast('danger', 'Error', 'Please enter a reason for cancellation.');
        return;
    }

    await hideCancelTicketModalForConfirmation();

    let paymentBreakdown = [];
    try {
        const response = await fetch(`${window.BASE_URL}/api/pos/transaction-payments.php?transaction_code=${encodeURIComponent(txnCode)}`);
        const result = await response.json();
        if (result.success && result.data && result.data.payments) {
            const payments = result.data.payments;
            let remainingAmount = operationType === 'VOID' ? Number.POSITIVE_INFINITY : netRefundAmount;

            const chargePayments = payments.filter(p => parseInt(p.tracks_credit) === 1);
            for (const p of chargePayments) {
                const originalAmount = parseFloat(p.amount) || 0;
                const amount = operationType === 'VOID'
                    ? originalAmount
                    : Math.min(originalAmount, remainingAmount);
                if (amount > 0) {
                    paymentBreakdown.push({
                        method_name: p.method_name,
                        tracks_credit: p.tracks_credit,
                        charged_to_passenger_name: p.charged_to_passenger_name,
                        amount,
                        type: 'charge'
                    });
                    if (operationType !== 'VOID') remainingAmount -= amount;
                }
            }

            if (operationType !== 'VOID') {
                const otherPayments = payments.filter(p => parseInt(p.tracks_credit) !== 1);
                for (const p of otherPayments) {
                    if (remainingAmount <= 0) break;
                    const originalAmount = parseFloat(p.amount) || 0;
                    const amount = Math.min(originalAmount, remainingAmount);
                    if (amount > 0) {
                        paymentBreakdown.push({
                            method_name: p.method_name,
                            tracks_credit: p.tracks_credit,
                            amount,
                            type: 'cash'
                        });
                        remainingAmount -= amount;
                    }
                }
            }
        }
    } catch (e) {
        console.error('Error fetching payment breakdown for confirmation:', e);
    }

    const txnData = window.currentCancelTxnData || {};
    const isConsumedVariant = Boolean(txnData.variant_id || txnData.variantId || txnData.is_consumed_variant);
    showRefundConfirmModal(
        txnCode,
        txnType,
        grossRefundAmount,
        reason,
        paymentBreakdown,
        isConsumedVariant,
        {
            operationType,
            reasonCategory,
            responsibility,
            responsibilityAmount,
            responsibleUserId,
            netRefundAmount,
            voidFee,
            voidServiceFee
        }
    );
}

function showRefundConfirmModal(txnCode, txnType, refundAmount, reason, paymentBreakdown, isConsumedVariant = false, adjustmentOptions = {}) {
    const operationType = adjustmentOptions.operationType || 'REFUND';
    const responsibility = adjustmentOptions.responsibility || 'NONE';
    const responsibilityAmount = parseFloat(adjustmentOptions.responsibilityAmount || 0) || 0;
    const responsibleUserId = adjustmentOptions.responsibleUserId || null;
    const isVoid = operationType === 'VOID';
    const isTechnicalIssueVoid = isVoid && isTechnicalIssueReason(adjustmentOptions.reasonCategory);
    const enteredVoidFee = isVoid ? Math.max(0, parseFloat(adjustmentOptions.voidFee || 0) || 0) : 0;
    const enteredVoidServiceFee = isVoid ? Math.max(0, parseFloat(adjustmentOptions.voidServiceFee || 0) || 0) : 0;
    const voidFee = isTechnicalIssueVoid ? 0 : enteredVoidFee;
    const voidServiceFee = isTechnicalIssueVoid ? 0 : enteredVoidServiceFee;
    const lostSalesVoidFee = isTechnicalIssueVoid ? enteredVoidFee : 0;

    // Calculate totals
    const totalCash = paymentBreakdown
        .filter(p => p.type === 'cash')
        .reduce((sum, p) => sum + p.amount, 0);
    const totalChargeReversal = paymentBreakdown
        .filter(p => p.type === 'charge')
        .reduce((sum, p) => sum + p.amount, 0);

    const configuredConfirmation = isVoid
        ? (window.CANCELLATION_SETTINGS?.void_requires_confirmation
            ?? window.CANCELLATION_SETTINGS?.requires_confirmation
            ?? true)
        : (window.CANCELLATION_SETTINGS?.return_requires_confirmation
            ?? window.CANCELLATION_SETTINGS?.requires_confirmation
            ?? true);
    const requiresConfirmation = Boolean(configuredConfirmation);

    // Build breakdown HTML
    let breakdownHtml = '';
    if (paymentBreakdown.length > 0 || (isVoid && (responsibilityAmount > 0 || voidFee > 0 || voidServiceFee > 0 || lostSalesVoidFee > 0))) {
        const breakdownTitle = isVoid
            ? (requiresConfirmation ? 'VOID Responsibility Breakdown (Pending Approval)' : 'VOID Responsibility Breakdown')
            : (requiresConfirmation ? 'Estimated Refund Breakdown (Pending Approval)' : 'Refund Breakdown');
        const cashLabel = requiresConfirmation ? 'Est. Cash to Give (if approved)' : 'Total Cash to Give';
        const chargeNote = requiresConfirmation
            ? 'Customer\'s outstanding balance will be reduced by this amount once approved.'
            : 'Customer\'s outstanding balance will be reduced by this amount.';

        breakdownHtml = `<div class="card border-0 bg-soft-info mb-3">
            <div class="card-body p-3">
                <h6 class="card-title mb-2"><span class="fas fa-list me-2"></span>${breakdownTitle}</h6>`;

        if (totalChargeReversal > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center mb-1">
                <span><i class="fas fa-file-invoice-dollar text-warning me-1"></i>Charge Debt Reversal (System)</span>
                <span class="fw-semibold text-warning">₱${fmt(totalChargeReversal)}</span>
            </div>
            <div class="small text-muted mb-2">${chargeNote}</div>`;
        }

        if (totalCash > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2">
                <span><i class="fas fa-hand-holding-usd text-success me-1"></i><strong>${cashLabel}</strong></span>
                <span class="fw-bold text-success">₱${fmt(totalCash)}</span>
            </div>`;
        }

        if (responsibility !== 'NONE' && responsibilityAmount > 0) {
            const responsibilityLabel = responsibility === 'CASHIER'
                ? 'Cashier responsibility deduction'
                : isVoid ? 'Customer responsibility (audit only)' : 'Customer responsibility deducted';
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                <span><i class="fas fa-user-shield text-danger me-1"></i>${responsibilityLabel}</span>
                <span class="fw-bold text-danger">₱${fmt(responsibilityAmount)}</span>
            </div>`;
        }

        if (isVoid && voidFee > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                <span><i class="fas fa-receipt text-warning me-1"></i>Void fee income</span>
                <span class="fw-bold text-warning">₱${fmt(voidFee)}</span>
            </div>`;
        }
        if (isVoid && voidServiceFee > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                <span><i class="fas fa-concierge-bell text-info me-1"></i>Service fee income</span>
                <span class="fw-bold text-info">₱${fmt(voidServiceFee)}</span>
            </div>`;
        }
        if (isTechnicalIssueVoid && lostSalesVoidFee > 0) {
            breakdownHtml += `<div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                <span><i class="fas fa-receipt text-danger me-1"></i>Void fee lost sales</span>
                <span class="fw-bold text-danger">₱${fmt(lostSalesVoidFee)}</span>
            </div>`;
        }
        breakdownHtml += `</div></div>`;
    }
    
    // Build confirmation text
    let confirmText = '';
    const cashierResponsibilityNotice = isVoid && responsibility === 'CASHIER' && responsibilityAmount > 0
        ? ` A Responsibility Amount of ₱${fmt(responsibilityAmount)} will be recorded against the selected cashier.`
        : '';
    const voidFeeNotice = isVoid && voidFee > 0 ? ` A void fee of ₱${fmt(voidFee)} will be recorded as income.` : '';
    const voidServiceFeeNotice = isVoid && voidServiceFee > 0 ? ` A service fee of ₱${fmt(voidServiceFee)} will be recorded as income.` : '';
    const lostSalesVoidFeeNotice = isTechnicalIssueVoid && lostSalesVoidFee > 0
        ? ` A void fee of ₱${fmt(lostSalesVoidFee)} will be recorded as Lost Sales.`
        : '';
    if (isVoid) {
        confirmText = requiresConfirmation
            ? `I confirm that this Void request should be submitted for manager approval. No cash or bank refund will be issued, and ₱${fmt(totalChargeReversal)} will be reversed from the original charge/debt balance.${cashierResponsibilityNotice}${voidFeeNotice}${voidServiceFeeNotice}${lostSalesVoidFeeNotice}`
            : `I confirm this Void operation. No cash or bank refund will be issued, and ₱${fmt(totalChargeReversal)} will be reversed from the original charge/debt balance.${cashierResponsibilityNotice}${voidFeeNotice}${voidServiceFeeNotice}${lostSalesVoidFeeNotice}`;
    } else if (requiresConfirmation) {
        if (totalCash > 0 && totalChargeReversal > 0) {
            confirmText = `I confirm that this cancellation request should be submitted for manager approval. If approved, an estimated ₱${fmt(totalCash)} cash will be given and ₱${fmt(totalChargeReversal)} will be reversed from the passenger's charge/debt balance.`;
        } else if (totalCash > 0) {
            confirmText = `I confirm that this cancellation request should be submitted for manager approval. If approved, an estimated ₱${fmt(totalCash)} cash will be given to the passenger.`;
        } else if (totalChargeReversal > 0) {
            confirmText = `I confirm that this cancellation request should be submitted for manager approval. If approved, ₱${fmt(totalChargeReversal)} will be reversed from the passenger's charge/debt balance.`;
        } else {
            confirmText = `I confirm that this cancellation request should be submitted for manager approval.`;
        }
    } else if (totalCash > 0 && totalChargeReversal > 0) {
        confirmText = `I confirm that I will give ₱${fmt(totalCash)} cash to the passenger, and the system will reverse ₱${fmt(totalChargeReversal)} from their outstanding charge/debt balance.`;
    } else if (totalCash > 0) {
        confirmText = `I confirm that I will give ₱${fmt(totalCash)} cash to the passenger from the cash drawer.`;
    } else if (totalChargeReversal > 0) {
        confirmText = `I confirm that the system will reverse ₱${fmt(totalChargeReversal)} from the passenger's outstanding charge/debt balance (no cash refund).`;
    } else {
        confirmText = `I confirm that I will process this cancellation with refund amount of ₱${fmt(refundAmount)}.`;
    }
    
    const modalTitle = requiresConfirmation
        ? '<span class="fas fa-user-clock text-warning me-2"></span>Submit for Manager Approval'
        : isVoid
            ? '<span class="fas fa-ban text-warning me-2"></span>Confirm Void'
            : '<span class="fas fa-money-bill-wave text-warning me-2"></span>Confirm Cancellation & Refund';
    let modalAlert = requiresConfirmation
        ? `<div class="alert alert-info"><span class="fas fa-info-circle me-2"></span><strong>Manager approval required:</strong> No financial effect will be finalized until approval.</div>`
        : isVoid
            ? `<div class="alert alert-warning"><span class="fas fa-ban me-2"></span><strong>Important:</strong> This will void the ticket without a cash or bank refund.</div>`
            : `<div class="alert alert-warning"><span class="fas fa-exclamation-triangle me-2"></span><strong>Important:</strong> This action will process the refund and update the customer's charge balance.</div>`;
    if (isConsumedVariant) {
        modalAlert += `<div class="alert alert-warning mt-2"><span class="fas fa-ticket-alt me-2"></span>This variant ticket is <strong>consumed</strong>. Physical availability and provider wallet balance will not be restored.</div>`;
    }
    if (responsibility === 'CASHIER' && requiresConfirmation) {
        modalAlert += `<div class="alert alert-danger mt-2"><span class="fas fa-user-shield me-2"></span>The selected cashier responsibility will be finalized after manager approval.</div>`;
    }
    const buttonText = requiresConfirmation
        ? `Submit for Approval`
        : isVoid
            ? 'Confirm Void'
            : `Confirm & Refund ${txnType === 'SERVICE' ? 'Service' : 'Ticket'}`;
    const operationLabel = isVoid ? 'Operation' : 'Gross Refund Amount';
    const operationValue = isVoid ? 'VOID — No refund' : `₱${fmt(refundAmount)}`;
    const responsibilityHtml = responsibility !== 'NONE'
        ? `<tr><td class="fw-bold">Responsibility:</td><td class="text-end">${responsibility}${responsibilityAmount > 0 ? ` — ₱${fmt(responsibilityAmount)}` : ''}</td></tr>`
        : '';

    const modalHtml = `
        <div class="modal fade" id="refundConfirmModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            ${modalTitle}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${modalAlert}
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td class="fw-bold">Transaction Code:</td>
                                        <td class="text-end">${txnCode}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">${operationLabel}:</td>
                                        <td class="text-end text-danger fw-bold">${operationValue}</td>
                                    </tr>
                                    ${isVoid && voidFee > 0 ? `<tr><td class="fw-bold">Void Fee Income:</td><td class="text-end text-warning fw-bold">₱${fmt(voidFee)}</td></tr>` : ''}
                                    ${isVoid && voidServiceFee > 0 ? `<tr><td class="fw-bold">Service Fee Income:</td><td class="text-end text-info fw-bold">₱${fmt(voidServiceFee)}</td></tr>` : ''}
                                    ${isTechnicalIssueVoid && lostSalesVoidFee > 0 ? `<tr><td class="fw-bold">Technical Void Fee Lost Sales:</td><td class="text-end text-danger fw-bold">₱${fmt(lostSalesVoidFee)}</td></tr>` : ''}
                                    ${!isVoid && responsibility === 'CUSTOMER' ? `<tr><td class="fw-bold">Net Refund Amount:</td><td class="text-end text-success fw-bold">₱${fmt(adjustmentOptions.netRefundAmount || 0)}</td></tr>` : ''}
                                    ${responsibilityHtml}
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
                            data-txn-type="${txnType.replace(/"/g, '&quot;')}"
                            data-operation-type="${operationType}"
                            data-reason-category="${adjustmentOptions.reasonCategory || 'OTHER'}"
                            data-responsibility="${responsibility}"
                            data-responsibility-amount="${responsibilityAmount}"
                            data-responsible-user-id="${responsibleUserId || ''}"
                            data-void-fee="${enteredVoidFee}"
                            data-void-service-fee="${enteredVoidServiceFee}"
                            data-refund-amount="${refundAmount}"
                            data-reason="${reason.replace(/"/g, '&quot;')}">
                            <span class="fas fa-check me-1"></span>${buttonText}
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
        const code = this.dataset.txnCode;
        const amount = parseFloat(this.dataset.refundAmount) || 0;
        const rsn = this.dataset.reason;
        executeTicketCancellation(code, amount, rsn, {
            operationType: this.dataset.operationType || 'REFUND',
            reasonCategory: this.dataset.reasonCategory || 'OTHER',
            responsibility: this.dataset.responsibility || 'NONE',
            responsibilityAmount: parseFloat(this.dataset.responsibilityAmount) || 0,
            responsibleUserId: this.dataset.responsibleUserId || null,
            voidFee: parseFloat(this.dataset.voidFee) || 0,
            voidServiceFee: parseFloat(this.dataset.voidServiceFee) || 0
        });
    });
    
    modal.show();
    
    // Cleanup on hide and restore the underlying cancel modal when dismissed
    modalElement.addEventListener('hidden.bs.modal', function() {
        const shouldRestoreCancelModal = restoreCancelTicketModal;
        restoreCancelTicketModal = false;
        modal.dispose();
        modalElement.remove();

        if (shouldRestoreCancelModal) {
            requestAnimationFrame(() => reopenCancelTicketModal());
        }
    });
}

function executeTicketCancellation(txnCode, refundAmount, reason, adjustmentOptions = {}) {
    const operationType = adjustmentOptions.operationType || 'REFUND';
    const isVoid = operationType === 'VOID';

    // Hide the confirmation modal first
    restoreCancelTicketModal = false;
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
            operation_type: operationType,
            reason_category: isVoid ? (adjustmentOptions.reasonCategory || 'OTHER') : 'OTHER',
            responsibility: isVoid ? (adjustmentOptions.responsibility || 'NONE') : 'NONE',
            responsibility_amount: isVoid
                ? parseFloat(adjustmentOptions.responsibilityAmount || 0) || 0
                : 0,
            responsible_user_id: isVoid ? (adjustmentOptions.responsibleUserId || null) : null,
            void_fee: isVoid
                ? Math.max(0, parseFloat(adjustmentOptions.voidFee || 0) || 0)
                : 0,
            void_service_fee: isVoid
                ? Math.max(0, parseFloat(adjustmentOptions.voidServiceFee || 0) || 0)
                : 0,
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
            // Only refresh wallets if the cancellation was processed immediately.
            // Pending requests do not credit the wallet until a manager approves.
            if (data.requires_confirmation !== true) {
                // Refresh Main Provider wallet balances, Variant wallet balances,
                // and the resolved wallet select in the Ticket Details area.
                loadTicketVariants().then(() => refreshPosWalletBalances());
            }
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
