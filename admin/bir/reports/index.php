<?php
/**
 * BIR Reports Controller
 * Generate DSR, Monthly Sales, SLS, Alphalist, and 2550M reports
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';
$reportData = null;

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $reportType = $_POST['report_type'] ?? '';
        $branchId = $_POST['branch_id'] ?? null;
        $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
        $dateTo = $_POST['date_to'] ?? date('Y-m-d');
        
        switch ($reportType) {
            case 'DSR':
                $reportData = generateDSR($branchId, $dateFrom);
                break;
            case 'Monthly':
                $reportData = generateMonthlyReport($branchId, $dateFrom, $dateTo);
                break;
            case 'SLS':
                $reportData = generateSLS($branchId, $dateFrom, $dateTo);
                break;
            case '2550M':
                $reportData = generate2550M($branchId, $dateFrom, $dateTo);
                break;
        }
        
        if ($reportData) {
            // Save report to database
            Database::execute(
                "INSERT INTO bir_reports (report_type, report_date, report_period_start, report_period_end, branch_id, generated_by, data_json, status) 
                 VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'generated')",
                [$reportType, $dateFrom, $dateTo, $branchId, $user['user_id'], json_encode($reportData)]
            );
            $message = $reportType . ' report generated successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error generating report: ' . $e->getMessage();
    }
}

// Fetch generated reports
$reports = Database::fetchAll(
    "SELECT r.*, b.branch_name, u.fullname as generated_by_name
     FROM bir_reports r
     LEFT JOIN business_branches b ON r.branch_id = b.branch_id
     LEFT JOIN user_accounts u ON r.generated_by = u.user_id
     ORDER BY r.created_at DESC
     LIMIT 50"
);

// Fetch branches
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

// Helper functions for report generation
function generateDSR($branchId, $date) {
    $branchFilter = $branchId ? "AND po.branch_id = $branchId" : "";
    
    $summary = Database::fetch(
        "SELECT 
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.vat_amount), 0) as total_vat,
            COALESCE(SUM(po.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(po.non_taxable_amount), 0) as non_taxable_sales,
            COALESCE(SUM(CASE WHEN po.status = 'void' THEN 1 ELSE 0 END), 0) as void_count,
            COALESCE(SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_count
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter",
        [$date]
    );
    
    $paymentBreakdown = Database::fetchAll(
        "SELECT 
            pm.method_name as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_amount
         FROM pos_orders po
         LEFT JOIN payment_methods pm ON po.payment_method_id = pm.method_id
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter
         GROUP BY pm.method_name",
        [$date]
    );
    
    $hourlySales = Database::fetchAll(
        "SELECT 
            HOUR(po.created_at) as hour,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter
         GROUP BY HOUR(po.created_at)
         ORDER BY hour",
        [$date]
    );
    
    return [
        'report_type' => 'DSR',
        'date' => $date,
        'summary' => $summary,
        'payment_breakdown' => $paymentBreakdown,
        'hourly_sales' => $hourlySales
    ];
}

function generateMonthlyReport($branchId, $dateFrom, $dateTo) {
    $branchFilter = $branchId ? "AND po.branch_id = $branchId" : "";
    
    $summary = Database::fetch(
        "SELECT 
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.vat_amount), 0) as total_vat,
            COALESCE(SUM(po.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(po.non_taxable_amount), 0) as non_taxable_sales
         FROM pos_orders po
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        [$dateFrom, $dateTo]
    );
    
    $dailyBreakdown = Database::fetchAll(
        "SELECT 
            DATE(po.created_at) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.vat_amount), 0) as vat_amount
         FROM pos_orders po
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY DATE(po.created_at)
         ORDER BY date",
        [$dateFrom, $dateTo]
    );
    
    return [
        'report_type' => 'Monthly',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'daily_breakdown' => $dailyBreakdown
    ];
}

function generateSLS($branchId, $dateFrom, $dateTo) {
    $branchFilter = $branchId ? "AND po.branch_id = $branchId" : "";
    
    $transactions = Database::fetchAll(
        "SELECT 
            po.order_code,
            po.created_at,
            bb.branch_name,
            po.grand_total,
            po.vat_amount,
            po.vat_type,
            po.status,
            orn.or_full_number
         FROM pos_orders po
         LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
         LEFT JOIN bir_or_numbers orn ON po.or_number_id = orn.or_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter
         ORDER BY po.created_at DESC",
        [$dateFrom, $dateTo]
    );
    
    $summary = Database::fetch(
        "SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.vat_amount), 0) as total_vat
         FROM pos_orders po
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter",
        [$dateFrom, $dateTo]
    );
    
    return [
        'report_type' => 'SLS',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'transactions' => $transactions
    ];
}

function generate2550M($branchId, $dateFrom, $dateTo) {
    $branchFilter = $branchId ? "AND po.branch_id = $branchId" : "";
    
    $outputVat = Database::fetch(
        "SELECT COALESCE(SUM(po.vat_amount), 0) as output_vat
         FROM pos_orders po
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        [$dateFrom, $dateTo]
    );
    
    $vatBreakdown = Database::fetchAll(
        "SELECT 
            po.vat_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.vat_amount), 0) as vat_amount,
            COALESCE(SUM(po.taxable_amount), 0) as taxable_amount
         FROM pos_orders po
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY po.vat_type",
        [$dateFrom, $dateTo]
    );
    
    return [
        'report_type' => '2550M',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'output_vat' => $outputVat,
        'vat_breakdown' => $vatBreakdown
    ];
}

$viewData = [
    'reports' => $reports,
    'branches' => $branches,
    'message' => $message,
    'error' => $error,
    'reportData' => $reportData,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
