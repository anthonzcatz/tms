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
                <h2 class="mb-1">Provider Wallets</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/wallet">Wallet</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Provider Wallets</li>
                  </ol>
                </nav>
              </div>
              <div>
                <button class="btn btn-primary" onclick="openAddWalletModal()">
                  <span class="fas fa-plus me-2"></span>Add Wallet
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
              <!--/.bg-holder-->
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center"><img class="img-fluid" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h6 class="mb-1 text-primary">Welcome to</h6>
                      <h4 class="mb-0 text-primary fw-bold">Provider <span class="text-info fw-medium">Wallets</span></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

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
        <div class="row g-3 mb-3">
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
                      <h6 class="mb-0"><?php echo htmlspecialchars($wallet['wallet_name']); ?></h6>
                      <div class="form-check form-switch ms-2">
                        <input class="form-check-input wallet-status-switch" type="checkbox" 
                               id="walletSwitch<?php echo $wallet['wallet_id']; ?>"
                               data-wallet-id="<?php echo $wallet['wallet_id']; ?>"
                               <?php echo $wallet['status'] === 'active' ? 'checked' : ''; ?>
                               style="width: 2.5em; height: 1.25em;">
                        <label class="form-check-label" for="walletSwitch<?php echo $wallet['wallet_id']; ?>" style="font-size: 0.75rem;">
                          <?php echo $wallet['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </label>
                      </div>
                    </div>
                    <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif <?php echo $wallet['current_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                      ₱<?php echo number_format($wallet['current_balance'], 2); ?>
                    </div>
                    <p class="mb-2 text-muted fs-10">
                      <span class="fas fa-building me-1"></span><?php echo htmlspecialchars($wallet['branch_name']); ?>
                    </p>
                    <div class="d-flex gap-2 mt-3">
                      <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewWallet(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-eye me-1"></span>View
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-success" onclick="editWallet(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-edit"></span>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-info" onclick="adjustBalance(<?php echo $wallet['wallet_id']; ?>)">
                        <span class="fas fa-exchange-alt"></span>
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
    <?php include __DIR__ . '/modals/add_wallet.php'; ?>
    <?php include __DIR__ . '/modals/edit_wallet.php'; ?>
    <?php include __DIR__ . '/modals/adjust_balance.php'; ?>

    <script src="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/assets/js/provider-wallets.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/provider-wallets.js'); ?>"></script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>
