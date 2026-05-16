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
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">System Name</label>
                        <input type="text" class="form-control" name="system_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? 'Falcon'); ?>" placeholder="e.g., Falcon">
                        <small class="text-muted">This will replace "Falcon" in the navbar and other places</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">System Logo URL</label>
                        <input type="text" class="form-control" name="system_logo" value="<?php echo htmlspecialchars($settings['system_logo'] ?? ''); ?>" placeholder="e.g., /resources/assets/img/logo.png">
                        <small class="text-muted">Leave empty to use default Falcon logo</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Developer Name</label>
                        <input type="text" class="form-control" name="developer_name" value="<?php echo htmlspecialchars($settings['developer_name'] ?? ''); ?>" placeholder="e.g., Your Company">
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Developer Details</label>
                        <textarea class="form-control" name="developer_details" rows="2" placeholder="e.g., Designed and developed by Your Development Team"><?php echo htmlspecialchars($settings['developer_details'] ?? ''); ?></textarea>
                      </div>
                      <div class="col-md-12">
                        <label class="form-label fw-semibold">Footer Copyright</label>
                        <textarea class="form-control" name="footer_copyright" rows="2" placeholder="e.g., © 2024 Your Company. All rights reserved."><?php echo htmlspecialchars($settings['footer_copyright'] ?? ''); ?></textarea>
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
                    <div class="row g-3">
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="cancellation_requires_confirmation" id="cancellationRequiresConfirmation" <?php echo ($settings['cancellation_requires_confirmation'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="cancellationRequiresConfirmation">Require Confirmation for Cancellations</label>
                        </div>
                        <small class="text-muted">When enabled, ticket cancellations require manager approval before processing.</small>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="cancellation_auto_approve" id="cancellationAutoApprove" <?php echo ($settings['cancellation_auto_approve'] ?? 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="cancellationAutoApprove">Auto-Approve Cancellations</label>
                        </div>
                        <small class="text-muted">When enabled, cancellations are automatically approved without requiring confirmation.</small>
                      </div>
                      <div class="col-md-12">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="cancellation_refund_to_wallet" id="cancellationRefundToWallet" <?php echo ($settings['cancellation_refund_to_wallet'] ?? 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-semibold" for="cancellationRefundToWallet">Refund to Wallet Balance</label>
                        </div>
                        <small class="text-muted">When enabled, cancelled tickets are automatically refunded to wallet balance.</small>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold">Refund Processing Days</label>
                        <input type="number" class="form-control" name="cancellation_refund_processing_days" value="<?php echo intval($settings['cancellation_refund_processing_days'] ?? 3); ?>" min="1" max="30">
                        <small class="text-muted">Number of days to process refunds.</small>
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

  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/system-settings/assets/js/system-settings.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/system-settings.js'); ?>"></script>
</body>
</html>
