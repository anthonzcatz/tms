<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/Auth.php';
require_once __DIR__ . '/helpers/SidebarHelper.php';

if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', 'vertical');
}

require_once __DIR__ . '/includes/head.php';
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
  <body>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="container" data-layout="container">
        <script>
          var isFluid = JSON.parse(localStorage.getItem('isFluid'));
          if (isFluid || !localStorage.getItem('isFluid')) {
            var container = document.querySelector('[data-layout]');
            container.classList.remove('container');
            container.classList.add('container-fluid');
            localStorage.setItem('isFluid', 'true');
          }
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include 'includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include 'includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include 'includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
<?php include 'includes/navbar.php'; ?>

     <!-- Content start========= -->

     <!-- Content end========= -->
    <?php endif; ?>
    </div>
    </div>
    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="../public/vendors/popper/popper.min.js"></script>
    <script src="../public/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="../public/vendors/anchorjs/anchor.min.js"></script>
    <script src="../public/vendors/is/is.min.js"></script>
    <script src="../public/vendors/echarts/echarts.min.js"></script>
    <script src="../public/vendors/fontawesome/all.min.js"></script>
    <script src="../public/vendors/lodash/lodash.min.js"></script>
    <script src="../public/vendors/list.js/list.min.js"></script>
    <script src="../public/assets/js/theme.js"></script>
  <?php include 'includes/body-top.php'; ?>
  </body>

</html>
