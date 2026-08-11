<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/ticket-stock/variants/assets/css/variants.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/variants.css'); ?>">
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
          <?php if (NAVBAR_POSITION === 'top'): ?>
            <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
          <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
            <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
          <?php endif; ?>
        <?php else: ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
        <?php endif; ?>
        <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
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
        <?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center"><img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Ticket <span class="text-info fw-medium">Variants</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Ticket Stock</a></li>
                            <li class="breadcrumb-item active">Variants</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-auto d-flex align-items-center">
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#variantModal" onclick="resetVariantForm()">
                      <span class="fas fa-plus me-2"></span>Add Variant
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
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Variants</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5" id="statTotalVariants">0</p></div>
                  <div class="col-auto ps-0"><span class="fas fa-palette text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Active</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5 text-success" id="statActiveVariants">0</p></div>
                  <div class="col-auto ps-0"><span class="fas fa-check-circle text-success fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Inactive</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5 text-danger" id="statInactiveVariants">0</p></div>
                  <div class="col-auto ps-0"><span class="fas fa-times-circle text-danger fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Stock Controlled</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5 text-info" id="statStockControlled">0</p></div>
                  <div class="col-auto ps-0"><span class="fas fa-boxes text-info fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body">
            <div class="row g-3 align-items-center">
              <div class="col-md-4">
                <label for="providerFilter" class="form-label fw-bold">Provider</label>
                <select class="form-select" id="providerFilter" onchange="loadVariants()">
                  <option value="">All Providers</option>
                  <?php foreach ($providers as $provider): ?>
                    <option value="<?php echo $provider['provider_id']; ?>">
                      <?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label for="variantSearch" class="form-label fw-bold">Search</label>
                <input type="text" class="form-control" id="variantSearch" placeholder="Code or name" oninput="filterVariants()">
              </div>
              <div class="col-md-4 text-md-end">
                <button class="btn btn-outline-primary mt-4" type="button" onclick="loadVariants()">
                  <span class="fas fa-sync-alt me-1"></span>Refresh
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Variants Table -->
        <div class="card">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0" id="variantsTable">
                <thead class="table-light">
                  <tr>
                    <th>Provider</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Color</th>
                    <th>Stock</th>
                    <th>Ticket #</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="9" class="text-center text-muted py-4">Loading variants...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </main>

    <!-- Add/Edit Variant Modal -->
    <div class="modal fade" id="variantModal" tabindex="-1" aria-labelledby="variantModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="variantModalLabel">Add Variant</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="variantForm">
              <input type="hidden" id="variantId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="variantProviderId" class="form-label fw-bold">Provider <span class="text-danger">*</span></label>
                  <select class="form-select" id="variantProviderId" required>
                    <option value="">Select Provider</option>
                    <?php foreach ($providers as $provider): ?>
                      <option value="<?php echo $provider['provider_id']; ?>">
                        <?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="variantCode" class="form-label fw-bold">Variant Code <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="variantCode" maxlength="50" placeholder="e.g. ECONOMY" required>
                </div>
                <div class="col-md-6">
                  <label for="variantName" class="form-label fw-bold">Variant Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="variantName" maxlength="150" placeholder="e.g. Economy Class" required>
                </div>
                <div class="col-md-6">
                  <label for="variantColor" class="form-label fw-bold">Display Color</label>
                  <input type="color" class="form-control form-control-color w-100" id="variantColor" value="#0d6efd">
                </div>
                <div class="col-12">
                  <label for="variantDescription" class="form-label fw-bold">Description</label>
                  <textarea class="form-control" id="variantDescription" rows="2" placeholder="Optional description"></textarea>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="variantStockControlled" checked>
                    <label class="form-check-label" for="variantStockControlled">Stock Controlled</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="variantRequiresTicketNumber">
                    <label class="form-check-label" for="variantRequiresTicketNumber">Requires Ticket Number</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="variantActive" checked>
                    <label class="form-check-label" for="variantActive">Active</label>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveVariant()">
              <span class="fas fa-save me-1"></span>Save Variant
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
      window.variantsProviders = <?php echo json_encode($providers); ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/ticket-stock/variants/assets/js/variants.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/variants.js'); ?>"></script>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
