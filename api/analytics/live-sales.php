<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosTransactionReporting.php';

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
    $branchIdRaw = isset($_GET['branch_id']) && $_GET['branch_id'] !== ''
        ? $_GET['branch_id']
        : null;
    $branchId = null;

    // Decode branch_id if provided
    if ($branchIdRaw !== null) {
        $branchId = IdEncoder::decode($branchIdRaw);
        if ($branchId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            exit;
        }
    }

    // Build a named branch condition so the totals, recent transactions,
    // and every chart bucket use the exact same branch scope.
    $branchWhere = '';
    $branchParams = [];
    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    if ($branchId !== null) {
        try {
            PosAccess::assertBranchAccess($user, (int) $branchId);
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $branchWhere = ' AND po.branch_id = :branch_id';
        $branchParams['branch_id'] = (int) $branchId;
    } elseif ($allowedBranchIds !== null) {
        if (!$allowedBranchIds) {
            $branchWhere = ' AND 1 = 0';
        } else {
            $branchPlaceholders = [];
            foreach (array_values($allowedBranchIds) as $index => $allowedBranchId) {
                $key = 'user_branch_' . $index;
                $branchPlaceholders[] = ':' . $key;
                $branchParams[$key] = $allowedBranchId;
            }
            $branchWhere = ' AND po.branch_id IN (' . implode(',', $branchPlaceholders) . ')';
        }
    }

    $transactionFrom = PosTransactionReporting::orderFrom();
    $transactionNet = PosTransactionReporting::netExpression();
    $transactionStatus = PosTransactionReporting::saleStatusCondition();

    // Build query for live sales from POS orders
    if ($today) {
        $whereClause = "WHERE $transactionStatus AND DATE(po.created_at) = CURDATE()" . $branchWhere;
        $params = $branchParams;
    } else {
        $whereClause = "WHERE $transactionStatus AND po.created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)" . $branchWhere;
        $params = array_merge(['hours' => $hours], $branchParams);
    }

    // Get total sales amount
    $totalSales = Database::fetch(
        "SELECT COALESCE(SUM($transactionNet), 0) as total $transactionFrom $whereClause",
        $params
    );

    // Get transaction count
    $transactionCount = Database::fetch(
        "SELECT COUNT(DISTINCT po.order_id) as count $transactionFrom $whereClause",
        $params
    );

    // Get recent transactions for the list (last 10)
    $recentTransactions = Database::fetchAll(
        "SELECT po.order_code, ($transactionNet) AS grand_total, po.created_at,
                bb.branch_name,
                CONCAT(e.first_name, ' ', COALESCE(CONCAT(LEFT(e.middle_name, 1), '. '), ''), e.last_name) as cashier_name
         $transactionFrom
         LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
         LEFT JOIN user_accounts ua ON po.created_by = ua.user_id
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
                "SELECT COALESCE(SUM($transactionNet), 0) as total,
                        COUNT(DISTINCT po.order_id) as count
                 $transactionFrom
                 WHERE $transactionStatus
                 AND DATE(po.created_at) = CURDATE()
                 AND HOUR(po.created_at) = :hour
                 $branchWhere",
                array_merge(['hour' => $hour], $branchParams)
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
                "SELECT COALESCE(SUM($transactionNet), 0) as total,
                        COUNT(DISTINCT po.order_id) as count
                 $transactionFrom
                 WHERE $transactionStatus
                 AND po.created_at >= :start AND po.created_at < :end
                 $branchWhere",
                array_merge(['start' => $bucketStart, 'end' => $bucketEnd], $branchParams)
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
                "SELECT COALESCE(SUM($transactionNet), 0) as total,
                        COUNT(DISTINCT po.order_id) as count
                 $transactionFrom
                 WHERE $transactionStatus
                 AND po.created_at >= :start AND po.created_at < :end
                 $branchWhere",
                array_merge(['start' => $bucketStart, 'end' => $bucketEnd], $branchParams)
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
                "SELECT COALESCE(SUM($transactionNet), 0) as total,
                        COUNT(DISTINCT po.order_id) as count
                 $transactionFrom
                 WHERE $transactionStatus
                 AND po.created_at >= :start AND po.created_at < :end
                 $branchWhere",
                array_merge(['start' => $bucketStart, 'end' => $bucketEnd], $branchParams)
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
