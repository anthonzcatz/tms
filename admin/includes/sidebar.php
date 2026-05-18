<?php
require_once dirname(__DIR__) . '/helpers/SidebarHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Fetch system settings for branding
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'Falcon', ENT_QUOTES, 'UTF-8');
$systemLogo = $systemSettings['system_logo'] ?? null;

// Validate logo URL to prevent XSS attacks
if ($systemLogo) {
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null;
    }
}

$navbarPosition = defined('NAVBAR_POSITION') ? NAVBAR_POSITION : 'vertical';
$showSidebar = in_array($navbarPosition, ['vertical', 'combo'], true);
?>
<?php if ($showSidebar): ?>
        <nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
          <script>
            var navbarStyle = localStorage.getItem("navbarStyle");
            if (navbarStyle && navbarStyle !== 'transparent') {
              document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
            }
          </script>
          <div class="d-flex align-items-center">
            <div class="toggle-icon-wrapper">
              <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" title="Toggle Navigation"><span class="navbar-toggle-icon"><span class="toggle-line"></span></span></button>
            </div>
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin">
              <div class="d-flex align-items-center py-3">
                <img class="me-2" src="<?php echo $systemLogo ? BASE_URL . $systemLogo : BASE_URL . '/resources/assets/img/icons/spot-illustrations/falcon.png'; ?>" alt="" width="40" />
                <span class="font-sans-serif text-primary"><?php echo $systemName; ?></span>
              </div>
            </a>
          </div>
          <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
            <div class="navbar-vertical-content scrollbar">
              <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                <?php echo SidebarHelper::render($active_page ?? null); ?>
              </ul>
              <!-- Falcon promotional message removed - template already purchased -->
            </div>
          </div>
        </nav>
<?php endif; ?>
