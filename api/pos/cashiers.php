<?php
/**
 * POS Cashier Lookup API — Active cashiers with an open session for a branch
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$branchId = (int) ($_GET['branch_id'] ?? 0);

try {
    PosAccess::assertBranchAccess($user, $branchId);

    $cashiers = Database::fetchAll(
        "SELECT ua.user_id,
                ua.username,
                CONCAT_WS(' ', e.first_name,
                    IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(LEFT(e.middle_name, 1), '.'), ''),
                    e.last_name) AS fullname,
                cs.session_id,
                :result_branch_id AS branch_id,
                bb.branch_name,
                cs.started_at
         FROM user_accounts ua
         JOIN user_roles ur ON ur.role_id = ua.role_id AND ur.role_code = 'CASHIER'
         LEFT JOIN employees e ON e.emp_id = ua.emp_id
         LEFT JOIN cashier_sessions cs ON cs.cashier_user_id = ua.user_id
             AND cs.branch_id = :session_branch_id
             AND cs.status = 'OPEN'
         JOIN business_branches bb ON bb.branch_id = :branch_lookup_id
         WHERE ua.status = 'active'
           AND FIND_IN_SET(:assigned_branch_id, ua.branch_id) > 0
         ORDER BY fullname ASC, ua.username ASC",
        [
            'result_branch_id' => $branchId,
            'session_branch_id' => $branchId,
            'branch_lookup_id' => $branchId,
            'assigned_branch_id' => $branchId,
        ]
    );

    foreach ($cashiers as &$cashier) {
        $cashier['display_name'] = trim((string) ($cashier['fullname'] ?: $cashier['username']));
    }
    unset($cashier);

    echo json_encode(['success' => true, 'data' => $cashiers]);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 403 : 400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
