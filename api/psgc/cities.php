<?php
/**
 * API Endpoint: Get Cities/Municipalities
 * Returns list of cities/municipalities by province from PSGC database
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

$provinceCode = $_GET['province_code'] ?? '';

if (empty($provinceCode)) {
    echo json_encode(['success' => false, 'error' => 'Province code is required']);
    exit;
}

try {
    $cities = Database::fetchAll(
        "SELECT city_municipality_code, city_municipality_name FROM psgc_cities_municipalities WHERE province_code = :province_code ORDER BY city_municipality_name ASC",
        ['province_code' => $provinceCode]
    );
    
    echo json_encode([
        'success' => true,
        'cities' => $cities
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch cities: ' . $e->getMessage()]);
}
