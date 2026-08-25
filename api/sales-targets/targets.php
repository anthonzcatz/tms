<?php
/**
 * Sales Targets API
 * Fetch sales targets with date range support
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/AnalyticsFilter.php';

header('Content-Type: application/json');

// Simple ID decoder to match JavaScript IdEncoder
function decodeBranchId($encoded) {
    if (!$encoded || !is_string($encoded)) {
        return null;
    }
    
    // If it's a plain numeric ID, return it as-is
    if (preg_match('/^\d+$/', $encoded)) {
        return (int)$encoded;
    }
    
    // Remove 'id' prefix if present
    $encoded = preg_replace('/^id/', '', $encoded);
    
    // Check if valid hex
    if (!preg_match('/^[0-9a-fA-F]+$/', $encoded)) {
        return null;
    }
    
    // Convert from hex
    $xorValue = hexdec($encoded);
    
    // Reverse the XOR (same key as JavaScript)
    $key = 0x5A3C8F1B;
    $originalId = $xorValue ^ $key;
    
    return $originalId > 0 ? $originalId : null;
}

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $month = $_GET['month'] ?? null;
    $targetDate = $_GET['target_date'] ?? null;
    $filterQuery = $_GET;
    if (empty($filterQuery['range'])) {
        if ($filterQuery['start_date'] ?? null) {
            $filterQuery['range'] = 'custom';
        } elseif ($targetDate) {
            $filterQuery['range'] = 'custom';
            $filterQuery['start_date'] = $targetDate;
            $filterQuery['end_date'] = $targetDate;
            $filterQuery['granularity'] = 'daily';
        } elseif ($month) {
            $filterQuery['range'] = 'custom';
            $filterQuery['start_date'] = $month . '-01';
            $filterQuery['end_date'] = date('Y-m-t', strtotime($month . '-01'));
            $filterQuery['granularity'] = 'monthly';
        } else {
            $filterQuery['range'] = 'today';
        }
    }
    $filter = AnalyticsFilter::parse($filterQuery, $user);
    $startDate = $filter['start_date'];
    $endDate = $filter['end_date'];
    $branchId = $filter['branch_id'];
    $userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
    $userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);

    $salesStartDate = $startDate;
    $salesEndDate = $endDate;
    
    // Get actual sales from pos_orders (using net sales: total - refunds)
    $actualSalesQuery = "
        SELECT 
            DATE(po.created_at) as sale_date,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(po.total_refunded_amount), 0) as total_refunds,
            COALESCE(SUM(po.grand_total), 0) - COALESCE(SUM(po.total_refunded_amount), 0) as actual_sales
        FROM pos_orders po
        WHERE po.status = 'completed'
        AND DATE(po.created_at) BETWEEN :start_date AND :end_date
    ";
    
    $actualBranchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'target_actual_branch');
    $actualSalesQuery .= " AND {$actualBranchScope['sql']}";
    $actualSalesQuery .= " GROUP BY DATE(po.created_at)";

    $salesParams = array_merge([
        'start_date' => $salesStartDate,
        'end_date' => $salesEndDate
    ], $actualBranchScope['params']);
    $actualSalesData = Database::fetchAll($actualSalesQuery, $salesParams);
    
    // Create lookup array for actual sales by date
    // If branch is specified, we still use date as key since sales are already filtered by branch
    $salesLookup = [];
    foreach ($actualSalesData as $sale) {
        $salesLookup[$sale['sale_date']] = floatval($sale['actual_sales']);
    }
    
    // Build query for sales targets
    $query = "SELECT st.id, st.branch_id, st.target_date, st.target_amount, st.notes, st.created_at,
              COALESCE(b.branch_name, 'All Branches') as branch_name
              FROM sales_targets st
              LEFT JOIN business_branches b ON st.branch_id = b.branch_id
              WHERE 1=1";
    
    $params = [];
    
    // Filter by date range (priority over month and specific date)
    if ($startDate && $endDate) {
        $query .= " AND st.target_date BETWEEN :start_date AND :end_date";
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
    }
    // Filter by month
    elseif ($month) {
        $query .= " AND st.target_date LIKE :month";
        $params['month'] = $month . '%';
    }
    // Filter by specific date
    elseif ($targetDate) {
        $query .= " AND st.target_date = :target_date";
        $params['target_date'] = $targetDate;
    }
    
    if ($branchId !== null) {
        $query .= " AND st.branch_id = :target_branch_id";
        $params['target_branch_id'] = $branchId;
    }

    if ($userRoleCode !== 'SUPER_ADMIN') {
        $allowedBranchIds = $filter['accessible_branch_ids'];
        $allowedPlaceholders = [];
        foreach ($allowedBranchIds as $index => $allowedBranchId) {
            $key = 'target_access_' . $index;
            $allowedPlaceholders[] = ':' . $key;
            $params[$key] = $allowedBranchId;
        }
        $query .= $allowedPlaceholders
            ? " AND (st.branch_id IS NULL OR st.branch_id IN (" . implode(', ', $allowedPlaceholders) . "))"
            : ' AND 1 = 0';
    }
    
    $query .= " ORDER BY st.target_date DESC, st.branch_id";
    
    $targets = Database::fetchAll($query, $params);
    
    // If no branch-specific targets found and branch was specified, try global targets
    if ($branchId && empty($targets)) {
        $query = "SELECT st.id, st.branch_id, st.target_date, st.target_amount, st.notes, st.created_at,
                  COALESCE(b.branch_name, 'All Branches') as branch_name
                  FROM sales_targets st
                  LEFT JOIN business_branches b ON st.branch_id = b.branch_id
                  WHERE 1=1";
        
        $params = [];
        
        // Re-apply date range filters
        if ($startDate && $endDate) {
            $query .= " AND st.target_date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif ($month) {
            $query .= " AND st.target_date LIKE :month";
            $params['month'] = $month . '%';
        } elseif ($targetDate) {
            $query .= " AND st.target_date = :target_date";
            $params['target_date'] = $targetDate;
        }
        
        // Get global targets (branch_id IS NULL)
        $query .= " AND st.branch_id IS NULL";
        
        $query .= " ORDER BY st.target_date DESC";
        
        $targets = Database::fetchAll($query, $params);
    }
    
    // Merge actual sales data with targets
    // For date range queries, sum all actual sales in the range and assign to targets
    $totalActualSales = array_sum($salesLookup);
    
    if (count($targets) > 0) {
        // Sum all target amounts
        $totalTargetAmount = array_sum(array_column($targets, 'target_amount'));
        
        // Assign total actual sales to all targets (for display purposes)
        // Return as number, not formatted string
        foreach ($targets as &$target) {
            $target['actual_sales'] = $totalActualSales;
        }
        
        // If multiple targets, add a summary entry
        if (count($targets) > 1) {
            $targets[] = [
                'id' => 0,
                'branch_id' => $branchId,
                'target_date' => 'TOTAL',
                'target_amount' => $totalTargetAmount,
                'notes' => 'Total for date range',
                'created_at' => date('Y-m-d H:i:s'),
                'branch_name' => $targets[0]['branch_name'] ?? 'All Branches',
                'actual_sales' => $totalActualSales
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'targets' => $targets,
        'filter' => AnalyticsFilter::responseMeta($filter)
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Sales Targets API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load sales targets']);
}
