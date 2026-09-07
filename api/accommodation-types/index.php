<?php
/**
 * Accommodation Types API
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            requireSettingsPermission();
            handlePost();
            break;
        case 'PUT':
            requireSettingsPermission();
            handlePut();
            break;
        case 'DELETE':
            requireSettingsPermission();
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    error_log('Accommodation types API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to process accommodation type request.']);
}

function requireSettingsPermission(): void
{
    global $userRoleCode;

    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit;
    }
}

function getInput(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function resolveAccommodationId($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        $value = IdEncoder::decode($value);
    }

    $id = (int) $value;
    return $id > 0 ? $id : null;
}

function validateAccommodationFields(string $code, string $name): ?string
{
    if (!$code || !$name) {
        return 'Code and name are required.';
    }
    if (strlen($code) > 50) {
        return 'Code must be 50 characters or fewer.';
    }
    if (strlen($name) > 100) {
        return 'Name must be 100 characters or fewer.';
    }
    return null;
}

function normalizeAccommodation(array $accommodation): array
{
    $accommodation['accommodation_id'] = (int) ($accommodation['accommodation_id'] ?? 0);
    $accommodation['is_default'] = (int) ($accommodation['is_default'] ?? 0);
    return $accommodation;
}

function atLogActivity($userId, string $action, string $referenceCode, $oldValue = null, $newValue = null): void
{
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, NULL, :action, 'ACCOMMODATION_TYPES', :reference_code, :ip_address, :old_value, :new_value, :created_at)",
        [
            'user_id' => $userId,
            'action' => $action,
            'reference_code' => $referenceCode,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'old_value' => $oldValue !== null ? json_encode($oldValue) : null,
            'new_value' => $newValue !== null ? json_encode($newValue) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    );
}

function handleGet(): void
{
    $rawId = $_GET['id'] ?? null;
    if ($rawId !== null && $rawId !== '') {
        $id = resolveAccommodationId($rawId);
        if ($id === null) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            return;
        }

        $accommodation = Database::fetch(
            "SELECT accommodation_id, code, name, is_default, created_at
             FROM accommodation_types
             WHERE accommodation_id = :id",
            ['id' => $id]
        );
        if (!$accommodation) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            return;
        }

        echo json_encode(['success' => true, 'data' => normalizeAccommodation($accommodation)]);
        return;
    }

    $accommodations = Database::fetchAll(
        "SELECT accommodation_id, code, name, is_default, created_at
         FROM accommodation_types
         ORDER BY is_default DESC, name ASC"
    );
    echo json_encode(['success' => true, 'data' => array_map('normalizeAccommodation', $accommodations)]);
}

function handlePost(): void
{
    global $user;

    $input = getInput();
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $name = trim((string) ($input['name'] ?? ''));
    $validationError = validateAccommodationFields($code, $name);
    if ($validationError !== null) {
        echo json_encode(['success' => false, 'error' => $validationError]);
        return;
    }

    $existing = Database::fetch(
        "SELECT accommodation_id FROM accommodation_types WHERE code = :code",
        ['code' => $code]
    );
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Accommodation code already exists.']);
        return;
    }

    $isDefault = !empty($input['is_default']) ? 1 : 0;
    $connection = Database::connection();
    $connection->beginTransaction();
    try {
        if ($isDefault) {
            Database::execute("UPDATE accommodation_types SET is_default = 0", []);
        }

        Database::execute(
            "INSERT INTO accommodation_types (code, name, is_default)
             VALUES (:code, :name, :is_default)",
            ['code' => $code, 'name' => $name, 'is_default' => $isDefault]
        );
        $newId = $connection->lastInsertId();
        $connection->commit();
    } catch (Throwable $e) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $e;
    }

    atLogActivity($user['user_id'], 'CREATE_ACCOMMODATION_TYPE', "AT-{$newId}", null, [
        'code' => $code,
        'name' => $name,
        'is_default' => $isDefault,
    ]);
    echo json_encode(['success' => true, 'message' => 'Accommodation type created.', 'accommodation_id' => $newId]);
}

function handlePut(): void
{
    global $user;

    $input = getInput();
    $id = resolveAccommodationId($input['accommodation_id'] ?? null);
    if ($id === null) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid ID.']);
        return;
    }

    $existing = Database::fetch(
        "SELECT * FROM accommodation_types WHERE accommodation_id = :id",
        ['id' => $id]
    );
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Not found.']);
        return;
    }

    $code = strtoupper(trim((string) ($input['code'] ?? $existing['code'])));
    $name = trim((string) ($input['name'] ?? $existing['name']));
    $validationError = validateAccommodationFields($code, $name);
    if ($validationError !== null) {
        echo json_encode(['success' => false, 'error' => $validationError]);
        return;
    }

    $duplicate = Database::fetch(
        "SELECT accommodation_id
         FROM accommodation_types
         WHERE code = :code AND accommodation_id != :id",
        ['code' => $code, 'id' => $id]
    );
    if ($duplicate) {
        echo json_encode(['success' => false, 'error' => 'Code already in use.']);
        return;
    }

    $isDefault = array_key_exists('is_default', $input)
        ? (!empty($input['is_default']) ? 1 : 0)
        : (int) ($existing['is_default'] ?? 0);
    $connection = Database::connection();
    $connection->beginTransaction();
    try {
        if ($isDefault) {
            Database::execute(
                "UPDATE accommodation_types SET is_default = 0 WHERE accommodation_id != :id",
                ['id' => $id]
            );
        }

        Database::execute(
            "UPDATE accommodation_types
             SET code = :code, name = :name, is_default = :is_default
             WHERE accommodation_id = :id",
            ['code' => $code, 'name' => $name, 'is_default' => $isDefault, 'id' => $id]
        );
        $connection->commit();
    } catch (Throwable $e) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $e;
    }

    atLogActivity($user['user_id'], 'UPDATE_ACCOMMODATION_TYPE', "AT-{$id}", $existing, [
        'code' => $code,
        'name' => $name,
        'is_default' => $isDefault,
    ]);
    echo json_encode(['success' => true, 'message' => 'Accommodation type updated.']);
}

function handleDelete(): void
{
    global $user;

    $input = getInput();
    $id = resolveAccommodationId($input['accommodation_id'] ?? null);
    if ($id === null) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid ID.']);
        return;
    }

    $existing = Database::fetch(
        "SELECT * FROM accommodation_types WHERE accommodation_id = :id",
        ['id' => $id]
    );
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Not found.']);
        return;
    }

    $ticketUsage = Database::fetch(
        "SELECT COUNT(*) AS cnt FROM ticket_transactions WHERE accommodation_id = :id",
        ['id' => $id]
    );
    $orderUsage = Database::fetch(
        "SELECT COUNT(*) AS cnt FROM pos_order_items WHERE accommodation_id = :id",
        ['id' => $id]
    );
    $usageCount = (int) ($ticketUsage['cnt'] ?? 0) + (int) ($orderUsage['cnt'] ?? 0);
    if ($usageCount > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete — this accommodation type is already used in transactions.',
        ]);
        return;
    }

    Database::execute(
        "DELETE FROM accommodation_types WHERE accommodation_id = :id",
        ['id' => $id]
    );
    atLogActivity($user['user_id'], 'DELETE_ACCOMMODATION_TYPE', "AT-{$id}", $existing, null);
    echo json_encode(['success' => true, 'message' => 'Accommodation type deleted.']);
}
