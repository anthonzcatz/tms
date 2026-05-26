<?php
/**
 * TMS Metrics API
 * Returns key performance metrics for the dashboard Goals card
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

    // Get filter parameters
    $range = $_GET['range'] ?? 'month'; // today, week, month, year
    $branchId = $_GET['branch_id'] ?? null;

    // Build branch restriction
    $branchWhere = '';
    $branchParams = [];

    // Use provided branch_id if available
    if ($branchId) {
        // Check if user has access to this branch
        if ($userRoleCode === 'SUPER_ADMIN') {
            // SUPER_ADMIN can access any branch
            $branchWhere = "AND po.branch_id = :branch_id";
            $branchParams['branch_id'] = $branchId;
        } elseif ($userBranchId) {
            // Non-SUPER_ADMIN can only filter within their allowed branches
            $branchIds = array_map('trim', explode(',', $userBranchId));
            if (in_array($branchId, $branchIds)) {
                // User has access to this specific branch
                $branchWhere = "AND po.branch_id = :branch_id";
                $branchParams['branch_id'] = $branchId;
            } else {
                // User doesn't have access to this branch, use their allowed branches
                $namedParams = [];
                foreach ($branchIds as $i => $bid) {
                    $namedParams['bid_' . $i] = $bid;
                }
                $placeholders = implode(',', array_keys($namedParams));
                $branchWhere = "AND po.branch_id IN ($placeholders)";
                $branchParams = $namedParams;
            }
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        // No specific branch selected, use user's branch restrictions
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) {
            $namedParams['bid_' . $i] = $bid;
        }
        $placeholders = implode(',', array_keys($namedParams));
        $branchWhere = "AND po.branch_id IN ($placeholders)";
        $branchParams = $namedParams;
    }

    // Custom date range params
    $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
    $endDate   = isset($_GET['end_date'])   && $_GET['end_date']   !== '' ? $_GET['end_date']   : null;

    // Determine date range based on filter
    $dateWhere = '';
    $dateParams = [];
    $trendDays = 30; // Default trend days

    if ($startDate && $endDate) {
        $dateWhere = "AND DATE(po.created_at) BETWEEN :sd AND :ed";
        $dateParams['sd'] = $startDate;
        $dateParams['ed'] = $endDate;
        $startTs = strtotime($startDate);
        $endTs   = strtotime($endDate);
        if ($startTs > $endTs) { $tmp = $startTs; $startTs = $endTs; $endTs = $tmp; }
        $trendDays = max(1, min(365, (int)(($endTs - $startTs) / 86400) + 1));
    } else {
        switch ($range) {
            case 'today':
                $dateWhere = "AND DATE(po.created_at) = CURDATE()";
                $trendDays = 1;
                break;
            case 'week':
                $dateWhere = "AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                $trendDays = 7;
                break;
            case 'month':
                $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
                $trendDays = (int)date('t');
                break;
            case 'year':
                $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
                $trendDays = (int)date('z') + 1;
                break;
            default:
                // Default to current month
                $dateWhere = "AND MONTH(po.created_at) = MONTH(CURDATE()) AND YEAR(po.created_at) = YEAR(CURDATE())";
        }
    }

    // Get total metrics for selected range
    $metricsParams = array_merge($branchParams, $dateParams);
    
    $totalMetrics = Database::fetch(
        "SELECT 
            COUNT(DISTINCT po.order_id) as total_orders,
            COALESCE(SUM(po.grand_total), 0) as total_revenue,
            COALESCE(AVG(po.grand_total), 0) as avg_order_value
         FROM pos_orders po
         WHERE po.status = 'completed'
           $dateWhere
           $branchWhere",
        $metricsParams
    );

    // Get daily trend for the selected period
    $trendLimit = min($trendDays, 30); // Cap at 30 days for chart readability
    $trendParams = array_merge(['days' => $trendLimit], $branchParams);
    $dailyTrend = Database::fetchAll(
        "SELECT 
            DATE(po.created_at) as date,
            COUNT(DISTINCT po.order_id) as orders,
            COALESCE(SUM(po.grand_total), 0) as revenue,
            COALESCE(AVG(po.grand_total), 0) as avg_value
         FROM pos_orders po
         WHERE po.status = 'completed'
           AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
           $branchWhere
         GROUP BY DATE(po.created_at)
         ORDER BY date ASC",
        $trendParams
    );

    // Prepare trend arrays (fill missing days with 0)
    $ordersTrend = [];
    $revenueTrend = [];
    $avgTrend = [];
    
    $startDate = date('Y-m-d', strtotime('-' . ($trendLimit - 1) . ' days'));
    $currentDate = $startDate;
    
    $trendMap = [];
    foreach ($dailyTrend as $day) {
        $trendMap[$day['date']] = $day;
    }
    
    for ($i = 0; $i < $trendLimit; $i++) {
        $date = date('Y-m-d', strtotime($startDate . " +$i days"));
        if (isset($trendMap[$date])) {
            $ordersTrend[] = intval($trendMap[$date]['orders']);
            $revenueTrend[] = floatval($trendMap[$date]['revenue']);
            $avgTrend[] = floatval($trendMap[$date]['avg_value']);
        } else {
            $ordersTrend[] = 0;
            $revenueTrend[] = 0;
            $avgTrend[] = 0;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total_orders' => intval($totalMetrics['total_orders'] ?? 0),
            'total_revenue' => floatval($totalMetrics['total_revenue'] ?? 0),
            'avg_order_value' => floatval($totalMetrics['avg_order_value'] ?? 0),
            'orders_trend' => $ordersTrend,
            'revenue_trend' => $revenueTrend,
            'avg_trend' => $avgTrend
        ]
    ]);

} catch (Exception $e) {
    error_log('TMS Metrics API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
