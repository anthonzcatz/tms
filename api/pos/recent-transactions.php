<?php
/**
 * POS Recent Transactions API — Fetch recent ticket and service transactions
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$limit = $_GET['limit'] ?? 10;
$limit = min(max(intval($limit), 1), 50);
$offset = $_GET['offset'] ?? 0;
$offset = max(intval($offset), 0);

// Get filter parameters
$search    = $_GET['search'] ?? null;
$type      = $_GET['type'] ?? null;
$status    = $_GET['status'] ?? null;
$date      = $_GET['date'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate   = $_GET['end_date'] ?? null;

// Get user branch for filtering
$branchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// ---------------------------------------------------------------
// Strategy: Query pos_orders (grouped) if table exists,
//           fall back to individual ticket/service tables if not.
// This ensures backward compatibility during migration.
// ---------------------------------------------------------------
$useOrdersTable = false;
try {
    $check = Database::fetch("SELECT 1 FROM pos_orders LIMIT 1");
    $useOrdersTable = true;
} catch (Exception $e) {
    $useOrdersTable = false;
}

$allTransactions = [];
$totalCount = 0;

if ($useOrdersTable) {
    // --- Query from pos_orders for grouped reporting ---
    $where  = [];
    $params = [];

    if ($branchId && $userRoleCode !== 'SUPER_ADMIN') {
        $branchIds = array_filter(array_map('intval', explode(',', $branchId)));
        if (!empty($branchIds)) {
            $branchPlaceholders = implode(',', array_fill(0, count($branchIds), '?'));
            $where[] = "o.branch_id IN ($branchPlaceholders)";
            $params = array_merge($params, $branchIds);
        }
    }

    $where[] = 'o.created_by = :created_by';
    $params['created_by'] = $user['user_id'];

    if ($search) {
        $where[] = '(o.order_code LIKE :search OR oi.transaction_code LIKE :search OR 
                      EXISTS (SELECT 1 FROM pos_order_items oi_search
                               LEFT JOIN ticket_transactions tt_search ON oi_search.reference_id = tt_search.transaction_id AND oi_search.item_type = \'TICKET\'
                               LEFT JOIN passenger_accounts pa_search ON tt_search.passenger_id = pa_search.passenger_id
                               WHERE oi_search.order_id = o.order_id AND pa_search.fullname LIKE :search))';
        $params['search'] = '%' . $search . '%';
    }

    if ($status) {
        $where[] = 'o.status = :status';
        $params['status'] = $status;
    }

    if ($date) {
        $where[] = 'DATE(o.created_at) = :date';
        $params['date'] = $date;
    }

    if ($startDate && $endDate) {
        $where[] = 'DATE(o.created_at) BETWEEN :start_date AND :end_date';
        $params['start_date'] = $startDate;
        $params['end_date']   = $endDate;
    }

    if ($type === 'TICKET') {
        $where[] = "EXISTS (SELECT 1 FROM pos_order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'TICKET')";
    } elseif ($type === 'SERVICE') {
        $where[] = "NOT EXISTS (SELECT 1 FROM pos_order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'TICKET')";
    }

    $whereClause = count($where) > 0 ? implode(' AND ', $where) : '1=1';

    $countRow = Database::fetch(
        "SELECT COUNT(DISTINCT o.order_id) as cnt FROM pos_orders o
         WHERE $whereClause",
        $params
    );
    $totalCount = $countRow['cnt'] ?? 0;

    $orders = Database::fetchAll(
        "SELECT
            o.order_id,
            o.order_code as transaction_code,
            COALESCE(o.original_grand_total, o.grand_total) as total_amount,
            o.discount_total as discount_amount,
            o.subtotal as base_amount,
            o.total_service_fees as service_fee,
            o.status,
            COALESCE(o.total_refunded_amount, 0) as total_refunded_amount,
            o.created_at,
            o.branch_id,
            o.created_by,
            o.amount_paid,
            o.change_amount,
            o.total_cost,
            o.total_service_fees,
            o.total_add_ons,
            o.total_profit,
            o.payment_method,
            o.payment_method_ids,
            o.payment_methods_json,
            o.cashier_name,
            b.branch_name,
            (SELECT COUNT(*) FROM pos_order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'TICKET') as ticket_count,
            (SELECT COUNT(*) FROM pos_order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'SERVICE') as service_count,
            (SELECT GROUP_CONCAT(DISTINCT pa2.fullname SEPARATOR ', ')
             FROM pos_order_items oi3
             LEFT JOIN ticket_transactions tt ON oi3.reference_id = tt.transaction_id AND oi3.item_type = 'TICKET'
             LEFT JOIN passenger_accounts pa2 ON tt.passenger_id = pa2.passenger_id
             WHERE oi3.order_id = o.order_id AND oi3.item_type = 'TICKET') as passenger_names,
            (SELECT MIN(tt.travel_date)
             FROM pos_order_items oi4
             LEFT JOIN ticket_transactions tt ON oi4.reference_id = tt.transaction_id AND oi4.item_type = 'TICKET'
             WHERE oi4.order_id = o.order_id AND oi4.item_type = 'TICKET') as travel_date,
            (SELECT GROUP_CONCAT(DISTINCT tp2.provider_name SEPARATOR ', ')
             FROM pos_order_items oi5
             LEFT JOIN ticket_transactions tt ON oi5.reference_id = tt.transaction_id AND oi5.item_type = 'TICKET'
             LEFT JOIN ticket_providers tp2 ON tt.provider_id = tp2.provider_id
             WHERE oi5.order_id = o.order_id AND oi5.item_type = 'TICKET') as provider_name,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(tt.origin, ' → ', tt.destination) SEPARATOR ' | ')
             FROM pos_order_items oi6
             LEFT JOIN ticket_transactions tt ON oi6.reference_id = tt.transaction_id AND oi6.item_type = 'TICKET'
             WHERE oi6.order_id = o.order_id AND oi6.item_type = 'TICKET' AND tt.origin IS NOT NULL) as routes,
            -- Check for pending cancellations on any ticket in this order
            (SELECT tc.cancellation_id
             FROM pos_order_items oi7
             LEFT JOIN ticket_transactions tt ON oi7.reference_id = tt.transaction_id AND oi7.item_type = 'TICKET'
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             WHERE oi7.order_id = o.order_id AND tc.cancellation_id IS NOT NULL
             LIMIT 1) as pending_cancellation_id,
            (SELECT COALESCE(CONCAT(e_req.first_name, ' ', e_req.last_name), ua_req.username)
             FROM pos_order_items oi8
             LEFT JOIN ticket_transactions tt ON oi8.reference_id = tt.transaction_id AND oi8.item_type = 'TICKET'
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             LEFT JOIN user_accounts ua_req ON tc.requested_by = ua_req.user_id
             LEFT JOIN employees e_req ON ua_req.emp_id = e_req.emp_id
             WHERE oi8.order_id = o.order_id AND tc.cancellation_id IS NOT NULL
             LIMIT 1) as cancellation_requested_by,
            (SELECT SUM(tc.refund_amount)
             FROM pos_order_items oi8
             LEFT JOIN ticket_transactions tt ON oi8.reference_id = tt.transaction_id AND oi8.item_type = 'TICKET'
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             WHERE oi8.order_id = o.order_id AND tc.cancellation_id IS NOT NULL) as pending_refund_amount,
            -- Charge amount (debt reversal portion) for pending cancellation
            (SELECT SUM(tc.charge_amount)
             FROM pos_order_items oi8
             LEFT JOIN ticket_transactions tt ON oi8.reference_id = tt.transaction_id AND oi8.item_type = 'TICKET'
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             WHERE oi8.order_id = o.order_id AND tc.cancellation_id IS NOT NULL) as pending_charge_amount,
            -- Cash refund amount (actual cash to give) for pending cancellation
            (SELECT SUM(tc.cash_refund_amount)
             FROM pos_order_items oi8
             LEFT JOIN ticket_transactions tt ON oi8.reference_id = tt.transaction_id AND oi8.item_type = 'TICKET'
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             WHERE oi8.order_id = o.order_id AND tc.cancellation_id IS NOT NULL) as pending_cash_refund_amount,
            -- Count cancelled tickets in this order
            (SELECT COUNT(*)
             FROM pos_order_items oi9
             LEFT JOIN ticket_transactions tt ON oi9.reference_id = tt.transaction_id AND oi9.item_type = 'TICKET'
             WHERE oi9.order_id = o.order_id AND tt.status IN ('cancelled', 'refunded')) as cancelled_ticket_count,
            -- Count refunded services in this order
            (SELECT COUNT(*)
             FROM pos_order_items oi10
             LEFT JOIN service_transactions st ON oi10.reference_id = st.service_txn_id AND oi10.item_type = 'SERVICE'
             WHERE oi10.order_id = o.order_id AND st.status IN ('cancelled', 'refunded')) as cancelled_service_count
         FROM pos_orders o
         LEFT JOIN business_branches b ON o.branch_id = b.branch_id
         LEFT JOIN user_accounts cua ON o.created_by = cua.user_id
         LEFT JOIN employees ce ON cua.emp_id = ce.emp_id
         WHERE $whereClause
         GROUP BY o.order_id
         ORDER BY o.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($params, ['limit' => $limit, 'offset' => $offset])
    );

    // Normalize to match legacy format for JS rendering
    foreach ($orders as &$order) {
        $hasTickets  = $order['ticket_count'] > 0;
        $hasServices = $order['service_count'] > 0;

        $order['transaction_type'] = $hasTickets ? 'TICKET' : 'SERVICE';
        $order['passenger_name']   = $order['passenger_names'] ?: ($order['ticket_count'] > 0 ? 'Multiple Passengers' : '-');
        $order['origin']           = null;
        $order['destination']      = null;
        $order['remarks']          = ($hasTickets && $hasServices)
            ? "{$order['ticket_count']} ticket(s) + {$order['service_count']} service(s)"
            : ($hasTickets ? "{$order['ticket_count']} ticket(s)" : "{$order['service_count']} service(s)");
        $order['provider_type']    = null;
        
        // Add profit information for display
        $order['profit_margin'] = $order['total_amount'] > 0 ? round(($order['total_profit'] / $order['total_amount']) * 100, 1) : 0;
        
        // Ensure payment method is available for display
        if (empty($order['payment_method']) && !empty($order['payments'])) {
            $paymentNames = array_column($order['payments'], 'method_name');
            $order['payment_method'] = implode(' + ', $paymentNames);
        }

        // Fetch line items for this order
        $order['order_items'] = Database::fetchAll(
            "SELECT oi.item_id, oi.order_id, oi.item_type, oi.reference_id, oi.transaction_code, oi.total_amount,
                    tt.passenger_id, tt.origin, tt.destination, tt.travel_date, tt.service_fee, tt.status as ticket_status,
                    st.description as service_name, st_type.name as service_type_name, st.status as service_status,
                    pa.fullname as passenger_name,
                    pw.wallet_id,
                    tp_op.provider_name as provider_name, tp_op.provider_type as provider_type,
                    tp_wallet.provider_name as wallet_provider_name
             FROM pos_order_items oi
             LEFT JOIN ticket_transactions tt ON oi.reference_id = tt.transaction_id AND oi.item_type = 'TICKET'
             LEFT JOIN service_transactions st ON oi.reference_id = st.service_txn_id AND oi.item_type = 'SERVICE'
             LEFT JOIN service_types st_type ON st.service_type_id = st_type.service_type_id
             LEFT JOIN passenger_accounts pa ON COALESCE(tt.passenger_id, st.passenger_id) = pa.passenger_id
             LEFT JOIN provider_wallets pw ON oi.wallet_id = pw.wallet_id
             LEFT JOIN ticket_providers tp_op ON oi.provider_id = tp_op.provider_id
             LEFT JOIN ticket_providers tp_wallet ON pw.provider_id = tp_wallet.provider_id
             WHERE oi.order_id = :oid
             ORDER BY oi.item_type DESC, oi.item_id ASC",
            ['oid' => $order['order_id']]
        );

        // Fetch aggregated payment breakdown for this order.
        // Use the new payment_methods_json if available, otherwise fall back to transaction_payments
        if (!empty($order['payment_methods_json'])) {
            $order['payments'] = json_decode($order['payment_methods_json'], true) ?: [];
        } else {
            // Fallback: We join transaction_payments against all items in the order (TICKET and SERVICE).
            // DISTINCT on payment_method_id + bank_account_id gives one row per payment split.
            $order['payments'] = Database::fetchAll(
                "SELECT pm.method_name,
                        pm.method_type,
                        pm.method_code,
                        pm.tracks_credit,
                        SUM(tp.amount) AS amount
                 FROM transaction_payments tp
                 JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
                 WHERE EXISTS (
                     SELECT 1 FROM pos_order_items oi
                     WHERE oi.order_id = :oid
                       AND oi.reference_id = tp.source_id
                       AND (
                           (oi.item_type = 'TICKET'  AND tp.source_type = 'TICKET_TRANSACTION') OR
                           (oi.item_type = 'SERVICE' AND tp.source_type = 'SERVICE_TRANSACTION')
                       )
                 )
                 GROUP BY pm.method_id
                 ORDER BY pm.sort_order ASC, pm.method_name ASC",
                ['oid' => $order['order_id']]
            );
        }
    }
    unset($order);

    $allTransactions = $orders;

} else {
    // --- Legacy fallback: individual ticket + service queries ---
    if (!$type || $type === 'TICKET') {
        $where  = [];
        $params = [];

        if ($branchId) { $where[] = 'tt.branch_id = :branch_id'; $params['branch_id'] = $branchId; }
        $where[] = 'tt.created_by = :created_by'; $params['created_by'] = $user['user_id'];
        if ($search) { $where[] = '(tt.transaction_code LIKE :search OR pa.fullname LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        if ($status) { $where[] = 'tt.status = :status'; $params['status'] = $status; }
        if ($date) { $where[] = 'DATE(tt.created_at) = :date'; $params['date'] = $date; }
        if ($startDate && $endDate) { $where[] = 'DATE(tt.created_at) BETWEEN :start_date AND :end_date'; $params['start_date'] = $startDate; $params['end_date'] = $endDate; }

        $whereClause = count($where) > 0 ? implode(' AND ', $where) : '1=1';

        $ticketTxns = Database::fetchAll(
            "SELECT tt.transaction_id, tt.transaction_code, tt.base_amount, tt.service_fee, tt.discount_amount,
                    tt.total_amount, tt.status, tt.created_at, tt.travel_date, tt.origin, tt.destination,
                    tt.remarks, tt.branch_id, tt.wallet_id, tt.created_by,
                    'TICKET' as transaction_type,
                    pa.fullname as passenger_name, b.branch_name, tp.provider_name, tp.provider_code, tp.provider_type,
                    tc.cancellation_id as pending_cancellation_id, tc.status as cancellation_status,
                    tc.refund_amount as cancellation_refund_amount, tc.requested_at as cancellation_requested_at,
                    COALESCE(CONCAT(e.first_name, ' ', e.last_name), ua.username) as cancellation_requested_by,
                    COALESCE(CONCAT(ce.first_name, ' ', ce.last_name), cua.username) as cashier_name
             FROM ticket_transactions tt
             LEFT JOIN passenger_accounts pa ON tt.passenger_id = pa.passenger_id
             LEFT JOIN business_branches b ON tt.branch_id = b.branch_id
             LEFT JOIN provider_wallets pw ON tt.wallet_id = pw.wallet_id
             LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
             LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id AND tc.status = 'pending'
             LEFT JOIN user_accounts ua ON tc.requested_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             LEFT JOIN user_accounts cua ON tt.created_by = cua.user_id
             LEFT JOIN employees ce ON cua.emp_id = ce.emp_id
             WHERE $whereClause ORDER BY tt.created_at DESC LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $limit, 'offset' => $offset])
        );
        $allTransactions = array_merge($allTransactions, $ticketTxns);

        $cnt = Database::fetch("SELECT COUNT(*) as count FROM ticket_transactions tt WHERE $whereClause", $params);
        $totalCount += $cnt['count'] ?? 0;
    }

    if (!$type || $type === 'SERVICE') {
        $whereS  = [];
        $paramsS = [];

        if ($branchId) { $whereS[] = 'st.branch_id = :branch_id'; $paramsS['branch_id'] = $branchId; }
        $whereS[] = 'st.created_by = :created_by'; $paramsS['created_by'] = $user['user_id'];
        if ($search) { $whereS[] = '(st.transaction_code LIKE :search OR st.description LIKE :search)'; $paramsS['search'] = '%' . $search . '%'; }
        if ($status) { $whereS[] = 'st.status = :status'; $paramsS['status'] = $status; }
        if ($date) { $whereS[] = 'DATE(st.created_at) = :date'; $paramsS['date'] = $date; }
        if ($startDate && $endDate) { $whereS[] = 'DATE(st.created_at) BETWEEN :start_date AND :end_date'; $paramsS['start_date'] = $startDate; $paramsS['end_date'] = $endDate; }

        $whereSClause = count($whereS) > 0 ? implode(' AND ', $whereS) : '1=1';

        $serviceTxns = Database::fetchAll(
            "SELECT st.service_txn_id as transaction_id, st.transaction_code, st.total_amount as base_amount, 0 as service_fee,
                    0 as discount_amount, st.total_amount, st.status, st.created_at, st.remarks, st.branch_id, st.created_by,
                    'SERVICE' as transaction_type, st.description as passenger_name, b.branch_name,
                    st.service_type_id, st.description,
                    COALESCE(CONCAT(e.first_name, ' ', e.last_name), ua.username) as cashier_name
             FROM service_transactions st
             LEFT JOIN business_branches b ON st.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON st.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE $whereSClause ORDER BY st.created_at DESC LIMIT :limit OFFSET :offset",
            array_merge($paramsS, ['limit' => $limit, 'offset' => $offset])
        );
        $allTransactions = array_merge($allTransactions, $serviceTxns);

        $cnt = Database::fetch("SELECT COUNT(*) as count FROM service_transactions st WHERE $whereSClause", $paramsS);
        $totalCount += $cnt['count'] ?? 0;
    }

    usort($allTransactions, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
    $allTransactions = array_slice($allTransactions, 0, $limit);
}

$totalPages  = $totalCount > 0 ? ceil($totalCount / $limit) : 1;
$currentPage = floor($offset / $limit) + 1;

echo json_encode([
    'success' => true,
    'data' => [
        'transactions' => array_values($allTransactions),
        'pagination' => [
            'total'        => $totalCount,
            'limit'        => $limit,
            'offset'       => $offset,
            'total_pages'  => $totalPages,
            'current_page' => $currentPage
        ]
    ]
]);
