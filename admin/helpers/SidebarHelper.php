<?php
/**
 * Sidebar Helper
 * Generates dynamic sidebar menu from permissions database
 */

class SidebarHelper {
    
    /**
     * Get user's role permissions
     */
    private static function getUserPermissions($userId) {
        $roleId = $_SESSION['user']['role_id'] ?? null;
        
        if (!$roleId) {
            return [];
        }
        
        // SUPER_ADMIN gets all permissions
        if ($_SESSION['user']['role_code'] === 'SUPER_ADMIN') {
            $sql = "SELECT * FROM permissions WHERE is_menu_item = 1 ORDER BY menu_order, permission_code";
            return Database::fetchAll($sql);
        }
        
        // Other roles get only assigned permissions
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.permission_id = rp.permission_id
                WHERE rp.role_id = :role_id 
                AND p.is_menu_item = 1
                ORDER BY p.menu_order, p.permission_code";
        
        return Database::fetchAll($sql, ['role_id' => $roleId]);
    }
    
    /**
     * Build permission tree structure
     */
    private static function buildPermissionTree($permissions) {
        $map = [];
        
        // First pass: create map with empty children
        foreach ($permissions as $permission) {
            $map[$permission['permission_id']] = $permission;
            $map[$permission['permission_id']]['children'] = [];
        }
        
        // Second pass: build tree using IDs to avoid PHP reference bugs
        $rootIds = [];
        foreach ($map as $id => $permission) {
            $parentId = $permission['parent_permission_id'];
            if ($parentId && isset($map[$parentId])) {
                $map[$parentId]['children'][] = $id;
            } else {
                $rootIds[] = $id;
            }
        }
        
        // Third pass: resolve ID references to actual node arrays (recursive)
        return self::resolveTree($rootIds, $map);
    }

    /**
     * Resolve tree from ID list to nested arrays
     */
    private static function resolveTree($ids, &$map) {
        $result = [];
        foreach ($ids as $id) {
            $node = $map[$id];
            $node['children'] = self::resolveTree($node['children'], $map);
            $result[] = $node;
        }
        return $result;
    }
    
    /**
     * Check if this item or any of its descendants is active
     */
    private static function isActiveOrHasActiveChild($item) {
        if (!empty($item['menu_url']) && self::isActive($item)) {
            return true;
        }
        foreach ($item['children'] as $child) {
            if (self::isActiveOrHasActiveChild($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render sidebar menu item
     */
    private static function renderMenuItem($item, $level = 0, $activePage = null) {
        $hasChildren = !empty($item['children']);
        $isActive = self::isActive($item);
        $hasActiveChild = $hasChildren && self::isActiveOrHasActiveChild($item);
        $isExpanded = $hasActiveChild; // Only expand when a child is active
        
        $html = '';
        
        if ($level === 0) {
            // Top-level item with potential children
            if ($hasChildren) {
                $html .= '<li class="nav-item">';
                $html .= '<a class="nav-link dropdown-indicator ' . ($hasActiveChild ? 'active' : '') . '" href="#' . self::slugify($item['permission_code']) . '" role="button" data-bs-toggle="collapse" aria-expanded="' . ($isExpanded ? 'true' : 'false') . '" aria-controls="' . self::slugify($item['permission_code']) . '">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<span class="nav-link-icon"><span class="' . htmlspecialchars($item['menu_icon'] ?? 'fas fa-circle') . '"></span></span>';
                $html .= '<span class="nav-link-text ps-1">' . htmlspecialchars($item['permission_name']) . '</span>';
                $html .= '</div>';
                $html .= '</a>';
                
                $html .= '<ul class="nav collapse ' . ($isExpanded ? 'show' : '') . '" id="' . self::slugify($item['permission_code']) . '">';
                foreach ($item['children'] as $child) {
                    $html .= self::renderMenuItem($child, $level + 1, $activePage);
                }
                $html .= '</ul>';
                $html .= '</li>';
            } else {
                // Single item without children
                $html .= '<li class="nav-item">';
                $html .= '<a class="nav-link ' . ($isActive ? 'active' : '') . '" href="' . BASE_URL . '/' . ltrim($item['menu_url'], '/') . '" role="button">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<span class="nav-link-icon"><span class="' . htmlspecialchars($item['menu_icon'] ?? 'fas fa-circle') . '"></span></span>';
                $html .= '<span class="nav-link-text ps-1">' . htmlspecialchars($item['permission_name']) . '</span>';
                $html .= '</div>';
                $html .= '</a>';
                $html .= '</li>';
            }
        } else {
            // Child item
            if ($hasChildren) {
                $html .= '<li class="nav-item">';
                $html .= '<a class="nav-link dropdown-indicator ' . ($hasActiveChild ? 'active' : '') . '" href="#' . self::slugify($item['permission_code']) . '" data-bs-toggle="collapse" aria-expanded="' . ($isExpanded ? 'true' : 'false') . '" aria-controls="' . self::slugify($item['permission_code']) . '">';
                $html .= '<div class="d-flex align-items-center"><span class="nav-link-text ps-1">' . htmlspecialchars($item['permission_name']) . '</span></div>';
                $html .= '</a>';
                
                $html .= '<ul class="nav collapse ' . ($isExpanded ? 'show' : '') . '" id="' . self::slugify($item['permission_code']) . '">';
                foreach ($item['children'] as $child) {
                    $html .= self::renderMenuItem($child, $level + 1, $activePage);
                }
                $html .= '</ul>';
                $html .= '</li>';
            } else {
                $html .= '<li class="nav-item">';
                $html .= '<a class="nav-link ' . ($isActive ? 'active' : '') . '" href="' . BASE_URL . '/' . ltrim($item['menu_url'], '/') . '">';
                $html .= '<div class="d-flex align-items-center"><span class="nav-link-text ps-1">' . htmlspecialchars($item['permission_name']) . '</span></div>';
                $html .= '</a>';
                $html .= '</li>';
            }
        }
        
        return $html;
    }
    
    /**
     * Check if menu item is active based on current URL
     */
    private static function isActive($item) {
        if (empty($item['menu_url'])) {
            return false;
        }
        
        $fullRequestPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // Strip the BASE_URL path prefix (e.g. /TMS) from the request URI
        $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
        if ($basePath && strpos($fullRequestPath, $basePath) === 0) {
            $currentPath = substr($fullRequestPath, strlen($basePath));
        } else {
            $currentPath = $fullRequestPath;
        }
        $currentPath = '/' . ltrim($currentPath, '/');
        
        $itemPath = '/' . rtrim(ltrim($item['menu_url'], '/'), '/');
        
        // Exact match or current path starts with item path followed by / (sub-pages)
        return $currentPath === $itemPath || strpos($currentPath, $itemPath . '/') === 0;
    }
    
    /**
     * Slugify string for ID
     */
    private static function slugify($string) {
        return strtolower(str_replace([' ', '_', '.'], '-', $string));
    }
    
    /**
     * Render sidebar label
     */
    private static function renderLabel($text) {
        return '<div class="row navbar-vertical-label-wrapper mt-3 mb-2">
            <div class="col-auto navbar-vertical-label">' . htmlspecialchars($text) . '</div>
            <div class="col ps-0">
              <hr class="mb-0 navbar-vertical-divider" />
            </div>
          </div>';
    }
    
    /**
     * Render complete sidebar
     */
    public static function render($activePage = null) {
        $permissions = self::getUserPermissions($_SESSION['user']['user_id'] ?? null);
        $tree = self::buildPermissionTree($permissions);
        
        $html = '';
        $lastModule = '';
        
        foreach ($tree as $item) {
            // Add label if module changed
            if ($item['module_name'] !== $lastModule && $item['module_name'] !== 'DASHBOARD') {
                $html .= self::renderLabel($item['module_name']);
                $lastModule = $item['module_name'];
            }
            
            $html .= self::renderMenuItem($item, 0, $activePage);
        }
        
        return $html;
    }
}
