  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token for API Security -->
    <?php
    require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';

    // Fetch system settings for branding
    $systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
    $systemName = htmlspecialchars($systemSettings['system_name'] ?? 'Falcon', ENT_QUOTES, 'UTF-8');
    $systemLogo = $systemSettings['system_logo'] ?? null;

    // Validate logo URL to prevent XSS attacks
    if ($systemLogo) {
        $systemLogo = trim($systemLogo);
        if (!preg_match('/^(\/|https?:\/\/)/i', $systemLogo)) {
            $systemLogo = null;
        }
    }

    // Determine page title based on current URL
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $pageTitle = 'Dashboard';

    if (preg_match('/\/admin\/([^\/]+)/i', $currentPath, $matches)) {
        $pageName = str_replace(['-', '_'], ' ', $matches[1]);
        $pageTitle = ucwords($pageName);
    }
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
    <title><?php echo $systemName; ?> | <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>


    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <?php if ($systemLogo): ?>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL . $systemLogo; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL . $systemLogo; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL . $systemLogo; ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL . $systemLogo; ?>">
    <?php else: ?>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon.ico">
    <?php endif; ?>
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

<!-- Session Expiry Detection -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Session expiry check interval (check every 30 seconds for better real-time response)
    const SESSION_CHECK_INTERVAL = 30000;
    let sessionCheckCount = 0;
    const MAX_RETRIES = 3;
    let lastActivityTime = Date.now();
    
    // Read session lifetime from env (defaults to 7200 seconds = 2 hours)
    const SESSION_LIFETIME_MS = <?php echo intval(env('SESSION_LIFETIME', 7200)); ?> * 1000;
    const ACTIVITY_TIMEOUT = SESSION_LIFETIME_MS; // Session expires after this much inactivity
    
    // Read warning timeout from database settings (minutes before expiry to show warning)
    // e.g., if set to 15, warning shows 15 minutes before session expires
    // Cap at 15 minutes max to prevent showing warning too early
    const WARNING_BEFORE_EXPIRY_MINUTES = <?php 
        $warningTimeout = Database::fetch("SELECT session_warning_timeout FROM system_settings WHERE setting_id = 1");
        $warningMins = intval($warningTimeout['session_warning_timeout'] ?? 15);
        // Cap between 1 and 15 minutes
        echo max(1, min($warningMins, 15));
    ?>;
    // Clamp warning minutes so it never exceeds session lifetime (leave at least 1 min of session)
    const SESSION_LIFETIME_MINUTES = SESSION_LIFETIME_MS / 60000;
    const EFFECTIVE_WARNING_MINUTES = Math.min(WARNING_BEFORE_EXPIRY_MINUTES, Math.max(SESSION_LIFETIME_MINUTES - 1, 1));
    // Calculate when to show warning: ACTIVITY_TIMEOUT minus warning minutes
    // Minimum 1 minute warning, maximum 15 minutes before expiry
    const WARNING_TIMEOUT = Math.max(ACTIVITY_TIMEOUT - (EFFECTIVE_WARNING_MINUTES * 60 * 1000), 60000);
    let warningShown = false;

    // Detect user activity
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    activityEvents.forEach(event => {
        document.addEventListener(event, function() {
            lastActivityTime = Date.now();
        }, true);
    });

    // Function to refresh/extend session
    function refreshSession() {
        fetch(window.BASE_URL + '/api/refresh-session.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Session refreshed');
            }
        })
        .catch(error => {
            console.warn('Failed to refresh session:', error);
        });
    }

    function checkSession() {
        fetch(window.BASE_URL + '/api/check-session.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Session check failed');
            }
            return response.json();
        })
        .then(data => {
            sessionCheckCount = 0; // Reset retry count on success
            if (!data.valid && !data.error) {
                // Only show alert if explicitly invalid (not on error)
                showSessionExpiredAlert();
            }
        })
        .catch(error => {
            // On network error, increment retry count
            sessionCheckCount++;
            if (sessionCheckCount >= MAX_RETRIES) {
                // Only show alert after multiple consecutive failures
                showSessionExpiredAlert();
            }
            console.warn('Session check error:', error);
        });
    }

    function showSessionExpiredAlert() {
        // Remove existing modal if present
        const existingModal = document.getElementById('sessionExpiredAlertModal');
        if (existingModal) {
            return; // Don't show duplicate modals
        }

        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="sessionExpiredAlertModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning bg-opacity-10">
                            <h5 class="modal-title text-warning">
                                <span class="fas fa-exclamation-triangle me-2"></span>Session Expired
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session has expired due to inactivity. You will be redirected to the login page.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="redirectToLogin()">
                                <span class="fas fa-sign-in-alt me-2"></span>Go to Login
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiredAlertModal'));
        modal.show();
    }

    function showSessionWarningAlert() {
        // Remove existing warning modal if present
        const existingModal = document.getElementById('sessionWarningModal');
        if (existingModal) {
            return; // Don't show duplicate modals
        }

        // Calculate actual remaining minutes
        const timeSinceLastActivity = Date.now() - lastActivityTime;
        const remainingMs = Math.max(ACTIVITY_TIMEOUT - timeSinceLastActivity, 0);
        const remainingMinutes = Math.ceil(remainingMs / 60000);

        // Create warning modal HTML
        const modalHtml = `
            <div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-info bg-opacity-10">
                            <h5 class="modal-title text-info">
                                <span class="fas fa-clock me-2"></span>Session Expiring Soon
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session will expire in approximately <strong>${remainingMinutes} minutes</strong> due to inactivity.</p>
                            <p>Would you like to extend your session?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <span class="fas fa-times me-2"></span>Ignore
                            </button>
                            <button type="button" class="btn btn-primary" onclick="extendSessionFromWarning()">
                                <span class="fas fa-redo me-2"></span>Extend Session
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
        modal.show();
    }

    window.extendSessionFromWarning = function() {
        // Close the warning modal
        const warningModal = document.getElementById('sessionWarningModal');
        if (warningModal) {
            const modal = bootstrap.Modal.getInstance(warningModal);
            if (modal) {
                modal.hide();
            }
            warningModal.remove();
        }

        // Refresh session
        refreshSession();
        lastActivityTime = Date.now(); // Reset activity timer
        warningShown = false; // Reset warning flag
    };

    window.redirectToLogin = function() {
        window.location.href = window.BASE_URL + '/auth/login.php?error=session_expired';
    };

    // Combined session management: refresh if active, check if inactive
    setInterval(function() {
        const timeSinceLastActivity = Date.now() - lastActivityTime;
        
        if (timeSinceLastActivity < ACTIVITY_TIMEOUT) {
            // User is active, refresh session
            refreshSession();
            
            // Reset warning flag when user is active
            if (timeSinceLastActivity < WARNING_TIMEOUT) {
                warningShown = false;
            }
        } else {
            // User has been inactive, check if session expired
            checkSession();
        }
        
        // Show warning before session expires (15 min before expiry)
        if (timeSinceLastActivity >= WARNING_TIMEOUT && 
            timeSinceLastActivity < ACTIVITY_TIMEOUT && 
            !warningShown) {
            showSessionWarningAlert();
            warningShown = true;
        }
    }, SESSION_CHECK_INTERVAL);
});
</script>