<?php
/**
 * Migration: add ticket_action column to ticket_transactions and pos_order_items.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    Database::execute("ALTER TABLE `ticket_transactions`
        ADD COLUMN IF NOT EXISTS `ticket_action` varchar(50) DEFAULT NULL AFTER `ticket_number`");

    Database::execute("ALTER TABLE `pos_order_items`
        ADD COLUMN IF NOT EXISTS `ticket_action` varchar(50) DEFAULT NULL AFTER `variant_id`");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
