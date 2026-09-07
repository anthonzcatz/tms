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
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosTransactionReporting.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $filter = AnalyticsFilter::parse($_GET, $user);
    $branchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'services_branch');
    $dateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'services_date');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $dateWhere = 'AND ' . $dateScope['sql'];
    $transactionStatus = PosTransactionReporting::saleStatusCondition();
    $params = array_merge($branchScope['params'], $dateScope['params']);

    $allServices = Database::fetchAll(
        "SELECT
            CASE WHEN poi.item_type = 'SERVICE'
                THEN COALESCE(st.name, NULLIF(poi.description, ''), 'Service')
                ELSE 'Ticket Sale'
            END AS service_name,
            CASE WHEN poi.item_type = 'SERVICE'
                THEN COALESCE(st.code, 'SERVICE')
                ELSE 'TICKET_SALE'
            END AS service_code,
            COUNT(DISTINCT po.order_id) AS orders,
            COALESCE(SUM(poi.total_amount), 0) AS revenue,
            COALESCE(AVG(poi.total_amount), 0) AS avg_price
         FROM pos_order_items poi
         INNER JOIN pos_orders po ON poi.order_id = po.order_id
         LEFT JOIN service_types st ON poi.service_type_id = st.service_type_id
         WHERE $transactionStatus
           AND COALESCE(poi.total_amount, 0) > 0
           $dateWhere
           $branchWhere
         GROUP BY poi.item_type, st.service_type_id, st.name, st.code, poi.description
         ORDER BY revenue DESC",
        $params
    );
    
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
