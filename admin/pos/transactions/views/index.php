<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/pos/transactions/assets/css/pos-transactions.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/pos-transactions.css'); ?>">
<script src="<?php echo BASE_URL; ?>/print_library/print_template.js"></script>
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
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>

      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
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
                    <img class="me-2" style="max-height: 48px; max-width: 48px; object-fit: contain;" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" onerror="this.src='<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/falcon.png'" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold"><span class="text-info fw-medium"><?php echo $systemName; ?></span> POS Transactions</h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/pos/">POS</a></li>
                            <li class="breadcrumb-item active">Transactions</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Orders</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-primary" id="statTotal">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-receipt text-primary fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Revenue</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-success" id="statRevenue">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-money-bill-wave text-success fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Refunded</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-danger" id="statRefunded">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-undo text-danger fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Net Profit</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-info" id="statProfit">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-chart-line text-info fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-2 align-items-end">
              <!-- Row 1: Search (wide) + Status + Type + Branch -->
              <div class="col-12 col-sm-12 col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch"
                         placeholder="Order code, cashier, passenger...">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                  <option value="all">All Status</option>
                  <option value="completed">Completed</option>
                  <option value="pending">Pending</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="refunded">Refunded</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Type</label>
                <select class="form-select form-select-sm" id="filterType">
                  <option value="all">All Types</option>
                  <option value="TICKET">Ticket</option>
                  <option value="SERVICE">Service</option>
                </select>
              </div>
              <?php if ($userRoleCode === 'SUPER_ADMIN' || $userRoleCode === 'MANAGER'): ?>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Branch</label>
                <select class="form-select form-select-sm" id="filterBranch">
                  <option value="">All Branches</option>
                </select>
              </div>
              <?php endif; ?>
              <!-- Row 2: Provider Type + Provider + Cashier + Date Range + Actions -->
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Provider Type</label>
                <select class="form-select form-select-sm" id="filterProviderType">
                  <option value="">All Types</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Provider</label>
                <select class="form-select form-select-sm" id="filterProvider">
                  <option value="">All Providers</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Cashier</label>
                <select class="form-select form-select-sm" id="filterCashier">
                  <option value="">All Cashiers</option>
                </select>
              </div>
              <div class="col-12 col-sm-8 col-md-5 col-lg-3">
                <label class="form-label small fw-semibold mb-1">Date Range</label>
                <input type="text" class="form-control form-control-sm" id="filterDateRange" placeholder="Select date or range...">
                <input type="hidden" id="filterDateFrom">
                <input type="hidden" id="filterDateTo">
              </div>
              <div class="col-12 col-sm-auto d-flex gap-2 flex-wrap align-items-end">
                <button type="button" class="btn btn-sm btn-primary" onclick="applyFilters()" title="Apply">
                  <span class="fas fa-filter"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetFilters()" title="Reset">
                  <span class="fas fa-undo"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTransactions()" title="Export">
                  <span class="fas fa-download"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="printTransactions()" title="Print">
                  <span class="fas fa-print"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTransactions(1)" title="Refresh">
                  <span class="fas fa-sync"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
          <div class="card-header bg-body-tertiary py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small" id="tableInfo">Loading...</span>
              <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Per page:</label>
                <select class="form-select form-select-sm" id="perPageSelect" style="width:80px;" onchange="loadTransactions(1)">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0" id="transactionsTable">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Order</th>
                    <th>Type</th>
                    <th>Passenger / Provider</th>
                    <th>Cashier</th>
                    <th>Branch</th>
                    <th>Payment</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Date</th>
                  </tr>
                </thead>
                <tbody id="transactionsTableBody">
                  <tr><td colspan="9" class="text-center py-5 text-muted">
                    <span class="fas fa-spinner fa-spin me-2"></span>Loading...
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small" id="paginationInfo"></span>
              <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- Transaction Detail Offcanvas (Right Side) -->
  <div class="offcanvas offcanvas-end" id="txnDetailModal" tabindex="-1" aria-labelledby="txnDetailModalLabel" aria-hidden="true">
    <div class="offcanvas-header bg-shape">
      <div class="position-relative z-1">
        <h5 class="mb-0 text-white" id="txnDetailModalLabel">
          <span class="fas fa-receipt me-2"></span>Order Details
        </h5>
        <p class="fs-10 mb-0 text-white">View transaction information and details</p>
      </div>
      <div data-bs-theme="dark">
        <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
    </div>
    <div class="offcanvas-body scrollbar-overlay" id="txnDetailContent">
      <div class="text-center py-4"><span class="fas fa-spinner fa-spin"></span></div>
    </div>
    <div class="offcanvas-footer border-top p-3">
      <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="offcanvas">
        <span class="fas fa-times me-2"></span>Close
      </button>
    </div>
  </div>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script>
    window.POS_TXN_CONFIG = {
      apiUrl: '<?php echo BASE_URL; ?>/api/pos-transactions',
      isSuperAdmin: <?php echo ($userRoleCode === 'SUPER_ADMIN') ? 'true' : 'false'; ?>,
      isManager: <?php echo ($userRoleCode === 'MANAGER') ? 'true' : 'false'; ?>
    };
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/transactions/assets/js/pos-transactions.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/pos-transactions.js'); ?>"></script>
</body>
</html>
