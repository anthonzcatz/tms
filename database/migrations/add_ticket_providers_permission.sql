-- Add Ticket Providers Management Permission
-- This adds the ticket providers module to the sidebar under WALLETS

-- Insert VIEW_TICKET_PROVIDERS permission as a child of WALLETS
INSERT INTO permissions (
    permission_code, 
    permission_name, 
    module_name, 
    parent_permission_id, 
    menu_order, 
    menu_icon, 
    menu_url, 
    is_menu_item, 
    is_active
) VALUES (
    'VIEW_TICKET_PROVIDERS', 
    'Ticket Providers', 
    'WALLETS', 
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLETS'), 
    3, 
    'fas fa-plane', 
    'admin/wallet/ticket-providers', 
    1, 
    1
);

-- Assign to SUPER_ADMIN role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT role_id FROM user_roles WHERE role_code = 'SUPER_ADMIN'),
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_TICKET_PROVIDERS')
WHERE NOT EXISTS (
    SELECT 1 FROM role_permissions 
    WHERE role_id = (SELECT role_id FROM user_roles WHERE role_code = 'SUPER_ADMIN')
    AND permission_id = (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_TICKET_PROVIDERS')
);

-- Add CRUD permissions for ticket providers (optional, for future use)
INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, is_menu_item, is_active) VALUES
('CREATE_TICKET_PROVIDER', 'Create Ticket Provider', 'WALLETS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLETS'), 4, 0, 1),
('UPDATE_TICKET_PROVIDER', 'Update Ticket Provider', 'WALLETS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLETS'), 5, 0, 1),
('DELETE_TICKET_PROVIDER', 'Delete Ticket Provider', 'WALLETS', (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLETS'), 6, 0, 1);
