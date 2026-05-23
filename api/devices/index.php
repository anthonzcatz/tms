<?php
/**
 * API: /api/devices/
 *
 * GET    ?page=&search=&status=   — paginated device list with active session count
 * PUT                             — approve or block a device   { device_id, status }
 * DELETE ?id=                     — delete a device (also terminates its sessions)
 *
 * SUPER_ADMIN only.
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

header('Content-Type: application/json');

Auth::requireLogin();

$user = Auth::user();
if ($user['role_code'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    /* ---------------------------------------------------------------
       GET — list devices
    --------------------------------------------------------------- */
    case 'GET':
        $page    = max(1, (int) ($_GET['page']   ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;
        $search  = trim($_GET['search'] ?? '');
        $status  = trim($_GET['status'] ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[]            = '(sd.device_name LIKE :search OR sd.ip_address LIKE :search OR sd.device_code LIKE :search)';
            $params['search']   = '%' . $search . '%';
        }
        if (in_array($status, ['pending', 'approved', 'blocked'], true)) {
            $where[]          = 'sd.status = :status';
            $params['status'] = $status;
        }

        $whereSQL = implode(' AND ', $where);

        $total = (int) Database::fetch(
            "SELECT COUNT(*) AS cnt FROM system_devices sd WHERE {$whereSQL}",
            $params
        )['cnt'];

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $devices = Database::fetchAll(
            "SELECT
                sd.device_id,
                sd.device_code,
                sd.device_name,
                sd.device_type,
                sd.ip_address,
                sd.location_name,
                sd.device_remark,
                sd.status,
                sd.last_used_at,
                sd.created_at,
                sd.last_user_id,
                sd.last_user_username,
                sd.last_user_fullname,
                ua.profile_image AS last_user_profile_image,
                sd.city,
                sd.country,
                sd.latitude,
                sd.longitude,
                ab.username AS approved_by_username,
                sd.approved_at,
                (
                    SELECT COUNT(*)
                    FROM user_sessions us
                    WHERE us.device_id = sd.device_id
                      AND us.is_active = TRUE
                      AND us.expires_at > NOW()
                ) AS active_sessions
             FROM system_devices sd
             LEFT JOIN user_accounts ua ON ua.user_id = sd.last_user_id
             LEFT JOIN user_accounts ab ON ab.user_id = sd.approved_by
             WHERE {$whereSQL}
             ORDER BY sd.last_used_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        echo json_encode([
            'success' => true,
            'data'    => [
                'devices'      => $devices,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
        break;

    /* ---------------------------------------------------------------
       PUT — approve or block device
    --------------------------------------------------------------- */
    case 'PUT':
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!SecurityHelper::validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $deviceId = isset($data['device_id']) ? (int) $data['device_id'] : 0;
        $newStatus = $data['status'] ?? '';

        if (!$deviceId || !in_array($newStatus, ['approved', 'blocked', 'pending'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid device_id or status.']);
            exit;
        }

        $extra = [];
        $extraParams = [];
        if ($newStatus === 'approved') {
            $extra[]                      = ', approved_by = :approved_by, approved_at = :approved_at';
            $extraParams['approved_by']   = $user['user_id'];
            $extraParams['approved_at']   = date('Y-m-d H:i:s');
        }

        Database::execute(
            "UPDATE system_devices
                SET status = :status" . implode('', $extra) . "
              WHERE device_id = :device_id",
            array_merge(['status' => $newStatus, 'device_id' => $deviceId], $extraParams)
        );

        // If blocking, terminate all active sessions on this device
        if ($newStatus === 'blocked') {
            Database::execute(
                "UPDATE user_sessions
                    SET is_active = FALSE, logout_time = :logout_time, termination_reason = 'device_blocked'
                  WHERE device_id = :device_id AND is_active = TRUE",
                ['device_id' => $deviceId, 'logout_time' => date('Y-m-d H:i:s')]
            );
        }

        echo json_encode(['success' => true, 'message' => "Device {$newStatus} successfully."]);
        break;

    /* ---------------------------------------------------------------
       DELETE — remove device
    --------------------------------------------------------------- */
    case 'DELETE':
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['_token'] ?? '');
        if (!SecurityHelper::validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $deviceId = isset($_GET['id']) ? $_GET['id'] : 0;

        // Decode device_id if provided
        if ($deviceId) {
            $decodedDeviceId = IdEncoder::decode($deviceId);
            if ($decodedDeviceId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid device ID']);
                exit;
            }
            $deviceId = $decodedDeviceId;
        }

        if (!$deviceId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Device ID is required.']);
            exit;
        }

        // Terminate sessions on this device first
        Database::execute(
            "UPDATE user_sessions SET is_active = FALSE, logout_time = NOW()
              WHERE device_id = :device_id AND is_active = TRUE",
            ['device_id' => $deviceId]
        );

        Database::execute(
            "DELETE FROM system_devices WHERE device_id = :device_id",
            ['device_id' => $deviceId]
        );

        echo json_encode(['success' => true, 'message' => 'Device removed successfully.']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
