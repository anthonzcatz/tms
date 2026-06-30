<?php
/**
 * API Helper: Encode ID
 * 
 * GET ?id=123 - Returns encoded ID for URL usage
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

header('Content-Type: application/json');

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$encoded = IdEncoder::encode($id);

echo json_encode([
    'success' => true,
    'encoded' => $encoded,
    'original' => $id
]);
