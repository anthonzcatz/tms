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
              <label for="addFeeType" class="form-label fw-bold">Fee Type <span class="text-danger">*</span></label>
              <select class="form-select" id="addFeeType" name="fee_type" required>
                <option value="FIXED" selected>Fixed Amount</option>
                <option value="PERCENT">Percentage (%)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="addFeeAmount" class="form-label fw-bold">Fee Amount</label>
              <input type="text" class="form-control" id="addFeeAmount" name="fee_value" placeholder="0.00" pattern="[0-9,.]*">
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
