-- Migration: Add provider-level Void Fee wallet deduction setting
-- Purpose: Allow a provider's regular POS Void Fee to debit its resolved monetary wallet.

ALTER TABLE `ticket_providers`
    ADD COLUMN IF NOT EXISTS `void_fee_wallet_enabled` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Whether regular POS Void Fees may be debited from this provider wallet'
        AFTER `status`;

UPDATE `ticket_providers`
SET `void_fee_wallet_enabled` = 0
WHERE `void_fee_wallet_enabled` IS NULL;
