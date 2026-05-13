<?php
/**
 * PSGC API Endpoint
 * Philippine Standard Geographic Code data
 * Provides regions, provinces, cities/municipalities, and barangays
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Check authentication
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("PSGC API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGet() {
    $action = $_GET['action'] ?? '';
    $regionCode = $_GET['region_code'] ?? '';
    $provinceCode = $_GET['province_code'] ?? '';
    $cityCode = $_GET['city_code'] ?? '';
    $search = $_GET['search'] ?? '';

    // Get regions
    if ($action === 'regions' || (empty($action) && empty($regionCode) && empty($provinceCode) && empty($cityCode))) {
        $sql = "SELECT * FROM psgc_regions ORDER BY region_name";
        $params = [];
        
        if ($search) {
            $sql .= " WHERE region_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $regions = Database::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => ['regions' => $regions]]);
        return;
    }

    // Get provinces by region
    if ($action === 'provinces' || $regionCode) {
        $sql = "SELECT * FROM psgc_provinces WHERE region_code = :region_code ORDER BY province_name";
        $params = ['region_code' => $regionCode];
        
        if ($search) {
            $sql .= " AND province_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $provinces = Database::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => ['provinces' => $provinces]]);
        return;
    }

    // Get cities/municipalities by province
    if ($action === 'cities' || $provinceCode) {
        $sql = "SELECT * FROM psgc_cities_municipalities WHERE province_code = :province_code ORDER BY city_municipality_name";
        $params = ['province_code' => $provinceCode];
        
        if ($search) {
            $sql .= " AND city_municipality_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $cities = Database::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => ['cities' => $cities]]);
        return;
    }

    // Get barangays by city
    if ($action === 'barangays' || $cityCode) {
        $sql = "SELECT * FROM psgc_barangays WHERE city_municipality_code = :city_code ORDER BY barangay_name";
        $params = ['city_code' => $cityCode];
        
        if ($search) {
            $sql .= " AND barangay_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $barangays = Database::fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => ['barangays' => $barangays]]);
        return;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
