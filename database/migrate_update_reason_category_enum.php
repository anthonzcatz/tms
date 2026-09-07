<?php
/**
 * Migration: extend ticket_cancellations.reason_category enum with PRINTER_ERROR and SYSTEM_ERROR.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    Database::execute("ALTER TABLE `ticket_cancellations`
        MODIFY COLUMN `reason_category`
            enum('CUSTOMER_REQUEST','CUSTOMER_ERROR','CASHIER_ERROR','PRINTER_ERROR','SYSTEM_ERROR','CANCEL','OTHER')
            NOT NULL DEFAULT 'OTHER'");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
