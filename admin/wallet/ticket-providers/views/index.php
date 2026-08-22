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
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
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
         ?><?php endif; ?>
        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <!--/.bg-holder-->
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center"><img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Ticket <span class="text-info fw-medium">Providers</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
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

        <?php $activeWalletModule = 'ticket-providers'; include dirname(dirname(__DIR__)) . '/_partials/wallet_nav.php'; ?>

        <!-- Provider Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Total Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-6 fw-medium"><?php echo count($providers); ?></p>
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
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Active Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-6 fw-medium font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($providers, fn($p) => $p['status'] === 'active')); ?></div>
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
            <div class="card stat-hover h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Inactive Providers</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-6 fw-medium font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($providers, fn($p) => $p['status'] === 'inactive')); ?></div>
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
            <div class="card stat-hover h-100">
              <div class="card-body d-flex align-items-center">
                <div class="w-100">
                  <h6 class="mb-3 text-800">Provider Types</h6>
                  <div class="d-flex flex-wrap gap-1 mb-1">
                    <?php
                    $providerTypeCounts = array_count_values(array_column($providers, 'provider_type'));
                    ksort($providerTypeCounts);
                    foreach ($providerTypeCounts as $typeName => $typeCount):
                        $badgeClass = match (strtolower($typeName)) {
                            'airline' => 'bg-primary',
                            'shipping' => 'bg-info',
                            'bus' => 'bg-warning text-dark',
                            default => 'bg-secondary'
                        };
                    ?>
                      <span class="badge <?php echo $badgeClass; ?> fs-10">
                        <?php echo htmlspecialchars(ucfirst($typeName)); ?> (<?php echo (int) $typeCount; ?>)
                      </span>
                    <?php endforeach; ?>
                    <?php if (empty($providerTypeCounts)): ?>
                      <span class="badge bg-light text-muted border fs-10">No types</span>
                    <?php endif; ?>
                  </div>
                  <div class="fs-10 fw-semi-bold text-500">Available types</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- How it works -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2" style="cursor:pointer;" onclick="toggleHowItWorks()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0"><span class="fas fa-info-circle me-2 text-info"></span>How it works: Ticket Providers</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body how-it-works-content" id="howItWorksContent" style="display:none;">
            <ul class="mb-0">
              <li>Manage ticket providers for different transportation services (airline, shipping, bus, etc.).</li>
              <li>Each provider can have <strong>multiple wallets</strong> across different branches — set them up in <em>Provider Wallets</em>.</li>
              <li>Each provider can have <strong>service fees</strong> per branch — configure them in <em>Provider Service Fees</em>.</li>
              <li>Active providers appear in POS; inactive providers are paused and hidden from cashiers.</li>
              <li>Provider codes must be unique (e.g., PAL, CEBPAC, 2GO).</li>
            </ul>
          </div>
        </div>
        <script>
        function toggleHowItWorks() {
          const c = document.getElementById('howItWorksContent');
          const i = document.getElementById('howItWorksIcon');
          const open = c.style.display !== 'none';
          c.style.display = open ? 'none' : 'block';
          i.classList.toggle('fa-chevron-down', open);
          i.classList.toggle('fa-chevron-up', !open);
        }
        </script>

        <!-- Providers Table -->
        <div class="card mb-3 overflow-visible">
          <div class="card-header bg-light py-3">
            <div class="row align-items-center">
              <div class="col">
                <h5 class="mb-0">Providers List</h5>
              </div>
              <div class="col-auto">
                <button class="btn btn-primary btn-sm" onclick="openAddProviderModal()">
                  <span class="fas fa-plus me-1"></span>Add Provider
                </button>
              </div>
            </div>
          </div>
          <div class="card-body border-bottom bg-light">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="search-box position-relative">
                  <input type="text" class="form-control" id="providerSearch" placeholder="Code, name, main provider..." oninput="applyFilters()">
                  <span class="fas fa-search position-absolute top-50 end-0 translate-middle-y me-3 text-muted" style="pointer-events: none;"></span>
                </div>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select class="form-select" id="providerTypeFilter" onchange="applyFilters()">
                  <option value="">All Types</option>
                  <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Main Provider</label>
                <select class="form-select" id="providerMainProviderFilter" onchange="applyFilters()">
                  <option value="">All Main Providers</option>
                  <option value="standalone">Standalone (no main provider)</option>
                  <?php foreach ($mainProviders as $mainId => $mainName): ?>
                    <?php
                      $mainCode = '';
                      foreach ($providers as $p) {
                          if ($p['provider_id'] == $mainId) {
                              $mainCode = $p['provider_code'];
                              break;
                          }
                      }
                    ?>
                    <option value="<?php echo $mainId; ?>"><?php echo htmlspecialchars($mainCode . ' - ' . $mainName); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" id="providerStatusFilter" onchange="applyFilters()">
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div class="col-6 col-md-1">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="resetProviderFilters()" title="Reset filters">
                  <span class="fas fa-redo"></span>
                </button>
              </div>
            </div>
            <div class="mt-2 small text-muted" id="providerFilterInfo"></div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover" id="providersTable">
                <thead class="table-light">
                  <tr>
                    <th>Main Provider</th>
                    <th>Provider</th>
                    <th>Type</th>
                    <th class="text-center">Variants</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($providers)): ?>
                    <tr>
                      <td colspan="7" class="text-center py-5">
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
                      <?php
                        $searchText = strtolower(
                            $provider['provider_code'] . ' ' .
                            $provider['provider_name'] . ' ' .
                            ($provider['parent_provider_name'] ?? '') . ' ' .
                            $provider['provider_type']
                        );
                      ?>
                      <tr data-status="<?php echo $provider['status']; ?>"
                          data-provider-type="<?php echo $provider['provider_type']; ?>"
                          data-provider-id="<?php echo $provider['provider_id']; ?>"
                          data-main-provider="<?php echo $provider['parent_provider_id'] ?? ''; ?>"
                          data-search="<?php echo htmlspecialchars($searchText); ?>"
                          class="<?php echo !empty($provider['parent_provider_id']) ? 'sub-provider-row' : 'main-provider-row'; ?>"
                          <?php if (empty($provider['parent_provider_id'])): ?>data-expanded="false"<?php endif; ?>>
                        <td>
                          <?php if (!empty($provider['parent_provider_name'])): ?>
                            <span class="text-muted small"><?php echo htmlspecialchars($provider['parent_provider_name']); ?></span>
                          <?php elseif (!empty($provider['sub_provider_count'])): ?>
                            <span class="badge bg-light text-success border border-success">Main Provider</span>
                            <div class="small text-muted mt-1"><?php echo (int) $provider['sub_provider_count']; ?> sub-provider<?php echo $provider['sub_provider_count'] > 1 ? 's' : ''; ?></div>
                          <?php else: ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="fw-medium d-flex align-items-center <?php echo empty($provider['parent_provider_id']) ? '' : 'ps-3 border-start border-2 border-success'; ?>">
                            <?php if (empty($provider['parent_provider_id']) && !empty($provider['sub_provider_count'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center me-2 toggle-sub-btn" data-main-id="<?php echo $provider['provider_id']; ?>" onclick="toggleSubProviders(<?php echo $provider['provider_id']; ?>, this)" title="Show/hide sub-providers" style="width: 1.5rem; height: 1.5rem; font-size: 0.75rem; line-height: 1; padding: 0; text-decoration: none;">
                              <span class="toggle-icon-right"><i class="fas fa-chevron-right"></i></span>
                              <span class="toggle-icon-down d-none"><i class="fas fa-chevron-down"></i></span>
                            </button>
                            <?php endif; ?>
                            <span id="providerCode<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_code']); ?></span>
                            <?php if (!empty($provider['parent_provider_name'])): ?>
                              <span class="badge bg-light text-success border border-success ms-1">Sub of <?php echo htmlspecialchars($provider['parent_provider_code'] ?? $provider['parent_provider_name']); ?></span>
                            <?php elseif (!empty($provider['sub_provider_count'])): ?>
                              <span class="badge bg-light text-primary border border-primary ms-1">Main</span>
                            <?php else: ?>
                              <span class="badge bg-light text-secondary border border-secondary ms-1">Standalone</span>
                            <?php endif; ?>
                          </div>
                          <div class="small text-muted <?php echo empty($provider['parent_provider_id']) ? '' : 'ps-3'; ?>" id="providerName<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_name']); ?></div>
                        </td>
                        <td>
                          <span class="badge <?php echo match($provider['provider_type']) {
                            'airline' => 'bg-primary',
                            'shipping' => 'bg-info',
                            'bus' => 'bg-warning',
                            default => 'bg-secondary'
                          }; ?>">
                            <span id="providerType<?php echo (int) $provider['provider_id']; ?>"><?php echo ucfirst($provider['provider_type']); ?></span>
                          </span>
                        </td>
                        <td class="text-center">
                          <?php if (!empty($provider['variant_count'])): ?>
                            <span class="badge bg-info rounded-pill" title="<?php echo (int) $provider['variant_count']; ?> variant<?php echo $provider['variant_count'] > 1 ? 's' : ''; ?>">
                              <?php echo (int) $provider['variant_count']; ?> variant<?php echo $provider['variant_count'] > 1 ? 's' : ''; ?>
                            </span>
                          <?php else: ?>
                            <span class="badge bg-light text-muted border rounded-pill">No variants</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="form-check form-switch d-flex align-items-center ps-0 mb-0">
                            <input class="form-check-input provider-status-switch" type="checkbox"
                                   id="providerSwitch<?php echo $provider['provider_id']; ?>"
                                   data-provider-id="<?php echo $provider['provider_id']; ?>"
                                   <?php echo $provider['status'] === 'active' ? 'checked' : ''; ?>
                                   style="width: 2.5em; height: 1.25em; float: none; margin: 0;">
                            <label class="form-check-label ms-2" for="providerSwitch<?php echo $provider['provider_id']; ?>" style="font-size: 0.75rem;">
                              <?php echo $provider['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                            </label>
                          </div>
                        </td>
                        <td><?php echo Auth::formatTimestamp($provider['created_at'], 'M d, Y'); ?></td>
                        <td class="text-end">
                          <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-info" data-provider-id="<?php echo $provider['provider_id']; ?>" data-provider-name="<?php echo htmlspecialchars($provider['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" data-provider-code="<?php echo htmlspecialchars($provider['provider_code'], ENT_QUOTES, 'UTF-8'); ?>" onclick="openManageVariantsModal(this)" title="Manage <?php echo (int) ($provider['variant_count'] ?? 0); ?> variant<?php echo ($provider['variant_count'] ?? 0) == 1 ? '' : 's'; ?>">
                              <span class="fas fa-palette"></span> <span class="d-none d-md-inline">Variants</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editProvider(<?php echo $provider['provider_id']; ?>)">
                              <span class="fas fa-edit"></span>
                            </button>
                            <?php if (!empty($provider['sub_provider_count'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete: has <?php echo (int) $provider['sub_provider_count']; ?> sub-provider<?php echo $provider['sub_provider_count'] > 1 ? 's' : ''; ?>">
                              <span class="fas fa-trash"></span>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProvider(<?php echo $provider['provider_id']; ?>)">
                              <span class="fas fa-trash"></span>
                            </button>
                            <?php endif; ?>
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
    <?php include __DIR__ . '/modals/manage_variants.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/admin/wallet/ticket-providers/assets/js/ticket-providers.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/ticket-providers.js'); ?>"></script>

    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
    </div>
    <?php endif; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  </body>
</html>
