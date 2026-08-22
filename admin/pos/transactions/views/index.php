<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/pos/transactions/assets/css/pos-transactions.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/pos-transactions.css'); ?>">
<script src="<?php echo BASE_URL; ?>/print_library/print_template.js"></script>
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
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>

      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
        }
        ?><?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4 no-print">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">POS Transactions</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/pos/">POS</a></li>
                            <li class="breadcrumb-item active">Transactions</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3 no-print">
          <div class="card-body py-3">
            <div class="row g-2 align-items-end">
              <!-- Row 1: Search (wide) + Status + Type + Branch -->
              <div class="col-12 col-sm-12 col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch"
                         placeholder="Order code, cashier, passenger...">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                  <option value="all">All Status</option>
                  <option value="completed">Completed</option>
                  <option value="pending">Pending</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="refunded">Refunded</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Type</label>
                <select class="form-select form-select-sm" id="filterType">
                  <option value="all">All Types</option>
                  <option value="TICKET">Ticket</option>
                  <option value="SERVICE">Service</option>
                </select>
              </div>
              <?php if ($userRoleCode === 'SUPER_ADMIN' || !empty($userBranchId)): ?>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Branch</label>
                <select class="form-select form-select-sm" id="filterBranch">
                  <option value="">All Branches</option>
                </select>
              </div>
              <?php endif; ?>
              <!-- Row 2: Provider Type + Provider + Cashier + Date Range + Actions -->
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Provider Type</label>
                <select class="form-select form-select-sm" id="filterProviderType">
                  <option value="">All Types</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Provider</label>
                <select class="form-select form-select-sm" id="filterProvider">
                  <option value="">All Providers</option>
                </select>
              </div>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold mb-1">Cashier</label>
                <select class="form-select form-select-sm" id="filterCashier">
                  <option value="">All Cashiers</option>
                </select>
              </div>
              <div class="col-12 col-sm-8 col-md-5 col-lg-3">
                <label class="form-label small fw-semibold mb-1">Date Range</label>
                <input type="text" class="form-control form-control-sm" id="filterDateRange" placeholder="Select date or range...">
                <input type="hidden" id="filterDateFrom">
                <input type="hidden" id="filterDateTo">
              </div>
              <div class="col-12 col-sm-auto d-flex gap-2 flex-wrap align-items-end">
                <button type="button" class="btn btn-sm btn-primary" onclick="applyFilters()" title="Apply">
                  <span class="fas fa-filter"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetFilters()" title="Reset">
                  <span class="fas fa-undo"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTransactions()" title="Export">
                  <span class="fas fa-download"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="printTransactions()" title="Print">
                  <span class="fas fa-print"></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTransactions(1)" title="Refresh">
                  <span class="fas fa-sync"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Report Tabs -->
        <ul class="nav nav-tabs pos-transactions-tabs mb-3" id="posTransactionTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactionsTab" type="button" role="tab" aria-controls="transactionsTab" aria-selected="true">
              <span class="fas fa-receipt me-2"></span>Transactions
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="financial-report-tab" data-bs-toggle="tab" data-bs-target="#financialReportTab" type="button" role="tab" aria-controls="financialReportTab" aria-selected="false">
              <span class="fas fa-file-invoice-dollar me-2"></span>Financial Report
            </button>
          </li>
        </ul>

        <div class="tab-content" id="posTransactionTabContent">
          <div class="tab-pane fade show active" id="transactionsTab" role="tabpanel" aria-labelledby="transactions-tab">

        <!-- Stats Cards -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Orders</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-primary" id="statTotal">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-receipt text-primary fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Revenue</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-success" id="statRevenue">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-money-bill-wave text-success fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Total Refunded</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-danger" id="statRefunded">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-undo text-danger fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2 small">Net Profit</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between align-items-end">
                  <div class="col-auto"><div class="fs-4 fs-md-5 fw-bold text-info" id="statProfit">—</div></div>
                  <div class="col-auto ps-0"><span class="fas fa-chart-line text-info fs-3 fs-md-4 opacity-75"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
          <div class="card-header bg-body-tertiary py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="d-flex align-items-center gap-3">
                <span class="text-muted small" id="tableInfo">Loading...</span>
                <span class="text-muted small" id="txnRealtimeStatus">Live updates initializing...</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Per page:</label>
                <select class="form-select form-select-sm" id="perPageSelect" style="width:80px;" onchange="loadTransactions(1)">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0" id="transactionsTable">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">TICKET NO</th>
                    <th>Type</th>
                    <th>Passenger</th>
                    <th>Operating Provider</th>
                    <th>Vessel Name</th>
                    <th>Cashier</th>
                    <th>Branch</th>
                    <th>Payment</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Service Fee</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Date</th>
                  </tr>
                </thead>
                <tbody id="transactionsTableBody">
                  <tr><td colspan="13" class="text-center py-5 text-muted">
                    <span class="fas fa-spinner fa-spin me-2"></span>Loading...
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="text-muted small" id="paginationInfo"></span>
              <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
            </div>
          </div>
        </div>

          </div>
          <div class="tab-pane fade" id="financialReportTab" role="tabpanel" aria-labelledby="financial-report-tab">
            <div class="card financial-report-card border-0 shadow-sm">
              <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
                <div>
                  <h5 class="mb-1 text-primary fw-bold"><span class="fas fa-file-invoice-dollar me-2"></span>Financial Report</h5>
                  <span class="text-muted small" id="financialReportPeriod">Loading report...</span>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshFinancialReport()" title="Refresh report" aria-label="Refresh financial report">
                    <span class="fas fa-sync-alt me-1" aria-hidden="true"></span><span class="d-none d-sm-inline">Refresh</span>
                  </button>
                  <?php if ($canManageFinancialReportSignatories): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openSignatorySettings()" title="Configure financial report signatories" aria-label="Configure financial report signatories">
                    <span class="fas fa-user-edit me-1" aria-hidden="true"></span><span class="d-none d-sm-inline">Signatories</span>
                  </button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="printFinancialReport()" title="Print financial report" aria-label="Print financial report">
                    <span class="fas fa-print me-1" aria-hidden="true"></span><span class="d-none d-sm-inline">Print</span>
                  </button>
                </div>
              </div>
              <div class="card-body p-2 p-md-3">
                <div id="financialReportContent">
                  <div class="text-center py-5 text-muted">
                    <span class="fas fa-spinner fa-spin me-2"></span>Loading financial report...
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- Transaction Detail Offcanvas (Right Side) -->
  <div class="offcanvas offcanvas-end" id="txnDetailModal" tabindex="-1" aria-labelledby="txnDetailModalLabel" aria-hidden="true">
    <div class="offcanvas-header bg-shape">
      <div class="position-relative z-1">
        <h5 class="mb-0 text-white" id="txnDetailModalLabel">
          <span class="fas fa-receipt me-2"></span>Order Details
        </h5>
        <p class="fs-10 mb-0 text-white">View transaction information and details</p>
      </div>
      <div data-bs-theme="dark">
        <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
    </div>
    <div class="offcanvas-body scrollbar-overlay" id="txnDetailContent">
      <div class="text-center py-4"><span class="fas fa-spinner fa-spin"></span></div>
    </div>
    <div class="offcanvas-footer border-top p-3">
      <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="offcanvas">
        <span class="fas fa-times me-2"></span>Close
      </button>
    </div>
  </div>

  <!-- Signatory Settings Modal -->
  <div class="modal fade" id="financialReportSignatoryModal" tabindex="-1" aria-labelledby="financialReportSignatoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content signatory-settings-modal">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="financialReportSignatoryModalLabel">
              <span class="fas fa-user-edit me-2" aria-hidden="true"></span>Financial Report Signatories
            </h5>
            <p class="text-muted small mb-0 mt-1">Configure the people and signing roles shown on the printed financial report.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="signatory-settings-guide mb-3">
            <span class="fas fa-info-circle text-primary me-2" aria-hidden="true"></span>
            <span>Signatories are saved per branch. Select an employee to keep the name and position synchronized with HR records, or use a manual display name when necessary.</span>
          </div>
          <div class="row g-3 align-items-end mb-3">
            <div class="col-12 col-md-7">
              <label class="form-label small fw-semibold mb-1" for="signatoryBranchSelect">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="signatoryBranchSelect" onchange="onSignatoryBranchChange()" aria-describedby="signatoryBranchHelp">
                <option value="">Select branch...</option>
              </select>
              <div class="form-text" id="signatoryBranchHelp">Choose the branch whose report signatories you want to manage.</div>
            </div>
            <div class="col-12 col-md-5 text-md-end">
              <span class="badge rounded-pill bg-subtle-primary text-primary" id="signatoryBranchStatus">No branch selected</span>
            </div>
          </div>
          <div class="signatory-list-header" aria-hidden="true">
            <span>#</span>
            <span>Role / label</span>
            <span>Employee</span>
            <span>Position</span>
            <span>Status</span>
            <span>Actions</span>
          </div>
          <div class="signatory-list" id="signatoryList"></div>
          <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-3" id="addSignatoryBtn" onclick="addSignatoryRow()">
            <span class="fas fa-plus me-1" aria-hidden="true"></span>Add signatory
          </button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="saveSignatoriesBtn" onclick="saveSignatories()">
            <span class="fas fa-save me-1" aria-hidden="true"></span>Save Signatories
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script>
    window.POS_TXN_CONFIG = {
      apiUrl: '<?php echo BASE_URL; ?>/api/pos-transactions',
      isSuperAdmin: <?php echo ($userRoleCode === 'SUPER_ADMIN') ? 'true' : 'false'; ?>,
      isManager: <?php echo ($userRoleCode === 'MANAGER') ? 'true' : 'false'; ?>,
      canManageSignatories: <?php echo $canManageFinancialReportSignatories ? 'true' : 'false'; ?>,
      currentUserName: <?php echo json_encode((string)($user['full_name'] ?? $user['username'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      currentUserBranchId: <?php echo json_encode($currentUserBranchId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };

    // Printer Settings
    window.PRINTER_SETTINGS = <?php echo json_encode([
        'enabled' => (bool)($printerSettings['receipt_printing_enabled'] ?? 1),
        'paperWidth' => $printerSettings['receipt_paper_width'] ?? '80mm',
        'autoPrint' => (bool)($printerSettings['receipt_auto_print'] ?? 1),
        'showPreview' => (bool)($printerSettings['receipt_show_preview'] ?? 0),
        'copies' => (int)($printerSettings['receipt_copies'] ?? 1),
        'addressSource' => $printerSettings['receipt_address_source'] ?? 'company',
        'showTin' => (bool)($printerSettings['receipt_show_tin'] ?? 1),
        'showServiceFee' => (bool)($printerSettings['receipt_show_service_fee'] ?? 1),
        'showBaseAmount' => (bool)($printerSettings['receipt_show_base_amount'] ?? 1),
        'showDiscount' => (bool)($printerSettings['receipt_show_discount'] ?? 1),
        'showItemTotal' => (bool)($printerSettings['receipt_show_item_total'] ?? 1),
        'showSubtotal' => (bool)($printerSettings['receipt_show_subtotal'] ?? 1),
        'showTendered' => (bool)($printerSettings['receipt_show_tendered'] ?? 1),
        'showServiceFeeTotal' => (bool)($printerSettings['receipt_show_service_fee_total'] ?? 1),
        'totalSource' => $printerSettings['receipt_total_source'] ?? 'grand_total',
        'showVat' => (bool)($printerSettings['receipt_show_vat'] ?? 1),
        'showCashier' => (bool)($printerSettings['receipt_show_cashier'] ?? 1),
        'showPaymentMethod' => (bool)($printerSettings['receipt_show_payment_method'] ?? 1),
        'showBranch' => (bool)($printerSettings['receipt_show_branch'] ?? 1),
        'logoEnabled' => (bool)($printerSettings['receipt_logo_enabled'] ?? 0),
        'qrEnabled' => (bool)($printerSettings['receipt_qr_code_enabled'] ?? 0),
        'qrFormat' => $printerSettings['receipt_qr_format'] ?? 'TRANSACTION_ID',
        'footerText' => $printerSettings['receipt_footer'] ?? 'Thank you for your business!',
        'customFooter' => $printerSettings['receipt_custom_footer'] ?? ''
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;

    // Company Info for Receipts and Financial Reports
    <?php
    $companyLogoPath = trim((string)($printerSettings['system_logo'] ?? $systemLogo ?? ''));
    if ($companyLogoPath !== '' && !preg_match('/^(https?:\/\/|data:)/i', $companyLogoPath)) {
        $companyLogoPath = rtrim(BASE_URL, '/') . '/' . ltrim($companyLogoPath, '/');
    }
    $companyInfo = [
        'name' => trim((string)($printerSettings['company_name'] ?? $systemSettings['system_name'] ?? 'TMS')),
        'address' => trim((string)($printerSettings['company_address'] ?? $systemSettings['company_address'] ?? '')),
        'contact' => trim((string)($printerSettings['company_contact_number'] ?? '')),
        'email' => trim((string)($printerSettings['company_email'] ?? '')),
        'tin' => trim((string)($printerSettings['company_tin'] ?? '')),
        'logo' => $companyLogoPath
    ];
    ?>
    window.COMPANY_INFO = <?php echo json_encode($companyInfo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;

    // Branch info for receipt address
    window.POS_BRANCH_INFO = <?php echo json_encode([
        'branch_name' => $branchDetails['branch_name'] ?? '',
        'street_address' => $branchDetails['street_address'] ?? '',
        'barangay_name' => $branchDetails['barangay_name'] ?? '',
        'city_municipality_name' => $branchDetails['city_municipality_name'] ?? '',
        'province_name' => $branchDetails['province_name'] ?? '',
        'region_name' => $branchDetails['region_name'] ?? '',
        'zip_code' => $branchDetails['zip_code'] ?? '',
        'landmark' => $branchDetails['landmark'] ?? '',
        'contact_number' => $branchDetails['contact_number'] ?? ''
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="<?php echo BASE_URL; ?>/admin/pos/transactions/assets/js/pos-transactions.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/pos-transactions.js'); ?>"></script>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
