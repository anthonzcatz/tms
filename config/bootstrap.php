<?php
/**
 * bootstrap.php
 * THE single entry every page should include. Handles:
 *   - Loads environment variables (.env)
 *   - Loads URL/route config
 *   - Starts a hardened session
 *   - Registers a tiny PSR-style autoloader for app/ classes
 *   - Exposes globals: db(), env(), url(), route(), Auth::*
 *
 * Usage in any page (works from any depth):
 *   require_once dirname(__DIR__, N) . '/config/bootstrap.php';
 * Or the convenience constant once defined elsewhere.
 */

if (defined('TMS_BOOTED')) {
    return;
}
define('TMS_BOOTED', true);
define('TMS_ROOT', dirname(__DIR__));

require_once TMS_ROOT . '/config/env.php';
require_once TMS_ROOT . '/config/database.php';
require_once TMS_ROOT . '/config/config.php';

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));

/* ------- Error handling ------- */
if (env('APP_DEBUG', false) === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

/* ------- Tiny autoloader for app/ ------- */
spl_autoload_register(function (string $class): void {
    $candidates = [
        TMS_ROOT . '/app/models/'      . $class . '.php',
        TMS_ROOT . '/app/controllers/' . $class . '.php',
        TMS_ROOT . '/app/helpers/'     . $class . '.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

/* ------- Session bootstrap (secure) ------- */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    session_name((string) env('SESSION_NAME', 'tms_session'));
    session_set_cookie_params([
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) env('SESSION_LIFETIME', 7200));
    session_start();
}

/* ------- Security headers ------- */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Prevent caching for admin pages
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
