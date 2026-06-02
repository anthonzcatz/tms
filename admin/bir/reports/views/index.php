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
                      <h4 class="mb-0 text-primary fw-bold">BIR Reports</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">Reports</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto">
                    <a href="<?php echo BASE_URL; ?>/admin/bir/reports/" class="btn btn-sm btn-outline-secondary">
                      <span class="fas fa-arrow-left me-1"></span>Back
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

        <!-- Report Generation Form -->
        <div class="card mb-4">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-file-alt me-2"></span>Generate Report</h6>
          </div>
          <div class="card-body">
            <form method="POST" class="row g-3">
              <input type="hidden" name="action" value="generate">
              
              <div class="col-md-3">
                <label class="form-label">Report Type <span class="text-danger">*</span></label>
                <select name="report_type" class="form-select" required>
                  <option value="">Select Report</option>
                  <option value="DSR">Daily Sales Report (DSR)</option>
                  <option value="Monthly">Monthly Sales Report</option>
                  <option value="SLS">Summary List of Sales (SLS)</option>
                  <option value="Alphalist">Alphalist of Purchases</option>
                  <option value="2550M">VAT Return (2550M)</option>
                </select>
              </div>
              
              <div class="col-md-3">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select">
                  <option value="">All Branches</option>
                  <?php foreach ($branches as $branch): ?>
                  <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              
              <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
              </div>
              
              <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" id="generateReportBtn" class="btn btn-primary w-100">
                  <span class="fas fa-cog me-1"></span>Generate
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Report Preview (if generated) -->
        <?php if ($reportData): ?>
        <div class="card mb-4 border-primary">
          <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><?php echo htmlspecialchars($reportData['report_type'] ?? ''); ?> Report Preview</h6>
              <button class="btn btn-sm btn-light" onclick="window.print()">
                <span class="fas fa-print me-1"></span>Print
              </button>
            </div>
          </div>
          <div class="card-body">
            <?php if (($reportData['report_type'] ?? '') === 'DSR'): ?>
              <!-- DSR Preview -->
              <h6 class="border-bottom pb-2 mb-3">Daily Summary - <?php echo date('F d, Y', strtotime($reportData['date'] ?? '')); ?></h6>
              <div class="row mb-4">
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['total_transactions'] ?? 0); ?></h4>
                    <small class="text-muted">Transactions</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['summary']['total_sales'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Total Sales</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">₱<?php echo number_format($reportData['summary']['total_vat'] ?? 0, 2); ?></h4>
                    <small class="text-muted">VAT</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-warning"><?php echo number_format(($reportData['summary']['refunded_count'] ?? 0) + ($reportData['summary']['cancelled_count'] ?? 0)); ?></h4>
                    <small class="text-muted">Refunded/Cancelled</small>
                  </div>
                </div>
              </div>
            <?php elseif (($reportData['report_type'] ?? '') === 'Alphalist'): ?>
              <!-- Alphalist Preview -->
              <h6 class="border-bottom pb-2 mb-3">Alphalist of Purchases - <?php echo date('F d, Y', strtotime($reportData['period_start'] ?? '')) . ' to ' . date('F d, Y', strtotime($reportData['period_end'] ?? '')); ?></h6>
              <div class="row mb-4">
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['transaction_count'] ?? 0); ?></h4>
                    <small class="text-muted">Purchases</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['summary']['total_purchases'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Total Purchases</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">₱<?php echo number_format($reportData['summary']['total_vat_input'] ?? 0, 2); ?></h4>
                    <small class="text-muted">VAT Input</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-warning">₱<?php echo number_format($reportData['summary']['total_non_vat'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Non-VAT</small>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Transaction Code</th>
                      <th>Type</th>
                      <th>Bank/Account</th>
                      <th>Amount</th>
                      <th>VAT Input</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reportData['purchases'] ?? [] as $purchase): ?>
                    <tr>
                      <td><?php echo date('M d, Y', strtotime($purchase['created_at'] ?? '')); ?></td>
                      <td><?php echo htmlspecialchars($purchase['txn_code'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($purchase['txn_type'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars(($purchase['bank_name'] ?? '') . ' - ' . ($purchase['account_name'] ?? '')); ?></td>
                      <td>₱<?php echo number_format($purchase['amount'] ?? 0, 2); ?></td>
                      <td>₱<?php echo number_format((($purchase['amount'] ?? 0) / 1.12 * 0.12), 2); ?></td>
                      <td><?php echo htmlspecialchars($purchase['remarks'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php elseif (($reportData['report_type'] ?? '') === 'Monthly'): ?>
              <!-- Monthly Report Preview -->
              <h6 class="border-bottom pb-2 mb-3">Monthly Sales Report - <?php echo date('F d, Y', strtotime($reportData['period_start'] ?? '')) . ' to ' . date('F d, Y', strtotime($reportData['period_end'] ?? '')); ?></h6>
              <div class="row mb-4">
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['total_transactions'] ?? 0); ?></h4>
                    <small class="text-muted">Transactions</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['summary']['total_sales'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Total Sales</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">₱<?php echo number_format($reportData['summary']['total_vat'] ?? 0, 2); ?></h4>
                    <small class="text-muted">VAT</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-warning">₱<?php echo number_format($reportData['summary']['taxable_sales'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Taxable Sales</small>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Transactions</th>
                      <th>Total Sales</th>
                      <th>VAT Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reportData['daily_breakdown'] ?? [] as $day): ?>
                    <tr>
                      <td><?php echo date('M d, Y', strtotime($day['date'] ?? '')); ?></td>
                      <td><?php echo number_format($day['transaction_count'] ?? 0); ?></td>
                      <td>₱<?php echo number_format($day['total_sales'] ?? 0, 2); ?></td>
                      <td>₱<?php echo number_format($day['vat_amount'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php elseif (($reportData['report_type'] ?? '') === 'SLS'): ?>
              <!-- SLS Preview -->
              <h6 class="border-bottom pb-2 mb-3">Summary List of Sales - <?php echo date('F d, Y', strtotime($reportData['period_start'] ?? '')) . ' to ' . date('F d, Y', strtotime($reportData['period_end'] ?? '')); ?></h6>
              <div class="row mb-4">
                <div class="col-md-4">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['total_transactions'] ?? 0); ?></h4>
                    <small class="text-muted">Transactions</small>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['summary']['total_sales'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Total Sales</small>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">₱<?php echo number_format($reportData['summary']['total_vat'] ?? 0, 2); ?></h4>
                    <small class="text-muted">VAT</small>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Order Code</th>
                      <th>Branch</th>
                      <th>Total</th>
                      <th>VAT</th>
                      <th>VAT Type</th>
                      <th>Status</th>
                      <th>OR Number</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reportData['transactions'] ?? [] as $txn): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'] ?? '')); ?></td>
                      <td><?php echo htmlspecialchars($txn['order_code'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($txn['branch_name'] ?? ''); ?></td>
                      <td>₱<?php echo number_format($txn['grand_total'] ?? 0, 2); ?></td>
                      <td>₱<?php echo number_format($txn['vat_amount'] ?? 0, 2); ?></td>
                      <td><?php echo htmlspecialchars($txn['vat_type'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($txn['status'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($txn['or_full_number'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php elseif (($reportData['report_type'] ?? '') === '2550M'): ?>
              <!-- 2550M Preview -->
              <h6 class="border-bottom pb-2 mb-3">VAT Return (2550M) - <?php echo date('F d, Y', strtotime($reportData['period_start'] ?? '')) . ' to ' . date('F d, Y', strtotime($reportData['period_end'] ?? '')); ?></h6>
              <div class="row mb-4">
                <div class="col-md-6">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['output_vat']['output_vat'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Output VAT</small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">Total Transactions: <?php echo count($reportData['vat_breakdown'] ?? []); ?></h4>
                    <small class="text-muted">VAT Breakdown Entries</small>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th>VAT Type</th>
                      <th>Transactions</th>
                      <th>Total Sales</th>
                      <th>VAT Amount</th>
                      <th>Taxable Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reportData['vat_breakdown'] ?? [] as $vat): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($vat['vat_type'] ?? ''); ?></td>
                      <td><?php echo number_format($vat['transaction_count'] ?? 0); ?></td>
                      <td>₱<?php echo number_format($vat['total_sales'] ?? 0, 2); ?></td>
                      <td>₱<?php echo number_format($vat['vat_amount'] ?? 0, 2); ?></td>
                      <td>₱<?php echo number_format($vat['taxable_amount'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <h6 class="alert-heading">Report Type: <?php echo htmlspecialchars($reportData['report_type'] ?? 'Unknown'); ?></h6>
                <p class="mb-0">This report type does not have a preview template yet. Please download the JSON file to view the full data.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Generated Reports History -->
        <div class="card">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-history me-2"></span>Generated Reports History</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Report Type</th>
                    <th>Period</th>
                    <th>Branch</th>
                    <th>Generated By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($reports)): ?>
                    <?php foreach ($reports as $report): ?>
                    <tr>
                      <td>
                        <span class="badge bg-<?php 
                          echo match($report['report_type']) {
                            'DSR' => 'primary',
                            'Monthly' => 'success',
                            'SLS' => 'info',
                            'Alphalist' => 'secondary',
                            '2550M' => 'warning',
                            default => 'secondary'
                          };
                        ?>">
                          <?php echo $report['report_type']; ?>
                        </span>
                      </td>
                      <td>
                        <?php 
                        if ($report['report_period_start'] && $report['report_period_end']) {
                            echo date('M d', strtotime($report['report_period_start'])) . ' - ' . date('M d, Y', strtotime($report['report_period_end']));
                        } else {
                            echo date('M d, Y', strtotime($report['report_date']));
                        }
                        ?>
                      </td>
                      <td><?php echo $report['branch_name'] ? htmlspecialchars($report['branch_name']) : 'All Branches'; ?></td>
                      <td><?php echo htmlspecialchars($report['generated_by_name']); ?></td>
                      <td><?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $report['status'] === 'generated' ? 'success' : ($report['status'] === 'submitted' ? 'primary' : 'info'); ?>">
                          <?php echo ucfirst($report['status']); ?>
                        </span>
                      </td>
                      <td>
                        <?php $displayId = !empty($report['encoded_id']) ? $report['encoded_id'] : $report['report_id']; ?>
                        <button class="btn btn-sm btn-outline-primary view-report-btn" data-report-id="<?php echo htmlspecialchars($displayId); ?>" title="View">
                          <span class="fas fa-eye"></span>
                        </button>
                        <div class="btn-group">
                          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Download">
                            <span class="fas fa-download"></span>
                          </button>
                          <ul class="dropdown-menu">
                            <li><a class="dropdown-item download-report-btn" href="#" data-report-id="<?php echo htmlspecialchars($displayId); ?>" data-format="json">
                              <span class="fas fa-file-code me-2"></span>JSON
                            </a></li>
                            <li><a class="dropdown-item download-report-btn" href="#" data-report-id="<?php echo htmlspecialchars($displayId); ?>" data-format="csv">
                              <span class="fas fa-file-csv me-2"></span>CSV (Excel)
                            </a></li>
                            <li><a class="dropdown-item download-report-btn" href="#" data-report-id="<?php echo htmlspecialchars($displayId); ?>" data-format="html">
                              <span class="fas fa-file-alt me-2"></span>HTML (Print)
                            </a></li>
                          </ul>
                        </div>
                        <button class="btn btn-sm btn-outline-danger delete-report-btn" data-report-id="<?php echo htmlspecialchars($displayId); ?>" title="Delete">
                          <span class="fas fa-trash"></span>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-4">No reports generated yet</td>
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Report generation form with loading state
      const form = document.querySelector('form');
      if (form) {
        form.addEventListener('submit', function(e) {
          const btn = document.getElementById('generateReportBtn');
          if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="fas fa-spinner fa-spin me-1"></span>Generating...';
          }
        });
      }

      // View report button functionality
      document.querySelectorAll('.view-report-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const reportId = this.getAttribute('data-report-id');

          if (!reportId) {
            alert('Invalid report ID');
            return;
          }

          // Redirect to view the report
          window.location.href = '<?php echo BASE_URL; ?>/admin/bir/reports/?view=' + encodeURIComponent(reportId);
        });
      });

      // Download report button functionality
      document.querySelectorAll('.download-report-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const reportId = this.getAttribute('data-report-id');
          const format = this.getAttribute('data-format') || 'json';

          if (!reportId) {
            alert('Invalid report ID');
            return;
          }

          // Redirect to download the report with format
          window.location.href = '<?php echo BASE_URL; ?>/admin/bir/reports/?download=' + encodeURIComponent(reportId) + '&format=' + format;
        });
      });

      // Delete report button functionality
      document.querySelectorAll('.delete-report-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const reportId = this.getAttribute('data-report-id');
          const originalIcon = this.innerHTML;

          if (confirm('Are you sure you want to delete this report?')) {
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';

            // Create a form to send POST request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>/admin/bir/reports/';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const reportIdInput = document.createElement('input');
            reportIdInput.type = 'hidden';
            reportIdInput.name = 'report_id';
            reportIdInput.value = reportId;

            form.appendChild(actionInput);
            form.appendChild(reportIdInput);
            document.body.appendChild(form);
            form.submit();
          }
        });
      });

      // Auto-dismiss alerts after 5 seconds
      setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        });
      }, 5000);
    });
  </script>
</body>
</html>
