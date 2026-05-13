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
          <div class="d-flex align-items-center"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/falcon.png" alt="" width="40" /><span class="font-sans-serif text-primary">falcon</span></div>
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
