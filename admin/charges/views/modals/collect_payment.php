<!-- Collect Payment Modal -->
<div class="modal fade" id="collectPaymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="collectPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="collectPaymentModalLabel">
            <span class="fas fa-hand-holding-usd me-2"></span>Collect Payment
          </h4>
          <p class="fs-10 mb-0 text-white">Record a payment against customer's outstanding balance</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="collectPassengerId">

        <!-- Customer Summary -->
        <div class="alert alert-warning d-flex align-items-start mb-3 py-3">
          <span class="fas fa-user-circle me-3 fs-4 mt-1"></span>
          <div>
            <div class="fw-bold fs-6" id="collectCustomerName">—</div>
            <div class="text-muted small" id="collectCustomerContact">—</div>
            <div class="mt-2 row g-2">
              <div class="col-4">
                <div class="small text-muted">Base</div>
                <strong class="text-primary" id="collectBaseBalance">₱0.00</strong>
              </div>
              <div class="col-4">
                <div class="small text-muted">Service Fee</div>
                <strong class="text-info" id="collectFeeBalance">₱0.00</strong>
              </div>
              <div class="col-4">
                <div class="small text-muted">Total</div>
                <strong class="text-danger" id="collectBalance">₱0.00</strong>
              </div>
            </div>
            <div class="mt-2" id="collectCollectibleRow">
              Collectible now: <strong class="text-success fs-5" id="collectCollectible">₱0.00</strong>
              <span id="collectModeBadge" class="badge bg-soft-secondary text-secondary ms-2">Customer</span>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Amount to Collect (₱) <span class="text-danger">*</span></label>
            <input type="number" class="form-control fs-5 fw-bold" id="collectAmount" min="0.01" step="0.01" placeholder="0.00" oninput="validateCollectAmount()">
            <div class="form-text d-flex justify-content-between align-items-center">
              <span>Can be partial or full payment.</span>
              <a href="javascript:void(0)" class="small" id="collectUseFullBalance" onclick="setFullCollectAmount()">Use full balance</a>
            </div>
            <div id="collectAmountFeedback" class="invalid-feedback" style="display:none;"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Branch</label>
            <input type="text" class="form-control" id="collectBranchName" readonly>
            <input type="hidden" id="collectBranchId">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select" id="collectMethodId" onchange="toggleCollectRef()">
              <option value="">Select method</option>
              <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?php echo $pm['method_id']; ?>"
                        data-type="<?php echo htmlspecialchars($pm['method_type']); ?>"
                        data-req-ref="<?php echo $pm['requires_reference'] ? '1' : '0'; ?>"
                        data-req-bank="<?php echo $pm['method_type'] === 'BANK_TRANSFER' || $pm['method_type'] === 'E_WALLET' ? '1' : '0'; ?>">
                  <?php echo htmlspecialchars($pm['method_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6" id="collectBankRow" style="display:none;">
            <label class="form-label fw-semibold">Bank Account <span class="text-danger">*</span></label>
            <select class="form-select" id="collectBankAccountId">
              <option value="">Select bank account</option>
              <?php foreach ($bankAccounts as $bank): ?>
                <option value="<?php echo $bank['bank_account_id']; ?>">
                  <?php echo htmlspecialchars($bank['bank_name'] . ' - ' . $bank['account_number']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12" id="confirmationInfoBox" style="display:none;">
            <div class="alert alert-info d-flex align-items-start py-2">
              <span class="fas fa-info-circle me-2 mt-1"></span>
              <div class="small">
                <strong>Confirmation Required:</strong> This payment will require manager approval before the bank account balance is updated. It will appear in <a href="<?php echo BASE_URL; ?>/admin/bank-confirmations/" target="_blank">Bank Confirmations</a>.
              </div>
            </div>
          </div>
          <div class="col-md-6" id="collectRefRow" style="display:none;">
            <label class="form-label fw-semibold">Reference # <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="collectRefNum" placeholder="e.g. GCash ref / bank ref">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea class="form-control" id="collectNotes" rows="2" placeholder="Optional payment notes"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="collectSubmitBtn" onclick="submitCollectPayment()">
          <span class="fas fa-check-circle me-1" id="collectSubmitIcon"></span>
          <span id="collectSubmitLabel">Record Payment</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Charge History Modal -->
<div class="modal fade" id="chargeHistoryModal" tabindex="-1" aria-labelledby="chargeHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="chargeHistoryModalLabel">
            <span class="fas fa-history me-2"></span>Charge History
          </h4>
          <p class="fs-10 mb-0 text-white" id="historyCustomerLabel">Customer charge history</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3 align-items-center">
          <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="historyBranchFilter" onchange="applyHistoryFilters()">
              <option value="">All Branches</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="historyTypeFilter" onchange="applyHistoryFilters()">
              <option value="">All Types</option>
              <option value="charge">Charge</option>
              <option value="payment">Payment</option>
              <option value="reversal">Reversal</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <button class="btn btn-outline-primary btn-sm w-100" onclick="printChargeStatement()">
              <span class="fas fa-file-invoice me-1"></span>Print Statement
            </button>
          </div>
          <div class="col-12 col-md-3">
            <div id="chargeHistoryPager" class="d-flex justify-content-md-end align-items-center"></div>
          </div>
        </div>
        <div id="chargeHistoryContent">
          <div class="text-center py-4"><span class="fas fa-spinner fa-spin me-2"></span>Loading history...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
