<?php
/**
 * Payment Breakdown API
 * Returns payment method distribution by revenue
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;
    $filterBranchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? $_GET['branch_id'] : null;

    // Build branch restriction
    $branchWhere = '';
    $branchParams = [];

    if ($filterBranchId) {
        $allowed = ($userRoleCode === 'SUPER_ADMIN')
            || ($userBranchId && in_array($filterBranchId, array_map('trim', explode(',', $userBranchId))));
        if ($allowed) {
            $branchWhere = "AND po.branch_id = :branch_id";
            $branchParams['branch_id'] = $filterBranchId;
        } elseif ($userBranchId) {
            $branchIds = array_map('trim', explode(',', $userBranchId));
            $namedParams = [];
            foreach ($branchIds as $i => $bid) { $namedParams['bid_' . $i] = $bid; }
            $placeholders = implode(',', array_keys($namedParams));
            $branchWhere = "AND po.branch_id IN ($placeholders)";
            $branchParams = $namedParams;
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) { $namedParams['bid_' . $i] = $bid; }
        $placeholders = implode(',', array_keys($namedParams));
        $branchWhere = "AND po.branch_id IN ($placeholders)";
        $branchParams = $namedParams;
    }

    // Get date range filter
    $range = isset($_GET['range']) ? $_GET['range'] : 'today';
    $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
    $endDate   = isset($_GET['end_date'])   && $_GET['end_date']   !== '' ? $_GET['end_date']   : null;

    // Build date condition
    $dateWhere = '';

    if ($startDate && $endDate) {
        $dateWhere = "AND DATE(po.created_at) BETWEEN :sd AND :ed";
        $branchParams['sd'] = $startDate;
        $branchParams['ed'] = $endDate;
    } elseif ($range === 'today') {
        $dateWhere = "AND DATE(po.created_at) = CURDATE()";
    } elseif ($range === 'week') {
        $dateWhere = "AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($range === 'month') {
        $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    } elseif ($range === 'year') {
        $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
    }
    
    // Get payment breakdown by revenue using actual payment methods
    $params = array_merge($branchParams);
    
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
            'total' => $total
        ]
    ]);

} catch (Exception $e) {
    error_log('Payment Breakdown API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
