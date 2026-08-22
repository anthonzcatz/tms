ALTER TABLE `ticket_cancellations`
    ADD COLUMN IF NOT EXISTS `lost_sales_void_fee` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Void fee entered for a Technical Issue; excluded from income and recorded as Lost Sales'
        AFTER `void_service_fee`,
    ADD COLUMN IF NOT EXISTS `lost_sales_service_fee` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Service fee entered for a Technical Issue; excluded from income and recorded as Lost Sales'
        AFTER `lost_sales_void_fee`;

UPDATE `ticket_cancellations`
SET `lost_sales_void_fee` = COALESCE(`lost_sales_void_fee`, 0.00) + COALESCE(`void_fee`, 0.00),
    `lost_sales_service_fee` = COALESCE(`lost_sales_service_fee`, 0.00) + COALESCE(`void_service_fee`, 0.00),
    `void_fee` = 0.00,
    `void_service_fee` = 0.00
WHERE `operation_type` = 'VOID'
  AND `reason_category` = 'PRINTER_ERROR'
  AND (COALESCE(`void_fee`, 0.00) > 0 OR COALESCE(`void_service_fee`, 0.00) > 0);
