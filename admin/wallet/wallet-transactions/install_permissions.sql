-- =========================================================
-- WALLET TRANSACTIONS PERMISSIONS INSTALL
-- Run this SQL to add the wallet transactions permissions
-- =========================================================

-- Add VIEW_WALLET permission under WALLET module (parent permission)
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'VIEW_WALLET',
    'WALLET',
    'ADMIN',
    NULL,
    10,
    'fas fa-wallet',
    'admin/wallet',
    1,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_WALLET');

-- Add VIEW_WALLET_TRANSACTIONS permission under WALLET
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'VIEW_WALLET_TRANSACTIONS',
    'WALLET TRANSACTIONS',
    'WALLET',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET'),
    1,
    'fas fa-exchange-alt',
    'admin/wallet/wallet-transactions',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_WALLET_TRANSACTIONS');

-- Add CREATE_WALLET_TRANSACTION action permission (non-menu)
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'CREATE_WALLET_TRANSACTION',
    'CREATE WALLET TRANSACTION',
    'ADMIN',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET_TRANSACTIONS'),
    0,
    NULL,
    NULL,
    2,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_WALLET_TRANSACTION');

-- Add UPDATE_WALLET_TRANSACTION action permission (non-menu)
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'UPDATE_WALLET_TRANSACTION',
    'UPDATE WALLET TRANSACTION',
    'ADMIN',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET_TRANSACTIONS'),
    0,
    NULL,
    NULL,
    2,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_WALLET_TRANSACTION');

-- Add DELETE_WALLET_TRANSACTION action permission (non-menu)
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'DELETE_WALLET_TRANSACTION',
    'DELETE WALLET TRANSACTION',
    'ADMIN',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET_TRANSACTIONS'),
    0,
    NULL,
    NULL,
    2,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_WALLET_TRANSACTION');

-- Assign wallet permissions to SUPER_ADMIN
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'SUPER_ADMIN' 
AND p.permission_code IN ('VIEW_WALLET', 'VIEW_WALLET_TRANSACTIONS', 'CREATE_WALLET_TRANSACTION', 'UPDATE_WALLET_TRANSACTION', 'DELETE_WALLET_TRANSACTION');

-- Assign wallet permissions to ADMIN role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'ADMIN' 
AND p.permission_code IN ('VIEW_WALLET', 'VIEW_WALLET_TRANSACTIONS', 'CREATE_WALLET_TRANSACTION', 'UPDATE_WALLET_TRANSACTION', 'DELETE_WALLET_TRANSACTION');

-- Assign VIEW_WALLET and VIEW_WALLET_TRANSACTIONS permissions to MANAGER role (for viewing only)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'MANAGER' 
AND p.permission_code IN ('VIEW_WALLET', 'VIEW_WALLET_TRANSACTIONS');

-- Add VIEW_WALLET_MANAGEMENT permission under WALLET
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'VIEW_WALLET_MANAGEMENT',
    'PROVIDER WALLETS',
    'WALLET',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET'),
    2,
    'fas fa-wallet',
    'admin/wallet/provider-wallets',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_WALLET_MANAGEMENT');

-- Add VIEW_SERVICE_FEES permission under WALLET
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT
    'VIEW_SERVICE_FEES',
    'SERVICE FEES',
    'WALLET',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET'),
    3,
    'fas fa-percent',
    'admin/wallet/provider-service-fees',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_SERVICE_FEES');

-- Assign wallet management and service fees permissions to SUPER_ADMIN
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'SUPER_ADMIN' 
AND p.permission_code IN ('VIEW_WALLET_MANAGEMENT', 'VIEW_SERVICE_FEES');

-- Assign wallet management and service fees permissions to ADMIN role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'ADMIN' 
AND p.permission_code IN ('VIEW_WALLET_MANAGEMENT', 'VIEW_SERVICE_FEES');

-- Assign VIEW_WALLET_MANAGEMENT and VIEW_SERVICE_FEES permissions to MANAGER role (for viewing only)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'MANAGER' 
AND p.permission_code IN ('VIEW_WALLET_MANAGEMENT', 'VIEW_SERVICE_FEES');
