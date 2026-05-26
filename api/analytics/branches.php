<?php
/**
 * Branches API
 * Returns list of branches accessible to the current user
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

    // Get branches for filter dropdown
    $branches = [];
    if ($userRoleCode === 'SUPER_ADMIN') {
        $branches = Database::fetchAll(
            "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
        );
    } elseif ($userBranchId) {
        $branchIds = array_map('trim', explode(',', $userBranchId));
        $namedParams = [];
        foreach ($branchIds as $i => $bid) {
            $namedParams['bid_' . $i] = $bid;
        }
        $placeholders = implode(',', array_keys($namedParams));
        $branches = Database::fetchAll(
            "SELECT branch_id, branch_name FROM business_branches WHERE branch_id IN ($placeholders) AND status = 'active' ORDER BY branch_name",
            $namedParams
        );
    }

    echo json_encode([
        'success' => true,
        'data' => $branches
    ]);

} catch (Exception $e) {
    error_log('Branches API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
