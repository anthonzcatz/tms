<?php
/**
 * Test script to verify Cashier Performance API data accuracy
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/bootstrap.php';

echo "=== Cashier Performance Data Verification ===\n\n";

// Test with 7 days range
$days = 7;
$branchId = null; // All branches

echo "Testing with days=$days, branch_id=" . ($branchId ?? 'null') . "\n\n";

// Get top performing cashiers (same query as API)
$cashierParams = ['days' => $days];
$topCashiers = Database::fetchAll(
    "SELECT 
        cs.cashier_user_id,
        CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) as cashier_name,
        COUNT(po.order_id) as transaction_count,
        COALESCE(SUM(po.grand_total), 0) as total_sales,
        COALESCE(AVG(po.grand_total), 0) as avg_transaction
     FROM pos_orders po
     INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
     INNER JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     WHERE po.status = 'completed'
       AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
     GROUP BY cs.cashier_user_id, e.first_name, e.last_name
     ORDER BY total_sales DESC
     LIMIT 5",
    $cashierParams
);

echo "Top Cashiers:\n";
echo str_repeat("-", 80) . "\n";
printf("%-30s %15s %10s %15s\n", "Cashier Name", "Total Sales", "Txn Count", "Avg Txn");
echo str_repeat("-", 80) . "\n";

$totalSales = 0;
$totalTxns = 0;

foreach ($topCashiers as $cashier) {
    printf("%-30s %15.2f %10d %15.2f\n", 
        $cashier['cashier_name'],
        $cashier['total_sales'],
        $cashier['transaction_count'],
        $cashier['avg_transaction']
    );
    $totalSales += $cashier['total_sales'];
    $totalTxns += $cashier['transaction_count'];
}

echo str_repeat("-", 80) . "\n";
printf("%-30s %15.2f %10d\n", "TOTAL", $totalSales, $totalTxns);
echo "\n";

// Verify against direct pos_orders query
echo "=== Verification Against Direct pos_orders Query ===\n\n";

$directQuery = Database::fetch(
    "SELECT 
        COUNT(DISTINCT po.order_id) as total_orders,
        COALESCE(SUM(po.grand_total), 0) as total_grand_total,
        COUNT(DISTINCT cs.cashier_user_id) as distinct_cashiers
     FROM pos_orders po
     INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
     WHERE po.status = 'completed'
       AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
    ['days' => $days]
);

echo "Direct Query Results:\n";
echo str_repeat("-", 50) . "\n";
printf("Total Orders: %d\n", $directQuery['total_orders']);
printf("Total Grand Total: ₱%.2f\n", $directQuery['total_grand_total']);
printf("Distinct Cashiers: %d\n", $directQuery['distinct_cashiers']);
echo "\n";

// Check for discrepancies
echo "=== Discrepancy Check ===\n\n";

if (abs($totalSales - $directQuery['total_grand_total']) > 0.01) {
    echo "WARNING: Sales total mismatch!\n";
    echo "API Total: ₱" . number_format($totalSales, 2) . "\n";
    echo "Direct Total: ₱" . number_format($directQuery['total_grand_total'], 2) . "\n";
    echo "Difference: ₱" . number_format(abs($totalSales - $directQuery['total_grand_total']), 2) . "\n";
} else {
    echo "✓ Sales totals match perfectly\n";
}

if ($totalTxns != $directQuery['total_orders']) {
    echo "WARNING: Transaction count mismatch!\n";
    echo "API Total: " . $totalTxns . "\n";
    echo "Direct Total: " . $directQuery['total_orders'] . "\n";
    echo "Difference: " . abs($totalTxns - $directQuery['total_orders']) . "\n";
} else {
    echo "✓ Transaction counts match perfectly\n";
}

echo "\n";

// Test hourly breakdown for today
echo "=== Today's Hourly Breakdown Test ===\n\n";

$todayCashiers = Database::fetchAll(
    "SELECT 
        cs.cashier_user_id,
        CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) as cashier_name,
        COUNT(po.order_id) as transaction_count,
        COALESCE(SUM(po.grand_total), 0) as total_sales
     FROM pos_orders po
     INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
     INNER JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     WHERE po.status = 'completed'
       AND DATE(po.created_at) = CURDATE()
     GROUP BY cs.cashier_user_id, e.first_name, e.last_name
     ORDER BY total_sales DESC
     LIMIT 3"
);

echo "Today's Top Cashiers:\n";
foreach ($todayCashiers as $cashier) {
    printf("- %s: ₱%.2f (%d transactions)\n", 
        $cashier['cashier_name'],
        $cashier['total_sales'],
        $cashier['transaction_count']
    );
}

echo "\n";

// Sample hourly data for first cashier
if (!empty($todayCashiers)) {
    $firstCashier = $todayCashiers[0];
    echo "Hourly breakdown for {$firstCashier['cashier_name']}:\n";
    
    for ($i = 0; $i < 24; $i++) {
        $hour = sprintf('%02d:00', $i);
        $result = Database::fetch(
            "SELECT COALESCE(SUM(po.grand_total), 0) as sales,
                    COUNT(po.order_id) as txns
             FROM pos_orders po
             INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
             WHERE po.status = 'completed'
               AND DATE(po.created_at) = CURDATE()
               AND HOUR(po.created_at) = :hour
               AND cs.cashier_user_id = :cashier_id",
            ['hour' => $i, 'cashier_id' => $firstCashier['cashier_user_id']]
        );
        
        if ($result['sales'] > 0) {
            printf("  %s: ₱%.2f (%d txns)\n", $hour, $result['sales'], $result['txns']);
        }
    }
}

echo "\n=== Test Complete ===\n";
