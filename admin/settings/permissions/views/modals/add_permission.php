    <!-- Add Permission Modal -->
    <div class="modal fade" id="addPermissionModal" tabindex="-1" role="dialog" aria-labelledby="addPermissionModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
            <div class="position-relative z-1">
              <h4 class="mb-0 text-white" id="addPermissionModalLabel">
                <span class="fas fa-shield-alt me-2"></span>
                Add New Permission
              </h4>
              <p class="fs-10 mb-0 text-white">Create a new permission with hierarchical menu structure</p>
            </div>
            <div data-bs-theme="dark">
              <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <form id="addPermissionForm">
            <div class="modal-body">
              <input type="hidden" id="permission_id" name="permission_id">

              <!-- Basic Information Section -->
              <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light border-0 py-2">
                  <h6 class="mb-0 fw-bold text-primary">
                    <span class="fas fa-info-circle me-2"></span>
                    Basic Information
                  </h6>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <label for="permission_code" class="form-label fw-bold">
                        Permission Code <span class="text-danger">*</span>
                      </label>
                      <input type="text" class="form-control" id="permission_code" name="permission_code" required placeholder="e.g., VIEW_CRM">
                      <small class="text-muted">Use uppercase with underscores (e.g., VIEW_CRM)</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="permission_name" class="form-label fw-bold">
                        Permission Name <span class="text-danger">*</span>
                      </label>
                      <input type="text" class="form-control" id="permission_name" name="permission_name" required placeholder="e.g., DASHBOARD">
                      <small class="text-muted">Display name in sidebar (uppercase)</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="module_name" class="form-label fw-bold">
                        Module Name <span class="text-danger">*</span>
                      </label>
                      <input type="text" class="form-control" id="module_name" name="module_name" required placeholder="e.g., DASHBOARD">
                      <small class="text-muted">Section label for grouping (uppercase)</small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hierarchy Section -->
              <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light border-0 py-2">
                  <h6 class="mb-0 fw-bold text-primary">
                    <span class="fas fa-sitemap me-2"></span>
                    Menu Hierarchy
                  </h6>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="menu_level" class="form-label fw-bold">Menu Level</label>
                      <select class="form-select" id="menu_level" name="menu_level" onchange="filterParentOptions()">
                        <option value="1">Level 1 - Parent (Root)</option>
                        <option value="2">Level 2 - Child</option>
                        <option value="3">Level 3 - Grandchild</option>
                      </select>
                      <small class="text-muted">Select level to filter parent options</small>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="menu_order" class="form-label fw-bold">Menu Order</label>
                      <input type="number" class="form-control" id="menu_order" name="menu_order" value="0" min="0">
                      <small class="text-muted">Lower numbers appear first in the menu</small>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="parent_permission_id" class="form-label fw-bold">
                      Parent Permission <span id="parent_required" class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="parent_permission_id" name="parent_permission_id">
                      <option value="">-- Select Parent --</option>
                      <?php
                      function renderPermissionOptions($permissions, $level = 0) {
                          foreach ($permissions as $permission) {
                              $levelLabel = $level === 0 ? 'L1' : ($level === 1 ? 'L2' : 'L3');
                              $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
                              ?>
                              <option value="<?php echo $permission['permission_id']; ?>" 
                                      data-level="<?php echo $level + 1; ?>"
                                      data-name="<?php echo htmlspecialchars($permission['permission_name']); ?>"
                                      class="parent-option">
                                  <?php echo $indent . '<span class="badge bg-secondary me-2">' . $levelLabel . '</span> ' . htmlspecialchars($permission['permission_name']); ?>
                              </option>
                              <?php
                              if (!empty($permission['children'])) {
                                  renderPermissionOptions($permission['children'], $level + 1);
                              }
                          }
                      }
                      if (isset($permissionsTree)) {
                          renderPermissionOptions($permissionsTree);
                      }
                      ?>
                    </select>
                    <div class="alert alert-info py-2 mt-2 mb-0">
                      <small class="mb-0">
                        <strong>L1 (Parent):</strong> Top-level menu items (e.g., Dashboard, Users)<br>
                        <strong>L2 (Child):</strong> Items under a parent (e.g., Analytics under Dashboard)<br>
                        <strong>L3 (Grandchild):</strong> Nested items (e.g., Report Type under Analytics)
                      </small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Menu Settings Section -->
              <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light border-0 py-2">
                  <h6 class="mb-0 fw-bold text-primary">
                    <span class="fas fa-cog me-2"></span>
                    Menu Settings
                  </h6>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 mb-3" id="menu_icon_wrapper">
                      <label for="menu_icon" class="form-label fw-bold">
                        Menu Icon
                      </label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-icons"></i></span>
                        <input type="text" class="form-control" id="menu_icon" name="menu_icon" placeholder="e.g., fas fa-chart-pie">
                      </div>
                      <small class="text-muted">Font Awesome icon class (e.g., fas fa-chart-pie) - Optional</small>
                    </div>
                    <div class="col-md-6 mb-3" id="menu_url_wrapper">
                      <label for="menu_url" class="form-label fw-bold">
                        Menu URL
                      </label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                        <input type="text" class="form-control" id="menu_url" name="menu_url" placeholder="e.g., admin/dashboard">
                      </div>
                      <small class="text-muted">URL path from admin (e.g., admin/dashboard) - Optional</small>
                    </div>
                  </div>
                  <div class="form-check form-switch form-check-lg">
                    <input class="form-check-input" type="checkbox" id="is_menu_item" name="is_menu_item" checked>
                    <label class="form-check-label fw-bold" for="is_menu_item">
                      <span class="fas fa-eye me-2"></span>
                      Show in Sidebar Menu
                    </label>
                    <small class="text-muted d-block ms-5">Uncheck to hide this item from the sidebar menu</small>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <span class="fas fa-times me-2"></span>Cancel
              </button>
              <button type="submit" class="btn btn-primary">
                <span class="fas fa-save me-2"></span>Save Permission
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
