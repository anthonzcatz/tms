<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css">
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
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary">
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
                <button type="submit" class="btn btn-primary w-100">
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
              <h6 class="mb-0"><?php echo $reportData['report_type']; ?> Report Preview</h6>
              <button class="btn btn-sm btn-light" onclick="window.print()">
                <span class="fas fa-print me-1"></span>Print
              </button>
            </div>
          </div>
          <div class="card-body">
            <?php if ($reportData['report_type'] === 'DSR'): ?>
              <!-- DSR Preview -->
              <h6 class="border-bottom pb-2 mb-3">Daily Summary - <?php echo date('F d, Y', strtotime($reportData['date'])); ?></h6>
              <div class="row mb-4">
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-primary"><?php echo number_format($reportData['summary']['total_transactions']); ?></h4>
                    <small class="text-muted">Transactions</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-success">₱<?php echo number_format($reportData['summary']['total_sales'], 2); ?></h4>
                    <small class="text-muted">Total Sales</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-info">₱<?php echo number_format($reportData['summary']['total_vat'], 2); ?></h4>
                    <small class="text-muted">VAT</small>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-3 text-center">
                    <h4 class="text-warning"><?php echo number_format($reportData['summary']['void_count'] + $reportData['summary']['cancelled_count']); ?></h4>
                    <small class="text-muted">Void/Cancelled</small>
                  </div>
                </div>
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
                        <button class="btn btn-sm btn-outline-primary" title="View">
                          <span class="fas fa-eye"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-success" title="Export">
                          <span class="fas fa-download"></span>
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
</body>
</html>
