<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/admin/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/system-settings/assets/css/system-settings.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/system-settings.css'); ?>">
<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script>
      <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-double-top.php'; ?>
      <?php endif; ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar.php'; break;
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

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
          <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
          <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
              <div class="col-lg-auto d-flex align-items-center">
                <img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">System <span class="text-info fw-medium">Settings</span></h4>
                  <h6 class="mb-1 text-primary">  <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a >Home</a></li>
                    <li class="breadcrumb-item active">System Settings</li>
                  </ol>
                 </nav>
                 </h6>
                </div>
              </div>
            </div>
          </div>
        </div>
          </div>
        </div>

        <!-- Success/Error Messages -->
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

        <!-- Maintenance Mode Notification -->
        <?php if ($settings['maintenance_mode'] ?? 0): ?>
          <?php 
            $maintenanceStart = $settings['maintenance_start'] ?? null;
            $maintenanceEnd = $settings['maintenance_end'] ?? null;
            $now = date('Y-m-d H:i:s');
            $inMaintenanceWindow = true;
            
            if ($maintenanceStart && $maintenanceStart > $now) {
                $inMaintenanceWindow = false;
            }
            if ($maintenanceEnd && $maintenanceEnd < $now) {
                $inMaintenanceWindow = false;
            }
            
            if ($inMaintenanceWindow): 
          ?>
          <div class="alert alert-warning fade show" role="alert" id="maintenanceNotification">
            <div class="d-flex align-items-center">
              <span class="fas fa-tools me-2 fs-5"></span>
              <div class="flex-grow-1">
                <strong>Maintenance Mode is Active</strong>
                <?php if ($maintenanceEnd): ?>
                <div class="mt-2">
                  <small class="text-muted">Time Remaining: </small>
                  <span id="maintenanceCountdown" class="fw-bold">Calculating...</span>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if ($maintenanceEnd): ?>
          <script>
            const maintenanceEndTime = new Date('<?php echo date('Y-m-d H:i:s', strtotime($maintenanceEnd)); ?>').getTime();
            
            function updateMaintenanceCountdown() {
                const now = new Date().getTime();
                const distance = maintenanceEndTime - now;
                
                if (distance < 0) {
                    document.getElementById('maintenanceCountdown').textContent = 'Maintenance has ended!';
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                let countdown = '';
                if (days > 0) countdown += days + 'd ';
                if (hours > 0) countdown += hours + 'h ';
                countdown += minutes + 'm ' + seconds + 's';
                
                document.getElementById('maintenanceCountdown').textContent = countdown;
            }
            
            updateMaintenanceCountdown();
            setInterval(updateMaintenanceCountdown, 1000);
          </script>
          <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="">
        <div class="card overflow-hidden">
          <div class="card-header p-0 bg-body-tertiary scrollbar-overlay">
            <ul class="nav nav-tabs border-0 tab-system-settings flex-nowrap" id="settings-tab" role="tablist">
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1 active" id="company-tab" data-bs-toggle="tab" href="#company" role="tab" aria-controls="company" aria-selected="true">
                  <span class="fas fa-building icon text-600"></span>
                  <h6 class="mb-0 text-600">Company Info</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="branding-tab" data-bs-toggle="tab" href="#branding" role="tab" aria-controls="branding" aria-selected="false">
                  <span class="fas fa-paint-brush icon text-600"></span>
                  <h6 class="mb-0 text-600">Branding</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="receipt-tab" data-bs-toggle="tab" href="#receipt" role="tab" aria-controls="receipt" aria-selected="false">
                  <span class="fas fa-receipt icon text-600"></span>
                  <h6 class="mb-0 text-600">Receipt & Report</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="printer-tab" data-bs-toggle="tab" href="#printer" role="tab" aria-controls="printer" aria-selected="false">
                  <span class="fas fa-print icon text-600"></span>
                  <h6 class="mb-0 text-600">Printer Settings</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="system-tab" data-bs-toggle="tab" href="#system" role="tab" aria-controls="system" aria-selected="false">
                  <span class="fas fa-cog icon text-600"></span>
                  <h6 class="mb-0 text-600">System Config</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="maintenance-tab" data-bs-toggle="tab" href="#maintenance" role="tab" aria-controls="maintenance" aria-selected="false">
                  <span class="fas fa-tools icon text-600"></span>
                  <h6 class="mb-0 text-600">Maintenance</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="cancellation-tab" data-bs-toggle="tab" href="#cancellation" role="tab" aria-controls="cancellation" aria-selected="false">
                  <span class="fas fa-ban icon text-600"></span>
                  <h6 class="mb-0 text-600">Cancellation</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="pos-tab" data-bs-toggle="tab" href="#pos" role="tab" aria-controls="pos" aria-selected="false">
                  <span class="fas fa-cash-register icon text-600"></span>
                  <h6 class="mb-0 text-600">POS Settings</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="bank-tab" data-bs-toggle="tab" href="#bank" role="tab" aria-controls="bank" aria-selected="false">
                  <span class="fas fa-university icon text-600"></span>
                  <h6 class="mb-0 text-600">Bank Transactions</h6>
                </a>
              </li>
              <li class="nav-item text-nowrap" role="presentation">
                <a class="nav-link mb-0 d-flex align-items-center gap-2 py-3 px-x1" id="security-tab" data-bs-toggle="tab" href="#security" role="tab" aria-controls="security" aria-selected="false">
                  <span class="fas fa-shield-alt icon text-600"></span>
                  <h6 class="mb-0 text-600">Security</h6>
                </a>
              </li>
            </ul>
          </div>
          <div class="card-body p-0">
            <div class="tab-content">
              <!-- Company Information Tab -->
              <div class="tab-pane active" id="company" role="tabpanel" aria-labelledby="company-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-building me-2"></span>Company Information</h5>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Company Name</label>
                        <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" placeholder="Enter company name">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Company Abbreviation</label>
                        <input type="text" class="form-control" name="company_abbreviation" value="<?php echo htmlspecialchars($settings['company_abbreviation'] ?? ''); ?>" placeholder="e.g., TMS">
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Company Address</label>
                        <textarea class="form-control" name="company_address" rows="2" placeholder="Enter company address"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Number</label>
                        <input type="text" class="form-control" name="company_contact_number" value="<?php echo htmlspecialchars($settings['company_contact_number'] ?? ''); ?>" placeholder="e.g., +63 912 345 6789">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Tax Identification Number (TIN)</label>
                        <input type="text" class="form-control" name="company_tin" value="<?php echo htmlspecialchars($settings['company_tin'] ?? ''); ?>" placeholder="e.g., 123-456-789-000">
                        <small class="text-muted">TIN to display on official receipts</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control" name="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" placeholder="e.g., info@company.com">
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Company Tagline</label>
                        <input type="text" class="form-control" name="company_tagline" value="<?php echo htmlspecialchars($settings['company_tagline'] ?? ''); ?>" placeholder="Enter company tagline">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Branding Tab -->
              <div class="tab-pane" id="branding" role="tabpanel" aria-labelledby="branding-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-paint-brush me-2"></span>System Branding</h5>
                    <div class="row g-4">

                      <!-- Row 1: System Name + Developer Name -->
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">System Name</label>
                        <input type="text" class="form-control" name="system_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? 'Falcon'); ?>" placeholder="e.g., Falcon">
                        <small class="text-muted">This will replace "Falcon" in the navbar and other places</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Developer Name</label>
                        <input type="text" class="form-control" name="developer_name" value="<?php echo htmlspecialchars($settings['developer_name'] ?? ''); ?>" placeholder="e.g., Your Company">
                      </div>

                      <!-- Row 2: System Logo (full width) -->
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">System Logo</label>
                        <input type="file" class="form-control d-none" id="systemLogoUpload" accept="image/png,image/jpeg,image/jpg,image/webp" onchange="previewLogo(this)">
                        <input type="hidden" name="system_logo" id="systemLogoUrl" value="<?php echo htmlspecialchars($settings['system_logo'] ?? ''); ?>">
                        <div class="border rounded-3 p-3 bg-light" style="border-style: dashed !important;">
                          <div class="row align-items-center g-3">
                            <div class="col-md-auto">
                              <!-- Logo preview box -->
                              <div id="logoUploadArea" onclick="document.getElementById('systemLogoUpload').click()" style="width: 250px; height: 150px; border: 2px dashed #0d6efd; border-radius: 10px; cursor: pointer; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <div id="logoUploadPlaceholder" class="text-center <?php echo !empty($settings['system_logo']) ? 'd-none' : ''; ?>">
                                  <span class="fas fa-image text-primary fs-4"></span>
                                  <p class="mb-0 text-primary small fw-bold mt-1">Click to upload</p>
                                </div>
                                <div id="logoPreviewContainer" class="<?php echo !empty($settings['system_logo']) ? '' : 'd-none'; ?> w-100 h-100 d-flex align-items-center justify-content-center">
                                  <img id="logoPreview" src="<?php echo !empty($settings['system_logo']) ? (strpos($settings['system_logo'], 'http') === 0 ? htmlspecialchars($settings['system_logo']) : BASE_URL . htmlspecialchars($settings['system_logo'])) : ''; ?>" alt="Logo Preview" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                              </div>
                            </div>
                            <div class="col">
                              <p class="fw-semibold mb-1">Upload your system logo</p>
                              <p class="text-muted small mb-2">Recommended: 500x300px &bull; PNG, JPG, WebP &bull; Max 10MB</p>
                              <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('systemLogoUpload').click()">
                                  <span class="fas fa-upload me-1"></span> Upload
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary <?php echo empty($settings['system_logo']) ? 'd-none' : ''; ?>" id="btnCropLogo" onclick="openLogoCropper()">
                                  <span class="fas fa-crop-alt me-1"></span> Crop
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger <?php echo empty($settings['system_logo']) ? 'd-none' : ''; ?>" id="btnRemoveLogo" onclick="removeLogo()">
                                  <span class="fas fa-trash-alt me-1"></span> Remove
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Row 3: Developer Details + Footer Copyright -->
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Developer Details</label>
                        <textarea class="form-control" name="developer_details" rows="3" placeholder="e.g., Designed and developed by Your Development Team"><?php echo htmlspecialchars($settings['developer_details'] ?? ''); ?></textarea>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Footer Copyright</label>
                        <textarea class="form-control" name="footer_copyright" rows="3" placeholder="e.g., © 2024 Your Company. All rights reserved."><?php echo htmlspecialchars($settings['footer_copyright'] ?? ''); ?></textarea>
                      </div>

                    </div>
                  </div>
                </div>
              </div>

              <!-- Receipt & Report Settings Tab -->
              <div class="tab-pane" id="receipt" role="tabpanel" aria-labelledby="receipt-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-receipt me-2"></span>Receipt & Report Settings</h5>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Receipt Footer</label>
                        <textarea class="form-control" name="receipt_footer" rows="3" placeholder="Footer text for receipts"><?php echo htmlspecialchars($settings['receipt_footer'] ?? ''); ?></textarea>
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Report Footer</label>
                        <textarea class="form-control" name="report_footer" rows="3" placeholder="Footer text for reports"><?php echo htmlspecialchars($settings['report_footer'] ?? ''); ?></textarea>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Printer Settings Tab -->
              <div class="tab-pane" id="printer" role="tabpanel" aria-labelledby="printer-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-print me-2"></span>POS Printer Settings</h5>
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-info-circle me-2"></span>
                      <strong>QZ Tray Integration:</strong> Configure receipt printing for POS terminals. Each terminal must have QZ Tray installed and configured separately via the <a href="<?php echo BASE_URL; ?>/admin/pos/printer-setup" target="_blank">Printer Setup Page</a>.
                    </div>
                    <div class="row g-3">
                      <!-- Enable/Disable Receipt Printing -->
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_printing_enabled" id="receiptPrintingEnabled" <?php echo ($settings['receipt_printing_enabled'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptPrintingEnabled">Enable Receipt Printing</label>
                        </div>
                        <small class="text-muted">When enabled, receipts can be printed after transactions. When disabled, no printing functionality will be available.</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Paper Width -->
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Receipt Paper Width</label>
                        <select class="form-select" name="receipt_paper_width" id="receiptPaperWidth">
                          <option value="58mm" <?php echo ($settings['receipt_paper_width'] ?? '80mm') === '58mm' ? 'selected' : ''; ?>>58mm (2 inches) - 30 characters</option>
                          <option value="76mm" <?php echo ($settings['receipt_paper_width'] ?? '80mm') === '76mm' ? 'selected' : ''; ?>>76mm (3 inches) - 40 characters</option>
                          <option value="80mm" <?php echo ($settings['receipt_paper_width'] ?? '80mm') === '80mm' ? 'selected' : ''; ?>>80mm (3 inches) - 46 characters</option>
                          <option value="100mm" <?php echo ($settings['receipt_paper_width'] ?? '80mm') === '100mm' ? 'selected' : ''; ?>>100mm (4 inches) - 58 characters</option>
                          <option value="112mm" <?php echo ($settings['receipt_paper_width'] ?? '80mm') === '112mm' ? 'selected' : ''; ?>>112mm (4.4 inches) - 66 characters</option>
                        </select>
                        <small class="text-muted">Select your thermal printer paper width. This affects receipt formatting. Each terminal can override this setting.</small>
                      </div>
                      <!-- Printer Type -->
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Printer Type</label>
                        <select class="form-select" name="printer_type" id="printerType">
                          <option value="THERMAL" <?php echo ($settings['printer_type'] ?? 'THERMAL') === 'THERMAL' ? 'selected' : ''; ?>>Thermal Receipt Printer</option>
                          <option value="DOT_MATRIX" <?php echo ($settings['printer_type'] ?? 'THERMAL') === 'DOT_MATRIX' ? 'selected' : ''; ?>>Dot Matrix Printer</option>
                          <option value="INKJET" <?php echo ($settings['printer_type'] ?? 'THERMAL') === 'INKJET' ? 'selected' : ''; ?>>Inkjet/Laser Printer</option>
                        </select>
                        <small class="text-muted">Type of printer connected to POS terminals.</small>
                      </div>
                      <!-- Auto Print -->
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_auto_print" id="receiptAutoPrint" <?php echo ($settings['receipt_auto_print'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptAutoPrint">Auto-Print After Transaction</label>
                        </div>
                        <small class="text-muted">Automatically print receipt when transaction is completed.</small>
                      </div>
                      <!-- Show Preview -->
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_preview" id="receiptShowPreview" <?php echo ($settings['receipt_show_preview'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowPreview">Show Print Preview</label>
                        </div>
                        <small class="text-muted">Show receipt preview dialog before printing.</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Receipt Copies -->
                      <div class="col-md-4">
                        <label class="form-label fw-semibold">Number of Copies</label>
                        <input type="number" class="form-control" name="receipt_copies" value="<?php echo intval($settings['receipt_copies'] ?? 1); ?>" min="1" max="3" step="1">
                        <small class="text-muted">Copies per print (1-3).</small>
                      </div>
                      <!-- Customer Copy -->
                      <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                          <input class="form-check-input" type="checkbox" name="receipt_customer_copy" id="receiptCustomerCopy" <?php echo ($settings['receipt_customer_copy'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptCustomerCopy">Print Customer Copy</label>
                        </div>
                      </div>
                      <!-- Merchant Copy -->
                      <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                          <input class="form-check-input" type="checkbox" name="receipt_merchant_copy" id="receiptMerchantCopy" <?php echo ($settings['receipt_merchant_copy'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptMerchantCopy">Print Merchant Copy</label>
                        </div>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Auto Cut -->
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_auto_cut" id="receiptAutoCut" <?php echo ($settings['receipt_auto_cut'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptAutoCut">Auto-Cut Paper</label>
                        </div>
                        <small class="text-muted">Automatically cut paper after printing (thermal printers only).</small>
                      </div>
                      <!-- Open Cash Drawer -->
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_open_cash_drawer" id="receiptOpenCashDrawer" <?php echo ($settings['receipt_open_cash_drawer'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptOpenCashDrawer">Open Cash Drawer</label>
                        </div>
                        <small class="text-muted">Open cash drawer after printing (requires connected drawer).</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Show Cashier -->
                      <div class="col-md-4">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_cashier" id="receiptShowCashier" <?php echo ($settings['receipt_show_cashier'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowCashier">Show Cashier Name</label>
                        </div>
                      </div>
                      <!-- Show Payment Method -->
                      <div class="col-md-4">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_payment_method" id="receiptShowPaymentMethod" <?php echo ($settings['receipt_show_payment_method'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowPaymentMethod">Show Payment Method</label>
                        </div>
                      </div>
                      <!-- Show Branch -->
                      <div class="col-md-4">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_branch" id="receiptShowBranch" <?php echo ($settings['receipt_show_branch'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowBranch">Show Branch Name</label>
                        </div>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- QR Code -->
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_qr_code_enabled" id="receiptQrCodeEnabled" <?php echo ($settings['receipt_qr_code_enabled'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptQrCodeEnabled">Print QR Code</label>
                        </div>
                        <small class="text-muted">Include QR code on receipt for verification or online access.</small>
                      </div>
                      <!-- QR Format -->
                      <div class="col-md-6" id="qrFormatContainer" style="<?php echo ($settings['receipt_qr_code_enabled'] ?? 0) ? '' : 'display:none;'; ?>">
                        <label class="form-label fw-semibold">QR Code Content</label>
                        <select class="form-select" name="receipt_qr_format" id="receiptQrFormat">
                          <option value="TRANSACTION_ID" <?php echo ($settings['receipt_qr_format'] ?? 'TRANSACTION_ID') === 'TRANSACTION_ID' ? 'selected' : ''; ?>>Transaction ID</option>
                          <option value="URL" <?php echo ($settings['receipt_qr_format'] ?? 'TRANSACTION_ID') === 'URL' ? 'selected' : ''; ?>>Verification URL</option>
                        </select>
                      </div>
                      <!-- Logo -->
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_logo_enabled" id="receiptLogoEnabled" <?php echo ($settings['receipt_logo_enabled'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptLogoEnabled">Print Logo on Receipt</label>
                        </div>
                        <small class="text-muted">Include company logo at top of receipt (requires logo upload in Branding tab).</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Receipt Display Configuration -->
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">Receipt Display Configuration</h6>
                        <p class="text-muted fs-10 mb-3">Configure what information appears on the receipt. These settings can be overridden at each terminal.</p>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_tin" id="receiptShowTin" <?php echo ($settings['receipt_show_tin'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowTin">Show TIN on Receipt</label>
                        </div>
                        <small class="text-muted">Display company TIN in receipt header</small>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_service_fee" id="receiptShowServiceFee" <?php echo ($settings['receipt_show_service_fee'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowServiceFee">Show Service Fee Separately</label>
                        </div>
                        <small class="text-muted">Display service fee as separate line item vs included in total</small>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_base_amount" id="receiptShowBaseAmount" <?php echo ($settings['receipt_show_base_amount'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowBaseAmount">Show Base Amount</label>
                        </div>
                        <small class="text-muted">Display base amount (cost) before service fee</small>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="receipt_show_discount" id="receiptShowDiscount" <?php echo ($settings['receipt_show_discount'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="receiptShowDiscount">Show Discount Details</label>
                        </div>
                        <small class="text-muted">Display discount amount on receipt</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <!-- Receipt Footer Configuration -->
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Custom Receipt Footer</label>
                        <textarea class="form-control" name="receipt_custom_footer" rows="2" placeholder="Custom footer text (optional)"><?php echo htmlspecialchars($settings['receipt_custom_footer'] ?? ''); ?></textarea>
                        <small class="text-muted">Override the default "Thank you" footer. Leave empty to use default.</small>
                      </div>
                    </div>
                    <script>
                      document.getElementById('receiptQrCodeEnabled').addEventListener('change', function() {
                        document.getElementById('qrFormatContainer').style.display = this.checked ? 'block' : 'none';
                      });
                    </script>
                  </div>
                </div>
              </div>

              <!-- System Configuration Tab -->
              <div class="tab-pane" id="system" role="tabpanel" aria-labelledby="system-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-cog me-2"></span>System Configuration</h5>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">System Timezone</label>
                        <select class="form-select" name="system_timezone">
                          <?php foreach ($timezones as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($settings['system_timezone'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">System Currency</label>
                        <input type="text" class="form-control" name="system_currency" value="<?php echo htmlspecialchars($settings['system_currency'] ?? 'PHP'); ?>" placeholder="e.g., PHP">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Maintenance Mode Tab -->
              <div class="tab-pane" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-tools me-2"></span>Maintenance Mode</h5>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="maintenanceMode">Enable Maintenance Mode</label>
                        </div>
                        <small class="text-muted">When enabled, the system will be in maintenance mode for non-admin users.</small>
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Maintenance Message</label>
                        <textarea class="form-control" name="maintenance_message" rows="3" placeholder="Message to display during maintenance"><?php echo htmlspecialchars($settings['maintenance_message'] ?? ''); ?></textarea>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Maintenance Start</label>
                        <input type="datetime-local" class="form-control" name="maintenance_start" value="<?php echo $settings['maintenance_start'] ? date('Y-m-d\TH:i', strtotime($settings['maintenance_start'])) : ''; ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Maintenance End</label>
                        <input type="datetime-local" class="form-control" name="maintenance_end" value="<?php echo $settings['maintenance_end'] ? date('Y-m-d\TH:i', strtotime($settings['maintenance_end'])) : ''; ?>">
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="allow_admin_during_maintenance" id="allowAdminDuringMaintenance" <?php echo ($settings['allow_admin_during_maintenance'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="allowAdminDuringMaintenance">Allow Admin Access During Maintenance</label>
                        </div>
                        <small class="text-muted">When enabled, admin users can still access the system during maintenance.</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cancellation Settings Tab -->
              <div class="tab-pane" id="cancellation" role="tabpanel" aria-labelledby="cancellation-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-ban me-2"></span>Cancellation Settings</h5>
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-info-circle me-2"></span>
                      <strong>Refund Policy:</strong> Ticket cancellations will refund the amount from the cashier's cash drawer directly to the passenger.
                    </div>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="cancellation_requires_confirmation" id="cancellationRequiresConfirmation" <?php echo ($settings['cancellation_requires_confirmation'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="cancellationRequiresConfirmation">Require Confirmation for Cancellations</label>
                        </div>
                        <small class="text-muted">When enabled, ticket cancellations require approval before processing. When disabled, cancellations are auto-approved.</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Refund Processing Days</label>
                        <input type="number" class="form-control" name="cancellation_refund_processing_days" value="<?php echo intval($settings['cancellation_refund_processing_days'] ?? 0); ?>" max="30" step="1">
                        <small class="text-muted">Number of days to process refunds. Set to 0 for immediate processing.</small>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="cancellation_allow_partial" id="cancellationAllowPartial" <?php echo ($settings['cancellation_allow_partial'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="cancellationAllowPartial">Allow Partial Cancellation</label>
                        </div>
                        <small class="text-muted">When enabled, partial ticket cancellations are allowed.</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- POS Settings Tab -->
              <div class="tab-pane" id="pos" role="tabpanel" aria-labelledby="pos-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-cash-register me-2"></span>POS Settings</h5>
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-info-circle me-2"></span>
                      <strong>Cashier Session Management:</strong> Control who can open and close cashier sessions.
                    </div>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">Cashier Permissions</h6>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="pos_cashier_can_open_session" id="posCashierCanOpen" <?php echo ($settings['pos_cashier_can_open_session'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="posCashierCanOpen">Cashier Can Open Own Session</label>
                        </div>
                        <small class="text-muted">When enabled, cashiers can open their own POS sessions. When disabled, only managers can open sessions for them.</small>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="pos_cashier_can_close_session" id="posCashierCanClose" <?php echo ($settings['pos_cashier_can_close_session'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="posCashierCanClose">Cashier Can Close Own Session</label>
                        </div>
                        <small class="text-muted">When enabled, cashiers can close their own sessions. When disabled, only managers can close sessions for them.</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">Manager Permissions</h6>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="pos_manager_can_open_for_cashier" id="posManagerCanOpen" <?php echo ($settings['pos_manager_can_open_for_cashier'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="posManagerCanOpen">Manager Can Open Session for Cashier</label>
                        </div>
                        <small class="text-muted">When enabled, managers can open POS sessions on behalf of cashiers.</small>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="pos_manager_can_close_for_cashier" id="posManagerCanClose" <?php echo ($settings['pos_manager_can_close_for_cashier'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="posManagerCanClose">Manager Can Close Session for Cashier</label>
                        </div>
                        <small class="text-muted">When enabled, managers can close POS sessions on behalf of cashiers.</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bank Transactions Tab -->
              <div class="tab-pane" id="bank" role="tabpanel" aria-labelledby="bank-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-university me-2"></span>Bank Transaction Settings</h5>
                    <div class="alert alert-info fs-10 mb-4">
                      <span class="fas fa-info-circle me-2"></span>
                      <strong>Confirmation Workflow:</strong> Configure which bank movements require manager confirmation before updating account balances.
                    </div>
                    <div class="row g-3">
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">POS Payments</h6>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="bank_pos_payments_require_confirmation" id="bankPosPaymentsConfirm" <?php echo ($settings['bank_pos_payments_require_confirmation'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="bankPosPaymentsConfirm">POS Bank/E-Wallet Payments Require Confirmation</label>
                        </div>
                        <small class="text-muted">When enabled, bank transfer and e-wallet payments from POS require manager confirmation before bank account balance is updated.</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">Charge Collections</h6>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="bank_charge_payments_require_confirmation" id="bankChargePaymentsConfirm" <?php echo ($settings['bank_charge_payments_require_confirmation'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="bankChargePaymentsConfirm">Charge Collections Require Confirmation</label>
                        </div>
                        <small class="text-muted">When enabled, bank/e-wallet payments for customer charges (utang) require manager confirmation before bank account balance is updated.</small>
                      </div>
                      <div class="col-md-12"><hr class="my-2"></div>
                      <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-3">Cash Deposits</h6>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="bank_deposits_require_confirmation" id="bankDepositsConfirm" <?php echo ($settings['bank_deposits_require_confirmation'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="bankDepositsConfirm">Cash Deposits Require Confirmation</label>
                        </div>
                        <small class="text-muted">When enabled, cash deposits from cashier shifts require manager confirmation before bank account balance is updated. "Deposit Now" option bypasses this.</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Security Tab -->
              <div class="tab-pane" id="security" role="tabpanel" aria-labelledby="security-tab">
                <div class="card border-0">
                  <div class="card-body">
                    <h5 class="card-title mb-4"><span class="fas fa-shield-alt me-2"></span>Security Settings</h5>

                    <h6 class="fw-bold text-primary mb-3">Session Management</h6>
                    <div class="row g-3 mb-4">
                      <div class="col-md-4">
                        <label class="form-label fw-semibold">Session Lifetime <span class="text-muted fw-normal">(minutes)</span></label>
                        <input type="number" class="form-control" name="session_lifetime_minutes"
                               value="<?php echo intval($settings['session_lifetime_minutes'] ?? 120); ?>"
                               min="5" max="1440" step="5">
                        <small class="text-muted">How long a session stays alive. Default 120 min (2 hrs). Range 5–1440.</small>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label fw-semibold">Session Warning Timeout <span class="text-muted fw-normal">(minutes)</span></label>
                        <input type="number" class="form-control" name="session_warning_timeout"
                               value="<?php echo intval($settings['session_warning_timeout'] ?? 15); ?>"
                               min="1" max="60" step="1">
                        <small class="text-muted">Minutes before expiry to show idle warning. Default: 15.</small>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label fw-semibold">Max Concurrent Sessions per User</label>
                        <input type="number" class="form-control" name="max_concurrent_sessions"
                               value="<?php echo intval($settings['max_concurrent_sessions'] ?? 1); ?>"
                               min="1" max="10" step="1">
                        <small class="text-muted">1 = single-session. New logins terminate the oldest. Max 10.</small>
                      </div>
                    </div>

                    <hr class="my-3">

                    <h6 class="fw-bold text-primary mb-3">Device Management</h6>
                    <div class="row g-3 mb-4">
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="device_approval_required"
                                 id="deviceApprovalRequired"
                                 <?php echo ($settings['device_approval_required'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="deviceApprovalRequired">
                            Require Device Approval Before Login
                          </label>
                        </div>
                        <small class="text-muted">When enabled, any new device/IP must be manually approved here before it can log in. Already-approved devices are unaffected.</small>
                      </div>
                    </div>

                    <!-- Device Manager -->
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                      <h6 class="fw-bold mb-0"><span class="fas fa-laptop me-1"></span>Registered Devices</h6>
                      <div class="d-flex gap-2 flex-wrap">
                        <select class="form-select form-select-sm" id="deviceStatusFilter" style="width:140px">
                          <option value="">All Status</option>
                          <option value="approved">Approved</option>
                          <option value="pending">Pending</option>
                          <option value="blocked">Blocked</option>
                        </select>
                        <input type="text" class="form-control form-control-sm" id="deviceSearch"
                               placeholder="Search IP / name / code..." style="width:210px">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadDevices()">
                          <span class="fas fa-sync-alt"></span>
                        </button>
                      </div>
                    </div>
                    <div class="table-responsive mb-4">
                      <table class="table table-sm table-hover fs-10 mb-0 align-middle" id="devicesTable">
                        <thead class="table-light">
                          <tr>
                            <th>Code</th>
                            <th>Name / Type</th>
                            <th>IP Address</th>
                            <th>Location</th>
                            <th>Last User</th>
                            <th>Last Used</th>
                            <th class="text-center">Active Sessions</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="devicesTableBody">
                          <tr><td colspan="9" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading devices...</td></tr>
                        </tbody>
                      </table>
                    </div>

                    <hr class="my-3">

                    <!-- Session Manager -->
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                      <h6 class="fw-bold mb-0"><span class="fas fa-sign-in-alt me-1"></span>Active Sessions</h6>
                      <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" id="activeOnlyFilter" checked>
                          <label class="form-check-label small" for="activeOnlyFilter">Active only</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadSessions()">
                          <span class="fas fa-sync-alt"></span>
                        </button>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover fs-10 mb-0 align-middle">
                        <thead class="table-light">
                          <tr>
                            <th>User</th>
                            <th>Device</th>
                            <th>IP</th>
                            <th>Logged In</th>
                            <th>Last Seen</th>
                            <th>Expires</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="sessionsTableBody">
                          <tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading sessions...</td></tr>
                        </tbody>
                      </table>
                    </div>

                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="card mt-3">
          <div class="card-body">
            <div class="d-flex justify-content-end gap-2">
              <a href="<?php echo BASE_URL; ?>/admin/dashboard" class="btn btn-outline-secondary">
                <span class="fas fa-times me-1"></span>Cancel
              </a>
              <button type="submit" class="btn btn-primary">
                <span class="fas fa-save me-1"></span>Save Settings
              </button>
            </div>
          </div>
        </div>
      </form>

      </div>
    </div>
  </main>

  <!-- Device Action Modal -->
  <div class="modal fade" id="deviceActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deviceActionModalTitle">Confirm Action</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="deviceActionModalBody">
          <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn" id="deviceActionConfirmBtn">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Session Termination Modal -->
  <div class="modal fade" id="sessionTerminateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Terminate Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to force-terminate this session? The user will be immediately logged out and may lose unsaved work.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="sessionTerminateConfirmBtn">Terminate Session</button>
        </div>
      </div>
    </div>
  </div>

  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/system-settings/assets/js/system-settings.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/system-settings.js'); ?>"></script>
<script>
/* =========================================================
   Security Tab — Device & Session Manager
   ========================================================= */
const BASE_URL = '<?php echo BASE_URL; ?>';
const CSRF_TOKEN = '<?php echo htmlspecialchars(\SecurityHelper::generateCSRFToken()); ?>';

function fmtDate(str) {
    if (!str) return '<span class="text-muted">—</span>';
    // Display as-is from database (Asia/Manila)
    const date = new Date(str);
    return date.toLocaleString('en-PH', {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
}

const deviceTypeIcon = {desktop:'fa-desktop',laptop:'fa-laptop',tablet:'fa-tablet-alt',mobile:'fa-mobile-alt',other:'fa-question-circle'};
const statusBadge    = {
    approved: '<span class="badge bg-success-subtle text-success">Approved</span>',
    pending:  '<span class="badge bg-warning-subtle text-warning">Pending</span>',
    blocked:  '<span class="badge bg-danger-subtle text-danger">Blocked</span>',
};

let deviceActionModal, sessionTerminateModal, currentDeviceAction = null, currentSessionId = null;

// ------ Devices ------
async function loadDevices() {
    const search = document.getElementById('deviceSearch')?.value ?? '';
    const status = document.getElementById('deviceStatusFilter')?.value ?? '';
    const tbody  = document.getElementById('devicesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

    const res  = await fetch(`${BASE_URL}/api/devices/index.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`, {credentials:'same-origin'});
    const json = await res.json();
    if (!json.success) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${json.error}</td></tr>`; return; }

    const devices = json.data.devices;
    if (!devices.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No devices found.</td></tr>'; return; }

    tbody.innerHTML = devices.map(d => {
        const icon = deviceTypeIcon[d.device_type] ?? 'fa-question-circle';
        const lastUser = d.last_user_fullname || d.last_user_username || '<span class="text-muted">Unknown</span>';
        const location = (d.city && d.country) ? `${d.city}, ${d.country}` : (d.city || d.country || '<span class="text-muted">Unknown</span>');
        return `<tr>
            <td><code class="fs-11">${d.device_code}</code></td>
            <td><span class="fas ${icon} me-1 text-600"></span>${d.device_name ?? '—'}<br><small class="text-muted">${d.device_type}</small></td>
            <td>${d.ip_address ?? '—'}</td>
            <td>${location}</td>
            <td><strong>${d.last_user_username ?? '—'}</strong><br><small class="text-muted">${lastUser}</small></td>
            <td>${fmtDate(d.last_used_at)}</td>
            <td class="text-center">${d.active_sessions > 0 ? `<span class="badge bg-primary-subtle text-primary">${d.active_sessions}</span>` : '0'}</td>
            <td class="text-center">${statusBadge[d.status] ?? d.status}</td>
            <td class="text-end">
                ${d.status !== 'approved' ? `<button type="button" class="btn btn-sm btn-outline-success me-1" onclick="showDeviceActionModal(${d.device_id},'approved')"><span class="fas fa-check"></span></button>` : ''}
                ${d.status !== 'blocked'  ? `<button type="button" class="btn btn-sm btn-outline-danger me-1"   onclick="showDeviceActionModal(${d.device_id},'blocked')"><span class="fas fa-ban"></span></button>` : ''}
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showDeviceActionModal(${d.device_id},'delete')"><span class="fas fa-trash-alt"></span></button>
            </td>
        </tr>`;
    }).join('');
}

function showDeviceActionModal(deviceId, action) {
    if (!deviceActionModal) deviceActionModal = new bootstrap.Modal(document.getElementById('deviceActionModal'));
    currentDeviceAction = {deviceId, action};

    const titleEl = document.getElementById('deviceActionModalTitle');
    const bodyEl  = document.getElementById('deviceActionModalBody');
    const btnEl   = document.getElementById('deviceActionConfirmBtn');

    btnEl.className = 'btn';

    if (action === 'approved') {
        titleEl.textContent = 'Approve Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to approve this device? Once approved, the device will be allowed to log in.</p>';
        btnEl.className = 'btn btn-success';
        btnEl.textContent = 'Approve';
    } else if (action === 'blocked') {
        titleEl.textContent = 'Block Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to block this device? All active sessions from this device will be terminated immediately.</p>';
        btnEl.className = 'btn btn-danger';
        btnEl.textContent = 'Block';
    } else if (action === 'delete') {
        titleEl.textContent = 'Remove Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to remove this device? All its sessions will be terminated and the device record will be permanently deleted.</p>';
        btnEl.className = 'btn btn-danger';
        btnEl.textContent = 'Remove';
    }

    deviceActionModal.show();
}

document.getElementById('deviceActionConfirmBtn')?.addEventListener('click', async () => {
    if (!currentDeviceAction) return;
    const {deviceId, action} = currentDeviceAction;

    if (action === 'delete') {
        const res  = await fetch(`${BASE_URL}/api/devices/index.php?id=${deviceId}&_token=${CSRF_TOKEN}`, {
            method: 'DELETE',
            headers: {'X-CSRF-Token': CSRF_TOKEN},
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            deviceActionModal?.hide();
            loadDevices();
        } else {
            alert(json.error);
        }
    } else {
        const res  = await fetch(`${BASE_URL}/api/devices/index.php`, {
            method: 'PUT',
            headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF_TOKEN},
            body: JSON.stringify({device_id: deviceId, status: action}),
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            deviceActionModal?.hide();
            loadDevices();
        } else {
            alert(json.error);
        }
    }
});

// ------ Sessions ------
async function loadSessions() {
    const activeOnly = document.getElementById('activeOnlyFilter')?.checked ? 1 : 0;
    const tbody      = document.getElementById('sessionsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

    const res  = await fetch(`${BASE_URL}/api/sessions/index.php?active_only=${activeOnly}`, {credentials:'same-origin'});
    const json = await res.json();
    if (!json.success) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${json.error}</td></tr>`; return; }

    const sessions = json.data.sessions;
    if (!sessions.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No sessions found.</td></tr>'; return; }

    tbody.innerHTML = sessions.map(s => {
        const statusDot = s.is_active == 1
            ? '<span class="fas fa-circle text-success fs-11 me-1" title="Active"></span>'
            : '<span class="fas fa-circle text-secondary fs-11 me-1" title="Ended"></span>';
        return `<tr>
            <td>${statusDot}<strong>${s.username ?? '—'}</strong><br><small class="text-muted">${s.fullname ?? ''}</small></td>
            <td>${s.device_name ?? '<span class="text-muted">Unknown</span>'}<br><small class="text-muted">${s.device_type ?? ''}</small></td>
            <td>${s.ip_address ?? '—'}</td>
            <td>${fmtDate(s.login_time)}</td>
            <td>${fmtDate(s.last_seen)}</td>
            <td>${fmtDate(s.expires_at)}</td>
            <td class="text-end">
                ${s.is_active == 1 ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="showSessionTerminateModal(${s.session_id})"><span class="fas fa-sign-out-alt"></span></button>` : '<span class="text-muted small">Ended</span>'}
            </td>
        </tr>`;
    }).join('');
}

function showSessionTerminateModal(sessionId) {
    if (!sessionTerminateModal) sessionTerminateModal = new bootstrap.Modal(document.getElementById('sessionTerminateModal'));
    currentSessionId = sessionId;
    sessionTerminateModal.show();
}

document.getElementById('sessionTerminateConfirmBtn')?.addEventListener('click', async () => {
    if (!currentSessionId) return;
    const res  = await fetch(`${BASE_URL}/api/sessions/index.php?id=${currentSessionId}&_token=${CSRF_TOKEN}`, {
        method: 'DELETE',
        headers: {'X-CSRF-Token': CSRF_TOKEN},
        credentials: 'same-origin'
    });
    const json = await res.json();
    if (json.success) {
        sessionTerminateModal?.hide();
        loadSessions();
    } else {
        alert(json.error);
    }
});

// Auto-load when Security tab is activated
document.getElementById('security-tab')?.addEventListener('shown.bs.tab', function () {
    loadDevices();
    loadSessions();
});

// Logo upload and preview functions
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please upload a valid image file (PNG, JPG, or WebP).');
            input.value = '';
            return;
        }
        
        // Validate file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Image size must be less than 10MB.');
            input.value = '';
            return;
        }
        
        // Read file and show in cropper modal
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
            
            document.getElementById('logoUploadPlaceholder').classList.add('d-none');
            document.getElementById('logoPreviewContainer').classList.remove('d-none');
            document.getElementById('btnCropLogo')?.classList.remove('d-none');
            document.getElementById('btnRemoveLogo')?.classList.remove('d-none');
            
            // Open cropper modal immediately
            openLogoCropper();
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    document.getElementById('systemLogoUpload').value = '';
    document.getElementById('systemLogoUrl').value = '';
    document.getElementById('logoPreview').src = '';
    
    document.getElementById('logoUploadPlaceholder').classList.remove('d-none');
    document.getElementById('logoPreviewContainer').classList.add('d-none');
    document.getElementById('btnCropLogo')?.classList.add('d-none');
    document.getElementById('btnRemoveLogo')?.classList.add('d-none');
}

// Handle form submission for logo upload
document.querySelector('form[name="settingsForm"]')?.addEventListener('submit', async function(e) {
    const logoInput = document.getElementById('systemLogoUpload');
    
    if (logoInput.files && logoInput.files[0]) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('logo', logoInput.files[0]);
        
        try {
            const res = await fetch(`${BASE_URL}/api/system-settings/upload-logo.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const json = await res.json();
            
            if (json.success) {
                // Set the logo URL in the hidden input
                document.getElementById('systemLogoUrl').value = json.logo_url;
                // Submit the form normally
                this.submit();
            } else {
                alert('Failed to upload logo: ' + (json.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Upload failed: ' + err.message);
        }
    }
});

// Wire up search/filter
document.getElementById('deviceSearch')?.addEventListener('input', debounce(loadDevices, 350));
document.getElementById('deviceStatusFilter')?.addEventListener('change', loadDevices);
document.getElementById('activeOnlyFilter')?.addEventListener('change', loadSessions);

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Logo Cropper Variables
let logoCropperModal = null;
let logoCropCanvas = null;
let logoPreviewCanvas = null;
let logoOriginalImage = null;
let logoScale = 1;
let logoOffsetX = 0;
let logoOffsetY = 0;
let logoRotation = 0;
let logoFlipH = 1;
let logoFlipV = 1;
let logoIsDragging = false;
let logoLastX = 0;
let logoLastY = 0;
let logoCanvasWidth = 500;
let logoCanvasHeight = 300;

function openLogoCropper() {
    const logoUrl = document.getElementById('logoPreview').src;
    if (!logoUrl || logoUrl === '') {
        alert('Please upload a logo first');
        return;
    }
    
    logoOriginalImage = new Image();
    logoOriginalImage.crossOrigin = 'anonymous';
    logoOriginalImage.onload = function() {
        if (!logoCropperModal) {
            logoCropperModal = new bootstrap.Modal(document.getElementById('logoCropperModal'));
        }
        
        logoCropCanvas = document.getElementById('logoCropCanvas');
        logoPreviewCanvas = document.getElementById('logoPreviewCanvas');
        
        // Use high-res internal canvas (500x300) for 5:3 ratio
        logoCanvasWidth = 500;
        logoCanvasHeight = 300;
        logoCropCanvas.width = logoCanvasWidth;
        logoCropCanvas.height = logoCanvasHeight;
        logoPreviewCanvas.width = logoCanvasWidth;
        logoPreviewCanvas.height = logoCanvasHeight;
        
        // Reset state
        logoScale = 1;
        logoOffsetX = 0;
        logoOffsetY = 0;
        logoRotation = 0;
        logoFlipH = 1;
        logoFlipV = 1;
        document.getElementById('logoZoomSlider').value = 1;
        
        // Auto-fit image to canvas
        autoFitImage();
        
        drawLogoCrop();
        logoCropperModal.show();
        
        // Attach canvas events after modal is shown
        setTimeout(function() {
            attachCanvasEvents();
            attachTouchEvents();
        }, 100);
    };
    logoOriginalImage.src = logoUrl;
}

function autoFitImage() {
    if (!logoOriginalImage) return;
    
    const imgRatio = logoOriginalImage.width / logoOriginalImage.height;
    const canvasRatio = logoCanvasWidth / logoCanvasHeight;
    
    // Scale image to COVER the canvas (fill mode - no padding)
    if (imgRatio > canvasRatio) {
        logoScale = logoCanvasHeight / logoOriginalImage.height;
    } else {
        logoScale = logoCanvasWidth / logoOriginalImage.width;
    }
    
    // Clamp to slider range
    const sliderVal = Math.min(Math.max(logoScale, 0.1), 3);
    document.getElementById('logoZoomSlider').value = sliderVal;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    // Reset offset so image is centered
    logoOffsetX = 0;
    logoOffsetY = 0;
}

function drawLogoCrop() {
    if (!logoOriginalImage || !logoCropCanvas) return;
    
    const ctx = logoCropCanvas.getContext('2d');
    const cw = logoCropCanvas.width;
    const ch = logoCropCanvas.height;
    
    ctx.clearRect(0, 0, cw, ch);
    
    // No background fill - preserve transparency for PNG output
    
    ctx.save();
    // Translate to center, rotate, scale, then offset by drag
    ctx.translate(cw / 2 + logoOffsetX, ch / 2 + logoOffsetY);
    ctx.rotate(logoRotation * Math.PI / 180);
    ctx.scale(logoScale * logoFlipH, logoScale * logoFlipV);
    
    // Draw image centered at origin
    ctx.drawImage(
        logoOriginalImage,
        -logoOriginalImage.width / 2,
        -logoOriginalImage.height / 2,
        logoOriginalImage.width,
        logoOriginalImage.height
    );
    
    ctx.restore();
    
    // Update preview
    updateLogoPreview();
}

function updateLogoPreview() {
    if (!logoPreviewCanvas || !logoCropCanvas) return;
    
    const previewCtx = logoPreviewCanvas.getContext('2d');
    previewCtx.clearRect(0, 0, logoPreviewCanvas.width, logoPreviewCanvas.height);
    
    // Draw cropped area as preview
    previewCtx.drawImage(logoCropCanvas, 0, 0, logoCropCanvas.width, logoCropCanvas.height, 0, 0, logoPreviewCanvas.width, logoPreviewCanvas.height);
}

function updateLogoZoom(value) {
    logoScale = parseFloat(value);
    document.getElementById('zoomValue').textContent = logoScale.toFixed(1) + 'x';
    drawLogoCrop();
}

function zoomLogoIn() {
    logoScale = Math.min(logoScale + 0.1, 3);
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    drawLogoCrop();
}

function zoomLogoOut() {
    logoScale = Math.max(logoScale - 0.1, 0.1);
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    drawLogoCrop();
}

function rotateLogoLogo(degrees) {
    logoRotation += degrees;
    // Normalize rotation to 0-360
    if (logoRotation >= 360) logoRotation -= 360;
    if (logoRotation < 0) logoRotation += 360;
    document.getElementById('rotationValue').textContent = logoRotation + '°';
    drawLogoCrop();
}

function flipLogoHorizontal() {
    logoFlipH *= -1;
    document.getElementById('flipHValue').textContent = logoFlipH === -1 ? 'On' : 'Off';
    drawLogoCrop();
}

function flipLogoVertical() {
    logoFlipV *= -1;
    document.getElementById('flipVValue').textContent = logoFlipV === -1 ? 'On' : 'Off';
    drawLogoCrop();
}

function resetLogoCrop() {
    autoFitImage();
    logoOffsetX = 0;
    logoOffsetY = 0;
    logoRotation = 0;
    logoFlipH = 1;
    logoFlipV = 1;
    document.getElementById('rotationValue').textContent = '0°';
    document.getElementById('flipHValue').textContent = 'Off';
    document.getElementById('flipVValue').textContent = 'Off';
    drawLogoCrop();
}

function applyLogoCrop() {
    if (!logoCropCanvas) return;
    
    // Convert canvas to blob and upload
    logoCropCanvas.toBlob(function(blob) {
        if (!blob) {
            alert('Failed to process image');
            return;
        }
        
        const file = new File([blob], 'cropped-logo.png', { type: 'image/png' });
        const formData = new FormData();
        formData.append('logo', file);
        
        fetch(`${BASE_URL}/api/system-settings/upload-logo.php`, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                document.getElementById('systemLogoUrl').value = json.logo_url;
                document.getElementById('logoPreview').src = BASE_URL + json.logo_url + '?t=' + Date.now();
                document.getElementById('logoUploadPlaceholder').classList.add('d-none');
                document.getElementById('logoPreviewContainer').classList.remove('d-none');
                document.getElementById('btnCropLogo')?.classList.remove('d-none');
                document.getElementById('btnRemoveLogo')?.classList.remove('d-none');
                logoCropperModal?.hide();
                showLogoToast('Logo saved successfully!', 'success');
            } else {
                showLogoToast('Failed to save: ' + (json.error || 'Unknown error'), 'danger');
            }
        })
        .catch(err => {
            showLogoToast('Upload failed: ' + err.message, 'danger');
        });
    }, 'image/png');
}

function showLogoToast(message, type = 'success') {
    const existing = document.getElementById('logoToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'logoToast';
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    toast.innerHTML = `
        <span class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></span>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4000);
}

// Canvas drag events - attach after modal is shown
function attachCanvasEvents() {
    const canvas = document.getElementById('logoCropCanvas');
    if (!canvas) return;
    
    // Remove existing listeners to prevent duplicates
    canvas.removeEventListener('mousedown', handleMouseDown);
    canvas.removeEventListener('wheel', handleWheel);
    
    // Add fresh listeners
    canvas.addEventListener('mousedown', handleMouseDown);
    canvas.addEventListener('wheel', handleWheel, { passive: false });
    
    canvas.style.cursor = 'move';
}

function handleMouseDown(e) {
    logoIsDragging = true;
    logoLastX = e.clientX;
    logoLastY = e.clientY;
    logoCropCanvas.style.cursor = 'grabbing';
}

function handleWheel(e) {
    e.preventDefault();
    
    const zoomSpeed = 0.1;
    const delta = e.deltaY > 0 ? -zoomSpeed : zoomSpeed;
    
    logoScale = Math.max(0.1, Math.min(3, logoScale + delta));
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    
    drawLogoCrop();
}

document.addEventListener('mousemove', function(e) {
    if (!logoIsDragging) return;
    
    const deltaX = e.clientX - logoLastX;
    const deltaY = e.clientY - logoLastY;
    
    logoOffsetX += deltaX;
    logoOffsetY += deltaY;
    
    logoLastX = e.clientX;
    logoLastY = e.clientY;
    
    drawLogoCrop();
});

document.addEventListener('mouseup', function() {
    logoIsDragging = false;
    if (logoCropCanvas) logoCropCanvas.style.cursor = 'move';
});

// Touch support for mobile
let logoInitialPinchDistance = 0;
let logoInitialScale = 1;

function attachTouchEvents() {
    const canvas = document.getElementById('logoCropCanvas');
    if (!canvas) return;
    
    canvas.removeEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
}

function handleTouchStart(e) {
    e.preventDefault();
    
    if (e.touches.length === 1) {
        // Single touch - drag
        const touch = e.touches[0];
        logoIsDragging = true;
        logoLastX = touch.clientX;
        logoLastY = touch.clientY;
    } else if (e.touches.length === 2) {
        // Pinch to zoom
        logoIsDragging = false;
        logoInitialPinchDistance = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        logoInitialScale = logoScale;
    }
}

document.addEventListener('touchmove', function(e) {
    if (e.touches.length === 1 && logoIsDragging) {
        // Single touch drag
        e.preventDefault();
        
        const touch = e.touches[0];
        const deltaX = touch.clientX - logoLastX;
        const deltaY = touch.clientY - logoLastY;
        
        logoOffsetX += deltaX;
        logoOffsetY += deltaY;
        
        logoLastX = touch.clientX;
        logoLastY = touch.clientY;
        
        drawLogoCrop();
    } else if (e.touches.length === 2) {
        // Pinch to zoom
        e.preventDefault();
        
        const currentPinchDistance = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        
        const scaleRatio = currentPinchDistance / logoInitialPinchDistance;
        logoScale = Math.max(0.5, Math.min(3, logoInitialScale * scaleRatio));
        document.getElementById('logoZoomSlider').value = logoScale;
        
        drawLogoCrop();
    }
});

document.addEventListener('touchend', function() {
    logoIsDragging = false;
    logoInitialPinchDistance = 0;
});
</script>

<!-- Logo Cropper Modal -->
<div class="modal fade" id="logoCropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-4 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white">
            <span class="fas fa-crop-alt me-2"></span>Crop Logo
          </h4>
          <p class="fs-10 mb-0 text-white">Adjust your logo position and appearance</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h6 class="fw-bold mb-0 text-primary">
                    <span class="fas fa-image me-2"></span>Crop Area (5:3 ratio)
                  </h6>
                  <span class="badge bg-soft-info text-info">
                    <span class="fas fa-info-circle me-1"></span>Drag to move • Scroll to zoom
                  </span>
                </div>
                <div class="position-relative" style="border: 1px solid #dee2e6; border-radius: 12px; background: #fff; overflow: hidden;">
                  <canvas id="logoCropCanvas" width="500" height="300" style="width: 100%; height: auto; display: block; cursor: move;"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="zoomLogoOut()" title="Zoom Out">
                    <span class="fas fa-search-minus"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="zoomLogoIn()" title="Zoom In">
                    <span class="fas fa-search-plus"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="rotateLogoLogo(-90)" title="Rotate Left">
                    <span class="fas fa-undo"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="rotateLogoLogo(90)" title="Rotate Right">
                    <span class="fas fa-redo"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="flipLogoHorizontal()" title="Flip Horizontal">
                    <span class="fas fa-arrows-alt-h"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="flipLogoVertical()" title="Flip Vertical">
                    <span class="fas fa-arrows-alt-v"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetLogoCrop()" title="Reset">
                    <span class="fas fa-sync"></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body p-3">
                <h6 class="fw-bold mb-3 text-primary">
                  <span class="fas fa-eye me-2"></span>Preview
                </h6>
                <div class="text-center mb-3">
                  <div class="border rounded-3 p-4 bg-light d-inline-block" style="background: linear-gradient(45deg, #e9ecef 25%, transparent 25%, transparent 75%, #e9ecef 75%, #e9ecef), linear-gradient(45deg, #e9ecef 25%, transparent 25%, transparent 75%, #e9ecef 75%, #e9ecef); background-size: 20px 20px; background-position: 0 0, 10px 10px;">
                    <canvas id="logoPreviewCanvas" width="500" height="300" style="width: 100%; height: auto; display: block;"></canvas>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold text-muted small mb-2">Zoom Level: <span id="zoomValue">1.0x</span></label>
                  <input type="range" class="form-range" id="logoZoomSlider" min="0.1" max="3" step="0.05" value="1" onchange="updateLogoZoom(this.value)">
                  <div class="d-flex justify-content-between text-muted small">
                    <span>0.1x</span>
                    <span>3x</span>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>Rotation:</span>
                    <span id="rotationValue">0°</span>
                  </div>
                  <div class="d-flex justify-content-between text-muted small">
                    <span>Flip H:</span>
                    <span id="flipHValue">Off</span>
                  </div>
                  <div class="d-flex justify-content-between text-muted small">
                    <span>Flip V:</span>
                    <span id="flipVValue">Off</span>
                  </div>
                </div>
                <div class="d-grid gap-2">
                  <button type="button" class="btn btn-primary" onclick="applyLogoCrop()">
                    <span class="fas fa-check me-2"></span>Apply & Save
                  </button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <span class="fas fa-times me-2"></span>Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
