<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/bank-accounts/assets/css/bank-accounts.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/bank-accounts.css'); ?>">
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

        <!-- Header Card -->
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
                      <h4 class="mb-0 text-primary fw-bold">Bank <span class="text-info fw-medium">Accounts</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Settings</a></li>
                            <li class="breadcrumb-item active">Bank Accounts</li>
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

        <!-- Stats Cards -->
        <?php
        $total = count($accounts);
        $active = count(array_filter($accounts, fn($a) => $a['is_active']));
        $inactive = $total - $active;
        $companyWide = count(array_filter($accounts, fn($a) => !$a['branch_id']));
        ?>
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-3">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Accounts</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo $total; ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-university text-primary fs-4"></span></div>
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
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Company-wide</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo $companyWide; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-globe text-info fs-4"></span></div>
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
              <li>Bank accounts are used to receive <strong>bank transfer payments</strong> and <strong>e-wallet payments</strong> (GCash, PayMaya, etc.)</li>
              <li>Each account can be linked to a specific <strong>branch</strong> or set as <strong>company-wide</strong> for all branches.</li>
              <li>Link to a <strong>payment method</strong> so cashiers know which account to use for each payment type.</li>
              <li>When a cashier records a bank transfer, they select the bank account and provide a reference number. A manager confirms receipt.</li>
              <li>Inactive accounts will not appear during checkout.</li>
            </ul>
          </div>
        </div>

        <!-- Filter & Search -->
        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-4">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search bank, account name..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="filterBranch" onchange="applyFilters()">
                  <option value="">All Branches</option>
                  <option value="__global__">Company-wide</option>
                  <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                  <?php endforeach; ?>
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

        <!-- Bank Accounts Table -->
        <div class="card">
          <div class="card-body p-0">
            <?php if (empty($accounts)): ?>
              <div class="empty-state py-5">
                <div class="empty-state-icon"><span class="fas fa-university"></span></div>
                <div class="empty-state-text">No Bank Accounts Found</div>
                <div class="empty-state-subtext">Click "Add Bank Account" to get started.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="accountsTable">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-3">Bank / Account</th>
                      <th>Account Number</th>
                      <th>Payment Method</th>
                      <th>Branch</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($accounts as $acc):
                      $typeIcons = [
                        'BANK_TRANSFER' => 'fa-university', 'E_WALLET' => 'fa-mobile-alt',
                        'OTHER' => 'fa-ellipsis-h'
                      ];
                      $typeColors = [
                        'BANK_TRANSFER' => 'primary', 'E_WALLET' => 'purple', 'OTHER' => 'secondary'
                      ];
                      $iconClass = $typeIcons[$acc['method_type'] ?? ''] ?? 'fa-university';
                      $typeColor = $typeColors[$acc['method_type'] ?? ''] ?? 'primary';
                    ?>
                    <tr class="account-row"
                        data-branch="<?php echo $acc['branch_id'] ?? '__global__'; ?>"
                        data-status="<?php echo $acc['is_active'] ? 'active' : 'inactive'; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($acc['bank_name'] . ' ' . $acc['account_name'] . ' ' . $acc['account_number'])); ?>">
                      <td class="ps-3 py-3">
                        <div class="d-flex align-items-center">
                          <div class="bank-icon bg-soft-<?php echo $typeColor; ?> text-<?php echo $typeColor; ?> me-3">
                            <span class="fas <?php echo $iconClass; ?>"></span>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($acc['account_name']); ?></div>
                            <?php if ($acc['account_type']): ?>
                              <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($acc['account_type']); ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <code><?php echo htmlspecialchars($acc['account_number']); ?></code>
                      </td>
                      <td class="py-3">
                        <?php if ($acc['method_name']): ?>
                          <span class="badge bg-soft-<?php echo $typeColor; ?> text-<?php echo $typeColor; ?>">
                            <?php echo htmlspecialchars($acc['method_name']); ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted small">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <?php if ($acc['branch_name']): ?>
                          <span class="badge bg-soft-secondary text-secondary">
                            <span class="fas fa-building me-1"></span><?php echo htmlspecialchars($acc['branch_name']); ?>
                          </span>
                        <?php else: ?>
                          <span class="badge bg-soft-info text-info">
                            <span class="fas fa-globe me-1"></span>Company-wide
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox"
                            <?php echo $acc['is_active'] ? 'checked' : ''; ?>
                            onchange="toggleAccountStatus(<?php echo $acc['bank_account_id']; ?>, this.checked)"
                            title="Toggle status">
                        </div>
                      </td>
                      <td class="py-3 text-end pe-3">
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editAccount(<?php echo $acc['bank_account_id']; ?>)" title="Edit">
                          <span class="fas fa-edit"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAccount(<?php echo $acc['bank_account_id']; ?>, '<?php echo htmlspecialchars($acc['bank_name'] . ' - ' . $acc['account_name'], ENT_QUOTES); ?>')" title="Delete">
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
          <p class="text-muted">No accounts match your filters.</p>
        </div>

      </div>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/settings/bank-accounts/assets/js/bank-accounts.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/bank-accounts.js'); ?>"></script>

  <?php include __DIR__ . '/modals/add_account.php'; ?>
  <?php include __DIR__ . '/modals/edit_account.php'; ?>
</body>
</html>
