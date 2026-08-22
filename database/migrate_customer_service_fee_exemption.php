<?php
/**
 * Migration: add per-customer service fee handling in customer_charges
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrations/add_customer_service_fee_exemption.sql');
    if ($sql === false) {
        throw new Exception('Could not read migration SQL file.');
    }

    // Execute statements one by one to handle IF NOT EXISTS gracefully
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        Database::execute($stmt);
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
