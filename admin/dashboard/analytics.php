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
        </script>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="content">
         <?php include __DIR__ . '/../includes/navbar.php'; ?>
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
                      <a class="nav-link mb-0" id="transactions-tab" data-bs-toggle="tab" href="#transactions" role="tab" aria-controls="transactions" aria-selected="false">
                        <div class="audience-tab-item p-2 pe-4">
                          <h6 class="text-800 fs-11 text-nowrap">Transactions</h6>
                          <h5 class="text-800" id="tabTransactions">0</h5>
                          <div class="d-flex align-items-center">
                            <span class="fas fa-caret-up text-success" id="tabTxnTrendIcon"></span>
                            <h6 class="fs-11 mb-0 ms-2 text-success" id="tabTxnTrend">0%</h6>
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
                      <div id="branchSalesChart" data-echart-responsive="true" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="net" role="tabpanel" aria-labelledby="net-tab">
                      <div id="branchNetChart" data-echart-responsive="true" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="refunds" role="tabpanel" aria-labelledby="refunds-tab">
                      <div id="branchRefundsChart" data-echart-responsive="true" style="height:320px;"></div>
                    </div>
                    <div class="tab-pane" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                      <div id="branchTransactionsChart" data-echart-responsive="true" style="height:320px;"></div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm" id="branchAnalyticsRange">
                        <option value="today">Today</option>
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last 30 days</option>
                        <option value="year">Last 365 days</option>
                      </select>
                    </div>
                    <div class="col-auto">
                      <a class="btn btn-link btn-sm px-0 fw-medium" href="<?php echo BASE_URL; ?>/admin/pos/">
                        <span class="fas fa-external-link-alt me-1"></span>View POS
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Accounts Receivable Widget -->
              <div class="card position-relative overflow-hidden" id="arWidget">
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
            <div class="col-md-6 col-xxl-4">
              <div class="card h-100 bg-line-chart-gradient">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-start pt-3" data-bs-theme="light">
                  <div>
                    <h5 class="text-white fw-bold mb-0">Live Sales</h5>
                  </div>
                  <select class="form-select form-select-sm" id="liveSalesFilter" style="width:auto;min-width:90px;background-color:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:#fff;">
                    <option value="today"   style="background:#1a68c0;color:#fff;">Today</option>
                    <option value="1"       style="background:#1a68c0;color:#fff;">Last Hour</option>
                    <option value="6"       style="background:#1a68c0;color:#fff;">Last 6 Hours</option>
                    <option value="24"      style="background:#1a68c0;color:#fff;">Last 24 Hours</option>
                  </select>
                </div>
                <div class="card-body pb-0" data-bs-theme="light">
                  <div class="display-4 fw-bold text-white mb-0" id="liveSalesTotal">₱0.00</div>
                  <p class="text-white opacity-75 fs-10 mb-3">Sales / <span id="liveFilterLabel">today</span></p>
                  <!-- Bar chart -->
                  <div id="liveSalesChart" style="height:130px;"></div>
                  <!-- Transactions subtitle -->
                  <p class="text-white opacity-75 fs-10 mt-2 mb-2" style="border-top:1px solid rgba(255,255,255,0.15);padding-top:8px;">
                    Recent Transactions &nbsp;<span class="badge" style="background:rgba(255,255,255,0.2);font-weight:500;" id="liveTransactionCount">0</span>
                  </p>
                  <!-- Transactions list -->
                  <div id="liveTransactionsList">
                    <div class="text-white opacity-50 fs-11 py-1">Loading...</div>
                  </div>
                </div>
                <div class="card-footer bg-transparent text-end pt-0" data-bs-theme="light">
                  <a class="text-white fs-10" href="<?php echo BASE_URL; ?>/admin/pos/">View POS <span class="fa fa-chevron-right ms-1"></span></a>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xxl-4">
              <div class="card echart-session-by-browser-card h-100">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Session By Browser</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-session-by-browser" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-session-by-browser"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-between py-0">
                  <div class="my-auto py-5 py-md-0">
                    <!-- Find the JS file for the following chart at: src/js/charts/echarts/session-by-browser.js-->
                    <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                    <div class="echart-session-by-browser h-100" data-echart-responsive="true"></div>
                  </div>
                  <div class="border-top">
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/icons/chrome-logo.png" alt="" width="16" />
                              <h6 class="text-600 mb-0 ms-2">Chrome</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><span class="fas fa-circle fs-11 me-2 text-primary"></span>
                              <h6 class="fw-normal text-700 mb-0">50.3%</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center justify-content-end"><span class="fas fa-caret-down text-danger"></span>
                              <h6 class="fs-11 mb-0 ms-2 text-700">2.9%</h6>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/icons/safari-logo.png" alt="" width="16" />
                              <h6 class="text-600 mb-0 ms-2">Safari</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><span class="fas fa-circle fs-11 me-2 text-success"></span>
                              <h6 class="fw-normal text-700 mb-0">30.1%</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center justify-content-end"><span class="fas fa-caret-up text-success"></span>
                              <h6 class="fs-11 mb-0 ms-2 text-700">29.4%</h6>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/icons/firefox-logo.png" alt="" width="16" />
                              <h6 class="text-600 mb-0 ms-2">Mozilla</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center"><span class="fas fa-circle fs-11 me-2 text-info"></span>
                              <h6 class="fw-normal text-700 mb-0">20.6%</h6>
                            </div>
                          </td>
                          <td class="py-3">
                            <div class="d-flex align-items-center justify-content-end"><span class="fas fa-caret-up text-success"></span>
                              <h6 class="fs-11 mb-0 ms-2 text-700">220.7%</h6>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm" data-target=".echart-session-by-browser">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Browser overview<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xxl-4">
              <div class="card">
                <div class="card-header d-flex align-items-center bg-body-tertiary py-2">
                  <h6 class="mb-0 flex-1">Users By Country</h6>
                  <div class="btn-reveal-trigger">
                    <button class="btn btn-link btn-reveal btn-sm session-by-country-map-reset" type="button"><span class="fas fa-sync-alt fs-10"></span></button>
                  </div>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-session-by-country" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-session-by-country"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/session-by-country-map.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-session-by-country-map w-100 h-100" data-echart-responsive="true"></div>
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/session-by-country.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-session-by-country h-100" data-echart-responsive="true"></div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm audience-select-menu">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Country overview<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xxl-4">
              <div class="card h-100">
                <div class="card-header">
                  <div class="d-flex align-items-center"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/icons/signal.png" alt="" height="35" />
                    <h5 class="fs-9 fw-normal text-800 mb-0">Ask Falcon Intelligence</h5>
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="scrollbar-overlay pt-0 px-x1 ask-analytics">
                    <div class="border border-1 border-300 rounded-2 p-3 ask-analytics-item position-relative mb-3">
                      <div class="d-flex align-items-center mb-3"><span class="fas fa-code-branch text-primary"></span><a class="stretched-link text-decoration-none" href="#!">
                          <h5 class="fs-10 text-600 mb-0 ps-3">Content Analysis</h5>
                        </a></div>
                      <h5 class="fs-10 text-800">Which landing pages with over 10 sessions have the worst bounce rates?</h5>
                    </div>
                    <div class="border border-1 border-300 rounded-2 p-3 ask-analytics-item position-relative mb-3">
                      <div class="d-flex align-items-center mb-3"><span class="fas fa-bug text-primary"></span><a class="stretched-link text-decoration-none" href="#!">
                          <h5 class="fs-10 text-600 mb-0 ps-3">Technical performance</h5>
                        </a></div>
                      <h5 class="fs-10 text-800">Show me a trend of my average page load time over the last 3 months</h5>
                    </div>
                    <div class="border border-1 border-300 rounded-2 p-3 ask-analytics-item position-relative mb-3">
                      <div class="d-flex align-items-center mb-3"><span class="fas fa-project-diagram text-primary"></span><a class="stretched-link text-decoration-none" href="#!">
                          <h5 class="fs-10 text-600 mb-0 ps-3">Technical performance</h5>
                        </a></div>
                      <h5 class="fs-10 text-800">What are my top default channel groupings by user?</h5>
                    </div>
                    <div class="border border-1 border-300 rounded-2 p-3 ask-analytics-item position-relative mb-3">
                      <div class="d-flex align-items-center mb-3"><span class="fas fa-map-marker-alt text-primary"></span><a class="stretched-link text-decoration-none" href="#!">
                          <h5 class="fs-10 text-600 mb-0 ps-3">Geographic Analysis</h5>
                        </a></div>
                      <h5 class="fs-10 text-800">What pages do people from California go to the most?</h5>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary text-end py-2"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">More Insights<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-7 d-flex flex-column align-items-stretch">
              <div class="card mb-3 h-100">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Active Users</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-active-user-report" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-active-user-report"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-auto mt-md-0">
                      <div class="row flex-md-column justify-content-between h-md-100 ms-0">
                        <div class="col border-end border-end-md-0 border-bottom-md pt-3">
                          <h6 class="fs-11 text-700"><span class="fas fa-circle text-primary me-2"></span>Mobile</h6>
                          <h5 class="text-700 fs-9">10,325</h5>
                        </div>
                        <div class="col border-end border-end-md-0 border-bottom-md pt-3 pt-md-4">
                          <h6 class="fs-11 text-700"><span class="fas fa-circle text-success me-2"></span>Desktop</h6>
                          <h5 class="text-700 fs-9">4,235</h5>
                        </div>
                        <div class="col pt-3 pt-md-4">
                          <h6 class="fs-11 text-700"><span class="fas fa-circle text-info me-2"></span>Tablet</h6>
                          <h5 class="text-700 fs-9">3,575</h5>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-auto echart-active-users-report-container">
                      <!-- Find the JS file for the following chart at: src/js/charts/echarts/active-users-report.js-->
                      <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                      <div class="echart-active-users-report h-100" data-echart-responsive="true"></div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm audience-select-menu">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto">
                      <h6 class="mb-0"><a class="py-2" href="#!">Active users report<span class="fas fa-chevron-right ms-1 fs-11"></span></a></h6>
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
                          <h6 class="text-700">Completed Goals</h6>
                          <h3 class="fw-normal text-700">1727</h3>
                        </div>
                        <div id="goalChart1" style="height:50px"></div>
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div class="border-end-sm border-300">
                        <div class="text-center">
                          <h6 class="text-700">Value</h6>
                          <h3 class="fw-normal text-700">$34.2M</h3>
                        </div>
                        <div id="goalChart2" style="height:50px"></div>
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div>
                        <div class="text-center">
                          <h6 class="text-700">Conversion Rate</h6>
                          <h3 class="fw-normal text-700">19.67%</h3>
                        </div>
                        <div id="goalChart3" style="height:50px"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="card h-100">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Ad campaigns perfomance</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-campaign-perfomance" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-campaign-perfomance"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body pb-0">
                  <div class="row">
                    <div class="col-6">
                      <div>
                        <h6 class="text-700">Revenue</h6>
                        <h3 class="fw-normal text-700">$10.87k</h3>
                      </div>
                      <div id="campaignRevenueChart" style="height:50px"></div>
                    </div>
                    <div class="col-6">
                      <div>
                        <h6 class="text-700">Clicks</h6>
                        <h3 class="fw-normal text-700">3.8k</h3>
                      </div>
                      <div id="campaignClicksChart" style="height:50px"></div>
                    </div>
                  </div>
                  <div class="mx-nx1">
                    <div class="table-responsive scrollbar">
                      <table class="table fs-10 mb-0 overflow-hidden">
                        <thead class="bg-100">
                          <tr>
                            <th class="text-800 text-nowrap">Top Campaigns</th>
                            <th class="text-800 text-nowrap text-end">Cost</th>
                            <th class="text-800 text-nowrap text-end">Revenue from Ads</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="text-truncate">Black Friday Sale</td>
                            <td class="text-truncate text-end">$1304.28</td>
                            <td class="text-truncate text-end">$543217.65</td>
                          </tr>
                          <tr>
                            <td class="text-truncate">Christmas Bundle</td>
                            <td class="text-truncate text-end">$9876.56</td>
                            <td class="text-truncate text-end">$3904</td>
                          </tr>
                          <tr>
                            <td class="text-truncate">Halloween Party Started 🎃 👻</td>
                            <td class="text-truncate text-end">$3267.84</td>
                            <td class="text-truncate text-end">$7654.8</td>
                          </tr>
                          <tr>
                            <td class="text-truncate">Grab your reward</td>
                            <td class="text-truncate text-end">$87545.28</td>
                            <td class="text-truncate text-end">$68654.35</td>
                          </tr>
                          <tr>
                            <td class="text-truncate">Black Friday Sale</td>
                            <td class="text-truncate text-end">$1304.28</td>
                            <td class="text-truncate text-end">$3904</td>
                          </tr>
                          <tr>
                            <td class="text-truncate">Boxing Day offer</td>
                            <td class="text-truncate text-end">$1200.5</td>
                            <td class="text-truncate text-end">$5004.87</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center">
                    <div class="col-auto">
                      <select class="form-select form-select-sm audience-select-menu">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Ad campaigns<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-5 col-xxl-4">
              <div class="card">
                <div class="card-header bg-body-tertiary py-3">
                  <h6 class="mb-0">Users at a Time</h6>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/users-by-time.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-users-by-time h-100" data-echart-responsive="true"></div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center">
                    <div class="col-auto">
                      <select class="form-select form-select-sm audience-select-menu">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Overview<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-7 col-xxl-8">
              <div class="card h-100" id="table" data-list='{"valueNames":["path","views","time","exitRate"],"page":8,"pagination":true,"fallback":"pages-table-fallback"}'>
                <div class="card-header">
                  <div class="row flex-between-center">
                    <div class="col-auto col-sm-6 col-lg-7">
                      <h6 class="mb-0 text-nowrap py-2 py-xl-0">What are my top pages today?</h6>
                    </div>
                    <div class="col-auto col-sm-6 col-lg-5">
                      <div class="h-100">
                        <form>
                          <div class="input-group">
                            <input class="form-control form-control-sm shadow-none search" type="search" placeholder="Search for a page" aria-label="search" />
                            <button class="btn btn-sm btn-outline-secondary border-300 hover-border-secondary"><span class="fa fa-search fs-10"></span></button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-body px-0 py-0">
                  <div class="table-responsive scrollbar">
                    <table class="table fs-10 mb-0 overflow-hidden">
                      <thead class="bg-200">
                        <tr>
                          <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="path">Page Path</th>
                          <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end" data-sort="views">Page Views</th>
                          <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end" data-sort="time">Avg Time on Page</th>
                          <th class="text-900 sort pe-x1 align-middle white-space-nowrap text-end" data-sort="exitRate">Exit Rate</th>
                        </tr>
                      </thead>
                      <tbody class="list">
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/sparrow/landing-page</a></td>
                          <td class="align-middle white-space-nowrap views text-end">1455</td>
                          <td class="align-middle white-space-nowrap time text-end">2m:25s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">20.4%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/falcon/pages/starter.html</a></td>
                          <td class="align-middle white-space-nowrap views text-end">1422</td>
                          <td class="align-middle white-space-nowrap time text-end">2m:14s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">52.4%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/pages/falcon-webapp-theme</a></td>
                          <td class="align-middle white-space-nowrap views text-end">1378</td>
                          <td class="align-middle white-space-nowrap time text-end">2m:23s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">25.1%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/product/sparrow-bootstrap-theme</a></td>
                          <td class="align-middle white-space-nowrap views text-end">1144</td>
                          <td class="align-middle white-space-nowrap time text-end">2m:2s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">6.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/themes/falcon/components</a></td>
                          <td class="align-middle white-space-nowrap views text-end">11047</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:16s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">49.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/themewagon.com/themes/free-website-template</a></td>
                          <td class="align-middle white-space-nowrap views text-end">1007</td>
                          <td class="align-middle white-space-nowrap time text-end">0m:34s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">35.9%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/mailbluster.com/about</a></td>
                          <td class="align-middle white-space-nowrap views text-end">997</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:5s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">87.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/technext.it/services</a></td>
                          <td class="align-middle white-space-nowrap views text-end">983</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:16s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">74.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/themewagon.com/themes/free-website-template</a></td>
                          <td class="align-middle white-space-nowrap views text-end">971</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:06s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">49.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/blog/mailbluster-vs-sendy</a></td>
                          <td class="align-middle white-space-nowrap views text-end">996</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:26s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">4.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/blog/mailbluster-vs-emailoctopus</a></td>
                          <td class="align-middle white-space-nowrap views text-end">890</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:19s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">49.3%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/themewagon.com/themes/bootstrap-template</a></td>
                          <td class="align-middle white-space-nowrap views text-end">11047</td>
                          <td class="align-middle white-space-nowrap time text-end">1m:16s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">21.5%</td>
                        </tr>
                        <tr class="btn-reveal-trigger">
                          <td class="align-middle white-space-nowrap path"><a class="text-primary fw-semi-bold" href="#!">/themewagon.com/themes/free-website-template</a></td>
                          <td class="align-middle white-space-nowrap views text-end">11047</td>
                          <td class="align-middle white-space-nowrap time text-end">0m:54s</td>
                          <td class="align-middle text-end exitRate text-end pe-x1">62.5%</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="text-center d-none" id="pages-table-fallback">
                    <p class="fw-bold fs-8 mt-3">No Page found</p>
                  </div>
                </div>
                <div class="card-footer">
                  <div class="row align-items-center">
                    <div class="pagination d-none"></div>
                    <div class="col">
                      <p class="mb-0 fs-10"><span class="d-none d-sm-inline-block me-2" data-list-info="data-list-info"></span></p>
                    </div>
                    <div class="col-auto d-flex">
                      <button class="btn btn-sm btn-primary" type="button" data-list-pagination="prev"><span>Previous</span></button>
                      <button class="btn btn-sm btn-primary px-4 ms-2" type="button" data-list-pagination="next"><span>Next</span></button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-lg-5 col-xxl-6">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="text-900 fs-9 mb-2">Trend of Bounce Rate</h5>
                  <h6 class="mb-0 fs-11 text-500">Nov 1, 2020–Jan 31, 2021</h6>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/bounce-rate.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-bounce-rate h-100" data-echart-responsive="true"></div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm" data-target=".echart-bounce-rate">
                        <option value="week">Last 7 days</option>
                        <option value="month" selected="selected">Last month</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">View full report<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-7 col-xxl-6">
              <div class="card">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Traffic source</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-traffic-channel" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-traffic-channel"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/traffic-channels.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-traffic-channels h-100" data-echart-responsive="true"></div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row flex-between-center g-0">
                    <div class="col-auto">
                      <select class="form-select form-select-sm audience-select-menu">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last Year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Acquisition overview<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
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
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- Suppress old Falcon audience chart init only (others are still valid) -->
    <script>
        // Only suppress .echart-audience which no longer has a valid element
        // DO NOT touch echart-session-by-browser, echart-session-by-country, echart-active-users-report
        window.document.querySelectorAll('.echart-audience').forEach(el => {
            if (el) el.classList.remove('echart-audience');
        });
        window._originalUtilsGetData = null;
    </script>

    <?php include __DIR__ . '/../includes/scripts.php'; ?>

    <!-- Suppress theme.js chart initialization errors -->
    <script>
        // Patch utils.getData after theme.js loads
        document.addEventListener('DOMContentLoaded', function() {
            if (window.utils && window.utils.getData) {
                window._originalUtilsGetData = window.utils.getData;
                window.utils.getData = function(el, data) {
                    if (!el || !el.dataset) {
                        console.warn('utils.getData: Invalid element', el);
                        return {};
                    }
                    return window._originalUtilsGetData.call(this, el, data);
                };
            }

            // Suppress map/regions errors from old charts
            window.addEventListener('error', function(e) {
                if (e.message && (e.message.includes('regions') || e.message.includes('dataset') || e.message.includes('echart'))) {
                    console.warn('Suppressed chart error:', e.message);
                    e.preventDefault();
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script>
    // Live Sales Data
    const LIVE_SALES_API = window.BASE_URL + '/api/analytics/live-sales.php';
    let liveSalesChart = null;
    let liveSalesInterval = null;

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
                barWidth: '100%',
                barCategoryGap: '5%',
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

        // Update subtitle label
        const labelEl = document.getElementById('liveFilterLabel');
        if (labelEl) labelEl.textContent = filterLabels[filter] || filter;

        const apiUrl = LIVE_SALES_API + (filter === 'today' ? '?hours=24&today=true' : '?hours=' + filter);

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
                    liveSalesChart.setOption({
                        xAxis:  { data: rawPoints.map(p => p.time) },
                        series: [{ data: rawPoints.map(p => parseFloat(p.amount) || 0) }]
                    });
                }

                // Recent transactions list — clean rows matching template
                const list = document.getElementById('liveTransactionsList');
                if (d.recent_transactions && d.recent_transactions.length > 0) {
                    list.innerHTML = d.recent_transactions.slice(0, 5).map((txn, idx) => `
                        <div class="d-flex justify-content-between align-items-center py-1 ${idx < 4 ? 'border-bottom' : ''}" style="border-color:rgba(255,255,255,0.1)!important">
                            <div class="text-truncate me-2" style="max-width:68%;">
                                <span class="fs-11 text-white">${txn.order_code}</span>
                                <span class="fs-11 opacity-50"> &bull; ${txn.branch_name || ''}</span>
                            </div>
                            <span class="fs-11 text-white fw-semibold">${formatCurrency(txn.grand_total)}</span>
                        </div>`).join('');
                } else {
                    list.innerHTML = `<p class="text-white opacity-50 fs-11 mb-0 py-1">No transactions yet</p>`;
                }
            })
            .catch(e => console.error('Live sales error:', e));
    }

    function initGoalCharts() {
        // Goal Chart 1 - Completed Goals
        const goalChart1 = echarts.init(document.getElementById('goalChart1'));
        goalChart1.setOption({
            tooltip: { show: false },
            grid: { right: '16px', left: '0', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(25).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: [172,129,123,158,196,106,187,198,152,175,178,165,188,139,115,131,143,140,112,167,180,156,121,190,100],
                itemStyle: { barBorderRadius: [5,5,0,0] },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });

        // Goal Chart 2 - Value
        const goalChart2 = echarts.init(document.getElementById('goalChart2'));
        goalChart2.setOption({
            tooltip: { show: false },
            grid: { right: '16px', left: '16px', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(25).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: [170,156,171,193,108,178,163,175,117,123,174,199,122,111,113,140,192,167,186,172,131,187,135,115,118],
                itemStyle: { barBorderRadius: [5,5,0,0] },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });

        // Goal Chart 3 - Conversion Rate
        const goalChart3 = echarts.init(document.getElementById('goalChart3'));
        goalChart3.setOption({
            tooltip: { show: false },
            grid: { right: '0', left: '16px', bottom: '0', top: '0' },
            xAxis: { 
                type: 'category',
                show: false,
                data: Array(25).fill('')
            },
            yAxis: { 
                type: 'value',
                show: false
            },
            series: [{
                type: 'bar',
                data: [199,181,155,164,108,158,117,148,121,152,189,116,111,130,113,171,193,104,110,153,190,162,180,114,183],
                itemStyle: { barBorderRadius: [5,5,0,0] },
                barWidth: '60%',
                showBackground: true,
                backgroundStyle: { color: 'rgba(0,0,0,0.05)' }
            }]
        });
    }

    function initCampaignCharts() {
        // Campaign Revenue Chart
        const campaignRevenueChart = echarts.init(document.getElementById('campaignRevenueChart'));
        campaignRevenueChart.setOption({
            series: [{
                type: 'line',
                data: [101,165,140,162,121,190,139],
                symbol: 'none',
                color: '#f5803e',
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(245, 128, 62, .25)' },
                        { offset: 1, color: 'rgba(245, 128, 62, 0)' }
                    ])
                }
            }],
            xAxis: { boundaryGap: false, show: false },
            yAxis: { show: false },
            grid: { right: '20px', left: '0', bottom: '0', top: '20px' }
        });

        // Campaign Clicks Chart
        const campaignClicksChart = echarts.init(document.getElementById('campaignClicksChart'));
        campaignClicksChart.setOption({
            series: [{
                type: 'line',
                data: [119,199,195,101,155,131,180],
                symbol: 'none'
            }],
            xAxis: { boundaryGap: false, show: false },
            yAxis: { show: false },
            grid: { right: '20px', left: '0', bottom: '0', top: '20px' }
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initLiveSalesChart();
        initGoalCharts();
        initCampaignCharts();
        
        // Restore filter from localStorage
        const savedFilter = localStorage.getItem('liveSalesFilter');
        if (savedFilter) {
            document.getElementById('liveSalesFilter').value = savedFilter;
        }
        
        // Save filter to localStorage on change
        document.getElementById('liveSalesFilter').addEventListener('change', function() {
            localStorage.setItem('liveSalesFilter', this.value);
            fetchLiveSales();
        });
        
        fetchLiveSales();
        
        // Poll every 30 seconds for real-time updates
        liveSalesInterval = setInterval(fetchLiveSales, 30000);
        
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
            
            // Resize campaign charts
            const campaignRevenueChart = echarts.getInstanceByDom(document.getElementById('campaignRevenueChart'));
            const campaignClicksChart = echarts.getInstanceByDom(document.getElementById('campaignClicksChart'));
            if (campaignRevenueChart) campaignRevenueChart.resize();
            if (campaignClicksChart) campaignClicksChart.resize();
        });
    });

    // Clean up interval when leaving page
    window.addEventListener('beforeunload', function() {
        if (liveSalesInterval) {
            clearInterval(liveSalesInterval);
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

    // Fetch branch analytics data
    async function fetchBranchAnalytics() {
        const range = document.getElementById('branchAnalyticsRange')?.value || 'week';
        const branchSelector = document.getElementById('branchSelector');
        // Always send branch_id: empty string = All Branches, value = specific branch
        const branchId = branchSelector ? (branchSelector.value || '') : null;

        try {
            // Only append branch_id if selector exists (null = omit param = default first branch)
            const branchParam = branchSelector !== null ? `&branch_id=${branchId}` : '';
            const url = `${window.BASE_URL}/api/analytics/branch-sales?range=${range}${branchParam}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                branchAnalyticsData = result.data;
                updateBranchAnalyticsUI();
                renderBranchCharts();
                populateBranchSelector(result.data.accessible_branches);

                // Clear any error message
                document.getElementById('branchNameText').textContent = result.data.branch_name;
            } else {
                console.error('Branch analytics error:', result.error);
                // Show error in UI
                document.getElementById('branchNameText').innerHTML =
                    `<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>${result.error || 'Failed to load'}</span>`;
            }
        } catch (error) {
            console.error('Failed to fetch branch analytics:', error);
            // Show network error in UI
            document.getElementById('branchNameText').innerHTML =
                '<span class="text-warning"><i class="fas fa-wifi me-1"></i>Connection error - retrying...</span>';

            // Retry after 5 seconds
            setTimeout(fetchBranchAnalytics, 5000);
        }
    }

    // Populate branch selector
    function populateBranchSelector(branches) {
        const selector = document.getElementById('branchSelector');
        if (!selector) return;

        // Only populate once (avoid resetting on every fetch)
        if (selector.dataset.populated === 'true') return;

        // Save current selection
        const currentValue = selector.value;

        // Build options: All Branches first (only for multi-branch users)
        let html = '';
        if (branches.length > 1) {
            html += `<option value="">All Branches</option>`;
        }
        html += branches.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
        selector.innerHTML = html;

        // Restore selection if still valid
        if (currentValue && branches.find(b => b.id === currentValue)) {
            selector.value = currentValue;
        }

        selector.dataset.populated = 'true';
    }

    // Update UI with analytics data
    function updateBranchAnalyticsUI() {
        if (!branchAnalyticsData) return;

        const { summary, branch_name } = branchAnalyticsData;

        // Update branch name
        document.getElementById('branchNameText').textContent = branch_name;

        // Update tab values
        document.getElementById('tabTotalSales').textContent = formatPeso(summary.total_sales);
        document.getElementById('tabNetSales').textContent = formatPeso(summary.total_net);
        document.getElementById('tabRefunds').textContent = formatPeso(summary.total_refunds);
        document.getElementById('tabTransactions').textContent = formatNumber(summary.total_transactions);

        // Update trends
        updateTrend('tabSalesTrend', 'tabSalesTrendIcon', summary.period_change);
        updateTrend('tabNetTrend', 'tabNetTrendIcon', summary.period_change);
        updateTrend('tabRefundTrend', 'tabRefundTrendIcon', -summary.period_change); // Inverse for refunds
        updateTrend('tabTxnTrend', 'tabTxnTrendIcon', summary.sales_trend);
    }

    // Initialize branch charts
    function initBranchCharts() {
        const chartIds = ['branchSalesChart', 'branchNetChart', 'branchRefundsChart', 'branchTransactionsChart'];

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
                if (chart) chart.resize();
            });
        });
    }

    // Render all branch charts
    function renderBranchCharts() {
        if (!branchAnalyticsData || !branchAnalyticsData.chart_data) {
            console.warn('No chart data available');
            return;
        }

        const data = branchAnalyticsData.chart_data;
        const dates = data.map(d => d.display_date);

        try { renderSalesChart(dates, data); } catch (e) { console.error('Sales chart error:', e); }
        try { renderNetChart(dates, data); } catch (e) { console.error('Net chart error:', e); }
        try { renderRefundsChart(dates, data); } catch (e) { console.error('Refunds chart error:', e); }
        try { renderTransactionsChart(dates, data); } catch (e) { console.error('Transactions chart error:', e); }
    }

    // Shared base chart config matching Falcon template style
    function baseChartOption(dates) {
        return {
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
            grid: { left: 8, right: 8, bottom: 24, top: 8, containLabel: true },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: dates,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: '#9da9bb', fontSize: 11, margin: 8 },
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
            lineSeries('Refunds',     data.map(d => d.refunds), '#79e8f6', true)
        ];

        chart.setOption(opt, true);
    }

    // Net Sales Chart
    function renderNetChart(dates, data) {
        const chart = branchCharts['branchNetChart'];
        if (!chart || typeof echarts === 'undefined') return;

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

    // Transactions Chart
    function renderTransactionsChart(dates, data) {
        const chart = branchCharts['branchTransactionsChart'];
        if (!chart || typeof echarts === 'undefined') return;

        const opt = baseChartOption(dates);
        opt.yAxis.axisLabel.formatter = v => v;
        opt.tooltip.formatter = function(params) {
            const p = params[0];
            return `<div style="font-weight:600;margin-bottom:4px">${p.axisValue}</div>
                    <div style="display:flex;justify-content:space-between;gap:16px">
                        <span style="color:${p.color}">Transactions</span>
                        <span style="font-weight:600">${p.value}</span>
                    </div>`;
        };
        opt.series = [ lineSeries('Transactions', data.map(d => d.transactions), '#27bcfd', false) ];

        chart.setOption(opt, true);
    }

    // Initialize branch analytics
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize branch charts after a short delay to ensure DOM is ready
        setTimeout(() => {
            try {
                initBranchCharts();
                fetchBranchAnalytics();
            } catch (e) {
                console.error('Branch analytics init error:', e);
            }
        }, 500);

        // Event listeners
        const rangeSelector = document.getElementById('branchAnalyticsRange');
        const branchSelector = document.getElementById('branchSelector');

        if (rangeSelector) {
            rangeSelector.addEventListener('change', function() {
                try { fetchBranchAnalytics(); } catch(e) { console.error(e); }
            });
        }

        if (branchSelector) {
            branchSelector.addEventListener('change', function() {
                try { fetchBranchAnalytics(); } catch(e) { console.error(e); }
            });
        }

        // Tab change event - resize charts
        document.querySelectorAll('#audience-chart-tab .nav-link').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                setTimeout(() => {
                    Object.values(branchCharts).forEach(chart => {
                        if (chart) chart.resize();
                    });
                }, 100);
            });
        });

        // Accounts Receivable widget
        fetchAccountsReceivable();
        setInterval(fetchAccountsReceivable, 60000);
    });

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
    </script>
  </body>

</html>
