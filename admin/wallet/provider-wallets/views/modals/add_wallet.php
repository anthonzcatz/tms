<!-- Add Wallet Modal -->
<div class="modal fade" id="addWalletModal" tabindex="-1" aria-labelledby="addWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addWalletModalLabel">
            <span class="fas fa-wallet me-2"></span>Add Wallet
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new provider wallet</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="addWalletForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="addProviderId" class="form-label fw-bold">Provider <span class="text-danger">*</span></label>
              <select class="form-select" id="addProviderId" name="provider_id" required>
                <option value="">Select Provider</option>
                <!-- Providers will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="addBranchId" class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="addBranchId" name="branch_id" required>
                <option value="">Select Branch</option>
                <!-- Branches will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="addInitialBalance" class="form-label fw-bold">Initial Balance</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" class="form-control" id="addInitialBalance" name="initial_balance" placeholder="0.00" step="0.01" min="0">
              </div>
            </div>
            <div class="col-md-6">
              <label for="addStatus" class="form-label fw-bold">Status</label>
              <select class="form-select" id="addStatus" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveWallet()">
          <span class="fas fa-save me-2"></span>Save Wallet
        </button>
      </div>
    </div>
  </div>
</div>
