-- Migration: Add the Cancel reason category for Void Ticket operations.
-- Cancel is a no-fee, no-responsibility Void category.

ALTER TABLE `ticket_cancellations`
    MODIFY COLUMN `reason_category`
        enum('CUSTOMER_REQUEST','CUSTOMER_ERROR','CASHIER_ERROR','PRINTER_ERROR','SYSTEM_ERROR','CANCEL','OTHER')
        NOT NULL DEFAULT 'OTHER';
