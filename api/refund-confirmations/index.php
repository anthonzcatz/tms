<?php
/**
 * Refund Confirmations API
 * GET  - list cancellations with filters + pagination + stats
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Permission check
if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::canAccessModule('admin/refund-confirmations/')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// --- Filters ---
$statusFilter   = $_GET['status']    ?? 'pending';
$validStatuses  = ['pending', 'approved', 'rejected', 'completed', 'all'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'pending';

$filterBranchId = $_GET['branch_id'] ?? null;
$filterWalletId = $_GET['wallet_id'] ?? null;
$filterCashier  = trim($_GET['cashier']    ?? '');
$filterDateFrom = $_GET['date_from'] ?? null;
$filterDateTo   = $_GET['date_to']   ?? null;
$filterSearch   = trim($_GET['search']     ?? '');

// Pagination
$limit  = max(1, min(100, (int)($_GET['limit']  ?? 15)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// --- Build WHERE ---
$where  = ['1=1'];
$params = [];

if ($statusFilter !== 'all') {
    $where[]          = "tc.status = :status";
    $params['status'] = $statusFilter;
}

// Branch scoping
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $where[]             = "bb.branch_id = :branch_id";
    $params['branch_id'] = $userBranchId;
} elseif ($filterBranchId) {
    $where[]             = "bb.branch_id = :branch_id";
    $params['branch_id'] = (int)$filterBranchId;
}

if ($filterWalletId) {
    $where[]             = "tt.wallet_id = :wallet_id";
    $params['wallet_id'] = (int)$filterWalletId;
}

if ($filterCashier) {
    $where[]           = "(CONCAT_WS(' ', e_request.first_name, e_request.last_name) LIKE :cashier OR ua_request.username LIKE :cashier)";
    $params['cashier'] = '%' . $filterCashier . '%';
}

if ($filterDateFrom && $filterDateTo) {
    $where[]             = "DATE(tc.requested_at) BETWEEN :date_from AND :date_to";
    $params['date_from'] = $filterDateFrom;
    $params['date_to']   = $filterDateTo;
} elseif ($filterDateFrom) {
    $where[]             = "DATE(tc.requested_at) = :date_from";
    $params['date_from'] = $filterDateFrom;
}

if ($filterSearch) {
    $where[]           = "(tc.transaction_code LIKE :search OR pa.fullname LIKE :search OR CONCAT_WS(' ', e_request.first_name, e_request.last_name) LIKE :search OR tp.provider_name LIKE :search OR bb.branch_name LIKE :search)";
    $params['search']  = '%' . $filterSearch . '%';
}

$whereClause = implode(' AND ', $where);

$baseJoins = "FROM ticket_cancellations tc
     LEFT JOIN user_accounts ua_request  ON tc.requested_by  = ua_request.user_id
     LEFT JOIN employees e_request       ON ua_request.emp_id = e_request.emp_id
     LEFT JOIN user_accounts ua_approve  ON tc.approved_by   = ua_approve.user_id
     LEFT JOIN employees e_approve       ON ua_approve.emp_id = e_approve.emp_id
     LEFT JOIN ticket_transactions tt    ON tc.transaction_id = tt.transaction_id
     LEFT JOIN provider_wallets pw       ON tt.wallet_id      = pw.wallet_id
     LEFT JOIN ticket_providers tp       ON pw.provider_id    = tp.provider_id
     LEFT JOIN business_branches bb      ON tt.branch_id      = bb.branch_id
     LEFT JOIN passenger_accounts pa     ON tc.passenger_id   = pa.passenger_id";

// Total count for pagination
$countSql = "SELECT COUNT(*) as total $baseJoins WHERE $whereClause";
$countRow = Database::fetch($countSql, $params);
$total    = (int)($countRow['total'] ?? 0);
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

// Paginated data
$dataSql = "SELECT
        tc.cancellation_id,
        tc.transaction_id,
        tc.transaction_code,
        tc.cancellation_type,
        tc.refund_amount,
        tc.status,
        tc.reason,
        tc.requested_at,
        tc.approved_at,
        tc.rejection_reason,
        tc.remarks,
        COALESCE(CONCAT_WS(' ', e_request.first_name, e_request.last_name), ua_request.username) AS requested_by_name,
        COALESCE(CONCAT_WS(' ', e_approve.first_name, e_approve.last_name), ua_approve.username) AS approved_by_name,
        bb.branch_name,
        tp.provider_name,
        tt.travel_date,
        tt.origin,
        tt.destination,
        pa.fullname AS passenger_name
    $baseJoins
    WHERE $whereClause
    ORDER BY tc.requested_at DESC
    LIMIT :limit OFFSET :offset";

$dataParams          = $params;
$dataParams['limit'] = $limit;
$dataParams['offset']= $offset;

$rows = Database::fetchAll($dataSql, $dataParams);

// Stats (scoped to user's branch, NOT current filters)
$statsWhere  = ['1=1'];
$statsParams = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $statsWhere[]              = "bb.branch_id = :branch_id";
    $statsParams['branch_id']  = $userBranchId;
}
$statsWhereClause = implode(' AND ', $statsWhere);

$stats = Database::fetch(
    "SELECT
        SUM(CASE WHEN tc.status='pending'   THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tc.status='approved'  THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN tc.status='rejected'  THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN tc.status='completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN tc.status='pending'   THEN tc.refund_amount ELSE 0 END) AS pending_amount
     FROM ticket_cancellations tc
     LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
     LEFT JOIN business_branches bb   ON tt.branch_id = bb.branch_id
     WHERE $statsWhereClause",
    $statsParams
);

echo json_encode([
    'success'    => true,
    'data'       => $rows,
    'pagination' => [
        'total'       => $total,
        'per_page'    => $limit,
        'current_page'=> $page,
        'total_pages' => $totalPages,
        'from'        => $total > 0 ? $offset + 1 : 0,
        'to'          => min($offset + $limit, $total),
    ],
    'stats' => $stats,
]);
