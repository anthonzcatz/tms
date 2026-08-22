<?php
/**
 * Bank Transactions API Endpoint
 * Handles bank transaction history queries
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

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

    // Decode bank_account_id if provided
    if ($bankAccountId) {
        $decodedBankAccountId = IdEncoder::decode($bankAccountId);
        if ($decodedBankAccountId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid bank account ID']);
            return;
        }
        $bankAccountId = $decodedBankAccountId;
    }

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
    if ($txnType && in_array($txnType, ['RECEIPT', 'DEPOSIT', 'DISBURSEMENT', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT', 'REFUND'])) {
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
    
    try {
        $movement = BalanceLedgerService::bankMovement(
            $bankAccountId,
            'ADJUSTMENT',
            $direction,
            $amount,
            'bank_accounts',
            $bankAccountId,
            $remarks,
            (int) $user['user_id'],
            null,
            null,
            false,
            $remarks
        );

        logActivity(
            $user['user_id'],
            $direction === 'IN' ? 'BANK_BALANCE_INCREASE' : 'BANK_BALANCE_DECREASE',
            'BANK_ACCOUNTS',
            $movement['txn_code'],
            ['balance_before' => $movement['balance_before']],
            [
                'balance_after' => $movement['balance_after'],
                'adjustment_amount' => $amount,
                'direction' => $direction,
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Balance adjustment recorded successfully',
            'data' => [
                'txn_code' => $movement['txn_code'],
                'balance_before' => $movement['balance_before'],
                'balance_after' => $movement['balance_after'],
            ]
        ]);
    } catch (Throwable $e) {
        error_log("Balance Adjustment Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

