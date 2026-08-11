<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceAdjustmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <span class="fas fa-balance-scale me-2"></span>Balance Adjustment
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="adjustBankAccountId">
        <input type="hidden" id="adjustCurrentBalance">
        
        <div class="card border-0 bg-light mb-3">
          <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-bold">Current Balance:</span>
              <span class="fw-bold text-success" id="displayCurrentBalance">₱0.00</span>
            </div>
          </div>
        </div>
        
        <form id="balanceAdjustmentForm">
          <div class="mb-3">
            <label class="form-label fw-semibold">Adjustment Type</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="adjustmentDirection" id="directionIN" value="IN" required>
              <label class="btn btn-outline-success" for="directionIN">
                <span class="fas fa-arrow-down me-1"></span>IN (Add)
              </label>
              
              <input type="radio" class="btn-check" name="adjustmentDirection" id="directionOUT" value="OUT">
              <label class="btn btn-outline-danger" for="directionOUT">
                <span class="fas fa-arrow-up me-1"></span>OUT (Deduct)
              </label>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount</label>
            <div class="input-group">
              <span class="input-group-text">₱</span>
              <input type="number" class="form-control" id="adjustAmount" min="0" step="0.01" required>
            </div>
            <div class="form-text">Enter the amount to add or deduct</div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">New Balance</label>
            <div class="input-group">
              <span class="input-group-text">₱</span>
              <input type="text" class="form-control bg-light" id="adjustNewBalance" readonly>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Remarks (Optional)</label>
            <textarea class="form-control" id="adjustRemarks" rows="3" placeholder="Reason for adjustment..."></textarea>
          </div>
          
          <div class="alert alert-warning d-none" id="adjustmentWarning">
            <span class="fas fa-exclamation-triangle me-2"></span>
            <span id="adjustmentWarningText"></span>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-sm btn-primary" onclick="submitBalanceAdjustment()">
          <span class="fas fa-check me-1"></span>Confirm Adjustment
        </button>
      </div>
    </div>
  </div>
</div>
