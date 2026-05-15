<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(__DIR__)) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/refund-confirmations/assets/css/refund-confirmations.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/refund-confirmations.css'); ?>">
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
            case 'combo':
                include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(__DIR__)) . '/includes/navbar.php'; break;
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
                      <h4 class="mb-0 text-primary fw-bold">Refund <span class="text-info fw-medium">Confirmations</span></h4>
                  <h6 class="mb-1 text-primary">  <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a >Home</a></li>
                    <li class="breadcrumb-item active">Refund Confirmations</li>
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

        <!-- Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Pending</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $statCounts['pending_count'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-clock text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Pending Amount</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-6 fw-bold font-sans-serif lh-1 mb-1 text-warning">₱<?php echo number_format($statCounts['pending_amount'] ?? 0, 2); ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-peso-sign text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Approved</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $statCounts['approved_count'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-check-circle text-success fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Rejected</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $statCounts['rejected_count'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-times-circle text-danger fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- How it works -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2" style="cursor:pointer;" onclick="toggleHowItWorks()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0">How it works:</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body" id="howItWorksContent" style="display:none;">
            <ul class="mb-0">
              <li>When a cashier cancels a ticket, it is flagged as <span class="badge bg-soft-warning text-warning">Pending</span> confirmation if the setting requires confirmation.</li>
              <li>A manager or authorized user reviews the cancellation request and approves or rejects it.</li>
              <li>Once <span class="badge bg-soft-success text-success">Approved</span>, the refund is processed to the wallet balance (if enabled). <span class="badge bg-soft-danger text-danger">Rejected</span> cancellations are not processed.</li>
              <li>Pending refunds are tracked in the cashier session but not applied to wallet until approved.</li>
            </ul>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-4">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search transaction, passenger, cashier..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="filterStatus" onchange="applyFilters()">
                  <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                  <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                  <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
              </div>
              <div class="col-md-3">
                <input type="date" class="form-control" id="filterDate" onchange="applyFilters()">
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                  <span class="fas fa-undo me-1"></span>Reset
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Cancellations Table -->
        <div class="card">
          <div class="card-body p-0">
            <?php if (empty($cancellations)): ?>
              <div class="empty-state">
                <div class="empty-state-icon"><span class="fas fa-check-double"></span></div>
                <div class="empty-state-text">No <?php echo strtolower($statusFilter); ?> cancellation requests</div>
                <div class="empty-state-subtext">All <?php echo $statusFilter === 'pending' ? 'requests have been reviewed' : 'matching requests will appear here'; ?>.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="confirmationsTable">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Transaction</th>
                      <th>Passenger / Route</th>
                      <th>Refund Details</th>
                      <th>Cashier</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($cancellations as $c):
                      $statusClass = strtolower($c['status']);
                      $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'completed' => 'primary'];
                      $color = $statusColors[$c['status']] ?? 'secondary';
                    ?>
                    <tr class="cancellation-row"
                        data-status="<?php echo htmlspecialchars($c['status']); ?>"
                        data-search="<?php echo strtolower(htmlspecialchars(
                            ($c['transaction_code'] ?? '') . ' ' .
                            ($c['passenger_name'] ?? '') . ' ' .
                            ($c['requested_by_name'] ?? '') . ' ' .
                            ($c['provider_name'] ?? '')
                        )); ?>"
                        data-date="<?php echo date('Y-m-d', strtotime($c['requested_at'])); ?>">
                      <td class="ps-3 py-3">
                        <div class="fw-semibold"><?php echo htmlspecialchars($c['transaction_code']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($c['provider_name'] ?? '—'); ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?php echo $c['branch_name'] ? htmlspecialchars($c['branch_name']) : '—'; ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?php echo date('M d, Y h:i A', strtotime($c['requested_at'])); ?></div>
                      </td>
                      <td class="py-3">
                        <div class="fw-semibold small"><?php echo htmlspecialchars($c['passenger_name'] ?? '—'); ?></div>
                        <?php if ($c['origin'] && $c['destination']): ?>
                          <div class="text-muted small"><?php echo htmlspecialchars($c['origin'] . ' → ' . $c['destination']); ?></div>
                        <?php endif; ?>
                        <?php if ($c['travel_date']): ?>
                          <div class="text-muted" style="font-size:0.75rem;">Travel: <?php echo date('M d, Y', strtotime($c['travel_date'])); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <div class="fw-semibold text-success fs-6">₱<?php echo number_format($c['refund_amount'], 2); ?></div>
                        <div class="small"><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($c['cancellation_type']); ?></span></div>
                        <?php if ($c['reason']): ?>
                          <div class="mt-1 text-muted small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($c['reason']); ?>">
                            <?php echo htmlspecialchars($c['reason']); ?>
                          </div>
                        <?php else: ?>
                          <div class="text-muted small">No reason</div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <div class="fw-semibold small"><?php echo htmlspecialchars($c['requested_by_name'] ?? '—'); ?></div>
                        <?php if ($c['approved_by_name']): ?>
                          <div class="text-muted" style="font-size:0.75rem;">
                            Reviewed by: <?php echo htmlspecialchars($c['approved_by_name']); ?>
                          </div>
                          <div class="text-muted" style="font-size:0.75rem;">
                            <?php echo date('M d h:i A', strtotime($c['approved_at'])); ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <span class="badge status-badge-<?php echo $statusClass; ?> fs-10 px-3 py-2">
                          <?php
                          $icons = ['pending' => 'fa-clock', 'approved' => 'fa-check-circle', 'rejected' => 'fa-times-circle', 'completed' => 'fa-check-double'];
                          echo '<span class="fas ' . ($icons[$c['status']] ?? 'fa-circle') . ' me-1"></span>';
                          echo htmlspecialchars(ucfirst($c['status']));
                          ?>
                        </span>
                      </td>
                      <td class="py-3 text-end pe-3">
                        <?php if ($c['status'] === 'pending'): ?>
                          <button class="btn btn-sm btn-success me-1" title="Review"
                            onclick="openConfirmModal(
                              <?php echo $c['cancellation_id']; ?>,
                              '<?php echo htmlspecialchars($c['transaction_code'], ENT_QUOTES); ?>',
                              '<?php echo number_format($c['refund_amount'], 2); ?>',
                              '<?php echo htmlspecialchars($c['cancellation_type'], ENT_QUOTES); ?>',
                              '<?php echo htmlspecialchars($c['reason'] ?? '—', ENT_QUOTES); ?>',
                              '<?php echo htmlspecialchars($c['requested_by_name'] ?? '—', ENT_QUOTES); ?>',
                              '<?php echo htmlspecialchars($c['passenger_name'] ?? '—', ENT_QUOTES); ?>',
                              '<?php echo htmlspecialchars($c['origin'] ?? '', ENT_QUOTES); ?>',
                              '<?php echo htmlspecialchars($c['destination'] ?? '', ENT_QUOTES); ?>',
                              '<?php echo date('M d, Y h:i A', strtotime($c['requested_at'])); ?>'
                            )">
                            <span class="fas fa-check-double me-1"></span>Review
                          </button>
                        <?php else: ?>
                          <span class="text-muted small">Reviewed</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div id="noResultsMsg" class="text-center py-4 d-none">
          <span class="fas fa-search text-muted fs-3 d-block mb-2"></span>
          <p class="text-muted">No results match your filters.</p>
        </div>

      </div>
    </div>
  </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/confirm_cancellation.php'; ?>

  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/refund-confirmations/assets/js/refund-confirmations.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/refund-confirmations.js'); ?>"></script>
</body>
</html>
