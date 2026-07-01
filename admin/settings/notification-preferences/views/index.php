<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/notification-preferences/assets/css/notification-preferences.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/notification-preferences.css'); ?>">
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
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);">
              </div>
              <!--/.bg-holder-->
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Notification <span class="text-info fw-medium">Preferences</span></h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings/">Settings</a></li>
                            <li class="breadcrumb-item active">Notification Preferences</li>
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

        <!-- Info Card -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2" style="cursor:pointer;" onclick="toggleHowItWorks()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0"><span class="fas fa-info-circle me-2 text-info"></span>How it works: Notification Preferences</h6>
              <span class="fas fa-chevron-down" id="howItWorksIcon"></span>
            </div>
          </div>
          <div class="card-body" id="howItWorksContent" style="display:none;">
            <ul class="mb-0">
              <li>Customize which notifications you receive and through which channels</li>
              <li>Available channels: <strong>In-App</strong> (bell icon), <strong>Email</strong>, <strong>SMS</strong>, <strong>Push</strong></li>
              <li>Disable specific notification types if you don't want to receive them</li>
              <li>Changes take effect immediately for new notifications</li>
            </ul>
          </div>
        </div>

        <!-- System Threshold Settings -->
        <div class="card mb-3">
          <div class="card-header bg-light py-2" style="cursor:pointer;" onclick="toggleThresholdSettings()">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0"><span class="fas fa-cog me-2 text-warning"></span>System Notification Thresholds</h6>
              <span class="fas fa-chevron-down" id="thresholdSettingsIcon"></span>
            </div>
          </div>
          <div class="card-body" id="thresholdSettingsContent" style="display:none;">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="newSettingKey">Setting Key</label>
                <input type="text" class="form-control" id="newSettingKey" placeholder="e.g., wallet_transaction_threshold">
                <div class="form-text fs-10">Unique identifier for the setting</div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold" for="newSettingValue">Threshold Value (₱)</label>
                <input type="number" class="form-control" id="newSettingValue" step="0.01" min="0" placeholder="10000">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="newSettingDescription">Description</label>
                <input type="text" class="form-control" id="newSettingDescription" placeholder="Description of the setting">
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" onclick="addSystemThreshold()">
                  <span class="fas fa-plus"></span>
                </button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Setting Key</th>
                    <th>Value (₱)</th>
                    <th>Description</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="thresholdSettingsTable">
                  <tr>
                    <td colspan="4" class="text-center py-3">
                      <span class="fas fa-spinner fa-spin"></span> Loading...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <script>
        function toggleThresholdSettings() {
          const c = document.getElementById('thresholdSettingsContent');
          const i = document.getElementById('thresholdSettingsIcon');
          const open = c.style.display !== 'none';
          c.style.display = open ? 'none' : 'block';
          i.classList.toggle('fa-chevron-down', open);
          i.classList.toggle('fa-chevron-up', !open);
          if (!open) loadSystemThresholds();
        }

        async function loadSystemThresholds() {
          try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/system-notification-settings/');
            const result = await response.json();
            if (result.success) {
              renderThresholdTable(result.data);
            }
          } catch (error) {
            console.error('Failed to load system thresholds:', error);
            document.getElementById('thresholdSettingsTable').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Failed to load</td></tr>';
          }
        }

        function renderThresholdTable(settings) {
          const tbody = document.getElementById('thresholdSettingsTable');
          if (!settings || settings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No settings configured</td></tr>';
            return;
          }
          
          let html = '';
          settings.forEach(setting => {
            html += `
              <tr data-setting-key="${setting.setting_key}">
                <td><code>${setting.setting_key}</code></td>
                <td><strong>₱${parseFloat(setting.setting_value).toFixed(2)}</strong></td>
                <td><small class="text-muted">${setting.description || '-'}</small></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick="editSystemThreshold('${setting.setting_key}')">
                    <span class="fas fa-edit"></span>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteSystemThreshold('${setting.setting_key}')">
                    <span class="fas fa-trash"></span>
                  </button>
                </td>
              </tr>
            `;
          });
          tbody.innerHTML = html;
        }

        async function addSystemThreshold() {
          const key = document.getElementById('newSettingKey').value.trim();
          const value = document.getElementById('newSettingValue').value;
          const description = document.getElementById('newSettingDescription').value.trim();
          
          if (!key || !value) {
            showToast('error', 'Validation Error', 'Please enter setting key and value');
            return;
          }
          
          try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/system-notification-settings/', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
              body: JSON.stringify({ setting_key: key, setting_value: value, description })
            });
            const result = await response.json();
            if (result.success) {
              document.getElementById('newSettingKey').value = '';
              document.getElementById('newSettingValue').value = '';
              document.getElementById('newSettingDescription').value = '';
              loadSystemThresholds();
              showToast('success', 'Success', 'Threshold setting added successfully');
            } else {
              showToast('error', 'Error', 'Failed to add: ' + result.error);
            }
          } catch (error) {
            showToast('error', 'Error', 'Failed to add: ' + error.message);
          }
        }

        async function editSystemThreshold(key) {
          const row = document.querySelector(`tr[data-setting-key="${key}"]`);
          const currentValue = row.querySelector('td:nth-child(2)').textContent.replace('₱', '');
          const currentDesc = row.querySelector('td:nth-child(3)').textContent.trim();
          
          // Show edit modal
          const modalHtml = `
            <div class="modal fade" id="editThresholdModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Threshold Setting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label fw-semibold">Setting Key</label>
                      <input type="text" class="form-control" id="editSettingKey" value="${key}" readonly>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold">Threshold Value (₱)</label>
                      <input type="number" class="form-control" id="editSettingValue" step="0.01" min="0" value="${currentValue}">
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold">Description</label>
                      <input type="text" class="form-control" id="editSettingDescription" value="${currentDesc === '-' ? '' : currentDesc}">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveThresholdEdit()">Save Changes</button>
                  </div>
                </div>
              </div>
            </div>
          `;
          
          // Remove existing modal if any
          const existingModal = document.getElementById('editThresholdModal');
          if (existingModal) existingModal.remove();
          
          // Add modal to body
          document.body.insertAdjacentHTML('beforeend', modalHtml);
          
          // Show modal
          const modal = new bootstrap.Modal(document.getElementById('editThresholdModal'));
          modal.show();
          
          // Store key for save function
          window.currentEditKey = key;
        }

        async function saveThresholdEdit() {
          const key = window.currentEditKey;
          const value = document.getElementById('editSettingValue').value;
          const description = document.getElementById('editSettingDescription').value.trim();
          
          if (!value) {
            showToast('error', 'Validation Error', 'Please enter a threshold value');
            return;
          }
          
          try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/system-notification-settings/', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
              body: JSON.stringify({ setting_key: key, setting_value: value, description })
            });
            const result = await response.json();
            if (result.success) {
              // Close modal
              const modal = bootstrap.Modal.getInstance(document.getElementById('editThresholdModal'));
              modal.hide();
              // Reload table
              loadSystemThresholds();
              showToast('success', 'Success', 'Threshold setting updated successfully');
            } else {
              showToast('error', 'Error', 'Failed to update: ' + result.error);
            }
          } catch (error) {
            showToast('error', 'Error', 'Failed to update: ' + error.message);
          }
        }

        async function deleteSystemThreshold(key) {
          if (!confirm('Are you sure you want to delete this setting?')) return;
          
          try {
            const response = await fetch(`<?php echo BASE_URL; ?>/api/system-notification-settings/?setting_key=${encodeURIComponent(key)}`, {
              method: 'DELETE',
              headers: { 'X-CSRF-Token': window.CSRF_TOKEN }
            });
            const result = await response.json();
            if (result.success) {
              loadSystemThresholds();
              showToast('success', 'Success', 'Threshold setting deleted successfully');
            } else {
              showToast('error', 'Error', 'Failed to delete: ' + result.error);
            }
          } catch (error) {
            showToast('error', 'Error', 'Failed to delete: ' + error.message);
          }
        }
        </script>
        <script>
        // Toast notification helper for threshold settings
        function showToast(type, title, message) {
          const toastContainer = document.querySelector('.toast-container') || createToastContainer();
          const toastId = 'toast_' + Date.now();
          const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-primary';
          
          const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
              <div class="d-flex">
                <div class="toast-body">
                  <strong>${title}</strong><br>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
              </div>
            </div>
          `;
          
          toastContainer.insertAdjacentHTML('beforeend', toastHtml);
          const toastElement = document.getElementById(toastId);
          const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
          toast.show();
          
          toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
          });
        }
        
        function createToastContainer() {
          const container = document.createElement('div');
          container.className = 'toast-container position-fixed top-0 end-0 p-3';
          container.style.zIndex = '1050';
          document.body.appendChild(container);
          return container;
        }
        </script>
        <script>
        function toggleHowItWorks() {
          const c = document.getElementById('howItWorksContent');
          const i = document.getElementById('howItWorksIcon');
          const open = c.style.display !== 'none';
          c.style.display = open ? 'none' : 'block';
          i.classList.toggle('fa-chevron-down', open);
          i.classList.toggle('fa-chevron-up', !open);
        }
        </script>

        <!-- Notification Preferences List -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Notification Types</h5>
              </div>
              <div class="card-body">
                <?php if (empty($notificationTypes)): ?>
                  <div class="text-center py-5">
                    <span class="fas fa-bell-slash text-muted fs-1 mb-3 d-block"></span>
                    <p class="text-muted">No notification types available</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover mb-0">
                      <thead>
                        <tr>
                          <th style="width: 30%;">Notification Type</th>
                          <th style="width: 50%;">Channels</th>
                          <th style="width: 20%;">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($notificationTypes as $type): ?>
                          <?php
                            $pref = $preferencesMap[$type['type']] ?? ['channels' => ['in-app'], 'enabled' => true];
                            $prefChannels = $pref['channels'];
                            $enabled = $pref['enabled'];
                          ?>
                          <tr data-type="<?php echo htmlspecialchars($type['type']); ?>">
                            <td>
                              <div class="fw-bold"><?php echo htmlspecialchars($type['type']); ?></div>
                              <small class="text-muted"><?php echo htmlspecialchars($type['title_template']); ?></small>
                            </td>
                            <td>
                              <div class="d-flex flex-wrap gap-2 align-items-center">
                                <?php foreach ($prefChannels as $channel): ?>
                                  <?php
                                    $channelIcon = match($channel) {
                                      'email' => 'fa-envelope',
                                      'sms' => 'fa-sms',
                                      'push' => 'fa-bell',
                                      'in-app' => 'fa-desktop',
                                      default => 'fa-circle'
                                    };
                                  ?>
                                  <span class="badge bg-primary channel-badge" data-channel="<?php echo htmlspecialchars($channel); ?>">
                                    <span class="fas <?php echo $channelIcon; ?> me-1"></span><?php echo ucfirst(htmlspecialchars($channel)); ?>
                                    <span class="ms-1 cursor-pointer remove-channel" data-type="<?php echo htmlspecialchars($type['type']); ?>" data-channel="<?php echo htmlspecialchars($channel); ?>">&times;</span>
                                  </span>
                                <?php endforeach; ?>
                                <div class="dropdown-wrapper position-relative">
                                  <button type="button" class="btn btn-sm btn-outline-primary add-channel-btn" data-type="<?php echo htmlspecialchars($type['type']); ?>">
                                    <span class="fas fa-plus"></span> Add
                                  </button>
                                  <div class="channel-dropdown dropdown-menu p-2" id="channelDropdown_<?php echo htmlspecialchars($type['type']); ?>" style="display:none; position: absolute; top: 100%; left: 0; z-index: 1000; min-width: 150px;">
                                    <?php foreach ($channels as $availableChannel): ?>
                                      <?php if (!in_array($availableChannel['name'], $prefChannels)): ?>
                                        <a class="dropdown-item add-channel-link" href="#" data-type="<?php echo htmlspecialchars($type['type']); ?>" data-channel="<?php echo htmlspecialchars($availableChannel['name']); ?>">
                                          <span class="fas fa-<?php echo match($availableChannel['name']) {
                                            'email' => 'envelope',
                                            'sms' => 'sms',
                                            'push' => 'bell',
                                            'in-app' => 'desktop',
                                            default => 'circle'
                                          }; ?> me-2"></span><?php echo ucfirst(htmlspecialchars($availableChannel['name'])); ?>
                                        </a>
                                      <?php endif; ?>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              </div>
                            </td>
                            <td>
                              <div class="form-check form-switch">
                                <input class="form-check-input preference-toggle" type="checkbox" 
                                       id="toggle_<?php echo htmlspecialchars($type['type']); ?>"
                                       data-type="<?php echo htmlspecialchars($type['type']); ?>"
                                       <?php echo $enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="toggle_<?php echo htmlspecialchars($type['type']); ?>">
                                  <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                                </label>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/admin/settings/notification-preferences/assets/js/notification-preferences.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/notification-preferences.js'); ?>"></script>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
    <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  </body>
</html>
