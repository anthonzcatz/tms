-- Add ticket_number column to pos_order_items table
-- This allows storing the actual ticket number for display on receipts

ALTER TABLE `pos_order_items`
ADD COLUMN `ticket_number` varchar(50) DEFAULT NULL COMMENT 'Actual ticket number from provider (e.g., PAL-123456)' AFTER `transaction_code`;
