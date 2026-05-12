<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
  <?php include 'includes/head.php'; ?>
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
        </script>
<?php include 'includes/sidebar.php'; ?>
        <div class="content">
<?php include 'includes/navbar.php'; ?>

     <!-- Content start========= -->

     <!-- Content end========= -->
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

  </body>

</html>
