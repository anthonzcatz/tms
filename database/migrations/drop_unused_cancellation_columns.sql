-- Migration: Drop unused cancellation columns
-- Date: 2026-05-18
-- Description: Remove columns that are no longer used after simplifying cancellation logic

-- Drop unused columns from system_settings table
ALTER TABLE `system_settings` DROP COLUMN IF EXISTS `cancellation_auto_approve`;
ALTER TABLE `system_settings` DROP COLUMN IF EXISTS `cancellation_refund_to_wallet`;

-- Drop unused wallet_impact_applied columns from cancellation tables (no longer using wallet refunds)
ALTER TABLE `ticket_cancellations` DROP COLUMN IF EXISTS `wallet_impact_applied`;
ALTER TABLE `ticket_refunds` DROP COLUMN IF EXISTS `wallet_impact_applied`;
