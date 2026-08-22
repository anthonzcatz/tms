<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(__DIR__)) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/charges/assets/css/charges.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/charges.css'); ?>">
<style>
  html.charges-fluid-layout [data-layout="container"] {
    max-width: none;
  }
</style>
<script>
  if (JSON.parse(localStorage.getItem('isFluid'))) {
    document.documentElement.classList.add('charges-fluid-layout');
  }
</script>
<body>
  <main class="main" id="top">
    <div class="container" data-layout="container"><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php elseif (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(__DIR__)) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(__DIR__)) . '/includes/navbar.php';
                break;
        }
        ?>
      <?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
          <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
          <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
              <div class="col-lg-auto d-flex align-items-center">
                <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Accounts <span class="text-info fw-medium">Receivable</span></h4>
                  <h6 class="mb-1 text-primary d-none d-sm-block">  <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Accounts Receivable</li>
                  </ol>
                 </nav>
                 </h6>
                </div>
              </div>
              <div class="col-auto ms-auto">
                <small id="chargesRealtimeStatus" class="text-muted" title="Customer charges data refresh status">Live updates initializing...</small>
              </div>
            </div>
          </div>
        </div>
          </div>
        </div>

        <?php $activeWalletModule = 'charges'; include dirname(dirname(__DIR__)) . '/wallet/_partials/wallet_nav.php'; ?>

        <!-- Stats -->
        <div class="row g-3 mb-3" id="chargesStats">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Customers</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $stats['total_customers'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-users text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Outstanding</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-6 fw-bold font-sans-serif lh-1 mb-1 text-danger">₱<?php echo number_format($stats['total_outstanding'] ?? 0, 2); ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-money-bill text-danger fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Outstanding</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $stats['outstanding_count'] ?? 0; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-exclamation-circle text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Overdue</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif lh-1 mb-1"><?php echo $stats['overdue_count'] ?? 0; ?></div></div>
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
              <li>When a cashier records a payment as <strong>CHARGE</strong>, the amount is added to the selected charge account's outstanding balance.</li>
              <li>This page lets you view all charge accounts with balances and collect full or partial payments.</li>
              <li>Charge history keeps the ticket passenger name separate from the charge account.</li>
              <li>Each collection is recorded and the balance is updated automatically.</li>
              <li>Click <strong>View History</strong> to see all charges and payments for a customer.</li>
            </ul>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-12 col-md-3">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search customer name, contact..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-6 col-md-2">
                <select class="form-select" id="filterStatus" onchange="applyFilters()">
                  <option value="">All Status</option>
                  <option value="OUTSTANDING">Outstanding</option>
                  <option value="OVERDUE">Overdue</option>
                  <option value="CLEAR">Clear</option>
                  <option value="WRITTEN_OFF">Written Off</option>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <select class="form-select" id="filterBalanceRange" onchange="applyFilters()">
                  <option value="">All Balances</option>
                  <option value="positive">Has Balance</option>
                  <option value="zero">No Balance</option>
                  <option value="high">High (≥₱5,000)</option>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <input type="date" class="form-control" id="filterDateFrom" onchange="applyFilters()" placeholder="From date">
              </div>
              <div class="col-6 col-md-2">
                <input type="date" class="form-control" id="filterDateTo" onchange="applyFilters()" placeholder="To date">
              </div>
              <div class="col-12 col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                  <span class="fas fa-undo"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Charges Table -->
        <div class="card" id="chargesDataTable">
          <div class="card-body p-0">
            <?php if (empty($charges)): ?>
              <div class="empty-state">
                <div class="empty-state-icon"><span class="fas fa-file-invoice-dollar"></span></div>
                <div class="empty-state-text">No Accounts Receivable Found</div>
                <div class="empty-state-subtext">Receivables appear here when a cashier records a CHARGE payment.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="chargesTable">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Charge Account</th>
                      <th>Base</th>
                      <th>Service Fee</th>
                      <th>Total Charged</th>
                      <th>Total Paid</th>
                      <th>Balance</th>
                      <th>Mode</th>
                      <th>Last Activity</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($charges as $c):
                      $statusColors = ['CLEAR' => 'success', 'OUTSTANDING' => 'warning', 'OVERDUE' => 'danger', 'WRITTEN_OFF' => 'secondary'];
                      $color = $statusColors[$c['status']] ?? 'secondary';
                      $balClass = $c['balance'] >= 500 ? 'balance-critical' : ($c['balance'] > 0 ? 'balance-warning' : 'balance-clear');
                    ?>
                    <tr class="charge-row"
                        data-status="<?php echo htmlspecialchars($c['status']); ?>"
                        data-balance="<?php echo $c['balance']; ?>"
                        data-last-date="<?php echo $c['last_charge_date'] ? date('Y-m-d', strtotime($c['last_charge_date'])) : ''; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($c['passenger_name'] . ' ' . $c['contact_number'])); ?>">
                      <td class="ps-3 py-3">
                        <div class="d-flex align-items-center">
                          <div class="rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;font-size:1rem;flex-shrink:0;">
                            <span class="fas fa-user"></span>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars(ucfirst(strtolower($c['passenger_name']))); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($c['contact_number'] ?? '—'); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <span class="text-muted">₱<?php echo number_format($c['base_balance'] ?? 0, 2); ?></span>
                      </td>
                      <td class="py-3">
                        <?php if (($c['service_fee_mode'] ?? 'CUSTOMER') !== 'CUSTOMER'): ?>
                          <span class="text-info">₱<?php echo number_format($c['fee_balance'] ?? 0, 2); ?></span>
                          <?php if ($c['service_fee_mode'] === 'COMPANY' && !empty($c['company_passenger_name'])): ?>
                            <div class="small text-muted">→ <?php echo htmlspecialchars($c['company_passenger_name']); ?></div>
                          <?php elseif ($c['service_fee_mode'] === 'WAIVED'): ?>
                            <div class="small text-muted">Waived</div>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted">₱<?php echo number_format($c['fee_balance'] ?? 0, 2); ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <span class="text-muted">₱<?php echo number_format($c['total_charged'], 2); ?></span>
                      </td>
                      <td class="py-3">
                        <span class="text-success">₱<?php echo number_format($c['total_paid'], 2); ?></span>
                      </td>
                      <td class="py-3">
                        <span class="<?php echo $balClass; ?> fs-6">₱<?php echo number_format($c['balance'], 2); ?></span>
                      </td>
                      <td class="py-3">
                        <?php
                        $modeLabels = [
                            'CUSTOMER' => '<span class="badge bg-soft-secondary text-secondary">Customer</span>',
                            'WAIVED' => '<span class="badge bg-soft-info text-info">Waived</span>',
                            'COMPANY' => '<span class="badge bg-soft-primary text-primary">Company</span>'
                        ];
                        echo $modeLabels[$c['service_fee_mode'] ?? 'CUSTOMER'] ?? $modeLabels['CUSTOMER'];
                        ?>
                      </td>
                      <td class="py-3">
                        <span class="text-muted small">
                          <?php echo $c['last_charge_date'] ? Auth::formatTimestamp($c['last_charge_date'], 'M d, Y') : '—'; ?>
                        </span>
                      </td>
                      <td class="py-3">
                        <span class="badge status-<?php echo strtolower($c['status']); ?> px-3 py-2">
                          <?php echo htmlspecialchars($c['status']); ?>
                        </span>
                      </td>
                      <td class="py-3 text-end pe-3">
                        <button class="btn btn-sm btn-outline-info me-1" title="View History"
                          onclick="viewHistory(<?php echo $c['passenger_id']; ?>, '<?php echo htmlspecialchars($c['passenger_name'], ENT_QUOTES); ?>')">
                          <span class="fas fa-history"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-1" title="Print Statement"
                          onclick="window.open('<?php echo BASE_URL; ?>/admin/charges/views/print/statement.php?passenger_id=<?php echo $c['passenger_id']; ?>', '_blank')">
                          <span class="fas fa-file-invoice"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-dark me-1" title="Print Balance"
                          onclick="window.open('<?php echo BASE_URL; ?>/admin/charges/views/print/balance.php?passenger_id=<?php echo $c['passenger_id']; ?>', '_blank')">
                          <span class="fas fa-receipt"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary me-1" title="Service Fee Mode"
                          onclick="openExemptionModal(
                            <?php echo $c['passenger_id']; ?>,
                            '<?php echo htmlspecialchars($c['passenger_name'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($c['service_fee_mode'] ?? 'CUSTOMER', ENT_QUOTES); ?>',
                            <?php echo !empty($c['company_passenger_id']) ? $c['company_passenger_id'] : 'null'; ?>
                          )">
                          <span class="fas fa-cog"></span>
                        </button>
                        <?php if ($c['balance'] > 0): ?>
                        <button class="btn btn-sm btn-success" title="Collect Payment"
                          onclick="openCollectModal(
                            <?php echo $c['passenger_id']; ?>,
                            '<?php echo htmlspecialchars($c['passenger_name'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($c['contact_number'] ?? '—', ENT_QUOTES); ?>',
                            <?php echo $c['balance']; ?>,
                            <?php echo $c['base_balance'] ?? 0; ?>,
                            <?php echo $c['fee_balance'] ?? 0; ?>,
                            '<?php echo htmlspecialchars($c['service_fee_mode'] ?? 'CUSTOMER', ENT_QUOTES); ?>',
                            <?php echo $c['charge_branch_id'] ?? 'null'; ?>,
                            '<?php echo htmlspecialchars($c['branch_name'] ?? '', ENT_QUOTES); ?>'
                          )">
                          <span class="fas fa-hand-holding-usd me-1"></span>Collect
                        </button>
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
          <p class="text-muted">No customers match your filters.</p>
        </div>

      </div>
    </div>
  </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/collect_payment.php'; ?>
    <?php include __DIR__ . '/modals/exemption.php'; ?>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script>
    window.CHARGES_PUSHER_CONFIG = {
      enabled: <?php echo $pusherConfigured ? 'true' : 'false'; ?>,
      key: <?php echo json_encode($pusherKey); ?>,
      cluster: <?php echo json_encode($pusherCluster); ?>,
      authEndpoint: <?php echo json_encode(BASE_URL . '/api/pusher/auth'); ?>,
      branchIds: <?php echo json_encode(array_values(array_unique($realtimeBranchIds))); ?>
    };
    window.CHARGE_CONFIRMATION_REQUIRED = <?php echo ($systemSettings['bank_charge_payments_require_confirmation'] ?? 0) ? 'true' : 'false'; ?>;
  </script>
  <?php if ($pusherConfigured): ?><script src="https://js.pusher.com/8.4.0/pusher.min.js"></script><?php endif; ?>
  <script src="<?php echo BASE_URL; ?>/admin/assets/js/branch-realtime.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/js/branch-realtime.js'); ?>"></script>
  <script src="<?php echo BASE_URL; ?>/admin/charges/assets/js/charges.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/charges.js'); ?>"></script>
  <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
</body>
</html>
