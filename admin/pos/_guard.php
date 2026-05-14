<?php
/**
 * POS (Operations) Module Guard
 */

require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();

// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/pos/')) {
    $message = 'You do not have permission to access the Cashier POS module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
