<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/wallet/ticket-providers/assets/css/ticket-providers.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/ticket-providers.css'); ?>">
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
                      <h4 class="mb-0 text-primary fw-bold">Ticket <span class="text-info fw-medium">Providers</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Wallet</a></li>
                            <li class="breadcrumb-item active">Ticket Providers</li>
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

        <!-- Provider Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Total Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo count($providers); ?></p>
                  </div>
                  <div class="col-auto ps-0">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-plane text-primary fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Active Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($providers, fn($p) => $p['status'] === 'active')); ?></div>
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
                <h6 class="mb-0 mt-2">Inactive Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($providers, fn($p) => $p['status'] === 'inactive')); ?></div>
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
                  <h6 class="mb-3 text-800">Provider Types</h6>
                  <p class="font-sans-serif lh-1 mb-1 fs-5 fw-bold text-primary">
                    <?php
                    $types = array_count_values(array_column($providers, 'provider_type'));
                    echo implode(', ', array_keys($types));
                    ?>
                  </p>
                  <div class="fs-10 fw-semi-bold text-500">Available types</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Info Card -->
        <div class="card mb-3">
          <div class="card-body">
            <h6 class="fw-bold mb-3">How it works:</h6>
            <ul class="mb-0">
              <li>Manage ticket providers for different transportation services (airline, shipping, bus, etc.)</li>
              <li>Each provider can have multiple wallets across different branches</li>
              <li>Active providers can receive transactions, inactive providers are paused</li>
              <li>Provider codes must be unique (e.g., PAL, CEBPAC, 2GO)</li>
            </ul>
          </div>
        </div>

        <!-- Providers Table -->
        <div class="card mb-3">
          <div class="card-header bg-light py-3">
            <div class="row align-items-center">
              <div class="col">
                <h5 class="mb-0">Providers List</h5>
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" style="width: auto;" onchange="filterProviders(this.value)">
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover" id="providersTable">
                <thead class="table-light">
                  <tr>
                    <th>Provider Code</th>
                    <th>Provider Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($providers)): ?>
                    <tr>
                      <td colspan="6" class="text-center py-5">
                        <div class="empty-state">
                          <div class="empty-state-icon">
                            <span class="fas fa-plane"></span>
                          </div>
                          <div class="empty-state-text">No providers found</div>
                          <div class="empty-state-subtext">Add a provider to get started</div>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($providers as $provider): ?>
                      <tr data-status="<?php echo $provider['status']; ?>">
                        <td>
                          <span class="fw-bold"><?php echo htmlspecialchars($provider['provider_code']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($provider['provider_name']); ?></td>
                        <td>
                          <span class="badge <?php echo match($provider['provider_type']) {
                            'airline' => 'bg-primary',
                            'shipping' => 'bg-info',
                            'bus' => 'bg-warning',
                            default => 'bg-secondary'
                          }; ?>">
                            <?php echo ucfirst($provider['provider_type']); ?>
                          </span>
                        </td>
                        <td>
                          <div class="form-check form-switch">
                            <input class="form-check-input provider-status-switch" type="checkbox" 
                                   id="providerSwitch<?php echo $provider['provider_id']; ?>"
                                   data-provider-id="<?php echo $provider['provider_id']; ?>"
                                   <?php echo $provider['status'] === 'active' ? 'checked' : ''; ?>
                                   style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label" for="providerSwitch<?php echo $provider['provider_id']; ?>" style="font-size: 0.75rem;">
                              <?php echo $provider['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                            </label>
                          </div>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($provider['created_at'])); ?></td>
                        <td class="text-end">
                          <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editProvider(<?php echo $provider['provider_id']; ?>)">
                              <span class="fas fa-edit"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProvider(<?php echo $provider['provider_id']; ?>)">
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
    <?php include __DIR__ . '/modals/add_provider.php'; ?>
    <?php include __DIR__ . '/modals/edit_provider.php'; ?>

    <script src="<?php echo BASE_URL; ?>/admin/wallet/ticket-providers/assets/js/ticket-providers.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/ticket-providers.js'); ?>"></script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>
