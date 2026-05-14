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
    </div>
  </li>
</ul>
<ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
  <li class="nav-item ps-2 pe-0">
    <div class="dropdown theme-control-dropdown"><a class="nav-link d-flex align-items-center dropdown-toggle fa-icon-wait fs-9 pe-1 py-0" href="#" role="button" id="themeSwitchDropdownTop" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="fas fa-sun fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="light"></span><span class="fas fa-moon fs-7" data-fa-transform="shrink-3" data-theme-dropdown-toggle-icon="dark"></span><span class="fas fa-adjust fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="auto"></span></a>
      <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="themeSwitchDropdownTop">
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
    <a class="nav-link notification-indicator notification-indicator-primary px-0 fa-icon-wait" id="navbarDropdownNotificationTopIcons" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="fas fa-bell" data-fa-transform="shrink-6" style="font-size: 33px;"></span></a>
    <div class="dropdown-menu dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg" aria-labelledby="navbarDropdownNotificationTopIcons">
      <div class="card card-notification shadow-none">
        <div class="card-header">
          <div class="row justify-content-between align-items-center">
            <div class="col-auto">
              <h6 class="card-header-title mb-0">Notifications</h6>
            </div>
            <div class="col-auto ps-0 ps-sm-3"><a class="card-link fw-normal" href="#">Mark all as read</a></div>
          </div>
        </div>
        <div class="scrollbar-overlay" style="max-height:19rem"></div>
        <div class="card-footer text-center border-top"><a class="card-link d-block" href="#">View all</a></div>
      </div>
    </div>
  </li>
  <li class="nav-item dropdown"><a class="nav-link pe-0 ps-2" id="navbarDropdownUserTopIcons" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <div class="avatar avatar-xl">
        <?php if ($profileImage): ?>
          <img class="rounded-circle" src="<?php echo BASE_URL . $profileImage; ?>" alt="User Avatar" style="width: 40px; height: 40px; object-fit: cover;" />
        <?php else: ?>
          <div class="avatar-name rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
            <?php echo $initials; ?>
          </div>
        <?php endif; ?>
      </div>
    </a>
    <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUserTopIcons">
      <div class="bg-white dark__bg-1000 rounded-2 py-2">
        <div class="dropdown-item-text">
          <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user']['fullname'] ?? ''); ?></div>
          <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['user']['role_name'] ?? ''); ?></div>
        </div>
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
