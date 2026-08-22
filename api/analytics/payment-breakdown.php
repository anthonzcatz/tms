<?php
/**
 * Payment Breakdown API
 * Returns payment method distribution by revenue
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/AnalyticsFilter.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $filter = AnalyticsFilter::parse($_GET, $user);
    $branchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'payments_branch');
    $dateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'payments_date');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $dateWhere = 'AND ' . $dateScope['sql'];
    
    // Get payment breakdown by revenue using actual payment methods
    $params = array_merge($branchScope['params'], $dateScope['params']);
    
    $paymentData = Database::fetchAll(
        "SELECT 
            po.payment_method,
            COALESCE(SUM(po.grand_total), 0) as total_amount,
            COUNT(DISTINCT po.order_id) as transaction_count
         FROM pos_orders po
         WHERE po.status = 'completed'
           $dateWhere
           $branchWhere
         GROUP BY po.payment_method
         ORDER BY total_amount DESC",
        $params
    );

    // Get all payment methods from database
    $allPaymentMethods = Database::fetchAll(
        "SELECT method_id, method_name FROM payment_methods ORDER BY method_name"
    );

    // Build breakdown using actual payment methods
    $breakdown = [];
    $total = 0;

    // Initialize all payment methods with 0
    foreach ($allPaymentMethods as $pm) {
        $breakdown[$pm['method_name']] = [
            'amount' => 0,
            'percent' => 0,
            'transaction_count' => 0
        ];
    }

    // Sum up actual payment data
    foreach ($paymentData as $payment) {
        $methodName = $payment['payment_method'] ?? 'Other';
        $amount = floatval($payment['total_amount']);
        $count = intval($payment['transaction_count']);
        $total += $amount;

        if (!isset($breakdown[$methodName])) {
            $breakdown[$methodName] = [
                'amount' => 0,
                'percent' => 0,
                'transaction_count' => 0
            ];
        }

        $breakdown[$methodName]['amount'] += $amount;
        $breakdown[$methodName]['transaction_count'] += $count;
    }

    // Calculate percentages
    foreach ($breakdown as $methodName => &$data) {
        $data['percent'] = $total > 0 ? round(($data['amount'] / $total) * 100, 1) : 0;
    }

    // Sort by amount descending
    uasort($breakdown, function($a, $b) {
        return $b['amount'] <=> $a['amount'];
    });

    echo json_encode([
        'success' => true,
        'data' => [
            'breakdown' => $breakdown,
            'total' => $total,
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Payment Breakdown API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load payment breakdown']);
}
