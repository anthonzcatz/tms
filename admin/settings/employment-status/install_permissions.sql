INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'MANAGE_EMPLOYMENT_STATUS', 'EMPLOYMENT STATUS', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'), 14, 'fas fa-user-check', 'admin/settings/employment-status', 2, 1 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_EMPLOYMENT_STATUS');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'CREATE_EMPLOYMENT_STATUS', 'CREATE EMPLOYMENT STATUS', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYMENT_STATUS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_EMPLOYMENT_STATUS');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'UPDATE_EMPLOYMENT_STATUS', 'UPDATE EMPLOYMENT STATUS', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYMENT_STATUS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_EMPLOYMENT_STATUS');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'DELETE_EMPLOYMENT_STATUS', 'DELETE EMPLOYMENT STATUS', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_EMPLOYMENT_STATUS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_EMPLOYMENT_STATUS');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM user_roles r CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND p.permission_code IN ('MANAGE_EMPLOYMENT_STATUS', 'CREATE_EMPLOYMENT_STATUS', 'UPDATE_EMPLOYMENT_STATUS', 'DELETE_EMPLOYMENT_STATUS');
