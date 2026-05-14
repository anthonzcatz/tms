<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="paymentModalLabel">
            <span class="fas fa-money-bill-wave me-2"></span>Payment
          </h4>
          <p class="fs-10 mb-0 text-white">Complete payment for your order</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">

        <!-- Cart Summary -->
        <div class="card mb-4 border-0 bg-light">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0"><span class="fas fa-shopping-cart me-2 text-primary"></span>Order Summary</h6>
              <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#paymentCartItemsCollapse">
                <span class="fas fa-chevron-down" id="orderSummaryToggleIcon"></span>
              </button>
            </div>
            <div class="collapse" id="paymentCartItemsCollapse">
              <div id="paymentCartItems" class="mb-3" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
            <div class="row align-items-center pt-3 border-top">
              <div class="col">
                <span class="text-muted fw-semibold">Total Due:</span>
              </div>
              <div class="col-auto">
                <span class="display-6 fw-bold text-primary" id="paymentTotalDue">₱0.00</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Methods -->
        <div class="mb-4">
          <h6 class="fw-bold mb-3"><span class="fas fa-credit-card me-2 text-primary"></span>Select Payment Method</h6>
          <div class="row g-2" id="paymentMethodsGrid">
            <?php foreach ($paymentMethods as $pm):
              $icons = ['CASH'=>'fa-money-bill-wave','BANK_TRANSFER'=>'fa-university','E_WALLET'=>'fa-mobile-alt','CHARGE'=>'fa-file-invoice','CARD'=>'fa-credit-card','OTHER'=>'fa-ellipsis-h'];
              $colors = ['CASH'=>'success','BANK_TRANSFER'=>'primary','E_WALLET'=>'purple','CHARGE'=>'warning','CARD'=>'info','OTHER'=>'secondary'];
              $icon = $pm['icon'] ?: ($icons[$pm['method_type']] ?? 'fa-credit-card');
              $color = $colors[$pm['method_type']] ?? 'secondary';
            ?>
            <div class="col-4 col-md-4 col-lg-4">
              <div class="card payment-method-btn text-center p-3 h-100"
                   data-method-id="<?php echo $pm['method_id']; ?>"
                   data-method-code="<?php echo htmlspecialchars($pm['method_code']); ?>"
                   data-method-name="<?php echo htmlspecialchars($pm['method_name']); ?>"
                   data-method-type="<?php echo htmlspecialchars($pm['method_type']); ?>"
                   data-requires-confirmation="<?php echo $pm['requires_confirmation'] ? '1' : '0'; ?>"
                   data-requires-customer="<?php echo $pm['requires_customer'] ? '1' : '0'; ?>"
                   data-requires-reference="<?php echo $pm['requires_reference'] ? '1' : '0'; ?>"
                   onclick="selectPaymentMethod(this)">
                <div class="mb-2"><span class="fas <?php echo $icon; ?> text-<?php echo $color; ?> fs-3"></span></div>
                <div class="fw-semibold small"><?php echo htmlspecialchars($pm['method_name']); ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Payment Entry Row -->
        <div id="paymentEntryRow" class="card mb-4 border-0 shadow-sm" style="display:none;">
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="selectedMethodName">
                  <span class="fas fa-tag me-1 text-muted"></span>Method
                </label>
                <input type="text" class="form-control bg-light" id="selectedMethodName" name="selectedMethodName" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="paymentAmount">
                  <span class="fas fa-money-bill-wave me-1 text-muted"></span>Amount (₱)
                </label>
                <input type="number" class="form-control fw-bold" id="paymentAmount" name="paymentAmount" min="0" step="0.01" placeholder="0.00" oninput="computeChange()">
              </div>
              <div class="col-md-4" id="referenceRow" style="display:none;">
                <label class="form-label fw-semibold" for="referenceNumber">
                  <span class="fas fa-hashtag me-1 text-muted"></span>Reference # <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="referenceNumber" name="referenceNumber" placeholder="e.g. GCash ref">
              </div>
              <div class="col-md-4" id="bankAccountRow" style="display:none;">
                <label class="form-label fw-semibold" for="bankAccountSelect">
                  <span class="fas fa-university me-1 text-muted"></span>Bank Account
                </label>
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
              <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" onclick="addPaymentLine()">
                  <span class="fas fa-plus me-1"></span>Add Payment
                </button>
                <button class="btn btn-outline-secondary" onclick="cancelPaymentEntry()">
                  <span class="fas fa-times"></span>
                </button>
              </div>
            </div>
            <div id="paymentAmountHint" class="form-text text-muted small text-end mt-2" style="font-family: 'Century Gothic', sans-serif;"></div>
          </div>
        </div>

        <!-- Payment Lines -->
        <div id="paymentLinesList" class="mb-4"></div>

        <!-- Totals Summary -->
        <div class="card border-0 bg-success bg-opacity-10" id="paymentTotals" style="display:none;">
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-4">
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Total Due:</span>
                  <span class="fw-bold" id="ptTotalDue">₱0.00</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex justify-content-between">
                  <span class="text-muted">Total Paid:</span>
                  <span class="fw-bold text-success" id="ptTotalPaid">₱0.00</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex justify-content-between">
                  <span class="fw-bold">Change:</span>
                  <span class="fw-bold fs-5 text-primary" id="ptChange">₱0.00</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="backToCart()">
          <span class="fas fa-arrow-left me-1"></span>Back to Cart
        </button>
        <button type="button" class="btn btn-primary btn-lg" onclick="confirmOrder()" id="confirmOrderBtn">
          <span class="fas fa-check-circle me-2"></span>Confirm & Process
        </button>
      </div>
    </div>
  </div>
</div>
