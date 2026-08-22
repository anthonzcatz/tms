<?php
/**
 * Reports Category Guard
 */

require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();

if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_REPORTS')) {
    $message = 'You do not have permission to access the Reports module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
