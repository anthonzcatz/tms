<!-- Collect Payment Modal -->
<div class="modal fade" id="collectPaymentModal" tabindex="-1" aria-labelledby="collectPaymentModalLabel" aria-hidden="true">
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
            <div class="mt-1">
              Outstanding Balance: <strong class="text-danger fs-5" id="collectBalance">₱0.00</strong>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Amount to Collect (₱) <span class="text-danger">*</span></label>
            <input type="number" class="form-control fs-5 fw-bold" id="collectAmount" min="0.01" step="0.01" placeholder="0.00">
            <div class="form-text">Can be partial or full payment.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select" id="collectMethodId" onchange="toggleCollectRef()">
              <option value="">Select method</option>
              <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?php echo $pm['method_id']; ?>"
                        data-type="<?php echo htmlspecialchars($pm['method_type']); ?>"
                        data-req-ref="<?php echo $pm['requires_reference'] ? '1' : '0'; ?>">
                  <?php echo htmlspecialchars($pm['method_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
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
        <button type="button" class="btn btn-success" onclick="submitCollectPayment()">
          <span class="fas fa-check-circle me-1"></span>Record Payment
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
