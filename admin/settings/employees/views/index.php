<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/employees/assets/css/employees.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/employees.css'); ?>">
<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) { var container = document.querySelector('[data-layout]'); container.classList.remove('container'); container.classList.add('container-fluid'); }
      </script>
      <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?>
        <?php if (NAVBAR_POSITION === 'top'): include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'double-top'): include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
      <?php else: include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php endif; ?>
      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php switch (NAVBAR_POSITION) { case 'combo': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break; case 'vertical': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break; } ?>
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
                      <h4 class="mb-0 text-primary fw-bold">Employee <span class="text-info fw-medium">Management</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                            <li class="breadcrumb-item active">Employees</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex align-items-center mt-3 mt-lg-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Employee</span></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Employees</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5" id="totalEmployees"><?php echo count($employees); ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-users text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card">
              <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                  <div class="col-md-4">
                    <div class="search-box">
                      <input type="text" class="form-control search-input" id="employeeSearch" placeholder="Search employees...">
                      <span class="fas fa-search search-icon"></span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <select class="form-select" id="departmentFilter">
                      <option value="">All Departments</option>
                      <?php foreach ($departments as $d): ?>
                      <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select class="form-select" id="positionFilter">
                      <option value="">All Positions</option>
                      <?php foreach ($positions as $p): ?>
                      <option value="<?php echo $p['pos_id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header"><h6 class="mb-0">Employee List</h6></div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="bg-light">
                      <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Date Hired</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="employeesTableBody">
                      <?php foreach ($employees as $emp): ?>
                      <?php $fullName = trim($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?>
                      <tr data-emp-id="<?php echo $emp['emp_id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($fullName)); ?>" data-dept="<?php echo $emp['b_department_id']; ?>" data-pos="<?php echo $emp['job_title']; ?>">
                        <td class="fw-semibold"><?php echo htmlspecialchars($fullName); ?></td>
                        <td><?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($emp['department_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($emp['company_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($emp['b_cont_no']); ?></td>
                        <td><?php echo $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '-'; ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-primary" onclick="viewEmployee(<?php echo $emp['emp_id']; ?>)"><span class="fas fa-eye"></span></button>
                          <button class="btn btn-sm btn-outline-primary" onclick="editEmployee(<?php echo $emp['emp_id']; ?>)"><span class="fas fa-edit"></span></button>
                          <button class="btn btn-sm btn-outline-danger" onclick="deleteEmployee(<?php echo $emp['emp_id']; ?>, '<?php echo htmlspecialchars($fullName, ENT_QUOTES); ?>')"><span class="fas fa-trash"></span></button>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div id="emptyState" class="text-center py-5 d-none"><p class="text-muted">No employees found</p></div>
              </div>
            </div>
          </div>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?></div><?php endif; ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    </div>
  </main>

  <?php include __DIR__ . '/modals/add_employee.php'; ?>
  <?php include __DIR__ . '/modals/edit_employee.php'; ?>
  <?php include __DIR__ . '/modals/view_employee.php'; ?>
  <?php include __DIR__ . '/modals/delete_employee.php'; ?>

  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header"><span id="toastIcon" class="me-2"></span><strong class="me-auto" id="toastTitle">Notification</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>
      <div class="toast-body" id="toastMessage"></div>
    </div>
  </div>

  <script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.CSRF_TOKEN = '<?php echo SecurityHelper::generateCSRFToken(); ?>';
    window.departments = <?php echo json_encode($departments); ?>;
    window.subDepartments = <?php echo json_encode($subDepartments); ?>;
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/settings/employees/assets/js/employees.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/employees.js'); ?>"></script>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
