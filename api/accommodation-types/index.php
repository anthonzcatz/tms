<?php
/**
 * Accommodation Types API
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $accommodations = Database::fetchAll(
            "SELECT accommodation_id, code, name FROM accommodation_types ORDER BY name ASC"
        );
        echo json_encode(['success' => true, 'data' => $accommodations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch accommodation types: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
