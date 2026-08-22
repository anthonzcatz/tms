<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/helpers/SecurityHelper.php';

// Prevent login page from being cached by browsers or proxies
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$csrf_token = SecurityHelper::generateCSRFToken();

// Collect and clear session messages once
$sessionError      = $_SESSION['error']       ?? null; unset($_SESSION['error']);
$sessionSuccess    = $_SESSION['success']     ?? null; unset($_SESSION['success']);
$loginError        = $_SESSION['login_error'] ?? null; unset($_SESSION['login_error']);
$terminatedMessage = $_SESSION['session_terminated_message'] ?? null;
// Don't clear terminatedMessage yet — we need it to suppress the session_expired modal

// Preserve form input values on error
$submittedUsername = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_username']);
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

<?php include __DIR__ . '/auth-head.php'; ?>


  <body>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="container-fluid">
        <div class="row min-vh-100 flex-center g-0">
          <div class="col-lg-8 col-xxl-5 py-3 position-relative"><img class="bg-auth-circle-shape" src="resources/assets/img/icons/spot-illustrations/bg-shape.png" alt="" width="250"><img class="bg-auth-circle-shape-2" src="resources/assets/img/icons/spot-illustrations/shape-1.png" alt="" width="150">
            <div class="card overflow-hidden z-1">
              <div class="card-body p-0">
                <div class="row g-0 h-100">
                  <div class="col-md-5 text-center bg-card-gradient">
                    <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                      <div class="bg-holder bg-auth-card-shape" style="background-image:url(resources/assets/img/icons/spot-illustrations/half-circle.png);">
                      </div>
                      <!--/.bg-holder-->

                      <div class="z-1 position-relative">
                        <div class="d-flex flex-column align-items-center justify-content-center mb-2">
                          <img class="mb-2" style="max-height: 65px; width: auto; height: auto; object-fit: contain;" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" alt="" />
                          <a class="link-light font-sans-serif fs-5 d-inline-block fw-bolder" href="<?php echo BASE_URL; ?>/admin"><?php echo $companyAbbreviation ?: $systemName; ?></a>
                        </div>
                        <?php if ($companyName): ?>
                        <div class="text-center fs-7 text-white opacity-75 mb-4"><?php echo $companyName; ?></div>
                        <?php endif; ?>
                        <p class="opacity-75 text-white d-none d-md-block"><?php echo $companyTagline; ?></p>
                      </div>
                    </div>
                    <div class="mt-3 mb-4 mt-md-4 mb-md-5 d-none d-md-block" data-bs-theme="light">
                      <p class="text-white">Don't have an account?<br><a class="text-decoration-underline link-light" href="<?php echo REGISTER_URL; ?>">Get started!</a></p>
                      <p class="mb-0 mt-4 mt-md-5 fs-10 fw-semi-bold text-white opacity-75">Read our <a class="text-decoration-underline text-white" href="#!">terms</a> and <a class="text-decoration-underline text-white" href="#!">conditions </a></p>
                    </div>
                  </div>
                  <div class="col-md-7 d-flex flex-center">
                    <div class="p-4 p-md-5 flex-grow-1">
                      <div class="row flex-between-center">
                        <div class="col-auto">
                          <h3>Account Login</h3>
                        </div>
                      </div>
                      
                      <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                          <span class="fas fa-check-circle me-2"></span>
                          <?php 
                          $success = htmlspecialchars($_GET['success']);
                          if ($success === 'logout_success') {
                              echo 'You have been logged out successfully.';
                          } else {
                              echo 'Operation completed successfully.';
                          }
                          ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>
                      
                      <?php if (isset($_GET['error']) && !isset($_GET['success'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <span class="fas fa-exclamation-triangle me-2"></span>
                          <?php 
                          $error = htmlspecialchars($_GET['error']);
                          if ($error === 'session_expired') {
                              echo 'Your session has expired. Please log in again.';
                          } elseif ($error === 'session_invalid') {
                              echo 'Your session is invalid. Please log in again.';
                          } else {
                              echo 'Authentication required. Please log in to access this page.';
                          }
                          ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>

                      <?php if ($loginError === 'device_pending'): ?>
                        <?php
                        // DEBUG: Show device detection info
                        $debugIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $debugUa = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        $debugType = 'desktop';
                        if (preg_match('/Mobile|Android|iPhone|iPad/i', $debugUa)) {
                            $debugType = preg_match('/iPad/i', $debugUa) ? 'tablet' : 'mobile';
                        }
                        // Check existing devices for this IP
                        $existingDevices = Database::fetchAll(
                            "SELECT device_id, device_type, status FROM system_devices WHERE ip_address = :ip ORDER BY last_used_at DESC",
                            ['ip' => $debugIp]
                        );
                        ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <span class="fas fa-clock me-2"></span>
                          <strong>Device Pending Approval</strong><br>
                          Your device has been registered and is awaiting approval by an administrator. You will be able to log in once approved.
                          <!-- Debug info hidden
                          <hr class="my-2">
                          <small class="text-muted">Debug: IP=<?php echo $debugIp; ?>, Type=<?php echo $debugType; ?></small>
                          <?php if (!empty($existingDevices)): ?>
                            <hr class="my-1">
                            <small class="text-muted">Existing devices for this IP:<br>
                            <?php foreach ($existingDevices as $dev): ?>
                              • ID=<?php echo $dev['device_id']; ?>, Type=<?php echo $dev['device_type']; ?>, Status=<?php echo $dev['status']; ?><br>
                            <?php endforeach; ?>
                            </small>
                          <?php endif; ?>
                          <div class="mt-2">
                            <a href="<?php echo BASE_URL; ?>/auth/auto-approve-device.php?ip=<?php echo urlencode($debugIp); ?>&type=<?php echo urlencode($debugType); ?>" class="btn btn-sm btn-outline-warning">Emergency: Auto-Approve This Device</a>
                          </div>
                          -->
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php elseif ($loginError === 'device_blocked'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <span class="fas fa-ban me-2"></span>
                          <strong>Device Blocked</strong><br>
                          Your device has been blocked by an administrator. Please contact support if you believe this is a mistake.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php elseif ($loginError === 'session_create_failed'): ?>
                        <?php $errorDetails = $_SESSION['session_error_details'] ?? 'Unknown error'; unset($_SESSION['session_error_details']); ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <span class="fas fa-exclamation-circle me-2"></span>
                          <strong>Session Error</strong><br>
                          Unable to create session.<br>
                          <small class="text-muted">Error: <?php echo htmlspecialchars($errorDetails); ?></small>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php elseif ($sessionError): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <span class="fas fa-exclamation-circle me-2"></span>
                          <?php echo htmlspecialchars($sessionError); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>

                      <?php if ($terminatedMessage): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <span class="fas fa-info-circle me-2"></span>
                          <?php echo htmlspecialchars($terminatedMessage); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif ?>

                      <?php
                      $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                          || (($_SERVER['SERVER_PORT'] ?? null) == 443)
                          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                      ?>

                      <?php if (!$isHttps): ?>
                      <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <span class="fas fa-exclamation-triangle me-2"></span>
                        <strong>Connection not secure:</strong> You are using HTTP. Your username and password will be sent unencrypted over the network. Ask your administrator to enable HTTPS/SSL for this server.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                      <?php endif; ?>

                      <form method="POST" action="<?php echo BASE_URL; ?>/auth/login-handler.php" id="loginForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="browser_latitude" id="browser_latitude">
                        <input type="hidden" name="browser_longitude" id="browser_longitude">
                        <div class="mb-3">
                          <label class="form-label" for="card-username">Username</label>
                          <input class="form-control" id="card-username" name="username" type="text" autocomplete="username" autocapitalize="off" spellcheck="false" required value="<?php echo htmlspecialchars($submittedUsername); ?>" />
                        </div>
                        <div class="mb-3">
                          <div class="d-flex justify-content-between">
                            <label class="form-label" for="card-password">Password</label>
                          </div>
                          <div class="input-group">
                            <input class="form-control" id="card-password" name="password" type="password" autocomplete="current-password" minlength="8" spellcheck="false" required />
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                              <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                          </div>
                        </div>
                        <div class="row flex-between-center">
                          <div class="col-auto">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="card-remember-me" name="remember_me" value="1" />
                              <label class="form-check-label mb-0" for="card-remember-me">Remember me for 30 days</label>
                            </div>
                            <div class="form-text fs-10 text-muted mt-1">
                              <span class="fas fa-shield-alt me-1"></span>Do not use on shared devices.
                            </div>
                          </div>
                          <div class="col-auto"><a class="fs-10" href="<?php echo FORGOT_PASSWORD_URL; ?>">Forgot Password?</a></div>
                        </div>
                        <div class="mb-3">
                          <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit" id="submitBtn">
                            <span id="btnText">Log in</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                          </button>
                        </div>
                      </form>
                      <!-- Social login buttons hidden
                      <div class="position-relative mt-4">
                        <hr />
                        <div class="divider-content-center">or log in with</div>
                      </div>
                      <div class="row g-2 mt-2">
                        <div class="col-sm-6"><a class="btn btn-outline-google-plus btn-sm d-block w-100" href="#"><span class="fab fa-google-plus-g me-2" data-fa-transform="grow-8"></span> google</a></div>
                        <div class="col-sm-6"><a class="btn btn-outline-facebook btn-sm d-block w-100" href="#"><span class="fab fa-facebook-square me-2" data-fa-transform="grow-8"></span> facebook</a></div>
                      </div>
                      -->
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

<?php include __DIR__ . '/auth-scripts.php'; ?>
    <?php
    // Check session flags and set them to be cleared after showing modal
    $showSessionExpiredModal = isset($_SESSION['session_expired']) && $_SESSION['session_expired'];
    $showSessionInvalidModal = isset($_SESSION['session_invalid']) && $_SESSION['session_invalid'];
    $showAuthRequiredModal = isset($_SESSION['authentication_required']) && $_SESSION['authentication_required'];

    // Suppress auth_required modal on explicit logout or when a login_error was just handled
    if ((isset($_GET['success']) && $_GET['success'] === 'logout_success') || $loginError !== null) {
        $showAuthRequiredModal = false;
    }

    // Clear the session flags after reading them
    if ($showSessionExpiredModal) {
        unset($_SESSION['session_expired']);
    }
    if ($showSessionInvalidModal) {
        unset($_SESSION['session_invalid']);
    }
    if ($showAuthRequiredModal) {
        unset($_SESSION['authentication_required']);
    }
    ?>

    <?php if ($showSessionExpiredModal && !$terminatedMessage): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertHtml = `
                <div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning bg-opacity-10 border-0 pb-0">
                                <h5 class="modal-title text-warning">
                                    <span class="fas fa-exclamation-triangle me-2"></span>Session Expired
                                </h5>
                            </div>
                            <div class="modal-body pt-2">
                                <p class="mb-0">Your session has expired due to inactivity. Please log in again to continue.</p>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                    <span class="fas fa-check me-1"></span>OK, Log In Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            new bootstrap.Modal(document.getElementById('sessionExpiredModal')).show();
        });
    </script>
    <?php endif; ?>

    <?php
    // Clear terminatedMessage after modal check so it doesn't persist
    if ($terminatedMessage !== null) {
        unset($_SESSION['session_terminated_message']);
    }
    ?>

    <?php if ($showSessionInvalidModal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show session invalid alert using Bootstrap modal
            const alertHtml = `
                <div class="modal fade" id="sessionInvalidModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger bg-opacity-10">
                                <h5 class="modal-title text-danger">
                                    <span class="fas fa-times-circle me-2"></span>Session Invalid
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session is invalid. This may be due to a security issue or session corruption. Please log in again.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="window.location.href = window.location.pathname">
                                    <span class="fas fa-check me-2"></span>OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            const modal = new bootstrap.Modal(document.getElementById('sessionInvalidModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>

    <?php if ($showAuthRequiredModal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show authentication required alert using Bootstrap modal
            const alertHtml = `
                <div class="modal fade" id="authRequiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-info bg-opacity-10">
                                <h5 class="modal-title text-info">
                                    <span class="fas fa-info-circle me-2"></span>Authentication Required
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>You need to log in to access this page. Please enter your credentials below.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                    <span class="fas fa-check me-2"></span>OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            const modal = new bootstrap.Modal(document.getElementById('authRequiredModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const passwordInput = document.getElementById('card-password');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const togglePassword = document.getElementById('togglePassword');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');

    // Request geolocation on page load
    if (navigator.geolocation) {
        console.log('Requesting browser geolocation...');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const coords = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                };
                console.log('Geolocation success:', coords);
                document.getElementById('browser_latitude').value = coords.latitude;
                document.getElementById('browser_longitude').value = coords.longitude;
            },
            (error) => {
                console.log('Geolocation error:', error.message, 'Code:', error.code);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        console.log('Geolocation not supported by browser');
    }

    // Toggle password visibility
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePasswordIcon.classList.toggle('fa-eye');
            togglePasswordIcon.classList.toggle('fa-eye-slash');
        });
    }

    // Form submission loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate inputs
            const username = document.getElementById('card-username').value;
            const password = passwordInput.value;

            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Logging in...';
            btnSpinner.classList.remove('d-none');
        });
    }
});
</script>

  </body>

</html>