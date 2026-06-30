<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once __DIR__ . '/../_guard.php';
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php include __DIR__ . '/../includes/head.php'; ?>
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
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include __DIR__ . '/../includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include __DIR__ . '/../includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include __DIR__ . '/../includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content"><?php switch (NAVBAR_POSITION) { case 'combo':
                  include __DIR__ . '/../includes/navbar-top.php';
                  break;
              case 'vertical':
                  include __DIR__ . '/../includes/navbar.php';
                  break;
              case 'top':
              case 'double-top':
              default:
                  break;
          }
          ?><?php endif; ?>
          
          <!-- Showing Data For Card -->
          <style>
            /* Mobile portrait (<576px) */
            @media (max-width: 575.98px) {
              .card .row.gx-0 { flex-direction: column; }
              .col-sm-auto.d-flex.align-items-center { 
                justify-content: center; 
                text-align: center; 
                margin-bottom: 1rem;
              }
              .col-sm-auto.d-flex.align-items-center img { 
                width: 60px !important; 
                margin: 0 0.25rem !important;
              }
              .col-sm-auto.d-flex.align-items-center h4 { font-size: 1.25rem; }
              #dateRangeButtons { 
                display: grid !important; 
                grid-template-columns: repeat(2, 1fr); 
                gap: 0.25rem; 
                width: 100%; 
              }
              #dateRangeButtons .btn { border-radius: 0.25rem !important; font-size: 0.75rem; }
              #globalBranchSelector { width: 100% !important; min-width: auto !important; }
              .col-md-auto.p-3 .row { flex-direction: column; align-items: stretch !important; }
              .col-md-auto.p-3 .col-auto { width: 100%; text-align: center; }
              #customDateRangeContainer .row { flex-direction: column; }
              #customDateRangeContainer .col-auto { width: 100%; }
              
              /* Analytics Chart Responsive */
              .audience-chart-header .nav-tabs {
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
              }
              .audience-chart-header .nav-tabs::-webkit-scrollbar {
                height: 3px;
              }
              .audience-chart-header .nav-tabs::-webkit-scrollbar-thumb {
                background: #dee2e6;
                border-radius: 3px;
              }
              .audience-tab-item {
                min-width: 120px;
                padding: 0.75rem 1rem !important;
              }
              .audience-tab-item h5 {
                font-size: 1rem !important;
              }
              .audience-tab-item h6 {
                font-size: 0.7rem !important;
              }
              #branchSalesChart,
              #branchNetChart,
              #branchRefundsChart,
              #branchProfitChart,
              #transactionsPerHourChart {
                height: 200px !important;
              }
              #currentBranchName {
                font-size: 0.8rem !important;
              }
              #currentBranchName h5 {
                font-size: 0.8rem !important;
              }
              #currentBranchName span {
                font-size: 0.7rem !important;
              }
              #currentBranchName .fas {
                font-size: 0.7rem !important;
              }
              .card-body > .d-flex.justify-content-between {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              .card-body > .d-flex.justify-content-between > h5 {
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
              }
              .card-body > .d-flex.justify-content-between > select {
                width: auto !important;
                min-width: 100px !important;
                max-width: 120px !important;
              }
              /* Cashier Performance card header */
              .card-header.d-flex.flex-between-center {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              .card-header.d-flex.flex-between-center h6 {
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-size: 0.8rem !important;
              }
              .card-header.d-flex.flex-between-center .d-flex.gap-2 {
                flex-wrap: nowrap !important;
              }
              .card-header.d-flex.flex-between-center .d-flex.gap-2 select {
                min-width: 80px !important;
                max-width: 100px !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
              }
              /* Provider Wallet card header */
              #walletWidget .card-header.d-flex {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              #walletWidget .card-header h6 {
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-size: 0.8rem !important;
              }
              #walletWidget .card-body > .d-flex {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              #walletWidget .card-body > .d-flex h6 {
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-size: 0.75rem !important;
              }
              #walletWidget .card-body > .d-flex select {
                min-width: 90px !important;
                max-width: 110px !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
              }
              /* Top Services card header */
              .card-header .row.flex-between-center {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              .card-header .row.flex-between-center h6 {
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                font-size: 0.8rem !important;
              }
              .card-header .row.flex-between-center select {
                min-width: 80px !important;
                max-width: 100px !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
              }
              /* Transactions per Hour card header */
              .card-header.bg-body-tertiary h6 {
                font-size: 0.8rem !important;
              }
              /* Transactions per Hour card footer */
              .card-footer .row.flex-between-center {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              .card-footer .row.flex-between-center .col-auto {
                flex: 0 0 auto !important;
              }
              .card-footer .row.flex-between-center select {
                min-width: 80px !important;
                max-width: 100px !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
              }
              .card-footer .row.flex-between-center .btn-link {
                font-size: 0.7rem !important;
                white-space: nowrap !important;
              }
              #branchSelector,
              #hourlyFilter,
              #hourlyBranchFilter,
              #paymentBreakdownFilter,
              #branchAnalyticsRange {
                width: 100% !important;
                min-width: auto !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
                margin-bottom: 0.5rem;
              }
              .card-footer .row {
                flex-direction: column;
                gap: 0.5rem;
              }
              .card-footer .col-auto {
                width: 100%;
              }
              .card-footer .btn-link {
                font-size: 0.75rem !important;
                text-align: center;
                display: block;
              }
            }
            
            /* Mobile landscape (576px-767px) */
            @media (min-width: 576px) and (max-width: 767.98px) {
              .card .row.gx-0 { flex-direction: column; }
              .col-sm-auto.d-flex.align-items-center { 
                justify-content: center; 
                margin-bottom: 0.75rem;
              }
              .col-sm-auto.d-flex.align-items-center img { width: 70px !important; }
              #dateRangeButtons { 
                display: grid !important; 
                grid-template-columns: repeat(3, 1fr); 
                gap: 0.25rem; 
                width: 100%; 
              }
              #dateRangeButtons .btn { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
              #globalBranchSelector { width: 100% !important; min-width: auto !important; }
              .col-md-auto.p-3 .row { flex-wrap: wrap; }
              .col-md-auto.p-3 .col-auto { flex: 0 0 auto; }
              
              /* Analytics Chart Responsive */
              .audience-chart-header .nav-tabs {
                overflow-x: auto;
                white-space: nowrap;
              }
              .audience-tab-item {
                min-width: 110px;
                padding: 0.75rem 1rem !important;
              }
              #branchSalesChart,
              #branchNetChart,
              #branchRefundsChart,
              #branchProfitChart {
                height: 240px !important;
              }
              #branchSelector {
                width: 120px !important;
                font-size: 0.85rem !important;
              }
              #branchAnalyticsRange {
                width: 120px !important;
                font-size: 0.85rem !important;
              }
            }
            
            /* Tablet (768px-991px) */
            @media (min-width: 768px) and (max-width: 991.98px) {
              .col-sm-auto.d-flex.align-items-center img { width: 70px !important; }
              .col-sm-auto.d-flex.align-items-center img:last-child { display: none !important; }
              #dateRangeButtons { flex-wrap: wrap; }
              #dateRangeButtons .btn { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
              #globalBranchSelector { min-width: 120px !important; }
              .col-md-auto.p-3 .row { flex-wrap: wrap; }
              
              /* Analytics Chart Responsive */
              .audience-tab-item {
                padding: 0.75rem 1.25rem !important;
              }
              #branchSalesChart,
              #branchNetChart,
              #branchRefundsChart,
              #branchProfitChart {
                height: 280px !important;
              }
              #branchSelector {
                min-width: 140px !important;
              }
            }
            
            /* Laptop (992px-1199px) */
            @media (min-width: 992px) and (max-width: 1199.98px) {
              .col-sm-auto.d-flex.align-items-center img { width: 80px !important; }
              #dateRangeButtons .btn { font-size: 0.8rem; padding: 0.3rem 0.6rem; }
              
              /* Analytics Chart Responsive */
              #branchSalesChart,
              #branchNetChart,
              #branchRefundsChart,
              #branchProfitChart {
                height: 300px !important;
              }
            }
            
            /* Custom date range responsive */
            #customDateRangeContainer .row { flex-wrap: wrap; }
            @media (max-width: 991.98px) {
              #customDateRangeContainer .row { flex-direction: column; }
              #customDateRangeContainer .col-auto { width: 100%; }
            }

            /* AR and Live Sales Side by Side on Tablet/Laptop/MacBook Air */
            @media (min-width: 768px) and (max-width: 1599.98px) {
              /* On tablet/laptop/MacBook Air: make Branch Analytics full width */
              .col-xxl-8 {
                width: 100% !important;
                flex: 0 0 100%;
                max-width: 100%;
              }
              /* Hide AR widget inside Branch Analytics on tablet/laptop/MacBook Air */
              #arWidget {
                display: none !important;
              }
              /* Show standalone AR widget on tablet/laptop/MacBook Air */
              #arWidgetStandalone {
                display: block !important;
              }
            }

            /* Large desktop screens (1600px+): show AR inside Branch Analytics, hide standalone */
            @media (min-width: 1600px) {
              #arWidgetStandalone {
                display: none !important;
              }
              #arWidget {
                display: block !important;
              }
            }

            /* Live Sales Card Responsive */
            @media (max-width: 575.98px) {
              /* Mobile: Live Sales card adjustments */
              .bg-line-chart-gradient .card-header {
                flex-direction: row !important;
                align-items: center !important;
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
              }
              .bg-line-chart-gradient .card-header h5 {
                font-size: 0.8rem !important;
                margin-bottom: 0 !important;
                flex: 1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
              }
              .bg-line-chart-gradient .card-header select {
                width: auto !important;
                min-width: 90px !important;
                max-width: 110px !important;
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
              }
              .bg-line-chart-gradient .display-4 {
                font-size: 1.5rem !important;
              }
              #liveSalesChart {
                height: 100px !important;
              }
              #liveTransactionsList {
                max-height: 280px !important;
              }
            }

            /* Live Sales branch filter dropdown options styling */
            #liveSalesBranchFilter option {
              background-color: #1a68c0 !important;
              color: #fff !important;
            }

            @media (min-width: 576px) and (max-width: 767.98px) {
              /* Mobile landscape: Chart height adjustment */
              #liveSalesChart {
                height: 110px !important;
              }
            }

            @media (min-width: 768px) and (max-width: 991.98px) {
              /* Tablet: Chart height adjustment */
              #liveSalesChart {
                height: 120px !important;
              }
            }
            
            /* Calendar icon inputs — icon always anchored to its input wrapper */
            #dailyRangeInput .position-relative,
            #monthlyRangeInput .position-relative,
            #annualRangeInput .position-relative {
              min-width: 160px;
            }
            @media (max-width: 767.98px) {
              #dailyRangeInput .position-relative,
              #monthlyRangeInput .position-relative,
              #annualRangeInput .position-relative {
                min-width: auto;
                width: 100%;
              }
            }
          </style>
          <div class="row mb-3">
            <div class="col">
              <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center">
                  <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                      <h6 class="text-primary fs-10 mb-0">Analytics Dashboard</h6>
                      <h4 class="text-primary fw-bold mb-0">Sales <span class="text-info fw-medium">Overview</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                  </div>
                  <div style="display: none;" class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                      <div class="col-auto">
                        <h6 class="text-700 mb-0">Showing Data For: </h6>
                      </div>
                      <div class="col-auto">
                        <div class="btn-group" role="group" id="dateRangeButtons">
                          <button type="button" class="btn btn-sm btn-outline-primary active" data-range="today">Today</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" data-range="week">This Week</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" data-range="last30days">Last 30 Days</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" data-range="year">This Year</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" data-range="custom">Custom</button>
                        </div>
                      </div>
                      <div class="col-auto">
                        <select class="form-select form-select-sm" id="globalBranchSelector" style="width: auto; min-width: 150px;">
                          <option value="">All Branches</option>
                        </select>
                      </div>
                      <div class="col-auto d-none" id="customDateRangeContainer">
                        <div class="row g-2 align-items-center">
                          <div class="col-auto">
                            <select class="form-select form-select-sm" id="customRangeType">
                              <option value="daily">Daily Range</option>
                              <option value="monthly">Monthly Range</option>
                              <option value="annual">Annual Range</option>
                            </select>
                          </div>
                          <div class="col-auto" id="dailyRangeInput">
                            <div class="position-relative">
                              <span class="fas fa-calendar-alt text-primary position-absolute top-50 translate-middle-y" style="left:10px;z-index:5;pointer-events:none;"></span>
                              <input class="form-control form-control-sm datetimepicker" id="AnalyticsDateRange" type="text" placeholder="Jan 1 - Jan 5" style="padding-left:2rem;" data-options="{&quot;mode&quot;:&quot;range&quot;,&quot;dateFormat&quot;:&quot;M d&quot;,&quot;disableMobile&quot;:true}" />
                            </div>
                          </div>
                          <div class="col-auto d-none" id="monthlyRangeInput">
                            <div class="position-relative">
                              <span class="fas fa-calendar-alt text-primary position-absolute top-50 translate-middle-y" style="left:10px;z-index:5;pointer-events:none;"></span>
                              <input class="form-control form-control-sm datetimepicker" id="MonthlyRange" type="text" placeholder="Jan - Mar" style="padding-left:2rem;" data-options="{&quot;mode&quot;:&quot;range&quot;,&quot;dateFormat&quot;:&quot;M&quot;,&quot;disableMobile&quot;:true}" />
                            </div>
                          </div>
                          <div class="col-auto d-none" id="annualRangeInput">
                            <div class="position-relative">
                              <span class="fas fa-calendar-alt text-primary position-absolute top-50 translate-middle-y" style="left:10px;z-index:5;pointer-events:none;"></span>
                              <input class="form-control form-control-sm datetimepicker" id="AnnualRange" type="text" placeholder="2021 - 2026" style="padding-left:2rem;" data-options="{&quot;mode&quot;:&quot;range&quot;,&quot;dateFormat&quot;:&quot;Y&quot;,&quot;disableMobile&quot;:true}" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Branch Sales Analytics Card -->
          <div class="row g-3 mb-3">
            <div class="col-xxl-8">
              <div class="card overflow-hidden mb-3" id="branchAnalyticsCard">
                <div class="card-header audience-chart-header p-0 bg-body-tertiary scrollbar-overlay">
                  <ul class="nav nav-tabs border-0 chart-tab flex-nowrap" id="audience-chart-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <a class="nav-link mb-0 active" id="sales-tab" data-bs-toggle="tab" href="#sales" role="tab" aria-controls="sales" aria-selected="true">
                        <div class="audience-tab-item p-2 pe-4">
                          <h6 class="text-800 fs-11 text-nowrap">Total Sales</h6>
                          <h5 class="text-800" id="tabTotalSales">₱0.00</h5>
                          <div class="d-flex align-items-center">
                            <span class="fas fa-caret-up text-success" id="tabSalesTrendIcon"></span>
                            <h6 class="fs-11 mb-0 ms-2 text-success" id="tabSalesTrend">0%</h6>
                          </div>
                        </div>
                      </a>
                    </li>
                 
                    <li class="nav-item" role="presentation">
                      <a class="nav-link mb-0" id="refunds-tab" data-bs-toggle="tab" href="#refunds" role="tab" aria-controls="refunds" aria-selected="false">
                        <div class="audience-tab-item p-2 pe-4">
                          <h6 class="text-800 fs-11 text-nowrap">Refunds</h6>
                          <h5 class="text-800" id="tabRefunds">₱0.00</h5>
                          <div class="d-flex align-items-center">
                            <span class="fas fa-caret-down text-warning" id="tabRefundTrendIcon"></span>
                            <h6 class="fs-11 mb-0 ms-2 text-warning" id="tabRefundTrend">0%</h6>
                          </div>
                        </div>
                      </a>
                    </li>

                      <li class="nav-item" role="presentation">
                      <a class="nav-link mb-0" id="net-tab" data-bs-toggle="tab" href="#net" role="tab" aria-controls="net" aria-selected="false">
                        <div class="audience-tab-item p-2 pe-4">
                          <h6 class="text-800 fs-11 text-nowrap">Net Sales</h6>
                          <h5 class="text-800" id="tabNetSales">₱0.00</h5>
                          <div class="d-flex align-items-center">
                            <span class="fas fa-caret-up text-success" id="tabNetTrendIcon"></span>
                            <h6 class="fs-11 mb-0 ms-2 text-success" id="tabNetTrend">0%</h6>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a class="nav-link mb-0" id="profit-tab" data-bs-toggle="tab" href="#profit" role="tab" aria-controls="profit" aria-selected="false">
                        <div class="audience-tab-item p-2 pe-4">
                          <h6 class="text-800 fs-11 text-nowrap">Profit</h6>
                          <h5 class="text-800" id="tabProfit">₱0.00</h5>
                          <div class="d-flex align-items-center">
                            <span class="fas fa-caret-up text-success" id="tabProfitTrendIcon"></span>
                            <h6 class="fs-11 mb-0 ms-2 text-success" id="tabProfitTrend">0%</h6>
                          </div>
                        </div>
                      </a>
                    </li>
                  </ul>
                </div>
                <div class="card-body">
                  <!-- Branch Selector -->
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-primary" id="currentBranchName">
                      <span class="fas fa-store me-2"></span><span id="branchNameText">Loading...</span>
                    </h5>
                    <select class="form-select form-select-sm" id="branchSelector" style="width: auto; min-width: 150px;">
                      <!-- Populated by JS -->
                    </select>
                  </div>

                  <div class="tab-content">
                    <div class="tab-pane active" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                      <div id="branchSalesChart" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="net" role="tabpanel" aria-labelledby="net-tab">
                      <div id="branchNetChart" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="refunds" role="tabpanel" aria-labelledby="refunds-tab">
                      <div id="branchRefundsChart" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="profit" role="tabpanel" aria-labelledby="profit-tab">
                      <div id="branchProfitChart" style="height:320px;"></div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm" id="branchAnalyticsRange">
                        <option value="today">Today</option>
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="last30days">Last 30 days</option>
                        <option value="year">This Year</option>
                      </select>
                    </div>
                    <div class="col-auto">
                      <a class="btn btn-link btn-sm px-0 fw-medium" href="<?php echo BASE_URL; ?>/admin/pos/transactions/">
                        <span class="fas fa-external-link-alt me-1"></span>View Transactions
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Accounts Receivable Widget -->
              <div class="card position-relative overflow-hidden d-none d-md-block" id="arWidget">
                <div class="bg-holder bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-2.png);"></div>
                <!--/.bg-holder-->
                <div class="card-header bg-body-tertiary py-2 d-flex align-items-center justify-content-between position-relative">
                  <h6 class="mb-0 text-700">
                    <span class="fas fa-file-invoice-dollar me-2 text-warning"></span>Accounts Receivable
                  </h6>
                  <a href="<?php echo BASE_URL; ?>/admin/charges/" class="btn btn-link btn-sm px-0 fw-medium">
                    <span class="fas fa-external-link-alt me-1 fs-11"></span>View All
                  </a>
                </div>
                <div class="card-body position-relative" id="arWidgetBody">
                  <!-- Loading state -->
                  <div class="text-center text-muted py-4" id="arLoading">
                    <span class="fas fa-spinner fa-spin fa-lg mb-2 d-block"></span>
                    <span class="fs-11">Loading...</span>
                  </div>
                  <!-- Content -->
                  <div id="arContent" style="display:none;">
                    <!-- Hero total -->
                    <div class="d-flex align-items-start justify-content-between mb-3">
                      <div>
                        <p class="fs-11 text-600 mb-1">Total Outstanding Balance</p>
                        <h3 class="fw-semibold text-warning mb-0" id="arTotalOutstanding">₱0.00</h3>
                      </div>
                      <div class="text-end">
                        <span class="badge badge-subtle-warning fs-11 px-2" id="arCustomersBadge">0 customers</span>
                      </div>
                    </div>
                    <!-- Collection progress (7-day) -->
                    <div class="mb-3">
                      <div class="d-flex justify-content-between fs-11 text-600 mb-1">
                        <span><span class="fas fa-arrow-up text-danger me-1"></span>Charged <span class="fw-semibold text-danger" id="arCharged7d">₱0.00</span></span>
                        <span><span class="fas fa-arrow-down text-success me-1"></span>Collected <span class="fw-semibold text-success" id="arCollected7d">₱0.00</span></span>
                      </div>
                      <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-success" id="arCollectionBar" role="progressbar" style="width:0%"></div>
                      </div>
                      <p class="fs-11 text-500 mt-1 mb-0">Collection rate this week: <span id="arCollectionRate" class="fw-semibold">0%</span></p>
                    </div>
                    <div id="arTopDebtorsSection" style="display:none;"><div id="arTopDebtors"></div></div>
                  </div>
                  <!-- Error state -->
                  <div id="arError" style="display:none;" class="text-center text-danger py-3 fs-11">
                    <span class="fas fa-exclamation-circle me-1"></span><span id="arErrorMsg">Failed to load</span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Standalone AR Widget for Tablet/Laptop -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6 d-none" id="arWidgetStandalone">
              <div class="card h-100 position-relative overflow-hidden">
                <div class="bg-holder bg-card" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/icons/spot-illustrations/corner-2.png);"></div>
                <div class="card-header bg-body-tertiary py-2 d-flex align-items-center justify-content-between position-relative">
                  <h6 class="mb-0 text-700">
                    <span class="fas fa-file-invoice-dollar me-2 text-warning"></span>Accounts Receivable
                  </h6>
                  <a href="<?php echo BASE_URL; ?>/admin/charges/" class="btn btn-link btn-sm px-0 fw-medium">
                    <span class="fas fa-external-link-alt me-1 fs-11"></span>View All
                  </a>
                </div>
                <div class="card-body position-relative" id="arWidgetBodyStandalone">
                  <!-- Content will be cloned from main AR widget -->
                </div>
              </div>
            </div>
            <!-- Live Sales Card -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6 col-xxl-4">
              <div class="card h-100 bg-line-chart-gradient">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-start pt-3" data-bs-theme="light">
                  <div class="d-flex gap-2">
                    <h5 class="text-white fw-bold mb-0">Live Sales</h5>
                    <select class="form-select form-select-sm" id="liveSalesBranchFilter" style="width:auto;min-width:120px;background-color:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:#fff;">
                      <option value="" style="background:#1a68c0;color:#fff;">All Branches</option>
                    </select>
                  </div>
                  <select class="form-select form-select-sm" id="liveSalesFilter" style="width:auto;min-width:90px;background-color:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:#fff;">
                    <option value="today"   style="background:#1a68c0;color:#fff;">Today</option>
                    <option value="1"       style="background:#1a68c0;color:#fff;">Last Hour</option>
                    <option value="6"       style="background:#1a68c0;color:#fff;">Last 6 Hours</option>
                    <option value="24"      style="background:#1a68c0;color:#fff;">Last 24 Hours</option>
                  </select>
                </div>
                <div class="card-body pb-0">
                  <div class="display-4 fw-bold text-white mb-0" id="liveSalesTotal">₱0.00</div>
                  <p class="text-white opacity-75 fs-10 mb-3">Sales / <span id="liveFilterLabel">today</span></p>
                  <!-- Bar chart -->
                  <div id="liveSalesChart" style="height:130px;"></div>
                  <!-- Transactions subtitle -->
                  <p class="text-white opacity-75 fs-10 mt-2 mb-2" style="border-top:1px solid rgba(255,255,255,0.15);padding-top:8px;">
                    Recent Transactions &nbsp;<span class="badge" style="background:rgba(255,255,255,0.2);font-weight:500;" id="liveTransactionCount">0</span>
                  </p>
                  <!-- Transactions list -->
                  <div id="liveTransactionsList" class="scrollbar" style="max-height: 320px; overflow-y: auto;">
                    <div class="text-white opacity-50 fs-11 py-1">Loading...</div>
                  </div>
                </div>
                <div class="card-footer bg-transparent text-end pt-0">
                  <a class="text-white fs-10" href="<?php echo BASE_URL; ?>/admin/pos/transactions/">View Transactions <span class="fa fa-chevron-right ms-1"></span></a>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-7 d-flex flex-column align-items-stretch">
              <div class="card mb-3 h-100">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Cashier Performance</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-cashier-report" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-cashier-report">
                      <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/pos/transactions/">View Transactions</a>
                    </div>
                  </div>
                </div>
                <div class="card-body pe-0">
                  <div class="row g-0">
                    <div class="col-auto" style="min-width:120px;">
                      <div class="d-flex flex-column justify-content-between h-100 ps-2 py-2" id="cashierLegend">
                        <div class="pb-2">
                          <span class="fas fa-spinner fa-spin text-muted fs-11"></span>
                        </div>
                      </div>
                    </div>
                    <div class="col">
                      <div id="cashierPerformanceChart" style="height:260px; width:100%;"></div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto d-flex gap-2">
                      <select class="form-select form-select-sm" id="cashierPerformanceBranchFilter" style="width:140px;">
                        <option value="">All Branches</option>
                      </select>
                      <select class="form-select form-select-sm" id="cashierPerformanceFilter" style="width:120px;">
                        <option value="1">Today</option>
                        <option value="7" selected>Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="365">This Year</option>
                      </select>
                    </div>
                    <div class="col-auto">
                      <h6 class="mb-0"><a class="py-2" href="<?php echo BASE_URL; ?>/admin/pos/transactions/">POS Transactions<span class="fas fa-chevron-right ms-1 fs-11"></span></a></h6>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card">
                <div class="card-body py-5 py-sm-3">
                  <div class="row g-5 g-sm-0">
                    <div class="col-sm-4">
                      <div class="border-end-sm border-300">
                        <div class="text-center">
                          <h6 class="text-700">Total Orders</h6>
                          <h3 class="fw-normal text-700" id="totalOrdersGoal">0</h3>
                        </div>
                        <div id="goalChart1" style="height:50px"></div>
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div class="border-end-sm border-300">
                        <div class="text-center">
                          <h6 class="text-700">Total Revenue</h6>
                          <h3 class="fw-normal text-700" id="totalRevenueGoal">₱0</h3>
                        </div>
                        <div id="goalChart2" style="height:50px"></div>
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div>
                        <div class="text-center">
                          <h6 class="text-700">Avg Order Value</h6>
                          <h3 class="fw-normal text-700" id="avgOrderValueGoal">₱0</h3>
                        </div>
                        <div id="goalChart3" style="height:50px"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Right column: Sales Target and Provider Wallet stacked -->
            <div class="col-lg-5 d-flex flex-column gap-3">
              <!-- Sales Target Card -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Sales Target</h6>
                  <a class="btn btn-link btn-sm" href="<?php echo BASE_URL; ?>/admin/dashboard/sales-targets/">
                    <span class="fas fa-cog"></span>
                  </a>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-6 text-center border-end">
                      <h6 class="text-700 mb-1 fs-11">Target</h6>
                      <h4 class="fw-bold text-primary mb-0" id="todayTargetAmount">₱0</h4>
                    </div>
                    <div class="col-6 text-center">
                      <h6 class="text-700 mb-1 fs-11">Net Sales</h6>
                      <h4 class="fw-normal text-700 mb-0" id="todayActualSales">₱0</h4>
                    </div>
                  </div>
                  <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                      <small class="text-700 fw-semibold">Achievement</small>
                      <small class="text-700 fw-semibold" id="todayTargetPercent">0%</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                      <div class="progress-bar" id="todayTargetProgress" role="progressbar" style="width: 0%"></div>
                    </div>
                  </div>
                  <div class="text-center mt-2">
                    <span class="badge bg-secondary" id="todayTargetStatus">No Target Set</span>
                  </div>
                  <div class="text-center mt-1" id="todayTargetNotesContainer" style="display: none;">
                    <small class="text-muted" id="todayTargetNotes"></small>
                  </div>
                  <div class="mt-2 pt-2 border-top">
                    <div class="d-flex justify-content-between fs-11 text-600">
                      <span id="todayTargetBranch">All Branches</span>
                      <span id="todayTargetDateRange">Today</span>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Provider Wallet Balances — Traffic Source style -->
              <div class="card h-100" id="walletWidget">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Provider Wallet Balances</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal"
                      type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <span class="fas fa-ellipsis-h fs-11"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end border py-2">
                      <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/">View All</a>
                    </div>
                  </div>
                </div>
                <div class="card-body pb-0">
                  <!-- Branch Selector -->
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-700 fs-11">
                      <span class="fas fa-store me-1"></span><span id="walletBranchName">All Branches</span>
                    </h6>
                    <select class="form-select form-select-sm" id="walletBranchSelector" style="width: auto; min-width: 150px;">
                      <option value="">All Branches</option>
                      <!-- Populated by JS -->
                    </select>
                  </div>
                  <!-- Legend row — dynamic -->
                  <div id="walletLegend" class="d-flex flex-wrap gap-2 mb-3 fs-11 text-600">
                    <span class="fas fa-spinner fa-spin text-muted"></span>
                  </div>
                  <!-- Stacked bar chart -->
                  <div id="walletChart" style="height:230px; width:100%;"></div>
                  <!-- Error -->
                  <div id="walletError" style="display:none;" class="text-center text-danger py-3 fs-11">
                    <span class="fas fa-exclamation-circle me-1"></span><span id="walletErrorMsg">Failed to load</span>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <p class="fs-11 mb-0 text-600">
                        Total: <strong class="text-800" id="walletTotalBalance">—</strong>
                        &nbsp;·&nbsp;
                        <span id="walletCountBadge" class="text-600">—</span>
                        <span id="walletLowBalanceBadge" style="display:none;">
                          &nbsp;·&nbsp;<span class="text-warning fw-semibold">
                            <span class="fas fa-exclamation-triangle me-1"></span><span id="walletLowCount">0</span> low
                          </span>
                        </span>
                      </p>
                    </div>
                    <div class="col-auto">
                      <a class="btn btn-link btn-sm px-0 fw-medium" href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets/">
                        Wallet overview<span class="fas fa-chevron-right ms-1 fs-11"></span>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-4 col-xxl-4">
              <div class="card h-100">
                <div class="card-header bg-body-tertiary py-3">
                  <h6 class="mb-0">Transactions per Hour</h6>
                </div>
                <div class="card-body">
                  <div id="transactionsPerHourChart" style="height:250px;"></div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm flex-grow-1" id="hourlyFilter">
                      <option value="today">Today</option>
                      <option value="week" selected="selected">Last 7 days</option>
                      <option value="last30days">Last 30 days</option>
                      <option value="year">This Year</option>
                    </select>
                    <select class="form-select form-select-sm flex-grow-1" id="hourlyBranchFilter">
                      <option value="" selected="selected">All Branches</option>
                    </select>
                    <a class="btn btn-link btn-sm px-0 fw-medium flex-shrink-0" href="#!">Details<span class="fas fa-chevron-right ms-1 fs-11"></span></a>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xxl-4">
              <div class="card h-100">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Payment Breakdown</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-payment-breakdown" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-payment-breakdown"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-between py-0">
                  <div class="my-auto py-5 py-md-0">
                    <div id="paymentBreakdownChart" style="height:250px;"></div>
                  </div>
                  <div class="border-top">
                    <table class="table table-sm mb-0" id="paymentBreakdownTable">
                      <tbody>
                        <tr><td colspan="3" class="text-center py-3 text-muted">Loading...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto d-flex gap-2">
                      <select class="form-select form-select-sm" id="paymentBreakdownBranchFilter" style="width:140px;">
                        <option value="">All Branches</option>
                      </select>
                      <select class="form-select form-select-sm" id="paymentBreakdownFilter" style="width:120px;">
                        <option value="today">Today</option>
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="last30days">Last 30 days</option>
                        <option value="year">This Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Payment overview<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-xxl-4">
              <div class="card h-100">
                <div class="card-header">
                  <div class="row flex-between-center">
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                      <h6 class="mb-0 text-nowrap py-2 py-xl-0">Top Services Today</h6>
                      <select class="form-select form-select-sm flex-grow-1 flex-shrink-0" id="topServicesBranchFilter" style="min-width:120px; max-width:140px;">
                        <option value="">All Branches</option>
                      </select>
                      <select class="form-select form-select-sm flex-grow-1 flex-shrink-0" id="topServicesFilter" style="min-width:120px; max-width:140px;">
                        <option value="today" selected="selected">Today</option>
                        <option value="week">Last 7 days</option>
                        <option value="last30days">Last 30 days</option>
                        <option value="year">This Year</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="card-body px-0 py-0">
                  <div class="table-responsive scrollbar">
                    <table class="table fs-10 mb-0 overflow-hidden">
                      <thead class="bg-200">
                        <tr>
                          <th class="text-900 pe-1 align-middle white-space-nowrap">Service Name</th>
                          <th class="text-900 pe-1 align-middle white-space-nowrap text-end">Orders</th>
                          <th class="text-900 pe-1 align-middle white-space-nowrap text-end">Revenue</th>
                          <th class="text-900 pe-x1 align-middle white-space-nowrap text-end">Avg Price</th>
                        </tr>
                      </thead>
                      <tbody class="list" id="topServicesTable">
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap text-center text-muted" colspan="4">
                            <span class="fas fa-spinner fa-spin me-1"></span>Loading...
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="text-center d-none" id="pages-table-fallback">
                    <p class="fw-bold fs-8 mt-3">No Page found</p>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center">
                    <div class="col-auto">
                      <p class="fs-11 mb-0 text-600">
                        Showing top <span class="fw-bold" id="topServicesCount">0</span> services
                      </p>
                    </div>
                    <div class="col-auto">
                      <a class="btn btn-link btn-sm px-0 fw-medium" href="#!">View All<span class="fas fa-chevron-right ms-1 fs-11"></span></a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <footer class="footer">
            <div class="row g-0 justify-content-between fs-10 mt-4 mb-3">
              <div class="col-12 col-sm-auto text-center">
                <p class="mb-0 text-600">Thank you for creating with Falcon <span class="d-none d-sm-inline-block">| </span><br class="d-sm-none" /> 2025 &copy; <a href="https://themewagon.com">Themewagon</a></p>
              </div>
              <div class="col-12 col-sm-auto text-center">
                <p class="mb-0 text-600">v3.26.0</p>
              </div>
            </div>
          </footer>
        </div>
        <div class="modal fade" id="authentication-modal" tabindex="-1" role="dialog" aria-labelledby="authentication-modal-label" aria-hidden="true">
          <div class="modal-dialog mt-6" role="document">
            <div class="modal-content border-0">
              <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                <div class="position-relative z-1">
                  <h4 class="mb-0 text-white" id="authentication-modal-label">Register</h4>
                  <p class="fs-10 mb-0 text-white">Please create your free Falcon account</p>
                </div>
                <div data-bs-theme="dark">
                  <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
              </div>
              <div class="modal-body py-4 px-5">
                <form>
                  <div class="mb-3">
                    <label class="form-label" for="modal-auth-name">Name</label>
                    <input class="form-control" type="text" autocomplete="on" id="modal-auth-name" />
                  </div>
                  <div class="mb-3">
                    <label class="form-label" for="modal-auth-email">Email address</label>
                    <input class="form-control" type="email" autocomplete="on" id="modal-auth-email" />
                  </div>
                  <div class="row gx-2">
                    <div class="mb-3 col-sm-6">
                      <label class="form-label" for="modal-auth-password">Password</label>
                      <input class="form-control" type="password" autocomplete="on" id="modal-auth-password" />
                    </div>
                    <div class="mb-3 col-sm-6">
                      <label class="form-label" for="modal-auth-confirm-password">Confirm Password</label>
                      <input class="form-control" type="password" autocomplete="on" id="modal-auth-confirm-password" />
                    </div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="modal-auth-register-checkbox" />
                    <label class="form-label" for="modal-auth-register-checkbox">I accept the <a href="#!">terms </a>and <a class="white-space-nowrap" href="#!">privacy policy</a></label>
                  </div>
                  <div class="mb-3">
                    <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit">Register</button>
                  </div>
                </form>
                <div class="position-relative mt-5">
                  <hr />
                  <div class="divider-content-center">or register with</div>
                </div>
                <div class="row g-2 mt-2">
                  <div class="col-sm-6"><a class="btn btn-outline-google-plus btn-sm d-block w-100" href="#"><span class="fab fa-google-plus-g me-2" data-fa-transform="grow-8"></span> google</a></div>
                  <div class="col-sm-6"><a class="btn btn-outline-facebook btn-sm d-block w-100" href="#"><span class="fab fa-facebook-square me-2" data-fa-transform="grow-8"></span> facebook</a></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- Suppress old Falcon audience chart init only (others are still valid) -->
    <script>
        // Suppress stale Falcon chart classes that no longer have valid DOM elements
        ['.echart-audience', '.echart-session-by-browser', '.echart-session-by-country',
         '.echart-session-by-country-map'].forEach(function(sel) {
            document.querySelectorAll(sel).forEach(function(el) { el.className = ''; });
        });
        window._originalUtilsGetData = null;
    </script>

    <?php include __DIR__ . '/../includes/scripts.php'; ?>

    <!-- Suppress theme.js chart initialization errors -->
    <script>
        // Patch utils.getData immediately to prevent audience.js errors
        (function() {
            // Suppress errors from old chart initializations
            window.addEventListener('error', function(e) {
                if (e.message && (e.message.includes('dataset') || e.message.includes('getAttribute') ||
                    e.message.includes('regions') || e.message.includes('echart') ||
                    e.filename?.includes('audience.js') || e.filename?.includes('utils.js'))) {
                    console.warn('Suppressed chart error:', e.message);
                    e.preventDefault();
                    e.stopPropagation();
                    return true;
                }
            }, true);

            // Patch utils.getData when it becomes available
            function patchUtils() {
                if (window.utils && window.utils.getData && !window._utilsPatched) {
                    window._utilsPatched = true;
                    window._originalUtilsGetData = window.utils.getData;
                    window.utils.getData = function(el, data) {
                        if (!el || !el.dataset) {
                            console.warn('utils.getData: Invalid element - returning empty data');
                            return data || {};
                        }
                        try {
                            return window._originalUtilsGetData.call(this, el, data);
                        } catch (e) {
                            console.warn('utils.getData error suppressed:', e.message);
                            return data || {};
                        }
                    };
                }
            }

            // Try patching immediately and on DOMContentLoaded
            patchUtils();
            document.addEventListener('DOMContentLoaded', patchUtils);
            setTimeout(patchUtils, 100);
            setTimeout(patchUtils, 500);
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script>
    // ─── Global Filter Helpers ─────────────────────────────────────────────
    // Returns the current global analytics filters from localStorage/DOM.
    // {range, branchId, startDate, endDate, effectiveRange}
    // - effectiveRange normalizes "custom-*" to a base range so legacy cards
    //   that don't support custom dates can still pick a sensible fallback.
    function getGlobalFilters() {
        const savedRange = localStorage.getItem('analyticsDateRange') || 'today';
        const branchEl = document.getElementById('globalBranchSelector');
        const branchId = (branchEl ? branchEl.value : localStorage.getItem('analyticsBranchId')) || '';
        const startDate = localStorage.getItem('analyticsCustomStartDate') || '';
        const endDate   = localStorage.getItem('analyticsCustomEndDate')   || '';

        // Map custom-* range -> base range for legacy fallbacks
        let effectiveRange = savedRange;
        const isCustom = typeof savedRange === 'string' && savedRange.startsWith('custom');
        if (isCustom) {
            switch (savedRange) {
                case 'custom-daily':   effectiveRange = 'week';  break;
                case 'custom-weekly':  effectiveRange = 'month'; break;
                case 'custom-monthly': effectiveRange = 'month'; break;
                case 'custom-annual':  effectiveRange = 'year';  break;
                default:               effectiveRange = 'month';
            }
        }
        return { range: savedRange, effectiveRange, branchId, startDate, endDate, isCustom };
    }

    // Build a URL querystring fragment from global filters.
    // opts.includeDates: send start_date/end_date when custom range is active
    // opts.includeBranch: include branch_id
    function buildFilterQuery(opts = {}) {
        const { includeDates = true, includeBranch = true } = opts;
        const f = getGlobalFilters();
        const params = new URLSearchParams();
        params.set('range', f.effectiveRange);
        if (includeBranch && f.branchId) params.set('branch_id', f.branchId);
        if (includeDates && f.isCustom && f.startDate && f.endDate) {
            params.set('start_date', f.startDate);
            params.set('end_date', f.endDate);
        }
        return params.toString();
    }

    // Live Sales Data
    const LIVE_SALES_API = window.BASE_URL + '/api/analytics/live-sales.php';
    let liveSalesChart = null;
    let liveSalesInterval = null;
    let transactionsPerHourChart = null;

    function initLiveSalesChart() {
        const chartDom = document.getElementById('liveSalesChart');
        if (!chartDom) return;

        liveSalesChart = echarts.init(chartDom, null, { renderer: 'canvas' });

        liveSalesChart.setOption({
            grid: { left: 0, right: 0, top: 4, bottom: 0 },
            xAxis: {
                type: 'category', data: [],
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { show: false }, splitLine: { show: false }
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { show: false }, splitLine: { show: false }
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'none' },
                backgroundColor: 'rgba(0,31,91,0.9)',
                borderWidth: 0,
                padding: [6, 10],
                textStyle: { color: '#fff', fontSize: 11 },
                formatter: function(params) {
                    const p = params[0];
                    return `<span style="opacity:.7">${p.axisValue}</span><br/><b>₱${parseFloat(p.value||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</b>`;
                }
            },
            series: [{
                type: 'bar',
                data: [],
                barWidth: '60%',
                barCategoryGap: '40%',
                itemStyle: {
                    color: 'rgba(255,255,255,0.35)',
                    borderRadius: [1, 1, 0, 0]
                },
                emphasis: {
                    itemStyle: { color: 'rgba(255,255,255,0.7)' }
                }
            }]
        });
    }

    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    }

    const filterLabels = { 'today': 'today', '1': 'last hour', '6': 'last 6 hours', '24': 'last 24 hours' };

    function fetchLiveSales() {
        const filter = document.getElementById('liveSalesFilter').value;
        const branchFilter = document.getElementById('liveSalesBranchFilter');
        const branchId = branchFilter ? branchFilter.value : '';

        // Update subtitle label
        const labelEl = document.getElementById('liveFilterLabel');
        if (labelEl) labelEl.textContent = filterLabels[filter] || filter;

        let apiUrl = LIVE_SALES_API + (filter === 'today' ? '?hours=24&today=true' : '?hours=' + filter);
        if (branchId) {
            apiUrl += '&branch_id=' + branchId;
        } else {
            // If no local branch filter, use global branch selector
            const globalBranchSelector = document.getElementById('globalBranchSelector');
            if (globalBranchSelector && globalBranchSelector.value) {
                apiUrl += '&branch_id=' + globalBranchSelector.value;
            }
        }

        fetch(apiUrl)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const d = data.data;

                document.getElementById('liveSalesTotal').textContent      = formatCurrency(d.total_sales);
                document.getElementById('liveTransactionCount').textContent = d.transaction_count;

                // Update bar chart — API always returns 60 minute slots
                if (liveSalesChart) {
                    const rawPoints = d.sales_by_minute || [];
                    const hasData = rawPoints.some(p => parseFloat(p.amount) > 0);
                    
                    if (hasData) {
                        liveSalesChart.clear();
                        liveSalesChart.setOption({
                            grid: { left: 0, right: 0, top: 4, bottom: 0 },
                            xAxis: {
                                type: 'category', data: rawPoints.map(p => p.time),
                                axisLine: { show: false }, axisTick: { show: false },
                                axisLabel: { show: false }, splitLine: { show: false }
                            },
                            yAxis: {
                                type: 'value',
                                axisLine: { show: false }, axisTick: { show: false },
                                axisLabel: { show: false }, splitLine: { show: false }
                            },
                            tooltip: {
                                trigger: 'axis',
                                axisPointer: { type: 'none' },
                                backgroundColor: 'rgba(0,31,91,0.9)',
                                borderWidth: 0,
                                padding: [6, 10],
                                textStyle: { color: '#fff', fontSize: 11 },
                                formatter: function(params) {
                                    const p = params[0];
                                    return `<span style="opacity:.7">${p.axisValue}</span><br/><b>₱${parseFloat(p.value||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</b>`;
                                }
                            },
                            series: [{
                                type: 'bar',
                                data: rawPoints.map(p => parseFloat(p.amount) || 0),
                                barWidth: '60%',
                                barCategoryGap: '40%',
                                itemStyle: {
                                    color: 'rgba(255,255,255,0.35)',
                                    borderRadius: [1, 1, 0, 0]
                                },
                                emphasis: {
                                    itemStyle: { color: 'rgba(255,255,255,0.7)' }
                                }
                            }]
                        });
                    } else {
                        // No data - show empty state
                        liveSalesChart.clear();
                        liveSalesChart.setOption({
                            grid: { left: 0, right: 0, top: 4, bottom: 0 },
                            xAxis: { show: false },
                            yAxis: { show: false },
                            series: []
                        });
                    }
                }

                // Recent transactions list — clean rows matching template
                const list = document.getElementById('liveTransactionsList');
                
                if (d.recent_transactions && d.recent_transactions.length > 0) {
                    list.innerHTML = d.recent_transactions.slice(0, 5).map((txn, idx) => `
                        <div class="d-flex flex-column py-2 ${idx < 4 ? 'border-bottom' : ''}" style="border-color:rgba(255,255,255,0.1)!important;cursor:pointer;transition:background-color 0.2s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.1)'" onmouseout="this.style.backgroundColor='transparent'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="text-truncate" style="max-width:65%;">
                                    <span class="fs-11 fw-medium text-white">${txn.order_code}</span>
                                </div>
                                <span class="fs-11 fw-light text-white">${formatCurrency(txn.grand_total)}</span>
                            </div>
                            <div class="d-flex align-items-center mt-1">
                                <span class="fs-10 text-white opacity-75">${txn.branch_name || 'N/A'}</span>
                                <span class="fs-10 text-white opacity-50 mx-1">&bull;</span>
                                <span class="fs-10 text-white opacity-75">${txn.cashier_name || 'N/A'}</span>
                            </div>
                        </div>`).join('');
                } else {
                    list.innerHTML = `<p class="fs-11 mb-0 py-1 text-white opacity-50">No transactions yet</p>`;
                }
            })
            .catch(e => console.error('Live sales error:', e));
    }

    function initGoalCharts(savedRange = null, savedBranch = null) {
        // Fetch TMS metrics from pos_orders with saved or current filters
        const range = savedRange || currentRange || 'month';
        const branchId = savedBranch || document.getElementById('globalBranchSelector')?.value || '';
        fetchTMSMetrics(range, branchId);
    }

    async function fetchTMSMetrics(range = 'month', branchId = null) {
        try {
            // Prefer the centralized global filters; fall back to args if needed
            const f = getGlobalFilters();
            const params = new URLSearchParams();
            const effRange = f.effectiveRange || range || 'month';
            params.set('range', effRange);
            const finalBranch = branchId ?? f.branchId;
            if (finalBranch) params.set('branch_id', finalBranch);
            if (f.isCustom && f.startDate && f.endDate) {
                params.set('start_date', f.startDate);
                params.set('end_date', f.endDate);
            }
            const url = `${window.BASE_URL}/api/analytics/tms-metrics.php?${params.toString()}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                const metrics = result.data;
                
                // Update display values
                document.getElementById('totalOrdersGoal').textContent = metrics.total_orders.toLocaleString();
                document.getElementById('totalRevenueGoal').textContent = '₱' + metrics.total_revenue.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                document.getElementById('avgOrderValueGoal').textContent = '₱' + metrics.avg_order_value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Render charts with trend data
                renderGoalChart1(metrics.orders_trend);
                renderGoalChart2(metrics.revenue_trend);
                renderGoalChart3(metrics.avg_trend);
            }
        } catch (e) {
            console.error('Error fetching TMS metrics:', e);
            // Set default values on error
            document.getElementById('totalOrdersGoal').textContent = '0';
            document.getElementById('totalRevenueGoal').textContent = '₱0';
            document.getElementById('avgOrderValueGoal').textContent = '₱0';
        }
    }

    function renderGoalChart1(data) {
        const goalChart1 = echarts.init(document.getElementById('goalChart1'));
        goalChart1.setOption({
            tooltip: { show: false },
            grid: { right: '16px', left: '0', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(data.length).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: data,
                itemStyle: { barBorderRadius: [5,5,0,0], color: '#2c7be5' },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });
    }

    function renderGoalChart2(data) {
        const goalChart2 = echarts.init(document.getElementById('goalChart2'));
        goalChart2.setOption({
            tooltip: { show: false },
            grid: { right: '16px', left: '16px', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(data.length).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: data,
                itemStyle: { barBorderRadius: [5,5,0,0], color: '#00d27a' },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });
    }

    function renderGoalChart3(data) {
        const goalChart3 = echarts.init(document.getElementById('goalChart3'));
        goalChart3.setOption({
            tooltip: { show: false },
            grid: { right: '0', left: '16px', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(data.length).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: data,
                itemStyle: { barBorderRadius: [5,5,0,0], color: '#f5803e' },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });
    }

    // initCampaignCharts removed - Ad campaigns card replaced with Provider Wallet Balances

    // Initialize on page load with optimized loading
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded started...');

        // Get saved filters before initializing charts
        const savedRange = localStorage.getItem('analyticsDateRange') || 'today';
        const savedBranch = localStorage.getItem('analyticsBranchId') || '';
        
        // Initialize charts first (non-blocking) with saved values
        requestAnimationFrame(() => {
            initLiveSalesChart();
            initGoalCharts(savedRange, savedBranch);
            initCashierPerformanceChart(savedRange, savedBranch);
            initTransactionsPerHourChart();
        });

        // Restore filter from localStorage
        const savedFilter = localStorage.getItem('liveSalesFilter');
        if (savedFilter) {
            document.getElementById('liveSalesFilter').value = savedFilter;
        }

        // Restore hourly filter from localStorage
        const savedHourlyFilter = localStorage.getItem('hourlyFilter') || 'week';
        const hourlyFilterEl = document.getElementById('hourlyFilter');
        if (hourlyFilterEl) {
            hourlyFilterEl.value = savedHourlyFilter;
        }

        // Restore payment breakdown filter from localStorage
        const savedPaymentFilter = localStorage.getItem('paymentBreakdownFilter') || 'week';
        const paymentFilterEl = document.getElementById('paymentBreakdownFilter');
        if (paymentFilterEl) {
            paymentFilterEl.value = savedPaymentFilter;
        }

        // Fetch Transactions per Hour after filters are restored
        setTimeout(() => {
            fetchTransactionsPerHour();
        }, 100);

        // Live Sales filter change handler -> push to global
        document.getElementById('liveSalesFilter').addEventListener('change', function() {
            localStorage.setItem('liveSalesFilter', this.value);
            // If it's a standard range, push to global so all cards stay in sync
            if (['today','week','last30days','year'].includes(this.value) && typeof refreshAnalytics === 'function') {
                refreshAnalytics(this.value);
                syncTopFilterButtons(this.value);
            } else {
                fetchLiveSales();
            }
        });

        // Live Sales branch filter change handler -> push to global
        document.getElementById('liveSalesBranchFilter')?.addEventListener('change', function() {
            const globalSelector = document.getElementById('globalBranchSelector');
            if (globalSelector) {
                globalSelector.value = this.value;
                localStorage.setItem('analyticsBranchId', this.value);
                // Trigger global change event so all cards refresh
                globalSelector.dispatchEvent(new Event('change'));
            } else {
                fetchLiveSales();
            }
        });

        // Save hourly filter to localStorage on change

        // Fetch critical data first
        fetchLiveSales();

        // Poll every 30 seconds for real-time updates
        liveSalesInterval = setInterval(fetchLiveSales, 30000);

        // Initialize branch analytics with loading state
        const branchCard = document.getElementById('branchAnalyticsCard');
        if (branchCard) {
            branchCard.classList.add('position-relative');
            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'branchAnalyticsLoading';
            loadingOverlay.className = 'position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex align-items-center justify-content-center z-1';
            loadingOverlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            branchCard.appendChild(loadingOverlay);
        }

        // Clone AR widget content to standalone version for tablet/laptop
        function cloneARContent() {
            const mainBody = document.getElementById('arWidgetBody');
            const standaloneBody = document.getElementById('arWidgetBodyStandalone');
            if (mainBody && standaloneBody) {
                standaloneBody.innerHTML = mainBody.innerHTML;
            }
        }
        
        // Clone on load and whenever AR content updates
        cloneARContent();
        
        // Watch for AR content changes (using MutationObserver)
        const arObserver = new MutationObserver(() => {
            cloneARContent();
        });
        const arWidgetBody = document.getElementById('arWidgetBody');
        if (arWidgetBody) {
            arObserver.observe(arWidgetBody, { childList: true, subtree: true });
        }

        // Initialize branch analytics after restoreDateRange is complete (300ms to ensure restore happens first)
        setTimeout(() => {
            try {
                initBranchCharts();
                fetchBranchAnalytics().finally(() => {
                    const loadingOverlay = document.getElementById('branchAnalyticsLoading');
                    if (loadingOverlay) loadingOverlay.remove();
                });
            } catch (e) {
                console.error('Branch analytics init error:', e);
                const loadingOverlay = document.getElementById('branchAnalyticsLoading');
                if (loadingOverlay) loadingOverlay.remove();
            }
        }, 300);

        // Branch analytics event listeners - sync with global filter
        const rangeSelector = document.getElementById('branchAnalyticsRange');
        if (rangeSelector) {
            rangeSelector.addEventListener('change', function() {
                // Update global currentRange
                currentRange = this.value;
                localStorage.setItem('analyticsDateRange', this.value);
                // Update global date range buttons UI
                if (dateRangeButtons) {
                    const buttons = dateRangeButtons.querySelectorAll('button');
                    buttons.forEach(btn => btn.classList.remove('active'));
                    const matchingBtn = dateRangeButtons.querySelector(`[data-range="${this.value}"]`);
                    if (matchingBtn) matchingBtn.classList.add('active');
                }
                customDateRangeContainer.classList.add('d-none');
                // Refresh all connected cards
                refreshAnalytics(this.value);
            });
        }

        const branchSelector = document.getElementById('branchSelector');
        if (branchSelector) {
            branchSelector.addEventListener('change', function() {
                // Sync with global branch selector
                const globalSelector = document.getElementById('globalBranchSelector');
                if (globalSelector) {
                    globalSelector.value = this.value;
                    localStorage.setItem('analyticsBranchId', this.value);
                    // Trigger global change event so all cards refresh
                    globalSelector.dispatchEvent(new Event('change'));
                } else {
                    localStorage.setItem('analyticsBranchId', this.value);
                    const currentRange = localStorage.getItem('analyticsDateRange') || 'week';
                    refreshAnalytics(currentRange);
                }
            });
        }

        // Load secondary widgets after primary data is loaded
        setTimeout(() => {
            // Accounts Receivable widget
            try {
                console.log('Fetching Accounts Receivable...');
                fetchAccountsReceivable();
                setInterval(fetchAccountsReceivable, 60000);
            } catch (e) {
                console.error('Error fetching Accounts Receivable:', e);
            }

            // Provider Wallet Balances widget - use saved branch
            try {
                console.log('Fetching Provider Wallet Balances...');
                fetchProviderWallets(savedBranch || null);
                setInterval(() => {
                    const currentBranch = localStorage.getItem('analyticsBranchId') || '';
                    fetchProviderWallets(currentBranch || null);
                }, 60000);
            } catch (e) {
                console.error('Error fetching Provider Wallet Balances:', e);
            }

            // Today's Sales Target widget
            try {
                console.log('Fetching Today\'s Sales Target...');
                fetchTodaySalesTarget(savedBranch || null);
                setInterval(() => {
                    const currentBranch = localStorage.getItem('analyticsBranchId') || '';
                    fetchTodaySalesTarget(currentBranch || null);
                }, 60000);
            } catch (e) {
                console.error('Error fetching Today\'s Sales Target:', e);
            }

            // Transactions per Hour chart
            try {
                console.log('Fetching Transactions per Hour...');
                fetchTransactionsPerHour();
            } catch (e) {
                console.error('Error fetching Transactions per Hour:', e);
            }

            // Top Services table
            try {
                console.log('Fetching Top Services...');
                fetchTopServices();
            } catch (e) {
                console.error('Error fetching Top Services:', e);
            }

            // Payment Breakdown chart
            try {
                console.log('Fetching Payment Breakdown...');
                fetchPaymentBreakdown();
            } catch (e) {
                console.error('Error fetching Payment Breakdown:', e);
            }
        }, 1000);

        // Handle window resize for all charts
        window.addEventListener('resize', function() {
            if (liveSalesChart) liveSalesChart.resize();

            // Resize goal charts
            const goalChart1 = echarts.getInstanceByDom(document.getElementById('goalChart1'));
            const goalChart2 = echarts.getInstanceByDom(document.getElementById('goalChart2'));
            const goalChart3 = echarts.getInstanceByDom(document.getElementById('goalChart3'));
            if (goalChart1) goalChart1.resize();
            if (goalChart2) goalChart2.resize();
            if (goalChart3) goalChart3.resize();

            // Resize cashier performance chart
            if (cashierPerformanceChart) cashierPerformanceChart.resize();

            // Resize branch analytics charts
            Object.values(branchCharts).forEach(chart => {
                if (chart && !chart.isDisposed()) chart.resize();
            });

            // Resize payment breakdown chart
            if (paymentBreakdownChart && !paymentBreakdownChart.isDisposed()) {
                paymentBreakdownChart.resize();
            }
        });
    });

    // Clean up interval when leaving page
    window.addEventListener('beforeunload', function() {
        if (liveSalesInterval) clearInterval(liveSalesInterval);
    });

    // ─── Transactions per Hour Chart ─────────────────────────────────────────────
    // Variable moved to top of script to avoid temporal dead zone issue

    function initTransactionsPerHourChart() {
        const chartDom = document.getElementById('transactionsPerHourChart');
        if (!chartDom) return;
        
        // Prevent re-initialization
        if (chartDom.dataset.initialized === 'true') return;
        chartDom.dataset.initialized = 'true';
        
        transactionsPerHourChart = echarts.init(chartDom, null, { renderer: 'canvas' });
    }

    async function fetchTransactionsPerHour() {
        try {
            console.log('fetchTransactionsPerHour called');
            // Use global filter as the source of truth; sync local selectors visually
            const f = getGlobalFilters();
            const localHourly = document.getElementById('hourlyFilter');
            const localBranch = document.getElementById('hourlyBranchFilter');
            if (localHourly && ['today','week','month','last30days','year'].includes(f.effectiveRange)) {
                localHourly.value = f.effectiveRange;
            }
            if (localBranch) localBranch.value = f.branchId || '';

            const url = `${window.BASE_URL}/api/analytics/transactions-per-hour.php?${buildFilterQuery()}`;
            console.log('Fetching from URL:', url);
            const response = await fetch(url);
            const result = await response.json();
            console.log('API response:', result);

            if (result.success) {
                console.log('Calling renderTransactionsPerHourChart with:', result.data);
                renderTransactionsPerHourChart(result.data);
            } else {
                console.error('API returned error:', result.error);
            }
        } catch (e) {
            console.error('Error fetching transactions per hour:', e);
        }
    }

    function renderTransactionsPerHourChart(data) {
        const chartDom = document.getElementById('transactionsPerHourChart');
        if (!chartDom) {
            console.error('transactionsPerHourChart element not found');
            return;
        }

        console.log('Chart element found, dimensions:', chartDom.offsetWidth, 'x', chartDom.offsetHeight);

        if (!transactionsPerHourChart) {
            transactionsPerHourChart = echarts.init(chartDom, null, { renderer: 'canvas' });
            console.log('Chart initialized');
        }

        console.log('Rendering Transactions per Hour chart with data:', data);

        // Check for empty data
        const hasData = data.data && data.data.some(v => v > 0);
        console.log('Has data:', hasData, 'Data array:', data.data);
        
        if (!hasData) {
            console.log('No data found, showing empty state');
            // Show empty state
            chartDom.innerHTML = `
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4">
                    <span class="fas fa-clock text-muted fs-2 mb-2"></span>
                    <p class="text-muted fs-11 mb-0">No transaction data available</p>
                    <p class="text-500 fs-10 mb-0">No transactions in selected period</p>
                </div>
            `;
            if (transactionsPerHourChart) {
                transactionsPerHourChart.clear();
                transactionsPerHourChart.setOption({
                    grid: { left: 0, right: 0, top: 0, bottom: 0 },
                    xAxis: { show: false },
                    yAxis: { show: false },
                    series: []
                });
            }
            return;
        }
        
        console.log('Data found, rendering chart');

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const labelColor = isDark ? '#9da9bb' : '#748194';

        // If chart exists, dispose it to clear any previous state
        if (transactionsPerHourChart) {
            transactionsPerHourChart.dispose();
            transactionsPerHourChart = null;
        }

        // Re-initialize chart
        transactionsPerHourChart = echarts.init(chartDom, null, { renderer: 'canvas' });

        transactionsPerHourChart.setOption({
            tooltip: {
                trigger: 'axis',
                backgroundColor: isDark ? '#0b1727' : '#fff',
                borderColor: isDark ? '#344050' : '#d8e2ef',
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: isDark ? '#d8e2ef' : '#344050', fontSize: 12 },
                formatter: function(params) {
                    const p = params[0];
                    return `<div class="fw-semibold mb-1 fs-11">${p.axisValue}</div>
                            <div style="display:flex;align-items:center;gap:6px">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color}"></span>
                                <span>Transactions: </span>
                                <span style="font-weight:600">${p.value}</span>
                            </div>`;
                }
            },
            grid: { left: 40, right: 20, top: 20, bottom: 30, containLabel: true },
            xAxis: {
                type: 'category',
                data: data.labels,
                axisLine: { lineStyle: { color: gridColor } },
                axisTick: { show: false },
                axisLabel: { color: labelColor, fontSize: 10, rotate: data.labels.length > 12 ? 45 : 0 }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: gridColor, type: 'dashed' } },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: labelColor, fontSize: 10 }
            },
            series: [{
                type: 'bar',
                data: data.data,
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: '#2c7be5' },
                        { offset: 1, color: '#27bcfd' }
                    ]),
                    borderRadius: [4, 4, 0, 0]
                },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });

        // Force resize after a short delay to ensure proper rendering
        setTimeout(() => {
            if (transactionsPerHourChart) {
                transactionsPerHourChart.resize();
                console.log('Chart resized after render');
            }
        }, 100);
    }

    // Hourly filter change handler -> push to global filter
    document.getElementById('hourlyFilter')?.addEventListener('change', function() {
        if (['today','week','last30days','year'].includes(this.value)) {
            // Push to global so all cards stay in sync
            if (typeof refreshAnalytics === 'function') {
                refreshAnalytics(this.value);
                syncTopFilterButtons(this.value);
            } else {
                localStorage.setItem('analyticsDateRange', this.value);
                fetchTransactionsPerHour();
            }
        } else {
            fetchTransactionsPerHour();
        }
    });

    // Branch filter change handler -> push to global filter
    document.getElementById('hourlyBranchFilter')?.addEventListener('change', function() {
        const globalSelector = document.getElementById('globalBranchSelector');
        if (globalSelector) {
            globalSelector.value = this.value;
            localStorage.setItem('analyticsBranchId', this.value);
            // Trigger global change event so all cards refresh
            globalSelector.dispatchEvent(new Event('change'));
        } else {
            fetchTransactionsPerHour();
        }
    });

    // ─── Top Services Table ───────────────────────────────────────────────────────
    async function fetchTopServices() {
        try {
            const f = getGlobalFilters();
            // Sync local selector visually
            const local = document.getElementById('topServicesFilter');
            const branchFilter = document.getElementById('topServicesBranchFilter');
            if (local && ['today','week','month','year'].includes(f.effectiveRange)) {
                local.value = f.effectiveRange;
            }
            
            // Use local branch filter if set, otherwise use global
            const branchId = branchFilter ? branchFilter.value : f.branchId;
            
            let query = 'range=' + (f.effectiveRange || 'today');
            if (branchId) {
                query += '&branch_id=' + branchId;
            }
            if (f.isCustom && f.startDate && f.endDate) {
                query += '&start_date=' + f.startDate + '&end_date=' + f.endDate;
            }
            
            const response = await fetch(`${window.BASE_URL}/api/analytics/top-services.php?${query}`);
            const result = await response.json();

            if (result.success) {
                renderTopServicesTable(result.data);
            }
        } catch (e) {
            console.error('Error fetching top services:', e);
            document.getElementById('topServicesTable').innerHTML = 
                '<tr><td class="align-middle white-space-nowrap text-center text-muted" colspan="4">Failed to load</td></tr>';
        }
    }

    // Top Services filter change handler -> push to global
    document.getElementById('topServicesFilter')?.addEventListener('change', function() {
        if (['today','week','last30days','year'].includes(this.value) && typeof refreshAnalytics === 'function') {
            refreshAnalytics(this.value);
            syncTopFilterButtons(this.value);
        } else {
            fetchTopServices();
        }
    });

    // Top Services branch filter change handler -> push to global
    document.getElementById('topServicesBranchFilter')?.addEventListener('change', function() {
        const globalSelector = document.getElementById('globalBranchSelector');
        if (globalSelector) {
            globalSelector.value = this.value;
            localStorage.setItem('analyticsBranchId', this.value);
            // Trigger global change event so all cards refresh
            globalSelector.dispatchEvent(new Event('change'));
        } else {
            fetchTopServices();
        }
    });

    // ─── Payment Breakdown Pie Chart ─────────────────────────────────────────────
    let paymentBreakdownChart = null;

    async function fetchPaymentBreakdown() {
        try {
            const f = getGlobalFilters();
            const local = document.getElementById('paymentBreakdownFilter');
            const branchFilter = document.getElementById('paymentBreakdownBranchFilter');
            if (local && ['today','week','last30days','year'].includes(f.effectiveRange)) {
                local.value = f.effectiveRange;
            }
            
            // Use local branch filter if set, otherwise use global
            const branchId = branchFilter ? branchFilter.value : f.branchId;
            
            let query = 'range=' + (f.effectiveRange || 'today');
            if (branchId) {
                query += '&branch_id=' + branchId;
            }
            if (f.isCustom && f.startDate && f.endDate) {
                query += '&start_date=' + f.startDate + '&end_date=' + f.endDate;
            }
            
            const response = await fetch(`${window.BASE_URL}/api/analytics/payment-breakdown.php?${query}`);
            const result = await response.json();

            if (result.success) {
                renderPaymentBreakdownChart(result.data);
                updatePaymentBreakdownTable(result.data);
            }
        } catch (e) {
            console.error('Error fetching payment breakdown:', e);
        }
    }

    function renderPaymentBreakdownChart(data) {
        const chartDom = document.getElementById('paymentBreakdownChart');
        if (!chartDom) return;

        // Check dimensions
        const rect = chartDom.getBoundingClientRect();
        console.log('Payment chart: dimensions', { width: rect.width, height: rect.height });

        // Build chart data
        const chartData = Object.entries(data.breakdown || {})
            .filter(([name, item]) => item.amount > 0)
            .map(([name, item], index) => ({
                value: item.amount,
                name: name,
                itemStyle: { color: ['#2c7be5', '#00d97e', '#0091e9', '#f6c343', '#e63757', '#6c757d', '#39afd1', '#727cf5'][index % 8] }
            }));

        console.log('Payment chart: data items', chartData.length, chartData);

        if (chartData.length === 0) {
            chartDom.innerHTML = `
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4">
                    <span class="fas fa-credit-card text-muted fs-2 mb-2"></span>
                    <p class="text-muted fs-11 mb-0">No payment data available</p>
                </div>
            `;
            return;
        }

        // Dispose and re-create chart for clean state
        if (paymentBreakdownChart && !paymentBreakdownChart.isDisposed()) {
            paymentBreakdownChart.dispose();
        }
        paymentBreakdownChart = echarts.init(chartDom);

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        const opt = {
            animation: true,
            animationDuration: 1000,
            animationEasing: 'cubicOut',
            animationDelay: (idx) => idx * 100,
            tooltip: {
                trigger: 'item',
                backgroundColor: isDark ? '#0b1727' : '#fff',
                borderColor: isDark ? '#344050' : '#d8e2ef',
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: isDark ? '#d8e2ef' : '#344050', fontSize: 12 },
                formatter: function(params) {
                    return `<div style="display:flex;align-items:center;gap:6px">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${params.color}"></span>
                        <span>${params.name}</span>
                        <span style="font-weight:600">₱${params.value.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                        <span style="opacity:0.7">(${params.percent}%)</span>
                    </div>`;
                }
            },
            series: [{
                type: 'pie',
                radius: ['40%', '70%'],
                center: ['50%', '50%'],
                avoidLabelOverlap: false,
                itemStyle: {
                    borderRadius: 4,
                    borderColor: isDark ? '#0b1727' : '#fff',
                    borderWidth: 2
                },
                label: { show: false },
                emphasis: {
                    label: { show: true, fontSize: 12, fontWeight: 'bold' }
                },
                data: chartData
            }]
        };

        try {
            paymentBreakdownChart.setOption(opt, true);
            console.log('Payment chart: rendered successfully');
        } catch (e) {
            console.error('Payment chart: render failed:', e);
        }
    }

    function updatePaymentBreakdownTable(data) {
        const tableBody = document.getElementById('paymentBreakdownTable').querySelector('tbody');
        const colors = ['#2c7be5', '#00d97e', '#0091e9', '#f6c343', '#e63757', '#6c757d', '#39afd1', '#727cf5'];
        
        // Get icons based on payment method name
        function getIconForMethod(methodName) {
            const method = methodName.toLowerCase();
            if (method.includes('cash')) return 'fa-money-bill';
            if (method.includes('card') || method.includes('credit') || method.includes('debit')) return 'fa-credit-card';
            if (method.includes('wallet') || method.includes('gcash') || method.includes('maya')) return 'fa-wallet';
            if (method.includes('bank') || method.includes('transfer')) return 'fa-university';
            return 'fa-circle';
        }
        
        // Get text color class based on payment method
        function getColorClass(methodName, index) {
            const colorClasses = ['text-primary', 'text-success', 'text-info', 'text-warning', 'text-danger', 'text-secondary', 'text-cyan', 'text-indigo'];
            return colorClasses[index % colorClasses.length];
        }
        
        // Build table rows dynamically
        const rows = Object.entries(data.breakdown || {})
            .filter(([name, item]) => item.amount > 0)
            .map(([name, item], index) => {
                const icon = getIconForMethod(name);
                const colorClass = getColorClass(name, index);
                const color = colors[index % colors.length];
                return `
                    <tr>
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <span class="fas ${icon} ${colorClass} fs-11 me-2"></span>
                                <h6 class="text-600 mb-0">${name}</h6>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <span class="fas fa-circle fs-11 me-2" style="color:${color}"></span>
                                <h6 class="fw-normal text-700 mb-0">${item.percent}%</h6>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center justify-content-end">
                                <h6 class="fs-11 mb-0 text-700">₱${item.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</h6>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        
        tableBody.innerHTML = rows || '<tr><td colspan="3" class="text-center py-3 text-muted">No payment data</td></tr>';
    }

    // Payment Breakdown filter change handler -> push to global
    document.getElementById('paymentBreakdownFilter')?.addEventListener('change', function() {
        if (['today','week','last30days','year'].includes(this.value) && typeof refreshAnalytics === 'function') {
            refreshAnalytics(this.value);
            syncTopFilterButtons(this.value);
        } else {
            fetchPaymentBreakdown();
        }
    });

    // Payment Breakdown branch filter change handler -> push to global
    document.getElementById('paymentBreakdownBranchFilter')?.addEventListener('change', function() {
        const globalSelector = document.getElementById('globalBranchSelector');
        if (globalSelector) {
            globalSelector.value = this.value;
            localStorage.setItem('analyticsBranchId', this.value);
            // Trigger global change event so all cards refresh
            globalSelector.dispatchEvent(new Event('change'));
        } else {
            fetchPaymentBreakdown();
        }
    });

    // Helper: visually sync the top filter buttons to a given range
    function syncTopFilterButtons(range) {
        const btnContainer = document.getElementById('dateRangeButtons');
        if (!btnContainer) return;
        btnContainer.querySelectorAll('button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.range === range);
        });
        const customContainer = document.getElementById('customDateRangeContainer');
        if (customContainer) customContainer.classList.add('d-none');
    }

    function renderTopServicesTable(data) {
        const tableBody = document.getElementById('topServicesTable');
        const countBadge = document.getElementById('topServicesCount');
        
        if (!data.services || data.services.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td class="align-middle white-space-nowrap text-center text-muted" colspan="4">
                        <div class="d-flex flex-column align-items-center justify-content-center py-4">
                            <span class="fas fa-concierge-bell text-muted fs-2 mb-2"></span>
                            <p class="text-muted fs-11 mb-0">No services data available</p>
                            <p class="text-500 fs-10 mb-0">No transactions in selected period</p>
                        </div>
                    </td>
                </tr>
            `;
            countBadge.textContent = '0';
            return;
        }

        countBadge.textContent = data.count;

        tableBody.innerHTML = data.services.map(service => `
            <tr class="btn-reveal-trigger hover-bg-100" style="cursor: pointer;">
                <td class="align-middle white-space-nowrap">
                    <span class="text-primary fw-semi-bold">${service.service_name}</span>
                </td>
                <td class="align-middle white-space-nowrap text-end">${service.orders}</td>
                <td class="align-middle white-space-nowrap text-end">₱${parseFloat(service.revenue).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td class="align-middle text-end pe-x1">₱${parseFloat(service.avg_price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>
        `).join('');
    }

    // ─── Cashier Performance Chart (Real POS Data) ──────────────────────────────────
    let cashierPerformanceChart = null;
    let cashierPerformanceData = null;

    async function fetchCashierPerformance(days = 7, branchId = null) {
        try {
            let url = `${window.BASE_URL}/api/analytics/cashier-performance.php?days=${days}`;
            if (branchId) {
                url += `&branch_id=${branchId}`;
            }
            const res = await fetch(url);
            const result = await res.json();

            if (!result.success) {
                console.error('Cashier performance error:', result.error);
                return null;
            }

            return result.data;
        } catch (e) {
            console.error('Error fetching cashier performance:', e);
            return null;
        }
    }

    function renderCashierPerformanceChart(data) {
        const legendEl = document.getElementById('cashierLegend');
        const chartDom = document.getElementById('cashierPerformanceChart');
        
        if (!data || !data.cashiers || data.cashiers.length === 0) {
            // Show empty state - modify parent column to span full width
            const legendParent = legendEl.closest('.col-auto');
            const chartCol = legendParent.nextElementSibling;
            
            if (legendParent && chartCol) {
                legendParent.style.width = '100%';
                legendParent.style.flex = '1';
                chartCol.style.display = 'none';
            }
            
            legendEl.innerHTML = `
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4">
                    <span class="fas fa-user-clock text-muted fs-2 mb-2"></span>
                    <p class="text-muted fs-11 mb-0">No cashier data available</p>
                    <p class="text-500 fs-10 mb-0">No transactions in selected period</p>
                </div>
            `;
            // Clear chart
            if (cashierPerformanceChart) {
                cashierPerformanceChart.clear();
                cashierPerformanceChart.setOption({
                    grid: { left: 0, right: 0, top: 0, bottom: 0 },
                    xAxis: { show: false },
                    yAxis: { show: false },
                    series: []
                });
            }
            return;
        }

        cashierPerformanceData = data;

        // Restore layout when data is available
        const legendParent = legendEl.closest('.col-auto');
        const chartCol = legendParent.nextElementSibling;
        
        if (legendParent && chartCol) {
            legendParent.style.width = '';
            legendParent.style.flex = '';
            chartCol.style.display = '';
        }

        // Update legend with top 3 cashiers
        const topCashiers = data.cashiers.slice(0, 3);
        legendEl.innerHTML = topCashiers.map((c, i) => `
            <div class="pb-2 ${i > 0 ? 'border-top pt-2' : ''}">
                <h6 class="fs-11 text-600 mb-1">
                    <span class="fas fa-circle me-2 fs-11" style="color:${c.color}"></span>${c.name}
                </h6>
                <h5 class="fw-normal text-800 mb-0">₱${c.total_sales.toLocaleString('en-PH')}</h5>
                <small class="text-600 fs-10">${c.transaction_count} sales</small>
            </div>
        `).join('');

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const labelColor = isDark ? '#9da9bb' : '#748194';
        const tooltipBg = isDark ? '#0b1727' : '#fff';
        const tooltipBorder = isDark ? '#344050' : '#d8e2ef';

        // Build series from real data
        const series = data.cashiers.map(c => ({
            name: c.name,
            type: 'line',
            data: c.data,
            smooth: false,
            symbol: 'none',
            lineStyle: { width: 3, color: c.color },
            itemStyle: { color: c.color },
            areaStyle: {
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: c.color + '40' }, // 25% opacity
                    { offset: 1, color: c.color + '05' }  // 2% opacity
                ])
            }
        }));

        const option = {
            tooltip: {
                trigger: 'axis',
                backgroundColor: tooltipBg,
                borderColor: tooltipBorder,
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: isDark ? '#d8e2ef' : '#344050', fontSize: 12 },
                axisPointer: { type: 'line', lineStyle: { color: gridColor, width: 1 } },
                formatter: function(params) {
                    let html = `<div class="fw-semibold mb-1 fs-11">${params[0].axisValue}</div>`;
                    params.forEach(p => {
                        const val = parseFloat(p.value);
                        html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0">`
                              + `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color}"></span>`
                              + `<span style="flex:1">${p.seriesName}</span>`
                              + `<span style="font-weight:600">₱${val.toLocaleString('en-PH')}</span>`
                              + `</div>`;
                    });
                    return html;
                }
            },
            legend: { show: false },
            grid: { left: 0, right: 20, top: 15, bottom: 30, containLabel: true },
            xAxis: {
                type: 'category',
                data: data.dates,
                boundaryGap: false,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: labelColor,
                    fontSize: 11,
                    interval: Math.floor(data.dates.length / 5),
                    formatter: v => v
                },
                splitLine: { show: false }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: gridColor, type: 'dashed' } },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: labelColor,
                    fontSize: 11,
                    formatter: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v
                }
            },
            series: series
        };

        if (cashierPerformanceChart) {
            cashierPerformanceChart.setOption(option, true);
        }
    }

    // Helper function to convert range to days
    function rangeToDays(range) {
        switch(range) {
            case 'today': return 1;
            case 'week': return 7;
            case 'last30days': return 30;
            case 'month': return 30; // Keep for backward compatibility
            case 'year': return 365;
            default: return 7; // Default to week
        }
    }

    function daysToRange(days) {
        switch(parseInt(days)) {
            case 1: return 'today';
            case 7: return 'week';
            case 30: return 'last30days';
            case 90: return 'year';
            default: return 'week';
        }
    }

    async function initCashierPerformanceChart(savedRange = null, savedBranch = null) {
        const chartDom = document.getElementById('cashierPerformanceChart');
        if (!chartDom) return;

        // Prevent re-initialization
        if (chartDom.dataset.initialized === 'true') return;
        chartDom.dataset.initialized = 'true';

        cashierPerformanceChart = echarts.init(chartDom, null, { renderer: 'canvas' });

        // Initial load with saved or current filter
        const range = savedRange || currentRange || 'week';
        const branchId = savedBranch || document.getElementById('globalBranchSelector')?.value || '';
        const days = rangeToDays(range);
        const data = await fetchCashierPerformance(days, branchId);
        renderCashierPerformanceChart(data);

        // Sync date filter with saved range
        const dateFilter = document.getElementById('cashierPerformanceFilter');
        const branchFilter = document.getElementById('cashierPerformanceBranchFilter');
        if (dateFilter && ['today', 'week', 'month', 'year'].includes(range)) {
            dateFilter.value = rangeToDays(range);
        }

        // Date filter change handler - only add once
        if (dateFilter && !dateFilter.dataset.listenerAdded) {
            dateFilter.dataset.listenerAdded = 'true';
            dateFilter.addEventListener('change', async function() {
                const days = parseInt(this.value);
                const branchId = document.getElementById('cashierPerformanceBranchFilter')?.value || null;
                // Push to global filter for consistency
                const range = daysToRange(days);
                if (['today','week','last30days','year'].includes(range) && typeof refreshAnalytics === 'function') {
                    refreshAnalytics(range);
                    syncTopFilterButtons(range);
                } else {
                    const newData = await fetchCashierPerformance(days, branchId);
                    renderCashierPerformanceChart(newData);
                }
            });
        }

        // Branch filter change handler - only add once
        if (branchFilter && !branchFilter.dataset.listenerAdded) {
            branchFilter.dataset.listenerAdded = 'true';
            branchFilter.addEventListener('change', async function() {
                const days = parseInt(document.getElementById('cashierPerformanceFilter')?.value || 7);
                const branchId = this.value || null;
                // Sync with global branch selector
                const globalSelector = document.getElementById('globalBranchSelector');
                if (globalSelector) {
                    globalSelector.value = branchId;
                    localStorage.setItem('analyticsBranchId', branchId);
                    // Trigger global change event so all cards refresh
                    globalSelector.dispatchEvent(new Event('change'));
                } else {
                    const newData = await fetchCashierPerformance(days, branchId);
                    renderCashierPerformanceChart(newData);
                }
            });
        }
    }

    // Wallet branch selector event listener - sync with global
    document.getElementById('walletBranchSelector')?.addEventListener('change', function() {
        const branchId = this.value;
        const branchName = branchId ? this.options[this.selectedIndex].textContent : 'All Branches';
        document.getElementById('walletBranchName').textContent = branchName;
        // Sync with global branch selector
        const globalSelector = document.getElementById('globalBranchSelector');
        if (globalSelector) {
            globalSelector.value = branchId;
            localStorage.setItem('analyticsBranchId', branchId);
            // Trigger global change event so all cards refresh
            globalSelector.dispatchEvent(new Event('change'));
        } else {
            fetchProviderWallets(branchId || null);
        }
    });

    // ============================================
    // BRANCH SALES ANALYTICS (NEW)
    // ============================================
    let branchAnalyticsData = null;
    let branchCharts = {};

    // Format currency
    function formatPeso(amount) {
        return '₱' + parseFloat(amount || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Format number
    function formatNumber(num) {
        return parseInt(num || 0).toLocaleString('en-PH');
    }

    // Update trend indicators
    function updateTrend(elementId, iconId, value) {
        const el = document.getElementById(elementId);
        const icon = document.getElementById(iconId);
        if (!el || !icon) return;

        const numValue = parseFloat(value) || 0;
        el.textContent = (numValue >= 0 ? '+' : '') + numValue.toFixed(1) + '%';

        if (numValue >= 0) {
            el.className = 'fs-11 mb-0 ms-2 text-success';
            icon.className = 'fas fa-caret-up text-success';
        } else {
            el.className = 'fs-11 mb-0 ms-2 text-danger';
            icon.className = 'fas fa-caret-down text-danger';
        }
    }

    // Fetch branch analytics data (optimized for new pos_orders structure)
    async function fetchBranchAnalytics() {
        // Read range directly from localStorage to get the latest value
        const globalRange = localStorage.getItem('analyticsDateRange') || 'week';
        const isCustom = globalRange && globalRange.startsWith('custom-');
        
        // For custom ranges, use a base range for the API but pass custom dates
        let range = globalRange;
        if (isCustom) {
            // Map custom ranges to base ranges for API compatibility
            if (globalRange === 'custom-daily') range = 'week';
            else if (globalRange === 'custom-monthly') range = 'month';
            else if (globalRange === 'custom-annual') range = 'year';
            else range = 'week';
        }
        
        // Use global branch selector if available, fallback to branchSelector
        const globalBranchSelector = document.getElementById('globalBranchSelector');
        const branchSelector = document.getElementById('branchSelector');
        // Always send branch_id: empty string = All Branches, value = specific branch
        const branchId = globalBranchSelector ? (globalBranchSelector.value || '') : (branchSelector ? (branchSelector.value || '') : null);

        // Sync the branch selectors
        if (globalBranchSelector && branchSelector) {
            branchSelector.value = globalBranchSelector.value;
        }

        // Show loading state
        const branchNameEl = document.getElementById('branchNameText');
        const originalText = branchNameEl.textContent;
        branchNameEl.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</span>';

        try {
            // Build URL with custom dates if applicable
            const branchParam = branchSelector !== null ? `&branch_id=${branchId}` : '';
            let url = `${window.BASE_URL}/api/analytics/branch-sales?range=${range}${branchParam}`;
            
            // Add custom date parameters if custom range is active
            if (isCustom) {
                const startDate = localStorage.getItem('analyticsCustomStartDate');
                const endDate = localStorage.getItem('analyticsCustomEndDate');
                if (startDate && endDate) {
                    url += `&start_date=${startDate}&end_date=${endDate}`;
                }
            }
            
            // Add timeout for better error handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
            
            const response = await fetch(url, { signal: controller.signal });
            clearTimeout(timeoutId);
            
            const result = await response.json();

            if (result.success) {
                branchAnalyticsData = result.data;
                updateBranchAnalyticsUI();
                renderBranchCharts();
                populateBranchSelector(result.data.accessible_branches);

                // Clear any error message
                branchNameEl.textContent = result.data.branch_name;
                
                // Log performance metrics for debugging
                console.log('Branch analytics loaded:', {
                    range,
                    branchId,
                    dataPoints: result.data.chart_data?.length || 0,
                    hasProfitData: result.data.summary.total_profit > 0
                });
            } else {
                console.error('Branch analytics error:', result.error);
                // Show error in UI
                branchNameEl.innerHTML =
                    `<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>${result.error || 'Failed to load'}</span>`;
            }
        } catch (error) {
            console.error('Failed to fetch branch analytics:', error);
            // Show appropriate error message
            if (error.name === 'AbortError') {
                branchNameEl.innerHTML = '<span class="text-warning"><i class="fas fa-clock me-1"></i>Request timeout</span>';
            } else {
                branchNameEl.innerHTML = '<span class="text-warning"><i class="fas fa-wifi me-1"></i>Connection error - retrying...</span>';
            }

            // Retry after 5 seconds (with exponential backoff)
            setTimeout(fetchBranchAnalytics, 5000);
        }
    }

    // Populate branch selector
    function populateBranchSelector(branches) {
        const selector = document.getElementById('branchSelector');
        const globalSelector = document.getElementById('globalBranchSelector');
        const cashierBranchFilter = document.getElementById('cashierPerformanceBranchFilter');
        const walletBranchFilter = document.getElementById('walletBranchSelector');
        const paymentBreakdownBranchFilter = document.getElementById('paymentBreakdownBranchFilter');
        const liveSalesBranchFilter = document.getElementById('liveSalesBranchFilter');
        const topServicesBranchFilter = document.getElementById('topServicesBranchFilter');
        const hourlyBranchFilter = document.getElementById('hourlyBranchFilter');
        
        // Build options: All Branches first (only for multi-branch users)
        let html = '';
        if (branches.length > 1) {
            html += `<option value="">All Branches</option>`;
        }
        html += branches.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
        
        // Populate global selector (only once)
        if (globalSelector && globalSelector.dataset.populated !== 'true') {
            const savedBranch = localStorage.getItem('analyticsBranchId') || '';
            globalSelector.innerHTML = html;
            // Restore saved selection if valid
            if (savedBranch && branches.find(b => b.id === savedBranch)) {
                globalSelector.value = savedBranch;
            }
            globalSelector.dataset.populated = 'true';
        }
        
        // Populate Cashier Performance branch filter (only once)
        if (cashierBranchFilter && cashierBranchFilter.dataset.populated !== 'true') {
            cashierBranchFilter.innerHTML = html;
            cashierBranchFilter.dataset.populated = 'true';
        }
        
        // Populate Wallet branch selector (only once)
        if (walletBranchFilter && walletBranchFilter.dataset.populated !== 'true') {
            walletBranchFilter.innerHTML = html;
            walletBranchFilter.dataset.populated = 'true';
        }
        
        // Populate Payment Breakdown branch filter (only once)
        if (paymentBreakdownBranchFilter && paymentBreakdownBranchFilter.dataset.populated !== 'true') {
            paymentBreakdownBranchFilter.innerHTML = html;
            paymentBreakdownBranchFilter.dataset.populated = 'true';
        }
        
        // Populate Live Sales branch filter (only once)
        if (liveSalesBranchFilter && liveSalesBranchFilter.dataset.populated !== 'true') {
            liveSalesBranchFilter.innerHTML = html;
            liveSalesBranchFilter.dataset.populated = 'true';
        }
        
        // Populate Top Services branch filter (only once)
        if (topServicesBranchFilter && topServicesBranchFilter.dataset.populated !== 'true') {
            topServicesBranchFilter.innerHTML = html;
            topServicesBranchFilter.dataset.populated = 'true';
        }
        
        // Populate Transactions per Hour branch filter (only once)
        if (hourlyBranchFilter && hourlyBranchFilter.dataset.populated !== 'true') {
            hourlyBranchFilter.innerHTML = html;
            hourlyBranchFilter.dataset.populated = 'true';
        }
        
        // Populate local selector (only once)
        if (selector && selector.dataset.populated !== 'true') {
            const currentValue = selector.value;
            selector.innerHTML = html;
            // Sync with global selector
            if (globalSelector) {
                selector.value = globalSelector.value;
            } else if (currentValue && branches.find(b => b.id === currentValue)) {
                selector.value = currentValue;
            }
            selector.dataset.populated = 'true';
        }
    }

    // Update UI with analytics data (optimized for profit data)
    function updateBranchAnalyticsUI() {
        if (!branchAnalyticsData) return;

        const { summary, branch_name } = branchAnalyticsData;

        // Update branch name
        document.getElementById('branchNameText').textContent = branch_name;

        // Update tab values with validation
        const updateElement = (id, value, formatter = formatPeso) => {
            const el = document.getElementById(id);
            if (el) el.textContent = formatter(value || 0);
        };
        
        updateElement('tabTotalSales', summary.total_sales);
        updateElement('tabNetSales', summary.total_net);
        updateElement('tabRefunds', summary.total_refunds);
        updateElement('tabTransactions', summary.total_transactions, formatNumber);
        updateElement('tabProfit', summary.total_profit);

        // Update trends with null checks
        const safeUpdateTrend = (id, iconId, value) => {
            if (value !== null && value !== undefined) {
                updateTrend(id, iconId, value);
            }
        };
        
        safeUpdateTrend('tabSalesTrend', 'tabSalesTrendIcon', summary.period_change);
        safeUpdateTrend('tabNetTrend', 'tabNetTrendIcon', summary.period_change);
        safeUpdateTrend('tabRefundTrend', 'tabRefundTrendIcon', -summary.period_change); // Inverse for refunds
        safeUpdateTrend('tabTxnTrend', 'tabTxnTrendIcon', summary.sales_trend);
        safeUpdateTrend('tabProfitTrend', 'tabProfitTrendIcon', summary.profit_margin);

        // Add profit margin indicator if available
        if (summary.profit_margin !== null && summary.profit_margin !== undefined) {
            const profitTab = document.getElementById('tabProfit');
            if (profitTab && !profitTab.dataset.hasMargin) {
                profitTab.dataset.hasMargin = 'true';
                profitTab.title = `Profit Margin: ${summary.profit_margin.toFixed(1)}%`;
            }
        }
    }

    // Initialize branch charts
    function initBranchCharts() {
        const chartIds = ['branchSalesChart', 'branchNetChart', 'branchRefundsChart', 'branchProfitChart'];

        chartIds.forEach(id => {
            try {
                const dom = document.getElementById(id);
                if (dom && typeof echarts !== 'undefined') {
                    branchCharts[id] = echarts.init(dom);
                }
            } catch (e) {
                console.warn(`Failed to init chart ${id}:`, e);
            }
        });

        // Handle resize
        window.addEventListener('resize', () => {
            Object.values(branchCharts).forEach(chart => {
                if (chart && !chart.isDisposed()) chart.resize();
            });
        });
    }

    // Render all branch charts (optimized with error handling)
    function renderBranchCharts() {
        if (!branchAnalyticsData || !branchAnalyticsData.chart_data) {
            console.warn('No chart data available');
            return;
        }

        const data = branchAnalyticsData.chart_data;
        const dates = data.map(d => d.display_date);

        // Validate data integrity
        if (!Array.isArray(data) || data.length === 0) {
            console.warn('Invalid chart data structure');
            return;
        }

        // Render charts with individual error handling
        const charts = [
            { name: 'Sales', fn: renderSalesChart },
            { name: 'Net', fn: renderNetChart },
            { name: 'Refunds', fn: renderRefundsChart },
            { name: 'Profit', fn: renderProfitChart }
        ];

        charts.forEach(chart => {
            try {
                chart.fn(dates, data);
            } catch (e) {
                console.error(`${chart.name} chart error:`, e);
                // Optionally show error in UI
                const chartEl = document.getElementById(`branch${chart.name}Chart`);
                if (chartEl) {
                    chartEl.innerHTML = '<div class="text-center text-muted p-4">Chart unavailable</div>';
                }
            }
        });
    }

    // Shared base chart config matching Falcon template style
    function baseChartOption(dates) {
        return {
            animation: true,
            animationDuration: 1000,
            animationEasing: 'cubicOut',
            animationDelay: 0,
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'none' },
                backgroundColor: 'rgba(255,255,255,0.96)',
                borderColor: '#e2e8f0',
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: '#344050', fontSize: 12 },
                extraCssText: 'box-shadow:0 4px 14px rgba(0,0,0,.12);border-radius:6px;'
            },
            grid: { left: 40, right: 8, bottom: 40, top: 8, containLabel: true },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: dates,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: '#9da9bb',
                    fontSize: 11,
                    margin: 8,
                    interval: 'auto'
                },
                splitLine: { show: false }
            },
            yAxis: {
                type: 'value',
                position: 'right',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: '#9da9bb', fontSize: 11, margin: 8 },
                splitLine: { lineStyle: { color: '#edf2f9', type: 'solid' } }
            }
        };
    }

    function pesoFormatter(value) {
        if (value >= 1000000) return '₱' + (value / 1000000).toFixed(1) + 'M';
        if (value >= 1000) return '₱' + (value / 1000).toFixed(0) + 'k';
        return '₱' + value;
    }

    function pesoFull(value) {
        return '₱' + parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1,3), 16);
        const g = parseInt(hex.slice(3,5), 16);
        const b = parseInt(hex.slice(5,7), 16);
        return `rgba(${r},${g},${b},${alpha})`;
    }

    function lineSeries(name, values, color, dotted) {
        return {
            name,
            type: 'line',
            smooth: false,
            symbol: 'circle',
            symbolSize: 4,
            showSymbol: false,
            data: values,
            lineStyle: { color, width: 2, type: dotted ? 'dotted' : 'solid' },
            itemStyle: { color, borderWidth: 2, borderColor: '#fff' },
            emphasis: { scale: true },
            areaStyle: dotted ? undefined : {
                color: {
                    type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                    colorStops: [
                        { offset: 0, color: hexToRgba(color, 0.18) },
                        { offset: 1, color: hexToRgba(color, 0) }
                    ]
                }
            }
        };
    }

    // Sales Chart — primary line + dotted refunds line
    function renderSalesChart(dates, data) {
        const chart = branchCharts['branchSalesChart'];
        if (!chart || typeof echarts === 'undefined') return;

        // Clear chart to force animation on re-render
        chart.clear();

        const opt = baseChartOption(dates);
        opt.yAxis.axisLabel.formatter = pesoFormatter;
        opt.tooltip.formatter = function(params) {
            let html = `<div style="font-weight:600;margin-bottom:4px">${params[0].axisValue}</div>`;
            params.forEach(p => {
                html += `<div style="display:flex;justify-content:space-between;gap:16px;margin:2px 0">
                    <span style="color:${p.color}">${p.seriesName}</span>
                    <span style="font-weight:600">${pesoFull(p.value)}</span>
                </div>`;
            });
            return html;
        };
        opt.legend = {
            data: ['Total Sales', 'Refunds'],
            bottom: 0,
            itemWidth: 16,
            itemHeight: 2,
            textStyle: { color: '#9da9bb', fontSize: 11 }
        };
        opt.grid.bottom = 36;
        opt.series = [
            lineSeries('Total Sales', data.map(d => d.sales), '#27bcfd', false),
            {
                name: 'Refunds',
                type: 'line',
                smooth: false,
                symbol: 'circle',
                symbolSize: 4,
                showSymbol: false,
                data: data.map(d => d.refunds),
                lineStyle: { color: '#e63757', width: 1, type: 'dotted' },
                itemStyle: { color: '#e63757', borderWidth: 2, borderColor: '#fff' },
                emphasis: { scale: true }
            }
        ];

        chart.setOption(opt, true);
    }

    // Net Sales Chart
    function renderNetChart(dates, data) {
        const chart = branchCharts['branchNetChart'];
        if (!chart || typeof echarts === 'undefined') return;

        // Clear chart to force animation on re-render
        chart.clear();

        const opt = baseChartOption(dates);
        opt.yAxis.axisLabel.formatter = pesoFormatter;
        opt.tooltip.formatter = function(params) {
            const p = params[0];
            return `<div style="font-weight:600;margin-bottom:4px">${p.axisValue}</div>
                    <div style="display:flex;justify-content:space-between;gap:16px">
                        <span style="color:${p.color}">Net Sales</span>
                        <span style="font-weight:600">${pesoFull(p.value)}</span>
                    </div>`;
        };
        opt.series = [ lineSeries('Net Sales', data.map(d => d.net), '#27bcfd', false) ];

        chart.setOption(opt, true);
    }

    // Refunds Chart
    function renderRefundsChart(dates, data) {
        const chart = branchCharts['branchRefundsChart'];
        if (!chart || typeof echarts === 'undefined') return;

        // Clear chart to force animation on re-render
        chart.clear();

        const opt = baseChartOption(dates);
        opt.yAxis.axisLabel.formatter = pesoFormatter;
        opt.tooltip.formatter = function(params) {
            const p = params[0];
            return `<div style="font-weight:600;margin-bottom:4px">${p.axisValue}</div>
                    <div style="display:flex;justify-content:space-between;gap:16px">
                        <span style="color:${p.color}">Refunds</span>
                        <span style="font-weight:600">${pesoFull(p.value)}</span>
                    </div>`;
        };
        opt.series = [ lineSeries('Refunds', data.map(d => d.refunds), '#e63757', false) ];

        chart.setOption(opt, true);
    }

    // Profit Chart - Multi-Bar Chart showing Revenue, Cost, Service Fees, Add-ons, Profit
    function renderProfitChart(dates, data) {
        const dom = document.getElementById('branchProfitChart');
        if (!dom || typeof echarts === 'undefined') {
            console.warn('Profit chart: DOM or echarts not available');
            return;
        }

        // Check if container has zero dimensions (tab not visible)
        const rect = dom.getBoundingClientRect();
        console.log('Profit chart: dimensions check', { width: rect.width, height: rect.height });

        if (rect.width === 0 || rect.height === 0) {
            console.log('Profit chart: Zero dimensions, deferring render');
            return;
        }

        // Always dispose and re-create for clean state
        let chart = branchCharts['branchProfitChart'];
        if (chart && !chart.isDisposed()) {
            chart.dispose();
        }
        chart = echarts.init(dom);
        branchCharts['branchProfitChart'] = chart;

        // Validate profit data exists
        const hasProfitData = data.some(d => (d.profit && d.profit > 0) || (d.cost && d.cost > 0) || (d.revenue && d.revenue > 0));
        if (!hasProfitData) {
            dom.innerHTML = `
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4">
                    <span class="fas fa-chart-line text-muted fs-2 mb-2"></span>
                    <p class="text-muted fs-11 mb-0">No profit data available</p>
                </div>
            `;
            return;
        }

        // Multi-bar chart configuration
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const tooltipBg = isDark ? '#0b1727' : '#fff';
        const tooltipText = isDark ? '#d8e2ef' : '#344050';

        const opt = {
            animation: true,
            animationDuration: 1000,
            animationEasing: 'cubicOut',
            animationDelay: (idx) => idx * 100,
            tooltip: {
                trigger: 'axis',
                backgroundColor: tooltipBg,
                borderColor: isDark ? '#344050' : '#d8e2ef',
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: tooltipText, fontSize: 12 },
                formatter: function(params) {
                    const d = data[params[0].dataIndex];
                    let html = `<div style="font-weight:600;margin-bottom:4px">${params[0].axisValue}</div>`;
                    params.forEach(p => {
                        if (p.value > 0) {
                            html += `<div style="display:flex;justify-content:space-between;gap:16px">
                                    <span style="color:${p.color}">${p.seriesName}</span>
                                    <span style="font-weight:600">${formatPeso(p.value)}</span>
                                </div>`;
                        }
                    });
                    if (d.profit_margin !== undefined) {
                        html += `<div style="font-size:11px;color:#6e7891;margin-top:4px">
                                    Margin: ${d.profit_margin}%
                                </div>`;
                    }
                    return html;
                }
            },
            grid: { left: 60, right: 20, top: 20, bottom: 60 },
            xAxis: {
                type: 'category',
                data: dates,
                axisLabel: { color: '#6e7891', fontSize: 11, rotate: dates.length > 10 ? 45 : 0 },
                axisLine: { lineStyle: { color: '#e2e8f0' } }
            },
            yAxis: {
                type: 'value',
                axisLabel: { formatter: v => pesoFormatter(v), color: '#6e7891', fontSize: 11 },
                splitLine: { lineStyle: { color: '#e2e8f0', type: 'dashed' } }
            },
            series: [
                {
                    name: 'Revenue',
                    type: 'bar',
                    data: data.map(d => d.revenue || 0),
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#2c7be5' },
                            { offset: 1, color: '#27bcfd' }
                        ]),
                        borderRadius: [4, 4, 0, 0]
                    }
                },
                {
                    name: 'Cost',
                    type: 'bar',
                    data: data.map(d => d.cost || 0),
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#e63757' },
                            { offset: 1, color: '#f5803e' }
                        ]),
                        borderRadius: [4, 4, 0, 0]
                    }
                },
                {
                    name: 'Service Fees',
                    type: 'bar',
                    data: data.map(d => d.service_fees || 0),
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#f5803e' },
                            { offset: 1, color: '#fbc77d' }
                        ]),
                        borderRadius: [4, 4, 0, 0]
                    }
                },
                {
                    name: 'Add-ons',
                    type: 'bar',
                    data: data.map(d => d.add_ons || 0),
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#27bcfd' },
                            { offset: 1, color: '#82d9f5' }
                        ]),
                        borderRadius: [4, 4, 0, 0]
                    }
                },
                {
                    name: 'Profit',
                    type: 'bar',
                    data: data.map(d => d.profit || 0),
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#00d27a' },
                            { offset: 1, color: '#82f5b5' }
                        ]),
                        borderRadius: [4, 4, 0, 0]
                    }
                }
            ],
            legend: {
                data: ['Revenue', 'Cost', 'Service Fees', 'Add-ons', 'Profit'],
                bottom: 10,
                textStyle: { color: '#6e7891', fontSize: 11 }
            }
        };

        try {
            chart.setOption(opt, true);
            console.log('Profit chart: rendered successfully with multi-bar');
        } catch (e) {
            console.error('Profit chart: render failed:', e);
        }
    }

    // Tab change event - render charts to trigger animation (no fetch, use existing data)
    document.querySelectorAll('#audience-chart-tab .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function() {
            // Save selected tab to localStorage
            localStorage.setItem('branchAnalyticsTab', this.id);

            // Re-render the specific chart for the active tab with delay to ensure tab is visible
            setTimeout(() => {
                if (branchAnalyticsData) {
                    const data = branchAnalyticsData.chart_data;
                    const dates = data.map(d => d.display_date);

                    if (this.id === 'sales-tab') renderSalesChart(dates, data);
                    if (this.id === 'net-tab') renderNetChart(dates, data);
                    if (this.id === 'refunds-tab') renderRefundsChart(dates, data);
                    if (this.id === 'profit-tab') renderProfitChart(dates, data);
                }
            }, 300);

            setTimeout(() => {
                Object.values(branchCharts).forEach(chart => {
                    if (chart && !chart.isDisposed()) chart.resize();
                });
            }, 400);
        });
    });
    
    // Restore tab state from localStorage on page load
    const savedTab = localStorage.getItem('branchAnalyticsTab');
    if (savedTab) {
        const tabElement = document.getElementById(savedTab);
        if (tabElement) {
            const tabInstance = new bootstrap.Tab(tabElement);
            tabInstance.show();

            // If profit tab is restored, render the chart after a delay with disposal
            if (savedTab === 'profit-tab' && branchAnalyticsData) {
                setTimeout(() => {
                    const chart = branchCharts['branchProfitChart'];
                    const dom = document.getElementById('branchProfitChart');
                    const data = branchAnalyticsData.chart_data;
                    const dates = data.map(d => d.display_date);

                    // Dispose if needed
                    if (chart && !chart.isDisposed() && dom) {
                        const rect = dom.getBoundingClientRect();
                        if (rect.width === 0 || rect.height === 0) {
                            chart.dispose();
                            branchCharts['branchProfitChart'] = null;
                        }
                    }

                    renderProfitChart(dates, data);

                    // Multiple resize attempts
                    [200, 500, 800].forEach(delay => {
                        setTimeout(() => {
                            const c = branchCharts['branchProfitChart'];
                            if (c && !c.isDisposed()) c.resize();
                        }, delay);
                    });
                }, 400);
            }
        }
    }

    // ─── Provider Wallet Balances Widget (Traffic Source stacked bar style) ──────

    let walletBarChart = null;
    let walletBranches = [];

    // Palette matching Falcon Traffic Source blues
    const WALLET_COLORS = [
        '#2c7be5', '#27bcfd', '#748cf7', '#6e94f5',
        '#a8cbff', '#c9dcf8', '#91c4f2', '#5ea3de'
    ];

    // Helper function to convert range to date range
    function getRangeDates(range) {
        const today = new Date();
        let startDate, endDate;
        
        switch (range) {
            case 'today':
                startDate = endDate = today.toISOString().slice(0, 10);
                break;
            case 'week':
                // Last 7 days (6 days ago to today)
                startDate = new Date(today.setDate(today.getDate() - 6)).toISOString().slice(0, 10);
                endDate = new Date().toISOString().slice(0, 10);
                break;
            case 'last30days':
                startDate = new Date(today.setDate(today.getDate() - 29)).toISOString().slice(0, 10);
                endDate = new Date().toISOString().slice(0, 10);
                break;
            case 'year':
                startDate = new Date(today.getFullYear(), 0, 1).toISOString().slice(0, 10);
                endDate = new Date(today.getFullYear(), 11, 31).toISOString().slice(0, 10);
                break;
            default:
                startDate = endDate = new Date().toISOString().slice(0, 10);
        }
        
        return { startDate, endDate };
    }

    async function fetchTodaySalesTarget(branchId = null) {
        try {
            const savedRange = localStorage.getItem('analyticsDateRange') || 'today';
            const customStartDate = localStorage.getItem('analyticsCustomStartDate');
            const customEndDate = localStorage.getItem('analyticsCustomEndDate');
            
            let startDate, endDate;
            
            // If custom date range is set, use it
            if ((savedRange === 'custom' || savedRange.startsWith('custom-')) && customStartDate && customEndDate) {
                startDate = customStartDate;
                endDate = customEndDate;
            } else {
                // Convert standard range to date range
                const dates = getRangeDates(savedRange);
                startDate = dates.startDate;
                endDate = dates.endDate;
            }
            
            let url = `${window.BASE_URL}/api/sales-targets/targets.php?start_date=${startDate}&end_date=${endDate}`;
            
            if (branchId) {
                url += `&branch_id=${branchId}`;
            }
            
            console.log('Fetching sales target from:', url);
            
            const res = await fetch(url);
            const result = await res.json();
            
            console.log('Sales target API response:', result);
            
            if (result.success && result.targets && result.targets.length > 0) {
                // For date range, aggregate all targets
                let totalTargetAmount = 0;
                let totalActualSales = 0;
                let notes = '';
                
                // Check if there's a TOTAL entry (from API)
                const totalEntry = result.targets.find(t => t.target_date === 'TOTAL');
                
                if (totalEntry) {
                    // Use the TOTAL entry values
                    totalTargetAmount = parseFloat(totalEntry.target_amount) || 0;
                    totalActualSales = parseFloat(totalEntry.actual_sales) || 0;
                    notes = totalEntry.notes || '';
                } else if (result.targets.length === 1) {
                    // Single target (today or specific date)
                    const target = result.targets[0];
                    totalTargetAmount = parseFloat(target.target_amount) || 0;
                    totalActualSales = parseFloat(target.actual_sales) || 0;
                    notes = target.notes || '';
                } else {
                    // Multiple targets without TOTAL entry - aggregate (legacy)
                    result.targets.forEach(target => {
                        totalTargetAmount += parseFloat(target.target_amount) || 0;
                        // Only add actual_sales once since API assigns total to all targets
                        if (totalActualSales === 0) {
                            totalActualSales = parseFloat(target.actual_sales) || 0;
                        }
                    });
                    notes = `Range: ${result.targets.length} day(s)`;
                }
                
                const percent = totalTargetAmount > 0 ? ((totalActualSales / totalTargetAmount) * 100).toFixed(1) : 0;
                
                document.getElementById('todayTargetAmount').textContent = '₱' + totalTargetAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                document.getElementById('todayActualSales').textContent = '₱' + totalActualSales.toLocaleString('en-PH', {minimumFractionDigits: 2});
                document.getElementById('todayTargetPercent').textContent = percent + '%';
                
                const progressBar = document.getElementById('todayTargetProgress');
                const statusBadge = document.getElementById('todayTargetStatus');
                const notesContainer = document.getElementById('todayTargetNotesContainer');
                const notesElement = document.getElementById('todayTargetNotes');
                const branchElement = document.getElementById('todayTargetBranch');
                const dateRangeElement = document.getElementById('todayTargetDateRange');
                
                progressBar.style.width = Math.min(percent, 100) + '%';
                progressBar.className = 'progress-bar';
                statusBadge.className = 'badge';
                
                // Update branch display
                if (branchId) {
                    const globalSelector = document.getElementById('globalBranchSelector');
                    if (globalSelector) {
                        const branchName = globalSelector.options[globalSelector.selectedIndex]?.text || 'Selected Branch';
                        branchElement.textContent = branchName;
                    } else {
                        branchElement.textContent = 'Selected Branch';
                    }
                } else {
                    branchElement.textContent = 'All Branches';
                }
                
                // Update date range display
                const savedRange = localStorage.getItem('analyticsDateRange') || 'today';
                const customStartDate = localStorage.getItem('analyticsCustomStartDate');
                const customEndDate = localStorage.getItem('analyticsCustomEndDate');
                
                if ((savedRange === 'custom' || savedRange.startsWith('custom-')) && customStartDate && customEndDate) {
                    dateRangeElement.textContent = `${customStartDate} - ${customEndDate}`;
                } else {
                    const rangeLabels = {
                        'today': 'Today',
                        'week': 'Last 7 Days',
                        'last30days': 'Last 30 Days',
                        'year': 'This Year'
                    };
                    dateRangeElement.textContent = rangeLabels[savedRange] || savedRange;
                }
                
                if (percent >= 100) {
                    progressBar.classList.add('bg-success');
                    statusBadge.classList.add('bg-success');
                    statusBadge.textContent = 'Exceeded';
                } else if (percent >= 80) {
                    progressBar.classList.add('bg-warning');
                    statusBadge.classList.add('bg-warning');
                    statusBadge.textContent = 'On Target';
                } else {
                    progressBar.classList.add('bg-danger');
                    statusBadge.classList.add('bg-danger');
                    statusBadge.textContent = 'Below Target';
                }
                
                // Display notes if available
                if (notes) {
                    notesContainer.style.display = 'block';
                    notesElement.textContent = 'Note: ' + notes;
                } else if (result.targets.length > 1) {
                    // Always show note for date range with specific details
                    notesContainer.style.display = 'block';
                    const branchName = branchElement.textContent;
                    const dateRangeText = dateRangeElement.textContent;
                    notesElement.textContent = `Note: Total for ${branchName} (${dateRangeText})`;
                } else {
                    notesContainer.style.display = 'none';
                }
            } else {
                console.log('No targets found or API error:', result);
                document.getElementById('todayTargetAmount').textContent = '₱0';
                document.getElementById('todayActualSales').textContent = '₱0';
                document.getElementById('todayTargetPercent').textContent = '0%';
                document.getElementById('todayTargetProgress').style.width = '0%';
                document.getElementById('todayTargetProgress').className = 'progress-bar bg-secondary';
                document.getElementById('todayTargetStatus').className = 'badge bg-secondary';
                document.getElementById('todayTargetStatus').textContent = 'No Target Set';
                document.getElementById('todayTargetNotesContainer').style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching sales target:', error);
        }
    }

    async function fetchProviderWallets(branchId = null) {
        try {
            let url = `${window.BASE_URL}/api/analytics/wallet-summary.php`;
            if (branchId) {
                url += `?branch_id=${branchId}`;
            }
            const res    = await fetch(url);
            const result = await res.json();

            if (!result.success) {
                document.getElementById('walletError').style.display   = 'block';
                document.getElementById('walletErrorMsg').textContent  = result.error || 'Failed to load';
                return;
            }

            const d = result.data;

            // Footer totals
            document.getElementById('walletTotalBalance').textContent = formatPHP(d.total_balance);
            document.getElementById('walletCountBadge').textContent   = d.wallet_count + ' wallet' + (d.wallet_count !== 1 ? 's' : '');

            const lowBadge = document.getElementById('walletLowBalanceBadge');
            if (d.low_balance_count > 0) {
                document.getElementById('walletLowCount').textContent = d.low_balance_count;
                lowBadge.style.display = 'inline';
            } else {
                lowBadge.style.display = 'none';
            }

            // Build stacked bar chart data
            // X-axis = branches (max 6), series = providers
            const branchSet   = [];
            const providerSet = [];

            (d.branches || []).forEach(b => {
                if (b.branch_name   && !branchSet.includes(b.branch_name))   branchSet.push(b.branch_name);
                if (b.provider_name && !providerSet.includes(b.provider_name)) providerSet.push(b.provider_name);
            });

            // Limit to 6 branches (columns) as per Traffic Source template
            if (branchSet.length > 6) {
                branchSet.length = 6;
            }

            // If no branch data, fall back to provider-only chart
            if (branchSet.length === 0 && d.providers && d.providers.length > 0) {
                renderWalletProviderChart(d.providers);
                return;
            }

            // If no data at all, show empty state
            if (branchSet.length === 0 && (!d.providers || d.providers.length === 0)) {
                const legendEl = document.getElementById('walletLegend');
                legendEl.innerHTML = `
                    <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4">
                        <span class="fas fa-wallet text-muted fs-2 mb-2"></span>
                        <p class="text-muted fs-11 mb-0">No wallet data available</p>
                        <p class="text-500 fs-10 mb-0">No provider wallets configured</p>
                    </div>
                `;
                const chartDom = document.getElementById('walletChart');
                if (walletBarChart) {
                    walletBarChart.clear();
                    walletBarChart.setOption({
                        grid: { left: 0, right: 0, top: 0, bottom: 0 },
                        xAxis: { show: false },
                        yAxis: { show: false },
                        series: []
                    });
                }
                return;
            }

            // Map: providerName -> { branchName: balance }
            const dataMap = {};
            providerSet.forEach(p => { dataMap[p] = {}; });
            (d.branches || []).forEach(b => {
                if (dataMap[b.provider_name]) {
                    dataMap[b.provider_name][b.branch_name] = b.balance;
                }
            });

            // Build series
            const series = providerSet.map((pName, idx) => ({
                name: pName,
                type: 'bar',
                stack: 'wallet',
                barMaxWidth: 40,
                emphasis: { focus: 'series' },
                itemStyle: { color: WALLET_COLORS[idx % WALLET_COLORS.length] },
                data: branchSet.map(bName => dataMap[pName][bName] || 0)
            }));

            renderWalletBarChart(branchSet, series, providerSet);

        } catch (e) {
            document.getElementById('walletError').style.display   = 'block';
            document.getElementById('walletErrorMsg').textContent  = 'Connection error';
            console.error('Wallet widget error:', e);
        }
    }

    function renderWalletBarChart(branches, series, providerNames) {
        const chartDom = document.getElementById('walletChart');
        if (!chartDom) return;

        const isDark       = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const gridColor    = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const labelColor   = isDark ? '#9da9bb' : '#748194';
        const tooltipBg    = isDark ? '#0b1727' : '#fff';
        const tooltipBorder= isDark ? '#344050' : '#d8e2ef';

        // Build legend HTML
        const legendEl = document.getElementById('walletLegend');
        legendEl.innerHTML = providerNames.map((n, i) =>
            `<span class="d-flex align-items-center gap-1">
               <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${WALLET_COLORS[i % WALLET_COLORS.length]}"></span>
               ${n}
             </span>`
        ).join('');

        if (!walletBarChart) {
            walletBarChart = echarts.init(chartDom, null, { renderer: 'canvas' });
            window.addEventListener('resize', () => { if (walletBarChart) walletBarChart.resize(); });
        }

        walletBarChart.setOption({
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: tooltipBg,
                borderColor: tooltipBorder,
                borderWidth: 1,
                padding: [8, 12],
                textStyle: { color: isDark ? '#d8e2ef' : '#344050', fontSize: 11 },
                formatter: function(params) {
                    let html = `<div class="fw-semibold mb-1 fs-11">${params[0].axisValue}</div>`;
                    let total = 0;
                    params.forEach(p => {
                        if (p.value > 0) {
                            total += p.value;
                            html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0">`
                                  + `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color}"></span>`
                                  + `<span style="flex:1;font-size:11px">${p.seriesName}</span>`
                                  + `<span style="font-weight:600;font-size:11px">₱${parseFloat(p.value).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>`
                                  + `</div>`;
                        }
                    });
                    html += `<div style="border-top:1px solid ${tooltipBorder};margin-top:4px;padding-top:4px;font-weight:700;font-size:11px">Total: ₱${total.toLocaleString('en-PH',{minimumFractionDigits:2})}</div>`;
                    return html;
                }
            },
            legend: { show: false },
            grid: { left: 0, right: 10, top: 4, bottom: 0, containLabel: true },
            xAxis: {
                type: 'category',
                data: branches,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: labelColor,
                    fontSize: 11,
                    interval: 0,
                    formatter: v => v.length > 8 ? v.substring(0, 7) + '…' : v
                },
                splitLine: { show: false }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: gridColor, type: 'dashed' } },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    color: labelColor,
                    fontSize: 10,
                    formatter: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M'
                                  : v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                }
            },
            series: series
        }, true);
    }

    function renderWalletProviderChart(providers) {
        // Fallback: single-series horizontal bar when no branch data
        const names    = providers.map(p => p.provider_name || '—');
        const balances = providers.map(p => p.total_balance);
        const isDark   = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const labelColor = isDark ? '#9da9bb' : '#748194';

        const legendEl = document.getElementById('walletLegend');
        legendEl.innerHTML = '';

        const chartDom = document.getElementById('walletChart');
        if (!chartDom) return;

        if (!walletBarChart) {
            walletBarChart = echarts.init(chartDom, null, { renderer: 'canvas' });
            window.addEventListener('resize', () => { if (walletBarChart) walletBarChart.resize(); });
        }

        walletBarChart.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: p => `${p[0].name}<br/><b>₱${parseFloat(p[0].value).toLocaleString('en-PH',{minimumFractionDigits:2})}</b>`
            },
            grid: { left: 0, right: 20, top: 4, bottom: 0, containLabel: true },
            xAxis: { type: 'value', show: false },
            yAxis: {
                type: 'category', data: names,
                axisLabel: { color: labelColor, fontSize: 11 },
                axisLine: { show: false }, axisTick: { show: false }
            },
            series: [{
                type: 'bar', data: balances, barMaxWidth: 28,
                itemStyle: {
                    color: params => WALLET_COLORS[params.dataIndex % WALLET_COLORS.length],
                    borderRadius: [0, 3, 3, 0]
                },
                label: {
                    show: true, position: 'right', color: labelColor, fontSize: 10,
                    formatter: p => '₱' + (parseFloat(p.value)/1000).toFixed(1) + 'k'
                }
            }]
        }, true);
    }

    // ─── Accounts Receivable Widget ──────────────────────────────────────────────

    function formatPHP(val) {
        return '₱' + parseFloat(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function fetchAccountsReceivable() {
        try {
            const res    = await fetch(`${window.BASE_URL}/api/analytics/receivables`);
            const result = await res.json();

            document.getElementById('arLoading').style.display = 'none';

            if (!result.success) {
                document.getElementById('arError').style.display  = 'block';
                document.getElementById('arErrorMsg').textContent = result.error || 'Failed to load';
                return;
            }

            const s = result.data.summary;

            // Hero
            document.getElementById('arTotalOutstanding').textContent = formatPHP(s.total_outstanding);
            document.getElementById('arCustomersBadge').textContent   = s.total_customers + ' customer' + (s.total_customers !== 1 ? 's' : '');

            // 7-day activity
            document.getElementById('arCharged7d').textContent   = formatPHP(s.charged_7d);
            document.getElementById('arCollected7d').textContent = formatPHP(s.collected_7d);

            // Collection rate progress bar
            const charged   = parseFloat(s.charged_7d)   || 0;
            const collected = parseFloat(s.collected_7d) || 0;
            const rate = charged > 0 ? Math.min(100, (collected / charged) * 100) : (collected > 0 ? 100 : 0);
            document.getElementById('arCollectionBar').style.width  = rate.toFixed(1) + '%';
            document.getElementById('arCollectionRate').textContent = rate.toFixed(1) + '%';

            document.getElementById('arContent').style.display = 'block';

        } catch (e) {
            document.getElementById('arLoading').style.display = 'none';
            document.getElementById('arError').style.display   = 'block';
            document.getElementById('arErrorMsg').textContent  = 'Connection error';
            console.error('AR widget error:', e);
        }
    }

    // ─── Date Range Picker for Analytics ─────────────────────────────────────────────
    
    // Date range button handling
    const dateRangeButtons = document.getElementById('dateRangeButtons');
    const customDateRangeContainer = document.getElementById('customDateRangeContainer');
    const customRangeType = document.getElementById('customRangeType');
    let currentRange = 'today';
    let flatpickrInstances = {};

    if (dateRangeButtons) {
        const buttons = dateRangeButtons.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                buttons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const range = this.getAttribute('data-range');
                currentRange = range;
                
                // Show/hide custom date picker
                if (range === 'custom') {
                    customDateRangeContainer.classList.remove('d-none');
                    if (!flatpickrInstances.daily) {
                        initCustomDatePickers();
                    }
                } else {
                    customDateRangeContainer.classList.add('d-none');
                    // Trigger analytics refresh with selected range
                    refreshAnalytics(range);
                }
            });
        });
    }

    // Global branch selector event listener
    const globalBranchSelector = document.getElementById('globalBranchSelector');
    if (globalBranchSelector) {
        globalBranchSelector.addEventListener('change', function() {
            const branchId = this.value;
            localStorage.setItem('analyticsBranchId', branchId);
            
            // Sync with Branch Analytics selector
            const branchSelector = document.getElementById('branchSelector');
            if (branchSelector) {
                branchSelector.value = branchId;
            }
            
            // Sync with Cashier Performance branch filter
            const cashierBranchFilter = document.getElementById('cashierPerformanceBranchFilter');
            if (cashierBranchFilter) {
                cashierBranchFilter.value = branchId;
            }
            
            // Sync with Wallet Balances branch filter
            const walletBranchFilter = document.getElementById('walletBranchSelector');
            if (walletBranchFilter) {
                walletBranchFilter.value = branchId;
                const walletNameEl = document.getElementById('walletBranchName');
                if (walletNameEl) {
                    walletNameEl.textContent = branchId ? 
                        (walletBranchFilter.options[walletBranchFilter.selectedIndex]?.text || 'All Branches') : 'All Branches';
                }
            }

            // Sync with Transactions per Hour branch filter
            const hourlyBranchFilter = document.getElementById('hourlyBranchFilter');
            if (hourlyBranchFilter) hourlyBranchFilter.value = branchId;
            
            // Sync with Payment Breakdown branch filter
            const paymentBreakdownBranchFilter = document.getElementById('paymentBreakdownBranchFilter');
            if (paymentBreakdownBranchFilter) paymentBreakdownBranchFilter.value = branchId;
            
            // Sync with Live Sales branch filter
            const liveSalesBranchFilter = document.getElementById('liveSalesBranchFilter');
            if (liveSalesBranchFilter) liveSalesBranchFilter.value = branchId;
            
            // Sync with Top Services branch filter
            const topServicesBranchFilter = document.getElementById('topServicesBranchFilter');
            if (topServicesBranchFilter) topServicesBranchFilter.value = branchId;
            
            // Refresh all connected cards
            fetchBranchAnalytics();
            fetchTMSMetrics(currentRange || 'month', branchId || null);
            fetchCashierPerformance(rangeToDays(currentRange || 'week'), branchId).then(data => {
                renderCashierPerformanceChart(data);
            });
            fetchProviderWallets(branchId || null);
            fetchTodaySalesTarget(branchId || null);
            // Branch-aware cards
            try { fetchTransactionsPerHour(); } catch (e) { console.warn(e); }
            try { fetchPaymentBreakdown();   } catch (e) { console.warn(e); }
            try { fetchTopServices();        } catch (e) { console.warn(e); }
        });
    }

    // Handle custom range type selection
    if (customRangeType) {
        customRangeType.addEventListener('change', function() {
            const rangeType = this.value;
            
            // Hide all range inputs
            document.getElementById('dailyRangeInput').classList.add('d-none');
            document.getElementById('monthlyRangeInput').classList.add('d-none');
            document.getElementById('annualRangeInput').classList.add('d-none');
            
            // Show selected range input
            document.getElementById(rangeType + 'RangeInput').classList.remove('d-none');
        });
    }

    // Initialize all custom date pickers
    function initCustomDatePickers() {
        if (typeof flatpickr === 'undefined') return;

        // Daily range picker (Jan 1 - Jan 5)
        const dailyPicker = document.getElementById('AnalyticsDateRange');
        if (dailyPicker) {
            flatpickrInstances.daily = flatpickr(dailyPicker, {
                mode: 'range',
                dateFormat: 'M d',
                disableMobile: true,
                defaultDate: [
                    new Date(Date.now() - 4 * 24 * 60 * 60 * 1000),
                    new Date()
                ],
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = formatDate(selectedDates[0]);
                        const endDate = formatDate(selectedDates[1]);
                        console.log('Daily range changed:', startDate, 'to', endDate);
                        refreshAnalytics('custom-daily', startDate, endDate);
                    }
                }
            });
        }

        // Monthly range picker (Jan - Mar)
        const monthlyPicker = document.getElementById('MonthlyRange');
        if (monthlyPicker) {
            flatpickrInstances.monthly = flatpickr(monthlyPicker, {
                mode: 'range',
                dateFormat: 'M',
                disableMobile: true,
                defaultDate: [
                    new Date(new Date().getFullYear(), 0, 1),
                    new Date(new Date().getFullYear(), 2, 1)
                ],
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = formatDate(selectedDates[0]);
                        const endDate = formatDate(selectedDates[1]);
                        console.log('Monthly range changed:', startDate, 'to', endDate);
                        refreshAnalytics('custom-monthly', startDate, endDate);
                    }
                }
            });
        }

        // Annual range picker (2021 - 2026)
        const annualPicker = document.getElementById('AnnualRange');
        if (annualPicker) {
            flatpickrInstances.annual = flatpickr(annualPicker, {
                mode: 'range',
                dateFormat: 'Y',
                disableMobile: true,
                defaultDate: [
                    new Date(new Date().getFullYear() - 5, 0, 1),
                    new Date()
                ],
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = formatDate(selectedDates[0]);
                        const endDate = formatDate(selectedDates[1]);
                        console.log('Annual range changed:', startDate, 'to', endDate);
                        refreshAnalytics('custom-annual', startDate, endDate);
                    }
                }
            });
        }
    }

    // Refresh analytics based on selected range
    function refreshAnalytics(range, startDate = null, endDate = null) {
        console.log('Refreshing analytics for range:', range);
        
        // Save selected range to localStorage
        localStorage.setItem('analyticsDateRange', range);
        if (startDate && endDate) {
            localStorage.setItem('analyticsCustomStartDate', startDate);
            localStorage.setItem('analyticsCustomEndDate', endDate);
        }
        
        // Sync branch analytics range selector (if not custom)
        const branchRangeSelector = document.getElementById('branchAnalyticsRange');
        if (branchRangeSelector && ['today', 'week', 'last30days', 'year'].includes(range)) {
            branchRangeSelector.value = range;
        }
        
        // Sync Top Services range selector (if not custom)
        const topServicesFilter = document.getElementById('topServicesFilter');
        if (topServicesFilter && ['today', 'week', 'last30days', 'year'].includes(range)) {
            topServicesFilter.value = range;
        }
        
        // Refresh Branch Analytics with new range
        fetchBranchAnalytics();
        
        // Refresh Total Orders / Revenue / Avg Order Value (Goals card)
        fetchTMSMetrics(range);
        
        // Refresh Cashier Performance with new range
        if (['today', 'week', 'last30days', 'year'].includes(range)) {
            const days = rangeToDays(range);
            const branchId = document.getElementById('globalBranchSelector')?.value || null;
            // Sync Cashier Performance dropdown UI
            const cashierFilter = document.getElementById('cashierPerformanceFilter');
            if (cashierFilter) {
                cashierFilter.value = days;
            }
            fetchCashierPerformance(days, branchId).then(data => {
                renderCashierPerformanceChart(data);
            });
        }
        
        // Refresh Provider Wallet Balances with new branch
        const globalBranchId = document.getElementById('globalBranchSelector')?.value || '';
        fetchProviderWallets(globalBranchId);
        
        // Refresh Today's Sales Target
        fetchTodaySalesTarget(globalBranchId || null);

        // Refresh Transactions per Hour, Payment Breakdown, Top Services
        try { fetchTransactionsPerHour(); } catch (e) { console.warn(e); }
        try { fetchPaymentBreakdown();   } catch (e) { console.warn(e); }
        try { fetchTopServices();        } catch (e) { console.warn(e); }

        // Log the action
        switch(range) {
            case 'today':
                console.log('Loading today\'s data');
                break;
            case 'week':
                console.log('Loading this week\'s data');
                break;
            case 'month':
                console.log('Loading this month\'s data');
                break;
            case 'year':
                console.log('Loading this year\'s data');
                break;
            case 'custom-daily':
                console.log('Loading custom daily range:', startDate, 'to', endDate);
                break;
            case 'custom-weekly':
                console.log('Loading custom weekly range:', startDate);
                break;
            case 'custom-monthly':
                console.log('Loading custom monthly range:', startDate, 'to', endDate);
                break;
            case 'custom-annual':
                console.log('Loading custom annual range:', startDate, 'to', endDate);
                break;
        }
    }

    // Restore saved range on page load
    function restoreDateRange() {
        const savedRange = localStorage.getItem('analyticsDateRange') || 'today';
        const savedBranch = localStorage.getItem('analyticsBranchId') || '';
        const button = dateRangeButtons?.querySelector(`[data-range="${savedRange}"]`);
        
        if (button) {
            // Just update UI classes without triggering click event
            const buttons = dateRangeButtons.querySelectorAll('button');
            buttons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentRange = savedRange;
            
            // Show/hide custom date picker based on saved range
            if (savedRange === 'custom') {
                customDateRangeContainer.classList.remove('d-none');
                if (!flatpickrInstances.daily) {
                    initCustomDatePickers();
                }
                // Restore custom range type
                const savedRangeType = localStorage.getItem('analyticsCustomRangeType') || 'daily';
                if (customRangeType) {
                    customRangeType.value = savedRangeType;
                    // Show the appropriate input
                    document.getElementById('dailyRangeInput').classList.add('d-none');
                    document.getElementById('monthlyRangeInput').classList.add('d-none');
                    document.getElementById('annualRangeInput').classList.add('d-none');
                    document.getElementById(savedRangeType + 'RangeInput').classList.remove('d-none');
                }
            } else {
                customDateRangeContainer.classList.add('d-none');
            }
        }
        
        // Restore global branch selector
        const globalBranchSelector = document.getElementById('globalBranchSelector');
        if (globalBranchSelector && savedBranch) {
            globalBranchSelector.value = savedBranch;
        }
        
        // Sync Branch Analytics range selector (if not custom)
        const branchRangeSelector = document.getElementById('branchAnalyticsRange');
        if (branchRangeSelector && ['today', 'week', 'last30days', 'year'].includes(savedRange)) {
            branchRangeSelector.value = savedRange;
        }
        
        // Sync Cashier Performance filters
        const cashierFilter = document.getElementById('cashierPerformanceFilter');
        const cashierBranchFilter = document.getElementById('cashierPerformanceBranchFilter');
        if (cashierFilter && ['today', 'week', 'last30days', 'year'].includes(savedRange)) {
            cashierFilter.value = rangeToDays(savedRange);
        }
        if (cashierBranchFilter) {
            cashierBranchFilter.value = savedBranch;
        }
        
        // Sync Wallet Balances branch filter
        const walletBranchFilter = document.getElementById('walletBranchSelector');
        const walletBranchName = document.getElementById('walletBranchName');
        if (walletBranchFilter) {
            walletBranchFilter.value = savedBranch;
            if (walletBranchName) {
                walletBranchName.textContent = savedBranch ? 
                    walletBranchFilter.options[walletBranchFilter.selectedIndex]?.text || 'All Branches' : 'All Branches';
            }
        }

        // Sync Transactions per Hour / Payment Breakdown / Top Services local selectors
        const effectiveRange = (savedRange && !savedRange.startsWith('custom'))
            ? savedRange
            : ({
                'custom-daily': 'week',
                'custom-weekly': 'last30days',
                'custom-monthly': 'last30days',
                'custom-annual': 'year'
            }[savedRange] || 'last30days');
        const hourlyFilter = document.getElementById('hourlyFilter');
        const hourlyBranchFilter = document.getElementById('hourlyBranchFilter');
        const pbFilter = document.getElementById('paymentBreakdownFilter');
        const pbBranchFilter = document.getElementById('paymentBreakdownBranchFilter');
        const tsFilter = document.getElementById('topServicesFilter');
        const tsBranchFilter = document.getElementById('topServicesBranchFilter');
        const lsBranchFilter = document.getElementById('liveSalesBranchFilter');
        if (hourlyFilter && ['today','week','last30days','year'].includes(effectiveRange)) hourlyFilter.value = effectiveRange;
        if (hourlyBranchFilter) hourlyBranchFilter.value = savedBranch;
        if (pbFilter && ['today','week','last30days','year'].includes(effectiveRange)) pbFilter.value = effectiveRange;
        if (pbBranchFilter) pbBranchFilter.value = savedBranch;
        if (tsFilter && ['today','week','last30days','year'].includes(effectiveRange)) tsFilter.value = effectiveRange;
        if (tsBranchFilter) tsBranchFilter.value = savedBranch;
        if (lsBranchFilter) lsBranchFilter.value = savedBranch;
        
        // Refresh all connected cards with restored filter values
        // This ensures charts load with correct saved filters instead of defaults
        setTimeout(() => {
            console.log('Refreshing charts with restored filters:', savedRange, savedBranch);
            
            // Refresh Total Orders / Revenue / Avg Order Value
            fetchTMSMetrics(savedRange, savedBranch || null);
            
            // Refresh Cashier Performance
            if (['today', 'week', 'month', 'year'].includes(savedRange)) {
                const days = rangeToDays(savedRange);
                fetchCashierPerformance(days, savedBranch).then(data => {
                    renderCashierPerformanceChart(data);
                });
            }
            
            // Refresh Provider Wallet Balances
            fetchProviderWallets(savedBranch || null);

            // Refresh Transactions per Hour, Payment Breakdown, Top Services
            try { fetchTransactionsPerHour(); } catch (e) { console.warn(e); }
            try { fetchPaymentBreakdown();   } catch (e) { console.warn(e); }
            try { fetchTopServices();        } catch (e) { console.warn(e); }
        }, 50);
    }

    // Save custom range type when changed
    if (customRangeType) {
        customRangeType.addEventListener('change', function() {
            localStorage.setItem('analyticsCustomRangeType', this.value);
        });
    }

    // Call restore after a short delay to ensure DOM is ready
    setTimeout(restoreDateRange, 100);

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    </script>
  <?php include __DIR__ . '/../includes/body-top.php'; ?>
  </body>

</html>
</html>
