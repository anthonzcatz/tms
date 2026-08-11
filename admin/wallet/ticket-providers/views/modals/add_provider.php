<!-- Add Provider Modal -->
<div class="modal fade" id="addProviderModal" tabindex="-1" aria-labelledby="addProviderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addProviderModalLabel">
            <span class="fas fa-plus me-2"></span>Add Provider
          </h4>
          <p class="fs-10 mb-0 text-white">Add a new ticket provider</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="addProviderForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="addProviderCode" class="form-label fw-bold">Provider Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="addProviderCode" name="provider_code" required placeholder="e.g., PAL, CEBPAC" maxlength="50">
              <small class="text-muted form-text">Unique code for the provider (max 50 characters)</small>
            </div>
            <div class="col-md-6">
              <label for="addProviderName" class="form-label fw-bold">Provider Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="addProviderName" name="provider_name" required placeholder="e.g., Philippine Airlines" maxlength="150">
            </div>
            <div class="col-md-6">
              <label for="addProviderType" class="form-label fw-bold">Provider Type <span class="text-danger">*</span></label>
              <select class="form-select" id="addProviderType" name="provider_type" required>
                <option value="">Select Type</option>
                <option value="airline">Airline</option>
                <option value="shipping">Shipping</option>
                <option value="bus">Bus</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="addStatus" class="form-label fw-bold">Status</label>
              <div class="form-check form-switch d-flex align-items-center ps-0">
                <input class="form-check-input" type="checkbox" id="addStatus" name="status" checked style="width: 3em; height: 1.5em; float: none; margin: 0;">
                <label class="form-check-label ms-2" for="addStatus" id="addStatusLabel">
                  <span class="text-success fw-bold">Active</span>
                </label>
              </div>
              <small class="text-muted form-text">Toggle to activate or deactivate this provider</small>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label for="addParentProvider" class="form-label fw-bold">Main Provider</label>
              <select class="form-select" id="addParentProvider" name="parent_provider_id">
                <option value="">Standalone (no main provider)</option>
                <?php foreach ($providers as $provider): ?>
                  <?php if (!empty($provider['parent_provider_id'])) continue; ?>
                  <option value="<?php echo $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name']); ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted form-text">Select the main provider if this is a sub-provider.</small>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveProvider()">
          <span class="fas fa-save me-2"></span>Save Provider
        </button>
      </div>
    </div>
  </div>
</div>
