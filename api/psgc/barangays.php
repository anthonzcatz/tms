<?php
/**
 * API Endpoint: Get Barangays
 * Returns list of barangays by city/municipality from PSGC database
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

$cityCode = $_GET['city_code'] ?? '';

if (empty($cityCode)) {
    echo json_encode(['success' => false, 'error' => 'City code is required']);
    exit;
}

try {
    $barangays = Database::fetchAll(
        "SELECT barangay_code, barangay_name FROM psgc_barangays WHERE city_municipality_code = :city_code ORDER BY barangay_name ASC",
        ['city_code' => $cityCode]
    );
    
    echo json_encode([
        'success' => true,
        'barangays' => $barangays
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch barangays: ' . $e->getMessage()]);
}
