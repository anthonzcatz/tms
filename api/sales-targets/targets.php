<?php
/**
 * Sales Targets API
 * Fetch sales targets with date range support
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';

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
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $branchId = $_GET['branch_id'] ?? null;
    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;
    
    // Decode branch_id if it's encoded
    if ($branchId) {
        $decodedBranchId = decodeBranchId($branchId);
        if ($decodedBranchId !== null) {
            $branchId = $decodedBranchId;
        }
    }
    
    // Debug logging
    error_log("Sales Targets API - branchId: " . ($branchId ?? 'NULL') . ", startDate: " . ($startDate ?? 'NULL') . ", endDate: " . ($endDate ?? 'NULL'));

    // Determine date range for actual sales lookup
    $salesStartDate = $startDate;
    $salesEndDate = $endDate;
    
    if (!$salesStartDate || !$salesEndDate) {
        if ($targetDate) {
            $salesStartDate = $salesEndDate = $targetDate;
        } elseif ($month) {
            $salesStartDate = $month . '-01';
            $salesEndDate = date('Y-m-t', strtotime($salesStartDate));
        } else {
            $salesStartDate = $salesEndDate = date('Y-m-d');
        }
    }
    
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
    
    $salesParams = [
        'start_date' => $salesStartDate,
        'end_date' => $salesEndDate
    ];
    
    // Add branch filter to sales query if specified
    if ($branchId) {
        $actualSalesQuery .= " AND po.branch_id = :branch_id";
        $salesParams['branch_id'] = $branchId;
    }
    
    $actualSalesQuery .= " GROUP BY DATE(po.created_at)";
    
    error_log("Sales Targets API - Actual Sales Query: " . $actualSalesQuery);
    error_log("Sales Targets API - Sales Params: " . json_encode($salesParams));
    
    $actualSalesData = Database::fetchAll($actualSalesQuery, $salesParams);
    
    error_log("Sales Targets API - Actual Sales Data: " . json_encode($actualSalesData));
    
    // Create lookup array for actual sales by date
    // If branch is specified, we still use date as key since sales are already filtered by branch
    $salesLookup = [];
    foreach ($actualSalesData as $sale) {
        $salesLookup[$sale['sale_date']] = floatval($sale['actual_sales']);
    }
    
    error_log("Sales Targets API - Sales Lookup: " . json_encode($salesLookup));
    
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
    
    // Filter by branch
    if ($branchId) {
        // First try to get branch-specific targets
        $query .= " AND st.branch_id = :branch_id";
        $params['branch_id'] = $branchId;
    }
    
    // Apply branch restrictions for non-super admins
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $query .= " AND (st.branch_id IS NULL OR st.branch_id IN ($placeholders))";
        $params = array_merge($params, $branchIds);
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
        
        // Apply branch restrictions for non-super admins
        if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
            $branchIds = array_map('trim', explode(',', $userBranchId));
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $query .= " AND (st.branch_id IS NULL OR st.branch_id IN ($placeholders))";
            $params = array_merge($params, $branchIds);
        }
        
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
    
    error_log("Sales Targets API - Final targets with actual_sales: " . json_encode($targets));
    
    echo json_encode(['success' => true, 'targets' => $targets]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
