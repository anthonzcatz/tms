<?php
/**
 * Cashier Performance API
 * Returns POS cashier sales performance data for the dashboard
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Get date range (default to last 7 days)
    $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
    if ($days > 90) $days = 90; // Max 3 months
    if ($days < 1) $days = 7;

    // Get branch filter and decode it
    $branchIdRaw = isset($_GET['branch_id']) ? $_GET['branch_id'] : null;
    $branchId = null;
    
    if ($branchIdRaw) {
        $decodedBranchId = IdEncoder::decode($branchIdRaw);
        if ($decodedBranchId !== false) {
            $branchId = $decodedBranchId;
        }
    }

    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;

    // Build branch restriction
    $branchWhere = '';
    $branchParams = [];

    // If specific branch selected, use it (respecting user permissions)
    if ($branchId) {
        // Check if user has access to this branch
        if ($userRoleCode === 'SUPER_ADMIN' || ($userBranchId && in_array($branchId, array_map('trim', explode(',', $userBranchId))))) {
            $branchWhere = "AND po.branch_id = :branch_id";
            $branchParams = ['branch_id' => $branchId];
        } else {
            // User doesn't have access, fall back to their branches
            $branchIds = array_map('trim', explode(',', $userBranchId));
            $namedParams = [];
            foreach ($branchIds as $i => $bid) {
                $namedParams['bid_' . $i] = $bid;
            }
            $placeholders = implode(',', array_keys($namedParams));
            $branchWhere = "AND po.branch_id IN ($placeholders)";
            $branchParams = $namedParams;
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        // No specific branch selected, use user's branches
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) {
            $namedParams['bid_' . $i] = $bid;
        }
        $placeholders = implode(',', array_keys($namedParams));
        $branchWhere = "AND po.branch_id IN ($placeholders)";
        $branchParams = $namedParams;
    }

    // Get top performing cashiers
    $cashierParams = array_merge(['days' => $days], $branchParams);
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
           $branchWhere
         GROUP BY cs.cashier_user_id, e.first_name, e.last_name
         ORDER BY total_sales DESC
         LIMIT 5",
        $cashierParams
    );

    // Get sales breakdown by top cashiers for the chart
    $chartData = [];
    $labels = [];

    // For "Today" (days=1), show hourly breakdown; otherwise show daily
    if ($days == 1) {
        // Hourly breakdown for today
        for ($i = 0; $i < 24; $i++) {
            $hour = sprintf('%02d:00', $i);
            $labels[] = $hour;
            $chartData[$hour] = [];
        }
    } else {
        // Daily breakdown for multiple days
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime("-$i days"));
            $chartData[$date] = [];
        }
    }

    // Fetch sales for each top cashier
    $cashierSeries = [];
    $colors = ['#2c7be5', '#00d27a', '#27bcfd', '#f5803e', '#e63757'];

    foreach ($topCashiers as $idx => $cashier) {
        $salesData = [];

        foreach (array_keys($chartData) as $key) {
            $params = array_merge([
                'cashier_id' => $cashier['cashier_user_id']
            ], $branchParams);

            if ($days == 1) {
                // Hourly query for today
                $hour = intval(explode(':', $key)[0]);
                $params['hour'] = $hour;
                $result = Database::fetch(
                    "SELECT COALESCE(SUM(po.grand_total), 0) as sales
                     FROM pos_orders po
                     INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
                     WHERE po.status = 'completed'
                       AND DATE(po.created_at) = CURDATE()
                       AND HOUR(po.created_at) = :hour
                       AND cs.cashier_user_id = :cashier_id
                       $branchWhere",
                    $params
                );
            } else {
                // Daily query for multiple days
                $params['date'] = $key;
                $result = Database::fetch(
                    "SELECT COALESCE(SUM(po.grand_total), 0) as sales
                     FROM pos_orders po
                     INNER JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
                     WHERE po.status = 'completed'
                       AND DATE(po.created_at) = :date
                       AND cs.cashier_user_id = :cashier_id
                       $branchWhere",
                    $params
                );
            }

            $salesData[] = floatval($result['sales'] ?? 0);
        }

        $cashierSeries[] = [
            'name' => $cashier['cashier_name'] ?: 'Cashier ' . ($idx + 1),
            'data' => $salesData,
            'total_sales' => floatval($cashier['total_sales']),
            'transaction_count' => intval($cashier['transaction_count']),
            'avg_transaction' => floatval($cashier['avg_transaction']),
            'color' => $colors[$idx % count($colors)]
        ];
    }

    // Get total active cashiers count
    $activeCashiers = Database::fetch(
        "SELECT COUNT(DISTINCT cs.cashier_user_id) as count
         FROM cashier_sessions cs
         INNER JOIN pos_orders po ON cs.session_id = po.cashier_session_id
         WHERE po.status = 'completed'
           AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
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
            'branches' => $branches
        ]
    ]);

} catch (Exception $e) {
    error_log('Cashier Performance API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
