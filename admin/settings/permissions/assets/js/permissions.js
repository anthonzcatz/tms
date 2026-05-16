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
  
  const searchTerm = searchInput.value.toLowerCase();
  const tableRows = permissionsTable.querySelectorAll('tbody.list tr');
  
  tableRows.forEach(function(row) {
    const rowText = row.textContent.toLowerCase();
    if (rowText.includes(searchTerm)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
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
function togglePermission(roleId, permissionId, endpoint, toggleElement) {
  fetch(`${API_BASE}/${endpoint}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': getCSRFToken()
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
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
      // Don't reload to stay on current tab
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
  });
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
        fetch(`${API_BASE}/delete?id=${deletePermissionId}`, {
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
  fetch(`${ROLES_API_BASE}?id=${roleId}`, {
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
  fetch(`${ROLES_API_BASE}?id=${roleId}`, {
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
