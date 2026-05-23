<?php
/**
 * API: /api/sessions/
 *
 * GET    ?page=&user_id=&active_only=1   — paginated session list
 * DELETE ?id=        — force-terminate a single session
 * DELETE ?user_id=   — terminate ALL sessions for a user
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
       GET — list sessions
    --------------------------------------------------------------- */
    case 'GET':
        $page       = max(1, (int) ($_GET['page']        ?? 1));
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;
        $filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : 0;
        $activeOnly = !empty($_GET['active_only']);

        // Decode user_id if provided
        if ($filterUser) {
            $decodedUserId = IdEncoder::decode($filterUser);
            if ($decodedUserId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
                exit;
            }
            $filterUser = $decodedUserId;
        }

        $where  = ['1=1'];
        $params = [];

        if ($filterUser) {
            $where[]             = 'us.user_id = :user_id';
            $params['user_id']   = $filterUser;
        }
        if ($activeOnly) {
            $where[] = 'us.is_active = TRUE AND us.expires_at > :now';
            $params['now'] = date('Y-m-d H:i:s');
        }

        $whereSQL = implode(' AND ', $where);

        $total = (int) Database::fetch(
            "SELECT COUNT(*) AS cnt FROM user_sessions us WHERE {$whereSQL}",
            $params
        )['cnt'];

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $sessions = Database::fetchAll(
            "SELECT
                us.session_id,
                us.user_id,
                ua.username,
                ua.email,
                ua.profile_image,
                CONCAT_WS(' ',
                    e.first_name,
                    IF(e.middle_name IS NOT NULL AND e.middle_name != '',
                        CONCAT(UPPER(LEFT(e.middle_name,1)), '.'), NULL),
                    e.last_name
                ) AS fullname,
                us.device_id,
                sd.device_name,
                sd.device_type,
                sd.device_code,
                us.ip_address,
                us.login_time,
                us.last_seen,
                us.logout_time,
                us.expires_at,
                us.is_active
             FROM user_sessions us
             JOIN user_accounts ua ON ua.user_id = us.user_id
             LEFT JOIN employees e  ON e.emp_id   = ua.emp_id
             LEFT JOIN system_devices sd ON sd.device_id = us.device_id
             WHERE {$whereSQL}
             ORDER BY us.login_time DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        echo json_encode([
            'success' => true,
            'data'    => [
                'sessions'     => $sessions,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
        break;

    /* ---------------------------------------------------------------
       DELETE — terminate session(s)
    --------------------------------------------------------------- */
    case 'DELETE':
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['_token'] ?? '');
        if (!SecurityHelper::validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $sessionId = isset($_GET['id'])      ? $_GET['id']      : 0;
        $userId    = isset($_GET['user_id']) ? $_GET['user_id'] : 0;

        // Decode session_id if provided
        if ($sessionId) {
            $decodedSessionId = IdEncoder::decode($sessionId);
            if ($decodedSessionId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
                exit;
            }
            $sessionId = $decodedSessionId;
        }

        // Decode user_id if provided
        if ($userId) {
            $decodedUserId = IdEncoder::decode($userId);
            if ($decodedUserId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
                exit;
            }
            $userId = $decodedUserId;
        }

        if ($sessionId) {
            Database::execute(
                "UPDATE user_sessions
                    SET is_active = FALSE, logout_time = :logout_time, termination_reason = 'admin_terminated'
                  WHERE session_id = :session_id",
                ['session_id' => $sessionId, 'logout_time' => date('Y-m-d H:i:s')]
            );
            echo json_encode(['success' => true, 'message' => 'Session terminated.']);
        } elseif ($userId) {
            // Prevent admin from wiping their own session
            if ($userId === (int) $user['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Cannot terminate your own sessions.']);
                exit;
            }
            Database::execute(
                "UPDATE user_sessions
                    SET is_active = FALSE, logout_time = :logout_time, termination_reason = 'admin_terminated'
                  WHERE user_id = :user_id AND is_active = TRUE",
                ['user_id' => $userId, 'logout_time' => date('Y-m-d H:i:s')]
            );
            echo json_encode(['success' => true, 'message' => 'All sessions for user terminated.']);
        } else {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Provide id or user_id.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
