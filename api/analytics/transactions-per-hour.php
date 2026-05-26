<?php
/**
 * Transactions per Hour API
 * Returns hourly transaction volume for the dashboard
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

    $range = isset($_GET['range']) ? $_GET['range'] : 'today';
    $branchId = isset($_GET['branch_id']) ? $_GET['branch_id'] : null;
    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;

    // Build branch restriction
    $branchWhere = '';
    $branchParams = [];

    // If specific branch is selected and user has access
    if ($branchId && $userRoleCode === 'SUPER_ADMIN') {
        $branchWhere = "AND po.branch_id = :branch_id";
        $branchParams['branch_id'] = $branchId;
    } elseif ($branchId && $userBranchId) {
        // Check if user has access to the selected branch
        $branchIds = array_map('trim', explode(',', $userBranchId));
        if (in_array($branchId, $branchIds)) {
            $branchWhere = "AND po.branch_id = :branch_id";
            $branchParams['branch_id'] = $branchId;
        } else {
            // User doesn't have access, use their assigned branches
            $namedParams = [];
            foreach ($branchIds as $i => $bid) {
                $namedParams['bid_' . $i] = $bid;
            }
            $placeholders = implode(',', array_keys($namedParams));
            $branchWhere = "AND po.branch_id IN ($placeholders)";
            $branchParams = $namedParams;
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        // No specific branch selected, use user's assigned branches
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

    // Build date range
    $dateWhere = '';
    $dateParams = [];
    $labels = [];

    if ($startDate && $endDate) {
        // Custom range — daily breakdown between dates (cap at 90 days)
        $start = strtotime($startDate);
        $end   = strtotime($endDate);
        if ($start > $end) { $tmp = $start; $start = $end; $end = $tmp; }
        $dayCount = min(90, (int)(($end - $start) / 86400) + 1);
        $dateWhere = "AND DATE(po.created_at) BETWEEN :sd AND :ed";
        $dateParams['sd'] = date('Y-m-d', $start);
        $dateParams['ed'] = date('Y-m-d', $end);
        for ($i = 0; $i < $dayCount; $i++) {
            $labels[] = date('M d', strtotime("+$i days", $start));
        }
    } elseif ($range === 'today') {
        $dateWhere = "AND DATE(po.created_at) = CURDATE()";
        for ($i = 0; $i < 24; $i++) {
            $labels[] = sprintf('%02d:00', $i);
        }
    } elseif ($range === 'week') {
        $dateWhere = "AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = date('D', strtotime("-$i days"));
        }
    } elseif ($range === 'month') {
        $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $daysInMonth = (int)date('t');
        $currentDay = (int)date('j');
        for ($i = 1; $i <= $currentDay; $i++) {
            $labels[] = date('M d', strtotime(date('Y-m') . "-$i"));
        }
    } elseif ($range === 'year') {
        // For year, show months from start of current year
        $dateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
        $currentMonth = (int)date('n');
        for ($i = 1; $i <= $currentMonth; $i++) {
            $labels[] = date('M Y', strtotime(date('Y') . "-$i-01"));
        }
    }

    // Fetch transaction data
    $params = array_merge($branchParams, $dateParams);
    
    if ($range === 'today' && !($startDate && $endDate)) {
        // Hourly breakdown
        $data = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = $i;
            $result = Database::fetch(
                "SELECT COUNT(DISTINCT po.order_id) as count,
                        COALESCE(SUM(po.grand_total), 0) as total
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                   AND DATE(po.created_at) = CURDATE()
                   AND HOUR(po.created_at) = :hour
                   $branchWhere",
                array_merge(['hour' => $hour], $branchParams)
            );
            $data[] = intval($result['count'] ?? 0);
        }
    } elseif ($range === 'year' && !($startDate && $endDate)) {
        // Monthly breakdown for year
        $monthlyData = Database::fetchAll(
            "SELECT DATE_FORMAT(po.created_at, '%Y-%m') as ym,
                    COUNT(DISTINCT po.order_id) as count
             FROM pos_orders po
             WHERE po.status = 'completed'
               $dateWhere
               $branchWhere
             GROUP BY DATE_FORMAT(po.created_at, '%Y-%m')",
            $params
        );
        $dataMap = [];
        foreach ($monthlyData as $row) { $dataMap[$row['ym']] = intval($row['count']); }
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("first day of -$i month"));
            $data[] = $dataMap[$ym] ?? 0;
        }
    } else {
        // Daily breakdown (week, month, custom)
        $dailyData = Database::fetchAll(
            "SELECT DATE(po.created_at) as date,
                    COUNT(DISTINCT po.order_id) as count
             FROM pos_orders po
             WHERE po.status = 'completed'
               $dateWhere
               $branchWhere
             GROUP BY DATE(po.created_at)
             ORDER BY date ASC",
            $params
        );

        $dataMap = [];
        foreach ($dailyData as $day) {
            $dataMap[$day['date']] = intval($day['count']);
        }

        $data = [];
        if ($startDate && $endDate) {
            $start = strtotime($startDate);
            $end   = strtotime($endDate);
            if ($start > $end) { $tmp = $start; $start = $end; $end = $tmp; }
            $dayCount = min(90, (int)(($end - $start) / 86400) + 1);
            for ($i = 0; $i < $dayCount; $i++) {
                $date = date('Y-m-d', strtotime("+$i days", $start));
                $data[] = $dataMap[$date] ?? 0;
            }
        } elseif ($range === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $data[] = $dataMap[$date] ?? 0;
            }
        } else {
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $data[] = $dataMap[$date] ?? 0;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'data' => $data
        ]
    ]);

} catch (Exception $e) {
    error_log('Transactions per Hour API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
