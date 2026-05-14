<!-- Add Payment Method Modal -->
<div class="modal fade" id="addMethodModal" tabindex="-1" aria-labelledby="addMethodModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addMethodModalLabel">
            <span class="fas fa-credit-card me-2"></span>Add Payment Method
          </h4>
          <p class="fs-10 mb-0 text-white">Add a new payment method</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="addMethodCode" placeholder="e.g. GCASH" maxlength="50">
            <div class="form-text">Unique identifier (no spaces, uppercase)</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addMethodName" placeholder="e.g. GCash" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Method Type <span class="text-danger">*</span></label>
            <select class="form-select" id="addMethodType">
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
            <input type="number" class="form-control" id="addSortOrder" value="0" min="0">
            <div class="form-text">Lower number = shown first</div>
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="addDescription" rows="2" placeholder="Optional description"></textarea>
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Icon (FontAwesome class)</label>
            <input type="text" class="form-control" id="addIcon" placeholder="e.g. fa-mobile-alt">
            <div class="form-text">FontAwesome 5 icon class name only (no 'fas' prefix)</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Settings</label>
            <div class="row g-2">
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addRequiresConfirmation">
                  <label class="form-check-label" for="addRequiresConfirmation">
                    Requires Confirmation
                    <span class="d-block text-muted" style="font-size:0.75rem">Bank/e-wallet: manager confirms payment received</span>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addRequiresCustomer">
                  <label class="form-check-label" for="addRequiresCustomer">
                    Requires Customer
                    <span class="d-block text-muted" style="font-size:0.75rem">Charge: customer must be selected</span>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addRequiresReference">
                  <label class="form-check-label" for="addRequiresReference">
                    Requires Reference #
                    <span class="d-block text-muted" style="font-size:0.75rem">Ref number needed (bank/gcash)</span>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addIncludeInExpectedCash">
                  <label class="form-check-label" for="addIncludeInExpectedCash">
                    Include in Expected Cash
                    <span class="d-block text-muted" style="font-size:0.75rem">Add to closing cash calculation</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAddMethod()">
          <span class="fas fa-save me-1"></span>Save Method
        </button>
      </div>
    </div>
  </div>
</div>
