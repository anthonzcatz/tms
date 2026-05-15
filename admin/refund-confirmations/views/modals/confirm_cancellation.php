<!-- Confirm Cancellation Modal -->
<div class="modal fade" id="confirmCancellationModal" tabindex="-1" aria-labelledby="confirmCancellationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape bg-success">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="confirmCancellationModalLabel">
            <span class="fas fa-check-double me-2"></span>Review Cancellation Request
          </h4>
          <p class="fs-10 mb-0 text-white">Approve or reject this ticket cancellation request</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="card bg-soft-light mb-4">
          <div class="card-body">
            <h6 class="card-title mb-3"><span class="fas fa-info-circle me-2"></span>Cancellation Details</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <small class="text-muted d-block">Transaction Code</small>
                <strong id="modalTransactionCode">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Refund Amount</small>
                <strong id="modalRefundAmount" class="text-success">₱0.00</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Cancellation Type</small>
                <strong id="modalCancellationType">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Passenger</small>
                <strong id="modalPassenger">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Route</small>
                <strong id="modalRoute">-</strong>
              </div>
              <div class="col-md-6">
                <small class="text-muted d-block">Requested By</small>
                <strong id="modalRequestedBy">-</strong>
              </div>
              <div class="col-12">
                <small class="text-muted d-block">Reason</small>
                <strong id="modalReason">-</strong>
              </div>
              <div class="col-12">
                <small class="text-muted d-block">Requested At</small>
                <strong id="modalRequestedAt">-</strong>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-semibold">Action</label>
          <select class="form-select" id="modalAction">
            <option value="approve">Approve - Process refund</option>
            <option value="reject">Reject - Deny cancellation</option>
          </select>
        </div>
        
        <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
          <label class="form-label fw-semibold" for="modalRejectionReason">Rejection Reason <span class="text-danger">*</span></label>
          <textarea class="form-control" id="modalRejectionReason" rows="3" placeholder="Enter reason for rejection"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-success" onclick="submitCancellationDecision()">
          <span class="fas fa-check me-1"></span>Submit Decision
        </button>
      </div>
    </div>
  </div>
</div>
