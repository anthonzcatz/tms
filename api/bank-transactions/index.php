<?php
/**
 * Bank Transactions API Endpoint
 * Handles bank transaction history queries
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';

// Authenticate user
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
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
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Bank Transactions API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage()]);
}

/**
 * Handle GET requests - list bank transactions
 */
function handleGet() {
    $bankAccountId = $_GET['bank_account_id'] ?? null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $txnType = $_GET['txn_type'] ?? null;
    $direction = $_GET['direction'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    $user = Auth::user();
    $userRoleCode = $user['role_code'] ?? '';
    $userBranchId = $user['branch_id'] ?? null;
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    // Filter by bank account
    if ($bankAccountId) {
        $where[] = "bt.bank_account_id = :bank_account_id";
        $params['bank_account_id'] = (int)$bankAccountId;
    }
    
    // Non-SUPER_ADMIN can only see transactions for their branch's bank accounts
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $where[] = "ba.branch_id = :user_branch_id";
        $params['user_branch_id'] = $userBranchId;
    }
    
    // Filter by transaction type
    if ($txnType && in_array($txnType, ['RECEIPT', 'DISBURSEMENT', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT', 'REFUND'])) {
        $where[] = "bt.txn_type = :txn_type";
        $params['txn_type'] = $txnType;
    }
    
    // Filter by direction
    if ($direction && in_array($direction, ['IN', 'OUT'])) {
        $where[] = "bt.direction = :direction";
        $params['direction'] = $direction;
    }
    
    // Filter by date range
    if ($dateFrom) {
        $where[] = "DATE(bt.created_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "DATE(bt.created_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM bank_transactions bt
        INNER JOIN bank_accounts ba ON bt.bank_account_id = ba.bank_account_id
        $whereClause
    ";
    
    $countResult = Database::fetch($countSql, $params);
    $total = $countResult['total'] ?? 0;
    $totalPages = ceil($total / $limit);
    
    // Get transactions with pagination
    $sql = "
        SELECT
            bt.bank_txn_id,
            bt.bank_account_id,
            ba.account_name,
            ba.account_number,
            ba.bank_name,
            bt.txn_code,
            bt.txn_type,
            bt.direction,
            bt.amount,
            bt.balance_before,
            bt.balance_after,
            bt.reference_table,
            bt.reference_id,
            bt.remarks,
            bt.created_by,
            bt.created_at,
            CONCAT_WS(' ', e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(UPPER(LEFT(e.middle_name, 1)), '.'), ''), e.last_name) AS created_by_name
        FROM bank_transactions bt
        INNER JOIN bank_accounts ba ON bt.bank_account_id = ba.bank_account_id
        LEFT JOIN user_accounts ua ON bt.created_by = ua.user_id
        LEFT JOIN employees e ON ua.emp_id = e.emp_id
        $whereClause
        ORDER BY bt.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $limit;
    $params['offset'] = $offset;
    
    $transactions = Database::fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]
    ]);
}

/**
 * Handle POST requests - create balance adjustment
 */
function handlePost() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['bank_account_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bank account ID is required']);
        return;
    }
    
    if (empty($data['amount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Amount is required']);
        return;
    }
    
    if (empty($data['direction']) || !in_array($data['direction'], ['IN', 'OUT'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid direction (IN/OUT) is required']);
        return;
    }
    
    $user = Auth::user();
    $bankAccountId = (int)$data['bank_account_id'];
    $amount = (float)$data['amount'];
    $direction = $data['direction'];
    $remarks = $data['remarks'] ?? null;
    
    // Get current balance
    $bankAccount = Database::fetch(
        "SELECT current_balance, account_name, bank_name 
         FROM bank_accounts 
         WHERE bank_account_id = :bank_account_id",
        ['bank_account_id' => $bankAccountId]
    );
    
    if (!$bankAccount) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bank account not found']);
        return;
    }
    
    $balanceBefore = (float)$bankAccount['current_balance'];
    
    // Calculate new balance
    $balanceAfter = $direction === 'IN' 
        ? $balanceBefore + $amount 
        : $balanceBefore - $amount;
    
    if ($balanceAfter < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Insufficient balance for OUT adjustment']);
        return;
    }
    
    // Generate transaction code
    $txnCode = 'ADJ-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    try {
        // Begin transaction
        Database::execute("START TRANSACTION");
        
        // Insert bank transaction
        Database::execute(
            "INSERT INTO bank_transactions (
                bank_account_id, txn_code, txn_type, direction, amount,
                balance_before, balance_after, remarks, created_by
            ) VALUES (
                :bank_account_id, :txn_code, 'ADJUSTMENT', :direction, :amount,
                :balance_before, :balance_after, :remarks, :created_by
            )",
            [
                'bank_account_id' => $bankAccountId,
                'txn_code' => $txnCode,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'remarks' => $remarks,
                'created_by' => $user['user_id']
            ]
        );
        
        // Update bank account balance
        Database::execute(
            "UPDATE bank_accounts SET current_balance = :balance_after 
             WHERE bank_account_id = :bank_account_id",
            [
                'balance_after' => $balanceAfter,
                'bank_account_id' => $bankAccountId
            ]
        );
        
        // Log activity
        $logAction = $direction === 'IN' ? 'BANK_BALANCE_INCREASE' : 'BANK_BALANCE_DECREASE';
        $referenceCode = $bankAccount['bank_name'] . ' - ' . $bankAccount['account_name'];
        
        Database::execute(
            "INSERT INTO activity_logs (
                user_id, action, module_name, reference_code, ip_address,
                old_value, new_value, created_at
            ) VALUES (
                :user_id, :action, :module_name, :reference_code, :ip_address,
                :old_value, :new_value, NOW()
            )",
            [
                'user_id' => $user['user_id'],
                'action' => $logAction,
                'module_name' => 'Bank Accounts',
                'reference_code' => $referenceCode,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'old_value' => json_encode(['balance_before' => $balanceBefore]),
                'new_value' => json_encode([
                    'balance_after' => $balanceAfter,
                    'adjustment_amount' => $amount,
                    'direction' => $direction,
                    'txn_code' => $txnCode
                ])
            ]
        );
        
        Database::execute("COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => 'Balance adjustment recorded successfully',
            'data' => [
                'txn_code' => $txnCode,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter
            ]
        ]);
    } catch (Exception $e) {
        Database::execute("ROLLBACK");
        error_log("Balance Adjustment Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to record balance adjustment']);
    }
}

