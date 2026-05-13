<?php
/**
 * PSGC Data API — Fetch regions, provinces, cities, barangays
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$type = $_GET['type'] ?? '';

try {
    switch ($type) {
        case 'regions':
            $data = Database::fetchAll("SELECT region_code, region_name FROM psgc_regions ORDER BY region_name ASC");
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'provinces':
            $regionCode = $_GET['region_code'] ?? '';
            if (!$regionCode) { echo json_encode(['success' => false, 'error' => 'Region code required.']); exit; }
            $data = Database::fetchAll(
                "SELECT province_code, province_name FROM psgc_provinces 
                 WHERE region_code = :region_code ORDER BY province_name ASC",
                ['region_code' => $regionCode]
            );
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'cities':
            $provinceCode = $_GET['province_code'] ?? '';
            if (!$provinceCode) { echo json_encode(['success' => false, 'error' => 'Province code required.']); exit; }
            $data = Database::fetchAll(
                "SELECT city_municipality_code, city_municipality_name FROM psgc_cities_municipalities 
                 WHERE province_code = :province_code ORDER BY city_municipality_name ASC",
                ['province_code' => $provinceCode]
            );
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'barangays':
            $cityCode = $_GET['city_municipality_code'] ?? '';
            if (!$cityCode) { echo json_encode(['success' => false, 'error' => 'City code required.']); exit; }
            $data = Database::fetchAll(
                "SELECT barangay_code, barangay_name FROM psgc_barangays 
                 WHERE city_municipality_code = :city_code ORDER BY barangay_name ASC",
                ['city_code' => $cityCode]
            );
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid type.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch PSGC data.']);
}
