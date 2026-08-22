<?php
/**
 * Cashier Performance API
 * Returns POS cashier sales performance data for the dashboard
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

    $filterQuery = $_GET;
    if (empty($filterQuery['range']) && isset($filterQuery['days'])) {
        $legacyDays = (int)$filterQuery['days'];
        $filterQuery['range'] = $legacyDays <= 1
            ? 'today'
            : ($legacyDays <= 7 ? 'week' : ($legacyDays <= 30 ? 'last30days' : 'year'));
    }
    $filter = AnalyticsFilter::parse($filterQuery, $user);
    $branchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'cashier_branch');
    $dateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'cashier_date');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $branchParams = $branchScope['params'];
    $dateWhere = 'AND ' . $dateScope['sql'];
    $dateParams = $dateScope['params'];
    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;

    $cashierParams = array_merge($branchParams, $dateParams);
    $topCashiers = Database::fetchAll(
        "SELECT
            cs.cashier_user_id,
            CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) as cashier_name,
            COUNT(DISTINCT po.order_id) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(AVG(po.grand_total), 0) as avg_transaction
         FROM pos_orders po
         INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
         INNER JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         WHERE po.status = 'completed'
           $dateWhere
           $branchWhere
         GROUP BY cs.cashier_user_id, e.first_name, e.last_name
         ORDER BY total_sales DESC
         LIMIT 5",
        $cashierParams
    );

    $isHourly = $filter['granularity'] === 'hourly';
    $isAnnual = $filter['granularity'] === 'annual';
    $isMonthly = $filter['granularity'] === 'monthly' || $isAnnual;
    $periodKeys = [];
    $labels = [];
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');

    if ($isHourly) {
        for ($hour = 0; $hour < 24; $hour++) {
            $periodKeys[] = sprintf('%02d:00', $hour);
            $labels[] = sprintf('%02d:00', $hour);
        }
    } else {
        $cursor = new DateTimeImmutable($filter['start_date'], $timezone);
        $lastDate = new DateTimeImmutable($filter['end_date'], $timezone);
        if ($isAnnual) {
            $cursor = $cursor->setDate((int)$cursor->format('Y'), 1, 1);
            $lastDate = $lastDate->setDate((int)$lastDate->format('Y'), 1, 1);
        } elseif ($isMonthly) {
            $cursor = $cursor->modify('first day of this month');
            $lastDate = $lastDate->modify('first day of this month');
        }

        while ($cursor <= $lastDate) {
            if ($isAnnual) {
                $periodKeys[] = $cursor->format('Y');
                $labels[] = $cursor->format('Y');
                $cursor = $cursor->modify('+1 year');
            } elseif ($isMonthly) {
                $periodKeys[] = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor = $cursor->modify('+1 month');
            } else {
                $periodKeys[] = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M d');
                $cursor = $cursor->modify('+1 day');
            }
        }
    }

    $cashierIds = array_values(array_filter(array_map(static fn ($cashier): int => (int)$cashier['cashier_user_id'], $topCashiers)));
    $chartMap = [];
    if ($cashierIds) {
        $cashierPlaceholders = [];
        $chartParams = array_merge($branchParams, $dateParams);
        foreach ($cashierIds as $index => $cashierId) {
            $key = 'cashier_' . $index;
            $cashierPlaceholders[] = ':' . $key;
            $chartParams[$key] = $cashierId;
        }

        $chartGroup = $isHourly
            ? "DATE_FORMAT(po.created_at, '%H:00')"
            : ($isAnnual
                ? "DATE_FORMAT(po.created_at, '%Y')"
                : ($isMonthly ? "DATE_FORMAT(po.created_at, '%Y-%m')" : "DATE(po.created_at)"));

        $chartRows = Database::fetchAll(
            "SELECT cs.cashier_user_id, $chartGroup as period_key,
                    COALESCE(SUM(po.grand_total), 0) as sales
             FROM pos_orders po
             INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
             WHERE po.status = 'completed'
               $dateWhere
               $branchWhere
               AND cs.cashier_user_id IN (" . implode(', ', $cashierPlaceholders) . ")
             GROUP BY cs.cashier_user_id, $chartGroup
             ORDER BY period_key ASC",
            $chartParams
        );

        foreach ($chartRows as $row) {
            $chartMap[(int)$row['cashier_user_id']][(string)$row['period_key']] = (float)$row['sales'];
        }
    }

    $cashierSeries = [];
    $colors = ['#2c7be5', '#00d27a', '#27bcfd', '#f5803e', '#e63757'];
    foreach ($topCashiers as $idx => $cashier) {
        $cashierId = (int)$cashier['cashier_user_id'];
        $cashierData = $chartMap[$cashierId] ?? [];
        $cashierSeries[] = [
            'name' => $cashier['cashier_name'] ?: 'Cashier ' . ($idx + 1),
            'data' => array_map(static fn (string $key): float => (float)($cashierData[$key] ?? 0), $periodKeys),
            'total_sales' => (float)$cashier['total_sales'],
            'transaction_count' => (int)$cashier['transaction_count'],
            'avg_transaction' => (float)$cashier['avg_transaction'],
            'color' => $colors[$idx % count($colors)]
        ];
    }

    $activeCashiers = Database::fetch(
        "SELECT COUNT(DISTINCT cs.cashier_user_id) as count
         FROM cashier_sessions cs
         INNER JOIN pos_orders po ON cs.session_id = po.cashier_session_id
         WHERE po.status = 'completed'
           $dateWhere
           $branchWhere",
        $cashierParams
    );

    // Get branches for filter dropdown
    $branches = [];
    if ($userRoleCode === 'SUPER_ADMIN') {
        $rawBranches = Database::fetchAll(
            "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
        );
        $branches = array_map(function($b) {
            return [
                'id' => IdEncoder::encode($b['branch_id']),
                'name' => $b['branch_name']
            ];
        }, $rawBranches);
    } elseif ($userBranchId) {
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) {
            $namedParams['bid_' . $i] = $bid;
        }
        $placeholders = implode(',', array_keys($namedParams));
        $rawBranches = Database::fetchAll(
            "SELECT branch_id, branch_name FROM business_branches WHERE branch_id IN ($placeholders) AND status = 'active' ORDER BY branch_name",
            $namedParams
        );
        $branches = array_map(function($b) {
            return [
                'id' => IdEncoder::encode($b['branch_id']),
                'name' => $b['branch_name']
            ];
        }, $rawBranches);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'dates' => $labels,
            'cashiers' => $cashierSeries,
            'active_cashiers' => intval($activeCashiers['count'] ?? 0),
            'total_sales' => array_sum(array_column($cashierSeries, 'total_sales')),
            'branches' => $branches,
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Cashier Performance API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load cashier performance']);
}
