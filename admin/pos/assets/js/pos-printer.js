/**
 * POS Printer Module
 * 
 * Handles receipt printing via QZ Tray for the TMS POS system.
 * This module integrates with the POS and provides printing functionality.
 * 
 * @version 1.0.0
 * @requires qz-tray.min.js
 */

(function(window) {
    'use strict';

    // POS Printer Module
    const PosPrinter = {
        // Configuration
        config: {
            paperWidth: '76mm',      // 58mm or 80mm
            printerType: 'THERMAL',  // THERMAL, DOT_MATRIX, INKJET
            autoCut: true,
            openCashDrawer: false,
            copies: 1,
            encoding: 'UTF-8',
            logoEnabled: false,
            showCashier: true,
            showPaymentMethod: true,
            showBranch: true,
            showTin: true,
            showServiceFee: true,
            showBaseAmount: true,
            showDiscount: true,
            showItemTotal: true,
            showSubtotal: true,
            showTendered: true,
            showServiceFeeTotal: true,
            totalSource: 'grand_total',
            showVat: true,
            qrEnabled: false,
            qrFormat: 'TRANSACTION_ID',
            footerText: 'Thank you for your business!',
            customFooter: ''
        },

        // State
        state: {
            connected: false,
            printerName: null,
            terminalName: null,
            autoConnect: false,
            lastError: null
        },

        // Company info (populated from database via PHP)
        companyInfo: {
            name: '',
            address: '',
            contact: '',
            email: '',
            tin: '',
            logo: '',
            // BIR accreditation details
            birPermitNumber: '',
            birAccreditationNumber: '',
            birValidityFrom: '',
            birValidityTo: '',
            birMin: '',
            birSerialNumber: ''
        },

        // ESC/POS Commands
        commands: {
            INIT: '\x1B\x40',           // Initialize printer
            LF: '\x0A',                 // Line feed
            CR: '\x0D',                 // Carriage return
            HT: '\x09',                 // Horizontal tab
            CUT: '\x1D\x56\x41\x03',    // Cut paper (partial cut)
            CUT_FULL: '\x1D\x56\x41\x00', // Cut paper (full cut)
            DRAWER: '\x1B\x70\x00\x32\x32', // Open cash drawer
            
            // Alignment
            ALIGN_LEFT: '\x1B\x61\x00',
            ALIGN_CENTER: '\x1B\x61\x01',
            ALIGN_RIGHT: '\x1B\x61\x02',
            
            // Font styles
            BOLD_ON: '\x1B\x45\x01',
            BOLD_OFF: '\x1B\x45\x00',
            UNDERLINE_ON: '\x1B\x2D\x01',
            UNDERLINE_OFF: '\x1B\x2D\x00',
            DOUBLE_HEIGHT: '\x1D\x21\x01',
            DOUBLE_WIDTH: '\x1D\x21\x10',
            NORMAL_SIZE: '\x1D\x21\x00',
            
            // Barcode
            BARCODE_HEIGHT: '\x1D\x68\x60',
            BARCODE_WIDTH: '\x1D\x77\x02',
            BARCODE_HRI: '\x1D\x48\x02',
            
            // QR Code
            QR_MODEL: '\x1D\x28\x6B\x04\x00\x31\x41\x32\x00',
            QR_SIZE: '\x1D\x28\x6B\x03\x00\x31\x43\x05',
            QR_ERROR: '\x1D\x28\x6B\x03\x00\x31\x45\x31'
        },

        /**
         * Initialize the printer module
         * @param {Object} options - Configuration options
         */
        init: function(options) {
            // Merge options with defaults
            if (options) {
                Object.assign(this.config, options.config || {});
                Object.assign(this.companyInfo, options.companyInfo || {});
            }

            // Load saved configuration from localStorage
            this.loadSavedConfig();

            console.log('[PosPrinter] Initialized with config:', this.config);
            return this;
        },

        /**
         * Load saved configuration from localStorage and global settings
         */
        loadSavedConfig: function() {
            this.state.printerName = localStorage.getItem('tms_pos_printer');
            this.state.terminalName = localStorage.getItem('tms_pos_terminal');
            this.state.autoConnect = localStorage.getItem('tms_qz_autoconnect') === 'true';

            // Use terminal override if set, otherwise use global
            const terminalOverride = localStorage.getItem('tms_pos_paper_width_override');
            const savedPaperWidth = localStorage.getItem('tms_pos_paper_width');
            this.config.paperWidth = terminalOverride || savedPaperWidth || '80mm';

            // Apply global settings
            if (window.PRINTER_SETTINGS) {
                this.config.showTin = window.PRINTER_SETTINGS.showTin;
                this.config.showServiceFee = window.PRINTER_SETTINGS.showServiceFee;
                this.config.showBaseAmount = window.PRINTER_SETTINGS.showBaseAmount;
                this.config.showDiscount = window.PRINTER_SETTINGS.showDiscount;
                this.config.showItemTotal = window.PRINTER_SETTINGS.showItemTotal;
                this.config.showSubtotal = window.PRINTER_SETTINGS.showSubtotal;
                this.config.showTendered = window.PRINTER_SETTINGS.showTendered;
                this.config.showServiceFeeTotal = window.PRINTER_SETTINGS.showServiceFeeTotal;
                this.config.totalSource = window.PRINTER_SETTINGS.totalSource || 'grand_total';
                this.config.showVat = window.PRINTER_SETTINGS.showVat;
                this.config.headerText = window.PRINTER_SETTINGS.headerText || '';
                this.config.footerText = window.PRINTER_SETTINGS.footerText || 'Thank you for your business!';
                this.config.customFooter = window.PRINTER_SETTINGS.customFooter || '';
                this.config.showCashier = window.PRINTER_SETTINGS.showCashier;
                this.config.showPaymentMethod = window.PRINTER_SETTINGS.showPaymentMethod;
                this.config.showBranch = window.PRINTER_SETTINGS.showBranch;
                this.config.logoEnabled = window.PRINTER_SETTINGS.logoEnabled;
                this.config.qrEnabled = window.PRINTER_SETTINGS.qrEnabled;
                this.config.autoCut = window.PRINTER_SETTINGS.autoCut;
                this.config.openCashDrawer = window.PRINTER_SETTINGS.openCashDrawer;
                this.config.copies = parseInt(window.PRINTER_SETTINGS.copies) || 1;
                this.config.customerCopy = !!window.PRINTER_SETTINGS.customerCopy;
                this.config.merchantCopy = window.PRINTER_SETTINGS.merchantCopy !== undefined ? !!window.PRINTER_SETTINGS.merchantCopy : true;
                this.config.addressSource = window.PRINTER_SETTINGS.addressSource || 'company';
                console.log('[PosPrinter] addressSource loaded:', this.config.addressSource, 'from PRINTER_SETTINGS:', window.PRINTER_SETTINGS.addressSource);

                // Apply terminal overrides (take precedence over global)
                const autoPrintOverride = localStorage.getItem('tms_pos_auto_print_override');
                console.log('[PosPrinter] autoPrint override raw:', autoPrintOverride);
                if (autoPrintOverride !== null) {
                    window.PRINTER_SETTINGS.autoPrint = autoPrintOverride === '1';
                    console.log('[PosPrinter] autoPrint overridden to:', window.PRINTER_SETTINGS.autoPrint);
                } else {
                    console.log('[PosPrinter] autoPrint using global:', window.PRINTER_SETTINGS.autoPrint);
                }
                const copiesOverride = localStorage.getItem('tms_pos_copies_override');
                if (copiesOverride !== null) {
                    const c = parseInt(copiesOverride);
                    if (c > 0) {
                        window.PRINTER_SETTINGS.copies = c;
                        this.config.copies = c;
                    }
                }
            }

            // Load company info
            if (window.COMPANY_INFO) {
                Object.assign(this.companyInfo, window.COMPANY_INFO);
            }

            console.log('[PosPrinter] Config loaded:', {
                paperWidth: this.config.paperWidth,
                showTin: this.config.showTin,
                showServiceFee: this.config.showServiceFee,
                showBaseAmount: this.config.showBaseAmount,
                showDiscount: this.config.showDiscount,
                showItemTotal: this.config.showItemTotal,
                showSubtotal: this.config.showSubtotal,
                showTendered: this.config.showTendered,
                showServiceFeeTotal: this.config.showServiceFeeTotal,
                totalSource: this.config.totalSource,
                showVat: this.config.showVat
            });

            const savedPrinterType = localStorage.getItem('tms_pos_printer_type');
            if (savedPrinterType) {
                this.config.printerType = savedPrinterType;
            }
        },

        /**
         * Check if printing is enabled and configured
         * @returns {Object} Status object
         */
        getStatus: function() {
            return {
                enabled: !!this.state.printerName,
                connected: this.state.connected,
                printerName: this.state.printerName,
                terminalName: this.state.terminalName,
                ready: !!this.state.printerName && this.state.connected
            };
        },

        /**
         * Configure QZ Tray certificate and SHA-512/RSA signature callbacks.
         * Must be called before qz.websocket.connect().
         */
        setupSecurity: function() {
            if (!window.QZ_CERT || !window.QZ_PRIVATE_KEY) {
                console.warn('[PosPrinter] QZ_CERT or QZ_PRIVATE_KEY not set; connecting unsigned.');
                return;
            }

            qz.security.setCertificatePromise(function(resolve, reject) {
                resolve(window.QZ_CERT);
            });

            qz.security.setSignatureAlgorithm('SHA512');

            qz.security.setSignaturePromise(function(toSign) {
                return function(resolve, reject) {
                    try {
                        const sig = new KJUR.crypto.Signature({ alg: 'SHA512withRSA' });
                        sig.init(window.QZ_PRIVATE_KEY);
                        sig.updateString(toSign);
                        resolve(hex2b64(sig.sign()));
                    } catch (e) {
                        reject(e);
                    }
                };
            });
        },

        /**
         * Connect to QZ Tray
         * @returns {Promise<boolean>}
         */
        connect: async function() {
            try {
                if (!window.qz) {
                    throw new Error('QZ Tray library not loaded');
                }

                if (qz.websocket.isActive()) {
                    this.state.connected = true;
                    return true;
                }

                this.setupSecurity();
                await qz.websocket.connect();
                this.state.connected = true;
                this.state.lastError = null;
                
                console.log('[PosPrinter] Connected to QZ Tray');
                return true;

            } catch (err) {
                this.state.connected = false;
                this.state.lastError = err.message || 'Connection failed';
                console.error('[PosPrinter] Connection failed:', err);
                throw err;
            }
        },

        /**
         * Disconnect from QZ Tray
         */
        disconnect: async function() {
            try {
                if (qz.websocket.isActive()) {
                    await qz.websocket.disconnect();
                }
                this.state.connected = false;
                console.log('[PosPrinter] Disconnected from QZ Tray');
            } catch (err) {
                console.error('[PosPrinter] Disconnect error:', err);
            }
        },

        /**
         * Auto-connect if previously connected
         */
        autoConnect: async function() {
            if (this.state.autoConnect && this.state.printerName) {
                try {
                    await this.connect();
                } catch (err) {
                    console.warn('[PosPrinter] Auto-connect failed:', err);
                }
            }
        },

        /**
         * Print a receipt
         * @param {Object} transaction - Transaction data
         * @param {Object} options - Print options
         * @returns {Promise<void>}
         */
        printReceipt: async function(transaction, options) {
            options = options || {};

            // Reload config to ensure terminal override is applied
            this.loadSavedConfig();

            // Validate
            if (!this.state.printerName) {
                throw new Error('No printer configured. Please set up printer first.');
            }

            // Connect if not connected
            if (!this.state.connected) {
                await this.connect();
            }

            // Generate receipt data
            const receiptData = this.generateReceiptData(transaction, options);

            // Create printer config
            const config = qz.configs.create(this.state.printerName);

            // Send to print
            try {
                // If logo is enabled, print it first using QZ Tray's image printing
                if (this.config.logoEnabled && this.companyInfo.logo && typeof qz !== 'undefined') {
                    try {
                        // Use QZ Tray's printImage for logo - scale down significantly
                        await qz.print(config, [{
                            type: 'image',
                            data: this.companyInfo.logo,
                            options: { units: 'mm', width: 8, scale: 0.3 }
                        }]);
                    } catch (logoErr) {
                        console.warn('[PosPrinter] Logo print failed, continuing without logo:', logoErr);
                    }
                }

                // Print copies based on settings (options.copies takes precedence)
                const copies = options.copies || this.config.copies || 1;

                for (let i = 0; i < copies; i++) {
                    const copyData = this.generateReceiptData(transaction, options);
                    await qz.print(config, copyData);
                }
                console.log('[PosPrinter] Receipt printed successfully (' + copies + ' copies)');

                // Log the print
                this.logPrint(transaction, 'SUCCESS');

            } catch (err) {
                this.logPrint(transaction, 'FAILED', err.message);
                throw err;
            }
        },

        /**
         * Generate receipt ESC/POS data
         * @param {Object} transaction - Transaction data
         * @param {Object} options - Print options
         * @returns {Array} ESC/POS commands
         */
        generateReceiptData: function(transaction, options) {
            const data = [];
            const cmd = this.commands;

            // Character width based on paper size
            let width;
            switch(this.config.paperWidth) {
                case '58mm':  width = 30; break;
                case '76mm':  width = 40; break;
                case '80mm':  width = 46; break;
                case '100mm': width = 58; break;
                case '112mm': width = 66; break;
                default:      width = 46;
            }

            console.log('[PosPrinter] Receipt generation:', {
                paperWidth: this.config.paperWidth,
                calculatedWidth: width,
                isReprint: options.isReprint
            });

            // Initialize printer
            data.push(cmd.INIT);

            // ── COMPANY HEADER ─────────────────────────────────────
            // Address source is controlled by PRINTER_SETTINGS.addressSource:
            // - 'branch': use branch address (options.branchInfo for reprints, window.POS_BRANCH_INFO for new)
            // - 'company': use company address from system_settings
            const addressSource = this.config.addressSource || 'company';
            const useBranchAddress = addressSource === 'branch';

            console.log('[PosPrinter] Receipt header addressSource:', addressSource, 'useBranchAddress:', useBranchAddress);

            const branchInfo = options.branchInfo
                || ((typeof window !== 'undefined' && window.POS_BRANCH_INFO) ? window.POS_BRANCH_INFO : null);

            console.log('[PosPrinter] branchInfo:', branchInfo);

            data.push(cmd.ALIGN_CENTER);
            data.push(cmd.BOLD_ON);
            data.push((this.companyInfo.name || 'TMS POS') + '\n');
            data.push(cmd.BOLD_OFF);

            if (useBranchAddress && branchInfo && branchInfo.branch_name) {
                // Build branch address from business_branches fields
                const addrParts = [];
                if (branchInfo.street_address) addrParts.push(branchInfo.street_address);
                if (branchInfo.barangay_name) addrParts.push(branchInfo.barangay_name);
                if (addrParts.length > 0) {
                    data.push(addrParts.join(', ') + '\n');
                }
                const cityProvParts = [];
                if (branchInfo.city_municipality_name) cityProvParts.push(branchInfo.city_municipality_name);
                if (branchInfo.province_name) cityProvParts.push(branchInfo.province_name);
                if (cityProvParts.length > 0) {
                    data.push(cityProvParts.join(', ') + '\n');
                }
                if (branchInfo.region_name) {
                    data.push(branchInfo.region_name + '\n');
                }
                if (branchInfo.zip_code) {
                    data.push(branchInfo.zip_code + '\n');
                }
                if (branchInfo.contact_number) {
                    data.push('Contact: ' + branchInfo.contact_number + '\n');
                }
            } else if (this.companyInfo.address) {
                // Use company address from system_settings
                data.push(this.companyInfo.address + '\n');
                if (this.companyInfo.contact) {
                    data.push('Contact: ' + this.companyInfo.contact + '\n');
                }
            }
            if (this.config.showTin && this.companyInfo.tin) {
                data.push('TIN: ' + this.companyInfo.tin + '\n');
            }
            // Custom header text from System Settings
            if (this.config.headerText) {
                data.push(this.config.headerText + '\n');
            }
            data.push(cmd.ALIGN_LEFT);
            data.push(this.repeatChar('=', width) + '\n');

            // ── REPRINT BANNER ─────────────────────────────────────
            if (options.isReprint) {
                data.push(cmd.ALIGN_CENTER);
                data.push(cmd.BOLD_ON);
                data.push('*** REPRINT ***\n');
                data.push(cmd.BOLD_OFF);
                data.push(cmd.ALIGN_LEFT);
            }

            // ── COPY LABEL ─────────────────────────────────────────
            if (options.copyLabel) {
                data.push(cmd.ALIGN_CENTER);
                data.push(cmd.BOLD_ON);
                data.push('[ ' + options.copyLabel + ' ]\n');
                data.push(cmd.BOLD_OFF);
                data.push(cmd.ALIGN_LEFT);
            }

            // ── TRANSACTION INFO ───────────────────────────────────
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            data.push(`Date       : ${dateStr}\n`);
            data.push(`Time       : ${timeStr}\n`);
            if (this.config.showBranch && transaction.branch_name) {
                data.push(`Branch     : ${transaction.branch_name}\n`);
            }
            // Receipt number: padded numeric ID, fallback to transaction_code
            const receiptId = transaction.id || transaction.order_id || transaction.transaction_id;
            const paddedId = receiptId != null
                ? String(receiptId).padStart(3, '0')
                : (transaction.transaction_code || 'N/A');
            data.push(`Receipt No.: ${paddedId}\n`);
            
            // BIR: OR Number (if available)
            if (transaction.or_number) {
                data.push(`OR Number  : ${transaction.or_number}\n`);
            }
            
            if (this.config.showCashier && transaction.cashier_name) {
                data.push(`Cashier    : ${transaction.cashier_name}\n`);
            }
            if (this.config.showPaymentMethod && transaction.payment_method) {
                data.push(`Payment    : ${transaction.payment_method}\n`);
            }
            data.push(this.repeatChar('-', width) + '\n');

            // ── ITEMS ──────────────────────────────────────────────
            data.push(cmd.BOLD_ON);
            data.push('DESCRIPTION' + ' '.repeat(Math.max(1, width - 19)) + 'AMOUNT\n');
            data.push(cmd.BOLD_OFF);
            data.push(this.repeatChar('-', width) + '\n');

            if (transaction.items && transaction.items.length > 0) {
                transaction.items.forEach(item => {
                    const itemName = item.name || item.description || 'Item';
                    const qty       = parseInt(item.quantity) || 1;
                    const baseAmt   = parseFloat(item.base_amount || item.price || 0);
                    const svcFee    = parseFloat(item.service_fee || 0);
                    const discAmt   = parseFloat(item.discount_amount || 0);
                    const itemTotal = parseFloat(item.total || (qty * baseAmt + svcFee - discAmt));

                    // Determine item line amount based on total source
                    let lineAmount = itemTotal;
                    if (this.config.totalSource === 'service_fee') {
                        lineAmount = svcFee > 0 ? svcFee : itemTotal;
                    } else if (this.config.totalSource === 'base_amount') {
                        lineAmount = (qty * baseAmt) > 0 ? (qty * baseAmt) : itemTotal;
                    } else if (this.config.totalSource === 'subtotal') {
                        lineAmount = (qty * baseAmt + svcFee) > 0 ? (qty * baseAmt + svcFee) : itemTotal;
                    }

                    // Item name line: name ... amount (optional)
                    if (this.config.showItemTotal) {
                        const amtStr   = lineAmount.toFixed(2);
                        const nameMax  = width - amtStr.length - 1;
                        const dispName = itemName.length > nameMax
                            ? itemName.substring(0, nameMax)
                            : itemName.padEnd(nameMax);
                        data.push(`${dispName} ${amtStr}\n`);
                    } else {
                        this.wrapText(itemName, width).forEach(line => data.push(`${line}\n`));
                    }

                    (item.details || []).forEach(detail => {
                        this.wrapText(`  ${detail}`, width).forEach(line => data.push(`${line}\n`));
                    });

                    // Qty × base price sub-line
                    if (this.config.showBaseAmount && baseAmt > 0) {
                        data.push(`  Base fare : ${qty} x ${baseAmt.toFixed(2)}\n`);
                    }
                    // Service fee sub-line (per item)
                    // Hide per-item service fee when the aggregate service fee total is shown
                    // to avoid redundancy on the receipt.
                    if (this.config.showServiceFee && svcFee > 0 && !this.config.showServiceFeeTotal) {
                        data.push(`  Service Fee : ${svcFee.toFixed(2)}\n`);
                    }
                    // Per-item discount sub-line
                    if (this.config.showDiscount && discAmt > 0) {
                        data.push(`  Discount        : -${discAmt.toFixed(2)}\n`);
                    }
                });
            }

            data.push(this.repeatChar('-', width) + '\n');

            // ── TOTALS ─────────────────────────────────────────────
            const subtotal        = parseFloat(transaction.subtotal || 0);
            const discountTotal   = parseFloat(transaction.discount || 0);
            const taxTotal        = parseFloat(transaction.tax || 0);
            const grandTotal      = parseFloat(transaction.total || transaction.grand_total || 0);
            const amountTendered  = parseFloat(transaction.amount_tendered || grandTotal);
            const changeAmt       = parseFloat(transaction.change_amount || 0);

            // Aggregate service fee from items
            const totalSvcFee = (transaction.items || []).reduce(
                (s, i) => s + parseFloat(i.service_fee || 0), 0
            );

            // Aggregate base amount from items (respecting quantity)
            const totalBase = (transaction.items || []).reduce(
                (s, i) => s + ((parseInt(i.quantity) || 1) * parseFloat(i.base_amount || 0)), 0
            );

            // Aggregate subtotal from items (base * qty + service fee) for display consistency
            const computedSubtotal = (transaction.items || []).reduce(
                (s, i) => s + ((parseInt(i.quantity) || 1) * parseFloat(i.base_amount || 0) + parseFloat(i.service_fee || 0)), 0
            );

            if (this.config.showSubtotal) {
                data.push(this.formatLine('Subtotal         :', subtotal.toFixed(2), width));
            }
            if (this.config.showServiceFeeTotal && totalSvcFee > 0) {
                data.push(this.formatLine('Service Fee      :', totalSvcFee.toFixed(2), width));
            }
            if (this.config.showDiscount && discountTotal > 0) {
                data.push(this.formatLine('Discount         :', '-' + discountTotal.toFixed(2), width));
            }
            
            // BIR: VAT breakdown
            if (this.config.showVat && transaction.vat_data) {
                const vatAmount = parseFloat(transaction.vat_data.vat_amount || 0);
                const vatType = transaction.vat_data.vat_type || '12_percent';
                const taxableAmount = parseFloat(transaction.vat_data.taxable_amount || 0);
                const nonTaxableAmount = parseFloat(transaction.vat_data.non_taxable_amount || 0);

                if (vatAmount > 0) {
                    let vatTypeLabel = '12% VAT';
                    if (vatType === 'exempt') vatTypeLabel = 'VAT-Exempt';
                    else if (vatType === 'zero_rated') vatTypeLabel = 'Zero-Rated';

                    data.push(this.formatLine(`${vatTypeLabel}       :`, vatAmount.toFixed(2), width));
                    data.push(this.formatLine('Taxable Sales    :', taxableAmount.toFixed(2), width));
                }
                if (nonTaxableAmount > 0) {
                    data.push(this.formatLine('Non-Taxable      :', nonTaxableAmount.toFixed(2), width));
                }
            } else if (this.config.showVat && taxTotal > 0) {
                // Fallback to legacy tax field
                data.push(this.formatLine('VAT/Tax          :', taxTotal.toFixed(2), width));
            }

            // Determine the grand total amount based on the configured source
            let totalAmount = grandTotal;
            if (this.config.totalSource === 'service_fee') {
                totalAmount = totalSvcFee;
            } else if (this.config.totalSource === 'base_amount') {
                totalAmount = totalBase;
            } else if (this.config.totalSource === 'subtotal') {
                totalAmount = computedSubtotal || subtotal;
            }

            data.push(this.repeatChar('=', width) + '\n');
            data.push(cmd.BOLD_ON);
            data.push(cmd.DOUBLE_HEIGHT);
            data.push(this.formatLine('TOTAL            :', totalAmount.toFixed(2), width));
            data.push(cmd.NORMAL_SIZE);
            data.push(cmd.BOLD_OFF);
            data.push(this.repeatChar('-', width) + '\n');

            if (this.config.showTendered) {
                data.push(this.formatLine('Cash Tendered    :', amountTendered.toFixed(2), width));
                data.push(this.formatLine('Change           :', changeAmt.toFixed(2), width));
            }

            // ── QR CODE ────────────────────────────────────────────
            if (this.config.qrEnabled) {
                const qrValue = transaction.transaction_code || transaction.id;
                if (qrValue) {
                    data.push('\n');
                    data.push(cmd.ALIGN_CENTER);
                    let qrData;
                    if (this.config.qrFormat === 'URL') {
                        qrData = (window.BASE_URL || '') + '/verify/' + encodeURIComponent(transaction.transaction_code || transaction.id);
                    } else {
                        qrData = String(transaction.transaction_code || transaction.id);
                    }
                    data.push(this.generateQRCode(qrData));
                    data.push(cmd.ALIGN_LEFT);
                }
            }

            // ── FOOTER ─────────────────────────────────────────────
            data.push('\n');
            data.push(this.repeatChar('=', width) + '\n');
            data.push(cmd.ALIGN_CENTER);

            // BIR: Accreditation details
            if (this.companyInfo.birPermitNumber) {
                data.push(`Permit No: ${this.companyInfo.birPermitNumber}\n`);
            }
            if (this.companyInfo.birAccreditationNumber) {
                data.push(`Accreditation No: ${this.companyInfo.birAccreditationNumber}\n`);
            }
            if (this.companyInfo.birValidityFrom && this.companyInfo.birValidityTo) {
                data.push(`Valid: ${this.companyInfo.birValidityFrom} to ${this.companyInfo.birValidityTo}\n`);
            }
            if (this.companyInfo.birMin) {
                data.push(`MIN: ${this.companyInfo.birMin}\n`);
            }
            if (this.companyInfo.birSerialNumber) {
                data.push(`Serial No: ${this.companyInfo.birSerialNumber}\n`);
            }
            
            // BIR: Official Receipt disclaimer
            data.push('\n');
            data.push(cmd.BOLD_ON);
            data.push('This serves as Official Receipt\n');
            data.push(cmd.BOLD_OFF);
            
            // Custom footer (from System Settings) takes precedence, then default footer text
            const footerLine = this.config.customFooter || this.config.footerText || 'Thank you for your business!';
            data.push(footerLine + '\n');
            data.push(cmd.ALIGN_LEFT);
            data.push('\n\n\n');

            // ── CUT / DRAWER ───────────────────────────────────────
            if (this.config.autoCut) {
                data.push(cmd.CUT);
            }
            if (this.config.openCashDrawer) {
                data.push(cmd.DRAWER);
            }

            return data;
        },

        /**
         * Generate QR Code ESC/POS data
         * @param {string} data - QR code data
         * @returns {string} ESC/POS commands
         */
        generateQRCode: function(data) {
            const cmd = this.commands;
            const dataLength = data.length + 3;
            const pL = dataLength % 256;
            const pH = Math.floor(dataLength / 256);
            
            let qrCmd = '';
            qrCmd += cmd.QR_MODEL;
            qrCmd += cmd.QR_SIZE;
            qrCmd += cmd.QR_ERROR;
            qrCmd += '\x1D\x28\x6B' + String.fromCharCode(pL, pH) + '\x31\x50\x30';
            qrCmd += data;
            qrCmd += '\x1D\x28\x6B\x03\x00\x31\x51\x30';
            
            return qrCmd;
        },

        /**
         * Format a line with label and amount
         * @param {string} label - Line label
         * @param {string} amount - Amount value
         * @param {number} width - Line width
         * @returns {string} Formatted line
         */
        formatLine: function(label, amount, width) {
            const labelWidth = width - amount.length - 1;
            return label.substring(0, labelWidth).padEnd(labelWidth) + ' ' + amount + '\n';
        },

        wrapText: function(text, width) {
            const words = String(text || '').split(/\s+/).filter(Boolean);
            const lines = [];
            let line = '';

            words.forEach(word => {
                if (line && `${line} ${word}`.length > width) {
                    lines.push(line);
                    line = word;
                } else {
                    line = line ? `${line} ${word}` : word;
                }
            });

            if (line) lines.push(line);
            return lines.length ? lines : [''];
        },

        /**
         * Center text within width
         * @param {string} text - Text to center
         * @param {number} width - Line width
         * @returns {string} Centered text
         */
        centerText: function(text, width) {
            const padding = Math.max(0, Math.floor((width - text.length) / 2));
            return ' '.repeat(padding) + text;
        },

        /**
         * Repeat character
         * @param {string} char - Character to repeat
         * @param {number} count - Number of times
         * @returns {string} Repeated string
         */
        repeatChar: function(char, count) {
            return char.repeat(Math.max(0, count));
        },

        /**
         * Log print to database (via AJAX)
         * @param {Object} transaction - Transaction data
         * @param {string} status - Print status
         * @param {string} error - Error message (if failed)
         */
        logPrint: function(transaction, status, error) {
            // This would be implemented to log prints to the database
            // for audit and reprint purposes
            if (window.console && console.log) {
                console.log('[PosPrinter] Print logged:', {
                    transaction: transaction.id || transaction.transaction_code,
                    status: status,
                    printer: this.state.printerName,
                    terminal: this.state.terminalName,
                    error: error || null
                });
            }
        },

        /**
         * Show print preview modal
         * @param {Object} transaction - Transaction data
         * @returns {Promise<boolean>} User confirmed print
         */
        showPreview: async function(transaction) {
            return new Promise((resolve) => {
                // Generate preview HTML
                const previewData = this.generateReceiptData(transaction, {});
                let previewText = previewData.join('')
                    .replace(/\x1B\[[0-9;]*[a-zA-Z]/g, '')
                    .replace(/\x1B[@ABCDEFGHIJKLMNOXZ]/g, '')
                    .replace(/\x1D\[[0-9;]*[a-zA-Z]/g, '')
                    .replace(/\x0A/g, '<br>')
                    .replace(/\x0D/g, '');

                // Prepend logo HTML if enabled
                let logoHtml = '';
                if (this.config.logoEnabled && this.companyInfo.logo) {
                    logoHtml = `<div style="text-align:center;margin-bottom:8px;"><img src="${this.companyInfo.logo}" alt="Logo" style="max-width:160px;max-height:60px;object-fit:contain;"></div>`;
                }

                // Create modal HTML
                const modalHtml = `
                    <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Receipt Preview</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="receipt-preview-modal" style="font-family: 'Courier New', monospace; font-size: 12px; background: #fff; border: 1px solid #ddd; padding: 20px; max-height: 450px; overflow-y: auto;">
                                        ${logoHtml}${previewText}
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window._receiptPreviewResult(false)">Cancel</button>
                                    <button type="button" class="btn btn-primary" onclick="window._receiptPreviewResult(true)" data-bs-dismiss="modal">
                                        <span class="fas fa-print me-2"></span>Print Receipt
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Add to DOM
                const modalContainer = document.createElement('div');
                modalContainer.innerHTML = modalHtml;
                document.body.appendChild(modalContainer);

                // Set up callback
                window._receiptPreviewResult = (result) => {
                    resolve(result);
                    document.body.removeChild(modalContainer);
                    delete window._receiptPreviewResult;
                };

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
                modal.show();
            });
        },

        /**
         * Quick print without preview
         * @param {Object} transaction - Transaction data
         * @returns {Promise<void>}
         */
        quickPrint: async function(transaction) {
            await this.printReceipt(transaction, { skipPreview: true });
        },

        /**
         * Reprint a previous transaction
         * @param {Object} transaction - Transaction data
         * @param {string} reason - Reprint reason
         * @returns {Promise<void>}
         */
        reprint: async function(transaction, reason) {
            // Reload config to ensure terminal override is applied
            this.loadSavedConfig();

            // Build branch info from transaction data (original transaction's branch)
            const branchInfo = transaction.branch_name ? {
                branch_name: transaction.branch_name,
                street_address: transaction.street_address || '',
                barangay_name: transaction.barangay_name || '',
                city_municipality_name: transaction.city_municipality_name || '',
                province_name: transaction.province_name || '',
                region_name: transaction.region_name || '',
                zip_code: transaction.zip_code || '',
                landmark: transaction.landmark || '',
                contact_number: transaction.branch_contact || transaction.contact_number || ''
            } : null;

            const options = {
                isReprint: true,
                reprintReason: reason,
                skipPreview: false,
                branchInfo: branchInfo
            };
            await this.printReceipt(transaction, options);
        }
    };

    // Expose to global scope
    window.PosPrinter = PosPrinter;

})(window);
