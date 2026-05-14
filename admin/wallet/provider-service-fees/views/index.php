<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/wallet/provider-service-fees/assets/css/provider-service-fees.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/provider-service-fees.css'); ?>">
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
                      <h4 class="mb-0 text-primary fw-bold">Provider <span class="text-info fw-medium">Service Fees</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Wallet</a></li>
                            <li class="breadcrumb-item active">Service Fees</li>
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

        <!-- Service Fees Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Total Service Fees</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo count($fees); ?></p>
                  </div>
                  <div class="col-auto ps-0">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-percent text-primary fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Active Fees</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($fees, fn($f) => $f['is_active'])); ?></div>
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
                <h6 class="mb-0 mt-2">Inactive Fees</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($fees, fn($f) => !$f['is_active'])); ?></div>
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
                  <h6 class="mb-3 text-800">Fee Types</h6>
                  <p class="font-sans-serif lh-1 mb-1 fs-5 fw-bold text-primary">
                    <?php
                    $feeTypes = array_unique(array_column($fees, 'fee_type'));
                    echo count($feeTypes);
                    ?>
                  </p>
                  <div class="fs-10 fw-semi-bold text-500">Total fee types</div>
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
              <li>Manage service fees for different providers and branches</li>
              <li>Fees can be fixed amount or percentage based</li>
              <li>Active fees are applied to transactions, inactive fees are paused</li>
              <li>Only users with VIEW_WALLET_MANAGEMENT permission can access this module</li>
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

        <!-- Filter Section -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-3">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search fees..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-2">
                <select class="form-select" id="filterProvider" onchange="applyFilters()">
                  <option value="">All Providers</option>
                  <?php
                  $providers = array_unique(array_column($fees, 'provider_name'));
                  foreach ($providers as $provider) {
                      echo '<option value="' . htmlspecialchars($provider) . '">' . htmlspecialchars($provider) . '</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="col-md-2">
                <select class="form-select" id="filterBranch" onchange="applyFilters()">
                  <option value="">All Branches</option>
                  <?php
                  $branches = array_unique(array_column($fees, 'branch_name'));
                  foreach ($branches as $branch) {
                      echo '<option value="' . htmlspecialchars($branch) . '">' . htmlspecialchars($branch) . '</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="col-md-2">
                <select class="form-select" id="filterStatus" onchange="applyFilters()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-2">
                <select class="form-select" id="filterFeeType" onchange="applyFilters()">
                  <option value="">All Fee Types</option>
                  <?php
                  $feeTypes = array_unique(array_column($fees, 'fee_type'));
                  foreach ($feeTypes as $type) {
                      echo '<option value="' . htmlspecialchars($type) . '">' . htmlspecialchars($type) . '</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                  <span class="fas fa-undo"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Service Fees Cards Display -->
        <div class="row g-3 mb-3">
          <?php if (empty($fees)): ?>
            <div class="col-12">
              <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                  <div class="empty-state">
                    <div class="empty-state-icon">
                      <span class="fas fa-percent"></span>
                    </div>
                    <div class="empty-state-text">No service fees found</div>
                    <div class="empty-state-subtext">Create a service fee to get started</div>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($fees as $fee): ?>
              <div class="col-sm-6 col-md-4 fee-card" 
                   data-provider="<?php echo htmlspecialchars($fee['provider_name'] ?? ''); ?>"
                   data-branch="<?php echo htmlspecialchars($fee['branch_name'] ?? ''); ?>"
                   data-status="<?php echo $fee['is_active'] ? 'active' : 'inactive'; ?>"
                   data-fee-type="<?php echo htmlspecialchars($fee['fee_type'] ?? ''); ?>"
                   data-fee-type-search="<?php echo strtolower(htmlspecialchars($fee['fee_type'] ?? '')); ?>">
                <div class="card overflow-hidden shadow-sm h-100" style="min-width: 12rem">
                  <div class="bg-holder bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-1.png);">
                  </div>
                  <!--/.bg-holder-->
                  <div class="card-body position-relative">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                      <h6 class="mb-0"><?php echo htmlspecialchars($fee['fee_type'] ?? '-'); ?></h6>
                      <span class="badge <?php echo $fee['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $fee['is_active'] ? 'Active' : 'Inactive'; ?>
                      </span>
                    </div>
                    <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary">
                      <?php echo $fee['fee_type'] === 'PERCENT' ? ($fee['fee_value'] ?? 0) . '%' : '₱' . number_format($fee['fee_value'] ?? 0, 2); ?>
                    </div>
                    <p class="mb-2 text-muted fs-10">
                      <span class="fas fa-building me-1"></span><?php echo htmlspecialchars($fee['provider_name'] ?? '-'); ?>
                    </p>
                    <p class="mb-2 text-muted fs-10">
                      <span class="fas fa-map-marker-alt me-1"></span><?php echo htmlspecialchars($fee['branch_name'] ?? '-'); ?>
                    </p>
                    <div class="d-flex gap-2 mt-3">
                      <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" onclick="editFee(<?php echo $fee['fee_id']; ?>)">
                        <span class="fas fa-edit me-1"></span>Edit
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFee(<?php echo $fee['fee_id']; ?>)">
                        <span class="fas fa-trash"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/add_fee.php'; ?>
    <?php include __DIR__ . '/modals/edit_fee.php'; ?>

    <script src="<?php echo BASE_URL; ?>/admin/wallet/provider-service-fees/assets/js/provider-service-fees.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/provider-service-fees.js'); ?>"></script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>
