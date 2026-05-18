<?php
/**
 * Wallet Transactions API Endpoint
 * Handles CRUD operations for wallet transaction management
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Helper function for logging activity
function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null; // Can be enhanced to track device ID if needed
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, NOW())",
        [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'action' => $action,
            'module_name' => $moduleName,
            'reference_code' => $referenceCode,
            'ip_address' => $ipAddress,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null
        ]
    );
}

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission - SUPER_ADMIN or users with VIEW_WALLET_TRANSACTIONS permission
$user = Auth::user();
$canViewTransactions = ($user['role_code'] === 'SUPER_ADMIN');

if (!$canViewTransactions) {
    $canViewTransactions = Auth::can('VIEW_WALLET_TRANSACTIONS');
}

if (!$canViewTransactions) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need VIEW_WALLET_TRANSACTIONS permission to access this resource.']);
    exit;
}

// Get user branch for filtering
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// CSRF protection for POST/PUT/DELETE requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? $_GET['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Wallet Transactions API Error: " . $e->getMessage());
    error_log("Wallet Transactions API Trace: " . $e->getTraceAsString());
    error_log("Wallet Transactions API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list transactions or get single transaction
 */
function handleGet() {
    $txnId = $_GET['id'] ?? null;
    $walletId = $_GET['wallet_id'] ?? null;
    $txnType = $_GET['txn_type'] ?? null;
    $direction = $_GET['direction'] ?? null;
    $search = $_GET['search'] ?? '';
    $date = $_GET['date'] ?? null;
    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;

    // Get single transaction
    if ($txnId) {
        $sql = "SELECT wt.*,
                       tp.provider_name,
                       bb.branch_name,
                       CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name,
                       ua.username as created_by_username,
                       CONCAT(
                           COALESCE(e.first_name, 'System'),
                           CASE 
                               WHEN e.middle_name IS NOT NULL AND e.middle_name != '' 
                               THEN CONCAT(' ', UPPER(LEFT(e.middle_name, 1)), '.')
                               ELSE ''
                           END,
                           ' ',
                           COALESCE(e.last_name, 'Admin')
                       ) as created_by_full_name,
                       ua.emp_id,
                       tt.transaction_code as ticket_txn_code,
                       tt.origin, tt.destination, tt.travel_date,
                       tt.base_amount, tt.service_fee, tt.discount_amount, tt.total_amount as ticket_total_amount,
                       tt.status as ticket_status,
                       pa.fullname as passenger_name
                FROM wallet_transactions wt
                LEFT JOIN provider_wallets pw ON wt.wallet_id = pw.wallet_id
                LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
                LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
                LEFT JOIN user_accounts ua ON wt.created_by = ua.user_id
                LEFT JOIN employees e ON ua.emp_id = e.emp_id
                LEFT JOIN ticket_transactions tt ON (wt.reference_table = 'ticket_transactions' AND wt.reference_id = tt.transaction_id)
                LEFT JOIN passenger_accounts pa ON tt.passenger_id = pa.passenger_id
                WHERE wt.wallet_txn_id = :txn_id";
        
        $txn = Database::fetch($sql, ['txn_id' => (int)$txnId]);
        
        if ($txn) {
            echo json_encode(['success' => true, 'data' => $txn]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        }
        return;
    }

    // List transactions with filters
    $where = ['1=1'];
    $params = [];

    // SUPER_ADMIN can see all transactions, others are restricted to their branch
    global $userRoleCode, $userBranchId;
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $where[] = 'pw.branch_id = :user_branch_id';
        $params['user_branch_id'] = $userBranchId;
    }

    if ($walletId) {
        $where[] = 'wt.wallet_id = :wallet_id';
        $params['wallet_id'] = (int)$walletId;
    }

    if ($txnType) {
        $where[] = 'wt.txn_type = :txn_type';
        $params['txn_type'] = $txnType;
    }

    if ($direction) {
        $where[] = 'wt.direction = :direction';
        $params['direction'] = $direction;
    }

    if ($search) {
        $where[] = '(wt.txn_code LIKE :search OR tp.provider_name LIKE :search OR bb.branch_name LIKE :search OR wt.remarks LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    if ($date) {
        $where[] = 'DATE(wt.created_at) = :date';
        $params['date'] = $date;
    }

    $whereClause = implode(' AND ', $where);

    // Get transactions (with ticket_transactions details when reference_table = 'ticket_transactions')
    $sql = "SELECT wt.*,
                   tp.provider_name,
                   bb.branch_name,
                   CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name,
                   ua.username as created_by_username,
                   CONCAT(
                       COALESCE(e.first_name, 'System'),
                       CASE 
                           WHEN e.middle_name IS NOT NULL AND e.middle_name != '' 
                           THEN CONCAT(' ', UPPER(LEFT(e.middle_name, 1)), '.')
                           ELSE ''
                       END,
                       ' ',
                       COALESCE(e.last_name, 'Admin')
                   ) as created_by_full_name,
                   tt.transaction_code as ticket_txn_code,
                   tt.origin, tt.destination, tt.travel_date,
                   tt.base_amount, tt.service_fee, tt.discount_amount, tt.total_amount as ticket_total_amount,
                   tt.status as ticket_status,
                   pa.fullname as passenger_name
            FROM wallet_transactions wt
            LEFT JOIN provider_wallets pw ON wt.wallet_id = pw.wallet_id
            LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
            LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
            LEFT JOIN user_accounts ua ON wt.created_by = ua.user_id
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            LEFT JOIN ticket_transactions tt ON (wt.reference_table = 'ticket_transactions' AND wt.reference_id = tt.transaction_id)
            LEFT JOIN passenger_accounts pa ON tt.passenger_id = pa.passenger_id
            WHERE $whereClause
            ORDER BY wt.created_at DESC
            LIMIT :limit OFFSET :offset";

    $params['limit'] = (int)$limit;
    $params['offset'] = (int)$offset;

    $transactions = Database::fetchAll($sql, $params);

    // Get stats
    $statsSql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN direction = 'IN' THEN amount ELSE 0 END) as totalInflow,
                    SUM(CASE WHEN direction = 'OUT' THEN amount ELSE 0 END) as totalOutflow,
                    SUM(CASE WHEN direction = 'IN' THEN amount ELSE -amount END) as netBalance
                 FROM wallet_transactions wt
                 WHERE $whereClause";

    $statsParams = $params;
    unset($statsParams['limit'], $statsParams['offset']);

    $stats = Database::fetch($statsSql, $statsParams);

    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'stats' => $stats
        ]
    ]);
}

/**
 * Handle POST requests - create new transaction
 */
function handlePost() {
    // Check CREATE_WALLET_TRANSACTION permission
    $user = Auth::user();
    $canCreate = ($user['role_code'] === 'SUPER_ADMIN');

    if (!$canCreate) {
        $canCreate = Auth::can('CREATE_WALLET_TRANSACTION');
    }

    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need CREATE_WALLET_TRANSACTION permission.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $walletId = $input['wallet_id'] ?? null;
    $txnType = $input['txn_type'] ?? null;
    $direction = $input['direction'] ?? null;
    $amount = $input['amount'] ?? null;
    $remarks = $input['remarks'] ?? null;
    $referenceTable = $input['reference_table'] ?? null;
    $referenceId = $input['reference_id'] ?? null;

    // Validate required fields
    if (!$walletId || !$txnType || !$direction || !$amount) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Validate amount
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
        return;
    }

    // Validate txn_type
    $validTxnTypes = ['TOPUP', 'SALE', 'REFUND', 'ADJUSTMENT'];
    if (!in_array($txnType, $validTxnTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid transaction type']);
        return;
    }

    // Validate direction
    if (!in_array($direction, ['IN', 'OUT'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid direction']);
        return;
    }

    // Get current wallet balance
    $wallet = Database::fetch(
        "SELECT current_balance FROM provider_wallets WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );

    if (!$wallet) {
        echo json_encode(['success' => false, 'error' => 'Wallet not found']);
        return;
    }

    $currentBalance = (float)$wallet['current_balance'];
    $balanceBefore = $currentBalance;

    // Calculate new balance
    if ($direction === 'IN') {
        $balanceAfter = $currentBalance + (float)$amount;
    } else {
        // Check if sufficient balance for OUT transactions
        if ($currentBalance < (float)$amount) {
            echo json_encode(['success' => false, 'error' => 'Insufficient wallet balance']);
            return;
        }
        $balanceAfter = $currentBalance - (float)$amount;
    }

    // Generate transaction code
    $txnCode = generateTransactionCode($txnType);

    // Start transaction
    Database::connection()->beginTransaction();

    try {
        // Insert transaction record
        $sql = "INSERT INTO wallet_transactions 
                (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, 
                 reference_table, reference_id, remarks, created_by, created_at)
                VALUES 
                (:wallet_id, :txn_code, :txn_type, :direction, :amount, :balance_before, :balance_after,
                 :reference_table, :reference_id, :remarks, :created_by, NOW())";

        $params = [
            'wallet_id' => (int)$walletId,
            'txn_code' => $txnCode,
            'txn_type' => $txnType,
            'direction' => $direction,
            'amount' => (float)$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId ? (int)$referenceId : null,
            'remarks' => $remarks,
            'created_by' => $user['user_id']
        ];

        Database::execute($sql, $params);

        // Update wallet balance
        Database::execute(
            "UPDATE provider_wallets SET current_balance = :new_balance WHERE wallet_id = :wallet_id",
            ['new_balance' => $balanceAfter, 'wallet_id' => (int)$walletId]
        );

        Database::connection()->commit();

        // Log activity
        logActivity(
            $user['user_id'],
            'CREATE_TRANSACTION',
            'WALLET_TRANSACTIONS',
            $txnCode,
            ['balance_before' => $balanceBefore],
            [
                'wallet_id' => $walletId,
                'txn_type' => $txnType,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'remarks' => $remarks
            ]
        );

        echo json_encode(['success' => true, 'message' => 'Transaction created successfully']);
    } catch (Exception $e) {
        Database::connection()->rollBack();
        throw $e;
    }
}

/**
 * Handle PUT requests - update transaction
 */
function handlePut() {
    echo json_encode(['success' => false, 'error' => 'Update not implemented']);
}

/**
 * Handle DELETE requests - delete transaction
 */
function handleDelete() {
    echo json_encode(['success' => false, 'error' => 'Delete not implemented']);
}

/**
 * Generate transaction code
 */
function generateTransactionCode($txnType) {
    $prefix = '';
    switch ($txnType) {
        case 'TOPUP':
            $prefix = 'TP';
            break;
        case 'SALE':
            $prefix = 'SL';
            break;
        case 'REFUND':
            $prefix = 'RF';
            break;
        case 'ADJUSTMENT':
            $prefix = 'AD';
            break;
        default:
            $prefix = 'TX';
    }

    $timestamp = date('YmdHis');
    $random = strtoupper(substr(md5(uniqid()), 0, 4));

    return $prefix . '-' . $timestamp . '-' . $random;
}
