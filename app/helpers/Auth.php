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

    /**
     * Read + in-request-cache the three security settings from system_settings.
     * Returns an array with keys: session_lifetime_seconds, device_approval_required, max_concurrent_sessions.
     */
    private static function getSecuritySettings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = Database::fetch(
                "SELECT session_lifetime_minutes, device_approval_required, max_concurrent_sessions
                   FROM system_settings LIMIT 1"
            );
        } catch (\Exception $e) {
            $row = null;
        }
        $cache = [
            'session_lifetime_seconds'  => isset($row['session_lifetime_minutes'])
                ? (int) $row['session_lifetime_minutes'] * 60
                : (int) env('SESSION_LIFETIME', 7200),
            'device_approval_required'  => (bool) ($row['device_approval_required'] ?? false),
            'max_concurrent_sessions'   => isset($row['max_concurrent_sessions'])
                ? max(1, (int) $row['max_concurrent_sessions'])
                : 1,
        ];
        return $cache;
    }

    /**
     * Persist user in session after successful credential check.
     * Returns true on success, false if login was blocked (device pending/blocked).
     * On false, $_SESSION['login_error'] is set with the reason.
     */
    public static function login(array $user, ?float $browserLatitude = null, ?float $browserLongitude = null): bool
    {
        unset($user['password_hash']);

        session_regenerate_id(true);
        $sessionToken = session_id();

        $dbSessionId = self::createUserSession((int) $user['user_id'], $sessionToken, $browserLatitude, $browserLongitude);

        // null means device is pending approval or blocked — abort login
        if ($dbSessionId === null) {
            // Only check/set error if not already set by createUserSession
            if (empty($_SESSION['login_error']) || $_SESSION['login_error'] === 'session_create_failed') {
                // Check specifically whether device is blocked vs pending
                $deviceType = self::detectDeviceType();
                $ipAddress  = $_SERVER['REMOTE_ADDR'] ?? null;
                $device = Database::fetch(
                    "SELECT status FROM system_devices WHERE ip_address = :ip AND device_type = :type ORDER BY last_used_at DESC LIMIT 1",
                    ['ip' => $ipAddress, 'type' => $deviceType]
                );
                error_log("Login failed: createUserSession returned null. Device status=" . ($device['status'] ?? 'NOT_FOUND') . ", login_error=" . ($_SESSION['login_error'] ?? 'not_set'));
                if ($device && $device['status'] === 'blocked') {
                    $_SESSION['login_error'] = 'device_blocked';
                } elseif ($device && $device['status'] === 'pending') {
                    $_SESSION['login_error'] = 'device_pending';
                } else {
                    // Device is approved but something else failed
                    $_SESSION['login_error'] = 'session_create_failed';
                }
            }
            return false;
        }

        $_SESSION['user'] = [
            'user_id'            => (int) $user['user_id'],
            'user_code'          => $user['user_code'] ?? null,
            'fullname'           => $user['fullname'] ?? '',
            'email'              => $user['email'] ?? '',
            'username'           => $user['username'] ?? '',
            'role_id'            => $user['role_id'] ?? null,
            'role_code'          => $user['role_code'] ?? null,
            'role_name'          => $user['role_name'] ?? null,
            'branch_id'          => $user['branch_id'] ?? null,
            'default_dashboard'  => $user['default_dashboard'] ?? '/admin/dashboard/analytics',
            // Cache time restriction flag — avoids a DB query on every page load for non-restricted users
            'is_time_restricted' => !empty($user['is_time_restricted']) ? 1 : 0,
        ];
        $_SESSION['login_time']    = time();
        $_SESSION['fingerprint']   = self::fingerprint();
        $_SESSION['db_session_id'] = $dbSessionId;
        self::logActivity((int) $user['user_id'], 'LOGIN', 'AUTH', null, null, ['session_id' => $dbSessionId]);
        return true;
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
            self::redirectToLogin('authentication_required');
        }
        
        // Skip fingerprint check on page reloads to avoid session_invalid errors
        // Only check fingerprint on sensitive operations like login/logout
        // This prevents session_invalid on Ctrl+F5 refresh
        
        if (!empty($_SESSION['db_session_id'])) {
            // Check if session still exists and is active in database
            $session = Database::fetch(
                "SELECT * FROM user_sessions 
                 WHERE session_id = :session_id AND is_active = TRUE AND expires_at > :now",
                ['session_id' => $_SESSION['db_session_id'], 'now' => date('Y-m-d H:i:s')]
            );
            
            if (!$session) {
                // Session doesn't exist or is expired, logout user
                // Store user info before destroying session for proper redirect
                $userId = self::id();
                $dbSessionId = $_SESSION['db_session_id'] ?? null;

                // Check for termination reason before closing
                $terminationReason = null;
                if ($dbSessionId) {
                    $terminated = Database::fetch(
                        "SELECT termination_reason FROM user_sessions
                         WHERE session_id = :session_id",
                        ['session_id' => $dbSessionId]
                    );
                    if ($terminated && $terminated['termination_reason']) {
                        $terminationReason = $terminated['termination_reason'];
                    }
                }

                // Close database session first
                if ($dbSessionId) {
                    self::closeUserSession((int) $dbSessionId);
                }

                // Log activity before destroying session
                if ($userId) {
                    self::logActivity($userId, 'SESSION_EXPIRED', 'AUTH', null, null, ['session_id' => $dbSessionId]);
                }

                // Destroy PHP session
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();

                // Start new session to set the flag for modal alert
                session_start();
                $_SESSION['session_expired'] = true;

                // Set user-friendly termination message
                if ($terminationReason === 'max_concurrent_sessions') {
                    $_SESSION['session_terminated_message'] = 'Your session was terminated because you logged in from another device (maximum concurrent sessions reached).';
                } elseif ($terminationReason === 'device_blocked') {
                    $_SESSION['session_terminated_message'] = 'Your session was terminated because your device was blocked by an administrator.';
                } elseif ($terminationReason === 'admin_terminated') {
                    $_SESSION['session_terminated_message'] = 'Your session was terminated by an administrator.';
                } elseif ($terminationReason === 'time_restriction') {
                    $_SESSION['session_terminated_message'] = 'Your session was terminated because your allowed login time window has ended.';
                }

                self::redirectToLogin('session_expired');
                return;
            }

            // --- Active session time restriction check --------------------------------
            // Use session-cached flag to skip DB query for non-restricted users
            $userId = self::id();
            $isTimeRestricted = !empty($_SESSION['user']['is_time_restricted']);

            if ($isTimeRestricted) {
                $user = Database::fetch(
                    "SELECT is_time_restricted, allowed_login_start, allowed_login_end, allowed_days
                     FROM user_accounts
                     WHERE user_id = :uid",
                    ['uid' => $userId]
                );
            } else {
                $user = null;
            }

            if ($user && !empty($user['is_time_restricted'])) {
                $timeError = User::checkTimeRestrictions($user);
                if ($timeError !== null) {
                    // Time restriction violated - terminate session
                    User::logTimeRestrictionViolation((int) $userId, $timeError, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

                    $dbSessionId = $_SESSION['db_session_id'] ?? null;
                    if ($dbSessionId) {
                        self::closeUserSession((int) $dbSessionId, 'time_restriction');
                    }

                    self::logActivity($userId, 'SESSION_TERMINATED', 'AUTH', null, null, ['reason' => 'time_restriction']);

                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $p = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000,
                            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                    }
                    session_destroy();

                    session_start();
                    $_SESSION['session_expired'] = true;
                    $_SESSION['session_terminated_message'] = 'Your session was terminated because your allowed login time window has ended.';

                    self::redirectToLogin('session_expired');
                    return;
                }
            }

            try {
                self::touchUserSession((int) $_SESSION['db_session_id']);
            } catch (Exception $e) {
                // If session touch fails, the session might be invalid
                $userId = self::id();
                $dbSessionId = $_SESSION['db_session_id'] ?? null;
                
                if ($dbSessionId) {
                    self::closeUserSession((int) $dbSessionId);
                }
                
                if ($userId) {
                    self::logActivity($userId, 'SESSION_INVALID', 'AUTH', null, null, ['session_id' => $dbSessionId]);
                }
                
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();
                
                // Start new session to set the flag for modal alert
                session_start();
                $_SESSION['session_invalid'] = true;
                
                self::redirectToLogin('session_invalid');
            }
        }
    }

    public static function permissions(): array
    {
        if (!self::check()) {
            return [];
        }

        $roleId      = $_SESSION['user']['role_id'];
        $cacheKey    = 'perm_role_' . $roleId;

        // Return cached permissions if role hasn't changed
        if (
            isset($_SESSION['permissions'], $_SESSION['permissions_role_key']) &&
            $_SESSION['permissions_role_key'] === $cacheKey &&
            is_array($_SESSION['permissions'])
        ) {
            return $_SESSION['permissions'];
        }

        // Cache miss — load from DB and store in session
        $_SESSION['permissions'] = Database::fetchAll(
            "SELECT p.permission_id, p.permission_code, p.module_name, p.menu_url
               FROM role_permissions rp
               JOIN permissions p ON p.permission_id = rp.permission_id
              WHERE rp.role_id = :role_id",
            ['role_id' => $roleId]
        );
        $_SESSION['permissions_role_key'] = $cacheKey;

        return $_SESSION['permissions'];
    }

    /** Call this after changing a user's role to force permission re-load on next request. */
    public static function bustPermissionsCache(): void
    {
        unset($_SESSION['permissions'], $_SESSION['permissions_role_key']);
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
        // Normalize: strip trailing slash for comparison
        $normalizedUrl = rtrim($menuUrl, '/');
        foreach ($permissions as $perm) {
            if (!is_array($perm)) {
                continue;
            }
            if (isset($perm['menu_url']) && rtrim($perm['menu_url'], '/') === $normalizedUrl) {
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
        if ($reason) {
            // Only set session flags for AJAX-safe redirect (session must be active)
            if (session_status() === PHP_SESSION_ACTIVE) {
                if ($reason === 'session_expired') {
                    $_SESSION['session_expired'] = true;
                } elseif ($reason === 'authentication_required') {
                    $_SESSION['authentication_required'] = true;
                }
            }
            $url .= '?error=' . urlencode($reason);
        }
        header('Location: ' . $url);
        exit;
    }

    private static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . env('APP_KEY', 'tms'));
    }

    /**
     * Creates a DB session row and returns the new session_id.
     * Returns null when device_approval_required is ON and the device is new/pending — caller must block login.
     */
    private static function createUserSession(int $userId, string $sessionToken, ?float $browserLatitude = null, ?float $browserLongitude = null): ?int
    {
        try {
            $security  = self::getSecuritySettings();
            $lifetime  = $security['session_lifetime_seconds'];

            self::closeExpiredUserSessions();

        // --- Device detection / lookup / creation --------------------------------
        $deviceName = self::detectDeviceName();
        $deviceType = self::detectDeviceType();
        $ipAddress  = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Fetch geolocation - prefer browser coordinates if available
        if ($browserLatitude && $browserLongitude) {
            // Use browser GPS coordinates with reverse geocoding
            $geo = self::reverseGeocode($browserLatitude, $browserLongitude);
        } else {
            // Fall back to IP-based geolocation
            $geo = self::getIpGeolocation($ipAddress);
        }

        // Fallback: use IP address if geolocation fails
        if ($geo && $geo['city']) {
            $locationName = trim(($geo['city'] ?? '') . ', ' . ($geo['country'] ?? ''));
        } elseif ($ipAddress === '127.0.0.1' || $ipAddress === '::1' || $ipAddress === 'localhost') {
            $locationName = 'Localhost (Development)';
        } else {
            $locationName = $ipAddress ?? 'Unknown IP';
        }

        // Get user info for tracking
        $userInfo = Database::fetch(
            "SELECT u.username, CONCAT_WS(' ',
                e.first_name,
                IF(e.middle_name IS NOT NULL AND e.middle_name != '',
                    CONCAT(UPPER(LEFT(e.middle_name,1)), '.'), NULL),
                e.last_name
            ) AS fullname
             FROM user_accounts u
             LEFT JOIN employees e ON e.emp_id = u.emp_id
             WHERE u.user_id = :uid",
            ['uid' => $userId]
        );

        // Match device by IP + type (exact match)
        $device = Database::fetch(
            "SELECT device_id, status FROM system_devices
              WHERE ip_address   = :ip
                AND device_type  = :type
              ORDER BY last_used_at DESC LIMIT 1",
            ['ip' => $ipAddress, 'type' => $deviceType]
        );

        // If not found, try IP-only match (flexible device type)
        if (!$device) {
            $device = Database::fetch(
                "SELECT device_id, status, device_type FROM system_devices
                  WHERE ip_address = :ip
                  ORDER BY last_used_at DESC LIMIT 1",
                ['ip' => $ipAddress]
            );
            if ($device) {
                error_log("Device found by IP only: IP=$ipAddress, DB type={$device['device_type']}, Request type=$deviceType");
            }
        }

        // DEBUG: Log all devices for this IP
        $allDevices = Database::fetchAll(
            "SELECT device_id, device_type, status, last_used_at FROM system_devices WHERE ip_address = :ip ORDER BY last_used_at DESC",
            ['ip' => $ipAddress]
        );
        $deviceList = [];
        foreach ($allDevices as $d) {
            $deviceList[] = "ID={$d['device_id']}, Type={$d['device_type']}, Status={$d['status']}";
        }
        error_log("All devices for IP=$ipAddress: " . (empty($deviceList) ? 'NONE' : implode('; ', $deviceList)));

        // DEBUG: Log device detection
        error_log("Device detection: IP=$ipAddress, Type=$deviceType, Found=" . ($device ? 'YES (status: ' . $device['status'] . ')' : 'NO'));

        $isNewDevice = false;

        if ($device) {
            $deviceId = (int) $device['device_id'];

            // Update user tracking and geolocation on existing device
            Database::execute(
                "UPDATE system_devices
                    SET last_user_id = :user_id,
                        last_user_username = :username,
                        last_user_fullname = :fullname,
                        city = :city,
                        country = :country,
                        latitude = :lat,
                        longitude = :lon,
                        last_used_at = :now
                  WHERE device_id = :device_id",
                [
                    'user_id'   => $userId,
                    'username'  => $userInfo['username'] ?? null,
                    'fullname'  => $userInfo['fullname'] ?? null,
                    'city'      => $geo['city'] ?? null,
                    'country'   => $geo['country'] ?? null,
                    'lat'       => $geo['latitude'] ?? null,
                    'lon'       => $geo['longitude'] ?? null,
                    'now'       => date('Y-m-d H:i:s'),
                    'device_id' => $deviceId,
                ]
            );

            // Blocked device — hard deny
            if ($device['status'] === 'blocked') {
                error_log("Device BLOCKED: device_id=$deviceId, denying login");
                return null;
            }

            // Pending device — deny if approval is required
            $approvalSetting = Database::fetch("SELECT device_approval_required FROM system_settings LIMIT 1");
            $isApprovalRequired = (bool) ($approvalSetting['device_approval_required'] ?? false);
            error_log("Device check: status={$device['status']}, approval_required=" . ($isApprovalRequired ? 'YES' : 'NO'));
            if ($device['status'] === 'pending' && $isApprovalRequired) {
                error_log("Device PENDING with approval required: denying login");
                return null;
            }
        } else {
            // First time this device/IP is seen
            $isNewDevice = true;
            // Double-check the database setting directly
            $approvalSetting = Database::fetch("SELECT device_approval_required FROM system_settings LIMIT 1");
            $isApprovalRequired = (bool) ($approvalSetting['device_approval_required'] ?? false);
            $initialStatus = $isApprovalRequired ? 'pending' : 'approved';
            error_log("New device: Creating with status=$initialStatus (DB approval_required=" . ($isApprovalRequired ? 'YES' : 'NO') . ")");

            Database::execute(
                "INSERT INTO system_devices
                    (device_code, device_name, device_type, ip_address, location_name, device_remark, status, last_used_at,
                     last_user_id, last_user_username, last_user_fullname, city, country, latitude, longitude)
                 VALUES
                    (:device_code, :device_name, :device_type, :ip_address, :location_name, :device_remark, :status, :now,
                     :user_id, :username, :fullname, :city, :country, :lat, :lon)",
                [
                    'device_code'   => 'DEV-' . strtoupper(bin2hex(random_bytes(4))),
                    'device_name'   => $deviceName,
                    'device_type'   => $deviceType,
                    'ip_address'    => $ipAddress,
                    'location_name' => $locationName,
                    'device_remark' => 'Auto-registered on login',
                    'status'        => $initialStatus,
                    'now'           => date('Y-m-d H:i:s'),
                    'user_id'       => $userId,
                    'username'      => $userInfo['username'] ?? null,
                    'fullname'      => $userInfo['fullname'] ?? null,
                    'city'          => $geo['city'] ?? null,
                    'country'       => $geo['country'] ?? null,
                    'lat'           => $geo['latitude'] ?? null,
                    'lon'           => $geo['longitude'] ?? null,
                ]
            );
            $deviceId = (int) Database::lastInsertId();
            error_log("New device created: device_id=$deviceId, status=$initialStatus, approval_required=" . ($isApprovalRequired ? 'YES' : 'NO'));

            // New device is pending — block login
            if ($isApprovalRequired) {
                error_log("New device blocked: approval is required");
                return null;
            }
        }

        error_log("Device check passed: device_id=$deviceId, isNewDevice=" . ($isNewDevice ? 'YES' : 'NO'));

        // --- Close existing session from the same device (prevent duplicates) ----
        // This prevents multiple sessions from the same IP/device when user logs in multiple times
        $existingDeviceSession = Database::fetch(
            "SELECT session_id FROM user_sessions
              WHERE user_id = :uid AND device_id = :device_id AND is_active = TRUE
              LIMIT 1",
            ['uid' => $userId, 'device_id' => $deviceId]
        );
        if ($existingDeviceSession) {
            error_log("Closing existing session from same device: session_id={$existingDeviceSession['session_id']}, device_id=$deviceId");
            self::closeUserSession((int) $existingDeviceSession['session_id'], 'device_replaced');
        }

        // --- Max concurrent sessions enforcement (only if device is approved) ----
        $maxSessions = $security['max_concurrent_sessions'];
        if ($maxSessions === 1) {
            // Classic single-session: terminate all other active sessions
            self::closeActiveSessionsForUser($userId, 'max_concurrent_sessions');
        } else {
            // Multi-session: only close oldest when at/over the limit
            $activeSessions = Database::fetchAll(
                "SELECT session_id FROM user_sessions
                  WHERE user_id = :uid AND is_active = TRUE
                  ORDER BY login_time ASC",
                ['uid' => $userId]
            );
            $overflow = count($activeSessions) - ($maxSessions - 1);
            for ($i = 0; $i < $overflow; $i++) {
                self::closeUserSession((int) $activeSessions[$i]['session_id'], 'max_concurrent_sessions');
            }
        }

        // --- Create session row --------------------------------------------------
        // Safety check for lifetime
        if (!isset($lifetime) || $lifetime <= 0) {
            $lifetime = 7200; // Default 2 hours
            error_log("WARNING: lifetime not set or invalid, using default: $lifetime");
        }
        error_log("About to create session: user_id=$userId, device_id=$deviceId, lifetime=$lifetime");
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);
        Database::execute(
            "INSERT INTO user_sessions
                (user_id, device_id, session_token, ip_address, login_time, last_seen, expires_at, is_active)
             VALUES
                (:user_id, :device_id, :session_token, :ip_address, :login_time, :last_seen, :expires_at, TRUE)",
            [
                'user_id'       => $userId,
                'device_id'     => $deviceId,
                'login_time'    => $now,
                'last_seen'     => $now,
                'expires_at'    => $expiresAt,
                'session_token' => hash('sha256', $sessionToken),
                'ip_address'    => $ipAddress,
            ]
        );

        $newSessionId = (int) Database::lastInsertId();
        error_log("Session created: session_id=$newSessionId, device_id=$deviceId");

        Database::execute(
            "UPDATE system_devices SET last_used_at = :now WHERE device_id = :device_id",
            ['device_id' => $deviceId, 'now' => date('Y-m-d H:i:s')]
        );

        error_log("SUCCESS: Returning session_id=$newSessionId");
        return $newSessionId;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
            error_log("createUserSession EXCEPTION: " . $errorMsg);
            $_SESSION['login_error'] = 'session_create_failed';
            $_SESSION['session_error_details'] = $errorMsg;
            return null;
        }
    }

    private static function detectDeviceName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // OS detection
        $os = 'Unknown OS';
        if (preg_match('/Windows NT/i', $ua))       $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua))     $os = 'macOS';
        elseif (preg_match('/Android/i', $ua))      $os = 'Android';
        elseif (preg_match('/iPhone|iPad/i', $ua))  $os = 'iOS';
        elseif (preg_match('/Linux/i', $ua))        $os = 'Linux';

        // Browser detection
        $browser = 'Unknown Browser';
        if (preg_match('/Edg\//i', $ua))                               $browser = 'Edge';
        elseif (preg_match('/OPR\//i', $ua))                           $browser = 'Opera';
        elseif (preg_match('/Chrome\//i', $ua))                        $browser = 'Chrome';
        elseif (preg_match('/Firefox\//i', $ua))                       $browser = 'Firefox';
        elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Safari';

        return "{$browser} on {$os}";
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

    /**
     * Format timestamp for display (no timezone conversion).
     * Returns formatted date string.
     */
    public static function formatTimestamp(?string $timestamp, string $format = 'F j, Y, g:i A'): string
    {
        if (!$timestamp) return '';

        try {
            $dt = new DateTime($timestamp);
            return $dt->format($format);
        } catch (Exception $e) {
            return $timestamp; // Return original if conversion fails
        }
    }

    /**
     * Reverse geocoding: get city/country from coordinates using OpenStreetMap Nominatim.
     * Returns array with keys: city, country, latitude, longitude, or null on failure.
     */
    private static function reverseGeocode(float $latitude, float $longitude): ?array
    {
        try {
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $latitude . "&lon=" . $longitude . "&zoom=10";
            $ctx = stream_context_create([
                'http' => ['timeout' => 5, 'user_agent' => 'TMS-System/1.0'],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $data = @file_get_contents($url, false, $ctx);

            if ($data === false) {
                error_log("Reverse geocoding failed: file_get_contents returned false");
                return null;
            }

            $json = json_decode($data, true);

            if (!is_array($json) || !isset($json['address'])) {
                error_log("Reverse geocoding failed: invalid response");
                return null;
            }

            $address = $json['address'];
            return [
                'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null,
                'country' => $address['country'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        } catch (Exception $e) {
            error_log("Reverse geocoding exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch IP geolocation data (city, country, lat, lon) using ipinfo.io.
     * Returns array with keys: city, country, latitude, longitude, or null on failure.
     * Caches results per request to avoid API rate limits.
     */
    private static function getIpGeolocation(?string $ipAddress): ?array
    {
        if (!$ipAddress || $ipAddress === '127.0.0.1' || $ipAddress === '::1' || $ipAddress === 'localhost') {
            return null;
        }

        // Check in-request cache
        static $cache = [];
        if (isset($cache[$ipAddress])) {
            return $cache[$ipAddress];
        }

        try {
            $token = 'aa1ee106ce6fad';
            $url = "https://ipinfo.io/" . urlencode($ipAddress) . "/json?token=" . $token;
            $ctx = stream_context_create([
                'http' => ['timeout' => 5, 'user_agent' => 'TMS-System/1.0'],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $data = @file_get_contents($url, false, $ctx);

            if ($data === false) {
                error_log("Geolocation API failed for IP $ipAddress: file_get_contents returned false");
                $cache[$ipAddress] = null;
                return null;
            }

            $json = json_decode($data, true);

            if (!is_array($json)) {
                error_log("Geolocation API failed for IP $ipAddress: invalid JSON response");
                $cache[$ipAddress] = null;
                return null;
            }

            if (isset($json['error'])) {
                error_log("Geolocation API failed for IP $ipAddress: " . ($json['error'] ?? 'unknown error'));
                $cache[$ipAddress] = null;
                return null;
            }

            // Map ipinfo.io response to our expected format
            $loc = $json['loc'] ?? null;
            $coords = $loc ? explode(',', $loc) : null;

            $result = [
                'city' => $json['city'] ?? null,
                'country' => $json['country'] ?? null,
                'latitude' => $coords[0] ?? null,
                'longitude' => $coords[1] ?? null,
            ];

            $cache[$ipAddress] = $result;
            return $result;
        } catch (Exception $e) {
            error_log("Geolocation API exception for IP $ipAddress: " . $e->getMessage());
            $cache[$ipAddress] = null;
            return null;
        }
    }

    private static function touchUserSession(int $sessionId): void
    {
        $security  = self::getSecuritySettings();
        $lifetime  = $security['session_lifetime_seconds'];
        Database::execute(
            "UPDATE user_sessions
                SET last_seen = :now,
                    expires_at = :expires_at
              WHERE session_id = :session_id
                AND is_active = TRUE",
            ['session_id' => $sessionId, 'now' => date('Y-m-d H:i:s'), 'expires_at' => date('Y-m-d H:i:s', time() + $lifetime)]
        );
    }

    private static function closeExpiredUserSessions(): void
    {
        Database::execute(
            "UPDATE user_sessions
                SET is_active = FALSE,
                    logout_time = COALESCE(logout_time, expires_at)
              WHERE is_active = TRUE
                AND expires_at <= :now",
            ['now' => date('Y-m-d H:i:s')]
        );
    }

    private static function closeActiveSessionsForUser(int $userId, ?string $reason = null): void
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE user_sessions
                SET is_active = FALSE,
                    logout_time = :logout_time,
                    last_seen = :last_seen";

        $params = ['user_id' => $userId, 'logout_time' => $now, 'last_seen' => $now];

        if ($reason !== null) {
            $sql .= ", termination_reason = :reason";
            $params['reason'] = $reason;
        }

        $sql .= " WHERE user_id = :user_id AND is_active = TRUE";
        Database::execute($sql, $params);
    }

    private static function closeUserSession(int $sessionId, ?string $reason = null): void
    {
        $sql = "UPDATE user_sessions SET is_active = FALSE, logout_time = :now";
        $params = ['session_id' => $sessionId, 'now' => date('Y-m-d H:i:s')];

        if ($reason !== null) {
            $sql .= ", termination_reason = :reason";
            $params['reason'] = $reason;
        }

        $sql .= " WHERE session_id = :session_id";
        Database::execute($sql, $params);
    }

    private static function logActivity(?int $userId, string $action, string $moduleName, ?string $referenceCode = null, $oldValue = null, $newValue = null): void
    {
        Database::execute(
            "INSERT INTO activity_logs
                (user_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
             VALUES
                (:user_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, :now)",
            [
                'user_id' => $userId,
                'now' => date('Y-m-d H:i:s'),
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
