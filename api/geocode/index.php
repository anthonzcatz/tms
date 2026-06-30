<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$type = $_GET['type'] ?? '';
$parentCode = $_GET['parent_code'] ?? '';

try {
    switch ($type) {
        case 'regions':
            $rows = Database::fetchAll("SELECT region_code, region_name, short_name FROM psgc_regions ORDER BY region_name");
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'provinces':
            if (!$parentCode) {
                echo json_encode(['success' => false, 'error' => 'Missing region_code']);
                exit;
            }
            $rows = Database::fetchAll(
                "SELECT province_code, province_name FROM psgc_provinces WHERE region_code = :code ORDER BY province_name",
                ['code' => $parentCode]
            );
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'cities':
            if (!$parentCode) {
                echo json_encode(['success' => false, 'error' => 'Missing province_code']);
                exit;
            }
            $rows = Database::fetchAll(
                "SELECT city_municipality_code, city_municipality_name FROM psgc_cities_municipalities WHERE province_code = :code ORDER BY city_municipality_name",
                ['code' => $parentCode]
            );
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'barangays':
            if (!$parentCode) {
                echo json_encode(['success' => false, 'error' => 'Missing city_municipality_code']);
                exit;
            }
            $rows = Database::fetchAll(
                "SELECT barangay_code, barangay_name FROM psgc_barangays WHERE city_municipality_code = :code ORDER BY barangay_name",
                ['code' => $parentCode]
            );
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'full':
            $codes = [
                'province_code' => $_GET['province_code'] ?? '',
                'city_municipality_code' => $_GET['city_municipality_code'] ?? '',
                'barangay_code' => $_GET['barangay_code'] ?? ''
            ];
            $result = [];
            if ($codes['province_code']) {
                $result['province'] = Database::fetch(
                    "SELECT province_code, province_name, region_code FROM psgc_provinces WHERE province_code = :code",
                    ['code' => $codes['province_code']]
                );
            }
            if ($codes['city_municipality_code']) {
                $result['city'] = Database::fetch(
                    "SELECT city_municipality_code, city_municipality_name FROM psgc_cities_municipalities WHERE city_municipality_code = :code",
                    ['code' => $codes['city_municipality_code']]
                );
            }
            if ($codes['barangay_code']) {
                $result['barangay'] = Database::fetch(
                    "SELECT barangay_code, barangay_name FROM psgc_barangays WHERE barangay_code = :code",
                    ['code' => $codes['barangay_code']]
                );
            }
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid geocode type']);
    }
} catch (Exception $e) {
    error_log('Geocode API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
