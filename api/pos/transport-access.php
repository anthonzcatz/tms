<?php
/**
 * POS cashier transportation-type access API.
 * Cashiers may view and update their own type-based assignments only.
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/CashierTransportAccess.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$userId = (int) ($user['user_id'] ?? 0);

if (($user['role_code'] ?? '') !== 'CASHIER' || $userId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Transportation access self-service is available to cashiers only.']);
    exit;
}

function cashierTransportAccessPayload(int $userId): array
{
    $account = Database::fetch(
        "SELECT ua.has_restricted_transport, ur.role_code
         FROM user_accounts ua
         LEFT JOIN user_roles ur ON ur.role_id = ua.role_id
         WHERE ua.user_id = :user_id
         LIMIT 1",
        ['user_id' => $userId]
    );

    $restricted = ($account['role_code'] ?? '') === 'CASHIER'
        && (int) ($account['has_restricted_transport'] ?? 0) === 1;
    $assignments = $restricted
        ? Database::fetchAll(
            "SELECT provider_id, transport_type
             FROM cashier_transport_assignments
             WHERE user_id = :user_id
             ORDER BY assignment_id ASC",
            ['user_id' => $userId]
        )
        : [];

    $providerIds = array_values(array_unique(array_filter(array_map(
        static fn(array $assignment): int => (int) ($assignment['provider_id'] ?? 0),
        $assignments
    ))));
    $transportTypes = array_values(array_unique(array_filter(array_map(
        static fn(array $assignment): string => (string) ($assignment['transport_type'] ?? ''),
        $assignments
    ))));

    $allowedTypes = CashierTransportAccess::getSupportedTransportTypes();
    $allowedTypeLabels = [];
    foreach ($allowedTypes as $type) {
        $allowedTypeLabels[$type] = $type;
    }
    foreach (Database::getProviderTypes() as $row) {
        $allowedTypeLabels[$row['type_code']] = $row['type_label'];
    }

    return [
        'restricted' => $restricted,
        'mode' => $providerIds ? 'provider' : ($restricted ? 'transport_type' : 'all'),
        'provider_ids' => $providerIds,
        'transport_types' => $transportTypes,
        'allowed_types' => $allowedTypeLabels,
        'labels' => $allowedTypeLabels,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'data' => cashierTransportAccessPayload($userId)]);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!SecurityHelper::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$allowedTypes = CashierTransportAccess::getSupportedTransportTypes();
$transportTypes = array_values(array_unique(array_filter(array_map(
    static fn($type): string => strtolower(trim((string) $type)),
    (array) ($input['transport_types'] ?? [])
))));

if (!$transportTypes || array_diff($transportTypes, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Select at least one valid transportation type.']);
    exit;
}

try {
    Database::connection()->beginTransaction();

    $account = Database::fetch(
        "SELECT ua.has_restricted_transport, ur.role_code
         FROM user_accounts ua
         LEFT JOIN user_roles ur ON ur.role_id = ua.role_id
         WHERE ua.user_id = :user_id
         LIMIT 1
         FOR UPDATE",
        ['user_id' => $userId]
    );
    if (($account['role_code'] ?? '') !== 'CASHIER') {
        throw new RuntimeException('Transportation access self-service is available to cashiers only.');
    }
    if ((int) ($account['has_restricted_transport'] ?? 0) !== 1) {
        throw new RuntimeException('Your transportation access is not set to By Transportation Type. Ask an administrator to enable it first.');
    }

    $providerAssignment = Database::fetch(
        "SELECT assignment_id
         FROM cashier_transport_assignments
         WHERE user_id = :user_id AND provider_id IS NOT NULL
         LIMIT 1
         FOR UPDATE",
        ['user_id' => $userId]
    );
    if ($providerAssignment) {
        throw new RuntimeException('Your access is managed by specific provider assignments. Ask an administrator to update it.');
    }

    Database::execute(
        "DELETE FROM cashier_transport_assignments
         WHERE user_id = :user_id AND provider_id IS NULL",
        ['user_id' => $userId]
    );

    foreach ($transportTypes as $transportType) {
        Database::execute(
            "INSERT INTO cashier_transport_assignments
                (user_id, provider_id, transport_type, created_by, created_at)
             VALUES (:user_id, NULL, :transport_type, :created_by, NOW())",
            [
                'user_id' => $userId,
                'transport_type' => $transportType,
                'created_by' => $userId,
            ]
        );
    }

    CashierTransportAccess::invalidate($userId);
    Database::connection()->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transportation type access updated.',
        'data' => cashierTransportAccessPayload($userId),
    ]);
} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
