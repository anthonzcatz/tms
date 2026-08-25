<?php
/**
 * Financial Reports Controller
 * Profit & Loss Statement and other financial reports
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/reports/financial/')) {
    $message = 'You do not have permission to access the Financial Reports module.';
    http_response_code(403);
    include dirname(dirname(dirname(__DIR__))) . '/admin/includes/access-denied.php';
    exit;
}

$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = PosAccess::allowedBranchIds($user);

// Default date range = this month
$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-t');

$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : $defaultStart;
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : $defaultEnd;
$branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int) $_GET['branch_id'] : null;
$invalidBranchFilter = false;
if ($branchId !== null) {
    try {
        PosAccess::assertBranchAccess($user, $branchId);
    } catch (Throwable $e) {
        $invalidBranchFilter = true;
        $branchId = null;
    }
}

// Branches for filter
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'financial_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

// System settings for print header
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'TMS');
$systemLogo = $systemSettings['system_logo'] ?? '/resources/assets/img/icons/spot-illustrations/falcon.png';
if ($systemLogo && !preg_match('/^\/|https?:\/\//i', trim($systemLogo))) {
    $systemLogo = '/resources/assets/img/icons/spot-illustrations/falcon.png';
}
$systemCurrency = $systemSettings['system_currency'] ?? 'PHP';

// Base params
$params = [
    'start_date' => $startDate . ' 00:00:00',
    'end_date' => $endDate . ' 23:59:59'
];
$reportWhere = [];
PosAccess::applyBranchScope($reportWhere, $params, 'po.branch_id', $user, 'financial_report_branch');
if ($invalidBranchFilter) {
    $reportWhere[] = '1 = 0';
} elseif ($branchId !== null) {
    $reportWhere[] = 'po.branch_id = :branch_filter';
    $params['branch_filter'] = $branchId;
}
$branchFilter = $reportWhere ? ' AND ' . implode(' AND ', $reportWhere) : '';


// Summary P&L
$summary = Database::fetch(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.grand_total ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_cost ELSE 0 END), 0) AS total_cost,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_profit ELSE 0 END), 0) AS gross_profit,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.discount_total ELSE 0 END), 0) AS total_discounts,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_service_fees ELSE 0 END), 0) AS total_service_fees,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_add_ons ELSE 0 END), 0) AS total_add_ons,
        COALESCE(SUM(CASE WHEN po.status IN ('refunded','cancelled') THEN po.total_refunded_amount ELSE 0 END), 0) AS total_refunds,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_profit ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN po.status IN ('refunded','cancelled') THEN po.total_refunded_amount ELSE 0 END), 0) AS net_profit
     FROM pos_orders po
     WHERE po.created_at BETWEEN :start_date AND :end_date
     $branchFilter",
    $params
);

// Daily breakdown for the period
$dailyData = Database::fetchAll(
    "SELECT
        DATE(po.created_at) AS report_date,
        COUNT(*) AS orders,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.grand_total ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_cost ELSE 0 END), 0) AS cost,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_profit ELSE 0 END), 0) AS gross_profit,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.discount_total ELSE 0 END), 0) AS discounts,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_service_fees ELSE 0 END), 0) AS service_fees,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_add_ons ELSE 0 END), 0) AS add_ons,
        COALESCE(SUM(CASE WHEN po.status IN ('refunded','cancelled') THEN po.total_refunded_amount ELSE 0 END), 0) AS refunds,
        COALESCE(SUM(CASE WHEN po.status = 'completed' THEN po.total_profit ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN po.status IN ('refunded','cancelled') THEN po.total_refunded_amount ELSE 0 END), 0) AS net_profit
     FROM pos_orders po
     WHERE po.created_at BETWEEN :start_date AND :end_date
     $branchFilter
     GROUP BY DATE(po.created_at)
     ORDER BY DATE(po.created_at)",
    $params
);

$reportData = [
    'summary' => $summary,
    'daily' => $dailyData,
    'filters' => [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'branch_id' => $branchId
    ],
    'system' => [
        'name' => $systemName,
        'logo' => $systemLogo,
        'currency' => $systemCurrency,
        'company_address' => htmlspecialchars($systemSettings['company_address'] ?? ''),
        'company_contact_number' => htmlspecialchars($systemSettings['company_contact_number'] ?? '')
    ],
    'generated_at' => date('Y-m-d H:i:s'),
    'generated_by' => $user['full_name'] ?? ($user['username'] ?? 'Administrator')
];

include __DIR__ . '/views/index.php';
