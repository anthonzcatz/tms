<!-- Cancel Ticket Modal -->
<div class="modal fade" id="cancelTicketModal" tabindex="-1" aria-labelledby="cancelTicketModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape bg-danger">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="cancelTicketModalLabel">
            <span class="fas fa-times-circle me-2"></span>Cancel Ticket
          </h4>
          <p class="fs-10 mb-0 text-white">Process ticket cancellation with wallet refund</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <!-- Cancellation Settings Info -->
        <div class="alert alert-info d-flex align-items-center mb-4">
          <span class="fas fa-info-circle me-3 fs-4"></span>
          <div>
            <strong>Cancellation Policy:</strong>
            <ul class="mb-0 mt-2 ps-3 fs-10">
              <li>Refund: Amount will be refunded from cashier cash to passenger</li>
              <li id="cancelPolicyProcessing">Processing: Loading...</li>
              <li id="cancelPolicyApproval">Approval: Loading...</li>
            </ul>
          </div>
        </div>

        <!-- Pending Cancellation Alert -->
        <div id="pendingCancellationAlert" class="alert alert-warning d-flex align-items-center mb-4 d-none">
          <span class="fas fa-clock me-3 fs-4"></span>
          <div>
            <strong>Pending Cancellation:</strong> <span id="pendingCancellationText">This ticket has a pending cancellation request awaiting approval.</span>
            <div class="mt-1 small text-muted">Requested by: <span id="pendingRequestedBy">-</span> on <span id="pendingRequestedAt">-</span></div>
          </div>
        </div>

        <div class="alert alert-warning d-flex align-items-center mb-4" id="cancelImportantAlert">
          <span class="fas fa-exclamation-triangle me-3 fs-4"></span>
          <div>
            <strong>Important:</strong> This action cannot be undone. Please review the details before confirming.
          </div>
        </div>

        <div class="row">
          <!-- Left Column: Ticket Details -->
          <div class="col-md-6">
            <div id="cancelTicketDetails" class="card bg-soft-light mb-3" style="display: none;">
              <div class="card-body p-3">
                <h6 class="card-title mb-3 fw-bold"><span class="fas fa-info-circle me-2"></span>Ticket Details</h6>
                <div class="row g-2">
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Passenger</small>
                    <span id="cancelPassengerName" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Travel Date</small>
                    <span id="cancelTravelDate" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Route</small>
                    <span id="cancelRoute" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Provider</small>
                    <span id="cancelProvider" class="fw-semibold">-</span>
                  </div>
                  <div class="col-4">
                    <small class="text-muted d-block mb-1 fs-10">Base Amount</small>
                    <span id="cancelBaseAmount" class="fw-semibold">₱0.00</span>
                  </div>
                  <div class="col-4">
                    <small class="text-muted d-block mb-1 fs-10">Service Fee</small>
                    <span id="cancelServiceFee" class="fw-semibold">₱0.00</span>
                  </div>
                  <div class="col-4">
                    <small class="text-muted d-block mb-1 fs-10">Total Amount</small>
                    <span id="cancelTotalAmount" class="fw-bold text-primary">₱0.00</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Status</small>
                    <span id="cancelStatus" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Transaction Date</small>
                    <span id="cancelTxnDate" class="fw-semibold">-</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Input Fields -->
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelTicketCode">Transaction Code</label>
              <input type="text" class="form-control" id="cancelTicketCode" placeholder="Enter transaction code (e.g., TKT-20260515-123456-789-01)">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelRefundAmount">Refund Amount (₱)</label>
              <input type="number" class="form-control" id="cancelRefundAmount" placeholder="0.00" min="0" step="0.01">
              <small class="text-muted">Only the Base Amount will be refunded to the wallet balance (excluding Service Fee: <span id="cancelServiceFeeDisplay" style="display: none;">₱0.00</span>).</small>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelReason">Reason for Cancellation</label>
              <textarea class="form-control" id="cancelReason" rows="4" placeholder="Enter reason for cancellation (optional)"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmCancelBtn" onclick="confirmCancelTicket()">
          <span class="fas fa-check me-1"></span>Confirm Cancellation
        </button>
      </div>
    </div>
  </div>
</div>
