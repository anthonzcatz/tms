<?php
/**
 * Completed POS refund history API.
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::canAccessModule('admin/refund-confirmations/')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$filterStatus = strtolower(trim((string)($_GET['status'] ?? 'completed')));
$validStatuses = ['pending', 'processing', 'completed', 'failed', 'all'];
if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = 'completed';
}

$filterBranchId = trim((string)($_GET['branch_id'] ?? ''));
$filterProviderId = trim((string)($_GET['provider_id'] ?? ''));
$filterCashierId = trim((string)($_GET['cashier_id'] ?? ''));
$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo = trim((string)($_GET['date_to'] ?? ''));
$filterSearch = trim((string)($_GET['search'] ?? ''));

$parseDate = static function (string $value): ?DateTimeImmutable {
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
};

$dateFrom = $parseDate($filterDateFrom);
$dateTo = $parseDate($filterDateTo);
if (($filterDateFrom !== '' && !$dateFrom) || ($filterDateTo !== '' && !$dateTo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date filter.']);
    exit;
}

$limit = max(1, min(100, (int)($_GET['limit'] ?? 15)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$processedAtExpression = 'COALESCE(tc.processed_at, tr.processed_at)';
$processorExpression = 'COALESCE(tr.processed_by, tc.approved_by, tc.requested_by)';
$originalCashierExpression = 'COALESCE(o.created_by, tt.created_by)';
$branchExpression = 'COALESCE(tt.branch_id, o.branch_id)';
$providerExpression = 'COALESCE(tt.provider_id, oi.provider_id)';
$refundAmountExpression = 'COALESCE(NULLIF(tr.refund_amount, 0), NULLIF(tc.refund_amount, 0), NULLIF(tc.gross_refund_amount, 0), 0)';

$where = [
    "tc.operation_type = 'REFUND'",
    "tc.status = 'completed'",
    "$processedAtExpression IS NOT NULL",
];
$params = [];

PosAccess::applyBranchScope($where, $params, $branchExpression, $user, 'refund_history_branch');

if ($filterBranchId !== '') {
    if (!ctype_digit($filterBranchId) || (int)$filterBranchId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid branch ID.']);
        exit;
    }
    try {
        PosAccess::assertBranchAccess($user, (int)$filterBranchId);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $where[] = "$branchExpression = :history_branch_id";
    $params['history_branch_id'] = (int)$filterBranchId;
}

if ($filterProviderId !== '') {
    if (!ctype_digit($filterProviderId) || (int)$filterProviderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid provider ID.']);
        exit;
    }
    $where[] = "$providerExpression = :history_provider_id";
    $params['history_provider_id'] = (int)$filterProviderId;
}

if ($filterCashierId !== '') {
    if (!ctype_digit($filterCashierId) || (int)$filterCashierId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid cashier ID.']);
        exit;
    }
    $where[] = "$processorExpression = :history_cashier_id";
    $params['history_cashier_id'] = (int)$filterCashierId;
}

if ($filterStatus !== 'all') {
    $where[] = "COALESCE(tr.status, 'completed') = :history_status";
    $params['history_status'] = $filterStatus;
}

if ($dateFrom) {
    $where[] = "$processedAtExpression >= :history_date_from";
    $params['history_date_from'] = $dateFrom->format('Y-m-d H:i:s');
}
if ($dateTo) {
    $where[] = "$processedAtExpression < :history_date_to_exclusive";
    $params['history_date_to_exclusive'] = $dateTo->modify('+1 day')->format('Y-m-d H:i:s');
}

if ($filterSearch !== '') {
    $searchFields = [
        'tr.transaction_code',
        'tc.transaction_code',
        'tt.ticket_number',
        'o.order_code',
        'pa.fullname',
        'tp.provider_name',
        'bb.branch_name',
        'ua_original.username',
        'e_original.first_name',
        'e_original.last_name',
        'ua_processor.username',
        'e_processor.first_name',
        'e_processor.last_name',
    ];
    $searchParts = [];
    foreach ($searchFields as $index => $field) {
        $placeholder = ':history_search_' . $index;
        $searchParts[] = $field . ' LIKE ' . $placeholder;
        $params['history_search_' . $index] = '%' . $filterSearch . '%';
    }
    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}

$whereClause = implode(' AND ', $where);
$historyFrom = "FROM ticket_cancellations tc
    INNER JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
    LEFT JOIN ticket_refunds tr ON tr.cancellation_id = tc.cancellation_id
    LEFT JOIN pos_order_items oi
        ON oi.reference_id = tt.transaction_id AND oi.item_type = 'TICKET'
    LEFT JOIN pos_orders o ON o.order_id = oi.order_id
    LEFT JOIN ticket_providers tp ON tp.provider_id = $providerExpression
    LEFT JOIN business_branches bb ON bb.branch_id = $branchExpression
    LEFT JOIN passenger_accounts pa ON pa.passenger_id = tt.passenger_id
    LEFT JOIN user_accounts ua_original ON ua_original.user_id = $originalCashierExpression
    LEFT JOIN employees e_original ON e_original.emp_id = ua_original.emp_id
    LEFT JOIN user_accounts ua_processor ON ua_processor.user_id = $processorExpression
    LEFT JOIN employees e_processor ON e_processor.emp_id = ua_processor.emp_id
    LEFT JOIN user_accounts ua_request ON ua_request.user_id = tc.requested_by
    LEFT JOIN employees e_request ON e_request.emp_id = ua_request.emp_id
    LEFT JOIN user_accounts ua_approve ON ua_approve.user_id = tc.approved_by
    LEFT JOIN employees e_approve ON e_approve.emp_id = ua_approve.emp_id
    LEFT JOIN cashier_sessions refund_session ON refund_session.session_id = tr.cashier_session_id";

$summary = Database::fetch(
    "SELECT
        COUNT(DISTINCT tc.cancellation_id) AS refund_count,
        COALESCE(SUM($refundAmountExpression), 0) AS total_refund,
        COALESCE(SUM(COALESCE(tr.cash_amount, tc.cash_refund_amount, 0)), 0) AS total_cash,
        COALESCE(SUM(COALESCE(tr.charge_reversal_amount, tc.charge_amount, 0)), 0) AS total_charge,
        COALESCE(SUM(COALESCE(tr.bank_amount, 0)), 0) AS total_bank,
        COALESCE(SUM(COALESCE(tr.other_amount, 0)), 0) AS total_other
     $historyFrom
     WHERE $whereClause",
    $params
) ?: [];

$dataParams = $params;
$dataParams['limit'] = $limit;
$dataParams['offset'] = $offset;
$rows = Database::fetchAll(
    "SELECT
        COALESCE(tr.refund_id, 0) AS refund_id,
        CONCAT(CASE WHEN tr.refund_id IS NULL THEN 'cancellation-' ELSE 'refund-' END, COALESCE(tr.refund_id, tc.cancellation_id)) AS history_id,
        tc.cancellation_id,
        tt.transaction_id AS transaction_id,
        COALESCE(NULLIF(tr.transaction_code, ''), tc.transaction_code, tt.transaction_code) AS transaction_code,
        tt.ticket_number,
        o.order_code,
        o.status AS order_status,
        COALESCE(o.created_at, tt.created_at) AS original_sale_at,
        tt.created_at AS ticket_sale_at,
        $originalCashierExpression AS original_cashier_id,
        COALESCE(
            NULLIF(CONCAT_WS(' ',
                NULLIF(TRIM(e_original.first_name), ''),
                IF(e_original.middle_name IS NOT NULL AND e_original.middle_name != '', CONCAT(UPPER(LEFT(e_original.middle_name, 1)), '.'), NULL),
                NULLIF(TRIM(e_original.last_name), '')
            ), ''),
            ua_original.username,
            o.cashier_name,
            'Unassigned'
        ) AS original_cashier_name,
        $processorExpression AS refund_processor_id,
        COALESCE(
            NULLIF(CONCAT_WS(' ',
                NULLIF(TRIM(e_processor.first_name), ''),
                IF(e_processor.middle_name IS NOT NULL AND e_processor.middle_name != '', CONCAT(UPPER(LEFT(e_processor.middle_name, 1)), '.'), NULL),
                NULLIF(TRIM(e_processor.last_name), '')
            ), ''),
            ua_processor.username,
            'Unassigned'
        ) AS refund_processor_name,
        COALESCE(
            NULLIF(CONCAT_WS(' ',
                NULLIF(TRIM(e_request.first_name), ''),
                IF(e_request.middle_name IS NOT NULL AND e_request.middle_name != '', CONCAT(UPPER(LEFT(e_request.middle_name, 1)), '.'), NULL),
                NULLIF(TRIM(e_request.last_name), '')
            ), ''),
            ua_request.username,
            'Unassigned'
        ) AS requested_by_name,
        COALESCE(
            NULLIF(CONCAT_WS(' ',
                NULLIF(TRIM(e_approve.first_name), ''),
                IF(e_approve.middle_name IS NOT NULL AND e_approve.middle_name != '', CONCAT(UPPER(LEFT(e_approve.middle_name, 1)), '.'), NULL),
                NULLIF(TRIM(e_approve.last_name), '')
            ), ''),
            ua_approve.username,
            'Unassigned'
        ) AS approved_by_name,
        $processedAtExpression AS refund_processed_at,
        COALESCE(tr.requested_at, tc.requested_at) AS refund_requested_at,
        tc.requested_at AS cancellation_requested_at,
        tc.approved_at AS cancellation_approved_at,
        tc.processed_at AS cancellation_processed_at,
        $branchExpression AS branch_id,
        bb.branch_name,
        $providerExpression AS provider_id,
        COALESCE(tp.provider_name, 'Unassigned') AS provider_name,
        pa.fullname AS passenger_name,
        tt.origin,
        tt.destination,
        tt.travel_date,
        tt.status AS ticket_status,
        tt.total_amount AS original_ticket_amount,
        $refundAmountExpression AS refund_amount,
        COALESCE(tr.gross_refund_amount, tc.gross_refund_amount, $refundAmountExpression) AS gross_refund_amount,
        COALESCE(tr.cash_amount, tc.cash_refund_amount, 0) AS cash_amount,
        COALESCE(tr.charge_reversal_amount, tc.charge_amount, 0) AS charge_reversal_amount,
        COALESCE(tr.bank_amount, 0) AS bank_amount,
        COALESCE(tr.other_amount, 0) AS other_amount,
        COALESCE(tr.refund_method, 'cash') AS refund_method,
        COALESCE(tr.status, 'completed') AS refund_status,
        tc.status AS cancellation_status,
        tc.cancellation_type,
        tc.reason,
        tc.reason_category,
        COALESCE(tr.cashier_session_id, tc.cashier_session_id) AS refund_cashier_session_id,
        COALESCE(refund_session.branch_id, $branchExpression) AS refund_session_branch_id
     $historyFrom
     WHERE $whereClause
     ORDER BY $processedAtExpression DESC, tr.refund_id DESC
     LIMIT :limit OFFSET :offset",
    $dataParams
);

foreach ($rows as &$row) {
    $row['refund_id'] = (int)($row['refund_id'] ?? 0);
    $row['history_id'] = (string)($row['history_id'] ?? ('refund-' . $row['refund_id']));
    $row['cancellation_id'] = (int)($row['cancellation_id'] ?? 0);
    $row['transaction_id'] = (int)($row['transaction_id'] ?? 0);
    $row['allocations'] = $row['refund_id'] > 0
        ? Database::fetchAll(
            "SELECT
                ra.allocation_id,
                ra.refund_route,
                ra.payment_method_type,
                pm.method_name,
                ra.amount,
                ra.status,
                ra.bank_account_id,
                ba.bank_name,
                ra.created_at,
                ra.processed_at
             FROM refund_allocations ra
             LEFT JOIN payment_methods pm ON pm.method_id = ra.payment_method_id
             LEFT JOIN bank_accounts ba ON ba.bank_account_id = ra.bank_account_id
             WHERE ra.refund_scope = 'TICKET'
               AND ra.refund_id = :refund_id
             ORDER BY ra.allocation_id ASC",
            ['refund_id' => $row['refund_id']]
        )
        : [];
}
unset($row);

$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'refund_history_dropdown');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

$providers = Database::fetchAll(
    "SELECT provider_id, provider_name
     FROM ticket_providers
     WHERE status = 'active'
     ORDER BY provider_name"
);

$cashierWhere = [
    "tc.operation_type = 'REFUND'",
    "tc.status = 'completed'",
    "$processedAtExpression IS NOT NULL",
];
$cashierParams = [];
PosAccess::applyBranchScope($cashierWhere, $cashierParams, $branchExpression, $user, 'refund_history_cashiers');
$cashiers = Database::fetchAll(
    "SELECT DISTINCT
        $processorExpression AS user_id,
        COALESCE(
            NULLIF(CONCAT_WS(' ',
                NULLIF(TRIM(e_processor.first_name), ''),
                IF(e_processor.middle_name IS NOT NULL AND e_processor.middle_name != '', CONCAT(UPPER(LEFT(e_processor.middle_name, 1)), '.'), NULL),
                NULLIF(TRIM(e_processor.last_name), '')
            ), ''),
            ua_processor.username,
            'Unassigned'
        ) AS cashier_name
     $historyFrom
     WHERE " . implode(' AND ', $cashierWhere) . "
     ORDER BY cashier_name",
    $cashierParams
);

$total = (int)($summary['refund_count'] ?? 0);
$totalPages = max(1, (int)ceil($total / $limit));

echo json_encode([
    'success' => true,
    'data' => $rows,
    'summary' => [
        'refund_count' => $total,
        'total_refund' => round((float)($summary['total_refund'] ?? 0), 2),
        'total_cash' => round((float)($summary['total_cash'] ?? 0), 2),
        'total_charge' => round((float)($summary['total_charge'] ?? 0), 2),
        'total_bank' => round((float)($summary['total_bank'] ?? 0), 2),
        'total_other' => round((float)($summary['total_other'] ?? 0), 2),
    ],
    'filters' => [
        'branches' => $branches,
        'providers' => $providers,
        'cashiers' => $cashiers,
    ],
    'pagination' => [
        'total' => $total,
        'per_page' => $limit,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'from' => $total > 0 ? $offset + 1 : 0,
        'to' => min($offset + $limit, $total),
    ],
]);
