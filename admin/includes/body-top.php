<?php
/**
 * body-top.php
 * Include this immediately after <body> in all admin pages.
 * Contains session alerts (toasts) and session-manager initialization.
 */
?>

<!-- Session Alerts (Success/Error/Warning/Info) -->
<?php include __DIR__ . '/alerts.php'; ?>

<?php
// Session configuration for session-manager.js — always read from system_settings so JS matches DB
$_sessionSettings = Database::fetch("SELECT session_lifetime_minutes, session_warning_timeout FROM system_settings WHERE setting_id = 1");
$_sessionLifetimeSec = max(300, intval($_sessionSettings['session_lifetime_minutes'] ?? intval(env('SESSION_LIFETIME', 7200) / 60)) * 60);
$sessionLifetimeMs   = $_sessionLifetimeSec * 1000;
$warningMins         = max(1, intval($_sessionSettings['session_warning_timeout'] ?? 15));
// Clamp: warning must be at least 1 min and at most (lifetime - 1) min
$warningMins         = min($warningMins, max(1, intval($_sessionLifetimeSec / 60) - 1));
unset($_sessionSettings, $_sessionLifetimeSec);
?>
<!-- Session Manager -->
<script src="<?php echo BASE_URL; ?>/resources/assets/js/session-manager.js?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/resources/assets/js/session-manager.js') ?: date('YmdHis'); ?>"></script>
<script>
// Initialize session configuration on body tag
document.body.setAttribute('data-session-lifetime', '<?php echo $sessionLifetimeMs; ?>');
document.body.setAttribute('data-warning-minutes', '<?php echo $warningMins; ?>');
</script>
