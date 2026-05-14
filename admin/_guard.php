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
