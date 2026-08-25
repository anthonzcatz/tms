<?php
/**
 * Cashier Shifts API
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']); exit;
}

$sessionId = $_GET['session_id'] ?? null;

// Decode session_id if provided
if ($sessionId) {
    $decodedSessionId = IdEncoder::decode($sessionId);
    if ($decodedSessionId === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
        exit;
    }
    $sessionId = $decodedSessionId;
}

if (!$sessionId) { echo json_encode(['success' => false, 'error' => 'session_id required']); exit; }

$session = Database::fetch(
    "SELECT cs.*,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS cashier_name,
            bb.branch_name,
            COALESCE(CONCAT_WS(' ', e_rev.first_name, e_rev.last_name), ua_rev.username) AS reviewed_by_name
     FROM cashier_sessions cs
     JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua_rev ON cs.reviewed_by = ua_rev.user_id
     LEFT JOIN employees e_rev ON ua_rev.emp_id = e_rev.emp_id
     WHERE cs.session_id = :id",
    ['id' => $sessionId]
);

if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found']); exit; }

try {
    PosAccess::assertBranchAccess($user, (int) $session['branch_id']);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Transactions in this session (from pos_orders) with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$whereClause = "po.cashier_session_id = :sid AND po.status = 'completed'";
$params = ['sid' => $sessionId];

if ($search) {
    $whereClause .= " AND (po.order_code LIKE :search OR po.payment_method LIKE :search)";
    $params['search'] = "%{$search}%";
}

// Get total count for pagination
$totalCount = Database::fetch(
    "SELECT COUNT(*) AS total FROM pos_orders po WHERE {$whereClause}",
    $params
)['total'];

// Get paginated transactions
$transactions = Database::fetchAll(
    "SELECT po.order_id, po.order_code, po.grand_total, po.amount_paid, po.change_amount,
            po.payment_method, po.status, po.created_at,
            po.cashier_name, po.cashier_user_id,
            (SELECT COUNT(*) FROM pos_order_items WHERE order_id = po.order_id) AS item_count
     FROM pos_orders po
     WHERE {$whereClause}
     ORDER BY po.created_at ASC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

// Payment breakdown by method (filter by session ID, not cashier ID)
$paymentWhere = "AND tp.created_at >= :start
                  AND NOT EXISTS (
                      SELECT 1
                      FROM ticket_cancellations tc_void
                      WHERE tp.source_type = 'TICKET_TRANSACTION'
                        AND tc_void.transaction_id = tp.source_id
                        AND tc_void.operation_type = 'VOID'
                        AND tc_void.status = 'completed'
                  )";
$paymentParams = [
    'sid'   => $session['session_id'],
    'start' => $session['started_at']
];
if ($session['ended_at']) {
    $paymentWhere .= " AND tp.created_at <= :end";
    $paymentParams['end'] = $session['ended_at'];
}

$payments = Database::fetchAll(
    "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order,
            COALESCE(SUM(tp.amount), 0) AS gross_amount,
            COALESCE(SUM(LEAST(tp.amount, COALESCE(refunds.refunded_amount, 0))), 0) AS refunded_amount,
            COALESCE(SUM(GREATEST(0, tp.amount - COALESCE(refunds.refunded_amount, 0))), 0) AS active_amount,
            COALESCE(SUM(tp.amount), 0) AS total_amount
     FROM payment_methods pm
     LEFT JOIN transaction_payments tp
       ON tp.payment_method_id = pm.method_id
      AND tp.cashier_session_id = :sid
      $paymentWhere
     LEFT JOIN (
         SELECT source_payment_id, SUM(amount) AS refunded_amount
         FROM refund_allocations
         WHERE refund_scope IN ('TICKET', 'SERVICE')
           AND status = 'PROCESSED'
         GROUP BY source_payment_id
     ) refunds ON refunds.source_payment_id = tp.payment_id
     WHERE (pm.is_active = 1 OR tp.payment_id IS NOT NULL)
     GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order
     ORDER BY pm.sort_order ASC, pm.method_name ASC",
    $paymentParams
);

$voidedCashAmount = (float) (Database::fetch(
    "SELECT COALESCE(SUM(tp.amount), 0) AS total
     FROM ticket_cancellations tc
     JOIN transaction_payments tp
       ON tp.source_type = 'TICKET_TRANSACTION'
      AND tp.source_id = tc.transaction_id
      AND tp.cashier_session_id = :payment_session_id
     JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
     WHERE tc.cashier_session_id = :void_session_id
       AND tc.operation_type = 'VOID'
       AND tc.status = 'completed'
       AND pm.include_in_expected_cash = 1
       AND tp.confirmation_status <> 'REJECTED'",
    [
        'payment_session_id' => (int) $session['session_id'],
        'void_session_id' => (int) $session['session_id'],
    ]
)['total'] ?? 0);

$voidSummary = Database::fetch(
    "SELECT
            COALESCE(SUM(COALESCE(tt.total_amount, 0)), 0) AS voided_ticket_amount,
            COALESCE(SUM(
                CASE
                    WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                    ELSE COALESCE(tc.void_fee, 0) + COALESCE(tc.void_service_fee, 0)
                END
            ), 0) AS void_income,
            COALESCE(SUM(
                CASE WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                     THEN COALESCE(NULLIF(tc.lost_sales_void_fee, 0), tc.void_fee, 0)
                          + COALESCE(tc.lost_sales_service_fee, 0)
                          + COALESCE(tc.void_service_fee, 0)
                     ELSE 0 END
            ), 0) AS void_lost_sales
     FROM ticket_cancellations tc
     JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
     WHERE COALESCE(NULLIF(tc.cashier_session_id, 0), tt.cashier_session_id) = :session_id
       AND tc.operation_type = 'VOID'
       AND tc.status = 'completed'",
    ['session_id' => (int) $session['session_id']]
) ?: [];
$voidedTicketAmount = round((float) ($voidSummary['voided_ticket_amount'] ?? 0), 2);
$voidIncome = round((float) ($voidSummary['void_income'] ?? 0), 2);
$voidLostSalesAmount = round((float) ($voidSummary['void_lost_sales'] ?? 0), 2);
$refundedSalesAmount = (float) (Database::fetch(
    "SELECT COALESCE(SUM(COALESCE(NULLIF(tc.refund_amount, 0), tc.gross_refund_amount, 0)), 0) AS total
     FROM ticket_cancellations tc
     JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
     WHERE tt.cashier_session_id = :session_id
       AND tc.operation_type = 'REFUND'
       AND tc.status = 'completed'",
    ['session_id' => (int) $session['session_id']]
)['total'] ?? 0);

// Calculate expected cash based on payment methods with include_in_expected_cash flag
$expectedCashPayments = 0.0;
$paymentRefundsAmount = 0.0;
foreach ($payments as $payment) {
    $paymentRefundsAmount += (float) ($payment['refunded_amount'] ?? 0);
    if ((int) $payment['include_in_expected_cash'] === 1) {
        $expectedCashPayments += (float) ($payment['total_amount'] ?? 0);
    }
}
$completedRefundsAmount = max(
    $refundedSalesAmount,
    $paymentRefundsAmount,
    (float) ($session['total_refunds'] ?? 0)
);

// Total cash change disbursed from the drawer during the session
$totalCashChange = PosAccess::sessionTotalCashChange(
    (int) $session['session_id'],
    $session['started_at'],
    $session['ended_at'] ?: null
);

// Add expected cash to session data
$totalCashAdjustments = floatval($session['total_cash_adjustments'] ?? 0);
$session['void_income'] = $voidIncome;
$session['technical_lost_sales_amount'] = $voidLostSalesAmount;
$session['approved_refunds_amount'] = round($completedRefundsAmount, 2);
$session['refunded_sales_amount'] = $refundedSalesAmount;
$session['voided_sales_amount'] = round($voidedTicketAmount + $voidLostSalesAmount, 2);
$session['net_sales'] = round(max(
    0,
    (float) ($session['total_sales'] ?? 0)
        - $session['refunded_sales_amount']
        - $session['voided_sales_amount']
        + $session['void_income']
), 2);
$session['expected_cash'] = $session['starting_cash'] + $expectedCashPayments
    + $voidIncome - $voidLostSalesAmount
    - $totalCashChange - $completedRefundsAmount - $totalCashAdjustments;
$session['total_cash_change'] = $totalCashChange;
$session['total_cash_adjustments'] = $totalCashAdjustments;
$session['voided_cash_amount'] = $voidedCashAmount;

// Pagination metadata
$totalPages = ceil($totalCount / $limit);
$pagination = [
    'current_page' => $page,
    'per_page' => $limit,
    'total' => $totalCount,
    'total_pages' => $totalPages
];

echo json_encode(['success' => true, 'data' => ['session' => $session, 'transactions' => $transactions, 'payments' => $payments, 'pagination' => $pagination]]);
