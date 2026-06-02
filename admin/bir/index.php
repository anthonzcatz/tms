<?php
/**
 * BIR Module - Main Controller
 * BIR Accredited System Dashboard
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Check permission - only admin, manager, accountant can access
$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/dashboard/');
    exit;
}

// Fetch BIR settings
$birSettings = Database::fetch(
    "SELECT bir_accreditation_number, bir_accreditation_expiry, bir_permit_number, 
            bir_validity_from, bir_validity_to, bir_min, bir_machine_serial, 
            bir_vat_rate, company_tin, company_name, company_address
     FROM system_settings 
     WHERE setting_id = 1"
);

// Fetch OR statistics
$orStats = Database::fetch(
    "SELECT 
        COUNT(*) as total_or,
        SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_or,
        SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_or,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_or
     FROM bir_or_numbers
     WHERE DATE(created_at) = CURDATE()"
);

// Fetch today's sales with VAT
$todaySales = Database::fetch(
    "SELECT 
        COUNT(DISTINCT po.order_id) as total_orders,
        COALESCE(SUM(po.grand_total), 0) as total_sales,
        COALESCE(SUM(vt.vat_amount), 0) as total_vat,
        COALESCE(SUM(vt.taxable_amount), 0) as taxable_amount,
        COALESCE(SUM(vt.non_taxable_amount), 0) as non_taxable_amount
     FROM pos_orders po
     LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
     WHERE DATE(po.created_at) = CURDATE()
     AND po.status = 'completed'"
);

// Fetch active OR series
$orSeries = Database::fetchAll(
    "SELECT s.*, b.branch_name
     FROM bir_or_series s
     LEFT JOIN business_branches b ON s.branch_id = b.branch_id
     WHERE s.status = 'active'
     ORDER BY s.year DESC, b.branch_name ASC"
);

// Fetch POS machines
$machines = Database::fetchAll(
    "SELECT m.*, b.branch_name
     FROM bir_pos_machines m
     LEFT JOIN business_branches b ON m.branch_id = b.branch_id
     ORDER BY m.status ASC, b.branch_name ASC"
);

// Check accreditation expiry warning
$accreditationWarning = false;
if ($birSettings['bir_accreditation_expiry']) {
    $expiryDate = new DateTime($birSettings['bir_accreditation_expiry']);
    $today = new DateTime();
    $daysUntilExpiry = $today->diff($expiryDate)->days;
    $accreditationWarning = $expiryDate < $today || $daysUntilExpiry <= 30;
}

// Pass data to view
$viewData = [
    'birSettings' => $birSettings,
    'orStats' => $orStats,
    'todaySales' => $todaySales,
    'orSeries' => $orSeries,
    'machines' => $machines,
    'accreditationWarning' => $accreditationWarning,
    'userRoleCode' => $userRoleCode,
    'userBranchId' => $userBranchId
];

extract($viewData);

include __DIR__ . '/views/index.php';
