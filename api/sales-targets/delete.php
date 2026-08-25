<?php
/**
 * Delete Sales Target API
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    $target = Database::fetch(
        "SELECT branch_id FROM sales_targets WHERE id = :id",
        ['id' => (int) $id]
    );
    if (!$target) {
        echo json_encode(['success' => false, 'error' => 'Target not found']);
        exit;
    }
    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    if ($target['branch_id'] === null && $allowedBranchIds !== null) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied for this target']);
        exit;
    }
    if ($target['branch_id'] !== null) {
        PosAccess::assertBranchAccess($user, (int) $target['branch_id']);
    }
    
    $query = "DELETE FROM sales_targets WHERE id = :id";
    Database::execute($query, ['id' => $id]);
    
    echo json_encode(['success' => true, 'message' => 'Target deleted']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
