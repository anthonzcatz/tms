<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(__DIR__)) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/refund-confirmations/assets/css/refund-history.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/refund-history.css'); ?>">
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
          <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
        <?php else: ?>
          <?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <?php endif; ?>
      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
        <div class="content">
          <?php if (NAVBAR_POSITION === 'combo'): ?>
            <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
          <?php else: ?>
            <?php include dirname(dirname(__DIR__)) . '/includes/navbar.php'; ?>
          <?php endif; ?>
      <?php endif; ?>

      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="card border-0 shadow-sm mb-4">
            <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
            <div class="card-header z-1">
              <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                  <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="">
                  <div class="ms-x1">
                    <h4 class="mb-0 text-primary fw-bold">Refund <span class="text-info fw-medium">History</span></h4>
                    <h6 class="mb-1 text-primary d-none d-sm-block">
                      <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                          <li class="breadcrumb-item"><a>Home</a></li>
                          <li class="breadcrumb-item active">Refund History</li>
                        </ol>
                      </nav>
                    </h6>
                  </div>
                </div>
                <div class="col-auto ms-auto">
                  <small id="refundHistoryRealtimeStatus" class="text-muted" title="Refund history refresh status">Live updates initializing...</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php $activeWalletModule = 'refund-history'; include dirname(dirname(__DIR__)) . '/wallet/_partials/wallet_nav.php'; ?>

      <div class="card mb-3">
        <div class="card-body py-3">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3 col-xl-2">
              <label class="form-label small fw-semibold mb-1" for="filterDateRange">Processed date</label>
              <input type="text" class="form-control" id="filterDateRange" placeholder="Select date range...">
              <input type="hidden" id="filterDateFrom">
              <input type="hidden" id="filterDateTo">
            </div>
            <div class="col-6 col-md-2 col-xl-2">
              <label class="form-label small fw-semibold mb-1" for="filterBranch">Branch</label>
              <select class="form-select" id="filterBranch">
                <option value="">All branches</option>
              </select>
            </div>
            <div class="col-6 col-md-2 col-xl-2">
              <label class="form-label small fw-semibold mb-1" for="filterCashier">Processed by</label>
              <select class="form-select" id="filterCashier">
                <option value="">All cashiers</option>
              </select>
            </div>
            <div class="col-6 col-md-2 col-xl-2">
              <label class="form-label small fw-semibold mb-1" for="filterProvider">Provider</label>
              <select class="form-select" id="filterProvider">
                <option value="">All providers</option>
              </select>
            </div>
            <div class="col-6 col-md-2 col-xl-2">
              <label class="form-label small fw-semibold mb-1" for="filterStatus">Status</label>
              <select class="form-select" id="filterStatus">
                <option value="completed" selected>Completed</option>
                <option value="processing">Processing</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
                <option value="all">All statuses</option>
              </select>
            </div>
            <div class="col-12 col-md-1 col-xl-2 d-flex gap-2">
              <button type="button" class="btn btn-primary flex-grow-1" id="applyFilters" title="Apply filters">
                <span class="fas fa-filter"></span><span class="d-md-none ms-2">Apply</span>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="resetFilters" title="Reset filters">
                <span class="fas fa-undo"></span>
              </button>
              <button type="button" class="btn btn-outline-primary" id="printRefundHistory" title="Print refund history">
                <span class="fas fa-print"></span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
          <div class="card h-100 refund-history-summary-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-muted small">Refunds</div><div class="fs-5 fw-bold" id="summaryCount">—</div></div>
                <span class="fas fa-receipt text-primary fs-4"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card h-100 refund-history-summary-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-muted small">Total refunded</div><div class="fs-5 fw-bold text-danger" id="summaryTotal">—</div></div>
                <span class="fas fa-money-bill-wave text-danger fs-4"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card h-100 refund-history-summary-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-muted small">Cash returned</div><div class="fs-5 fw-bold text-success" id="summaryCash">—</div></div>
                <span class="fas fa-hand-holding-usd text-success fs-4"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="card h-100 refund-history-summary-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div><div class="text-muted small">Charge reversals</div><div class="fs-5 fw-bold text-info" id="summaryCharge">—</div></div>
                <span class="fas fa-file-invoice-dollar text-info fs-4"></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-body-tertiary py-2">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-7">
              <div class="search-box">
                <input type="search" class="form-control search-input" id="filterSearch" placeholder="Search transaction, ticket, passenger, cashier...">
                <span class="fas fa-search search-icon"></span>
              </div>
            </div>
            <div class="col-12 col-md-5">
              <div class="d-flex align-items-center gap-2 justify-content-md-end">
                <span class="text-muted small" id="tableInfo">Loading...</span>
                <label class="text-muted small mb-0" for="perPageSelect">Per page:</label>
                <select class="form-select form-select-sm" id="perPageSelect" style="width: 80px;">
                  <option value="10">10</option>
                  <option value="15" selected>15</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 refund-history-table" id="refundHistoryTable">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Processed</th>
                  <th>Transaction</th>
                  <th>Original sale</th>
                  <th>Passenger / route</th>
                  <th>Provider / branch</th>
                  <th>Original cashier</th>
                  <th>Processed by</th>
                  <th class="text-end">Refund amount</th>
                  <th>Status</th>
                  <th class="text-end pe-3">Details</th>
                </tr>
              </thead>
              <tbody id="refundHistoryTableBody">
                <tr><td colspan="10" class="text-center py-5 text-muted"><span class="fas fa-spinner fa-spin me-2"></span>Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-body-tertiary">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small" id="paginationInfo">Loading...</span>
            <nav aria-label="Refund history pages"><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
          </div>
        </div>
      </div>

      <div class="modal fade" id="refundHistoryDetailModal" tabindex="-1" aria-labelledby="refundHistoryDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <div>
                <h5 class="modal-title" id="refundHistoryDetailModalLabel">Refund details</h5>
                <div class="small text-white-50" id="detailTransactionCode"></div>
              </div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                  <div class="card bg-body-tertiary h-100">
                    <div class="card-header py-2"><h6 class="mb-0">Ticket and sale</h6></div>
                    <div class="card-body small" id="detailTicket"></div>
                  </div>
                </div>
                <div class="col-12 col-lg-6">
                  <div class="card bg-body-tertiary h-100">
                    <div class="card-header py-2"><h6 class="mb-0">Refund processing</h6></div>
                    <div class="card-body small" id="detailProcessing"></div>
                  </div>
                </div>
              </div>
              <div class="card mb-3">
                <div class="card-header py-2"><h6 class="mb-0">Refund amounts</h6></div>
                <div class="card-body" id="detailAmounts"></div>
              </div>
              <div class="card mb-3">
                <div class="card-header py-2"><h6 class="mb-0">Payment allocations</h6></div>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Route</th><th>Payment method</th><th>Bank</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody id="detailAllocations"></tbody>
                  </table>
                </div>
              </div>
              <div class="card">
                <div class="card-header py-2"><h6 class="mb-0">Reason</h6></div>
                <div class="card-body small" id="detailReason"></div>
              </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
          </div>
        </div>
      </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?></div><?php endif; ?>
    </div>
  </main>

  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script>
    window.REFUND_HISTORY_CONFIG = {
      apiUrl: <?php echo json_encode(BASE_URL . '/api/refund-confirmations/history'); ?>,
      companyName: <?php echo json_encode($systemSettings['system_name'] ?? 'TMS'); ?>,
      companyAddress: <?php echo json_encode($systemSettings['company_address'] ?? ''); ?>,
      companyContact: <?php echo json_encode($systemSettings['company_contact_number'] ?? ''); ?>,
      companyLogo: <?php
        $historyLogo = trim((string)($systemSettings['system_logo'] ?? ''));
        if ($historyLogo === '' || !preg_match('/^(\/|https?:\/\/)/i', $historyLogo)) {
            $historyLogo = '/api/images/logo/logo_1779670787_4364a51c.png';
        }
        $historyLogo = (strpos($historyLogo, 'http') === 0) ? $historyLogo : rtrim(BASE_URL, '/') . '/' . ltrim($historyLogo, '/');
        echo json_encode($historyLogo);
      ?>,
      reportFooter: <?php echo json_encode($systemSettings['report_footer'] ?? 'System Generated Report'); ?>,
      pusher: {
        enabled: <?php echo $pusherConfigured ? 'true' : 'false'; ?>,
        key: <?php echo json_encode($pusherKey); ?>,
        cluster: <?php echo json_encode($pusherCluster); ?>,
        authEndpoint: <?php echo json_encode(BASE_URL . '/api/pusher/auth'); ?>,
        branchIds: <?php echo json_encode(array_values(array_unique($realtimeBranchIds))); ?>
      }
    };
  </script>
  <?php if ($pusherConfigured): ?><script src="https://js.pusher.com/8.4.0/pusher.min.js"></script><?php endif; ?>
  <script src="<?php echo BASE_URL; ?>/admin/assets/js/branch-realtime.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/js/branch-realtime.js'); ?>"></script>
  <script src="<?php echo BASE_URL; ?>/admin/refund-confirmations/assets/js/refund-history.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/refund-history.js'); ?>"></script>
  <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
</body>
</html>
