<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/TicketStockAccess.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if (!TicketStockAccess::canManage($user)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    if ($method === 'GET') {
        handleTicketStockAccessGet();
    } elseif ($method === 'POST') {
        handleTicketStockAccessPost();
    } elseif ($method === 'PUT') {
        handleTicketStockAccessPut();
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    error_log('Ticket Stock Access API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to process Ticket Stock Access request.']);
}

function handleTicketStockAccessGet(): void
{
    global $user;
    $where = ['1 = 1'];
    $params = [];
    if (!TicketStockAccess::canManageAllBranches($user)) {
        PosAccess::applyBranchScope($where, $params, 'a.branch_id', $user, 'ticket_stock_access_list_branch');
    }

    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '') {
        $branchId = requireTicketStockAccessId($_GET['branch_id'], 'branch_id');
        assertTicketStockAccessBranch($branchId);
        $where[] = 'a.branch_id = :filter_branch_id';
        $params['filter_branch_id'] = $branchId;
    }
    if (!empty($_GET['access_type'])) {
        $where[] = 'a.access_type = :filter_access_type';
        $params['filter_access_type'] = TicketStockAccess::normalizeAccessType($_GET['access_type']);
    }
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $isActive = filter_var($_GET['is_active'], FILTER_VALIDATE_INT);
        if ($isActive === false || !in_array($isActive, [0, 1], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }
        $where[] = 'a.is_active = :filter_is_active';
        $params['filter_is_active'] = $isActive;
    }

    $assignments = Database::fetchAll(
        "SELECT a.assignment_id, a.user_id, a.branch_id, a.access_type, a.is_active,
                a.created_at, a.updated_at,
                ua.username, ua.user_code, ur.role_code, ur.role_name,
                CONCAT_WS(' ', e.first_name, e.last_name) AS full_name,
                b.branch_name,
                creator.username AS created_by_username,
                updater.username AS updated_by_username
         FROM ticket_stock_access_assignments a
         JOIN user_accounts ua ON ua.user_id = a.user_id
         LEFT JOIN user_roles ur ON ur.role_id = ua.role_id
         LEFT JOIN employees e ON e.emp_id = ua.emp_id
         JOIN business_branches b ON b.branch_id = a.branch_id
         LEFT JOIN user_accounts creator ON creator.user_id = a.created_by
         LEFT JOIN user_accounts updater ON updater.user_id = a.updated_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY b.branch_name, a.access_type, COALESCE(NULLIF(CONCAT_WS(' ', e.first_name, e.last_name), ''), ua.username)",
        $params
    );

    echo json_encode(['success' => true, 'data' => $assignments]);
}

function handleTicketStockAccessPost(): void
{
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('A valid JSON payload is required.');
    }

    $userId = requireTicketStockAccessId($input['user_id'] ?? null, 'user_id');
    $branchId = requireTicketStockAccessId($input['branch_id'] ?? null, 'branch_id');
    $accessType = TicketStockAccess::normalizeAccessType($input['access_type'] ?? null);
    assertTicketStockAccessBranch($branchId);
    assertTicketStockAccessUser($userId);

    $pdo = Database::connection();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        Database::execute(
            "INSERT INTO ticket_stock_access_assignments
                (user_id, branch_id, access_type, is_active, created_by, updated_by, created_at)
             VALUES (:user_id, :branch_id, :access_type, 1, :created_by, :updated_by, NOW())
             ON DUPLICATE KEY UPDATE
                is_active = 1,
                updated_by = :updated_by_existing,
                updated_at = NOW()",
            [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'access_type' => $accessType,
                'created_by' => (int) $user['user_id'],
                'updated_by' => (int) $user['user_id'],
                'updated_by_existing' => (int) $user['user_id'],
            ]
        );
        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    logTicketStockAccessActivity('TICKET_STOCK_ACCESS_ASSIGNED', $userId, $branchId, $accessType, 1);
    echo json_encode(['success' => true, 'message' => 'Ticket Stock Access assignment saved.']);
}

function handleTicketStockAccessPut(): void
{
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('A valid JSON payload is required.');
    }

    $assignmentId = requireTicketStockAccessId($input['assignment_id'] ?? null, 'assignment_id');
    $assignment = Database::fetch(
        "SELECT assignment_id, user_id, branch_id, access_type, is_active
         FROM ticket_stock_access_assignments
         WHERE assignment_id = :assignment_id",
        ['assignment_id' => $assignmentId]
    );
    if (!$assignment) {
        throw new RuntimeException('Assignment not found.');
    }
    assertTicketStockAccessBranch((int) $assignment['branch_id']);

    $targetUserId = array_key_exists('user_id', $input)
        ? requireTicketStockAccessId($input['user_id'], 'user_id')
        : (int) $assignment['user_id'];
    $targetBranchId = array_key_exists('branch_id', $input)
        ? requireTicketStockAccessId($input['branch_id'], 'branch_id')
        : (int) $assignment['branch_id'];
    $targetAccessType = array_key_exists('access_type', $input)
        ? TicketStockAccess::normalizeAccessType($input['access_type'])
        : (string) $assignment['access_type'];
    $isActive = array_key_exists('is_active', $input)
        ? filter_var($input['is_active'], FILTER_VALIDATE_INT)
        : (int) $assignment['is_active'];
    if ($isActive === false || !in_array($isActive, [0, 1], true)) {
        throw new InvalidArgumentException('is_active must be 0 or 1.');
    }

    assertTicketStockAccessBranch($targetBranchId);
    assertTicketStockAccessUser($targetUserId);
    $duplicate = Database::fetch(
        "SELECT assignment_id
         FROM ticket_stock_access_assignments
         WHERE user_id = :user_id
           AND branch_id = :branch_id
           AND access_type = :access_type
           AND assignment_id <> :assignment_id
         LIMIT 1",
        [
            'user_id' => $targetUserId,
            'branch_id' => $targetBranchId,
            'access_type' => $targetAccessType,
            'assignment_id' => $assignmentId,
        ]
    );
    if ($duplicate) {
        throw new InvalidArgumentException('An assignment already exists for this user, branch, and access type.');
    }

    Database::execute(
        "UPDATE ticket_stock_access_assignments
         SET user_id = :user_id,
             branch_id = :branch_id,
             access_type = :access_type,
             is_active = :is_active,
             updated_by = :updated_by,
             updated_at = NOW()
         WHERE assignment_id = :assignment_id",
        [
            'user_id' => $targetUserId,
            'branch_id' => $targetBranchId,
            'access_type' => $targetAccessType,
            'is_active' => $isActive,
            'updated_by' => (int) $user['user_id'],
            'assignment_id' => $assignmentId,
        ]
    );

    logTicketStockAccessActivity(
        'TICKET_STOCK_ACCESS_UPDATED',
        $targetUserId,
        $targetBranchId,
        $targetAccessType,
        $isActive
    );
    echo json_encode(['success' => true, 'message' => 'Ticket Stock Access assignment updated.']);
}

function assertTicketStockAccessBranch(int $branchId): void
{
    global $user;
    TicketStockAccess::assertManagedBranch($user, $branchId);
    $branch = Database::fetch(
        "SELECT branch_id FROM business_branches WHERE branch_id = :branch_id AND status = 'active'",
        ['branch_id' => $branchId]
    );
    if (!$branch) {
        throw new InvalidArgumentException('The selected branch is invalid or inactive.');
    }
}

function assertTicketStockAccessUser(int $userId): void
{
    $targetUser = Database::fetch(
        "SELECT user_id FROM user_accounts WHERE user_id = :user_id AND status = 'active'",
        ['user_id' => $userId]
    );
    if (!$targetUser) {
        throw new InvalidArgumentException('The selected user is invalid or inactive.');
    }
}

function requireTicketStockAccessId($value, string $field): int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
        throw new InvalidArgumentException("{$field} must be a positive integer.");
    }
    return (int) $value;
}

function logTicketStockAccessActivity(string $action, int $assignedUserId, int $branchId, string $accessType, int $isActive): void
{
    global $user;
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:user_id, NULL, :action, 'TICKET_STOCK_ACCESS', :reference_code, :ip_address, :old_value, :new_value, NOW())",
        [
            'user_id' => (int) $user['user_id'],
            'action' => $action,
            'reference_code' => "U{$assignedUserId}-B{$branchId}-{$accessType}",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'old_value' => null,
            'new_value' => json_encode([
                'assigned_user_id' => $assignedUserId,
                'branch_id' => $branchId,
                'access_type' => $accessType,
                'is_active' => $isActive,
            ]),
        ]
    );
}
