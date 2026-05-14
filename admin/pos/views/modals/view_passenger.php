<!-- View/Edit Passenger Modal -->
<div class="modal fade" id="viewPassengerModal" tabindex="-1" aria-labelledby="viewPassengerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="viewPassengerModalLabel">
            <span class="fas fa-user me-2"></span>Passenger Details
          </h4>
          <p class="fs-10 mb-0 text-white">View and edit passenger information</p>
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
        </div>

        <form id="viewPassengerForm" autocomplete="off">
          <input type="hidden" id="viewPassengerId" name="passenger_id">
          
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
                    <label class="form-label fw-bold" for="viewPassengerFullname">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="viewPassengerFullname" name="fullname" required autocomplete="off">
                    <div class="invalid-feedback">Full name is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerMobile">Mobile Number</label>
                    <input type="text" class="form-control" id="viewPassengerMobile" name="mobile_number" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" autocomplete="off">
                    <div class="invalid-feedback">Mobile number must be 11 digits starting with 09</div>
                    <small class="text-muted">Format: 09XXXXXXXXX (11 digits, optional)</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerEmail">Email</label>
                    <input type="email" class="form-control" id="viewPassengerEmail" name="email">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerGender">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="viewPassengerGender" name="gender" required>
                      <option value="">Select Gender</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                    <div class="invalid-feedback">Gender is required</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerBirthDate">Birth Date</label>
                    <input type="date" class="form-control" id="viewPassengerBirthDate" name="birth_date">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Customer Since</label>
                    <div class="form-control bg-light" id="viewPassengerCreatedSince">-</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Age</label>
                    <div class="form-control bg-light" id="viewPassengerAge">-</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Created At</label>
                    <div class="form-control bg-light" id="viewPassengerCreatedAt">-</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Created By</label>
                    <div class="form-control bg-light" id="viewPassengerCreatedBy">-</div>
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
                    <label class="form-label fw-bold" for="viewPassengerRegion">Region</label>
                    <select class="form-select" id="viewPassengerRegion" name="region_code" onchange="loadViewProvinces()">
                      <option value="">Select Region</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerProvince">Province</label>
                    <select class="form-select" id="viewPassengerProvince" name="province_code" onchange="loadViewCities()" disabled>
                      <option value="">Select Province</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerCity">City/Municipality</label>
                    <select class="form-select" id="viewPassengerCity" name="city_municipality_code" onchange="loadViewBarangays()" disabled>
                      <option value="">Select City/Municipality</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="viewPassengerBarangay">Barangay</label>
                    <select class="form-select" id="viewPassengerBarangay" name="barangay_code" disabled>
                      <option value="">Select Barangay</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold" for="viewPassengerStreetAddress">Street Address & Landmark</label>
                    <input type="text" class="form-control" id="viewPassengerStreetAddress" name="street_address" autocomplete="off">
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold" for="viewPassengerNotes">Notes</label>
                    <textarea class="form-control" id="viewPassengerNotes" name="notes" rows="2"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Additional Information (Hidden - All fields moved to previous steps) -->
          <div class="wizard-step" data-step="3" style="display: none;">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Created At</label>
                    <div class="form-control bg-light" id="viewPassengerCreatedAt">-</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Last Updated</label>
                    <div class="form-control bg-light" id="viewPassengerUpdatedAt">-</div>
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
        <button type="button" class="btn btn-outline-primary" id="viewPassengerPrevBtn" onclick="prevViewPassengerStep()" style="display: none;">
          <span class="fas fa-arrow-left me-2"></span>Previous
        </button>
        <button type="button" class="btn btn-primary" id="viewPassengerNextBtn" onclick="nextViewPassengerStep()">
          Next<span class="fas fa-arrow-right ms-2"></span>
        </button>
        <button type="button" class="btn btn-primary" onclick="updatePassenger()" style="display: none;">
          <span class="fas fa-save me-2"></span>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>
