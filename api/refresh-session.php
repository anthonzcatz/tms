<?php
/**
 * Session Refresh API
 * Extends the session when user is active
 */
session_start();

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/Auth.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!Auth::check()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $userId = Auth::id();
    $dbSessionId = $_SESSION['db_session_id'] ?? null;

    if (!$dbSessionId) {
        echo json_encode(['success' => false, 'error' => 'No database session']);
        exit;
    }

    // Extend the session expiry time in database
    $sessionLifetime = (int) env('SESSION_LIFETIME', 7200);
    $expiresAt = date('Y-m-d H:i:s', time() + $sessionLifetime);

    Database::execute(
        "UPDATE user_sessions
         SET last_seen = NOW(),
             expires_at = :expires_at
         WHERE session_id = :session_id
           AND user_id = :user_id
           AND is_active = TRUE",
        [
            'session_id' => $dbSessionId,
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ]
    );

    // Regenerate session ID periodically for security (every 30 minutes)
    if (!isset($_SESSION['last_regenerated']) || (time() - $_SESSION['last_regenerated']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }

    // Update PHP session expiry time
    $_SESSION['login_time'] = time();

    // Extend the session cookie lifetime (sliding expiration)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    
    setcookie(
        session_name(),
        session_id(),
        [
            'expires' => time() + $sessionLifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    echo json_encode([
        'success' => true,
        'expires_at' => $expiresAt
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
