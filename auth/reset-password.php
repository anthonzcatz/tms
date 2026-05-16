<?php
session_start();
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
    header('Location: ' . BASE_URL . '/auth/forgot-password');
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
                      <div class="text-center text-md-start">
                        <h4 class="mb-0">Reset your password</h4>
                        <p class="mb-4">Resetting password for <strong><?php echo htmlspecialchars($username); ?></strong></p>
                      </div>
                      <div class="row justify-content-center">
                        <div class="col-sm-8 col-md">
                          <form class="mb-3" method="POST" action="reset-password-handler.php">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>" />
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
                            <div class="mb-3">
                              <label class="form-label" for="card-password">New Password</label>
                              <input class="form-control" id="card-password" name="password" type="password" required minlength="8" />
                              <small class="text-muted">Must be at least 8 characters</small>
                            </div>
                            <div class="mb-3">
                              <label class="form-label" for="card-confirm-password">Confirm Password</label>
                              <input class="form-control" id="card-confirm-password" name="confirm_password" type="password" required />
                            </div>
                            <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit">Reset password</button>
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

  </body>

</html>
