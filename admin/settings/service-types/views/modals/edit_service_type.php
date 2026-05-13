<!-- Edit Service Type Modal -->
<div class="modal fade" id="editServiceTypeModal" tabindex="-1" aria-labelledby="editServiceTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editServiceTypeModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Service Type
          </h4>
          <p class="fs-10 mb-0 text-white">Update service type details</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editServiceTypeId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Service Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="editCode" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Service Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editName" maxlength="100">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="editDescription" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Default Amount (₱)</label>
            <input type="number" class="form-control" id="editDefaultAmount" min="0" step="0.01">
          </div>
          <div class="col-md-6">
            <div class="row g-2 pt-3">
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editAllowCustomAmount">
                  <label class="form-check-label" for="editAllowCustomAmount">
                    Allow Custom Amount
                  </label>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editRequiresWallet">
                  <label class="form-check-label" for="editRequiresWallet">
                    Requires Provider Wallet
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="editIsActive">
              <label class="form-check-label" for="editIsActive">Active</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitEditServiceType()">
          <span class="fas fa-save me-1"></span>Update Service Type
        </button>
      </div>
    </div>
  </div>
</div>
