-- =========================================================
-- ENTERPRISE TICKETING + WALLET + POS SYSTEM
-- FINAL NORMALIZED DATABASE STRUCTURE
-- PSGC ADDRESS READY
-- =========================================================
-- FEATURES:
-- ✔ PSGC Normalized Address System
-- ✔ Multi-Branch
-- ✔ Multi-Provider Wallet
-- ✔ Shared Devices
-- ✔ Enterprise Security
-- ✔ Wallet Ledger System
-- ✔ Flexible Provider Fees
-- ✔ Maintenance Mode
-- ✔ Reporting Ready
-- ✔ Receipt Ready
-- ✔ Audit Logs
-- ✔ Financial Integrity
-- =========================================================



-- =========================================================
-- PSGC REGIONS
-- =========================================================
CREATE TABLE psgc_regions (
    region_code VARCHAR(12) PRIMARY KEY,
    region_name VARCHAR(150),
    region_description VARCHAR(255) NULL
);



-- =========================================================
-- PSGC PROVINCES
-- =========================================================
CREATE TABLE psgc_provinces (
    province_code VARCHAR(12) PRIMARY KEY,

    region_code VARCHAR(12) NOT NULL,

    province_name VARCHAR(150),

    FOREIGN KEY (region_code)
        REFERENCES psgc_regions(region_code),

    INDEX idx_region_code (region_code)
);



-- =========================================================
-- PSGC CITIES / MUNICIPALITIES
-- =========================================================
CREATE TABLE psgc_cities_municipalities (
    city_municipality_code VARCHAR(12) PRIMARY KEY,

    region_code VARCHAR(12) NOT NULL,
    province_code VARCHAR(12) NOT NULL,

    city_municipality_name VARCHAR(150),

    FOREIGN KEY (region_code)
        REFERENCES psgc_regions(region_code),

    FOREIGN KEY (province_code)
        REFERENCES psgc_provinces(province_code),

    INDEX idx_region_code (region_code),
    INDEX idx_province_code (province_code)
);



-- =========================================================
-- PSGC BARANGAYS
-- =========================================================
CREATE TABLE psgc_barangays (
    barangay_code VARCHAR(12) PRIMARY KEY,

    region_code VARCHAR(12) NOT NULL,
    province_code VARCHAR(12) NOT NULL,
    city_municipality_code VARCHAR(12) NOT NULL,

    barangay_name VARCHAR(150),

    FOREIGN KEY (region_code)
        REFERENCES psgc_regions(region_code),

    FOREIGN KEY (province_code)
        REFERENCES psgc_provinces(province_code),

    FOREIGN KEY (city_municipality_code)
        REFERENCES psgc_cities_municipalities(city_municipality_code),

    INDEX idx_region_code (region_code),
    INDEX idx_province_code (province_code),
    INDEX idx_city_municipality_code (city_municipality_code)
);



-- =========================================================
-- BUSINESS BRANCHES
-- =========================================================
CREATE TABLE business_branches (
    branch_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    branch_code VARCHAR(50) UNIQUE,

    branch_name VARCHAR(150) NOT NULL,

    -- =====================================================
    -- PSGC ADDRESS
    -- =====================================================
    region_code VARCHAR(12) NULL,
    province_code VARCHAR(12) NULL,
    city_municipality_code VARCHAR(12) NULL,
    barangay_code VARCHAR(12) NULL,

    -- =====================================================
    -- ADDRESS DETAILS
    -- =====================================================
    street_address VARCHAR(255) NULL,
    landmark VARCHAR(255) NULL,
    zip_code VARCHAR(10) NULL,

    -- =====================================================
    -- CONTACT DETAILS
    -- =====================================================
    contact_number VARCHAR(50) UNIQUE NULL,
    email VARCHAR(100) UNIQUE NULL,

    status ENUM(
        'active',
        'inactive'
    ) DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- =====================================================
    -- ONE-LIFE POLICY
    -- =====================================================
    UNIQUE KEY uq_branch_address (
        region_code,
        province_code,
        city_municipality_code,
        barangay_code,
        street_address
    ),

    FOREIGN KEY (region_code)
        REFERENCES psgc_regions(region_code),

    FOREIGN KEY (province_code)
        REFERENCES psgc_provinces(province_code),

    FOREIGN KEY (city_municipality_code)
        REFERENCES psgc_cities_municipalities(city_municipality_code),

    FOREIGN KEY (barangay_code)
        REFERENCES psgc_barangays(barangay_code),

    INDEX idx_branch_name (branch_name),
    INDEX idx_region_code (region_code),
    INDEX idx_province_code (province_code),
    INDEX idx_city_municipality_code (city_municipality_code),
    INDEX idx_barangay_code (barangay_code),
    INDEX idx_status (status)
);



-- =========================================================
-- USER ROLES
-- =========================================================
CREATE TABLE user_roles (
    role_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    role_code VARCHAR(50) UNIQUE,

    role_name VARCHAR(100),

    role_description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



-- =========================================================
-- USER ACCOUNTS
-- =========================================================

-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2026 at 10:21 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 8.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `user_id` bigint(20) NOT NULL,
  `user_code` varchar(50) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `role_id` bigint(20) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `is_time_restricted` tinyint(1) DEFAULT 0,
  `allowed_login_start` time DEFAULT NULL,
  `allowed_login_end` time DEFAULT NULL,
  `allowed_days` varchar(100) DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `require_password_change` tinyint(1) DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD CONSTRAINT `user_accounts_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`),
  ADD CONSTRAINT `user_accounts_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- =========================================================
-- SYSTEM DEVICES
-- =========================================================
CREATE TABLE system_devices (
    device_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    device_code VARCHAR(255) UNIQUE,

    device_name VARCHAR(255),

    device_type ENUM(
        'desktop',
        'laptop',
        'tablet',
        'mobile',
        'other'
    ) DEFAULT 'desktop',

    branch_id BIGINT NULL,

    ip_address VARCHAR(100),

    location_name VARCHAR(255),

    device_remark TEXT NULL,

    status ENUM(
        'pending',
        'approved',
        'blocked'
    ) DEFAULT 'pending',

    approved_by BIGINT NULL,
    approved_at TIMESTAMP NULL,

    last_used_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (approved_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- USER SESSIONS
-- =========================================================
CREATE TABLE user_sessions (
    session_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT NOT NULL,

    device_id BIGINT NULL,

    session_token VARCHAR(255) UNIQUE,

    ip_address VARCHAR(100),

    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    logout_time TIMESTAMP NULL,

    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    expires_at TIMESTAMP NULL,

    is_active BOOLEAN DEFAULT TRUE,

    FOREIGN KEY (user_id)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (device_id)
        REFERENCES system_devices(device_id),

    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_token (session_token),
    INDEX idx_user_sessions_active (is_active)
);



-- =========================================================
-- PERMISSIONS
-- =========================================================
CREATE TABLE permissions (
    permission_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    permission_code VARCHAR(100) UNIQUE,

    permission_name VARCHAR(150),

    module_name VARCHAR(100),

    parent_permission_id BIGINT NULL,

    menu_order INT DEFAULT 0,

    menu_icon VARCHAR(50) NULL,

    menu_url VARCHAR(255) NULL,

    menu_level INT DEFAULT 1,

    is_menu_item TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_permission_id)
        REFERENCES permissions(permission_id) ON DELETE SET NULL,

    INDEX idx_parent_permission_id (parent_permission_id),
    INDEX idx_menu_order (menu_order),
    INDEX idx_module_name (module_name)
);



-- =========================================================
-- ROLE PERMISSIONS
-- =========================================================
CREATE TABLE role_permissions (
    role_permission_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    role_id BIGINT NOT NULL,
    permission_id BIGINT NOT NULL,

    FOREIGN KEY (role_id)
        REFERENCES user_roles(role_id),

    FOREIGN KEY (permission_id)
        REFERENCES permissions(permission_id),

    UNIQUE KEY uq_role_permission (
        role_id,
        permission_id
    )
);



-- =========================================================
-- ACTIVITY LOGS
-- =========================================================
CREATE TABLE activity_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT NULL,

    device_id BIGINT NULL,

    action VARCHAR(100),

    module_name VARCHAR(100),

    reference_code VARCHAR(100),

    ip_address VARCHAR(100),

    old_value LONGTEXT,
    new_value LONGTEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (device_id)
        REFERENCES system_devices(device_id)
);



-- =========================================================
-- PASSENGER ACCOUNTS
-- =========================================================
CREATE TABLE passenger_accounts (
    passenger_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    fullname VARCHAR(150) NOT NULL,

    mobile_number VARCHAR(30) UNIQUE NULL,

    email VARCHAR(100) UNIQUE NULL,

    -- =====================================================
    -- PSGC ADDRESS
    -- =====================================================
    region_code VARCHAR(12) NULL,
    province_code VARCHAR(12) NULL,
    city_municipality_code VARCHAR(12) NULL,
    barangay_code VARCHAR(12) NULL,

    -- =====================================================
    -- ADDRESS DETAILS
    -- =====================================================
    street_address VARCHAR(255) NULL,
    landmark VARCHAR(255) NULL,
    zip_code VARCHAR(10) NULL,

    gender ENUM(
        'male',
        'female',
        'other'
    ) NULL,

    birth_date DATE NULL,

    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (region_code)
        REFERENCES psgc_regions(region_code),

    FOREIGN KEY (province_code)
        REFERENCES psgc_provinces(province_code),

    FOREIGN KEY (city_municipality_code)
        REFERENCES psgc_cities_municipalities(city_municipality_code),

    FOREIGN KEY (barangay_code)
        REFERENCES psgc_barangays(barangay_code),

    INDEX idx_fullname (fullname),
    INDEX idx_mobile_number (mobile_number),
    INDEX idx_email (email)
);



-- =========================================================
-- TICKET PROVIDERS
-- =========================================================
CREATE TABLE ticket_providers (
    provider_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    provider_code VARCHAR(50) UNIQUE,

    provider_name VARCHAR(150),

    provider_type ENUM(
        'airline',
        'shipping',
        'bus',
        'other'
    ),

    status ENUM(
        'active',
        'inactive'
    ) DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



-- =========================================================
-- ACCOMMODATION TYPES
-- =========================================================
CREATE TABLE accommodation_types (
    accommodation_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(50) UNIQUE,

    name VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



-- =========================================================
-- DISCOUNT TYPES
-- =========================================================
CREATE TABLE discount_types (
    discount_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(50) UNIQUE,

    name VARCHAR(100),

    description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



-- =========================================================
-- PROVIDER SERVICE FEES
-- =========================================================
CREATE TABLE provider_service_fees (
    fee_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    provider_id BIGINT NOT NULL,

    branch_id BIGINT NULL,

    fee_type ENUM(
        'FIXED',
        'PERCENT'
    ) DEFAULT 'FIXED',

    fee_value DECIMAL(12,2),

    effective_start_date DATE NULL,
    effective_end_date DATE NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_by BIGINT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (provider_id)
        REFERENCES ticket_providers(provider_id),

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- PROVIDER WALLETS
-- =========================================================
CREATE TABLE provider_wallets (
    wallet_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    provider_id BIGINT NOT NULL,

    branch_id BIGINT NOT NULL,

    current_balance DECIMAL(12,2) DEFAULT 0,

    status ENUM(
        'active',
        'inactive'
    ) DEFAULT 'active',

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (provider_id)
        REFERENCES ticket_providers(provider_id),

    FOREIGN KEY (branch_id)
        REFERENCES business_branches(branch_id),

    UNIQUE KEY uq_wallet (
        provider_id,
        branch_id
    )
);



-- =========================================================
-- WALLET TRANSACTIONS
-- =========================================================
CREATE TABLE wallet_transactions (
    wallet_txn_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    wallet_id BIGINT NOT NULL,

    txn_code VARCHAR(100) UNIQUE,

    txn_type ENUM(
        'TOPUP',
        'SALE',
        'REFUND',
        'ADJUSTMENT'
    ),

    direction ENUM(
        'IN',
        'OUT'
    ),

    amount DECIMAL(12,2),

    balance_before DECIMAL(12,2),
    balance_after DECIMAL(12,2),

    reference_table VARCHAR(100),
    reference_id BIGINT,

    remarks TEXT NULL,

    created_by BIGINT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (wallet_id)
        REFERENCES provider_wallets(wallet_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- TICKET TRANSACTIONS
-- =========================================================
CREATE TABLE ticket_transactions (
    transaction_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    transaction_code VARCHAR(50) UNIQUE,

    wallet_id BIGINT NOT NULL,

    passenger_id BIGINT NOT NULL,

    accommodation_id BIGINT NULL,

    discount_id BIGINT NULL,

    origin VARCHAR(100),

    destination VARCHAR(100),

    travel_date DATE,

    base_amount DECIMAL(12,2),

    service_fee DECIMAL(12,2),

    discount_amount DECIMAL(12,2),

    total_amount DECIMAL(12,2),

    status ENUM(
        'booked',
        'cancelled',
        'refunded'
    ) DEFAULT 'booked',

    remarks TEXT NULL,

    created_by BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (wallet_id)
        REFERENCES provider_wallets(wallet_id),

    FOREIGN KEY (passenger_id)
        REFERENCES passenger_accounts(passenger_id),

    FOREIGN KEY (accommodation_id)
        REFERENCES accommodation_types(accommodation_id),

    FOREIGN KEY (discount_id)
        REFERENCES discount_types(discount_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- TICKET ADJUSTMENTS
-- =========================================================
CREATE TABLE ticket_adjustments (
    adjustment_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    transaction_id BIGINT NOT NULL,

    type ENUM(
        'CANCEL',
        'REFUND',
        'CORRECTION'
    ),

    amount DECIMAL(12,2),

    reason TEXT,

    charged_to ENUM(
        'customer',
        'cashier',
        'branch',
        'company'
    ) DEFAULT 'customer',

    approval_status ENUM(
        'PENDING',
        'APPROVED',
        'REJECTED'
    ) DEFAULT 'PENDING',

    approved_by BIGINT NULL,

    created_by BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (transaction_id)
        REFERENCES ticket_transactions(transaction_id),

    FOREIGN KEY (approved_by)
        REFERENCES user_accounts(user_id),

    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- SYSTEM SETTINGS
-- =========================================================
CREATE TABLE system_settings (
    setting_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    company_name VARCHAR(255),

    company_abbreviation VARCHAR(50),

    company_address TEXT,

    company_contact_number VARCHAR(100),

    company_email VARCHAR(100) NULL,

    company_tagline VARCHAR(255) NULL,

    receipt_footer TEXT NULL,

    report_footer TEXT NULL,

    system_timezone VARCHAR(100) DEFAULT 'Asia/Manila',

    system_currency VARCHAR(20) DEFAULT 'PHP',

    maintenance_mode BOOLEAN DEFAULT FALSE,

    maintenance_message TEXT NULL,

    maintenance_start TIMESTAMP NULL,
    maintenance_end TIMESTAMP NULL,

    allow_admin_during_maintenance BOOLEAN DEFAULT TRUE,

    updated_by BIGINT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (updated_by)
        REFERENCES user_accounts(user_id)
);



-- =========================================================
-- SYSTEM MAINTENANCE LOGS
-- =========================================================
CREATE TABLE system_maintenance_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,

    mode VARCHAR(50),

    message TEXT,

    started_by BIGINT NULL,

    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    ended_at TIMESTAMP NULL,

    FOREIGN KEY (started_by)
        REFERENCES user_accounts(user_id)
);