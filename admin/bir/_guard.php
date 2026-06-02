<?php
/**
 * BIR Module Guard
 * Checks if user has access to BIR module
 */

require_once dirname(__DIR__) . '/_guard.php';  // Global admin guard

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

// Allowed roles for BIR module
$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];

if (!in_array($userRoleCode, $allowedRoles)) {
    $message = 'You do not have permission to access the BIR module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
