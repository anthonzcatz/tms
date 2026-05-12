<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/_guard.php';

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string if present
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Remove base URL path
$basePath = parse_url(BASE_URL, PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$path = trim($path, '/');

// Remove 'admin' prefix if present
if (strpos($path, 'admin/') === 0) {
    $path = substr($path, 6);
}

// If path is empty, redirect to analytics
if (empty($path) || $path === 'admin') {
    header('Location: ' . BASE_URL . '/admin/dashboard/analytics');
    exit;
}

// Auto-discover routes for folders
// This function automatically maps folder/* to folder/*.php
function autoDiscoverRoutes($baseDir, $folder) {
    $routes = [];
    $folderPath = $baseDir . '/' . $folder;
    if (is_dir($folderPath)) {
        $files = scandir($folderPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $routeName = pathinfo($file, PATHINFO_FILENAME);
                $routes[$folder . '/' . $routeName] = $folder . '/' . $file;
            }
        }
    }
    return $routes;
}

// Define route mappings
$routes = [];

// Auto-discover dashboard routes
$routes = array_merge($routes, autoDiscoverRoutes(__DIR__, 'dashboard'));

// Auto-discover settings routes
$routes = array_merge($routes, autoDiscoverRoutes(__DIR__, 'settings'));

// Future folders - uncomment to enable auto-discovery
// $routes = array_merge($routes, autoDiscoverRoutes(__DIR__, 'users'));
// $routes = array_merge($routes, autoDiscoverRoutes(__DIR__, 'reports'));

// Manual routes (for special cases or files not in standard folders)
// Add manual routes here if needed for special handling
// 'custom/page' => 'custom/special.php';

// Check if route exists
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

// 404 - Page not found
http_response_code(404);
echo '<h1>404 - Page Not Found</h1><p>The requested page does not exist.</p>';
exit;
