<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', 'vertical');
}
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/vendors/simplebar/simplebar.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/vendors/select2/select2.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/resources/vendors/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/permissions/assets/css/permissions.css">
<?php
if (!function_exists('renderModulePermissionRow')) {
  function renderModulePermissionRow(array $permission, string $moduleName, callable $determinePermissionType, array $permissionMap, ?string $userRoleCode, array &$renderedTracker, int $indentLevel = 0) {
    if (isset($renderedTracker[$permission['permission_id']])) {
      return;
    }
    $renderedTracker[$permission['permission_id']] = true;
    $permissionType = $determinePermissionType($permission);
    $isMenuItem = (int)($permission['is_menu_item'] ?? 1) === 1;
    $rowClasses = ['permission-row'];
    $parentId = $permission['parent_permission_id'] ?? '';
    if ($permissionType === 'action' && $isMenuItem) {
      $rowClasses[] = 'table-warning';
    }
    if ($indentLevel > 0) {
      $rowClasses[] = 'child-row';
      $rowClasses[] = 'd-none';
    }
    $menuLevel = (int)($permission['menu_level'] ?? 1);
    $indentPadding = max(0, $indentLevel) * 20;
    $menuUrl = $permission['menu_url'] ?? '-';
    // Determine which children to render under this permission.
    // Priority 1: children in the same module as what's being displayed.
    // Priority 2: if none match, children in any sub-module of this permission's own module
    //             (e.g. VIEW_WALLET_MANAGEMENT lives in 'WALLET', its kids live in 'WALLET MANAGEMENT').
    // Never pull in children from unrelated modules (e.g. ADMIN).
    $allChildren = $permissionMap[$permission['permission_id']]['children'] ?? [];
    $ownModule = $permission['module_name'] ?? $moduleName;
    $moduleChildren = array_values(array_filter($allChildren, function($c) use ($moduleName) {
      return ($c['module_name'] ?? '') === $moduleName;
    }));
    if (!empty($moduleChildren)) {
      $children = $moduleChildren;
    } else {
      // Keep children whose module starts with or contains this permission's module name
      // (handles WALLET → WALLET MANAGEMENT / WALLET_MANAGEMENT sub-modules)
      $children = array_values(array_filter($allChildren, function($c) use ($ownModule) {
        $cm = $c['module_name'] ?? '';
        return stripos($cm, $ownModule) === 0 || stripos($ownModule, $cm) === 0;
      }));
    }
    $hasChildren = !empty($children);
    ?>
    <tr class="<?php echo implode(' ', $rowClasses); ?>"
        data-permission-id="<?php echo $permission['permission_id']; ?>"
        data-parent-id="<?php echo $parentId; ?>"
        data-has-children="<?php echo $hasChildren ? '1' : '0'; ?>"
        data-permission-type="<?php echo $permissionType; ?>"
        data-permission-code="<?php echo htmlspecialchars(strtoupper($permission['permission_code'])); ?>"
        data-visible="<?php echo $isMenuItem ? '1' : '0'; ?>">
      <td data-column="name">
        <div class="d-flex align-items-start" style="padding-left: <?php echo $indentPadding; ?>px;">
          <?php if ($hasChildren): ?>
          <button class="btn btn-link btn-sm p-0 me-2 permission-tree-toggle" type="button" data-permission-id="<?php echo $permission['permission_id']; ?>" aria-expanded="false">
            <span class="fas fa-chevron-right"></span>
          </button>
          <?php else: ?>
          <span class="permission-tree-spacer me-2"></span>
          <?php endif; ?>
          <div>
            <div class="fw-semibold mb-0"><?php echo htmlspecialchars($permission['permission_name']); ?></div>
            <code class="small text-muted"><?php echo htmlspecialchars($permission['permission_code']); ?></code>
            <?php if (!empty($permission['module_name']) && $permission['module_name'] !== $moduleName): ?>
              <div class="badge bg-light text-dark mt-1">Module: <?php echo htmlspecialchars($permission['module_name']); ?></div>
            <?php endif; ?>
          </div>
        </div>
      </td>
      <td>
        <span class="badge <?php echo $permissionType === 'action' ? 'bg-warning text-dark' : 'bg-primary'; ?>">
          <?php echo $permissionType === 'action' ? 'Action only' : 'Menu entry'; ?>
        </span>
      </td>
      <td><small class="text-muted"><?php echo htmlspecialchars($menuUrl ?: '-'); ?></small></td>
      <td class="text-center"><span class="badge bg-light text-dark"><?php echo $menuLevel; ?></span></td>
      <td class="text-center">
        <div class="d-flex flex-column align-items-center gap-1">
          <div class="form-check form-switch">
            <input class="form-check-input sidebar-toggle" 
                   type="checkbox" 
                   style="width: 2.5em; height: 1.25em;"
                   id="sidebar_<?php echo $permission['permission_id']; ?>"
                   data-permission-id="<?php echo $permission['permission_id']; ?>"
                   data-permission-type="<?php echo $permissionType; ?>"
                   data-module-name="<?php echo htmlspecialchars($moduleName); ?>"
                   <?php echo $isMenuItem ? 'checked' : ''; ?>>
          </div>
          <small id="sidebarStatus_<?php echo $permission['permission_id']; ?>" class="text-muted">
            <?php echo $isMenuItem ? 'Visible in sidebar' : 'Hidden'; ?>
          </small>
        </div>
      </td>
      <td class="text-center">
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" onclick="editPermission(<?php echo $permission['permission_id']; ?>)">
            <span class="fas fa-edit"></span>
          </button>
          <?php if ($userRoleCode === 'SUPER_ADMIN'): ?>
          <button class="btn btn-outline-danger" onclick="deletePermission(<?php echo $permission['permission_id']; ?>)">
            <span class="fas fa-trash"></span>
          </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
    if (!empty($children)) {
      foreach ($children as $childPermission) {
        renderModulePermissionRow($childPermission, $moduleName, $determinePermissionType, $permissionMap, $userRoleCode, $renderedTracker, $indentLevel + 1);
      }
    }
  }
}
?>
<body>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="container" data-layout="container">
        <script>
          var isFluid = JSON.parse(localStorage.getItem('isFluid'));
          if (isFluid) {
            var container = document.querySelector('[data-layout]');
            container.classList.remove('container');
            container.classList.add('container-fluid');
          }
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
         <?php
         switch (NAVBAR_POSITION) {
             case 'combo':
                 include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                 break;
             case 'vertical':
                 include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                 break;
             case 'top':
             case 'double-top':
             default:
                 break;
         }
         ?><?php endif; ?>
          
          <div class="row g-4 mb-4">
            <!-- Header Card -->
            <div class="col-12">
              <div class="card border-0 shadow-sm mb-4">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
                </div>
                <!--/.bg-holder-->

                <div class="card-header z-1">
                  <div class="row flex-between-center gx-0">
                    <div class="col-lg-auto d-flex align-items-center">
                      <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                      <div class="ms-x1">
                        <h4 class="mb-0 text-primary fw-bold">Permission <span class="text-info fw-medium">Management</span></h4>
                        <h6 class="mb-1 text-primary d-none d-sm-block">
                          <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                              <li class="breadcrumb-item"><a>Home</a></li>
                              <li class="breadcrumb-item"><a>Settings</a></li>
                              <li class="breadcrumb-item active">Permissions</li>
                            </ol>
                          </nav>
                        </h6>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            

            <!-- Tabs Navigation -->
            <div class="col-12">
              <div class="card border-0 shadow-sm mb-4">

              
                <div class="card-header p-0 bg-body-tertiary scrollbar-overlay">
                  <ul class="nav nav-tabs border-0 mb-0 flex-nowrap" id="permissionTabs" role="tablist">
                    <li class="nav-item text-nowrap" role="presentation">
                      <button class="nav-link active px-4 py-3" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab" aria-controls="roles" aria-selected="true">
                        <span class="fas fa-users-cog me-2"></span>
                        <span class="fw-medium">Role Permissions Matrix</span>
                      </button>
                    </li>
                    <li class="nav-item text-nowrap" role="presentation">
                      <button class="nav-link px-4 py-3" id="role-management-tab" data-bs-toggle="tab" data-bs-target="#role-management" type="button" role="tab" aria-controls="role-management" aria-selected="false">
                        <span class="fas fa-user-tag me-2"></span>
                        <span class="fw-medium">Role Management</span>
                      </button>
                    </li>
                    <li class="nav-item text-nowrap" role="presentation">
                      <button class="nav-link px-4 py-3" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions" aria-selected="false">
                        <span class="fas fa-sitemap me-2"></span>
                        <span class="fw-medium">Permissions by Module</span>
                      </button>
                    </li>
                    <li class="nav-item text-nowrap" role="presentation">
                      <button class="nav-link px-4 py-3" id="developer-tab" data-bs-toggle="tab" data-bs-target="#guide" type="button" role="tab" aria-controls="guide" aria-selected="false">
                        <span class="fas fa-code me-2"></span>
                        <span class="fw-medium">Developer Guide</span>
                      </button>
                    </li>
                  </ul>

                  <div class="tab-content p-4 bg-body-tertiary bg-opacity-25">
                    <!-- Role Permissions Matrix Tab -->
                    <div class="tab-pane fade show active" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                      <!-- Role Sub-tabs -->
                      <ul class="nav nav-pills mb-3 flex-nowrap" id="roleTabs" role="tablist">
                        <?php $firstRole = true; ?>
                        <?php foreach ($roles as $role): ?>
                        <li class="nav-item text-nowrap" role="presentation">
                          <button class="nav-link <?php echo $firstRole ? 'active' : ''; ?>" 
                                  id="role-<?php echo $role['role_code']; ?>-tab" 
                                  data-bs-toggle="pill" 
                                  data-bs-target="#role-<?php echo $role['role_code']; ?>" 
                                  type="button" 
                                  role="tab" 
                                  aria-controls="role-<?php echo $role['role_code']; ?>" 
                                  aria-selected="<?php echo $firstRole ? 'true' : 'false'; ?>">
                            <?php echo $role['role_name']; ?>
                          </button>
                        </li>
                        <?php $firstRole = false; ?>
                        <?php endforeach; ?>
                      </ul>

                      <!-- Role Tab Content -->
                      <div class="tab-content" id="roleTabsContent">
                        <?php foreach ($roles as $role): ?>
                        <div class="tab-pane fade <?php echo $role === reset($roles) ? 'show active' : ''; ?>" 
                             id="role-<?php echo $role['role_code']; ?>" 
                             role="tabpanel" 
                             aria-labelledby="role-<?php echo $role['role_code']; ?>-tab">
                          <div class="card">
                            <div class="card-body">
                              <div class="d-flex align-items-center justify-content-between mb-4">
                                <h5 class="card-title mb-0">
                                  <span class="badge bg-primary"><?php echo $role['role_code']; ?></span>
                                  Permissions for <?php echo $role['role_name']; ?>
                                </h5>
                                <div class="btn-group" role="group">
                                  <button type="button" class="btn btn-sm btn-outline-primary active" id="viewModuleBtn-<?php echo $role['role_id']; ?>" onclick="toggleView('module', <?php echo $role['role_id']; ?>)">
                                    <span class="fas fa-folder me-1"></span> By Module
                                  </button>
                                  <button type="button" class="btn btn-sm btn-outline-primary" id="viewAllBtn-<?php echo $role['role_id']; ?>" onclick="toggleView('all', <?php echo $role['role_id']; ?>)">
                                    <span class="fas fa-list me-1"></span> View All
                                  </button>
                                </div>
                              </div>
                              
                              <?php 
                              $assignedPermissions = $rolePermissionsByRole[$role['role_code']] ?? [];
                              $assignedPermissionIds = array_column($assignedPermissions, 'permission_id');
                              
                              // Group permissions by module
                              $permissionsByModule = [];
                              foreach ($allPermissions as $permission) {
                                $permissionsByModule[$permission['module_name']][] = $permission;
                              }
                              ?>
                              
                              <!-- Module View -->
                              <div class="row g-0" id="moduleView-<?php echo $role['role_id']; ?>">
                                <!-- Module List (Left Sidebar) -->
                                <div class="col-md-3">
                                  <div class="p-2 border-bottom">
                                    <div class="input-group input-group-sm">
                                      <span class="input-group-text"><span class="fas fa-search"></span></span>
                                      <input type="text" 
                                             class="form-control" 
                                             id="moduleSearch-<?php echo $role['role_id']; ?>"
                                             placeholder="Search modules..."
                                             onkeyup="filterModules(<?php echo $role['role_id']; ?>)">
                                    </div>
                                  </div>
                                  <div class="list-group module-list" id="moduleList-<?php echo $role['role_id']; ?>">
                                    <?php $firstModule = true; ?>
                                    <?php foreach ($permissionsByModule as $module => $permissions): ?>
                                    <button type="button" 
                                            class="list-group-item list-group-item-action <?php echo $firstModule ? 'active' : ''; ?> module-item"
                                            data-module="<?php echo htmlspecialchars($module); ?>"
                                            data-role-id="<?php echo $role['role_id']; ?>"
                                            onclick="selectModule('<?php echo htmlspecialchars($module); ?>', <?php echo $role['role_id']; ?>)">
                                      <div class="d-flex align-items-center">
                                        <span class="fas fa-folder me-2"></span>
                                        <span class="fw-medium"><?php echo htmlspecialchars($module); ?></span>
                                        <span class="badge bg-light text-dark ms-auto"><?php echo count($permissions); ?></span>
                                      </div>
                                    </button>
                                    <?php $firstModule = false; ?>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                                
                                <!-- Permissions Display (Right Side) -->
                                <div class="col-md-9">
                                  <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-light py-2 d-flex align-items-center justify-content-between">
                                      <h6 class="mb-0 fw-bold text-primary" id="moduleTitle-<?php echo $role['role_id']; ?>">
                                        <span class="fas fa-folder me-2"></span><?php echo htmlspecialchars(array_key_first($permissionsByModule)); ?>
                                      </h6>
                                      <div class="d-flex gap-2 align-items-center">
                                        <div class="input-group input-group-sm" style="width: 200px;">
                                          <span class="input-group-text"><span class="fas fa-search"></span></span>
                                          <input class="form-control" type="search" id="permissionSearch-<?php echo $role['role_id']; ?>" placeholder="Search permissions..." aria-label="Search" oninput="filterRolePermissions(<?php echo $role['role_id']; ?>)" />
                                        </div>
                                        <div class="d-flex gap-1">
                                          <button class="btn btn-sm btn-outline-success" onclick="enableModulePermissions(<?php echo $role['role_id']; ?>)">
                                            <span class="fas fa-check me-1"></span> Enable All
                                          </button>
                                          <button class="btn btn-sm btn-outline-danger" onclick="disableModulePermissions(<?php echo $role['role_id']; ?>)">
                                            <span class="fas fa-times me-1"></span> Disable All
                                          </button>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="card-body py-3" id="permissionsContainer-<?php echo $role['role_id']; ?>">
                                      <?php 
                                      $firstModule = true;
                                      foreach ($permissionsByModule as $module => $permissions): ?>
                                      <div class="module-permissions <?php echo $firstModule ? '' : 'd-none'; ?>" 
                                           data-module="<?php echo htmlspecialchars($module); ?>"
                                           data-role-id="<?php echo $role['role_id']; ?>">
                                        <div class="d-flex flex-column gap-2">
                                          <?php foreach ($permissions as $permission): ?>
                                            <?php $isAssigned = in_array($permission['permission_id'], $assignedPermissionIds); ?>
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded hover-bg">
                                              <div class="flex-grow-1">
                                                <small class="fw-medium text-dark"><?php echo htmlspecialchars($permission['permission_code']); ?></small>
                                                <div class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($permission['permission_name']); ?></div>
                                              </div>
                                              <div class="form-check form-switch ms-2">
                                                <input class="form-check-input permission-toggle" 
                                                       type="checkbox" 
                                                       style="width: 2.5em; height: 1.25em;"
                                                       id="perm_<?php echo $role['role_id']; ?>_<?php echo $permission['permission_id']; ?>"
                                                       data-role-id="<?php echo $role['role_id']; ?>"
                                                       data-permission-id="<?php echo $permission['permission_id']; ?>"
                                                       <?php echo $isAssigned ? 'checked' : ''; ?>>
                                              </div>
                                            </div>
                                          <?php endforeach; ?>
                                        </div>
                                      </div>
                                      <?php $firstModule = false; ?>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              
                              <!-- View All Permissions -->
                              <div class="d-none" id="allView-<?php echo $role['role_id']; ?>">
                                <div class="table-responsive">
                                  <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                      <tr>
                                        <th>Module</th>
                                        <th>Permission Code</th>
                                        <th>Permission Name</th>
                                        <th class="text-center">Enabled</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($allPermissions as $permission): ?>
                                      <?php $isAssigned = in_array($permission['permission_id'], $assignedPermissionIds); ?>
                                      <tr>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($permission['module_name']); ?></span></td>
                                        <td><code class="small"><?php echo htmlspecialchars($permission['permission_code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($permission['permission_name']); ?></td>
                                        <td class="text-center">
                                          <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input permission-toggle" 
                                                   type="checkbox" 
                                                   style="width: 2.5em; height: 1.25em;"
                                                   id="perm_<?php echo $role['role_id']; ?>_<?php echo $permission['permission_id']; ?>"
                                                   data-role-id="<?php echo $role['role_id']; ?>"
                                                   data-permission-id="<?php echo $permission['permission_id']; ?>"
                                                   <?php echo $isAssigned ? 'checked' : ''; ?>>
                                          </div>
                                        </td>
                                      </tr>
                                      <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <!-- Role Management Tab -->
                    <div class="tab-pane fade" id="role-management" role="tabpanel" aria-labelledby="role-management-tab">
                      <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                          <h5 class="mb-0 fw-bold">User Roles</h5>
                          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                            <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Role</span>
                          </button>
                        </div>
                        <div class="card-body">
                          <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                              <thead class="table-light">
                                <tr>
                                  <th>Role Code</th>
                                  <th>Role Name</th>
                                  <th>Description</th>
                                  <th>Created At</th>
                                  <th class="text-center">Actions</th>
                                </tr>
                              </thead>
                              <tbody id="rolesTableBody">
                                <?php foreach ($roles as $role): ?>
                                <tr>
                                  <td><span class="badge bg-primary"><?php echo htmlspecialchars($role['role_code']); ?></span></td>
                                  <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                  <td><?php echo htmlspecialchars($role['role_description'] ?? '-'); ?></td>
                                  <td><?php echo Auth::formatTimestamp($role['created_at'], 'M d, Y'); ?></td>
                                  <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRole(<?php echo $role['role_id']; ?>)">
                                      <span class="fas fa-edit"></span>
                                    </button>
                                    <?php if ($role['role_code'] !== 'SUPER_ADMIN'): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRole(<?php echo $role['role_id']; ?>)">
                                      <span class="fas fa-trash"></span>
                                    </button>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Permissions by Module Tab -->
                    <div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                      <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                          <div class="d-flex align-items-center justify-content-between">
                            <div>
                              <h5 class="mb-0 fw-bold text-primary">
                                <span class="fas fa-sitemap me-2"></span>
                                Permissions by Module
                              </h5>
                              <small class="text-muted">Manage permissions organized by module</small>
                            </div>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
                              <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Permission</span>
                            </button>
                          </div>
                        </div>
                        <div class="card-body p-0">
                          <div class="row g-0">
                            <!-- Module List (Left Sidebar) -->
                            <div class="col-md-3 border-end">
                              <div class="p-2 border-bottom">
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text"><span class="fas fa-search"></span></span>
                                  <input type="text" 
                                         class="form-control" 
                                         id="permissionModuleSidebarSearch"
                                         placeholder="Search modules..."
                                         onkeyup="filterPermissionModules()">
                                </div>
                              </div>
                              <div class="list-group list-group-flush" id="permissionModuleList">
                                <?php 
                                $permissionsByModule = [];
                                $modulePermissionSearchIndex = [];
                                $permissionTypeKeywords = ['UPDATE', 'DELETE', 'EDIT', 'REMOVE', 'ASSIGN', 'MANAGE', 'PROCESS', 'APPROVE'];
                                $determinePermissionType = function(array $permission) use ($permissionTypeKeywords) {
                                  $code = strtoupper($permission['permission_code'] ?? '');
                                  $menuLevel = (int)($permission['menu_level'] ?? 1);
                                  $menuUrl = trim($permission['menu_url'] ?? '');
                                  $isAction = $menuLevel > 2 || $menuUrl === '';
                                  foreach ($permissionTypeKeywords as $keyword) {
                                    if (strpos($code, $keyword) === 0 || strpos($code, '_' . $keyword) !== false) {
                                      $isAction = true;
                                      break;
                                    }
                                  }
                                  return $isAction ? 'action' : 'menu';
                                };

                                foreach ($allPermissions as $permission) {
                                  $moduleKey = $permission['module_name'] ?: 'Unassigned';
                                  $permissionsByModule[$moduleKey][] = $permission;
                                  $modulePermissionSearchIndex[$moduleKey][] = strtolower($permission['permission_name'] . ' ' . $permission['permission_code']);
                                }
                                ksort($permissionsByModule);
                                $moduleStats = [];
                                foreach ($permissionsByModule as $module => $permissionsGroup) {
                                  $visibleCount = 0;
                                  $hiddenCount = 0;
                                  $actionCount = 0;
                                  foreach ($permissionsGroup as $permission) {
                                    $isMenuItem = (int)($permission['is_menu_item'] ?? 1) === 1;
                                    $permissionType = $determinePermissionType($permission);
                                    if ($isMenuItem) {
                                      $visibleCount++;
                                    } else {
                                      $hiddenCount++;
                                    }
                                    if ($permissionType === 'action') {
                                      $actionCount++;
                                    }
                                  }
                                  $moduleStats[$module] = [
                                    'visible' => $visibleCount,
                                    'hidden' => $hiddenCount,
                                    'action' => $actionCount,
                                    'total' => count($permissionsGroup)
                                  ];
                                }
                                $firstModule = true;
                                foreach ($permissionsByModule as $module => $permissions):
                                  $stats = $moduleStats[$module];
                                ?>
                                <button type="button" 
                                        class="list-group-item list-group-item-action <?php echo $firstModule ? 'active' : ''; ?> permission-module-item"
                                        data-module="<?php echo htmlspecialchars($module); ?>"
                                        data-visible-count="<?php echo $stats['visible']; ?>"
                                        data-hidden-count="<?php echo $stats['hidden']; ?>"
                                        data-action-count="<?php echo $stats['action']; ?>"
                                        data-permission-index="<?php echo htmlspecialchars(implode(' ', $modulePermissionSearchIndex[$module] ?? [])); ?>"
                                        onclick="selectPermissionModule('<?php echo htmlspecialchars($module); ?>')">
                                  <div class="d-flex flex-column">
                                    <div class="d-flex align-items-center">
                                      <span class="fas fa-folder me-2 text-primary"></span>
                                      <span class="fw-medium"><?php echo htmlspecialchars($module); ?></span>
                                      <span class="badge text-bg-primary ms-auto"><?php echo $stats['total']; ?></span>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap mt-1">
                                      <span class="badge rounded-pill text-bg-success">Menu: <?php echo $stats['visible']; ?></span>
                                      <span class="badge rounded-pill text-bg-secondary">Non-menu: <?php echo $stats['hidden']; ?></span>
                                      <span class="badge rounded-pill text-bg-warning text-dark">Action: <?php echo $stats['action']; ?></span>
                                    </div>
                                  </div>
                                </button>
                                <?php $firstModule = false; ?>
                                <?php endforeach; ?>
                              </div>
                            </div>
                            
                            <!-- Permissions Display (Right Side) -->
                            <div class="col-md-9">
                              <div class="card border-0 shadow-none h-100">
                                <div class="card-header bg-light py-3">
                                  <?php 
                                  $initialModuleKey = array_key_first($permissionsByModule);
                                  $initialStats = $initialModuleKey ? $moduleStats[$initialModuleKey] : ['visible' => 0, 'hidden' => 0, 'action' => 0];
                                  ?>
                                  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2">
                                    <h6 class="mb-0 fw-bold text-primary" id="permissionModuleTitle">
                                      <span class="fas fa-folder me-2"></span><?php echo htmlspecialchars($initialModuleKey ?? 'Select a module'); ?>
                                    </h6>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                      <div class="input-group input-group-sm" style="width: 250px;">
                                        <span class="input-group-text"><span class="fas fa-search"></span></span>
                                        <input class="form-control" type="search" id="permissionModuleSearch" placeholder="Search permissions..." aria-label="Search" oninput="filterPermissionModule()" />
                                      </div>
                                      <select class="form-select form-select-sm" id="sidebarVisibilityFilter" style="width:auto;" onchange="handleVisibilityFilterChange(this.value)">
                                        <option value="all">All sidebar states</option>
                                        <option value="visible">Sidebar only</option>
                                        <option value="hidden">Hidden only</option>
                                      </select>
                                      <button class="btn btn-outline-secondary btn-sm" id="hideActionPermissionsBtn" type="button">
                                        <span class="fas fa-eye-slash me-1"></span>Hide action-only links
                                      </button>
                                      <small class="text-muted" id="actionToggleFeedback"></small>
                                    </div>
                                  </div>
                                  <div class="d-flex flex-wrap gap-2 mt-3" id="permissionModuleStats" data-active-module="<?php echo htmlspecialchars($initialModuleKey ?? ''); ?>">
                                    <span class="badge rounded-pill text-bg-success">
                                      Menu items: <span class="fw-semibold" data-stat="visible"><?php echo $initialStats['visible']; ?></span>
                                    </span>
                                    <span class="badge rounded-pill text-bg-secondary">
                                      Non-menu: <span class="fw-semibold" data-stat="hidden"><?php echo $initialStats['hidden']; ?></span>
                                    </span>
                                    <span class="badge rounded-pill text-bg-warning text-dark">
                                      Action-only: <span class="fw-semibold" data-stat="actions"><?php echo $initialStats['action']; ?></span>
                                    </span>
                                  </div>
                                </div>
                                <div class="card-body py-3" id="permissionModuleContainer">
                                  <div class="alert alert-info border-0 shadow-sm small" role="alert">
                                    <div class="d-flex">
                                      <span class="fas fa-lightbulb me-2 mt-1"></span>
                                      <div>
                                        Toggle <strong>Sidebar visibility</strong> to instantly hide transactional actions (e.g., Update or Delete). Yellow rows indicate action-only permissions that are still visible in the sidebar, such as "Update Ticket Provider". Non-menu permissions stay available to the backend even when hidden from the sidebar.
                                      </div>
                                    </div>
                                  </div>
                                  <?php 
                                  $firstModule = true;
                                  foreach ($permissionsByModule as $module => $permissions): 
                                    $moduleStat = $moduleStats[$module];
                                    $renderedTracker = [];
                                  ?>
                                  <div class="permission-module-section <?php echo $firstModule ? '' : 'd-none'; ?>" 
                                       data-module="<?php echo htmlspecialchars($module); ?>"
                                       data-visible-count="<?php echo $moduleStat['visible']; ?>"
                                       data-hidden-count="<?php echo $moduleStat['hidden']; ?>"
                                       data-action-count="<?php echo $moduleStat['action']; ?>">
                                    <div class="table-responsive">
                                      <table class="table table-hover table-bordered align-middle">
                                        <thead class="table-light">
                                          <tr>
                                            <th style="min-width: 240px;">Permission</th>
                                            <th>Type</th>
                                            <th>Menu URL</th>
                                            <th class="text-center">Level</th>
                                            <th class="text-center">Sidebar visibility</th>
                                            <th class="text-center">Actions</th>
                                          </tr>
                                        </thead>
                                        <tbody>
                                          <?php
                                          // A permission is a root in this module if it has no parent,
                                          // or its parent is not in this module's permission set.
                                          $modulePermIds = array_column($permissions, 'permission_id');
                                          $modulePermIds = array_flip($modulePermIds);
                                          $moduleRoots = [];
                                          foreach ($permissions as $permission) {
                                            $parentId = $permission['parent_permission_id'] ?? null;
                                            if (!$parentId || !isset($modulePermIds[$parentId])) {
                                              $moduleRoots[] = $permission;
                                            }
                                          }
                                          if (empty($moduleRoots)) {
                                            $moduleRoots = $permissions;
                                          }
                                          foreach ($moduleRoots as $permission):
                                            renderModulePermissionRow($permission, $module, $determinePermissionType, $permissionMap, $userRoleCode ?? null, $renderedTracker, 0);
                                          endforeach; ?>
                                        </tbody>
                                      </table>
                                    </div>
                                  </div>
                                  <?php $firstModule = false; ?>
                                  <?php endforeach; ?>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Developer Guide Tab -->
                    <div class="tab-pane fade" id="guide" role="tabpanel" aria-labelledby="guide-tab">
                      <div class="row">
                        <div class="col-md-6 mb-4 mb-md-0">
                          <h6 class="fw-bold mb-3">How to add new module permissions:</h6>
                          <ol class="mb-0">
                            <li class="mb-2">Click "Add Permission" button above</li>
                            <li class="mb-2">Enter permission code (e.g., <code>VIEW_CRM</code>)</li>
                            <li class="mb-2">Enter permission name (e.g., <code>View CRM</code>)</li>
                            <li class="mb-2">Enter module name (e.g., <code>CRM</code>)</li>
                            <li class="mb-2">Assign the permission to appropriate roles in the "Role Permissions Matrix" tab</li>
                            <li>Use <code>Auth::requirePermission('VIEW_CRM')</code> in your PHP files</li>
                          </ol>
                        </div>
                        <div class="col-md-6">
                          <h6 class="fw-bold mb-3">Quick Tips:</h6>
                          <ul class="mb-0">
                            <li class="mb-2">Use uppercase for permission codes</li>
                            <li class="mb-2">Group related permissions by module</li>
                            <li class="mb-2">Assign permissions to multiple roles</li>
                            <li>Use descriptive permission names</li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
          </div>
          <?php endif; ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
        </div>
      </div>
    </main>

    <!-- Bootstrap Toast for notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100; margin-top: 70px;">
      <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
          <strong class="me-auto">Success</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          Operation completed successfully!
        </div>
      </div>
      <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-danger text-white">
          <strong class="me-auto">Error</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="errorMessage">
          An error occurred.
        </div>
      </div>
    </div>

    <!-- Include Modal -->
    <?php include __DIR__ . '/modals/add_permission.php'; ?>

    <!-- Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
            <div class="position-relative z-1">
              <h4 class="mb-0 text-white" id="addRoleModalLabel">
                <span class="fas fa-user-shield me-2"></span>
                Add New Role
              </h4>
              <p class="fs-10 mb-0 text-white">Create a new user role with specific permissions</p>
            </div>
            <div data-bs-theme="dark">
              <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <div class="modal-body">
            <form id="addRoleForm">
              <input type="hidden" id="roleId" name="role_id">
              <div class="mb-3">
                <label for="roleCode" class="form-label fw-bold">Role Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="roleCode" name="role_code" required placeholder="e.g., SUPER_ADMIN, MANAGER">
                <div class="form-text">Use uppercase letters and underscores only</div>
              </div>
              <div class="mb-3">
                <label for="roleName" class="form-label fw-bold">Role Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="roleName" name="role_name" required placeholder="e.g., Super Administrator">
              </div>
              <div class="mb-3">
                <label for="roleDescription" class="form-label fw-bold">Description</label>
                <textarea class="form-control" id="roleDescription" name="role_description" rows="3" placeholder="Describe the role's purpose and responsibilities"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <span class="fas fa-times me-2"></span>Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="saveRole()">
              <span class="fas fa-save me-2"></span>Save Role
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Permission Confirmation Modal -->
    <div class="modal fade" id="deletePermissionModal" tabindex="-1" aria-labelledby="deletePermissionModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deletePermissionModalLabel">
              <span class="fas fa-exclamation-triangle me-2"></span>
              Confirm Delete Permission
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="text-center mb-4">
              <div class="icon-box text-danger mb-3">
                <span class="fas fa-trash-alt fa-3x"></span>
              </div>
              <p class="mb-2">Are you sure you want to delete this permission?</p>
              <p class="text-muted small mb-0">
                <strong>Warning:</strong> This action will also remove all role assignments for this permission. This cannot be undone.
              </p>
            </div>
            <div id="deletePermissionWarning" class="alert alert-warning d-none">
              <span class="fas fa-exclamation-circle me-2"></span>
              <span id="deletePermissionWarningText"></span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <span class="fas fa-times me-1"></span> Cancel
            </button>
            <button type="button" class="btn btn-danger" id="confirmDeletePermission">
              <span class="fas fa-trash me-1"></span> Delete Permission
            </button>
          </div>
        </div>
      </div>
    </div>


    <!-- Include JavaScript -->
    <script src="<?php echo BASE_URL; ?>/resources/vendors/jquery/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/select2/select2.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/list.js/list.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/admin/settings/permissions/assets/js/permissions.js"></script>

    <script>
    // Restore selected modules and active role tab on page load
    document.addEventListener('DOMContentLoaded', function() {
      restoreActiveRoleTab();
      restorePermissionModule();
      <?php foreach ($roles as $role): ?>
      restoreSelectedModule(<?php echo $role['role_id']; ?>);
      <?php endforeach; ?>
      
      // Save active role tab when clicked
      document.querySelectorAll('[data-bs-toggle="pill"][data-bs-target^="#role-"]').forEach(tabButton => {
        tabButton.addEventListener('shown.bs.tab', function() {
          const targetId = this.getAttribute('data-bs-target');
          const roleCode = targetId.replace('#role-', '');
          saveActiveRoleTab(roleCode);
        });
      });
    });
    </script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  </body>
</html>
