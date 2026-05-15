<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(__DIR__)) . '/includes/head.php';
?>
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
        ?>

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <div class="card-header z-1">
                <div class="row align-items-center">
                  <div class="col d-flex align-items-center">
                    <img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Cashier <span class="text-info fw-medium">POS</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item active">Cashier POS</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-auto d-flex gap-2">
                    <?php if ($activeSession): ?>
                      <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-2 bg-success bg-opacity-10 border border-success" style="cursor: pointer;" onclick="toggleSessionBanner()">
                        <span class="fas fa-circle text-success session-active-pulse" style="font-size: 8px;"></span>
                        <span class="text-success fw-semibold">Session Active</span>
                        <span class="fas fa-chevron-down text-success ms-1" id="sessionBannerToggleIcon"></span>
                      </div>
                      <button class="btn btn-outline-danger btn-sm rounded-2" onclick="openCloseSession()">
                        <span class="fas fa-stop-circle me-1"></span>Close
                      </button>
                    <?php else: ?>
                      <div class="d-flex align-items-center px-3 py-2 rounded-2 bg-secondary bg-opacity-10 border border-secondary">
                        <span class="text-secondary fw-semibold">No Active Session</span>
                      </div>
                      <button class="btn btn-success btn-sm rounded-2" onclick="openSessionModal.show()">
                        <span class="fas fa-play-circle me-1"></span>Open
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!$activeSession): ?>
        <!-- No session warning -->
        <div class="alert alert-warning d-flex align-items-center mb-3">
          <span class="fas fa-exclamation-triangle me-3 fs-4"></span>
          <div>
            <strong>No Active Session.</strong> You must open a cashier session before processing transactions.
            <button class="btn btn-sm btn-warning ms-3" onclick="openSessionModal.show()">
              <span class="fas fa-play-circle me-1"></span>Open Session Now
            </button>
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
                    Started <?php echo date('M j, Y h:i A', strtotime($activeSession['started_at'])); ?> •
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
                      <span class="fas fa-ticket-alt me-2"></span>Ticket Booking
                    </button>
                  </div>
                  <div class="col-4">
                    <button type="button" class="btn btn-outline-success d-block w-100 py-3 rounded-4 position-relative" id="btnServiceType" onclick="switchTransactionType('service')">
                      <span class="fas fa-concierge-bell me-2"></span>Service Only
                    </button>
                  </div>
                  <div class="col-4">
                    <button type="button" class="btn btn-outline-info d-block w-100 py-3 rounded-4 position-relative" id="btnTransactionType" onclick="switchTransactionType('transaction')">
                      <span class="fas fa-history me-2"></span>Transactions
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ticket Selection (shown by default) -->
            <div class="card mb-3" id="ticketSection">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-6.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <h6 class="mb-0 fw-bold"><span class="fas fa-ticket-alt me-2 text-primary"></span>Ticket Details  </h6>
              </div>
              <div class="card-body position-relative z-1">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketPassenger">Passenger</label>
                    <div class="position-relative">
                      <div class="input-group mb-2">
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
                      <small class="text-muted">Start typing to search passengers (min 2 characters)</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketTravelDate">Travel Date</label>
                    <input type="date" class="form-control" id="ticketTravelDate" name="ticketTravelDate">
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
                    <label class="form-label fw-semibold" for="ticketBaseAmount">Base Amount (₱)</label>
                    <input type="number" class="form-control" id="ticketBaseAmount" name="ticketBaseAmount" min="0" step="0.01" placeholder="0.00" oninput="computeTicketTotal()">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Discount</label>
                    <select class="form-select" id="ticketDiscount" name="ticketDiscount" onchange="computeTicketTotal()">
                      <option value="0">No Discount</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketAccommodation">Accommodation</label>
                    <select class="form-select" id="ticketAccommodation" name="ticketAccommodation">
                      <option value="">None</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketWallet">Wallet</label>
                    <select class="form-select" id="ticketWallet" name="ticketWallet" onchange="loadServiceFeeForWallet()">
                      <option value="">Select Wallet</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Service Fee</label>
                    <div class="form-control bg-light" id="ticketServiceFeeDisplay">-</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Base Amount</label>
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
                      <span class="fas fa-plus me-1"></span>Add Ticket to Cart
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Service Type Selection (hidden by default) -->
            <div class="card mb-3" id="serviceSection" style="display:none;">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-2.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <h6 class="mb-0 fw-bold"><span class="fas fa-concierge-bell me-2 text-primary"></span>Select Service</h6>
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

            <!-- Service Add-ons (for tickets) -->
            <div class="card mb-3" id="serviceAddonsSection" style="display:none;">
              <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-plus-circle me-2 text-success"></span>Add Service Add-ons</h6>
                  <button class="btn btn-sm btn-outline-secondary" onclick="toggleServiceAddons()">
                    <span class="fas fa-chevron-down"></span>
                  </button>
                </div>
              </div>
              <div class="card-body" id="serviceAddonsBody" style="display:none;">
                <div class="row g-2 mb-3">
                  <?php foreach ($serviceTypes as $st): ?>
                  <div class="col-6 col-md-4 col-lg-3">
                    <div class="card service-type-card text-center p-2"
                         onclick="selectServiceAddon(<?php echo $st['service_type_id']; ?>, '<?php echo htmlspecialchars($st['name'], ENT_QUOTES); ?>', <?php echo $st['default_amount']; ?>, <?php echo $st['allow_custom_amount'] ? 'true' : 'false'; ?>)">
                      <div class="mb-1">
                        <span class="fas fa-concierge-bell text-primary fs-4"></span>
                      </div>
                      <div class="fw-semibold small"><?php echo htmlspecialchars($st['name']); ?></div>
                      <?php if ($st['default_amount'] > 0): ?>
                        <div class="text-success" style="font-size:0.75rem;">₱<?php echo number_format($st['default_amount'], 2); ?></div>
                      <?php else: ?>
                        <div class="text-muted" style="font-size:0.75rem;">Custom</div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Transactions Section -->
            <div class="card mb-3" id="transactionSection" style="display:none;">
              <div class="bg-holder d-none d-lg-block bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-7.png); pointer-events: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;">
              </div>
              <!--/.bg-holder-->
              <div class="card-header py-2 bg-light position-relative z-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-history me-2 text-primary"></span>Transaction History</h6>
                  <button class="btn btn-sm btn-outline-primary" onclick="loadRecentTransactions()">
                    <span class="fas fa-sync-alt me-1"></span>Refresh
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
                      <option value="booked">Booked</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                      <option value="refunded">Refunded</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm pos-date-picker" id="filterDate" placeholder="Select date range" onchange="filterTransactions()">
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="clearFilters()">
                      <span class="fas fa-times me-1"></span>Clear Filters
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
                        <th>Provider</th>
                        <th>Travel Date</th>
                        <th>Origin/Dest</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="recentTransactionsList">
                      <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                          <span class="fas fa-spinner fa-spin me-2"></span>Loading transactions...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Pagination -->
                <div class="card-footer bg-light">
                  <div class="d-flex justify-content-between align-items-center">
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
          <div class="cart-panel">
            <div class="card">
              <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-shopping-cart me-2 text-primary"></span>Cart  </h6>
                  <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" id="clearCartBtn" style="display:none;">
                    <span class="fas fa-trash me-1"></span>Clear
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
                    <span class="fas fa-money-bill-wave me-2"></span>Proceed to Payment
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
    window.POS_HAS_SESSION  = <?php echo $debugActive ? 'true' : 'false'; ?>;
    window.POS_SESSION_START = <?php echo !empty($activeSession) && isset($activeSession['started_at']) ? "'" . $activeSession['started_at'] . "'" : 'null'; ?>;
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/assets/js/pos.js"></script>
</body>
</html>
