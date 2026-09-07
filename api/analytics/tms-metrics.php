<?php
/**
 * TMS Metrics API
 * Returns key performance metrics for the dashboard Goals card
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
    $branchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'metrics_branch');
    $dateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'metrics_date');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $branchParams = $branchScope['params'];
    $dateWhere = 'AND ' . $dateScope['sql'];
    $dateParams = $dateScope['params'];
    $transactionFrom = PosTransactionReporting::orderFrom();
    $transactionNet = PosTransactionReporting::netExpression();
    $transactionStatus = PosTransactionReporting::saleStatusCondition();

    // Get total metrics for selected range
    $metricsParams = array_merge($branchParams, $dateParams);
    
    $totalMetrics = Database::fetch(
        "SELECT 
            COUNT(DISTINCT po.order_id) as total_orders,
            COALESCE(SUM($transactionNet), 0) as total_revenue,
            COALESCE(AVG($transactionNet), 0) as avg_order_value
         $transactionFrom
         WHERE $transactionStatus
           $dateWhere
           $branchWhere",
        $metricsParams
    );

    $trendIsAnnual = $filter['granularity'] === 'annual';
    $trendIsMonthly = $filter['granularity'] === 'monthly' || $trendIsAnnual;
    $trendKeyExpression = $trendIsAnnual
        ? "DATE_FORMAT(po.created_at, '%Y')"
        : ($trendIsMonthly ? "DATE_FORMAT(po.created_at, '%Y-%m')" : "DATE(po.created_at)");

    $trendParams = array_merge($branchParams, $dateParams);
    $periodTrend = Database::fetchAll(
        "SELECT
            $trendKeyExpression as period_key,
            COUNT(DISTINCT po.order_id) as orders,
            COALESCE(SUM($transactionNet), 0) as revenue
         $transactionFrom
         WHERE $transactionStatus
           $dateWhere
           $branchWhere
         GROUP BY $trendKeyExpression
         ORDER BY period_key ASC",
        $trendParams
    );

    $trendMap = [];
    foreach ($periodTrend as $period) {
        $trendMap[(string)$period['period_key']] = [
            'orders' => (int)$period['orders'],
            'revenue' => (float)$period['revenue'],
        ];
    }

    $ordersTrend = [];
    $revenueTrend = [];
    $avgTrend = [];
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    $cursor = new DateTimeImmutable($filter['start_date'], $timezone);
    $lastDate = new DateTimeImmutable($filter['end_date'], $timezone);

    if ($trendIsAnnual) {
        $cursor = $cursor->setDate((int)$cursor->format('Y'), 1, 1);
        $lastDate = $lastDate->setDate((int)$lastDate->format('Y'), 1, 1);
    } elseif ($trendIsMonthly) {
        $cursor = $cursor->modify('first day of this month');
        $lastDate = $lastDate->modify('first day of this month');
    }

    while ($cursor <= $lastDate) {
        $key = $trendIsAnnual
            ? $cursor->format('Y')
            : ($trendIsMonthly ? $cursor->format('Y-m') : $cursor->format('Y-m-d'));
        $period = $trendMap[$key] ?? ['orders' => 0, 'revenue' => 0];
        $orders = (int)$period['orders'];
        $revenue = (float)$period['revenue'];
        $ordersTrend[] = $orders;
        $revenueTrend[] = $revenue;
        $avgTrend[] = $orders > 0 ? $revenue / $orders : 0;
        $cursor = $trendIsAnnual
            ? $cursor->modify('+1 year')
            : ($trendIsMonthly ? $cursor->modify('+1 month') : $cursor->modify('+1 day'));
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total_orders' => intval($totalMetrics['total_orders'] ?? 0),
            'total_revenue' => floatval($totalMetrics['total_revenue'] ?? 0),
            'avg_order_value' => floatval($totalMetrics['avg_order_value'] ?? 0),
            'orders_trend' => $ordersTrend,
            'revenue_trend' => $revenueTrend,
            'avg_trend' => $avgTrend,
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('TMS Metrics API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load analytics metrics']);
}
