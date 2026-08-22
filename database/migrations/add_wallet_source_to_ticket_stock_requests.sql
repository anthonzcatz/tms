-- Migration: Link ticket stock requests to an optional source wallet
-- Date: 2026-08-12
-- Purpose: Preserve the wallet selected in the New Stock Request modal for
-- source/audit lookup while keeping external/provider requests supported.

ALTER TABLE `ticket_stock_requests`
    ADD COLUMN IF NOT EXISTS `wallet_id` bigint(20) DEFAULT NULL
        COMMENT 'Optional source provider wallet selected for this stock request' AFTER `provider_id`;

CREATE INDEX IF NOT EXISTS `idx_ticket_stock_requests_wallet`
    ON `ticket_stock_requests` (`wallet_id`);
