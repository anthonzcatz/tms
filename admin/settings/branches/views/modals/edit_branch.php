<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editBranchModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Branch
          </h4>
          <p class="fs-10 mb-0 text-white">Update branch information and operating hours</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <!-- Wizard Steps Indicator -->
        <div class="wizard-steps d-flex justify-content-between px-5 pt-4 pb-4">
          <div class="step-item active" data-step="1" onclick="goToEditStep(1)">
            <div class="step-icon">
              <span class="fas fa-building"></span>
            </div>
            <div class="step-label">Basic Info</div>
          </div>
          <div class="step-item" data-step="2" onclick="goToEditStep(2)">
            <div class="step-icon">
              <span class="fas fa-clock"></span>
            </div>
            <div class="step-label">Operating Hours</div>
          </div>
          <div class="step-item" data-step="3" onclick="goToEditStep(3)">
            <div class="step-icon">
              <span class="fas fa-cog"></span>
            </div>
            <div class="step-label">Settings</div>
          </div>
        </div>
        
        <form id="editBranchForm" novalidate autocomplete="off">
          <input type="hidden" id="editBranchId" name="branch_id">
          
          <!-- Step 1: Basic Information -->
          <div class="wizard-step active" data-step="1">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-building me-2"></span>Basic Information
                </h6>
              </div>
              <div class="card-body">
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
                    <div class="form-check form-switch d-flex align-items-center">
                      <input class="form-check-input me-3" type="checkbox" id="editStatus" name="status" style="width: 3em; height: 1.5em;" onchange="updateEditStatusLabel(this.checked)">
                      <label class="form-check-label fw-bold" for="editStatus" id="editStatusLabel">
                        <span class="text-muted">Inactive</span>
                      </label>
                    </div>
                    <small class="text-muted form-text">Toggle to activate or deactivate this branch</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Step 2: Operating Hours -->
          <div class="wizard-step" data-step="2">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-clock me-2"></span>Operating Hours
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3 mb-4">
                  <div class="col-md-4">
                    <label class="form-label fw-bold">24/7 Operation</label>
                    <div class="form-check form-switch d-flex align-items-center">
                      <input class="form-check-input me-3" type="checkbox" id="editIs24Hours" name="is_24_hours" style="width: 3em; height: 1.5em;" onchange="toggleEditOperatingHours()">
                      <label class="form-check-label text-muted" for="editIs24Hours">
                        Open 24 hours
                      </label>
                    </div>
                  </div>
                </div>

                <div class="row g-4" id="editOperatingHoursContainer">
                  <!-- Monday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Monday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editMondayOpen" name="monday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editMondayClose" name="monday_close" value="18:00">
                          <div class="input-group-text bg-white d-flex align-items-center">
                            <input class="form-check-input me-1" type="checkbox" id="editMondayClosed" name="monday_closed">
                            <label class="form-check-label text-muted" for="editMondayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editMondayBreakStart" name="monday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editMondayBreakEnd" name="monday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Tuesday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Tuesday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editTuesdayOpen" name="tuesday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editTuesdayClose" name="tuesday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editTuesdayClosed" name="tuesday_closed">
                            <label class="form-check-label text-muted" for="editTuesdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editTuesdayBreakStart" name="tuesday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editTuesdayBreakEnd" name="tuesday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Wednesday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Wednesday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editWednesdayOpen" name="wednesday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editWednesdayClose" name="wednesday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editWednesdayClosed" name="wednesday_closed">
                            <label class="form-check-label text-muted" for="editWednesdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editWednesdayBreakStart" name="wednesday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editWednesdayBreakEnd" name="wednesday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Thursday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Thursday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editThursdayOpen" name="thursday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editThursdayClose" name="thursday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editThursdayClosed" name="thursday_closed">
                            <label class="form-check-label text-muted" for="editThursdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editThursdayBreakStart" name="thursday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editThursdayBreakEnd" name="thursday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Friday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Friday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editFridayOpen" name="friday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editFridayClose" name="friday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editFridayClosed" name="friday_closed">
                            <label class="form-check-label text-muted" for="editFridayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editFridayBreakStart" name="friday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editFridayBreakEnd" name="friday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Saturday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Saturday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editSaturdayOpen" name="saturday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editSaturdayClose" name="saturday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editSaturdayClosed" name="saturday_closed">
                            <label class="form-check-label text-muted" for="editSaturdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editSaturdayBreakStart" name="saturday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editSaturdayBreakEnd" name="saturday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Sunday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Sunday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="editSundayOpen" name="sunday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editSundayClose" name="sunday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="editSundayClosed" name="sunday_closed">
                            <label class="form-check-label text-muted" for="editSundayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="editSundayBreakStart" name="sunday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="editSundayBreakEnd" name="sunday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Copy hours button -->
                  <div class="col-12">
                    <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="copyEditMondayToAll()">
                      <span class="fas fa-copy me-2"></span>Copy Monday Hours to All Days
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Step 3: Additional Settings -->
          <div class="wizard-step" data-step="3">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-cog me-2"></span>Additional Settings
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label for="editMaxCapacity" class="form-label fw-bold">Max Capacity</label>
                    <input type="number" class="form-control" id="editMaxCapacity" name="max_capacity" min="1">
                    <small class="text-muted form-text">Maximum customer capacity</small>
                  </div>
                  <div class="col-md-4">
                    <label for="editManagerName" class="form-label fw-bold">Manager Name</label>
                    <input type="text" class="form-control" id="editManagerName" name="manager_name" placeholder="Branch manager name">
                  </div>
                  <div class="col-md-4">
                    <label for="editManagerContact" class="form-label fw-bold">Manager Contact</label>
                    <input type="text" class="form-control" id="editManagerContact" name="manager_contact" placeholder="Manager contact number">
                  </div>
                  <div class="col-12">
                    <label for="editNotes" class="form-label fw-bold">Notes</label>
                    <textarea class="form-control" id="editNotes" name="notes" rows="3" placeholder="Additional branch notes..."></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-outline-primary" id="editPrevStepBtn" onclick="editPrevStep()" style="display: none;">
          <span class="fas fa-arrow-left me-2"></span>Previous
        </button>
        <button type="button" class="btn btn-primary" id="editNextStepBtn" onclick="editNextStep()">
          Next<span class="fas fa-arrow-right ms-2"></span>
        </button>
        <button type="button" class="btn btn-primary" id="editSaveBranchBtn" onclick="updateBranch()" style="display: none;">
          <span class="fas fa-save me-2"></span>Update Branch
        </button>
      </div>
    </div>
  </div>
</div>
