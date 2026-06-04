<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/css/bir.css'); ?>">
<style>
/* ── BIR Reports Page ────────────────────────────────────── */
.rpt-type-card { cursor:pointer; border:2px solid transparent; transition:all .18s; }
.rpt-type-card:hover, .rpt-type-card.selected { border-color:#0d6efd; background:#f0f5ff; }
.rpt-type-card.selected .rpt-icon { color:#0d6efd !important; }
.rpt-icon { font-size:1.6rem; margin-bottom:6px; }

/* Summary stat cards */
.bir-stat { border-left:4px solid; border-radius:6px; }
.bir-stat.primary  { border-color:#0d6efd; background:#f0f5ff; }
.bir-stat.success  { border-color:#198754; background:#f0faf5; }
.bir-stat.info     { border-color:#0dcaf0; background:#f0fafe; }
.bir-stat.warning  { border-color:#ffc107; background:#fffbf0; }
.bir-stat.danger   { border-color:#dc3545; background:#fff5f5; }
.bir-stat .stat-val { font-size:1.3rem; font-weight:700; line-height:1.1; }
.bir-stat .stat-lbl { font-size:.72rem; text-transform:uppercase; letter-spacing:.4px; color:#6c757d; margin-top:2px; }

/* History table */
#reportsTableWrapper { overflow:visible !important; }
.reports-table th { font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
.reports-table td { font-size:.83rem; vertical-align:middle; }
.reports-table .btn-sm { padding:2px 8px; font-size:.75rem; }

/* Preview card */
.preview-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; border-bottom:1px solid #dee2e6; padding-bottom:4px; margin:16px 0 8px; }
.preview-table th { font-size:.75rem; background:#dce6f1; white-space:nowrap; }
.preview-table td { font-size:.8rem; }

/* Offcanvas report viewer */
#reportViewOffcanvas { width: 680px !important; }
.ofc-stat { border-radius:8px; padding:14px 16px; }
.ofc-section-title { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; border-bottom:1px solid #dee2e6; padding-bottom:4px; margin:18px 0 10px; }
.ofc-table th { font-size:.72rem; background:#f0f5ff; white-space:nowrap; }
.ofc-table td { font-size:.78rem; }
</style>
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

        <!-- ── Page Header ──────────────────────────────────────────── -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
          <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
              <div class="col-auto d-flex align-items-center gap-3">
                <div class="avatar avatar-xl bg-primary-subtle rounded-3 d-flex align-items-center justify-content-center">
                  <span class="fas fa-file-invoice-dollar fa-lg text-primary"></span>
                </div>
                <div>
                  <h5 class="mb-0 fw-bold">BIR Reports</h5>
                  <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                      <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                      <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                      <li class="breadcrumb-item active">Reports</li>
                    </ol>
                  </nav>
                </div>
              </div>
              <div class="col-auto">
                <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary">
                  <span class="fas fa-arrow-left me-1"></span>Back to BIR
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Alerts ────────────────────────────────────────────────── -->
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
          <span class="fas fa-check-circle fa-lg"></span>
          <div><?php echo htmlspecialchars($message); ?></div>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
          <span class="fas fa-exclamation-circle fa-lg"></span>
          <div><?php echo htmlspecialchars($error); ?></div>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ── Generate Report Form ──────────────────────────────────── -->
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center gap-2">
            <span class="fas fa-cog text-primary"></span>
            <h6 class="mb-0 fw-semibold">Generate New Report</h6>
          </div>
          <div class="card-body">
            <form method="POST" id="generateForm">
              <input type="hidden" name="action" value="generate">

              <!-- Report type picker tiles -->
              <div class="mb-3">
                <label class="form-label fw-semibold small text-uppercase text-muted">Report Type <span class="text-danger">*</span></label>
                <input type="hidden" name="report_type" id="reportTypeInput" required>
                <div class="row g-2" id="reportTypePicker">
                  <?php
                  $rptTypes = [
                    'DSR'       => ['icon'=>'fa-calendar-day',    'color'=>'text-primary',  'label'=>'Daily Sales',         'desc'=>'Daily summary of all POS transactions'],
                    'Monthly'   => ['icon'=>'fa-calendar-alt',    'color'=>'text-success',  'label'=>'Monthly Sales',       'desc'=>'Month-over-month sales breakdown'],
                    'SLS'       => ['icon'=>'fa-list-alt',        'color'=>'text-info',     'label'=>'Summary List (SLS)',   'desc'=>'Summary list of all sales with OR numbers'],
                    'Alphalist' => ['icon'=>'fa-receipt',         'color'=>'text-secondary','label'=>'Alphalist',           'desc'=>'Alphalist of disbursements/purchases'],
                    '2550M'     => ['icon'=>'fa-percent',         'color'=>'text-warning',  'label'=>'VAT Return 2550M',    'desc'=>'VAT output computation for filing'],
                  ];
                  foreach ($rptTypes as $val => $t): ?>
                  <div class="col-6 col-md">
                    <div class="rpt-type-card card h-100 p-2 text-center rounded" data-value="<?php echo $val; ?>">
                      <div class="rpt-icon <?php echo $t['color']; ?>"><span class="fas <?php echo $t['icon']; ?>"></span></div>
                      <div class="fw-semibold small"><?php echo $t['label']; ?></div>
                      <div class="text-muted" style="font-size:.67rem;"><?php echo $t['desc']; ?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div id="reportTypeError" class="text-danger small mt-1 d-none">Please select a report type.</div>
              </div>

              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Branch</label>
                  <select name="branch_id" class="form-select form-select-sm">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Date From <span class="text-danger">*</span></label>
                  <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-semibold">Date To</label>
                  <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-2">
                  <button type="submit" id="generateReportBtn" class="btn btn-primary btn-sm w-100">
                    <span class="fas fa-cog me-1"></span>Generate
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- ── Report Preview (after generate or view) ───────────────── -->
        <?php if ($reportData): ?>
        <?php
          $rt   = $reportData['report_type'] ?? '';
          $rtLabels = ['DSR'=>'Daily Sales Report','Monthly'=>'Monthly Sales Report','SLS'=>'Summary List of Sales','Alphalist'=>'Alphalist of Purchases','2550M'=>'VAT Return (2550M)'];
          $rtColors = ['DSR'=>'primary','Monthly'=>'success','SLS'=>'info','Alphalist'=>'secondary','2550M'=>'warning'];
          $rtLabel = $rtLabels[$rt] ?? $rt . ' Report';
          $rtColor = $rtColors[$rt] ?? 'primary';

          // Period label
          if (isset($reportData['date'])) {
              $periodLabel = date('F d, Y', strtotime($reportData['date']));
          } elseif (isset($reportData['period_start'])) {
              $periodLabel = date('M d, Y', strtotime($reportData['period_start'])) . ' – ' . date('M d, Y', strtotime($reportData['period_end']));
          } else { $periodLabel = ''; }
        ?>
        <div class="card mb-4 border-0 shadow-sm" id="reportPreviewCard">
          <div class="card-header bg-<?php echo $rtColor; ?> bg-opacity-10 border-<?php echo $rtColor; ?> border-start border-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-<?php echo $rtColor; ?>"><?php echo $rt; ?></span>
              <span class="fw-semibold"><?php echo htmlspecialchars($rtLabel); ?></span>
              <?php if ($periodLabel): ?>
              <span class="text-muted small"><span class="fas fa-calendar-alt me-1"></span><?php echo $periodLabel; ?></span>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <?php
              // find the encoded id for the last generated report if available
              $lastReport = !empty($reports) ? $reports[0] : null;
              $previewId  = $lastReport ? ($lastReport['encoded_id'] ?? $lastReport['report_id']) : null;
              ?>
              <?php if ($previewId): ?>
              <a href="<?php echo BASE_URL; ?>/admin/bir/reports/?download=<?php echo urlencode($previewId); ?>&format=pdf" class="btn btn-sm btn-outline-danger">
                <span class="fas fa-file-pdf me-1"></span>PDF
              </a>
              <a href="<?php echo BASE_URL; ?>/admin/bir/reports/?download=<?php echo urlencode($previewId); ?>&format=excel" class="btn btn-sm btn-outline-success">
                <span class="fas fa-file-excel me-1"></span>Excel
              </a>
              <?php endif; ?>
              <a href="<?php echo BASE_URL; ?>/admin/bir/reports/" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-times me-1"></span>Close
              </a>
            </div>
          </div>
          <div class="card-body">

            <?php /* ── Stat Cards ── */ ?>
            <div class="row g-3 mb-4">
            <?php if ($rt === 'DSR'): $s = $reportData['summary'] ?? []; ?>
              <div class="col-6 col-md-3"><div class="bir-stat primary p-3"><div class="stat-val text-primary"><?php echo number_format($s['total_transactions']??0); ?></div><div class="stat-lbl">Transactions</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat success p-3"><div class="stat-val text-success">₱<?php echo number_format($s['total_sales']??0,2); ?></div><div class="stat-lbl">Total Sales</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat info p-3"><div class="stat-val text-info">₱<?php echo number_format($s['total_vat']??0,2); ?></div><div class="stat-lbl">Total VAT</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat danger p-3"><div class="stat-val text-danger"><?php echo number_format(($s['cancelled_count']??0)+($s['refunded_count']??0)); ?></div><div class="stat-lbl">Cancelled / Refunded</div></div></div>
            <?php elseif ($rt === 'Monthly'): $s = $reportData['summary'] ?? []; ?>
              <div class="col-6 col-md-3"><div class="bir-stat primary p-3"><div class="stat-val text-primary"><?php echo number_format($s['total_transactions']??0); ?></div><div class="stat-lbl">Transactions</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat success p-3"><div class="stat-val text-success">₱<?php echo number_format($s['total_sales']??0,2); ?></div><div class="stat-lbl">Total Sales</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat info p-3"><div class="stat-val text-info">₱<?php echo number_format($s['total_vat']??0,2); ?></div><div class="stat-lbl">Total VAT</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat warning p-3"><div class="stat-val text-warning">₱<?php echo number_format($s['taxable_sales']??0,2); ?></div><div class="stat-lbl">Taxable Sales</div></div></div>
            <?php elseif ($rt === 'SLS'): $s = $reportData['summary'] ?? []; ?>
              <div class="col-6 col-md-4"><div class="bir-stat primary p-3"><div class="stat-val text-primary"><?php echo number_format($s['total_transactions']??0); ?></div><div class="stat-lbl">Transactions</div></div></div>
              <div class="col-6 col-md-4"><div class="bir-stat success p-3"><div class="stat-val text-success">₱<?php echo number_format($s['total_sales']??0,2); ?></div><div class="stat-lbl">Total Sales</div></div></div>
              <div class="col-6 col-md-4"><div class="bir-stat info p-3"><div class="stat-val text-info">₱<?php echo number_format($s['total_vat']??0,2); ?></div><div class="stat-lbl">Total VAT</div></div></div>
            <?php elseif ($rt === 'Alphalist'): $s = $reportData['summary'] ?? []; ?>
              <div class="col-6 col-md-3"><div class="bir-stat primary p-3"><div class="stat-val text-primary"><?php echo number_format($s['transaction_count']??0); ?></div><div class="stat-lbl">Purchases</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat success p-3"><div class="stat-val text-success">₱<?php echo number_format($s['total_purchases']??0,2); ?></div><div class="stat-lbl">Total Purchases</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat info p-3"><div class="stat-val text-info">₱<?php echo number_format($s['total_vat_input']??0,2); ?></div><div class="stat-lbl">VAT Input</div></div></div>
              <div class="col-6 col-md-3"><div class="bir-stat warning p-3"><div class="stat-val text-warning">₱<?php echo number_format($s['total_non_vat']??0,2); ?></div><div class="stat-lbl">Non-VAT</div></div></div>
            <?php elseif ($rt === '2550M'): ?>
              <div class="col-6 col-md-6"><div class="bir-stat success p-3"><div class="stat-val text-success">₱<?php echo number_format($reportData['output_vat']['output_vat']??0,2); ?></div><div class="stat-lbl">Output VAT</div></div></div>
              <div class="col-6 col-md-6"><div class="bir-stat info p-3"><div class="stat-val text-info"><?php echo count($reportData['vat_breakdown']??[]); ?></div><div class="stat-lbl">VAT Breakdown Entries</div></div></div>
            <?php endif; ?>
            </div>

            <?php /* ── Detail Tables ── */ ?>
            <?php if ($rt === 'DSR' && !empty($reportData['hourly_sales'])): ?>
              <p class="preview-section-title"><span class="fas fa-clock me-1"></span>Hourly Sales Breakdown</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>Hour</th><th class="text-end">Transactions</th><th class="text-end">Total Sales</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['hourly_sales'] as $h): ?>
                    <tr>
                      <td><?php echo str_pad($h['hour']??0,2,'0',STR_PAD_LEFT).':00'; ?></td>
                      <td class="text-end"><?php echo number_format($h['transaction_count']??0); ?></td>
                      <td class="text-end">₱<?php echo number_format($h['total_sales']??0,2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if (!empty($reportData['payment_breakdown'])): ?>
              <p class="preview-section-title mt-3"><span class="fas fa-credit-card me-1"></span>Payment Breakdown</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>Method</th><th class="text-end">Transactions</th><th class="text-end">Total</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['payment_breakdown'] as $p): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($p['payment_method']??''); ?></td>
                      <td class="text-end"><?php echo number_format($p['transaction_count']??0); ?></td>
                      <td class="text-end">₱<?php echo number_format($p['total_amount']??0,2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php endif; ?>

            <?php elseif ($rt === 'Monthly' && !empty($reportData['daily_breakdown'])): ?>
              <p class="preview-section-title"><span class="fas fa-table me-1"></span>Daily Breakdown</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>Date</th><th class="text-end">Transactions</th><th class="text-end">Total Sales</th><th class="text-end">VAT Amount</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['daily_breakdown'] as $d): ?>
                    <tr>
                      <td><?php echo date('M d, Y', strtotime($d['date']??'')); ?></td>
                      <td class="text-end"><?php echo number_format($d['transaction_count']??0); ?></td>
                      <td class="text-end">₱<?php echo number_format($d['total_sales']??0,2); ?></td>
                      <td class="text-end">₱<?php echo number_format($d['vat_amount']??0,2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            <?php elseif ($rt === 'SLS' && !empty($reportData['transactions'])): ?>
              <p class="preview-section-title"><span class="fas fa-list me-1"></span>Transactions</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>Date</th><th>Order Code</th><th>Branch</th><th class="text-end">Total</th><th class="text-end">VAT</th><th>VAT Type</th><th>Status</th><th>OR #</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['transactions'] as $txn): ?>
                    <tr>
                      <td style="white-space:nowrap"><?php echo date('M d H:i', strtotime($txn['created_at']??'')); ?></td>
                      <td><span class="fw-semibold"><?php echo htmlspecialchars($txn['order_code']??''); ?></span></td>
                      <td><?php echo htmlspecialchars($txn['branch_name']??''); ?></td>
                      <td class="text-end">₱<?php echo number_format($txn['grand_total']??0,2); ?></td>
                      <td class="text-end">₱<?php echo number_format($txn['vat_amount']??0,2); ?></td>
                      <td><span class="badge bg-info-subtle text-info"><?php echo htmlspecialchars($txn['vat_type']??'-'); ?></span></td>
                      <td><?php echo htmlspecialchars(ucfirst($txn['status']??'')); ?></td>
                      <td class="text-success fw-semibold"><?php echo htmlspecialchars($txn['or_full_number']??'-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            <?php elseif ($rt === 'Alphalist' && !empty($reportData['purchases'])): ?>
              <p class="preview-section-title"><span class="fas fa-receipt me-1"></span>Purchases</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>Date</th><th>Code</th><th>Type</th><th>Bank / Account</th><th class="text-end">Amount</th><th class="text-end">VAT Input</th><th>Remarks</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['purchases'] as $p): ?>
                    <tr>
                      <td style="white-space:nowrap"><?php echo date('M d, Y', strtotime($p['created_at']??'')); ?></td>
                      <td><?php echo htmlspecialchars($p['txn_code']??''); ?></td>
                      <td><?php echo htmlspecialchars($p['txn_type']??''); ?></td>
                      <td><?php echo htmlspecialchars(($p['bank_name']??'').($p['account_name']?' – '.$p['account_name']:'')); ?></td>
                      <td class="text-end">₱<?php echo number_format($p['amount']??0,2); ?></td>
                      <td class="text-end">₱<?php echo number_format((float)($p['amount']??0)/1.12*0.12,2); ?></td>
                      <td class="text-muted"><?php echo htmlspecialchars($p['remarks']??'-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            <?php elseif ($rt === '2550M' && !empty($reportData['vat_breakdown'])): ?>
              <p class="preview-section-title"><span class="fas fa-percent me-1"></span>VAT Breakdown</p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered preview-table mb-0">
                  <thead><tr><th>VAT Type</th><th class="text-end">Transactions</th><th class="text-end">Total Sales</th><th class="text-end">VAT Amount</th><th class="text-end">Taxable Amount</th></tr></thead>
                  <tbody>
                    <?php foreach ($reportData['vat_breakdown'] as $v): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($v['vat_type']??''); ?></td>
                      <td class="text-end"><?php echo number_format($v['transaction_count']??0); ?></td>
                      <td class="text-end">₱<?php echo number_format($v['total_sales']??0,2); ?></td>
                      <td class="text-end fw-semibold text-warning">₱<?php echo number_format($v['vat_amount']??0,2); ?></td>
                      <td class="text-end">₱<?php echo number_format($v['taxable_amount']??0,2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endif; ?>

        <!-- ── Generated Reports History ────────────────────────────── -->
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <span class="fas fa-history text-muted"></span>
              <h6 class="mb-0 fw-semibold">Generated Reports History</h6>
              <?php if (!empty($reports)): ?>
              <span class="badge bg-secondary"><?php echo count($reports); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive" id="reportsTableWrapper">
              <table class="table table-hover table-sm mb-0 reports-table">
                <thead class="table-light">
                  <tr>
                    <th>Type</th>
                    <th>Period</th>
                    <th>Branch</th>
                    <th>Generated By</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($reports)): ?>
                    <?php foreach ($reports as $report):
                      $did = !empty($report['encoded_id']) ? $report['encoded_id'] : $report['report_id'];
                      $rColor = match($report['report_type']) {
                        'DSR'=>'primary','Monthly'=>'success','SLS'=>'info','Alphalist'=>'secondary','2550M'=>'warning', default=>'secondary'
                      };
                    ?>
                    <tr>
                      <td>
                        <span class="badge bg-<?php echo $rColor; ?>-subtle text-<?php echo $rColor; ?> border border-<?php echo $rColor; ?>-subtle">
                          <?php echo htmlspecialchars($report['report_type']); ?>
                        </span>
                      </td>
                      <td class="text-nowrap">
                        <?php if ($report['report_period_start'] && $report['report_period_end']): ?>
                          <span class="fas fa-calendar-alt text-muted me-1"></span>
                          <?php echo date('M d', strtotime($report['report_period_start'])) . ' – ' . date('M d, Y', strtotime($report['report_period_end'])); ?>
                        <?php else: ?>
                          <span class="fas fa-calendar-day text-muted me-1"></span>
                          <?php echo date('M d, Y', strtotime($report['report_date'])); ?>
                        <?php endif; ?>
                      </td>
                      <td><?php echo $report['branch_name'] ? htmlspecialchars($report['branch_name']) : '<span class="text-muted fst-italic">All Branches</span>'; ?></td>
                      <td>
                        <span class="fas fa-user-circle text-muted me-1"></span>
                        <?php echo htmlspecialchars($report['generated_by_name'] ?? '—'); ?>
                      </td>
                      <td class="text-nowrap text-muted"><?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $report['status']==='generated'?'success':($report['status']==='submitted'?'primary':'info'); ?>">
                          <?php echo ucfirst($report['status']); ?>
                        </span>
                      </td>
                      <td class="text-center" style="white-space:nowrap">
                        <button class="btn btn-sm btn-outline-primary view-report-btn"
                          data-report-id="<?php echo htmlspecialchars($did); ?>"
                          title="View Report">
                          <span class="fas fa-eye"></span>
                        </button>
                        <div class="btn-group" role="group">
                          <button type="button"
                            class="btn btn-sm btn-outline-success dropdown-toggle"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="true"
                            aria-expanded="false"
                            title="Download">
                            <span class="fas fa-download"></span>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header text-uppercase" style="font-size:.65rem">Download As</h6></li>
                            <li>
                              <a class="dropdown-item download-report-btn" href="#"
                                data-report-id="<?php echo htmlspecialchars($did); ?>" data-format="excel">
                                <span class="fas fa-file-excel me-2 text-success"></span>Excel <span class="text-muted small">(.xls)</span>
                              </a>
                            </li>
                            <li>
                              <a class="dropdown-item download-report-btn" href="#"
                                data-report-id="<?php echo htmlspecialchars($did); ?>" data-format="pdf">
                                <span class="fas fa-file-pdf me-2 text-danger"></span>PDF
                              </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                              <a class="dropdown-item download-report-btn" href="#"
                                data-report-id="<?php echo htmlspecialchars($did); ?>" data-format="csv">
                                <span class="fas fa-file-csv me-2 text-muted"></span>CSV <span class="text-muted small">(plain)</span>
                              </a>
                            </li>
                            <li>
                              <a class="dropdown-item download-report-btn" href="#"
                                data-report-id="<?php echo htmlspecialchars($did); ?>" data-format="json">
                                <span class="fas fa-file-code me-2 text-muted"></span>JSON <span class="text-muted small">(raw)</span>
                              </a>
                            </li>
                          </ul>
                        </div>
                        <button class="btn btn-sm btn-outline-danger delete-report-btn"
                          data-report-id="<?php echo htmlspecialchars($did); ?>"
                          title="Delete">
                          <span class="fas fa-trash"></span>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-5">
                        <span class="fas fa-folder-open fa-2x d-block mb-2 text-muted opacity-50"></span>
                        No reports generated yet
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /.container -->
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>

  <!-- ── Report View Offcanvas ─────────────────────────────────────────── -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="reportViewOffcanvas" aria-labelledby="reportViewOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
      <div>
        <h6 class="offcanvas-title fw-bold mb-0" id="reportViewOffcanvasLabel">
          <span class="fas fa-file-invoice me-2 text-primary"></span>Report Details
        </h6>
        <p class="text-muted small mb-0" id="reportViewSubtitle"></p>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-header border-bottom py-2" id="reportViewActions" style="display:none!important">
      <div class="d-flex gap-2 flex-wrap w-100">
        <a id="ofcPdfBtn" href="#" class="btn btn-sm btn-outline-danger flex-fill">
          <span class="fas fa-file-pdf me-1"></span>PDF
        </a>
        <a id="ofcExcelBtn" href="#" class="btn btn-sm btn-outline-success flex-fill">
          <span class="fas fa-file-excel me-1"></span>Excel
        </a>
        <a id="ofcCsvBtn" href="#" class="btn btn-sm btn-outline-secondary flex-fill">
          <span class="fas fa-file-csv me-1"></span>CSV
        </a>
      </div>
    </div>
    <div class="offcanvas-body" id="reportViewBody">
      <div class="text-center py-5 text-muted" id="reportViewLoading">
        <span class="fas fa-spinner fa-spin fa-2x mb-3 d-block"></span>Loading report…
      </div>
    </div>
  </div>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>

  <script>
  (function () {
    'use strict';
    const BASE = '<?php echo BASE_URL; ?>';

    /* ── helpers ─────────────────────────────────────────────────── */
    const esc  = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const fmt  = n => '₱' + parseFloat(n ?? 0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
    const fmtN = n => parseInt(n ?? 0).toLocaleString('en-PH');
    const fmtD = s => { try { return new Date(s).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}); } catch{ return s||'—'; }};
    const fmtDT= s => { try { return new Date(s).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}); } catch{ return s||'—'; }};

    function statCard(val, lbl, cls) {
      return `<div class="col-6 col-md"><div class="bir-stat ${cls} p-3"><div class="stat-val text-${cls}">${esc(val)}</div><div class="stat-lbl">${esc(lbl)}</div></div></div>`;
    }
    function tHead(cols) {
      return '<thead><tr>' + cols.map(c=>`<th>${esc(c)}</th>`).join('') + '</tr></thead>';
    }
    function tRows(rows) {
      return '<tbody>' + rows.map(r=>'<tr>' + r.map(c=>`<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody>';
    }
    function tableWrap(headers, rows) {
      return `<div class="table-responsive"><table class="table table-sm table-bordered ofc-table mb-0">${tHead(headers)}${tRows(rows)}</table></div>`;
    }
    function sectionTitle(txt) {
      return `<p class="ofc-section-title"><span class="fas fa-chevron-right me-1"></span>${esc(txt)}</p>`;
    }

    function renderReport(data) {
      const rt = data.report_type ?? '';
      let html = '';

      /* Stat cards */
      const rtColors = {DSR:'primary',Monthly:'success',SLS:'info',Alphalist:'secondary','2550M':'warning'};
      const rtBadge  = `<span class="badge bg-${rtColors[rt]||'secondary'}">${esc(rt)}</span>`;
      html += `<div class="d-flex align-items-center gap-2 mb-3">${rtBadge}`;
      if (data.date)         html += `<span class="text-muted small">${fmtD(data.date)}</span>`;
      if (data.period_start) html += `<span class="text-muted small">${fmtD(data.period_start)} – ${fmtD(data.period_end)}</span>`;
      html += '</div>';

      html += '<div class="row g-2 mb-3">';
      if (rt === 'DSR') {
        const s = data.summary ?? {};
        html += statCard(fmtN(s.total_transactions),'Transactions','primary');
        html += statCard(fmt(s.total_sales),'Total Sales','success');
        html += statCard(fmt(s.total_vat),'Total VAT','info');
        html += statCard(fmtN((s.cancelled_count|0)+(s.refunded_count|0)),'Cancelled/Refunded','danger');
      } else if (rt === 'Monthly') {
        const s = data.summary ?? {};
        html += statCard(fmtN(s.total_transactions),'Transactions','primary');
        html += statCard(fmt(s.total_sales),'Total Sales','success');
        html += statCard(fmt(s.total_vat),'Total VAT','info');
        html += statCard(fmt(s.taxable_sales),'Taxable Sales','warning');
      } else if (rt === 'SLS') {
        const s = data.summary ?? {};
        html += statCard(fmtN(s.total_transactions),'Transactions','primary');
        html += statCard(fmt(s.total_sales),'Total Sales','success');
        html += statCard(fmt(s.total_vat),'Total VAT','info');
      } else if (rt === 'Alphalist') {
        const s = data.summary ?? {};
        html += statCard(fmtN(s.transaction_count),'Purchases','primary');
        html += statCard(fmt(s.total_purchases),'Total Purchases','success');
        html += statCard(fmt(s.total_vat_input),'VAT Input','info');
        html += statCard(fmt(s.total_non_vat),'Non-VAT','warning');
      } else if (rt === '2550M') {
        html += statCard(fmt(data.output_vat?.output_vat),'Output VAT','success');
        html += statCard(fmtN((data.vat_breakdown||[]).length),'VAT Entries','info');
      }
      html += '</div>';

      /* Detail tables */
      if (rt === 'DSR') {
        if ((data.hourly_sales||[]).length) {
          html += sectionTitle('Hourly Sales');
          html += tableWrap(
            ['Hour','Transactions','Total Sales'],
            (data.hourly_sales).map(r => [
              `${String(r.hour||0).padStart(2,'0')}:00`,
              `<span class="text-end d-block">${fmtN(r.transaction_count)}</span>`,
              `<span class="text-end d-block">${fmt(r.total_sales)}</span>`
            ])
          );
        }
        if ((data.payment_breakdown||[]).length) {
          html += sectionTitle('Payment Breakdown');
          html += tableWrap(
            ['Method','Transactions','Total'],
            (data.payment_breakdown).map(r => [
              esc(r.payment_method),
              `<span class="text-end d-block">${fmtN(r.transaction_count)}</span>`,
              `<span class="text-end d-block">${fmt(r.total_amount)}</span>`
            ])
          );
        }
      } else if (rt === 'Monthly' && (data.daily_breakdown||[]).length) {
        html += sectionTitle('Daily Breakdown');
        html += tableWrap(
          ['Date','Transactions','Total Sales','VAT'],
          (data.daily_breakdown).map(r => [
            fmtD(r.date),
            `<span class="text-end d-block">${fmtN(r.transaction_count)}</span>`,
            `<span class="text-end d-block">${fmt(r.total_sales)}</span>`,
            `<span class="text-end d-block">${fmt(r.vat_amount)}</span>`
          ])
        );
      } else if (rt === 'SLS' && (data.transactions||[]).length) {
        html += sectionTitle('Transactions');
        html += tableWrap(
          ['Date','Order','Branch','Total','VAT','Status','OR #'],
          (data.transactions).map(r => [
            fmtDT(r.created_at),
            `<strong>${esc(r.order_code)}</strong>`,
            esc(r.branch_name),
            `<span class="text-end d-block">${fmt(r.grand_total)}</span>`,
            `<span class="text-end d-block">${fmt(r.vat_amount)}</span>`,
            `<span class="badge bg-success-subtle text-success">${esc(r.status)}</span>`,
            `<span class="text-success fw-semibold">${esc(r.or_full_number||'—')}</span>`
          ])
        );
      } else if (rt === 'Alphalist' && (data.purchases||[]).length) {
        html += sectionTitle('Purchases');
        html += tableWrap(
          ['Date','Code','Type','Bank','Amount','VAT In','Remarks'],
          (data.purchases).map(r => [
            fmtD(r.created_at),
            esc(r.txn_code),
            esc(r.txn_type),
            esc((r.bank_name||'')+(r.account_name?' – '+r.account_name:'')),
            `<span class="text-end d-block">${fmt(r.amount)}</span>`,
            `<span class="text-end d-block">${fmt((+r.amount||0)/1.12*0.12)}</span>`,
            `<span class="text-muted">${esc(r.remarks||'—')}</span>`
          ])
        );
      } else if (rt === '2550M' && (data.vat_breakdown||[]).length) {
        html += sectionTitle('VAT Breakdown');
        html += tableWrap(
          ['VAT Type','Transactions','Total Sales','VAT Amount','Taxable'],
          (data.vat_breakdown).map(r => [
            esc(r.vat_type),
            `<span class="text-end d-block">${fmtN(r.transaction_count)}</span>`,
            `<span class="text-end d-block">${fmt(r.total_sales)}</span>`,
            `<span class="text-end d-block fw-bold text-warning">${fmt(r.vat_amount)}</span>`,
            `<span class="text-end d-block">${fmt(r.taxable_amount)}</span>`
          ])
        );
      }

      return html;
    }

    /* ── Report type tile picker ──────────────────────────────────── */
    document.querySelectorAll('#reportTypePicker .rpt-type-card').forEach(card => {
      card.addEventListener('click', () => {
        document.querySelectorAll('#reportTypePicker .rpt-type-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('reportTypeInput').value = card.dataset.value;
        document.getElementById('reportTypeError').classList.add('d-none');
      });
    });

    /* ── Generate form submit ─────────────────────────────────────── */
    document.getElementById('generateForm')?.addEventListener('submit', function(e) {
      const rType = document.getElementById('reportTypeInput').value;
      if (!rType) {
        e.preventDefault();
        document.getElementById('reportTypeError').classList.remove('d-none');
        return;
      }
      const btn = document.getElementById('generateReportBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="fas fa-spinner fa-spin me-1"></span>Generating…';
    });

    /* ── View report → offcanvas (AJAX fetch of ?view=ID&ajax=1) ──── */
    document.querySelectorAll('.view-report-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const reportId = this.dataset.reportId;
        if (!reportId) return;

        const ofc     = new bootstrap.Offcanvas(document.getElementById('reportViewOffcanvas'));
        const body    = document.getElementById('reportViewBody');
        const loading = document.getElementById('reportViewLoading');
        const sub     = document.getElementById('reportViewSubtitle');
        const actions = document.getElementById('reportViewActions');

        body.innerHTML = '';
        loading.style.display = '';
        body.appendChild(loading);
        sub.textContent = '';
        actions.style.display = 'none !important';

        ofc.show();

        fetch(`${BASE}/admin/bir/reports/?view=${encodeURIComponent(reportId)}&ajax=1`)
          .then(r => r.json())
          .then(res => {
            loading.style.display = 'none';
            if (!res.success) {
              body.innerHTML = `<div class="alert alert-danger">${esc(res.error||'Failed to load report.')}</div>`;
              return;
            }
            const data = res.data;
            sub.textContent = res.meta?.period || '';

            // set download links
            const pdfUrl   = `${BASE}/admin/bir/reports/?download=${encodeURIComponent(reportId)}&format=pdf`;
            const excelUrl = `${BASE}/admin/bir/reports/?download=${encodeURIComponent(reportId)}&format=excel`;
            const csvUrl   = `${BASE}/admin/bir/reports/?download=${encodeURIComponent(reportId)}&format=csv`;
            document.getElementById('ofcPdfBtn').href   = pdfUrl;
            document.getElementById('ofcExcelBtn').href = excelUrl;
            document.getElementById('ofcCsvBtn').href   = csvUrl;
            actions.removeAttribute('style');
            actions.style.display = '';

            body.innerHTML = renderReport(data);
          })
          .catch(() => {
            loading.style.display = 'none';
            body.innerHTML = '<div class="alert alert-danger">Failed to load report. Please try again.</div>';
          });
      });
    });

    /* ── Download buttons ─────────────────────────────────────────── */
    document.querySelectorAll('.download-report-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const reportId = this.dataset.reportId;
        const format   = this.dataset.format || 'json';
        if (!reportId) return;
        const url = `${BASE}/admin/bir/reports/?download=${encodeURIComponent(reportId)}&format=${format}`;
        if (format === 'pdf') { window.location.href = url; }
        else { window.location.href = url; }
      });
    });

    /* ── Delete buttons ───────────────────────────────────────────── */
    document.querySelectorAll('.delete-report-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('Delete this report? This cannot be undone.')) return;
        const reportId = this.dataset.reportId;
        this.disabled = true;
        this.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = `${BASE}/admin/bir/reports/`;
        f.innerHTML = `<input type="hidden" name="action" value="delete">
                       <input type="hidden" name="report_id" value="${esc(reportId)}">`;
        document.body.appendChild(f);
        f.submit();
      });
    });

    /* ── Auto-dismiss alerts ──────────────────────────────────────── */
    setTimeout(() => {
      document.querySelectorAll('.alert.fade').forEach(el => {
        try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){}
      });
    }, 5000);

  })();
  </script>
</body>
</html>
