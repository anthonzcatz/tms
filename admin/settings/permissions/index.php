<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Maintenance mode check (cached in session for performance)
$maintenanceCacheKey = 'maintenance_settings_cache';
$maintenanceCacheTime = 300; // 5 minutes cache

// Check if we have cached maintenance settings
if (isset($_SESSION[$maintenanceCacheKey]) && isset($_SESSION[$maintenanceCacheKey . '_time'])) {
    $cacheAge = time() - $_SESSION[$maintenanceCacheKey . '_time'];
    if ($cacheAge < $maintenanceCacheTime) {
        $maintenanceSettings = $_SESSION[$maintenanceCacheKey];
    }
}

// If no cache or cache expired, fetch from database
if (!isset($maintenanceSettings)) {
    $maintenanceSettings = Database::fetch(
        "SELECT maintenance_mode, maintenance_message, maintenance_start, maintenance_end, allow_admin_during_maintenance 
         FROM system_settings WHERE setting_id = 1"
    );
    
    // Cache the settings
    $_SESSION[$maintenanceCacheKey] = $maintenanceSettings;
    $_SESSION[$maintenanceCacheKey . '_time'] = time();
}

$maintenanceMode = $maintenanceSettings['maintenance_mode'] ?? 0;
$maintenanceStart = $maintenanceSettings['maintenance_start'] ?? null;
$maintenanceEnd = $maintenanceSettings['maintenance_end'] ?? null;
$allowAdmin = $maintenanceSettings['allow_admin_during_maintenance'] ?? 1;

if ($maintenanceMode) {
    $now = date('Y-m-d H:i:s');
    $inMaintenanceWindow = true;
    
    if ($maintenanceStart && $maintenanceStart > $now) {
        $inMaintenanceWindow = false;
    }
    if ($maintenanceEnd && $maintenanceEnd < $now) {
        $inMaintenanceWindow = false;
        // Auto-disable maintenance mode if end time has passed
        Database::execute(
            "UPDATE system_settings SET maintenance_mode = 0 WHERE setting_id = 1"
        );
        // Clear cache to reflect the change
        unset($_SESSION[$maintenanceCacheKey]);
        unset($_SESSION[$maintenanceCacheKey . '_time']);
    }
    
    if ($inMaintenanceWindow) {
        $user = Auth::user();
        $isAdmin = $user && ($user['role_code'] === 'SUPER_ADMIN' || $user['role_code'] === 'ADMIN');
        
        if (!($isAdmin && $allowAdmin)) {
            include dirname(dirname(__DIR__)) . '/includes/maintenance.php';
            exit;
        }
    }
}

// Permission gatekeeper - only SUPER_ADMIN can manage permissions
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/settings/permissions/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
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
