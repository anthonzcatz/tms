<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/css/bir.css'); ?>">
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
      
      <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?>
        <?php if (NAVBAR_POSITION === 'top'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php endif; ?>
      
      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
        }
        ?>
      <?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">BIR Settings</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">Settings</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto">
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-primary">
                      <span class="fas fa-arrow-left me-1"></span>Back to BIR
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <div class="card">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-cog me-2"></span>BIR Registration Details</h6>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <div class="row g-3">
                <!-- Company Information -->
                <div class="col-12">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Company Information</h6>
                </div>
                
                <div class="col-md-6">
                    <label for="company_tin" class="form-label">Company TIN <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="company_tin" name="company_tin" 
                           value="<?php echo htmlspecialchars($birSettings['company_tin'] ?? ''); ?>"
                           placeholder="000-123-456-000" required>
                    <div class="form-text">Tax Identification Number format: XXX-XXX-XXX-XXX</div>
                </div>

                <!-- BIR Accreditation -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3">BIR Accreditation</h6>
                </div>
                
                <div class="col-md-6">
                    <label for="bir_accreditation_number" class="form-label">Accreditation Number</label>
                    <input type="text" class="form-control" id="bir_accreditation_number" name="bir_accreditation_number"
                           value="<?php echo htmlspecialchars($birSettings['bir_accreditation_number'] ?? ''); ?>"
                           placeholder="e.g., ACC-2024-001">
                    <small class="text-muted">BIR accreditation certificate number for CAS/POS</small>
                </div>

                <div class="col-md-6">
                    <label for="bir_accreditation_expiry" class="form-label">Accreditation Expiry Date</label>
                    <input type="date" class="form-control" id="bir_accreditation_expiry" name="bir_accreditation_expiry"
                           value="<?php echo $birSettings['bir_accreditation_expiry'] ?? ''; ?>">
                    <small class="text-muted">When the BIR accreditation expires</small>
                </div>

                <div class="col-md-6">
                    <label for="bir_permit_number" class="form-label">Permit Number</label>
                    <input type="text" class="form-control" id="bir_permit_number" name="bir_permit_number"
                           value="<?php echo htmlspecialchars($birSettings['bir_permit_number'] ?? ''); ?>"
                           placeholder="e.g., PERMIT-2024-001">
                    <small class="text-muted">BIR permit to use computerized system</small>
                </div>

                <!-- Validity Period -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Validity Period</h6>
                </div>

                <div class="col-md-6">
                    <label for="bir_validity_from" class="form-label">Validity From</label>
                    <input type="date" class="form-control" id="bir_validity_from" name="bir_validity_from"
                           value="<?php echo $birSettings['bir_validity_from'] ?? ''; ?>">
                    <small class="text-muted">Start date of permit validity</small>
                </div>

                <div class="col-md-6">
                    <label for="bir_validity_to" class="form-label">Validity To</label>
                    <input type="date" class="form-control" id="bir_validity_to" name="bir_validity_to"
                           value="<?php echo $birSettings['bir_validity_to'] ?? ''; ?>">
                    <small class="text-muted">End date of permit validity</small>
                </div>

                <!-- Machine Information -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3">POS Machine Information</h6>
                </div>

                <div class="col-md-6">
                    <label for="bir_min" class="form-label">Machine Identification Number (MIN)</label>
                    <input type="text" class="form-control" id="bir_min" name="bir_min"
                           value="<?php echo htmlspecialchars($birSettings['bir_min'] ?? ''); ?>"
                           placeholder="e.g., 123456789012">
                    <small class="text-muted">Unique ID issued by BIR for this machine</small>
                </div>

                <div class="col-md-6">
                    <label for="bir_machine_serial" class="form-label">Machine Serial Number</label>
                    <input type="text" class="form-control" id="bir_machine_serial" name="bir_machine_serial"
                           value="<?php echo htmlspecialchars($birSettings['bir_machine_serial'] ?? ''); ?>"
                           placeholder="e.g., SN123456789">
                    <small class="text-muted">Physical serial number of the POS terminal</small>
                </div>

                <!-- VAT Settings -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3">VAT Settings</h6>
                </div>

                <div class="col-md-6">
                    <label for="bir_vat_rate" class="form-label">VAT Rate (%)</label>
                    <input type="number" class="form-control" id="bir_vat_rate" name="bir_vat_rate"
                           value="<?php echo $birSettings['bir_vat_rate'] ?? 12.00; ?>"
                           min="0" max="100" step="0.01">
                    <small class="text-muted">Standard VAT rate in Philippines is 12%</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">OR Assignment</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="bir_auto_or_assignment"
                               name="bir_auto_or_assignment" value="1"
                               <?php echo ($birSettings['bir_auto_or_assignment'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="bir_auto_or_assignment">
                            Auto-assign OR numbers on transaction
                        </label>
                    </div>
                    <small class="text-muted">Automatically assign OR number when order is completed</small>
                </div>

                <!-- Info Note -->
                <div class="col-12 mt-4">
                    <div class="alert alert-info d-flex align-items-center">
                        <span class="fas fa-info-circle me-2"></span>
                        <div>
                            <strong>Receipt Printing Options</strong> (paper size, auto-print, etc.) are managed in <a href="<?php echo BASE_URL; ?>/admin/system-settings/">System Settings → Printer Settings</a>.
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="col-12 mt-4">
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-secondary">
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

      </div>
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
