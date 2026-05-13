<?php
/**
 * POS Passengers List API — Get passengers for dropdown
 */

header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

Auth::requireLogin();

try {
    $passengers = Database::fetchAll(
        "SELECT passenger_id, fullname, mobile_number,
                (SELECT balance FROM customer_charges WHERE passenger_id = pa.passenger_id) AS balance
         FROM passenger_accounts pa
         WHERE status = 'active'
         ORDER BY fullname ASC"
    );

    echo json_encode([
        'success' => true,
        'passengers' => $passengers
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch passengers.']);
}
