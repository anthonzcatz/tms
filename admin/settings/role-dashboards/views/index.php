<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/role-dashboards/assets/css/role-dashboards.css">
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
        <div class="content">
         <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>
          
          <div class="row g-4 mb-4">
            <!-- Page Header Card -->
            <div class="col-12">
              <div class="card border-0 shadow-sm mb-4">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
                </div>
                <!--/.bg-holder-->
                <div class="card-header z-1">
                  <div class="row flex-between-center gx-0">
                    <div class="col-lg-auto d-flex align-items-center"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                      <div class="ms-x1">
                        <h6 class="mb-1 text-primary">Welcome to</h6>
                        <h4 class="mb-0 text-primary fw-bold">Role <span class="text-info fw-medium">Dashboards</span></h4>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Card -->
          <div class="card mb-4">
            <div class="card-header bg-light py-3">
              <h5 class="mb-0">Default Dashboard Settings</h5>
            </div>
            <div class="card-body">
              <p class="text-muted mb-4">Configure the default landing dashboard for each user role. After login, users will be redirected to their role's assigned dashboard.</p>
              
              <div class="table-responsive">
                <table class="table table-striped table-hover">
                  <thead>
                    <tr>
                      <th>Role Code</th>
                      <th>Role Name</th>
                      <th>Default Dashboard</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                      <td>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($role['role_code']); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                      <td>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               id="dashboard_<?php echo $role['role_id']; ?>" 
                               value="<?php echo htmlspecialchars($role['default_dashboard']); ?>"
                               placeholder="e.g., /admin/dashboard/analytics">
                      </td>
                      <td>
                        <button class="btn btn-sm btn-primary" onclick="saveDashboard(<?php echo $role['role_id']; ?>)">
                          Save
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Info Card -->
          <div class="card">
            <div class="card-body">
              <h6 class="fw-bold mb-3">How it works:</h6>
              <ul class="mb-0">
                <li>Each user role can have a different default dashboard</li>
                <li>After successful login, users are redirected to their role's assigned dashboard</li>
                <li>Changes take effect immediately for the next login</li>
                <li>Only SUPER_ADMIN can modify these settings</li>
              </ul>
            </div>
          </div>

        </div>
        </div>
        <?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
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
      Dashboard setting saved successfully!
    </div>
  </div>
  <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-danger text-white">
      <strong class="me-auto">Error</strong>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="errorMessage">
      An error occurred while saving.
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="<?php echo BASE_URL; ?>/admin/settings/role-dashboards/assets/js/role-dashboards.js"></script>
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
</body>
</html>
