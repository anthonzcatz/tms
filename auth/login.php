<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/helpers/SecurityHelper.php';

$csrf_token = SecurityHelper::generateCSRFToken();

// Collect and clear session messages once
$sessionError   = $_SESSION['error']   ?? null; unset($_SESSION['error']);
$sessionSuccess = $_SESSION['success'] ?? null; unset($_SESSION['success']);

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
                        <div class="d-flex align-items-center justify-content-center mb-2">
                          <img class="me-2" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" alt="" width="40" />
                          <a class="link-light font-sans-serif fs-5 d-inline-block fw-bolder" href="<?php echo BASE_URL; ?>/admin"><?php echo $companyAbbreviation ?: $systemName; ?></a>
                        </div>
                        <?php if ($companyName): ?>
                        <div class="text-center fs-7 text-white opacity-75 mb-4"><?php echo $companyName; ?></div>
                        <?php endif; ?>
                        <p class="opacity-75 text-white"><?php echo $companyTagline; ?></p>
                      </div>
                    </div>
                    <div class="mt-3 mb-4 mt-md-4 mb-md-5" data-bs-theme="light">
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
                      
                      <?php if (isset($_GET['error'])): ?>
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

                      <?php if ($sessionError): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <span class="fas fa-exclamation-circle me-2"></span>
                          <?php echo htmlspecialchars($sessionError); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>

                      <?php if ($sessionSuccess): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                          <span class="fas fa-check-circle me-2"></span>
                          <?php echo htmlspecialchars($sessionSuccess); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>

                      <form method="POST" action="<?php echo BASE_URL; ?>/auth/login-handler.php" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                          <label class="form-label" for="card-username">Username</label>
                          <input class="form-control" id="card-username" name="username" type="text" required value="<?php echo htmlspecialchars($submittedUsername); ?>" />
                        </div>
                        <div class="mb-3">
                          <div class="d-flex justify-content-between">
                            <label class="form-label" for="card-password">Password</label>
                          </div>
                          <div class="input-group">
                            <input class="form-control" id="card-password" name="password" type="password" required />
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                              <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                          </div>
                        </div>
                        <div class="row flex-between-center">
                          <div class="col-auto">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="card-checkbox" checked="checked" />
                              <label class="form-check-label mb-0" for="card-checkbox">Remember me</label>
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
                      <div class="position-relative mt-4">
                        <hr />
                        <div class="divider-content-center">or log in with</div>
                      </div>
                      <div class="row g-2 mt-2">
                        <div class="col-sm-6"><a class="btn btn-outline-google-plus btn-sm d-block w-100" href="#"><span class="fab fa-google-plus-g me-2" data-fa-transform="grow-8"></span> google</a></div>
                        <div class="col-sm-6"><a class="btn btn-outline-facebook btn-sm d-block w-100" href="#"><span class="fab fa-facebook-square me-2" data-fa-transform="grow-8"></span> facebook</a></div>
                      </div>
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
    <?php if (isset($_SESSION['session_expired']) && $_SESSION['session_expired']): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show session expiration alert using Bootstrap modal
            const alertHtml = `
                <div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning bg-opacity-10">
                                <h5 class="modal-title text-warning">
                                    <span class="fas fa-exclamation-triangle me-2"></span>Session Expired
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session has expired due to inactivity. Please log in again to continue.</p>
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
            const modal = new bootstrap.Modal(document.getElementById('sessionExpiredModal'));
            modal.show();

            // Clear the session flag
            <?php unset($_SESSION['session_expired']); ?>
        });
    </script>
    <?php endif; ?>

    <?php if (isset($_SESSION['authentication_required']) && $_SESSION['authentication_required']): ?>
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

            // Clear the session flag
            <?php unset($_SESSION['authentication_required']); ?>
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