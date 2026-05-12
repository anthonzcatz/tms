-- =========================================================
-- USER MANAGEMENT PERMISSIONS INSTALL
-- Run this SQL to add the MANAGE_USERS permission
-- =========================================================

-- Add MANAGE_USERS permission under SETTINGS
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
    'MANAGE_USERS',
    'USER MANAGEMENT',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'),
    2,
    'fas fa-user-cog',
    'admin/settings/users',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_USERS');

-- Add CREATE_USER action permission (non-menu)
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
    'CREATE_USER',
    'CREATE USER',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_USERS'),
    0,
    NULL,
    NULL,
    3,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_USER');

-- Add UPDATE_USER action permission (non-menu)
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
    'UPDATE_USER',
    'UPDATE USER',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_USERS'),
    0,
    NULL,
    NULL,
    3,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_USER');

-- Add DELETE_USER action permission (non-menu)
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
    'DELETE_USER',
    'DELETE USER',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_USERS'),
    0,
    NULL,
    NULL,
    3,
    0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_USER');

-- Assign MANAGE_USERS permission to SUPER_ADMIN
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'SUPER_ADMIN' 
AND p.permission_code IN ('MANAGE_USERS', 'CREATE_USER', 'UPDATE_USER', 'DELETE_USER');

-- Assign MANAGE_USERS permission to ADMIN role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'ADMIN' 
AND p.permission_code IN ('MANAGE_USERS', 'CREATE_USER', 'UPDATE_USER', 'DELETE_USER');

-- Assign MANAGE_USERS permission to MANAGER role (for viewing users)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'MANAGER' 
AND p.permission_code IN ('MANAGE_USERS');
