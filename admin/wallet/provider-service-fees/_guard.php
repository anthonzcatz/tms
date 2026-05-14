<?php
/**
 * Provider Service Fees Module Guard
 * Protects provider service fees module access based on permissions
 */

require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Get current user
$user = Auth::user();

// SUPER_ADMIN can access everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/wallet/provider-service-fees/')) {
    $message = 'You do not have permission to access the Service Fees module.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
