-- Migration: Add receipt display settings and company TIN
-- This migration adds new columns for flexible receipt configuration

-- First, add all missing printer/receipt columns if they don't exist
ALTER TABLE system_settings
    ADD COLUMN IF NOT EXISTS receipt_printing_enabled TINYINT(1) DEFAULT 1 AFTER max_concurrent_sessions,
    ADD COLUMN IF NOT EXISTS receipt_paper_width VARCHAR(20) DEFAULT '80mm' AFTER receipt_printing_enabled,
    ADD COLUMN IF NOT EXISTS receipt_auto_print TINYINT(1) DEFAULT 1 AFTER receipt_paper_width,
    ADD COLUMN IF NOT EXISTS receipt_show_preview TINYINT(1) DEFAULT 0 AFTER receipt_auto_print,
    ADD COLUMN IF NOT EXISTS receipt_copies INT(11) DEFAULT 1 AFTER receipt_show_preview,
    ADD COLUMN IF NOT EXISTS receipt_customer_copy TINYINT(1) DEFAULT 0 AFTER receipt_copies,
    ADD COLUMN IF NOT EXISTS receipt_merchant_copy TINYINT(1) DEFAULT 1 AFTER receipt_customer_copy,
    ADD COLUMN IF NOT EXISTS receipt_auto_cut TINYINT(1) DEFAULT 1 AFTER receipt_merchant_copy,
    ADD COLUMN IF NOT EXISTS receipt_open_cash_drawer TINYINT(1) DEFAULT 0 AFTER receipt_auto_cut,
    ADD COLUMN IF NOT EXISTS receipt_show_cashier TINYINT(1) DEFAULT 1 AFTER receipt_open_cash_drawer,
    ADD COLUMN IF NOT EXISTS receipt_show_payment_method TINYINT(1) DEFAULT 1 AFTER receipt_show_cashier,
    ADD COLUMN IF NOT EXISTS receipt_show_branch TINYINT(1) DEFAULT 1 AFTER receipt_show_payment_method,
    ADD COLUMN IF NOT EXISTS receipt_qr_code_enabled TINYINT(1) DEFAULT 0 AFTER receipt_show_branch,
    ADD COLUMN IF NOT EXISTS receipt_qr_format VARCHAR(50) DEFAULT 'TRANSACTION_ID' AFTER receipt_qr_code_enabled,
    ADD COLUMN IF NOT EXISTS receipt_logo_enabled TINYINT(1) DEFAULT 0 AFTER receipt_qr_format,
    ADD COLUMN IF NOT EXISTS printer_type VARCHAR(50) DEFAULT 'THERMAL' AFTER receipt_logo_enabled;

-- Now add the new receipt display settings and company TIN
ALTER TABLE system_settings
    ADD COLUMN IF NOT EXISTS company_tin VARCHAR(50) DEFAULT NULL AFTER company_contact_number,
    ADD COLUMN IF NOT EXISTS receipt_show_tin TINYINT(1) DEFAULT 1 AFTER receipt_logo_enabled,
    ADD COLUMN IF NOT EXISTS receipt_show_service_fee TINYINT(1) DEFAULT 1 AFTER receipt_show_tin,
    ADD COLUMN IF NOT EXISTS receipt_show_base_amount TINYINT(1) DEFAULT 1 AFTER receipt_show_service_fee,
    ADD COLUMN IF NOT EXISTS receipt_show_discount TINYINT(1) DEFAULT 1 AFTER receipt_show_base_amount,
    ADD COLUMN IF NOT EXISTS receipt_custom_footer TEXT DEFAULT NULL AFTER receipt_show_discount;

-- Update existing settings to ensure defaults are applied
UPDATE system_settings SET
    receipt_printing_enabled = 1,
    receipt_paper_width = '80mm',
    receipt_auto_print = 1,
    receipt_show_preview = 0,
    receipt_copies = 1,
    receipt_customer_copy = 0,
    receipt_merchant_copy = 1,
    receipt_auto_cut = 1,
    receipt_open_cash_drawer = 0,
    receipt_show_cashier = 1,
    receipt_show_payment_method = 1,
    receipt_show_branch = 1,
    receipt_qr_code_enabled = 0,
    receipt_qr_format = 'TRANSACTION_ID',
    receipt_logo_enabled = 0,
    printer_type = 'THERMAL',
    receipt_show_tin = 1,
    receipt_show_service_fee = 1,
    receipt_show_base_amount = 1,
    receipt_show_discount = 1
WHERE setting_id = 1;
