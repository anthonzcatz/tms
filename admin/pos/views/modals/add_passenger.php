<!-- Add Passenger Modal -->
<div class="modal fade" id="addPassengerModal" tabindex="-1" aria-labelledby="addPassengerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addPassengerModalLabel">
            <span class="fas fa-user-plus me-2"></span>Add New Passenger
          </h4>
          <p class="fs-10 mb-0 text-white">Register a new passenger for ticket bookings</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <!-- Wizard Steps Indicator -->
        <div class="wizard-steps d-flex justify-content-between px-5 pt-4 pb-4">
          <div class="step-item active" data-step="1">
            <div class="step-icon">
              <span class="fas fa-id-card"></span>
            </div>
            <div class="step-label">Personal</div>
          </div>
          <div class="step-item" data-step="2">
            <div class="step-icon">
              <span class="fas fa-map-marker-alt"></span>
            </div>
            <div class="step-label">Address</div>
          </div>
          <div class="step-item" data-step="3">
            <div class="step-icon">
              <span class="fas fa-info-circle"></span>
            </div>
            <div class="step-label">Additional</div>
          </div>
        </div>

        <form id="addPassengerForm">
          <!-- Step 1: Personal Information -->
          <div class="wizard-step active" data-step="1">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-id-card me-2"></span>Personal Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerFullname">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="newPassengerFullname" name="fullname" required placeholder="e.g. Juan Dela Cruz">
                    <div class="invalid-feedback">Full name is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerMobile">Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="newPassengerMobile" name="mobile_number" required placeholder="e.g. 09123456789" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric">
                    <div class="invalid-feedback">Mobile number must be 11 digits starting with 09</div>
                    <small class="text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerEmail">Email <span class="text-danger small ms-2">(Optional)</span></label>
                    <input type="email" class="form-control" id="newPassengerEmail" name="email" placeholder="e.g. juan@example.com">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerGender">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="newPassengerGender" name="gender" required>
                      <option value="">Select Gender</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                    <div class="invalid-feedback">Gender is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerBirthDate">Birth Date <span class="text-danger small ms-2">(Optional)</span></label>
                    <input type="date" class="form-control" id="newPassengerBirthDate" name="birth_date">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2: Address Information -->
          <div class="wizard-step" data-step="2">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-map-marker-alt me-2"></span>Address Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerRegion">Region <span class="text-danger">*</span></label>
                    <select class="form-select" id="newPassengerRegion" name="region_code" onchange="loadProvinces()" required>
                      <option value="">Select Region</option>
                    </select>
                    <div class="invalid-feedback">Region is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerProvince">Province <span class="text-danger">*</span></label>
                    <select class="form-select" id="newPassengerProvince" name="province_code" onchange="loadCities()" disabled required>
                      <option value="">Select Province</option>
                    </select>
                    <div class="invalid-feedback">Province is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerCity">City/Municipality <span class="text-danger">*</span></label>
                    <select class="form-select" id="newPassengerCity" name="city_municipality_code" onchange="loadBarangays()" disabled required>
                      <option value="">Select City/Municipality</option>
                    </select>
                    <div class="invalid-feedback">City/Municipality is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerBarangay">Barangay <span class="text-danger">*</span></label>
                    <select class="form-select" id="newPassengerBarangay" name="barangay_code" disabled required>
                      <option value="">Select Barangay</option>
                    </select>
                    <div class="invalid-feedback">Barangay is required</div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold" for="newPassengerStreetAddress">Street Address <span class="text-danger small ms-2">(Optional)</span></label>
                    <input type="text" class="form-control" id="newPassengerStreetAddress" name="street_address" placeholder="e.g. 123 Main St">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerLandmark">Landmark <span class="text-danger small ms-2">(Optional)</span></label>
                    <input type="text" class="form-control" id="newPassengerLandmark" name="landmark" placeholder="e.g. Near Mall">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="newPassengerZipCode">Zip Code <span class="text-danger small ms-2">(Optional)</span></label>
                    <input type="text" class="form-control" id="newPassengerZipCode" name="zip_code" placeholder="e.g. 1000">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Additional Information -->
          <div class="wizard-step" data-step="3">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-info-circle me-2"></span>Additional Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label fw-bold" for="newPassengerNotes">Notes <span class="text-danger small ms-2">(Optional)</span></label>
                    <textarea class="form-control" id="newPassengerNotes" name="notes" rows="4" placeholder="Additional notes about the passenger..."></textarea>
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
        <button type="button" class="btn btn-outline-primary" id="prevPassengerStepBtn" onclick="prevPassengerStep()" style="display: none;">
          <span class="fas fa-arrow-left me-2"></span>Previous
        </button>
        <button type="button" class="btn btn-primary" id="nextPassengerStepBtn" onclick="nextPassengerStep()">
          Next<span class="fas fa-arrow-right ms-2"></span>
        </button>
        <button type="button" class="btn btn-primary" id="savePassengerBtn" onclick="saveNewPassenger()" style="display: none;">
          <span class="fas fa-save me-2"></span>Save Passenger
        </button>
      </div>
    </div>
  </div>
</div>
