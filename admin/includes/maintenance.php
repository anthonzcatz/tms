<?php
/**
 * Global Maintenance Page
 * Used when the system is in maintenance mode
 */
?>
<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Fetch maintenance settings first before any output
$maintenanceSettings = Database::fetch(
    "SELECT maintenance_mode, maintenance_message, maintenance_start, maintenance_end, allow_admin_during_maintenance 
     FROM system_settings WHERE setting_id = 1"
);

$maintenanceMode = $maintenanceSettings['maintenance_mode'] ?? 0;
$maintenanceMessage = $maintenanceSettings['maintenance_message'] ?? 'The system is currently under maintenance. Please check back later.';
$maintenanceStart = $maintenanceSettings['maintenance_start'] ?? null;
$maintenanceEnd = $maintenanceSettings['maintenance_end'] ?? null;
$allowAdmin = $maintenanceSettings['allow_admin_during_maintenance'] ?? 1;

// Check if maintenance is still active
if ($maintenanceMode) {
    $now = date('Y-m-d H:i:s');
    $inMaintenanceWindow = true;
    
    if ($maintenanceStart && $maintenanceStart > $now) {
        $inMaintenanceWindow = false;
    }
    if ($maintenanceEnd && $maintenanceEnd < $now) {
        $inMaintenanceWindow = false;
    }
    
    // If not in maintenance window, redirect back to the page user was trying to access
    if (!$inMaintenanceWindow) {
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/dashboard';
        header('Location: ' . $redirectUrl);
        exit;
    }
} else {
    // Maintenance mode is disabled, redirect back to the page user was trying to access
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/dashboard';
    header('Location: ' . $redirectUrl);
    exit;
}

// Now include head.php since we know maintenance is active
require_once dirname(__DIR__) . '/includes/head.php';
if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', 'vertical');
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
        <!-- Maintenance Content -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-body text-center py-5">
                <div class="mb-4">
                  <div class="icon-item bg-soft-warning rounded-circle mx-auto mb-3" style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                    <span class="fas fa-tools fs-1 text-warning"></span>
                  </div>
                </div>
                <h2 class="mb-3">System Under Maintenance</h2>
                <p class="text-muted mb-3 fs-5">
                  <?php echo htmlspecialchars($maintenanceMessage); ?>
                </p>
                <?php if ($maintenanceStart || $maintenanceEnd): ?>
                <div class="mb-4">
                  <?php if ($maintenanceStart): ?>
                  <p class="text-muted mb-2">
                    <span class="fas fa-clock me-2"></span>
                    <strong>Start:</strong> <?php echo date('F j, Y, g:i a', strtotime($maintenanceStart)); ?>
                  </p>
                  <?php endif; ?>
                  <?php if ($maintenanceEnd): ?>
                  <p class="text-muted mb-2">
                    <span class="fas fa-clock me-2"></span>
                    <strong>Expected End:</strong> <?php echo date('F j, Y, g:i a', strtotime($maintenanceEnd)); ?>
                  </p>
                  <div id="countdown" class="mt-3 p-3 bg-light rounded">
                    <h5 class="mb-2 text-primary">Time Remaining:</h5>
                    <div class="d-flex justify-content-center gap-3">
                      <div class="text-center">
                        <div id="days" class="fs-3 fw-bold text-primary">00</div>
                        <small class="text-muted">Days</small>
                      </div>
                      <div class="text-center">
                        <div id="hours" class="fs-3 fw-bold text-primary">00</div>
                        <small class="text-muted">Hours</small>
                      </div>
                      <div class="text-center">
                        <div id="minutes" class="fs-3 fw-bold text-primary">00</div>
                        <small class="text-muted">Minutes</small>
                      </div>
                      <div class="text-center">
                        <div id="seconds" class="fs-3 fw-bold text-primary">00</div>
                        <small class="text-muted">Seconds</small>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
                <p class="text-muted mb-4">
                  We apologize for the inconvenience. Please check back soon.
                </p>
                <div class="d-flex justify-content-center gap-2">
                  <button onclick="location.reload()" class="btn btn-primary">
                    <span class="fas fa-sync-alt me-2"></span>Refresh Page
                  </button>
                  <?php if ($allowAdmin): ?>
                  <a href="mailto:support@tms.com" class="btn btn-outline-secondary">
                    <span class="fas fa-question-circle me-2"></span>Contact Support
                  </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
    <?php include dirname(__DIR__) . '/includes/scripts.php'; ?>
    <script>
        // Countdown timer for maintenance end time
        <?php if ($maintenanceEnd): ?>
        const maintenanceEndTime = new Date('<?php echo date('Y-m-d H:i:s', strtotime($maintenanceEnd)); ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = maintenanceEndTime - now;
            
            if (distance < 0) {
                // Maintenance has ended
                document.getElementById('countdown').innerHTML = '<h5 class="text-success">Maintenance has ended!</h5>';
                document.getElementById('countdown').classList.add('bg-success', 'text-white');
                document.getElementById('countdown').classList.remove('bg-light');
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }
        
        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
  </body>
</html>
