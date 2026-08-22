ALTER TABLE `ticket_adjustments`
    MODIFY COLUMN `type` enum('VOID','CANCEL','REFUND','CORRECTION') DEFAULT NULL,
    MODIFY COLUMN `charged_to` enum('none','customer','cashier','branch','company') DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS `idempotency_key` varchar(150) DEFAULT NULL AFTER `adjustment_id`,
    ADD COLUMN IF NOT EXISTS `cancellation_id` bigint(20) DEFAULT NULL AFTER `transaction_id`,
    ADD COLUMN IF NOT EXISTS `charged_to_passenger_id` bigint(20) DEFAULT NULL AFTER `charged_to`,
    ADD COLUMN IF NOT EXISTS `responsible_user_id` bigint(20) DEFAULT NULL AFTER `charged_to_passenger_id`,
    ADD COLUMN IF NOT EXISTS `cashier_session_id` bigint(20) DEFAULT NULL AFTER `responsible_user_id`,
    ADD COLUMN IF NOT EXISTS `settlement_status` enum('NOT_APPLICABLE','AUDIT_ONLY','RECORDED','DEDUCTED') NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER `approval_status`,
    ADD COLUMN IF NOT EXISTS `approved_at` datetime DEFAULT NULL AFTER `approved_by`,
    ADD COLUMN IF NOT EXISTS `settled_at` datetime DEFAULT NULL AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `settled_by` bigint(20) DEFAULT NULL AFTER `settled_at`,
    ADD UNIQUE KEY IF NOT EXISTS `uq_ticket_adjustments_idempotency` (`idempotency_key`),
    ADD KEY IF NOT EXISTS `idx_ticket_adjustments_cancellation_id` (`cancellation_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_adjustments_charged_to_passenger` (`charged_to_passenger_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_adjustments_responsible_user` (`responsible_user_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_adjustments_cashier_session` (`cashier_session_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_adjustments_settlement_status` (`settlement_status`);

ALTER TABLE `ticket_adjustments`
    MODIFY COLUMN `settlement_status` enum('NOT_APPLICABLE','AUDIT_ONLY','RECORDED','DEDUCTED') NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER `approval_status`;

ALTER TABLE `ticket_cancellations`
    ADD COLUMN IF NOT EXISTS `operation_type` enum('REFUND','VOID') NOT NULL DEFAULT 'REFUND' AFTER `transaction_code`,
    ADD COLUMN IF NOT EXISTS `reason_category` enum('CUSTOMER_REQUEST','CUSTOMER_ERROR','CASHIER_ERROR','PRINTER_ERROR','SYSTEM_ERROR','OTHER') NOT NULL DEFAULT 'OTHER' AFTER `reason`,
    ADD COLUMN IF NOT EXISTS `responsibility` enum('NONE','CUSTOMER','CASHIER') NOT NULL DEFAULT 'NONE' AFTER `reason_category`,
    ADD COLUMN IF NOT EXISTS `responsible_user_id` bigint(20) DEFAULT NULL AFTER `responsibility`,
    ADD COLUMN IF NOT EXISTS `responsibility_cashier_session_id` bigint(20) DEFAULT NULL AFTER `cashier_session_id`,
    ADD COLUMN IF NOT EXISTS `gross_refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `refund_amount`,
    ADD COLUMN IF NOT EXISTS `responsibility_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `cash_refund_amount`,
    ADD COLUMN IF NOT EXISTS `adjustment_id` bigint(20) DEFAULT NULL AFTER `remarks`,
    ADD KEY IF NOT EXISTS `idx_ticket_cancellations_operation_type` (`operation_type`),
    ADD KEY IF NOT EXISTS `idx_ticket_cancellations_responsibility` (`responsibility`),
    ADD KEY IF NOT EXISTS `idx_ticket_cancellations_responsible_user` (`responsible_user_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_cancellations_responsibility_session` (`responsibility_cashier_session_id`),
    ADD KEY IF NOT EXISTS `idx_ticket_cancellations_adjustment_id` (`adjustment_id`);

ALTER TABLE `ticket_refunds`
    ADD COLUMN IF NOT EXISTS `gross_refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `refund_amount`,
    ADD COLUMN IF NOT EXISTS `responsibility_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `gross_refund_amount`,
    ADD COLUMN IF NOT EXISTS `adjustment_id` bigint(20) DEFAULT NULL AFTER `cancellation_id`,
    ADD KEY IF NOT EXISTS `idx_ticket_refunds_adjustment_id` (`adjustment_id`);

ALTER TABLE `cashier_sessions`
    ADD COLUMN IF NOT EXISTS `total_cash_adjustments` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Immediate responsibility deductions applied to this cashier drawer'
        AFTER `pending_refunds_cash`;

UPDATE `ticket_cancellations`
SET `gross_refund_amount` = COALESCE(`refund_amount`, 0.00)
WHERE `gross_refund_amount` = 0.00;

UPDATE `ticket_refunds`
SET `gross_refund_amount` = COALESCE(`refund_amount`, 0.00)
WHERE `gross_refund_amount` = 0.00;
