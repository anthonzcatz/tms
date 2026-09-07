<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';

$totalTypes = count($accommodationTypes);
$defaultTypes = array_values(array_filter($accommodationTypes, fn($type) => (int) ($type['is_default'] ?? 0) === 1));
$usedTypes = count(array_filter($accommodationTypes, fn($type) => ((int) ($type['ticket_count'] ?? 0) + (int) ($type['order_item_count'] ?? 0)) > 0));
$defaultName = $defaultTypes[0]['name'] ?? '—';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/accommodation-types/assets/css/accommodation-types.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/accommodation-types.css'); ?>">
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

        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Accommodation <span class="text-info fw-medium">Types</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item"><a>Settings</a></li>
                            <li class="breadcrumb-item active">Accommodation Types</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto">
                    <button class="btn btn-primary" onclick="openAddAccommodationTypeModal()">
                      <span class="fas fa-plus"></span><span class="ms-2 d-none d-sm-inline">Add Accommodation Type</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php $activeWalletModule = 'accommodation-types'; include dirname(dirname(dirname(__DIR__))) . '/wallet/_partials/wallet_nav.php'; ?>

        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-md-4">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Total Types</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row">
                  <div class="col"><p class="font-sans-serif lh-1 mb-1 fs-5"><?php echo $totalTypes; ?></p></div>
                  <div class="col-auto ps-0"><span class="fas fa-bed text-primary fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-4">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Default Type</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end">
                    <div class="fs-6 fw-semibold font-sans-serif text-700 lh-1 mb-1"><?php echo htmlspecialchars((string) $defaultName, ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-star text-warning fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-4">
            <div class="card h-md-100">
              <div class="card-header pb-0"><h6 class="mb-0 mt-2">Used Types</h6></div>
              <div class="card-body d-flex flex-column justify-content-end">
                <div class="row justify-content-between">
                  <div class="col-auto align-self-end"><div class="fs-5 fw-normal font-sans-serif text-700 lh-1 mb-1"><?php echo $usedTypes; ?></div></div>
                  <div class="col-auto ps-0 mt-n4"><span class="fas fa-link text-info fs-4"></span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-12 col-md-8">
                <div class="search-box">
                  <input type="text" class="form-control search-input" id="filterSearch" placeholder="Search accommodation types..." onkeyup="applyFilters()">
                  <span class="fas fa-search search-icon"></span>
                </div>
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                  <span class="fas fa-undo me-1"></span>Reset
                </button>
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="openAddAccommodationTypeModal()">
                  <span class="fas fa-plus me-1"></span>Add
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body p-0">
            <?php if (empty($accommodationTypes)): ?>
              <div class="empty-state py-5">
                <div class="empty-state-icon"><span class="fas fa-bed"></span></div>
                <div class="empty-state-text">No Accommodation Types Found</div>
                <div class="empty-state-subtext">Click "Add Accommodation Type" to get started.</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="accommodationTypesTable">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-3">Accommodation Type</th>
                      <th>Default</th>
                      <th>Usage</th>
                      <th>Created</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($accommodationTypes as $accommodation):
                      $name = (string) ($accommodation['name'] ?? '');
                      $code = (string) ($accommodation['code'] ?? '');
                      $usageCount = (int) ($accommodation['ticket_count'] ?? 0) + (int) ($accommodation['order_item_count'] ?? 0);
                      $isUsed = $usageCount > 0;
                      $createdAt = $accommodation['created_at'] ?? null;
                      $createdLabel = $createdAt ? date('M d, Y', strtotime($createdAt)) : '—';
                      $nameForJs = htmlspecialchars(json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="accommodation-type-row" data-search="<?php echo htmlspecialchars(strtolower($name . ' ' . $code), ENT_QUOTES, 'UTF-8'); ?>">
                      <td class="ps-3 py-3">
                        <div class="d-flex align-items-center">
                          <div class="accommodation-icon bg-soft-primary text-primary me-3">
                            <span class="fas fa-bed"></span>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-muted small"><code><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></code></div>
                          </div>
                        </div>
                      </td>
                      <td class="py-3">
                        <?php if ((int) ($accommodation['is_default'] ?? 0) === 1): ?>
                          <span class="badge bg-soft-warning text-warning"><span class="fas fa-star me-1"></span>Default</span>
                        <?php else: ?>
                          <span class="text-muted small">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3">
                        <?php if ($isUsed): ?>
                          <span class="badge bg-soft-info text-info"><span class="fas fa-link me-1"></span><?php echo $usageCount; ?> record<?php echo $usageCount === 1 ? '' : 's'; ?></span>
                        <?php else: ?>
                          <span class="text-muted small">Not used</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-3"><span class="text-muted small"><?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                      <td class="py-3 text-end pe-3">
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="editAccommodationType(<?php echo (int) $accommodation['accommodation_id']; ?>)" title="Edit">
                          <span class="fas fa-edit"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-danger"
                                <?php if (!$isUsed): ?>onclick="deleteAccommodationType(<?php echo (int) $accommodation['accommodation_id']; ?>, <?php echo $nameForJs; ?>)"<?php endif; ?>
                                title="<?php echo $isUsed ? 'Cannot delete: already used in transactions' : 'Delete'; ?>"
                                <?php echo $isUsed ? 'disabled' : ''; ?>>
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
          <p class="text-muted">No accommodation types match your search.</p>
        </div>

      </div>
    </div>
  </main>
  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/admin/settings/accommodation-types/assets/js/accommodation-types.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/accommodation-types.js'); ?>"></script>

  <?php include __DIR__ . '/modals/add_accommodation_type.php'; ?>
  <?php include __DIR__ . '/modals/edit_accommodation_type.php'; ?>
  <?php include __DIR__ . '/modals/delete_accommodation_type.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
