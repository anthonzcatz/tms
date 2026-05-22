<!-- Reprint Receipt Confirmation Modal -->
<div class="modal fade" id="reprintReceiptModal" tabindex="-1" aria-labelledby="reprintReceiptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape bg-info">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="reprintReceiptModalLabel">
            <span class="fas fa-print me-2"></span>Reprint Receipt
          </h4>
          <p class="fs-10 mb-0 text-white">Print a copy of the transaction receipt</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info d-flex align-items-center mb-4">
          <span class="fas fa-info-circle me-3 fs-4"></span>
          <div>
            <strong>Transaction Details:</strong>
            <div class="small mt-1">
              <span id="reprintTxnCode">-</span>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="reprintReason">Reason for Reprint <span class="text-danger">*</span></label>
          <select class="form-select" id="reprintReason" required>
            <option value="">Select a reason...</option>
            <option value="Customer request">Customer request</option>
            <option value="Printer error">Printer error</option>
            <option value="Paper jam">Paper jam</option>
            <option value="Faded print">Faded print</option>
            <option value="Lost receipt">Lost receipt</option>
            <option value="Other">Other (specify below)</option>
          </select>
        </div>

        <div class="mb-3" id="reprintReasonOtherContainer" style="display:none;">
          <label class="form-label fw-semibold" for="reprintReasonOther">Please specify</label>
          <input type="text" class="form-control" id="reprintReasonOther" placeholder="Enter reason...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-info" id="confirmReprintBtn" onclick="confirmReprintReceipt()" disabled>
          <span class="fas fa-print me-1"></span>Print Receipt
        </button>
      </div>
    </div>
  </div>
</div>
