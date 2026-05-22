<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addBranchModalLabel">
            <span class="fas fa-code-branch me-2"></span>Add New Branch
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new branch with detailed information and operating hours</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <!-- Wizard Steps Indicator -->
        <div class="wizard-steps d-flex justify-content-between px-5 pt-4 pb-4">
          <div class="step-item active" data-step="1" onclick="goToAddStep(1)">
            <div class="step-icon">
              <span class="fas fa-building"></span>
            </div>
            <div class="step-label">Basic Info</div>
          </div>
          <div class="step-item" data-step="2" onclick="goToAddStep(2)">
            <div class="step-icon">
              <span class="fas fa-clock"></span>
            </div>
            <div class="step-label">Operating Hours</div>
          </div>
          <div class="step-item" data-step="3" onclick="goToAddStep(3)">
            <div class="step-icon">
              <span class="fas fa-cog"></span>
            </div>
            <div class="step-label">Settings</div>
          </div>
        </div>
        
        <form id="addBranchForm" novalidate autocomplete="off">
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
                    <label for="addBranchCode" class="form-label fw-bold">Branch Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addBranchCode" name="branch_code" required placeholder="e.g., MAIN_BRANCH, CEBU_BRANCH" maxlength="50">
                    <small class="text-muted form-text">Unique code for the branch (max 50 characters)</small>
                  </div>
                  <div class="col-md-6">
                    <label for="addBranchName" class="form-label fw-bold">Branch Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addBranchName" name="branch_name" required placeholder="e.g., Main Branch" maxlength="150">
                  </div>
                  <div class="col-md-4">
                    <label for="addRegionCode" class="form-label fw-bold">Region</label>
                    <select class="form-select" id="addRegionCode" name="region_code">
                      <option value="">Select Region</option>
                      <!-- Regions will be loaded dynamically -->
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="addProvinceCode" class="form-label fw-bold">Province</label>
                    <select class="form-select" id="addProvinceCode" name="province_code" disabled>
                      <option value="">Select Province</option>
                      <!-- Provinces will be loaded dynamically -->
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="addCityCode" class="form-label fw-bold">City/Municipality</label>
                    <select class="form-select" id="addCityCode" name="city_municipality_code" disabled>
                      <option value="">Select City</option>
                      <!-- Cities will be loaded dynamically -->
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="addBarangayCode" class="form-label fw-bold">Barangay</label>
                    <select class="form-select" id="addBarangayCode" name="barangay_code" disabled>
                      <option value="">Select Barangay</option>
                      <!-- Barangays will be loaded dynamically -->
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="addZipCode" class="form-label fw-bold">Zip Code</label>
                    <input type="text" class="form-control" id="addZipCode" name="zip_code" placeholder="e.g., 8000" maxlength="10">
                  </div>
                  <div class="col-md-12">
                    <label for="addStreetAddress" class="form-label fw-bold">Street Address</label>
                    <input type="text" class="form-control" id="addStreetAddress" name="street_address" placeholder="e.g., 123 Main Street">
                  </div>
                  <div class="col-md-6">
                    <label for="addLandmark" class="form-label fw-bold">Landmark</label>
                    <input type="text" class="form-control" id="addLandmark" name="landmark" placeholder="e.g., Near City Hall">
                  </div>
                  <div class="col-md-6">
                    <label for="addContactNumber" class="form-label fw-bold">Contact Number</label>
                    <input type="text" class="form-control" id="addContactNumber" name="contact_number" placeholder="e.g., 09171234567" maxlength="50">
                  </div>
                  <div class="col-md-6">
                    <label for="addEmail" class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control" id="addEmail" name="email" placeholder="e.g., branch@example.com" maxlength="100">
                  </div>
                  <div class="col-md-6">
                    <label for="addStatus" class="form-label fw-bold">Status</label>
                    <div class="form-check form-switch d-flex align-items-center">
                      <input class="form-check-input me-3" type="checkbox" id="addStatus" name="status" checked style="width: 3em; height: 1.5em;" onchange="updateAddStatusLabel(this.checked)">
                      <label class="form-check-label fw-bold" for="addStatus" id="addStatusLabel">
                        <span class="text-success">Active</span>
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
                      <input class="form-check-input me-3" type="checkbox" id="addIs24Hours" name="is_24_hours" style="width: 3em; height: 1.5em;" onchange="toggleAddOperatingHours()">
                      <label class="form-check-label text-muted" for="addIs24Hours">
                        Open 24 hours
                      </label>
                    </div>
                  </div>
                </div>

                <div class="row g-4" id="addOperatingHoursContainer">
                  <!-- Monday -->
                  <div class="col-12 col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Monday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addMondayOpen" name="monday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addMondayClose" name="monday_close" value="18:00">
                          <div class="input-group-text bg-white d-flex align-items-center">
                            <input class="form-check-input me-1" type="checkbox" id="addMondayClosed" name="monday_closed">
                            <label class="form-check-label text-muted" for="addMondayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addMondayBreakStart" name="monday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addMondayBreakEnd" name="monday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Tuesday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Tuesday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addTuesdayOpen" name="tuesday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addTuesdayClose" name="tuesday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addTuesdayClosed" name="tuesday_closed">
                            <label class="form-check-label text-muted" for="addTuesdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addTuesdayBreakStart" name="tuesday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addTuesdayBreakEnd" name="tuesday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Wednesday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Wednesday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addWednesdayOpen" name="wednesday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addWednesdayClose" name="wednesday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addWednesdayClosed" name="wednesday_closed">
                            <label class="form-check-label text-muted" for="addWednesdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addWednesdayBreakStart" name="wednesday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addWednesdayBreakEnd" name="wednesday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Thursday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Thursday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addThursdayOpen" name="thursday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addThursdayClose" name="thursday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addThursdayClosed" name="thursday_closed">
                            <label class="form-check-label text-muted" for="addThursdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addThursdayBreakStart" name="thursday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addThursdayBreakEnd" name="thursday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Friday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Friday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addFridayOpen" name="friday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addFridayClose" name="friday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addFridayClosed" name="friday_closed">
                            <label class="form-check-label text-muted" for="addFridayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addFridayBreakStart" name="friday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addFridayBreakEnd" name="friday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Saturday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Saturday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addSaturdayOpen" name="saturday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addSaturdayClose" name="saturday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addSaturdayClosed" name="saturday_closed">
                            <label class="form-check-label text-muted" for="addSaturdayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addSaturdayBreakStart" name="saturday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addSaturdayBreakEnd" name="saturday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Sunday -->
                  <div class="col-md-6">
                    <div class="card border-0 bg-light">
                      <div class="card-body p-3">
                        <label class="form-label fw-bold mb-2 text-primary">Sunday</label>
                        <div class="input-group mb-2">
                          <span class="input-group-text bg-white"><i class="fas fa-clock text-muted"></i></span>
                          <input type="time" class="form-control" id="addSundayOpen" name="sunday_open" value="08:00">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addSundayClose" name="sunday_close" value="18:00">
                          <div class="input-group-text bg-white">
                            <input class="form-check-input mt-0 me-1" type="checkbox" id="addSundayClosed" name="sunday_closed">
                            <label class="form-check-label text-muted" for="addSundayClosed" style="font-size: 0.75rem;">Closed</label>
                          </div>
                        </div>
                        <div class="input-group">
                          <span class="input-group-text bg-white"><i class="fas fa-coffee text-muted"></i></span>
                          <input type="time" class="form-control" id="addSundayBreakStart" name="sunday_break_start" placeholder="Break start">
                          <span class="input-group-text bg-white">to</span>
                          <input type="time" class="form-control" id="addSundayBreakEnd" name="sunday_break_end" placeholder="Break end">
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Copy hours button -->
                  <div class="col-12">
                    <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="copyAddMondayToAll()">
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
                    <label for="addMaxCapacity" class="form-label fw-bold">Max Capacity</label>
                    <input type="number" class="form-control" id="addMaxCapacity" name="max_capacity" value="100" min="1">
                    <small class="text-muted form-text">Maximum customer capacity</small>
                  </div>
                  <div class="col-md-4">
                    <label for="addManagerName" class="form-label fw-bold">Manager Name</label>
                    <input type="text" class="form-control" id="addManagerName" name="manager_name" placeholder="Branch manager name">
                  </div>
                  <div class="col-md-4">
                    <label for="addManagerContact" class="form-label fw-bold">Manager Contact</label>
                    <input type="text" class="form-control" id="addManagerContact" name="manager_contact" placeholder="Manager contact number">
                  </div>
                  <div class="col-12">
                    <label for="addNotes" class="form-label fw-bold">Notes</label>
                    <textarea class="form-control" id="addNotes" name="notes" rows="3" placeholder="Additional branch notes..."></textarea>
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
        <button type="button" class="btn btn-outline-primary" id="addPrevStepBtn" onclick="addPrevStep()" style="display: none;">
          <span class="fas fa-arrow-left me-2"></span>Previous
        </button>
        <button type="button" class="btn btn-primary" id="addNextStepBtn" onclick="addNextStep()">
          Next<span class="fas fa-arrow-right ms-2"></span>
        </button>
        <button type="button" class="btn btn-primary" id="addSaveBranchBtn" onclick="saveBranch()" style="display: none;">
          <span class="fas fa-save me-2"></span>Save Branch
        </button>
      </div>
    </div>
  </div>
</div>
