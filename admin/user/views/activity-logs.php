<?php
/**
 * Activity Logs Full Page View
 * Accessed via /admin/user/?view=activity-logs
 */

$actionIconMap = [
    'LOGIN'                      => ['icon' => 'fa-sign-in-alt',        'color' => 'success',   'label' => 'Login'],
    'LOGOUT'                     => ['icon' => 'fa-sign-out-alt',       'color' => 'secondary', 'label' => 'Logout'],
    'SESSION_EXPIRED'            => ['icon' => 'fa-clock',              'color' => 'warning',   'label' => 'Session Expired'],
    'SESSION_TERMINATED'         => ['icon' => 'fa-ban',                'color' => 'danger',    'label' => 'Session Terminated'],
    'SESSION_INVALID'            => ['icon' => 'fa-exclamation-circle', 'color' => 'danger',    'label' => 'Session Invalid'],
    'CREATE'                     => ['icon' => 'fa-plus-circle',        'color' => 'primary',   'label' => 'Create'],
    'UPDATE'                     => ['icon' => 'fa-edit',               'color' => 'info',      'label' => 'Update'],
    'DELETE'                     => ['icon' => 'fa-trash-alt',          'color' => 'danger',    'label' => 'Delete'],
    'VIEW'                       => ['icon' => 'fa-eye',                'color' => 'secondary', 'label' => 'View'],
    'PAYMENT'                    => ['icon' => 'fa-credit-card',        'color' => 'success',   'label' => 'Payment'],
    'CANCEL'                     => ['icon' => 'fa-times-circle',       'color' => 'warning',   'label' => 'Cancel'],
    'VOID'                       => ['icon' => 'fa-ban',                'color' => 'danger',    'label' => 'Void'],
    'APPROVE'                    => ['icon' => 'fa-check-circle',       'color' => 'success',   'label' => 'Approve'],
    'CREATE_TICKET_TRANSACTION'  => ['icon' => 'fa-ticket-alt',         'color' => 'primary',   'label' => 'Ticket Transaction'],
    'CANCEL_TICKET'              => ['icon' => 'fa-times-circle',       'color' => 'warning',   'label' => 'Cancel Ticket'],
    'VOID_TICKET'                => ['icon' => 'fa-ban',                'color' => 'danger',    'label' => 'Void Ticket'],
    'CHANGE_PASSWORD'            => ['icon' => 'fa-key',                'color' => 'warning',   'label' => 'Change Password'],
    'UPDATE_PROFILE'             => ['icon' => 'fa-user-edit',          'color' => 'info',      'label' => 'Update Profile'],
    'UPLOAD_PROFILE_IMAGE'       => ['icon' => 'fa-camera',             'color' => 'info',      'label' => 'Upload Profile Image'],
];

// Build query string for pagination (preserve filters)
function buildPageUrl(int $pg, array $filters): string {
    $q = array_merge($filters, ['view' => 'activity-logs', 'page' => $pg]);
    return BASE_URL . '/admin/user/?' . http_build_query(array_filter($q, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php require_once dirname(dirname(__DIR__)) . '/includes/head.php'; ?>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/user/assets/css/user.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/user.css'); ?>">

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

      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?>
      <?php else: ?>
        <?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?>
      <?php endif; ?>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php if (NAVBAR_POSITION === 'combo'): ?>
          <?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'vertical'): ?>
          <?php include dirname(dirname(__DIR__)) . '/includes/navbar.php'; ?>
        <?php endif; ?>
      <?php endif; ?>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/user/">Profile</a></li>
            <li class="breadcrumb-item active">Activity Logs</li>
          </ol>
        </nav>

        <!-- Banner -->
        <?php $currentView = 'activity-logs'; include __DIR__ . '/_banner.php'; ?>

        <!-- Filter Card -->
        <div class="card mb-3">
          <div class="card-header bg-body-tertiary">
            <h5 class="mb-0"><span class="fas fa-filter me-2"></span>Filters</h5>
          </div>
          <div class="card-body">
            <form method="GET" action="<?php echo BASE_URL; ?>/admin/user/" class="row g-2 align-items-end">
              <input type="hidden" name="view" value="activity-logs">
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Action</label>
                <select name="action" class="form-select form-select-sm">
                  <option value="">All Actions</option>
                  <?php foreach ($distinctActions as $a): ?>
                    <option value="<?php echo htmlspecialchars($a['action']); ?>"
                      <?php echo ($filterAction === $a['action']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($a['action']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Module</label>
                <select name="module" class="form-select form-select-sm">
                  <option value="">All Modules</option>
                  <?php foreach ($distinctModules as $m): ?>
                    <option value="<?php echo htmlspecialchars($m['module_name']); ?>"
                      <?php echo ($filterModule === $m['module_name']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($m['module_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                  value="<?php echo htmlspecialchars($filterDateFrom); ?>">
              </div>
              <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                  value="<?php echo htmlspecialchars($filterDateTo); ?>">
              </div>
              <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                  <span class="fas fa-search me-1"></span>Filter
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/user/?view=activity-logs" class="btn btn-falcon-default btn-sm flex-fill">
                  <span class="fas fa-undo me-1"></span>Reset
                </a>
              </div>
            </form>
          </div>
        </div>

        <!-- Logs Table Card -->
        <div class="card">
          <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">
              <span class="fas fa-history me-2"></span>Activity Logs
              <span class="badge bg-soft-primary text-primary ms-2"><?php echo number_format($totalLogs); ?></span>
            </h5>
            <span class="text-muted fs-10">
              Page <?php echo $page; ?> of <?php echo $totalPages; ?>
              &mdash; Showing <?php echo count($activityLogs); ?> of <?php echo number_format($totalLogs); ?> entries
            </span>
          </div>
          <div class="card-body p-0">
            <?php if (empty($activityLogs)): ?>
            <div class="text-center py-5 text-muted">
              <span class="fas fa-history fs-2 mb-3 d-block"></span>
              <h6>No activity logs found</h6>
              <p class="mb-0 fs-10">Try adjusting your filters or date range.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover fs-10 mb-0 align-middle">
                <thead class="bg-body-tertiary text-uppercase fs-11 text-600">
                  <tr>
                    <th class="ps-3" style="min-width:50px">#</th>
                    <th style="min-width:110px">Action</th>
                    <th style="min-width:120px">Module</th>
                    <th style="min-width:130px">Reference</th>
                    <th style="min-width:110px">IP Address</th>
                    <th style="min-width:150px">Date &amp; Time</th>
                    <th class="text-end pe-3" style="min-width:80px">Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $rowNum = $offset + 1;
                  foreach ($activityLogs as $log):
                    $action    = strtoupper($log['action'] ?? '');
                    $iconInfo  = $actionIconMap[$action] ?? ['icon' => 'fa-circle', 'color' => 'secondary', 'label' => $action];
                    $module    = htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $log['module_name'] ?? ''))));
                    $ref       = htmlspecialchars($log['reference_code'] ?? '');
                    $ip        = htmlspecialchars($log['ip_address'] ?? 'N/A');
                    $oldVal    = $log['old_value'] ?? null;
                    $newVal    = $log['new_value'] ?? null;
                    $hasDetail = $oldVal !== null || $newVal !== null;
                    $logId     = (int) $log['log_id'];
                  ?>
                  <tr>
                    <td class="ps-3 text-muted"><?php echo $rowNum++; ?></td>
                    <td>
                      <span class="badge badge-subtle-<?php echo $iconInfo['color']; ?>">
                        <span class="fas <?php echo $iconInfo['icon']; ?> me-1"></span>
                        <?php echo htmlspecialchars($action); ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($module): ?>
                        <span class="text-700"><?php echo $module; ?></span>
                      <?php else: ?>
                        <span class="text-400">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($ref): ?>
                        <code class="fs-11"><?php echo $ref; ?></code>
                      <?php else: ?>
                        <span class="text-400">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="fas fa-map-marker-alt me-1 text-400"></span><?php echo $ip; ?>
                    </td>
                    <td class="text-nowrap">
                      <?php echo Auth::formatTimestamp($log['created_at'], 'M j, Y g:i A'); ?>
                    </td>
                    <td class="text-end pe-3">
                      <?php if ($hasDetail): ?>
                      <button class="btn btn-sm btn-falcon-default py-0 px-2"
                        data-bs-toggle="modal"
                        data-bs-target="#logDetailModal"
                        data-log-id="<?php echo $logId; ?>"
                        data-action="<?php echo htmlspecialchars($action); ?>"
                        data-module="<?php echo $module ?: '—'; ?>"
                        data-ref="<?php echo $ref ?: '—'; ?>"
                        data-old="<?php echo htmlspecialchars($oldVal ?? ''); ?>"
                        data-new="<?php echo htmlspecialchars($newVal ?? ''); ?>">
                        <span class="fas fa-eye fs-11"></span>
                      </button>
                      <?php else: ?>
                        <span class="text-300">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">
              Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo number_format($totalLogs); ?> logs
            </small>
            <?php
            $filterParams = [
                'action'    => $filterAction,
                'module'    => $filterModule,
                'date_from' => $filterDateFrom,
                'date_to'   => $filterDateTo,
            ];
            ?>
            <nav>
              <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?php echo buildPageUrl(1, $filterParams); ?>">
                    <span class="fas fa-angle-double-left"></span>
                  </a>
                </li>
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?php echo buildPageUrl($page - 1, $filterParams); ?>">
                    <span class="fas fa-angle-left"></span>
                  </a>
                </li>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                  <a class="page-link" href="<?php echo buildPageUrl($p, $filterParams); ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?php echo buildPageUrl($page + 1, $filterParams); ?>">
                    <span class="fas fa-angle-right"></span>
                  </a>
                </li>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?php echo buildPageUrl($totalPages, $filterParams); ?>">
                    <span class="fas fa-angle-double-right"></span>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Log Detail Modal -->
  <div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0">
        <div class="modal-header px-4 position-relative modal-shape-header bg-shape">
          <div class="position-relative z-1">
            <h4 class="mb-0 text-white">
              <span class="fas fa-history me-2"></span>Log Detail
            </h4>
            <p class="fs-10 mb-0 text-white" id="logDetailSubtitle"></p>
          </div>
          <div data-bs-theme="dark">
            <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-3">
            <div class="col-sm-4">
              <label class="form-label text-muted fs-11 text-uppercase fw-bold">Action</label>
              <div id="detailAction" class="fw-semibold"></div>
            </div>
            <div class="col-sm-4">
              <label class="form-label text-muted fs-11 text-uppercase fw-bold">Module</label>
              <div id="detailModule" class="fw-semibold"></div>
            </div>
            <div class="col-sm-4">
              <label class="form-label text-muted fs-11 text-uppercase fw-bold">Reference</label>
              <div id="detailRef" class="fw-semibold font-monospace"></div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-muted fs-11 text-uppercase fw-bold">
                <span class="fas fa-arrow-left me-1 text-warning"></span>Old Value
              </label>
              <pre id="detailOld" class="bg-soft-warning rounded p-3 fs-11 mb-0" style="max-height:280px; overflow:auto; white-space:pre-wrap; word-break:break-all;"></pre>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted fs-11 text-uppercase fw-bold">
                <span class="fas fa-arrow-right me-1 text-success"></span>New Value
              </label>
              <pre id="detailNew" class="bg-soft-success rounded p-3 fs-11 mb-0" style="max-height:280px; overflow:auto; white-space:pre-wrap; word-break:break-all;"></pre>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-body-tertiary">
          <button type="button" class="btn btn-falcon-default btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>

  <script>
  (function () {
    var logDetailModal = document.getElementById('logDetailModal');
    if (!logDetailModal) return;
    logDetailModal.addEventListener('show.bs.modal', function (e) {
      var btn = e.relatedTarget;
      document.getElementById('logDetailSubtitle').textContent = btn.dataset.action + ' — ' + btn.dataset.module;
      document.getElementById('detailAction').textContent  = btn.dataset.action  || '—';
      document.getElementById('detailModule').textContent  = btn.dataset.module  || '—';
      document.getElementById('detailRef').textContent     = btn.dataset.ref     || '—';

      function tryFormatJson(str) {
        if (!str) return '—';
        try { return JSON.stringify(JSON.parse(str), null, 2); } catch (e) { return str; }
      }
      document.getElementById('detailOld').textContent = tryFormatJson(btn.dataset.old);
      document.getElementById('detailNew').textContent = tryFormatJson(btn.dataset.new);
    });
  })();
  </script>

  <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
</body>
</html>
