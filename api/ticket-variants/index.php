<?php
/**
 * Ticket Variants API
 *
 * CRUD operations for provider ticket variants.
 * GET returns active ticket variants for a provider, including current branch availability.
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            requireManagePermission();
            handlePost();
            break;
        case 'PUT':
            requireManagePermission();
            handlePut();
            break;
        case 'DELETE':
            requireManagePermission();
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
    }
} catch (Exception $e) {
    error_log('Ticket variants API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process ticket variant request.']);
}

function requireManagePermission(): void {
    $user = Auth::user();
    if ($user && $user['role_code'] === 'SUPER_ADMIN') {
        return;
    }
    if (!Auth::can('MANAGE_TICKET_VARIANTS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit;
    }
}

function getCsrfTokenFromRequest(): ?string {
    // 1. Standard PHP/Apache header variable
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    // 2. getallheaders()/apache_request_headers() (case-insensitive)
    $allHeaders = [];
    if (function_exists('getallheaders')) {
        $allHeaders = getallheaders();
    } elseif (function_exists('apache_request_headers')) {
        $allHeaders = apache_request_headers();
    }
    foreach ($allHeaders as $name => $value) {
        if (strcasecmp($name, 'X-CSRF-TOKEN') === 0) {
            return $value;
        }
    }

    // 3. Fallback to explicit token in query or form data
    return $_POST['_token'] ?? $_GET['_token'] ?? null;
}

function validateCsrf(): void {
    $csrfToken = getCsrfTokenFromRequest();
    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function getInput(): array {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    return is_array($input) ? $input : [];
}

function canManageVariants(): bool {
    $user = Auth::user();
    if ($user && $user['role_code'] === 'SUPER_ADMIN') {
        return true;
    }
    return Auth::can('MANAGE_TICKET_VARIANTS');
}

function handleGet(): void {
    $providerId = $_GET['provider_id'] ?? null;
    $variantId  = $_GET['variant_id']  ?? null;
    $branchId   = $_GET['branch_id']   ?? null;
    $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1';

    $user = Auth::user();
    if ($user['role_code'] !== 'SUPER_ADMIN' && $branchId) {
        if ((int)$user['branch_id'] !== (int)$branchId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied: branch mismatch']);
            exit;
        }
    }

    // Fetch single variant by ID
    if ($variantId && is_numeric($variantId)) {
        $variantId = (int) $variantId;
        $variant = Database::fetch(
            "SELECT
                v.variant_id,
                v.provider_id,
                p.provider_name,
                v.variant_code,
                v.variant_name,
                v.display_color AS color_code,
                v.description,
                v.stock_controlled,
                v.requires_ticket_number,
                v.is_active,
                COALESCE(s.on_hand_qty, 0) AS on_hand_qty,
                COALESCE(s.reserved_qty, 0) AS reserved_qty,
                (COALESCE(s.on_hand_qty, 0) - COALESCE(s.reserved_qty, 0)) AS available_qty
            FROM provider_ticket_variants v
            LEFT JOIN ticket_providers p ON p.provider_id = v.provider_id
            LEFT JOIN branch_ticket_stocks s
                ON s.variant_id = v.variant_id
               AND s.provider_id = v.provider_id
               AND s.branch_id = :branch_id
            WHERE v.variant_id = :variant_id
              AND v.deleted_at IS NULL",
            ['variant_id' => $variantId, 'branch_id' => ($branchId ? (int) $branchId : 0)]
        );

        if (!$variant) {
            echo json_encode(['success' => false, 'error' => 'Variant not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => [$variant]]);
        exit;
    }

    // List all variants (admin view) when no provider_id is supplied
    if (!$providerId) {
        if (!$includeInactive && !canManageVariants()) {
            echo json_encode(['success' => false, 'error' => 'Valid provider_id is required.']);
            exit;
        }

        $sql = "SELECT
                    v.variant_id,
                    v.provider_id,
                    p.provider_code,
                    p.provider_name,
                    v.variant_code,
                    v.variant_name,
                    v.display_color AS color_code,
                    v.description,
                    v.stock_controlled,
                    v.requires_ticket_number,
                    v.is_active
                FROM provider_ticket_variants v
                LEFT JOIN ticket_providers p ON p.provider_id = v.provider_id
                WHERE v.deleted_at IS NULL";

        if (!$includeInactive) {
            $sql .= " AND v.is_active = 1";
        }

        $sql .= " ORDER BY p.provider_name, v.variant_name";

        $data = Database::fetchAll($sql);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    if (!is_numeric($providerId)) {
        echo json_encode(['success' => false, 'error' => 'Valid provider_id is required.']);
        exit;
    }

    $providerId = (int) $providerId;
    $branchId   = $branchId ? (int) $branchId : null;

    $sql = "SELECT
                v.variant_id,
                v.provider_id,
                p.provider_name,
                v.variant_code,
                v.variant_name,
                v.display_color AS color_code,
                v.description,
                v.stock_controlled,
                v.requires_ticket_number,
                v.is_active,
                COALESCE(s.on_hand_qty, 0) AS on_hand_qty,
                COALESCE(s.reserved_qty, 0) AS reserved_qty,
                (COALESCE(s.on_hand_qty, 0) - COALESCE(s.reserved_qty, 0)) AS available_qty,
                pw.wallet_id,
                COALESCE(pw.current_balance, 0) AS wallet_balance,
                pw_main.wallet_id AS main_wallet_id,
                COALESCE(pw_main.current_balance, 0) AS main_wallet_balance
            FROM provider_ticket_variants v
            LEFT JOIN ticket_providers p ON p.provider_id = v.provider_id
            LEFT JOIN branch_ticket_stocks s
                ON s.variant_id = v.variant_id
               AND s.provider_id = v.provider_id
               AND s.branch_id = :stock_branch_id
            LEFT JOIN provider_wallets pw
                ON pw.provider_id = v.provider_id
               AND pw.variant_id = v.variant_id
               AND pw.branch_id = :wallet_branch_id
               AND pw.status = 'active'
            LEFT JOIN provider_wallets pw_main
                ON pw_main.provider_id = v.provider_id
               AND pw_main.variant_id IS NULL
               AND pw_main.branch_id = :wallet_branch_id
               AND pw_main.status = 'active'
            WHERE v.provider_id = :provider_id
              AND v.deleted_at IS NULL";

    if (!$includeInactive) {
        $sql .= " AND v.is_active = 1";
    }

    $sql .= " ORDER BY v.variant_name ASC";

    $data = Database::fetchAll($sql, [
        'provider_id'      => $providerId,
        'stock_branch_id'  => $branchId ?? 0,
        'wallet_branch_id' => $branchId ?? 0,
    ]);

    echo json_encode(['success' => true, 'data' => $data]);
}

function handlePost(): void {
    validateCsrf();
    $input = getInput();

    $providerId = isset($input['provider_id']) && is_numeric($input['provider_id']) ? (int) $input['provider_id'] : null;
    $variantCode = trim($input['variant_code'] ?? '');
    $variantName = trim($input['variant_name'] ?? '');
    $description = isset($input['description']) ? trim($input['description']) : null;
    $displayColor = isset($input['display_color']) ? trim($input['display_color']) : null;
    $stockControlled = isset($input['stock_controlled']) ? (int) $input['stock_controlled'] : 1;
    $requiresTicketNumber = isset($input['requires_ticket_number']) ? (int) $input['requires_ticket_number'] : 0;
    $isActive = isset($input['is_active']) ? (int) $input['is_active'] : 1;

    if (!$providerId) {
        echo json_encode(['success' => false, 'error' => 'Valid provider_id is required.']);
        exit;
    }
    if (empty($variantCode) || empty($variantName)) {
        echo json_encode(['success' => false, 'error' => 'variant_code and variant_name are required.']);
        exit;
    }

    // Validate provider exists
    $provider = Database::fetch(
        "SELECT provider_id FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => $providerId]
    );
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found.']);
        exit;
    }

    // Check unique variant code per provider
    $existing = Database::fetch(
        "SELECT variant_id FROM provider_ticket_variants WHERE provider_id = :provider_id AND variant_code = :variant_code AND deleted_at IS NULL",
        ['provider_id' => $providerId, 'variant_code' => $variantCode]
    );
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Variant code already exists for this provider.']);
        exit;
    }

    $user = Auth::user();
    $sql = "INSERT INTO provider_ticket_variants
                (provider_id, variant_code, variant_name, description, display_color, stock_controlled, requires_ticket_number, is_active, created_by, created_at)
            VALUES
                (:provider_id, :variant_code, :variant_name, :description, :display_color, :stock_controlled, :requires_ticket_number, :is_active, :created_by, :created_at)";

    Database::execute($sql, [
        'provider_id'          => $providerId,
        'variant_code'         => $variantCode,
        'variant_name'         => $variantName,
        'description'        => $description ?: null,
        'display_color'        => $displayColor ?: null,
        'stock_controlled'     => $stockControlled ? 1 : 0,
        'requires_ticket_number' => $requiresTicketNumber ? 1 : 0,
        'is_active'            => $isActive ? 1 : 0,
        'created_by'           => $user['user_id'] ?? null,
        'created_at'           => date('Y-m-d H:i:s'),
    ]);

    $variantId = Database::lastInsertId();

    echo json_encode(['success' => true, 'message' => 'Variant created successfully.', 'variant_id' => $variantId]);
}

function handlePut(): void {
    validateCsrf();
    $input = getInput();

    $variantId = isset($input['variant_id']) && is_numeric($input['variant_id']) ? (int) $input['variant_id'] : null;
    if (!$variantId) {
        echo json_encode(['success' => false, 'error' => 'Valid variant_id is required.']);
        exit;
    }

    $current = Database::fetch(
        "SELECT * FROM provider_ticket_variants WHERE variant_id = :variant_id AND deleted_at IS NULL",
        ['variant_id' => $variantId]
    );
    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Variant not found.']);
        exit;
    }

    $fields = [];
    $params = ['variant_id' => $variantId];

    if (array_key_exists('variant_code', $input)) {
        $variantCode = trim($input['variant_code']);
        if (empty($variantCode)) {
            echo json_encode(['success' => false, 'error' => 'variant_code cannot be empty.']);
            exit;
        }
        $duplicate = Database::fetch(
            "SELECT variant_id FROM provider_ticket_variants WHERE provider_id = :provider_id AND variant_code = :variant_code AND variant_id <> :variant_id AND deleted_at IS NULL",
            ['provider_id' => $current['provider_id'], 'variant_code' => $variantCode, 'variant_id' => $variantId]
        );
        if ($duplicate) {
            echo json_encode(['success' => false, 'error' => 'Variant code already exists for this provider.']);
            exit;
        }
        $fields[] = "variant_code = :variant_code";
        $params['variant_code'] = $variantCode;
    }
    if (array_key_exists('variant_name', $input)) {
        $variantName = trim($input['variant_name']);
        if (empty($variantName)) {
            echo json_encode(['success' => false, 'error' => 'variant_name cannot be empty.']);
            exit;
        }
        $fields[] = "variant_name = :variant_name";
        $params['variant_name'] = $variantName;
    }
    if (array_key_exists('description', $input)) {
        $fields[] = "description = :description";
        $params['description'] = empty($input['description']) ? null : trim($input['description']);
    }
    if (array_key_exists('display_color', $input)) {
        $fields[] = "display_color = :display_color";
        $params['display_color'] = empty($input['display_color']) ? null : trim($input['display_color']);
    }
    if (array_key_exists('stock_controlled', $input)) {
        $fields[] = "stock_controlled = :stock_controlled";
        $params['stock_controlled'] = (int) $input['stock_controlled'] ? 1 : 0;
    }
    if (array_key_exists('requires_ticket_number', $input)) {
        $fields[] = "requires_ticket_number = :requires_ticket_number";
        $params['requires_ticket_number'] = (int) $input['requires_ticket_number'] ? 1 : 0;
    }
    if (array_key_exists('is_active', $input)) {
        $fields[] = "is_active = :is_active";
        $params['is_active'] = (int) $input['is_active'] ? 1 : 0;
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update.']);
        exit;
    }

    $sql = "UPDATE provider_ticket_variants SET " . implode(', ', $fields) . " WHERE variant_id = :variant_id AND deleted_at IS NULL";
    Database::execute($sql, $params);

    echo json_encode(['success' => true, 'message' => 'Variant updated successfully.']);
}

function handleDelete(): void {
    validateCsrf();

    $variantId = $_GET['variant_id'] ?? null;
    if (!$variantId || !is_numeric($variantId)) {
        echo json_encode(['success' => false, 'error' => 'Valid variant_id is required.']);
        exit;
    }
    $variantId = (int) $variantId;

    $current = Database::fetch(
        "SELECT variant_id FROM provider_ticket_variants WHERE variant_id = :variant_id AND deleted_at IS NULL",
        ['variant_id' => $variantId]
    );
    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Variant not found.']);
        exit;
    }

    // Dependency checks before deletion
    $dependencies = [
        'branch_ticket_stocks'        => 'variant_id',
        'ticket_stock_request_items'  => 'variant_id',
        'ticket_stock_movements'      => 'variant_id',
        'ticket_stock_reservations'   => 'variant_id',
        'ticket_transactions'         => 'variant_id',
        'pos_order_items'             => 'variant_id',
    ];

    foreach ($dependencies as $table => $column) {
        $ref = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM {$table} WHERE {$column} = :variant_id",
            ['variant_id' => $variantId]
        );
        if (($ref['cnt'] ?? 0) > 0) {
            echo json_encode(['success' => false, 'error' => "Cannot delete variant: it is referenced in {$table}."]);
            exit;
        }
    }

    Database::execute(
        "DELETE FROM provider_ticket_variants WHERE variant_id = :variant_id",
        ['variant_id' => $variantId]
    );

    echo json_encode(['success' => true, 'message' => 'Variant deleted successfully.']);
}
