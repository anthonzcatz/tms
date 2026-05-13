<?php
/**
 * Bank Transfer Confirmations API
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

function logActivity($userId, $action, $module, $ref = null, $old = null, $new = null) {
    Database::execute(
        "INSERT INTO activity_logs (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, NULL, :action, :mod, :ref, :ip, :old, :new, NOW())",
        ['uid' => $userId, 'action' => $action, 'mod' => $module, 'ref' => $ref,
         'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
         'old' => $old ? json_encode($old) : null, 'new' => $new ? json_encode($new) : null]
    );
}

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $p = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id", ['id' => $id]);
        echo json_encode($p ? ['success' => true, 'data' => $p] : ['success' => false, 'error' => 'Not found']);
        return;
    }
    $status = $_GET['status'] ?? 'PENDING';
    $rows = Database::fetchAll(
        "SELECT tp.*, pm.method_name, ba.bank_name
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN bank_accounts ba ON tp.bank_account_id = ba.bank_account_id
         WHERE pm.requires_confirmation = 1 AND tp.confirmation_status = :status
         ORDER BY tp.created_at DESC",
        ['status' => $status]
    );
    echo json_encode(['success' => true, 'data' => $rows]);
    return;
}

if ($method === 'PUT') {
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_BANK_CONFIRMATIONS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input  = json_decode(file_get_contents('php://input'), true);
    $payId  = $input['payment_id'] ?? null;
    $action = $input['action'] ?? null;
    $notes  = $input['notes'] ?? null;

    if (!$payId || !in_array($action, ['CONFIRMED', 'REJECTED'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']); return;
    }

    $existing = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id", ['id' => $payId]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Payment not found.']); return; }
    if ($existing['confirmation_status'] !== 'PENDING') {
        echo json_encode(['success' => false, 'error' => 'This payment has already been reviewed.']); return;
    }

    Database::execute(
        "UPDATE transaction_payments
         SET confirmation_status = :status, confirmed_by = :uid, confirmed_at = NOW(), confirmation_notes = :notes
         WHERE payment_id = :id",
        ['status' => $action, 'uid' => $user['user_id'], 'notes' => $notes, 'id' => $payId]
    );

    logActivity($user['user_id'], $action . '_BANK_TRANSFER', 'BANK_CONFIRMATIONS', "PAY-{$payId}",
        ['status' => 'PENDING'], ['status' => $action, 'notes' => $notes]);

    echo json_encode(['success' => true, 'message' => 'Payment ' . strtolower($action) . ' successfully.']);
    return;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
