INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'MANAGE_EMPLOYEES', 'EMPLOYEE MANAGEMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'), 15, 'fas fa-users', 'admin/settings/employees', 2, 1 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_EMPLOYEES');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'CREATE_EMPLOYEE', 'CREATE EMPLOYEE', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYEES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_EMPLOYEE');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'UPDATE_EMPLOYEE', 'UPDATE EMPLOYEE', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYEES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_EMPLOYEE');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'DELETE_EMPLOYEE', 'DELETE EMPLOYEE', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYEES'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_EMPLOYEE');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM user_roles r CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND p.permission_code IN ('MANAGE_EMPLOYEES', 'CREATE_EMPLOYEE', 'UPDATE_EMPLOYEE', 'DELETE_EMPLOYEE');
