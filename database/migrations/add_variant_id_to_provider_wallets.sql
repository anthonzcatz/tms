-- Migration: Add variant-level wallet support
-- Date: 2026-07-17
-- Purpose: Allow each ticket variant to maintain its own wallet balance per branch/provider.

-- 1. Add variant_id to provider_wallets
ALTER TABLE `provider_wallets`
    ADD COLUMN `variant_id` BIGINT NULL AFTER `branch_id`,
    ADD CONSTRAINT `fk_provider_wallets_variant`
        FOREIGN KEY (`variant_id`) REFERENCES `provider_ticket_variants` (`variant_id`)
        ON DELETE RESTRICT;

-- 2. Replace the old provider/branch unique key with a provider/branch/variant key.
--    Note: MySQL treats NULL as distinct, so provider-level wallets (variant_id IS NULL)
--    remain unique at the application level; the API enforces a single NULL wallet per provider/branch.
ALTER TABLE `provider_wallets`
    DROP INDEX `uq_wallet`,
    ADD UNIQUE KEY `uq_provider_wallet_variant` (`provider_id`, `branch_id`, `variant_id`);
