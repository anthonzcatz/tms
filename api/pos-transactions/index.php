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
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

Auth::requireLogin();
$user = Auth::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userRoleCode = $user['role_code'] ?? '';

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

if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::canAccessModule('admin/pos/transactions/') && !Auth::canAccessModule('admin/pos/')) {
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
$checkOnly       = filter_var($_GET['check_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
$includeFinancialReport = filter_var($_GET['include_financial_report'] ?? false, FILTER_VALIDATE_BOOLEAN);

$limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$validStatuses = ['all', 'completed', 'pending', 'cancelled', 'refunded'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

// --- WHERE ---
$where  = ['1=1'];
$params = [];

// Branch scoping — non-super-admin users are restricted to assigned branches.
$allowedBranchIds = PosAccess::allowedBranchIds($user);
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $where[] = '1 = 0';
    } else {
        $scopePlaceholders = [];
        foreach ($allowedBranchIds as $index => $allowedBranchId) {
            $placeholder = ':scope_branch_' . $index;
            $scopePlaceholders[] = $placeholder;
            $params['scope_branch_' . $index] = $allowedBranchId;
        }
        $where[] = 'o.branch_id IN (' . implode(',', $scopePlaceholders) . ')';
    }
}

if ($filterBranchId) {
    try {
        PosAccess::assertBranchAccess($user, (int) $filterBranchId);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $where[] = 'o.branch_id = :branch_filter_id';
    $params['branch_filter_id'] = (int) $filterBranchId;
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
        WHERE oi_p.order_id = o.order_id AND tt_p.provider_id = :provider_id
    )";
    $params['provider_id'] = (int)$filterProviderId;
}

if ($filterProviderType) {
    $where[] = "EXISTS (
        SELECT 1 FROM pos_order_items oi_pt
        JOIN ticket_transactions tt_pt ON oi_pt.reference_id = tt_pt.transaction_id AND oi_pt.item_type = 'TICKET'
        JOIN ticket_providers tp_pt ON tt_pt.provider_id = tp_pt.provider_id
        WHERE oi_pt.order_id = o.order_id AND tp_pt.provider_type = :provider_type
    )";
    $params['provider_type'] = $filterProviderType;
}

if ($filterPayMethod) {
    $where[]                  = "o.payment_method LIKE :pay_method";
    $params['pay_method']     = '%' . $filterPayMethod . '%';
}

if ($filterDateFrom || $filterDateTo) {
    $from = $filterDateFrom
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $filterDateFrom)
        : null;
    $to = $filterDateTo
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $filterDateTo)
        : null;
    if (($filterDateFrom && !$from) || ($filterDateTo && !$to)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date filter.']);
        exit;
    }
    if ($from) {
        $where[] = 'o.created_at >= :date_from';
        $params['date_from'] = $from->format('Y-m-d H:i:s');
    }
    if ($to) {
        $where[] = 'o.created_at < :date_to_exclusive';
        $params['date_to_exclusive'] = $to->modify('+1 day')->format('Y-m-d H:i:s');
    }
}

if ($filterSearch) {
    $where[] = "(o.order_code LIKE :search_order
        OR o.cashier_name LIKE :search_cashier
        OR b.branch_name LIKE :search_branch
        OR EXISTS (
            SELECT 1 FROM pos_order_items oi_s
            LEFT JOIN ticket_transactions tt_s ON oi_s.reference_id = tt_s.transaction_id AND oi_s.item_type = 'TICKET'
            LEFT JOIN passenger_accounts pa_s ON tt_s.passenger_id = pa_s.passenger_id
            WHERE oi_s.order_id = o.order_id AND pa_s.fullname LIKE :search_passenger
        )
        OR EXISTS (
            SELECT 1 FROM pos_order_items oi_s2
            LEFT JOIN ticket_transactions tt_s2 ON oi_s2.reference_id = tt_s2.transaction_id AND oi_s2.item_type = 'TICKET'
            LEFT JOIN ticket_providers tp_s ON tt_s2.provider_id = tp_s.provider_id
            LEFT JOIN provider_wallets pw_s ON tt_s2.wallet_id = pw_s.wallet_id
            LEFT JOIN ticket_providers wallet_tp_s ON pw_s.provider_id = wallet_tp_s.provider_id
            WHERE oi_s2.order_id = o.order_id
            AND (tp_s.provider_name LIKE :search_provider OR wallet_tp_s.provider_name LIKE :search_wallet_provider)
        )
    )";
    $searchValue = '%' . $filterSearch . '%';
    $params['search_order'] = $searchValue;
    $params['search_cashier'] = $searchValue;
    $params['search_branch'] = $searchValue;
    $params['search_passenger'] = $searchValue;
    $params['search_provider'] = $searchValue;
    $params['search_wallet_provider'] = $searchValue;
}

$whereClause = implode(' AND ', $where);

$baseFrom = "FROM pos_orders o
    LEFT JOIN business_branches b ON o.branch_id = b.branch_id
    LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
    LEFT JOIN employees e ON ua.emp_id = e.emp_id
    LEFT JOIN bir_or_numbers orn ON o.or_number_id = orn.or_id
    LEFT JOIN bir_vat_transactions vt ON o.order_id = vt.order_id";

$markerFrom = "FROM pos_orders o
    LEFT JOIN business_branches b ON o.branch_id = b.branch_id
    LEFT JOIN pos_order_items marker_oi
        ON marker_oi.order_id = o.order_id AND marker_oi.item_type = 'TICKET'
    LEFT JOIN ticket_adjustments marker_ta
        ON marker_ta.transaction_id = marker_oi.reference_id
    LEFT JOIN ticket_cancellations marker_tc
        ON marker_tc.transaction_id = marker_oi.reference_id";

$markerRow = Database::fetch(
    "SELECT
        COUNT(DISTINCT o.order_id) AS total,
        COALESCE(MAX(o.order_id), 0) AS max_order_id,
        MAX(o.created_at) AS latest_created_at,
        MAX(o.updated_at) AS latest_updated_at,
        MAX(marker_ta.created_at) AS latest_adjustment_at,
        MAX(marker_ta.approved_at) AS latest_adjustment_approved_at,
        MAX(marker_tc.requested_at) AS latest_cancellation_requested_at,
        MAX(marker_tc.approved_at) AS latest_cancellation_approved_at,
        MAX(marker_tc.processed_at) AS latest_cancellation_processed_at
     $markerFrom
     WHERE $whereClause",
    $params
) ?: [];

$marker = [
    'total_orders' => (int)($markerRow['total'] ?? 0),
    'max_order_id' => (int)($markerRow['max_order_id'] ?? 0),
    'latest_created_at' => (string)($markerRow['latest_created_at'] ?? ''),
    'latest_updated_at' => (string)($markerRow['latest_updated_at'] ?? ''),
    'latest_adjustment_at' => (string)($markerRow['latest_adjustment_at'] ?? ''),
    'latest_adjustment_approved_at' => (string)($markerRow['latest_adjustment_approved_at'] ?? ''),
    'latest_cancellation_requested_at' => (string)($markerRow['latest_cancellation_requested_at'] ?? ''),
    'latest_cancellation_approved_at' => (string)($markerRow['latest_cancellation_approved_at'] ?? ''),
    'latest_cancellation_processed_at' => (string)($markerRow['latest_cancellation_processed_at'] ?? ''),
];

if ($checkOnly) {
    echo json_encode([
        'success' => true,
        'marker' => $marker,
    ]);
    exit;
}

// --- Total count ---
$total      = $marker['total_orders'];
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
        o.total_cost,
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
        (SELECT GROUP_CONCAT(DISTINCT COALESCE(oi4.ticket_number, tt4.ticket_number) SEPARATOR ', ')
         FROM pos_order_items oi4
         LEFT JOIN ticket_transactions tt4 ON oi4.reference_id = tt4.transaction_id AND oi4.item_type = 'TICKET'
         WHERE oi4.order_id = o.order_id AND oi4.item_type = 'TICKET'
           AND (oi4.ticket_number IS NOT NULL OR tt4.ticket_number IS NOT NULL)) AS ticket_numbers,
        (SELECT GROUP_CONCAT(DISTINCT COALESCE(
            CASE
                WHEN pv.variant_name IS NOT NULL AND pv.variant_name != '' THEN CONCAT(pv.variant_name, IF(tp_op.provider_type IS NOT NULL AND tp_op.provider_type != '', CONCAT(' (', tp_op.provider_type, ')'), ''))
                WHEN tp_parent.provider_name IS NOT NULL AND tp_parent.provider_name != '' THEN CONCAT(tp_parent.provider_name, ' - ', tp_op.provider_name, IF(tp_op.provider_type IS NOT NULL AND tp_op.provider_type != '', CONCAT(' (', tp_op.provider_type, ')'), ''))
                ELSE CONCAT(tp_op.provider_name, IF(tp_op.provider_type IS NOT NULL AND tp_op.provider_type != '', CONCAT(' (', tp_op.provider_type, ')'), ''))
            END, ''
         ) SEPARATOR ', ')
         FROM pos_order_items oi5
         LEFT JOIN ticket_transactions tt5 ON oi5.reference_id = tt5.transaction_id AND oi5.item_type = 'TICKET'
         LEFT JOIN ticket_providers tp_op ON tt5.provider_id = tp_op.provider_id
         LEFT JOIN ticket_providers tp_parent ON tp_op.parent_provider_id = tp_parent.provider_id
         LEFT JOIN provider_ticket_variants pv ON tt5.variant_id = pv.variant_id
         WHERE oi5.order_id = o.order_id) AS provider_names,
        (SELECT GROUP_CONCAT(DISTINCT COALESCE(
            CONCAT_WS(' - ', wallet_tp2.provider_name, pv_wallet.variant_name), ''
         ) SEPARATOR ', ')
         FROM pos_order_items oi5_wallet
         LEFT JOIN ticket_transactions tt5_wallet ON oi5_wallet.reference_id = tt5_wallet.transaction_id AND oi5_wallet.item_type = 'TICKET'
         LEFT JOIN provider_wallets pw2_wallet ON tt5_wallet.wallet_id = pw2_wallet.wallet_id
         LEFT JOIN ticket_providers wallet_tp2 ON pw2_wallet.provider_id = wallet_tp2.provider_id
         LEFT JOIN provider_ticket_variants pv_wallet ON pw2_wallet.variant_id = pv_wallet.variant_id
         WHERE oi5_wallet.order_id = o.order_id) AS wallet_provider_names,
        (SELECT GROUP_CONCAT(DISTINCT CONCAT(tt6.origin, ' → ', tt6.destination) SEPARATOR ' | ')
         FROM pos_order_items oi6
         LEFT JOIN ticket_transactions tt6 ON oi6.reference_id = tt6.transaction_id AND oi6.item_type = 'TICKET'
         WHERE oi6.order_id = o.order_id AND tt6.origin IS NOT NULL) AS routes,
        (SELECT GROUP_CONCAT(DISTINCT at.name SEPARATOR ', ')
         FROM pos_order_items oi9
         LEFT JOIN accommodation_types at ON oi9.accommodation_id = at.accommodation_id
         WHERE oi9.order_id = o.order_id AND oi9.accommodation_id IS NOT NULL) AS accommodation_names,
        (SELECT GROUP_CONCAT(DISTINCT dt.name SEPARATOR ', ')
         FROM pos_order_items oi10
         LEFT JOIN discount_types dt ON oi10.discount_id = dt.discount_id
         WHERE oi10.order_id = o.order_id AND oi10.discount_id IS NOT NULL) AS discount_names,
        (SELECT ta.type
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_type,
        (SELECT ta.approval_status
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_approval_status,
        (SELECT ta.charged_to
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_responsibility,
        (SELECT ta.amount
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_amount,
        (SELECT tc.reason_category
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_reason_category,
        (SELECT COALESCE(SUM(
                    CASE WHEN tc.reason_category = 'PRINTER_ERROR' THEN COALESCE(tt.total_amount, 0) ELSE 0 END
                ), 0)
         FROM pos_order_items oi_technical_void
         JOIN ticket_transactions tt ON tt.transaction_id = oi_technical_void.reference_id
         JOIN ticket_cancellations tc ON tc.transaction_id = tt.transaction_id
         WHERE oi_technical_void.order_id = o.order_id
           AND oi_technical_void.item_type = 'TICKET'
           AND tc.operation_type = 'VOID'
           AND tc.status = 'completed') AS technical_void_amount,
        (SELECT COALESCE(SUM(
                    CASE WHEN tc.reason_category = 'PRINTER_ERROR' THEN 0 ELSE COALESCE(tc.void_fee, 0) END
                ), 0)
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         WHERE oi_adj.order_id = o.order_id
           AND oi_adj.item_type = 'TICKET'
           AND tc.operation_type = 'VOID'
           AND tc.status = 'completed') AS void_fee,
        (SELECT COALESCE(SUM(
                    CASE WHEN tc.reason_category = 'PRINTER_ERROR' THEN 0 ELSE tc.void_service_fee END
                ), 0)
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         WHERE oi_adj.order_id = o.order_id
           AND oi_adj.item_type = 'TICKET'
           AND tc.operation_type = 'VOID'
           AND tc.status = 'completed') AS void_service_fee,
        (SELECT COALESCE(SUM(
                    CASE WHEN tc.reason_category = 'PRINTER_ERROR'
                         THEN COALESCE(tc.lost_sales_void_fee, 0)
                         ELSE 0 END
                ), 0)
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         WHERE oi_adj.order_id = o.order_id
           AND oi_adj.item_type = 'TICKET'
           AND tc.operation_type = 'VOID'
           AND tc.status = 'completed') AS lost_sales_void_fee,
        (SELECT COALESCE(SUM(
                    CASE WHEN tc.reason_category = 'PRINTER_ERROR'
                         THEN COALESCE(tc.lost_sales_service_fee, 0) + COALESCE(tc.void_service_fee, 0)
                         ELSE 0 END
                ), 0)
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         WHERE oi_adj.order_id = o.order_id
           AND oi_adj.item_type = 'TICKET'
           AND tc.operation_type = 'VOID'
           AND tc.status = 'completed') AS lost_sales_service_fee,
        (SELECT COALESCE(CONCAT_WS(' ', e_adj.first_name, e_adj.last_name), ua_adj.username)
         FROM pos_order_items oi_adj
         JOIN ticket_adjustments ta ON ta.transaction_id = oi_adj.reference_id
         LEFT JOIN user_accounts ua_adj ON ua_adj.user_id = ta.responsible_user_id
         LEFT JOIN employees e_adj ON e_adj.emp_id = ua_adj.emp_id
         WHERE oi_adj.order_id = o.order_id AND oi_adj.item_type = 'TICKET'
         ORDER BY ta.created_at DESC
         LIMIT 1) AS adjustment_responsible_cashier,
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
         ORDER BY tc8.requested_at DESC LIMIT 1) AS cancellation_status,
        orn.or_full_number,
        vt.vat_type,
        vt.vat_amount
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

// --- Stats (filtered by the same WHERE conditions as the main query) ---
$stats = Database::fetch(
    "SELECT
        COUNT(DISTINCT o.order_id) AS total_orders,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN o.status = 'refunded'  THEN 1 ELSE 0 END) AS refunded_count,
        COALESCE(SUM(
            GREATEST(
                0,
                COALESCE(o.original_grand_total, o.grand_total)
                - COALESCE(o.total_refunded_amount, 0)
                - COALESCE((
                    SELECT SUM(
                        CASE
                            WHEN tc_void.reason_category = 'PRINTER_ERROR'
                            THEN COALESCE(tc_void.lost_sales_void_fee, 0)
                            ELSE COALESCE(tt_void.total_amount, 0)
                                - COALESCE(tc_void.void_fee, 0)
                                - COALESCE(tc_void.void_service_fee, 0)
                        END
                    )
                    FROM pos_order_items oi_void
                    JOIN ticket_transactions tt_void
                      ON tt_void.transaction_id = oi_void.reference_id
                    JOIN ticket_cancellations tc_void
                      ON tc_void.transaction_id = tt_void.transaction_id
                     AND tc_void.operation_type = 'VOID'
                     AND tc_void.status = 'completed'
                    WHERE oi_void.order_id = o.order_id
                      AND oi_void.item_type = 'TICKET'
                ), 0)
            )
        ), 0) AS total_revenue,
        SUM(COALESCE(o.total_refunded_amount, 0))            AS total_refunded,
        SUM(COALESCE(o.total_profit, 0))                     AS total_profit
     FROM pos_orders o
     LEFT JOIN business_branches b ON o.branch_id = b.branch_id
     WHERE $whereClause",
    $params
);

$financialReport = null;
if ($includeFinancialReport) {
    $financialFrom = "FROM pos_orders o
        LEFT JOIN business_branches b ON o.branch_id = b.branch_id";
    $financialSaleWhere = $whereClause . " AND o.status IN ('completed', 'refunded')";

    $providerSalesRows = Database::fetchAll(
        "SELECT
            COALESCE(tp.provider_id, 0) AS provider_id,
            COALESCE(NULLIF(tp.provider_name, ''), 'Unassigned') AS provider_name,
            COALESCE(SUM(CASE WHEN oi.quantity IS NULL OR oi.quantity < 1 THEN 1 ELSE oi.quantity END), 0) AS tickets,
            COALESCE(SUM(COALESCE(tt.base_amount, oi.unit_price, 0) * CASE WHEN oi.quantity IS NULL OR oi.quantity < 1 THEN 1 ELSE oi.quantity END), 0) AS total_cost,
            COALESCE(SUM(COALESCE(tt.service_fee, oi.service_fee, 0) * CASE WHEN oi.quantity IS NULL OR oi.quantity < 1 THEN 1 ELSE oi.quantity END), 0) AS service_fee_income,
            COALESCE(SUM(COALESCE(tt.total_amount, oi.total_amount, 0)), 0) AS total_amount
         $financialFrom
         INNER JOIN pos_order_items oi
            ON oi.order_id = o.order_id AND oi.item_type = 'TICKET'
         LEFT JOIN ticket_transactions tt ON tt.transaction_id = oi.reference_id
         LEFT JOIN ticket_providers tp ON tp.provider_id = COALESCE(tt.provider_id, oi.provider_id)
         WHERE $financialSaleWhere
         GROUP BY tp.provider_id, tp.provider_name
         ORDER BY provider_name",
        $params
    );

    $serviceSalesRow = Database::fetch(
        "SELECT
            COALESCE(SUM(COALESCE(oi.total_amount, 0)), 0) AS service_amount,
            COALESCE(SUM(CASE WHEN oi.quantity IS NULL OR oi.quantity < 1 THEN 1 ELSE oi.quantity END), 0) AS service_units
         $financialFrom
         INNER JOIN pos_order_items oi
            ON oi.order_id = o.order_id AND oi.item_type = 'SERVICE'
         WHERE $financialSaleWhere",
        $params
    ) ?: [];

    $financialOrders = Database::fetchAll(
        "SELECT DISTINCT
            o.order_id,
            COALESCE(o.original_grand_total, o.grand_total) AS gross_amount,
            o.amount_paid,
            o.payment_method,
            o.payment_methods_json
         $financialFrom
         WHERE $financialSaleWhere",
        $params
    );

    $methodDefinitions = Database::fetchAll(
        "SELECT method_id, method_name, method_type, sort_order
         FROM payment_methods
         ORDER BY sort_order, method_name"
    );
    $methodMap = [];
    foreach ($methodDefinitions as $methodDefinition) {
        $methodMap[(int)$methodDefinition['method_id']] = $methodDefinition;
    }

    $paymentSummary = [];
    $paymentOrderIds = [];
    $totalSales = 0.0;
    foreach ($financialOrders as $financialOrder) {
        $orderId = (string)($financialOrder['order_id'] ?? '');
        $totalSales += (float)($financialOrder['gross_amount'] ?? 0);
        $orderPayments = [];
        if (!empty($financialOrder['payment_methods_json'])) {
            $decodedPayments = json_decode($financialOrder['payment_methods_json'], true);
            if (is_array($decodedPayments)) {
                $orderPayments = $decodedPayments;
            }
        }
        if (empty($orderPayments) && !empty($financialOrder['payment_method'])) {
            $orderPayments[] = [
                'method_name' => $financialOrder['payment_method'],
                'amount' => (float)($financialOrder['gross_amount'] ?? $financialOrder['amount_paid'] ?? 0)
            ];
        }

        foreach ($orderPayments as $orderPayment) {
            $methodId = isset($orderPayment['method_id']) && $orderPayment['method_id'] !== ''
                ? (int)$orderPayment['method_id']
                : null;
            $definition = $methodId !== null ? ($methodMap[$methodId] ?? null) : null;
            $methodName = trim((string)($definition['method_name'] ?? $orderPayment['method_name'] ?? $orderPayment['method_code'] ?? 'Other'));
            $methodType = (string)($definition['method_type'] ?? $orderPayment['method_type'] ?? 'OTHER');
            $amount = (float)($orderPayment['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $key = $methodId !== null ? 'id:' . $methodId : 'name:' . strtolower($methodName);
            if (!isset($paymentSummary[$key])) {
                $paymentSummary[$key] = [
                    'method_id' => $methodId,
                    'method_name' => $methodName,
                    'method_type' => $methodType,
                    'order_count' => 0,
                    'amount' => 0.0,
                    'sort_order' => (int)($definition['sort_order'] ?? 999)
                ];
            }
            $paymentSummary[$key]['amount'] += $amount;
            $paymentOrderIds[$key][$orderId] = true;
        }
    }

    $requiredPaymentTypes = ['CASH', 'BANK_TRANSFER', 'CHARGE'];
    $paymentTypesPresent = [];
    foreach ($paymentSummary as $payment) {
        $paymentTypesPresent[$payment['method_type']] = true;
    }
    foreach ($methodDefinitions as $methodDefinition) {
        $methodType = (string)$methodDefinition['method_type'];
        $key = 'id:' . (int)$methodDefinition['method_id'];
        if (in_array($methodType, $requiredPaymentTypes, true)
            && !isset($paymentSummary[$key])
            && !isset($paymentTypesPresent[$methodType])) {
            $paymentSummary[$key] = [
                'method_id' => (int)$methodDefinition['method_id'],
                'method_name' => $methodDefinition['method_name'],
                'method_type' => $methodType,
                'order_count' => 0,
                'amount' => 0.0,
                'sort_order' => (int)$methodDefinition['sort_order']
            ];
        }
    }
    foreach ($paymentSummary as $key => &$payment) {
        $payment['order_count'] = count($paymentOrderIds[$key] ?? []);
        $payment['amount'] = round((float)$payment['amount'], 2);
    }
    unset($payment);
    usort($paymentSummary, static function (array $left, array $right): int {
        $sortComparison = ((int)$left['sort_order']) <=> ((int)$right['sort_order']);
        return $sortComparison !== 0 ? $sortComparison : strcasecmp($left['method_name'], $right['method_name']);
    });
    foreach ($paymentSummary as &$payment) {
        unset($payment['sort_order']);
    }
    unset($payment);

    $providerSales = [];
    $totalTickets = 0;
    $totalCost = 0.0;
    $totalServiceFeeIncome = 0.0;
    $totalAmount = 0.0;
    foreach ($providerSalesRows as $providerSalesRow) {
        $providerRow = [
            'provider_id' => (int)($providerSalesRow['provider_id'] ?? 0),
            'provider_name' => $providerSalesRow['provider_name'] ?? 'Unassigned',
            'tickets' => (int)($providerSalesRow['tickets'] ?? 0),
            'total_cost' => round((float)($providerSalesRow['total_cost'] ?? 0), 2),
            'service_fee_income' => round((float)($providerSalesRow['service_fee_income'] ?? 0), 2),
            'total_amount' => round((float)($providerSalesRow['total_amount'] ?? 0), 2)
        ];
        $providerSales[] = $providerRow;
        $totalTickets += $providerRow['tickets'];
        $totalCost += $providerRow['total_cost'];
        $totalServiceFeeIncome += $providerRow['service_fee_income'];
        $totalAmount += $providerRow['total_amount'];
    }

    $serviceAmount = round((float)($serviceSalesRow['service_amount'] ?? 0), 2);
    if ($serviceAmount > 0) {
        $providerSales[] = [
            'provider_id' => 0,
            'provider_name' => 'Services / Add-ons',
            'tickets' => 0,
            'total_cost' => 0.0,
            'service_fee_income' => $serviceAmount,
            'total_amount' => $serviceAmount,
            'is_service' => true
        ];
        $totalServiceFeeIncome += $serviceAmount;
        $totalAmount += $serviceAmount;
    }

    $refundRows = Database::fetchAll(
        "SELECT
            COALESCE(tp.provider_id, 0) AS provider_id,
            COALESCE(NULLIF(tp.provider_name, ''), 'Unassigned') AS provider_name,
            COUNT(DISTINCT tc.cancellation_id) AS refund_count,
            COALESCE(SUM(COALESCE(NULLIF(tc.gross_refund_amount, 0), tc.refund_amount)), 0) AS amount
         $financialFrom
         INNER JOIN pos_order_items oi
            ON oi.order_id = o.order_id AND oi.item_type = 'TICKET'
         INNER JOIN ticket_transactions tt ON tt.transaction_id = oi.reference_id
         INNER JOIN ticket_cancellations tc ON tc.transaction_id = tt.transaction_id
         LEFT JOIN ticket_providers tp ON tp.provider_id = COALESCE(tt.provider_id, oi.provider_id)
         WHERE $financialSaleWhere
           AND tc.status = 'completed'
           AND tc.operation_type = 'REFUND'
         GROUP BY tp.provider_id, tp.provider_name
         ORDER BY provider_name",
        $params
    );

    $salesRefunds = [];
    $totalSalesRefunds = 0.0;
    foreach ($refundRows as $refundRow) {
        $refund = [
            'provider_id' => (int)($refundRow['provider_id'] ?? 0),
            'provider_name' => $refundRow['provider_name'] ?? 'Unassigned',
            'refund_count' => (int)($refundRow['refund_count'] ?? 0),
            'amount' => round((float)($refundRow['amount'] ?? 0), 2)
        ];
        $salesRefunds[] = $refund;
        $totalSalesRefunds += $refund['amount'];
    }

    $financialReport = [
        'date_from' => $filterDateFrom ?: '',
        'date_to' => $filterDateTo ?: '',
        'provider_sales' => $providerSales,
        'total_tickets' => $totalTickets,
        'total_cost' => round($totalCost, 2),
        'total_service_fee_income' => round($totalServiceFeeIncome, 2),
        'total_amount' => round($totalAmount, 2),
        'service_units' => (int)($serviceSalesRow['service_units'] ?? 0),
        'service_amount' => $serviceAmount,
        'payment_summary' => $paymentSummary,
        'total_sales' => round($totalSales, 2),
        'sales_refunds' => $salesRefunds,
        'total_sales_refunds' => round($totalSalesRefunds, 2),
        'net_amount_for_deposit' => round(max(0, $totalSales - $totalSalesRefunds), 2)
    ];
}

// --- Dropdown data for filters ---
$branchFilterSql = "WHERE status = 'active'";
$branchFilterParams = [];
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $branchFilterSql .= ' AND 1 = 0';
    } else {
        $branchPlaceholders = [];
        foreach ($allowedBranchIds as $index => $allowedBranchId) {
            $placeholder = ':dropdown_branch_' . $index;
            $branchPlaceholders[] = $placeholder;
            $branchFilterParams['dropdown_branch_' . $index] = $allowedBranchId;
        }
        $branchFilterSql .= ' AND branch_id IN (' . implode(',', $branchPlaceholders) . ')';
    }
}
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches $branchFilterSql ORDER BY branch_name",
    $branchFilterParams
);

$providers = Database::fetchAll(
    "SELECT provider_id, provider_name, provider_type
     FROM ticket_providers
     WHERE status = 'active'
     ORDER BY provider_name"
);

$providerTypes = Database::fetchAll(
    "SELECT DISTINCT provider_type
     FROM ticket_providers
     WHERE provider_type IS NOT NULL AND provider_type != ''
     ORDER BY provider_type"
);

$cashierWhere = '';
$cashierParams = [];
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $cashierWhere = ' WHERE 1 = 0';
    } else {
        $cashierPlaceholders = [];
        foreach ($allowedBranchIds as $index => $allowedBranchId) {
            $placeholder = ':cashier_branch_' . $index;
            $cashierPlaceholders[] = $placeholder;
            $cashierParams['cashier_branch_' . $index] = $allowedBranchId;
        }
        $cashierWhere = ' WHERE o.branch_id IN (' . implode(',', $cashierPlaceholders) . ')';
    }
}
$cashiers = Database::fetchAll(
    "SELECT DISTINCT o.created_by AS user_id,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username, o.cashier_name) AS cashier_name
     FROM pos_orders o
     LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     $cashierWhere
     ORDER BY cashier_name",
    $cashierParams
);

// --- Financial report signatory configuration (per branch) ---
function resolveFinancialReportSignatoryNames(array $signatories, int $branchId): array {
    if (empty($signatories) || $branchId <= 0) {
        return $signatories;
    }

    $employeeIds = [];
    foreach ($signatories as $signatory) {
        $employeeId = (int)($signatory['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $employeeIds[] = $employeeId;
        }
    }

    $employeeMap = [];
    if (!empty($employeeIds)) {
        $placeholders = [];
        $employeeParams = ['signatory_branch_id' => $branchId];
        foreach (array_values(array_unique($employeeIds)) as $index => $employeeId) {
            $placeholder = ':report_signatory_employee_' . $index;
            $placeholders[] = $placeholder;
            $employeeParams['report_signatory_employee_' . $index] = $employeeId;
        }
        $employees = Database::fetchAll(
            "SELECT e.emp_id,
                    e.job_title AS position_id,
                    CONCAT_WS(' ',
                        NULLIF(TRIM(e.first_name), ''),
                        NULLIF(TRIM(e.middle_name), ''),
                        NULLIF(TRIM(e.last_name), '')
                    ) AS full_name,
                    p.position_name
             FROM employees e
             LEFT JOIN position p ON e.job_title = p.pos_id
             WHERE e.emp_id IN (" . implode(',', $placeholders) . ")
               AND (e.branch_id = :signatory_branch_id OR e.branch_id IS NULL)",
            $employeeParams
        );
        foreach ($employees as $employee) {
            $employeeMap[(int)$employee['emp_id']] = $employee;
        }
    }

    $positionMap = [];
    foreach (Database::fetchAll(
        "SELECT pos_id, position_name FROM position WHERE status = 'active'"
    ) as $position) {
        $positionMap[(int)$position['pos_id']] = $position['position_name'];
    }

    foreach ($signatories as $index => $signatory) {
        $employeeId = (int)($signatory['employee_id'] ?? 0);
        if ($employeeId > 0 && !empty($employeeMap[$employeeId])) {
            $employee = $employeeMap[$employeeId];
            $signatories[$index]['name'] = trim((string)$employee['full_name']);
            $signatories[$index]['position_id'] = (int)($employee['position_id'] ?? 0);
            $signatories[$index]['position_name'] = trim((string)($employee['position_name'] ?? ''));
            $signatories[$index]['employee_available'] = 1;
        } elseif ($employeeId > 0) {
            // Do not display an employee assigned to a different branch or removed from the employee list.
            $signatories[$index]['name'] = '';
            $signatories[$index]['position_id'] = 0;
            $signatories[$index]['position_name'] = '';
            $signatories[$index]['employee_available'] = 0;
        } else {
            $signatories[$index]['name'] = trim((string)($signatory['name'] ?? ''));
            $positionId = (int)($signatory['position_id'] ?? 0);
            $signatories[$index]['position_name'] = $positionMap[$positionId]
                ?? trim((string)($signatory['position_name'] ?? ''));
            $signatories[$index]['employee_available'] = 1;
        }
    }
    return $signatories;
}

if ($includeFinancialReport && is_array($financialReport)) {
    $financialReport['signatory_config'] = [];
    $financialReport['signatory_branch_id'] = null;
    $financialReport['signatory_configured'] = false;

    // A report covering multiple branches cannot safely use one branch's signatories.
    // Fallback to the user's primary branch when no explicit branch filter is set.
    $signatoryBranchId = null;
    if ($filterBranchId !== null && $filterBranchId !== '') {
        $signatoryBranchId = (int)$filterBranchId;
    } else {
        $userBranchId = Auth::userBranchId();
        if ($userBranchId) {
            $userBranchIds = array_values(array_unique(array_filter(
                array_map('intval', explode(',', (string)$userBranchId)),
                static fn (int $id): bool => $id > 0
            )));
            if (count($userBranchIds) === 1) {
                $signatoryBranchId = $userBranchIds[0];
            }
        }
        if (!$signatoryBranchId && is_array($allowedBranchIds) && count($allowedBranchIds) === 1) {
            $signatoryBranchId = (int)$allowedBranchIds[0];
        }
    }

    if ($signatoryBranchId > 0) {
        try {
            PosAccess::assertBranchAccess($user, $signatoryBranchId);
            $branchSignatoriesRow = Database::fetch(
                "SELECT financial_report_signatories
                 FROM business_branches
                 WHERE branch_id = :branch_id",
                ['branch_id' => $signatoryBranchId]
            );
            $hasSignatoryConfig = $branchSignatoriesRow
                && $branchSignatoriesRow['financial_report_signatories'] !== null
                && trim((string)$branchSignatoriesRow['financial_report_signatories']) !== '';
            $signatoryConfig = [];
            if ($hasSignatoryConfig) {
                $decodedSignatories = json_decode((string)$branchSignatoriesRow['financial_report_signatories'], true);
                if (is_array($decodedSignatories)) {
                    $signatoryConfig = resolveFinancialReportSignatoryNames($decodedSignatories, $signatoryBranchId);
                }
            }
            $financialReport['signatory_config'] = $signatoryConfig;
            $financialReport['signatory_branch_id'] = $signatoryBranchId;
            $financialReport['signatory_configured'] = $hasSignatoryConfig && !empty($signatoryConfig);
        } catch (Throwable $e) {
            $financialReport['signatory_branch_id'] = $signatoryBranchId;
        }
    }
}

echo json_encode([
    'success'    => true,
    'data'       => $rows,
    'stats'      => $stats,
    'marker'     => $marker,
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
    'financial_report' => $financialReport,
]);
