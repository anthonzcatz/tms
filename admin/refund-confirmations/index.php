<?php
/**
 * Refund Confirmations Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/refund-confirmations/')) {
    $message = 'You do not have permission to access Refund Confirmations.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$userRoleCode  = $user['role_code'] ?? '';
$userBranchId  = $user['branch_id'] ?? null;

// Build branch filter
$branchWhere = '';
$branchParam = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchWhere = 'AND bb.branch_id = :branch_id';
    $branchParam['branch_id'] = $userBranchId;
}

// Status filter from GET
$statusFilter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'completed', 'all'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'pending';

$statusWhere = $statusFilter !== 'all' ? "AND tc.status = :status" : '';
$statusParam = $statusFilter !== 'all' ? ['status' => $statusFilter] : [];

// Fetch pending / filtered refund requests
$cancellations = Database::fetchAll(
    "SELECT tc.*,
            COALESCE(CONCAT_WS(' ', e_request.first_name, e_request.last_name), ua_request.username) AS requested_by_name,
            COALESCE(CONCAT_WS(' ', e_approve.first_name, e_approve.last_name), ua_approve.username) AS approved_by_name,
            bb.branch_name,
            tp.provider_name,
            tt.travel_date,
            tt.origin,
            tt.destination,
            pa.fullname AS passenger_name
     FROM ticket_cancellations tc
     LEFT JOIN user_accounts ua_request ON tc.requested_by = ua_request.user_id
     LEFT JOIN employees e_request ON ua_request.emp_id = e_request.emp_id
     LEFT JOIN user_accounts ua_approve ON tc.approved_by = ua_approve.user_id
     LEFT JOIN employees e_approve ON ua_approve.emp_id = e_approve.emp_id
     LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
     LEFT JOIN provider_wallets pw ON tt.wallet_id = pw.wallet_id
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON tt.branch_id = bb.branch_id
     LEFT JOIN passenger_accounts pa ON tc.passenger_id = pa.passenger_id
     WHERE 1=1
       {$statusWhere}
       {$branchWhere}
     ORDER BY tc.requested_at DESC",
    array_merge($statusParam, $branchParam)
);

// Stats
$statCounts = Database::fetch(
    "SELECT
        SUM(CASE WHEN tc.status='pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tc.status='approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN tc.status='rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN tc.status='completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN tc.status='pending' THEN tc.refund_amount ELSE 0 END) AS pending_amount
     FROM ticket_cancellations tc
     LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
     LEFT JOIN business_branches bb ON tt.branch_id = bb.branch_id
     WHERE 1=1
       {$branchWhere}",
    $branchParam
);

include __DIR__ . '/views/index.php';
