<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
$currency = $reportData['system']['currency'] ?? 'PHP';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/reports/financial/assets/css/financial.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/financial.css'); ?>">
<body>
  <main class="main financial-reports-page" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }
        ?><?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4 no-print">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Financial <span class="text-info fw-medium">Reports</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin">Home</a></li>
                            <li class="breadcrumb-item active">Financial Reports</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2 no-print">
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                      <span class="fas fa-print"></span><span class="ms-2 d-none d-sm-inline">Print</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Print-Only Header -->
        <div class="print-header d-none d-print-block">
          <img class="img-fluid" src="<?php echo BASE_URL; ?><?php echo htmlspecialchars($reportData['system']['logo'] ?? '/resources/assets/img/icons/spot-illustrations/falcon.png'); ?>" alt="" />
          <h2><?php echo htmlspecialchars($reportData['system']['name'] ?? 'TMS'); ?></h2>
          <?php if (!empty($reportData['system']['company_address'])): ?>
          <p class="company-meta"><?php echo nl2br(htmlspecialchars($reportData['system']['company_address'])); ?></p>
          <?php endif; ?>
          <?php if (!empty($reportData['system']['company_contact_number'])): ?>
          <p class="company-meta">Contact: <?php echo htmlspecialchars($reportData['system']['company_contact_number']); ?></p>
          <?php endif; ?>
          <h3>Profit &amp; Loss Statement</h3>
          <p>
            Period: <?php echo htmlspecialchars($reportData['filters']['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($reportData['filters']['end_date'] ?? ''); ?>
            <?php
              $selectedBranchName = 'All Branches';
              foreach ($branches as $b) {
                  if ($b['branch_id'] == ($reportData['filters']['branch_id'] ?? null)) {
                      $selectedBranchName = $b['branch_name'];
                      break;
                  }
              }
            ?>
            <br />Branch: <?php echo htmlspecialchars($selectedBranchName); ?>
          </p>
          <p class="small">Generated on: <?php echo htmlspecialchars($reportData['generated_at'] ?? ''); ?> by <?php echo htmlspecialchars($reportData['generated_by'] ?? ''); ?></p>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm mb-4 no-print border-0">
          <div class="card-header bg-light py-2">
            <h6 class="mb-0 fw-bold"><span class="fas fa-filter me-2 text-primary"></span>Report Filters</h6>
          </div>
          <div class="card-body">
            <form method="get" action="<?php echo BASE_URL; ?>/admin/reports/financial/" class="row g-3 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small text-muted">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($reportData['filters']['start_date'] ?? ''); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small text-muted">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($reportData['filters']['end_date'] ?? ''); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small text-muted">Branch</label>
                <select class="form-select" name="branch_id">
                  <option value="">All Branches</option>
                  <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo ($reportData['filters']['branch_id'] ?? null) == $b['branch_id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($b['branch_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                  <span class="fas fa-sync-alt me-2"></span>Generate Report
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-body d-flex align-items-center py-3">
                <div class="icon-item icon-item-sm bg-primary-subtle shadow-none me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                  <span class="fas fa-money-bill-wave text-primary fs-5"></span>
                </div>
                <div>
                  <h6 class="text-muted mb-1 text-uppercase fs-11 fw-semibold">Total Revenue</h6>
                  <h4 class="mb-0 fw-bold text-primary lh-1"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_revenue'] ?? 0), 2); ?></h4>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-body d-flex align-items-center py-3">
                <div class="icon-item icon-item-sm bg-info-subtle shadow-none me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                  <span class="fas fa-chart-line text-info fs-5"></span>
                </div>
                <div>
                  <h6 class="text-muted mb-1 text-uppercase fs-11 fw-semibold">Gross Profit</h6>
                  <h4 class="mb-0 fw-bold text-info lh-1"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['gross_profit'] ?? 0), 2); ?></h4>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-body d-flex align-items-center py-3">
                <div class="icon-item icon-item-sm bg-warning-subtle shadow-none me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                  <span class="fas fa-undo text-warning fs-5"></span>
                </div>
                <div>
                  <h6 class="text-muted mb-1 text-uppercase fs-11 fw-semibold">Total Refunds</h6>
                  <h4 class="mb-0 fw-bold text-warning lh-1"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_refunds'] ?? 0), 2); ?></h4>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card shadow-sm h-100 border-0">
              <div class="card-body d-flex align-items-center py-3">
                <div class="icon-item icon-item-sm bg-success-subtle shadow-none me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                  <span class="fas fa-piggy-bank text-success fs-5"></span>
                </div>
                <div>
                  <h6 class="text-muted mb-1 text-uppercase fs-11 fw-semibold">Net Profit</h6>
                  <h4 class="mb-0 fw-bold text-success lh-1"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['net_profit'] ?? 0), 2); ?></h4>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Profit & Loss Statement Table -->
        <div class="card shadow-sm mb-4 border-0">
          <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-md-between align-items-md-center py-3 border-bottom">
            <div class="d-flex align-items-center">
              <div class="icon-item icon-item-sm bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;">
                <span class="fas fa-chart-line text-primary fs-11"></span>
              </div>
              <h5 class="mb-0 fw-bold text-primary">Profit &amp; Loss Statement</h5>
            </div>
            <div class="mt-2 mt-md-0">
              <span class="badge bg-light text-dark border">
                <span class="fas fa-calendar-alt me-1 text-muted"></span>
                <?php echo htmlspecialchars($reportData['filters']['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($reportData['filters']['end_date'] ?? ''); ?>
              </span>
              <?php if (!empty($reportData['filters']['branch_id'])): ?>
                <?php
                  $selectedBranchName = 'Selected';
                  foreach ($branches as $b) {
                      if ($b['branch_id'] == $reportData['filters']['branch_id']) {
                          $selectedBranchName = $b['branch_name'];
                          break;
                      }
                  }
                ?>
                <span class="badge bg-light text-dark border ms-1">
                  <span class="fas fa-building me-1 text-muted"></span><?php echo htmlspecialchars($selectedBranchName); ?>
                </span>
              <?php else: ?>
                <span class="badge bg-light text-dark border ms-1">
                  <span class="fas fa-building me-1 text-muted"></span>All Branches
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Revenue (<?php echo $currency; ?>)</th>
                    <th class="text-end">Cost (<?php echo $currency; ?>)</th>
                    <th class="text-end">Discounts (<?php echo $currency; ?>)</th>
                    <th class="text-end">Service Fees (<?php echo $currency; ?>)</th>
                    <th class="text-end">Add-ons (<?php echo $currency; ?>)</th>
                    <th class="text-end">Gross Profit (<?php echo $currency; ?>)</th>
                    <th class="text-end text-warning">Refunds (<?php echo $currency; ?>)</th>
                    <th class="text-end text-success">Net Profit (<?php echo $currency; ?>)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($reportData['daily'] as $row): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['report_date'] ?? ''); ?></td>
                      <td class="text-end"><?php echo number_format((int)($row['orders'] ?? 0)); ?></td>
                      <td class="text-end"><?php echo $currency . ' ' . number_format((float)($row['revenue'] ?? 0), 2); ?></td>
                      <td class="text-end"><?php echo $currency . ' ' . number_format((float)($row['cost'] ?? 0), 2); ?></td>
                      <td class="text-end"><?php echo $currency . ' ' . number_format((float)($row['discounts'] ?? 0), 2); ?></td>
                      <td class="text-end"><?php echo $currency . ' ' . number_format((float)($row['service_fees'] ?? 0), 2); ?></td>
                      <td class="text-end"><?php echo $currency . ' ' . number_format((float)($row['add_ons'] ?? 0), 2); ?></td>
                      <td class="text-end fw-semibold"><?php echo $currency . ' ' . number_format((float)($row['gross_profit'] ?? 0), 2); ?></td>
                      <td class="text-end text-warning"><?php echo $currency . ' ' . number_format((float)($row['refunds'] ?? 0), 2); ?></td>
                      <td class="text-end fw-bold text-success"><?php echo $currency . ' ' . number_format((float)($row['net_profit'] ?? 0), 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="table-group-divider table-light">
                  <tr>
                    <td class="fw-bold">Total</td>
                    <td class="text-end fw-bold"><?php echo number_format((int)($reportData['summary']['total_orders'] ?? 0)); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_revenue'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_cost'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_discounts'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_service_fees'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_add_ons'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['gross_profit'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold text-warning"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['total_refunds'] ?? 0), 2); ?></td>
                    <td class="text-end fw-bold text-success"><?php echo $currency . ' ' . number_format((float)($reportData['summary']['net_profit'] ?? 0), 2); ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Print Footer -->
        <div class="print-footer d-none d-print-block">
          <div class="row">
            <div class="col-6">
              <strong>Prepared by:</strong> <?php echo htmlspecialchars($reportData['generated_by'] ?? ''); ?><br />
              <strong>Generated:</strong> <?php echo htmlspecialchars($reportData['generated_at'] ?? ''); ?>
            </div>
            <div class="col-6 text-end">
              <strong><?php echo htmlspecialchars($reportData['system']['name'] ?? 'TMS'); ?></strong><br />
              Profit & Loss Statement
            </div>
          </div>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?></div><?php endif; ?>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/reports/financial/assets/js/financial.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/financial.js'); ?>"></script>
</body>
</html>
