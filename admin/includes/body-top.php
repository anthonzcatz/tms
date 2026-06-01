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
// Session configuration for session-manager.js
$sessionLifetimeMs = intval(env('SESSION_LIFETIME', 7200)) * 1000;
$warningTimeout = Database::fetch("SELECT session_warning_timeout FROM system_settings WHERE setting_id = 1");
$warningMins = intval($warningTimeout['session_warning_timeout'] ?? 15);
$warningMins = max(1, min($warningMins, 15)); // Cap between 1 and 15 minutes
?>
<!-- Session Manager -->
<script src="<?php echo BASE_URL; ?>/resources/assets/js/session-manager.js?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/resources/assets/js/session-manager.js'); ?>"></script>
<script>
// Initialize session configuration on body tag
document.body.setAttribute('data-session-lifetime', '<?php echo $sessionLifetimeMs; ?>');
document.body.setAttribute('data-warning-minutes', '<?php echo $warningMins; ?>');
</script>
