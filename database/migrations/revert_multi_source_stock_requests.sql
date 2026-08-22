-- Revert: multi_source_stock_requests.sql
-- Restores the original constraints and removes per-item source fields.

ALTER TABLE `ticket_stock_request_items`
    DROP FOREIGN KEY `fk_tsri_source_wallet_id`,
    DROP FOREIGN KEY `fk_tsri_source_branch_id`,
    DROP FOREIGN KEY `fk_tsri_provider_id`,
    DROP INDEX `idx_tsri_source_wallet_id`,
    DROP INDEX `idx_tsri_source_branch_id`,
    DROP INDEX `idx_tsri_provider_id`;

ALTER TABLE `ticket_stock_request_items`
    DROP COLUMN `source_wallet_id`,
    DROP COLUMN `source_branch_id`,
    DROP COLUMN `provider_id`,
    MODIFY COLUMN `variant_id` bigint(20) NOT NULL,
    ADD UNIQUE KEY `uq_request_variant` (`stock_request_id`, `variant_id`);

ALTER TABLE `branch_ticket_stocks`
    MODIFY COLUMN `variant_id` bigint(20) NOT NULL;

ALTER TABLE `ticket_stock_movements`
    MODIFY COLUMN `variant_id` bigint(20) NOT NULL;
