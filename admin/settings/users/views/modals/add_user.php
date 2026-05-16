<?php
/**
 * Add/Edit User Modal
 */
?>
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="userModalLabel">
            <span class="fas fa-user-plus me-2"></span>
            <span id="modalTitleText">Add New User</span>
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new user account with role-based access</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <!-- Wizard Steps Indicator -->
        <div class="wizard-steps d-flex justify-content-between px-5 pt-4 pb-4">
          <div class="step-item" data-step="1">
            <div class="step-icon">
              <span class="fas fa-id-card"></span>
            </div>
            <div class="step-label">Personal</div>
          </div>
          <div class="step-item" data-step="2">
            <div class="step-icon">
              <span class="fas fa-user-circle"></span>
            </div>
            <div class="step-label">Account</div>
          </div>
          <div class="step-item" data-step="3">
            <div class="step-icon">
              <span class="fas fa-image"></span>
            </div>
            <div class="step-label">Profile Image</div>
          </div>
          <div class="step-item" data-step="4">
            <div class="step-icon">
              <span class="fas fa-user-tag"></span>
            </div>
            <div class="step-label">Role & Branch</div>
          </div>
          <div class="step-item" data-step="5">
            <div class="step-icon">
              <span class="fas fa-clock"></span>
            </div>
            <div class="step-label">Time Restrictions</div>
          </div>
        </div>
        
        <form id="userForm" novalidate autocomplete="off">
          <input type="hidden" id="userId" name="user_id">
          
          <!-- Step 1: Personal Information -->
          <div class="wizard-step active" data-step="1">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-id-card me-2"></span>
                  Personal Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-12 mb-4">
                    <label for="employeeId" class="form-label fw-bold">
                      Link to Employee <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                      <input type="text" class="form-control" id="employeeSearch" placeholder="Search employee..." onkeyup="filterEmployees()">
                      <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-chevron-down"></i>
                      </button>
                      <select class="d-none" id="employeeId" name="emp_id" onchange="updateEmployeeDetails()" required>
                        <option value="">Select Employee</option>
                        <?php
                        // Fetch employees from database with full name and details
                        $employees = @Database::fetchAll("SELECT emp_id, first_name, last_name, middle_name, b_permanent_address, emp_street_address, b_cont_no, b_email FROM employees ORDER BY first_name, last_name");
                        if ($employees): foreach ($employees as $emp): 
                            $middleInitial = $emp['middle_name'] ? strtoupper(substr($emp['middle_name'], 0, 1)) . '.' : '';
                            $displayName = trim($emp['first_name'] . ' ' . $middleInitial . ' ' . $emp['last_name']);
                        ?>
                          <option value="<?php echo $emp['emp_id']; ?>"
                                  data-fullname="<?php echo htmlspecialchars($displayName); ?>"
                                  data-permanent-address="<?php echo htmlspecialchars($emp['b_permanent_address']); ?>"
                                  data-street-address="<?php echo htmlspecialchars($emp['emp_street_address'] ?? ''); ?>"
                                  data-contact-number="<?php echo htmlspecialchars($emp['b_cont_no']); ?>"
                                  data-email="<?php echo htmlspecialchars($emp['b_email']); ?>"
                                  data-search-text="<?php echo htmlspecialchars(strtolower($displayName)); ?>">
                            <?php echo htmlspecialchars($displayName); ?>
                          </option>
                        <?php endforeach; endif; ?>
                      </select>
                      <div class="dropdown-menu w-100" id="employeeDropdown" style="max-height: 200px; overflow-y: auto;">
                        <div class="px-3 py-2 text-muted" id="employeeList">
                          <?php if ($employees): foreach ($employees as $emp): 
                            $middleInitial = $emp['middle_name'] ? strtoupper(substr($emp['middle_name'], 0, 1)) . '.' : '';
                            $displayName = trim($emp['first_name'] . ' ' . $middleInitial . ' ' . $emp['last_name']);
                          ?>
                            <a href="#" class="dropdown-item employee-option" onclick="selectEmployee(<?php echo $emp['emp_id']; ?>, '<?php echo htmlspecialchars($displayName); ?>')">
                              <?php echo htmlspecialchars($displayName); ?>
                            </a>
                          <?php endforeach; else: ?>
                            <div class="dropdown-item text-muted">No employees found</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="invalid-feedback">Please select an employee</div>
                    <small class="text-muted">Required: Select an existing employee record</small>
                  </div>
                </div>

                <!-- Employee Details Display (shown when employee is selected) -->
                <div class="row mb-4" id="employeeDetailsSection" style="display: none;">
                  <div class="col-12">
                    <div class="card border-0 bg-light">
                      <div class="card-header py-2">
                        <h6 class="mb-0 fw-bold text-primary">
                          <span class="fas fa-info-circle me-2"></span>Employee Details
                        </h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Contact Number</label>
                            <div class="form-control-plaintext" id="displayContactNumber">-</div>
                          </div>
                          <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Email</label>
                            <div class="form-control-plaintext" id="displayEmail">-</div>
                          </div>
                          <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Street Address</label>
                            <div class="form-control-plaintext" id="displayStreetAddress">-</div>
                          </div>
                          <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Permanent Address</label>
                            <div class="form-control-plaintext" id="displayPermanentAddress">-</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Step 2: Account Information -->
          <div class="wizard-step" data-step="2">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-user-circle me-2"></span>
                  Account Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-4">
                    <label for="username" class="form-label fw-bold">
                      Username <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-user"></i></span>
                      <input type="text" class="form-control" id="username" name="username" required
                             pattern="[a-zA-Z0-9_]{3,20}" placeholder="Enter username (3-20 chars)" autocomplete="off">
                    </div>
                    <div class="invalid-feedback" id="usernameInvalidFeedback">Username must be 3-20 alphanumeric characters</div>
                    <small class="text-muted">Use alphanumeric characters and underscores only</small>
                  </div>

                  <div class="col-md-6 mb-4">
                    <label for="password" class="form-label fw-bold">
                      Password <span class="text-danger" id="passwordRequired">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-lock"></i></span>
                      <input type="password" class="form-control" id="password" name="password"
                             minlength="8" placeholder="Min 8 characters" autocomplete="new-password" required>
                      <button type="button" class="btn btn-falcon-default" onclick="togglePassword('password')">
                        <span class="fas fa-eye" id="passwordToggleIcon"></span>
                      </button>
                      <button type="button" class="btn btn-falcon-default" onclick="generatePassword()" title="Generate Password">
                        <span class="fas fa-key"></span>
                      </button>
                    </div>
                    <div class="invalid-feedback">Password is required</div>
                    <small class="text-muted" id="passwordHint">Must be at least 8 characters</small>
                  </div>

                  <div class="col-md-6 mb-4">
                    <label for="email" class="form-label fw-bold">
                      Email Address
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                      <input type="email" class="form-control" id="email" name="email"
                             placeholder="user@example.com" autocomplete="off">
                    </div>
                    <div class="invalid-feedback">Please enter a valid email address</div>
                    <small class="text-muted">Used for password reset and notifications</small>
                  </div>

                  <div class="col-md-6 mb-4">
                    <label for="isActive" class="form-label fw-bold">
                      Account Status
                    </label>
                    <div class="form-check form-switch form-check-lg">
                      <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1" checked>
                      <label class="form-check-label fw-bold ms-2" for="isActive">
                        <span class="fas fa-check-circle me-2"></span>Active
                      </label>
                    </div>
                    <small class="text-muted d-block ms-5">Inactive users cannot log in to the system</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Profile Image -->
          <div class="wizard-step" data-step="3">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-image me-2"></span>
                  Profile Image
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-12">
                    <div class="text-center">
                      <div class="profile-image-container inline-block cursor-pointer" onclick="document.getElementById('profileImage').click()" style="cursor: pointer; display: inline-block;">
                        <div class="profile-image-preview" id="profileImagePreview" style="display: none;">
                          <img src="" alt="Profile Preview" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #0d6efd;">
                          <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="event.stopPropagation(); openImageCropper()">
                              <span class="fas fa-crop-alt me-1"></span>Crop
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removeProfileImage()">
                              <span class="fas fa-trash-alt me-1"></span>Remove
                            </button>
                          </div>
                        </div>
                        <div class="profile-image-placeholder" id="profileImagePlaceholder">
                          <div class="avatar avatar-xl bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 150px; height: 150px;">
                            <span class="fas fa-user fs-2"></span>
                          </div>
                          <p class="text-muted mb-0">Click to upload profile picture</p>
                        </div>
                      </div>
                      <input type="file" class="form-control d-none" id="profileImage" name="profile_image" accept="image/*" onchange="previewProfileImage(this)">
                      <small class="text-muted d-block mt-2">Optional: Upload profile picture (JPG, PNG)</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 4: User Role & Branch -->
          <div class="wizard-step" data-step="4">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-user-tag me-2"></span>
                  User Role & Branch
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-4">
                    <label for="roleId" class="form-label fw-bold">
                      User Role <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                      <select class="form-select" id="roleId" name="role_id" required>
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $role): ?>
                          <option value="<?php echo $role['role_id']; ?>"
                                  data-role-code="<?php echo htmlspecialchars($role['role_code']); ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="invalid-feedback">Please select a role</div>
                    <small class="text-muted">Determines user permissions and access level</small>
                  </div>

                  <div class="col-md-6 mb-4">
                    <label for="branchId" class="form-label fw-bold">
                      Branch
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-building"></i></span>
                      <select class="form-select" id="branchId" name="branch_id">
                        <option value="">No Branch</option>
                        <?php
                        // Fetch branches from database
                        $branches = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE deleted_at IS NULL ORDER BY branch_name");
                        foreach ($branches as $branch): ?>
                          <option value="<?php echo $branch['branch_id']; ?>">
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <small class="text-muted">Assign user to a specific branch (optional)</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 5: Time Restrictions -->
          <div class="wizard-step" data-step="5">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-clock me-2"></span>
                  Time Restrictions (Optional)
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-12 mb-4">
                    <div class="form-check form-switch form-check-lg">
                      <input class="form-check-input" type="checkbox" id="isTimeRestricted" name="is_time_restricted" value="1">
                      <label class="form-check-label fw-bold" for="isTimeRestricted">
                        <span class="fas fa-lock me-2"></span>Enable Time Restrictions
                      </label>
                      <small class="text-muted d-block ms-5">Limit user login to specific times and days</small>
                    </div>
                  </div>
                  
                  <div class="col-md-6 mb-4">
                    <label for="allowedLoginStart" class="form-label fw-bold">
                      Allowed Start Time
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-play-circle"></i></span>
                      <input type="time" class="form-control" id="allowedLoginStart" name="allowed_login_start">
                    </div>
                    <small class="text-muted">Earliest allowed login time</small>
                  </div>
                  
                  <div class="col-md-6 mb-4">
                    <label for="allowedLoginEnd" class="form-label fw-bold">
                      Allowed End Time
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-stop-circle"></i></span>
                      <input type="time" class="form-control" id="allowedLoginEnd" name="allowed_login_end">
                    </div>
                    <small class="text-muted">Latest allowed login time</small>
                  </div>
                  
                  <div class="col-md-12 mb-4">
                    <label for="allowedDays" class="form-label fw-bold">
                      Allowed Days
                    </label>
                    <div class="d-flex gap-3">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-mon" name="allowed_days[]" value="Monday">
                        <label class="form-check-label fw-bold" for="day-mon">Mon</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-tue" name="allowed_days[]" value="Tuesday">
                        <label class="form-check-label fw-bold" for="day-tue">Tue</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-wed" name="allowed_days[]" value="Wednesday">
                        <label class="form-check-label fw-bold" for="day-wed">Wed</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-thu" name="allowed_days[]" value="Thursday">
                        <label class="form-check-label fw-bold" for="day-thu">Thu</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-fri" name="allowed_days[]" value="Friday">
                        <label class="form-check-label fw-bold" for="day-fri">Fri</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-sat" name="allowed_days[]" value="Saturday">
                        <label class="form-check-label fw-bold" for="day-sat">Sat</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="day-sun" name="allowed_days[]" value="Sunday">
                        <label class="form-check-label fw-bold" for="day-sun">Sun</label>
                      </div>
                    </div>
                    <small class="text-muted d-block mt-2">Select which days the user is allowed to log in</small>
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
        <button type="button" class="btn btn-outline-primary" id="prevStepBtn" onclick="prevStep()" style="display: none;">
          <span class="fas fa-arrow-left me-2"></span>Previous
        </button>
        <button type="button" class="btn btn-primary" id="nextStepBtn" onclick="nextStep()">
          Next<span class="fas fa-arrow-right ms-2"></span>
        </button>
        <button type="button" class="btn btn-primary" id="saveUserBtn" onclick="saveUser()" style="display: none;">
          <span class="fas fa-save me-2"></span><span id="saveBtnText">Save User</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Image Cropper Modal -->
<div class="modal fade" id="imageCropperModal" tabindex="-1" aria-labelledby="imageCropperModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="imageCropperModalLabel">
            <span class="fas fa-crop-alt me-2"></span>
            Crop Profile Image
          </h4>
          <p class="fs-10 mb-0 text-white">Drag the image to position, then crop</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8">
            <div class="text-center mb-3 position-relative" id="cropContainer" style="overflow: hidden; border: 2px dashed #0d6efd; border-radius: 8px;">
              <canvas id="cropCanvas" style="max-width: 100%; cursor: move;"></canvas>
            </div>
            <div class="d-flex justify-content-center gap-2 mb-3">
              <button type="button" class="btn btn-sm btn-secondary" onclick="rotateImage(-90)">
                <span class="fas fa-undo me-1"></span>Rotate Left
              </button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="rotateImage(90)">
                <span class="fas fa-redo me-1"></span>Rotate Right
              </button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="resetCrop()">
                <span class="fas fa-sync me-1"></span>Reset
              </button>
            </div>
            <div class="text-center">
              <small class="text-muted">Drag the image to position it within the crop area</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="text-center mb-3">
              <h6 class="fw-bold">Preview (200x200)</h6>
              <div class="border rounded p-3 bg-light d-inline-block">
                <canvas id="previewCanvas" width="200" height="200" class="rounded-circle"></canvas>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Zoom (0.5x - 3x)</label>
              <input type="range" class="form-range" id="zoomSlider" min="0.5" max="3" step="0.1" value="1" onchange="updateZoom(this.value)">
            </div>
            <div class="d-grid gap-2">
              <button type="button" class="btn btn-primary" onclick="applyCrop()">
                <span class="fas fa-check me-2"></span>Apply Crop
              </button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <span class="fas fa-times me-2"></span>Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
