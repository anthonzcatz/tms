<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatDate = static fn ($value): string => Auth::formatTimestamp($value, 'M d, Y');
$formatDateTime = static fn ($value): string => Auth::formatTimestamp($value, 'M d, Y h:i A');
$buildPageUrl = static function (int $page) use ($filterValues): string {
    $query = $filterValues;
    $query['page'] = $page;
    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);
    return BASE_URL . '/admin/charges/cashiers?' . http_build_query($query);
};
$summaryStatusColors = [
    'PENDING' => 'warning',
    'APPROVED' => 'primary',
    'COMPLETED' => 'success',
    'OPEN' => 'success',
    'CLOSED' => 'primary',
    'RECONCILED' => 'info'
];
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/charges/assets/css/charges.css?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/charges/assets/css/charges.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/charges/cashiers/assets/css/cashier-charges.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/cashier-charges.css'); ?>">
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
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php elseif (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                break;
        }
        ?>
      <?php endif; ?>

        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Cashier <span class="text-info fw-medium">Charges</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/charges">Payments</a></li>
                            <li class="breadcrumb-item active">Cashier Charges</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-auto ms-auto text-end">
                    <span class="badge bg-soft-warning text-warning px-3 py-2">
                      <span class="fas fa-calendar-day me-1"></span><?php echo $escape($formatDate($filterDateFrom)); ?><?php if ($filterDateFrom !== $filterDateTo): ?> – <?php echo $escape($formatDate($filterDateTo)); ?><?php endif; ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php $activeWalletModule = 'cashier-charges'; include dirname(dirname(dirname(__DIR__))) . '/wallet/_partials/wallet_nav.php'; ?>

        <?php if ($dateFilterNotice !== ''): ?>
        <div class="alert alert-info py-2 mb-3">
          <span class="fas fa-info-circle me-2"></span><?php echo $escape($dateFilterNotice); ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-6 col-xl-3">
            <div class="card h-100 cashier-stat-card">
              <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Cashiers with Charges</div>
                    <div class="fs-4 fw-bold text-primary"><?php echo number_format((int) ($stats['total_cashiers'] ?? 0)); ?></div>
                  </div>
                  <span class="fas fa-users text-primary fs-3"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card h-100 cashier-stat-card">
              <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Charge Transactions</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo number_format((int) ($stats['total_charges'] ?? 0)); ?></div>
                  </div>
                  <span class="fas fa-file-invoice-dollar text-warning fs-3"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card h-100 cashier-stat-card">
              <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Customers Charged</div>
                    <div class="fs-4 fw-bold text-info"><?php echo number_format((int) ($stats['total_customers'] ?? 0)); ?></div>
                  </div>
                  <span class="fas fa-user-tag text-info fs-3"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card h-100 cashier-stat-card">
              <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Total Charged</div>
                    <div class="fs-5 fw-bold text-danger">₱<?php echo number_format((float) ($stats['total_amount'] ?? 0), 2); ?></div>
                  </div>
                  <span class="fas fa-money-bill-wave text-danger fs-3"></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (($stats['void_count'] ?? 0) > 0): ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 mb-3">
          <div>
            <span class="fas fa-user-shield me-2"></span>
            <strong>VOID Responsibility:</strong>
            <?php echo number_format((int) ($stats['void_count'] ?? 0)); ?> selected cashier entr<?php echo (int) ($stats['void_count'] ?? 0) === 1 ? 'y' : 'ies'; ?>
            from <?php echo number_format((int) ($stats['void_cashiers'] ?? 0)); ?> Responsible Cashier<?php echo (int) ($stats['void_cashiers'] ?? 0) === 1 ? '' : 's'; ?>
          </div>
          <span class="fw-bold">₱<?php echo number_format((float) ($stats['void_amount'] ?? 0), 2); ?></span>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
          <div class="card-header py-2 bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-bold"><span class="fas fa-users me-2 text-success"></span>All Cashiers with Charge &amp; VOID Activity</h6>
                <span class="text-muted small">All-time charge activity grouped by cashier. This table is not affected by the filters below.</span>
              </div>
              <span class="text-muted small"><?php echo number_format(count($allCashierCharges)); ?> cashier<?php echo count($allCashierCharges) === 1 ? '' : 's'; ?></span>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($allCashierCharges)): ?>
              <div class="empty-state py-4">
                <div class="empty-state-icon fs-2"><span class="fas fa-users"></span></div>
                <div class="empty-state-text fs-6">No Cashier Charge Activity Found</div>
                <div class="empty-state-subtext">Cashiers appear here after a CHARGE transaction, REFUND responsibility, or VOID responsibility is assigned.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Cashier</th>
                      <th>Branches</th>
                      <th class="text-end">Charges</th>
                      <th class="text-end">Customers</th>
                      <th class="text-end">Charge Amount</th>
                      <th class="text-end">VOID Duties</th>
                      <th class="text-end">VOID Amount</th>
                      <th class="text-end">Service Amount</th>
                      <th class="text-end">Total Charge</th>
                      <th>Last Activity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allCashierCharges as $row): ?>
                      <?php
                      $totalCharge = (float) ($row['total_charged'] ?? 0)
                          + (float) ($row['charge_amount'] ?? 0)
                          + (float) ($row['void_amount'] ?? 0)
                          + (float) ($row['service_amount'] ?? 0);
                      ?>
                      <tr class="cashier-charge-row">
                        <td class="ps-3 py-3">
                          <div class="d-flex align-items-center">
                            <div class="cashier-avatar rounded-circle bg-soft-success text-success d-flex align-items-center justify-content-center me-3">
                              <span class="fas fa-user"></span>
                            </div>
                            <div>
                              <div class="fw-semibold"><?php echo $escape($row['cashier_name']); ?></div>
                              <?php if (!empty($row['cashier_username'])): ?><div class="text-muted small">@<?php echo $escape($row['cashier_username']); ?></div><?php endif; ?>
                            </div>
                          </div>
                        </td>
                        <td class="py-3"><?php echo $escape($row['branch_names']); ?></td>
                        <td class="py-3 text-end fw-semibold"><?php echo number_format((int) $row['charge_count']); ?></td>
                        <td class="py-3 text-end"><?php echo number_format((int) $row['customer_count']); ?></td>
                        <td class="py-3 text-end fw-bold text-info">₱<?php echo number_format((float) ($row['charge_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-semibold text-warning"><?php echo number_format((int) ($row['void_count'] ?? 0)); ?></td>
                        <td class="py-3 text-end fw-bold text-warning">₱<?php echo number_format((float) ($row['void_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-bold text-warning">₱<?php echo number_format((float) ($row['service_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-bold text-danger">₱<?php echo number_format($totalCharge, 2); ?></td>
                        <td class="py-3 text-muted small"><?php echo $escape($formatDateTime($row['last_charge_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 bg-body-tertiary">
            <h6 class="mb-0 fw-bold"><span class="fas fa-filter me-2 text-primary"></span>Filters</h6>
          </div>
          <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-end">
              <div class="col-12 col-lg-3">
                <label class="form-label small fw-semibold mb-1" for="filterSearch">Search</label>
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" name="search" value="<?php echo $escape($filterSearch); ?>" placeholder="Cashier, customer, transaction...">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterDateFrom">Date From</label>
                <input type="date" class="form-control" id="filterDateFrom" name="date_from" value="<?php echo $escape($filterDateFrom); ?>">
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterDateTo">Date To</label>
                <input type="date" class="form-control" id="filterDateTo" name="date_to" value="<?php echo $escape($filterDateTo); ?>">
              </div>
              <?php if ($isSuperAdmin || count($branches) > 1): ?>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterBranch">Branch</label>
                <select class="form-select" id="filterBranch" name="branch">
                  <option value="">All Branches</option>
                  <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo (int) $filterBranch === (int) $branch['branch_id'] ? 'selected' : ''; ?>><?php echo $escape($branch['branch_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterCashier">Cashier</label>
                <select class="form-select" id="filterCashier" name="cashier">
                  <option value="">All Cashiers</option>
                  <?php foreach ($cashierOptions as $cashier): ?>
                    <option value="<?php echo (int) $cashier['cashier_user_id']; ?>" <?php echo (int) $filterCashier === (int) $cashier['cashier_user_id'] ? 'selected' : ''; ?>><?php echo $escape($cashier['cashier_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterSource">Charge Source</label>
                <select class="form-select" id="filterSource" name="source">
                  <option value="">All Sources</option>
                  <option value="TICKET_TRANSACTION" <?php echo $filterSource === 'TICKET_TRANSACTION' ? 'selected' : ''; ?>>Ticket</option>
                  <option value="SERVICE_TRANSACTION" <?php echo $filterSource === 'SERVICE_TRANSACTION' ? 'selected' : ''; ?>>Service</option>
                  <option value="POS_ORDER" <?php echo $filterSource === 'POS_ORDER' ? 'selected' : ''; ?>>POS Order</option>
                </select>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="filterSessionStatus">Session Status</label>
                <select class="form-select" id="filterSessionStatus" name="session_status">
                  <option value="">All Sessions</option>
                  <option value="OPEN" <?php echo $filterSessionStatus === 'OPEN' ? 'selected' : ''; ?>>Open</option>
                  <option value="CLOSED" <?php echo $filterSessionStatus === 'CLOSED' ? 'selected' : ''; ?>>Closed</option>
                  <option value="RECONCILED" <?php echo $filterSessionStatus === 'RECONCILED' ? 'selected' : ''; ?>>Reconciled</option>
                </select>
              </div>
              <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1" for="perPage">Rows</label>
                <select class="form-select" id="perPage" name="per_page">
                  <?php foreach ([25, 50, 100] as $perPage): ?>
                    <option value="<?php echo $perPage; ?>" <?php echo $detailPerPage === $perPage ? 'selected' : ''; ?>><?php echo $perPage; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                  <span class="fas fa-filter me-1"></span>Apply
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/charges/cashiers" class="btn btn-outline-secondary" title="Reset filters">
                  <span class="fas fa-undo"></span>
                </a>
              </div>
            </form>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-bold"><span class="fas fa-users me-2 text-primary"></span>Cashiers with Charge &amp; VOID Activity</h6>
                <span class="text-muted small">Normal CHARGE activity stays with the payment cashier; REFUND responsibility is shown as Charge Amount, while VOID responsibility is split into VOID and Service Amount.</span>
              </div>
              <span class="text-muted small"><?php echo number_format(count($cashierCharges)); ?> cashier group<?php echo count($cashierCharges) === 1 ? '' : 's'; ?></span>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($cashierCharges)): ?>
              <div class="empty-state">
                <div class="empty-state-icon"><span class="fas fa-file-invoice-dollar"></span></div>
                <div class="empty-state-text">No Cashier Charges Found</div>
                <div class="empty-state-subtext">No CHARGE, REFUND responsibility, or VOID activity matched the selected filters.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Cashier</th>
                      <th>Branch</th>
                      <th class="text-end">Charges</th>
                      <th class="text-end">Customers</th>
                      <th class="text-end">Charge Amount</th>
                      <th class="text-end">VOID Duties</th>
                      <th class="text-end">VOID Amount</th>
                      <th class="text-end">Service Amount</th>
                      <th class="text-end">Total Charge</th>
                      <th>Sessions</th>
                      <th>Last Activity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($cashierCharges as $row): ?>
                      <?php
                      $sessionStatuses = array_values(array_filter(array_map('trim', explode(',', (string) ($row['session_statuses'] ?? '')))));
                      $totalCharge = (float) ($row['total_charged'] ?? 0)
                          + (float) ($row['charge_amount'] ?? 0)
                          + (float) ($row['void_amount'] ?? 0)
                          + (float) ($row['service_amount'] ?? 0);
                      ?>
                      <tr class="cashier-charge-row">
                        <td class="ps-3 py-3">
                          <div class="d-flex align-items-center">
                            <div class="cashier-avatar rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3">
                              <span class="fas fa-user"></span>
                            </div>
                            <div>
                              <div class="fw-semibold"><?php echo $escape($row['cashier_name']); ?></div>
                              <?php if (!empty($row['cashier_username'])): ?><div class="text-muted small">@<?php echo $escape($row['cashier_username']); ?></div><?php endif; ?>
                            </div>
                          </div>
                        </td>
                        <td class="py-3"><?php echo $escape($row['branch_name']); ?></td>
                        <td class="py-3 text-end fw-semibold"><?php echo number_format((int) $row['charge_count']); ?></td>
                        <td class="py-3 text-end"><?php echo number_format((int) $row['customer_count']); ?></td>
                        <td class="py-3 text-end fw-bold text-info">₱<?php echo number_format((float) ($row['charge_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-semibold text-warning"><?php echo number_format((int) ($row['void_count'] ?? 0)); ?></td>
                        <td class="py-3 text-end fw-bold text-warning">₱<?php echo number_format((float) ($row['void_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-bold text-warning">₱<?php echo number_format((float) ($row['service_amount'] ?? 0), 2); ?></td>
                        <td class="py-3 text-end fw-bold text-danger">₱<?php echo number_format($totalCharge, 2); ?></td>
                        <td class="py-3">
                          <div class="small mb-1"><?php echo number_format((int) $row['session_count']); ?> session<?php echo (int) $row['session_count'] === 1 ? '' : 's'; ?></div>
                          <?php if ($sessionStatuses): ?>
                            <?php foreach ($sessionStatuses as $sessionStatus): ?>
                              <?php $statusColor = $summaryStatusColors[$sessionStatus] ?? 'secondary'; ?>
                              <span class="badge bg-soft-<?php echo $statusColor; ?> text-<?php echo $statusColor; ?> me-1"><?php echo $escape($sessionStatus); ?></span>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <span class="text-muted small">No session link</span>
                          <?php endif; ?>
                        </td>
                        <td class="py-3 text-muted small"><?php echo $escape($formatDateTime($row['last_charge_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header py-2 bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-bold"><span class="fas fa-list me-2 text-info"></span>Charge, Refund &amp; VOID Entries</h6>
                <span class="text-muted small">
                  <?php if ($detailCount > 0): ?>Showing <?php echo number_format(($detailPage - 1) * $detailPerPage + 1); ?>–<?php echo number_format(min($detailPage * $detailPerPage, $detailCount)); ?> of <?php echo number_format($detailCount); ?><?php else: ?>No entries to display<?php endif; ?>
                </span>
              </div>
              <span class="badge bg-soft-warning text-warning">CHARGE / REFUND / VOID RESPONSIBILITY</span>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($chargeEntries)): ?>
              <div class="empty-state py-4">
                <div class="empty-state-icon fs-2"><span class="fas fa-search"></span></div>
                <div class="empty-state-text fs-6">No CHARGE, REFUND, or VOID responsibility entries for this filter.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Date</th>
                      <th>Cashier</th>
                      <th>Charged To</th>
                      <th>Transaction</th>
                      <th>Source</th>
                      <th>Branch</th>
                      <th class="text-end pe-3">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($chargeEntries as $entry): ?>
                      <?php
                      $isVoidEntry = ($entry['entry_type'] ?? '') === 'void_responsibility';
                      $isRefundEntry = ($entry['entry_type'] ?? '') === 'refund_responsibility';
                      $isResponsibilityEntry = $isVoidEntry || $isRefundEntry;
                      $entryStatus = $isResponsibilityEntry
                          ? strtoupper((string) ($entry['responsibility_status'] ?? ''))
                          : strtoupper((string) ($entry['session_status'] ?? ''));
                      $entryStatusColor = $summaryStatusColors[$entryStatus] ?? 'secondary';
                      $sourceBadgeColor = $isVoidEntry ? 'warning' : ($isRefundEntry ? 'primary' : 'info');
                      $entryRowClass = $isVoidEntry ? 'table-warning' : ($isRefundEntry ? 'table-primary' : '');
                      ?>
                      <tr class="<?php echo $entryRowClass; ?>">
                        <td class="ps-3 py-3 text-muted small text-nowrap"><?php echo $escape($formatDateTime($entry['created_at'])); ?></td>
                        <td class="py-3">
                          <div class="fw-semibold"><?php echo $escape($entry['cashier_name']); ?></div>
                          <?php if (!empty($entry['cashier_username'])): ?><div class="text-muted small">@<?php echo $escape($entry['cashier_username']); ?></div><?php endif; ?>
                          <?php if (!empty($entry['session_code'])): ?><div class="text-muted small"><?php echo $escape($entry['session_code']); ?></div><?php endif; ?>
                        </td>
                        <td class="py-3">
                          <?php if ($isVoidEntry): ?>
                            <div class="fw-semibold text-warning">Cashier Responsibility</div>
                            <div class="text-muted small">Selected Responsible Cashier</div>
                          <?php elseif ($isRefundEntry): ?>
                            <div class="fw-semibold text-primary">Refund Responsibility</div>
                            <div class="text-muted small">Refund for <?php echo $escape($entry['passenger_name'] ?: 'Customer'); ?></div>
                          <?php else: ?>
                            <div class="fw-semibold"><?php echo $escape($entry['charge_account_name'] ?: '—'); ?></div>
                            <?php if (!empty($entry['charge_account_mobile'])): ?><div class="text-muted small"><?php echo $escape($entry['charge_account_mobile']); ?></div><?php endif; ?>
                          <?php endif; ?>
                        </td>
                        <td class="py-3">
                          <div class="fw-semibold"><?php echo $escape($entry['transaction_code'] ?: '—'); ?></div>
                          <?php if (!empty($entry['ticket_number'])): ?><div class="text-muted small"><?php echo $escape($entry['ticket_number']); ?></div><?php elseif (!empty($entry['passenger_name'])): ?><div class="text-muted small"><?php echo $escape($entry['passenger_name']); ?></div><?php endif; ?>
                        </td>
                        <td class="py-3">
                          <span class="badge bg-soft-<?php echo $sourceBadgeColor; ?> text-<?php echo $sourceBadgeColor; ?>"><?php echo $escape($entry['transaction_type'] ?: $entry['source_type']); ?></span>
                          <?php if ($isVoidEntry): ?><div class="text-warning small mt-1">VOID Responsibility Amount</div><?php elseif ($isRefundEntry): ?><div class="text-primary small mt-1">Refund Responsibility Amount</div><?php elseif (!empty($entry['confirmation_status']) && $entry['confirmation_status'] !== 'NOT_REQUIRED'): ?><div class="text-muted small mt-1"><?php echo $escape($entry['confirmation_status']); ?></div><?php endif; ?>
                        </td>
                        <td class="py-3">
                          <?php echo $escape($entry['branch_name']); ?>
                          <?php if ($entryStatus !== ''): ?><div><span class="badge bg-soft-<?php echo $entryStatusColor; ?> text-<?php echo $entryStatusColor; ?> mt-1"><?php echo $escape($entryStatus); ?></span></div><?php endif; ?>
                          <?php if ($isVoidEntry && !empty($entry['settlement_status'])): ?><div class="text-muted small mt-1">Settlement: <?php echo $escape($entry['settlement_status']); ?></div><?php endif; ?>
                        </td>
                        <td class="py-3 text-end pe-3 fw-bold <?php echo $isVoidEntry ? 'text-warning' : ($isRefundEntry ? 'text-primary' : 'text-danger'); ?> text-nowrap">₱<?php echo number_format((float) $entry['amount'], 2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($detailTotalPages > 1): ?>
          <div class="card-footer bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small">Page <?php echo $detailPage; ?> of <?php echo $detailTotalPages; ?></span>
              <nav aria-label="Charge entries pagination">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $detailPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $detailPage <= 1 ? '#' : $escape($buildPageUrl($detailPage - 1)); ?>">Previous</a>
                  </li>
                  <?php
                  $paginationStart = max(1, $detailPage - 2);
                  $paginationEnd = min($detailTotalPages, $detailPage + 2);
                  for ($pageNumber = $paginationStart; $pageNumber <= $paginationEnd; $pageNumber++):
                  ?>
                    <li class="page-item <?php echo $pageNumber === $detailPage ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $escape($buildPageUrl($pageNumber)); ?>"><?php echo $pageNumber; ?></a></li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $detailPage >= $detailTotalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $detailPage >= $detailTotalPages ? '#' : $escape($buildPageUrl($detailPage + 1)); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
          <?php endif; ?>
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
