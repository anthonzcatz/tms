<?php
/**
 * API Router
 * Auto-discovers and routes API endpoints
 * Scalable solution - no need to add .htaccess rules for each API
 */

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove query string
$requestUri = strtok($requestUri, '?');

// Get the path relative to /api/ (strip base path like /TMS)
$path = str_replace('/api', '', $requestUri);
$path = trim($path, '/');

// Remove base path if present (e.g., TMS)
$segments = explode('/', $path);
if (!empty($segments[0]) && strtolower($segments[0]) === 'tms') {
    array_shift($segments);
}
$path = implode('/', $segments);
$path = trim($path, '/');

// Split into segments
$segments = explode('/', $path);

// If no segments, return 404
if (empty($segments) || $segments[0] === '') {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// Get the API name (first segment)
$apiName = $segments[0];

// Check if it's a nested endpoint (e.g., permissions/assign) - check this FIRST
if (count($segments) > 1) {
    $endpoint = $segments[1];
    $apiFile = __DIR__ . "/{$apiName}/{$endpoint}.php";
    
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

// Build the file path for index.php
$apiFile = __DIR__ . "/{$apiName}/index.php";

// Check if the API directory exists with index.php
if (file_exists($apiFile)) {
    require $apiFile;
    exit;
}

// Check if it's a single file API (e.g., users.php)
$apiFile = __DIR__ . "/{$apiName}.php";
if (file_exists($apiFile)) {
    require $apiFile;
    exit;
}

// 404 if nothing found
http_response_code(404);
echo json_encode(['error' => 'API endpoint not found']);
