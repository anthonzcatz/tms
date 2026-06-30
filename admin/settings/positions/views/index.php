<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/positions/assets/css/positions.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/positions.css'); ?>">
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
                      <h4 class="mb-0 text-primary fw-bold">Position <span class="text-info fw-medium">Management</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                            <li class="breadcrumb-item active">Positions</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex align-items-center mt-3 mt-lg-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPositionModal"><span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Position</span></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php $activeModule = 'positions'; include dirname(dirname(__DIR__)) . '/_partials/hr_settings_nav.php'; ?>

        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Positions</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5" id="totalPositions"><?php echo count($positions); ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-briefcase text-primary fs-4"></span></div>
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
                      <input type="text" class="form-control search-input" id="positionSearch" placeholder="Search positions...">
                      <span class="fas fa-search search-icon"></span>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                      <option value="">All Status</option>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
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
              <div class="card-header"><h6 class="mb-0">Position List</h6></div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>Position Name</th><th>Code</th><th>Status</th><th>Employees</th><th>Added By</th><th>Date Added</th><th class="text-end">Actions</th></tr></thead>
                    <tbody id="positionsTableBody">
                      <?php foreach ($positions as $pos): ?>
                      <tr data-pos-id="<?php echo $pos['pos_id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($pos['position_name'])); ?>" data-status="<?php echo $pos['status']; ?>">
                        <td class="fw-semibold"><?php echo htmlspecialchars($pos['position_name']); ?></td>
                        <td><?php echo htmlspecialchars($pos['pos_code'] ?? '-'); ?></td>
                        <td><span class="badge bg-<?php echo $pos['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($pos['status']); ?></span></td>
                        <td>
                          <a href="<?php echo BASE_URL; ?>/admin/settings/employees/?position=<?php echo $pos['pos_id']; ?>" class="badge bg-<?php echo $pos['employee_count'] > 0 ? 'info' : 'light text-dark'; ?> text-decoration-none">
                            <?php echo $pos['employee_count']; ?> employee<?php echo $pos['employee_count'] !== 1 ? 's' : ''; ?>
                          </a>
                        </td>
                        <td><?php echo htmlspecialchars($pos['added_by_name'] ?? '-'); ?></td>
                        <td><?php echo $pos['pos_dateadded'] ? date('M d, Y', strtotime($pos['pos_dateadded'])) : '-'; ?></td>
                        <td class="text-end">
                          <a href="<?php echo BASE_URL; ?>/admin/settings/employees/?position=<?php echo $pos['pos_id']; ?>" class="btn btn-sm btn-outline-info" title="View Employees"><span class="fas fa-users"></span></a>
                          <button class="btn btn-sm btn-outline-primary" onclick="editPosition(<?php echo $pos['pos_id']; ?>)"><span class="fas fa-edit"></span></button>
                          <button class="btn btn-sm btn-outline-danger" onclick="deletePosition(<?php echo $pos['pos_id']; ?>, '<?php echo htmlspecialchars($pos['position_name'], ENT_QUOTES); ?>')" <?php echo $pos['employee_count'] > 0 ? 'disabled title="Cannot delete: employees are assigned"' : 'title="Delete"'; ?>><span class="fas fa-trash"></span></button>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div id="emptyState" class="text-center py-5 d-none"><p class="text-muted">No positions found</p></div>
              </div>
            </div>
          </div>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?></div><?php endif; ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    </div>
  </main>

  <?php include __DIR__ . '/modals/add_position.php'; ?>
  <?php include __DIR__ . '/modals/edit_position.php'; ?>
  <?php include __DIR__ . '/modals/delete_position.php'; ?>

  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header"><span id="toastIcon" class="me-2"></span><strong class="me-auto" id="toastTitle">Notification</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>
      <div class="toast-body" id="toastMessage"></div>
    </div>
  </div>

  <script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.CSRF_TOKEN = '<?php echo SecurityHelper::generateCSRFToken(); ?>';
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/settings/positions/assets/js/positions.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/positions.js'); ?>"></script>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
