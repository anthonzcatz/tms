  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token for API Security -->
    <?php
    if (!isset($_SESSION)) {
        session_start();
    }
    require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
    ?>
    <meta name="csrf-token" content="<?php echo SecurityHelper::generateCSRFToken(); ?>">
    <meta name="base-url" content="<?php echo BASE_URL; ?>">

    <!-- Global JavaScript variables -->
    <script>
      window.BASE_URL = '<?php echo BASE_URL; ?>';
    </script>

    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title>Falcon | Dashboard &amp; Web App Template</title>


    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon.ico">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="<?php echo BASE_URL; ?>/resources/assets/img/favicons/mstile-150x160.png">
    <meta name="theme-color" content="#ffffff">
    <script src="<?php echo BASE_URL; ?>/resources/assets/js/config.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/simplebar/simplebar.min.js"></script>


    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <link href="<?php echo BASE_URL; ?>/resources/vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/vendors/flatpickr/flatpickr.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/user.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/resources/assets/css/user.css'); ?>" rel="stylesheet" id="user-style-default">
    
    <!-- Fix navbar positioning variables -->
    <style>
      :root {
        --falcon-top-nav-height: 4.3125rem;
      }
      @media (min-width: 992px) {
        :root.double-top-nav-layout {
          --falcon-top-nav-height: 8.688rem;
        }
      }
      
      /* Ensure navbar-top is positioned correctly */
      .navbar-top {
        position: sticky;
        top: 0;
        z-index: 1020;
        min-height: var(--falcon-top-nav-height);
      }
      
      /* Fix content positioning */
      .navbar-top + .content {
        min-height: calc(100vh - var(--falcon-top-nav-height));
      }
    </style>
    
    <?php if (defined('NAVBAR_POSITION')): ?>
    <script>
      // Only set navbarPosition from PHP if not already set by user in localStorage
      if (!localStorage.getItem('navbarPosition')) {
        localStorage.setItem('navbarPosition', '<?php echo NAVBAR_POSITION; ?>');
      }
    </script>
    <?php endif; ?>
    <script>
      var isRTL = JSON.parse(localStorage.getItem('isRTL'));
      if (isRTL) {
        var linkDefault = document.getElementById('style-default');
        var userLinkDefault = document.getElementById('user-style-default');
        linkDefault.setAttribute('disabled', true);
        userLinkDefault.setAttribute('disabled', true);
        document.querySelector('html').setAttribute('dir', 'rtl');
      } else {
        var linkRTL = document.getElementById('style-rtl');
        var userLinkRTL = document.getElementById('user-style-rtl');
        linkRTL.setAttribute('disabled', true);
        userLinkRTL.setAttribute('disabled', true);
      }
      
      // Add double-top-nav-layout class if needed
      <?php if (defined('NAVBAR_POSITION') && NAVBAR_POSITION === 'double-top'): ?>
      document.documentElement.classList.add('double-top-nav-layout');
      <?php else: ?>
      var navbarPosition = localStorage.getItem('navbarPosition');
      if (navbarPosition === 'double-top') {
        document.documentElement.classList.add('double-top-nav-layout');
      }
      <?php endif; ?>
    </script>
  </head>

<!-- Session Alerts (Success/Error/Warning/Info) -->
<?php include __DIR__ . '/alerts.php'; ?>