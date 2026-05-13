<!-- Edit Wallet Modal -->
<div class="modal fade" id="editWalletModal" tabindex="-1" aria-labelledby="editWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editWalletModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Wallet
          </h4>
          <p class="fs-10 mb-0 text-white">Update wallet information</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="editWalletForm">
        <div class="modal-body">
          <input type="hidden" id="editWalletId" name="wallet_id">
          <div class="alert alert-info mb-3">
            <div class="d-flex align-items-center">
              <span class="fas fa-info-circle me-2"></span>
              <div>
                <strong>Provider:</strong> <span id="editProviderName">-</span><br>
                <strong>Branch:</strong> <span id="editBranchName">-</span><br>
                <strong>Current Balance:</strong> ₱<span id="editCurrentBalance">0.00</span>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-12">
              <label for="editStatus" class="form-label fw-bold">Status</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="editStatus" name="status" style="width: 3em; height: 1.5em;">
                <label class="form-check-label" for="editStatus" id="editStatusLabel">
                  <span class="text-muted">Inactive</span>
                </label>
              </div>
              <small class="text-muted form-text">Toggle to activate or deactivate this wallet</small>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="updateWallet()">
          <span class="fas fa-save me-2"></span>Update Wallet
        </button>
      </div>
    </div>
  </div>
</div>
