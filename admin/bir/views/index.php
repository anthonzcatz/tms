<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
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
                      <h4 class="mb-0 text-primary fw-bold">BIR Accredited System</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item active">BIR Module</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                    <?php if ($accreditationWarning): ?>
                    <div class="alert alert-warning mb-0 py-2 px-3">
                      <span class="fas fa-exclamation-triangle me-2"></span>
                      <strong>Warning:</strong> Accreditation expiring soon!
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Today's OR Issued</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-primary" id="statOrIssued"><?php echo $orStats['issued_or'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0"><span class="fas fa-receipt text-primary fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Today's Sales (VAT)</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-success" id="statVatSales">₱<?php echo number_format($todaySales['total_sales'] ?? 0, 2); ?></div></div>
                  <div class="col-auto ps-0"><span class="fas fa-money-bill-wave text-success fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">VAT Collected</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-info" id="statVat">₱<?php echo number_format($todaySales['total_vat'] ?? 0, 2); ?></div></div>
                  <div class="col-auto ps-0"><span class="fas fa-percentage text-info fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Active Machines</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-warning" id="statMachines"><?php echo count(array_filter($machines, fn($m) => $m['status'] === 'active')); ?></div></div>
                  <div class="col-auto ps-0"><span class="fas fa-desktop text-warning fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- BIR Settings Summary -->
        <div class="card mb-3">
          <div class="card-header bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><span class="fas fa-building me-2"></span>BIR Registration Details</h6>
              <a href="<?php echo BASE_URL; ?>/admin/bir/settings/" class="btn btn-sm btn-primary">
                <span class="fas fa-cog me-1"></span>Manage Settings
              </a>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">Company TIN</h6>
                  <p class="mb-0 fw-semibold"><?php echo $birSettings['company_tin'] ? htmlspecialchars($birSettings['company_tin']) : '<span class="text-danger">Not Set</span>'; ?></p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">Accreditation Number</h6>
                  <p class="mb-0 fw-semibold"><?php echo $birSettings['bir_accreditation_number'] ? htmlspecialchars($birSettings['bir_accreditation_number']) : '<span class="text-danger">Not Set</span>'; ?></p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">Permit Number</h6>
                  <p class="mb-0 fw-semibold"><?php echo $birSettings['bir_permit_number'] ? htmlspecialchars($birSettings['bir_permit_number']) : '<span class="text-danger">Not Set</span>'; ?></p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">Validity Period</h6>
                  <p class="mb-0 fw-semibold">
                    <?php 
                    if ($birSettings['bir_validity_from'] && $birSettings['bir_validity_to']) {
                        echo date('M d, Y', strtotime($birSettings['bir_validity_from'])) . ' - ' . date('M d, Y', strtotime($birSettings['bir_validity_to']));
                    } else {
                        echo '<span class="text-danger">Not Set</span>';
                    }
                    ?>
                  </p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">Machine ID (MIN)</h6>
                  <p class="mb-0 fw-semibold"><?php echo $birSettings['bir_min'] ? htmlspecialchars($birSettings['bir_min']) : '<span class="text-danger">Not Set</span>'; ?></p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <h6 class="text-muted small mb-1">VAT Rate</h6>
                  <p class="mb-0 fw-semibold"><?php echo $birSettings['bir_vat_rate'] ?? '12'; ?>%</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Module Menu Grid -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/or-numbers/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-receipt fa-3x text-primary"></span>
                  </div>
                  <h5 class="card-title">OR Numbers</h5>
                  <p class="card-text text-muted small">Manage Official Receipt numbering, void tracking, and series management</p>
                </div>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/reports/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-chart-bar fa-3x text-success"></span>
                  </div>
                  <h5 class="card-title">BIR Reports</h5>
                  <p class="card-text text-muted small">Generate DSR, Monthly Sales, SLS, Alphalist, and VAT Returns</p>
                </div>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/vat/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-percentage fa-3x text-info"></span>
                  </div>
                  <h5 class="card-title">VAT Management</h5>
                  <p class="card-text text-muted small">VAT computation, exemption types, and VAT transaction tracking</p>
                </div>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/machines/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-desktop fa-3x text-warning"></span>
                  </div>
                  <h5 class="card-title">POS Machines</h5>
                  <p class="card-text text-muted small">Manage POS machine accreditation details and serial numbers</p>
                </div>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/audit-trail/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-history fa-3x text-secondary"></span>
                  </div>
                  <h5 class="card-title">Audit Trail</h5>
                  <p class="card-text text-muted small">View comprehensive audit logs and transaction modifications</p>
                </div>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a href="<?php echo BASE_URL; ?>/admin/bir/backups/" class="text-decoration-none">
              <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                  <div class="mb-3">
                    <span class="fas fa-database fa-3x text-danger"></span>
                  </div>
                  <h5 class="card-title">Backups</h5>
                  <p class="card-text text-muted small">Manage automated backups and restore functionality</p>
                </div>
              </div>
            </a>
          </div>
        </div>

        <!-- Active OR Series -->
        <div class="card mb-3">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-list-ol me-2"></span>Active OR Series</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Branch</th>
                    <th>Series Code</th>
                    <th>Year</th>
                    <th>Current Number</th>
                    <th>End Number</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($orSeries)): ?>
                    <?php foreach ($orSeries as $series): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($series['branch_name']); ?></td>
                      <td><?php echo htmlspecialchars($series['series_code']); ?></td>
                      <td><?php echo $series['year']; ?></td>
                      <td><?php echo number_format($series['current_number']); ?></td>
                      <td><?php echo number_format($series['end_number']); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $series['status'] === 'active' ? 'success' : 'secondary'; ?>">
                          <?php echo ucfirst($series['status']); ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted py-3">No active OR series found</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- POS Machines Summary -->
        <div class="card mb-3">
          <div class="card-header bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><span class="fas fa-desktop me-2"></span>POS Machines</h6>
              <a href="<?php echo BASE_URL; ?>/admin/bir/machines/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Machine</th>
                    <th>Branch</th>
                    <th>Serial Number</th>
                    <th>Type</th>
                    <th>Accreditation</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $machinesToShow = array_slice($machines, 0, 5);
                  if (!empty($machinesToShow)): 
                  ?>
                    <?php foreach ($machinesToShow as $machine): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                      <td><?php echo htmlspecialchars($machine['branch_name']); ?></td>
                      <td><?php echo htmlspecialchars($machine['serial_number']); ?></td>
                      <td><?php echo $machine['machine_type']; ?></td>
                      <td>
                        <?php if ($machine['accreditation_number']): ?>
                          <?php echo htmlspecialchars($machine['accreditation_number']); ?><br>
                          <small class="text-muted">Exp: <?php echo $machine['accreditation_expiry'] ? date('M d, Y', strtotime($machine['accreditation_expiry'])) : 'N/A'; ?></small>
                        <?php else: ?>
                          <span class="text-muted">Not set</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php 
                        $badgeClass = match($machine['status']) {
                            'active' => 'success',
                            'expired' => 'danger',
                            'suspended' => 'warning',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($machine['status']); ?></span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted py-3">No POS machines registered</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
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
  <script src="<?php echo BASE_URL; ?>/admin/bir/assets/js/bir.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/bir.js'); ?>"></script>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
