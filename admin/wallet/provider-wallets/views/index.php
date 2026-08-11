<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/assets/css/provider-wallets.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/provider-wallets.css'); ?>">
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
                      <h4 class="mb-0 text-primary fw-bold">Provider <span class="text-info fw-medium">Wallets</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Wallet</a></li>
                            <li class="breadcrumb-item active">Provider Wallets</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex align-items-center mt-3 mt-lg-0">
                    <?php if ($canCreateWallet): ?>
                    <button class="btn btn-primary" onclick="openAddWalletModal()">
                      <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Wallet</span>
                    </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php $activeWalletModule = 'provider-wallets'; include dirname(dirname(__DIR__)) . '/_partials/wallet_nav.php'; ?>

        <!-- Wallet Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100 ecommerce-card-min-width">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Total Wallets</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col">
                    <p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo count($wallets); ?></p>
                  </div>
                  <div class="col-auto ps-0">
                    <div class="d-flex align-items-center">
                      <span class="fas fa-wallet text-primary fs-4"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0">
                <h6 class="mb-0 mt-2">Active Wallets</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($wallets, fn($w) => $w['status'] === 'active')); ?></div>
                    <?php
                    $totalWallets = count($wallets);
                    $activeWallets = count(array_filter($wallets, fn($w) => $w['status'] === 'active'));
                    $activePercentage = $totalWallets > 0 ? round(($activeWallets / $totalWallets) * 100, 1) : 0;
                    ?>
                    <span class="badge rounded-pill fs-11 bg-success-subtle text-success"><span class="fas fa-caret-up me-1"></span><?php echo $activePercentage; ?>%</span>
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
                <h6 class="mb-0 mt-2">Inactive Wallets</h6>
              </div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo count(array_filter($wallets, fn($w) => $w['status'] === 'inactive')); ?></div>
                    <?php
                    $inactiveWallets = count(array_filter($wallets, fn($w) => $w['status'] === 'inactive'));
                    $inactivePercentage = $totalWallets > 0 ? round(($inactiveWallets / $totalWallets) * 100, 1) : 0;
                    ?>
                    <span class="badge rounded-pill fs-11 bg-danger-subtle text-danger"><span class="fas fa-caret-down me-1"></span><?php echo $inactivePercentage; ?>%</span>
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
                  <h6 class="mb-3 text-800">Total Balance</h6>
                  <p class="font-sans-serif lh-1 mb-1 fs-5 fw-bold text-primary">₱<?php echo number_format(array_sum(array_column($wallets, 'current_balance')), 2); ?></p>
                  <div class="progress mb-2 rounded-3" style="height: 8px;">
                    <?php
                    $totalBalance = array_sum(array_column($wallets, 'current_balance'));
                    $maxBalance = max(10000, $totalBalance); // Minimum scale of 10k
                    $balancePercentage = $maxBalance > 0 ? min(100, round(($totalBalance / $maxBalance) * 100, 1)) : 0;
                    ?>
                    <div class="progress-bar bg-primary" style="width: <?php echo $balancePercentage; ?>%;" role="progressbar" aria-valuenow="<?php echo $balancePercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="fs-10 fw-semi-bold text-500">Wallet Balance</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-3 shadow-sm">
          <div class="card-header bg-light py-2">
            <div class="d-flex align-items-center justify-content-between">
              <h6 class="mb-0 fw-bold text-primary">
                <span class="fas fa-filter me-2"></span>Filter Wallets
              </h6>
              <span class="badge bg-primary"><?php echo count($wallets); ?> of <?php echo count($baseWallets ?? $wallets); ?> shown</span>
            </div>
          </div>
          <div class="card-body py-3">
            <form method="GET" id="filterForm">
              <!-- Quick Search (client-side) -->
              <div class="row g-3 mb-3">
                <div class="col-12">
                  <label class="form-label small text-muted mb-1">Quick Search</label>
                  <div class="search-box position-relative">
                    <input type="text" class="form-control ps-4" id="walletSearch" placeholder="Search wallet name...">
                    <span class="fas fa-search position-absolute text-muted" style="left: 0.75rem; top: 50%; transform: translateY(-50%);"></span>
                  </div>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-12 col-md-3">
                  <label class="form-label small text-muted mb-1">Provider</label>
                <select class="form-select" name="provider" onchange="this.form.submit()">
                  <option value="">All Providers</option>
                  <?php foreach ($filterProviders as $provider): ?>
                    <option value="<?php echo htmlspecialchars($provider['provider_name']); ?>" <?php echo $filterProvider === $provider['provider_name'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($provider['provider_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Branch</label>
                <select class="form-select" name="branch" onchange="this.form.submit()">
                  <option value="">All Branches</option>
                  <?php foreach ($filterBranches as $branch): ?>
                    <option value="<?php echo htmlspecialchars($branch['branch_name']); ?>" <?php echo $filterBranch === $branch['branch_name'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($branch['branch_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" name="status" onchange="this.form.submit()">
                  <option value="">All Status</option>
                  <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                  <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1">Actions</label>
                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary">
                    <span class="fas fa-filter me-1"></span>Apply Filter
                  </button>
                  <a href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/" class="btn btn-outline-secondary">
                    <span class="fas fa-times me-1"></span>Clear
                  </a>
                  <button type="button" class="btn btn-outline-primary" onclick="printWallets()">
                    <span class="fas fa-print me-1"></span>Print
                  </button>
                </div>
              </div>
            </form>
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
              <li>Manage provider wallets for different branches and service providers</li>
              <li>Track wallet balances and adjust them as needed</li>
              <li>Active wallets can receive transactions, inactive wallets are paused</li>
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

        <!-- Wallet Cards Display -->
        <div class="row g-3 mb-3" id="walletCardsContainer">
          <?php if (empty($wallets)): ?>
            <div class="col-12">
              <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                  <div class="empty-state">
                    <div class="empty-state-icon">
                      <span class="fas fa-wallet"></span>
                    </div>
                    <div class="empty-state-text">No wallets found</div>
                    <div class="empty-state-subtext">Create a wallet to get started</div>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($wallets as $wallet): ?>
              <div class="col-sm-6 col-md-4">
                <div class="card overflow-hidden shadow-sm h-100" style="min-width: 12rem">
                  <div class="bg-holder bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-1.png);">
                  </div>
                  <!--/.bg-holder-->
                  <div class="card-body position-relative">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                      <h6 class="mb-0 flex-grow-1"><?php echo htmlspecialchars($wallet['wallet_name']); ?></h6>
                      <div class="form-check form-switch ms-2 d-flex align-items-center">
                        <input class="form-check-input wallet-status-switch" type="checkbox" 
                               id="walletSwitch<?php echo $wallet['wallet_id']; ?>"
                               data-wallet-id="<?php echo $wallet['wallet_id']; ?>"
                               <?php echo $wallet['status'] === 'active' ? 'checked' : ''; ?>
                               style="width: 2.5em; height: 1.25em;">
                        <label class="form-check-label ms-2" for="walletSwitch<?php echo $wallet['wallet_id']; ?>" style="font-size: 0.75rem; white-space: nowrap;">
                          <?php echo $wallet['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </label>
                      </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                      <?php
                        $providerType = $wallet['provider_type'] ?? '';
                        $providerTypeClass = 'bg-secondary';
                        if ($providerType === 'airline') {
                            $providerTypeClass = 'bg-primary';
                        } elseif ($providerType === 'shipping') {
                            $providerTypeClass = 'bg-info';
                        } elseif ($providerType === 'bus') {
                            $providerTypeClass = 'bg-warning';
                        }
                      ?>
                      <span class="badge <?php echo $providerTypeClass; ?>"><?php echo ucfirst(htmlspecialchars($providerType ?: 'Unknown')); ?></span>
                      <?php if (!empty($wallet['parent_provider_id'])): ?>
                        <span class="badge bg-light text-success border border-success" title="This wallet belongs to a sub-provider"><span class="fas fa-sitemap me-1"></span>Sub-provider of <?php echo htmlspecialchars($wallet['parent_provider_name'] ?? 'Main Provider'); ?></span>
                      <?php elseif (!empty($wallet['child_provider_names'])): ?>
                        <span class="badge bg-light text-primary border border-primary" title="This wallet belongs to a main provider"><span class="fas fa-sitemap me-1"></span>Main Provider</span>
                      <?php else: ?>
                        <span class="badge bg-light text-secondary border border-secondary" title="Standalone provider wallet"><span class="fas fa-building me-1"></span>Standalone</span>
                      <?php endif; ?>
                      <?php if (!empty($wallet['variant_name'])): ?>
                        <span class="badge bg-light text-dark border rounded-pill" title="Variant-specific wallet">
                          <span class="fas fa-palette me-1"></span>
                          <?php echo htmlspecialchars($wallet['variant_name']); ?>
                          <span class="d-inline-block rounded-circle ms-1" style="width:10px;height:10px;background-color:<?php echo htmlspecialchars($wallet['variant_color'] ?: '#0d6efd'); ?>;border:1px solid rgba(0,0,0,0.25);"></span>
                        </span>
                      <?php elseif (!empty($wallet['variant_count'])): ?>
                        <span class="badge bg-info rounded-pill" title="<?php echo (int) $wallet['variant_count']; ?> ticket variant(s) for this provider"><span class="fas fa-palette me-1"></span><?php echo (int) $wallet['variant_count']; ?> variant<?php echo $wallet['variant_count'] > 1 ? 's' : ''; ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif <?php echo $wallet['current_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                      ₱<?php echo number_format($wallet['current_balance'], 2); ?>
                    </div>
                    <p class="mb-1 text-muted fs-10">
                      <span class="fas fa-building me-1"></span><?php echo htmlspecialchars($wallet['branch_name']); ?>
                    </p>
                    <p class="mb-2 text-muted fs-10">
                      <span class="fas fa-exclamation-triangle me-1"></span>Min: ₱<?php echo number_format($wallet['min_balance'] ?? 1000, 2); ?>
                    </p>
                    <?php if (!empty($wallet['child_provider_names'])): ?>
                    <p class="mb-2 text-muted fs-10">
                      <span class="fas fa-sitemap me-1"></span>Shared with: <?php echo htmlspecialchars($wallet['child_provider_names']); ?>
                    </p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                      <?php if (!empty($wallet['variant_count']) && $wallet['variant_count'] > 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-dark flex-grow-1 flex-sm-grow-0" onclick="openManageProviderWallets(<?php echo (int)$wallet['provider_id']; ?>, <?php echo (int)$wallet['branch_id']; ?>, '<?php echo htmlspecialchars($wallet['provider_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($wallet['branch_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">
                        <span class="fas fa-list me-1"></span>Variants
                      </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 flex-sm-grow-0" onclick="viewWallet(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-eye me-1"></span>View
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-success flex-grow-1 flex-sm-grow-0" onclick="editWallet(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-edit"></span>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-info flex-grow-1 flex-sm-grow-0" onclick="adjustBalance(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-exchange-alt"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <script>
        // Client-side search for instant filtering (like shifts page)
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('walletSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    // Select all wallet card containers
                    const cards = document.querySelectorAll('#walletCardsContainer > .col-sm-6, #walletCardsContainer > .col-md-4');
                    cards.forEach(card => {
                        const name = card.querySelector('h6')?.textContent?.toLowerCase() || '';
                        const branchEl = card.querySelector('.fa-building');
                        const branch = branchEl?.parentElement?.textContent?.toLowerCase() || '';
                        const match = name.includes(term) || branch.includes(term);
                        card.style.display = match ? '' : 'none';
                    });
                });
            }
        });
        </script>

        <!-- Include Modals -->
        <?php include __DIR__ . '/modals/add_wallet.php'; ?>
        <?php include __DIR__ . '/modals/edit_wallet.php'; ?>
        <?php include __DIR__ . '/modals/adjust_balance.php'; ?>
        <?php include __DIR__ . '/modals/view_wallet.php'; ?>
        <?php include __DIR__ . '/modals/manage_wallets.php'; ?>

        <script>
          window.providerWalletData = {
            providers: <?php echo json_encode(array_map(fn($p) => [
                'provider_id' => (int)$p['provider_id'],
                'provider_name' => $p['provider_name'],
                'variant_count' => (int)$p['variant_count']
            ], $allProviders)); ?>,
            existingWallets: <?php echo json_encode(array_map(fn($w) => [
                'wallet_id' => (int)$w['wallet_id'],
                'provider_id' => (int)$w['provider_id'],
                'branch_id' => (int)$w['branch_id'],
                'variant_id' => $w['variant_id'] ? (int)$w['variant_id'] : null,
                'current_balance' => (float)$w['current_balance'],
                'min_balance' => (float)$w['min_balance'],
                'status' => $w['status']
            ], $wallets)); ?>
          };
        </script>
        <script>
          window.COMPANY_INFO = {
            name: '<?php echo htmlspecialchars($systemSettings['company_name'] ?? $systemSettings['system_name'] ?? 'TMS'); ?>',
            address: '<?php echo htmlspecialchars($systemSettings['company_address'] ?? ''); ?>',
            contact: '<?php echo htmlspecialchars($systemSettings['company_contact_number'] ?? ''); ?>',
            email: '<?php echo htmlspecialchars($systemSettings['company_email'] ?? ''); ?>',
            tin: '<?php echo htmlspecialchars($systemSettings['company_tin'] ?? ''); ?>',
            logo: '<?php echo !empty($systemSettings['system_logo']) ? BASE_URL . htmlspecialchars($systemSettings['system_logo']) : ''; ?>'
          };
        </script>
        <script src="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/assets/js/provider-wallets.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/provider-wallets.js'); ?>"></script>

        <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
        </div>
        <?php endif; ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
      </body>
    </html>
