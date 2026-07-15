<!-- Edit Service Fee Modal -->
<div class="modal fade" id="editFeeModal" tabindex="-1" aria-labelledby="editFeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editFeeModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Service Fee
          </h4>
          <p class="fs-10 mb-0 text-white">Update service fee information</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <div class="d-flex align-items-center">
              <span class="fas fa-info-circle me-2"></span>
              <div>
                <strong>Current Provider:</strong> <span id="editCurrentProviderName">-</span><br>
                <strong>Current Branch:</strong> <span id="editCurrentBranchName">-</span>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editProviderId" class="form-label fw-bold">Provider <span class="text-danger">*</span></label>
              <select class="form-select" id="editProviderId" name="provider_id" required>
                <option value="">Select Provider</option>
                <!-- Providers will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="editBranchId" class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="editBranchId" name="branch_id" required>
                <option value="">Select Branch</option>
                <!-- Branches will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="editFeeType" class="form-label fw-bold">Fee Type <span class="text-danger">*</span></label>
              <select class="form-select" id="editFeeType" name="fee_type" required>
                <option value="FIXED">Fixed Amount</option>
                <option value="PERCENT">Percentage (%)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="editFeeAmount" class="form-label fw-bold">Fee Amount</label>
              <input type="text" class="form-control" id="editFeeAmount" name="fee_value" placeholder="0.00" pattern="[0-9,.]*">
            </div>
            <div class="col-md-6">
              <label for="editStatus" class="form-label fw-bold">Status</label>
              <select class="form-select" id="editStatus" name="status" required>
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
        <button type="button" class="btn btn-primary" onclick="updateFee()">
          <span class="fas fa-save me-2"></span>Update Fee
        </button>
      </div>
    </div>
  </div>
</div>
