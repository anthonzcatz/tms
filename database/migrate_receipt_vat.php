<?php
/**
 * Migration: add receipt VAT and taxable sales display toggles.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    Database::execute("ALTER TABLE `system_settings`
        ADD COLUMN IF NOT EXISTS `receipt_show_vat` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show VAT amount in receipt VAT breakdown',
        ADD COLUMN IF NOT EXISTS `receipt_show_taxable_sales` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show taxable sales amount in receipt VAT breakdown'");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
