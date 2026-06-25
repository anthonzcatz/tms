INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'MANAGE_DEPARTMENTS', 'DEPARTMENT MANAGEMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'), 11, 'fas fa-sitemap', 'admin/settings/departments', 2, 1 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_DEPARTMENTS');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'CREATE_DEPARTMENT', 'CREATE DEPARTMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_DEPARTMENTS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_DEPARTMENT');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'UPDATE_DEPARTMENT', 'UPDATE DEPARTMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_DEPARTMENTS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_DEPARTMENT');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'DELETE_DEPARTMENT', 'DELETE DEPARTMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_DEPARTMENTS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_DEPARTMENT');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM user_roles r CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND p.permission_code IN ('MANAGE_DEPARTMENTS', 'CREATE_DEPARTMENT', 'UPDATE_DEPARTMENT', 'DELETE_DEPARTMENT');
