<?php
require_once __DIR__ . '/navbar-context.php';

$navMenu = SidebarHelper::renderTopNav();
$navbarPosition = defined('NAVBAR_POSITION') ? NAVBAR_POSITION : 'vertical';
$navbarDataAttrs = '';
if ($navbarPosition === 'combo') {
    $navbarDataAttrs = ' data-move-target="#navbarVerticalNav" data-navbar-top="combo"';
}
?>
<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg"<?php echo $navbarDataAttrs; ?>>
  <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="<?php echo $navbarPosition === 'combo' ? '#navbarVerticalCollapse' : '#navbarStandard'; ?>" aria-controls="<?php echo $navbarPosition === 'combo' ? 'navbarVerticalCollapse' : 'navbarStandard'; ?>" aria-expanded="false" aria-label="Toggle Navigation"><span class="navbar-toggle-icon"><span class="toggle-line"></span></span></button>
  <a class="navbar-brand me-1 me-sm-3" href="<?php echo BASE_URL; ?>/admin">
    <div class="d-flex align-items-center"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/falcon.png" alt="" width="40" /><span class="font-sans-serif text-primary">falcon</span></div>
  </a>
  <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
    <ul class="navbar-nav" data-top-nav-dropdowns="data-top-nav-dropdowns">
      <?php echo $navMenu ?: '<li class="nav-item"><a class="nav-link" href="#">Menu</a></li>'; ?>
    </ul>
  </div>
  <ul class="navbar-nav align-items-center d-none d-lg-block">
    <li class="nav-item">
      <div class="search-box" data-list='{"valueNames":["title"]}'>
        <form class="position-relative" data-bs-toggle="search" data-bs-display="static">
          <input class="form-control search-input fuzzy-search" type="search" placeholder="Search..." aria-label="Search" />
          <span class="fas fa-search search-box-icon"></span>
        </form>
        <div class="btn-close-falcon-container position-absolute end-0 top-50 translate-middle shadow-none" data-bs-dismiss="search">
          <button class="btn btn-link btn-close-falcon p-0" aria-label="Close"></button>
        </div>
        <div class="dropdown-menu border font-base start-0 mt-2 py-0 overflow-hidden w-100">
          <div class="scrollbar list py-3" style="max-height: 24rem;">
            <h6 class="dropdown-header fw-medium text-uppercase px-x1 fs-11 pt-0 pb-2">Recently Browsed</h6>
          </div>
        </div>
      </div>
    </li>
  </ul>
  <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
    <li class="nav-item ps-2 pe-0">
      <div class="dropdown theme-control-dropdown"><a class="nav-link d-flex align-items-center dropdown-toggle fa-icon-wait fs-9 pe-1 py-0" href="#" role="button" id="themeSwitchDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="fas fa-sun fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="light"></span><span class="fas fa-moon fs-7" data-fa-transform="shrink-3" data-theme-dropdown-toggle-icon="dark"></span><span class="fas fa-adjust fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="auto"></span></a>
        <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="themeSwitchDropdown">
          <div class="bg-white dark__bg-1000 rounded-2 py-2">
            <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="light" data-theme-control="theme"><span class="fas fa-sun"></span>Light<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
            <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="dark" data-theme-control="theme"><span class="fas fa-moon"></span>Dark<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
            <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="auto" data-theme-control="theme"><span class="fas fa-adjust"></span>Auto<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
          </div>
        </div>
      </div>
    </li>
    <li class="nav-item d-none d-sm-block">
      <a class="nav-link px-0 notification-indicator notification-indicator-warning notification-indicator-fill fa-icon-wait" href="#"><span class="fas fa-shopping-cart" data-fa-transform="shrink-7" style="font-size: 33px;"></span><span class="notification-indicator-number">1</span></a>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link notification-indicator notification-indicator-primary px-0 fa-icon-wait" id="navbarDropdownNotificationTop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="fas fa-bell" data-fa-transform="shrink-6" style="font-size: 33px;"></span></a>
      <div class="dropdown-menu dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg" aria-labelledby="navbarDropdownNotificationTop">
        <div class="card card-notification shadow-none">
          <div class="card-header">
            <div class="row justify-content-between align-items-center">
              <div class="col-auto">
                <h6 class="card-header-title mb-0">Notifications</h6>
              </div>
              <div class="col-auto ps-0 ps-sm-3"><a class="card-link fw-normal" href="#">Mark all as read</a></div>
            </div>
          </div>
          <div class="scrollbar-overlay" style="max-height:19rem">
            <div class="list-group list-group-flush fw-normal fs-10">
              <div class="list-group-title border-bottom">NEW</div>
            </div>
          </div>
          <div class="card-footer text-center border-top"><a class="card-link d-block" href="#">View all</a></div>
        </div>
      </div>
    </li>
    <li class="nav-item dropdown px-1">
      <a class="nav-link fa-icon-wait nine-dots p-1" id="navbarDropdownMenuTop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="43" viewBox="0 0 16 16" fill="none">
          <circle cx="2" cy="2" r="2" fill="#6C6E71"></circle>
          <circle cx="2" cy="8" r="2" fill="#6C6E71"></circle>
          <circle cx="2" cy="14" r="2" fill="#6C6E71"></circle>
          <circle cx="8" cy="8" r="2" fill="#6C6E71"></circle>
          <circle cx="8" cy="14" r="2" fill="#6C6E71"></circle>
          <circle cx="14" cy="8" r="2" fill="#6C6E71"></circle>
          <circle cx="14" cy="14" r="2" fill="#6C6E71"></circle>
          <circle cx="8" cy="2" r="2" fill="#6C6E71"></circle>
          <circle cx="14" cy="2" r="2" fill="#6C6E71"></circle>
        </svg></a>
      <div class="dropdown-menu dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-caret-bg" aria-labelledby="navbarDropdownMenuTop">
        <div class="card shadow-none">
          <div class="scrollbar-overlay nine-dots-dropdown">
            <div class="card-body px-3">
              <div class="row text-center gx-0 gy-0">
                <div class="col-4"><a class="d-block hover-bg-200 px-2 py-3 rounded-3 text-center text-decoration-none" href="#"><div class="avatar avatar-2xl"></div><p class="mb-0 fw-medium text-800 text-truncate fs-11">Links</p></a></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </li>
    <li class="nav-item dropdown"><a class="nav-link pe-0 ps-2" id="navbarDropdownUserTop" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <div class="avatar avatar-xl">
          <?php if ($profileImage): ?>
            <img class="rounded-circle" src="<?php echo BASE_URL . $profileImage; ?>" alt="User Avatar" />
          <?php else: ?>
            <div class="avatar-name rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold">
              <?php echo $initials; ?>
            </div>
          <?php endif; ?>
        </div>
      </a>
      <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUserTop">
        <div class="bg-white dark__bg-1000 rounded-2 py-2">
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/user/profile">Profile &amp; account</a>
          <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/permissions">Permission Management</a>
          <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/users">User Management</a>
          <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/role-dashboards">Role Dashboards</a>
          <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a>
        </div>
      </div>
    </li>
  </ul>
</nav>
