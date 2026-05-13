<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/service-types/assets/css/service-types.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/service-types.css'); ?>">
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

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h2 class="mb-1">Service Types</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Service Types</li>
                  </ol>
                </nav>
              </div>
              <div>
                <button class="btn btn-primary" onclick="openAddServiceTypeModal()">
                  <span class="fas fa-plus me-2"></span>Add Service Type
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Page Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h6 class="mb-1 text-primary">Manage</h6>
                      <h4 class="mb-0 text-primary fw-bold">Service <span class="text-info fw-medium">Types</span></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <?php
        $total = count($serviceTypes);
        $active = count(array_filter($serviceTypes, fn($s) => $s['is_active']));
        $inactive = $total - $active;
        $walletRequired = count(array_filter($serviceTypes, fn($s) => $s['requires_wallet']));
        ?>
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Types</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo $total; ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-concierge-bell text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Active</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo $active; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-check-circle text-success fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Inactive</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo $inactive; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-times-circle text-danger fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Needs Wallet</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo $walletRequired; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-wallet text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- How it works -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2" style="cursor:pointer;" onclick="toggleHowItWorks()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0">How it works:</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body how-it-works-content" id="howItWorksContent">
            <ul class="mb-0">
              <li><strong>Ticket Sale</strong> — requires a provider wallet. Main service.</li>
              <li><strong>Print Fee / Photocopy / Scan</strong> — no wallet needed. Common non-ticket services charged directly to customer.</li>
              <li><strong>Default Amount</strong> — set a standard price per service. Cashier can override if <em>Allow Custom Amount</em> is enabled.</li>
              <li><strong>Requires Provider Wallet</strong> — enable for services that need to charge the provider's wallet balance (like ticket sales).</li>
              <li>Add new service types anytime without system changes (e.g. Notarial Fee, ID Lamination).</li>
            </ul>
          </div>
        </div>

        <!-- Filter & Search -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-5">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search service types..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="filterStatus" onchange="applyFilters()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-2">
                <select class="form-select" id="filterWallet" onchange="applyFilters()">
                  <option value="">All Types</option>
                  <option value="1">Needs Wallet</option>
                  <option value="0">No Wallet</option>
                </select>
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                  <span class="fas fa-undo me-1"></span>Reset
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Service Types Table -->
        <div class="card">
          <div class="card-body p-0">
            <?php if (empty($serviceTypes)): ?>
              <div class="empty-state py-5">
                <div class="empty-state-icon"><span class="fas fa-concierge-bell"></span></div>
                <div class="empty-state-text">No Service Types Found</div>
                <div class="empty-state-subtext">Click "Add Service Type" to get started.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="serviceTypesTable">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-3">Service</th>
                      <th>Default Amount</th>
                      <th>Settings</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($serviceTypes as $st): ?>
                    <tr class="service-type-row"
                        data-status="<?php echo $st['is_active'] ? 'active' : 'inactive'; ?>"
                        data-wallet="<?php echo $st['requires_wallet'] ? '1' : '0'; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($st['name'] . ' ' . $st['code'] . ' ' . ($st['description'] ?? ''))); ?>">
                      <td class="ps-3 py-3">
                        <div class="d-flex align-items-center">
                          <div class="service-icon bg-soft-primary text-primary me-3">
                            <span class="fas fa-concierge-bell"></span>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($st['name']); ?></div>
                            <div class="text-muted small"><code><?php echo htmlspecialchars($st['code']); ?></code></div>
                            <?php if ($st['description']): ?>
                              <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($st['description']); ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <span class="fw-semibold text-success">
                          ₱<?php echo number_format($st['default_amount'], 2); ?>
                        </span>
                        <?php if ($st['allow_custom_amount']): ?>
                          <div class="text-muted" style="font-size:0.75rem;">Custom allowed</div>
                        <?php else: ?>
                          <div class="text-muted" style="font-size:0.75rem;">Fixed price</div>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <div class="d-flex flex-wrap gap-1">
                          <?php if ($st['requires_wallet']): ?>
                            <span class="badge bg-soft-warning text-warning">
                              <span class="fas fa-wallet me-1"></span>Wallet
                            </span>
                          <?php endif; ?>
                          <?php if ($st['allow_custom_amount']): ?>
                            <span class="badge bg-soft-info text-info">
                              <span class="fas fa-sliders-h me-1"></span>Custom ₱
                            </span>
                          <?php endif; ?>
                          <?php if (!$st['requires_wallet'] && !$st['allow_custom_amount']): ?>
                            <span class="text-muted small">Fixed / Simple</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="py-3">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox"
                            <?php echo $st['is_active'] ? 'checked' : ''; ?>
                            onchange="toggleServiceTypeStatus(<?php echo $st['service_type_id']; ?>, this.checked)"
                            title="Toggle status">
                        </div>
                      </td>
                      <td class="py-3 text-end pe-3">
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editServiceType(<?php echo $st['service_type_id']; ?>)" title="Edit">
                          <span class="fas fa-edit"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteServiceType(<?php echo $st['service_type_id']; ?>, '<?php echo htmlspecialchars($st['name'], ENT_QUOTES); ?>')" title="Delete">
                          <span class="fas fa-trash"></span>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div id="noResultsMsg" class="text-center py-4 d-none">
          <span class="fas fa-search text-muted fs-3 d-block mb-2"></span>
          <p class="text-muted">No service types match your filters.</p>
        </div>

      </div>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/settings/service-types/assets/js/service-types.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/service-types.js'); ?>"></script>

  <?php include __DIR__ . '/modals/add_service_type.php'; ?>
  <?php include __DIR__ . '/modals/edit_service_type.php'; ?>
</body>
</html>
