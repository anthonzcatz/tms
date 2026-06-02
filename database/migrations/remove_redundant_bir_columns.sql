-- Remove redundant BIR columns from pos_orders and pos_order_items
-- This migration removes VAT columns that were added in add_bir_tables.sql
-- VAT data should only be in bir_vat_transactions table to avoid redundancy
-- Version: 1.0
-- Date: June 2, 2026

USE `tms_db`;

-- Remove redundant VAT columns from pos_orders
ALTER TABLE `pos_orders`
DROP COLUMN IF EXISTS `vat_amount`,
DROP COLUMN IF EXISTS `vat_type`,
DROP COLUMN IF EXISTS `taxable_amount`,
DROP COLUMN IF EXISTS `non_taxable_amount`,
DROP COLUMN IF EXISTS `exemption_type`,
DROP COLUMN IF EXISTS `exemption_id_number`,
DROP COLUMN IF EXISTS `exemption_name`,
DROP INDEX IF EXISTS `idx_vat_type`;

-- Remove redundant VAT columns from pos_order_items
ALTER TABLE `pos_order_items`
DROP COLUMN IF EXISTS `vat_amount`,
DROP COLUMN IF EXISTS `vat_type`,
DROP COLUMN IF EXISTS `taxable_amount`,
DROP INDEX IF EXISTS `idx_vat_type`;

-- ============================================================
-- END OF MIGRATION
-- ============================================================
