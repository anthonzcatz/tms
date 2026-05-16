<?php
require_once __DIR__ . '/navbar-context.php';
$navMenu = SidebarHelper::renderTopNav();
?>
<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg">
  <div class="w-100">
    <div class="d-flex flex-between-center">
      <div class="d-flex align-items-center">
        <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarDoubleTop" aria-controls="navbarDoubleTop" aria-expanded="false" aria-label="Toggle Navigation"><span class="navbar-toggle-icon"><span class="toggle-line"></span></span></button>
        <a class="navbar-brand me-1 me-sm-3" href="<?php echo BASE_URL; ?>/admin">
          <div class="d-flex align-items-center">
            <img class="me-2" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" alt="" width="40" />
            <span class="font-sans-serif text-primary"><?php echo $systemName; ?></span>
          </div>
        </a>
      </div>
      <div class="d-flex align-items-center">
        <?php include __DIR__ . '/navbar-top-icons.php'; ?>
      </div>
    </div>
    <hr class="my-2 d-none d-lg-block" />
    <div class="collapse navbar-collapse scrollbar py-lg-2" id="navbarDoubleTop">
      <ul class="navbar-nav" data-top-nav-dropdowns="data-top-nav-dropdowns">
        <?php echo $navMenu ?: '<li class="nav-item"><a class="nav-link" href="#">Menu</a></li>'; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog mt-6" role="document">
    <div class="modal-content border-0">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="logoutModalLabel">Confirm Logout</h4>
          <p class="fs-10 mb-0 text-white">Are you sure you want to logout?</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body py-4 px-5">
        <p class="text-600">You will be logged out of your account and redirected to the login page.</p>
      </div>
      <div class="modal-footer bg-body-tertiary py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>
