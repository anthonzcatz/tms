<?php
/**
 * _guard.php
 * Include this at the TOP of any admin page to require authentication.
 *
 * Usage:
 *   <?php require_once __DIR__ . '/_guard.php'; ?>
 *
 * This file:
 *   - Loads the bootstrap (env, db, session, auth helpers)
 *   - Checks if user is logged in
 *   - Redirects to login if not authenticated
 *   - Detects session hijacking (fingerprint mismatch)
 *   - Prevents caching of admin pages
 *
 * Place this file in the admin/ folder.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$allowedNavbarPositions = ['vertical', 'top', 'combo', 'double-top'];
if (isset($_GET['layout']) && in_array($_GET['layout'], $allowedNavbarPositions, true)) {
    $_SESSION['navbarPosition'] = $_GET['layout'];
    $params = $_GET;
    unset($params['layout']);
    $redirectPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    if (!empty($params)) {
        $redirectPath .= '?' . http_build_query($params);
    }
    header('Location: ' . $redirectPath);
    exit;
}

if (!isset($_SESSION['navbarPosition']) || !in_array($_SESSION['navbarPosition'], $allowedNavbarPositions, true)) {
    $_SESSION['navbarPosition'] = 'vertical';
}

define('NAVBAR_POSITION', $_SESSION['navbarPosition']);

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login
Auth::requireLogin();

// SUPER_ADMIN has access to everything, other roles are checked by module-specific guards

// Maintenance mode check (cached in session for performance)
$maintenanceSettings = null;
$maintenanceCacheKey = 'maintenance_settings_cache';
$maintenanceCacheTime = 300; // 5 minutes cache

// Check if we have cached maintenance settings
if (isset($_SESSION[$maintenanceCacheKey]) && isset($_SESSION[$maintenanceCacheKey . '_time'])) {
    $cacheAge = time() - $_SESSION[$maintenanceCacheKey . '_time'];
    if ($cacheAge < $maintenanceCacheTime) {
        $maintenanceSettings = $_SESSION[$maintenanceCacheKey];
    }
}

// If no cache or cache expired, fetch from database
if (!$maintenanceSettings) {
    $maintenanceSettings = Database::fetch(
        "SELECT maintenance_mode, maintenance_message, maintenance_start, maintenance_end, allow_admin_during_maintenance 
         FROM system_settings WHERE setting_id = 1"
    );
    
    // Cache the settings
    $_SESSION[$maintenanceCacheKey] = $maintenanceSettings;
    $_SESSION[$maintenanceCacheKey . '_time'] = time();
}

$maintenanceMode = $maintenanceSettings['maintenance_mode'] ?? 0;
$maintenanceStart = $maintenanceSettings['maintenance_start'] ?? null;
$maintenanceEnd = $maintenanceSettings['maintenance_end'] ?? null;
$allowAdmin = $maintenanceSettings['allow_admin_during_maintenance'] ?? 1;

if ($maintenanceMode) {
    $now = date('Y-m-d H:i:s');
    $inMaintenanceWindow = true;
    
    // Check if within maintenance window
    if ($maintenanceStart && $maintenanceStart > $now) {
        $inMaintenanceWindow = false;
    }
    if ($maintenanceEnd && $maintenanceEnd < $now) {
        $inMaintenanceWindow = false;
        // Auto-disable maintenance mode if end time has passed
        Database::execute(
            "UPDATE system_settings SET maintenance_mode = 0 WHERE setting_id = 1"
        );
        // Clear cache to reflect the change
        unset($_SESSION[$maintenanceCacheKey]);
        unset($_SESSION[$maintenanceCacheKey . '_time']);
    }
    
    if ($inMaintenanceWindow) {
        $user = Auth::user();
        $isAdmin = $user && ($user['role_code'] === 'SUPER_ADMIN' || $user['role_code'] === 'ADMIN');
        
        // Show maintenance page for non-admin users OR if allowAdmin is disabled
        if (!$isAdmin || !$allowAdmin) {
            $maintenanceMessage = $maintenanceSettings['maintenance_message'] ?? 'The system is currently under maintenance. Please check back later.';
            include dirname(__DIR__) . '/admin/includes/maintenance.php';
            exit;
        }
        
        // Admin with allowAdmin enabled - allow access (do nothing)
    }
}

