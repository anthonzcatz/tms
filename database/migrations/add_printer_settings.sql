-- =====================================================
-- PRINTER SETTINGS MIGRATION
-- Adds receipt printing configuration to system_settings
-- =====================================================

-- Add printer-related columns to system_settings
ALTER TABLE `system_settings`
    -- Enable/Disable receipt printing
    ADD COLUMN IF NOT EXISTS `receipt_printing_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable receipt printing globally',
    
    -- Paper width configuration (58mm = 32 chars, 80mm = 48 chars)
    ADD COLUMN IF NOT EXISTS `receipt_paper_width` VARCHAR(10) NOT NULL DEFAULT '80mm' COMMENT 'Receipt paper width: 58mm or 80mm',
    
    -- Auto-print on transaction complete
    ADD COLUMN IF NOT EXISTS `receipt_auto_print` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Auto-print receipt after transaction',
    
    -- Show print dialog before printing
    ADD COLUMN IF NOT EXISTS `receipt_show_preview` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Show receipt preview before printing',
    
    -- Receipt header text (store name, address, etc)
    ADD COLUMN IF NOT EXISTS `receipt_header_text` TEXT DEFAULT NULL COMMENT 'Custom receipt header text',
    
    -- Receipt footer text (thank you message, policy, etc)
    ADD COLUMN IF NOT EXISTS `receipt_footer_text` TEXT DEFAULT NULL COMMENT 'Custom receipt footer text',
    
    -- Logo printing (base64 encoded small logo or path)
    ADD COLUMN IF NOT EXISTS `receipt_logo_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable logo on receipt',
    
    -- Logo data (base64 encoded image, max 10KB)
    ADD COLUMN IF NOT EXISTS `receipt_logo_data` MEDIUMTEXT DEFAULT NULL COMMENT 'Base64 encoded logo image for receipt',
    
    -- Printer connection timeout
    ADD COLUMN IF NOT EXISTS `receipt_connection_timeout` INT NOT NULL DEFAULT 30 COMMENT 'QZ Tray connection timeout in seconds',
    
    -- Cut paper after print
    ADD COLUMN IF NOT EXISTS `receipt_auto_cut` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Auto cut paper after printing',
    
    -- Open cash drawer after print
    ADD COLUMN IF NOT EXISTS `receipt_open_cash_drawer` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Open cash drawer after printing',
    
    -- Number of copies
    ADD COLUMN IF NOT EXISTS `receipt_copies` INT NOT NULL DEFAULT 1 COMMENT 'Number of receipt copies to print',
    
    -- Print customer copy
    ADD COLUMN IF NOT EXISTS `receipt_customer_copy` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Print extra copy for customer',
    
    -- Print merchant copy
    ADD COLUMN IF NOT EXISTS `receipt_merchant_copy` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Print merchant copy',
    
    -- QZ Tray security settings
    ADD COLUMN IF NOT EXISTS `qz_tray_host` VARCHAR(255) NOT NULL DEFAULT 'localhost' COMMENT 'QZ Tray host',
    
    ADD COLUMN IF NOT EXISTS `qz_tray_ports` VARCHAR(255) NOT NULL DEFAULT '8181,8282,8383,8484' COMMENT 'QZ Tray secure ports',
    
    -- Printer type (thermal, dot matrix, etc)
    ADD COLUMN IF NOT EXISTS `printer_type` VARCHAR(50) NOT NULL DEFAULT 'THERMAL' COMMENT 'Printer type: THERMAL, DOT_MATRIX, INKJET',
    
    -- Character encoding
    ADD COLUMN IF NOT EXISTS `receipt_encoding` VARCHAR(20) NOT NULL DEFAULT 'UTF-8' COMMENT 'Receipt character encoding',
    
    -- Print QR code on receipt
    ADD COLUMN IF NOT EXISTS `receipt_qr_code_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable QR code on receipt',
    
    -- QR code data format (transaction_id, url, etc)
    ADD COLUMN IF NOT EXISTS `receipt_qr_format` VARCHAR(50) DEFAULT 'TRANSACTION_ID' COMMENT 'QR code format: TRANSACTION_ID, URL, CUSTOM',
    
    -- Custom QR code URL prefix
    ADD COLUMN IF NOT EXISTS `receipt_qr_url_prefix` VARCHAR(255) DEFAULT NULL COMMENT 'URL prefix for QR code',
    
    -- Print barcode on receipt
    ADD COLUMN IF NOT EXISTS `receipt_barcode_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable barcode on receipt',
    
    -- Include transaction details (payment method, cashier name, etc)
    ADD COLUMN IF NOT EXISTS `receipt_show_cashier` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Show cashier name on receipt',
    
    ADD COLUMN IF NOT EXISTS `receipt_show_payment_method` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Show payment method on receipt',
    
    ADD COLUMN IF NOT EXISTS `receipt_show_branch` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Show branch name on receipt',
    
    -- Font size settings
    ADD COLUMN IF NOT EXISTS `receipt_font_size` VARCHAR(10) NOT NULL DEFAULT 'NORMAL' COMMENT 'Receipt font size: SMALL, NORMAL, LARGE',
    
    -- Line spacing
    ADD COLUMN IF NOT EXISTS `receipt_line_spacing` INT NOT NULL DEFAULT 30 COMMENT 'Line spacing in dots (30 = normal)',
    
    -- Updated timestamp
    MODIFY COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Create index for faster printer settings lookup
CREATE INDEX IF NOT EXISTS idx_system_settings_printer ON system_settings(setting_id, receipt_printing_enabled);

-- Insert or update default settings if not exists
-- Only update the new printer-related columns, leave existing columns unchanged
UPDATE `system_settings` SET
    `receipt_printing_enabled` = 1,
    `receipt_paper_width` = '80mm',
    `receipt_auto_print` = 1,
    `receipt_show_preview` = 0,
    `receipt_header_text` = NULL,
    `receipt_footer_text` = 'Thank you for your business!\nPlease come again.',
    `receipt_logo_enabled` = 0,
    `receipt_auto_cut` = 1,
    `receipt_open_cash_drawer` = 0,
    `receipt_copies` = 1,
    `receipt_customer_copy` = 0,
    `receipt_merchant_copy` = 1,
    `qz_tray_host` = 'localhost',
    `qz_tray_ports` = '8181,8282,8383,8484',
    `printer_type` = 'THERMAL',
    `receipt_encoding` = 'UTF-8',
    `receipt_qr_code_enabled` = 0,
    `receipt_qr_format` = 'TRANSACTION_ID',
    `receipt_show_cashier` = 1,
    `receipt_show_payment_method` = 1,
    `receipt_show_branch` = 1,
    `receipt_font_size` = 'NORMAL',
    `receipt_line_spacing` = 30,
    `updated_at` = NOW()
WHERE `setting_id` = 1;

-- =====================================================
-- PRINTER CONFIGURATION TABLE (Per-terminal settings)
-- Stores printer assignments per workstation
-- =====================================================

CREATE TABLE IF NOT EXISTS `pos_printer_configs` (
    `config_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `terminal_name` VARCHAR(100) NOT NULL COMMENT 'Terminal/workstation identifier',
    `printer_name` VARCHAR(255) NOT NULL COMMENT 'Printer name as shown in Windows',
    `branch_id` INT UNSIGNED DEFAULT NULL COMMENT 'Associated branch',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is this the default printer for terminal',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `qz_tray_connected` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Last known QZ Tray connection status',
    `last_connected_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_terminal_printer` (`terminal_name`, `printer_name`),
    KEY `idx_branch` (`branch_id`),
    KEY `idx_terminal` (`terminal_name`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='POS Printer configurations per terminal';

-- =====================================================
-- RECEIPT PRINT LOG
-- Tracks all printed receipts for audit purposes
-- =====================================================

CREATE TABLE IF NOT EXISTS `receipt_print_logs` (
    `print_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT UNSIGNED NOT NULL COMMENT 'Reference to pos_orders or transactions',
    `transaction_type` VARCHAR(20) NOT NULL COMMENT 'TICKET, SERVICE, etc',
    `terminal_name` VARCHAR(100) NOT NULL COMMENT 'Terminal that printed',
    `printer_name` VARCHAR(255) NOT NULL,
    `printed_by` INT UNSIGNED NOT NULL COMMENT 'User ID who printed',
    `is_reprint` TINYINT(1) NOT NULL DEFAULT 0,
    `original_print_id` INT UNSIGNED DEFAULT NULL COMMENT 'Reference to original print if reprint',
    `print_status` VARCHAR(20) NOT NULL DEFAULT 'SUCCESS' COMMENT 'SUCCESS, FAILED, PENDING',
    `error_message` TEXT DEFAULT NULL,
    `receipt_data` MEDIUMTEXT DEFAULT NULL COMMENT 'JSON of receipt data for reprint',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_transaction` (`transaction_id`, `transaction_type`),
    KEY `idx_terminal` (`terminal_name`),
    KEY `idx_printed_by` (`printed_by`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Receipt print audit log';
