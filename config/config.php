<?php
/**
 * config.php
 * Defines BASE_URL and named route constants for the entire app.
 * Supports:
 *   - APP_URL from .env (preferred for production / Hostinger)
 *   - Auto-detection from $_SERVER (great for local XAMPP at /TMS)
 */

require_once __DIR__ . '/env.php';

if (!function_exists('tms_detect_base_url')) {
    function tms_detect_base_url() {
        // 1) Explicit APP_URL wins (production-safe)
        $appUrl = env('APP_URL');
        if (!empty($appUrl)) {
            return rtrim($appUrl, '/');
        }

        // 2) Auto-detect (local dev under /TMS)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        // The project root is wherever this file's parent's parent lives,
        // mapped from DOCUMENT_ROOT.
        $docRoot     = str_replace('\\', '/', rtrim(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '', '/'));
        $projectRoot = str_replace('\\', '/', dirname(__DIR__));
        $basePath    = '';
        if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
            $basePath = substr($projectRoot, strlen($docRoot));
        } else {
            // Fallback: derive from SCRIPT_NAME
            $basePath = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')), '/');
            // Strip known subfolders so URLs always resolve to project root
            $basePath = preg_replace('#/(auth|admin)(/.*)?$#', '', $basePath);
        }

        return $protocol . $host . rtrim(isset($basePath) ? $basePath : '', '/');
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', tms_detect_base_url());
}

/* Auth routes (clean URLs, .htaccess maps them to .php files) */
if (!defined('LOGIN_URL'))            define('LOGIN_URL',            BASE_URL . '/login');
if (!defined('REGISTER_URL'))         define('REGISTER_URL',         BASE_URL . '/register');
if (!defined('FORGOT_PASSWORD_URL'))  define('FORGOT_PASSWORD_URL',  BASE_URL . '/forgot-password');
if (!defined('RESET_PASSWORD_URL'))   define('RESET_PASSWORD_URL',   BASE_URL . '/reset-password');
if (!defined('CONFIRM_MAIL_URL'))     define('CONFIRM_MAIL_URL',     BASE_URL . '/confirm-mail');
if (!defined('LOCK_SCREEN_URL'))      define('LOCK_SCREEN_URL',      BASE_URL . '/lock-screen');
if (!defined('LOGOUT_URL'))           define('LOGOUT_URL',           BASE_URL . '/logout');

/* Admin routes (clean URLs) */
if (!defined('ADMIN_URL'))            define('ADMIN_URL',            BASE_URL . '/admin');
if (!defined('ADMIN_DASHBOARD_URL'))  define('ADMIN_DASHBOARD_URL',  BASE_URL . '/admin');

/**
 * Build a URL relative to BASE_URL.
 *   url('admin/dashboard/analytics') -> http://host/TMS/admin/dashboard/analytics
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

/**
 * Resolve a named route (extend this map as you add modules).
 *   route('admin.users')  -> .../admin/users
 */
if (!function_exists('route')) {
    function route(string $name, array $params = []): string {
        static $map = [
            'login'             => '/login',
            'register'          => '/register',
            'forgot-password'   => '/forgot-password',
            'reset-password'    => '/reset-password',
            'logout'            => '/logout',
            'admin'             => '/admin',
            'admin.dashboard'   => '/admin',
            'admin.analytics'   => '/admin/dashboard/analytics',
            'admin.users'       => '/admin/user/profile',
            'admin.settings'    => '/admin/user/settings',
        ];
        $path = $map[$name] ?? '/' . ltrim($name, '/');
        if ($params) {
            $path .= '?' . http_build_query($params);
        }
        return BASE_URL . $path;
    }
}
