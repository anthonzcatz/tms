<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/users/assets/css/users.css">
<body>
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
      <div class="content">
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h2 class="mb-1">User Management</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">User Management</li>
                  </ol>
                </nav>
              </div>
              <div>
                <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
                  <span class="fas fa-user-plus me-2"></span>Add New User
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters and Stats -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card">
              <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                  <div class="col-md-3">
                    <div class="search-box">
                      <input type="text" class="form-control search-input" id="userSearch" placeholder="Search users...">
                      <span class="fas fa-search search-icon"></span>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="roleFilter">
                      <option value="">All Roles</option>
                      <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                      <option value="">All Status</option>
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                  </div>
                  <div class="col-md-5 text-md-end">
                    <div class="d-inline-flex gap-2">
                      <div class="badge bg-primary-subtle text-primary fs-10 px-3 py-2">
                        <span class="fas fa-users me-1"></span>
                        Total: <span id="totalUsers">0</span>
                      </div>
                      <div class="badge bg-success-subtle text-success fs-10 px-3 py-2">
                        <span class="fas fa-user-check me-1"></span>
                        Active: <span id="activeUsers">0</span>
                      </div>
                      <div class="badge bg-warning-subtle text-warning fs-10 px-3 py-2">
                        <span class="fas fa-user-clock me-1"></span>
                        Inactive: <span id="inactiveUsers">0</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Users List (Card View) -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card" id="usersCard">
              <div class="card-header border-bottom border-200 px-0">
                <div class="d-lg-flex justify-content-between">
                  <div class="row flex-between-center gy-2 px-x1">
                    <div class="col-auto pe-0">
                      <h6 class="mb-0">User Accounts</h6>
                    </div>
                    <div class="col-auto">
                      <div class="dropdown">
                        <button class="btn btn-falcon-default btn-sm dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                          <span class="fas fa-download me-1"></span>Export
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                          <li><a class="dropdown-item" href="#" onclick="exportUsers('csv')"><span class="fas fa-file-csv me-2"></span>CSV</a></li>
                          <li><a class="dropdown-item" href="#" onclick="exportUsers('excel')"><span class="fas fa-file-excel me-2"></span>Excel</a></li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="form-check d-none">
                  <input class="form-check-input" id="checkbox-bulk-card-users-select" type="checkbox" data-bulk-select='{"body":"card-user-body","actions":"table-user-actions","replacedElement":"table-user-replace-element"}' />
                </div>
                <div class="list bg-body-tertiary p-x1 d-flex flex-column gap-3" id="card-user-body">
                  <!-- Users will be loaded here as cards -->
                </div>
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center py-3 px-4 border-top">
                  <div class="d-flex align-items-center">
                    <span class="text-muted fs-10 me-2">Showing</span>
                    <select class="form-select form-select-sm" id="perPage" style="width: 70px;">
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                    <span class="text-muted fs-10 ms-2">entries</span>
                  </div>
                  <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0" id="pagination">
                      <!-- Pagination will be loaded here -->
                    </ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Bar (appears when selecting users) -->
        <div class="card position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg" id="quickActionsBar" style="display: none; z-index: 1040; min-width: 400px;">
          <div class="card-body py-3 px-4">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="fw-semibold"><span id="selectedCount">0</span> users selected</span>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm" onclick="bulkActivate()">
                  <span class="fas fa-check me-1"></span>Activate
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="bulkDeactivate()">
                  <span class="fas fa-ban me-1"></span>Deactivate
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                  <span class="fas fa-trash-alt me-1"></span>Delete
                </button>
                <button type="button" class="btn btn-falcon-default btn-sm" onclick="clearSelection()">
                  <span class="fas fa-times"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
      </div>
    </div>
  </main>

  <!-- Include Modals -->
  <?php include __DIR__ . '/modals/add_user.php'; ?>
  <?php include __DIR__ . '/modals/delete_user.php'; ?>

  <!-- Toast Container -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header">
        <span id="toastIcon" class="me-2"></span>
        <strong class="me-auto" id="toastTitle">Notification</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body" id="toastMessage"></div>
    </div>
  </div>

  <!-- Page Scripts -->
  <script>
    // Pass PHP data to JavaScript
    window.CURRENT_USER = <?php echo json_encode($currentUser); ?>;
    window.IS_SUPER_ADMIN = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.CSRF_TOKEN = '<?php echo SecurityHelper::generateCSRFToken(); ?>';
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/settings/users/assets/js/users.js"></script>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
</body>
</html>
