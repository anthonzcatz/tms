<?php
/**
 * Bank Transfer Confirmations Controller
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
} elseif (!Auth::canAccessModule('admin/bank-confirmations/')) {
    $message = 'You do not have permission to access Bank Transfer Confirmations.';
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
$statusFilter = $_GET['status'] ?? 'PENDING';
$validStatuses = ['PENDING', 'CONFIRMED', 'REJECTED', 'ALL'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'PENDING';

$statusWhere = $statusFilter !== 'ALL' ? "AND tp.confirmation_status = :status" : '';
$statusParam = $statusFilter !== 'ALL' ? ['status' => $statusFilter] : [];

// Fetch pending / filtered bank transfers
$payments = Database::fetchAll(
    "SELECT tp.*,
            pm.method_name, pm.method_type,
            ba.bank_name, ba.account_name, ba.account_number,
            COALESCE(CONCAT_WS(' ', e_cashier.first_name, e_cashier.last_name), ua_cashier.username) AS cashier_name,
            COALESCE(CONCAT_WS(' ', e_confirm.first_name, e_confirm.last_name), ua_confirm.username) AS confirmed_by_name,
            bb.branch_name,
            st.transaction_code AS service_txn_code,
            st.total_amount AS service_txn_total,
            stype.name AS service_type_name
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     LEFT JOIN bank_accounts ba ON tp.bank_account_id = ba.bank_account_id
     LEFT JOIN user_accounts ua_cashier ON tp.created_by = ua_cashier.user_id
     LEFT JOIN employees e_cashier ON ua_cashier.emp_id = e_cashier.emp_id
     LEFT JOIN user_accounts ua_confirm ON tp.confirmed_by = ua_confirm.user_id
     LEFT JOIN employees e_confirm ON ua_confirm.emp_id = e_confirm.emp_id
     LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
     LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
     LEFT JOIN business_branches bb ON st.branch_id = bb.branch_id
     WHERE pm.requires_confirmation = 1
       {$statusWhere}
       {$branchWhere}
     ORDER BY tp.created_at DESC",
    array_merge($statusParam, $branchParam)
);

// Stats
$statCounts = Database::fetch(
    "SELECT
        SUM(CASE WHEN tp.confirmation_status='PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tp.confirmation_status='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN tp.confirmation_status='REJECTED' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN tp.confirmation_status='PENDING' THEN tp.amount ELSE 0 END) AS pending_amount
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     WHERE pm.requires_confirmation = 1"
);

include __DIR__ . '/views/index.php';
