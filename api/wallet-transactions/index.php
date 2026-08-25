<?php
/**
 * Wallet Transactions API Endpoint
 * Handles CRUD operations for wallet transaction management
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

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

// Read system settings for wallet overdraft allowance
$sysSettings = Database::fetch("SELECT pos_allow_insufficient_wallet FROM system_settings WHERE setting_id = 1") ?? [];
$allowWalletOverdraft = !empty($sysSettings['pos_allow_insufficient_wallet']);

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
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

function canAccessBranch($branchId) {
    global $user;
    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    return $allowedBranchIds === null
        || ($branchId && in_array((int) $branchId, $allowedBranchIds, true));
}

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
    $operatingProviderId = $_GET['operating_provider_id'] ?? null;
    $txnType = $_GET['txn_type'] ?? null;
    $direction = $_GET['direction'] ?? null;
    $search = $_GET['search'] ?? '';
    $date = $_GET['date'] ?? null;
    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;

    // Decode IDs if provided
    if ($txnId) {
        $decodedTxnId = IdEncoder::decode($txnId);
        if ($decodedTxnId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid transaction ID']);
            return;
        }
        $txnId = $decodedTxnId;
    }
    if ($walletId) {
        $decodedWalletId = IdEncoder::decode($walletId);
        if ($decodedWalletId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid wallet ID']);
            return;
        }
        $walletId = $decodedWalletId;
    }
    if ($operatingProviderId && !ctype_digit((string)$operatingProviderId)) {
        $decodedOperatingProviderId = IdEncoder::decode($operatingProviderId);
        if ($decodedOperatingProviderId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid operating provider ID']);
            return;
        }
        $operatingProviderId = $decodedOperatingProviderId;
    }

    // Get single transaction
    if ($txnId) {
        $sql = "SELECT wt.*,
                       pw.branch_id,
                       tp.provider_name as wallet_provider_name,
                       bb.branch_name,
                       CONCAT(tp.provider_name,
                              IF(pwv.variant_name IS NOT NULL, CONCAT(' - ', pwv.variant_name), ''),
                              ' - ', bb.branch_name) as wallet_name,
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
                       tt.provider_id as operating_provider_id,
                       tp_op.provider_name as operating_provider_name,
                       tt.origin, tt.destination, tt.travel_date,
                       tt.base_amount, tt.service_fee, tt.discount_amount, tt.total_amount as ticket_total_amount,
                       tt.status as ticket_status,
                       pa.fullname as passenger_name,
                       pv.variant_id as ticket_variant_id, pv.variant_code as ticket_variant_code,
                       pv.variant_name as ticket_variant_name, pv.display_color as ticket_variant_color,
                       pwv.variant_id as wallet_variant_id, pwv.variant_code as wallet_variant_code,
                       pwv.variant_name as wallet_variant_name, pwv.display_color as wallet_variant_color,
                       COALESCE(pv.variant_name, pwv.variant_name) as variant_name,
                       COALESCE(pv.variant_code, pwv.variant_code) as variant_code,
                       COALESCE(pv.display_color, pwv.display_color) as variant_color
                FROM wallet_transactions wt
                LEFT JOIN provider_wallets pw ON wt.wallet_id = pw.wallet_id
                LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
                LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
                LEFT JOIN provider_ticket_variants pwv ON pw.variant_id = pwv.variant_id
                LEFT JOIN user_accounts ua ON wt.created_by = ua.user_id
                LEFT JOIN employees e ON ua.emp_id = e.emp_id
                LEFT JOIN ticket_transactions tt ON (wt.reference_table = 'ticket_transactions' AND wt.reference_id = tt.transaction_id)
                LEFT JOIN ticket_providers tp_op ON tt.provider_id = tp_op.provider_id
                LEFT JOIN passenger_accounts pa ON tt.passenger_id = pa.passenger_id
                LEFT JOIN provider_ticket_variants pv ON tt.variant_id = pv.variant_id
                WHERE wt.wallet_txn_id = :txn_id";

        $txn = Database::fetch($sql, ['txn_id' => (int)$txnId]);

        if (!$txn) {
            echo json_encode(['success' => false, 'error' => 'Transaction not found']);
            return;
        }

        if (!canAccessBranch($txn['branch_id'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied: transaction does not belong to your branch']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $txn]);
        return;
    }

    // List transactions with filters
    $where = ['1=1'];
    $params = [];

    // SUPER_ADMIN can see all transactions, others are restricted to their branch
    global $user;
    PosAccess::applyBranchScope($where, $params, 'pw.branch_id', $user, 'wallet_transaction_branch');

    if ($walletId) {
        $where[] = 'wt.wallet_id = :wallet_id';
        $params['wallet_id'] = (int)$walletId;
    }

    if ($operatingProviderId) {
        $where[] = 'tt.provider_id = :operating_provider_id';
        $params['operating_provider_id'] = (int)$operatingProviderId;
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
        $where[] = '(wt.txn_code LIKE :search OR tp.provider_name LIKE :search OR tp_op.provider_name LIKE :search OR tt.transaction_code LIKE :search OR pwv.variant_code LIKE :search OR pwv.variant_name LIKE :search OR pv.variant_code LIKE :search OR pv.variant_name LIKE :search OR bb.branch_name LIKE :search OR wt.remarks LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    if ($date) {
        $where[] = 'DATE(wt.created_at) = :date';
        $params['date'] = $date;
    }

    $whereClause = implode(' AND ', $where);

    // Get transactions (with ticket_transactions details when reference_table = 'ticket_transactions')
    $sql = "SELECT wt.*,
                   tp.provider_name as wallet_provider_name,
                   bb.branch_name,
                   CONCAT(tp.provider_name,
                          IF(pwv.variant_name IS NOT NULL, CONCAT(' - ', pwv.variant_name), ''),
                          ' - ', bb.branch_name) as wallet_name,
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
                   tt.provider_id as operating_provider_id,
                   tp_op.provider_name as operating_provider_name,
                   tt.origin, tt.destination, tt.travel_date,
                   tt.base_amount, tt.service_fee, tt.discount_amount, tt.total_amount as ticket_total_amount,
                   tt.status as ticket_status,
                   pa.fullname as passenger_name,
                   pv.variant_id as ticket_variant_id, pv.variant_code as ticket_variant_code,
                   pv.variant_name as ticket_variant_name, pv.display_color as ticket_variant_color,
                   pwv.variant_id as wallet_variant_id, pwv.variant_code as wallet_variant_code,
                   pwv.variant_name as wallet_variant_name, pwv.display_color as wallet_variant_color,
                   COALESCE(pv.variant_name, pwv.variant_name) as variant_name,
                   COALESCE(pv.variant_code, pwv.variant_code) as variant_code,
                   COALESCE(pv.display_color, pwv.display_color) as variant_color
            FROM wallet_transactions wt
            LEFT JOIN provider_wallets pw ON wt.wallet_id = pw.wallet_id
            LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
            LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
            LEFT JOIN provider_ticket_variants pwv ON pw.variant_id = pwv.variant_id
            LEFT JOIN user_accounts ua ON wt.created_by = ua.user_id
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            LEFT JOIN ticket_transactions tt ON (wt.reference_table = 'ticket_transactions' AND wt.reference_id = tt.transaction_id)
            LEFT JOIN ticket_providers tp_op ON tt.provider_id = tp_op.provider_id
            LEFT JOIN passenger_accounts pa ON tt.passenger_id = pa.passenger_id
            LEFT JOIN provider_ticket_variants pv ON tt.variant_id = pv.variant_id
            WHERE $whereClause
            ORDER BY wt.created_at DESC
            LIMIT :limit OFFSET :offset";

    $params['limit'] = (int)$limit;
    $params['offset'] = (int)$offset;

    $transactions = Database::fetchAll($sql, $params);

    // Get stats
    $statsSql = "SELECT
                    SUM(CASE WHEN pw.variant_id IS NULL THEN 1 ELSE 0 END) as total,
                    SUM(CASE WHEN pw.variant_id IS NULL AND direction = 'IN' THEN amount ELSE 0 END) as totalInflow,
                    SUM(CASE WHEN pw.variant_id IS NULL AND direction = 'OUT' THEN amount ELSE 0 END) as totalOutflow,
                    SUM(CASE WHEN pw.variant_id IS NULL AND direction = 'IN' THEN amount
                             WHEN pw.variant_id IS NULL AND direction = 'OUT' THEN -amount
                             ELSE 0 END) as netBalance
                 FROM wallet_transactions wt
                 LEFT JOIN provider_wallets pw ON wt.wallet_id = pw.wallet_id
                 LEFT JOIN ticket_transactions tt ON (wt.reference_table = 'ticket_transactions' AND wt.reference_id = tt.transaction_id)
                 LEFT JOIN provider_ticket_variants pv ON tt.variant_id = pv.variant_id
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

    // Sanitize remarks to prevent XSS
    if ($remarks) {
        $remarks = htmlspecialchars(strip_tags(trim($remarks)), ENT_QUOTES, 'UTF-8');
    }

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
    if (in_array($txnType, ['SALE', 'REFUND'], true) && (!$referenceTable || !$referenceId)) {
        echo json_encode(['success' => false, 'error' => 'SALE and REFUND movements require an original source reference.']);
        return;
    }
    if ($referenceTable !== null && !preg_match('/^[A-Za-z0-9_]{1,100}$/', (string) $referenceTable)) {
        echo json_encode(['success' => false, 'error' => 'Invalid source reference table.']);
        return;
    }

    $walletAccess = Database::fetch(
        "SELECT wallet_id, branch_id, variant_id
         FROM provider_wallets
         WHERE wallet_id = :wallet_id AND status = 'active'",
        ['wallet_id' => (int) $walletId]
    );

    if (!$walletAccess) {
        echo json_encode(['success' => false, 'error' => 'Wallet not found or inactive']);
        return;
    }

    if (!canAccessBranch($walletAccess['branch_id'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied: wallet does not belong to your branch']);
        return;
    }

    if (!empty($walletAccess['variant_id'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'This wallet represents ticket stock. Use Ticket Stock Balances or Stock Requests to top up quantities; wallet transactions are monetary only.'
        ]);
        return;
    }

    try {
        $movement = BalanceLedgerService::walletMovement(
            (int) $walletId,
            strtoupper((string) $txnType),
            strtoupper((string) $direction),
            (float) $amount,
            $referenceTable,
            $referenceId ? (int) $referenceId : null,
            $remarks,
            (int) $user['user_id']
        );

        $txnCode = $movement['txn_code'];
        $balanceBefore = $movement['balance_before'];
        $balanceAfter = $movement['balance_after'];
        $wallet = $walletAccess;

        PusherService::triggerBranch((int) $wallet['branch_id'], 'wallet.updated', [
            'wallet_id' => (int) $walletId,
            'branch_id' => (int) $wallet['branch_id'],
            'current_balance' => (float) $balanceAfter
        ]);

        logActivity(
            $user['user_id'],
            'CREATE_TRANSACTION',
            'WALLET_TRANSACTIONS',
            $txnCode,
            ['balance_before' => $balanceBefore],
            [
                'wallet_id' => $walletId,
                'txn_type' => strtoupper((string) $txnType),
                'direction' => strtoupper((string) $direction),
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'remarks' => $remarks
            ]
        );

        // Notification triggers
        // Get configurable threshold from system settings
        $thresholdSetting = Database::fetch(
            "SELECT setting_value FROM system_notification_settings WHERE setting_key = 'wallet_transaction_threshold'"
        );
        $largeTransactionThreshold = $thresholdSetting ? (float)$thresholdSetting['setting_value'] : 10000;

        // Get wallet details for threshold check
        $wallet = Database::fetch(
            "SELECT pw.*, tp.provider_name, bb.branch_name
             FROM provider_wallets pw
             LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
             LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
             WHERE pw.wallet_id = :wallet_id",
            ['wallet_id' => (int)$walletId]
        );

        // Low balance alert - notify wallet owner and SUPER_ADMIN
        if ($wallet) {
            $lowBalanceThreshold = $wallet['min_balance'] ?? 1000; // Use wallet's threshold or default to 1000

            if ($balanceAfter < $lowBalanceThreshold && $direction === 'OUT') {
                // Notify wallet owner / branch admin users (SUPER_ADMIN handled separately below)
                $providerAdmins = Database::fetchAll(
                    "SELECT ua.user_id
                     FROM user_accounts ua
                     JOIN user_roles r ON ua.role_id = r.role_id
                     WHERE ua.branch_id = :branch_id
                     AND r.role_code = 'ADMIN'
                     AND ua.status = 'active'",
                    ['branch_id' => $wallet['branch_id']]
                );

                foreach ($providerAdmins as $admin) {
                    NotificationService::createFromTemplate('wallet_low', $admin['user_id'], [
                        'wallet_id' => $wallet['provider_name'] . ' - ' . $wallet['branch_name'],
                        'balance' => number_format($balanceAfter, 2)
                    ]);
                }

                // Notify SUPER_ADMIN
                $superAdmins = Database::fetchAll(
                    "SELECT ua.user_id FROM user_accounts ua
                     JOIN user_roles r ON ua.role_id = r.role_id
                     WHERE r.role_code = 'SUPER_ADMIN' AND ua.status = 'active'"
                );

                foreach ($superAdmins as $superAdmin) {
                    NotificationService::createFromTemplate('wallet_low', $superAdmin['user_id'], [
                        'wallet_id' => $wallet['provider_name'] . ' - ' . $wallet['branch_name'],
                        'balance' => number_format($balanceAfter, 2)
                    ]);
                }
            }
        }

        // Large transaction alert - notify SUPER_ADMIN and ADMIN
        if ((float)$amount >= $largeTransactionThreshold && $wallet) {
            $adminUsers = Database::fetchAll(
                "SELECT ua.user_id FROM user_accounts ua
                 JOIN user_roles r ON ua.role_id = r.role_id
                 WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND ua.status = 'active'"
            );

            foreach ($adminUsers as $admin) {
                NotificationService::createFromTemplate('wallet_transaction', $admin['user_id'], [
                    'transaction_type' => $txnType,
                    'amount' => number_format($amount, 2),
                    'wallet_id' => $wallet['provider_name'] . ' - ' . $wallet['branch_name']
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Transaction created successfully',
            'data' => [
                'wallet_id' => (int)$walletId,
                'current_balance' => (float)$balanceAfter
            ],
            'csrf_token' => SecurityHelper::generateCSRFToken()
        ]);
    } catch (InvalidArgumentException $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }
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
