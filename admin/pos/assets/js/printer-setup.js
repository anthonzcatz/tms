// ── QZ Tray Security Setup ───────────────────────────────────────────────
/**
 * Configure QZ Tray certificate and SHA-512/RSA signature callbacks.
 * Must be called before qz.websocket.connect().
 */
function setupQZSecurity() {
  if (!window.QZ_CERT || !window.QZ_PRIVATE_KEY) {
    console.warn('[QZSecurity] QZ_CERT or QZ_PRIVATE_KEY not set.');
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
}

// Global state
let qzConnected = false;
let selectedPrinter = null;
let availablePrinters = [];
const paperWidth = document.querySelector('body').dataset.paperWidth || '80mm';
const printerType = document.querySelector('body').dataset.printerType || 'THERMAL';

// Terminal detection
const terminalName = 'POS-' + Math.random().toString(36).substr(2, 9).toUpperCase();

// DOM Elements
const btnConnect = document.getElementById('btnConnect');
const btnDisconnect = document.getElementById('btnDisconnect');
const btnSave = document.getElementById('btnSave');
const btnTestPrint = document.getElementById('btnTestPrint');
const connectionStatus = document.getElementById('connectionStatus');
const printerList = document.getElementById('printerList');
const savedPrinterName = document.getElementById('savedPrinterName');
const terminalPaperWidth = document.getElementById('terminalPaperWidth');
const terminalAutoPrint = document.getElementById('terminalAutoPrint');
const terminalCopies = document.getElementById('terminalCopies');
const connectionLog = document.getElementById('connectionLog');
const receiptPreview = document.getElementById('receiptPreview');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
  // Display system info
  document.getElementById('terminalName').textContent = terminalName;
  document.getElementById('browserInfo').textContent = navigator.userAgent.split(' ')[0];

  // Load saved printer
  const savedPrinter = localStorage.getItem('tms_pos_printer');
  if (savedPrinter) {
    savedPrinterName.value = savedPrinter;
    selectedPrinter = savedPrinter;
  }

  // Load saved terminal paper width override
  const savedPaperWidth = localStorage.getItem('tms_pos_paper_width_override');
  if (savedPaperWidth) {
    terminalPaperWidth.value = savedPaperWidth;
  }

  // Load saved terminal print mode override
  const savedAutoPrint = localStorage.getItem('tms_pos_auto_print_override');
  if (savedAutoPrint !== null && terminalAutoPrint) {
    terminalAutoPrint.value = savedAutoPrint;
  }

  // Load saved terminal copies override
  const savedCopies = localStorage.getItem('tms_pos_copies_override');
  if (savedCopies !== null && terminalCopies) {
    terminalCopies.value = savedCopies;
  }

  // Initialize PosPrinter module if available
  if (window.PRINTER_SETTINGS && window.PosPrinter) {
    console.log('[PrinterSetup] Initializing PosPrinter with settings:', window.PRINTER_SETTINGS);
    window.PosPrinter.init({
      config: window.PRINTER_SETTINGS,
      companyInfo: window.COMPANY_INFO
    });
  }

  // Auto-connect if previously connected
  if (localStorage.getItem('tms_qz_autoconnect') === 'true') {
    setTimeout(connectToQZ, 500);
  }
});

// Logging function
function log(message, type = 'info') {
  const entry = document.createElement('div');
  entry.className = `log-entry log-${type}`;
  entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
  connectionLog.appendChild(entry);
  connectionLog.scrollTop = connectionLog.scrollHeight;
}

// Update connection status UI
function updateConnectionStatus(status, message) {
  if (status === 'connected') {
    connectionStatus.innerHTML = '<span class="status-indicator status-connected me-2"></span><span class="small">Connected</span>';
    connectionStatus.className = 'badge bg-success d-flex align-items-center';
    btnConnect.disabled = true;
    btnDisconnect.disabled = false;
    btnTestPrint.disabled = !selectedPrinter;
  } else if (status === 'connecting') {
    connectionStatus.innerHTML = '<span class="status-indicator status-connecting me-2"></span><span class="small">Connecting...</span>';
    connectionStatus.className = 'badge bg-warning text-dark d-flex align-items-center';
    btnConnect.disabled = true;
  } else {
    connectionStatus.innerHTML = '<span class="status-indicator status-disconnected me-2"></span><span class="small">Not Connected</span>';
    connectionStatus.className = 'badge bg-secondary d-flex align-items-center';
    btnConnect.disabled = false;
    btnDisconnect.disabled = true;
    btnTestPrint.disabled = true;
    btnSave.disabled = true;
  }
}

// Connect to QZ Tray
async function connectToQZ() {
  try {
    updateConnectionStatus('connecting');
    log('Connecting to QZ Tray...', 'info');

    if (qz.websocket.isActive()) {
      await qz.websocket.disconnect();
    }

    setupQZSecurity();
    await qz.websocket.connect();
    qzConnected = true;
    updateConnectionStatus('connected');
    log('Successfully connected to QZ Tray', 'success');

    // Enable auto-connect for future visits
    localStorage.setItem('tms_qz_autoconnect', 'true');

    // Load available printers
    await loadPrinters();

  } catch (err) {
    qzConnected = false;
    updateConnectionStatus('disconnected');
    log('Connection failed: ' + (err.message || err), 'error');
    
    // Show detailed error alert
    let errorMsg = 'Failed to connect to QZ Tray.\n\n';
    errorMsg += 'Please ensure:\n';
    errorMsg += '1. QZ Tray is installed and running\n';
    errorMsg += '2. You clicked "Allow" on the QZ Tray prompt\n';
    errorMsg += '3. This website is added to QZ Tray allowed origins\n\n';
    errorMsg += 'Download QZ Tray from: https://qz.io/download/';
    
    alert(errorMsg);
  }
}

// Disconnect from QZ Tray
async function disconnectFromQZ() {
  try {
    if (qz.websocket.isActive()) {
      await qz.websocket.disconnect();
    }
    qzConnected = false;
    updateConnectionStatus('disconnected');
    log('Disconnected from QZ Tray', 'info');
    
    // Disable auto-connect
    localStorage.setItem('tms_qz_autoconnect', 'false');
    
    // Reset printer list
    printerList.innerHTML = `
      <div class="col-12 text-center text-muted py-4">
        <span class="fas fa-plug-circle-xmark fa-2x mb-2"></span>
        <p>Connect to QZ Tray first to see available printers</p>
      </div>
    `;
  } catch (err) {
    log('Error disconnecting: ' + (err.message || err), 'error');
  }
}

// Load available printers
async function loadPrinters() {
  try {
    log('Fetching available printers...', 'info');
    const printers = await qz.printers.find();
    availablePrinters = printers;
    
    log(`Found ${printers.length} printer(s)`, 'success');

    // Render printer list
    if (printers.length === 0) {
      printerList.innerHTML = `
        <div class="col-12 text-center text-warning py-4">
          <span class="fas fa-print fa-2x mb-2"></span>
          <p>No printers found. Please install a printer first.</p>
        </div>
      `;
      return;
    }

    let html = '';
    printers.forEach((printer, index) => {
      const isSelected = printer === selectedPrinter;
      html += `
        <div class="col-md-6">
          <div class="card printer-card ${isSelected ? 'selected' : ''}" 
               onclick="selectPrinter('${printer.replace(/'/g, "\\'")}')" 
               id="printer-${index}">
            <div class="card-body p-3">
              <div class="d-flex align-items-center">
                <span class="fas fa-print fa-2x me-3 ${isSelected ? 'text-success' : 'text-muted'}"></span>
                <div>
                  <h6 class="mb-1">${printer}</h6>
                  ${isSelected ? '<span class="badge bg-success">Selected</span>' : '<span class="badge bg-light text-dark">Click to select</span>'}
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    printerList.innerHTML = html;
    btnSave.disabled = !selectedPrinter;

  } catch (err) {
    log('Error loading printers: ' + (err.message || err), 'error');
    printerList.innerHTML = `
      <div class="col-12 text-center text-danger py-4">
        <span class="fas fa-exclamation-triangle fa-2x mb-2"></span>
        <p>Error loading printers. Please check QZ Tray permissions.</p>
      </div>
    `;
  }
}

// Select a printer
function selectPrinter(printerName) {
  selectedPrinter = printerName;
  
  // Update UI
  document.querySelectorAll('.printer-card').forEach(card => {
    card.classList.remove('selected');
    card.querySelector('.fa-print').classList.remove('text-success');
    card.querySelector('.fa-print').classList.add('text-muted');
    const badge = card.querySelector('.badge');
    badge.className = 'badge bg-light text-dark';
    badge.textContent = 'Click to select';
  });
  
  event.currentTarget.classList.add('selected');
  event.currentTarget.querySelector('.fa-print').classList.remove('text-muted');
  event.currentTarget.querySelector('.fa-print').classList.add('text-success');
  const badge = event.currentTarget.querySelector('.badge');
  badge.className = 'badge bg-success';
  badge.textContent = 'Selected';
  
  btnSave.disabled = false;
  btnTestPrint.disabled = false;
  
  log(`Selected printer: ${printerName}`, 'info');
}

// Save printer configuration
function savePrinterConfig() {
  if (!selectedPrinter) {
    const err = document.createElement('div');
    err.className = 'alert alert-warning alert-dismissible fade show mt-3 shadow-sm';
    err.innerHTML = `
      <span class="fas fa-exclamation-triangle me-2"></span>
      <strong>Please select a printer first.</strong> Click a printer from the list above, then click Save.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    const container = document.getElementById('printerConfigContainer') || document.querySelector('.card-body');
    if (container) {
      container.insertBefore(err, container.firstChild);
      setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(err);
        if (bsAlert) bsAlert.close();
      }, 4000);
    }
    return;
  }

  // Get terminal overrides
  const terminalWidth = terminalPaperWidth.value || paperWidth;
  const autoPrintOverride = terminalAutoPrint ? terminalAutoPrint.value : '';
  const copiesOverride = terminalCopies ? terminalCopies.value : '';

  // Save to localStorage
  localStorage.setItem('tms_pos_printer', selectedPrinter);
  localStorage.setItem('tms_pos_terminal', terminalName);
  localStorage.setItem('tms_pos_paper_width', paperWidth);
  localStorage.setItem('tms_pos_printer_type', printerType);
  localStorage.setItem('tms_pos_paper_width_override', terminalPaperWidth.value || '');

  // Save terminal overrides
  if (autoPrintOverride !== '') {
    localStorage.setItem('tms_pos_auto_print_override', autoPrintOverride);
  } else {
    localStorage.removeItem('tms_pos_auto_print_override');
  }
  if (copiesOverride !== '') {
    localStorage.setItem('tms_pos_copies_override', copiesOverride);
  } else {
    localStorage.removeItem('tms_pos_copies_override');
  }

  // Update display
  savedPrinterName.value = selectedPrinter;

  log(`Configuration saved: ${selectedPrinter} (Paper: ${terminalWidth})`, 'success');

  // Show nice inline success message
  const saveAlert = document.createElement('div');
  saveAlert.className = 'alert alert-success alert-dismissible fade show mt-3 shadow-sm';
  saveAlert.innerHTML = `
    <div class="d-flex align-items-start">
      <span class="fas fa-check-circle fa-lg me-2 mt-1"></span>
      <div>
        <strong>Configuration Saved!</strong>
        <div class="small text-muted mt-1">
          <span class="fas fa-print me-1"></span>${selectedPrinter}<br>
          <span class="fas fa-desktop me-1"></span>${terminalName}<br>
          <span class="fas fa-ruler me-1"></span>Paper: ${terminalWidth}
          ${autoPrintOverride !== '' ? '<br><span class="fas fa-sliders-h me-1"></span>Mode: ' + (autoPrintOverride === '1' ? 'Auto Print' : 'Manual') : ''}
          ${copiesOverride !== '' ? '<br><span class="fas fa-copy me-1"></span>Copies: ' + copiesOverride : ''}
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;
  const container = document.getElementById('printerConfigContainer') || document.querySelector('.card-body');
  if (container) {
    container.insertBefore(saveAlert, container.firstChild);
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(saveAlert);
      if (bsAlert) bsAlert.close();
    }, 4000);
  }
}

// Clear printer configuration
function clearPrinterConfig() {
  if (!confirm('Are you sure you want to clear the printer configuration?')) {
    return;
  }

  localStorage.removeItem('tms_pos_printer');
  localStorage.removeItem('tms_pos_terminal');
  localStorage.removeItem('tms_qz_autoconnect');
  localStorage.removeItem('tms_pos_paper_width_override');
  localStorage.removeItem('tms_pos_auto_print_override');
  localStorage.removeItem('tms_pos_copies_override');

  selectedPrinter = null;
  savedPrinterName.value = 'No printer configured';
  terminalPaperWidth.value = '';
  if (terminalAutoPrint) terminalAutoPrint.value = '';
  if (terminalCopies) terminalCopies.value = '';
  btnSave.disabled = true;
  btnTestPrint.disabled = true;

  // Reset selection UI
  document.querySelectorAll('.printer-card').forEach(card => {
    card.classList.remove('selected');
    card.querySelector('.fa-print').classList.remove('text-success');
    card.querySelector('.fa-print').classList.add('text-muted');
    const badge = card.querySelector('.badge');
    badge.className = 'badge bg-light text-dark';
    badge.textContent = 'Click to select';
  });

  log('Configuration cleared', 'warning');
}

// Test print using shared PosPrinter module
async function testPrint() {
  if (!qzConnected) {
    alert('Please connect to QZ Tray first.');
    return;
  }

  if (!selectedPrinter) {
    alert('Please select and save a printer first.');
    return;
  }

  if (!window.PosPrinter) {
    alert('PosPrinter module not loaded.');
    return;
  }

  try {
    log('Sending test print...', 'info');

    // Use real branch info for test print if available
    const branchInfo = (typeof window !== 'undefined' && window.POS_BRANCH_INFO) ? window.POS_BRANCH_INFO : null;
    const branchName = branchInfo && branchInfo.branch_name ? branchInfo.branch_name : 'Test Branch';

    // Create test transaction data
    const testTransaction = {
      id: 'TEST-' + Date.now(),
      transaction_code: 'TEST-' + Date.now(),
      branch_name: branchName,
      cashier_name: 'Test Cashier',
      payment_method: 'Cash',
      amount_tendered: 175.00,
      change_amount: 0.00,
      subtotal: 175.00,
      discount: 0.00,
      tax: 0.00,
      total: 175.00,
      items: [
        { name: 'Test Product 1', quantity: 1, price: 100.00, base_amount: 100.00, service_fee: 0.00 },
        { name: 'Test Product 2', quantity: 2, price: 50.00, base_amount: 50.00, service_fee: 0.00 },
        { name: 'Service Fee', quantity: 1, price: 25.00, base_amount: 0.00, service_fee: 25.00 }
      ]
    };

    // Reload config to ensure paper width override is applied
    window.PosPrinter.loadSavedConfig();

    // Print using shared module
    await window.PosPrinter.printReceipt(testTransaction, { skipPreview: true });

    log('Test print sent successfully', 'success');

    // Generate preview HTML
    const receiptData = window.PosPrinter.generateReceiptData(testTransaction, {});
    let previewText = receiptData.join('')
      .replace(/\x1B\[[0-9;]*[a-zA-Z]/g, '')
      .replace(/\x1B[@ABCDEFGHIJKLMNOXZ]/g, '')
      .replace(/\x1D\[[0-9;]*[a-zA-Z]/g, '')
      .replace(/\x0A/g, '<br>')
      .replace(/\x0D/g, '');

    // Add logo HTML if enabled
    let logoHtml = '';
    if (window.PosPrinter.config.logoEnabled && window.COMPANY_INFO && window.COMPANY_INFO.logo) {
      logoHtml = `<div style="text-align:center;margin-bottom:8px;"><img src="${window.COMPANY_INFO.logo}" alt="Logo" style="max-width:160px;max-height:60px;object-fit:contain;"></div>`;
    }

    receiptPreview.innerHTML = logoHtml + previewText;

    alert('Test print sent to printer successfully!');

  } catch (err) {
    log('Print error: ' + (err.message || err), 'error');
    alert('Print failed: ' + (err.message || err));
  }
}
