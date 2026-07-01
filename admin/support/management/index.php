<?php
/**
 * Support Requests Management Controller
 * Allows support team to view and manage support tickets
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

require_once dirname(dirname(__DIR__)) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();

// Only SUPER_ADMIN and ADMIN can access support management
if ($user['role_code'] !== 'SUPER_ADMIN' && $user['role_code'] !== 'ADMIN') {
    $message = 'You do not have permission to access Support Management. This section is restricted to Administrators only.';
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Fetch all support requests with user info
$requests = Database::fetchAll(
    "SELECT sr.*,
            ua_assigned.username AS assigned_to_name
     FROM support_requests sr
     LEFT JOIN user_accounts ua_assigned ON sr.assigned_to = ua_assigned.user_id
     ORDER BY sr.created_at DESC"
);

// Fetch all users for assignment dropdown
$assignableUsers = Database::fetchAll(
    "SELECT ua.user_id, ua.username 
     FROM user_accounts ua
     JOIN user_roles ur ON ua.role_id = ur.role_id
     WHERE ua.status = 'active' 
       AND ur.role_code IN ('SUPER_ADMIN', 'ADMIN')
     ORDER BY ua.username ASC"
);

// Pass data to view
$viewData = [
    'requests' => $requests,
    'assignableUsers' => $assignableUsers,
    'user' => $user
];

extract($viewData);
include __DIR__ . '/views/index.php';
