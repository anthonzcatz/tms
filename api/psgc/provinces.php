<?php
/**
 * API Endpoint: Get Provinces
 * Returns list of provinces from PSGC database
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

try {
    $provinces = Database::fetchAll("SELECT province_code, province_name FROM psgc_provinces ORDER BY province_name ASC");
    
    echo json_encode([
        'success' => true,
        'provinces' => $provinces
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch provinces: ' . $e->getMessage()]);
}
