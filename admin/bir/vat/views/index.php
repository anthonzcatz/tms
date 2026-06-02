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

        <!-- Header -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">VAT Management</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">VAT</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto">
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary">
                      <span class="fas fa-arrow-left me-1"></span>Back
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- VAT Settings Card -->
        <div class="card mb-4">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-percentage me-2"></span>VAT Settings</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                  <h6 class="text-muted small mb-1">Current VAT Rate</h6>
                  <h3 class="text-primary mb-0"><?php echo $vatSettings['bir_vat_rate'] ?? 12; ?>%</h3>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                  <h6 class="text-muted small mb-1">Today's VAT Collected</h6>
                  <h3 class="text-success mb-0">₱<?php echo number_format($vatSummary['total_vat_collected'] ?? 0, 2); ?></h3>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                  <h6 class="text-muted small mb-1">Taxable Sales</h6>
                  <h3 class="text-info mb-0">₱<?php echo number_format($vatSummary['total_taxable_sales'] ?? 0, 2); ?></h3>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                  <h6 class="text-muted small mb-1">Transactions Today</h6>
                  <h3 class="text-warning mb-0"><?php echo number_format($vatSummary['total_transactions'] ?? 0); ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- VAT by Type -->
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header bg-body-tertiary">
                <h6 class="mb-0"><span class="fas fa-chart-pie me-2"></span>Today's VAT Breakdown</h6>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>VAT Type</th>
                        <th>Count</th>
                        <th>VAT Amount</th>
                        <th>Taxable Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($vatByType as $vat): ?>
                      <tr>
                        <td>
                          <?php 
                          $typeLabel = match($vat['vat_type']) {
                              '12_percent' => '12% VAT',
                              'exempt' => 'VAT Exempt',
                              'zero_rated' => 'Zero Rated',
                              default => $vat['vat_type']
                          };
                          $badgeClass = match($vat['vat_type']) {
                              '12_percent' => 'primary',
                              'exempt' => 'success',
                              'zero_rated' => 'info',
                              default => 'secondary'
                          };
                          ?>
                          <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $typeLabel; ?></span>
                        </td>
                        <td><?php echo number_format($vat['transaction_count']); ?></td>
                        <td>₱<?php echo number_format($vat['vat_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($vat['taxable_amount'], 2); ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($vatByType)): ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted">No VAT transactions today</td>
                      </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header bg-body-tertiary">
                <h6 class="mb-0"><span class="fas fa-calendar-alt me-2"></span>Monthly VAT Summary</h6>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>Month</th>
                        <th>Transactions</th>
                        <th>Taxable Sales</th>
                        <th>VAT Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($monthlyVat as $month): ?>
                      <tr>
                        <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                        <td><?php echo number_format($month['transaction_count']); ?></td>
                        <td>₱<?php echo number_format($month['taxable_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($month['vat_amount'], 2); ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($monthlyVat)): ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted">No data available</td>
                      </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent VAT Transactions -->
        <div class="card">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-list me-2"></span>Recent VAT Transactions</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Order</th>
                    <th>Branch</th>
                    <th>VAT Type</th>
                    <th>Exemption</th>
                    <th>Taxable</th>
                    <th>VAT Amount</th>
                    <th>Grand Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentTransactions)): ?>
                    <?php foreach ($recentTransactions as $tx): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($tx['order_code']); ?></td>
                      <td><?php echo htmlspecialchars($tx['branch_name']); ?></td>
                      <td>
                        <?php 
                        $typeLabel = match($tx['vat_type']) {
                            '12_percent' => '12% VAT',
                            'exempt' => 'Exempt',
                            'zero_rated' => 'Zero Rated',
                            default => $tx['vat_type']
                        };
                        $badgeClass = match($tx['vat_type']) {
                            '12_percent' => 'primary',
                            'exempt' => 'success',
                            'zero_rated' => 'info',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $typeLabel; ?></span>
                      </td>
                      <td><?php echo $tx['exemption_type'] ? ucfirst(str_replace('_', ' ', $tx['exemption_type'])) : '-'; ?></td>
                      <td>₱<?php echo number_format($tx['taxable_amount'], 2); ?></td>
                      <td>₱<?php echo number_format($tx['vat_amount'], 2); ?></td>
                      <td>₱<?php echo number_format($tx['grand_total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-4">No VAT transactions found</td>
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
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
