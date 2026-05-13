<!-- Edit Payment Method Modal -->
<div class="modal fade" id="editMethodModal" tabindex="-1" aria-labelledby="editMethodModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editMethodModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Payment Method
          </h4>
          <p class="fs-10 mb-0 text-white">Update payment method details</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editMethodId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="editMethodCode" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editMethodName" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Type <span class="text-danger">*</span></label>
            <select class="form-select" id="editMethodType">
              <option value="">Select Type</option>
              <option value="CASH">Cash</option>
              <option value="BANK_TRANSFER">Bank Transfer</option>
              <option value="E_WALLET">E-Wallet</option>
              <option value="CHARGE">Charge (Utang)</option>
              <option value="CARD">Card</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Sort Order</label>
            <input type="number" class="form-control" id="editSortOrder" value="0" min="0">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="editDescription" rows="2"></textarea>
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Icon (FontAwesome class)</label>
            <input type="text" class="form-control" id="editIcon" placeholder="e.g. fa-mobile-alt">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Settings</label>
            <div class="row g-2">
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editRequiresConfirmation">
                  <label class="form-check-label" for="editRequiresConfirmation">
                    Requires Confirmation
                    <span class="d-block text-muted" style="font-size:0.75rem">Bank/e-wallet: manager confirms payment</span>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editRequiresCustomer">
                  <label class="form-check-label" for="editRequiresCustomer">
                    Requires Customer
                    <span class="d-block text-muted" style="font-size:0.75rem">Charge: customer must be selected</span>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editRequiresReference">
                  <label class="form-check-label" for="editRequiresReference">
                    Requires Reference #
                    <span class="d-block text-muted" style="font-size:0.75rem">Ref number needed</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <div class="form-check form-switch mt-1">
              <input class="form-check-input" type="checkbox" id="editIsActive" checked>
              <label class="form-check-label" for="editIsActive">Active</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitEditMethod()">
          <span class="fas fa-save me-1"></span>Update Method
        </button>
      </div>
    </div>
  </div>
</div>
