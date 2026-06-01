<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php require_once dirname(dirname(__DIR__)) . '/includes/head.php'; ?>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/user/assets/css/user.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/user.css'); ?>">

<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script>

      <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>

      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(__DIR__)) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(__DIR__)) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }

        ?><?php endif; ?>

        <?php
        $currentView = 'settings';
        include __DIR__ . '/_banner.php';

        // Additional variables from $userProfile
        $recoveryEmail = htmlspecialchars($up['recovery_email'] ?? '');
        $recoveryEmailVerifiedAt = $up['recovery_email_verified_at'];
        $isRecoveryEmailVerified = !empty($recoveryEmailVerifiedAt);
        
        // Employee data for settings form
        $empId = $up['emp_id'] ?? null;
        $empGender = htmlspecialchars($up['b_sex'] ?? '');
        $empAddress = htmlspecialchars($up['b_address'] ?? '');
        $empStreetAddress = htmlspecialchars($up['emp_street_address'] ?? '');
        $empProvinceCode = htmlspecialchars($up['emp_province_code'] ?? '');
        $empCityCode = htmlspecialchars($up['emp_city_code'] ?? '');
        $empBarangayCode = htmlspecialchars($up['emp_barangay_code'] ?? '');
        ?>

          <!-- Settings Content -->
          <div class="row g-0">
            <div class="col-lg-8 pe-lg-2">
              <div class="card mb-3">
                <div class="card-header">
                  <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link active" id="profile-settings-tab" data-bs-toggle="tab" href="#profile-settings" role="tab" aria-controls="profile-settings" aria-selected="true">Profile Settings</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="change-password-tab" data-bs-toggle="tab" href="#change-password" role="tab" aria-controls="change-password" aria-selected="false">Change Password</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="recovery-email-tab" data-bs-toggle="tab" href="#recovery-email" role="tab" aria-controls="recovery-email" aria-selected="false">Recovery Email</a>
                    </li>
                  </ul>
                </div>
                <div class="card-body bg-body-tertiary">
                  <div class="tab-content">
                    <!-- Profile Settings Tab -->
                    <div class="tab-pane fade show active" id="profile-settings" role="tabpanel" aria-labelledby="profile-settings-tab">
                      <form class="row g-3" id="profileSettingsForm">
                        <div class="col-lg-4">
                          <label class="form-label" for="first-name">First Name</label>
                          <input class="form-control" id="first-name" type="text" value="<?php echo $firstName; ?>" readonly />
                        </div>
                        <div class="col-lg-4">
                          <label class="form-label" for="middle-name">Middle Name</label>
                          <input class="form-control" id="middle-name" type="text" value="<?php echo htmlspecialchars($up['middle_name'] ?? ''); ?>" readonly />
                        </div>
                        <div class="col-lg-4">
                          <label class="form-label" for="last-name">Last Name</label>
                          <input class="form-control" id="last-name" type="text" value="<?php echo $lastName; ?>" readonly />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="email1">Email</label>
                          <input class="form-control" id="email1" type="email" value="<?php echo $email; ?>" readonly />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="phone1">Phone Number <span class="text-danger">*</span></label>
                          <input class="form-control" id="phone1" type="text" value="<?php echo $phone; ?>" />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                          <select class="form-select" id="gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (htmlspecialchars($up['b_sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (htmlspecialchars($up['b_sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (htmlspecialchars($up['b_sex'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                          </select>
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="position">Position</label>
                          <input class="form-control" id="position" type="text" value="<?php echo $position; ?>" readonly />
                        </div>
                        <div class="col-lg-12">
                          <label class="form-label" for="street-address">Street Address / Landmark (Optional)</label>
                          <input class="form-control" id="street-address" type="text" value="<?php echo htmlspecialchars($up['emp_street_address'] ?? ''); ?>" placeholder="e.g., 123 Main St (Near Mall)" />
                        </div>
                        <div class="col-lg-4">
                          <label class="form-label" for="province">Province <span class="text-danger">*</span></label>
                          <select class="form-select" id="province">
                            <option value="">Select Province</option>
                          </select>
                        </div>
                        <div class="col-lg-4">
                          <label class="form-label" for="city">City/Municipality <span class="text-danger">*</span></label>
                          <select class="form-select" id="city" disabled>
                            <option value="">Select City/Municipality</option>
                          </select>
                        </div>
                        <div class="col-lg-4">
                          <label class="form-label" for="barangay">Barangay <span class="text-danger">*</span></label>
                          <select class="form-select" id="barangay" disabled>
                            <option value="">Select Barangay</option>
                          </select>
                        </div>
                        <div class="col-lg-12">
                          <label class="form-label" for="permanent-address">Permanent Address (Auto-generated)</label>
                          <textarea class="form-control" id="permanent-address" rows="2" readonly><?php echo htmlspecialchars($up['b_permanent_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                          <button class="btn btn-primary" type="button" onclick="updateProfileSettings()">Update</button>
                        </div>
                      </form>
                    </div>
                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="change-password" role="tabpanel" aria-labelledby="change-password-tab">
                      <form id="change-password-form">
                        <div class="mb-3">
                          <label class="form-label" for="old-password">Current Password <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <input class="form-control" id="old-password" type="password" required />
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('old-password')">
                              <span class="fas fa-eye" id="old-password-icon"></span>
                            </button>
                          </div>
                          <div id="old-password-status" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="new-password">New Password <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <input class="form-control" id="new-password" type="password" required minlength="8" />
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new-password')">
                              <span class="fas fa-eye" id="new-password-icon"></span>
                            </button>
                          </div>
                          <div class="form-text">Minimum 8 characters. Must include uppercase, lowercase, number, and special character.</div>
                          <div id="password-strength" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="confirm-password">Confirm New Password <span class="text-danger">*</span></label>
                          <div class="input-group">
                            <input class="form-control" id="confirm-password" type="password" required />
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm-password')">
                              <span class="fas fa-eye" id="confirm-password-icon"></span>
                            </button>
                          </div>
                          <div id="password-match" class="mt-2"></div>
                        </div>
                        <button class="btn btn-primary d-block w-100" type="submit" id="change-password-btn" disabled>
                          <span id="change-password-btn-text">Update Password</span>
                          <span id="change-password-btn-spinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                        </button>
                      </form>
                    </div>
                    <!-- Recovery Email Tab -->
                    <div class="tab-pane fade" id="recovery-email" role="tabpanel" aria-labelledby="recovery-email-tab">
                      <p class="text-600 mb-3">Set a recovery email to help you regain access if you forget your password. This email can be used for password reset.</p>
                      <div class="alert alert-info d-flex align-items-center mb-3">
                        <span class="fas fa-info-circle me-2"></span>
                        <div>Your primary email: <strong><?php echo htmlspecialchars($email); ?></strong></div>
                      </div>
                      <form id="recovery-email-form">
                        <div class="mb-3">
                          <label class="form-label" for="recovery-email">Recovery Email</label>
                          <div class="input-group">
                            <input class="form-control" id="recovery-email" type="email" value="<?php echo $recoveryEmail; ?>" placeholder="Enter recovery email" />
                            <?php if ($recoveryEmail): ?>
                              <button class="btn btn-outline-primary" type="button" id="send-verification-btn">
                                <?php echo $isRecoveryEmailVerified ? '<span class="fas fa-check text-success me-1"></span>Verified' : '<span class="fas fa-paper-plane me-1"></span>Verify'; ?>
                              </button>
                            <?php else: ?>
                              <button class="btn btn-primary" type="submit" id="save-recovery-email-btn">Save</button>
                            <?php endif; ?>
                          </div>
                          <?php if ($recoveryEmail && $isRecoveryEmailVerified): ?>
                            <div class="form-text text-success mt-2">
                              <span class="fas fa-check-circle me-1"></span>Recovery email verified on <?php echo Auth::formatTimestamp($recoveryEmailVerifiedAt, 'F j, Y'); ?>
                            </div>
                          <?php elseif ($recoveryEmail): ?>
                            <div class="form-text text-warning mt-2">
                              <span class="fas fa-exclamation-circle me-1"></span>Recovery email not yet verified. Click "Verify" to send a verification link.
                            </div>
                          <?php endif; ?>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-3">
                <div class="card-header">
                  <h5 class="mb-0">Experiences</h5>
                </div>
                <div class="card-body bg-body-tertiary">
                  <a class="mb-4 d-block d-flex align-items-center" href="#experience-form1" data-bs-toggle="collapse" aria-expanded="false" aria-controls="experience-form1"><span class="circle-dashed"><span class="fas fa-plus"></span></span><span class="ms-3">Add new experience</span></a>
                  <div class="collapse" id="experience-form1">
                    <form class="row">
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="company">Company</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="company" type="text" />
                      </div>
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="position">Position</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="position" type="text" />
                      </div>
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="city">City</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="city" type="text" />
                      </div>
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="exp-description">Description</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <textarea class="form-control form-control-sm" id="exp-description" rows="3"></textarea>
                      </div>
                      <div class="col-9 col-sm-7 offset-3 mb-3">
                        <div class="form-check mb-0 lh-1">
                          <input class="form-check-input" type="checkbox" id="experience-current" checked="checked" />
                          <label class="form-check-label mb-0" for="experience-current">I currently work here</label>
                        </div>
                      </div>
                      <div class="col-9 col-sm-7 offset-3">
                        <button class="btn btn-primary" type="button">Save</button>
                      </div>
                    </form>
                    <div class="border-dashed-bottom my-4"></div>
                  </div>
                  <div class="d-flex"><a href="#!"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/logos/g.png" alt="" width="56" /></a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0">Big Data Engineer<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></h6>
                      <p class="mb-1"><a href="#!">Google</a></p>
                      <p class="text-1000 mb-0">Apr 2012 - Present &bull; 6 yrs 9 mos</p>
                      <p class="text-1000 mb-0">California, USA</p>
                      <div class="border-bottom border-dashed my-3"></div>
                    </div>
                  </div>
                  <div class="d-flex"><a href="#!"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/logos/apple.png" alt="" width="56" /></a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0">Software Engineer<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></h6>
                      <p class="mb-1"><a href="#!">Apple</a></p>
                      <p class="text-1000 mb-0">Jan 2012 - Apr 2012 &bull; 4 mos</p>
                      <p class="text-1000 mb-0">California, USA</p>
                      <div class="border-bottom border-dashed my-3"></div>
                    </div>
                  </div>
                  <div class="d-flex"><a href="#!"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/logos/nike.png" alt="" width="56" /></a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0">Mobile App Developer<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></h6>
                      <p class="mb-1"><a href="#!">Nike</a></p>
                      <p class="text-1000 mb-0">Jan 2011 - Apr 2012 &bull; 1 yr 4 mos</p>
                      <p class="text-1000 mb-0">Beaverton, USA</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card mb-3 mb-lg-0">
                <div class="card-header">
                  <h5 class="mb-0">Educations</h5>
                </div>
                <div class="card-body bg-body-tertiary">
                  <a class="mb-4 d-block d-flex align-items-center" href="#education-form" data-bs-toggle="collapse" aria-expanded="false" aria-controls="education-form"><span class="circle-dashed"><span class="fas fa-plus"></span></span><span class="ms-3">Add new education</span></a>
                  <div class="collapse" id="education-form">
                    <form class="row">
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="school">School</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="school" type="text" />
                      </div>
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="degree">Degree</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="degree" type="text" />
                      </div>
                      <div class="col-3 mb-3 text-lg-end">
                        <label class="form-label" for="field">Field</label>
                      </div>
                      <div class="col-9 col-sm-7 mb-3">
                        <input class="form-control form-control-sm" id="field" type="text" />
                      </div>
                      <div class="col-9 col-sm-7 offset-3">
                        <button class="btn btn-primary" type="button">Save</button>
                      </div>
                    </form>
                    <div class="border-dashed-bottom my-3"></div>
                  </div>
                  <div class="d-flex"><a href="#!">
                      <div class="avatar avatar-3xl">
                        <div class="avatar-name rounded-circle"><span>SU</span></div>
                      </div>
                    </a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0"><a href="#!">Stanford University<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></a></h6>
                      <p class="mb-1">Computer Science and Engineering</p>
                      <p class="text-1000 mb-0">2010 - 2014 &bull; 4 yrs</p>
                      <p class="text-1000 mb-0">California, USA</p>
                      <div class="border-bottom border-dashed my-3"></div>
                    </div>
                  </div>
                  <div class="d-flex"><a href="#!"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/logos/staten.png" alt="" width="56" /></a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0"><a href="#!">Staten Island Technical High School<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></a></h6>
                      <p class="mb-1">Higher Secondary School Certificate, Science</p>
                      <p class="text-1000 mb-0">2008 - 2010 &bull; 2 yrs</p>
                      <p class="text-1000 mb-0">New York, USA</p>
                      <div class="border-bottom border-dashed my-3"></div>
                    </div>
                  </div>
                  <div class="d-flex"><a href="#!"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/logos/tj-heigh-school.png" alt="" width="56" /></a>
                    <div class="flex-1 position-relative ps-3">
                      <h6 class="fs-9 mb-0"><a href="#!">Thomas Jefferson High School for Science and Technology<span data-bs-toggle="tooltip" data-bs-placement="top" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></a></h6>
                      <p class="mb-1">Secondary School Certificate, Science</p>
                      <p class="text-1000 mb-0">2003 - 2008 &bull; 5 yrs</p>
                      <p class="text-1000 mb-0">Alexandria, USA</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 ps-lg-2">
              <div class="sticky-sidebar">
                <!-- Account Information Card -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                  </div>
                  <div class="card-body bg-body-tertiary fs-10">
                    <div class="mb-3">
                      <label class="form-label text-muted">User Code</label>
                      <div class="fw-semibold"><?php echo htmlspecialchars($up['user_code'] ?? 'N/A'); ?></div>
                    </div>
                    <?php if ($up['branch_name']): ?>
                    <div class="mb-3">
                      <label class="form-label text-muted">Branch</label>
                      <div class="fw-semibold"><?php echo htmlspecialchars(($up['branch_code'] ?? '') . ' - ' . $up['branch_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                      <label class="form-label text-muted">Account Status</label>
                      <div>
                        <?php
                        $status = $up['status'] ?? 'active';
                        $statusClass = $status === 'active' ? 'bg-success' : ($status === 'suspended' ? 'bg-warning' : 'bg-secondary');
                        ?>
                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label text-muted">Member Since</label>
                      <div class="fw-semibold"><?php echo $up['created_at'] ? Auth::formatTimestamp($up['created_at'], 'F j, Y') : 'N/A'; ?></div>
                    </div>
                    <div class="mb-3">
                      <label class="text-muted small text-uppercase fw-bold">Last Login</label>
                      <div class="fw-semibold"><?php echo $up['last_login_at'] ? Auth::formatTimestamp($up['last_login_at'], 'F j, Y \a\t g:i A') : 'Never'; ?></div>
                    </div>
                    <div class="mb-3">
                      <label class="text-muted small text-uppercase fw-bold">Password Changed</label>
                      <div class="fw-semibold"><?php echo $up['password_changed_at'] ? Auth::formatTimestamp($up['password_changed_at'], 'F j, Y') : 'Never'; ?></div>
                    </div>
                    <?php if ($up['require_password_change']): ?>
                    <div class="alert alert-warning py-2 mb-0">
                      <span class="fas fa-exclamation-triangle me-1"></span>Password change required
                    </div>
                    <?php endif; ?>
                    <?php if ($up['is_time_restricted']): ?>
                    <div class="border-top my-3"></div>
                    <div class="mb-2">
                      <label class="form-label text-muted">Login Time Restrictions</label>
                      <div class="fw-semibold">
                        <?php echo ($up['allowed_login_start'] ?? '00:00') . ' - ' . ($up['allowed_login_end'] ?? '23:59'); ?>
                      </div>
                      <?php if ($up['allowed_days']): ?>
                      <div class="text-muted small">Days: <?php echo str_replace(',', ', ', $up['allowed_days']); ?></div>
                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($up['has_restricted_transport']): ?>
                    <div class="alert alert-info py-2 mb-0">
                      <span class="fas fa-info-circle me-1"></span>Transport restrictions apply
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="card mb-3 overflow-hidden">
                  <div class="card-header">
                    <h5 class="mb-0">Account Settings</h5>
                  </div>
                  <div class="card-body bg-body-tertiary">
                    <h6 class="fw-bold">Who can see your profile ?<span class="fs-11 ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Only The group of selected people can see your profile"><span class="fas fa-question-circle"></span></span></h6>
                    <div class="ps-2">
                      <div class="form-check mb-0 lh-1">
                        <input class="form-check-input" type="radio" value="" id="everyone" name="view-settings" />
                        <label class="form-check-label mb-0" for="everyone">Everyone</label>
                      </div>
                      <div class="form-check mb-0 lh-1">
                        <input class="form-check-input" type="radio" value="" id="my-followers" checked="checked" name="view-settings" />
                        <label class="form-check-label mb-0" for="my-followers">My followers</label>
                      </div>
                      <div class="form-check mb-0 lh-1">
                        <input class="form-check-input" type="radio" value="" id="only-me" name="view-settings" />
                        <label class="form-check-label mb-0" for="only-me">Only me</label>
                      </div>
                    </div>
                    <h6 class="mt-2 fw-bold">Who can tag you ?<span class="fs-11 ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Only The group of selected people can tag you"><span class="fas fa-question-circle"></span></span></h6>
                    <div class="ps-2">
                      <div class="form-check mb-0 lh-1">
                        <input class="form-check-input" type="radio" value="" id="tag-everyone" name="tag-settings" />
                        <label class="form-check-label mb-0" for="tag-everyone">Everyone</label>
                      </div>
                      <div class="form-check mb-0 lh-1">
                        <input class="form-check-input" type="radio" value="" id="group-members" checked="checked" name="tag-settings" />
                        <label class="form-check-label mb-0" for="group-members">Group Members</label>
                      </div>
                    </div>
                    <div class="border-dashed-bottom my-3"></div>
                    <div class="form-check mb-0 lh-1">
                      <input class="form-check-input" type="checkbox" id="userSettings1" checked="checked" />
                      <label class="form-check-label mb-0" for="userSettings1">Allow users to show your followers</label>
                    </div>
                    <div class="form-check mb-0 lh-1">
                      <input class="form-check-input" type="checkbox" id="userSettings2" checked="checked" />
                      <label class="form-check-label mb-0" for="userSettings2">Allow users to show your email</label>
                    </div>
                    <div class="form-check mb-0 lh-1">
                      <input class="form-check-input" type="checkbox" id="userSettings3" />
                      <label class="form-check-label mb-0" for="userSettings3">Allow users to show your experiences</label>
                    </div>
                    <div class="border-bottom border-dashed my-3"></div>
                    <div class="form-check form-switch mb-0 lh-1">
                      <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault" checked="checked" />
                      <label class="form-check-label mb-0" for="flexSwitchCheckDefault">Make your phone number visible</label>
                    </div>
                    <div class="form-check form-switch mb-0 lh-1">
                      <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" />
                      <label class="form-check-label mb-0" for="flexSwitchCheckChecked">Allow user to follow you</label>
                    </div>
                  </div>
                </div>

                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="mb-0">Billing Setting</h5>
                  </div>
                  <div class="card-body bg-body-tertiary">
                    <h5>Plan</h5>
                    <p class="fs-9"><strong>Developer</strong>- Unlimited private repositories</p>
                    <a class="btn btn-falcon-default btn-sm" href="#!">Update Plan</a>
                  </div>
                  <div class="card-body bg-body-tertiary border-top">
                    <h5>Payment</h5>
                    <p class="fs-9">You have not added any payment.</p>
                    <a class="btn btn-falcon-default btn-sm" href="#!">Add Payment</a>
                  </div>
                </div>
                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Danger Zone</h5>
                  </div>
                  <div class="card-body bg-body-tertiary">
                    <h5 class="fs-9">Transfer Ownership</h5>
                    <p class="fs-10">Transfer this account to another user or to an organization where you have the ability to create repositories.</p>
                    <a class="btn btn-falcon-warning d-block" href="#!">Transfer</a>
                    <div class="border-bottom border-dashed my-4"></div>
                    <h5 class="fs-9">Delete this account</h5>
                    <p class="fs-10">Once you delete a account, there is no going back. Please be certain.</p>
                    <a class="btn btn-falcon-danger d-block" href="#!">Deactivate Account</a>
                  </div>
                </div>
              </div>
            </div>
          </div>

      </div>
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/user/assets/js/user.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/user.js'); ?>"></script>
  <script>
  // Persist active tab state across page loads
  document.addEventListener('DOMContentLoaded', function() {
    var settingsTabs = document.querySelector('#profile-settings-tab, #change-password-tab, #recovery-email-tab');
    if (settingsTabs) {
      var savedTab = localStorage.getItem('settingsActiveTab');
      if (savedTab) {
        var savedTabElement = document.querySelector(savedTab);
        if (savedTabElement) {
          var tabTrigger = new bootstrap.Tab(savedTabElement);
          tabTrigger.show();
        }
      }

      // Save active tab to localStorage when changed
      var tabButtons = document.querySelectorAll('#profile-settings-tab, #change-password-tab, #recovery-email-tab');
      tabButtons.forEach(function(button) {
        button.addEventListener('shown.bs.tab', function(event) {
          localStorage.setItem('settingsActiveTab', '#' + event.target.id);
        });
      });
    }

    // Recovery Email Form Handler
    var recoveryEmailForm = document.getElementById('recovery-email-form');
    var saveBtn = document.getElementById('save-recovery-email-btn');
    var verifyBtn = document.getElementById('send-verification-btn');

    // Handle form submit to prevent default submission
    if (recoveryEmailForm) {
      recoveryEmailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (saveBtn) {
          saveRecoveryEmail();
        }
      });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', function(e) {
        e.preventDefault();
        saveRecoveryEmail();
      });
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        sendVerificationEmail();
      });
    }

    function saveRecoveryEmail() {
      var emailInput = document.getElementById('recovery-email');
      var email = emailInput.value.trim();
      var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      if (!email) {
        alert('Please enter a recovery email.');
        return;
      }

      if (!email.includes('@') || !email.includes('.')) {
        alert('Please enter a valid email address.');
        return;
      }

      var formData = new FormData();
      formData.append('action', 'save');
      formData.append('email', email);
      formData.append('csrf_token', csrfToken);

      fetch('<?php echo BASE_URL; ?>/api/user/recovery-email.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          location.reload();
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
      });
    }

    // Load provinces on page load
    function loadProvinces() {
      fetch('<?php echo BASE_URL; ?>/api/psgc/provinces.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const provinceSelect = document.getElementById('province');
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            data.provinces.forEach(province => {
              const option = document.createElement('option');
              option.value = province.province_code;
              option.textContent = province.province_name;
              provinceSelect.appendChild(option);
            });
            
            // Set selected value after options are added
            const currentProvinceCode = '<?php echo $empProvinceCode; ?>';
            if (currentProvinceCode) {
              provinceSelect.value = currentProvinceCode;
              loadCities(currentProvinceCode, true);
            }
          }
        })
        .catch(error => console.error('Error loading provinces:', error));
    }

    // Load cities when province is selected
    function loadCities(provinceCode, isInitialLoad = false) {
      if (!provinceCode) {
        document.getElementById('city').innerHTML = '<option value="">Select City/Municipality</option>';
        document.getElementById('city').disabled = true;
        document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        document.getElementById('barangay').disabled = true;
        return;
      }

      fetch('<?php echo BASE_URL; ?>/api/psgc/cities.php?province_code=' + provinceCode)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const citySelect = document.getElementById('city');
            citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            data.cities.forEach(city => {
              const option = document.createElement('option');
              option.value = city.city_municipality_code;
              option.textContent = city.city_municipality_name;
              citySelect.appendChild(option);
            });
            citySelect.disabled = false;
            
            // Set selected value after options are added
            const currentCityCode = '<?php echo $empCityCode; ?>';
            if (currentCityCode) {
              citySelect.value = currentCityCode;
              loadBarangays(currentCityCode, true);
            }
            
            // Update permanent address on initial load (if no barangay code)
            if (isInitialLoad && !'<?php echo $empCityCode; ?>') {
              updatePermanentAddress();
            }
          }
        })
        .catch(error => console.error('Error loading cities:', error));
    }

    // Load barangays when city is selected
    function loadBarangays(cityCode, isInitialLoad = false) {
      if (!cityCode) {
        document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        document.getElementById('barangay').disabled = true;
        return;
      }

      fetch('<?php echo BASE_URL; ?>/api/psgc/barangays.php?city_code=' + cityCode)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const barangaySelect = document.getElementById('barangay');
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            data.barangays.forEach(barangay => {
              const option = document.createElement('option');
              option.value = barangay.barangay_code;
              option.textContent = barangay.barangay_name;
              barangaySelect.appendChild(option);
            });
            barangaySelect.disabled = false;
            
            // Set selected value after options are added
            const currentBarangayCode = '<?php echo $empBarangayCode; ?>';
            if (currentBarangayCode) {
              barangaySelect.value = currentBarangayCode;
            }
            
            // Update permanent address on initial load
            if (isInitialLoad) {
              updatePermanentAddress();
            }
          }
        })
        .catch(error => console.error('Error loading barangays:', error));
    }

    // Update permanent address when dropdowns change
    function updatePermanentAddress() {
      const provinceSelect = document.getElementById('province');
      const citySelect = document.getElementById('city');
      const barangaySelect = document.getElementById('barangay');
      const streetAddress = document.getElementById('street-address').value.trim();

      const provinceName = provinceSelect.options[provinceSelect.selectedIndex]?.text || '';
      const cityName = citySelect.options[citySelect.selectedIndex]?.text || '';
      const barangayName = barangaySelect.options[barangaySelect.selectedIndex]?.text || '';

      if (provinceName && cityName && barangayName) {
        // UI Display: Combined address (Street Address/Landmark, Barangay, City, Province)
        let displayAddress = `${barangayName}, ${cityName}, ${provinceName}`;
        if (streetAddress) {
          displayAddress = `${streetAddress}, ${barangayName}, ${cityName}, ${provinceName}`;
        }
        document.getElementById('permanent-address').value = displayAddress;
      }
    }

    // Event listeners for dropdowns
    document.getElementById('province').addEventListener('change', function() {
      loadCities(this.value);
      updatePermanentAddress();
    });
    document.getElementById('city').addEventListener('change', function() {
      loadBarangays(this.value);
      updatePermanentAddress();
    });
    document.getElementById('barangay').addEventListener('change', updatePermanentAddress);
    document.getElementById('street-address').addEventListener('input', updatePermanentAddress);

    // Load provinces on page load
    loadProvinces();
  });

  // Global function for profile settings update (outside DOMContentLoaded)
  function updateProfileSettings() {
    console.log('updateProfileSettings called');
    var phone = document.getElementById('phone1').value.trim();
    var gender = document.getElementById('gender').value;
    var streetAddress = document.getElementById('street-address').value.trim();
    var provinceCode = document.getElementById('province').value;
    var cityCode = document.getElementById('city').value;
    var barangayCode = document.getElementById('barangay').value;
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Generate permanent address for database (only Barangay, City, Province)
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    const provinceName = provinceSelect.options[provinceSelect.selectedIndex]?.text || '';
    const cityName = citySelect.options[citySelect.selectedIndex]?.text || '';
    const barangayName = barangaySelect.options[barangaySelect.selectedIndex]?.text || '';
    const permanentAddress = `${barangayName}, ${cityName}, ${provinceName}`;

    console.log('Phone:', phone);
    console.log('Gender:', gender);
    console.log('Street Address:', streetAddress);
    console.log('Province Code:', provinceCode);
    console.log('City Code:', cityCode);
    console.log('Barangay Code:', barangayCode);
    console.log('Permanent Address (for DB):', permanentAddress);

    if (!phone) {
      showToast('error', 'Please enter a phone number.');
      return;
    }

    if (!gender) {
      showToast('error', 'Please select a gender.');
      return;
    }

    // Street address is optional
    // if (!streetAddress) {
    //   showToast('error', 'Please enter a street address.');
    //   return;
    // }

    if (!provinceCode || !cityCode || !barangayCode) {
      showToast('error', 'Please select province, city, and barangay.');
      return;
    }

    var formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('phone', phone);
    formData.append('gender', gender);
    formData.append('street_address', streetAddress);
    formData.append('province_code', provinceCode);
    formData.append('city_code', cityCode);
    formData.append('barangay_code', barangayCode);
    formData.append('permanent_address', permanentAddress);
    formData.append('csrf_token', csrfToken);

    console.log('Sending data to API...');
    console.log('Permanent Address being sent to DB:', permanentAddress);

    fetch('<?php echo BASE_URL; ?>/api/user/profile-settings.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      console.log('API Response:', data);
      if (data.success) {
        showToast('success', data.message);
        setTimeout(function() {
          location.reload();
        }, 1500);
      } else {
        showToast('error', data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error', 'An error occurred. Please try again.');
    });
  }

  // Toast notification function
  function showToast(type, message) {
    var toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
      toastContainer.style.zIndex = '9999';
      document.body.appendChild(toastContainer);
    }

    var bgClass = 'bg-info';
    var icon = 'fa-info-circle';
    var title = 'Info';

    if (type === 'success') {
      bgClass = 'bg-success';
      icon = 'fa-check-circle';
      title = 'Success';
    } else if (type === 'error') {
      bgClass = 'bg-danger';
      icon = 'fa-exclamation-circle';
      title = 'Error';
    } else if (type === 'warning') {
      bgClass = 'bg-warning text-dark';
      icon = 'fa-exclamation-triangle';
      title = 'Warning';
    }

    var toastId = 'toast-' + Date.now();
    var toastHtml = `
      <div id="${toastId}" class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
        <div class="toast-header ${bgClass} text-white">
          <span class="fas ${icon} me-2"></span>
          <strong class="me-auto">${title}</strong>
          <small>Just now</small>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          ${message}
        </div>
      </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    var toastElement = document.getElementById(toastId);
    var bsToast = new bootstrap.Toast(toastElement, { delay: 5000 });
    bsToast.show();

    toastElement.addEventListener('hidden.bs.toast', function() {
      toastElement.remove();
    });
  }

  // Toggle password visibility
  function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
      field.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      field.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }

  // Password strength checker
  function checkPasswordStrength(password) {
    let strength = 0;
    const feedback = document.getElementById('password-strength');
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
    const textColorClasses = ['text-danger', 'text-danger', 'text-warning', 'text-info', 'text-success'];
    
    if (password.length === 0) {
      feedback.innerHTML = '';
    } else {
      feedback.innerHTML = `
        <div class="progress" style="height: 5px;">
          <div class="progress-bar ${strengthColors[strength]}" style="width: ${(strength / 4) * 100}%"></div>
        </div>
        <small class="${textColorClasses[strength]}">Password strength: ${strengthLabels[strength]}</small>
      `;
    }
    
    checkPasswordMatch();
  }

  // Password match validation
  function checkPasswordMatch() {
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const passwordMatch = document.getElementById('password-match');
    const submitBtn = document.getElementById('change-password-btn');
    const oldPassword = document.getElementById('old-password').value;

    if (confirmPassword.length === 0) {
      passwordMatch.innerHTML = '';
      submitBtn.disabled = true;
      return;
    }

    if (newPassword === confirmPassword && newPassword.length >= 8 && oldPassword.length > 0) {
      passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check-circle"></i> Passwords match</small>';
      submitBtn.disabled = false;
    } else {
      passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</small>';
      submitBtn.disabled = true;
    }
  }

  // Change password form handler
  document.getElementById('change-password-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const oldPassword = document.getElementById('old-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const submitBtn = document.getElementById('change-password-btn');
    const btnText = document.getElementById('change-password-btn-text');
    const btnSpinner = document.getElementById('change-password-btn-spinner');
    
    // Validate password strength
    if (newPassword.length < 8) {
      showToast('error', 'Password must be at least 8 characters long.');
      return;
    }
    
    if (!newPassword.match(/[a-z]/) || !newPassword.match(/[A-Z]/)) {
      showToast('error', 'Password must include both uppercase and lowercase letters.');
      return;
    }
    
    if (!newPassword.match(/\d/)) {
      showToast('error', 'Password must include at least one number.');
      return;
    }
    
    if (!newPassword.match(/[^a-zA-Z\d]/)) {
      showToast('error', 'Password must include at least one special character.');
      return;
    }
    
    if (newPassword !== confirmPassword) {
      showToast('error', 'New password and confirm password do not match.');
      return;
    }
    
    if (oldPassword === newPassword) {
      showToast('error', 'New password must be different from current password.');
      return;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    btnText.textContent = 'Updating...';
    btnSpinner.classList.remove('d-none');
    
    const formData = new FormData();
    formData.append('action', 'change_password');
    formData.append('old_password', oldPassword);
    formData.append('new_password', newPassword);
    formData.append('csrf_token', csrfToken);
    
    fetch('<?php echo BASE_URL; ?>/api/user/change-password.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('success', data.message);
        document.getElementById('change-password-form').reset();
        document.getElementById('password-strength').innerHTML = '';
        document.getElementById('password-match').innerHTML = '';
        submitBtn.disabled = true;
      } else {
        showToast('error', data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error', 'An error occurred. Please try again.');
    })
    .finally(() => {
      // Reset button state
      submitBtn.disabled = false;
      btnText.textContent = 'Update Password';
      btnSpinner.classList.add('d-none');
    });
  });

  // Password strength checker on input
  document.getElementById('new-password').addEventListener('input', function() {
    checkPasswordStrength(this.value);
  });

  // Password match checker on input
  document.getElementById('confirm-password').addEventListener('input', function() {
    checkPasswordMatch();
  });

  // Password match checker on old password input
  document.getElementById('old-password').addEventListener('input', function() {
    checkPasswordMatch();
    verifyCurrentPassword(this.value);
  });

  // Verify current password in real-time
  let verifyPasswordTimeout;
  function verifyCurrentPassword(password) {
    const statusDiv = document.getElementById('old-password-status');
    
    if (password.length === 0) {
      statusDiv.innerHTML = '';
      return;
    }
    
    // Show checking state
    statusDiv.innerHTML = '<small class="text-muted"><i class="fas fa-spinner fa-spin"></i> Checking...</small>';
    
    // Log for debugging
    console.log('Verifying password, length:', password.length);
    console.log('Password value:', password);
    
    // Get CSRF token using getAttribute for better compatibility
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    console.log('CSRF Token:', csrfToken);
    
    // Get user_id from PHP variable and encode it if encryption is enabled
    const userId = <?php echo $currentUser['user_id'] ?? 'null'; ?>;
    const encodedUserId = typeof IdEncoder !== 'undefined' ? IdEncoder.encode(userId) : userId;
    console.log('User ID:', userId);
    console.log('Encoded User ID:', encodedUserId);
    
    // Debounce the API call
    clearTimeout(verifyPasswordTimeout);
    verifyPasswordTimeout = setTimeout(function() {
      const formData = new FormData();
      formData.append('action', 'verify_password');
      formData.append('password', password);
      formData.append('user_id', encodedUserId);
      formData.append('csrf_token', csrfToken);
      
      console.log('Sending request to API...');
      
      fetch('<?php echo BASE_URL; ?>/api/user/change-password.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        if (data.success) {
          statusDiv.innerHTML = '<small class="text-success"><i class="fas fa-check-circle"></i> Password is correct</small>';
        } else {
          statusDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle"></i> Password is incorrect</small>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Unable to verify</small>';
      });
    }, 500); // 500ms debounce
  }
  </script>

<!-- Profile Image Cropper Modal -->
<div class="modal fade" id="profileImageCropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-4 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white">
            <span class="fas fa-crop-alt me-2"></span>Crop Profile Image
          </h4>
          <p class="fs-10 mb-0 text-white">Adjust your profile image position and appearance</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h6 class="fw-bold mb-0 text-primary">
                    <span class="fas fa-image me-2"></span>Crop Area (1:1 ratio)
                  </h6>
                  <span class="badge bg-soft-info text-info">
                    <span class="fas fa-info-circle me-1"></span>Drag to move • Scroll to zoom
                  </span>
                </div>
                <div class="position-relative" style="border: 1px solid #dee2e6; border-radius: 12px; background: #fff; overflow: hidden;">
                  <canvas id="profileImageCropCanvas" width="1000" height="1000" style="width: 100%; height: auto; display: block; cursor: move;"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="zoomProfileImageOut()" title="Zoom Out">
                    <span class="fas fa-search-minus"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="zoomProfileImageIn()" title="Zoom In">
                    <span class="fas fa-search-plus"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="rotateProfileImage(-90)" title="Rotate Left">
                    <span class="fas fa-undo"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="rotateProfileImage(90)" title="Rotate Right">
                    <span class="fas fa-redo"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="flipProfileImageHorizontal()" title="Flip Horizontal">
                    <span class="fas fa-arrows-alt-h"></span>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="flipProfileImageVertical()" title="Flip Vertical">
                    <span class="fas fa-arrows-alt-v"></span>
                  </button>
                  <div class="vr mx-1"></div>
                  <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetProfileImageCrop()" title="Reset">
                    <span class="fas fa-sync-alt"></span>
                  </button>
                </div>
                <hr class="my-4">
                <div class="d-grid gap-2">
                  <button type="button" class="btn btn-primary" onclick="applyProfileImageCrop()">
                    <span class="fas fa-check me-2"></span>Apply & Save
                  </button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <span class="fas fa-times me-2"></span>Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <h6 class="fw-bold mb-3 text-primary">
                  <span class="fas fa-sliders-h me-2"></span>Controls
                </h6>
                <div class="mb-4">
                  <label class="form-label fw-semibold d-flex justify-content-between">
                    <span>Zoom</span>
                    <span id="profileImageZoomValue" class="text-primary">1.0x</span>
                  </label>
                  <input type="range" class="form-range" id="profileImageZoomSlider" min="0.1" max="3" step="0.1" value="1" oninput="updateProfileImageZoom(this.value)">
                </div>
                <div class="mb-4">
                  <label class="form-label fw-semibold d-flex justify-content-between">
                    <span>Rotation</span>
                    <span id="profileImageRotationValue" class="text-primary">0°</span>
                  </label>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-1" onclick="rotateProfileImage(-90)">
                      <span class="fas fa-undo"></span> -90°
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-1" onclick="rotateProfileImage(90)">
                      <span class="fas fa-redo"></span> +90°
                    </button>
                  </div>
                </div>
                <div class="mb-4">
                  <label class="form-label fw-semibold d-flex justify-content-between">
                    <span>Flip Horizontal</span>
                    <span id="profileImageFlipHValue" class="text-primary">Off</span>
                  </label>
                  <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="flipProfileImageHorizontal()">
                    <span class="fas fa-arrows-alt-h"></span> Toggle
                  </button>
                </div>
                <div class="mb-4">
                  <label class="form-label fw-semibold d-flex justify-content-between">
                    <span>Flip Vertical</span>
                    <span id="profileImageFlipVValue" class="text-primary">Off</span>
                  </label>
                  <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="flipProfileImageVertical()">
                    <span class="fas fa-arrows-alt-v"></span> Toggle
                  </button>
                </div>
                <hr class="my-4">
                <h6 class="fw-bold mb-3 text-primary">
                  <span class="fas fa-eye me-2"></span>Preview
                </h6>
                <div class="text-center mb-3">
                  <div style="width: 200px; height: 200px; border-radius: 50%; overflow: hidden; border: 3px solid #dee2e6; margin: 0 auto; background: #f8f9fa;">
                    <canvas id="profileImagePreviewCanvas" width="1000" height="1000" style="width: 100%; height: 100%; display: block;"></canvas>
                  </div>
                  <p class="text-muted small mt-2">Circular preview (as seen in profile)</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
</body>
</html>
