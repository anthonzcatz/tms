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
    $expiresAt = date('Y-m-d H:i:s', time() + (int) env('SESSION_LIFETIME', 7200));

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

    // Update PHP session expiry time
    $_SESSION['login_time'] = time();

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
