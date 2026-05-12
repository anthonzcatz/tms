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
        
        // Check if token is not expired (1 hour)
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            self::regenerateCSRFToken();
            return false;
        }
        
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
    
    // Check rate limiting
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $currentTime = time();
        
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Remove old attempts outside time window
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($currentTime, $timeWindow) {
                return $currentTime - $timestamp < $timeWindow;
            }
        );
        
        // Check if rate limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION['rate_limit'][$key][] = $currentTime;
        return true;
    }
    
    // Get remaining attempts
    public static function getRemainingAttempts(string $key, int $maxAttempts = 5, int $timeWindow = 300): int {
        $currentTime = time();
        
        if (!isset($_SESSION['rate_limit'][$key])) {
            return $maxAttempts;
        }
        
        // Remove old attempts outside time window
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($currentTime, $timeWindow) {
                return $currentTime - $timestamp < $timeWindow;
            }
        );
        
        return max(0, $maxAttempts - count($_SESSION['rate_limit'][$key]));
    }
    
    // Initialize secure session configuration (must be called BEFORE session_start())
    public static function initializeSession(): void {
        // Set secure session parameters BEFORE session starts
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', 7200); // 2 hours
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
    public static function addSecurityHeaders(): void {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\';');
        }
    }
}
?>
