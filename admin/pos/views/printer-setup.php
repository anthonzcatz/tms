<?php
/**
 * POS Printer Setup View
 *
 * This view allows each POS terminal to configure its local printer
 * via QZ Tray connection. Settings are stored in browser localStorage.
 */
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(__DIR__)) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/pos/assets/css/printer-setup.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/printer-setup.css'); ?>">
<body data-paper-width="<?php echo htmlspecialchars($paperWidth); ?>" data-printer-type="<?php echo htmlspecialchars($printerType); ?>">
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }

        // Company Info for Receipts
        window.COMPANY_INFO = {
          name: '<?php echo htmlspecialchars($printerSettings['company_name'] ?? ''); ?>',
          address: '<?php echo htmlspecialchars($printerSettings['company_address'] ?? ''); ?>',
          contact: '<?php echo htmlspecialchars($printerSettings['company_contact_number'] ?? ''); ?>',
          email: '<?php echo htmlspecialchars($printerSettings['company_email'] ?? ''); ?>',
          tin: '<?php echo htmlspecialchars($printerSettings['company_tin'] ?? ''); ?>',
          logo: '<?php echo !empty($printerSettings['system_logo']) ? BASE_URL . htmlspecialchars($printerSettings['system_logo']) : ''; ?>'
        };

        // Printer Settings
        window.PRINTER_SETTINGS = {
          enabled: <?php echo ($printerSettings['receipt_printing_enabled'] ?? 1) ? 'true' : 'false'; ?>,
          paperWidth: '<?php echo $printerSettings['receipt_paper_width'] ?? '80mm'; ?>',
          showTin: <?php echo ($printerSettings['receipt_show_tin'] ?? 1) ? 'true' : 'false'; ?>,
          showServiceFee: <?php echo ($printerSettings['receipt_show_service_fee'] ?? 1) ? 'true' : 'false'; ?>,
          showBaseAmount: <?php echo ($printerSettings['receipt_show_base_amount'] ?? 1) ? 'true' : 'false'; ?>,
          showDiscount: <?php echo ($printerSettings['receipt_show_discount'] ?? 1) ? 'true' : 'false'; ?>,
          showCashier: <?php echo ($printerSettings['receipt_show_cashier'] ?? 1) ? 'true' : 'false'; ?>,
          showPaymentMethod: <?php echo ($printerSettings['receipt_show_payment_method'] ?? 1) ? 'true' : 'false'; ?>,
          showBranch: <?php echo ($printerSettings['receipt_show_branch'] ?? 1) ? 'true' : 'false'; ?>,
          logoEnabled: <?php echo ($printerSettings['receipt_logo_enabled'] ?? 0) ? 'true' : 'false'; ?>,
          qrEnabled: <?php echo ($printerSettings['receipt_qr_code_enabled'] ?? 0) ? 'true' : 'false'; ?>,
          qrFormat: '<?php echo $printerSettings['receipt_qr_format'] ?? 'TRANSACTION_ID'; ?>',
          footerText: '<?php echo htmlspecialchars($printerSettings['receipt_footer'] ?? 'Thank you for your business!'); ?>',
          customFooter: '<?php echo htmlspecialchars($printerSettings['receipt_custom_footer'] ?? ''); ?>',
          autoCut: <?php echo ($printerSettings['receipt_auto_cut'] ?? 1) ? 'true' : 'false'; ?>,
          openCashDrawer: <?php echo ($printerSettings['receipt_open_cash_drawer'] ?? 0) ? 'true' : 'false'; ?>
        };
      </script>
      <?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(__DIR__)) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(__DIR__)) . '/includes/navbar.php';
                break;
        }
        ?>

        <!-- Success/Error Messages (Legacy - for backward compatibility) -->
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="fas fa-check-circle me-2"></span><?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <span class="fas fa-exclamation-triangle me-2"></span><?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row align-items-center">
                  <div class="col d-flex align-items-center">
                    <img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">POS <span class="text-info fw-medium">Printer Setup</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/pos">POS</a></li>
                            <li class="breadcrumb-item active">Printer Setup</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-auto">
                    <a href="<?php echo BASE_URL; ?>/admin/pos" class="btn btn-outline-primary btn-sm">
                      <span class="fas fa-arrow-left me-1"></span>Back to POS
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$printingEnabled): ?>
        <div class="alert alert-warning">
          <span class="fas fa-exclamation-triangle me-2"></span>
          <strong>Receipt printing is currently disabled.</strong> Please contact your administrator to enable printing in System Settings > Printer Settings.
        </div>
        <?php endif; ?>

        <div class="printer-setup-container">
          <div class="row g-4">
            <!-- Connection Status Card -->
            <div class="col-lg-6">
              <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><span class="fas fa-plug me-2"></span>QZ Tray Connection</h5>
              <span id="connectionStatus" class="badge bg-secondary d-flex align-items-center">
                <span class="status-indicator status-disconnected me-2"></span>
                <span class="small">Not Connected</span>
              </span>
            </div>
            <div class="card-body">
              <div class="alert alert-info fs-10 mb-3">
                <h6 class="fw-bold"><span class="fas fa-info-circle me-2"></span>Setup Instructions:</h6>
                <ol class="mb-0 ps-3">
                  <li>Download and install <a href="https://qz.io/download/" target="_blank" class="fw-bold">QZ Tray</a> on this computer</li>
                  <li>Run QZ Tray (check system tray for icon)</li>
                  <li>Click "Connect to QZ Tray" below</li>
                  <li>When prompted, click "Allow" and check "Remember this decision"</li>
                  <li>Select your printer from the list</li>
                  <li>Click "Save Configuration" and test print</li>
                </ol>
              </div>
              
              <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-3">
                <button type="button" class="btn btn-primary" id="btnConnect" onclick="connectToQZ()">
                  <span class="fas fa-plug me-2"></span>Connect to QZ Tray
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnDisconnect" onclick="disconnectFromQZ()" disabled>
                  <span class="fas fa-unlink me-2"></span>Disconnect
                </button>
                <a href="https://qz.io/download/" target="_blank" class="btn btn-outline-info">
                  <span class="fas fa-download me-2"></span>Download QZ Tray
                </a>
              </div>

              <!-- Connection Log -->
              <div class="mt-3">
                <h6 class="fw-bold mb-2">Connection Log:</h6>
                <div id="connectionLog" class="connection-log">
                  <div class="log-entry log-info">Ready to connect...</div>
                </div>
              </div>
            </div>
            </div>
            </div>

            <!-- Printer Selection Card -->
            <div class="col-lg-6">
              <div class="card h-100">
            <div class="card-header">
              <h5 class="mb-0"><span class="fas fa-print me-2"></span>Printer Selection</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Available Printers</label>
                <div id="printerList" class="row g-2">
                  <div class="col-12 text-center text-muted py-4">
                    <span class="fas fa-plug-circle-xmark fa-2x mb-2"></span>
                    <p>Connect to QZ Tray first to see available printers</p>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Currently Saved Printer</label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><span class="fas fa-print"></span></span>
                  <input type="text" class="form-control" id="savedPrinterName" readonly value="No printer configured">
                </div>
                <small class="text-muted">This printer will be used for receipt printing on this terminal.</small>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Terminal Paper Width (Override)</label>
                <select class="form-select" id="terminalPaperWidth">
                  <?php
                  $globalWidthInfo = [
                    '58mm' => '58mm (2 inches) - 30 characters',
                    '76mm' => '76mm (3 inches) - 40 characters',
                    '80mm' => '80mm (3 inches) - 46 characters',
                    '100mm' => '100mm (4 inches) - 58 characters',
                    '112mm' => '112mm (4.4 inches) - 66 characters'
                  ];
                  $globalDisplay = $globalWidthInfo[$paperWidth] ?? $paperWidth;
                  ?>
                  <option value="">Use Global Setting (<?php echo htmlspecialchars($globalDisplay); ?>)</option>
                  <option value="58mm">58mm (2 inches) - 30 characters</option>
                  <option value="76mm">76mm (3 inches) - 40 characters</option>
                  <option value="80mm">80mm (3 inches) - 46 characters</option>
                  <option value="100mm">100mm (4 inches) - 58 characters</option>
                  <option value="112mm">112mm (4.4 inches) - 66 characters</option>
                </select>
                <small class="text-muted">Override the global paper width for this terminal only. Leave empty to use global setting from System Settings.</small>
              </div>

              <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <button type="button" class="btn btn-success" id="btnSave" onclick="savePrinterConfig()" disabled>
                  <span class="fas fa-save me-2"></span>Save Configuration
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="clearPrinterConfig()">
                  <span class="fas fa-trash me-2"></span>Clear Settings
                </button>
              </div>
            </div>
            </div>
            </div>
          </div>

          <!-- Test Print Card -->
          <div class="col-12">
            <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><span class="fas fa-vial me-2"></span>Test Print</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <button type="button" class="btn btn-warning" id="btnTestPrint" onclick="testPrint()" disabled>
                  <span class="fas fa-print me-2"></span>Print Test Receipt
                </button>
              </div>

              <!-- Receipt Preview -->
              <div class="mb-2">
                <h6 class="fw-bold">Receipt Preview:</h6>
              </div>
              <div id="receiptPreview" class="receipt-preview receipt-80mm">
Waiting for test print...
              </div>
            </div>
            </div>
          </div>

          <!-- System Info Card -->
          <div class="col-12">
            <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><span class="fas fa-info-circle me-2"></span>System Information</h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <table class="table table-sm table-borderless">
                    <tr>
                      <td class="fw-semibold">Terminal:</td>
                      <td id="terminalName">Detecting...</td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">Browser:</td>
                      <td id="browserInfo">Detecting...</td>
                    </tr>
                  </table>
                </div>
                <div class="col-md-6">
                  <table class="table table-sm table-borderless">
                    <tr>
                      <td class="fw-semibold">Global Paper Width:</td>
                      <td><?php echo htmlspecialchars($paperWidth); ?></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">Printer Type:</td>
                      <td><?php echo htmlspecialchars($printerType); ?></td>
                    </tr>
                  </table>
                </div>
              </div>
            </div>
            </div>
          </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- QZ Tray Library -->
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/qz-tray.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.9.0/jsrsasign-all-min.js"></script>
  <script>
    // QZ Tray signing credentials (served via PHP to avoid public file exposure)
    window.QZ_CERT = <?php echo json_encode(
      file_get_contents(dirname(__FILE__) . '/digital-certificate.txt')
    ); ?>;
    window.QZ_PRIVATE_KEY = <?php echo json_encode(
      file_get_contents(dirname(__FILE__) . '/private-key.pem')
    ); ?>;
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/pos-printer.js"></script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/printer-setup.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/printer-setup.js'); ?>"></script>
  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  </main>
</body>
</html>
