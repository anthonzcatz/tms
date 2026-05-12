<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Permission gatekeeper - only SUPER_ADMIN can manage permissions
if ($_SESSION['user']['role_code'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    require __DIR__ . '/views/access_denied.php';
    exit;
}

// Fetch data directly from database (more efficient than API calls)
$permissions = Database::fetchAll(
    "SELECT * FROM permissions ORDER BY menu_order, permission_code"
);

// Group permissions by module (for backward compatibility)
$permissionsByModule = [];
foreach ($permissions as $permission) {
    $permissionsByModule[$permission['module_name']][] = $permission;
}

// Build hierarchical tree structure
$permissionsTree = [];
$permissionMap = [];

// First pass: create a map of all permissions
foreach ($permissions as $permission) {
    $permissionMap[$permission['permission_id']] = $permission;
    $permissionMap[$permission['permission_id']]['children'] = [];
}

// Second pass: build the tree structure
foreach ($permissions as $permission) {
    if ($permission['parent_permission_id'] && isset($permissionMap[$permission['parent_permission_id']])) {
        // Add as child to parent
        $permissionMap[$permission['parent_permission_id']]['children'][] = &$permissionMap[$permission['permission_id']];
    } else {
        // Add as root level
        $permissionsTree[] = &$permissionMap[$permission['permission_id']];
    }
}

// Get roles
$roles = Database::fetchAll(
    "SELECT * FROM user_roles ORDER BY role_code"
);

// Get role permissions
$rolePermissions = Database::fetchAll(
    "SELECT rp.*, r.role_code, p.permission_code, p.module_name 
     FROM role_permissions rp
     JOIN user_roles r ON r.role_id = rp.role_id
     JOIN permissions p ON p.permission_id = rp.permission_id
     ORDER BY r.role_code, p.module_name, p.permission_code"
);

// Group role permissions by role
$rolePermissionsByRole = [];
foreach ($rolePermissions as $rp) {
    $rolePermissionsByRole[$rp['role_code']][] = $rp;
}

// Flatten permissions for matrix view
$allPermissions = [];
foreach ($permissionsByModule as $module => $perms) {
    foreach ($perms as $perm) {
        $allPermissions[] = $perm;
    }
}

// Load view
require __DIR__ . '/views/index.php';
