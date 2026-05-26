<?php
/**
 * Top Services API
 * Returns most popular services by revenue and order count
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
    $filterBranchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? $_GET['branch_id'] : null;

    // Build branch restriction (po table + tt table)
    $branchWhere = '';
    $ticketBranchWhere = '';
    $branchParams = [];

    if ($filterBranchId) {
        // Validate access for non-SUPER_ADMIN
        $allowed = ($userRoleCode === 'SUPER_ADMIN')
            || ($userBranchId && in_array($filterBranchId, array_map('trim', explode(',', $userBranchId))));
        if ($allowed) {
            $branchWhere = "AND po.branch_id = :branch_id";
            $ticketBranchWhere = "AND tt.branch_id = :branch_id";
            $branchParams['branch_id'] = $filterBranchId;
        } elseif ($userBranchId) {
            $branchIds = array_map('trim', explode(',', $userBranchId));
            $namedParams = [];
            foreach ($branchIds as $i => $bid) { $namedParams['bid_' . $i] = $bid; }
            $placeholders = implode(',', array_keys($namedParams));
            $branchWhere = "AND po.branch_id IN ($placeholders)";
            $ticketBranchWhere = "AND tt.branch_id IN ($placeholders)";
            $branchParams = $namedParams;
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) { $namedParams['bid_' . $i] = $bid; }
        $placeholders = implode(',', array_keys($namedParams));
        $branchWhere = "AND po.branch_id IN ($placeholders)";
        $ticketBranchWhere = "AND tt.branch_id IN ($placeholders)";
        $branchParams = $namedParams;
    }

    // Get date range filter
    $range = isset($_GET['range']) ? $_GET['range'] : 'today';
    $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
    $endDate   = isset($_GET['end_date'])   && $_GET['end_date']   !== '' ? $_GET['end_date']   : null;

    // Build date conditions for each table
    $posDateWhere = '';
    $ticketDateWhere = '';

    if ($startDate && $endDate) {
        $posDateWhere    = "AND DATE(po.created_at) BETWEEN :sd AND :ed";
        $ticketDateWhere = "AND DATE(tt.created_at) BETWEEN :sd AND :ed";
        $branchParams['sd'] = $startDate;
        $branchParams['ed'] = $endDate;
    } elseif ($range === 'today') {
        $posDateWhere = "AND DATE(po.created_at) = CURDATE()";
        $ticketDateWhere = "AND DATE(tt.created_at) = CURDATE()";
    } elseif ($range === 'week') {
        $posDateWhere = "AND po.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $ticketDateWhere = "AND tt.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($range === 'month') {
        $posDateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $ticketDateWhere = "AND tt.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    } elseif ($range === 'year') {
        $posDateWhere = "AND po.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
        $ticketDateWhere = "AND tt.created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
    }
    
    // Get top services by revenue for selected range (combine pos_order_items and ticket_transactions)
    $params = $branchParams;
    
    // Get services from pos_order_items
    $posServices = Database::fetchAll(
        "SELECT 
            st.name as service_name,
            st.code as service_code,
            COUNT(DISTINCT po.order_id) as orders,
            COALESCE(SUM(poi.unit_price * poi.quantity), 0) as revenue,
            COALESCE(AVG(poi.unit_price), 0) as avg_price
         FROM pos_order_items poi
         INNER JOIN pos_orders po ON poi.order_id = po.order_id
         INNER JOIN service_types st ON poi.service_type_id = st.service_type_id
         WHERE po.status = 'completed'
           $posDateWhere
           $branchWhere
         GROUP BY st.service_type_id, st.name, st.code",
        $params
    );

    // Get ticket transactions
    $ticketServices = Database::fetchAll(
        "SELECT 
            'Ticket Sale' as service_name,
            'TICKET_SALE' as service_code,
            COUNT(DISTINCT tt.transaction_id) as orders,
            COALESCE(SUM(tt.total_amount), 0) as revenue,
            COALESCE(AVG(tt.total_amount), 0) as avg_price
         FROM ticket_transactions tt
         WHERE tt.status = 'booked'
           $ticketDateWhere
           $ticketBranchWhere",
        $params
    );

    // Combine results
    $allServices = array_merge($posServices, $ticketServices);
    
    // Group by service_name and aggregate
    $groupedServices = [];
    foreach ($allServices as $service) {
        $key = $service['service_name'];
        if (!isset($groupedServices[$key])) {
            $groupedServices[$key] = [
                'service_name' => $service['service_name'],
                'service_code' => $service['service_code'],
                'orders' => 0,
                'revenue' => 0,
                'avg_price' => 0
            ];
        }
        $groupedServices[$key]['orders'] += $service['orders'];
        $groupedServices[$key]['revenue'] += $service['revenue'];
    }

    // Recalculate average price
    foreach ($groupedServices as &$service) {
        if ($service['orders'] > 0) {
            $service['avg_price'] = $service['revenue'] / $service['orders'];
        }
    }

    // Sort by revenue and limit to 10
    usort($groupedServices, function($a, $b) {
        return $b['revenue'] <=> $a['revenue'];
    });
    $topServices = array_slice($groupedServices, 0, 10);

    echo json_encode([
        'success' => true,
        'data' => [
            'services' => $topServices,
            'count' => count($topServices)
        ]
    ]);

} catch (Exception $e) {
    error_log('Top Services API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
