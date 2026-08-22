<?php
/**
 * Migration: split service fee display and add total source option.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    Database::execute("ALTER TABLE `system_settings`
        ADD COLUMN IF NOT EXISTS `receipt_show_service_fee_total` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show aggregate service fee total in receipt totals',
        ADD COLUMN IF NOT EXISTS `receipt_total_source` varchar(50) NOT NULL DEFAULT 'grand_total' COMMENT 'Source amount for the receipt grand total line: grand_total, service_fee, base_amount, subtotal'");

    // Backfill: new service fee total toggle follows existing service fee toggle
    Database::execute("UPDATE `system_settings` SET `receipt_show_service_fee_total` = `receipt_show_service_fee` WHERE `setting_id` = 1");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}


