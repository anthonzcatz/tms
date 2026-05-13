<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editBranchModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Branch
          </h4>
          <p class="fs-10 mb-0 text-white">Update branch information</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="editBranchForm">
        <div class="modal-body">
          <input type="hidden" id="editBranchId" name="branch_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editBranchCode" class="form-label fw-bold">Branch Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="editBranchCode" name="branch_code" required placeholder="e.g., MAIN_BRANCH, CEBU_BRANCH" maxlength="50">
              <small class="text-muted form-text">Unique code for the branch (max 50 characters)</small>
            </div>
            <div class="col-md-6">
              <label for="editBranchName" class="form-label fw-bold">Branch Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="editBranchName" name="branch_name" required placeholder="e.g., Main Branch" maxlength="150">
            </div>
            <div class="col-md-4">
              <label for="editRegionCode" class="form-label fw-bold">Region</label>
              <select class="form-select" id="editRegionCode" name="region_code">
                <option value="">Select Region</option>
                <!-- Regions will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-4">
              <label for="editProvinceCode" class="form-label fw-bold">Province</label>
              <select class="form-select" id="editProvinceCode" name="province_code" disabled>
                <option value="">Select Province</option>
                <!-- Provinces will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-4">
              <label for="editCityCode" class="form-label fw-bold">City/Municipality</label>
              <select class="form-select" id="editCityCode" name="city_municipality_code" disabled>
                <option value="">Select City</option>
                <!-- Cities will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="editBarangayCode" class="form-label fw-bold">Barangay</label>
              <select class="form-select" id="editBarangayCode" name="barangay_code" disabled>
                <option value="">Select Barangay</option>
                <!-- Barangays will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="editZipCode" class="form-label fw-bold">Zip Code</label>
              <input type="text" class="form-control" id="editZipCode" name="zip_code" placeholder="e.g., 8000" maxlength="10">
            </div>
            <div class="col-md-12">
              <label for="editStreetAddress" class="form-label fw-bold">Street Address</label>
              <input type="text" class="form-control" id="editStreetAddress" name="street_address" placeholder="e.g., 123 Main Street">
            </div>
            <div class="col-md-6">
              <label for="editLandmark" class="form-label fw-bold">Landmark</label>
              <input type="text" class="form-control" id="editLandmark" name="landmark" placeholder="e.g., Near City Hall">
            </div>
            <div class="col-md-6">
              <label for="editContactNumber" class="form-label fw-bold">Contact Number</label>
              <input type="text" class="form-control" id="editContactNumber" name="contact_number" placeholder="e.g., 09171234567" maxlength="50">
            </div>
            <div class="col-md-6">
              <label for="editEmail" class="form-label fw-bold">Email</label>
              <input type="email" class="form-control" id="editEmail" name="email" placeholder="e.g., branch@example.com" maxlength="100">
            </div>
            <div class="col-md-6">
              <label for="editStatus" class="form-label fw-bold">Status</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="editStatus" name="status" style="width: 3em; height: 1.5em;">
                <label class="form-check-label" for="editStatus" id="editStatusLabel">
                  <span class="text-muted">Inactive</span>
                </label>
              </div>
              <small class="text-muted form-text">Toggle to activate or deactivate this branch</small>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="updateBranch()">
          <span class="fas fa-save me-2"></span>Update Branch
        </button>
      </div>
    </div>
  </div>
</div>
