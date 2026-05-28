<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Get time range (default to last 1 hour for real-time data)
    $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 1;
    $today = isset($_GET['today']) && $_GET['today'] === 'true';
    $branchId = isset($_GET['branch_id']) ? $_GET['branch_id'] : null;

    // Decode branch_id if provided
    if ($branchId) {
        $decodedBranchId = IdEncoder::decode($branchId);
        if ($decodedBranchId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            exit;
        }
        $branchId = $decodedBranchId;
    }

    // Build query for live sales from POS orders
    if ($today) {
        $whereClause = "WHERE po.status = 'completed' AND DATE(po.created_at) = CURDATE()";
        $params = [];
    } else {
        $whereClause = "WHERE po.status = 'completed' AND po.created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        $params = ['hours' => $hours];
    }

    // Filter by branch if specified and user is not SUPER_ADMIN
    if ($branchId && $user['role_code'] !== 'SUPER_ADMIN') {
        $whereClause .= " AND po.branch_id = :branch_id";
        $params['branch_id'] = $branchId;
    } elseif ($user['role_code'] !== 'SUPER_ADMIN' && $user['branch_id']) {
        // Filter by user's assigned branches
        $userBranchIds = array_map('trim', explode(',', $user['branch_id']));
        $placeholders = implode(',', array_fill(0, count($userBranchIds), '?'));
        $whereClause .= " AND po.branch_id IN ($placeholders)";
        $params = array_merge($params, $userBranchIds);
    }

    // Get total sales amount
    $totalSales = Database::fetch(
        "SELECT COALESCE(SUM(po.grand_total), 0) as total FROM pos_orders po $whereClause",
        $params
    );

    // Get transaction count
    $transactionCount = Database::fetch(
        "SELECT COUNT(*) as count FROM pos_orders po $whereClause",
        $params
    );

    // Get recent transactions for the list (last 10)
    $recentTransactions = Database::fetchAll(
        "SELECT po.order_code, po.grand_total, po.created_at,
                bb.branch_name,
                CONCAT(e.first_name, ' ', COALESCE(CONCAT(LEFT(e.middle_name, 1), '. '), ''), e.last_name) as cashier_name
         FROM pos_orders po
         LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
         LEFT JOIN cashier_sessions cs ON po.cashier_session_id = cs.session_id
         LEFT JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         $whereClause
         ORDER BY po.created_at DESC
         LIMIT 10",
        $params
    );

    // Get sales by time bucket — bucket size depends on filter range
    if ($today) {
        // Today: hourly buckets from 00:00 to current hour
        $bucketMinutes = 60;
        $currentHour = intval(date('H'));
        $bucketCount = $currentHour + 1; // Include current hour (0 to current)
        
        $salesByMinute = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $hour = $i;
            $bucketStart = date('Y-m-d H:i:s', strtotime(date('Y-m-d') . ' +' . $hour . ' hours'));
            $bucketEnd = date('Y-m-d H:i:s', strtotime(date('Y-m-d') . ' +' . ($hour + 1) . ' hours'));

            $bucketSales = Database::fetch(
                "SELECT COALESCE(SUM(po.grand_total), 0) as total,
                        COUNT(*) as count
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                 AND DATE(po.created_at) = CURDATE()
                 AND HOUR(po.created_at) = :hour",
                ['hour' => $hour]
            );

            $salesByMinute[] = [
                'time'   => sprintf('%02d:00', $hour),
                'amount' => floatval($bucketSales['total']),
                'count'  => intval($bucketSales['count']),
            ];
        }
    } elseif ($hours >= 24) {
        $bucketMinutes = 60;  // 1-hour buckets → 24 bars
        $bucketCount   = 24;

        $salesByMinute = [];
        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $bucketStart = date('Y-m-d H:i:s', strtotime('-' . (($i + 1) * $bucketMinutes) . ' minutes'));
            $bucketEnd   = date('Y-m-d H:i:s', strtotime('-' . ($i * $bucketMinutes) . ' minutes'));

            $bucketSales = Database::fetch(
                "SELECT COALESCE(SUM(po.grand_total), 0) as total,
                        COUNT(*) as count
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                 AND po.created_at >= :start AND po.created_at < :end",
                ['start' => $bucketStart, 'end' => $bucketEnd]
            );

            $salesByMinute[] = [
                'time'   => date('H:i', strtotime('-' . ($i * $bucketMinutes) . ' minutes')),
                'amount' => floatval($bucketSales['total']),
                'count'  => intval($bucketSales['count']),
            ];
        }
    } elseif ($hours >= 6) {
        $bucketMinutes = 15;  // 15-min buckets → 24 bars
        $bucketCount   = 24;

        $salesByMinute = [];
        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $bucketStart = date('Y-m-d H:i:s', strtotime('-' . (($i + 1) * $bucketMinutes) . ' minutes'));
            $bucketEnd   = date('Y-m-d H:i:s', strtotime('-' . ($i * $bucketMinutes) . ' minutes'));

            $bucketSales = Database::fetch(
                "SELECT COALESCE(SUM(po.grand_total), 0) as total,
                        COUNT(*) as count
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                 AND po.created_at >= :start AND po.created_at < :end",
                ['start' => $bucketStart, 'end' => $bucketEnd]
            );

            $salesByMinute[] = [
                'time'   => date('H:i', strtotime('-' . ($i * $bucketMinutes) . ' minutes')),
                'amount' => floatval($bucketSales['total']),
                'count'  => intval($bucketSales['count']),
            ];
        }
    } else {
        $bucketMinutes = 3;   // 3-min buckets → 20 bars
        $bucketCount   = 20;

        $salesByMinute = [];
        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $bucketStart = date('Y-m-d H:i:s', strtotime('-' . (($i + 1) * $bucketMinutes) . ' minutes'));
            $bucketEnd   = date('Y-m-d H:i:s', strtotime('-' . ($i * $bucketMinutes) . ' minutes'));

            $bucketSales = Database::fetch(
                "SELECT COALESCE(SUM(po.grand_total), 0) as total,
                        COUNT(*) as count
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                 AND po.created_at >= :start AND po.created_at < :end",
                ['start' => $bucketStart, 'end' => $bucketEnd]
            );

            $salesByMinute[] = [
                'time'   => date('H:i', strtotime('-' . ($i * $bucketMinutes) . ' minutes')),
                'amount' => floatval($bucketSales['total']),
                'count'  => intval($bucketSales['count']),
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total_sales' => floatval($totalSales['total']),
            'transaction_count' => intval($transactionCount['count']),
            'recent_transactions' => $recentTransactions,
            'sales_by_minute' => $salesByMinute
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
