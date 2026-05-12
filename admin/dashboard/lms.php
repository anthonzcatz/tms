<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(__DIR__)) . '/config/config.php';
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
          <div class="card mb-3">
            <div class="card-body px-xxl-0 pt-4">
              <div class="row g-0">
                <div class="col-xxl-3 col-md-6 px-3 text-center border-end-md border-bottom border-bottom-xxl-0 pb-3 p-xxl-0 ps-md-0">
                  <div class="icon-circle icon-circle-primary"><span class="fs-7 fas fa-user-graduate text-primary"></span></div>
                  <h4 class="mb-1 font-sans-serif"><span class="text-700 mx-2" data-countup='{"endValue":"4968"}'>0</span><span class="fw-normal text-600">New Learners</span></h4>
                  <p class="fs-10 fw-semi-bold mb-0">4203 <span class="text-600 fw-normal">last month</span></p>
                </div>
                <div class="col-xxl-3 col-md-6 px-3 text-center border-end-xxl border-bottom border-bottom-xxl-0 pb-3 pt-4 pt-md-0 pe-md-0 p-xxl-0">
                  <div class="icon-circle icon-circle-info"><span class="fs-7 fas fa-chalkboard-teacher text-info"></span></div>
                  <h4 class="mb-1 font-sans-serif"><span class="text-700 mx-2" data-countup='{"endValue":"324"}'>0</span><span class="fw-normal text-600">New Trainers</span></h4>
                  <p class="fs-10 fw-semi-bold mb-0">301 <span class="text-600 fw-normal">last month</span></p>
                </div>
                <div class="col-xxl-3 col-md-6 px-3 text-center border-end-md border-bottom border-bottom-md-0 pb-3 pt-4 p-xxl-0 pb-md-0 ps-md-0">
                  <div class="icon-circle icon-circle-success"><span class="fs-7 fas fa-book-open text-success"></span></div>
                  <h4 class="mb-1 font-sans-serif"><span class="text-700 mx-2" data-countup='{"endValue":"3712"}'>0</span><span class="fw-normal text-600">New Courses</span></h4>
                  <p class="fs-10 fw-semi-bold mb-0">2779 <span class="text-600 fw-normal">last month</span></p>
                </div>
                <div class="col-xxl-3 col-md-6 px-3 text-center pt-4 p-xxl-0 pb-0 pe-md-0">
                  <div class="icon-circle icon-circle-warning"><span class="fs-7 fas fa-dollar-sign text-warning"></span></div>
                  <h4 class="mb-1 font-sans-serif"><span class="text-700 mx-2" data-countup='{"endValue":"1054"}'>0</span><span class="fw-normal text-600">Refunds</span></h4>
                  <p class="fs-10 fw-semi-bold mb-0">1201 <span class="text-600 fw-normal">last month</span></p>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-xxl-4">
              <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex flex-between-center py-2">
                  <h6 class="mb-0">Trending Keywords</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-trending-keywords" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-trending-keywords"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body p-2 d-flex flex-center">
                  <!-- Find the JS file for the following chart at: src/js/charts/d3/trending-keywords.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="d3-trending-keywords position-relative w-100">
                    <svg class="d3-trending-keywords-svg h-100 w-100"></svg>
                    <div class="d3-trending-keywords-tooltip pe-none position-fixed py-1 px-2 rounded">
                      <div class="d-flex align-items-center fs-10"><span class="dot d3-tooltip-dot"></span><span class="fw-semi-bold d3-tooltip-name pe-2"></span><span class="fw-semi-bold d3-tooltip-value"></span></div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2">
                  <div class="row justify-content-between">
                    <div class="col-auto">
                      <select class="form-select form-select-sm">
                        <option value="week" selected="selected">Last 7 days</option>
                        <option value="month">Last month</option>
                        <option value="year">Last year</option>
                      </select>
                    </div>
                    <div class="col-auto"><a class="btn btn-sm btn-falcon-default" href="#!">View All</a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-8">
              <div class="row g-3 h-100">
                <div class="col-md-6">
                  <div class="card font-sans-serif h-100">
                    <div class="card-header pb-0">
                      <h6 class="mb-0">
                        Monthly Revenue Target</h6>
                    </div>
                    <div class="card-body pt-0">
                      <div class="row align-items-end h-100 mb-n1">
                        <div class="col-6 col-sm-5 pe-md-0 pe-lg-3">
                          <div class="row g-0">
                            <div class="col-7">
                              <h6 class="text-600">Target:</h6>
                            </div>
                            <div class="col-5">
                              <h6 class="text-800">$1.2M</h6>
                            </div>
                          </div>
                          <div class="row g-0">
                            <div class="col-7">
                              <h6 class="mb-0 text-600">Reached:</h6>
                            </div>
                            <div class="col-5">
                              <h6 class="mb-0 text-800">$823K</h6>
                            </div>
                          </div>
                        </div>
                        <div class="col-6 col-sm-7">
                          <div class="lms-half-doughnut ms-auto mt-n4">
                            <div class="position-relative">
                              <canvas class="pe-none" data-half-doughnut='{"data":{"labels":["Target","Reached"],"datasets":[{"data":[1200000,823000],"backgroundColor":["primary","gray-300"]}]}}'></canvas>
                              <p class="mt-n4 mb-0 position-absolute start-50 translate-middle-x fs-8 fw-medium" data-countup='{"endValue":"69","suffix":"%"}'>0</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card font-sans-serif h-100">
                    <div class="card-header pb-0">
                      <h6 class="mb-0">Monthly Visitor Target</h6>
                    </div>
                    <div class="card-body pt-0">
                      <div class="row align-items-end h-100 mb-n1">
                        <div class="col-6 col-sm-5 pe-md-0 pe-lg-3">
                          <div class="row g-0">
                            <div class="col-7">
                              <h6 class="text-600">Target:</h6>
                            </div>
                            <div class="col-5">
                              <h6 class="text-800">$7.5M</h6>
                            </div>
                          </div>
                          <div class="row g-0">
                            <div class="col-7">
                              <h6 class="mb-0 text-600">Reached:</h6>
                            </div>
                            <div class="col-5">
                              <h6 class="mb-0 text-800">$4.8M</h6>
                            </div>
                          </div>
                        </div>
                        <div class="col-6 col-sm-7">
                          <div class="lms-half-doughnut ms-auto mt-n4">
                            <div class="position-relative">
                              <canvas class="pe-none" data-half-doughnut='{"data":{"labels":["Target","Reached"],"datasets":[{"data":[7500000,4800000],"backgroundColor":["info","gray-300"]}]}}'></canvas>
                              <p class="mt-n4 mb-0 position-absolute start-50 translate-middle-x fs-8 fw-medium" data-countup='{"endValue":"64","suffix":"%"}'>0</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="card h-100">
                    <div class="card-header py-2">
                      <div class="row flex-between-center g-0">
                        <div class="col-auto">
                          <h6 class="mb-0">Avarage Enrollment Rate</h6>
                        </div>
                        <div class="col-12 col-md-auto order-3 order-md-2">
                          <div class="d-flex flex-wrap gap-md-2">
                            <button class="btn btn-link text-decoration-none text-600 fs-11 px-0 me-2" id="onSaleCourse"><span class="fas fa-circle text-primary me-1" data-fa-transform="shrink-3"></span>On Sale Course</button>
                            <button class="btn btn-link text-decoration-none text-600 fs-11 px-0" id="regularPaidCourse"><span class="fas fa-circle text-warning me-1" data-fa-transform="shrink-3"></span>Regular Paid Course</button>
                          </div>
                        </div>
                        <div class="col-auto order-2 order-md-3">
                          <div class="dropdown font-sans-serif btn-reveal-trigger">
                            <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="dropdown-avg-enrollment-lms" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                            <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-avg-enrollment-lms"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                              <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-body pb-0">
                      <!-- Find the JS file for the following chart at: src/js/charts/echarts/average-enrollment-rate.js-->
                      <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                      <div class="echart-avg-enrollment-rate" data-echart-responsive="true" data-options='{"optionOne":"onSaleCourse","optionTwo":"regularPaidCourse"}'></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="card overflow-hidden mb-3">
            <div class="card-header p-0 scrollbar">
              <ul class="nav nav-tabs border-0 top-courses-tab flex-nowrap" role="tablist">
                <li class="nav-item" role="presentation"><a class="nav-link p-x1 mb-0 active" role="tab" id="popularPaid-tab" data-bs-toggle="tab" href="#popularPaid" aria-controls="popularPaid" aria-selected="false">
                    <div class="d-flex gap-1 py-1 pe-3">
                      <div class="d-flex flex-column flex-between-center"><span class="fas fa-crown fs-11 text-warning" data-fa-transform="shrink-4"></span><span class="mt-auto fas fa-fire fs-7"></span>
                      </div>
                      <div class="ms-2">
                        <h6 class="mb-1 text-700 fs-11 text-nowrap">Most Popular</h6>
                        <h5 class="mb-0 lh-1">Paid</h5>
                      </div>
                    </div>
                  </a></li>
                <li class="nav-item" role="presentation"><a class="nav-link p-x1 mb-0 false" role="tab" id="popularFree-tab" data-bs-toggle="tab" href="#popularFree" aria-controls="popularFree" aria-selected="true">
                    <div class="d-flex gap-1 py-1 pe-3">
                      <div class="d-flex flex-column flex-between-center"><span class="mt-auto fas fa-fire fs-7"></span>
                      </div>
                      <div class="ms-2">
                        <h6 class="mb-1 text-700 fs-11 text-nowrap">Most Popular</h6>
                        <h5 class="mb-0 lh-1">Free</h5>
                      </div>
                    </div>
                  </a></li>
                <li class="nav-item" role="presentation"><a class="nav-link p-x1 mb-0 false" role="tab" id="topPaid-tab" data-bs-toggle="tab" href="#topPaid" aria-controls="topPaid" aria-selected="false">
                    <div class="d-flex gap-1 py-1 pe-3">
                      <div class="d-flex flex-column flex-between-center"><span class="fas fa-crown fs-11 text-warning" data-fa-transform="shrink-4"></span><span class="mt-auto fas fa-star fs-7"></span>
                      </div>
                      <div class="ms-2">
                        <h6 class="mb-1 text-700 fs-11 text-nowrap">Top Rated</h6>
                        <h5 class="mb-0 lh-1">Paid</h5>
                      </div>
                    </div>
                  </a></li>
                <li class="nav-item" role="presentation"><a class="nav-link p-x1 mb-0 false" role="tab" id="topFree-tab" data-bs-toggle="tab" href="#topFree" aria-controls="topFree" aria-selected="false">
                    <div class="d-flex gap-1 py-1 pe-3">
                      <div class="d-flex flex-column flex-between-center"><span class="mt-auto fas fa-star fs-7"></span>
                      </div>
                      <div class="ms-2">
                        <h6 class="mb-1 text-700 fs-11 text-nowrap">Top Rated</h6>
                        <h5 class="mb-0 lh-1">Free</h5>
                      </div>
                    </div>
                  </a></li>
              </ul>
            </div>
            <div class="card-body p-0">
              <div class="tab-content">
                <div class="tab-pane active" id="popularPaid" role="tabpanel" aria-labelledby="popularPaid-tab">
                  <div class="z-1" id="popularPaidCourses" data-list='{"valueNames":["title","name","published","enrolled","price"],"page":4}'>
                    <div class="px-0 py-0">
                      <div class="table-responsive scrollbar">
                        <table class="table fs-10 mb-0 overflow-hidden">
                          <thead class="bg-body-tertiary">
                            <tr class="font-sans-serif">
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="title">Course Title</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="name">Trainer</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="published">Published on</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="enrolled">Enrolled</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end text-end" data-sort="price">Price</th>
                              <th class="text-900 fw-medium no-sort pe-1 align-middle data-table-row-action"></th>
                            </tr>
                          </thead>
                          <tbody class="list">
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Advanced Design Tools for Modern Designs</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">01/10/21</td>
                              <td class="align-middle text-end enrolled">47,726</td>
                              <td class="align-middle text-end price">$39.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularPaidCourses-1" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularPaidCourses-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Photograpy Basics: Get Familiar Standing Behind Lens</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bruce Timm</a></td>
                              <td class="align-middle white-space-nowrap text-end published">11/12/21</td>
                              <td class="align-middle text-end enrolled">38,541</td>
                              <td class="align-middle text-end price">$19.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularPaidCourses-2" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularPaidCourses-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Abstract Painting: Zero to Mastery in Traditional Medium</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">J. H. Williams III</a></td>
                              <td class="align-middle white-space-nowrap text-end published">03/09/21</td>
                              <td class="align-middle text-end enrolled">35,666</td>
                              <td class="align-middle text-end price">$45.49</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularPaidCourses-3" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularPaidCourses-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Character Design Masterclass: Your First Supervillain</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">31/12/21</td>
                              <td class="align-middle text-end enrolled">29,988</td>
                              <td class="align-middle text-end price">$99.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularPaidCourses-4" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularPaidCourses-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="tab-pane" id="popularFree" role="tabpanel" aria-labelledby="popularFree-tab">
                  <div class="z-1" id="popularFreeCourses" data-list='{"valueNames":["title","name","published","enrolled","price"],"page":4}'>
                    <div class="px-0 py-0">
                      <div class="table-responsive scrollbar">
                        <table class="table fs-10 mb-0 overflow-hidden">
                          <thead class="bg-body-tertiary">
                            <tr class="font-sans-serif">
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="title">Course Title</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="name">Trainer</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="published">Published on</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="enrolled">Enrolled</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end text-end" data-sort="price">Price</th>
                              <th class="text-900 fw-medium no-sort pe-1 align-middle data-table-row-action"></th>
                            </tr>
                          </thead>
                          <tbody class="list">
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Script Writing Masterclass: Introdution to Industry Cliches</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">31/08/21</td>
                              <td class="align-middle text-end enrolled">92,632</td>
                              <td class="align-middle text-end price">$69.50</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularFreeCourses-1" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularFreeCourses-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Composition in Comics: Easy to Read Between Panels</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">14/05/21</td>
                              <td class="align-middle text-end enrolled">92,603</td>
                              <td class="align-middle text-end price">$39.50</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularFreeCourses-2" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularFreeCourses-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Comic Page Layout: Analysing The Classics</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">09/06/21</td>
                              <td class="align-middle text-end enrolled">32,106</td>
                              <td class="align-middle text-end price">$49.50</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularFreeCourses-3" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularFreeCourses-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Inking: Choosing Between Analog vs Digital</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">09/06/21</td>
                              <td class="align-middle text-end enrolled">9,312</td>
                              <td class="align-middle text-end price">$39.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-popularFreeCourses-4" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-popularFreeCourses-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="tab-pane" id="topPaid" role="tabpanel" aria-labelledby="topPaid-tab">
                  <div class="z-1" id="topPaidCourses" data-list='{"valueNames":["title","name","published","enrolled","price"],"page":4}'>
                    <div class="px-0 py-0">
                      <div class="table-responsive scrollbar">
                        <table class="table fs-10 mb-0 overflow-hidden">
                          <thead class="bg-body-tertiary">
                            <tr class="font-sans-serif">
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="title">Course Title</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="name">Trainer</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="published">Published on</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="enrolled">Enrolled</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end text-end" data-sort="price">Price</th>
                              <th class="text-900 fw-medium no-sort pe-1 align-middle data-table-row-action"></th>
                            </tr>
                          </thead>
                          <tbody class="list">
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Character Art School: Character Drawing Course</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bruce Timm</a></td>
                              <td class="align-middle white-space-nowrap text-end published">01/09/21</td>
                              <td class="align-middle text-end enrolled">3,625</td>
                              <td class="align-middle text-end price">$65.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topPaidCourses-1" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topPaidCourses-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">User Experience Design Essentials</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">15/12/21</td>
                              <td class="align-middle text-end enrolled">1,202</td>
                              <td class="align-middle text-end price">$25.20</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topPaidCourses-2" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topPaidCourses-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">The Art &amp; Science of Drawing</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">J. H. Williams III</a></td>
                              <td class="align-middle white-space-nowrap text-end published">03/09/21</td>
                              <td class="align-middle text-end enrolled">35,666</td>
                              <td class="align-middle text-end price">$45.49</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topPaidCourses-3" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topPaidCourses-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Abstract Painting: One-to-One</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">03/09/21</td>
                              <td class="align-middle text-end enrolled">6,356</td>
                              <td class="align-middle text-end price">$20.49</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topPaidCourses-4" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topPaidCourses-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="tab-pane" id="topFree" role="tabpanel" aria-labelledby="topFree-tab">
                  <div class="z-1" id="topFreeCourses" data-list='{"valueNames":["title","name","published","enrolled","price"],"page":4}'>
                    <div class="px-0 py-0">
                      <div class="table-responsive scrollbar">
                        <table class="table fs-10 mb-0 overflow-hidden">
                          <thead class="bg-body-tertiary">
                            <tr class="font-sans-serif">
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="title">Course Title</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle" data-sort="name">Trainer</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="published">Published on</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end" data-sort="enrolled">Enrolled</th>
                              <th class="text-900 fw-medium sort pe-1 align-middle text-end text-end" data-sort="price">Price</th>
                              <th class="text-900 fw-medium no-sort pe-1 align-middle data-table-row-action"></th>
                            </tr>
                          </thead>
                          <tbody class="list">
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Portrait Drawing Fundamentals Made Simple</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bill finger</a></td>
                              <td class="align-middle white-space-nowrap text-end published">05/10/20</td>
                              <td class="align-middle text-end enrolled">10,356</td>
                              <td class="align-middle text-end price">$36.49</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topFreeCourses-1" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topFreeCourses-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Anatomy for Figure Drawing</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">J. H. Williams</a></td>
                              <td class="align-middle white-space-nowrap text-end published">26/10/20</td>
                              <td class="align-middle text-end enrolled">12,386</td>
                              <td class="align-middle text-end price">$30.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topFreeCourses-2" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topFreeCourses-2"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">Complete Perspective Drawing Course</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bruce Timm</a></td>
                              <td class="align-middle white-space-nowrap text-end published">26/09/21</td>
                              <td class="align-middle text-end enrolled">6,757</td>
                              <td class="align-middle text-end price">$23.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topFreeCourses-3" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topFreeCourses-3"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                            <tr class="btn-reveal-trigger fw-semi-bold">
                              <td class="align-middle white-space-nowrap title"><a href="../app/e-learning/course/course-details.html">The Ultimate Animal Drawing Course</a></td>
                              <td class="align-middle text-nowrap name"><a class="text-800" href="../app/e-learning/trainer-profile.html">Bruce Timm</a></td>
                              <td class="align-middle white-space-nowrap text-end published">06/12/21</td>
                              <td class="align-middle text-end enrolled">7,658</td>
                              <td class="align-middle text-end price">$19.99</td>
                              <td class="align-middle white-space-nowrap text-end">
                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                  <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-topFreeCourses-4" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                  <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-topFreeCourses-4"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                    <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-footer bg-body-tertiary py-2">
              <div class="row flex-between-center g-0">
                <div class="col-auto">
                  <select class="form-select form-select-sm">
                    <option value="week" selected="selected">Last 7 days</option>
                    <option value="month">Last month</option>
                  </select>
                </div>
                <div class="col-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="../app/e-learning/course/course-list.html">All Courses<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-6 col-xxl-7">
              <div class="card" id="LmsUserByLocationTable" data-list='{"valueNames":["country","users","revenue","native-support"],"page":4}'>
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                  <h6 class="mb-0">User Location</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="lms-user-by-location" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="lms-user-by-location"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body pb-0 position-relative">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/user-by-location-lms.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-user-by-location-map" data-echart-responsive="true" style="height:302px;"></div>
                  <div class="position-absolute top-0 border mt-3 border-200 rounded-3 bg-body-tertiary">
                    <button class="btn btn-link btn-sm bg-100 rounded-bottom-0 px-2 user-by-location-map-zoom text-700" type="button"><span class="fas fa-plus fs-10"></span></button>
                    <hr class="text-200 m-0" />
                    <button class="btn btn-link btn-sm bg-100 rounded-top-0 px-2 user-by-location-map-zoomOut text-700" type="button"><span class="fas fa-minus fs-10"></span></button>
                  </div>
                  <div class="mt-3">
                    <div class="bar-indicator-gradient mb-1"></div>
                    <div class="d-flex flex-between-center fs-11">
                      <p class="mb-0">less than 1k</p>
                      <p class="mb-0">more than 100k</p>
                    </div>
                  </div>
                  <div class="table-responsive scrollbar mx-nx1 mt-3">
                    <table class="table fs-10 mb-0">
                      <thead class="bg-body-tertiary">
                        <tr class="font-sans-serif">
                          <th class="text-800 sort" data-sort="country" style="width: 40%">Country</th>
                          <th class="text-800 sort text-end" data-sort="users">User Count</th>
                          <th class="text-800 sort text-end" data-sort="revenue">Revenue</th>
                          <th class="text-800 sort text-end w-25" data-sort="native-support">Language Support</th>
                        </tr>
                      </thead>
                      <tbody class="list">
                        <tr class="fw-semi-bold">
                          <td class="align-middle py-3"><a href="#!">
                              <p class="mb-0 text-primary country">Bahrain</p>
                            </a></td>
                          <td class="align-middle text-end users">900</td>
                          <td class="align-middle text-end revenue">$3997</td>
                          <td class="align-middle pe-x1 text-end native-support"><span class="badge badge rounded-pill fw-medium fs-11 badge-subtle-danger">Unavailable</span>
                          </td>
                        </tr>
                        <tr class="fw-semi-bold">
                          <td class="align-middle py-3"><a href="#!">
                              <p class="mb-0 text-primary country">Bangladesh</p>
                            </a></td>
                          <td class="align-middle text-end users">123k</td>
                          <td class="align-middle text-end revenue">$6700</td>
                          <td class="align-middle pe-x1 text-end native-support"><span class="badge badge rounded-pill fw-medium fs-11 badge-subtle-warning">Early Beta</span>
                          </td>
                        </tr>
                        <tr class="fw-semi-bold">
                          <td class="align-middle py-3"><a href="#!">
                              <p class="mb-0 text-primary country">Belarus</p>
                            </a></td>
                          <td class="align-middle text-end users">6.5k</td>
                          <td class="align-middle text-end revenue">$5949</td>
                          <td class="align-middle pe-x1 text-end native-support"><span class="badge badge rounded-pill fw-medium fs-11 badge-subtle-danger">Unavailable</span>
                          </td>
                        </tr>
                        <tr class="fw-semi-bold">
                          <td class="align-middle py-3"><a href="#!">
                              <p class="mb-0 text-primary country">Belgium</p>
                            </a></td>
                          <td class="align-middle text-end users">27k</td>
                          <td class="align-middle text-end revenue">$73000</td>
                          <td class="align-middle pe-x1 text-end native-support"><span class="badge badge rounded-pill fw-medium fs-11 badge-subtle-success">Available</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary text-end py-2"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">View all<span class="fas fa-chevron-right ms-1 fs-11"></span></a></div>
              </div>
            </div>
            <div class="col-lg-6 col-xxl-5">
              <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex flex-between-center py-2">
                  <h6 class="mb-0">Marketing Expenses</h6>
                  <div class="ms-auto"><a class="btn btn-link btn-sm px-0 fw-medium" href="#!">Full Report<span class="fas fa-external-link-alt ms-1 fs-11"></span></a></div>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                  <div class="position-relative">
                    <!-- Find the JS file for the following chart at: src/js/charts/echarts/marketing-expenses.js-->
                    <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                    <div class="echart-marketing-expenses" data-echart-responsive="true"></div>
                    <div class="absolute-centered">
                      <div class="rounded-circle d-flex flex-center marketing-exp-circle">
                        <h4 class="mb-0 text-900">$1.1M</h4>
                      </div>
                    </div>
                  </div>
                  <div class="row g-3 font-sans-serif">
                    <div class="col-sm-6">
                      <div class="rounded-3 border p-3">
                        <div class="d-flex align-items-center mb-4"><span class="dot bg-info bg-opacity-25"></span>
                          <h6 class="mb-0 fw-bold">Digital Marketing</h6>
                        </div>
                        <ul class="list-unstyled mb-0">
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-info bg-opacity-100"></span>
                            <p class="lh-sm mb-0 text-700">Generate Backlinks<span class="text-900 ps-2">$91.6k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-info bg-opacity-75"></span>
                            <p class="lh-sm mb-0 text-700">Email Marketing<span class="text-900 ps-2">$183k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-info bg-opacity-50"></span>
                            <p class="lh-sm mb-0 text-700">Influencer Marketing<span class="text-900 ps-2">$138k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-info bg-opacity-25"></span>
                            <p class="lh-sm mb-0 text-700">Google Ads<span class="text-900 ps-2">$45.9k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 false"><span class="dot bg-info bg-opacity-10"></span>
                            <p class="lh-sm mb-0 text-700">Social Media<span class="text-900 ps-2">$183k</span></p>
                          </li>
                        </ul>
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <div class="rounded-3 border p-3 h-100">
                        <div class="d-flex align-items-center mb-4"><span class="dot bg-primary"></span>
                          <h6 class="mb-0 fw-bold">Offline Marketing</h6>
                        </div>
                        <ul class="list-unstyled mb-0">
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-primary bg-opacity-75"></span>
                            <p class="lh-sm mb-0 text-700">Event Sponsorship<span class="text-900 ps-2">$91.6k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1 mb-3"><span class="dot bg-primary bg-opacity-50"></span>
                            <p class="lh-sm mb-0 text-700">Outrich Event<span class="text-900 ps-2">$183k</span></p>
                          </li>
                          <li class="d-flex align-items-center fs-11 fw-medium pt-1"><span class="dot bg-primary bg-opacity-25"></span>
                            <p class="lh-sm mb-0 text-700">Ad Campaign<span class="text-900 ps-2">$138k</span></p>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-body-tertiary py-2 d-flex">
                  <div class="ms-auto">
                    <select class="form-select form-select-sm">
                      <option value="3months" selected="selected">Last 3 months</option>
                      <option value="1month">Last month</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-lg-6 col-xxl-5">
              <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex flex-between-center py-2">
                  <h6 class="mb-0">Weekly Goal</h6>
                  <div class="dropdown font-sans-serif btn-reveal-trigger">
                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal" type="button" id="lms-weekly-goal" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-ellipsis-h fs-11"></span></button>
                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="lms-weekly-goal"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Export</a>
                      <div class="dropdown-divider"></div><a class="dropdown-item text-danger" href="#!">Remove</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-sm-4">
                      <div class="pb-3 mb-3 border-bottom border-200">
                        <div class="position-relative ps-3">
                          <div class="position-absolute h-100 start-0 rounded bg-info" style="width: 4px;"></div>
                          <h6 class="fs-11 text-600 mb-1">Site Visit</h6>
                          <div class="d-flex align-items-center">
                            <h5 class="fs-7 text-700 mb-0 me-2" data-countup='{"endValue":79,"suffix":"%"}'>0</h5><span class="badge rounded-pill fs-11 fw-medium badge-subtle-success"><span class="fas fa-check"></span> On par</span>
                          </div>
                        </div>
                      </div>
                      <div class="pb-3 mb-3 border-bottom border-200">
                        <div class="position-relative ps-3">
                          <div class="position-absolute h-100 start-0 rounded bg-primary" style="width: 4px;"></div>
                          <h6 class="fs-11 text-600 mb-1">Support</h6>
                          <div class="d-flex align-items-center">
                            <h5 class="fs-7 text-700 mb-0 me-2" data-countup='{"endValue":85,"suffix":"%"}'>0</h5><span class="badge rounded-pill fs-11 fw-medium badge-subtle-primary"><span class="fas fa-caret-up"></span> Ahead</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <div class="position-relative ps-3">
                          <div class="position-absolute h-100 start-0 rounded bg-success" style="width: 4px;"></div>
                          <h6 class="fs-11 text-600 mb-1">Revenue</h6>
                          <div class="d-flex align-items-center">
                            <h5 class="fs-7 text-700 mb-0 me-2" data-countup='{"endValue":70,"suffix":"%"}'>0</h5><span class="badge rounded-pill fs-11 fw-medium badge-subtle-danger"><span class="fas fa-caret-down"></span> Behind</span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-8 h-100">
                      <!-- Find the JS file for the following chart at: src/js/charts/echarts/weekly-goals-lms.js-->
                      <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                      <div class="echart-weekly-goals-lms" data-echart-responsive="true"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 col-xxl-7">
              <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex flex-between-center py-2">
                  <h6 class="mb-0">Course Enrollment</h6>
                  <div class="ms-auto">
                    <select class="form-select form-select-sm">
                      <option value="week" selected="selected">Last 7 days</option>
                      <option value="month">Last month</option>
                    </select>
                  </div>
                </div>
                <div class="card-body">
                  <!-- Find the JS file for the following chart at: src/js/charts/echarts/course-enrollments.js-->
                  <!-- If you are not using gulp based workflow, you can find the transpiled code at: public/assets/js/theme.js-->
                  <div class="echart-bar-course-enrollments" data-echart-responsive="true"></div>
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


    <?php include_once '../includes/footer.php'; ?>


    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="<?php echo BASE_URL; ?>/resources/vendors/popper/popper.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/anchorjs/anchor.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/is/is.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/chart/chart.umd.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/countup/countUp.umd.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/echarts/echarts.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/assets/data/world.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/d3/d3.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/fontawesome/all.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/lodash/lodash.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/list.js/list.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/assets/js/theme.js"></script>

  </body>

</html>