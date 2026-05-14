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
        </script>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
        <?php if (NAVBAR_POSITION === 'top'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
        <div class="content">
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
         ?>
          
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
                      <img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                      <div class="ms-x1">
                        <h4 class="mb-0 text-primary fw-bold">Permission <span class="text-info fw-medium">Management</span></h4>
                        <h6 class="mb-1 text-primary">
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
                              <h5 class="card-title mb-3">
                                <span class="badge bg-primary"><?php echo $role['role_code']; ?></span>
                                Permissions for <?php echo $role['role_name']; ?>
                              </h5>
                              <div class="d-flex flex-wrap gap-2">
                                <?php 
                                $assignedPermissions = $rolePermissionsByRole[$role['role_code']] ?? [];
                                $assignedPermissionIds = array_column($assignedPermissions, 'permission_id');
                                ?>
                                <?php foreach ($allPermissions as $permission): ?>
                                  <?php $isAssigned = in_array($permission['permission_id'], $assignedPermissionIds); ?>
                                  <div class="d-flex align-items-center me-3 mb-2">
                                    <div class="form-check form-switch">
                                      <input class="form-check-input permission-toggle" 
                                             type="checkbox" 
                                             style="width: 3em; height: 1.5em;"
                                             id="perm_<?php echo $role['role_id']; ?>_<?php echo $permission['permission_id']; ?>"
                                             data-role-id="<?php echo $role['role_id']; ?>"
                                             data-permission-id="<?php echo $permission['permission_id']; ?>"
                                             <?php echo $isAssigned ? 'checked' : ''; ?>>
                                      <label class="form-check-label ms-2" for="perm_<?php echo $role['role_id']; ?>_<?php echo $permission['permission_id']; ?>">
                                        <span class="badge bg-light text-dark border">
                                          <?php echo $permission['permission_code']; ?>
                                        </span>
                                      </label>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
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
                            <span class="fas fa-plus me-1"></span> Add Role
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
                                  <td><?php echo date('M d, Y', strtotime($role['created_at'])); ?></td>
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
                                Permission Hierarchy
                              </h5>
                              <small class="text-muted">Manage permissions with multi-level menu structure</small>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                              <div class="d-flex gap-2">
                                <span class="badge bg-light text-dark border">
                                  <span class="fas fa-circle text-primary me-1" style="font-size: 6px;"></span>
                                  Menu Item
                                </span>
                                <span class="badge bg-light text-dark border">
                                  <span class="fas fa-circle text-secondary me-1" style="font-size: 6px;"></span>
                                  Hidden Action
                                </span>
                              </div>
                              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPermissionModal">
                                <span class="fas fa-plus me-1"></span> Add Permission
                              </button>
                            </div>
                          </div>
                        </div>
                        <div class="card-body p-0">
                          <div class="d-flex mb-3 px-3 pt-3">
                            <div class="search-box position-relative flex-grow-1">
                              <input class="form-control search-input form-control-sm" type="search" placeholder="Search permissions..." aria-label="Search" />
                              <span class="fas fa-search search-box-icon"></span>
                            </div>
                          </div>
                          <div class="table-responsive">
                            <table class="table table-hover mb-0" id="permissionsTable">
                              <thead class="bg-light">
                                <tr>
                                  <th style="width: 60px;" class="text-center">
                                    <span class="fas fa-eye"></span>
                                  </th>
                                  <th style="width: 200px;" class="sort" data-sort="code">Permission Code</th>
                                  <th class="sort" data-sort="name">Permission Name</th>
                                  <th style="width: 100px;" class="sort" data-sort="level">Level</th>
                                  <th style="width: 120px;" class="sort" data-sort="module">Module</th>
                                  <th style="width: 200px;" class="sort" data-sort="url">Menu URL</th>
                                  <th style="width: 80px;" class="text-center sort" data-sort="order">Order</th>
                                  <th style="width: 120px;" class="text-end no-sort">Actions</th>
                                </tr>
                              </thead>
                              <tbody class="list">
                                <?php
                                function renderPermissionTree($permissions, $level = 0) {
                                    foreach ($permissions as $permission) {
                                        $indent = $level * 24;
                                        $hasChildren = !empty($permission['children']);
                                        $isMenuItem = $permission['is_menu_item'];
                                        $bgClass = $level > 0 ? 'bg-body-tertiary bg-opacity-25' : '';
                                        ?>
                                        <tr class="<?php echo $bgClass; ?>">
                                          <td class="text-center">
                                            <?php if ($permission['menu_icon']): ?>
                                              <span class="<?php echo htmlspecialchars($permission['menu_icon']); ?> text-primary"></span>
                                            <?php else: ?>
                                              <span class="fas fa-circle text-muted" style="font-size: 6px;"></span>
                                            <?php endif; ?>
                                          </td>
                                          <td class="code">
                                            <div style="padding-left: <?php echo $indent; ?>px;">
                                              <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($permission['permission_code']); ?></code>
                                              <?php if ($hasChildren): ?>
                                                <span class="fas fa-chevron-down ms-2 text-muted" style="font-size: 10px;"></span>
                                              <?php endif; ?>
                                            </div>
                                          </td>
                                          <td class="name">
                                            <div style="padding-left: <?php echo $indent; ?>px;">
                                              <div class="d-flex align-items-center">
                                                <span class="fw-medium"><?php echo htmlspecialchars($permission['permission_name']); ?></span>
                                                <?php if (!$isMenuItem): ?>
                                                  <span class="badge bg-secondary ms-2" style="font-size: 10px;">Hidden</span>
                                                <?php endif; ?>
                                              </div>
                                            </div>
                                          </td>
                                          <td class="level">
                                            <span class="badge bg-<?php echo $permission['menu_level'] == 1 ? 'primary' : ($permission['menu_level'] == 2 ? 'success' : 'info'); ?>">
                                              L<?php echo $permission['menu_level']; ?>
                                            </span>
                                          </td>
                                          <td class="module">
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($permission['module_name']); ?></span>
                                          </td>
                                          <td class="url">
                                            <?php if ($permission['menu_url']): ?>
                                              <code class="bg-light px-2 py-1 rounded" style="font-size: 11px;"><?php echo htmlspecialchars($permission['menu_url']); ?></code>
                                            <?php else: ?>
                                              <span class="text-muted">-</span>
                                            <?php endif; ?>
                                          </td>
                                          <td class="order text-center">
                                            <span class="badge bg-light text-dark border"><?php echo $permission['menu_order'] ?? 0; ?></span>
                                          </td>
                                          <td class="text-end">
                                            <div class="btn-group">
                                              <button class="btn btn-sm btn-outline-primary" onclick="editPermission(<?php echo $permission['permission_id']; ?>)" title="Edit Permission">
                                                <span class="fas fa-edit"></span>
                                              </button>
                                              <button class="btn btn-sm btn-outline-danger" onclick="deletePermission(<?php echo $permission['permission_id']; ?>)" title="Delete Permission">
                                                <span class="fas fa-trash"></span>
                                              </button>
                                            </div>
                                          </td>
                                        </tr>
                                        <?php
                                        if ($hasChildren) {
                                            renderPermissionTree($permission['children'], $level + 1);
                                        }
                                    }
                                }
                                renderPermissionTree($permissionsTree);
                                ?>
                              </tbody>
                            </table>
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

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>
