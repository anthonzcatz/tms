<?php
/**
 * POS (Operations) Module Guard
 */

require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();

if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_CASHIER_POS')) {
    $message = 'You do not have permission to access the Cashier POS module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
