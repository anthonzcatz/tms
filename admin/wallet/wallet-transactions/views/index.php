<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/wallet/wallet-transactions/assets/css/wallet-transactions.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/wallet-transactions.css'); ?>">
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
                <h2 class="mb-1">Wallet Transactions</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Wallet Transactions</li>
                  </ol>
                </nav>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="openWalletManagementModal()">
                  <span class="fas fa-wallet me-2"></span>Wallet Management
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddTransactionModal()">
                  <span class="fas fa-plus-circle me-2"></span>Add Transaction
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Wallet Stats Cards -->
        <div class="row mb-3 g-3">
          <div class="col-lg-12">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-lg-3 border-end-lg border-bottom border-bottom-lg-0 pb-3 pb-lg-0">
                    <div class="d-flex flex-between-center mb-3">
                      <div class="d-flex align-items-center">
                        <div class="icon-item icon-item-sm bg-primary-subtle shadow-none me-2"><span class="fs-11 fas fa-exchange-alt text-primary"></span></div>
                        <h6 class="mb-0">Total Transactions</h6>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-total-transactions" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-total-transactions"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex">
                      <div class="d-flex">
                        <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2" id="totalTransactions">0</p>
                        <div class="d-flex flex-column">
                          <span class="me-1 text-success fas fa-caret-up text-primary"></span>
                          <p class="fs-11 mb-0 text-nowrap">This Month</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-3 border-end-lg border-bottom border-bottom-lg-0 py-3 py-lg-0">
                    <div class="d-flex flex-between-center mb-3">
                      <div class="d-flex align-items-center">
                        <div class="icon-item icon-item-sm bg-success-subtle shadow-none me-2"><span class="fs-11 fas fa-arrow-down text-success"></span></div>
                        <h6 class="mb-0">Total Inflow</h6>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-total-inflow" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-total-inflow"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex">
                      <div class="d-flex">
                        <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2" id="totalInflow">₱0.00</p>
                        <div class="d-flex flex-column">
                          <span class="me-1 text-success fas fa-caret-up text-success"></span>
                          <p class="fs-11 mb-0 text-nowrap">Total Amount</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-3 border-end-lg border-bottom border-bottom-lg-0 py-3 py-lg-0">
                    <div class="d-flex flex-between-center mb-3">
                      <div class="d-flex align-items-center">
                        <div class="icon-item icon-item-sm bg-danger-subtle shadow-none me-2"><span class="fs-11 fas fa-arrow-up text-danger"></span></div>
                        <h6 class="mb-0">Total Outflow</h6>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-total-outflow" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-total-outflow"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex">
                      <div class="d-flex">
                        <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2" id="totalOutflow">₱0.00</p>
                        <div class="d-flex flex-column">
                          <span class="me-1 text-danger fas fa-caret-down text-danger"></span>
                          <p class="fs-11 mb-0 text-nowrap">Total Amount</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-3 pt-3 pt-lg-0">
                    <div class="d-flex flex-between-center mb-3">
                      <div class="d-flex align-items-center">
                        <div class="icon-item icon-item-sm bg-info-subtle shadow-none me-2"><span class="fs-11 fas fa-wallet text-info"></span></div>
                        <h6 class="mb-0">Net Balance</h6>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-net-balance" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-net-balance"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                    <div class="d-flex">
                      <div class="d-flex">
                        <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2" id="netBalance">₱0.00</p>
                        <div class="d-flex flex-column">
                          <span class="me-1 text-info fas fa-minus text-info"></span>
                          <p class="fs-11 mb-0 text-nowrap">Current Balance</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card">
              <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                  <div class="col-md-3">
                    <div class="search-box">
                      <input type="text" class="form-control search-input" id="transactionSearch" placeholder="Search transactions...">
                      <span class="fas fa-search search-icon"></span>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="walletFilter">
                      <option value="">All Wallets</option>
                      <?php foreach ($wallets as $wallet): ?>
                        <option value="<?php echo $wallet['wallet_id']; ?>">
                          <?php echo htmlspecialchars($wallet['wallet_name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="txnTypeFilter">
                      <option value="">All Types</option>
                      <option value="TOPUP">Topup</option>
                      <option value="SALE">Sale</option>
                      <option value="REFUND">Refund</option>
                      <option value="ADJUSTMENT">Adjustment</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <select class="form-select" id="directionFilter">
                      <option value="">All Directions</option>
                      <option value="IN">In</option>
                      <option value="OUT">Out</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <input type="date" class="form-control" id="dateFilter">
                  </div>
                  <div class="col-md-1">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                      <span class="fas fa-redo"></span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Transactions List -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header bg-body-tertiary">
                <div class="d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Transaction History</h5>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportTransactions()">
                      <span class="fas fa-download me-1"></span>Export
                    </button>
                  </div>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead>
                      <tr>
                        <th>Txn Code</th>
                        <th>Wallet</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Date</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="transactionsTableBody">
                      <tr>
                        <td colspan="8" class="text-center py-4">
                          <span class="fas fa-spinner fa-spin"></span> Loading transactions...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer bg-body-tertiary">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <span class="text-muted">Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> transactions</span>
                  </div>
                  <nav>
                    <ul class="pagination mb-0" id="pagination">
                    </ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>

    <!-- Include Modals -->
    <?php include __DIR__ . '/modals/wallet_management.php'; ?>
    <?php include __DIR__ . '/modals/add_transaction.php'; ?>
    <?php include __DIR__ . '/modals/view_transaction.php'; ?>

    <script src="<?php echo BASE_URL; ?>/admin/wallet/wallet-transactions/assets/js/wallet-transactions.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/wallet-transactions.js'); ?>"></script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  </body>
</html>

