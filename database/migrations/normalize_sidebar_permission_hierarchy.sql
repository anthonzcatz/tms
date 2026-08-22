-- Migration: Normalize sidebar and permission-management hierarchy
-- Date: 2026-08-12
-- Purpose: Make Wallet, Settings, and POS permissions render as organized
-- parent/child trees like Ticket Stock without changing permission codes.
-- Existing legacy alias permissions remain available for backend compatibility
-- but are hidden from the sidebar.

-- =========================================================
-- 1. ENSURE WALLET MENU RECORDS EXIST
-- =========================================================
INSERT INTO `permissions`
    (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
     `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`)
SELECT 'VIEW_WALLET', 'Wallet', 'WALLET', NULL, 5, 'fas fa-wallet', NULL, 1, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_code` = 'VIEW_WALLET'
);

INSERT INTO `permissions`
    (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
     `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`)
SELECT 'VIEW_TICKET_PROVIDERS', 'Ticket Providers', 'WALLET', NULL, 1,
       'fas fa-plane', 'admin/wallet/ticket-providers', 2, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_code` = 'VIEW_TICKET_PROVIDERS'
);

INSERT INTO `permissions`
    (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
     `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`)
SELECT 'VIEW_WALLET_MANAGEMENT', 'Provider Wallets', 'WALLET', NULL, 2,
       'fas fa-wallet', 'admin/wallet/provider-wallets', 2, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_code` = 'VIEW_WALLET_MANAGEMENT'
);

INSERT INTO `permissions`
    (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
     `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`)
SELECT 'WALLET_TRANSACTIONS', 'Wallet Transactions', 'WALLET', NULL, 3,
       'fas fa-exchange-alt', 'admin/wallet/wallet-transactions', 2, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_code` = 'WALLET_TRANSACTIONS'
);

INSERT INTO `permissions`
    (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
     `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`)
SELECT 'VIEW_SERVICE_FEES', 'Provider Service Fees', 'WALLET', NULL, 4,
       'fas fa-percent', 'admin/wallet/provider-service-fees', 2, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_code` = 'VIEW_SERVICE_FEES'
);

-- =========================================================
-- 2. NORMALIZE WALLET TREE
-- =========================================================
SET @wallet_root_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_WALLET' LIMIT 1
);
SET @wallet_providers_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_TICKET_PROVIDERS' LIMIT 1
);
SET @wallet_wallets_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_WALLET_MANAGEMENT' LIMIT 1
);
SET @wallet_transactions_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'WALLET_TRANSACTIONS' LIMIT 1
);
SET @wallet_fees_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_SERVICE_FEES' LIMIT 1
);

UPDATE `permissions`
SET `permission_name` = 'Wallet',
    `module_name` = 'WALLET',
    `parent_permission_id` = NULL,
    `menu_order` = 5,
    `menu_icon` = 'fas fa-wallet',
    `menu_url` = NULL,
    `menu_level` = 1,
    `is_menu_item` = 1
WHERE `permission_code` = 'VIEW_WALLET';

UPDATE `permissions`
SET `permission_name` = 'Ticket Providers',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_root_id,
    `menu_order` = 1,
    `menu_icon` = 'fas fa-plane',
    `menu_url` = 'admin/wallet/ticket-providers',
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'VIEW_TICKET_PROVIDERS';

UPDATE `permissions`
SET `permission_name` = 'Provider Wallets',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_root_id,
    `menu_order` = 2,
    `menu_icon` = 'fas fa-wallet',
    `menu_url` = 'admin/wallet/provider-wallets',
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'VIEW_WALLET_MANAGEMENT';

UPDATE `permissions`
SET `permission_name` = 'Wallet Transactions',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_root_id,
    `menu_order` = 3,
    `menu_icon` = 'fas fa-exchange-alt',
    `menu_url` = 'admin/wallet/wallet-transactions',
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'WALLET_TRANSACTIONS';

UPDATE `permissions`
SET `permission_name` = 'Provider Service Fees',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_root_id,
    `menu_order` = 4,
    `menu_icon` = 'fas fa-percent',
    `menu_url` = 'admin/wallet/provider-service-fees',
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'VIEW_SERVICE_FEES';

-- Legacy provider-wallet permission remains usable by role/API checks but is
-- no longer rendered as a duplicate sidebar entry.
UPDATE `permissions`
SET `permission_name` = 'Provider Wallet Access (legacy)',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_wallets_id,
    `menu_order` = 1,
    `menu_icon` = NULL,
    `menu_url` = NULL,
    `menu_level` = 3,
    `is_menu_item` = 0
WHERE `permission_code` = 'VIEW_PROVIDER_WALLETS';

UPDATE `permissions`
SET `permission_name` = 'Wallet Transaction Access (legacy)',
    `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_transactions_id,
    `menu_order` = 1,
    `menu_icon` = NULL,
    `menu_url` = NULL,
    `menu_level` = 3,
    `is_menu_item` = 0
WHERE `permission_code` = 'VIEW_WALLET_TRANSACTIONS';

UPDATE `permissions`
SET `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_wallets_id,
    `menu_level` = 3,
    `is_menu_item` = 0,
    `menu_url` = NULL
WHERE `permission_code` IN ('TOPUP_WALLETS', 'CREATE_WALLET', 'UPDATE_WALLET', 'DELETE_WALLET');

UPDATE `permissions`
SET `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_providers_id,
    `menu_level` = 3,
    `is_menu_item` = 0,
    `menu_url` = NULL
WHERE `permission_code` IN ('CREATE_TICKET_PROVIDER', 'UPDATE_TICKET_PROVIDER', 'DELETE_TICKET_PROVIDER');

UPDATE `permissions`
SET `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_transactions_id,
    `menu_level` = 3,
    `is_menu_item` = 0,
    `menu_url` = NULL
WHERE `permission_code` IN (
    'CREATE_WALLET_TRANSACTION',
    'UPDATE_WALLET_TRANSACTION',
    'DELETE_WALLET_TRANSACTION'
);

UPDATE `permissions`
SET `module_name` = 'WALLET',
    `parent_permission_id` = @wallet_providers_id,
    `menu_level` = 3,
    `is_menu_item` = 0,
    `menu_url` = NULL
WHERE `permission_code` IN ('CREATE_PROVIDERS', 'UPDATE_PROVIDERS');

-- =========================================================
-- 3. MOVE SERVICE TYPES UNDER SETTINGS
-- =========================================================
SET @settings_root_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_SETTINGS' LIMIT 1
);
SET @settings_branches_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'VIEW_BRANCHES' LIMIT 1
);
SET @settings_users_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'MANAGE_USERS' LIMIT 1
);

UPDATE `permissions`
SET `permission_name` = 'Service Types',
    `module_name` = 'SETTINGS',
    `parent_permission_id` = @settings_root_id,
    `menu_order` = 5,
    `menu_icon` = 'fas fa-concierge-bell',
    `menu_url` = 'admin/settings/service-types',
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'SERVICE-TYPES';

-- =========================================================
-- 4. NORMALIZE SETTINGS AND POS TREES
-- =========================================================
UPDATE `permissions`
SET `module_name` = 'SETTINGS',
    `parent_permission_id` = @settings_root_id,
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` IN (
    'VIEW_BRANCHES',
    'MANAGE_USERS',
    'PAYMENT METHOD',
    'BANK-ACCOUNTS',
    'SYSTEM_SETTINGS',
    'MANAGE_EMAIL_SETTINGS',
    'CRUD_DISCOUNT',
    'MANAGE_COMPANIES',
    'MANAGE_DEPARTMENTS',
    'MANAGE_SUB_DEPARTMENTS',
    'MANAGE_POSITIONS',
    'MANAGE_EMPLOYMENT_STATUS',
    'MANAGE_EMPLOYEES',
    'SERVICE-TYPES'
);

UPDATE `permissions`
SET `permission_name` = CASE `permission_code`
        WHEN 'VIEW_BRANCHES' THEN 'Branches'
        WHEN 'MANAGE_USERS' THEN 'User Management'
        WHEN 'PAYMENT METHOD' THEN 'Payment Methods'
        WHEN 'BANK-ACCOUNTS' THEN 'Bank Accounts'
        WHEN 'SYSTEM_SETTINGS' THEN 'System Settings'
        WHEN 'MANAGE_EMAIL_SETTINGS' THEN 'Email Settings'
        WHEN 'CRUD_DISCOUNT' THEN 'Discount Types'
        WHEN 'SERVICE-TYPES' THEN 'Service Types'
        ELSE `permission_name`
    END,
    `menu_order` = CASE `permission_code`
        WHEN 'SYSTEM_SETTINGS' THEN 1
        WHEN 'MANAGE_USERS' THEN 2
        WHEN 'VIEW_BRANCHES' THEN 3
        WHEN 'BANK-ACCOUNTS' THEN 4
        WHEN 'SERVICE-TYPES' THEN 5
        WHEN 'PAYMENT METHOD' THEN 6
        WHEN 'MANAGE_EMAIL_SETTINGS' THEN 7
        WHEN 'CRUD_DISCOUNT' THEN 8
        ELSE `menu_order`
    END
WHERE `permission_code` IN (
    'VIEW_BRANCHES', 'MANAGE_USERS', 'PAYMENT METHOD', 'BANK-ACCOUNTS',
    'SYSTEM_SETTINGS', 'MANAGE_EMAIL_SETTINGS', 'CRUD_DISCOUNT', 'SERVICE-TYPES'
);

UPDATE `permissions`
SET `module_name` = 'SETTINGS',
    `parent_permission_id` = CASE
        WHEN `permission_code` IN ('CREATE_BRANCHES', 'UPDATE_BRANCHES')
            THEN @settings_branches_id
        WHEN `permission_code` IN ('CREATE_USERS', 'UPDATE_USERS')
            THEN @settings_users_id
        ELSE `parent_permission_id`
    END,
    `menu_level` = 3,
    `is_menu_item` = 0,
    `menu_url` = NULL
WHERE `permission_code` IN ('CREATE_BRANCHES', 'UPDATE_BRANCHES', 'CREATE_USERS', 'UPDATE_USERS');

SET @pos_root_id = (
    SELECT `permission_id` FROM `permissions`
    WHERE `permission_code` = 'POS' LIMIT 1
);

UPDATE `permissions`
SET `module_name` = 'POS',
    `parent_permission_id` = @pos_root_id,
    `menu_order` = 2,
    `menu_level` = 2,
    `is_menu_item` = 1
WHERE `permission_code` = 'VIEW_POS_TRANSACTIONS';

-- =========================================================
-- 5. ENSURE PARENTS ARE ASSIGNED WHEN A CHILD IS ASSIGNED
-- =========================================================
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, @wallet_root_id
FROM `role_permissions` rp
JOIN `permissions` child ON child.`permission_id` = rp.`permission_id`
WHERE child.`permission_code` IN (
    'VIEW_TICKET_PROVIDERS', 'VIEW_WALLET_MANAGEMENT',
    'WALLET_TRANSACTIONS', 'VIEW_SERVICE_FEES', 'SERVICE-TYPES',
    'VIEW_PROVIDER_WALLETS', 'VIEW_WALLET_TRANSACTIONS',
    'CREATE_PROVIDERS', 'UPDATE_PROVIDERS'
)
AND @wallet_root_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` existing
    WHERE existing.`role_id` = rp.`role_id`
      AND existing.`permission_id` = @wallet_root_id
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, @wallet_wallets_id
FROM `role_permissions` rp
JOIN `permissions` child ON child.`permission_id` = rp.`permission_id`
WHERE child.`permission_code` IN ('VIEW_PROVIDER_WALLETS', 'TOPUP_WALLETS', 'CREATE_WALLET', 'UPDATE_WALLET', 'DELETE_WALLET', 'CREATE_PROVIDERS', 'UPDATE_PROVIDERS')
AND @wallet_wallets_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` existing
    WHERE existing.`role_id` = rp.`role_id`
      AND existing.`permission_id` = @wallet_wallets_id
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, @wallet_transactions_id
FROM `role_permissions` rp
JOIN `permissions` child ON child.`permission_id` = rp.`permission_id`
WHERE child.`permission_code` IN ('VIEW_WALLET_TRANSACTIONS', 'CREATE_WALLET_TRANSACTION', 'UPDATE_WALLET_TRANSACTION', 'DELETE_WALLET_TRANSACTION')
AND @wallet_transactions_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` existing
    WHERE existing.`role_id` = rp.`role_id`
      AND existing.`permission_id` = @wallet_transactions_id
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, @settings_root_id
FROM `role_permissions` rp
JOIN `permissions` child ON child.`permission_id` = rp.`permission_id`
WHERE child.`permission_code` IN (
    'VIEW_BRANCHES', 'MANAGE_USERS', 'SERVICE-TYPES', 'PAYMENT METHOD',
    'BANK-ACCOUNTS', 'SYSTEM_SETTINGS', 'MANAGE_EMAIL_SETTINGS',
    'CRUD_DISCOUNT', 'MANAGE_COMPANIES', 'MANAGE_DEPARTMENTS',
    'MANAGE_SUB_DEPARTMENTS', 'MANAGE_POSITIONS',
    'MANAGE_EMPLOYMENT_STATUS', 'MANAGE_EMPLOYEES',
    'CREATE_BRANCHES', 'UPDATE_BRANCHES', 'CREATE_USERS', 'UPDATE_USERS'
)
AND @settings_root_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` existing
    WHERE existing.`role_id` = rp.`role_id`
      AND existing.`permission_id` = @settings_root_id
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, @pos_root_id
FROM `role_permissions` rp
JOIN `permissions` child ON child.`permission_id` = rp.`permission_id`
WHERE child.`permission_code` = 'VIEW_POS_TRANSACTIONS'
AND @pos_root_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` existing
    WHERE existing.`role_id` = rp.`role_id`
      AND existing.`permission_id` = @pos_root_id
);
