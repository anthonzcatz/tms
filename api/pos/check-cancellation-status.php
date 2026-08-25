<?php
/**
 * Check Cancellation Status API — Check if a transaction has a pending cancellation
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$txnCode = $_GET['transaction_code'] ?? null;

if (!$txnCode) {
    echo json_encode(['success' => false, 'error' => 'Transaction code required']);
    exit;
}

$user = Auth::user();
$allowedBranchIds = PosAccess::allowedBranchIds($user);
$isAdmin = $allowedBranchIds === null;

// Determine whether this is a ticket or service code by looking up both tables
$serviceTxn = Database::fetch(
    "SELECT service_txn_id FROM service_transactions WHERE transaction_code = :code",
    ['code' => $txnCode]
);
$isService = !empty($serviceTxn);

$branchScopeSql = '';
$branchScopeParams = [];
if (!$isAdmin) {
    if (!$allowedBranchIds) {
        $branchScopeSql = ' AND 1 = 0 ';
    } else {
        $branchPlaceholders = [];
        foreach (array_values($allowedBranchIds) as $index => $allowedBranchId) {
            $key = 'branch_scope_' . $index;
            $branchPlaceholders[] = ':' . $key;
            $branchScopeParams[$key] = $allowedBranchId;
        }
        $branchScopeSql = ' AND %s IN (' . implode(',', $branchPlaceholders) . ') ';
    }
}

if ($isService) {
    // Pending service cancellation
    $serviceBranchScopeSql = sprintf($branchScopeSql, 'st.branch_id');
    $pendingSql = "SELECT sc.*, COALESCE(CONCAT(e.first_name, ' ', e.last_name), ua.username) as requested_by_name
                   FROM service_cancellations sc
                   LEFT JOIN service_transactions st ON sc.service_transaction_id = st.service_txn_id
                   LEFT JOIN user_accounts ua ON sc.requested_by = ua.user_id
                   LEFT JOIN employees e ON ua.emp_id = e.emp_id
                   WHERE sc.transaction_code = :code AND sc.status = 'pending' " .
                   $serviceBranchScopeSql .
                   "ORDER BY sc.requested_at DESC LIMIT 1";

    $historySql = "SELECT sc.*,
                          COALESCE(CONCAT(e1.first_name, ' ', e1.last_name), ua1.username) as requested_by_name,
                          COALESCE(CONCAT(e2.first_name, ' ', e2.last_name), ua2.username) as approved_by_name
                   FROM service_cancellations sc
                   LEFT JOIN service_transactions st ON sc.service_transaction_id = st.service_txn_id
                   LEFT JOIN user_accounts ua1 ON sc.requested_by = ua1.user_id
                   LEFT JOIN employees e1 ON ua1.emp_id = e1.emp_id
                   LEFT JOIN user_accounts ua2 ON sc.approved_by = ua2.user_id
                   LEFT JOIN employees e2 ON ua2.emp_id = e2.emp_id
                   WHERE sc.transaction_code = :code " .
                   $serviceBranchScopeSql .
                   "ORDER BY sc.requested_at DESC";

    $params = array_merge(['code' => $txnCode], $branchScopeParams);
} else {
    // Pending ticket cancellation
    $ticketBranchScopeSql = sprintf($branchScopeSql, 'tt.branch_id');
    $pendingSql = "SELECT tc.*, COALESCE(CONCAT(e.first_name, ' ', e.last_name), ua.username) as requested_by_name
                   FROM ticket_cancellations tc
                   LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
                   LEFT JOIN user_accounts ua ON tc.requested_by = ua.user_id
                   LEFT JOIN employees e ON ua.emp_id = e.emp_id
                   WHERE tc.transaction_code = :code AND tc.status = 'pending' " .
                   $ticketBranchScopeSql .
                   "ORDER BY tc.requested_at DESC LIMIT 1";

    $historySql = "SELECT tc.*,
                          COALESCE(CONCAT(e1.first_name, ' ', e1.last_name), ua1.username) as requested_by_name,
                          COALESCE(CONCAT(e2.first_name, ' ', e2.last_name), ua2.username) as approved_by_name
                   FROM ticket_cancellations tc
                   LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
                   LEFT JOIN user_accounts ua1 ON tc.requested_by = ua1.user_id
                   LEFT JOIN employees e1 ON ua1.emp_id = e1.emp_id
                   LEFT JOIN user_accounts ua2 ON tc.approved_by = ua2.user_id
                   LEFT JOIN employees e2 ON ua2.emp_id = e2.emp_id
                   WHERE tc.transaction_code = :code " .
                   $ticketBranchScopeSql .
                   "ORDER BY tc.requested_at DESC";

    $params = array_merge(['code' => $txnCode], $branchScopeParams);
}

$pendingCancellation = Database::fetch($pendingSql, $params);
$cancellationHistory = Database::fetchAll($historySql, $params);

echo json_encode([
    'success' => true,
    'has_pending_cancellation' => !empty($pendingCancellation),
    'pending_cancellation' => $pendingCancellation,
    'cancellation_history' => $cancellationHistory
]);
