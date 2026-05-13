<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/payment-methods/assets/css/payment-methods.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/payment-methods.css'); ?>">
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
                <h2 class="mb-1">Payment Methods</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment Methods</li>
                  </ol>
                </nav>
              </div>
              <div>
                <button class="btn btn-primary" onclick="openAddMethodModal()">
                  <span class="fas fa-plus me-2"></span>Add Payment Method
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
                      <h4 class="mb-0 text-primary fw-bold">Payment <span class="text-info fw-medium">Methods</span></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <?php
        $totalMethods = count($methods);
        $activeMethods = count(array_filter($methods, fn($m) => $m['is_active']));
        $inactiveMethods = $totalMethods - $activeMethods;
        $typeGroups = array_count_values(array_column($methods, 'method_type'));
        ?>
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Methods</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-5" id="stat-total"><?php echo $totalMethods; ?></p>
                  </div>
                  <div class="col-auto ps-0">
                    <span class="fas fa-credit-card text-primary fs-4"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Active</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1" id="stat-active"><?php echo $activeMethods; ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4">
                    <span class="fas fa-check-circle text-success fs-4"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Inactive</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1" id="stat-inactive"><?php echo $inactiveMethods; ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4">
                    <span class="fas fa-times-circle text-danger fs-4"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Types</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count($typeGroups); ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4">
                    <span class="fas fa-layer-group text-info fs-4"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- How it works (collapsible) -->
        <div class="card mb-3">
          <div class="card-header py-3" style="cursor:pointer;" onclick="toggleHowItWorks()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><span class="fas fa-info-circle me-2 text-info"></span>How it works: Payment Methods</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body how-it-works-content" id="howItWorksContent">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-money-bill-wave text-success me-3 mt-1 fs-5"></span>
                  <div><strong>Cash</strong><p class="text-muted mb-0 small">No confirmation needed. Cashier receives cash directly.</p></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-university text-primary me-3 mt-1 fs-5"></span>
                  <div><strong>Bank Transfer</strong><p class="text-muted mb-0 small">Requires reference number and confirmation by manager/encoder.</p></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-mobile-alt text-purple me-3 mt-1 fs-5"></span>
                  <div><strong>E-Wallet (GCash)</strong><p class="text-muted mb-0 small">Requires reference number. Manager confirms receipt.</p></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-file-invoice text-warning me-3 mt-1 fs-5"></span>
                  <div><strong>Charge (Utang)</strong><p class="text-muted mb-0 small">Customer owes amount. Tracked per customer. Payable later.</p></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-plus-circle text-info me-3 mt-1 fs-5"></span>
                  <div><strong>Adding New Methods</strong><p class="text-muted mb-0 small">New payment methods (e.g. PayMaya) can be added anytime without system changes.</p></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-start">
                  <span class="fas fa-sliders-h text-secondary me-3 mt-1 fs-5"></span>
                  <div><strong>Mixed Payments</strong><p class="text-muted mb-0 small">1 transaction can use multiple payment methods (e.g. ₱1000 cash + ₱500 bank).</p></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter & Search -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-4">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search methods..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="filterType" onchange="applyFilters()">
                  <option value="">All Types</option>
                  <option value="CASH">Cash</option>
                  <option value="BANK_TRANSFER">Bank Transfer</option>
                  <option value="E_WALLET">E-Wallet</option>
                  <option value="CHARGE">Charge</option>
                  <option value="CARD">Card</option>
                  <option value="OTHER">Other</option>
                </select>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="filterStatus" onchange="applyFilters()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
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

        <!-- Payment Methods Table -->
        <div class="card">
          <div class="card-body p-0">
            <?php if (empty($methods)): ?>
              <div class="empty-state py-5">
                <div class="empty-state-icon"><span class="fas fa-credit-card"></span></div>
                <div class="empty-state-text">No Payment Methods Found</div>
                <div class="empty-state-subtext">Click "Add Payment Method" to get started.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="methodsTable">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-3">Method</th>
                      <th>Type</th>
                      <th>Settings</th>
                      <th>Sort</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($methods as $method):
                      $typeColors = [
                        'CASH' => 'success', 'BANK_TRANSFER' => 'primary',
                        'E_WALLET' => 'purple', 'CHARGE' => 'warning',
                        'CARD' => 'info', 'OTHER' => 'secondary'
                      ];
                      $typeLabels = [
                        'CASH' => 'Cash', 'BANK_TRANSFER' => 'Bank Transfer',
                        'E_WALLET' => 'E-Wallet', 'CHARGE' => 'Charge',
                        'CARD' => 'Card', 'OTHER' => 'Other'
                      ];
                      $icons = [
                        'CASH' => 'fa-money-bill-wave', 'BANK_TRANSFER' => 'fa-university',
                        'E_WALLET' => 'fa-mobile-alt', 'CHARGE' => 'fa-file-invoice',
                        'CARD' => 'fa-credit-card', 'OTHER' => 'fa-ellipsis-h'
                      ];
                      $typeColor = $typeColors[$method['method_type']] ?? 'secondary';
                      $typeLabel = $typeLabels[$method['method_type']] ?? $method['method_type'];
                      $iconClass = $method['icon'] ? $method['icon'] : ($icons[$method['method_type']] ?? 'fa-credit-card');
                    ?>
                    <tr class="method-row"
                        data-type="<?php echo htmlspecialchars($method['method_type']); ?>"
                        data-status="<?php echo $method['is_active'] ? 'active' : 'inactive'; ?>"
                        data-name="<?php echo strtolower(htmlspecialchars($method['method_name'] . ' ' . $method['method_code'])); ?>">
                      <td class="ps-3 py-3">
                        <div class="d-flex align-items-center">
                          <div class="method-icon bg-soft-<?php echo $typeColor; ?> text-<?php echo $typeColor; ?> me-3">
                            <span class="fas <?php echo htmlspecialchars($iconClass); ?>"></span>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($method['method_name']); ?></div>
                            <div class="text-muted small"><code><?php echo htmlspecialchars($method['method_code']); ?></code></div>
                            <?php if ($method['description']): ?>
                              <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($method['description']); ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <span class="badge bg-<?php echo $typeColor; ?>"><?php echo $typeLabel; ?></span>
                      </td>
                      <td class="py-3">
                        <div class="d-flex flex-wrap gap-1">
                          <?php if ($method['requires_confirmation']): ?>
                            <span class="badge bg-soft-primary text-primary" title="Requires confirmation">
                              <span class="fas fa-check-double me-1"></span>Confirm
                            </span>
                          <?php endif; ?>
                          <?php if ($method['requires_customer']): ?>
                            <span class="badge bg-soft-warning text-warning" title="Requires customer">
                              <span class="fas fa-user me-1"></span>Customer
                            </span>
                          <?php endif; ?>
                          <?php if ($method['requires_reference']): ?>
                            <span class="badge bg-soft-info text-info" title="Requires reference number">
                              <span class="fas fa-hashtag me-1"></span>Ref#
                            </span>
                          <?php endif; ?>
                          <?php if (!$method['requires_confirmation'] && !$method['requires_customer'] && !$method['requires_reference']): ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="py-3">
                        <span class="badge bg-light text-dark"><?php echo $method['sort_order']; ?></span>
                      </td>
                      <td class="py-3">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox"
                            <?php echo $method['is_active'] ? 'checked' : ''; ?>
                            onchange="toggleMethodStatus(<?php echo $method['method_id']; ?>, this.checked)"
                            title="Toggle status">
                        </div>
                      </td>
                      <td class="py-3 text-end pe-3">
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editMethod(<?php echo $method['method_id']; ?>)" title="Edit">
                          <span class="fas fa-edit"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMethod(<?php echo $method['method_id']; ?>, '<?php echo htmlspecialchars($method['method_name'], ENT_QUOTES); ?>')" title="Delete">
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

        <!-- No results row (for JS filtering) -->
        <div id="noResultsMsg" class="text-center py-4 d-none">
          <span class="fas fa-search text-muted fs-3 d-block mb-2"></span>
          <p class="text-muted">No methods match your filters.</p>
        </div>

      </div>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/settings/payment-methods/assets/js/payment-methods.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/payment-methods.js'); ?>"></script>

  <?php include __DIR__ . '/modals/add_method.php'; ?>
  <?php include __DIR__ . '/modals/edit_method.php'; ?>
</body>
</html>
