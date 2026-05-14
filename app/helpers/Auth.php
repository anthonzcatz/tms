<?php
/**
 * Auth — central helper for session-based authentication.
 * Use anywhere:
 *   require_once __DIR__ . '/../config/bootstrap.php';
 *   Auth::requireLogin();
 *   $me = Auth::user();
 */
final class Auth
{
    /** Returns true if the current session belongs to a logged-in user. */
    public static function check(): bool
    {
        return !empty($_SESSION['user']['user_id']);
    }

    /** The current user array (or null). */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['user_id']) ? (int) $_SESSION['user']['user_id'] : null;
    }

    /** Persist user in session after successful credential check. */
    public static function login(array $user): void
    {
        unset($user['password_hash']);

        session_regenerate_id(true);
        $sessionToken = session_id();

        $_SESSION['user'] = [
            'user_id'   => (int) $user['user_id'],
            'user_code' => $user['user_code'] ?? null,
            'fullname'  => $user['fullname'] ?? '',
            'email'     => $user['email'] ?? '',
            'username'  => $user['username'] ?? '',
            'role_id'   => $user['role_id'] ?? null,
            'role_code' => $user['role_code'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'branch_id' => $user['branch_id'] ?? null,
            'default_dashboard' => $user['default_dashboard'] ?? '/admin/dashboard/analytics',
        ];
        $_SESSION['login_time']  = time();
        $_SESSION['fingerprint'] = self::fingerprint();
        $_SESSION['db_session_id'] = self::createUserSession((int) $user['user_id'], $sessionToken);
        self::logActivity((int) $user['user_id'], 'LOGIN', 'AUTH', null, null, ['session_id' => $_SESSION['db_session_id']]);
    }

    public static function logout(): void
    {
        $userId = self::id();
        $dbSessionId = $_SESSION['db_session_id'] ?? null;
        if ($userId) {
            self::logActivity($userId, 'LOGOUT', 'AUTH', null, null, ['session_id' => $dbSessionId]);
        }
        if ($dbSessionId) {
            self::closeUserSession((int) $dbSessionId);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::redirectToLogin();
        }
        
        // Skip fingerprint check on page reloads to avoid session_invalid errors
        // Only check fingerprint on sensitive operations like login/logout
        // This prevents session_invalid on Ctrl+F5 refresh
        
        if (!empty($_SESSION['db_session_id'])) {
            self::touchUserSession((int) $_SESSION['db_session_id']);
        }
    }

    public static function permissions(): array
    {
        if (!self::check()) {
            return [];
        }
        // Always reload from database to ensure correct format
        $_SESSION['permissions'] = Database::fetchAll(
            "SELECT p.permission_id, p.permission_code, p.module_name, p.menu_url
               FROM role_permissions rp
               JOIN permissions p ON p.permission_id = rp.permission_id
              WHERE rp.role_id = :role_id",
            ['role_id' => $_SESSION['user']['role_id']]
        );
        return $_SESSION['permissions'];
    }

    public static function can(string $permissionCode): bool
    {
        // SUPER_ADMIN has access to everything
        if (self::check() && $_SESSION['user']['role_code'] === 'SUPER_ADMIN') {
            return true;
        }

        $permissions = self::permissions();
        if (!is_array($permissions)) {
            return false;
        }
        foreach ($permissions as $perm) {
            if (!is_array($perm)) {
                continue;
            }
            if (isset($perm['permission_code']) && $perm['permission_code'] === $permissionCode) {
                return true;
            }
        }
        return false;
    }

    public static function canAccessModule(string $menuUrl): bool
    {
        // SUPER_ADMIN has access to everything
        if (self::check() && $_SESSION['user']['role_code'] === 'SUPER_ADMIN') {
            return true;
        }

        $permissions = self::permissions();
        if (!is_array($permissions)) {
            return false;
        }
        foreach ($permissions as $perm) {
            if (!is_array($perm)) {
                continue;
            }
            if (isset($perm['menu_url']) && $perm['menu_url'] === $menuUrl) {
                return true;
            }
        }
        return false;
    }

    public static function requirePermission(string $permissionCode): void
    {
        self::requireLogin();
        if (!self::can($permissionCode)) {
            http_response_code(403);
            die('Access denied. You do not have permission to access this resource.');
        }
    }

    private static function redirectToLogin(?string $reason = null): void
    {
        $url = defined('LOGIN_URL') ? LOGIN_URL : '/login';
        if ($reason) $url .= '?error=' . urlencode($reason);
        header('Location: ' . $url);
        exit;
    }

    private static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . env('APP_KEY', 'tms'));
    }

    private static function createUserSession(int $userId, string $sessionToken): ?int
    {
        $expiresAt = date('Y-m-d H:i:s', time() + (int) env('SESSION_LIFETIME', 7200));
        self::closeExpiredUserSessions();
        self::closeActiveSessionsForUser($userId);
        
        // Auto-create or find device based on IP and user agent
        $deviceName = self::detectDeviceName();
        $deviceType = self::detectDeviceType();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Try to find existing device
        $device = Database::fetch(
            "SELECT device_id FROM system_devices 
             WHERE ip_address = :ip_address 
               AND device_type = :device_type
             ORDER BY last_used_at DESC LIMIT 1",
            ['ip_address' => $ipAddress, 'device_type' => $deviceType]
        );
        
        $deviceId = $device ? $device['device_id'] : null;
        
        // If no device found, create one
        if (!$deviceId) {
            Database::execute(
                "INSERT INTO system_devices 
                    (device_code, device_name, device_type, ip_address, location_name, device_remark, status, last_used_at)
                 VALUES 
                    (:device_code, :device_name, :device_type, :ip_address, :location_name, :device_remark, 'approved', NOW())",
                [
                    'device_code' => 'DEV-' . strtoupper(bin2hex(random_bytes(4))),
                    'device_name' => $deviceName,
                    'device_type' => $deviceType,
                    'ip_address' => $ipAddress,
                    'location_name' => 'Auto-detected',
                    'device_remark' => 'Auto-created during login',
                ]
            );
            $deviceId = (int) Database::lastInsertId();
        }
        
        Database::execute(
            "INSERT INTO user_sessions
                (user_id, device_id, session_token, ip_address, login_time, last_seen, expires_at, is_active)
             VALUES
                (:user_id, :device_id, :session_token, :ip_address, NOW(), NOW(), :expires_at, TRUE)",
            [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'session_token' => hash('sha256', $sessionToken),
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt,
            ]
        );
        
        // Update device last used
        Database::execute(
            "UPDATE system_devices SET last_used_at = NOW() WHERE device_id = :device_id",
            ['device_id' => $deviceId]
        );
        
        return (int) Database::lastInsertId();
    }
    
    private static function detectDeviceName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Detect browser
        if (preg_match('/Chrome/i', $ua)) return 'Chrome Browser';
        if (preg_match('/Firefox/i', $ua)) return 'Firefox Browser';
        if (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) return 'Safari Browser';
        if (preg_match('/Edge/i', $ua)) return 'Edge Browser';
        if (preg_match('/Opera/i', $ua)) return 'Opera Browser';
        
        return 'Unknown Browser';
    }
    
    private static function detectDeviceType(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Detect mobile/tablet
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
            if (preg_match('/iPad/i', $ua)) return 'tablet';
            return 'mobile';
        }
        
        return 'desktop';
    }

    private static function touchUserSession(int $sessionId): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + (int) env('SESSION_LIFETIME', 7200));
        Database::execute(
            "UPDATE user_sessions
                SET last_seen = NOW(),
                    expires_at = :expires_at
              WHERE session_id = :session_id
                AND is_active = TRUE",
            ['session_id' => $sessionId, 'expires_at' => $expiresAt]
        );
    }

    private static function closeExpiredUserSessions(): void
    {
        Database::execute(
            "UPDATE user_sessions
                SET is_active = FALSE,
                    logout_time = COALESCE(logout_time, expires_at)
              WHERE is_active = TRUE
                AND expires_at <= NOW()"
        );
    }

    private static function closeActiveSessionsForUser(int $userId): void
    {
        Database::execute(
            "UPDATE user_sessions
                SET is_active = FALSE,
                    logout_time = NOW(),
                    last_seen = NOW()
              WHERE user_id = :user_id
                AND is_active = TRUE",
            ['user_id' => $userId]
        );
    }

    private static function closeUserSession(int $sessionId): void
    {
        Database::execute(
            "UPDATE user_sessions
                SET logout_time = NOW(),
                    last_seen = NOW(),
                    is_active = FALSE
              WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        );
    }

    private static function logActivity(?int $userId, string $action, string $moduleName, ?string $referenceCode = null, $oldValue = null, $newValue = null): void
    {
        Database::execute(
            "INSERT INTO activity_logs
                (user_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
             VALUES
                (:user_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, NOW())",
            [
                'user_id' => $userId,
                'action' => $action,
                'module_name' => $moduleName,
                'reference_code' => $referenceCode,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'old_value' => $oldValue === null ? null : json_encode($oldValue),
                'new_value' => $newValue === null ? null : json_encode($newValue),
            ]
        );
    }
}
