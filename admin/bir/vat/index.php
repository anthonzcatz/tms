<?php
/**
 * BIR VAT Management Controller
 * VAT computation, exemption types, and VAT transaction tracking
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$vatWhere = [];
$vatParams = [];
PosAccess::applyBranchScope($vatWhere, $vatParams, 'po.branch_id', $user, 'bir_vat_branch');
$vatFilter = $vatWhere ? ' AND ' . implode(' AND ', $vatWhere) : '';
$vatWhereClause = $vatWhere ? 'WHERE ' . implode(' AND ', $vatWhere) : '';


$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';

// Fetch VAT settings
$vatSettings = Database::fetch(
    "SELECT bir_vat_rate, bir_accreditation_number FROM system_settings WHERE setting_id = 1"
);

// Fetch VAT transactions summary
$vatSummary = Database::fetch(
    "SELECT 
        SUM(CASE WHEN vt.vat_type = '12_percent' THEN vt.vat_amount ELSE 0 END) as vat_12_percent,
        SUM(CASE WHEN vt.vat_type = 'exempt' THEN vt.non_taxable_amount ELSE 0 END) as vat_exempt,
        SUM(CASE WHEN vt.vat_type = 'zero_rated' THEN vt.non_taxable_amount ELSE 0 END) as vat_zero_rated,
        SUM(vt.vat_amount) as total_vat_collected,
        SUM(vt.taxable_amount) as total_taxable_sales,
        COUNT(*) as total_transactions
     FROM bir_vat_transactions vt
     LEFT JOIN pos_orders po ON vt.order_id = po.order_id
     WHERE DATE(vt.created_at) = CURDATE(){$vatFilter}",
    $vatParams
);

// Fetch VAT transactions by type for today
$vatByType = Database::fetchAll(
    "SELECT 
        vt.vat_type,
        vt.exemption_type,
        COUNT(*) as transaction_count,
        SUM(vt.vat_amount) as vat_amount,
        SUM(vt.taxable_amount) as taxable_amount,
        SUM(vt.non_taxable_amount) as non_taxable_amount
     FROM bir_vat_transactions vt
     LEFT JOIN pos_orders po ON vt.order_id = po.order_id
     WHERE DATE(vt.created_at) = CURDATE(){$vatFilter}
     GROUP BY vt.vat_type, vt.exemption_type
     ORDER BY vt.vat_type",
    $vatParams
);

// Fetch recent VAT transactions
$recentTransactions = Database::fetchAll(
    "SELECT vt.*, po.order_code, po.grand_total, bb.branch_name
     FROM bir_vat_transactions vt
     LEFT JOIN pos_orders po ON vt.order_id = po.order_id
     LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
     {$vatWhereClause}
     ORDER BY vt.created_at DESC
     LIMIT 50",
    $vatParams
);

// Monthly VAT summary
$monthlyVat = Database::fetchAll(
    "SELECT 
        DATE_FORMAT(vt.created_at, '%Y-%m') as month,
        SUM(vt.vat_amount) as vat_amount,
        SUM(vt.taxable_amount) as taxable_amount,
        COUNT(*) as transaction_count
     FROM bir_vat_transactions vt
     LEFT JOIN pos_orders po ON vt.order_id = po.order_id
     WHERE vt.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH){$vatFilter}
     GROUP BY DATE_FORMAT(vt.created_at, '%Y-%m')
     ORDER BY month DESC",
    $vatParams
);

$viewData = [
    'vatSettings' => $vatSettings,
    'vatSummary' => $vatSummary,
    'vatByType' => $vatByType,
    'recentTransactions' => $recentTransactions,
    'monthlyVat' => $monthlyVat,
    'message' => $message,
    'error' => $error,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
