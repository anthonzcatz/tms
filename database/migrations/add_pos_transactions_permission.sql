-- Add POS Transactions Report permission as a sub-item under POS (permission_id = 47)
INSERT INTO permissions
    (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item)
VALUES
    ('VIEW_POS_TRANSACTIONS', 'POS Transactions', 'POS', 47, 10, 'fas fa-list-alt', 'admin/pos/transactions/', 2, 1);
