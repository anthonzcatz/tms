<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/companies/assets/css/companies.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/companies.css'); ?>">
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
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical': include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
        }
        ?><?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Company <span class="text-info fw-medium">Management</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                            <li class="breadcrumb-item active">Companies</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex align-items-center mt-3 mt-lg-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                      <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Company</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Companies</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5" id="totalCompanies"><?php echo count($companies); ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-building text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Active</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5 text-success" id="activeCompanies"><?php echo count(array_filter($companies, fn($c) => $c['status'] === 'active')); ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-check-circle text-success fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Inactive</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5 text-warning" id="inactiveCompanies"><?php echo count(array_filter($companies, fn($c) => $c['comp_status'] !== 'active')); ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-pause-circle text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Search -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card">
              <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                  <div class="col-md-4">
                    <div class="search-box">
                      <input type="text" class="form-control search-input" id="companySearch" placeholder="Search companies...">
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

        <!-- Companies List -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0">Company List</h6>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="bg-light">
                      <tr>
                        <th>Company Name</th>
                        <th>Code</th>
                        <th>Address</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Added By</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="companiesTableBody">
                      <?php foreach ($companies as $company): ?>
                      <tr data-company-id="<?php echo $company['comp_id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($company['comp_name'])); ?>" data-status="<?php echo $company['comp_status']; ?>">
                        <td class="fw-semibold"><?php echo htmlspecialchars($company['comp_name']); ?></td>
                        <td><?php echo htmlspecialchars($company['comp_abbre'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($company['company_address'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($company['company_contact'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($company['company_email'] ?? '-'); ?></td>
                        <td>
                          <span class="badge bg-<?php echo $company['comp_status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($company['comp_status']); ?>
                          </span>
                        </td>
                        <td><?php echo htmlspecialchars($company['added_by_name'] ?? '-'); ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-primary" onclick="editCompany(<?php echo $company['comp_id']; ?>)"><span class="fas fa-edit"></span></button>
                          <button class="btn btn-sm btn-outline-danger" onclick="deleteCompany(<?php echo $company['comp_id']; ?>, '<?php echo htmlspecialchars($company['comp_name'], ENT_QUOTES); ?>')"><span class="fas fa-trash"></span></button>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div id="emptyState" class="text-center py-5 d-none">
                  <p class="text-muted">No companies found</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?></div><?php endif; ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    </div>
  </main>

  <?php include __DIR__ . '/modals/add_company.php'; ?>
  <?php include __DIR__ . '/modals/edit_company.php'; ?>
  <?php include __DIR__ . '/modals/delete_company.php'; ?>

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

  <script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.CSRF_TOKEN = '<?php echo SecurityHelper::generateCSRFToken(); ?>';
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/settings/companies/assets/js/companies.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/companies.js'); ?>"></script>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
