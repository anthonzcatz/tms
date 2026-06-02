<?php
/**
 * BIR VAT Management Controller
 * VAT computation, exemption types, and VAT transaction tracking
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

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
        SUM(CASE WHEN vat_type = '12_percent' THEN vat_amount ELSE 0 END) as vat_12_percent,
        SUM(CASE WHEN vat_type = 'exempt' THEN non_taxable_amount ELSE 0 END) as vat_exempt,
        SUM(CASE WHEN vat_type = 'zero_rated' THEN non_taxable_amount ELSE 0 END) as vat_zero_rated,
        SUM(vat_amount) as total_vat_collected,
        SUM(taxable_amount) as total_taxable_sales,
        COUNT(*) as total_transactions
     FROM bir_vat_transactions
     WHERE DATE(created_at) = CURDATE()"
);

// Fetch VAT transactions by type for today
$vatByType = Database::fetchAll(
    "SELECT 
        vat_type,
        exemption_type,
        COUNT(*) as transaction_count,
        SUM(vat_amount) as vat_amount,
        SUM(taxable_amount) as taxable_amount,
        SUM(non_taxable_amount) as non_taxable_amount
     FROM bir_vat_transactions
     WHERE DATE(created_at) = CURDATE()
     GROUP BY vat_type, exemption_type
     ORDER BY vat_type"
);

// Fetch recent VAT transactions
$recentTransactions = Database::fetchAll(
    "SELECT vt.*, po.order_code, po.grand_total, bb.branch_name
     FROM bir_vat_transactions vt
     LEFT JOIN pos_orders po ON vt.order_id = po.order_id
     LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
     ORDER BY vt.created_at DESC
     LIMIT 50"
);

// Monthly VAT summary
$monthlyVat = Database::fetchAll(
    "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(vat_amount) as vat_amount,
        SUM(taxable_amount) as taxable_amount,
        COUNT(*) as transaction_count
     FROM bir_vat_transactions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month DESC"
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
