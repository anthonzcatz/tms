<?php
/**
 * Session Refresh API
 * Extends the session when user is active
 */
require_once dirname(__DIR__) . '/config/bootstrap.php';

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

    // Extend the session expiry time in database — read from system_settings for consistency
    $settingsRow = Database::fetch("SELECT session_lifetime_minutes FROM system_settings LIMIT 1");
    $sessionLifetime = isset($settingsRow['session_lifetime_minutes'])
        ? max(300, (int) $settingsRow['session_lifetime_minutes'] * 60)
        : max(300, (int) env('SESSION_LIFETIME', 7200));
    $expiresAt = date('Y-m-d H:i:s', time() + $sessionLifetime);

    Database::execute(
        "UPDATE user_sessions
         SET last_seen  = NOW(),
             expires_at = :expires_at
         WHERE session_id = :session_id
           AND user_id    = :user_id
           AND is_active  = TRUE",
        [
            'session_id' => $dbSessionId,
            'user_id'    => $userId,
            'expires_at' => $expiresAt,
        ]
    );

    // NOTE: Cookie sliding and gc_maxlifetime are already handled by bootstrap.php
    // on every page request — no need to re-issue the cookie here.
    // session_regenerate_id is intentionally omitted from this hot-path endpoint
    // because it would break the session_token stored in user_sessions.

    echo json_encode([
        'success'    => true,
        'expires_at' => $expiresAt,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
