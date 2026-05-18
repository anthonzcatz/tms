<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

// Validate token from email link
$token = $_GET['token'] ?? '';
$tokenValid = false;
$errorMessage = '';

if (empty($token)) {
    $errorMessage = 'Invalid reset link. Please request a new password reset.';
} else {
    // Check if token exists and is valid
    $resetToken = Database::fetch(
        "SELECT prt.*, ua.username, ua.email 
         FROM password_reset_tokens prt
         JOIN user_accounts ua ON prt.user_id = ua.user_id
         WHERE prt.token = :token 
         AND prt.used_at IS NULL 
         AND prt.expires_at > NOW()
         LIMIT 1",
        ['token' => $token]
    );
    
    if (!$resetToken) {
        $errorMessage = 'Invalid or expired reset link. Please request a new password reset.';
    } else {
        $tokenValid = true;
        $username = $resetToken['username'];
        $userEmail = $resetToken['email'];
    }
}

// If token is invalid, show error and redirect option
if (!$tokenValid) {
    $_SESSION['error'] = $errorMessage;
    session_write_close();
    header('Location: ' . BASE_URL . '/forgot-password');
    exit;
}
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
                      <p class="mb-0 mt-4 mt-md-5 fs-10 fw-semi-bold text-white opacity-75">Read our <a class="text-decoration-underline text-white" href="#!">terms</a> and <a class="text-decoration-underline text-white" href="#!">conditions </a></p>
                    </div>
                  </div>
                  <div class="col-md-7 d-flex flex-center">
                    <div class="p-4 p-md-5 flex-grow-1">
                      <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <?php echo htmlspecialchars($_SESSION['error']); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                      <?php endif; ?>
                      <div class="text-center text-md-start">
                        <h4 class="mb-0">Reset your password</h4>
                        <p class="mb-4">Resetting password for <strong><?php echo htmlspecialchars($username); ?></strong></p>
                      </div>
                      <div class="row justify-content-center">
                        <div class="col-sm-8 col-md">
                          <form class="mb-3" method="POST" action="<?php echo BASE_URL; ?>/reset-password-handler" id="resetPasswordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>" />
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
                            <div class="mb-3">
                              <label class="form-label" for="card-password">New Password</label>
                              <div class="input-group">
                                <input class="form-control" id="card-password" name="password" type="password" required minlength="8" />
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                  <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                              </div>
                              <small class="text-muted">Must be at least 8 characters</small>
                              <div id="passwordStrength" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                              <label class="form-label" for="card-confirm-password">Confirm Password</label>
                              <div class="input-group">
                                <input class="form-control" id="card-confirm-password" name="confirm_password" type="password" required />
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                  <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                                </button>
                              </div>
                              <div id="passwordMatch" class="mt-2"></div>
                            </div>
                            <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit" id="submitBtn" disabled>
                              <span id="btnText">Reset password</span>
                              <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                            </button>
                          </form><a class="fs-10 text-600" href="<?php echo LOGIN_URL; ?>">Back to login</a>
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetPasswordForm');
    const passwordInput = document.getElementById('card-password');
    const confirmPasswordInput = document.getElementById('card-confirm-password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordMatch = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const togglePassword = document.getElementById('togglePassword');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const toggleConfirmPasswordIcon = document.getElementById('toggleConfirmPasswordIcon');

    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePasswordIcon.classList.toggle('fa-eye');
        togglePasswordIcon.classList.toggle('fa-eye-slash');
    });

    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        toggleConfirmPasswordIcon.classList.toggle('fa-eye');
        toggleConfirmPasswordIcon.classList.toggle('fa-eye-slash');
    });

    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let feedback = '';

        if (password.length >= 8) strength += 1;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
        if (password.match(/\d/)) strength += 1;
        if (password.match(/[^a-zA-Z\d]/)) strength += 1;

        const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['text-danger', 'text-warning', 'text-info', 'text-primary', 'text-success'];

        if (password.length === 0) {
            feedback = '';
        } else {
            feedback = '<small class="' + strengthColors[strength] + '">Password strength: ' + strengthLabels[strength] + '</small>';
        }

        passwordStrength.innerHTML = feedback;
        checkPasswordMatch();
    });

    // Password match validation
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (confirmPassword.length === 0) {
            passwordMatch.innerHTML = '';
            submitBtn.disabled = true;
            return;
        }

        if (password === confirmPassword && password.length >= 8) {
            passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check-circle"></i> Passwords match</small>';
            submitBtn.disabled = false;
        } else {
            passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</small>';
            submitBtn.disabled = true;
        }
    }

    // Form submission loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate password match before submission
            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Resetting...';
            btnSpinner.classList.remove('d-none');
        });
    }
});
</script>

  </body>

</html>
