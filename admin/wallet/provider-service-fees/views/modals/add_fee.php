<!-- Add Service Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1" aria-labelledby="addFeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addFeeModalLabel">
            <span class="fas fa-percent me-2"></span>Add Service Fee
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new service fee</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
        <div class="modal-body">
          <div id="addExistingFeeAlert" class="alert alert-info d-none mb-3">
            <span class="fas fa-info-circle me-2"></span>
            <span id="addExistingFeeMessage">An existing service fee was found for this branch, provider and type.</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="addBranchId" class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="addBranchId" name="branch_id" required>
                <option value="">Select Branch</option>
                <!-- Branches will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="addProviderId" class="form-label fw-bold">Main Provider <span class="text-danger">*</span></label>
              <select class="form-select" id="addProviderId" name="provider_id" required>
                <option value="">Select Main Provider</option>
                <!-- Main providers will be loaded dynamically -->
              </select>
              <small class="text-muted form-text">Service fees can only be assigned to main (top-level) providers.</small>
            </div>
            <div class="col-md-6">
              <label for="addFeeType" class="form-label fw-bold">Fee Type <span class="text-danger">*</span></label>
              <select class="form-select" id="addFeeType" name="fee_type" required>
                <option value="FIXED" selected>Fixed Amount</option>
                <option value="PERCENT">Percentage (%)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="addFeeAmount" id="addFeeAmountLabel" class="form-label fw-bold">Fee Amount</label>
              <input type="text" class="form-control" id="addFeeAmount" name="fee_value" placeholder="0.00" pattern="[0-9,.]*">
            </div>
            <div class="col-md-6">
              <label for="addStatus" class="form-label fw-bold d-block">Status</label>
              <div class="form-check form-switch ps-0">
                <input class="form-check-input" type="checkbox" id="addStatus" name="status" checked style="width: 3em; height: 1.5em; float: none; margin: 0;">
                <label class="form-check-label ms-2" for="addStatus" id="addStatusLabel">
                  <span class="text-success fw-bold">Active</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveFee()">
          <span class="fas fa-save me-2"></span>Save Fee
        </button>
      </div>
    </div>
  </div>
</div>
