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
  <?php $dropdownId = 'navbarDropdownMenuTopIcons'; include __DIR__ . '/navbar-components/nine-dots-dropdown.php'; ?>
    <?php $dropdownId = 'navbarDropdownUserTopIcons'; include __DIR__ . '/navbar-components/user-dropdown.php'; ?>
</ul>
