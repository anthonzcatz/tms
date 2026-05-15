<!-- Cancel Ticket Modal -->
<div class="modal fade" id="cancelTicketModal" tabindex="-1" aria-labelledby="cancelTicketModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
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
      <div class="modal-body">
        <div class="alert alert-warning d-flex align-items-center mb-4">
          <span class="fas fa-exclamation-triangle me-3 fs-4"></span>
          <div>
            <strong>Important:</strong> Cancellation will refund the specified amount to the wallet balance. This action cannot be undone.
          </div>
        </div>
        
        <!-- Ticket Details Display -->
        <div id="cancelTicketDetails" class="card bg-soft-light mb-4" style="display: none;">
          <div class="card-body">
            <h6 class="card-title mb-3"><span class="fas fa-info-circle me-2"></span>Ticket Details</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <small class="text-muted d-block">Passenger</small>
                <strong id="cancelPassengerName">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Travel Date</small>
                <strong id="cancelTravelDate">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Route</small>
                <strong id="cancelRoute">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Provider</small>
                <strong id="cancelProvider">-</strong>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Base Amount</small>
                <strong id="cancelBaseAmount">₱0.00</strong>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Service Fee</small>
                <strong id="cancelServiceFee">₱0.00</strong>
              </div>
              <div class="col-md-4">
                <small class="text-muted d-block">Total Amount</small>
                <strong id="cancelTotalAmount">₱0.00</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Status</small>
                <strong id="cancelStatus">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Transaction Date</small>
                <strong id="cancelTxnDate">-</strong>
              </div>
            </div>
          </div>
        </div>
        
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
          <textarea class="form-control" id="cancelReason" rows="3" placeholder="Enter reason for cancellation (optional)"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-danger" onclick="confirmCancelTicket()">
          <span class="fas fa-check me-1"></span>Confirm Cancellation
        </button>
      </div>
    </div>
  </div>
</div>
