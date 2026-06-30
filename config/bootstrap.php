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

// Set timezone from system_settings database or fallback to env variable
$timezone = env('APP_TIMEZONE', 'Asia/Manila');
try {
    $systemTimezone = Database::fetch("SELECT system_timezone FROM system_settings LIMIT 1");
    if ($systemTimezone && !empty($systemTimezone['system_timezone'])) {
        $timezone = $systemTimezone['system_timezone'];
    }
} catch (Exception $e) {
    // Fallback to env variable if database query fails
}
date_default_timezone_set($timezone);

/* ------- Error handling ------- */
// API requests must never output HTML errors — they corrupt JSON responses.
// Detect API requests by URI prefix or X-Requested-With header.
$_isApiRequest = (
    strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
);

if (env('APP_DEBUG', false) === true && !$_isApiRequest) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
unset($_isApiRequest);

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
// Phase 1: Start session with .env as the safe floor value.
// gc_maxlifetime MUST be set before session_start(); the DB is already
// loaded above so we can read the real value right here.
(function () {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // Resolve the authoritative lifetime (DB → .env fallback).
    // Minimum 300 s (5 min) to avoid accidental lock-outs.
    $dbLifetimeSec = 0;
    try {
        $row = Database::fetch("SELECT session_lifetime_minutes FROM system_settings LIMIT 1");
        if ($row && isset($row['session_lifetime_minutes'])) {
            $dbLifetimeSec = max(300, (int) $row['session_lifetime_minutes'] * 60);
        }
    } catch (\Exception $e) { /* DB not ready yet — will use env below */ }

    $lifetimeSec = $dbLifetimeSec ?: max(300, (int) env('SESSION_LIFETIME', 7200));

    if (session_status() === PHP_SESSION_NONE) {
        session_name((string) env('SESSION_NAME', 'tms_session'));
        session_set_cookie_params([
            'lifetime' => $lifetimeSec,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode',  '1');
        ini_set('session.gc_maxlifetime',   (string) $lifetimeSec);
        session_start();
    }

    // Phase 2: Re-issue the session cookie on every request so it slides
    // with the correct DB-driven lifetime (keeps cookie alive as long as
    // the user is active, matching what touchUserSession does on the DB side).
    // NOTE: gc_maxlifetime cannot be changed after session_start() — it was
    //       already set correctly in Phase 1 above before session_start().
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => time() + $lifetimeSec,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
})();

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
