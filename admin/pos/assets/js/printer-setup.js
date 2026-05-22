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
    connectionStatus.innerHTML = '<span class="status-indicator status-connected"></span>Connected';
    connectionStatus.className = 'badge bg-success';
    btnConnect.disabled = true;
    btnDisconnect.disabled = false;
    btnTestPrint.disabled = !selectedPrinter;
  } else if (status === 'connecting') {
    connectionStatus.innerHTML = '<span class="status-indicator status-connecting"></span>Connecting...';
    connectionStatus.className = 'badge bg-warning text-dark';
    btnConnect.disabled = true;
  } else {
    connectionStatus.innerHTML = '<span class="status-indicator status-disconnected"></span>Not Connected';
    connectionStatus.className = 'badge bg-secondary';
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
    alert('Please select a printer first.');
    return;
  }

  // Get terminal paper width override
  const terminalWidth = terminalPaperWidth.value || paperWidth;

  // Save to localStorage
  localStorage.setItem('tms_pos_printer', selectedPrinter);
  localStorage.setItem('tms_pos_terminal', terminalName);
  localStorage.setItem('tms_pos_paper_width', paperWidth);
  localStorage.setItem('tms_pos_printer_type', printerType);
  localStorage.setItem('tms_pos_paper_width_override', terminalPaperWidth.value || '');

  // Update display
  savedPrinterName.value = selectedPrinter;

  log(`Configuration saved: ${selectedPrinter} (Paper: ${terminalWidth})`, 'success');
  alert(`Printer configuration saved successfully!\n\nPrinter: ${selectedPrinter}\nTerminal: ${terminalName}\nPaper Width: ${terminalWidth}`);
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

  selectedPrinter = null;
  savedPrinterName.value = 'No printer configured';
  terminalPaperWidth.value = '';
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

    // Create test transaction data
    const testTransaction = {
      id: 'TEST-' + Date.now(),
      transaction_code: 'TEST-' + Date.now(),
      branch_name: 'Test Branch',
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
