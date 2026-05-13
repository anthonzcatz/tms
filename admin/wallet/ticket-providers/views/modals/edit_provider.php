<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1" aria-labelledby="editProviderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editProviderModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Provider
          </h4>
          <p class="fs-10 mb-0 text-white">Update provider information</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="editProviderForm">
        <div class="modal-body">
          <input type="hidden" id="editProviderId" name="provider_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editProviderCode" class="form-label fw-bold">Provider Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="editProviderCode" name="provider_code" required placeholder="e.g., PAL, CEBPAC" maxlength="50">
              <small class="text-muted form-text">Unique code for the provider (max 50 characters)</small>
            </div>
            <div class="col-md-6">
              <label for="editProviderName" class="form-label fw-bold">Provider Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="editProviderName" name="provider_name" required placeholder="e.g., Philippine Airlines" maxlength="150">
            </div>
            <div class="col-md-6">
              <label for="editProviderType" class="form-label fw-bold">Provider Type <span class="text-danger">*</span></label>
              <select class="form-select" id="editProviderType" name="provider_type" required>
                <option value="">Select Type</option>
                <option value="airline">Airline</option>
                <option value="shipping">Shipping</option>
                <option value="bus">Bus</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="editStatus" class="form-label fw-bold">Status</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="editStatus" name="status" style="width: 3em; height: 1.5em;">
                <label class="form-check-label" for="editStatus" id="editStatusLabel">
                  <span class="text-muted">Inactive</span>
                </label>
              </div>
              <small class="text-muted form-text">Toggle to activate or deactivate this provider</small>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="updateProvider()">
          <span class="fas fa-save me-2"></span>Update Provider
        </button>
      </div>
    </div>
  </div>
</div>
