<?php
/**
 * Global Access Denied Page
 * Used across all admin modules for consistent access denied experience
 */
?>
<?php
require_once dirname(__DIR__) . '/includes/head.php';
if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', 'vertical');
}

// Get user's default dashboard based on role
$user = Auth::user();
$defaultDashboard = BASE_URL . '/admin/dashboard';
if ($user && isset($user['default_dashboard']) && !empty($user['default_dashboard'])) {
    $defaultDashboard = BASE_URL . $user['default_dashboard'];
}
?>
<body>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="container" data-layout="container">
        <script>
          var isFluid = JSON.parse(localStorage.getItem('isFluid'));
          if (isFluid) {
            var container = document.querySelector('[data-layout]');
            container.classList.remove('container');
            container.classList.add('container-fluid');
          }
        </script>
        <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
        <?php if (NAVBAR_POSITION === 'top'): ?>
          <?php include dirname(__DIR__) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
          <?php include dirname(__DIR__) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
        <div class="content">
         <?php
         switch (NAVBAR_POSITION) {
             case 'combo':
                 include dirname(__DIR__) . '/includes/navbar-top.php';
                 break;
             case 'vertical':
                 include dirname(__DIR__) . '/includes/navbar.php';
                 break;
             case 'top':
             case 'double-top':
             default:
                 break;
         }
         ?>
        <!-- Access Denied Content -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-body text-center py-5">
                <div class="mb-4">
                  <div class="icon-item bg-soft-danger rounded-circle mx-auto mb-3" style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                    <span class="fas fa-exclamation-triangle fs-1 text-danger"></span>
                  </div>
                </div>
                <h2 class="mb-3">403 - Access Denied</h2>
                <p class="text-muted mb-3 fs-5">
                  <?php echo $message ?? 'You do not have permission to access this module.'; ?>
                </p>
                <p class="text-muted mb-4">
                  Please contact your administrator if you believe this is an error.
                </p>
                <div class="d-flex justify-content-center gap-2">
                  <a href="<?php echo $defaultDashboard; ?>" class="btn btn-primary">
                    <span class="fas fa-home me-2"></span>Return to Dashboard
                  </a>
                  <a href="mailto:support@tms.com" class="btn btn-outline-secondary">
                    <span class="fas fa-question-circle me-2"></span>Contact Support
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
    <?php include dirname(__DIR__) . '/includes/scripts.php'; ?>
  </body>
</html>
