<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(__DIR__)) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/shifts/assets/css/shifts.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/shifts.css'); ?>">
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
      <?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':    include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; break;
            case 'vertical': include dirname(dirname(__DIR__)) . '/includes/navbar.php';    break;
        }
        ?>


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
                      <h4 class="mb-0 text-primary fw-bold">Cashier <span class="text-info fw-medium">Shift Reports</span></h4>
                  <h6 class="mb-1 text-primary">  <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a >Home</a></li>
                    <li class="breadcrumb-item active">Cashier Shifts</li>
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

        <!-- Summary Stats -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-body py-3">
                <h6 class="pb-1 text-700 mb-1">Total Sessions</h6>
                <p class="font-sans-serif lh-1 mb-0 fs-5 fw-bold"><?php echo $summary['total_sessions'] ?? 0; ?></p>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-body py-3">
                <h6 class="pb-1 text-700 mb-1">Open Sessions</h6>
                <p class="font-sans-serif lh-1 mb-0 fs-5 fw-bold text-success"><?php echo $summary['open_sessions'] ?? 0; ?></p>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-body py-3">
                <h6 class="pb-1 text-700 mb-1">Total Sales</h6>
                <p class="font-sans-serif lh-1 mb-0 fs-5 fw-bold text-success">₱<?php echo number_format($summary['total_sales'] ?? 0, 2); ?></p>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-body py-3">
                <h6 class="pb-1 text-700 mb-1">Total Cash</h6>
                <p class="font-sans-serif lh-1 mb-0 fs-5 fw-bold text-primary">₱<?php echo number_format($summary['total_cash'] ?? 0, 2); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Date & Branch Filters -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-center">
              <div class="col-md-3">
                <label class="form-label fw-semibold mb-1 small">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
              </div>
              <?php if ($userRoleCode === 'SUPER_ADMIN'): ?>
              <div class="col-md-3">
                <label class="form-label fw-semibold mb-1 small">Branch</label>
                <select class="form-select" name="branch">
                  <option value="">All Branches</option>
                  <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo $filterBranch == $b['branch_id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($b['branch_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                  <span class="fas fa-search me-1"></span>View
                </button>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <a href="<?php echo BASE_URL; ?>/admin/shifts" class="btn btn-outline-secondary w-100">
                  <span class="fas fa-undo me-1"></span>Today
                </a>
              </div>
            </form>
          </div>
        </div>

        <!-- Sessions -->
        <?php if (empty($sessions)): ?>
          <div class="card">
            <div class="card-body empty-state">
              <div class="empty-state-icon"><span class="fas fa-clipboard-list"></span></div>
              <div class="empty-state-text">No Sessions Found</div>
              <div class="empty-state-subtext">No cashier sessions for <?php echo date('F d, Y', strtotime($filterDate)); ?>.</div>
            </div>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($sessions as $s):
              $statusColor = ['OPEN' => 'success', 'CLOSED' => 'primary', 'RECONCILED' => 'purple'][$s['status']] ?? 'secondary';
              $statusIcon  = ['OPEN' => 'fa-circle', 'CLOSED' => 'fa-check-circle', 'RECONCILED' => 'fa-star'][$s['status']] ?? 'fa-circle';
              $borderClass = 'session-' . strtolower($s['status']);
              $variance    = floatval($s['cash_variance'] ?? 0);
              $varClass    = $variance > 0.005 ? 'variance-positive' : ($variance < -0.005 ? 'variance-negative' : 'variance-zero');
            ?>
            <div class="col-lg-6">
              <div class="card shift-card <?php echo $borderClass; ?> h-100">
                <div class="card-header py-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <div class="d-flex align-items-center">
                        <div class="icon-circle icon-circle-<?php echo $statusColor; ?> me-2"><span class="fas fa-user text-<?php echo $statusColor; ?>"></span></div>
                        <div>
                          <span class="fw-bold"><?php echo htmlspecialchars($s['cashier_name']); ?></span>
                          <div class="text-muted small"><?php echo htmlspecialchars($s['branch_name'] ?? '—'); ?></div>
                        </div>
                      </div>
                    </div>
                    <span class="badge bg-soft-<?php echo $statusColor; ?> text-<?php echo $statusColor; ?>">
                      <span class="fas <?php echo $statusIcon; ?> me-1"></span><?php echo $s['status']; ?>
                    </span>
                  </div>
                  <div class="text-muted small mt-2">
                    <span class="fas fa-clock me-1"></span><?php echo $s['session_code'] ?? 'SES-' . $s['session_id']; ?> •
                    Started: <?php echo date('h:i A', strtotime($s['started_at'])); ?>
                    <?php if ($s['ended_at']): ?>
                      — Closed: <?php echo date('h:i A', strtotime($s['ended_at'])); ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-body py-3">
                  <div class="row g-3">
                    <div class="col-6">
                      <div class="card bg-light h-100">
                        <div class="card-body py-2 text-center">
                          <div class="text-muted small mb-1">Opening Cash</div>
                          <div class="fw-semibold">₱<?php echo number_format($s['starting_cash'], 2); ?></div>
                        </div>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="card bg-light h-100">
                        <div class="card-body py-2 text-center">
                          <div class="text-muted small mb-1">Total Sales</div>
                          <div class="fw-bold text-success">₱<?php echo number_format($s['total_sales'], 2); ?></div>
                        </div>
                      </div>
                    </div>
                    <?php if ($s['status'] !== 'OPEN'): ?>
                    <div class="col-6">
                      <div class="card bg-light h-100">
                        <div class="card-body py-2 text-center">
                          <div class="text-muted small mb-1">Expected Cash</div>
                          <div class="fw-semibold">₱<?php echo number_format($s['expected_cash'] ?? 0, 2); ?></div>
                        </div>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="card bg-light h-100">
                        <div class="card-body py-2 text-center">
                          <div class="text-muted small mb-1">Actual Cash</div>
                          <div class="fw-semibold">₱<?php echo number_format($s['actual_cash'] ?? 0, 2); ?></div>
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="card bg-light h-100">
                        <div class="card-body py-2">
                          <div class="d-flex justify-content-between align-items-center">
                            <div>
                              <div class="text-muted small mb-1">Variance</div>
                              <div class="<?php echo $varClass; ?> fs-6 fw-bold">
                                <?php echo ($variance >= 0 ? '+' : '') . '₱' . number_format(abs($variance), 2); ?>
                              </div>
                            </div>
                            <div>
                              <?php if (abs($variance) < 0.005): ?>
                                <span class="badge bg-soft-success text-success">Balanced</span>
                              <?php elseif ($variance < 0): ?>
                                <span class="badge bg-soft-danger text-danger">Short</span>
                              <?php else: ?>
                                <span class="badge bg-soft-warning text-warning">Over</span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-footer py-3 d-flex justify-content-between align-items-center">
                  <?php if ($s['reviewed_by_name']): ?>
                    <span class="text-muted small"><span class="fas fa-user-check me-1 text-success"></span>Reviewed by <?php echo htmlspecialchars($s['reviewed_by_name']); ?></span>
                  <?php else: ?>
                    <span class="text-muted small">Not yet reviewed</span>
                  <?php endif; ?>
                  <button class="btn btn-sm btn-primary" onclick="viewSessionDetail(<?php echo $s['session_id']; ?>)">
                    <span class="fas fa-eye me-1"></span>Details
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/session_detail.php'; ?>

  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/shifts/assets/js/shifts.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/shifts.js'); ?>"></script>
</body>
</html>
