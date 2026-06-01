<?php
/**
 * BIR Module Guard
 * Checks if user has access to BIR module
 */

require_once dirname(__DIR__) . '/app/helpers/Auth.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

// Allowed roles for BIR module
$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];

if (!in_array($userRoleCode, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. You do not have permission to access the BIR module.']);
    exit;
}
