-- Migration: add per-customer service fee handling in customer_charges
-- Scope: ticket transactions only, future transactions from exempt_effective_date

ALTER TABLE `customer_charges`
    ADD COLUMN IF NOT EXISTS `service_fee_mode` ENUM('CUSTOMER','WAIVED','COMPANY') NOT NULL DEFAULT 'CUSTOMER' COMMENT 'Who owns the service fee portion',
    ADD COLUMN IF NOT EXISTS `company_passenger_id` BIGINT NULL COMMENT 'Company/CEO passenger_id when service_fee_mode = COMPANY',
    ADD COLUMN IF NOT EXISTS `exempt_effective_date` DATETIME NULL COMMENT 'From this date forward the service fee mode is applied',
    ADD COLUMN IF NOT EXISTS `base_charged` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `fee_charged` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `base_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `fee_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `base_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `fee_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD KEY IF NOT EXISTS `idx_service_fee_mode` (`service_fee_mode`),
    ADD KEY IF NOT EXISTS `idx_company_passenger_id` (`company_passenger_id`);

-- Backfill: treat existing charges as all base (so total stays correct while still showing base/fee split for new transactions)
UPDATE `customer_charges`
SET `base_charged` = `total_charged`,
    `base_paid` = `total_paid`,
    `base_balance` = `balance`
WHERE `base_charged` = 0 AND `fee_charged` = 0;
