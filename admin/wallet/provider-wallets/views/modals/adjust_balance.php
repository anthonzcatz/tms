<!-- Adjust Balance Modal -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="adjustBalanceModalLabel">
            <span class="fas fa-exchange-alt me-2"></span>Adjust Balance
          </h4>
          <p class="fs-10 mb-0 text-white">Adjust wallet balance</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="adjustBalanceForm">
        <div class="modal-body">
          <input type="hidden" id="adjustWalletId" name="wallet_id">
          <div class="alert alert-info mb-3">
            <div class="d-flex align-items-center">
              <span class="fas fa-info-circle me-2"></span>
              <div>
                <strong>Provider:</strong> <span id="adjustProviderName">-</span><br>
                <strong>Branch:</strong> <span id="adjustBranchName">-</span><br>
                <strong>Current Balance:</strong> ₱<span id="adjustCurrentBalance">0.00</span>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="adjustDirection" class="form-label fw-bold">Direction <span class="text-danger">*</span></label>
              <select class="form-select" id="adjustDirection" name="direction" required>
                <option value="">Select Direction</option>
                <option value="IN">In (Add)</option>
                <option value="OUT">Out (Deduct)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="adjustAmount" class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" class="form-control" id="adjustAmount" name="amount" required placeholder="0.00" step="0.01" min="0">
              </div>
            </div>
            <div class="col-md-12">
              <label for="adjustRemarks" class="form-label fw-bold">Remarks</label>
              <textarea class="form-control" id="adjustRemarks" name="remarks" rows="3" placeholder="Enter adjustment remarks"></textarea>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveAdjustment()">
          <span class="fas fa-save me-2"></span>Save Adjustment
        </button>
      </div>
    </div>
  </div>
</div>
