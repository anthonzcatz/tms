-- Migration: Improve pos_orders payment tracking and cashier name
-- Run once against tms_db

-- 1. Replace single payment_method text with structured columns
ALTER TABLE `pos_orders`
    ADD COLUMN `payment_method_ids`  varchar(255) DEFAULT NULL COMMENT 'Comma-separated payment_method_id values (for multi-payment)'  AFTER `cashier_name`,
    ADD COLUMN `payment_methods_json` text         DEFAULT NULL COMMENT 'JSON array of {method_id, method_name, amount} for full detail' AFTER `payment_method_ids`,
    ADD COLUMN `cashier_user_id`      bigint(20)   DEFAULT NULL COMMENT 'FK to user_accounts for joins'                                 AFTER `payment_methods_json`;

-- 2. Add index on cashier_user_id for reporting joins
ALTER TABLE `pos_orders`
    ADD KEY `idx_cashier_user_id` (`cashier_user_id`);

-- 3. Allow transaction_payments to be linked to a POS order directly
ALTER TABLE `transaction_payments`
    MODIFY COLUMN `source_type` enum('TICKET_TRANSACTION','SERVICE_TRANSACTION','POS_ORDER') NOT NULL;

-- 4. Backfill cashier_user_id from created_by
UPDATE `pos_orders` SET `cashier_user_id` = `created_by` WHERE `cashier_user_id` IS NULL;

-- 5. Backfill cashier_name from employees (proper full name, not username)
UPDATE `pos_orders` po
JOIN `user_accounts` ua ON ua.user_id = po.created_by
JOIN `employees` e ON e.emp_id = ua.emp_id
SET po.cashier_name = TRIM(CONCAT(
    e.first_name, ' ',
    CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
         THEN CONCAT(SUBSTRING(e.middle_name,1,1), '. ')
         ELSE '' END,
    e.last_name
));

-- 6. Backfill payment_method (primary) and payment_method_ids from transaction_payments
UPDATE `pos_orders` po
SET
    po.payment_method = (
        SELECT GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.sort_order SEPARATOR ' + ')
        FROM `transaction_payments` tp
        JOIN `payment_methods` pm ON pm.method_id = tp.payment_method_id
        WHERE tp.source_type = 'TICKET_TRANSACTION'
          AND tp.source_id IN (
              SELECT poi.reference_id FROM pos_order_items poi
              WHERE poi.order_id = po.order_id AND poi.item_type = 'TICKET'
          )
    ),
    po.payment_method_ids = (
        SELECT GROUP_CONCAT(DISTINCT tp.payment_method_id ORDER BY tp.payment_method_id SEPARATOR ',')
        FROM `transaction_payments` tp
        WHERE tp.source_type = 'TICKET_TRANSACTION'
          AND tp.source_id IN (
              SELECT poi.reference_id FROM pos_order_items poi
              WHERE poi.order_id = po.order_id AND poi.item_type = 'TICKET'
          )
    );
