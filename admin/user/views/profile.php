<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php require_once dirname(dirname(__DIR__)) . '/includes/head.php'; ?>
  <link href="<?php echo BASE_URL; ?>/resources/vendors/glightbox/glightbox.min.css" rel="stylesheet">
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
        $currentView = 'profile';
        include __DIR__ . '/_banner.php';
        ?>

          <!-- Content Rows -->
          <div class="row g-0">
            <div class="col-lg-8 pe-lg-2">
              <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                  <h5 class="mb-0">Intro</h5>
                </div>
                <div class="card-body text-justify">
                  <p class="mb-0 text-1000">Dedicated, passionate, and accomplished Full Stack Developer with 9+ years of progressive experience working as an Independent Contractor for Google and developing and growing my educational social network that helps others learn programming, web design, game development, networking.</p>
                  <div class="collapse show" id="profile-intro">
                    <p class="mt-3 text-1000">I've acquired a wide depth of knowledge and expertise in using my technical skills in programming, computer science, software development, and mobile app development to developing solutions to help organizations increase productivity, and accelerate business performance.</p>
                    <p class="text-1000">It's great that we live in an age where we can share so much with technology but I'm but I'm ready for the next phase of my career, with a healthy balance between the virtual world and a workplace where I help others face-to-face.</p>
                    <p class="text-1000 mb-0">There's always something new to learn, especially in IT-related fields. People like working with me because I can explain technology to everyone, from staff to executives who need me to tie together the details and the big picture. I can also implement the technologies that successful projects need.</p>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary p-0 border-top">
                  <button class="btn btn-link d-block w-100 btn-intro-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#profile-intro" aria-expanded="true" aria-controls="profile-intro">Show <span class="less">less<span class="fas fa-chevron-up ms-2 fs-11"></span></span><span class="full">full<span class="fas fa-chevron-down ms-2 fs-11"></span></span></button>
                </div>
              </div>
              <div class="card mb-3">
                <div class="card-header bg-body-tertiary d-flex justify-content-between">
                  <h5 class="mb-0">Activity log</h5><a class="font-sans-serif" href="#!">All logs</a>
                </div>
                <div class="card-body fs-10 p-0">
                  <a class="border-bottom-0 notification rounded-0 border-x-0 border border-300" href="#!">
                    <div class="notification-avatar">
                      <div class="avatar avatar-xl me-3">
                        <div class="avatar-emoji rounded-circle"><span role="img" aria-label="Emoji">🎁</span></div>
                      </div>
                    </div>
                    <div class="notification-body">
                      <p class="mb-1"><strong>Jennifer Kent</strong> Congratulated <strong>Anthony Hopkins</strong></p>
                      <span class="notification-time">November 13, 5:00 Am</span>
                    </div>
                  </a>
                  <a class="border-bottom-0 notification rounded-0 border-x-0 border border-300" href="#!">
                    <div class="notification-avatar">
                      <div class="avatar avatar-xl me-3">
                        <div class="avatar-emoji rounded-circle"><span role="img" aria-label="Emoji">🏷️</span></div>
                      </div>
                    </div>
                    <div class="notification-body">
                      <p class="mb-1"><strong>California Institute of Technology</strong> tagged <strong>Anthony Hopkins</strong> in a post.</p>
                      <span class="notification-time">November 8, 5:00 PM</span>
                    </div>
                  </a>
                  <a class="border-bottom-0 notification rounded-0 border-x-0 border border-300" href="#!">
                    <div class="notification-avatar">
                      <div class="avatar avatar-xl me-3">
                        <div class="avatar-emoji rounded-circle"><span role="img" aria-label="Emoji">📋️</span></div>
                      </div>
                    </div>
                    <div class="notification-body">
                      <p class="mb-1"><strong>Anthony Hopkins</strong> joined <strong>Victory day cultural Program</strong> with <strong>Tony Stark</strong></p>
                      <span class="notification-time">November 01, 11:30 AM</span>
                    </div>
                  </a>
                  <a class="notification border-x-0 border-bottom-0 border-300 rounded-top-0" href="#!">
                    <div class="notification-avatar">
                      <div class="avatar avatar-xl me-3">
                        <div class="avatar-emoji rounded-circle"><span role="img" aria-label="Emoji">📅️</span></div>
                      </div>
                    </div>
                    <div class="notification-body">
                      <p class="mb-1"><strong>Massachusetts Institute of Technology</strong> invited <strong>Anthony Hopkins</strong> to an event</p>
                      <span class="notification-time">October 28, 12:00 PM</span>
                    </div>
                  </a>
                </div>
              </div>
              <div class="card mb-3 mb-lg-0">
                <div class="card-header bg-body-tertiary">
                  <h5 class="mb-0">Photos</h5>
                </div>
                <div class="card-body overflow-hidden">
                  <div class="row g-0">
                    <div class="col-6 p-1"><a class="glightbox" href="<?php echo BASE_URL; ?>/resources/assets/img/generic/4.jpg" data-gallery="gallery1" data-glightbox="data-glightbox"><img class="img-fluid rounded" src="<?php echo BASE_URL; ?>/resources/assets/img/generic/4.jpg" alt="..." /></a></div>
                    <div class="col-6 p-1"><a class="glightbox" href="<?php echo BASE_URL; ?>/resources/assets/img/generic/5.jpg" data-gallery="gallery1" data-glightbox="data-glightbox"><img class="img-fluid rounded" src="<?php echo BASE_URL; ?>/resources/assets/img/generic/5.jpg" alt="..." /></a></div>
                    <div class="col-4 p-1"><a class="glightbox" href="<?php echo BASE_URL; ?>/resources/assets/img/gallery/4.jpg" data-gallery="gallery1" data-glightbox="data-glightbox"><img class="img-fluid rounded" src="<?php echo BASE_URL; ?>/resources/assets/img/gallery/4.jpg" alt="..." /></a></div>
                    <div class="col-4 p-1"><a class="glightbox" href="<?php echo BASE_URL; ?>/resources/assets/img/gallery/5.jpg" data-gallery="gallery1" data-glightbox="data-glightbox"><img class="img-fluid rounded" src="<?php echo BASE_URL; ?>/resources/assets/img/gallery/5.jpg" alt="..." /></a></div>
                    <div class="col-4 p-1"><a class="glightbox" href="<?php echo BASE_URL; ?>/resources/assets/img/gallery/3.jpg" data-gallery="gallery1" data-glightbox="data-glightbox"><img class="img-fluid rounded" src="<?php echo BASE_URL; ?>/resources/assets/img/gallery/3.jpg" alt="..." /></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 ps-lg-2">
              <div class="sticky-sidebar">
                <!-- Account Information Card -->
                <div class="card mb-3">
                  <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Account Information</h5>
                  </div>
                  <div class="card-body fs-10">
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
                    <div class="alert alert-warning py-2 mb-3">
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
                <div class="card mb-3">
                  <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Education</h5>
                  </div>
                  <div class="card-body fs-10">
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
                <div class="card mb-3 mb-lg-0">
                  <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Events</h5>
                  </div>
                  <div class="card-body fs-10">
                    <div class="d-flex btn-reveal-trigger">
                      <div class="calendar"><span class="calendar-month">Feb</span><span class="calendar-day">21</span></div>
                      <div class="flex-1 position-relative ps-3">
                        <h6 class="fs-9 mb-0"><a href="#!">Newmarket Nights</a></h6>
                        <p class="mb-1">Organized by <a href="#!" class="text-700">University of Oxford</a></p>
                        <p class="text-1000 mb-0">Time: 6:00AM</p>
                        <p class="text-1000 mb-0">Duration: 6:00AM - 5:00PM</p>Place: Cambridge Boat Club, Cambridge
                        <div class="border-bottom border-dashed my-3"></div>
                      </div>
                    </div>
                    <div class="d-flex btn-reveal-trigger">
                      <div class="calendar"><span class="calendar-month">Dec</span><span class="calendar-day">31</span></div>
                      <div class="flex-1 position-relative ps-3">
                        <h6 class="fs-9 mb-0"><a href="#!">31st Night Celebration</a></h6>
                        <p class="mb-1">Organized by <a href="#!" class="text-700">Chamber Music Society</a></p>
                        <p class="text-1000 mb-0">Time: 11:00PM</p>
                        <p class="text-1000 mb-0">280 people interested</p>Place: Tavern on the Greend, New York
                        <div class="border-bottom border-dashed my-3"></div>
                      </div>
                    </div>
                    <div class="d-flex btn-reveal-trigger">
                      <div class="calendar"><span class="calendar-month">Dec</span><span class="calendar-day">16</span></div>
                      <div class="flex-1 position-relative ps-3">
                        <h6 class="fs-9 mb-0"><a href="#!">Folk Festival</a></h6>
                        <p class="mb-1">Organized by <a href="#!" class="text-700">Harvard University</a></p>
                        <p class="text-1000 mb-0">Time: 9:00AM</p>
                        <p class="text-1000 mb-0">Location: Cambridge Masonic Hall Association</p>Place: Porter Square, North Cambridge
                      </div>
                    </div>
                  </div>
                  <div class="card-footer bg-body-tertiary p-0 border-top"><a class="btn btn-link d-block w-100" href="#!">All Events<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
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
  <script src="<?php echo BASE_URL; ?>/resources/vendors/glightbox/glightbox.min.js"></script>
  <script src="<?php echo BASE_URL; ?>/admin/user/assets/js/user.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/user.js'); ?>"></script>

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
