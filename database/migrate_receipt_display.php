<?php
/**
 * Migration: add receipt display toggles to system_settings.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Add new columns
    Database::execute("ALTER TABLE `system_settings`
        ADD COLUMN IF NOT EXISTS `receipt_show_item_total` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show item total amount in receipt item list',
        ADD COLUMN IF NOT EXISTS `receipt_show_subtotal` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show subtotal in receipt totals',
        ADD COLUMN IF NOT EXISTS `receipt_show_tendered` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show cash tendered and change in receipt'");

    // Preserve previous behavior: subtotal visibility was tied to base amount toggle
    Database::execute("UPDATE `system_settings` SET `receipt_show_subtotal` = `receipt_show_base_amount` WHERE `receipt_show_subtotal` = 1 AND `receipt_show_base_amount` = 0");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
