<?php
class SecurityHelper {
    // Generate CSRF token
    public static function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    // Validate CSRF token
    public static function validateCSRFToken(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Check if token matches
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        
        // Get CSRF token lifetime from system_settings (default 8 hours = 480 minutes)
        try {
            $row = Database::fetch("SELECT csrf_token_lifetime_minutes FROM system_settings LIMIT 1");
            $lifetimeMinutes = (int) ($row['csrf_token_lifetime_minutes'] ?? 480);
        } catch (\Exception $e) {
            $lifetimeMinutes = 480; // Fallback to 8 hours
        }
        $lifetimeSeconds = $lifetimeMinutes * 60;
        
        // Check if token is not expired
        if (time() - $_SESSION['csrf_token_time'] > $lifetimeSeconds) {
            self::regenerateCSRFToken();
            return false;
        }

        // Don't regenerate token after successful validation to allow multiple AJAX requests
        return true;
    }
    
    // Regenerate CSRF token
    public static function regenerateCSRFToken(): void {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    // Sanitize input
    public static function sanitizeInput($input): string|array {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    // Validate email
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Validate password strength
    public static function validatePassword(string $password): bool {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }
    
    // Hash password
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    // Verify password
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    // Generate secure random token
    public static function generateSecureToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    // Check rate limiting — DB-backed, keyed by IP so attackers cannot bypass by clearing cookies
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cutoff  = date('Y-m-d H:i:s', time() - $timeWindow);

        try {
            // Count recent attempts for this IP+key within the window
            $row = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM login_attempts
                  WHERE ip_address = :ip AND attempt_key = :key AND attempted_at > :cutoff",
                ['ip' => $ip, 'key' => $key, 'cutoff' => $cutoff]
            );
            $count = (int) ($row['cnt'] ?? 0);

            if ($count >= $maxAttempts) {
                return false; // rate limit exceeded
            }

            // Record this attempt
            Database::execute(
                "INSERT INTO login_attempts (ip_address, attempt_key, attempted_at) VALUES (:ip, :key, NOW())",
                ['ip' => $ip, 'key' => $key]
            );

            // Prune old rows for this IP to keep the table lean
            Database::execute(
                "DELETE FROM login_attempts WHERE ip_address = :ip AND attempted_at <= :cutoff",
                ['ip' => $ip, 'cutoff' => $cutoff]
            );

            return true;
        } catch (\Exception $e) {
            // If DB fails, fall back to session-based limiting (better than no limit)
            error_log('Rate limit DB error: ' . $e->getMessage());
            return self::checkRateLimitSession($key, $maxAttempts, $timeWindow);
        }
    }

    // Clear rate limit for an IP+key (call after successful login)
    public static function clearRateLimit(string $key): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            Database::execute(
                "DELETE FROM login_attempts WHERE ip_address = :ip AND attempt_key = :key",
                ['ip' => $ip, 'key' => $key]
            );
        } catch (\Exception $e) {
            // Fallback: clear session-based limit
            unset($_SESSION['rate_limit'][$key]);
        }
    }

    // Get remaining attempts
    public static function getRemainingAttempts(string $key, int $maxAttempts = 5, int $timeWindow = 300): int {
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cutoff = date('Y-m-d H:i:s', time() - $timeWindow);
        try {
            $row = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM login_attempts
                  WHERE ip_address = :ip AND attempt_key = :key AND attempted_at > :cutoff",
                ['ip' => $ip, 'key' => $key, 'cutoff' => $cutoff]
            );
            return max(0, $maxAttempts - (int) ($row['cnt'] ?? 0));
        } catch (\Exception $e) {
            return $maxAttempts;
        }
    }

    // Session-based fallback (used only when DB is unavailable)
    private static function checkRateLimitSession(string $key, int $maxAttempts, int $timeWindow): bool {
        $currentTime = time();
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($currentTime, $timeWindow) {
                return $currentTime - $timestamp < $timeWindow;
            }
        );
        if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
            return false;
        }
        $_SESSION['rate_limit'][$key][] = $currentTime;
        return true;
    }
    
    // Initialize secure session configuration (must be called BEFORE session_start())
    // NOTE: gc_maxlifetime and cookie lifetime are managed by bootstrap.php
    //       using the DB value from system_settings.session_lifetime_minutes.
    //       Do NOT set them here to avoid overriding the dynamic value.
    public static function initializeSession(): void {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
    }
    
    // Secure session configuration (called AFTER session_start())
    public static function secureSession(): void {
        // Regenerate session ID only if session is active and not already initiated
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }
    
    // Validate session integrity
    public static function validateSession(): bool {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['initiated'])) {
            $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Check IP and User Agent for session hijacking
            if ($_SESSION['ip_address'] !== $current_ip || $_SESSION['user_agent'] !== $current_ua) {
                session_destroy();
                return false;
            }
        }
        return true;
    }
    
    // Add security headers
    // NOTE: X-Content-Type-Options, X-Frame-Options, Referrer-Policy are set by bootstrap.php.
    // Only add headers here that are NOT already in bootstrap (e.g. CSP for auth pages).
    // Avoid setting X-Frame-Options: DENY — bootstrap uses SAMEORIGIN for embedded admin widgets.
    public static function addSecurityHeaders(): void {
        if (!headers_sent()) {
            // CSP: allow self + inline styles/scripts (required by admin UI libraries)
            // Fonts/images from CDN are allowed via data: and self
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: blob:; font-src \'self\' data:;');
        }
    }
}
?>
