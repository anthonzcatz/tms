<?php
/**
 * Branch Sales Analytics API
 * Returns sales data with refunds breakdown for dashboard charts
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';

header('Content-Type: application/json');

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in']);
        exit;
    }

    // Get parameters
    $range = isset($_GET['range']) ? $_GET['range'] : 'week';
    // branch_id absent = default to first branch; branch_id = '' = All Branches
    $branchIdRaw = array_key_exists('branch_id', $_GET) ? $_GET['branch_id'] : 'DEFAULT';
    $isAllBranches = ($branchIdRaw === '');
    $branchId = null;

    // Decode branch_id if a real value was provided
    if (!$isAllBranches && $branchIdRaw !== 'DEFAULT' && $branchIdRaw !== '') {
        $decodedBranchId = IdEncoder::decode($branchIdRaw);
        if ($decodedBranchId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            exit;
        }
        $branchId = $decodedBranchId;
    }

    // Determine user's accessible branches
    $accessibleBranches = [];
    if ($user['role_code'] === 'SUPER_ADMIN') {
        // Get all active branches
        $allBranchRows = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE status = 'active'");
        $accessibleBranches = array_column($allBranchRows, 'branch_id');
        $branchNames = array_column($allBranchRows, 'branch_name', 'branch_id');
    } else {
        $userBranchIds = array_map('trim', explode(',', $user['branch_id'] ?? ''));
        $accessibleBranches = array_filter($userBranchIds);
        // Get branch names
        $branchNames = [];
        if (!empty($accessibleBranches)) {
            $placeholders = implode(',', array_fill(0, count($accessibleBranches), '?'));
            $branches = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE branch_id IN ($placeholders)", $accessibleBranches);
            $branchNames = array_column($branches, 'branch_name', 'branch_id');
        }
    }

    // If specific branch requested, verify access
    if (!$isAllBranches && $branchId && !in_array($branchId, $accessibleBranches)) {
        echo json_encode(['success' => false, 'error' => 'Access denied for this branch']);
        exit;
    }

    // Determine target branch(es)
    if ($isAllBranches) {
        $targetBranchId = null;
        $targetBranchName = 'All Branches';
    } else {
        $targetBranchId = $branchId ?: ($accessibleBranches[0] ?? null);
        $targetBranchName = $branchNames[$targetBranchId] ?? 'Unknown Branch';
    }

    if (!$isAllBranches && !$targetBranchId) {
        echo json_encode(['success' => false, 'error' => 'No accessible branch found']);
        exit;
    }

    // Calculate date range
    $endDate = date('Y-m-d');
    switch ($range) {
        case 'today':
            $startDate = date('Y-m-d');
            $days = 1;
            break;
        case 'month':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $days = 30;
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-365 days'));
            $days = 365;
            break;
        case 'week':
        default:
            $startDate = date('Y-m-d', strtotime('-6 days'));
            $days = 7;
            break;
    }

    // Get daily sales data from cashier_sessions
    // For 'today', build hourly buckets instead of daily
    $dailyData = [];
    if ($range === 'today') {
        for ($h = 0; $h < 24; $h++) {
            $key = date('Y-m-d') . sprintf(' %02d:00', $h);
            $dailyData[$key] = [
                'date' => date('Y-m-d'),
                'display_date' => sprintf('%02d:00', $h),
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
        }
    } else {
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dailyData[$date] = [
                'date' => $date,
                'display_date' => date('M d', strtotime($date)),
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
        }
    }

    // Build positional params for sales and previous-period queries
    if ($isAllBranches) {
        $branchInPlaceholders = implode(',', array_fill(0, count($accessibleBranches), '?'));
        $branchWhereCs   = "cs.branch_id IN ($branchInPlaceholders)";
        $branchWhereFlat = "branch_id IN ($branchInPlaceholders)";

        $salesParams    = array_merge([$startDate, $endDate], $accessibleBranches);
        $prevBranchArgs = $accessibleBranches;
    } else {
        $branchWhereCs   = "cs.branch_id = ?";
        $branchWhereFlat = "branch_id = ?";

        $salesParams    = [$startDate, $endDate, $targetBranchId];
        $prevBranchArgs = [$targetBranchId];
    }

    // Query sales data from completed sessions
    if ($range === 'today') {
        // Hourly grouping for today
        $salesQuery = "
            SELECT
                DATE_FORMAT(cs.ended_at, '%Y-%m-%d %H:00') as sale_date,
                COALESCE(SUM(cs.total_sales), 0) as total_sales,
                COALESCE(SUM(cs.total_refunds_wallet), 0) as total_refunds,
                COUNT(*) as session_count
            FROM cashier_sessions cs
            WHERE cs.status = 'CLOSED'
            AND DATE(cs.ended_at) = ?
            AND $branchWhereCs
            GROUP BY DATE_FORMAT(cs.ended_at, '%Y-%m-%d %H:00')
            ORDER BY sale_date ASC
        ";
        $salesParams = $isAllBranches
            ? array_merge([date('Y-m-d')], $accessibleBranches)
            : [date('Y-m-d'), $targetBranchId];
    } else {
        $salesQuery = "
            SELECT
                DATE(cs.ended_at) as sale_date,
                COALESCE(SUM(cs.total_sales), 0) as total_sales,
                COALESCE(SUM(cs.total_refunds_wallet), 0) as total_refunds,
                COUNT(*) as session_count
            FROM cashier_sessions cs
            WHERE cs.status = 'CLOSED'
            AND DATE(cs.ended_at) BETWEEN ? AND ?
            AND $branchWhereCs
            GROUP BY DATE(cs.ended_at)
            ORDER BY sale_date DESC
        ";
    }

    $salesResults = Database::fetchAll($salesQuery, $salesParams);

    foreach ($salesResults as $row) {
        $key = $row['sale_date'];
        if (isset($dailyData[$key])) {
            $dailyData[$key]['sales'] = floatval($row['total_sales']);
            $dailyData[$key]['refunds'] = floatval($row['total_refunds']);
            $dailyData[$key]['net'] = floatval($row['total_sales']) - floatval($row['total_refunds']);
            $dailyData[$key]['sessions'] = intval($row['session_count']);
        }
    }

    // Get transaction counts from pos_orders (has branch_id directly — most reliable)
    if ($range === 'today') {
        $txnGroupBy  = "DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')";
        $txnDateWhere = "DATE(po.created_at) = ?";
        $txnDateArgs  = [date('Y-m-d')];
    } else {
        $txnGroupBy  = "DATE(po.created_at)";
        $txnDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $txnDateArgs  = [$startDate, $endDate];
    }

    // Build branch filter for pos_orders
    if ($isAllBranches) {
        $branchInPo = implode(',', array_fill(0, count($accessibleBranches), '?'));
        $txnBranchWhere = "po.branch_id IN ($branchInPo)";
        $txnBranchArgs  = $accessibleBranches;
    } else {
        $txnBranchWhere = "po.branch_id = ?";
        $txnBranchArgs  = [$targetBranchId];
    }

    $txnQuery = "
        SELECT
            $txnGroupBy as txn_date,
            COUNT(*) as txn_count
        FROM pos_orders po
        WHERE po.status = 'completed'
        AND $txnDateWhere
        AND $txnBranchWhere
        GROUP BY $txnGroupBy
    ";

    $txnParams = array_merge($txnDateArgs, $txnBranchArgs);

    $txnResults = Database::fetchAll($txnQuery, $txnParams);
    foreach ($txnResults as $row) {
        $key = $row['txn_date'];
        if (isset($dailyData[$key])) {
            $dailyData[$key]['transactions'] = intval($row['txn_count']);
        }
    }

    // Calculate totals
    $totalSales = array_sum(array_column($dailyData, 'sales'));
    $totalRefunds = array_sum(array_column($dailyData, 'refunds'));
    $totalNet = $totalSales - $totalRefunds;
    $totalTransactions = array_sum(array_column($dailyData, 'transactions'));

    // Prepare chart data (reverse to show oldest first)
    $chartData = array_reverse(array_values($dailyData));

    // Calculate trend percentages
    $midPoint = floor(count($dailyData) / 2);
    $firstHalfSales = 0;
    $secondHalfSales = 0;
    $dailyValues = array_values($dailyData);

    for ($i = 0; $i < count($dailyValues); $i++) {
        if ($i < $midPoint) {
            $firstHalfSales += $dailyValues[$i]['net'];
        } else {
            $secondHalfSales += $dailyValues[$i]['net'];
        }
    }

    $salesTrend = 0;
    if ($firstHalfSales > 0) {
        $salesTrend = (($secondHalfSales - $firstHalfSales) / $firstHalfSales) * 100;
    }

    // Get previous period for comparison
    $prevStartDate = date('Y-m-d', strtotime($startDate . " -$days days"));
    $prevEndDate   = date('Y-m-d', strtotime($endDate   . " -$days days"));

    $prevParams = array_merge([$prevStartDate, $prevEndDate], $prevBranchArgs);
    $prevResult = Database::fetch("
        SELECT COALESCE(SUM(total_sales), 0) as total,
               COALESCE(SUM(total_refunds_wallet), 0) as refunds
        FROM cashier_sessions
        WHERE status = 'CLOSED'
        AND DATE(ended_at) BETWEEN ? AND ?
        AND $branchWhereFlat
    ", $prevParams);

    $prevTotal = floatval($prevResult['total']) - floatval($prevResult['refunds']);
    $periodChange = 0;
    if ($prevTotal > 0) {
        $periodChange = (($totalNet - $prevTotal) / $prevTotal) * 100;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'branch_name' => $targetBranchName,
            'branch_id' => $targetBranchId ? IdEncoder::encode($targetBranchId) : '',
            'period' => $range,
            'summary' => [
                'total_sales' => $totalSales,
                'total_refunds' => $totalRefunds,
                'total_net' => $totalNet,
                'total_transactions' => $totalTransactions,
                'sales_trend' => round($salesTrend, 1),
                'period_change' => round($periodChange, 1)
            ],
            'chart_data' => $chartData,
            'accessible_branches' => array_map(function($id) use ($branchNames) {
                return [
                    'id' => IdEncoder::encode($id),
                    'name' => $branchNames[$id] ?? 'Unknown'
                ];
            }, $accessibleBranches)
        ]
    ]);

} catch (Exception $e) {
    error_log("Branch Analytics Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
