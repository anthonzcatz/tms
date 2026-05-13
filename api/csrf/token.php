<?php
/**
 * CSRF Token Refresh Endpoint
 * Returns a fresh CSRF token for the current session
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Regenerate and return a fresh token
SecurityHelper::regenerateCSRFToken();
echo json_encode(['success' => true, 'csrf_token' => SecurityHelper::generateCSRFToken()]);
