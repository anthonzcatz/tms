-- Migration: Add provider-level POS wallet deduction settings.
-- All-charges includes normal sale service fee and regular Void fees.

ALTER TABLE `ticket_providers`
    ADD COLUMN IF NOT EXISTS `wallet_deduct_all_charges` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Deduct base amount, normal sale service fee, regular Void Fee and Void Service Fee from provider wallet'
        AFTER `status`,
    ADD COLUMN IF NOT EXISTS `wallet_deduct_base_only` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Deduct base amount only from provider wallet'
        AFTER `wallet_deduct_all_charges`;

-- Preserve an explicit opt-in from the earlier Void Fee wallet setting when
-- this migration is applied after add_void_fee_wallet_setting.sql.
SET @legacy_void_fee_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ticket_providers'
      AND column_name = 'void_fee_wallet_enabled'
);

SET @copy_legacy_wallet_policy_sql = IF(
    @legacy_void_fee_column_exists > 0,
    'UPDATE `ticket_providers` SET `wallet_deduct_all_charges` = 1 WHERE `void_fee_wallet_enabled` = 1 AND `wallet_deduct_all_charges` = 0',
    'SELECT "Legacy Void Fee wallet setting is not present; using base-only defaults" AS message'
);

PREPARE copy_legacy_wallet_policy_stmt FROM @copy_legacy_wallet_policy_sql;
EXECUTE copy_legacy_wallet_policy_stmt;
DEALLOCATE PREPARE copy_legacy_wallet_policy_stmt;

UPDATE `ticket_providers`
SET `wallet_deduct_all_charges` = COALESCE(`wallet_deduct_all_charges`, 0),
    `wallet_deduct_base_only` = COALESCE(`wallet_deduct_base_only`, 1);
