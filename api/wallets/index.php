<?php
/**
 * Wallets API Endpoint
 * Handles wallet management operations
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

// Helper function for logging activity
function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null; // Can be enhanced to track device ID if needed
    $now = date('Y-m-d H:i:s');
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, :created_at)",
        [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'action' => $action,
            'module_name' => $moduleName,
            'reference_code' => $referenceCode,
            'ip_address' => $ipAddress,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'created_at' => $now
        ]
    );
}

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission - SUPER_ADMIN or users with VIEW_WALLETS / VIEW_WALLET_TRANSACTIONS permission
$user = Auth::user();
$canView = ($user['role_code'] === 'SUPER_ADMIN');

if (!$canView) {
    $canView = Auth::can('VIEW_WALLETS') || Auth::can('VIEW_WALLET_TRANSACTIONS') || Auth::canAccessModule('admin/pos/');
}

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need VIEW_WALLETS or VIEW_WALLET_TRANSACTIONS permission to access this resource.']);
    exit;
}

// Get user branch for filtering
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

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
    error_log("Wallets API Error: " . $e->getMessage());
    error_log("Wallets API Trace: " . $e->getTraceAsString());
    error_log("Wallets API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list wallets or get stats
 */
function handleGet() {
    $walletId = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;

    // Decode wallet_id if encrypted
    if ($walletId && !is_numeric($walletId)) {
        $decodedId = IdEncoder::decode($walletId);
        if ($decodedId === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid wallet ID']);
            exit;
        }
        $walletId = $decodedId;
    }

    // Get wallet stats
    if ($action === 'stats') {
        $branchFilter = "";
        $params = [];

        // SUPER_ADMIN can see all wallets, others are restricted to their branch
        global $userRoleCode, $userBranchId;
        if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
            $userBranchIds = array_filter(array_map('intval', explode(',', $userBranchId)));
            if (!empty($userBranchIds)) {
                $branchPlaceholders = [];
                foreach ($userBranchIds as $i => $branchId) {
                    $branchPlaceholders[] = ':user_branch_' . $i;
                    $params['user_branch_' . $i] = $branchId;
                }
                $branchFilter = "WHERE pw.branch_id IN (" . implode(',', $branchPlaceholders) . ")";
            }
        }

        $sql = "SELECT 
                    COUNT(*) as total_wallets,
                    SUM(CASE WHEN pw.status = 'active' THEN 1 ELSE 0 END) as active_wallets,
                    SUM(CASE WHEN pw.status = 'inactive' THEN 1 ELSE 0 END) as inactive_wallets,
                    COALESCE(SUM(pw.current_balance), 0) as total_balance
                FROM provider_wallets pw
                $branchFilter";

        $stats = Database::fetch($sql, $params);

        echo json_encode([
            'success' => true,
            'data' => [
                'total_wallets' => (int)$stats['total_wallets'],
                'active_wallets' => (int)$stats['active_wallets'],
                'inactive_wallets' => (int)$stats['inactive_wallets'],
                'total_balance' => (float)$stats['total_balance']
            ]
        ]);
        return;
    }

    // Get single wallet
    if ($walletId) {
        $sql = "SELECT pw.*,
                       tp.provider_code,
                       tp.provider_name,
                       tp.provider_type,
                       tp.status as provider_status,
                       tp.parent_provider_id,
                       ptp.provider_name as parent_provider_name,
                       tp.created_at as provider_created_at,
                       bb.branch_name,
                       bb.branch_code,
                       pv.variant_id as variant_id,
                       pv.variant_code as variant_code,
                       pv.variant_name as variant_name,
                       pv.display_color as variant_color,
                       CONCAT(tp.provider_name,
                              IF(pv.variant_name IS NOT NULL, CONCAT(' - ', pv.variant_name), ''),
                              ' - ', bb.branch_name) as wallet_name,
                       (
                           SELECT GROUP_CONCAT(child.provider_name ORDER BY child.provider_name SEPARATOR ', ')
                           FROM ticket_providers child
                           WHERE child.parent_provider_id = pw.provider_id
                             AND child.status = 'active'
                       ) as child_provider_names,
                       (
                           SELECT GROUP_CONCAT(DISTINCT v.variant_name ORDER BY v.variant_name SEPARATOR ', ')
                           FROM provider_ticket_variants v
                           WHERE v.provider_id = pw.provider_id
                             AND v.deleted_at IS NULL
                       ) as variant_names,
                       (
                           SELECT COUNT(*)
                           FROM provider_ticket_variants v
                           WHERE v.provider_id = pw.provider_id
                             AND v.deleted_at IS NULL
                       ) as variant_count
                FROM provider_wallets pw
                LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
                LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
                LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
                LEFT JOIN provider_ticket_variants pv ON pw.variant_id = pv.variant_id
                WHERE pw.wallet_id = :wallet_id";

        $wallet = Database::fetch($sql, ['wallet_id' => (int)$walletId]);

        if ($wallet) {
            echo json_encode(['success' => true, 'data' => $wallet]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Wallet not found']);
        }
        return;
    }

    // List all wallets
    $branchFilter = "";
    $params = [];

    // SUPER_ADMIN can see all wallets, others are restricted to their branch
    global $userRoleCode, $userBranchId, $user;
    
    // Check if user has an active cashier session - use session's branch_id if available
    $sessionBranchId = null;
    if ($userRoleCode === 'CASHIER' && $user['user_id']) {
        $activeSession = Database::fetch(
            "SELECT branch_id FROM cashier_sessions 
             WHERE cashier_user_id = :user_id 
             AND status = 'OPEN' 
             AND ended_at IS NULL 
             ORDER BY started_at DESC 
             LIMIT 1",
            ['user_id' => $user['user_id']]
        );
        if ($activeSession && $activeSession['branch_id']) {
            $sessionBranchId = $activeSession['branch_id'];
        }
    }
    
    // Use session branch_id if active session exists, otherwise use user's assigned branch
    $effectiveBranchId = $sessionBranchId ?: $userBranchId;
    $allBranches = isset($_GET['all_branches']) && $_GET['all_branches'] == '1';

    if ($userRoleCode !== 'SUPER_ADMIN') {
        // When all_branches is requested, use all branches assigned to the user;
        // otherwise use the single effective branch (active session or default).
        $allowedBranchIdStr = $allBranches ? $userBranchId : $effectiveBranchId;

        if ($allowedBranchIdStr) {
            // Parse comma-separated branch IDs
            $userBranchIds = array_map('intval', explode(',', $allowedBranchIdStr));
            $userBranchIds = array_filter($userBranchIds);

            if (!empty($userBranchIds)) {
                $branchPlaceholders = [];
                foreach ($userBranchIds as $i => $branchId) {
                    $branchPlaceholders[] = ':user_branch_' . $i;
                    $params['user_branch_' . $i] = $branchId;
                }
                $branchFilter = "WHERE pw.branch_id IN (" . implode(',', $branchPlaceholders) . ")";
            }
        }
    }

    // Filter by provider_id if provided
    $providerId = $_GET['provider_id'] ?? null;
    if ($providerId) {
        // Decode provider_id if encrypted
        if (!is_numeric($providerId)) {
            $decodedId = IdEncoder::decode($providerId);
            if ($decodedId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid provider ID']);
                exit;
            }
            $providerId = $decodedId;
        }
        $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "pw.provider_id = :provider_id";
        $params['provider_id'] = (int)$providerId;
    }

    // Filter by variant_id if provided
    $variantId = $_GET['variant_id'] ?? null;
    if ($variantId) {
        // Decode variant_id if encrypted
        if (!is_numeric($variantId)) {
            $decodedId = IdEncoder::decode($variantId);
            if ($decodedId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid variant ID']);
                exit;
            }
            $variantId = $decodedId;
        }
        $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "pw.variant_id = :variant_id";
        $params['variant_id'] = (int)$variantId;
    }

    // Filter by branch_id if provided
    $branchId = $_GET['branch_id'] ?? null;
    if ($branchId) {
        // Decode branch_id if encrypted
        if (!is_numeric($branchId)) {
            $decodedId = IdEncoder::decode($branchId);
            if ($decodedId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
                exit;
            }
            $branchId = $decodedId;
        }
        $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "pw.branch_id = :branch_id";
        $params['branch_id'] = (int)$branchId;
    }

    // Resolve wallet for an operating provider (provider_id + branch_id + optional variant_id)
    // Returns the actual wallet (parent wallet if child has none)
    $resolve = $_GET['resolve'] ?? null;
    if ($resolve === '1' && $providerId) {
        $resolveBranchId = $branchId ? (int)$branchId : (int)$effectiveBranchId;
        if (!$resolveBranchId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Branch ID is required to resolve wallet']);
            exit;
        }

        $resolveVariantId = isset($_GET['variant_id']) ? $_GET['variant_id'] : null;
        if ($resolveVariantId && !is_numeric($resolveVariantId)) {
            $decodedVariantId = IdEncoder::decode($resolveVariantId);
            if ($decodedVariantId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid variant ID']);
                exit;
            }
            $resolveVariantId = $decodedVariantId;
        }
        $resolveVariantId = $resolveVariantId ? (int)$resolveVariantId : null;
        $resolvedWallet = WalletResolver::resolve((int)$providerId, $resolveBranchId, $resolveVariantId);

        // If not found in the requested/effective branch, try all accessible branches.
        if (!$resolvedWallet) {
            $fallbackBranchIds = [];
            if ($userRoleCode === 'SUPER_ADMIN') {
                $allBranchRows = Database::fetchAll("SELECT branch_id FROM business_branches WHERE status = 'active' OR status IS NULL");
                $fallbackBranchIds = array_column($allBranchRows, 'branch_id');
            } elseif (!empty($userBranchId)) {
                $fallbackBranchIds = array_filter(array_map('intval', explode(',', $userBranchId)));
            }

            foreach ($fallbackBranchIds as $fallbackBranchId) {
                if ((int)$fallbackBranchId === (int)$resolveBranchId) {
                    continue; // Already tried
                }
                $resolvedWallet = WalletResolver::resolve((int)$providerId, (int)$fallbackBranchId, $resolveVariantId);
                if ($resolvedWallet) {
                    break;
                }
            }
        }

        if (!$resolvedWallet) {
            echo json_encode(['success' => false, 'error' => 'No active wallet found for this provider, branch and variant']);
            exit;
        }

        $walletDetails = Database::fetch(
            "SELECT pw.*,
                    tp.provider_name,
                    bb.branch_name,
                    pv.variant_id as variant_id,
                    pv.variant_code as variant_code,
                    pv.variant_name as variant_name,
                    pv.display_color as variant_color,
                    CONCAT(tp.provider_name,
                           IF(pv.variant_name IS NOT NULL, CONCAT(' - ', pv.variant_name), ''),
                           ' - ', bb.branch_name) as wallet_name
             FROM provider_wallets pw
             LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
             LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
             LEFT JOIN provider_ticket_variants pv ON pw.variant_id = pv.variant_id
             WHERE pw.wallet_id = :wallet_id",
            ['wallet_id' => (int)$resolvedWallet['wallet_id']]
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'resolved' => true,
                'operating_provider_id' => (int)$providerId,
                'variant_id' => $resolveVariantId,
                'wallet' => $walletDetails
            ]
        ]);
        exit;
    }

    // Filter by transport type for cashiers with restrictions
    // Fetch user data directly to get has_restricted_transport
    $userData = Database::fetch(
        "SELECT has_restricted_transport FROM user_accounts WHERE user_id = :user_id",
        ['user_id' => $user['user_id']]
    );
    $hasRestrictedTransport = ($userData['has_restricted_transport'] ?? 0) == 1;
    
    if ($userRoleCode === 'CASHIER' && $hasRestrictedTransport) {
        // Get cashier's transport assignments
        $transportAssignments = Database::fetchAll(
            "SELECT cta.provider_id, cta.transport_type
             FROM cashier_transport_assignments cta
             WHERE cta.user_id = :user_id",
            ['user_id' => $user['user_id']]
        );

        if ($transportAssignments) {
            $allowedProviderIds = [];
            $allowedTransportTypes = [];

            foreach ($transportAssignments as $assignment) {
                if ($assignment['provider_id']) {
                    $allowedProviderIds[] = (int)$assignment['provider_id'];
                }
                if ($assignment['transport_type']) {
                    $allowedTransportTypes[] = $assignment['transport_type'];
                }
            }

            // Build filter for specific providers or transport types
            $transportFilter = "";
            if (!empty($allowedProviderIds)) {
                $providerPlaceholders = [];
                foreach ($allowedProviderIds as $i => $pid) {
                    $providerPlaceholders[] = ':transport_provider_' . $i;
                    $params['transport_provider_' . $i] = $pid;
                }
                $transportFilter = "pw.provider_id IN (" . implode(',', $providerPlaceholders) . ")";
            } elseif (!empty($allowedTransportTypes)) {
                $typePlaceholders = [];
                foreach ($allowedTransportTypes as $i => $type) {
                    $typePlaceholders[] = ':transport_type_' . $i;
                    $params['transport_type_' . $i] = $type;
                }
                $transportFilter = "tp.provider_type IN (" . implode(',', $typePlaceholders) . ")";
            }

            if ($transportFilter) {
                $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . $transportFilter;
            } else {
                // No assignments - show no wallets
                $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "1 = 0";
            }
        }
    }

    $sql = "SELECT pw.*,
                   tp.provider_id as ticket_provider_id,
                   tp.provider_name,
                   tp.provider_type,
                   tp.parent_provider_id,
                   ptp.provider_name as parent_provider_name,
                   bb.branch_name,
                   pv.variant_id as variant_id,
                   pv.variant_code as variant_code,
                   pv.variant_name as variant_name,
                   pv.display_color as variant_color,
                   CONCAT(tp.provider_name,
                          IF(pv.variant_name IS NOT NULL, CONCAT(' - ', pv.variant_name), ''),
                          ' - ', bb.branch_name) as wallet_name
            FROM provider_wallets pw
            LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
            LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
            LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
            LEFT JOIN provider_ticket_variants pv ON pw.variant_id = pv.variant_id
            $branchFilter
            ORDER BY tp.provider_name, pv.variant_name, bb.branch_name";

    $wallets = Database::fetchAll($sql, $params);

    echo json_encode([
        'success' => true,
        'data' => [
            'wallets' => $wallets
        ]
    ]);
}

/**
 * Handle POST requests - create wallet
 */
function handlePost() {
    global $user, $userRoleCode;
    
    // Check permission
    $canCreate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canCreate) {
        $canCreate = Auth::can('CREATE_WALLET');
    }
    
    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need CREATE_WALLET permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $providerId = $input['provider_id'] ?? null;
    $branchId = $input['branch_id'] ?? null;
    $variantId = $input['variant_id'] ?? null;
    $initialBalance = floatval($input['initial_balance'] ?? 0);
    $minBalance = floatval($input['min_balance'] ?? 1000);
    $status = $input['status'] ?? 'active';

    // Decode provider_id if encrypted
    if ($providerId && !is_numeric($providerId)) {
        $decodedId = IdEncoder::decode($providerId);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid provider ID']);
            return;
        }
        $providerId = $decodedId;
    }

    // Decode branch_id if encrypted
    if ($branchId && !is_numeric($branchId)) {
        $decodedId = IdEncoder::decode($branchId);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            return;
        }
        $branchId = $decodedId;
    }

    // Decode variant_id if encrypted
    if ($variantId && !is_numeric($variantId)) {
        $decodedId = IdEncoder::decode($variantId);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid variant ID']);
            return;
        }
        $variantId = $decodedId;
    }
    
    // Validate required fields
    if (!$providerId || !$branchId) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Only main or standalone providers can have wallets
    $provider = Database::fetch(
        "SELECT parent_provider_id FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    if ($provider && !empty($provider['parent_provider_id'])) {
        echo json_encode(['success' => false, 'error' => 'Sub-providers cannot have their own wallets. They share the main provider wallet.']);
        return;
    }

    // If a variant is specified, ensure it belongs to this provider
    if ($variantId) {
        $variant = Database::fetch(
            "SELECT variant_id FROM provider_ticket_variants WHERE variant_id = :variant_id AND provider_id = :provider_id AND deleted_at IS NULL",
            ['variant_id' => (int)$variantId, 'provider_id' => (int)$providerId]
        );
        if (!$variant) {
            echo json_encode(['success' => false, 'error' => 'Variant does not belong to the selected provider or is inactive/deleted']);
            return;
        }
    }
    
    // Check if wallet already exists for this provider-branch-variant combination
    $existing = Database::fetch(
        "SELECT wallet_id FROM provider_wallets 
         WHERE provider_id = :provider_id 
           AND branch_id = :branch_id 
           AND IFNULL(variant_id, 0) = IFNULL(:variant_id, 0)",
        ['provider_id' => (int)$providerId, 'branch_id' => (int)$branchId, 'variant_id' => $variantId ? (int)$variantId : null]
    );
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Wallet already exists for this provider, branch and variant']);
        return;
    }
    
    // Insert new wallet with initial balance
    $sql = "INSERT INTO provider_wallets (provider_id, branch_id, variant_id, current_balance, min_balance, status, created_at)
            VALUES (:provider_id, :branch_id, :variant_id, :initial_balance, :min_balance, :status, :created_at)";
    
    Database::execute($sql, [
        'provider_id' => (int)$providerId,
        'branch_id' => (int)$branchId,
        'variant_id' => $variantId ? (int)$variantId : null,
        'initial_balance' => $initialBalance,
        'min_balance' => $minBalance,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $walletId = Database::connection()->lastInsertId();
    
    // Log activity
    logActivity(
        $user['user_id'],
        'CREATE_WALLET',
        'WALLET_MANAGEMENT',
        "WALLET-{$walletId}",
        null,
        [
            'wallet_id' => $walletId,
            'provider_id' => $providerId,
            'branch_id' => $branchId,
            'initial_balance' => $initialBalance,
            'status' => $status
        ]
    );
    
    echo json_encode(['success' => true, 'message' => 'Wallet created successfully', 'wallet_id' => $walletId, 'initial_balance' => $initialBalance]);
}

/**
 * Handle PUT requests - update wallet
 */
function handlePut() {
    global $user, $userRoleCode;
    
    // Check permission
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canUpdate) {
        $canUpdate = Auth::can('UPDATE_WALLET');
    }
    
    if (!$canUpdate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need UPDATE_WALLET permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $walletId = $input['wallet_id'] ?? null;
    $status = $input['status'] ?? null;
    $minBalance = $input['min_balance'] ?? null;

    // Decode wallet_id if encrypted
    if ($walletId && !is_numeric($walletId)) {
        $decodedId = IdEncoder::decode($walletId);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid wallet ID']);
            return;
        }
        $walletId = $decodedId;
    }
    
    if (!$walletId || (!$status && !$minBalance)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Get current wallet data
    $currentWallet = Database::fetch(
        "SELECT * FROM provider_wallets WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );
    
    if (!$currentWallet) {
        echo json_encode(['success' => false, 'error' => 'Wallet not found']);
        return;
    }
    
    // Update wallet status and/or min_balance
    $updateFields = [];
    $updateParams = ['wallet_id' => (int)$walletId, 'updated_at' => date('Y-m-d H:i:s')];
    
    if ($status) {
        $updateFields[] = "status = :status";
        $updateParams['status'] = $status;
    }
    
    if ($minBalance !== null) {
        $updateFields[] = "min_balance = :min_balance";
        $updateParams['min_balance'] = (float)$minBalance;
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    $sql = "UPDATE provider_wallets SET " . implode(', ', $updateFields) . ", updated_at = :updated_at WHERE wallet_id = :wallet_id";
    
    Database::execute($sql, $updateParams);
    
    // Log activity
    logActivity(
        $user['user_id'],
        'UPDATE_WALLET',
        'WALLET_MANAGEMENT',
        "WALLET-{$walletId}",
        ['status' => $currentWallet['status']],
        ['status' => $status]
    );
    
    echo json_encode(['success' => true, 'message' => 'Wallet updated successfully']);
}

/**
 * Handle DELETE requests - delete wallet
 */
function handleDelete() {
    global $user, $userRoleCode;
    
    // Check permission
    $canDelete = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canDelete) {
        $canDelete = Auth::can('DELETE_WALLET');
    }
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need DELETE_WALLET permission.']);
        exit;
    }
    
    $walletId = $_GET['id'] ?? null;
    
    if (!$walletId) {
        echo json_encode(['success' => false, 'error' => 'Missing wallet ID']);
        return;
    }

    // Decode wallet_id if encrypted
    if (!is_numeric($walletId)) {
        $decodedId = IdEncoder::decode($walletId);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid wallet ID']);
            return;
        }
        $walletId = $decodedId;
    }
    
    // Get current wallet data
    $currentWallet = Database::fetch(
        "SELECT * FROM provider_wallets WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );
    
    if (!$currentWallet) {
        echo json_encode(['success' => false, 'error' => 'Wallet not found']);
        return;
    }
    
    // Check if wallet has wallet transactions
    $hasWalletTransactions = Database::fetch(
        "SELECT COUNT(*) as count FROM wallet_transactions WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );
    
    if ($hasWalletTransactions && $hasWalletTransactions['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete wallet with existing wallet transactions']);
        return;
    }

    // Check if wallet is referenced by ticket transactions
    $hasTicketTransactions = Database::fetch(
        "SELECT COUNT(*) as count FROM ticket_transactions WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );

    if ($hasTicketTransactions && $hasTicketTransactions['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete wallet with existing ticket transactions']);
        return;
    }
    
    // Delete wallet
    Database::execute(
        "DELETE FROM provider_wallets WHERE wallet_id = :wallet_id",
        ['wallet_id' => (int)$walletId]
    );
    
    // Log activity
    logActivity(
        $user['user_id'],
        'DELETE_WALLET',
        'WALLET_MANAGEMENT',
        "WALLET-{$walletId}",
        $currentWallet,
        null
    );
    
    echo json_encode(['success' => true, 'message' => 'Wallet deleted successfully']);
}
