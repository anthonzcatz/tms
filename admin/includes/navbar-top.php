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
  <a class="navbar-brand me-1 me-sm-3" href="<?php
    $defaultDashboard = Auth::user()['default_dashboard'] ?? '/admin/dashboard/analytics';
    // Fix invalid default_dashboard values
    if ($defaultDashboard === '/admin' || $defaultDashboard === '/admin/') {
        $defaultDashboard = '/admin/dashboard/analytics';
    }
    echo BASE_URL . $defaultDashboard;
  ?>">
    <div class="d-flex align-items-center"><img class="me-2 navbar-brand-logo" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" alt="" width="40" /><span class="font-sans-serif text-primary d-none d-sm-block"><?php echo $systemName; ?></span></div>
  </a>
  <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
    <ul class="navbar-nav" data-top-nav-dropdowns="data-top-nav-dropdowns">
      <?php echo $navMenu ?: '<li class="nav-item"><a class="nav-link" href="#">Menu</a></li>'; ?>
    </ul>
  </div>
  <ul class="navbar-nav align-items-center d-none d-lg-block">
    <?php include __DIR__ . '/navbar-components/search-box.php'; ?>
  </ul>
  <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
    <?php include __DIR__ . '/navbar-components/theme-switcher.php'; ?>
    <!-- Cart icon hidden temporarily -->
    <!-- <li class="nav-item d-none d-sm-block">
      <a class="nav-link px-0 notification-indicator notification-indicator-warning notification-indicator-fill fa-icon-wait" href="../app/e-commerce/shopping-cart.html"><span class="fas fa-shopping-cart" data-fa-transform="shrink-7" style="font-size: 33px;"></span><span class="notification-indicator-number">1</span></a>
    </li> -->
    <?php include __DIR__ . '/navbar-components/notification-dropdown.php'; ?>
    <?php $dropdownId = 'navbarDropdownMenuTop'; include __DIR__ . '/navbar-components/nine-dots-dropdown.php'; ?>
    <?php $dropdownId = 'navbarDropdownUserTop'; include __DIR__ . '/navbar-components/user-dropdown.php'; ?>
  </ul>
</nav>

<?php include __DIR__ . '/navbar-components/logout-modal.php'; ?>
