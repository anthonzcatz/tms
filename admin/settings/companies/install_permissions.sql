-- =========================================================
-- COMPANY MANAGEMENT PERMISSIONS INSTALL
-- =========================================================

INSERT IGNORE INTO permissions (
    permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item
)
SELECT
    'MANAGE_COMPANIES',
    'COMPANY MANAGEMENT',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'),
    10,
    'fas fa-building',
    'admin/settings/companies',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_COMPANIES');

INSERT IGNORE INTO permissions (
    permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item
)
SELECT 'CREATE_COMPANY', 'CREATE COMPANY', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_COMPANIES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_COMPANY');

INSERT IGNORE INTO permissions (
    permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item
)
SELECT 'UPDATE_COMPANY', 'UPDATE COMPANY', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_COMPANIES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_COMPANY');

INSERT IGNORE INTO permissions (
    permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item
)
SELECT 'DELETE_COMPANY', 'DELETE COMPANY', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_COMPANIES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_COMPANY');

-- Assign to SUPER_ADMIN and ADMIN
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN')
AND p.permission_code IN ('MANAGE_COMPANIES', 'CREATE_COMPANY', 'UPDATE_COMPANY', 'DELETE_COMPANY');
