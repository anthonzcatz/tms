<?php
/**
 * Branch Sales Analytics API
 * Returns sales data with refunds breakdown for dashboard charts
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/AnalyticsFilter.php';

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

    $filter = AnalyticsFilter::parse($_GET, $user);
    $range = $filter['range'];
    $startDate = $filter['start_date'];
    $endDate = $filter['end_date'];
    $days = $filter['days'];
    $isAllBranches = $filter['is_all_branches'];
    $branchId = $filter['branch_id'];
    $accessibleBranches = $filter['accessible_branch_ids'];
    $isHourly = $filter['granularity'] === 'hourly';
    $isAnnual = $filter['granularity'] === 'annual';
    $isMonthly = $filter['granularity'] === 'monthly' || $isAnnual;

    $branchListFilter = $filter;
    $branchListFilter['branch_id'] = null;
    $branchListScope = AnalyticsFilter::branchCondition($branchListFilter, 'branch_id', 'branch_list');
    $branchRows = Database::fetchAll(
        "SELECT branch_id, branch_name
         FROM business_branches
         WHERE status = 'active' AND {$branchListScope['sql']}",
        $branchListScope['params']
    );
    $accessibleBranches = array_values(array_map('intval', array_column($branchRows, 'branch_id')));
    $branchNames = array_column($branchRows, 'branch_name', 'branch_id');

    if ($isAllBranches) {
        $targetBranchId = null;
        $targetBranchName = 'All Branches';
    } else {
        $targetBranchId = $branchId;
        $targetBranchName = $branchNames[$targetBranchId] ?? 'Unknown Branch';
    }

    if (!$isAllBranches && !$targetBranchId) {
        throw new InvalidArgumentException('No accessible branch found');
    }

    $dailyData = [];
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    if ($isHourly) {
        for ($hour = 0; $hour < 24; $hour++) {
            $key = $startDate . sprintf(' %02d:00', $hour);
            $dailyData[$key] = [
                'date' => $startDate,
                'display_date' => sprintf('%02d:00', $hour),
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
        }
    } else {
        $cursor = new DateTimeImmutable($startDate, $timezone);
        $lastDate = new DateTimeImmutable($endDate, $timezone);
        if ($isAnnual) {
            $cursor = $cursor->setDate((int)$cursor->format('Y'), 1, 1);
            $lastDate = $lastDate->setDate((int)$lastDate->format('Y'), 1, 1);
        } elseif ($isMonthly) {
            $cursor = $cursor->modify('first day of this month');
            $lastDate = $lastDate->modify('first day of this month');
        }

        while ($cursor <= $lastDate) {
            if ($isAnnual) {
                $key = $cursor->format('Y');
                $displayDate = $cursor->format('Y');
                $cursor = $cursor->modify('+1 year');
            } elseif ($isMonthly) {
                $key = $cursor->format('Y-m');
                $displayDate = $cursor->format('M Y');
                $cursor = $cursor->modify('+1 month');
            } else {
                $key = $cursor->format('Y-m-d');
                $displayDate = $cursor->format('M d');
                $cursor = $cursor->modify('+1 day');
            }
            $dailyData[$key] = [
                'date' => $key,
                'display_date' => $displayDate,
                'sales' => 0,
                'refunds' => 0,
                'net' => 0,
                'transactions' => 0
            ];
        }
    }

    // Build positional params for sales and previous-period queries
    if ($isAllBranches) {
        if ($accessibleBranches) {
            $branchInPlaceholders = implode(',', array_fill(0, count($accessibleBranches), '?'));
            $branchWhereCs = "po.branch_id IN ($branchInPlaceholders)";
            $branchWhereFlat = "branch_id IN ($branchInPlaceholders)";
        } else {
            $branchWhereCs = '1 = 0';
            $branchWhereFlat = '1 = 0';
        }

        $salesParams = array_merge([$startDate, $endDate], $accessibleBranches);
        $prevBranchArgs = $accessibleBranches;
    } else {
        $branchWhereCs = "po.branch_id = ?";
        $branchWhereFlat = "branch_id = ?";

        $salesParams = [$startDate, $endDate, $targetBranchId];
        $prevBranchArgs = [$targetBranchId];
    }

    // Query sales data from pos_orders (new optimized system)
    if ($isHourly) {
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
            ? array_merge([$startDate], $accessibleBranches)
            : [$startDate, $targetBranchId];
    } elseif ($isMonthly || $isAnnual) {
        $salesGroup = $isAnnual ? '%Y' : '%Y-%m';
        $salesQuery = "
            SELECT
                DATE_FORMAT(po.created_at, '$salesGroup') as sale_date,
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
    if ($isHourly) {
        $txnGroupBy = "DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')";
        $txnDateWhere = "DATE(po.created_at) = ?";
        $txnDateArgs = [$startDate];
    } elseif ($isMonthly || $isAnnual) {
        $txnGroupBy = $isAnnual
            ? "DATE_FORMAT(po.created_at, '%Y')"
            : "DATE_FORMAT(po.created_at, '%Y-%m')";
        $txnDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $txnDateArgs = [$startDate, $endDate];
    } else {
        $txnGroupBy = "DATE(po.created_at)";
        $txnDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $txnDateArgs = [$startDate, $endDate];
    }

    // Build branch filter for pos_orders
    if ($isAllBranches) {
        if ($accessibleBranches) {
            $branchInPo = implode(',', array_fill(0, count($accessibleBranches), '?'));
            $txnBranchWhere = "po.branch_id IN ($branchInPo)";
        } else {
            $txnBranchWhere = '1 = 0';
        }
        $txnBranchArgs = $accessibleBranches;
    } else {
        $txnBranchWhere = "po.branch_id = ?";
        $txnBranchArgs = [$targetBranchId];
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
    if ($isHourly) {
        $profitGroupBy = "DATE_FORMAT(po.created_at, '%Y-%m-%d %H:00')";
        $profitDateWhere = "DATE(po.created_at) = ?";
        $profitDateArgs = [$startDate];
    } elseif ($isMonthly || $isAnnual) {
        $profitGroupBy = $isAnnual
            ? "DATE_FORMAT(po.created_at, '%Y')"
            : "DATE_FORMAT(po.created_at, '%Y-%m')";
        $profitDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $profitDateArgs = [$startDate, $endDate];
    } else {
        $profitGroupBy = "DATE(po.created_at)";
        $profitDateWhere = "DATE(po.created_at) BETWEEN ? AND ?";
        $profitDateArgs = [$startDate, $endDate];
    }

    // Build branch filter for profit query
    if ($isAllBranches) {
        $profitBranchWhere = $accessibleBranches
            ? "po.branch_id IN (" . implode(',', array_fill(0, count($accessibleBranches), '?')) . ")"
            : '1 = 0';
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
            }, $accessibleBranches),
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Branch Analytics Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Unable to load branch analytics']);
}
