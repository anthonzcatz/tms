-- =========================================================
-- FLEXIBLE PAYMENT SYSTEM MIGRATION
-- =========================================================
-- Features:
-- ✔ Flexible Payment Methods (Cash, Bank Transfer, Charge, GCash, etc.)
-- ✔ Mixed Payments (multiple payment methods per transaction)
-- ✔ Bank Transfer Confirmation by another user
-- ✔ Customer Charge / Utang / Collectibles tracking
-- ✔ Print Fee and other non-ticket services
-- ✔ Cashier Session / Shift Reconciliation
-- ✔ Branch-level Reporting Ready
-- =========================================================

-- =========================================================
-- 1. PAYMENT METHODS (LOOKUP)
--    Flexible: easy to add new methods (GCash, PayMaya, etc.)
-- =========================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    method_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    method_code VARCHAR(50) UNIQUE NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    method_type ENUM(
        'CASH',
        'BANK_TRANSFER',
        'E_WALLET',
        'CHARGE',
        'CARD',
        'OTHER'
    ) NOT NULL,

    description TEXT NULL,
    icon VARCHAR(100) NULL,

    -- Bank transfers and e-wallets need confirmation
    requires_confirmation BOOLEAN DEFAULT FALSE,

    -- Charge requires customer (utang)
    requires_customer BOOLEAN DEFAULT FALSE,

    -- Reference number required (for bank transfers, e-wallets)
    requires_reference BOOLEAN DEFAULT FALSE,

    is_active BOOLEAN DEFAULT TRUE,

    sort_order INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    INDEX idx_method_type (method_type),
    INDEX idx_is_active (is_active)
);

-- Seed default payment methods
INSERT INTO payment_methods (method_code, method_name, method_type, requires_confirmation, requires_customer, requires_reference, sort_order) VALUES
('CASH', 'Cash', 'CASH', FALSE, FALSE, FALSE, 1),
('BANK_TRANSFER', 'Bank Transfer', 'BANK_TRANSFER', TRUE, FALSE, TRUE, 2),
('CHARGE', 'Charge (Utang)', 'CHARGE', FALSE, TRUE, FALSE, 3),
('GCASH', 'GCash', 'E_WALLET', TRUE, FALSE, TRUE, 4),
('PAYMAYA', 'PayMaya', 'E_WALLET', TRUE, FALSE, TRUE, 5),
('CHECK', 'Check', 'OTHER', TRUE, FALSE, TRUE, 6);


-- =========================================================
-- 2. BANK ACCOUNTS (COMPANY BANK ACCOUNTS)
--    For receiving bank transfers
-- =========================================================
CREATE TABLE IF NOT EXISTS bank_accounts (
    bank_account_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    branch_id BIGINT NULL,

    bank_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_type VARCHAR(50) NULL,

    -- For e-wallets like GCash (mobile number)
    payment_method_id BIGINT NULL,

    is_active BOOLEAN DEFAULT TRUE,

    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(method_id),

    INDEX idx_branch_id (branch_id),
    INDEX idx_is_active (is_active)
);


-- =========================================================
-- 3. SERVICE TYPES (LOOKUP)
--    Types of services: ticket sales, print fees, etc.
-- =========================================================
CREATE TABLE IF NOT EXISTS service_types (
    service_type_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,

    -- Default amount (for fixed-price services like print fee)
    default_amount DECIMAL(12,2) DEFAULT 0,

    -- Allow custom amount per transaction
    allow_custom_amount BOOLEAN DEFAULT TRUE,

    -- Provider/wallet tracking
    requires_wallet BOOLEAN DEFAULT FALSE,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    INDEX idx_is_active (is_active)
);

-- Seed default service types
INSERT INTO service_types (code, name, description, default_amount, allow_custom_amount, requires_wallet) VALUES
('TICKET_SALE', 'Ticket Sale', 'Sale of bus/ferry/plane ticket', 0, TRUE, TRUE),
('PRINT_FEE', 'Print Fee', 'Document printing service fee', 5.00, TRUE, FALSE),
('PHOTOCOPY_FEE', 'Photocopy Fee', 'Photocopy service fee', 2.00, TRUE, FALSE),
('SCAN_FEE', 'Scan Fee', 'Document scanning fee', 10.00, TRUE, FALSE),
('TICKET_REPRINT', 'Ticket Reprint', 'Reprinting of issued ticket', 20.00, TRUE, FALSE),
('OTHER_SERVICE', 'Other Service', 'Miscellaneous service', 0, TRUE, FALSE);


-- =========================================================
-- 4. SERVICE TRANSACTIONS (NON-TICKET SERVICES)
--    For print fees, photocopy, etc.
--    Ticket sales still use ticket_transactions
-- =========================================================
CREATE TABLE IF NOT EXISTS service_transactions (
    service_txn_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    transaction_code VARCHAR(50) UNIQUE,

    branch_id BIGINT NOT NULL,
    service_type_id BIGINT NOT NULL,

    -- Optional customer (for charge/utang)
    passenger_id BIGINT NULL,

    -- Description of service
    description TEXT NULL,

    quantity INT DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,

    status ENUM(
        'completed',
        'cancelled',
        'refunded'
    ) DEFAULT 'completed',

    remarks TEXT NULL,

    -- Cashier session for shift reconciliation
    cashier_session_id BIGINT NULL,

    created_by BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (service_type_id)
        REFERENCES service_types(service_type_id),

    FOREIGN KEY (passenger_id)
        REFERENCES passenger_accounts(passenger_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id),

    INDEX idx_branch_id (branch_id),
    INDEX idx_service_type_id (service_type_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);


-- =========================================================
-- 5. TRANSACTION PAYMENTS
--    Mixed payments support: 1 transaction can have
--    multiple payment methods (e.g. 1000 cash + 500 bank)
-- =========================================================
CREATE TABLE IF NOT EXISTS transaction_payments (
    payment_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Polymorphic: ticket_transactions or service_transactions
    source_type ENUM(
        'TICKET_TRANSACTION',
        'SERVICE_TRANSACTION'
    ) NOT NULL,

    source_id BIGINT NOT NULL,

    -- Payment method used (CASH, BANK_TRANSFER, CHARGE, etc.)
    payment_method_id BIGINT NOT NULL,

    -- Bank account where bank transfer was deposited (if applicable)
    bank_account_id BIGINT NULL,

    -- Amount paid via this method
    amount DECIMAL(12,2) NOT NULL,

    -- Reference number (for bank transfers, e-wallets, checks)
    reference_number VARCHAR(100) NULL,

    -- Optional payment date (for bank transfers/checks that may differ)
    payment_date DATE NULL,

    -- Confirmation status (for bank transfers, e-wallets)
    confirmation_status ENUM(
        'NOT_REQUIRED',
        'PENDING',
        'CONFIRMED',
        'REJECTED'
    ) DEFAULT 'NOT_REQUIRED',

    -- Confirmed by another user (for verifying bank transfers)
    confirmed_by BIGINT NULL,
    confirmed_at TIMESTAMP NULL,
    confirmation_notes TEXT NULL,

    -- For CHARGE: customer who owes this amount
    charged_to_passenger_id BIGINT NULL,

    -- Has this charge been settled?
    charge_settled BOOLEAN DEFAULT FALSE,
    charge_settled_at TIMESTAMP NULL,

    notes TEXT NULL,

    created_by BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(method_id),

    FOREIGN KEY (bank_account_id)
        REFERENCES bank_accounts(bank_account_id),

    FOREIGN KEY (confirmed_by)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (charged_to_passenger_id)
        REFERENCES passenger_accounts(passenger_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id),

    INDEX idx_source (source_type, source_id),
    INDEX idx_payment_method_id (payment_method_id),
    INDEX idx_confirmation_status (confirmation_status),
    INDEX idx_charge_settled (charge_settled),
    INDEX idx_charged_to_passenger_id (charged_to_passenger_id),
    INDEX idx_created_at (created_at)
);


-- =========================================================
-- 6. CUSTOMER CHARGES (UTANG / COLLECTIBLES SUMMARY)
--    Aggregate view of customer outstanding balances
--    Updated automatically when CHARGE payment is made/settled
-- =========================================================
CREATE TABLE IF NOT EXISTS customer_charges (
    charge_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    passenger_id BIGINT NOT NULL,

    -- Total charged (lifetime)
    total_charged DECIMAL(12,2) DEFAULT 0,

    -- Total paid against charges
    total_paid DECIMAL(12,2) DEFAULT 0,

    -- Outstanding balance (utang)
    balance DECIMAL(12,2) DEFAULT 0,

    -- Last activity date
    last_charge_date TIMESTAMP NULL,
    last_payment_date TIMESTAMP NULL,

    status ENUM(
        'CLEAR',
        'OUTSTANDING',
        'OVERDUE',
        'WRITTEN_OFF'
    ) DEFAULT 'CLEAR',

    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uq_passenger_charge (passenger_id),

    FOREIGN KEY (passenger_id)
        REFERENCES passenger_accounts(passenger_id),

    INDEX idx_status (status),
    INDEX idx_balance (balance)
);


-- =========================================================
-- 7. CHARGE PAYMENTS
--    Records when customer pays off their charge balance
--    Allows partial payments
-- =========================================================
CREATE TABLE IF NOT EXISTS charge_payments (
    charge_payment_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    payment_code VARCHAR(50) UNIQUE,

    passenger_id BIGINT NOT NULL,

    branch_id BIGINT NOT NULL,

    -- Payment method used to settle the charge
    payment_method_id BIGINT NOT NULL,

    -- Amount paid against charges
    amount_paid DECIMAL(12,2) NOT NULL,

    -- Balance before and after this payment
    balance_before DECIMAL(12,2),
    balance_after DECIMAL(12,2),

    -- Reference (for bank transfer, gcash, etc.)
    reference_number VARCHAR(100) NULL,
    bank_account_id BIGINT NULL,

    -- Confirmation (for bank transfers)
    confirmation_status ENUM(
        'NOT_REQUIRED',
        'PENDING',
        'CONFIRMED',
        'REJECTED'
    ) DEFAULT 'NOT_REQUIRED',

    confirmed_by BIGINT NULL,
    confirmed_at TIMESTAMP NULL,

    -- Cashier session for shift reconciliation
    cashier_session_id BIGINT NULL,

    notes TEXT NULL,

    created_by BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (passenger_id)
        REFERENCES passenger_accounts(passenger_id),

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(method_id),

    FOREIGN KEY (bank_account_id)
        REFERENCES bank_accounts(bank_account_id),

    FOREIGN KEY (confirmed_by)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id),

    INDEX idx_passenger_id (passenger_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_confirmation_status (confirmation_status),
    INDEX idx_created_at (created_at)
);


-- =========================================================
-- 8. CHARGE PAYMENT ALLOCATIONS
--    When customer pays partial, this records which
--    transactions/charges the payment was applied to (FIFO)
-- =========================================================
CREATE TABLE IF NOT EXISTS charge_payment_allocations (
    allocation_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    charge_payment_id BIGINT NOT NULL,

    -- Which transaction_payment (CHARGE) is being settled
    transaction_payment_id BIGINT NOT NULL,

    -- Amount applied to this transaction_payment
    amount_applied DECIMAL(12,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (charge_payment_id)
        REFERENCES charge_payments(charge_payment_id) ON DELETE CASCADE,

    FOREIGN KEY (transaction_payment_id)
        REFERENCES transaction_payments(payment_id),

    INDEX idx_charge_payment_id (charge_payment_id),
    INDEX idx_transaction_payment_id (transaction_payment_id)
);


-- =========================================================
-- 9. CASHIER SESSIONS (SHIFTS)
--    Track cashier shifts for daily reconciliation
-- =========================================================
CREATE TABLE IF NOT EXISTS cashier_sessions (
    session_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    session_code VARCHAR(50) UNIQUE,

    cashier_user_id BIGINT NOT NULL,
    branch_id BIGINT NOT NULL,

    -- Cash float (starting cash)
    starting_cash DECIMAL(12,2) DEFAULT 0,

    -- Computed at close
    expected_cash DECIMAL(12,2) NULL,
    actual_cash DECIMAL(12,2) NULL,
    cash_variance DECIMAL(12,2) NULL,

    -- Total per payment method (computed at close)
    total_cash DECIMAL(12,2) DEFAULT 0,
    total_bank_transfer DECIMAL(12,2) DEFAULT 0,
    total_e_wallet DECIMAL(12,2) DEFAULT 0,
    total_charge DECIMAL(12,2) DEFAULT 0,
    total_other DECIMAL(12,2) DEFAULT 0,
    total_sales DECIMAL(12,2) DEFAULT 0,

    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,

    status ENUM(
        'OPEN',
        'CLOSED',
        'RECONCILED'
    ) DEFAULT 'OPEN',

    -- Reviewed by supervisor
    reviewed_by BIGINT NULL,
    reviewed_at TIMESTAMP NULL,

    notes TEXT NULL,

    FOREIGN KEY (cashier_user_id)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (reviewed_by)
        REFERENCES user_accounts(user_id),

    INDEX idx_cashier_user_id (cashier_user_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
);


-- =========================================================
-- 10. CASHIER SESSION DETAILS (PER PAYMENT METHOD)
--     Detailed breakdown per method per session
-- =========================================================
CREATE TABLE IF NOT EXISTS cashier_session_details (
    detail_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    session_id BIGINT NOT NULL,

    payment_method_id BIGINT NOT NULL,

    -- Counts
    transaction_count INT DEFAULT 0,

    -- Amounts
    expected_amount DECIMAL(12,2) DEFAULT 0,
    actual_amount DECIMAL(12,2) DEFAULT 0,
    variance DECIMAL(12,2) DEFAULT 0,

    notes TEXT NULL,

    FOREIGN KEY (session_id)
        REFERENCES cashier_sessions(session_id) ON DELETE CASCADE,

    FOREIGN KEY (payment_method_id)
        REFERENCES payment_methods(method_id),

    UNIQUE KEY uq_session_method (session_id, payment_method_id),

    INDEX idx_session_id (session_id)
);


-- =========================================================
-- 11. ADD cashier_session_id TO ticket_transactions
--     For shift reconciliation tracking
-- =========================================================
ALTER TABLE ticket_transactions
    ADD COLUMN IF NOT EXISTS cashier_session_id BIGINT NULL AFTER created_by,
    ADD INDEX IF NOT EXISTS idx_cashier_session_id (cashier_session_id);

-- Add foreign key (separate to avoid issues if column already exists)
-- ALTER TABLE ticket_transactions
--     ADD CONSTRAINT fk_ticket_cashier_session
--     FOREIGN KEY (cashier_session_id) REFERENCES cashier_sessions(session_id);


-- =========================================================
-- 12. ADD branch_id TO ticket_transactions
--     For branch-level reporting (faster than joining wallet)
-- =========================================================
ALTER TABLE ticket_transactions
    ADD COLUMN IF NOT EXISTS branch_id BIGINT NULL AFTER wallet_id,
    ADD INDEX IF NOT EXISTS idx_branch_id (branch_id);


-- =========================================================
-- 13. CREATE FOREIGN KEY for service_transactions.cashier_session_id
-- =========================================================
ALTER TABLE service_transactions
    ADD CONSTRAINT fk_service_cashier_session
    FOREIGN KEY (cashier_session_id) REFERENCES cashier_sessions(session_id);

ALTER TABLE charge_payments
    ADD CONSTRAINT fk_charge_payment_cashier_session
    FOREIGN KEY (cashier_session_id) REFERENCES cashier_sessions(session_id);


-- =========================================================
-- DONE!
-- =========================================================
-- New Module: Payment Management
-- Tables Added:
-- - payment_methods (lookup)
-- - bank_accounts
-- - service_types (lookup)
-- - service_transactions
-- - transaction_payments (mixed payments)
-- - customer_charges (utang summary)
-- - charge_payments
-- - charge_payment_allocations
-- - cashier_sessions
-- - cashier_session_details
-- =========================================================
