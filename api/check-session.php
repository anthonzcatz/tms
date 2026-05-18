<?php
/**
 * Session Check API
 * Returns session validity status for AJAX polling
 */
session_start();

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/Auth.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    $isValid = Auth::check();

    // Only check database session if the basic session check passes
    if ($isValid && !empty($_SESSION['db_session_id'])) {
        $session = Database::fetch(
            "SELECT * FROM user_sessions
             WHERE session_id = :session_id AND is_active = TRUE AND expires_at > NOW()",
            ['session_id' => $_SESSION['db_session_id']]
        );

        if (!$session) {
            // Database session expired but PHP session still exists
            // Consider the session invalid
            $isValid = false;
        }
    }

    echo json_encode([
        'valid' => $isValid,
        'timestamp' => time(),
        'user_id' => $_SESSION['user']['user_id'] ?? null
    ]);
} catch (Exception $e) {
    // On error, assume session is still valid to avoid false positives
    // Only show expired alert if we're certain the session is gone
    echo json_encode([
        'valid' => true,
        'error' => $e->getMessage()
    ]);
}
