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
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosTransactionReporting.php';

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
    $transactionFrom = PosTransactionReporting::orderFrom();
    $transactionGross = PosTransactionReporting::grossExpression();
    $transactionStatus = PosTransactionReporting::saleStatusCondition();
    
    // Read the same payment split stored on each POS transaction.
    $params = array_merge($branchScope['params'], $dateScope['params']);
    $paymentData = Database::fetchAll(
        "SELECT
            po.order_id,
            $transactionGross AS gross_amount,
            po.payment_method,
            po.payment_methods_json
         $transactionFrom
         WHERE $transactionStatus
           $dateWhere
           $branchWhere
         ORDER BY po.order_id",
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

    $paymentOrderIds = [];
    foreach ($paymentData as $payment) {
        $orderId = (string)($payment['order_id'] ?? '');
        $orderPayments = [];
        if (!empty($payment['payment_methods_json'])) {
            $decodedPayments = json_decode($payment['payment_methods_json'], true);
            if (is_array($decodedPayments)) {
                $orderPayments = $decodedPayments;
            }
        }
        if (empty($orderPayments) && trim((string)($payment['payment_method'] ?? '')) !== '') {
            $orderPayments[] = [
                'method_name' => $payment['payment_method'],
                'amount' => (float)($payment['gross_amount'] ?? 0)
            ];
        }

        foreach ($orderPayments as $orderPayment) {
            $methodName = trim((string)($orderPayment['method_name']
                ?? $orderPayment['method_code']
                ?? '')) ?: 'Other';
            $amount = max(0, (float)($orderPayment['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }
            if (!isset($breakdown[$methodName])) {
                $breakdown[$methodName] = [
                    'amount' => 0,
                    'percent' => 0,
                    'transaction_count' => 0
                ];
            }
            $breakdown[$methodName]['amount'] += $amount;
            $paymentOrderIds[$methodName][$orderId] = true;
            $total += $amount;
        }
    }

    foreach ($breakdown as $methodName => &$data) {
        $data['transaction_count'] = count($paymentOrderIds[$methodName] ?? []);
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
