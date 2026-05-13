<?php
/**
 * Bank Accounts API Endpoint
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null;
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, NOW())",
        [
            'user_id' => $userId, 'device_id' => $deviceId, 'action' => $action,
            'module_name' => $moduleName, 'reference_code' => $referenceCode,
            'ip_address' => $ipAddress,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null
        ]
    );
}

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':    handleGet();    break;
    case 'POST':   handlePost();   break;
    case 'PUT':    handlePut();    break;
    case 'DELETE': handleDelete(); break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $account = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $id]);
        if (!$account) { echo json_encode(['success' => false, 'error' => 'Not found']); return; }
        echo json_encode(['success' => true, 'data' => $account]);
        return;
    }
    $activeOnly = $_GET['active_only'] ?? null;
    $methodId = $_GET['payment_method_id'] ?? null;

    $sql = "SELECT ba.*, bb.branch_name, pm.method_name, pm.method_type
            FROM bank_accounts ba
            LEFT JOIN business_branches bb ON ba.branch_id = bb.branch_id
            LEFT JOIN payment_methods pm ON ba.payment_method_id = pm.method_id
            WHERE 1=1";
    $params = [];
    if ($activeOnly) { $sql .= " AND ba.is_active = 1"; }
    if ($methodId) { $sql .= " AND ba.payment_method_id = :method_id"; $params['method_id'] = $methodId; }
    $sql .= " ORDER BY bb.branch_name ASC, ba.bank_name ASC";

    echo json_encode(['success' => true, 'data' => Database::fetchAll($sql, $params)]);
}

function handlePost() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $bankName = trim($input['bank_name'] ?? '');
    $accountName = trim($input['account_name'] ?? '');
    $accountNumber = trim($input['account_number'] ?? '');

    if (!$bankName || !$accountName || !$accountNumber) {
        echo json_encode(['success' => false, 'error' => 'Bank name, account name, and account number are required.']); return;
    }

    Database::execute(
        "INSERT INTO bank_accounts
            (branch_id, bank_name, account_name, account_number, account_type, payment_method_id, is_active, notes, created_at)
         VALUES
            (:branch_id, :bank_name, :account_name, :account_number, :account_type, :payment_method_id, :is_active, :notes, NOW())",
        [
            'branch_id' => $input['branch_id'] ?: null,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'account_type' => $input['account_type'] ?? null,
            'payment_method_id' => $input['payment_method_id'] ?: null,
            'is_active' => $input['is_active'] ?? 1,
            'notes' => $input['notes'] ?? null,
        ]
    );
    $newId = Database::connection()->lastInsertId();

    logActivity($user['user_id'], 'CREATE_BANK_ACCOUNT', 'BANK_ACCOUNTS', "BA-{$newId}",
        null, ['bank_name' => $bankName, 'account_name' => $accountName]);

    echo json_encode(['success' => true, 'message' => 'Bank account created.', 'bank_account_id' => $newId]);
}

function handlePut() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = $input['bank_account_id'] ?? null;
    if (!$accountId) { echo json_encode(['success' => false, 'error' => 'Missing account ID.']); return; }

    $existing = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $accountId]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Account not found.']); return; }

    // Status-only toggle
    if (count($input) === 2 && isset($input['is_active'])) {
        Database::execute("UPDATE bank_accounts SET is_active = :is_active, updated_at = NOW() WHERE bank_account_id = :id",
            ['is_active' => $input['is_active'], 'id' => $accountId]);
        logActivity($user['user_id'], 'TOGGLE_BANK_ACCOUNT', 'BANK_ACCOUNTS', "BA-{$accountId}",
            ['is_active' => $existing['is_active']], ['is_active' => $input['is_active']]);
        echo json_encode(['success' => true, 'message' => 'Status updated.']); return;
    }

    $bankName = trim($input['bank_name'] ?? $existing['bank_name']);
    $accountName = trim($input['account_name'] ?? $existing['account_name']);
    $accountNumber = trim($input['account_number'] ?? $existing['account_number']);

    if (!$bankName || !$accountName || !$accountNumber) {
        echo json_encode(['success' => false, 'error' => 'Bank name, account name, and account number are required.']); return;
    }

    Database::execute(
        "UPDATE bank_accounts SET
            branch_id = :branch_id, bank_name = :bank_name, account_name = :account_name,
            account_number = :account_number, account_type = :account_type,
            payment_method_id = :payment_method_id, is_active = :is_active,
            notes = :notes, updated_at = NOW()
         WHERE bank_account_id = :id",
        [
            'branch_id' => $input['branch_id'] ?: null,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'account_type' => $input['account_type'] ?? null,
            'payment_method_id' => $input['payment_method_id'] ?: null,
            'is_active' => $input['is_active'] ?? 1,
            'notes' => $input['notes'] ?? null,
            'id' => $accountId
        ]
    );

    logActivity($user['user_id'], 'UPDATE_BANK_ACCOUNT', 'BANK_ACCOUNTS', "BA-{$accountId}", $existing,
        ['bank_name' => $bankName, 'account_name' => $accountName]);

    echo json_encode(['success' => true, 'message' => 'Bank account updated.']);
}

function handleDelete() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = $input['bank_account_id'] ?? null;
    if (!$accountId) { echo json_encode(['success' => false, 'error' => 'Missing account ID.']); return; }

    $existing = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $accountId]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Account not found.']); return; }

    // Check if used in payments
    $inUse = Database::fetch("SELECT payment_id FROM transaction_payments WHERE bank_account_id = :id LIMIT 1", ['id' => $accountId]);
    if ($inUse) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete — this account is already used in transactions. Deactivate it instead.']); return;
    }

    Database::execute("DELETE FROM bank_accounts WHERE bank_account_id = :id", ['id' => $accountId]);
    logActivity($user['user_id'], 'DELETE_BANK_ACCOUNT', 'BANK_ACCOUNTS', "BA-{$accountId}", $existing, null);
    echo json_encode(['success' => true, 'message' => 'Bank account deleted.']);
}
