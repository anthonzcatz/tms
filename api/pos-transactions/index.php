<?php
/**
 * POS Transactions Report API
 * GET - list all pos_orders with comprehensive filters + pagination + stats
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
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

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Check if ID encryption is enabled
$encryptIds = false;
try {
    $systemSettings = Database::fetch("SELECT encrypt_ids FROM system_settings WHERE setting_id = 1");
    if ($systemSettings && isset($systemSettings['encrypt_ids'])) {
        $encryptIds = $systemSettings['encrypt_ids'] == 1;
    }
} catch (Exception $e) {
    // Default to false if table doesn't exist or query fails
    $encryptIds = false;
}

if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::canAccessModule('admin/pos/transactions/')) {
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
$filterSearch    = trim($_GET['search']      ?? '');
$filterStatus    = $_GET['status']           ?? 'all';
$filterType      = $_GET['type']             ?? 'all';
$filterBranchId  = $_GET['branch_id']        ?? null;
$filterProviderId= $_GET['provider_id']      ?? null;
$filterProviderType = $_GET['provider_type'] ?? null;
$filterCashierId = $_GET['cashier_id']       ?? null;
$filterDateFrom  = $_GET['date_from']        ?? null;
$filterDateTo    = $_GET['date_to']          ?? null;
$filterPayMethod = $_GET['payment_method']   ?? null;

$limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$validStatuses = ['all', 'completed', 'pending', 'cancelled', 'refunded'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

// --- WHERE ---
$where  = ['1=1'];
$params = [];

// Branch scoping — non-super-admin locked to their branch
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $where[]             = 'o.branch_id = :branch_id';
    $params['branch_id'] = (int)$userBranchId;
} elseif ($filterBranchId) {
    $where[]             = 'o.branch_id = :branch_id';
    $params['branch_id'] = (int)$filterBranchId;
}

if ($filterStatus !== 'all') {
    $where[]          = 'o.status = :status';
    $params['status'] = $filterStatus;
}

if ($filterType === 'TICKET') {
    $where[] = "EXISTS (SELECT 1 FROM pos_order_items oi_t WHERE oi_t.order_id = o.order_id AND oi_t.item_type = 'TICKET')";
} elseif ($filterType === 'SERVICE') {
    $where[] = "NOT EXISTS (SELECT 1 FROM pos_order_items oi_t WHERE oi_t.order_id = o.order_id AND oi_t.item_type = 'TICKET')";
}

if ($filterCashierId) {
    $where[]              = 'o.created_by = :cashier_id';
    $params['cashier_id'] = (int)$filterCashierId;
}

if ($filterProviderId) {
    $where[] = "EXISTS (
        SELECT 1 FROM pos_order_items oi_p
        JOIN ticket_transactions tt_p ON oi_p.reference_id = tt_p.transaction_id AND oi_p.item_type = 'TICKET'
        JOIN provider_wallets pw_p ON tt_p.wallet_id = pw_p.wallet_id
        WHERE oi_p.order_id = o.order_id AND pw_p.provider_id = :provider_id
    )";
    $params['provider_id'] = (int)$filterProviderId;
}

if ($filterProviderType) {
    $where[] = "EXISTS (
        SELECT 1 FROM pos_order_items oi_pt
        JOIN ticket_transactions tt_pt ON oi_pt.reference_id = tt_pt.transaction_id AND oi_pt.item_type = 'TICKET'
        JOIN provider_wallets pw_pt ON tt_pt.wallet_id = pw_pt.wallet_id
        JOIN ticket_providers tp_pt ON pw_pt.provider_id = tp_pt.provider_id
        WHERE oi_pt.order_id = o.order_id AND tp_pt.provider_type = :provider_type
    )";
    $params['provider_type'] = $filterProviderType;
}

if ($filterPayMethod) {
    $where[]                  = "o.payment_method LIKE :pay_method";
    $params['pay_method']     = '%' . $filterPayMethod . '%';
}

if ($filterDateFrom && $filterDateTo) {
    $where[]             = 'DATE(o.created_at) BETWEEN :date_from AND :date_to';
    $params['date_from'] = $filterDateFrom;
    $params['date_to']   = $filterDateTo;
} elseif ($filterDateFrom) {
    $where[]             = 'DATE(o.created_at) >= :date_from';
    $params['date_from'] = $filterDateFrom;
} elseif ($filterDateTo) {
    $where[]           = 'DATE(o.created_at) <= :date_to';
    $params['date_to'] = $filterDateTo;
}

if ($filterSearch) {
    $where[] = "(o.order_code LIKE :search
        OR o.cashier_name LIKE :search
        OR b.branch_name LIKE :search
        OR EXISTS (
            SELECT 1 FROM pos_order_items oi_s
            LEFT JOIN ticket_transactions tt_s ON oi_s.reference_id = tt_s.transaction_id AND oi_s.item_type = 'TICKET'
            LEFT JOIN passenger_accounts pa_s ON tt_s.passenger_id = pa_s.passenger_id
            WHERE oi_s.order_id = o.order_id AND pa_s.fullname LIKE :search
        )
        OR EXISTS (
            SELECT 1 FROM pos_order_items oi_s2
            LEFT JOIN ticket_transactions tt_s2 ON oi_s2.reference_id = tt_s2.transaction_id AND oi_s2.item_type = 'TICKET'
            LEFT JOIN provider_wallets pw_s ON tt_s2.wallet_id = pw_s.wallet_id
            LEFT JOIN ticket_providers tp_s ON pw_s.provider_id = tp_s.provider_id
            WHERE oi_s2.order_id = o.order_id AND tp_s.provider_name LIKE :search
        )
    )";
    $params['search'] = '%' . $filterSearch . '%';
}

$whereClause = implode(' AND ', $where);

$baseFrom = "FROM pos_orders o
    LEFT JOIN business_branches b ON o.branch_id = b.branch_id
    LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
    LEFT JOIN employees e ON ua.emp_id = e.emp_id";

// --- Total count ---
$countRow   = Database::fetch("SELECT COUNT(DISTINCT o.order_id) as total $baseFrom WHERE $whereClause", $params);
$total      = (int)($countRow['total'] ?? 0);
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

// --- Main data query ---
$dataParams          = $params;
$dataParams['limit'] = $limit;
$dataParams['offset']= $offset;

$rows = Database::fetchAll(
    "SELECT
        o.order_id,
        o.order_code,
        o.status,
        COALESCE(o.original_grand_total, o.grand_total) AS total_amount,
        o.grand_total,
        o.subtotal,
        o.discount_total,
        o.total_service_fees,
        COALESCE(o.total_refunded_amount, 0) AS total_refunded_amount,
        o.amount_paid,
        o.change_amount,
        o.total_profit,
        o.payment_method,
        o.payment_methods_json,
        o.cashier_name,
        o.created_at,
        o.branch_id,
        o.created_by,
        b.branch_name,
        b.branch_code,
        COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username, o.cashier_name) AS cashier_full_name,
        (SELECT COUNT(*) FROM pos_order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'TICKET') AS ticket_count,
        (SELECT COUNT(*) FROM pos_order_items oi3 WHERE oi3.order_id = o.order_id AND oi3.item_type = 'SERVICE') AS service_count,
        (SELECT GROUP_CONCAT(DISTINCT pa2.fullname SEPARATOR ', ')
         FROM pos_order_items oi4
         LEFT JOIN ticket_transactions tt4 ON oi4.reference_id = tt4.transaction_id AND oi4.item_type = 'TICKET'
         LEFT JOIN passenger_accounts pa2 ON tt4.passenger_id = pa2.passenger_id
         WHERE oi4.order_id = o.order_id) AS passenger_names,
        (SELECT GROUP_CONCAT(DISTINCT pa2.mobile_number SEPARATOR ', ')
         FROM pos_order_items oi4
         LEFT JOIN ticket_transactions tt4 ON oi4.reference_id = tt4.transaction_id AND oi4.item_type = 'TICKET'
         LEFT JOIN passenger_accounts pa2 ON tt4.passenger_id = pa2.passenger_id
         WHERE oi4.order_id = o.order_id) AS passenger_numbers,
        (SELECT GROUP_CONCAT(DISTINCT oi4.ticket_number SEPARATOR ', ')
         FROM pos_order_items oi4
         WHERE oi4.order_id = o.order_id AND oi4.item_type = 'TICKET' AND oi4.ticket_number IS NOT NULL) AS ticket_numbers,
        (SELECT GROUP_CONCAT(DISTINCT CONCAT(tp2.provider_name, ' (', tp2.provider_type, ')') SEPARATOR ', ')
         FROM pos_order_items oi5
         LEFT JOIN ticket_transactions tt5 ON oi5.reference_id = tt5.transaction_id AND oi5.item_type = 'TICKET'
         LEFT JOIN provider_wallets pw2 ON tt5.wallet_id = pw2.wallet_id
         LEFT JOIN ticket_providers tp2 ON pw2.provider_id = tp2.provider_id
         WHERE oi5.order_id = o.order_id) AS provider_names,
        (SELECT GROUP_CONCAT(DISTINCT CONCAT(tt6.origin, ' → ', tt6.destination) SEPARATOR ' | ')
         FROM pos_order_items oi6
         LEFT JOIN ticket_transactions tt6 ON oi6.reference_id = tt6.transaction_id AND oi6.item_type = 'TICKET'
         WHERE oi6.order_id = o.order_id AND tt6.origin IS NOT NULL) AS routes,
        (SELECT GROUP_CONCAT(DISTINCT at.name SEPARATOR ', ')
         FROM pos_order_items oi9
         LEFT JOIN accommodation_types at ON oi9.accommodation_id = at.accommodation_id
         WHERE oi9.order_id = o.order_id AND oi9.accommodation_id IS NOT NULL) AS accommodation_names,
        (SELECT COUNT(*)
         FROM pos_order_items oi7
         LEFT JOIN ticket_transactions tt7 ON oi7.reference_id = tt7.transaction_id AND oi7.item_type = 'TICKET'
         LEFT JOIN ticket_cancellations tc7 ON tt7.transaction_id = tc7.transaction_id
         WHERE oi7.order_id = o.order_id AND tc7.status IN ('pending','approved')) AS has_cancellation,
        (SELECT tc8.status
         FROM pos_order_items oi8
         LEFT JOIN ticket_transactions tt8 ON oi8.reference_id = tt8.transaction_id AND oi8.item_type = 'TICKET'
         LEFT JOIN ticket_cancellations tc8 ON tt8.transaction_id = tc8.transaction_id
         WHERE oi8.order_id = o.order_id AND tc8.cancellation_id IS NOT NULL
         ORDER BY tc8.requested_at DESC LIMIT 1) AS cancellation_status
    $baseFrom
    WHERE $whereClause
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset",
    $dataParams
);

// Decode payment methods json
foreach ($rows as &$row) {
    if (!empty($row['payment_methods_json'])) {
        $row['payments'] = json_decode($row['payment_methods_json'], true) ?: [];
    } else {
        $row['payments'] = [];
    }
    unset($row['payment_methods_json']);
    $row['ticket_count']  = (int)$row['ticket_count'];
    $row['service_count'] = (int)$row['service_count'];
    $row['has_cancellation'] = (int)$row['has_cancellation'] > 0;
    
    // Encode order_id if encryption is enabled
    if ($encryptIds) {
        $row['order_id'] = IdEncoder::encode($row['order_id']);
    }
}
unset($row);

// --- Stats (scoped to user branch, unfiltered by current filters except branch) ---
$statsWhere  = ['1=1'];
$statsParams = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $statsWhere[]              = 'o.branch_id = :branch_id';
    $statsParams['branch_id']  = (int)$userBranchId;
} elseif ($filterBranchId) {
    $statsWhere[]              = 'o.branch_id = :branch_id';
    $statsParams['branch_id']  = (int)$filterBranchId;
}
// Apply date filter to stats too
if ($filterDateFrom && $filterDateTo) {
    $statsWhere[]                   = 'DATE(o.created_at) BETWEEN :date_from AND :date_to';
    $statsParams['date_from']       = $filterDateFrom;
    $statsParams['date_to']         = $filterDateTo;
} elseif ($filterDateFrom) {
    $statsWhere[]                   = 'DATE(o.created_at) >= :date_from';
    $statsParams['date_from']       = $filterDateFrom;
} elseif ($filterDateTo) {
    $statsWhere[]                 = 'DATE(o.created_at) <= :date_to';
    $statsParams['date_to']       = $filterDateTo;
}
$statsWhereClause = implode(' AND ', $statsWhere);

$stats = Database::fetch(
    "SELECT
        COUNT(DISTINCT o.order_id) AS total_orders,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN o.status = 'refunded'  THEN 1 ELSE 0 END) AS refunded_count,
        SUM(COALESCE(o.original_grand_total, o.grand_total)) AS total_revenue,
        SUM(COALESCE(o.total_refunded_amount, 0))            AS total_refunded,
        SUM(COALESCE(o.total_profit, 0))                     AS total_profit
     FROM pos_orders o
     LEFT JOIN business_branches b ON o.branch_id = b.branch_id
     WHERE $statsWhereClause",
    $statsParams
);

// --- Dropdown data for filters ---
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

$providers = Database::fetchAll(
    "SELECT DISTINCT tp.provider_id, tp.provider_name, tp.provider_type
     FROM ticket_providers tp
     JOIN provider_wallets pw ON tp.provider_id = pw.provider_id
     ORDER BY tp.provider_name"
);

$providerTypes = Database::fetchAll(
    "SELECT DISTINCT provider_type
     FROM ticket_providers
     WHERE provider_type IS NOT NULL AND provider_type != ''
     ORDER BY provider_type"
);

$cashiers = Database::fetchAll(
    "SELECT DISTINCT o.created_by AS user_id,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username, o.cashier_name) AS cashier_name
     FROM pos_orders o
     LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id" .
    ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId ? " WHERE o.branch_id = " . (int)$userBranchId : "") .
    " ORDER BY cashier_name"
);

echo json_encode([
    'success'    => true,
    'data'       => $rows,
    'stats'      => $stats,
    'filters'    => [
        'branches'       => $branches,
        'providers'      => $providers,
        'provider_types' => $providerTypes,
        'cashiers'       => $cashiers,
    ],
    'pagination' => [
        'total'        => $total,
        'per_page'     => $limit,
        'current_page' => $page,
        'total_pages'  => $totalPages,
        'from'         => $total > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $limit, $total),
    ],
]);
