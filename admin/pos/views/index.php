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
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h2 class="mb-1"><span class="fas fa-cash-register me-2 text-primary"></span>Cashier POS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Cashier POS</li>
                  </ol>
                </nav>
              </div>
              <div class="d-flex gap-2">
                <?php if ($activeSession): ?>
                  <span class="badge bg-success fs-6 px-3 py-2">
                    <span class="fas fa-circle me-1"></span>Session Active
                  </span>
                  <button class="btn btn-outline-danger btn-sm" onclick="openCloseSession()">
                    <span class="fas fa-stop-circle me-1"></span>Close Session
                  </button>
                <?php else: ?>
                  <span class="badge bg-secondary fs-6 px-3 py-2">No Active Session</span>
                  <button class="btn btn-success btn-sm" onclick="openSessionModal.show()">
                    <span class="fas fa-play-circle me-1"></span>Open Session
                  </button>
                <?php endif; ?>
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
                  Started <?php echo date('h:i A', strtotime($activeSession['started_at'])); ?> •
                  Opening Cash: <strong>₱<?php echo number_format($activeSession['starting_cash'], 2); ?></strong>
                </span>
              </div>
              <div class="col-auto">
                <span class="badge bg-soft-success text-success">Session #<?php echo $activeSession['session_id']; ?></span>
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
              <div class="card-header py-2 bg-light">
                <h6 class="mb-0 fw-bold"><span class="fas fa-exchange-alt me-2 text-primary"></span>Transaction Type</h6>
              </div>
              <div class="card-body">
                <div class="btn-group w-100" role="group">
                  <button type="button" class="btn btn-outline-primary active" id="btnTicketType" onclick="switchTransactionType('ticket')">
                    <span class="fas fa-ticket-alt me-2"></span>Ticket Booking
                  </button>
                  <button type="button" class="btn btn-outline-primary" id="btnServiceType" onclick="switchTransactionType('service')">
                    <span class="fas fa-concierge-bell me-2"></span>Service Only
                  </button>
                </div>
              </div>
            </div>

            <!-- Ticket Selection (shown by default) -->
            <div class="card mb-3" id="ticketSection">
              <div class="card-header py-2 bg-light">
                <h6 class="mb-0 fw-bold"><span class="fas fa-ticket-alt me-2 text-primary"></span>Ticket Details</h6>
              </div>
              <div class="card-body">
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
                      <small class="text-muted">Start typing to search passengers</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketTravelDate">Travel Date</label>
                    <input type="date" class="form-control" id="ticketTravelDate" name="ticketTravelDate">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketOrigin">Origin</label>
                    <input type="text" class="form-control" id="ticketOrigin" name="ticketOrigin" placeholder="e.g. Manila">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="ticketDestination">Destination</label>
                    <input type="text" class="form-control" id="ticketDestination" name="ticketDestination" placeholder="e.g. Baguio">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold" for="ticketBaseAmount">Base Amount (₱)</label>
                    <input type="number" class="form-control" id="ticketBaseAmount" name="ticketBaseAmount" min="0" step="0.01" value="0.00" oninput="computeTicketTotal()">
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
              <div class="card-header py-2 bg-light">
                <h6 class="mb-0 fw-bold"><span class="fas fa-concierge-bell me-2 text-primary"></span>Select Service</h6>
              </div>
              <div class="card-body">
                <div class="row g-2">
                  <?php foreach ($serviceTypes as $st): ?>
                  <div class="col-6 col-md-4 col-lg-3">
                    <div class="card service-type-card text-center p-2"
                         onclick="selectServiceType(<?php echo $st['service_type_id']; ?>, '<?php echo htmlspecialchars($st['name'], ENT_QUOTES); ?>', <?php echo $st['default_amount']; ?>, <?php echo $st['allow_custom_amount'] ? 'true' : 'false'; ?>, <?php echo $st['requires_wallet'] ? 'true' : 'false'; ?>)">
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

            <!-- Item Entry -->
            <div class="card mb-3" id="itemEntryCard" style="display:none;">
              <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-plus-circle me-2 text-success"></span>Add Item</h6>
                  <button class="btn btn-sm btn-outline-secondary" onclick="cancelItemEntry()">
                    <span class="fas fa-times me-1"></span>Cancel
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="itemServiceName">Service</label>
                    <input type="text" class="form-control" id="itemServiceName" name="itemServiceName" readonly>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold" for="itemQty">Qty</label>
                    <input type="number" class="form-control" id="itemQty" name="itemQty" value="1" min="1" oninput="computeItemTotal()">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold" for="itemUnitPrice">Unit Price (₱)</label>
                    <input type="number" class="form-control" id="itemUnitPrice" name="itemUnitPrice" value="0.00" min="0" step="0.01" oninput="computeItemTotal()">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold" for="itemDescription">Description / Remarks</label>
                    <input type="text" class="form-control" id="itemDescription" name="itemDescription" placeholder="Optional">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Item Total</label>
                    <div class="form-control bg-light fw-bold text-success" id="itemTotalDisplay">₱0.00</div>
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-success w-100" onclick="addItemToCart()">
                      <span class="fas fa-plus me-1"></span>Add to Cart
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Section -->
            <div class="card mb-3" id="paymentSection" style="display:none;">
              <div class="card-header py-2 bg-light">
                <h6 class="mb-0 fw-bold"><span class="fas fa-money-bill-wave me-2 text-success"></span>Payment</h6>
              </div>
              <div class="card-body">

                <!-- Payment Methods -->
                <label class="form-label fw-semibold mb-2">Payment Methods</label>
                <div class="row g-2 mb-3" id="paymentMethodsGrid">
                  <?php foreach ($paymentMethods as $pm):
                    $icons = ['CASH'=>'fa-money-bill-wave','BANK_TRANSFER'=>'fa-university','E_WALLET'=>'fa-mobile-alt','CHARGE'=>'fa-file-invoice','CARD'=>'fa-credit-card','OTHER'=>'fa-ellipsis-h'];
                    $colors = ['CASH'=>'success','BANK_TRANSFER'=>'primary','E_WALLET'=>'purple','CHARGE'=>'warning','CARD'=>'info','OTHER'=>'secondary'];
                    $icon = $pm['icon'] ?: ($icons[$pm['method_type']] ?? 'fa-credit-card');
                    $color = $colors[$pm['method_type']] ?? 'secondary';
                  ?>
                  <div class="col-6 col-md-4 col-lg-3">
                    <div class="card payment-method-btn text-center p-2"
                         data-method-id="<?php echo $pm['method_id']; ?>"
                         data-method-code="<?php echo htmlspecialchars($pm['method_code']); ?>"
                         data-method-name="<?php echo htmlspecialchars($pm['method_name']); ?>"
                         data-method-type="<?php echo htmlspecialchars($pm['method_type']); ?>"
                         data-requires-confirmation="<?php echo $pm['requires_confirmation'] ? '1' : '0'; ?>"
                         data-requires-customer="<?php echo $pm['requires_customer'] ? '1' : '0'; ?>"
                         data-requires-reference="<?php echo $pm['requires_reference'] ? '1' : '0'; ?>"
                         onclick="selectPaymentMethod(this)">
                      <div class="mb-1"><span class="fas <?php echo $icon; ?> text-<?php echo $color; ?> fs-4"></span></div>
                      <div class="fw-semibold small"><?php echo htmlspecialchars($pm['method_name']); ?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>

                <!-- Payment Entry Row -->
                <div id="paymentEntryRow" style="display:none;">
                  <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" for="selectedMethodName">Method</label>
                      <input type="text" class="form-control" id="selectedMethodName" name="selectedMethodName" readonly>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label fw-semibold" for="paymentAmount">Amount (₱)</label>
                      <input type="number" class="form-control" id="paymentAmount" name="paymentAmount" min="0" step="0.01" placeholder="0.00" oninput="computeChange()">
                      <div id="paymentAmountHint" class="form-text text-muted"></div>
                    </div>
                    <div class="col-md-3" id="referenceRow" style="display:none;">
                      <label class="form-label fw-semibold" for="referenceNumber">Reference # <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="referenceNumber" name="referenceNumber" placeholder="e.g. GCash ref">
                    </div>
                    <div class="col-md-3" id="bankAccountRow" style="display:none;">
                      <label class="form-label fw-semibold" for="bankAccountSelect">Bank Account</label>
                      <select class="form-select" id="bankAccountSelect" name="bankAccountSelect">
                        <option value="">Select Account</option>
                        <?php foreach ($bankAccounts as $ba): ?>
                          <option value="<?php echo $ba['bank_account_id']; ?>"
                                  data-method="<?php echo htmlspecialchars($ba['method_code'] ?? ''); ?>"
                                  data-method-type="<?php echo htmlspecialchars($ba['method_code'] ?? ''); ?>">
                            <?php echo htmlspecialchars($ba['bank_name'] . ' — ' . $ba['account_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                      <button class="btn btn-primary flex-grow-1" onclick="addPaymentLine()">
                        <span class="fas fa-plus me-1"></span>Add
                      </button>
                      <button class="btn btn-outline-secondary" onclick="cancelPaymentEntry()">
                        <span class="fas fa-times"></span>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Payment Lines -->
                <div id="paymentLinesList" class="mt-3"></div>

                <!-- Totals Summary -->
                <div class="cart-totals mt-3" id="paymentTotals" style="display:none;">
                  <div class="total-row"><span>Total Due:</span><span id="ptTotalDue" class="fw-bold">₱0.00</span></div>
                  <div class="total-row"><span>Total Paid:</span><span id="ptTotalPaid" class="fw-bold text-success">₱0.00</span></div>
                  <div class="total-row grand-total"><span>Change:</span><span id="ptChange" class="fw-bold">₱0.00</span></div>
                </div>

              </div>
            </div>

          </div>

          <!-- RIGHT: Cart Panel -->
          <div class="cart-panel">
            <div class="card">
              <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 fw-bold"><span class="fas fa-shopping-cart me-2 text-primary"></span>Cart</h6>
                  <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" id="clearCartBtn" style="display:none;">
                    <span class="fas fa-trash me-1"></span>Clear
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
                <!-- Empty state -->
                <div class="empty-cart" id="emptyCartMsg">
                  <div class="empty-cart-icon"><span class="fas fa-shopping-cart"></span></div>
                  <div class="text-muted">Cart is empty.<br>Select a service to begin.</div>
                </div>
                <!-- Cart Items -->
                <div id="cartItemsList" class="p-3"></div>
              </div>
              <div class="card-footer">
                <!-- Cart Totals -->
                <div class="cart-totals" id="cartTotals" style="display:none;">
                  <div class="total-row"><span>Subtotal:</span><span id="cartSubtotal">₱0.00</span></div>
                  <div class="total-row grand-total"><span>Total:</span><span id="cartTotal">₱0.00</span></div>
                </div>
                <!-- Actions -->
                <div class="d-grid gap-2 mt-3" id="cartActions" style="display:none !important;">
                  <button class="btn btn-success btn-lg" onclick="proceedToPayment()" id="payBtn">
                    <span class="fas fa-money-bill-wave me-2"></span>Proceed to Payment
                  </button>
                </div>
                <div class="d-grid gap-2 mt-2" id="confirmOrderSection" style="display:none;">
                  <button class="btn btn-primary btn-lg" onclick="confirmOrder()" id="confirmOrderBtn">
                    <span class="fas fa-check-circle me-2"></span>Confirm & Process
                  </button>
                  <button class="btn btn-outline-secondary" onclick="backToCart()">
                    <span class="fas fa-arrow-left me-1"></span>Back to Cart
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

    <!-- Switch Transaction Type Confirmation Modal -->
    <div class="modal fade" id="switchTypeModal" tabindex="-1" aria-labelledby="switchTypeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="switchTypeModalLabel">
              <span class="fas fa-exclamation-triangle text-warning me-2"></span>Switch Transaction Type
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">Switching transaction type will clear the current cart. Are you sure you want to continue?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="cancelSwitchType()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmSwitchType()">
              <span class="fas fa-check me-1"></span>Yes, Continue
            </button>
          </div>
        </div>
      </div>
    </div>

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
