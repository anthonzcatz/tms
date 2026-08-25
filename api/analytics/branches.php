<?php
/**
 * Branches API
 * Returns list of branches accessible to the current user
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

    // Get branches for filter dropdown
    $branchWhere = ["status = 'active'"];
    $branchParams = [];
    PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'analytics_branch');
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name
         FROM business_branches
         WHERE " . implode(' AND ', $branchWhere) . "
         ORDER BY branch_name",
        $branchParams
    );

    $branches = array_map(static function (array $branch): array {
        $encodedId = IdEncoder::encode($branch['branch_id']);
        return [
            'id' => $encodedId,
            'name' => $branch['branch_name'],
            'branch_id' => $encodedId,
            'branch_name' => $branch['branch_name']
        ];
    }, $branches);

    echo json_encode([
        'success' => true,
        'data' => $branches
    ]);

} catch (Exception $e) {
    error_log('Branches API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
