<?php
/**
 * Ticket Providers API Endpoint
 * Handles provider management operations
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/CashierTransportAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

// Helper function for logging activity
/**
 * Check if a provider is a descendant of another provider (used for cycle detection)
 */
function isDescendantOf($descendantId, $ancestorId) {
    $currentId = $descendantId;
    $visited = [];

    while ($currentId) {
        if (isset($visited[$currentId])) {
            break; // Cycle in existing data, stop to avoid infinite loop
        }
        $visited[$currentId] = true;

        if ($currentId == $ancestorId) {
            return true;
        }

        $row = Database::fetch(
            "SELECT parent_provider_id FROM ticket_providers WHERE provider_id = :provider_id",
            ['provider_id' => (int)$currentId]
        );
        $currentId = $row['parent_provider_id'] ?? null;
    }

    return false;
}

/**
 * Return true if the supplied value is a valid provider_type lookup value.
 */
function isValidProviderType(?string $type): bool
{
    if ($type === null || $type === '') {
        return false;
    }
    return in_array($type, array_column(Database::getProviderTypes(), 'type_code'), true);
}

function normalizeBooleanFlag($value): ?int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value)) {
        return in_array($value, [0, 1], true) ? $value : null;
    }
    if (is_float($value)) {
        return in_array($value, [0.0, 1.0], true) ? (int) $value : null;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'on'], true)) {
            return 1;
        }
        if (in_array($normalized, ['0', 'false', 'off'], true)) {
            return 0;
        }
    }
    return null;
}

function hasProviderWalletDeductionSettings(): bool
{
    try {
        return (bool) Database::fetch("SHOW COLUMNS FROM ticket_providers LIKE 'wallet_deduct_all_charges'")
            && (bool) Database::fetch("SHOW COLUMNS FROM ticket_providers LIKE 'wallet_deduct_base_only'");
    } catch (Throwable $e) {
        return false;
    }
}

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

function broadcastProviderUpdate(int $providerId, string $action, array $provider = []): void
{
    if ($providerId < 1 || !PusherService::isConfigured()) {
        return;
    }

    $payload = [
        'provider_id' => $providerId,
        'action' => $action,
        'provider_code' => $provider['provider_code'] ?? null,
        'provider_name' => $provider['provider_name'] ?? null,
        'provider_type' => $provider['provider_type'] ?? null,
        'parent_provider_id' => isset($provider['parent_provider_id']) && $provider['parent_provider_id'] !== null
            ? (int) $provider['parent_provider_id']
            : null,
        'status' => $provider['status'] ?? null,
        'wallet_deduct_all_charges' => (int) ($provider['wallet_deduct_all_charges'] ?? 0),
        'wallet_deduct_base_only' => (int) ($provider['wallet_deduct_base_only'] ?? 1),
        'updated_at' => date(DATE_ATOM),
    ];

    try {
        $branches = Database::fetchAll("SELECT branch_id FROM business_branches");
    } catch (Throwable $e) {
        error_log('[Pusher] Provider update branch lookup failed: ' . $e->getMessage());
        return;
    }

    foreach ($branches as $branch) {
        PusherService::triggerBranch((int) ($branch['branch_id'] ?? 0), 'provider.updated', $payload);
    }
}

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission - SUPER_ADMIN or users with VIEW_PROVIDERS / wallet permissions
$user = Auth::user();
$canView = ($user['role_code'] === 'SUPER_ADMIN');

if (!$canView) {
    $canView = Auth::can('VIEW_PROVIDERS')
            || Auth::can('VIEW_WALLET_MANAGEMENT')
            || Auth::can('VIEW_WALLETS');
}

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need VIEW_WALLET_MANAGEMENT permission to access this resource.']);
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
    error_log("Ticket Providers API Error: " . $e->getMessage());
    error_log("Ticket Providers API Trace: " . $e->getTraceAsString());
    error_log("Ticket Providers API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list providers or get stats
 */
function handleGet() {
    global $user;
    $providerId = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;

    // Decode provider_id if provided
    if ($providerId) {
        $decodedProviderId = IdEncoder::decode($providerId);
        if ($decodedProviderId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid provider ID']);
            return;
        }
        $providerId = $decodedProviderId;
    }

    // Get provider stats
    if ($action === 'stats') {
        $sql = "SELECT
                    COUNT(*) as total_providers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_providers,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_providers
                FROM ticket_providers";

        $stats = Database::fetch($sql);

        echo json_encode([
            'success' => true,
            'data' => [
                'total_providers' => (int)$stats['total_providers'],
                'active_providers' => (int)$stats['active_providers'],
                'inactive_providers' => (int)$stats['inactive_providers']
            ]
        ]);
        return;
    }

    // Get single provider
    if ($providerId) {
        $sql = "SELECT tp.*, ptp.provider_name as parent_provider_name
                FROM ticket_providers tp
                LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
                WHERE tp.provider_id = :provider_id";
        $provider = Database::fetch($sql, ['provider_id' => (int)$providerId]);
        
        if ($provider) {
            echo json_encode(['success' => true, 'data' => $provider]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
        }
        return;
    }

    // List all providers with parent info and active variant count
    $sql = "SELECT tp.*, ptp.provider_name as parent_provider_name,
                   (
                       SELECT COUNT(*)
                       FROM provider_ticket_variants v
                       WHERE v.provider_id = tp.provider_id
                         AND v.deleted_at IS NULL
                   ) AS variant_count,
                   (
                       SELECT GROUP_CONCAT(v.variant_name ORDER BY v.variant_name SEPARATOR ', ')
                       FROM provider_ticket_variants v
                       WHERE v.provider_id = tp.provider_id
                         AND v.deleted_at IS NULL
                   ) AS variant_names
            FROM ticket_providers tp
            LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
            ORDER BY tp.provider_name";
    $providers = Database::fetchAll($sql);
    $providers = CashierTransportAccess::filterProviders($providers, $user);

    echo json_encode([
        'success' => true,
        'data' => [
            'providers' => $providers
        ]
    ]);
}

/**
 * Handle POST requests - create provider
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
    
    $providerCode = $input['provider_code'] ?? null;
    $providerName = $input['provider_name'] ?? null;
    $providerType = $input['provider_type'] ?? null;
    $parentProviderId = $input['parent_provider_id'] ?? null;
    $status = $input['status'] ?? 'active';
    $walletDeductAllCharges = normalizeBooleanFlag($input['wallet_deduct_all_charges'] ?? 0);
    $walletDeductBaseOnly = normalizeBooleanFlag($input['wallet_deduct_base_only'] ?? 1);

    if ($walletDeductAllCharges === null || $walletDeductBaseOnly === null) {
        echo json_encode(['success' => false, 'error' => 'Invalid provider wallet deduction setting']);
        return;
    }
    $hasProviderWalletDeductionSettings = hasProviderWalletDeductionSettings();

    // Validate required fields
    if (!$providerCode || !$providerName || !$providerType) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    if (!isValidProviderType($providerType)) {
        echo json_encode(['success' => false, 'error' => 'Invalid provider type. Allowed types: ' . implode(', ', array_column(Database::getProviderTypes(), 'type_code'))]);
        return;
    }

    // Validate parent provider if provided
    if ($parentProviderId) {
        $parent = Database::fetch(
            "SELECT provider_id FROM ticket_providers WHERE provider_id = :provider_id",
            ['provider_id' => (int)$parentProviderId]
        );
        if (!$parent) {
            echo json_encode(['success' => false, 'error' => 'Parent provider not found']);
            return;
        }
    }

    // Check if provider code already exists
    $existing = Database::fetch(
        "SELECT provider_id FROM ticket_providers WHERE provider_code = :provider_code",
        ['provider_code' => $providerCode]
    );

    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Provider code already exists']);
        return;
    }

    // Insert new provider
    $insertFields = 'provider_code, provider_name, provider_type, parent_provider_id, status, created_at';
    $insertValues = ':provider_code, :provider_name, :provider_type, :parent_provider_id, :status, :created_at';
    $insertParams = [
        'provider_code' => $providerCode,
        'provider_name' => $providerName,
        'provider_type' => $providerType,
        'parent_provider_id' => $parentProviderId ? (int)$parentProviderId : null,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s')
    ];
    if ($hasProviderWalletDeductionSettings) {
        $insertFields .= ', wallet_deduct_all_charges, wallet_deduct_base_only';
        $insertValues .= ', :wallet_deduct_all_charges, :wallet_deduct_base_only';
        $insertParams['wallet_deduct_all_charges'] = $walletDeductAllCharges;
        $insertParams['wallet_deduct_base_only'] = $walletDeductBaseOnly;
    }

    $sql = "INSERT INTO ticket_providers ({$insertFields}) VALUES ({$insertValues})";
    Database::execute($sql, $insertParams);
    
    $providerId = Database::connection()->lastInsertId();
    
    // Log activity
    logActivity(
        $user['user_id'],
        'CREATE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        null,
        [
            'provider_id' => $providerId,
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
            'provider_type' => $providerType,
            'status' => $status,
            'wallet_deduct_all_charges' => $walletDeductAllCharges,
            'wallet_deduct_base_only' => $walletDeductBaseOnly
        ]
    );
    broadcastProviderUpdate((int) $providerId, 'created', [
        'provider_code' => $providerCode,
        'provider_name' => $providerName,
        'provider_type' => $providerType,
        'parent_provider_id' => $parentProviderId,
        'status' => $status,
        'wallet_deduct_all_charges' => $walletDeductAllCharges,
        'wallet_deduct_base_only' => $walletDeductBaseOnly,
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Provider created successfully', 'provider_id' => $providerId]);
}

/**
 * Handle PUT requests - update provider
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
    $providerId = $input['provider_id'] ?? null;
    $providerCode = $input['provider_code'] ?? null;
    $providerName = $input['provider_name'] ?? null;
    $providerType = $input['provider_type'] ?? null;
    $parentProviderId = $input['parent_provider_id'] ?? null;
    $status = $input['status'] ?? null;
    $walletDeductAllCharges = null;
    $walletDeductBaseOnly = null;
    $hasWalletDeductionInput = array_key_exists('wallet_deduct_all_charges', $input)
        || array_key_exists('wallet_deduct_base_only', $input);
    if (array_key_exists('wallet_deduct_all_charges', $input)) {
        $walletDeductAllCharges = normalizeBooleanFlag($input['wallet_deduct_all_charges']);
        if ($walletDeductAllCharges === null) {
            echo json_encode(['success' => false, 'error' => 'Invalid all-charges wallet setting']);
            return;
        }
    }
    if (array_key_exists('wallet_deduct_base_only', $input)) {
        $walletDeductBaseOnly = normalizeBooleanFlag($input['wallet_deduct_base_only']);
        if ($walletDeductBaseOnly === null) {
            echo json_encode(['success' => false, 'error' => 'Invalid base-only wallet setting']);
            return;
        }
    }
    if ($hasWalletDeductionInput && !hasProviderWalletDeductionSettings()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'Database migration required: add_provider_wallet_deduction_settings.sql'
        ]);
        return;
    }

    if ($providerType !== null && !isValidProviderType($providerType)) {
        echo json_encode(['success' => false, 'error' => 'Invalid provider type. Allowed types: ' . implode(', ', array_column(Database::getProviderTypes(), 'type_code'))]);
        return;
    }

    if (!$providerId) {
        echo json_encode(['success' => false, 'error' => 'Missing provider ID']);
        return;
    }

    // Get current provider data
    $currentProvider = Database::fetch(
        "SELECT * FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );

    if (!$currentProvider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }

    // Validate parent provider if provided
    if ($parentProviderId) {
        if ((int)$parentProviderId === (int)$providerId) {
            echo json_encode(['success' => false, 'error' => 'Provider cannot be its own parent']);
            return;
        }
        $parent = Database::fetch(
            "SELECT provider_id FROM ticket_providers WHERE provider_id = :provider_id",
            ['provider_id' => (int)$parentProviderId]
        );
        if (!$parent) {
            echo json_encode(['success' => false, 'error' => 'Parent provider not found']);
            return;
        }
        if (isDescendantOf((int)$parentProviderId, (int)$providerId)) {
            echo json_encode(['success' => false, 'error' => 'Cannot set a descendant provider as the parent']);
            return;
        }
    }

    // Build update query
    $updateFields = [];
    $params = ['provider_id' => (int)$providerId];

    if ($providerCode !== null) {
        $updateFields[] = "provider_code = :provider_code";
        $params['provider_code'] = $providerCode;
    }
    if ($providerName !== null) {
        $updateFields[] = "provider_name = :provider_name";
        $params['provider_name'] = $providerName;
    }
    if ($providerType !== null) {
        $updateFields[] = "provider_type = :provider_type";
        $params['provider_type'] = $providerType;
    }
    if (array_key_exists('parent_provider_id', $input)) {
        $updateFields[] = "parent_provider_id = :parent_provider_id";
        $params['parent_provider_id'] = $parentProviderId ? (int)$parentProviderId : null;
    }
    if ($status !== null) {
        $updateFields[] = "status = :status";
        $params['status'] = $status;
    }
    if ($walletDeductAllCharges !== null) {
        $updateFields[] = "wallet_deduct_all_charges = :wallet_deduct_all_charges";
        $params['wallet_deduct_all_charges'] = $walletDeductAllCharges;
    }
    if ($walletDeductBaseOnly !== null) {
        $updateFields[] = "wallet_deduct_base_only = :wallet_deduct_base_only";
        $params['wallet_deduct_base_only'] = $walletDeductBaseOnly;
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Update provider
    $sql = "UPDATE ticket_providers SET " . implode(', ', $updateFields) . " WHERE provider_id = :provider_id";
    Database::execute($sql, $params);
    
    // Log activity
    $oldValues = [];
    $newValues = [];
    
    if ($providerCode !== null) {
        $oldValues['provider_code'] = $currentProvider['provider_code'];
        $newValues['provider_code'] = $providerCode;
    }
    if ($providerName !== null) {
        $oldValues['provider_name'] = $currentProvider['provider_name'];
        $newValues['provider_name'] = $providerName;
    }
    if ($providerType !== null) {
        $oldValues['provider_type'] = $currentProvider['provider_type'];
        $newValues['provider_type'] = $providerType;
    }
    if ($status !== null) {
        $oldValues['status'] = $currentProvider['status'];
        $newValues['status'] = $status;
    }
    if ($walletDeductAllCharges !== null) {
        $oldValues['wallet_deduct_all_charges'] = (int) ($currentProvider['wallet_deduct_all_charges'] ?? 0);
        $newValues['wallet_deduct_all_charges'] = $walletDeductAllCharges;
    }
    if ($walletDeductBaseOnly !== null) {
        $oldValues['wallet_deduct_base_only'] = (int) ($currentProvider['wallet_deduct_base_only'] ?? 1);
        $newValues['wallet_deduct_base_only'] = $walletDeductBaseOnly;
    }
    
    logActivity(
        $user['user_id'],
        'UPDATE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        !empty($oldValues) ? $oldValues : null,
        !empty($newValues) ? $newValues : null
    );
    $updatedProviderFields = 'provider_code, provider_name, provider_type, parent_provider_id, status';
    if (hasProviderWalletDeductionSettings()) {
        $updatedProviderFields .= ', wallet_deduct_all_charges, wallet_deduct_base_only';
    }
    $updatedProvider = Database::fetch(
        "SELECT {$updatedProviderFields}
         FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int) $providerId]
    );
    broadcastProviderUpdate((int) $providerId, 'updated', $updatedProvider ?: $newValues);
    
    echo json_encode([
        'success' => true,
        'message' => 'Provider updated successfully',
        'data' => $updatedProvider ?: []
    ]);
}

/**
 * Handle DELETE requests - delete provider
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
    
    $providerId = $_GET['id'] ?? null;

    // Decode provider_id if provided
    if ($providerId) {
        $decodedProviderId = IdEncoder::decode($providerId);
        if ($decodedProviderId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid provider ID']);
            return;
        }
        $providerId = $decodedProviderId;
    }

    if (!$providerId) {
        echo json_encode(['success' => false, 'error' => 'Missing provider ID']);
        return;
    }
    
    // Get current provider data
    $currentProvider = Database::fetch(
        "SELECT * FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if (!$currentProvider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }
    
    // Check if provider has wallets
    $hasWallets = Database::fetch(
        "SELECT COUNT(*) as count FROM provider_wallets WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if ($hasWallets && $hasWallets['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with existing wallets']);
        return;
    }
    
    // Check if provider has service fees
    $hasServiceFees = Database::fetch(
        "SELECT COUNT(*) as count FROM provider_service_fees WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if ($hasServiceFees && $hasServiceFees['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with existing service fees']);
        return;
    }

    // Check if provider has child providers
    $hasChildren = Database::fetch(
        "SELECT COUNT(*) as count FROM ticket_providers WHERE parent_provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );

    if ($hasChildren && $hasChildren['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with child providers. Reassign or delete the child providers first.']);
        return;
    }

    // Check if provider is referenced by ticket transactions
    $hasTickets = Database::fetch(
        "SELECT COUNT(*) as count FROM ticket_transactions WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );

    if ($hasTickets && $hasTickets['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with existing ticket transactions']);
        return;
    }

    // Check if provider is assigned to cashiers
    $hasCashierAssignments = Database::fetch(
        "SELECT COUNT(*) as count FROM cashier_transport_assignments WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );

    if ($hasCashierAssignments && $hasCashierAssignments['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider assigned to cashiers']);
        return;
    }
    
    // Delete provider
    Database::execute(
        "DELETE FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    // Log activity
    logActivity(
        $user['user_id'],
        'DELETE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        $currentProvider,
        null
    );
    broadcastProviderUpdate((int) $providerId, 'deleted', $currentProvider);
    
    echo json_encode(['success' => true, 'message' => 'Provider deleted successfully']);
}
