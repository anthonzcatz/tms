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
        <?php
        require_once __DIR__ . '/../includes/sidebar.php';
        ?>
        <div class="content">
          <?php
          switch (NAVBAR_POSITION) {
              case 'top':
                  require_once __DIR__ . '/../includes/navbar-top.php';
                  break;
              case 'combo':
                  require_once __DIR__ . '/../includes/navbar-top.php';
                  break;
              case 'double-top':
                  require_once __DIR__ . '/../includes/navbar-double-top.php';
                  break;
              case 'vertical':
              default:
                  require_once __DIR__ . '/../includes/navbar.php';
                  break;
          }
          ?>
          <div class="row mb-3">
            <div class="col">
              <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center">
                  <div class="col-sm-auto d-flex align-items-center"><img class="ms-n2" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                      <h6 class="text-primary fs-10 mb-0">Welcome to </h6>
                      <h4 class="text-primary fw-bold mb-0">Falcon <span class="text-info fw-medium">CRM</span></h4>
                    </div><img class="ms-n4 d-md-none d-lg-block" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                  </div>
                  <div class="col-md-auto p-3">
                    <form class="row align-items-center g-3">
                      <div class="col-auto">
                        <h6 class="text-700 mb-0">Showing Data For: </h6>
                      </div>
                      <div class="col-md-auto position-relative">
                        <input class="form-control form-control-sm datetimepicker ps-4" id="CRMDateRange" type="text" data-options="{&quot;mode&quot;:&quot;range&quot;,&quot;dateFormat&quot;:&quot;M d&quot;,&quot;disableMobile&quot;:true , &quot;defaultDate&quot;: [&quot;Nov 03&quot;, &quot;Nov 10&quot;] }" /><span class="fas fa-calendar-alt text-primary position-absolute top-50 translate-middle-y ms-2"> </span>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-3 g-3">
            <div class="col-lg-12 col-xxl-9">
              <div class="card mb-3">
                <div class="card-body">
                  <div class="row">
                    <div class="col-lg-4 border-end-lg border-bottom border-bottom-lg-0 pb-3 pb-lg-0">
                      <div class="d-flex flex-between-center mb-3">
                        <div class="d-flex align-items-center">
                          <div class="icon-item icon-item-sm bg-primary-subtle shadow-none me-2 bg-primary-subtle"><span class="fs-11 fas fa-phone text-primary"></span></div>
                          <h6 class="mb-0">New Contact</h6>
                        </div>
                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                          <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-new-contact" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                          <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-new-contact"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                            <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                          </div>
                        </div>
                      </div>
                      <div class="d-flex">
                        <div class="d-flex">
                          <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2">15%</p>
                          <div class="d-flex flex-column"> <span class="me-1 text-success fas fa-caret-up text-primary"></span>
                            <p class="fs-11 mb-0 text-nowrap">2500 vs 2683 </p>
                          </div>
                        </div>
                        <div class="echart-crm-statistics w-100 ms-2" data-echart-responsive="true" data-echarts='{"tooltip":{"trigger":"axis"},"xAxis":{"type":"category","data":["June 01","June 02","June 03","June 04","June 05","June 06","June 07"]},"series":[{"type":"line","data":[220,230,150,175,200,170,70,160],"color":"#2c7be5","areaStyle":{"color":{"colorStops":[{"offset":0,"color":"#2c7be53A"},{"offset":1,"color":"#2c7be50A"}]}}}],"grid":{"bottom":"-10px"}}'></div>
                      </div>
                    </div>
                    <div class="col-lg-4 border-end-lg border-bottom border-bottom-lg-0 py-3 py-lg-0">
                      <div class="d-flex flex-between-center mb-3">
                        <div class="d-flex align-items-center">
                          <div class="icon-item icon-item-sm bg-primary-subtle shadow-none me-2 bg-info-subtle"><span class="fs-11 fas fa-user text-info"></span></div>
                          <h6 class="mb-0">New Users</h6>
                        </div>
                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                          <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-new-users" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                          <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-new-users"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                            <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                          </div>
                        </div>
                      </div>
                      <div class="d-flex">
                        <div class="d-flex">
                          <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2">13%</p>
                          <div class="d-flex flex-column"> <span class="me-1 text-success fas fa-caret-up text-success"></span>
                            <p class="fs-11 mb-0 text-nowrap">1635 vs 863 </p>
                          </div>
                        </div>
                        <div class="echart-crm-statistics w-100 ms-2" data-echart-responsive="true" data-echarts='{"tooltip":{"trigger":"axis"},"xAxis":{"type":"category","data":["June 01","June 02","June 03","June 04","June 05","June 06","June 07"]},"series":[{"type":"line","data":[90,160,150,120,230,155,220,240],"color":"#27bcfd","areaStyle":{"color":{"colorStops":[{"offset":0,"color":"#27bcfd3A"},{"offset":1,"color":"#27bcfd0A"}]}}}],"grid":{"bottom":"-10px"}}'></div>
                      </div>
                    </div>
                    <div class="col-lg-4 pt-3 pt-lg-0">
                      <div class="d-flex flex-between-center mb-3">
                        <div class="d-flex align-items-center">
                          <div class="icon-item icon-item-sm bg-primary-subtle shadow-none me-2 bg-success-subtle"><span class="fs-11 fas fa-bolt text-success"></span></div>
                          <h6 class="mb-0">New Leads</h6>
                        </div>
                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                          <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-new-leads" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                          <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-new-leads"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                            <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                          </div>
                        </div>
                      </div>
                      <div class="d-flex">
                        <div class="d-flex">
                          <p class="font-sans-serif lh-1 mb-1 fs-5 pe-2">16%</p>
                          <div class="d-flex flex-column"> <span class="me-1 text-success fas fa-caret-down text-danger"></span>
                            <p class="fs-11 mb-0 text-nowrap">1423 vs 256 </p>
                          </div>
                        </div>
                        <div class="echart-crm-statistics w-100 ms-2" data-echart-responsive="true" data-echarts='{"tooltip":{"trigger":"axis"},"xAxis":{"type":"category","data":["June 01","June 02","June 03","June 04","June 05","June 06","June 07"]},"series":[{"type":"line","data":[200,150,175,130,150,115,130,100],"color":"#00d27a","areaStyle":{"color":{"colorStops":[{"offset":0,"color":"#00d27a3A"},{"offset":1,"color":"#00d27a0A"}]}}}],"grid":{"bottom":"-10px"}}'></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card">
                <div class="card-header d-flex flex-between-center ps-0 py-0 border-bottom">
                  <ul class="nav nav-tabs border-0 flex-nowrap tab-active-caret" id="crm-revenue-chart-tab" role="tablist" data-tab-has-echarts="data-tab-has-echarts">
                    <li class="nav-item" role="presentation"><a class="nav-link py-3 mb-0 active" id="crm-revenue-tab" data-bs-toggle="tab" href="#crm-revenue" role="tab" aria-controls="crm-revenue" aria-selected="true">Revenue</a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link py-3 mb-0" id="crm-users-tab" data-bs-toggle="tab" href="#crm-users" role="tab" aria-controls="crm-users" aria-selected="false">Users</a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link py-3 mb-0" id="crm-deals-tab" data-bs-toggle="tab" href="#crm-deals" role="tab" aria-controls="crm-deals" aria-selected="false">Deals</a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link py-3 mb-0" id="crm-profit-tab" data-bs-toggle="tab" href="#crm-profit" role="tab" aria-controls="crm-profit" aria-selected="false">Profit</a></li>
                  </ul>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-session-by-country" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-session-by-country"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-1">
                    <div class="col-xxl-3">
                      <div class="row g-0 my-2">
                        <div class="col-md-6 col-xxl-12">
                          <div class="border-bottom-xxl border-200 mb-2">
                            <h2 class="text-primary">$37,950</h2>
                            <p class="fs-11 text-500 fw-semi-bold mb-0"><span class="fas fa-circle text-primary me-2"></span>Closed Amount</p>
                            <p class="fs-11 text-500 fw-semi-bold"><span class="fas fa-circle text-warning me-2"></span>Revenue Goal</p>
                          </div>
                          <div class="form-check form-check-inline me-2">
                            <input class="form-check-input" id="crmInbound" type="radio" name="bound" value="inbound" Checked="Checked" />
                            <label class="form-check-label" for="crmInbound">Inbound</label>
                          </div>
                          <div class="form-check form-check-inline">
                            <input class="form-check-input" id="outbound" type="radio" name="bound" value="outbound" />
                            <label class="form-check-label" for="outbound">Outbound</label>
                          </div>
                        </div>
                        <div class="col-md-6 col-xxl-12 py-2">
                          <div class="row mx-0">
                            <div class="col-6 border-end border-bottom py-3">
                              <h5 class="fw-normal text-600">$4.2k</h5>
                              <h6 class="text-500 mb-0">Email</h6>
                            </div>
                            <div class="col-6 border-bottom py-3">
                              <h5 class="fw-normal text-600">$5.6k</h5>
                              <h6 class="text-500 mb-0">Social</h6>
                            </div>
                            <div class="col-6 border-end py-3">
                              <h5 class="fw-normal text-600">$6.7k</h5>
                              <h6 class="text-500 mb-0">Call</h6>
                            </div>
                            <div class="col-6 py-3">
                              <h5 class="fw-normal text-600">$2.3k</h5>
                              <h6 class="text-500 mb-0">Other</h6>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-xxl-9">
                      <div class="tab-content">
                        <!-- Find the JS file for the following chart at: src/js/charts/echarts/crm-revenue.js-->
                        <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                        <div class="tab-pane active" id="crm-revenue" role="tabpanel" aria-labelledby="crm-revenue-tab">
                          <div class="echart-crm-revenue" data-echart-responsive="true" data-echart-tab="data-echart-tab" style="height:320px;"></div>
                        </div>
                        <div class="tab-pane" id="crm-users" role="tabpanel" aria-labelledby="crm-users-tab">
                          <div class="echart-crm-users" data-echart-responsive="true" data-echart-tab="data-echart-tab" style="height:320px;"></div>
                        </div>
                        <div class="tab-pane" id="crm-deals" role="tabpanel" aria-labelledby="crm-deals-tab">
                          <div class="echart-crm-deals" data-echart-responsive="true" data-echart-tab="data-echart-tab" style="height:320px;"></div>
                        </div>
                        <div class="tab-pane" id="crm-profit" role="tabpanel" aria-labelledby="crm-profit-tab">
                          <div class="echart-crm-profit" data-echart-responsive="true" data-echart-tab="data-echart-tab" style="height:320px;"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-3">
              <div class="card">
                <div class="card-header d-flex flex-between-center py-2 border-bottom">
                  <h6 class="mb-0">Most Leads</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-most-leads" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-most-leads"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                  <div class="row align-items-center">
                    <div class="col-md-5 col-xxl-12 mb-xxl-1">
                      <div class="position-relative">
                        <!-- Find the JS file for the following chart at: src/js/charts/echarts/most-leads.js-->
                        <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                        <div class="echart-most-leads my-2" data-echart-responsive="true"></div>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                          <p class="fs-10 mb-0 text-400 font-sans-serif fw-medium">Total</p>
                          <p class="fs-6 mb-0 font-sans-serif fw-medium mt-n2">15.6k</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-xxl-12 col-md-7">
                      <hr class="mx-nx1 mb-0 d-md-none d-xxl-block" />
                      <div class="d-flex flex-between-center border-bottom py-3 pt-md-0 pt-xxl-3">
                        <div class="d-flex"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/crm/email.svg " width="16" height="16" alt="..." />
                          <h6 class="text-700 mb-0">Email </h6>
                        </div>
                        <p class="fs-10 text-500 mb-0 fw-semi-bold">5200 vs 1052</p>
                        <h6 class="text-700 mb-0">54%</h6>
                      </div>
                      <div class="d-flex flex-between-center border-bottom py-3">
                        <div class="d-flex"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/crm/social.svg " width="16" height="16" alt="..." />
                          <h6 class="text-700 mb-0">Social </h6>
                        </div>
                        <p class="fs-10 text-500 mb-0 fw-semi-bold">5623 vs 4929</p>
                        <h6 class="text-700 mb-0">27%</h6>
                      </div>
                      <div class="d-flex flex-between-center border-bottom py-3">
                        <div class="d-flex"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/crm/call.svg " width="16" height="16" alt="..." />
                          <h6 class="text-700 mb-0">Call </h6>
                        </div>
                        <p class="fs-10 text-500 mb-0 fw-semi-bold">2535 vs 1486</p>
                        <h6 class="text-700 mb-0">4%</h6>
                      </div>
                      <div class="d-flex flex-between-center border-bottom py-3 border-bottom-0 pb-0">
                        <div class="d-flex"><img class="me-2" src="<?php echo BASE_URL; ?>/resources/assets/img/crm/other.svg " width="16" height="16" alt="..." />
                          <h6 class="text-700 mb-0">Other </h6>
                        </div>
                        <p class="fs-10 text-500 mb-0 fw-semi-bold">256 vs 189</p>
                        <h6 class="text-700 mb-0">13%</h6>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary p-0"><a class="btn btn-sm btn-link d-block py-2" href="#!">View all<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
              </div>
            </div>
            <div class="col-md-12 col-xxl-8">
              <div class="card h-100">
                <div class="card-header d-flex flex-between-center border-bottom border-200 py-2">
                  <h6 class="mb-0">Deal Forecast</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="crm-deal-forecast-bar" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-deal-forecast-bar"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body d-flex align-items-center">
                  <div class="w-100">
                    <h3 class="text-700 mb-6">$90,439</h3>
                    <div class="progress-stacked overflow-visible rounded-5 font-sans-serif fw-medium mt-xxl-auto">
                      <div class="progress fs-10 rounded-start-5" style="width: 50%; height: 20px;" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Closed won" data-bs-original-title="Closed won">
                        <div class="progress-bar overflow-visible bg-progress-gradient border-end border-100 border-2 rounded-end rounded-pill text-start"><span class="text-700 mt-n6">$47.8k</span></div>
                      </div>
                      <div class="progress fs-10" style="width: 20%; height: 20px;" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Contact sent" data-bs-original-title="Contact sent">
                        <div class="progress-bar overflow-visible bg-primary-subtle border-end border-100 border-2 text-start"><span class="text-700 mt-n6">$20.2k</span></div>
                      </div>
                      <div class="progress fs-10" style="width: 15%; height: 20px;" role="progressbar" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pending" data-bs-original-title="Pending">
                        <div class="progress-bar overflow-visible bg-info-subtle border-end border-100 border-2 text-start"><span class="text-700 mt-n6">$18k</span></div>
                      </div>
                      <div class="progress fs-10 rounded-end-5" style="width: 15%; height: 20px;" role="progressbar" aria-valuenow="15" aria-valuemin="15" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Qualified" data-bs-original-title="Qualified">
                        <div class="progress-bar overflow-visible bg-info rounded-start rounded-pill text-start"><span class="text-700 mt-n6">$16k</span></div>
                      </div>
                    </div>
                    <div class="row fs-10 fw-semi-bold text-500 mt-3 g-0 mt-3 mt-xxl-4">
                      <div class="col-auto d-flex align-items-center pe-3"><span class="dot bg-primary"></span><span>Closed won</span><span class="d-none d-md-inline-block d-lg-none d-xxl-inline-block">(100%)</span></div>
                      <div class="col-auto d-flex align-items-center pe-3"><span class="dot bg-primary-subtle"></span><span>Contact sent</span><span class="d-none d-md-inline-block d-lg-none d-xxl-inline-block">(5%)</span></div>
                      <div class="col-auto d-flex align-items-center pe-3"><span class="dot bg-info-subtle"></span><span>Pending</span><span class="d-none d-md-inline-block d-lg-none d-xxl-inline-block">(5%)</span></div>
                      <div class="col-auto d-flex align-items-center"><span class="dot bg-info"></span><span>Qualified</span><span class="d-none d-md-inline-block d-lg-none d-xxl-inline-block">(20%)</span></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-4">
              <div class="card h-100">
                <div class="card-header d-flex flex-between-center border-bottom py-2">
                  <h6 class="mb-0">Deal Storage Funnel</h6><a class="btn btn-link btn-sm px-0 shadow-none" href="#!">View Details<span class="fas fa-chevron-right ms-1 fs-11"></span></a>
                </div>
                <div class="card-body">
                  <div class="row rtl-row-reverse g-1">
                    <div class="col">
                      <div class="d-flex flex-between-center rtl-row-reverse">
                        <h6 class="fs-11 text-500">Deal Stage</h6>
                        <h6 class="fs-11 text-500">Count of Deals</h6>
                      </div>
                    </div>
                    <div class="col-auto">
                      <h6 class="fs-11 text-500">Conversion </h6>
                    </div>
                  </div>
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/deal-storage-funnel.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-deal-storage-funnel" data-echart-responsive="true" data-options='{"data":[7,10,13,19,19],"dataAxis1":["Processing","Contact won","Contact Sent","Qualified to Buy","Created"],"dataAxis2":["50%","70%","76%","68%","99%"]}'></div>
                </div>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="card h-100">
                <div class="card-header d-flex flex-between-center py-2">
                  <h6 class="mb-0">Deal Closed vs Goal</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="crm-closed-vs-goal" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-closed-vs-goal"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/closed-vs-goal.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-closed-vs-goal" data-echart-responsive="true"></div>
                </div>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="card overflow-hidden">
                <div class="card-header">
                  <h6 class="mb-0">Deal Forecast by Owner</h6>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive scrollbar">
                    <table class="table mb-0 fs-10 border-200 table-borderless">
                      <thead class="bg-200">
                        <tr>
                          <th class="text-800 text-nowrap">Owner</th>
                          <th class="text-800 text-center text-nowrap">Qualified to buy</th>
                          <th class="text-800 text-center text-nowrap">Appointment </th>
                          <th class="text-800 text-end text-nowrap">Contact sent</th>
                          <th class="text-800 pe-x1 text-end text-nowrap">Closed won</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr class="border-bottom border-200">
                          <td class="align-middle font-sans-serif fw-medium text-nowrap"><a href="<?php echo BASE_URL; ?>/public/app/e-commerce/customer-details.html">John oliver</a></td>
                          <td class="align-middle text-center">1000</td>
                          <td class="align-middle text-center">$2600</td>
                          <td class="align-middle text-center">$3523</td>
                          <td class="align-middle text-end">$1311</td>
                        </tr>
                        <tr class="border-bottom border-200">
                          <td class="align-middle font-sans-serif fw-medium text-nowrap"><a href="<?php echo BASE_URL; ?>/public/app/e-commerce/customer-details.html">Sean Paul</a></td>
                          <td class="align-middle text-center">1500</td>
                          <td class="align-middle text-center">$1600</td>
                          <td class="align-middle text-center">$3523</td>
                          <td class="align-middle text-end">$2311</td>
                        </tr>
                        <tr class="border-bottom border-200">
                          <td class="align-middle font-sans-serif fw-medium text-nowrap"><a href="<?php echo BASE_URL; ?>/public/app/e-commerce/customer-details.html">Brad Shaw</a></td>
                          <td class="align-middle text-center">1400</td>
                          <td class="align-middle text-center">$2200</td>
                          <td class="align-middle text-center">$3523</td>
                          <td class="align-middle text-end">$3311</td>
                        </tr>
                        <tr>
                          <td class="align-middle font-sans-serif fw-medium text-nowrap"><a href="<?php echo BASE_URL; ?>/public/app/e-commerce/customer-details.html">Max Payne</a></td>
                          <td class="align-middle text-center">6600</td>
                          <td class="align-middle text-center">$2220</td>
                          <td class="align-middle text-center">$3523</td>
                          <td class="align-middle text-end">$1511</td>
                        </tr>
                      </tbody>
                      <tfoot class="bg-body-tertiary">
                        <tr class="fw-bold">
                          <td class="text-700">Total</td>
                          <td class="text-700 text-center">$6359</td>
                          <td class="text-700 text-center"> $8151</td>
                          <td class="text-700 text-center"> $9174</td>
                          <td class="text-700 pe-x1 text-end"> $12587</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-3 g-3">
            <div class="col-lg-7">
              <div class="card" id="CrmLocationBySessionTable" data-list='{"valueNames":["country","sessions","users"],"page":3,"pagination":true}'>
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">Location By Session</h6>
                  <div class="d-flex">
                    <div class="btn-reveal-trigger">
                      <button class="btn btn-link btn-reveal btn-sm location-by-session-map-reset" type="button"><span class="fas fa-sync-alt fs-10"></span></button>
                    </div>
                    <div class="dropdown font-sans-serif btn-reveal-trigger">
                      <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="crm-location-by-session" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                      <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-location-by-session"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                        <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-body pb-0 position-relative">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/location-by-session-crm.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-location-by-session-map" data-echart-responsive="true" style="height:302px;"></div>
                  <div class="position-absolute top-0 border mt-3 border-200 rounded-3 bg-body-tertiary">
                    <button class="btn btn-link btn-sm bg-100 rounded-bottom-0 px-2 location-by-session-map-zoom text-700" type="button"><span class="fas fa-plus fs-10"></span></button>
                    <hr class="text-200 m-0" />
                    <button class="btn btn-link btn-sm bg-100 rounded-top-0 px-2 location-by-session-map-zoomOut text-700" type="button"><span class="fas fa-minus fs-10"></span></button>
                  </div>
                  <div class="table-responsive scrollbar mx-nx1 mt-3">
                    <table class="table fs-10 mb-0">
                      <thead class="bg-200">
                        <tr>
                          <th class="text-800 sort" data-sort="country">Country</th>
                          <th class="text-800 sort" data-sort="sessions">Sessions</th>
                          <th class="text-800 sort" data-sort="users">Users</th>
                          <th class="text-800 sort text-end" style="width: 9.625rem;">Percentage</th>
                        </tr>
                      </thead>
                      <tbody class="list" id="table-crm-location-session">
                        <tr>
                          <td class="align-middle py-3"><a href="#!">
                              <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/crm/india.png" alt="" />
                                <p class="mb-0 ps-3 country text-700">India</p>
                              </div>
                            </a></td>
                          <td class="align-middle fw-semi-bold sessions">268,663</td>
                          <td class="users align-middle">325,633</td>
                          <td class="align-middle pe-x1">
                            <div class="d-flex align-items-center justify-content-end">
                              <p class="mb-0 me-2">89%</p>
                              <div class="progress rounded-3 bg-200" style="height: 0.3125rem;width:3.8rem" role="progressbar" aria-valuenow="89" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary rounded-pill" style="width: 89%;"></div>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td class="align-middle py-3"><a href="#!">
                              <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/crm/uae.png" alt="" />
                                <p class="mb-0 ps-3 country text-700">UAE</p>
                              </div>
                            </a></td>
                          <td class="align-middle fw-semi-bold sessions">250,663</td>
                          <td class="users align-middle">525,633</td>
                          <td class="align-middle pe-x1">
                            <div class="d-flex align-items-center justify-content-end">
                              <p class="mb-0 me-2">62%</p>
                              <div class="progress rounded-3 bg-200" style="height: 0.3125rem;width:3.8rem" role="progressbar" aria-valuenow="62" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary rounded-pill" style="width: 62%;"></div>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td class="align-middle py-3"><a href="#!">
                              <div class="d-flex align-items-center"><img src="<?php echo BASE_URL; ?>/resources/assets/img/crm/nepal.png" alt="" />
                                <p class="mb-0 ps-3 country text-700">Nepal</p>
                              </div>
                            </a></td>
                          <td class="align-middle fw-semi-bold sessions">268,663</td>
                          <td class="users align-middle">325,633</td>
                          <td class="align-middle pe-x1">
                            <div class="d-flex align-items-center justify-content-end">
                              <p class="mb-0 me-2">50%</p>
                              <div class="progress rounded-3 bg-200" style="height: 0.3125rem;width:3.8rem" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary rounded-pill" style="width: 50%;"></div>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer text-end p-0">
                  <div class="pagination d-none"></div>
                  <p class="mb-0 fs-10 px-x1"><span class="d-none d-sm-inline-block me-2" data-list-info="data-list-info"> </span><span class="d-none d-sm-inline-block me-2">&mdash;  </span><a class="btn btn-link btn-sm py-2 px-0" href="#!">View all<span class="fas fa-angle-right ms-1"></span></a></p>
                </div>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="row g-3">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header">
                      <h6 class="mb-0">Average Call Duration<span class="ms-1 text-400" data-bs-toggle="tooltip" data-bs-placement="top" title="Average call duration based of last 50 calls"><span class="far fa-question-circle" data-fa-transform="shrink-1"></span></span></h6>
                    </div>
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col">
                          <h4 class="text-primary fw-normal">10m:8s</h4>
                          <p class="fs-11 fw-semi-bold text-500 mb-0">Based on 50 calls</p>
                        </div>
                        <div class="col-auto text-end">
                          <div class="echart-call-duration" data-echart-responsive="true" data-echarts='{"series":[{"type":"line","data":[8,15,12,14,18,12,12,25,13,12,10,13,35],"color":"#f5803e","areaStyle":{"color":{"colorStops":[{"offset":0,"color":"#f5803e3A"},{"offset":1,"color":"#f5803e0A"}]}}}],"grid":{"bottom":"-10px","right":"0px"}}'></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-12">
                  <div class="card h-100">
                    <div class="card-header d-flex flex-between-center border-bottom border-200 py-2">
                      <h6 class="mb-0">Lead Conversion</h6>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="crm-lead-conversion" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-lead-conversion"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                    <div class="card-body pt-0">
                      <div class="d-flex bg-100 py-2 mb-3 justify-content-center mx-nx1 fs-10 border-bottom border-200">
                        <p class="text-600 mb-0 border-end border-200 px-x1 d-flex align-items-center">Current Rete: <span class="fs-sm-8 font-sans-serif ms-2 text-700"> 4.5%</span><span class="fas fa-caret-up ms-2 ms-xxl-3 fs-10 text-success"></span></p>
                        <p class="text-600 mb-0 px-x1">Target Rete:<span class="fs-sm-8 font-sans-serif ms-2 text-700"> 6%</span></p>
                      </div>
                      <!-- Find the JS file for the following chart at: src/js/charts/echarts/lead-conversion.js-->
                      <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                      <div class="echart-lead-conversion" data-echart-responsive="true"></div>
                    </div>
                    <div class="card-footer bg-body-tertiary p-0"><a class="btn btn-sm btn-link d-block py-2" href="#!">View Details<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="card h-100">
                <div class="card-header border-bottom">
                  <h6 class="mb-0">To Do List</h6>
                </div>
                <div class="card-body p-0 overflow-hidden">
                  <div class="row gx-3 align-items-center my-3 px-x1">
                    <div class="col-auto">
                      <h6 class="text-primary mb-0">25%</h6>
                    </div>
                    <div class="col">
                      <div class="progress rounded-pill" style="height: 0.5625rem;" role="progressbar" aria-valuenow="43.72" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-progress-gradient rounded-pill" style="width: 75%"></div>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-between hover-actions-trigger btn-reveal-trigger px-x1 hover-bg-100">
                    <div class="form-check mb-0 d-flex align-items-center">
                      <input class="form-check-input rounded-3 form-check-line-through p-2 mt-0" type="checkbox" id="crm-checkbox-todo-0" />
                      <label class="form-check-label mb-0 p-3" for="crm-checkbox-todo-0">Design a ad</label>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="hover-actions">
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-clock"></span></button>
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-user-plus"> </span></button>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-to-do-list-0" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-to-do-list-0"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-between hover-actions-trigger btn-reveal-trigger px-x1 hover-bg-100">
                    <div class="form-check mb-0 d-flex align-items-center">
                      <input class="form-check-input rounded-3 form-check-line-through p-2 mt-0" type="checkbox" id="crm-checkbox-todo-1" />
                      <label class="form-check-label mb-0 p-3" for="crm-checkbox-todo-1">Analyze Data</label>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="hover-actions">
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-clock"></span></button>
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-user-plus"> </span></button>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-to-do-list-1" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-to-do-list-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-between hover-actions-trigger btn-reveal-trigger px-x1 hover-bg-100">
                    <div class="form-check mb-0 d-flex align-items-center">
                      <input class="form-check-input rounded-3 form-check-line-through p-2 mt-0" type="checkbox" id="crm-checkbox-todo-2" />
                      <label class="form-check-label mb-0 p-3" for="crm-checkbox-todo-2">Youtube campaign</label>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="hover-actions">
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-clock"></span></button>
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-user-plus"> </span></button>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-to-do-list-2" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-to-do-list-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-between hover-actions-trigger btn-reveal-trigger px-x1 hover-bg-100">
                    <div class="form-check mb-0 d-flex align-items-center">
                      <input class="form-check-input rounded-3 form-check-line-through p-2 mt-0" type="checkbox" id="crm-checkbox-todo-3" />
                      <label class="form-check-label mb-0 p-3" for="crm-checkbox-todo-3">Assaign employee</label>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="hover-actions">
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-clock"></span></button>
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-user-plus"> </span></button>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-to-do-list-3" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-to-do-list-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex justify-content-between hover-actions-trigger btn-reveal-trigger px-x1 hover-bg-100">
                    <div class="form-check mb-0 d-flex align-items-center">
                      <input class="form-check-input rounded-3 form-check-line-through p-2 mt-0" type="checkbox" id="crm-checkbox-todo-4" />
                      <label class="form-check-label mb-0 p-3" for="crm-checkbox-todo-4">Video Conference</label>
                    </div>
                    <div class="d-flex align-items-center">
                      <div class="hover-actions">
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-clock"></span></button>
                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="fas fa-user-plus"> </span></button>
                      </div>
                      <div class="dropdown font-sans-serif btn-reveal-trigger">
                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-to-do-list-4" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                        <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-to-do-list-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                          <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary p-0"><a class="btn btn-sm btn-link d-block py-2" href="#!"><span class="fas fa-plus me-1 fs-11"></span>Add New Task</a></div>
              </div>
            </div>
            <div class="col-lg-7">
              <div class="card" id="TableCrmRecentLeads" data-list='{"valueNames":["name","email","status"],"page":8,"pagination":true}'>
                <div class="card-header d-flex flex-between-center flex-wrap gap-2 py-2">
                  <h6 class="mb-0">Recent Leads</h6>
                  <div class="d-none" id="table-recent-leads-actions">
                    <div class="d-flex">
                      <select class="form-select form-select-sm" aria-label="Bulk actions">
                        <option selected="">Bulk actions</option>
                        <option value="Delete">Delete</option>
                        <option value="Archive">Archive</option>
                      </select>
                      <button class="btn btn-falcon-default btn-sm ms-2" type="button">Apply</button>
                    </div>
                  </div>
                  <div id="table-recent-leads-replace-element">
                    <div class="dropdown font-sans-serif btn-reveal-trigger">
                      <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="recent-leads-header-dropdown" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                      <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="recent-leads-header-dropdown"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                        <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-body px-0 py-0">
                  <div class="table-responsive scrollbar">
                    <table class="table fs-10 mb-0">
                      <thead class="bg-200">
                        <tr>
                          <th class="white-space-nowrap align-middle py-0" style="max-width: 30px;">
                            <div class="form-check mb-0 d-flex align-items-center">
                              <input class="form-check-input mt-0" id="checkbox-bulk-leads-select" type="checkbox" data-bulk-select='{"body":"table-recent-leads-body","actions":"table-recent-leads-actions","replacedElement":"table-recent-leads-replace-element"}' />
                            </div>
                          </th>
                          <th class="text-800 sort align-middle" data-sort="name">Name</th>
                          <th class="text-800 sort align-middle" data-sort="email">Email and Phone</th>
                          <th class="text-800 sort align-middle" data-sort="status">Status</th>
                          <th class="text-800 sort align-middle text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody class="list" id="table-recent-leads-body">
                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                          <td class="align-middle" style="max-width: 30px;">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="recent-leads-0" data-bulk-select-row="data-bulk-select-row" />
                            </div>
                          </td>
                          <td class="align-middle white-space-nowrap"><a class="text-800" href="<?php echo BASE_URL; ?>/public/pages/user/profile.html">
                              <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl">
                                  <img class="rounded-circle" src="<?php echo BASE_URL; ?>/resources/assets/img/team/1-thumb.png" alt="" />

                                </div>
                                <h6 class="mb-0 ps-2 name">Kerry Ingram</h6>
                              </div>
                            </a></td>
                          <td class="align-middle white-space-nowrap text-primary email"><a href="mailto:john@gmail.com">john@gmail.com</a></td>
                          <td class="align-middle white-space-nowrap"><small class="badge fw-semi-bold rounded-pill status badge-subtle-primary"> New Lead</small></td>
                          <td class="align-middle white-space-nowrap text-end position-relative">
                            <div class="hover-actions bg-100">
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-edit"></span></button>
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-comment"></span></button>
                            </div>
                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                              <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-0" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                              <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-recent-leads-0"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                                <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                          <td class="align-middle" style="max-width: 30px;">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="recent-leads-1" data-bulk-select-row="data-bulk-select-row" />
                            </div>
                          </td>
                          <td class="align-middle white-space-nowrap"><a class="text-800" href="<?php echo BASE_URL; ?>/public/pages/user/profile.html">
                              <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl">
                                  <img class="rounded-circle" src="<?php echo BASE_URL; ?>/resources/assets/img/team/2-thumb.png" alt="" />

                                </div>
                                <h6 class="mb-0 ps-2 name">Bradie Knowall</h6>
                              </div>
                            </a></td>
                          <td class="align-middle white-space-nowrap text-primary email"><a href="mailto:bradie@mail.ru">bradie@mail.ru</a></td>
                          <td class="align-middle white-space-nowrap"><small class="badge fw-semi-bold rounded-pill status badge-subtle-primary"> New Lead</small></td>
                          <td class="align-middle white-space-nowrap text-end position-relative">
                            <div class="hover-actions bg-100">
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-edit"></span></button>
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-comment"></span></button>
                            </div>
                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                              <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-1" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                              <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-recent-leads-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                                <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                          <td class="align-middle" style="max-width: 30px;">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="recent-leads-2" data-bulk-select-row="data-bulk-select-row" />
                            </div>
                          </td>
                          <td class="align-middle white-space-nowrap"><a class="text-800" href="<?php echo BASE_URL; ?>/public/pages/user/profile.html">
                              <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl">
                                  <img class="rounded-circle" src="<?php echo BASE_URL; ?>/resources/assets/img/team/3-thumb.png" alt="" />

                                </div>
                                <h6 class="mb-0 ps-2 name">Jenny Horas</h6>
                              </div>
                            </a></td>
                          <td class="align-middle white-space-nowrap text-primary email"><a href="mailto:jenny@mail.ru">jenny@mail.ru</a></td>
                          <td class="align-middle white-space-nowrap"><small class="badge fw-semi-bold rounded-pill status badge-subtle-warning"> Cold Lead</small></td>
                          <td class="align-middle white-space-nowrap text-end position-relative">
                            <div class="hover-actions bg-100">
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-edit"></span></button>
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-comment"></span></button>
                            </div>
                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                              <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-2" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                              <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-recent-leads-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                                <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                          <td class="align-middle" style="max-width: 30px;">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="recent-leads-3" data-bulk-select-row="data-bulk-select-row" />
                            </div>
                          </td>
                          <td class="align-middle white-space-nowrap"><a class="text-800" href="<?php echo BASE_URL; ?>/public/pages/user/profile.html">
                              <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl">
                                  <img class="rounded-circle" src="<?php echo BASE_URL; ?>/resources/assets/img/team/4-thumb.png" alt="" />

                                </div>
                                <h6 class="mb-0 ps-2 name">Chris Pratt</h6>
                              </div>
                            </a></td>
                          <td class="align-middle white-space-nowrap text-primary email"><a href="mailto:pratt@mail.ru">pratt@mail.ru</a></td>
                          <td class="align-middle white-space-nowrap"><small class="badge fw-semi-bold rounded-pill status badge-subtle-warning"> Cold Lead</small></td>
                          <td class="align-middle white-space-nowrap text-end position-relative">
                            <div class="hover-actions bg-100">
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-edit"></span></button>
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-comment"></span></button>
                            </div>
                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                              <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-3" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                              <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-recent-leads-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                                <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                              </div>
                            </div>
                          </td>
                        </tr>
                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                          <td class="align-middle" style="max-width: 30px;">
                            <div class="form-check mb-0">
                              <input class="form-check-input" type="checkbox" id="recent-leads-4" data-bulk-select-row="data-bulk-select-row" />
                            </div>
                          </td>
                          <td class="align-middle white-space-nowrap"><a class="text-800" href="<?php echo BASE_URL; ?>/public/pages/user/profile.html">
                              <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl">
                                  <img class="rounded-circle" src="<?php echo BASE_URL; ?>/resources/assets/img/team/5-thumb.png" alt="" />

                                </div>
                                <h6 class="mb-0 ps-2 name">Andy Murray</h6>
                              </div>
                            </a></td>
                          <td class="align-middle white-space-nowrap text-primary email"><a href="mailto:andy@gmail.com">andy@gmail.com</a></td>
                          <td class="align-middle white-space-nowrap"><small class="badge fw-semi-bold rounded-pill status badge-subtle-success"> Won Lead</small></td>
                          <td class="align-middle white-space-nowrap text-end position-relative">
                            <div class="hover-actions bg-100">
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-edit"></span></button>
                              <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm"><span class="far fa-comment"></span></button>
                            </div>
                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                              <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-4" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                              <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="crm-recent-leads-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                                <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary p-0">
                  <div class="pagination d-none"></div><a class="btn btn-sm btn-link d-block py-2" href="#!">Show full list<span class="fas fa-chevron-right ms-1 fs-11"></span></a>
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
      </div>
    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->


   <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
      <?php include __DIR__ . '/../includes/scripts.php'; ?>
  </body>

</html>