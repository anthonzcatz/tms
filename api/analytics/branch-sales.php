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
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
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
    if (!$isAllBranches && $branchId && !in_array(intval($branchId), array_map('intval', $accessibleBranches))) {
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
    // Use custom dates if provided, otherwise use preset ranges
    if ($startDate && $endDate) {
        // Custom date range - calculate days between dates
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs > $endTs) { $tmp = $startTs; $startTs = $endTs; $endTs = $tmp; }
        $days = max(1, min(365, (int)(($endTs - $startTs) / 86400) + 1));
    } else {
        $endDate = date('Y-m-d');
        switch ($range) {
            case 'today':
                $startDate = date('Y-m-d');
                $days = 1;
                break;
            case 'month':
                $startDate = date('Y-m-01'); // First day of current month
                $days = (int)date('t'); // Days in current month
                break;
            case 'last30days':
                $startDate = date('Y-m-d', strtotime('-29 days')); // Last 30 days including today
                $days = 30;
                break;
            case 'year':
                $startDate = date('Y-01-01'); // January 1st of current year
                $days = (int)date('z') + 1; // Day of year (1-365/366)
                break;
            case 'week':
            default:
                $startDate = date('Y-m-d', strtotime('-6 days'));
                $days = 7;
                break;
        }
    }

    // Get daily sales data from cashier_sessions
    // For 'today', build hourly buckets instead of daily
    // For 'year', build monthly buckets instead of daily
    // For custom date ranges, build daily buckets
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
    } elseif ($range === 'year' && !($startDate && $endDate)) {
        // Generate monthly data from January to current month (only for preset year range)
        $currentMonth = 1;
        $currentYear = date('Y');
        $endMonth = (int)date('n');
        while ($currentMonth <= $endMonth) {
            $key = sprintf('%d-%02d', $currentYear, $currentMonth);
            $dailyData[$key] = [
                'date' => $key,
                'display_date' => date('M Y', strtotime(sprintf('%d-%02d-01', $currentYear, $currentMonth))),
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
            $currentMonth++;
        }
    } else {
        // Generate dates from startDate to endDate in chronological order (for custom ranges and preset week/month)
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dailyData[$currentDate] = [
                'date' => $currentDate,
                'display_date' => date('M d', strtotime($currentDate)),
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
    }

    // Build positional params for sales and previous-period queries
    if ($isAllBranches) {
        $branchInPlaceholders = implode(',', array_fill(0, count($accessibleBranches), '?'));
        $branchWhereCs   = "po.branch_id IN ($branchInPlaceholders)";
        $branchWhereFlat = "branch_id IN ($branchInPlaceholders)";

        $salesParams    = array_merge([$startDate, $endDate], $accessibleBranches);
        $prevBranchArgs = $accessibleBranches;
    } else {
        $branchWhereCs   = "po.branch_id = ?";
        $branchWhereFlat = "branch_id = ?";

        $salesParams    = [$startDate, $endDate, $targetBranchId];
        $prevBranchArgs = [$targetBranchId];
    }

    // Query sales data from pos_orders (new optimized system)
    if ($range === 'today') {
        // Hourly grouping for today
        $salesQuery = "
            SELECT
                DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00') as sale_date,
                COALESCE(SUM(po.grand_total), 0) as total_sales,
                COALESCE(SUM(po.total_refunded_amount), 0) as total_refunds,
                COUNT(*) as transaction_count
            FROM pos_orders po
            WHERE po.status = 'completed'
            AND DATE(po.created_at) = ?
            AND $branchWhereCs
            GROUP BY DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')
            ORDER BY sale_date ASC
        ";
        $salesParams = $isAllBranches
            ? array_merge([date('Y-m-d')], $accessibleBranches)
            : [date('Y-m-d'), $targetBranchId];
    } elseif ($range === 'year' && !($startDate && $endDate)) {
        // Monthly grouping for preset year range only
        $salesQuery = "
            SELECT
                DATE_FORMAT(po.created_at, '%Y-%m') as sale_date,
                COALESCE(SUM(po.grand_total), 0) as total_sales,
                COALESCE(SUM(po.total_refunded_amount), 0) as total_refunds,
                COUNT(*) as transaction_count
            FROM pos_orders po
            WHERE po.status = 'completed'
            AND DATE(po.created_at) BETWEEN ? AND ?
            AND $branchWhereCs
            GROUP BY DATE_FORMAT(po.created_at, '%Y-%m')
            ORDER BY sale_date ASC
        ";
    } else {
        // Daily grouping for custom ranges and preset week/month
        $salesQuery = "
            SELECT
                DATE(po.created_at) as sale_date,
                COALESCE(SUM(po.grand_total), 0) as total_sales,
                COALESCE(SUM(po.total_refunded_amount), 0) as total_refunds,
                COUNT(*) as transaction_count
            FROM pos_orders po
            WHERE po.status = 'completed'
            AND DATE(po.created_at) BETWEEN ? AND ?
            AND $branchWhereCs
            GROUP BY DATE(po.created_at)
            ORDER BY sale_date ASC
        ";
    }

    $salesResults = Database::fetchAll($salesQuery, $salesParams);

    foreach ($salesResults as $row) {
        $key = $row['sale_date'];
        if (isset($dailyData[$key])) {
            $dailyData[$key]['sales'] = floatval($row['total_sales']);
            $dailyData[$key]['refunds'] = floatval($row['total_refunds']);
            $dailyData[$key]['net'] = floatval($row['total_sales']) - floatval($row['total_refunds']);
            $dailyData[$key]['transactions'] = intval($row['transaction_count']);
        }
    }

    // Get transaction counts from pos_orders (has branch_id directly — most reliable)
    if ($range === 'today') {
        $txnGroupBy  = "DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')";
        $txnDateWhere = "DATE(po.created_at) = ?";
        $txnDateArgs  = [date('Y-m-d')];
    } elseif ($range === 'year' && !($startDate && $endDate)) {
        $txnGroupBy  = "DATE_FORMAT(po.created_at, '%Y-%m')";
        $txnDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $txnDateArgs  = [$startDate, $endDate];
    } else {
        // Daily grouping for custom ranges and preset week/month
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

    // Get profit data from pos_orders with item costs
    // Profit = Revenue (grand_total) - Cost (base_amount from tickets + unit_price from services)
    if ($range === 'today') {
        $profitGroupBy = "DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')";
        $profitDateWhere = "DATE(po.created_at) = ?";
        $profitDateArgs = [date('Y-m-d')];
    } elseif ($range === 'year' && !($startDate && $endDate)) {
        $profitGroupBy = "DATE_FORMAT(po.created_at, '%Y-%m')";
        $profitDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $profitDateArgs = [$startDate, $endDate];
    } else {
        // Daily grouping for custom ranges and preset week/month
        $profitGroupBy = "DATE(po.created_at)";
        $profitDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $profitDateArgs = [$startDate, $endDate];
    }

    // Build branch filter for profit query
    if ($isAllBranches) {
        $profitBranchWhere = "po.branch_id IN ($branchInPo)";
        $profitBranchArgs = $accessibleBranches;
    } else {
        $profitBranchWhere = "po.branch_id = ?";
        $profitBranchArgs = [$targetBranchId];
    }

    // Read profit data directly from pos_orders denormalized columns
    $profitQuery = "
        SELECT
            $profitGroupBy as profit_date,
            COALESCE(SUM(po.grand_total), 0)          as revenue,
            COALESCE(SUM(po.total_cost), 0)           as cost,
            COALESCE(SUM(po.total_service_fees), 0)   as service_fees,
            COALESCE(SUM(po.total_add_ons), 0)        as add_ons,
            COALESCE(SUM(po.total_profit), 0)         as profit
        FROM pos_orders po
        WHERE po.status = 'completed'
        AND $profitDateWhere
        AND $profitBranchWhere
        GROUP BY $profitGroupBy
    ";

    $profitParams = array_merge($profitDateArgs, $profitBranchArgs);
    $profitResults = Database::fetchAll($profitQuery, $profitParams);

    foreach ($profitResults as $row) {
        $key = $row['profit_date'];
        if (isset($dailyData[$key])) {
            $revenue     = floatval($row['revenue']);
            $cost        = floatval($row['cost']);
            $serviceFees = floatval($row['service_fees']);
            $addOns      = floatval($row['add_ons']);
            $profit      = floatval($row['profit']);

            $dailyData[$key]['revenue']        = $revenue;
            $dailyData[$key]['cost']           = $cost;
            $dailyData[$key]['service_fees']   = $serviceFees;
            $dailyData[$key]['add_ons']        = $addOns;
            $dailyData[$key]['profit']         = $profit;
            $dailyData[$key]['profit_margin']  = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;
        }
    }

    // Calculate totals - use revenue from pos_orders for consistency
    $totalSales = array_sum(array_column($dailyData, 'sales'));
    $totalRefunds = array_sum(array_column($dailyData, 'refunds'));
    $totalNet = $totalSales - $totalRefunds;
    $totalTransactions = array_sum(array_column($dailyData, 'transactions'));
    $totalRevenue = array_sum(array_column($dailyData, 'revenue'));
    $totalCost = array_sum(array_column($dailyData, 'cost'));
    $totalServiceFees = array_sum(array_column($dailyData, 'service_fees'));
    $totalAddOns = array_sum(array_column($dailyData, 'add_ons'));
    $totalProfit = array_sum(array_column($dailyData, 'profit'));
    $avgProfitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;

    // Prepare chart data
    $chartData = array_values($dailyData);

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
        SELECT COALESCE(SUM(grand_total), 0) as total,
               COALESCE(SUM(total_refunded_amount), 0) as refunds
        FROM pos_orders
        WHERE status = 'completed'
        AND DATE(created_at) BETWEEN ? AND ?
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
                'total_sales' => $totalRevenue, // Use revenue for consistency
                'total_refunds' => $totalRefunds,
                'total_net' => $totalNet,
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_service_fees' => $totalServiceFees,
                'total_add_ons' => $totalAddOns,
                'total_profit' => $totalProfit,
                'profit_margin' => $avgProfitMargin,
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
