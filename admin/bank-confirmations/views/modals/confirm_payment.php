<!-- Action Confirmation Modal (for Confirm/Reject confirmation) -->
<div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-labelledby="actionConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="actionConfirmTitle">Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="actionConfirmMessage" class="mb-0">Are you sure?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="actionConfirmBtn" onclick="executePendingAction()" data-bs-dismiss="modal">
          Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm / Reject Payment Modal -->
<div class="modal fade" id="confirmPaymentModal" tabindex="-1" aria-labelledby="confirmPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="confirmPaymentModalLabel">
            <span class="fas fa-check-double me-2"></span>Review Bank Transfer
          </h4>
          <p class="fs-10 mb-0 text-white" id="confirmPaymentModalSubtitle">Confirm or reject this bank transfer payment</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="confirmPaymentId">
        <input type="hidden" id="confirmItemType">

        <!-- Payment Details (read-only summary) -->
        <div class="card bg-light border-0 mb-3">
          <div class="card-body py-3">
            <div class="row g-2">
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Payment Method</div>
                <div id="cpMethodName" class="fw-semibold">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Amount</div>
                <div id="cpAmount" class="fw-bold fs-5 text-success">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Reference #</div>
                <div id="cpRefNum" class="ref-badge">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Bank Account</div>
                <div id="cpBankAccount">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Cashier</div>
                <div id="cpCashier">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Date</div>
                <div id="cpDate">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Service</div>
                <div id="cpService">—</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small fw-semibold">Branch</div>
                <div id="cpBranch">—</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Information Box (Confirm only - Reject warning shown in confirmation popup) -->
        <div class="row g-3 mb-3" id="actionInfoBox">
          <div class="col-12">
            <div class="alert alert-success py-2 mb-0" id="confirmInfoBox">
              <span class="fas fa-info-circle me-2"></span>
              <strong>On Confirm:</strong> Bank account balance will be updated. Payment will be finalized.
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Confirmation Notes <span class="text-muted small">(optional)</span></label>
            <textarea class="form-control" id="cpNotes" rows="2" placeholder="e.g. Verified in BPI online banking, Rejected — amount mismatch"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger me-auto" onclick="submitConfirmPayment('REJECTED')">
          <span class="fas fa-times-circle me-1"></span>Reject
        </button>
        <button type="button" class="btn btn-success" onclick="submitConfirmPayment('CONFIRMED')">
          <span class="fas fa-check-circle me-1"></span>Confirm Transfer
        </button>
      </div>
    </div>
  </div>
</div>
