<?php
/**
 * Refund Confirmations API
 * GET  - list cancellations with filters + pagination + stats
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = PosAccess::allowedBranchIds($user);

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

// Decode IDs if provided
if ($filterBranchId) {
    $decodedBranchId = IdEncoder::decode($filterBranchId);
    if ($decodedBranchId === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
        exit;
    }
    $filterBranchId = $decodedBranchId;
}
if ($filterWalletId) {
    $decodedWalletId = IdEncoder::decode($filterWalletId);
    if ($decodedWalletId === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid wallet ID']);
        exit;
    }
    $filterWalletId = $decodedWalletId;
}

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
PosAccess::applyBranchScope($where, $params, 'bb.branch_id', $user, 'refund_branch');
if ($filterBranchId) {
    try {
        PosAccess::assertBranchAccess($user, (int) $filterBranchId);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $where[] = "bb.branch_id = :filter_branch";
    $params['filter_branch'] = (int) $filterBranchId;
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
    $where[]           = "(tc.transaction_code LIKE :search OR pa.fullname LIKE :search OR CONCAT_WS(' ', e_request.first_name, e_request.last_name) LIKE :search OR CONCAT_WS(' ', e_responsible.first_name, e_responsible.last_name) LIKE :search OR tp.provider_name LIKE :search OR bb.branch_name LIKE :search)";
    $params['search']  = '%' . $filterSearch . '%';
}

$whereClause = implode(' AND ', $where);

$baseJoins = "FROM ticket_cancellations tc
     LEFT JOIN user_accounts ua_request  ON tc.requested_by  = ua_request.user_id
     LEFT JOIN employees e_request       ON ua_request.emp_id = e_request.emp_id
     LEFT JOIN user_accounts ua_approve  ON tc.approved_by   = ua_approve.user_id
     LEFT JOIN employees e_approve       ON ua_approve.emp_id = e_approve.emp_id
     LEFT JOIN user_accounts ua_responsible ON tc.responsible_user_id = ua_responsible.user_id
     LEFT JOIN employees e_responsible ON ua_responsible.emp_id = e_responsible.emp_id
     LEFT JOIN ticket_transactions tt     ON tc.transaction_id = tt.transaction_id
     LEFT JOIN provider_wallets pw        ON tt.wallet_id      = pw.wallet_id
     LEFT JOIN ticket_providers tp        ON pw.provider_id    = tp.provider_id
     LEFT JOIN provider_ticket_variants pv ON tt.variant_id    = pv.variant_id
     LEFT JOIN business_branches bb       ON tt.branch_id      = bb.branch_id
     LEFT JOIN business_branches wallet_bb ON pw.branch_id     = wallet_bb.branch_id
     LEFT JOIN passenger_accounts pa      ON tc.passenger_id   = pa.passenger_id";

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
        tc.operation_type,
        tc.reason_category,
        tc.responsibility,
        tc.responsible_user_id,
        tc.responsibility_cashier_session_id,
        tc.gross_refund_amount,
        tc.responsibility_amount,
        tc.void_fee,
        tc.void_service_fee,
        tc.lost_sales_void_fee,
        tc.lost_sales_service_fee,
        tc.adjustment_id,
        COALESCE(CONCAT_WS(' ', e_responsible.first_name, e_responsible.last_name), ua_responsible.username) AS responsible_cashier_name,
        tt.ticket_number,
        tt.wallet_id,
        tt.variant_id,
        pw.branch_id AS wallet_branch_id,
        pw.variant_id AS wallet_variant_id,
        CASE WHEN pw.variant_id IS NOT NULL THEN 1 ELSE 0 END AS wallet_is_variant,
        pw.status AS wallet_status,
        tc.cancellation_type,
        tc.refund_amount,
        tc.charge_amount,
        COALESCE(tc.cash_refund_amount, (tc.refund_amount - tc.charge_amount)) AS cash_refund_amount,
        tc.status,
        tc.reason,
        tc.requested_at,
        tc.approved_at,
        tc.rejection_reason,
        tc.remarks,
        COALESCE(CONCAT_WS(' ', e_request.first_name, e_request.last_name), ua_request.username) AS requested_by_name,
        COALESCE(CONCAT_WS(' ', e_approve.first_name, e_approve.last_name), ua_approve.username) AS approved_by_name,
        bb.branch_id,
        bb.branch_name,
        wallet_bb.branch_name AS wallet_branch_name,
        tp.provider_name,
        COALESCE(pv.variant_name, '') AS variant_name,
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

foreach ($rows as &$row) {
    $row['payment_sources'] = Database::fetchAll(
        "SELECT tp.payment_id, tp.amount, tp.confirmation_status,
                pm.method_name, pm.method_type, pm.tracks_credit,
                tp.bank_account_id, tp.charged_to_passenger_id,
                pa.fullname AS charged_to_passenger_name
         FROM transaction_payments tp
         JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
         LEFT JOIN passenger_accounts pa ON pa.passenger_id = tp.charged_to_passenger_id
         WHERE tp.source_type = 'TICKET_TRANSACTION'
           AND tp.source_id = :transaction_id
           AND tp.confirmation_status <> 'REJECTED'
         ORDER BY CASE WHEN pm.tracks_credit = 1 THEN 0 ELSE 1 END,
                  tp.payment_id ASC",
        ['transaction_id' => (int) $row['transaction_id']]
    );
}
unset($row);

// Stats (scoped to user's branch, NOT current filters)
$statsWhere  = ['1=1'];
$statsParams = [];
PosAccess::applyBranchScope($statsWhere, $statsParams, 'bb.branch_id', $user, 'refund_stats_branch');
$statsWhereClause = implode(' AND ', $statsWhere);

$stats = Database::fetch(
    "SELECT
        SUM(CASE WHEN tc.status='pending'   THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tc.status='approved'  THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN tc.status='rejected'  THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN tc.status='completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN tc.status='pending'   THEN tc.refund_amount ELSE 0 END) AS pending_total_amount,
        SUM(CASE WHEN tc.status='pending'   THEN COALESCE(tc.cash_refund_amount, (tc.refund_amount - tc.charge_amount)) ELSE 0 END) AS pending_cash_amount,
        SUM(CASE WHEN tc.status='pending'   THEN tc.charge_amount ELSE 0 END) AS pending_charge_amount
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
