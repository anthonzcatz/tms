<?php
/**
 * Cashier Shift Reports Controller
 */
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
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
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = PosAccess::allowedBranchIds($user);

$filterDate   = $_GET['date']   ?? date('Y-m-d');
$filterBranch = $_GET['branch'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$branchConditions = [];
$statusWhere = '';
$params = ['date_start' => $filterDate . ' 00:00:00', 'date_end' => $filterDate . ' 23:59:59'];
PosAccess::applyBranchScope($branchConditions, $params, 'cs.branch_id', $user, 'shift_branch');

if ($filterBranch !== '') {
    $filterBranchId = filter_var($filterBranch, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($filterBranchId === false) {
        $branchConditions[] = '1 = 0';
    } else {
        try {
            PosAccess::assertBranchAccess($user, (int) $filterBranchId);
            $branchConditions[] = 'cs.branch_id = :filter_branch';
            $params['filter_branch'] = (int) $filterBranchId;
        } catch (Throwable $e) {
            $branchConditions[] = '1 = 0';
        }
    }
}
$branchWhere = $branchConditions ? 'AND ' . implode(' AND ', $branchConditions) : '';

if ($filterStatus) {
    $statusWhere = 'AND cs.status = :status';
    $params['status'] = strtoupper($filterStatus);
}

// Fetch sessions for the day
$sessions = Database::fetchAll(
    "SELECT cs.*,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS cashier_name,
            ua.profile_image,
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
$branchListWhere = ["status = 'active'"];
$branchListParams = [];
PosAccess::applyBranchScope($branchListWhere, $branchListParams, 'branch_id', $user, 'shift_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchListWhere) . "
     ORDER BY branch_name",
    $branchListParams
);

// Fetch POS settings for manager permissions
$posSettings = Database::fetch(
    "SELECT pos_manager_can_open_for_cashier, pos_manager_can_close_for_cashier
     FROM system_settings WHERE setting_id = 1"
);

// Fetch cancellation / refund display settings for close session modal
$cancellationSettings = Database::fetch(
    "SELECT cancellation_requires_confirmation,
            cancellation_refund_processing_days,
            cancellation_allow_partial,
            show_pending_refunds_in_close_session
     FROM system_settings
     WHERE setting_id = 1"
) ?: [];
$cancellationSettings['show_pending_refunds_in_close_session'] = (int) ($cancellationSettings['show_pending_refunds_in_close_session'] ?? 0);

// Fetch bank accounts for deposit modal
$bankAccountWhere = ['is_active = 1'];
$bankAccountParams = [];
if ($allowedBranchIds !== null) {
    if (!$allowedBranchIds) {
        $bankAccountWhere[] = '1 = 0';
    } else {
        $bankBranchPlaceholders = [];
        foreach (array_values($allowedBranchIds) as $index => $allowedBranchId) {
            $key = 'shift_bank_branch_' . $index;
            $bankBranchPlaceholders[] = ':' . $key;
            $bankAccountParams[$key] = $allowedBranchId;
        }
        $bankAccountWhere[] = '(branch_id IS NULL OR branch_id IN (' . implode(',', $bankBranchPlaceholders) . '))';
    }
}
$bankAccounts = Database::fetchAll(
    "SELECT bank_account_id, bank_name, account_name, account_number
     FROM bank_accounts
     WHERE " . implode(' AND ', $bankAccountWhere) . "
     ORDER BY bank_name ASC",
    $bankAccountParams
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
    'posSettings' => $posSettings,
    'cancellationSettings' => $cancellationSettings,
    'bankAccounts' => $bankAccounts
];

extract($viewData);
include __DIR__ . '/views/index.php';
