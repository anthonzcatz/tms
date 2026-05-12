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
          <div class="step-item active" data-step="1">
            <div class="step-icon">
              <span class="fas fa-user-circle"></span>
            </div>
            <div class="step-label">Account</div>
          </div>
          <div class="step-item" data-step="2">
            <div class="step-icon">
              <span class="fas fa-id-card"></span>
            </div>
            <div class="step-label">Personal</div>
          </div>
          <div class="step-item" data-step="3">
            <div class="step-icon">
              <span class="fas fa-clock"></span>
            </div>
            <div class="step-label">Time Restrictions</div>
          </div>
        </div>
        
        <form id="userForm" novalidate>
          <input type="hidden" id="userId" name="user_id">
          
          <!-- Step 1: Account Information -->
          <div class="wizard-step active" data-step="1">
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
                             pattern="[a-zA-Z0-9_]{3,20}" placeholder="Enter username (3-20 chars)">
                    </div>
                    <div class="invalid-feedback">Username must be 3-20 alphanumeric characters</div>
                    <small class="text-muted">Use alphanumeric characters and underscores only</small>
                  </div>
                  
                  <div class="col-md-6 mb-4">
                    <label for="email" class="form-label fw-bold">
                      Email Address
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                      <input type="email" class="form-control" id="email" name="email" 
                             placeholder="user@example.com">
                    </div>
                    <div class="invalid-feedback">Please enter a valid email address</div>
                    <small class="text-muted">Optional: Used for password recovery</small>
                  </div>
                  
                  <div class="col-md-6 mb-4">
                    <label for="password" class="form-label fw-bold">
                      Password <span class="text-danger" id="passwordRequired">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-lock"></i></span>
                      <input type="password" class="form-control" id="password" name="password" 
                             minlength="8" placeholder="Min 8 characters">
                      <button type="button" class="btn btn-falcon-default" onclick="togglePassword('password')">
                        <span class="fas fa-eye" id="passwordToggleIcon"></span>
                      </button>
                      <button type="button" class="btn btn-falcon-default" onclick="generatePassword()" title="Generate Password">
                        <span class="fas fa-key"></span>
                      </button>
                    </div>
                    <div class="invalid-feedback">Password must be at least 8 characters</div>
                    <small class="text-muted" id="passwordHint">Leave blank to keep current password when editing</small>
                  </div>
                  
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
                </div>
              </div>
            </div>
          </div>
          
          <!-- Step 2: Personal Information -->
          <div class="wizard-step" data-step="2">
            <div class="card border-0 shadow-sm m-3">
              <div class="card-header bg-light border-0 py-2">
                <h6 class="mb-0 fw-bold text-primary">
                  <span class="fas fa-id-card me-2"></span>
                  Personal Information
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-4">
                    <label for="firstName" class="form-label fw-bold">
                      Full Name <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-user"></i></span>
                      <input type="text" class="form-control" id="firstName" name="fullname" required 
                             placeholder="Enter full name">
                    </div>
                    <div class="invalid-feedback">Full name is required</div>
                    <small class="text-muted">User's full name for display</small>
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
                  
                  <div class="col-md-6 mb-4">
                    <label for="profileImage" class="form-label fw-bold">
                      Profile Image
                    </label>
                    <div class="mb-2">
                      <div class="profile-image-container text-center cursor-pointer" onclick="document.getElementById('profileImage').click()" style="cursor: pointer;">
                        <div class="profile-image-preview" id="profileImagePreview" style="display: none;">
                          <img src="" alt="Profile Preview" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #0d6efd;">
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
                          <div class="avatar avatar-xl bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 100px; height: 100px;">
                            <span class="fas fa-user fs-4"></span>
                          </div>
                          <p class="text-muted fs-10 mb-0">Click to upload</p>
                        </div>
                      </div>
                    </div>
                    <input type="file" class="form-control d-none" id="profileImage" name="profile_image" accept="image/*" onchange="previewProfileImage(this)">
                    <small class="text-muted">Optional: Upload profile picture (JPG, PNG)</small>
                  </div>
                  
                  <div class="col-md-6 mb-4">
                    <label for="isActive" class="form-label fw-bold text-center">Account Status</label>
                    <div class="form-check form-switch form-check-lg d-flex justify-content-center align-items-center">
                      <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1" checked>
                      <label class="form-check-label fw-bold ms-2" for="isActive">
                        <span class="fas fa-check-circle me-2"></span>Active
                      </label>
                    </div>
                    <small class="text-muted d-block text-center mt-1">Inactive users cannot log in to the system</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Step 3: Time Restrictions -->
          <div class="wizard-step" data-step="3">
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
