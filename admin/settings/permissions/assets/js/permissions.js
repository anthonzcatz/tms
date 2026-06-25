/**
 * Permission Management JavaScript
 * Handles all client-side interactions for permission management
 */

const API_BASE = window.BASE_URL + '/api/permissions';
const ROLES_API_BASE = window.BASE_URL + '/api/roles';

/**
 * Filter permissions table based on search input
 */
function filterPermissions() {
  const searchInput = document.getElementById('permissionsSearch');
  const permissionsTable = document.getElementById('permissionsTable');
  
  if (!searchInput || !permissionsTable) {
    console.error('Search input or permissions table not found');
    return;
  }
  
  const searchTerm = searchInput.value.toLowerCase().trim();
  const tableRows = permissionsTable.querySelectorAll('tbody.list tr');
  
  tableRows.forEach(function(row) {
    const codeCell = row.querySelector('.code');
    const nameCell = row.querySelector('.name');
    const moduleCell = row.querySelector('.module');
    const urlCell = row.querySelector('.url');
    
    const code = codeCell ? codeCell.textContent.toLowerCase() : '';
    const name = nameCell ? nameCell.textContent.toLowerCase() : '';
    const module = moduleCell ? moduleCell.textContent.toLowerCase() : '';
    const url = urlCell ? urlCell.textContent.toLowerCase() : '';
    
    const matches = code.includes(searchTerm) || 
                    name.includes(searchTerm) || 
                    module.includes(searchTerm) || 
                    url.includes(searchTerm);
    
    if (matches || searchTerm === '') {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

/**
 * Select a module to display its permissions
 */
function selectModule(moduleName, roleId) {
  // Update active state in module list
  const moduleList = document.getElementById(`moduleList-${roleId}`);
  if (moduleList) {
    moduleList.querySelectorAll('.module-item').forEach(item => {
      item.classList.remove('active');
      if (item.dataset.module === moduleName) {
        item.classList.add('active');
      }
    });
  }
  
  // Update module title
  const moduleTitle = document.getElementById(`moduleTitle-${roleId}`);
  if (moduleTitle) {
    moduleTitle.innerHTML = `<span class="fas fa-folder me-2"></span>${moduleName}`;
  }
  
  // Show/hide permission sections
  const permissionsContainer = document.getElementById(`permissionsContainer-${roleId}`);
  if (permissionsContainer) {
    permissionsContainer.querySelectorAll('.module-permissions').forEach(section => {
      if (section.dataset.module === moduleName) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    });
  }
  
  // Clear permission search when switching modules
  const searchInput = document.getElementById(`permissionSearch-${roleId}`);
  if (searchInput) {
    searchInput.value = '';
  }
  
  // Save selected module to localStorage
  localStorage.setItem(`permissions_selected_module_${roleId}`, moduleName);
}

/**
 * Filter modules based on search input
 */
function filterModules(roleId) {
  const searchInput = document.getElementById(`moduleSearch-${roleId}`);
  const moduleList = document.getElementById(`moduleList-${roleId}`);
  
  if (!searchInput || !moduleList) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  const moduleItems = moduleList.querySelectorAll('.module-item');
  
  moduleItems.forEach(item => {
    const moduleName = item.dataset.module.toLowerCase();
    if (moduleName.includes(searchTerm)) {
      item.classList.remove('d-none');
    } else {
      item.classList.add('d-none');
    }
  });
}

function cssEscapeValue(value) {
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(value);
  }
  return value.replace(/([ #.;,:!+*?^${}()|[\]\\])/g, '\\$1');
}

function updateModuleStats(moduleName) {
  const statsContainer = document.getElementById('permissionModuleStats');
  if (!statsContainer || !moduleName) return;
  const escaped = cssEscapeValue(moduleName);
  const section = document.querySelector(`.permission-module-section[data-module="${escaped}"]`);
  const listItem = document.querySelector(`.permission-module-item[data-module="${escaped}"]`);
  const visible = section?.dataset.visibleCount || listItem?.dataset.visibleCount || 0;
  const hidden = section?.dataset.hiddenCount || listItem?.dataset.hiddenCount || 0;
  const actions = section?.dataset.actionCount || listItem?.dataset.actionCount || 0;

  statsContainer.dataset.activeModule = moduleName;
  const visibleEl = statsContainer.querySelector('[data-stat="visible"]');
  const hiddenEl = statsContainer.querySelector('[data-stat="hidden"]');
  const actionEl = statsContainer.querySelector('[data-stat="actions"]');
  if (visibleEl) visibleEl.textContent = visible;
  if (hiddenEl) hiddenEl.textContent = hidden;
  if (actionEl) actionEl.textContent = actions;
}

function refreshModuleStatsFromSection(section) {
  if (!section) return;
  const moduleName = section.dataset.module;
  const visible = section.querySelectorAll('tbody tr[data-visible="1"]').length;
  const hidden = section.querySelectorAll('tbody tr[data-visible="0"]').length;
  const actions = section.querySelectorAll('tbody tr[data-permission-type="action"]').length;

  section.dataset.visibleCount = visible;
  section.dataset.hiddenCount = hidden;
  section.dataset.actionCount = actions;

  const escaped = cssEscapeValue(moduleName);
  const listItem = document.querySelector(`.permission-module-item[data-module="${escaped}"]`);
  if (listItem) {
    listItem.dataset.visibleCount = visible;
    listItem.dataset.hiddenCount = hidden;
    listItem.dataset.actionCount = actions;
  }

  updateModuleStats(moduleName);
}

function handleVisibilityFilterChange(filterValue) {
  const statsContainer = document.getElementById('permissionModuleStats');
  const activeModule = statsContainer?.dataset.activeModule;
  if (!activeModule) return;

  const escaped = cssEscapeValue(activeModule);
  const section = document.querySelector(`.permission-module-section[data-module="${escaped}"]`);
  if (!section) return;

  const allRows = section.querySelectorAll('tbody tr.permission-row');

  allRows.forEach(row => {
    const isChildRow = row.classList.contains('child-row');
    const visible = row.dataset.visible === '1';

    if (filterValue === 'all') {
      if (!isChildRow) {
        row.style.display = '';
      }
    } else if (filterValue === 'visible') {
      if (!isChildRow) {
        row.style.display = visible ? '' : 'none';
      } else {
        row.style.display = visible ? '' : 'none';
      }
    } else if (filterValue === 'hidden') {
      if (!isChildRow) {
        row.style.display = !visible ? '' : 'none';
      } else {
        row.style.display = !visible ? '' : 'none';
      }
    }
  });

  // Collapse all open tree nodes when filter changes
  if (filterValue !== 'all') {
    section.querySelectorAll('.permission-tree-toggle[aria-expanded="true"]').forEach(btn => {
      btn.setAttribute('aria-expanded', 'false');
    });
    section.querySelectorAll('tr.child-row').forEach(row => {
      if (filterValue === 'all') {
        row.classList.add('d-none');
      }
    });
  } else {
    // On "all" reset, collapse all child rows back to hidden
    section.querySelectorAll('tr.child-row').forEach(row => {
      row.classList.add('d-none');
      row.style.display = '';
    });
    section.querySelectorAll('.permission-tree-toggle').forEach(btn => {
      btn.setAttribute('aria-expanded', 'false');
    });
  }
}

function setActionFeedback(message, className = 'text-muted') {
  const feedback = document.getElementById('actionToggleFeedback');
  if (!feedback) return;
  if (!message) {
    feedback.textContent = '';
    feedback.className = 'text-muted';
    return;
  }
  feedback.className = className;
  feedback.textContent = message;
}

/**
 * Filter permissions based on search input in Role Permissions Matrix
 */
function filterRolePermissions(roleId) {
  const searchInput = document.getElementById(`permissionSearch-${roleId}`);
  const permissionsContainer = document.getElementById(`permissionsContainer-${roleId}`);
  
  if (!searchInput || !permissionsContainer) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  const visibleSection = permissionsContainer.querySelector('.module-permissions:not(.d-none)');
  
  if (!visibleSection) return;
  
  const permissionRows = visibleSection.querySelectorAll('.d-flex.align-items-center.justify-content-between');
  
  permissionRows.forEach(row => {
    const permissionCode = row.querySelector('.fw-medium')?.textContent.toLowerCase() || '';
    const permissionName = row.querySelector('.text-muted')?.textContent.toLowerCase() || '';
    
    if (permissionCode.includes(searchTerm) || permissionName.includes(searchTerm)) {
      row.classList.remove('d-none');
    } else {
      row.classList.add('d-none');
    }
  });
}

/**
 * Restore selected module from localStorage on page load
 */
function restoreSelectedModule(roleId) {
  const savedModule = localStorage.getItem(`permissions_selected_module_${roleId}`);
  if (savedModule) {
    selectModule(savedModule, roleId);
  }
}

/**
 * Save active role tab to localStorage
 */
function saveActiveRoleTab(roleCode) {
  localStorage.setItem('permissions_active_role', roleCode);
}

/**
 * Restore active role tab from localStorage on page load
 */
function restoreActiveRoleTab() {
  const savedRole = localStorage.getItem('permissions_active_role');
  if (savedRole) {
    const tabButton = document.querySelector(`button[data-bs-target="#role-${savedRole}"]`);
    if (tabButton) {
      const tab = new bootstrap.Tab(tabButton);
      tab.show();
    }
  }
}

/**
 * Toggle between module view and all permissions view
 */
function toggleView(viewType, roleId) {
  const moduleView = document.getElementById(`moduleView-${roleId}`);
  const allView = document.getElementById(`allView-${roleId}`);
  const moduleBtn = document.getElementById(`viewModuleBtn-${roleId}`);
  const allBtn = document.getElementById(`viewAllBtn-${roleId}`);
  
  if (viewType === 'module') {
    moduleView.classList.remove('d-none');
    allView.classList.add('d-none');
    moduleBtn.classList.add('active');
    allBtn.classList.remove('active');
  } else {
    moduleView.classList.add('d-none');
    allView.classList.remove('d-none');
    moduleBtn.classList.remove('active');
    allBtn.classList.add('active');
  }
}

/**
 * Select a module in the Permissions by Module tab
 */
function selectPermissionModule(moduleName) {
  // Update active state in module list
  const moduleList = document.getElementById('permissionModuleList');
  if (moduleList) {
    moduleList.querySelectorAll('.permission-module-item').forEach(item => {
      item.classList.remove('active');
      if (item.dataset.module === moduleName) {
        item.classList.add('active');
      }
    });
  }
  
  // Update module title
  const moduleTitle = document.getElementById('permissionModuleTitle');
  if (moduleTitle) {
    moduleTitle.innerHTML = `<span class="fas fa-folder me-2"></span>${moduleName}`;
  }
  
  // Show/hide permission sections
  const permissionsContainer = document.getElementById('permissionModuleContainer');
  if (permissionsContainer) {
    permissionsContainer.querySelectorAll('.permission-module-section').forEach(section => {
      if (section.dataset.module === moduleName) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    });
  }
  
  // Clear search when switching modules
  const searchInput = document.getElementById('permissionModuleSearch');
  if (searchInput) {
    searchInput.value = '';
  }

  // Reset visibility filter
  const visFilter = document.getElementById('sidebarVisibilityFilter');
  if (visFilter) visFilter.value = 'all';

  // Collapse all open tree nodes in the newly-selected section
  const permissionsContainer2 = document.getElementById('permissionModuleContainer');
  if (permissionsContainer2) {
    permissionsContainer2.querySelectorAll('.permission-module-section').forEach(section => {
      section.querySelectorAll('tr.child-row').forEach(row => {
        row.classList.add('d-none');
        row.style.display = '';
      });
      section.querySelectorAll('.permission-tree-toggle').forEach(btn => {
        btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Save selected module to localStorage
  localStorage.setItem('permissions_by_module_selected', moduleName);

  updateModuleStats(moduleName);
  setActionFeedback('');
}

/**
 * Restore selected module in Permissions by Module tab on page load
 */
function restorePermissionModule() {
  const savedModule = localStorage.getItem('permissions_by_module_selected');
  if (savedModule) {
    selectPermissionModule(savedModule);
    return true;
  }
  return false;
}

/**
 * Filter permissions in the Permissions by Module tab
 */
function filterPermissionModule() {
  const searchInput = document.getElementById('permissionModuleSearch');
  const permissionsContainer = document.getElementById('permissionModuleContainer');
  
  if (!searchInput || !permissionsContainer) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  const visibleSection = permissionsContainer.querySelector('.permission-module-section:not(.d-none)');
  
  if (!visibleSection) return;
  
  const tableRows = visibleSection.querySelectorAll('tbody tr');
  
  tableRows.forEach(row => {
    const code = (row.dataset.permissionCode || '').toLowerCase();
    const nameCell = row.querySelector('td[data-column="name"]');
    const name = nameCell ? nameCell.textContent.toLowerCase() : '';
    
    if (code.includes(searchTerm) || name.includes(searchTerm)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

/**
 * Filter modules in the Permissions by Module tab sidebar
 */
function filterPermissionModules() {
  const searchInput = document.getElementById('permissionModuleSidebarSearch');
  const moduleList = document.getElementById('permissionModuleList');
  
  if (!searchInput || !moduleList) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  const moduleItems = moduleList.querySelectorAll('.permission-module-item');
  
  moduleItems.forEach(item => {
    const moduleName = item.dataset.module.toLowerCase();
    const permissionIndex = (item.dataset.permissionIndex || '').toLowerCase();
    if (!searchTerm || moduleName.includes(searchTerm) || permissionIndex.includes(searchTerm)) {
      item.classList.remove('d-none');
    } else {
      item.classList.add('d-none');
    }
  });
}

/**
 * Enable all permissions for the currently selected module
 */
function enableModulePermissions(roleId) {
  const permissionsContainer = document.getElementById(`permissionsContainer-${roleId}`);
  if (!permissionsContainer) return;
  
  const visibleSection = permissionsContainer.querySelector('.module-permissions:not(.d-none)');
  if (!visibleSection) return;
  
  const toggles = visibleSection.querySelectorAll('.permission-toggle');
  const uncheckedToggles = [];
  
  toggles.forEach(toggle => {
    if (!toggle.checked) {
      uncheckedToggles.push(toggle);
    }
  });
  
  if (uncheckedToggles.length === 0) {
    const toast = new bootstrap.Toast(document.getElementById('successToast'));
    toast.show();
    return;
  }
  
  // Get permission IDs
  const permissionIds = Array.from(uncheckedToggles).map(t => parseInt(t.dataset.permissionId));
  
  // Get fresh CSRF token
  const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  // Use bulk API endpoint
  fetch(`${API_BASE}/bulk-assign`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': freshToken
    },
    body: JSON.stringify({
      role_id: roleId,
      permission_ids: permissionIds
    })
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update CSRF token in meta tag if returned
      if (data.csrf_token) {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
          metaTag.setAttribute('content', data.csrf_token);
        }
      }
      // Update UI
      uncheckedToggles.forEach(toggle => toggle.checked = true);
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

/**
 * Disable all permissions for the currently selected module
 */
function disableModulePermissions(roleId) {
  const permissionsContainer = document.getElementById(`permissionsContainer-${roleId}`);
  if (!permissionsContainer) return;
  
  const visibleSection = permissionsContainer.querySelector('.module-permissions:not(.d-none)');
  if (!visibleSection) return;
  
  const toggles = visibleSection.querySelectorAll('.permission-toggle');
  const checkedToggles = [];
  
  toggles.forEach(toggle => {
    if (toggle.checked) {
      checkedToggles.push(toggle);
    }
  });
  
  if (checkedToggles.length === 0) {
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'No permissions are currently enabled in this module';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
    return;
  }
  
  // Get permission IDs
  const permissionIds = Array.from(checkedToggles).map(t => parseInt(t.dataset.permissionId));
  
  // Get fresh CSRF token
  const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  // Use bulk API endpoint
  fetch(`${API_BASE}/bulk-unassign`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': freshToken
    },
    body: JSON.stringify({
      role_id: roleId,
      permission_ids: permissionIds
    })
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update CSRF token in meta tag if returned
      if (data.csrf_token) {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
          metaTag.setAttribute('content', data.csrf_token);
        }
      }
      // Update UI
      checkedToggles.forEach(toggle => toggle.checked = false);
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}


/**
 * Filter parent permission options based on selected menu level
 */
function filterParentOptions() {
  const menuLevelSelect = document.getElementById('menu_level');
  const parentSelect = document.getElementById('parent_permission_id');
  const parentRequired = document.getElementById('parent_required');
  const menuIconWrapper = document.getElementById('menu_icon_wrapper');
  const menuUrlWrapper = document.getElementById('menu_url_wrapper');
  const menuIconInput = document.getElementById('menu_icon');
  const menuUrlInput = document.getElementById('menu_url');
  const iconRequired = document.getElementById('icon_required');
  const urlRequired = document.getElementById('url_required');
  
  if (menuLevelSelect && parentSelect) {
    const selectedLevel = parseInt(menuLevelSelect.value);
    
    // Show options based on selected level using Select2
    if (selectedLevel === 1) {
      // Level 1 - no parent needed
      if ($(parentSelect).data('select2')) {
        $(parentSelect).val(null).trigger('change');
        $(parentSelect).select2('enable', false);
      } else {
        parentSelect.value = '';
        parentSelect.disabled = true;
      }
      if (parentRequired) parentRequired.style.display = 'none';
      
      // Level 1 needs Menu Icon
      if (menuIconWrapper) menuIconWrapper.style.display = 'block';
      if (menuIconInput) menuIconInput.required = false;
      if (iconRequired) iconRequired.style.display = 'none';
      
      // Level 1 can have Menu URL
      if (menuUrlWrapper) menuUrlWrapper.style.display = 'block';
      if (menuUrlInput) menuUrlInput.required = false;
      if (urlRequired) urlRequired.style.display = 'none';
      
    } else if (selectedLevel === 2 || selectedLevel === 3) {
      // Level 2 or 3 - show appropriate parent options
      // Destroy existing Select2 if it exists
      if ($(parentSelect).data('select2')) {
        $(parentSelect).select2('destroy');
      }
      
      // Enable the select
      parentSelect.disabled = false;
      if (parentRequired) parentRequired.style.display = 'inline';
      
      // Determine target parent level
      const targetLevel = selectedLevel === 2 ? 1 : 2;
      
      // Initialize Select2 with templateResults to filter options
      $(parentSelect).select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '-- Select Parent --',
        allowClear: true,
        dropdownParent: $('#addPermissionModal'),
        templateResult: function(result) {
          if (!result.id) {
            return result.text;
          }
          const optionElement = result.element;
          const optionLevel = parseInt($(optionElement).attr('data-level'));
          
          // Only show options that match the target level
          if (optionLevel === targetLevel) {
            return result.text;
          } else {
            return null; // Hide this option
          }
        }
      });
      
      // Sync Select2 with our filter function
      $(parentSelect).on('select2:select', function() {
        autoSetMenuLevel();
      });
      
      $(parentSelect).on('select2:clear', function() {
        autoSetMenuLevel();
      });
      
      // Level 2 and 3 don't need Menu Icon (only Level 1)
      if (menuIconWrapper) menuIconWrapper.style.display = 'none';
      if (menuIconInput) {
        menuIconInput.required = false;
        menuIconInput.value = '';
      }
      if (iconRequired) iconRequired.style.display = 'none';
      
      // Level 2 and 3 need Menu URL
      if (menuUrlWrapper) menuUrlWrapper.style.display = 'block';
      if (menuUrlInput) menuUrlInput.required = false;
      if (urlRequired) urlRequired.style.display = 'none';
    }
  }
}

/**
 * Auto-set menu level based on parent permission selection
 */
function autoSetMenuLevel() {
  const parentSelect = document.getElementById('parent_permission_id');
  const menuLevelSelect = document.getElementById('menu_level');
  
  if (parentSelect && menuLevelSelect) {
    const selectedOption = parentSelect.options[parentSelect.selectedIndex];
    const parentLevel = selectedOption.getAttribute('data-level');
    
    if (parentLevel) {
      // Set menu level to parent level + 1
      const newLevel = parseInt(parentLevel) + 1;
      menuLevelSelect.value = newLevel > 3 ? 3 : newLevel;
      // Trigger filter to show correct parent options
      filterParentOptions();
    } else {
      // No parent selected, set to Level 1
      menuLevelSelect.value = 1;
      filterParentOptions();
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  // Search is handled by inline oninput handler calling filterPermissions()

  // Restore active tab from localStorage
  const activeTab = localStorage.getItem('permissionsActiveTab');
  if (activeTab) {
    const tabElement = document.querySelector(`[data-bs-target="${activeTab}"]`);
    if (tabElement) {
      const tabInstance = new bootstrap.Tab(tabElement);
      tabInstance.show();
    }
  }

  // Save active tab to localStorage when clicked
  const tabButtons = document.querySelectorAll('#permissionTabs button[data-bs-toggle="tab"]');
  tabButtons.forEach(button => {
    button.addEventListener('shown.bs.tab', function(e) {
      const target = e.target.getAttribute('data-bs-target');
      localStorage.setItem('permissionsActiveTab', target);
    });
  });

  // Permission toggles
  const toggles = document.querySelectorAll('.permission-toggle');
  
  toggles.forEach(toggle => {
    toggle.addEventListener('change', function() {
      const roleId = this.dataset.roleId;
      const permissionId = this.dataset.permissionId;
      const isChecked = this.checked;
      const endpoint = isChecked ? 'assign' : 'unassign';
      
      togglePermission(roleId, permissionId, endpoint, this);
    });
  });

  // Permission tree expand/collapse toggles
  // CSS handles the chevron rotation via: .permission-tree-toggle[aria-expanded="true"] .fas { transform: rotate(90deg) }
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.permission-tree-toggle');
    if (!btn) return;

    const parentId = btn.dataset.permissionId;
    const isExpanded = btn.getAttribute('aria-expanded') === 'true';
    const container = btn.closest('tbody');
    if (!container) return;

    const collapseDescendants = function(pid) {
      container.querySelectorAll(`tr.child-row[data-parent-id="${pid}"]`).forEach(function(row) {
        row.classList.add('d-none');
        const rowId = row.dataset.permissionId;
        if (rowId) collapseDescendants(rowId);
        const nestedBtn = row.querySelector('.permission-tree-toggle');
        if (nestedBtn) {
          nestedBtn.setAttribute('aria-expanded', 'false');
        }
      });
    };

    if (!isExpanded) {
      container.querySelectorAll(`tr.child-row[data-parent-id="${parentId}"]`).forEach(function(row) {
        row.classList.remove('d-none');
      });
      btn.setAttribute('aria-expanded', 'true');
    } else {
      collapseDescendants(parentId);
      btn.setAttribute('aria-expanded', 'false');
    }
  });

  // Sidebar toggles (Show in Sidebar Menu)
  const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
  
  sidebarToggles.forEach(toggle => {
    toggle.addEventListener('change', function() {
      const permissionId = this.dataset.permissionId;
      const isChecked = this.checked;
      
      toggleSidebarMenuItem(permissionId, isChecked, this);
    });
  });

  const hideActionBtn = document.getElementById('hideActionPermissionsBtn');
  if (hideActionBtn) {
    hideActionBtn.addEventListener('click', () => {
      const statsContainer = document.getElementById('permissionModuleStats');
      const activeModule = statsContainer?.dataset.activeModule;
      if (!activeModule) {
        setActionFeedback('Select a module first.', 'text-warning');
        return;
      }

      const section = document.querySelector(`.permission-module-section[data-module="${cssEscapeValue(activeModule)}"]`);
      if (!section) {
        setActionFeedback('No permissions were found for this module.', 'text-warning');
        return;
      }

      const toggles = Array.from(section.querySelectorAll('.sidebar-toggle[data-permission-type="action"]'))
        .filter(toggle => toggle.checked);

      if (!toggles.length) {
        setActionFeedback('All action-only permissions are already hidden.', 'text-success');
        return;
      }

      hideActionBtn.disabled = true;
      setActionFeedback(`Hiding ${toggles.length} action permission${toggles.length > 1 ? 's' : ''}...`, 'text-muted');

      const operations = toggles.map(toggle => {
        toggle.checked = false;
        return toggleSidebarMenuItem(toggle.dataset.permissionId, false, toggle);
      });

      Promise.allSettled(operations).then(results => {
        hideActionBtn.disabled = false;
        const hasError = results.some(result => result.status === 'rejected');
        if (hasError) {
          setActionFeedback('Some action permissions could not be updated. Please review the error toast.', 'text-danger');
        } else {
          setActionFeedback('Done! Sidebar links updated. Reload the admin sidebar to see the change.', 'text-success');
        }
      });
    });
  }

  if (!restorePermissionModule()) {
    const firstModuleItem = document.querySelector('.permission-module-item');
    if (firstModuleItem) {
      selectPermissionModule(firstModuleItem.dataset.module);
    }
  }

  // Add permission form
  const addForm = document.getElementById('addPermissionForm');
  if (addForm) {
    addForm.addEventListener('submit', function(e) {
      e.preventDefault();
      addPermission();
    });
    
    // Auto-uppercase Permission Code
    const permissionCodeInput = document.getElementById('permission_code');
    if (permissionCodeInput) {
      permissionCodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
      });
    }
    
    // Auto-UC first Module Name
    const moduleNameInput = document.getElementById('module_name');
    if (moduleNameInput) {
      moduleNameInput.addEventListener('input', function() {
        // Convert to uppercase first letter only
        this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
      });
    }
    
    // Initialize parent dropdown filter on modal open
    const modal = document.getElementById('addPermissionModal');
    const parentSelect = document.getElementById('parent_permission_id');
    if (modal) {
      modal.addEventListener('show.bs.modal', function() {
        // Only reset title if we're adding a new permission (permission_id is empty)
        const permissionId = document.getElementById('permission_id').value;
        if (!permissionId) {
          document.getElementById('addPermissionModalLabel').textContent = 'Add New Permission';
          // Reset form only when adding
          addForm.reset();
          document.getElementById('permission_id').value = '';
        }
        
        // Destroy existing Select2 instance if any
        if (parentSelect && $(parentSelect).data('select2')) {
          $(parentSelect).select2('destroy');
        }
        
        // Initialize filter based on default menu level (Level 1)
        filterParentOptions();
        
        // Initialize Select2 after filtering only if dropdown is enabled
        if (parentSelect) {
          const menuLevelSelect = document.getElementById('menu_level');
          const selectedLevel = parseInt(menuLevelSelect.value);
          
          if (selectedLevel !== 1) {
            $(parentSelect).select2({
              theme: 'bootstrap-5',
              width: '100%',
              placeholder: '-- Select Parent --',
              allowClear: true,
              dropdownParent: $('#addPermissionModal')
            });
            
            // Sync Select2 with our filter function
            $(parentSelect).on('select2:select', function() {
              autoSetMenuLevel();
            });
            
            $(parentSelect).on('select2:clear', function() {
              autoSetMenuLevel();
            });
          }
        }
      });
    }
  }
});

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken() {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  return metaTag ? metaTag.getAttribute('content') : '';
}

/**
 * Toggle permission assignment
 */
function togglePermission(roleId, permissionId, endpoint, toggleElement, callback, errorCallback) {
  // Get fresh CSRF token from meta tag
  const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  fetch(`${API_BASE}/${endpoint}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': freshToken
    },
    body: JSON.stringify({
      role_id: roleId,
      permission_id: permissionId
    })
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update CSRF token in meta tag if returned
      if (data.csrf_token) {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
          metaTag.setAttribute('content', data.csrf_token);
        }
      }
      if (callback) {
        callback();
      } else {
        const toast = new bootstrap.Toast(document.getElementById('successToast'));
        toast.show();
      }
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    if (toggleElement) {
      toggleElement.checked = !toggleElement.checked;
    }
    if (errorCallback) {
      errorCallback();
    } else if (!callback) {
      const errorToast = document.getElementById('errorToast');
      const errorMessage = document.getElementById('errorMessage');
      errorMessage.textContent = error.error || error.message;
      const toast = new bootstrap.Toast(errorToast);
      toast.show();
    }
  });
}

/**
 * Toggle sidebar menu item visibility
 */
function toggleSidebarMenuItem(permissionId, isChecked, toggleElement) {
  const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  const request = fetch(`${API_BASE}/index.php`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': freshToken
    },
    body: JSON.stringify({
      permission_id: permissionId,
      is_menu_item: isChecked ? 1 : 0
    })
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update CSRF token in meta tag if returned
      if (data.csrf_token) {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
          metaTag.setAttribute('content', data.csrf_token);
        }
      }
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();

      if (toggleElement) {
        const statusLabel = document.getElementById(`sidebarStatus_${permissionId}`);
        if (statusLabel) {
          statusLabel.textContent = isChecked ? 'Visible in sidebar' : 'Hidden';
        }

        const row = toggleElement.closest('tr');
        if (row) {
          row.dataset.visible = isChecked ? '1' : '0';
          if (toggleElement.dataset.permissionType === 'action') {
            row.classList.toggle('table-warning', isChecked);
          } else {
            row.classList.remove('table-warning');
          }

          const section = row.closest('.permission-module-section');
          if (section) {
            refreshModuleStatsFromSection(section);
          }
        }
      }
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    toggleElement.checked = !toggleElement.checked;
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
    throw error;
  });

  return request;
}

/**
 * Add new permission
 */
function addPermission() {
  const permissionId = document.getElementById('permission_id').value;
  const permissionCode = document.getElementById('permission_code').value.trim();
  const permissionName = document.getElementById('permission_name').value.trim();
  const moduleName = document.getElementById('module_name').value.trim();
  const parentPermissionId = document.getElementById('parent_permission_id').value;
  const menuOrder = document.getElementById('menu_order').value;
  const menuLevel = document.getElementById('menu_level').value;
  const menuIcon = document.getElementById('menu_icon').value.trim();
  const menuUrl = document.getElementById('menu_url').value.trim();
  const isMenuItem = document.getElementById('is_menu_item').checked;

  if (!permissionCode || !permissionName || !moduleName) {
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'Permission code, name, and module are required';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
    return;
  }

  const formData = {
    permission_code: permissionCode,
    permission_name: permissionName,
    module_name: moduleName,
    parent_permission_id: parentPermissionId || null,
    menu_order: parseInt(menuOrder) || 0,
    menu_level: parseInt(menuLevel) || 1,
    menu_icon: menuIcon || null,
    menu_url: menuUrl || null,
    is_menu_item: isMenuItem ? 1 : 0
  };

  if (permissionId) {
    formData.permission_id = parseInt(permissionId);
  }

  fetch(`${API_BASE}/index.php`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': getCSRFToken()
    },
    body: JSON.stringify(formData)
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
      document.getElementById('addPermissionForm').reset();
      const modal = bootstrap.Modal.getInstance(document.getElementById('addPermissionModal'));
      modal.hide();
      setTimeout(() => window.location.reload(), 1000);
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

/**
 * Delete permission
 */
let deletePermissionId = null;

function deletePermission(permissionId) {
  deletePermissionId = permissionId;
  
  // Validate before showing delete modal
  // Check if permission is being used by any role
  fetch(`${API_BASE}/index.php`, {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    // Find the permission
    let permission = null;
    for (const module in data) {
      const found = data[module].find(p => p.permission_id === permissionId);
      if (found) {
        permission = found;
        break;
      }
    }
    
    if (permission) {
      // Check if permission is a parent to other permissions
      const hasChildren = Object.values(data).flat().some(p => p.parent_permission_id === permissionId);
      const warningDiv = document.getElementById('deletePermissionWarning');
      const warningText = document.getElementById('deletePermissionWarningText');
      
      if (hasChildren) {
        warningDiv.classList.remove('d-none');
        warningText.textContent = 'This permission has child permissions. Deleting it will also remove all child permissions.';
      } else {
        warningDiv.classList.add('d-none');
      }
      
      // Show the delete confirmation modal
      const deleteModal = new bootstrap.Modal(document.getElementById('deletePermissionModal'));
      deleteModal.show();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'Failed to validate permission';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

// Handle confirm delete button click
document.addEventListener('DOMContentLoaded', function() {
  const confirmDeleteBtn = document.getElementById('confirmDeletePermission');
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function() {
      if (deletePermissionId) {
        const encodedPermissionId = IdEncoder.encode(deletePermissionId);
        fetch(`${API_BASE}/delete?id=${encodedPermissionId}`, {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': getCSRFToken()
          }
        })
        .then(response => {
          if (!response.ok) {
            return response.json().then(err => { throw err; });
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            const toast = new bootstrap.Toast(document.getElementById('successToast'));
            toast.show();
            // Close the delete modal
            const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deletePermissionModal'));
            deleteModal.hide();
            setTimeout(() => window.location.reload(), 1000);
          } else {
            throw new Error(data.error || 'Operation failed');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          const errorToast = document.getElementById('errorToast');
          const errorMessage = document.getElementById('errorMessage');
          errorMessage.textContent = error.error || error.message;
          const toast = new bootstrap.Toast(errorToast);
          toast.show();
        });
      }
    });
  }
});

/**
 * Edit permission
 */
function editPermission(permissionId) {
  console.log('Editing permission:', permissionId);
  
  fetch(`${API_BASE}/index.php`, {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(response => {
    console.log('Response status:', response.status);
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    console.log('API data:', data);
    
    // Flatten the grouped data to find the permission
    let permission = null;
    for (const module in data) {
      const found = data[module].find(p => p.permission_id === permissionId);
      if (found) {
        permission = found;
        break;
      }
    }

    if (permission) {
      console.log('Found permission:', permission);
      
      document.getElementById('permission_id').value = permission.permission_id;
      document.getElementById('permission_code').value = permission.permission_code;
      document.getElementById('permission_name').value = permission.permission_name;
      document.getElementById('module_name').value = permission.module_name;
      document.getElementById('menu_order').value = permission.menu_order || 0;
      document.getElementById('menu_level').value = permission.menu_level || 1;
      document.getElementById('menu_icon').value = permission.menu_icon || '';
      document.getElementById('menu_url').value = permission.menu_url || '';
      document.getElementById('is_menu_item').checked = permission.is_menu_item == 1;
      
      const parentSelect = document.getElementById('parent_permission_id');
      const menuLevel = permission.menu_level || 1;
      
      // Destroy existing Select2 instance if any
      if ($(parentSelect).data('select2')) {
        $(parentSelect).select2('destroy');
      }
      
      // Set parent permission value before calling filterParentOptions
      parentSelect.value = permission.parent_permission_id || '';
      
      // Call filterParentOptions to set correct state and initialize Select2
      filterParentOptions();
      
      // Set the value after Select2 is initialized for non-level 1 permissions
      if (menuLevel !== 1 && permission.parent_permission_id) {
        setTimeout(() => {
          if ($(parentSelect).data('select2')) {
            $(parentSelect).val(permission.parent_permission_id).trigger('change');
          }
        }, 200);
      }
      
      // Set modal title to Edit Permission
      document.getElementById('addPermissionModalLabel').textContent = 'Edit Permission';
      
      const modal = new bootstrap.Modal(document.getElementById('addPermissionModal'));
      modal.show();
    } else {
      console.error('Permission not found with ID:', permissionId);
      const errorToast = document.getElementById('errorToast');
      const errorMessage = document.getElementById('errorMessage');
      errorMessage.textContent = 'Permission not found';
      const toast = new bootstrap.Toast(errorToast);
      toast.show();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || 'Failed to load permission data';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

/**
 * Save role (add or edit)
 */
function saveRole() {
  const roleId = document.getElementById('roleId').value;
  const roleCode = document.getElementById('roleCode').value.trim();
  const roleName = document.getElementById('roleName').value.trim();
  const roleDescription = document.getElementById('roleDescription').value.trim();

  if (!roleCode || !roleName) {
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'Role code and role name are required';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
    return;
  }

  // Prevent editing SUPER_ADMIN role
  if (roleCode === 'SUPER_ADMIN' || (roleId && roleCode.toUpperCase() === 'SUPER_ADMIN')) {
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'SUPER_ADMIN role cannot be edited';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
    return;
  }

  const method = roleId ? 'PUT' : 'POST';
  const url = ROLES_API_BASE;
  const data = {
    role_id: roleId || null,
    role_code: roleCode,
    role_name: roleName,
    role_description: roleDescription
  };

  fetch(url, {
    method: method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': getCSRFToken()
    },
    body: JSON.stringify(data)
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
      const modal = bootstrap.Modal.getInstance(document.getElementById('addRoleModal'));
      modal.hide();
      document.getElementById('addRoleForm').reset();
      document.getElementById('roleId').value = '';
      setTimeout(() => window.location.reload(), 1000);
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

/**
 * Edit role
 */
function editRole(roleId) {
  const encodedRoleId = IdEncoder.encode(roleId);
  fetch(`${ROLES_API_BASE}?id=${encodedRoleId}`, {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    const role = data.find(r => r.role_id === roleId);
    if (role) {
      // Prevent editing SUPER_ADMIN role
      if (role.role_code === 'SUPER_ADMIN') {
        const errorToast = document.getElementById('errorToast');
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.textContent = 'SUPER_ADMIN role cannot be edited';
        const toast = new bootstrap.Toast(errorToast);
        toast.show();
        return;
      }
      
      document.getElementById('roleId').value = role.role_id;
      document.getElementById('roleCode').value = role.role_code;
      document.getElementById('roleName').value = role.role_name;
      document.getElementById('roleDescription').value = role.role_description || '';
      document.getElementById('addRoleModalLabel').textContent = 'Edit Role';
      const modal = new bootstrap.Modal(document.getElementById('addRoleModal'));
      modal.show();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = 'Failed to load role data';
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}

/**
 * Delete role
 */
function deleteRole(roleId) {
  // Prevent deleting SUPER_ADMIN role
  const encodedRoleId = IdEncoder.encode(roleId);
  fetch(`${ROLES_API_BASE}?id=${encodedRoleId}`, {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    const role = data.find(r => r.role_id === roleId);
    if (role && role.role_code === 'SUPER_ADMIN') {
      const errorToast = document.getElementById('errorToast');
      const errorMessage = document.getElementById('errorMessage');
      errorMessage.textContent = 'SUPER_ADMIN role cannot be deleted';
      const toast = new bootstrap.Toast(errorToast);
      toast.show();
      return;
    }
    
    if (confirm('Are you sure you want to delete this role? This will also remove all permissions assigned to this role.')) {
      fetch(`${ROLES_API_BASE}?id=${roleId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-Token': getCSRFToken()
        }
      })
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => { throw err; });
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          const toast = new bootstrap.Toast(document.getElementById('successToast'));
          toast.show();
          setTimeout(() => window.location.reload(), 1000);
        } else {
          throw new Error(data.error || 'Operation failed');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        const errorToast = document.getElementById('errorToast');
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.textContent = error.error || error.message;
        const toast = new bootstrap.Toast(errorToast);
        toast.show();
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Reset modal when opened for adding new role
document.addEventListener('DOMContentLoaded', function() {
  const addRoleModal = document.getElementById('addRoleModal');
  if (addRoleModal) {
    addRoleModal.addEventListener('hidden.bs.modal', function() {
      document.getElementById('addRoleForm').reset();
      document.getElementById('roleId').value = '';
      document.getElementById('addRoleModalLabel').textContent = 'Add New Role';
    });
  }

  // Reset permission modal when closed
  const addPermissionModal = document.getElementById('addPermissionModal');
  if (addPermissionModal) {
    addPermissionModal.addEventListener('hidden.bs.modal', function() {
      document.getElementById('addPermissionForm').reset();
      document.getElementById('permission_id').value = '';
      document.getElementById('addPermissionModalLabel').textContent = 'Add New Permission';
    });
  }
});
