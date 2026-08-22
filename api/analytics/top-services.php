<?php
/**
 * Top Services API
 * Returns most popular services by revenue and order count
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

    $filter = AnalyticsFilter::parse($_GET, $user);
    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;
    $poBranchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'services_po_branch');
    $ticketBranchScope = AnalyticsFilter::branchCondition($filter, 'tt.branch_id', 'services_ticket_branch');
    $poDateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'services_po_date');
    $ticketDateScope = AnalyticsFilter::dateCondition($filter, 'tt.created_at', 'services_ticket_date');
    $branchWhere = 'AND ' . $poBranchScope['sql'];
    $ticketBranchWhere = 'AND ' . $ticketBranchScope['sql'];
    $posDateWhere = 'AND ' . $poDateScope['sql'];
    $ticketDateWhere = 'AND ' . $ticketDateScope['sql'];
    
    // Get top services by revenue for selected range (combine pos_order_items and ticket_transactions)
    $params = array_merge($poBranchScope['params'], $poDateScope['params']);
    $ticketParams = array_merge($ticketBranchScope['params'], $ticketDateScope['params']);
    
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
        $ticketParams
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
            'count' => count($topServices),
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Top Services API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load top services']);
}
