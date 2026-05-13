<!-- Add Service Type Modal -->
<div class="modal fade" id="addServiceTypeModal" tabindex="-1" aria-labelledby="addServiceTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addServiceTypeModalLabel">
            <span class="fas fa-concierge-bell me-2"></span>Add Service Type
          </h4>
          <p class="fs-10 mb-0 text-white">Add a new service type (e.g. Print Fee, Photocopy)</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Service Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="addCode" placeholder="e.g. PRINT_FEE" maxlength="50">
            <div class="form-text">Unique identifier (no spaces, uppercase)</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Service Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addName" placeholder="e.g. Print Fee" maxlength="100">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="addDescription" rows="2" placeholder="Optional description of this service"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Default Amount (₱)</label>
            <input type="number" class="form-control" id="addDefaultAmount" value="0.00" min="0" step="0.01">
            <div class="form-text">Default price per transaction (can be overridden)</div>
          </div>
          <div class="col-md-6">
            <div class="row g-2 pt-3">
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addAllowCustomAmount" checked>
                  <label class="form-check-label" for="addAllowCustomAmount">
                    Allow Custom Amount
                    <span class="d-block text-muted" style="font-size:0.75rem;">Cashier can override the default price</span>
                  </label>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addRequiresWallet">
                  <label class="form-check-label" for="addRequiresWallet">
                    Requires Provider Wallet
                    <span class="d-block text-muted" style="font-size:0.75rem;">Ticket sales need a wallet; print fees usually don't</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAddServiceType()">
          <span class="fas fa-save me-1"></span>Save Service Type
        </button>
      </div>
    </div>
  </div>
</div>
