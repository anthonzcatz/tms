<?php
/**
 * Create Sales Target API
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
    
    if (preg_match('/^\d+$/', $encoded)) {
        return (int)$encoded;
    }
    
    $encoded = preg_replace('/^id/', '', $encoded);
    
    if (!preg_match('/^[0-9a-fA-F]+$/', $encoded)) {
        return null;
    }
    
    $xorValue = hexdec($encoded);
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

    $input = json_decode(file_get_contents('php://input'), true);
    
    $branchId = isset($input['branch_id']) && $input['branch_id'] !== '' ? $input['branch_id'] : null;
    $targetDate = $input['target_date'] ?? null;
    $targetAmount = $input['target_amount'] ?? 0;
    $notes = $input['notes'] ?? null;
    
    // Decode branch_id if it's encoded
    if ($branchId) {
        $decodedBranchId = decodeBranchId($branchId);
        if ($decodedBranchId !== null) {
            $branchId = $decodedBranchId;
        }
    }
    
    if (!$targetDate || $targetAmount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    // Check if target already exists for this branch and date
    $checkQuery = "SELECT id FROM sales_targets WHERE branch_id " . 
                  ($branchId ? "= :branch_id" : "IS NULL") . 
                  " AND target_date = :target_date";
    
    $checkParams = ['target_date' => $targetDate];
    if ($branchId) {
        $checkParams['branch_id'] = $branchId;
    }
    
    $existing = Database::fetch($checkQuery, $checkParams);
    
    if ($existing) {
        // Update existing target
        $updateQuery = "UPDATE sales_targets SET target_amount = :target_amount, notes = :notes, updated_at = NOW() WHERE id = :id";
        Database::execute($updateQuery, [
            'target_amount' => $targetAmount,
            'notes' => $notes,
            'id' => $existing['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Target updated']);
    } else {
        // Insert new target
        $insertQuery = "INSERT INTO sales_targets (branch_id, target_date, target_amount, notes, created_by) " .
                       "VALUES (:branch_id, :target_date, :target_amount, :notes, :created_by)";
        
        Database::execute($insertQuery, [
            'branch_id' => $branchId,
            'target_date' => $targetDate,
            'target_amount' => $targetAmount,
            'notes' => $notes,
            'created_by' => $user['user_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Target created']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
