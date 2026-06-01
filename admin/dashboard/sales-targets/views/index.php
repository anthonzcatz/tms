<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/dashboard/sales-targets/assets/css/sales-targets.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/sales-targets.css'); ?>">

<!-- Optional: scoped Settings panel styles -->
<style>
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] {
    min-width: 240px;
    border-radius: 6px;
    border-color: #d0d5dd;
    background-color: #ffffff;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:hover {
    border-color: #b6beca;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:focus {
    border-color: #84c5f4;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] option {
    font-size: 0.9rem;
  }
</style>

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
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Sales <span class="text-info fw-medium">Targets</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/analytics.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Sales Targets</li>
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

        <!-- Sales Targets Content -->
        <div class="row g-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-header card-no-border pb-0">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2">
                  <div>
                    <h3 class="mb-1">Sales Targets (Daily)</h3>
                    <div class="text-muted">Manage daily sales targets per month. You can apply one target to the whole month or set per-day values.</div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark" id="stSelectedMonthBadge">Month: <span id="currentMonthDisplay">-</span></span>
                  </div>
                </div>
              </div>
              <hr>
              
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                      <div class="card-header bg-light">
                        <h5 class="mb-0">Month Setup</h5>
                      </div>
                      <div class="card-body">
                        <div class="mb-3">
                          <label class="form-label fw-semibold">Branch</label>
                          <select class="form-select" id="stBranch">
                            <option value="">All Branches</option>
                            <!-- Populated by JS -->
                          </select>
                        </div>

                        <div class="mb-3">
                          <label class="form-label fw-semibold">Select Month</label>
                          <input type="month" class="form-control" id="stMonth">
                        </div>

                        <div class="mb-3">
                          <label class="form-label fw-semibold">Mode</label>
                          <select class="form-select" id="stMode">
                            <option value="same" selected>Same target for whole month</option>
                            <option value="manual">Manual per-day targets</option>
                          </select>
                        </div>

                        <div id="stSameModeFields">
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Target Sales Amount (applies to all dates)</label>
                            <div class="input-group">
                              <span class="input-group-text">₱</span>
                              <input type="text" class="form-control" id="stSameAmount" inputmode="decimal" autocomplete="off" placeholder="0.00">
                            </div>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-semibold">Notes (optional)</label>
                            <input type="text" class="form-control" id="stSameNotes" maxlength="255" placeholder="Optional notes for this month">
                          </div>

                          <div class="mb-3">
                            <button type="button" class="btn btn-primary w-100" id="stApplySameBtn">
                              <span class="fas fa-calendar-check me-2"></span>Apply to whole month
                            </button>
                          </div>
                          <div class="text-muted small">This will create/update entries from the 1st until the last day of the selected month.</div>
                        </div>

                        <div class="d-none" id="stManualModeFields">
                          <div class="alert alert-info mb-0">
                            <small class="mb-0">
                              <span class="fas fa-info-circle me-1"></span>
                              Manual mode enabled. Edit the table on the right then click <strong>Save Manual Changes</strong>.
                            </small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                      <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center mb-2 p-3">
                          <h5 class="mb-0">Daily Targets</h5>
                          <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="stReloadBtn">
                              <span class="fas fa-sync-alt me-1"></span>Reload
                            </button>
                          </div>
                        </div>

                        <div class="st-table-scroll px-3 pb-3">
                          <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="stTable">
                              <thead class="table-light">
                                <tr>
                                  <th class="text-nowrap" style="width: 60px;">Day</th>
                                  <th class="text-nowrap" style="width: 160px;">Date</th>
                                  <th class="text-nowrap" style="width: 220px;">Target Sales Amount</th>
                                  <th>Notes</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr><td colspan="4" class="text-center">Loading...</td></tr>
                              </tbody>
                            </table>
                          </div>
                        </div>

                        <div class="st-sticky-actions px-3 pb-3">
                          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div class="text-muted small" id="stSummary">—</div>
                            <div class="d-flex gap-2">
                              <button type="button" class="btn btn-outline-danger" id="stClearMonthBtn">
                                <span class="fas fa-trash-alt me-1"></span>Clear month targets
                              </button>
                              <button type="button" class="btn btn-success" id="stSaveManualBtn" disabled>
                                <span class="fas fa-save me-1"></span>Save Manual Changes
                              </button>
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
        </div>

      </div>
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  
  <script src="<?php echo BASE_URL; ?>/admin/dashboard/sales-targets/assets/js/sales-targets.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/sales-targets.js'); ?>"></script>
</body>
</html>
