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

      <?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>

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

        $currentView = 'settings';
        include __DIR__ . '/_banner.php';

        // Additional variables from $userProfile
        $recoveryEmail = htmlspecialchars($up['recovery_email'] ?? '');
        $recoveryEmailVerifiedAt = $up['recovery_email_verified_at'];
        $isRecoveryEmailVerified = !empty($recoveryEmailVerifiedAt);
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
                      <form class="row g-3">
                        <div class="col-lg-6">
                          <label class="form-label" for="first-name">First Name</label>
                          <input class="form-control" id="first-name" type="text" value="<?php echo $firstName; ?>" />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="last-name">Last Name</label>
                          <input class="form-control" id="last-name" type="text" value="<?php echo $lastName; ?>" />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="email1">Email</label>
                          <input class="form-control" id="email1" type="email" value="<?php echo $email; ?>" readonly />
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="phone1">Phone</label>
                          <input class="form-control" id="phone1" type="text" value="<?php echo $phone; ?>" />
                        </div>
                        <div class="col-lg-12">
                          <label class="form-label" for="heading">Position</label>
                          <input class="form-control" id="heading" type="text" value="<?php echo $position; ?>" readonly />
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                          <button class="btn btn-primary" type="submit">Update</button>
                        </div>
                      </form>
                    </div>
                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="change-password" role="tabpanel" aria-labelledby="change-password-tab">
                      <form>
                        <div class="mb-3">
                          <label class="form-label" for="old-password">Old Password</label>
                          <input class="form-control" id="old-password" type="password" />
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="new-password">New Password</label>
                          <input class="form-control" id="new-password" type="password" />
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="confirm-password">Confirm Password</label>
                          <input class="form-control" id="confirm-password" type="password" />
                        </div>
                        <button class="btn btn-primary d-block w-100" type="submit">Update Password</button>
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
      var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    function sendVerificationEmail() {
      var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

      var formData = new FormData();
      formData.append('action', 'send_verification');
      formData.append('csrf_token', csrfToken);

      fetch('<?php echo BASE_URL; ?>/api/user/recovery-email.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
      });
    }
  });
  </script>
</body>
</html>
