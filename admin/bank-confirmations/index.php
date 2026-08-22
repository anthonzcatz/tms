<?php
/**
 * Bank Transfer Confirmations Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
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
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

$userRoleCode  = $user['role_code'] ?? '';
$userBranchId  = $user['branch_id'] ?? null;
$realtimeBranchIds = $userRoleCode === 'SUPER_ADMIN'
    ? array_map('intval', array_column(Database::fetchAll("SELECT branch_id FROM business_branches"), 'branch_id'))
    : array_values(array_filter(array_map('intval', explode(',', (string) $userBranchId))));
$pusherConfigured = PusherService::isConfigured();
$pusherKey = $pusherConfigured ? env('PUSHER_KEY', '') : '';
$pusherCluster = $pusherConfigured ? env('PUSHER_CLUSTER', 'ap1') : 'ap1';

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
$chargeStatusWhere = $statusFilter !== 'ALL' ? "AND cp.confirmation_status = :status" : '';
$chargeStatusParam = $statusFilter !== 'ALL' ? ['status' => $statusFilter] : [];

// Fetch pending / filtered bank transfers and charge collections
// Fetch transaction_payments
$transactionPayments = Database::fetchAll(
    "SELECT
            tp.payment_id,
            tp.amount,
            tp.reference_number,
            tp.confirmation_status,
            tp.confirmed_by,
            tp.confirmed_at,
            tp.created_at,
            tp.bank_account_id,
            tp.payment_method_id,
            tp.source_type,
            tp.source_id,
            pm.method_name, pm.method_type,
            ba.bank_name, ba.account_name, ba.account_number,
            COALESCE(CONCAT_WS(' ', e_cashier.first_name, e_cashier.last_name), ua_cashier.username) AS cashier_name,
            COALESCE(CONCAT_WS(' ', e_confirm.first_name, e_confirm.last_name), ua_confirm.username) AS confirmed_by_name,
            bb.branch_name,
            COALESCE(tt.transaction_code, st.transaction_code) AS service_txn_code,
            COALESCE(po.order_code, '') AS order_code,
            tt.ticket_number,
            tt.status AS ticket_status,
            COALESCE(st.total_amount, tt.total_amount) AS service_txn_total,
            COALESCE(stype.name, 'Ticket Sale') AS service_type_name,
            'PAYMENT' AS source_type_label,
            'PAYMENT' AS item_type,
            tp.confirmation_status AS item_status,
            NULL AS amount_paid
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     LEFT JOIN bank_accounts ba ON tp.bank_account_id = ba.bank_account_id
     LEFT JOIN user_accounts ua_cashier ON tp.created_by = ua_cashier.user_id
     LEFT JOIN employees e_cashier ON ua_cashier.emp_id = e_cashier.emp_id
     LEFT JOIN user_accounts ua_confirm ON tp.confirmed_by = ua_confirm.user_id
     LEFT JOIN employees e_confirm ON ua_confirm.emp_id = e_confirm.emp_id
     LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
     LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
     LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
     LEFT JOIN pos_order_items oi
        ON ((tp.source_type = 'TICKET_TRANSACTION' AND oi.item_type = 'TICKET' AND oi.reference_id = tt.transaction_id)
         OR (tp.source_type = 'SERVICE_TRANSACTION' AND oi.item_type = 'SERVICE' AND oi.reference_id = st.service_txn_id))
     LEFT JOIN pos_orders po ON oi.order_id = po.order_id
     LEFT JOIN business_branches bb ON COALESCE(st.branch_id, tt.branch_id) = bb.branch_id
     WHERE pm.requires_confirmation = 1
       {$statusWhere}
       {$branchWhere}
     ORDER BY tp.created_at DESC",
    array_merge($statusParam, $branchParam)
);

// Fetch charge_payments
$chargePayments = [];
$chargeStatusParam = $statusFilter !== 'ALL' ? ['status' => $statusFilter] : [];
$chargeStatusWhere = $statusFilter !== 'ALL' ? "AND cp.confirmation_status = :status" : '';
$chargeBranchWhere = '';
$chargeBranchParam = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $chargeBranchWhere = 'AND bb.branch_id = :branch_id';
    $chargeBranchParam['branch_id'] = $userBranchId;
}
$chargePayments = Database::fetchAll(
    "SELECT
            cp.charge_payment_id AS payment_id,
            cp.payment_code,
            cp.passenger_id,
            cp.amount_paid,
            cp.amount_paid AS amount,
            cp.reference_number,
            cp.confirmation_status,
            cp.confirmed_by,
            cp.confirmed_at,
            cp.created_at,
            cp.bank_account_id,
            cp.payment_method_id,
            pm.method_name, pm.method_type,
            ba.bank_name, ba.account_name, ba.account_number,
            COALESCE(CONCAT_WS(' ', e_cashier.first_name, e_cashier.last_name), ua_cashier.username) AS cashier_name,
            COALESCE(CONCAT_WS(' ', e_confirm.first_name, e_confirm.last_name), ua_confirm.username) AS confirmed_by_name,
            bb.branch_name,
            cp.payment_code AS service_txn_code,
            cp.amount_paid AS service_txn_total,
            'Charge Collection' AS service_type_name,
            'CHARGE' AS item_type,
            cp.confirmation_status AS item_status
     FROM charge_payments cp
     JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
     LEFT JOIN bank_accounts ba ON cp.bank_account_id = ba.bank_account_id
     LEFT JOIN user_accounts ua_cashier ON cp.created_by = ua_cashier.user_id
     LEFT JOIN employees e_cashier ON ua_cashier.emp_id = e_cashier.emp_id
     LEFT JOIN user_accounts ua_confirm ON cp.confirmed_by = ua_confirm.user_id
     LEFT JOIN employees e_confirm ON ua_confirm.emp_id = e_confirm.emp_id
     LEFT JOIN business_branches bb ON cp.branch_id = bb.branch_id
     WHERE cp.bank_account_id IS NOT NULL
       {$chargeStatusWhere}
       {$chargeBranchWhere}
     ORDER BY cp.created_at DESC",
    array_merge($chargeStatusParam, $chargeBranchParam)
);

$depositStatusWhere = $statusFilter !== 'ALL' ? "AND bt.confirmation_status = :status" : '';
$depositStatusParam = $statusFilter !== 'ALL' ? ['status' => $statusFilter] : [];
$depositBranchWhere = '';
$depositBranchParam = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $depositBranchWhere = 'AND COALESCE(ba.branch_id, cs.branch_id) = :branch_id';
    $depositBranchParam['branch_id'] = $userBranchId;
}
$depositPayments = Database::fetchAll(
    "SELECT
            bt.bank_txn_id AS payment_id,
            bt.txn_code AS service_txn_code,
            bt.amount,
            bt.confirmation_status,
            bt.confirmed_by,
            bt.confirmed_at,
            bt.created_at,
            bt.bank_account_id,
            'Cash Deposit' AS method_name,
            'CASH_DEPOSIT' AS method_type,
            ba.bank_name, ba.account_name, ba.account_number,
            COALESCE(CONCAT_WS(' ', e_cashier.first_name, e_cashier.last_name), ua_cashier.username) AS cashier_name,
            COALESCE(CONCAT_WS(' ', e_confirm.first_name, e_confirm.last_name), ua_confirm.username) AS confirmed_by_name,
            COALESCE(bb.branch_name, session_bb.branch_name) AS branch_name,
            'Cash Deposit' AS service_type_name,
            'DEPOSIT' AS item_type,
            bt.confirmation_status AS item_status,
            NULL AS amount_paid
     FROM bank_transactions bt
     JOIN bank_accounts ba ON bt.bank_account_id = ba.bank_account_id
     LEFT JOIN business_branches bb ON ba.branch_id = bb.branch_id
     LEFT JOIN cashier_sessions cs ON bt.reference_table = 'cashier_sessions' AND bt.reference_id = cs.session_id
     LEFT JOIN business_branches session_bb ON cs.branch_id = session_bb.branch_id
     LEFT JOIN user_accounts ua_cashier ON bt.created_by = ua_cashier.user_id
     LEFT JOIN employees e_cashier ON ua_cashier.emp_id = e_cashier.emp_id
     LEFT JOIN user_accounts ua_confirm ON bt.confirmed_by = ua_confirm.user_id
     LEFT JOIN employees e_confirm ON ua_confirm.emp_id = e_confirm.emp_id
     WHERE bt.txn_type = 'DEPOSIT'
       {$depositStatusWhere}
       {$depositBranchWhere}
     ORDER BY bt.created_at DESC",
    array_merge($depositStatusParam, $depositBranchParam)
);

// Merge and sort by date
$payments = array_merge($transactionPayments, $chargePayments, $depositPayments);
usort($payments, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Stats (include both transaction_payments and charge_payments)
$paymentStats = Database::fetch(
    "SELECT
        SUM(CASE WHEN tp.confirmation_status='PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tp.confirmation_status='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN tp.confirmation_status='REJECTED' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN tp.confirmation_status='PENDING' THEN tp.amount ELSE 0 END) AS pending_amount
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     WHERE pm.requires_confirmation = 1"
);

$chargeStats = Database::fetch(
    "SELECT
        SUM(CASE WHEN cp.confirmation_status='PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN cp.confirmation_status='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN cp.confirmation_status='REJECTED' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN cp.confirmation_status='PENDING' THEN cp.amount_paid ELSE 0 END) AS pending_amount
     FROM charge_payments cp
     WHERE cp.bank_account_id IS NOT NULL"
);

$statCounts = [
    'pending_count' => ($paymentStats['pending_count'] ?? 0) + ($chargeStats['pending_count'] ?? 0),
    'confirmed_count' => ($paymentStats['confirmed_count'] ?? 0) + ($chargeStats['confirmed_count'] ?? 0),
    'rejected_count' => ($paymentStats['rejected_count'] ?? 0) + ($chargeStats['rejected_count'] ?? 0),
    'pending_amount' => ($paymentStats['pending_amount'] ?? 0) + ($chargeStats['pending_amount'] ?? 0)
];

$depositStats = Database::fetch(
    "SELECT
        SUM(CASE WHEN bt.confirmation_status='PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN bt.confirmation_status='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN bt.confirmation_status='REJECTED' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN bt.confirmation_status='PENDING' THEN bt.amount ELSE 0 END) AS pending_amount
     FROM bank_transactions bt
     WHERE bt.txn_type = 'DEPOSIT'"
);

$statCounts = [
    'pending_count' => ($paymentStats['pending_count'] ?? 0) + ($depositStats['pending_count'] ?? 0),
    'confirmed_count' => ($paymentStats['confirmed_count'] ?? 0) + ($depositStats['confirmed_count'] ?? 0),
    'rejected_count' => ($paymentStats['rejected_count'] ?? 0) + ($depositStats['rejected_count'] ?? 0),
    'pending_amount' => ($paymentStats['pending_amount'] ?? 0) + ($depositStats['pending_amount'] ?? 0)
];

include __DIR__ . '/views/index.php';
