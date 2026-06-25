INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'MANAGE_POSITIONS', 'POSITION MANAGEMENT', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'), 12, 'fas fa-briefcase', 'admin/settings/positions', 2, 1 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_POSITIONS');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'CREATE_POSITION', 'CREATE POSITION', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_POSITIONS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'CREATE_POSITION');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'UPDATE_POSITION', 'UPDATE POSITION', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_POSITIONS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'UPDATE_POSITION');

INSERT IGNORE INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
SELECT 'DELETE_POSITION', 'DELETE POSITION', 'SETTINGS', (SELECT permission_id FROM permissions WHERE permission_code = 'MANAGE_POSITIONS'), 0, NULL, NULL, 3, 0 FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'DELETE_POSITION');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM user_roles r CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND p.permission_code IN ('MANAGE_POSITIONS', 'CREATE_POSITION', 'UPDATE_POSITION', 'DELETE_POSITION');
