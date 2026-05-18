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
$limit = min(max(intval($limit), 1), 50); // Limit between 1 and 50
$offset = $_GET['offset'] ?? 0;
$offset = max(intval($offset), 0);

// Get filter parameters
$search = $_GET['search'] ?? null;
$type = $_GET['type'] ?? null;
$status = $_GET['status'] ?? null;
$date = $_GET['date'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Get user branch for filtering
$branchId = $user['branch_id'] ?? null;

// Only fetch the requested type if specified
$allTransactions = [];

if (!$type || $type === 'TICKET') {
    // Build WHERE clauses for filters
    $where = [];
    $params = [];

    if ($branchId) {
        $where[] = 'tt.branch_id = :branch_id';
        $params['branch_id'] = $branchId;
    }

    // Filter to show only current user's transactions
    $where[] = 'tt.created_by = :created_by';
    $params['created_by'] = $user['user_id'];

    if ($search) {
        $where[] = '(tt.transaction_code LIKE :search OR pa.fullname LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    if ($status) {
        $where[] = 'tt.status = :status';
        $params['status'] = $status;
    }

    if ($date) {
        $where[] = 'DATE(tt.created_at) = :date';
        $params['date'] = $date;
    }

    if ($startDate && $endDate) {
        $where[] = 'DATE(tt.created_at) BETWEEN :start_date AND :end_date';
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
    }

    $whereClause = count($where) > 0 ? implode(' AND ', $where) : '1=1';

    // Fetch recent ticket transactions with pending cancellation status
    $ticketTxns = Database::fetchAll(
        "SELECT 
            tt.transaction_id,
            tt.transaction_code,
            tt.base_amount,
            tt.service_fee,
            tt.discount_amount,
            tt.total_amount,
            tt.status,
            tt.created_at,
            tt.travel_date,
            tt.origin,
            tt.destination,
            tt.remarks,
            tt.branch_id,
            tt.wallet_id,
            tt.created_by,
            'TICKET' as transaction_type,
            pa.fullname as passenger_name,
            b.branch_name,
            tp.provider_name,
            tp.provider_code,
            tp.provider_type,
            tc.cancellation_id as pending_cancellation_id,
            tc.status as cancellation_status,
            tc.refund_amount as cancellation_refund_amount,
            tc.requested_at as cancellation_requested_at,
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
         WHERE $whereClause
         ORDER BY tt.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($params, ['limit' => $limit, 'offset' => $offset])
    );

    $allTransactions = array_merge($allTransactions, $ticketTxns);
}

if (!$type || $type === 'SERVICE') {
    // Build WHERE clauses for service transactions
    $whereService = [];
    $paramsService = [];

    if ($branchId) {
        $whereService[] = 'st.branch_id = :branch_id';
        $paramsService['branch_id'] = $branchId;
    }

    // Filter to show only current user's transactions
    $whereService[] = 'st.created_by = :created_by';
    $paramsService['created_by'] = $user['user_id'];

    if ($search) {
        $whereService[] = '(st.transaction_code LIKE :search OR st.description LIKE :search)';
        $paramsService['search'] = '%' . $search . '%';
    }

    if ($status) {
        $whereService[] = 'st.status = :status';
        $paramsService['status'] = $status;
    }

    if ($date) {
        $whereService[] = 'DATE(st.created_at) = :date';
        $paramsService['date'] = $date;
    }

    if ($startDate && $endDate) {
        $whereService[] = 'DATE(st.created_at) BETWEEN :start_date AND :end_date';
        $paramsService['start_date'] = $startDate;
        $paramsService['end_date'] = $endDate;
    }

    $whereServiceClause = count($whereService) > 0 ? implode(' AND ', $whereService) : '1=1';

    // Fetch recent service transactions
    $serviceTxns = Database::fetchAll(
        "SELECT 
            st.service_txn_id as transaction_id,
            st.transaction_code,
            st.quantity,
            st.unit_price,
            st.total_amount as base_amount,
            0 as service_fee,
            0 as discount_amount,
            st.total_amount,
            st.status,
            st.created_at,
            st.remarks,
            st.branch_id,
            st.created_by,
            'SERVICE' as transaction_type,
            st.description as passenger_name,
            b.branch_name,
            st.service_type_id,
            st.description,
            COALESCE(CONCAT(e.first_name, ' ', e.last_name), ua.username) as cashier_name
         FROM service_transactions st
         LEFT JOIN business_branches b ON st.branch_id = b.branch_id
         LEFT JOIN user_accounts ua ON st.created_by = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         WHERE $whereServiceClause
         ORDER BY st.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($paramsService, ['limit' => $limit, 'offset' => $offset])
    );

    $allTransactions = array_merge($allTransactions, $serviceTxns);
}

// Sort by date
usort($allTransactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate total count for pagination
$totalCount = 0;
if (!$type || $type === 'TICKET') {
    $ticketCount = Database::fetch(
        "SELECT COUNT(*) as count FROM ticket_transactions tt WHERE $whereClause",
        $params
    );
    $totalCount += $ticketCount['count'] ?? 0;
}
if (!$type || $type === 'SERVICE') {
    $serviceCount = Database::fetch(
        "SELECT COUNT(*) as count FROM service_transactions st WHERE $whereServiceClause",
        $paramsService
    );
    $totalCount += $serviceCount['count'] ?? 0;
}

$totalPages = ceil($totalCount / $limit);
$currentPage = floor($offset / $limit) + 1;

// Limit to requested number
$allTransactions = array_slice($allTransactions, 0, $limit);

echo json_encode([
    'success' => true,
    'data' => [
        'transactions' => array_values($allTransactions),
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => $totalPages,
            'current_page' => $currentPage
        ]
    ]
]);
