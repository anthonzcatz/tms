<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/branches/assets/css/branches.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/branches.css'); ?>">
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
        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <!--/.bg-holder-->
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Business <span class="text-info fw-medium">Branches</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Settings</a></li>
                            <li class="breadcrumb-item active">Branches</li>
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

        <!-- Branch Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Total Branches</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo count($branches); ?></p>
                  </div>
                  <div class="col-auto ps-0">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-building text-primary fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Active Branches</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($branches, fn($b) => $b['status'] === 'active')); ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-check-circle text-success fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Inactive Branches</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($branches, fn($b) => $b['status'] === 'inactive')); ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-times-circle text-danger fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body d-flex align-items-center">
                <div class="w-100">
                  <h6 class="mb-3 text-800">Regions Covered</h6>
                  <p class="font-sans-serif lh-1 mb-1 fs-5 fw-bold text-primary">
                    <?php
                    $regions = array_unique(array_column($branches, 'region_name'));
                    echo count(array_filter($regions));
                    ?>
                  </p>
                  <div class="fs-10 fw-semi-bold text-500">Total regions</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Info Card -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2 cursor-pointer" onclick="toggleHowItWorks()" style="cursor: pointer;">
            <div class="d-flex align-items-center justify-content-between">
              <h6 class="fw-bold mb-0">How it works:</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body" id="howItWorksContent" style="display: none;">
            <ul class="mb-0">
              <li>Manage business branches across different regions, provinces, and cities</li>
              <li>Branches are linked to PSGC (Philippine Standard Geographic Code) data for accurate location</li>
              <li>Active branches can receive transactions, inactive branches are paused</li>
              <li>Branch codes must be unique (e.g., MAIN_BRANCH, CEBU_BRANCH)</li>
            </ul>
          </div>
        </div>

        <script>
        function toggleHowItWorks() {
            const content = document.getElementById('howItWorksContent');
            const icon = document.getElementById('howItWorksIcon');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                content.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
        </script>

        <!-- Branches Table -->
        <div class="card mb-3">
          <div class="card-header bg-light py-3">
            <div class="row align-items-center">
              <div class="col">
                <h5 class="mb-0">Branches List</h5>
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" style="width: auto;" onchange="filterBranches(this.value)">
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover" id="branchesTable">
                <thead class="table-light">
                  <tr>
                    <th>Branch Code</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($branches)): ?>
                    <tr>
                      <td colspan="6" class="text-center py-5">
                        <div class="empty-state">
                          <div class="empty-state-icon">
                            <span class="fas fa-building"></span>
                          </div>
                          <div class="empty-state-text">No branches found</div>
                          <div class="empty-state-subtext">Add a branch to get started</div>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($branches as $branch): ?>
                      <tr data-status="<?php echo $branch['status']; ?>">
                        <td>
                          <span class="fw-bold"><?php echo htmlspecialchars($branch['branch_code']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                        <td>
                          <small class="text-muted">
                            <?php 
                            $location = [];
                            if ($branch['barangay_name']) $location[] = $branch['barangay_name'];
                            if ($branch['city_municipality_name']) $location[] = $branch['city_municipality_name'];
                            if ($branch['province_name']) $location[] = $branch['province_name'];
                            if ($branch['region_name']) $location[] = $branch['region_name'];
                            echo implode(', ', array_slice($location, 0, 3));
                            ?>
                          </small>
                        </td>
                        <td>
                          <small class="text-muted">
                            <?php echo htmlspecialchars($branch['contact_number'] ?? '-'); ?>
                          </small>
                        </td>
                        <td>
                          <div class="form-check form-switch">
                            <input class="form-check-input branch-status-switch" type="checkbox" 
                                   id="branchSwitch<?php echo $branch['branch_id']; ?>"
                                   data-branch-id="<?php echo $branch['branch_id']; ?>"
                                   <?php echo $branch['status'] === 'active' ? 'checked' : ''; ?>
                                   style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label" for="branchSwitch<?php echo $branch['branch_id']; ?>" style="font-size: 0.75rem;">
                              <?php echo $branch['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                            </label>
                          </div>
                        </td>
                        <td class="text-end">
                          <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBranch(<?php echo $branch['branch_id']; ?>)">
                              <span class="fas fa-edit"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?php echo $branch['branch_id']; ?>)">
                              <span class="fas fa-trash"></span>
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/add_branch.php'; ?>
    <?php include __DIR__ . '/modals/edit_branch.php'; ?>

    <script src="<?php echo BASE_URL; ?>/admin/settings/branches/assets/js/branches.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/branches.js'); ?>"></script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>
