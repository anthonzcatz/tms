-- =========================================================
-- ENTERPRISE TICKETING SYSTEM
-- INITIAL SEEDING DATA
-- =========================================================
-- NOTE:
-- PSGC TABLES ARE EXCLUDED
-- =========================================================



-- =========================================================
-- USER ROLES
-- =========================================================
INSERT IGNORE INTO user_roles (
    role_code,
    role_name,
    role_description
) VALUES

(
    'SUPER_ADMIN',
    'Super Administrator',
    'Full system access'
),

(
    'CEO',
    'Chief Executive Officer',
    'Executive management access'
),

(
    'MANAGER',
    'Branch Manager',
    'Branch management access'
),

(
    'CASHIER',
    'Cashier',
    'Ticketing and wallet transaction access'
),

(
    'AUDITOR',
    'Auditor',
    'Audit and reporting access'
),

(
    'ADMIN',
    'Administrator',
    'System administration access'
),

(
    'STAFF',
    'Staff',
    'General staff access'
);



-- =========================================================
-- PERMISSIONS
-- =========================================================
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
) VALUES

-- DASHBOARD (Level 1 - Parent)
('VIEW_DASHBOARD', 'Dashboard', 'DASHBOARD', NULL, 1, 'fas fa-chart-pie', 'admin/dashboard', 1, 1),

-- USERS (Level 1 - Parent)
('VIEW_USERS', 'Users', 'USERS', NULL, 2, 'fas fa-users', 'admin/users', 1, 1),
('CREATE_USERS', 'Create Users', 'USERS', NULL, 0, NULL, NULL, 2, 0),
('UPDATE_USERS', 'Update Users', 'USERS', NULL, 0, NULL, NULL, 2, 0),

-- BRANCHES (Level 1 - Parent)
('VIEW_BRANCHES', 'Branches', 'BRANCHES', NULL, 3, 'fas fa-building', 'admin/branches', 1, 1),
('CREATE_BRANCHES', 'Create Branches', 'BRANCHES', NULL, 0, NULL, NULL, 2, 0),
('UPDATE_BRANCHES', 'Update Branches', 'BRANCHES', NULL, 0, NULL, NULL, 2, 0),

-- PROVIDERS (Level 1 - Parent)
('VIEW_PROVIDERS', 'Providers', 'PROVIDERS', NULL, 4, 'fas fa-wallet', 'admin/providers', 1, 1),
('CREATE_PROVIDERS', 'Create Providers', 'PROVIDERS', NULL, 0, NULL, NULL, 2, 0),
('UPDATE_PROVIDERS', 'Update Providers', 'PROVIDERS', NULL, 0, NULL, NULL, 2, 0),

-- WALLET (Level 1 - Parent)
('VIEW_WALLETS', 'Wallets', 'WALLETS', NULL, 5, 'fas fa-wallet', 'admin/wallets', 1, 1),
('TOPUP_WALLETS', 'Topup Wallets', 'WALLETS', NULL, 0, NULL, NULL, 2, 0),
('VIEW_WALLET_TRANSACTIONS', 'View Wallet Transactions', 'WALLETS', NULL, 0, NULL, NULL, 2, 0),

-- TICKETS (Level 1 - Parent)
('VIEW_TICKETS', 'Tickets', 'TICKETS', NULL, 6, 'fas fa-ticket-alt', 'admin/tickets', 1, 1),
('CREATE_TICKETS', 'Create Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0),
('CANCEL_TICKETS', 'Cancel Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0),
('REFUND_TICKETS', 'Refund Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0),

-- REPORTS (Level 1 - Parent)
('VIEW_REPORTS', 'Reports', 'REPORTS', NULL, 7, 'fas fa-chart-bar', 'admin/reports', 1, 1),

-- SETTINGS (Level 1 - Parent)
('VIEW_SETTINGS', 'Settings', 'SETTINGS', NULL, 8, 'fas fa-cog', 'admin/settings', 1, 1),
('UPDATE_SETTINGS', 'Update Settings', 'SETTINGS', NULL, 0, NULL, NULL, 2, 0),

-- MAINTENANCE (Level 1 - Parent)
('ENABLE_MAINTENANCE', 'Maintenance', 'MAINTENANCE', NULL, 9, 'fas fa-tools', 'admin/maintenance', 1, 1),

-- PERMISSIONS (Level 1 - Parent)
('MANAGE_PERMISSIONS', 'Permissions', 'PERMISSIONS', NULL, 10, 'fas fa-shield-alt', 'admin/settings/permissions', 1, 1);

-- DASHBOARD CHILDREN (Level 2 - Children of VIEW_DASHBOARD)
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
    'VIEW_DASHBOARD_ANALYTICS',
    'Analytics',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    1,
    'fas fa-chart-line',
    'admin/dashboard/analytics',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_ANALYTICS');

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
    'VIEW_DASHBOARD_CRM',
    'CRM',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    2,
    'fas fa-users',
    'admin/dashboard/crm',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_CRM');

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
    'VIEW_DASHBOARD_ECOMMERCE',
    'E-Commerce',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    3,
    'fas fa-shopping-cart',
    'admin/dashboard/e-commerce',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_ECOMMERCE');

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
    'VIEW_DASHBOARD_LMS',
    'LMS',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    4,
    'fas fa-graduation-cap',
    'admin/dashboard/lms',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_LMS');

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
    'VIEW_DASHBOARD_PROJECT',
    'Project Management',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    5,
    'fas fa-project-diagram',
    'admin/dashboard/project-management',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_PROJECT');

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
    'VIEW_DASHBOARD_SAAS',
    'SaaS',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    6,
    'fas fa-cloud',
    'admin/dashboard/saas',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_SAAS');

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
    'VIEW_DASHBOARD_SUPPORT',
    'Support Desk',
    'DASHBOARD',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_DASHBOARD'),
    7,
    'fas fa-headset',
    'admin/dashboard/support-desk',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'VIEW_DASHBOARD_SUPPORT');



-- =========================================================
-- ROLE PERMISSIONS
-- =========================================================

-- =========================================================
-- SUPER ADMIN = ALL PERMISSIONS
-- =========================================================
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'SUPER_ADMIN';



-- =========================================================
-- CEO PERMISSIONS
-- =========================================================
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM user_roles r
JOIN permissions p
WHERE r.role_code = 'CEO'
AND p.permission_code IN (
    'VIEW_DASHBOARD',
    'VIEW_BRANCHES',
    'VIEW_PROVIDERS',
    'VIEW_WALLETS',
    'VIEW_WALLET_TRANSACTIONS',
    'VIEW_TICKETS',
    'VIEW_REPORTS'
);



-- =========================================================
-- MANAGER PERMISSIONS
-- =========================================================
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM user_roles r
JOIN permissions p
WHERE r.role_code = 'MANAGER'
AND p.permission_code IN (
    'VIEW_DASHBOARD',
    'VIEW_USERS',
    'VIEW_BRANCHES',
    'VIEW_PROVIDERS',
    'VIEW_WALLETS',
    'VIEW_WALLET_TRANSACTIONS',
    'VIEW_TICKETS',
    'CREATE_TICKETS',
    'CANCEL_TICKETS',
    'REFUND_TICKETS',
    'VIEW_REPORTS'
);



-- =========================================================
-- CASHIER PERMISSIONS
-- =========================================================
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM user_roles r
JOIN permissions p
WHERE r.role_code = 'CASHIER'
AND p.permission_code IN (
    'VIEW_DASHBOARD',
    'VIEW_PROVIDERS',
    'VIEW_WALLETS',
    'VIEW_TICKETS',
    'CREATE_TICKETS'
);



-- =========================================================
-- AUDITOR PERMISSIONS
-- =========================================================
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM user_roles r
JOIN permissions p
WHERE r.role_code = 'AUDITOR'
AND p.permission_code IN (
    'VIEW_DASHBOARD',
    'VIEW_REPORTS',
    'VIEW_WALLET_TRANSACTIONS',
    'VIEW_TICKETS'
);



-- =========================================================
-- BUSINESS BRANCHES
-- =========================================================
INSERT IGNORE INTO business_branches (
    branch_code,
    branch_name,
    street_address,
    landmark,
    zip_code,
    contact_number,
    email,
    status
) VALUES

(
    'MAIN_BRANCH',
    'Main Branch',
    '123 Main Street',
    'Near City Hall',
    '8000',
    '09171234567',
    'mainbranch@example.com',
    'active'
);



-- =========================================================
-- DEFAULT SUPER ADMIN ACCOUNT
-- PASSWORD:
-- Change immediately after first login
-- =========================================================
-- UPDATE existing admin password (run this if user already exists)
UPDATE user_accounts
SET password_hash = '$argon2id$v=19$m=65536,t=4,p=1$TkFNbDQ0cVdhNlYyMlxiMQ$kbOhGqPLQ4SrBLOrNXWjPaUveUoJQqsyMk5uD+r/P6U'
WHERE email = 'admin@example.com';

-- INSERT new admin (run this if user doesn't exist)
INSERT INTO user_accounts (
    user_code,
    branch_id,
    role_id,
    fullname,
    username,
    email,
    password_hash,
    status,
    require_password_change
)
SELECT
    'USR-0001',
    NULL,
    r.role_id,
    'System Administrator',
    'admin',
    'admin@example.com',

    -- Password: admin123 (Argon2id hash)
    '$argon2id$v=19$m=65536,t=4,p=1$TkFNbDQ0cVdhNlYyMlxiMQ$kbOhGqPLQ4SrBLOrNXWjPaUveUoJQqsyMk5uD+r/P6U',

    'active',
    TRUE

FROM user_roles r
WHERE r.role_code = 'SUPER_ADMIN'
AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'admin@example.com');



-- =========================================================
-- DEFAULT SYSTEM DEVICE
-- =========================================================
INSERT IGNORE INTO system_devices (
    device_code,
    device_name,
    device_type,
    location_name,
    device_remark,
    status
) VALUES

(
    'MAIN-POS-001',
    'Main POS Terminal',
    'desktop',
    'Main Branch Front Desk',
    'Primary cashier terminal',
    'approved'
);



-- =========================================================
-- ACCOMMODATION TYPES
-- =========================================================
INSERT INTO accommodation_types (
    code,
    name
) VALUES

('ECONOMY', 'Economy'),
('TOURIST', 'Tourist');



-- =========================================================
-- DISCOUNT TYPES
-- =========================================================
INSERT INTO discount_types (
    code,
    name,
    description
) VALUES

(
    'REGULAR',
    'Regular',
    'Regular passenger'
),

(
    'STUDENT',
    'Student',
    'Student discounted fare'
),

(
    'SENIOR',
    'Senior Citizen',
    'Senior citizen discounted fare'
),

(
    'PWD',
    'PWD',
    'Person with disability discounted fare'
),

(
    'MINOR',
    'Minor',
    'Minor passenger discounted fare'
);



-- =========================================================
-- SAMPLE PROVIDERS
-- =========================================================
INSERT INTO ticket_providers (
    provider_code,
    provider_name,
    provider_type,
    status
) VALUES

(
    'PAL',
    'Philippine Airlines',
    'airline',
    'active'
),

(
    'CEBPAC',
    'Cebu Pacific',
    'airline',
    'active'
),

(
    '2GO',
    '2GO Travel',
    'shipping',
    'active'
);



-- =========================================================
-- PROVIDER SERVICE FEES
-- GENERAL DEFAULT FEES
-- =========================================================
INSERT INTO provider_service_fees (
    provider_id,
    branch_id,
    fee_type,
    fee_value,
    is_active
)
SELECT
    provider_id,
    NULL,
    'FIXED',
    100.00,
    TRUE
FROM ticket_providers;



-- =========================================================
-- PROVIDER WALLETS
-- MAIN BRANCH WALLETS
-- =========================================================
INSERT INTO provider_wallets (
    provider_id,
    branch_id,
    current_balance,
    status
)
SELECT
    p.provider_id,
    b.branch_id,
    0.00,
    'active'
FROM ticket_providers p
CROSS JOIN business_branches b
WHERE b.branch_code = 'MAIN_BRANCH';



-- =========================================================
-- SYSTEM SETTINGS
-- =========================================================
INSERT INTO system_settings (
    company_name,
    company_abbreviation,
    company_address,
    company_contact_number,
    company_email,
    company_tagline,
    receipt_footer,
    report_footer,
    system_timezone,
    system_currency,
    maintenance_mode,
    maintenance_message,
    allow_admin_during_maintenance
) VALUES (

    'Sample Ticketing Services Inc.',
    'STSI',

    '123 Main Street, Philippines',

    '09171234567',

    'support@example.com',

    'Fast, Reliable & Secure Ticketing',

    'Thank you for choosing our services.',

    'System Generated Report',

    'Asia/Manila',

    'PHP',

    FALSE,

    'System is under maintenance.',

    TRUE
);