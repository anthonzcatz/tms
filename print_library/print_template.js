/**
 * Professional Print Library
 * Reusable print function for generating clean, professional document prints
 * 
 * USAGE:
 * 1. Include this script in your page
 * 2. Load signatories data into window.printSignatories array (optional)
 * 3. Call printDocument() with your configuration
 * 
 * @version 1.0
 * @author CSTDC
 */

// Global flag to prevent multiple print triggers
let __isPrintingDocument = false;

// Signatories data - load this from your database
window.printSignatories = window.printSignatories || [
    { role: 'Prepared by', name: 'YOUR NAME' },
    { role: 'Verified by', name: 'VERIFIER NAME' },
    { role: 'Approved by', name: 'APPROVER NAME' }
];

/**
 * Main print function
 * @param {Object} config - Configuration object
 * @param {string} config.title - Document title
 * @param {string} config.companyLogo - Path to company logo
 * @param {string} config.companyAddress - Company address
 * @param {string} config.companyTagline - Company motto/tagline
 * @param {Object} config.header - Header info (left and right sections)
 * @param {Object} config.infoSections - Info sections (e.g., Supplier, Ship To)
 * @param {string} config.shippingInfo - Shipping info table HTML
 * @param {string} config.itemsTable - Items table HTML
 * @param {string} config.notes - Notes content
 * @param {Object} config.totals - Totals object (subtotal, discount, shipping, total)
 * @param {string} config.footerInfo - Footer info (e.g., payment terms)
 * @param {string} config.pageSize - Page size: 'letter' or 'legal' (default: 'legal')
 */
function printDocument(config) {
    if (__isPrintingDocument) {
        console.warn('Print already in progress');
        return;
    }
    __isPrintingDocument = true;

    try {
        // Merge with defaults
        const cfg = {
            title: 'Document',
            companyLogo: '../../img/logo/default.png',
            companyAddress: '',
            companyTagline: '',
            header: { left: '', right: '' },
            infoSections: [],
            shippingInfo: '',
            itemsTable: '',
            notes: '',
            totals: { subtotal: '₱0.00', discount: null, shipping: '₱0.00', total: '₱0.00' },
            footerInfo: '',
            pageSize: 'legal',
            ...config
        };

        // Build signatures table
        const signaturesHTML = buildSignaturesTable();

        // Build complete HTML
        const htmlContent = buildPrintHTML(cfg, signaturesHTML);

        // Create hidden iframe and print
        executePrint(htmlContent);

    } catch (error) {
        console.error('Error in print function:', error);
        __isPrintingDocument = false;
    }
}

/**
 * Build signatures table HTML
 */
function buildSignaturesTable() {
    let html = '<table class="signatures-table">';
    if (window.printSignatories && Array.isArray(window.printSignatories)) {
        window.printSignatories.forEach(sig => {
            const role = sig.role || '';
            const name = sig.name || '';
            html += `
                <tr>
                    <td class="sig-role">${role}:</td>
                    <td class="sig-name">${name}</td>
                    <td class="sig-label">Signature:</td>
                    <td class="sig-line"></td>
                    <td class="date-label">Date:</td>
                    <td class="date-line"></td>
                </tr>`;
        });
    }
    html += '</table>';
    return html;
}

/**
 * Build complete print HTML with embedded CSS
 */
function buildPrintHTML(config, signaturesHTML) {
    // Build discount row if present
    let discountHTML = '';
    if (config.totals.discount) {
        discountHTML = `
            <div class="total-row">
                <span>Discount:</span>
                <span>${config.totals.discount}</span>
            </div>`;
    }

    // Build info sections
    let infoSectionsHTML = '';
    if (config.infoSections && Array.isArray(config.infoSections)) {
        config.infoSections.forEach(section => {
            infoSectionsHTML += `
                <div class="info-section">
                    <h3>${section.title}</h3>
                    ${section.items.map(item => `<div class="info-item"><strong>${item.label}:</strong> ${item.value}</div>`).join('')}
                </div>`;
        });
    }

    return `<!DOCTYPE html>
<html>
<head>
    <title>${config.title}</title>
    <style>
        /* ============================================
           PRINT CSS - Professional Document Layout
           ============================================ */
        
        @media print {
            @page {
                size: ${config.pageSize};
                margin: 0;
            }
            
            body { 
                font-family: 'Century Gothic', CenturyGothic, AppleGothic, Arial, sans-serif; 
                margin: 0; 
                padding: 10mm 12mm; 
                font-size: 10pt; 
                line-height: 1.1;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: #000;
            }
            
            /* Company Header */
            .company-header { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 10px; 
            }
            
            .company-logo { 
                max-width: 150px;
                max-height: 70px;
                width: auto;
                height: auto;
                object-fit: contain;
            }
            
            .company-address { 
                font-size: 9pt; 
                margin-top: 2px; 
            }
            
            .company-motto { 
                font-size: 9pt; 
                font-style: italic;
            }
            
            /* Document Title Section */
            .doc-title { 
                font-size: 16pt; 
                font-weight: bold; 
                text-align: right;
                margin-top: 40px; 
            }
            
            .doc-number { 
                font-size: 12pt; 
                color: #666; 
                margin-top: 10px; 
            }
            
            .doc-date { 
                font-size: 10pt; 
                margin-top: 10px; 
            }
            
            /* Info Sections (Supplier, Ship To, etc.) */
            .info-section { 
                border: 1px solid #000; 
                background-color: #f0f8ff; 
                padding: 5px; 
                margin: 5px 0; 
            }
            
            .info-section h3 { 
                margin: 0 0 5px 0; 
                font-size: 11pt; 
                color: #333;
                font-weight: normal;
            }
            
            .info-item {
                margin-bottom: 2px;
                font-size: 9pt;
            }
            
            /* Shipping Info Table */
            .shipping-info { 
                margin-top: 5px; 
                width: 100%; 
                border-collapse: collapse; 
                font-size: 9pt;
            }
            
            .shipping-info th, .shipping-info td { 
                border: 1px solid #000; 
                padding: 4px; 
                text-align: left; 
            }
            
            .shipping-info th {
                background-color: #f0f8ff;
                font-weight: normal;
            }
            
            /* Items Table */
            .items-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0; 
                font-size: 9pt;
                table-layout: fixed;
            }
            
            .items-table th, .items-table td { 
                border: 1px solid #000; 
                padding: 4px; 
                text-align: left; 
            }
            
            .items-table th { 
                background-color: #f0f8ff; 
                font-weight: normal;
                text-transform: uppercase;
            }
            
            /* Column widths - adjust based on your needs */
            .items-table th:nth-child(1), .items-table td:nth-child(1) { width: 10%; text-align: center; }
            .items-table th:nth-child(2), .items-table td:nth-child(2) { width: auto; }
            .items-table th:nth-child(3), .items-table td:nth-child(3) { width: 12%; text-align: center; }
            .items-table th:nth-child(4), .items-table td:nth-child(4) { width: 8%; text-align: center; }
            .items-table th:nth-child(5), .items-table td:nth-child(5) { width: 12%; text-align: right; }
            .items-table th:nth-child(6), .items-table td:nth-child(6) { width: 12%; text-align: right; }
            
            /* Notes and Totals Container */
            .notes-totals-container {
                display: flex;
                justify-content: space-between;
                align-items: stretch;
                margin: 5px 0 10px 0;
                gap: 20px;
            }
            
            .notes { 
                border: 1px solid #000; 
                background-color: #f0f8ff; 
                padding: 5px; 
                font-size: 9pt;
                width: calc(100% - 240px);
                flex-shrink: 0;
                display: flex;
                flex-direction: column;
                box-sizing: border-box;
            }
            
            .notes h3 {
                margin: 0 0 3px 0;
                font-size: 10pt;
                font-weight: normal;
            }
            
            .notes > div {
                flex: 1;
            }
            
            /* Totals Section */
            .totals { 
                width: 220px; 
                font-size: 9pt;
                flex-shrink: 0;
                border: none;
                background: transparent;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                box-sizing: border-box;
            }
            
            .totals .total-row:first-child { margin-top: 0; }
            
            .total-row { 
                display: flex; 
                justify-content: space-between; 
                padding: 2px 0; 
            }
            
            .grand-total { 
                font-weight: bold; 
                border-top: 1px solid #000; 
                padding-top: 2px;
                font-size: 10pt; 
            }
            
            /* Signatures Table */
            .signatures-table { 
                margin-top: 10px;
                font-size: 9pt;
                width: 100%;
                border-collapse: separate;
                border-spacing: 0 8px;
            }
            
            .signatures-table td {
                padding: 6px 5px;
                vertical-align: bottom;
                line-height: 1.5;
            }
            
            .signatures-table .sig-role { width: 80px; text-align: left; }
            .signatures-table .sig-name { width: 150px; text-align: center; border-bottom: 1px solid #000; }
            .signatures-table .sig-label { width: 70px; text-align: left; }
            .signatures-table .sig-line { width: 120px; border-bottom: 1px solid #000; }
            .signatures-table .date-label { width: 40px; text-align: right; }
            .signatures-table .date-line { width: 80px; border-bottom: 1px solid #000; }
            
            /* Footer Info (Payment Terms, etc.) */
            .footer-info {
                margin-top: 10px;
                font-size: 9pt;
            }
            
            .footer-info h3 {
                margin: 0 0 3px 0;
                font-size: 10pt;
                font-weight: normal;
            }
            
            .footer-info-box {
                border: 1px solid #000; 
                background-color: #f0f8ff; 
                padding: 5px; 
                min-height: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="company-header">
        <div>
            <img src="${config.companyLogo}" alt="Company Logo" class="company-logo">
            <div class="company-address">${config.companyAddress}</div>
            <div class="company-motto">${config.companyTagline}</div>
        </div>
        <div>
            <div class="doc-title">${config.title}</div>
            <div class="doc-number">${config.header.right}</div>
            <div class="doc-date">${config.header.left}</div>
        </div>
    </div>

    <!-- Info Sections -->
    <div style="display: flex; justify-content: space-between; gap: 10px;">
        ${infoSectionsHTML}
    </div>

    <!-- Shipping Info -->
    ${config.shippingInfo}

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th>ITEM NO.</th>
                <th>ITEM DESCRIPTION</th>
                <th>QTY.</th>
                <th>UOM</th>
                <th>UNIT PRICE</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            ${config.itemsTable}
        </tbody>
    </table>

    <!-- Notes and Totals -->
    <div class="notes-totals-container">
        <div class="notes">
            <h3>Notes and Instructions</h3>
            <div>${config.notes || 'No notes provided.'}</div>
        </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>${config.totals.subtotal}</span>
            </div>
            ${discountHTML}
            <div class="total-row">
                <span>F&H:</span>
                <span>${config.totals.shipping}</span>
            </div>
            <div class="total-row grand-total">
                <span>Total Amount:</span>
                <span>${config.totals.total}</span>
            </div>
        </div>
    </div>

    <!-- Signatures -->
    ${signaturesHTML}

    <!-- Footer Info -->
    <div class="footer-info">
        <h3>${config.footerInfo.title || 'PAYMENT TERMS'}</h3>
        <div class="footer-info-box">
            ${config.footerInfo.content || ''}
        </div>
    </div>
</body>
</html>`;
}

/**
 * Execute print using hidden iframe
 */
function executePrint(htmlContent) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.left = '-9999px';
    iframe.style.top = '-9999px';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.setAttribute('aria-hidden', 'true');
    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(htmlContent);
    iframeDoc.close();

    iframe.onload = function() {
        setTimeout(function() {
            const cleanup = function() {
                try {
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                } catch (e) {
                    // ignore
                }
                __isPrintingDocument = false;
            };

            try {
                if (iframe.contentWindow) {
                    iframe.contentWindow.onafterprint = cleanup;
                    iframe.contentWindow.addEventListener('afterprint', cleanup, { once: true });

                    const mm = iframe.contentWindow.matchMedia ? iframe.contentWindow.matchMedia('print') : null;
                    if (mm && typeof mm.addEventListener === 'function') {
                        mm.addEventListener('change', function(e) {
                            if (!e.matches) cleanup();
                        }, { once: true });
                    }
                }
            } catch (e) {
                // ignore
            }

            iframe.contentWindow.focus();
            iframe.contentWindow.print();

            // Fallback cleanup
            setTimeout(cleanup, 3000);
        }, 200);
    };
}
