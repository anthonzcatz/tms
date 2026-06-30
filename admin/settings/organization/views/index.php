<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
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
      <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?>
        <?php if (NAVBAR_POSITION === 'top'): include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'double-top'): include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
      <?php else: include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php endif; ?>
      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
        }
        ?>
      <?php endif; ?>

        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Organization <span class="text-info fw-medium">Setup</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                            <li class="breadcrumb-item active">Organization</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php $activeModule = 'organization'; include dirname(dirname(__DIR__)) . '/_partials/hr_settings_nav.php'; ?>

        <!-- Count Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/companies" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-building fa-2x text-primary"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['company_count']); ?></h5>
                <p class="text-muted mb-0 small">Companies</p>
              </div>
            </a>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/departments" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-sitemap fa-2x text-info"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['department_count']); ?></h5>
                <p class="text-muted mb-0 small">Departments</p>
              </div>
            </a>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/sub-departments" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-folder-open fa-2x text-warning"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['sub_department_count']); ?></h5>
                <p class="text-muted mb-0 small">Sub-Departments</p>
              </div>
            </a>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/positions" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-briefcase fa-2x text-success"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['position_count']); ?></h5>
                <p class="text-muted mb-0 small">Positions</p>
              </div>
            </a>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/employment-status" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-user-check fa-2x text-secondary"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['employment_status_count']); ?></h5>
                <p class="text-muted mb-0 small">Employment Status</p>
              </div>
            </a>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo BASE_URL; ?>/admin/settings/employees" class="card text-decoration-none h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="mb-2"><span class="fas fa-users fa-2x text-danger"></span></div>
                <h5 class="mb-1"><?php echo number_format($counts['employee_count']); ?></h5>
                <p class="text-muted mb-0 small">Employees</p>
              </div>
            </a>
          </div>
        </div>

        <!-- Department Hierarchy -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-header"><h6 class="mb-0">Department Hierarchy</h6></div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="bg-light">
                      <tr>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Sub-Departments</th>
                        <th>Employees</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($departments as $dept): ?>
                      <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($dept['department_name']); ?></td>
                        <td><span class="badge bg-<?php echo $dept['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($dept['status']); ?></span></td>
                        <td>
                          <a href="<?php echo BASE_URL; ?>/admin/settings/sub-departments?department=<?php echo $dept['dept_id']; ?>" class="badge bg-<?php echo $dept['sub_department_count'] > 0 ? 'info' : 'light text-dark'; ?> text-decoration-none">
                            <?php echo $dept['sub_department_count']; ?> sub-department<?php echo $dept['sub_department_count'] !== 1 ? 's' : ''; ?>
                          </a>
                        </td>
                        <td>
                          <a href="<?php echo BASE_URL; ?>/admin/settings/employees?department=<?php echo $dept['dept_id']; ?>" class="badge bg-<?php echo $dept['employee_count'] > 0 ? 'info' : 'light text-dark'; ?> text-decoration-none">
                            <?php echo $dept['employee_count']; ?> employee<?php echo $dept['employee_count'] !== 1 ? 's' : ''; ?>
                          </a>
                        </td>
                        <td class="text-end">
                          <a href="<?php echo BASE_URL; ?>/admin/settings/sub-departments?department=<?php echo $dept['dept_id']; ?>" class="btn btn-sm btn-outline-info" title="View Sub-Departments"><span class="fas fa-folder-open"></span></a>
                          <a href="<?php echo BASE_URL; ?>/admin/settings/employees?department=<?php echo $dept['dept_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Employees"><span class="fas fa-users"></span></a>
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

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
  <?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
</body>
</html>
