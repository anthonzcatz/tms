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

        <!-- Stats Cards (populated via AJAX) -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Pending</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1" id="statPending">—</div></div>
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
                  <div class="col-auto align-self-end"><div class="fs-6 fw-bold font-sans-serif lh-1 mb-1 text-warning" id="statPendingAmount">—</div></div>
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
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1" id="statApproved">—</div></div>
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
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1" id="statRejected">—</div></div>
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
            <div id="filterForm">
              <div class="row g-3 align-items-end">
                <div class="col-md-3">
                  <label class="form-label small fw-semibold mb-1">Search</label>
                  <div class="search-box">
                    <input type="text" class="form-control search-input" id="filterSearch"
                           placeholder="Transaction, passenger, cashier...">
                    <span class="fas fa-search search-icon"></span>
                  </div>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold mb-1">Status</label>
                  <select class="form-select" id="filterStatus">
                    <option value="pending" selected>Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="completed">Completed</option>
                    <option value="all">All</option>
                  </select>
                </div>
                <?php if ($userRoleCode === 'SUPER_ADMIN'): ?>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold mb-1">Branch</label>
                  <select class="form-select" id="filterBranch">
                    <option value="">All Branches</option>
                    <?php foreach ($allBranches as $b): ?>
                      <option value="<?php echo $b['branch_id']; ?>">
                        <?php echo htmlspecialchars($b['branch_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold mb-1">Wallet</label>
                  <select class="form-select" id="filterWallet">
                    <option value="">All Wallets</option>
                    <?php foreach ($allWallets as $w): ?>
                      <option value="<?php echo $w['wallet_id']; ?>">
                        <?php echo htmlspecialchars($w['wallet_label']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                  <label class="form-label small fw-semibold mb-1">Cashier</label>
                  <input type="text" class="form-control" id="filterCashier" placeholder="Cashier name...">
                </div>
                <div class="col-md-<?php echo $userRoleCode === 'SUPER_ADMIN' ? '3' : '3'; ?>">
                  <label class="form-label small fw-semibold mb-1">Date Range</label>
                  <input type="text" class="form-control" id="filterDateRange" placeholder="Select date range...">
                  <input type="hidden" id="filterDateFrom">
                  <input type="hidden" id="filterDateTo">
                </div>
                <div class="col-md-1 d-flex gap-2">
                  <button type="button" class="btn btn-primary w-100" onclick="loadCancellations(1)" title="Apply Filters">
                    <span class="fas fa-filter"></span>
                  </button>
                  <button type="button" class="btn btn-outline-secondary w-100" onclick="resetFilters()" title="Reset">
                    <span class="fas fa-undo"></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Cancellations Table -->
        <div class="card">
          <div class="card-header bg-body-tertiary py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small" id="tableInfo">Loading...</span>
              <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Per page:</label>
                <select class="form-select form-select-sm" id="perPageSelect" style="width:80px;" onchange="loadCancellations(1)">
                  <option value="10">10</option>
                  <option value="15" selected>15</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
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
                <tbody id="confirmationsTableBody">
                  <tr><td colspan="6" class="text-center py-5 text-muted">
                    <span class="fas fa-spinner fa-spin me-2"></span>Loading...
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer bg-body-tertiary" id="paginationContainer">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small" id="paginationInfo"></span>
              <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/confirm_cancellation.php'; ?>

  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script>
    window.REFUND_CONF_CONFIG = {
      apiUrl: '<?php echo BASE_URL; ?>/api/refund-confirmations',
      isSuperAdmin: <?php echo ($userRoleCode === 'SUPER_ADMIN') ? 'true' : 'false'; ?>
    };
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/refund-confirmations/assets/js/refund-confirmations.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/refund-confirmations.js'); ?>"></script>
</body>
</html>
