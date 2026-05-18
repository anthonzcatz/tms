<?php
/**
 * Cashier Shift Reports Controller
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
} elseif (!Auth::canAccessModule('admin/shifts/')) {
    $message = 'You do not have permission to access Cashier Shift Reports.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

$filterDate   = $_GET['date']   ?? date('Y-m-d');
$filterBranch = $_GET['branch'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$branchWhere = '';
$statusWhere = '';
$params = ['date_start' => $filterDate . ' 00:00:00', 'date_end' => $filterDate . ' 23:59:59'];

if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchWhere = 'AND cs.branch_id = :branch_id';
    $params['branch_id'] = $userBranchId;
} elseif ($filterBranch) {
    $branchWhere = 'AND cs.branch_id = :branch_id';
    $params['branch_id'] = $filterBranch;
}

if ($filterStatus) {
    $statusWhere = 'AND cs.status = :status';
    $params['status'] = strtoupper($filterStatus);
}

// Fetch sessions for the day
$sessions = Database::fetchAll(
    "SELECT cs.*,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS cashier_name,
            bb.branch_name,
            COALESCE(CONCAT_WS(' ', e_rev.first_name, e_rev.last_name), ua_rev.username) AS reviewed_by_name
     FROM cashier_sessions cs
     JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua_rev ON cs.reviewed_by = ua_rev.user_id
     LEFT JOIN employees e_rev ON ua_rev.emp_id = e_rev.emp_id
     WHERE cs.started_at BETWEEN :date_start AND :date_end
       {$branchWhere}
       {$statusWhere}
     ORDER BY cs.started_at DESC",
    $params
);

// Summary stats
$summary = Database::fetch(
    "SELECT
        COUNT(*) AS total_sessions,
        SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS open_sessions,
        SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS closed_sessions,
        SUM(total_sales) AS total_sales,
        SUM(total_cash) AS total_cash
     FROM cashier_sessions cs
     WHERE cs.started_at BETWEEN :date_start AND :date_end
       {$branchWhere}
       {$statusWhere}",
    $params
);

// All branches for filter dropdown and manager session modal
$branches = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE status='active' ORDER BY branch_name");

// Fetch POS settings for manager permissions
$posSettings = Database::fetch(
    "SELECT pos_manager_can_open_for_cashier, pos_manager_can_close_for_cashier
     FROM system_settings WHERE setting_id = 1"
);

// Pass data to view
$viewData = [
    'sessions' => $sessions,
    'summary' => $summary,
    'branches' => $branches,
    'filterDate' => $filterDate,
    'filterBranch' => $filterBranch,
    'filterStatus' => $filterStatus,
    'userRoleCode' => $userRoleCode,
    'posSettings' => $posSettings
];

extract($viewData);
include __DIR__ . '/views/index.php';
