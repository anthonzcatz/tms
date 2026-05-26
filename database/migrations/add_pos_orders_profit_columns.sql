-- Migration: Add profit/cost columns to pos_orders for easier reporting
-- Run once against tms_db

ALTER TABLE `pos_orders`
    ADD COLUMN `total_cost`         decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of base_amount (ticket cost paid to provider)' AFTER `discount_total`,
    ADD COLUMN `total_service_fees` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of service_fee charged per ticket'              AFTER `total_cost`,
    ADD COLUMN `total_add_ons`      decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of service add-on charges (unit_price x qty)'    AFTER `total_service_fees`,
    ADD COLUMN `total_profit`       decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'total_service_fees + total_add_ons'                  AFTER `total_add_ons`,
    ADD COLUMN `payment_method`     varchar(100)  DEFAULT NULL          COMMENT 'Denormalized primary payment method name'           AFTER `amount_paid`,
    ADD COLUMN `cashier_name`       varchar(150)  DEFAULT NULL          COMMENT 'Denormalized cashier name at time of sale'          AFTER `payment_method`;
