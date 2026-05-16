<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
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
                      <p class="text-white">Already have an account?<br><a class="text-decoration-underline link-light" href="<?php echo LOGIN_URL; ?>">Sign in</a></p>
                      <p class="mb-0 mt-4 mt-md-5 fs-10 fw-semi-bold text-white opacity-75">Read our <a class="text-decoration-underline text-white" href="#!">terms</a> and <a class="text-decoration-underline text-white" href="#!">conditions </a></p>
                    </div>
                  </div>
                  <div class="col-md-7 d-flex flex-center">
                    <div class="p-4 p-md-5 flex-grow-1">
                      <div class="row flex-between-center">
                        <div class="col-auto">
                          <h3>Register</h3>
                        </div>
                      </div>
                      <form class="mb-3" method="POST" action="register-handler.php">
                        <div class="mb-3">
                          <label class="form-label" for="card-name">Name</label>
                          <input class="form-control" id="card-name" name="name" type="text" required />
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="card-email">Email address</label>
                          <input class="form-control" id="card-email" name="email" type="email" required />
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="card-password">Password</label>
                          <input class="form-control" id="card-password" name="password" type="password" required />
                        </div>
                        <div class="mb-3">
                          <label class="form-label" for="card-confirm-password">Confirm Password</label>
                          <input class="form-control" id="card-confirm-password" name="confirm_password" type="password" required />
                        </div>
                        <div class="mb-3">
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="card-checkbox" required />
                            <label class="form-check-label mb-0" for="card-checkbox">I accept the <a href="#!">terms</a> and <a href="#!">privacy policy</a></label>
                          </div>
                        </div>
                        <div class="mb-3">
                          <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit">Sign up</button>
                        </div>
                      </form>
                      <div class="position-relative mt-4">
                        <hr />
                        <div class="divider-content-center">or sign up with</div>
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

  </body>

</html>
