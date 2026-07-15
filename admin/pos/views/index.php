<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
/**
 * @var array|null $activeSession
 * @var array      $serviceTypes
 * @var array      $paymentMethods
 * @var array      $bankAccounts
 * @var array      $passengers
 * @var array|null $cancellationSettings
 * @var array|null $posSettings
 * @var array|null $printerSettings
 * @var string     $userRoleCode
 * @var string     $userBranchId
 * @var array      $depositBankAccounts
 */
require_once dirname(dirname(__DIR__)) . '/includes/head.php';

// Calculate POS permissions once at the top using explicit integer values
$isManagerOrAdmin = ($userRoleCode === 'SUPER_ADMIN' || $userRoleCode === 'MANAGER');
$posCashierOpenRaw = intval($posSettings['pos_cashier_can_open_session'] ?? 1);
$posCashierCloseRaw = intval($posSettings['pos_cashier_can_close_session'] ?? 1);
$posManagerOpenRaw = intval($posSettings['pos_manager_can_open_for_cashier'] ?? 1);
$posManagerCloseRaw = intval($posSettings['pos_manager_can_close_for_cashier'] ?? 1);

$canOpenSession = $isManagerOrAdmin ? ($posManagerOpenRaw === 1) : ($posCashierOpenRaw === 1);
$canCloseSession = $isManagerOrAdmin ? ($posManagerCloseRaw === 1) : ($posCashierCloseRaw === 1);
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/vendors/choices/choices.min.css?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/resources/vendors/choices/choices.min.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/pos/assets/css/pos.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/pos.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/pos/assets/css/passenger-dropdown.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/passenger-dropdown.css'); ?>">
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
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(__DIR__)) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(__DIR__)) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }
        ?><?php endif; ?>

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <div class="card-header z-1">
                <div class="row align-items-center">
                  <div class="col d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Cashier <span class="text-info fw-medium">POS</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item active">Cashier POS</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-auto d-flex gap-2 flex-wrap justify-content-end">
                    <a href="<?php echo BASE_URL; ?>/admin/pos/printer-setup" class="btn btn-outline-info btn-sm rounded-2 d-flex align-items-center">
                      <span class="fas fa-print"></span><span class="ms-1 d-none d-sm-inline">Printer Setup</span>
                    </a>
                    <?php if ($activeSession): ?>
                      <div class="d-flex align-items-center gap-2 px-2 py-1 rounded-2 bg-success bg-opacity-10 border border-success" style="cursor: pointer;" onclick="toggleSessionBanner()">
                        <span class="fas fa-circle text-success session-active-pulse" style="font-size: 8px;"></span>
                        <span class="text-success fw-semibold d-none d-sm-inline">Session Active</span>
                        <span class="fas fa-chevron-down text-success ms-1" id="sessionBannerToggleIcon"></span>
                      </div>
                      <button class="btn btn-outline-danger btn-sm rounded-2" onclick="openCloseSession()">
                        <span class="fas fa-stop-circle"></span><span class="ms-1 d-none d-sm-inline">Close</span>
                      </button>
                    <?php else: ?>
                      <div class="d-flex align-items-center px-2 py-1 rounded-2 bg-secondary bg-opacity-10 border border-secondary">
                        <span class="text-secondary fw-semibold d-none d-sm-inline">No Active Session</span>
                        <span class="fas fa-circle text-secondary d-sm-none" style="font-size: 8px;"></span>
                      </div>
                      <?php if ($canOpenSession): ?>
                      <button class="btn btn-success btn-sm rounded-2" onclick="openSessionModal.show()">
                        <span class="fas fa-play-circle"></span><span class="ms-1 d-none d-sm-inline">Open</span>
                      </button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$activeSession): ?>
        <!-- No session warning -->
        <div class="alert alert-warning d-flex align-items-center mb-3 py-2" style="font-size: 0.85rem;">
          <span class="fas fa-exclamation-triangle me-2" style="font-size: 0.9rem;"></span>
          <div class="flex-grow-1">
            <span class="fw-medium">No Active Session.</span> You must open a cashier session before processing transactions.
            <?php if ($canOpenSession): ?>
            <button class="btn btn-sm btn-warning ms-2" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;" onclick="openSessionModal.show()">
              <span class="fas fa-play-circle"></span><span class="ms-1 d-none d-sm-inline">Open Session Now</span>
            </button>
            <?php else: ?>
            <span class="text-muted ms-2" style="font-size: 0.8rem;"><span class="fas fa-lock me-1"></span>Contact your manager to open a session.</span>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <!-- Session Banner -->
        <div class="collapse" id="sessionBannerCollapse">
          <div class="card session-banner mb-3 border-0 shadow-sm">
            <div class="card-body py-2">
              <div class="row align-items-center">
                <div class="col-auto">
                  <span class="fas fa-user-clock text-success fs-4"></span>
                </div>
                <div class="col">
                  <strong>Active Session</strong>
                  <span class="text-muted ms-2">
                    <?php echo htmlspecialchars($activeSession['branch_name'] ?? '—'); ?> •
                    Started <?php echo Auth::formatTimestamp($activeSession['started_at'], 'M j, Y h:i A'); ?> •
                    Opening Cash: <strong>₱<?php echo number_format($activeSession['starting_cash'], 2); ?></strong>
                  </span>
                </div>
                <div class="col-auto">
                  <span class="badge bg-soft-success text-success">Session #<?php echo $activeSession['session_id']; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- POS Layout -->
        <div class="pos-layout">

          <!-- LEFT: Ticket & Services -->
          <div>

            <!-- Transaction Type Toggle -->
            <div class="card mb-3">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-4.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header bg-body-tertiary d-flex flex-between-center py-2 position-relative z-1">
                <h6 class="mb-0">Transaction Type</h6>
              </div>
              <div class="card-body p-4 position-relative rounded-4 z-1">
                <div class="row g-3">
                  <div class="col-4">
                    <button type="button" class="btn btn-outline-primary d-block w-100 py-3 rounded-4 active position-relative" id="btnTicketType" onclick="switchTransactionType('ticket')">
                      <span class="fas fa-ticket-alt"></span><span class="ms-2 d-none d-sm-inline">Ticket Booking</span>
                    </button>
                  </div>
                  <div class="col-4">
                    <button type="button" class="btn btn-outline-success d-block w-100 py-3 rounded-4 position-relative" id="btnServiceType" onclick="switchTransactionType('service')">
                      <span class="fas fa-concierge-bell"></span><span class="ms-2 d-none d-sm-inline">Service Add-ons</span>
                    </button>
                  </div>
                  <div class="col-4">
                    <button type="button" class="btn btn-outline-info d-block w-100 py-3 rounded-4 position-relative" id="btnTransactionType" onclick="switchTransactionType('transaction')">
                      <span class="fas fa-history"></span><span class="ms-2 d-none d-sm-inline">Transactions</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ticket Selection (shown by default) -->
            <div class="card" id="ticketSection">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-6.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <h6 class="mb-0 fw-bold"><span class="fas fa-ticket-alt me-2 text-primary"></span>Ticket Details  </h6>
              </div>
              <div class="card-body position-relative z-1">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketPassenger">Passenger <span class="text-danger">*</span></label>
                    <div class="position-relative">
                      <!-- Search input (shown when no passenger selected) -->
                      <div id="passengerSearchGroup" class="input-group">
                        <span class="input-group-text bg-light">
                          <span class="fas fa-search text-muted"></span>
                        </span>
                        <input type="text" class="form-control" id="ticketPassengerSearch" placeholder="Search by name or mobile number..." oninput="searchTicketPassenger(this.value)" autocomplete="off">
                        <button class="btn btn-outline-primary" type="button" onclick="openAddPassengerModal()" title="Add New Passenger">
                          <span class="fas fa-user-plus"></span>
                        </button>
                      </div>
                      <input type="hidden" id="ticketPassenger" name="ticketPassenger">
                      <div id="ticketPassengerDropdown" class="dropdown-menu w-100" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                      <!-- Selected passenger display (shown inside input area after selection) -->
                      <div id="selectedPassengerDetails" style="display:none;" class="input-group">
                        <span class="input-group-text bg-success bg-opacity-10 border-success">
                          <span class="fas fa-user-check text-success"></span>
                        </span>
                        <div class="form-control d-flex align-items-center border-success bg-success bg-opacity-10 px-2" style="height:38px;">
                          <span class="fw-semibold text-dark me-2" style="font-size:0.88rem;" id="selectedPassengerName"></span>
                          <span class="text-muted" style="font-size:0.82rem;" id="selectedPassengerMobile"></span>
                        </div>
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSelectedPassenger()" title="Change Passenger">
                          <span class="fas fa-times"></span>
                        </button>
                      </div>
                      <small class="text-muted" id="passengerSearchHint">Start typing to search passengers (min 2 characters)</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketNumber">Ticket Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ticketNumber" name="ticketNumber" placeholder="Enter ticket number for tracking">
                  </div>
                  <!-- Hidden for now - Origin and Destination
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketOrigin">Origin</label>
                    <input type="text" class="form-control" id="ticketOrigin" name="ticketOrigin" placeholder="e.g. Manila">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketDestination">Destination</label>
                    <input type="text" class="form-control" id="ticketDestination" name="ticketDestination" placeholder="e.g. Baguio">
                  </div>
                  -->
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketBaseAmount">Cost (₱) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="ticketBaseAmount" name="ticketBaseAmount" min="0" step="0.01" placeholder="0.00" oninput="computeTicketTotal()">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Discount <span class="text-danger">*</span></label>
                    <select class="form-select" id="ticketDiscount" name="ticketDiscount" onchange="computeTicketTotal()">
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketAccommodation">Accommodation <span class="text-danger">*</span></label>
                    <select class="form-select" id="ticketAccommodation" name="ticketAccommodation">
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketProvider">Provider <span class="text-danger">*</span></label>
                    <select class="form-select" id="ticketProvider" name="ticketProvider" onchange="onProviderChanged()">
                      <option value="">Select Provider</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold d-flex justify-content-between" for="ticketWallet">
                      <span>Wallet <span class="text-danger">*</span></span>
                      <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="refreshWallets()" title="Refresh Wallets">
                        <span class="fas fa-sync-alt"></span>
                      </button>
                    </label>
                    <select class="form-select" id="ticketWallet" name="ticketWallet" disabled>
                      <option value="">Select Provider First</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Service Fee</label>
                    <div class="form-control bg-light" id="ticketServiceFeeDisplay">-</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Cost</label>
                    <div class="form-control bg-light" id="ticketBaseAmountDisplay">₱0.00</div>
                  </div>
                  <!-- Hidden input for service fee value -->
                  <input type="hidden" id="ticketServiceFee" name="ticketServiceFee" value="0.00">
                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                      <span class="fw-bold">Ticket Total:</span>
                      <span class="fw-bold text-success fs-5" id="ticketTotalDisplay">₱0.00</span>
                    </div>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-primary w-100" onclick="addTicketToCart()">
                      <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Ticket to Cart</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Service Type Selection (hidden by default) -->
            <div class="card" id="serviceSection" style="display:none;">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-2.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <h6 class="mb-0 fw-bold"><span class="fas fa-concierge-bell me-2 text-primary"></span>Select Service Add-ons</h6>
              </div>
              <div class="card-body position-relative z-1">
                <div class="row g-3">
                  <?php
                  $iconColors = ['icon-circle-primary', 'icon-circle-success', 'icon-circle-info', 'icon-circle-warning'];
                  $textColors = ['text-primary', 'text-success', 'text-info', 'text-warning'];
                  $bgGradients = ['bg-primary-gradient', 'bg-success-gradient', 'bg-info-gradient', 'bg-warning-gradient'];
                  $colorIndex = 0;
                  foreach ($serviceTypes as $st):
                    $iconColor = $iconColors[$colorIndex % count($iconColors)];
                    $textColor = $textColors[$colorIndex % count($textColors)];
                    $bgGradient = $bgGradients[$colorIndex % count($bgGradients)];
                    $colorIndex++;
                  ?>
                  <div class="col-6 col-md-4 col-lg-3">
                    <div class="card service-type-card <?php echo $bgGradient; ?> h-100 text-center p-3 position-relative shadow-none"
                         onclick="selectServiceType(<?php echo $st['service_type_id']; ?>, '<?php echo htmlspecialchars($st['name'], ENT_QUOTES); ?>', <?php echo $st['default_amount']; ?>, <?php echo $st['allow_custom_amount'] ? 'true' : 'false'; ?>, <?php echo $st['requires_wallet'] ? 'true' : 'false'; ?>)">
                      <button class="btn btn-success quick-add-btn position-absolute top-0 end-0 m-2 shadow-sm"
                              onclick="event.stopPropagation(); quickAddServiceToCart(<?php echo $st['service_type_id']; ?>, '<?php echo htmlspecialchars($st['name'], ENT_QUOTES); ?>', <?php echo $st['default_amount']; ?>, <?php echo $st['allow_custom_amount'] ? 'true' : 'false'; ?>, <?php echo $st['requires_wallet'] ? 'true' : 'false'; ?>)"
                              title="Quick Add to Cart">
                        <span class="fas fa-plus"></span>
                      </button>
                      <div class="mb-3">
                        <div class="icon-circle <?php echo $iconColor; ?> mx-auto" style="width: 64px; height: 64px;">
                          <span class="fas fa-concierge-bell <?php echo $textColor; ?> fs-3"></span>
                        </div>
                      </div>
                      <div class="fw-bold mb-2 text-900"><?php echo htmlspecialchars($st['name']); ?></div>
                      <?php if ($st['default_amount'] > 0): ?>
                        <div class="<?php echo $textColor; ?> fw-bold fs-5">₱<?php echo number_format($st['default_amount'], 2); ?></div>
                      <?php else: ?>
                        <div class="text-600 fw-bold">Custom</div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Transactions Section -->
            <div class="card" id="transactionSection" style="display:none;">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-7.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-history me-2 text-primary"></span>Transaction History</h6>
                  <button class="btn btn-sm btn-outline-primary" onclick="loadRecentTransactions()">
                    <span class="fas fa-sync-alt"></span><span class="ms-1 d-none d-sm-inline">Refresh</span>
                  </button>
                </div>
                <!-- Filters -->
                <div class="row g-2">
                  <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Search transaction code..." onkeyup="filterTransactions()">
                  </div>
                  <div class="col-md-2">
                    <select class="form-select form-select-sm" id="filterType" onchange="filterTransactions()">
                      <option value="">All Types</option>
                      <option value="TICKET">Ticket Booking</option>
                      <option value="SERVICE">Service Only</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select form-select-sm" id="filterStatus" onchange="filterTransactions()">
                      <option value="">All Status</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                      <option value="refunded">Refunded</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm datetimepicker" id="filterDate" placeholder="Select date range" data-options='{"mode":"range","dateFormat":"Y-m-d","disableMobile":true,"position":"below","predefinedRanges":["today","last_7_days","last_month"]}'>
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="clearFilters()">
                      <span class="fas fa-times"></span><span class="ms-1 d-none d-sm-inline">Clear Filters</span>
                    </button>
                  </div>
                </div>
              </div>
              <div class="card-body p-0 position-relative z-1">
                <div class="table-responsive">
                  <table class="table table-hover table-striped mb-0 fs-10">
                    <thead class="bg-light">
                      <tr>
                        <th>Transaction Code & Type</th>
                        <th>Passenger/Description</th>
                        <th>Branch</th>
                        <th>Provider / Wallet</th>
                        <th>Payment</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="recentTransactionsList">
                      <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                          <div class="d-flex flex-column align-items-center">
                            <span class="fas fa-inbox mb-3" style="font-size: 2.5rem; color: #adb5bd;"></span>
                            <span class="fw-medium" style="color: #6c757d;">No transactions found</span>
                            <small class="text-muted mt-1">Select filters and click Refresh to load transactions</small>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Pagination -->
                <div class="card-footer bg-light">
                  <div class="d-flex flex-column align-items-center gap-2">
                    <div class="small text-muted">
                      Showing <span id="paginationStart">0</span> to <span id="paginationEnd">0</span> of <span id="paginationTotal">0</span> transactions
                    </div>
                    <nav aria-label="Page navigation">
                      <ul class="pagination pagination-sm mb-0" id="paginationNav">
                        <li class="page-item disabled">
                          <a class="page-link" href="#" onclick="changePage(0); return false;">First</a>
                        </li>
                        <li class="page-item disabled">
                          <a class="page-link" href="#" onclick="changePage('prev'); return false;">Previous</a>
                        </li>
                        <li class="page-item active">
                          <span class="page-link" id="currentPage">1</span>
                        </li>
                        <li class="page-item disabled">
                          <a class="page-link" href="#" onclick="changePage('next'); return false;">Next</a>
                        </li>
                        <li class="page-item disabled">
                          <a class="page-link" href="#" onclick="changePage('last'); return false;">Last</a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                </div>
              </div>
            </div>

            </div>

          <!-- RIGHT: Cart Panel -->
          <div class="cart-panel col-12">
            <div class="card">
              <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-shopping-cart me-2 text-primary"></span>Cart  </h6>
                  <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" id="clearCartBtn" style="display:none;">
                    <span class="fas fa-trash"></span><span class="ms-1 d-none d-sm-inline">Clear</span>
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
                <!-- Empty state -->
                <div class="empty-cart" id="emptyCartMsg">
                  <div class="empty-cart-icon"><span class="fas fa-shopping-cart"></span></div>
                  <div class="text-muted">Cart is empty.<br>Select a ticket or service to begin.</div>
                </div>
                <!-- Cart Items -->
                <div id="cartItemsList" class="p-3"></div>
              </div>
              <div class="card-footer">
                <!-- Cart Totals -->
                <div class="cart-totals" id="cartTotals">
                  <div class="total-row"><span>Subtotal:</span><span id="cartSubtotal">₱0.00</span></div>
                  <div class="total-row grand-total"><span>Total:</span><span id="cartTotal">₱0.00</span></div>
                </div>
                <!-- Actions -->
                <div class="d-grid gap-2 mt-3" id="cartActions">
                  <button class="btn btn-success btn-lg" onclick="proceedToPayment()" id="payBtn" disabled>
                    <span class="fas fa-money-bill-wave"></span><span class="ms-2 d-none d-sm-inline">Proceed to Payment</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- /POS Layout -->

      </div>
    </div>
  </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/open_session.php'; ?>
    <?php include __DIR__ . '/modals/close_session.php'; ?>
    <?php include __DIR__ . '/modals/select_customer.php'; ?>
    <?php include __DIR__ . '/modals/add_passenger.php'; ?>
    <?php include __DIR__ . '/modals/view_passenger.php'; ?>
    <?php include __DIR__ . '/modals/payment.php'; ?>
    <?php include __DIR__ . '/modals/switch_type.php'; ?>
    <?php include __DIR__ . '/modals/item_entry.php'; ?>
    <?php include __DIR__ . '/modals/clear_cart.php'; ?>
    <?php include __DIR__ . '/modals/cancel_ticket.php'; ?>
    <?php include __DIR__ . '/modals/reprint_receipt.php'; ?>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script>
    <?php
    $currentUser = Auth::user();
    $debugActive = !empty($activeSession);
    $debugSessionId = !empty($activeSession) && isset($activeSession['session_id']) ? (int)$activeSession['session_id'] : null;
    ?>

    window.POS_SESSION_ID   = <?php echo $debugSessionId ? $debugSessionId : 'null'; ?>;
    window.POS_BRANCH_ID    = <?php echo !empty($activeSession) && isset($activeSession['branch_id']) ? (int)$activeSession['branch_id'] : (isset($userBranchId) && $userBranchId ? (int)$userBranchId : 'null'); ?>;
    window.POS_USER_ID      = <?php echo isset($currentUser) && isset($currentUser['user_id']) ? (int)$currentUser['user_id'] : 'null'; ?>;
    window.POS_USER_NAME    = '<?php echo isset($currentUser) && isset($currentUser['fullname']) ? htmlspecialchars($currentUser['fullname']) : ''; ?>';
    window.POS_HAS_SESSION  = <?php echo $debugActive ? 'true' : 'false'; ?>;
    window.POS_SESSION_START = <?php echo !empty($activeSession) && isset($activeSession['started_at']) ? "'" . $activeSession['started_at'] . "'" : 'null'; ?>;

    // Branch info for receipt address (from business_branches, not system_settings)
    window.POS_BRANCH_NAME  = '<?php echo !empty($branchDetails) && isset($branchDetails['branch_name']) ? htmlspecialchars($branchDetails['branch_name']) : ''; ?>';
    window.POS_BRANCH_INFO  = {
        branch_name: '<?php echo !empty($branchDetails) && isset($branchDetails['branch_name']) ? htmlspecialchars($branchDetails['branch_name']) : ''; ?>',
        street_address: '<?php echo !empty($branchDetails) && isset($branchDetails['street_address']) ? htmlspecialchars($branchDetails['street_address']) : ''; ?>',
        barangay_name: '<?php echo !empty($branchDetails) && isset($branchDetails['barangay_name']) ? htmlspecialchars($branchDetails['barangay_name']) : ''; ?>',
        city_municipality_name: '<?php echo !empty($branchDetails) && isset($branchDetails['city_municipality_name']) ? htmlspecialchars($branchDetails['city_municipality_name']) : ''; ?>',
        province_name: '<?php echo !empty($branchDetails) && isset($branchDetails['province_name']) ? htmlspecialchars($branchDetails['province_name']) : ''; ?>',
        region_name: '<?php echo !empty($branchDetails) && isset($branchDetails['region_name']) ? htmlspecialchars($branchDetails['region_name']) : ''; ?>',
        zip_code: '<?php echo !empty($branchDetails) && isset($branchDetails['zip_code']) ? htmlspecialchars($branchDetails['zip_code']) : ''; ?>',
        landmark: '<?php echo !empty($branchDetails) && isset($branchDetails['landmark']) ? htmlspecialchars($branchDetails['landmark']) : ''; ?>',
        contact_number: '<?php echo !empty($branchDetails) && isset($branchDetails['contact_number']) ? htmlspecialchars($branchDetails['contact_number']) : ''; ?>'
    };
    console.log('[POS] POS_BRANCH_INFO loaded:', window.POS_BRANCH_INFO);
    console.log('[POS] PHP branchDetails:', <?php echo json_encode($branchDetails); ?>);

    // Cancellation settings
    window.CANCELLATION_SETTINGS = {
        requires_confirmation: <?php echo ($cancellationSettings['cancellation_requires_confirmation'] ?? 1) ? 'true' : 'false'; ?>,
        auto_approve: <?php echo ($cancellationSettings['cancellation_auto_approve'] ?? 0) ? 'true' : 'false'; ?>,
        refund_to_wallet: <?php echo ($cancellationSettings['cancellation_refund_to_wallet'] ?? 1) ? 'true' : 'false'; ?>,
        refund_processing_days: <?php echo intval($cancellationSettings['cancellation_refund_processing_days'] ?? 3); ?>,
        allow_partial: <?php echo ($cancellationSettings['cancellation_allow_partial'] ?? 0) ? 'true' : 'false'; ?>
    };

    // POS Settings and user permissions
    window.POS_SETTINGS = {
        cashier_can_open: <?php echo $posCashierOpenRaw === 1 ? 'true' : 'false'; ?>,
        cashier_can_close: <?php echo $posCashierCloseRaw === 1 ? 'true' : 'false'; ?>,
        manager_can_open: <?php echo $posManagerOpenRaw === 1 ? 'true' : 'false'; ?>,
        manager_can_close: <?php echo $posManagerCloseRaw === 1 ? 'true' : 'false'; ?>
    };
    window.POS_USER_ROLE = '<?php echo $userRoleCode; ?>';
    window.POS_CAN_OPEN = (window.POS_USER_ROLE === 'SUPER_ADMIN' || window.POS_USER_ROLE === 'MANAGER') ? window.POS_SETTINGS.manager_can_open : window.POS_SETTINGS.cashier_can_open;
    window.POS_CAN_CLOSE = (window.POS_USER_ROLE === 'SUPER_ADMIN' || window.POS_USER_ROLE === 'MANAGER') ? window.POS_SETTINGS.manager_can_close : window.POS_SETTINGS.cashier_can_close;

    // Printer Settings
    window.PRINTER_SETTINGS = {
        enabled: <?php echo ($printerSettings['receipt_printing_enabled'] ?? 1) ? 'true' : 'false'; ?>,
        paperWidth: '<?php echo $printerSettings['receipt_paper_width'] ?? '80mm'; ?>',
        autoPrint: <?php echo ($printerSettings['receipt_auto_print'] ?? 1) ? 'true' : 'false'; ?>,
        showPreview: <?php echo ($printerSettings['receipt_show_preview'] ?? 0) ? 'true' : 'false'; ?>,
        copies: <?php echo intval($printerSettings['receipt_copies'] ?? 1); ?>,
        addressSource: '<?php echo $printerSettings['receipt_address_source'] ?? 'company'; ?>',
        autoCut: <?php echo ($printerSettings['receipt_auto_cut'] ?? 1) ? 'true' : 'false'; ?>,
        openCashDrawer: <?php echo ($printerSettings['receipt_open_cash_drawer'] ?? 0) ? 'true' : 'false'; ?>,
        showCashier: <?php echo ($printerSettings['receipt_show_cashier'] ?? 1) ? 'true' : 'false'; ?>,
        showPaymentMethod: <?php echo ($printerSettings['receipt_show_payment_method'] ?? 1) ? 'true' : 'false'; ?>,
        showBranch: <?php echo ($printerSettings['receipt_show_branch'] ?? 1) ? 'true' : 'false'; ?>,
        showTin: <?php echo ($printerSettings['receipt_show_tin'] ?? 1) ? 'true' : 'false'; ?>,
        showServiceFee: <?php echo ($printerSettings['receipt_show_service_fee'] ?? 1) ? 'true' : 'false'; ?>,
        showBaseAmount: <?php echo ($printerSettings['receipt_show_base_amount'] ?? 1) ? 'true' : 'false'; ?>,
        showDiscount: <?php echo ($printerSettings['receipt_show_discount'] ?? 1) ? 'true' : 'false'; ?>,
        qrEnabled: <?php echo ($printerSettings['receipt_qr_code_enabled'] ?? 0) ? 'true' : 'false'; ?>,
        qrFormat: '<?php echo $printerSettings['receipt_qr_format'] ?? 'TRANSACTION_ID'; ?>',
        logoEnabled: <?php echo ($printerSettings['receipt_logo_enabled'] ?? 0) ? 'true' : 'false'; ?>,
        printerType: '<?php echo $printerSettings['printer_type'] ?? 'THERMAL'; ?>',
        headerText: '<?php echo htmlspecialchars($printerSettings['receipt_header_text'] ?? ''); ?>',
        footerText: '<?php echo htmlspecialchars($printerSettings['receipt_footer'] ?? 'Thank you for your business!'); ?>',
        customFooter: '<?php echo htmlspecialchars($printerSettings['receipt_custom_footer'] ?? ''); ?>',
        customerCopy: <?php echo ($printerSettings['receipt_customer_copy'] ?? 0) ? 'true' : 'false'; ?>,
        merchantCopy: <?php echo ($printerSettings['receipt_merchant_copy'] ?? 1) ? 'true' : 'false'; ?>
    };

    // Company Info for Receipts
    window.COMPANY_INFO = {
        name: '<?php echo htmlspecialchars($printerSettings['company_name'] ?? ''); ?>',
        address: '<?php echo htmlspecialchars($printerSettings['company_address'] ?? ''); ?>',
        contact: '<?php echo htmlspecialchars($printerSettings['company_contact_number'] ?? ''); ?>',
        email: '<?php echo htmlspecialchars($printerSettings['company_email'] ?? ''); ?>',
        tin: '<?php echo htmlspecialchars($printerSettings['company_tin'] ?? ''); ?>',
        logo: '<?php echo !empty($printerSettings['system_logo']) ? BASE_URL . htmlspecialchars($printerSettings['system_logo']) : ''; ?>',
        // BIR accreditation details
        birPermitNumber: '<?php echo htmlspecialchars($printerSettings['bir_permit_number'] ?? ''); ?>',
        birAccreditationNumber: '<?php echo htmlspecialchars($printerSettings['bir_accreditation_number'] ?? ''); ?>',
        birValidityFrom: '<?php echo htmlspecialchars($printerSettings['bir_validity_from'] ?? ''); ?>',
        birValidityTo: '<?php echo htmlspecialchars($printerSettings['bir_validity_to'] ?? ''); ?>',
        birMin: '<?php echo htmlspecialchars($printerSettings['bir_min'] ?? ''); ?>',
        birSerialNumber: '<?php echo htmlspecialchars($printerSettings['bir_machine_serial'] ?? ''); ?>'
    };
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/qz-tray.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.9.0/jsrsasign-all-min.js"></script>
  <script>
    // QZ Tray signing credentials (served via PHP to avoid public file exposure)
    window.QZ_CERT = <?php echo json_encode(
      file_get_contents(dirname(__DIR__) . '/views/digital-certificate.txt')
    ); ?>;
    window.QZ_PRIVATE_KEY = <?php echo json_encode(
      file_get_contents(dirname(__DIR__) . '/views/private-key.pem')
    ); ?>;
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/pos-printer.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/pos-printer.js'); ?>"></script>
  <script src="<?php echo BASE_URL; ?>/resources/vendors/choices/choices.min.js?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/resources/vendors/choices/choices.min.js'); ?>"></script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/pos.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/pos.js'); ?>"></script>
  <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
</body>
</html>
