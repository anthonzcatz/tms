/**
 * User Management JavaScript
 * Handles client-side functionality for user management
 */

// Global state
let usersData = [];
let filteredUsers = [];
let currentPage = 1;
let perPage = 10;
let selectedUsers = new Set();
let deleteUserId = null;
let userModal = null;
let deleteModal = null;
let cropperModal = null;

// Wizard state
let currentStep = 1;
const totalSteps = 3;

// Image cropper variables
let originalImage = null;
let cropCanvas = null;
let previewCanvas = null;
let cropCtx = null;
let previewCtx = null;
let rotation = 0;
let zoom = 1;
let isDragging = false;
let startX, startY;
let imageOffsetX = 0;
let imageOffsetY = 0;
const cropSize = 200;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('DOM loaded, initializing user management...');
        console.log('BASE_URL:', window.BASE_URL);
        console.log('CURRENT_USER:', window.CURRENT_USER);
        console.log('IS_SUPER_ADMIN:', window.IS_SUPER_ADMIN);
        
        initComponents();
        loadUsers();
        setupEventListeners();
        
        console.log('User management initialized successfully');
    } catch (error) {
        console.error('Error initializing user management:', error);
        showToast('error', 'Error', 'Failed to initialize user management: ' + error.message);
    }
});

/**
 * Initialize Bootstrap components and plugins
 */
function initComponents() {
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
    deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    cropperModal = new bootstrap.Modal(document.getElementById('imageCropperModal'));
    
    // Initialize cropper canvas
    cropCanvas = document.getElementById('cropCanvas');
    previewCanvas = document.getElementById('previewCanvas');
    cropCtx = cropCanvas.getContext('2d');
    previewCtx = previewCanvas.getContext('2d');
    
    // Setup canvas drag events
    setupCanvasDrag();
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Setup all event listeners
 */
function setupEventListeners() {
    // Search input
    document.getElementById('userSearch').addEventListener('input', debounce(function() {
        currentPage = 1;
        filterAndRenderUsers();
    }, 300));
    
    // Role filter
    document.getElementById('roleFilter').addEventListener('change', function() {
        currentPage = 1;
        filterAndRenderUsers();
    });
    
    // Status filter
    document.getElementById('statusFilter').addEventListener('change', function() {
        currentPage = 1;
        filterAndRenderUsers();
    });
    
    // Per page selector
    document.getElementById('perPage').addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        renderUsersTable();
    });
    
    // Role change handler for SUPER_ADMIN warning
    document.getElementById('roleId').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const roleCode = selectedOption.dataset.roleCode;
        
        if (roleCode === 'SUPER_ADMIN' && !window.IS_SUPER_ADMIN) {
            showToast('warning', 'Warning', 'Only SUPER_ADMIN can assign super admin roles');
            this.value = '';
        }
    });
    
    // Time restrictions toggle handler
    document.getElementById('isTimeRestricted').addEventListener('change', function() {
        const isEnabled = this.checked;
        document.getElementById('allowedLoginStart').disabled = !isEnabled;
        document.getElementById('allowedLoginEnd').disabled = !isEnabled;
        
        // Disable/enable day checkboxes
        document.querySelectorAll('input[name="allowed_days[]"]').forEach(cb => {
            cb.disabled = !isEnabled;
        });
    });
    
    // Initialize time fields as disabled
    document.getElementById('allowedLoginStart').disabled = true;
    document.getElementById('allowedLoginEnd').disabled = true;
    document.querySelectorAll('input[name="allowed_days[]"]').forEach(cb => {
        cb.disabled = true;
    });
    
    // Form validation on input
    const formInputs = document.querySelectorAll('#userForm input, #userForm select');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
            }
        });
    });
}

/**
 * Load users from API
 */
async function loadUsers() {
    try {
        showTableLoading(true);
        
        const params = new URLSearchParams();
        const roleFilter = document.getElementById('roleFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        if (roleFilter) params.append('role_id', roleFilter);
        if (statusFilter !== '') params.append('status', statusFilter);
        
        const searchQuery = document.getElementById('userSearch').value;
        if (searchQuery) params.append('search', searchQuery);
        
        const url = `${window.BASE_URL}/api/users/index.php${params.toString() ? '?' + params.toString() : ''}`;
        
        console.log('Request URL:', url);
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API result:', result);
        
        if (result.success) {
            usersData = result.data || [];
            filterAndRenderUsers();
            updateStats();
        } else {
            console.error('API error:', result.error);
            showToast('error', 'Error', result.error || 'Failed to load users');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showToast('error', 'Error', 'Failed to load users. Please try again.');
    } finally {
        showTableLoading(false);
    }
}

/**
 * Filter users based on search and render table
 */
function filterAndRenderUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    filteredUsers = usersData.filter(user => {
        // Search filter
        const matchesSearch = !searchTerm || 
            user.username?.toLowerCase().includes(searchTerm) ||
            user.email?.toLowerCase().includes(searchTerm) ||
            user.fullname?.toLowerCase().includes(searchTerm);
        
        // Role filter
        const matchesRole = !roleFilter || user.role_id == roleFilter;
        
        // Status filter
        const matchesStatus = statusFilter === '' || user.status === (statusFilter === '1' ? 'active' : 'inactive');
        
        return matchesSearch && matchesRole && matchesStatus;
    });
    
    renderUsersTable();
}

/**
 * Render users as card-style list items
 */
function renderUsersTable() {
    const container = document.getElementById('card-user-body');
    const totalPages = Math.ceil(filteredUsers.length / perPage);
    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    const pageUsers = filteredUsers.slice(start, end);
    
    if (pageUsers.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="text-muted">
                    <span class="fas fa-inbox fa-3x mb-3 d-block"></span>
                    No users found
                </div>
            </div>
        `;
    } else {
        // Helper to build image URL (supports api/images/users via API proxy)
        const buildImageUrl = (path) => {
            if (!path) return '';
            if (path.startsWith('/api/images/users/')) {
                return `${window.BASE_URL}/api/users/profile_image.php?path=${encodeURIComponent(path)}`;
            }
            if (path.startsWith('http')) return path;
            return `${window.BASE_URL}${path}`;
        };

        container.innerHTML = pageUsers.map(user => {
            const fullName = user.fullname || 'N/A';
            const initials = getInitials(fullName);
            const isSuperAdmin = user.role_code === 'SUPER_ADMIN';
            const isSelected = selectedUsers.has(user.user_id.toString());
            const isActive = user.status === 'active';
            const statusBadgeClass = isActive ? 'badge-subtle-success' : (user.status === 'inactive' ? 'badge-subtle-warning' : 'badge-subtle-danger');
            const hasProfileImage = !!user.profile_image;
            
            // Avatar content - either image or initials
            let avatarContent;
            if (hasProfileImage) {
                const imgUrl = buildImageUrl(user.profile_image);
                console.log('User:', fullName, 'Profile Image Path:', user.profile_image, 'Built URL:', imgUrl);
                avatarContent = `<img class="rounded-circle" src="${imgUrl}" alt="${fullName}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><div class="avatar-name rounded-circle ${isSuperAdmin ? 'bg-primary text-white' : 'bg-soft-primary text-primary'} d-flex align-items-center justify-content-center" style="display:none">${initials}</div>`;
            } else {
                avatarContent = `<div class="avatar-name rounded-circle ${isSuperAdmin ? 'bg-primary text-white' : 'bg-soft-primary text-primary'} d-flex align-items-center justify-content-center">${initials}</div>`;
            }
            
            return `
                <div class="bg-white dark__bg-1100 d-md-flex d-xl-inline-block d-xxl-flex align-items-center p-x1 rounded-3 shadow-sm" data-user-id="${user.user_id}">
                    <div class="d-flex align-items-start align-items-sm-center">
                        <div class="form-check me-2 me-xxl-3 mb-0">
                            <input class="form-check-input user-checkbox" type="checkbox" 
                                   data-user-id="${user.user_id}" ${isSelected ? 'checked' : ''}>
                        </div>
                        <div class="avatar avatar-xl avatar-3xl ${isSuperAdmin ? 'status-online' : ''}">
                            ${avatarContent}
                        </div>
                        <div class="ms-1 ms-sm-3">
                            <p class="fw-semi-bold mb-3 mb-sm-2">
                                <a href="#" onclick="openEditUserModal(${user.user_id})">
                                    ${fullName}
                                    ${isSuperAdmin ? '<span class="badge bg-primary-subtle text-primary ms-1 fs-11">SUPER</span>' : ''}
                                </a>
                            </p>
                            <div class="row align-items-center gx-0 gy-2">
                                <div class="col-auto me-2">
                                    <h6 class="mb-0 text-800">
                                        <span class="fas fa-user me-1 text-muted"></span>${user.username}
                                    </h6>
                                </div>
                                <div class="col-auto lh-1 me-3">
                                    <small class="badge rounded ${statusBadgeClass}">${isActive ? 'Active' : user.status}</small>
                                </div>
                                <div class="col-auto">
                                    <h6 class="mb-0 text-500">${formatDate(user.last_login_at) || 'Never'}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border-bottom mt-4 mb-x1"></div>
                    <div class="d-flex justify-content-between ms-auto">
                        <div class="d-flex align-items-center gap-2 ms-md-4 ms-xl-0">
                            <span class="badge bg-soft-${getRoleBadgeColor(user.role_code)} text-${getRoleBadgeColor(user.role_code)}">
                                ${user.role_name || 'N/A'}
                            </span>
                            <span class="text-500 fs-10">
                                <span class="fas fa-envelope me-1"></span>${user.email}
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-falcon-default btn-sm" 
                                    onclick="openEditUserModal(${user.user_id})">
                                <span class="fas fa-edit"></span>
                            </button>
                            <button type="button" class="btn btn-falcon-default btn-sm" 
                                    onclick="confirmDelete(${user.user_id}, '${user.username}', ${isSuperAdmin})"
                                    ${isSuperAdmin ? 'disabled' : ''}>
                                <span class="fas fa-trash-alt"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Setup individual checkboxes
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const userId = this.dataset.userId;
                if (this.checked) {
                    selectedUsers.add(userId);
                } else {
                    selectedUsers.delete(userId);
                }
                updateQuickActionsBar();
            });
        });
        
        // Reinitialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    renderPagination(totalPages);
}

/**
 * Render pagination controls
 */
function renderPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">Previous</a>
        </li>
    `;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">Next</a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

/**
 * Navigate to a specific page
 */
function goToPage(page) {
    const totalPages = Math.ceil(filteredUsers.length / perPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderUsersTable();
    }
}

/**
 * Update statistics
 */
function updateStats() {
    const total = usersData.length;
    const active = usersData.filter(u => u.status === 'active').length;
    const inactive = total - active;
    
    document.getElementById('totalUsers').textContent = total;
    document.getElementById('activeUsers').textContent = active;
    document.getElementById('inactiveUsers').textContent = inactive;
}

/**
 * Update quick actions bar visibility
 */
function updateQuickActionsBar() {
    const bar = document.getElementById('quickActionsBar');
    const count = selectedUsers.size;
    
    document.getElementById('selectedCount').textContent = count;
    
    if (count > 0) {
        bar.style.display = 'block';
    } else {
        bar.style.display = 'none';
    }
}

/**
 * Update select all checkbox state (removed for card layout)
 */
function updateSelectAllCheckbox() {
    // No longer needed with card layout
}

/**
 * Clear all selections
 */
function clearSelection() {
    selectedUsers.clear();
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    updateQuickActionsBar();
}

/**
 * Open add user modal
 */
function openAddUserModal() {
    console.log('Opening add user modal');
    
    // Reset form
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    
    // Reset wizard to step 1
    currentStep = 1;
    updateWizardUI();
    
    // Clear validation states
    document.querySelectorAll('#userForm .is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    
    userModal.show();
}

/**
 * Navigate to next step
 */
function nextStep() {
    // Validate current step before proceeding
    if (!validateStep(currentStep)) {
        return;
    }
    
    if (currentStep < totalSteps) {
        currentStep++;
        updateWizardUI();
    }
}

/**
 * Navigate to previous step
 */
function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateWizardUI();
    }
}

/**
 * Validate current step
 */
function validateStep(step) {
    const form = document.getElementById('userForm');
    const stepElement = document.querySelector(`.wizard-step[data-step="${step}"]`);
    const requiredFields = stepElement.querySelectorAll('input[required], select[required]');
    
    let isValid = true;
    requiredFields.forEach(field => {
        if (!field.checkValidity()) {
            isValid = false;
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        showToast('warning', 'Warning', 'Please fill in all required fields');
    }
    
    return isValid;
}

/**
 * Update wizard UI based on current step
 */
function updateWizardUI() {
    // Update step indicators
    document.querySelectorAll('.step-item').forEach(item => {
        const stepNum = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed');
        
        if (stepNum === currentStep) {
            item.classList.add('active');
        } else if (stepNum < currentStep) {
            item.classList.add('completed');
        }
    });
    
    // Show/hide step content
    document.querySelectorAll('.wizard-step').forEach(step => {
        step.classList.remove('active');
    });
    document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('active');
    
    // Update buttons
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const saveBtn = document.getElementById('saveUserBtn');
    
    if (currentStep === 1) {
        prevBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'inline-block';
    }
    
    if (currentStep === totalSteps) {
        nextBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    } else {
        nextBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
    }
}

/**
 * Open edit user modal
 */
async function openEditUserModal(userId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/users/index.php?id=${userId}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            const user = result.data;
            
            // Populate form
            document.getElementById('userId').value = user.user_id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('firstName').value = user.fullname || '';
            document.getElementById('roleId').value = user.role_id;
            document.getElementById('branchId').value = user.branch_id || '';
            document.getElementById('isActive').checked = user.status === 'active';
            document.getElementById('isTimeRestricted').checked = user.is_time_restricted == 1;
            document.getElementById('allowedLoginStart').value = user.allowed_login_start || '';
            document.getElementById('allowedLoginEnd').value = user.allowed_login_end || '';
            
            // Populate allowed days checkboxes
            if (user.allowed_days) {
                const days = user.allowed_days.split(',');
                document.querySelectorAll('input[name="allowed_days[]"]').forEach(cb => {
                    cb.checked = days.includes(cb.value);
                });
            } else {
                document.querySelectorAll('input[name="allowed_days[]"]').forEach(cb => {
                    cb.checked = false;
                });
            }
            
            // Update modal title
            document.getElementById('modalTitleText').textContent = 'Edit User';
            document.getElementById('saveBtnText').textContent = 'Update User';
            
            // Password is optional for edit
            document.getElementById('password').required = false;
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('passwordHint').style.display = 'block';
            
            // Handle profile image
            const preview = document.getElementById('profileImagePreview');
            const placeholder = document.getElementById('profileImagePlaceholder');
            const previewImg = preview.querySelector('img');
            
            // Helper to build image URL (supports api/images/users via API proxy)
            const buildImageUrl = (path) => {
                if (!path) return '';
                if (path.startsWith('/api/images/users/')) {
                    return `${window.BASE_URL}/api/users/profile_image.php?path=${encodeURIComponent(path)}`;
                }
                if (path.startsWith('http')) return path;
                return `${window.BASE_URL}${path}`;
            };
            
            if (user.profile_image) {
                // Use the profile image from database
                const imgUrl = buildImageUrl(user.profile_image);
                console.log('Edit Modal - Profile Image Path:', user.profile_image, 'Built URL:', imgUrl);
                previewImg.src = imgUrl;
                previewImg.onerror = function() {
                    console.error('Failed to load image:', imgUrl);
                    this.style.display = 'none';
                    placeholder.style.display = 'block';
                };
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                // No profile image
                preview.style.display = 'none';
                placeholder.style.display = 'block';
                previewImg.src = '';
            }
            
            // Reset wizard to step 1
            currentStep = 1;
            updateWizardUI();
            
            // Clear validation states
            document.querySelectorAll('#userForm .is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            userModal.show();
        } else {
            showToast('error', 'Error', result.error || 'Failed to load user data');
        }
    } catch (error) {
        console.error('Error loading user:', error);
        showToast('error', 'Error', 'Failed to load user data. Please try again.');
    }
}

/**
 * Save user
 */
async function saveUser() {
    const form = document.getElementById('userForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const userId = document.getElementById('userId').value;
    const isEdit = !!userId;
    
    const saveBtn = document.getElementById('saveUserBtn');
    const saveBtnText = document.getElementById('saveBtnText');
    const originalText = saveBtnText.textContent;
    
    saveBtn.disabled = true;
    saveBtnText.textContent = isEdit ? 'Updating...' : 'Saving...';
    
    try {
        const data = {
            username: document.getElementById('username').value,
            email: document.getElementById('email').value,
            fullname: document.getElementById('firstName').value,
            role_id: document.getElementById('roleId').value,
            branch_id: document.getElementById('branchId').value || null,
            status: document.getElementById('isActive').checked ? 'active' : 'inactive',
            is_time_restricted: document.getElementById('isTimeRestricted').checked ? 1 : 0,
            allowed_login_start: document.getElementById('allowedLoginStart').value || null,
            allowed_login_end: document.getElementById('allowedLoginEnd').value || null
        };
        
        // Collect allowed days
        const allowedDays = [];
        document.querySelectorAll('input[name="allowed_days[]"]:checked').forEach(cb => {
            allowedDays.push(cb.value);
        });
        data.allowed_days = allowedDays.length > 0 ? allowedDays.join(',') : null;
        
        // Handle profile image - only send if it's a base64 string (newly uploaded/cropped)
        const previewImg = document.querySelector('#profileImagePreview img');
        if (previewImg && previewImg.src && previewImg.src.startsWith('data:image')) {
            data.profile_image = previewImg.src;
            console.log('Sending profile image, length:', previewImg.src.length);
        } else {
            console.log('No profile image to send, previewImg.src:', previewImg ? previewImg.src.substring(0, 50) + '...' : 'null');
        }
        
        if (!isEdit) {
            data.password = document.getElementById('password').value;
        } else {
            const password = document.getElementById('password').value;
            if (password) {
                data.password = password;
            }
        }
        
        const url = `${window.BASE_URL}/api/users/index.php`;
        const method = isEdit ? 'PUT' : 'POST';
        
        if (isEdit) {
            data.user_id = userId;
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', result.message || (isEdit ? 'User updated successfully' : 'User created successfully'));
            userModal.hide();
            loadUsers();
        } else {
            showToast('error', 'Error', result.error || 'Failed to save user');
        }
    } catch (error) {
        console.error('Error saving user:', error);
        showToast('error', 'Error', 'Failed to save user. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtnText.textContent = originalText;
    }
}

/**
 * Show delete confirmation modal
 */
function confirmDelete(userId, username, isSuperAdmin) {
    if (isSuperAdmin) {
        showToast('error', 'Error', 'Cannot delete SUPER_ADMIN accounts');
        return;
    }
    
    deleteUserId = userId;
    document.getElementById('deleteUsername').textContent = username;
    deleteModal.show();
}

/**
 * Confirm and delete user
 */
async function confirmDeleteUser() {
    if (!deleteUserId) return;
    
    try {
        const btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="fas fa-spinner fa-spin me-1"></span>Deleting...';
        
        const response = await fetch(`${window.BASE_URL}/api/users/index.php?id=${deleteUserId}&_token=${window.CSRF_TOKEN}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'Success', 'User deleted successfully');
            deleteModal.hide();
            loadUsers();
        } else {
            showToast('error', 'Error', result.error || 'Failed to delete user');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showToast('error', 'Error', 'Failed to delete user. Please try again.');
    } finally {
        const btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = false;
        btn.innerHTML = '<span class="fas fa-trash-alt me-1"></span>Delete User';
        deleteUserId = null;
    }
}

/**
 * Toggle password visibility
 */
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + 'ToggleIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Generate random password
 */
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
    document.getElementById('password').type = 'text';
    setTimeout(() => {
        document.getElementById('password').type = 'password';
    }, 2000);
    document.getElementById('passwordToggleIcon').classList.remove('fa-eye');
    document.getElementById('passwordToggleIcon').classList.add('fa-eye-slash');
}

/**
 * Preview profile image
 */
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profileImagePreview');
            const placeholder = document.getElementById('profileImagePlaceholder');
            const img = preview.querySelector('img');
            
            console.log('Image selected, setting preview');
            img.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
            
            // Automatically open cropper after image selection
            setTimeout(() => {
                console.log('Opening cropper');
                openImageCropper();
            }, 100);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Remove profile image
 */
function removeProfileImage() {
    const input = document.getElementById('profileImage');
    const preview = document.getElementById('profileImagePreview');
    const placeholder = document.getElementById('profileImagePlaceholder');
    
    input.value = '';
    preview.style.display = 'none';
    placeholder.style.display = 'block';
}

/**
 * Open image cropper modal
 */
function openImageCropper() {
    const previewImg = document.querySelector('#profileImagePreview img');
    if (!previewImg || !previewImg.src) {
        showToast('warning', 'Warning', 'Please upload an image first');
        return;
    }
    
    // Load image into canvas
    originalImage = new Image();
    originalImage.onload = function() {
        // Set canvas size to match container
        const containerWidth = 400;
        const containerHeight = 400;
        cropCanvas.width = containerWidth;
        cropCanvas.height = containerHeight;
        
        // Reset position and zoom
        rotation = 0;
        zoom = 1;
        imageOffsetX = 0;
        imageOffsetY = 0;
        
        // Center image
        const scale = Math.min(containerWidth / originalImage.width, containerHeight / originalImage.height);
        imageOffsetX = (containerWidth - originalImage.width * scale) / 2;
        imageOffsetY = (containerHeight - originalImage.height * scale) / 2;
        
        drawCropCanvas();
        updatePreview();
        
        cropperModal.show();
    };
    originalImage.src = previewImg.src;
}

/**
 * Setup canvas drag events
 */
function setupCanvasDrag() {
    cropCanvas.addEventListener('mousedown', function(e) {
        isDragging = true;
        startX = e.clientX - imageOffsetX;
        startY = e.clientY - imageOffsetY;
    });
    
    cropCanvas.addEventListener('mousemove', function(e) {
        if (isDragging) {
            imageOffsetX = e.clientX - startX;
            imageOffsetY = e.clientY - startY;
            drawCropCanvas();
            updatePreview();
        }
    });
    
    cropCanvas.addEventListener('mouseup', function() {
        isDragging = false;
    });
    
    cropCanvas.addEventListener('mouseleave', function() {
        isDragging = false;
    });
    
    // Mouse scroll to zoom
    cropCanvas.addEventListener('wheel', function(e) {
        e.preventDefault();
        
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newZoom = Math.max(0.5, Math.min(3, zoom + delta));
        
        if (newZoom !== zoom) {
            zoom = parseFloat(newZoom.toFixed(1));
            document.getElementById('zoomSlider').value = zoom;
            document.getElementById('zoomSlider').min = 0.5;
            drawCropCanvas();
            updatePreview();
        }
    });
}

/**
 * Draw crop canvas with rotation and zoom
 */
function drawCropCanvas() {
    cropCtx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
    
    // Calculate image dimensions
    const scale = Math.min(cropCanvas.width / originalImage.width, cropCanvas.height / originalImage.height);
    const scaledWidth = originalImage.width * scale * zoom;
    const scaledHeight = originalImage.height * scale * zoom;
    
    // Draw image with offset
    cropCtx.save();
    cropCtx.translate(cropCanvas.width / 2, cropCanvas.height / 2);
    cropCtx.rotate(rotation * Math.PI / 180);
    cropCtx.translate(-cropCanvas.width / 2, -cropCanvas.height / 2);
    cropCtx.drawImage(originalImage, imageOffsetX, imageOffsetY, scaledWidth, scaledHeight);
    cropCtx.restore();
    
    // Draw circular crop area overlay
    const centerX = cropCanvas.width / 2;
    const centerY = cropCanvas.height / 2;
    const radius = cropSize / 2;
    
    // Draw semi-transparent overlay
    cropCtx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    cropCtx.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
    
    // Clear circular crop area
    cropCtx.save();
    cropCtx.beginPath();
    cropCtx.arc(centerX, centerY, radius, 0, Math.PI * 2);
    cropCtx.clip();
    
    // Redraw image inside crop area
    cropCtx.save();
    cropCtx.translate(cropCanvas.width / 2, cropCanvas.height / 2);
    cropCtx.rotate(rotation * Math.PI / 180);
    cropCtx.translate(-cropCanvas.width / 2, -cropCanvas.height / 2);
    cropCtx.drawImage(originalImage, imageOffsetX, imageOffsetY, scaledWidth, scaledHeight);
    cropCtx.restore();
    cropCtx.restore();
    
    // Draw crop circle border
    cropCtx.strokeStyle = '#0d6efd';
    cropCtx.lineWidth = 3;
    cropCtx.beginPath();
    cropCtx.arc(centerX, centerY, radius, 0, Math.PI * 2);
    cropCtx.stroke();
}

/**
 * Update preview canvas
 */
function updatePreview() {
    previewCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
    
    // Create circular clip
    previewCtx.beginPath();
    previewCtx.arc(100, 100, 100, 0, Math.PI * 2);
    previewCtx.closePath();
    previewCtx.clip();
    
    // Calculate scale and position
    const scale = Math.min(cropCanvas.width / originalImage.width, cropCanvas.height / originalImage.height);
    const scaledWidth = originalImage.width * scale * zoom;
    const scaledHeight = originalImage.height * scale * zoom;
    
    // Calculate the crop area in the source image
    const centerX = cropCanvas.width / 2;
    const centerY = cropCanvas.height / 2;
    const cropRadius = cropSize / 2;
    
    // Calculate source coordinates
    const sourceX = (centerX - cropRadius - imageOffsetX) / scale / zoom;
    const sourceY = (centerY - cropRadius - imageOffsetY) / scale / zoom;
    const sourceSize = cropSize / scale / zoom;
    
    previewCtx.drawImage(
        originalImage,
        sourceX, sourceY, sourceSize, sourceSize,
        0, 0, 200, 200
    );
}

/**
 * Rotate image
 */
function rotateImage(degrees) {
    rotation = (rotation + degrees) % 360;
    drawCropCanvas();
    updatePreview();
}

/**
 * Update zoom
 */
function updateZoom(value) {
    zoom = parseFloat(value);
    drawCropCanvas();
    updatePreview();
}

/**
 * Reset crop
 */
function resetCrop() {
    rotation = 0;
    zoom = 1;
    document.getElementById('zoomSlider').value = 1;
    
    // Center image
    const scale = Math.min(cropCanvas.width / originalImage.width, cropCanvas.height / originalImage.height);
    imageOffsetX = (cropCanvas.width - originalImage.width * scale) / 2;
    imageOffsetY = (cropCanvas.height - originalImage.height * scale) / 2;
    
    drawCropCanvas();
    updatePreview();
}

/**
 * Apply crop
 */
function applyCrop() {
    const croppedCanvas = document.createElement('canvas');
    croppedCanvas.width = cropSize;
    croppedCanvas.height = cropSize;
    const croppedCtx = croppedCanvas.getContext('2d');
    
    // Create circular clip
    croppedCtx.beginPath();
    croppedCtx.arc(100, 100, 100, 0, Math.PI * 2);
    croppedCtx.closePath();
    croppedCtx.clip();
    
    // Calculate scale and position
    const scale = Math.min(cropCanvas.width / originalImage.width, cropCanvas.height / originalImage.height);
    const centerX = cropCanvas.width / 2;
    const centerY = cropCanvas.height / 2;
    const cropRadius = cropSize / 2;
    
    // Calculate source coordinates
    const sourceX = (centerX - cropRadius - imageOffsetX) / scale / zoom;
    const sourceY = (centerY - cropRadius - imageOffsetY) / scale / zoom;
    const sourceSize = cropSize / scale / zoom;
    
    croppedCtx.drawImage(
        originalImage,
        sourceX, sourceY, sourceSize, sourceSize,
        0, 0, cropSize, cropSize
    );
    
    // Update preview image
    const preview = document.getElementById('profileImagePreview');
    const placeholder = document.getElementById('profileImagePlaceholder');
    const previewImg = preview.querySelector('img');
    
    // Set cropped image as data URL
    const croppedDataUrl = croppedCanvas.toDataURL('image/png');
    previewImg.src = croppedDataUrl;
    
    // Ensure preview is visible and placeholder is hidden
    preview.style.display = 'block';
    placeholder.style.display = 'none';
    
    console.log('Cropped image applied, src length:', croppedDataUrl.length);
    
    cropperModal.hide();
    showToast('success', 'Success', 'Profile image cropped successfully');
}

/**
 * Show toast notification
 */
function showToast(type, title, message) {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    
    const icons = {
        success: '<span class="fas fa-check-circle text-success"></span>',
        error: '<span class="fas fa-times-circle text-danger"></span>',
        warning: '<span class="fas fa-exclamation-circle text-warning"></span>',
        info: '<span class="fas fa-info-circle text-info"></span>'
    };
    
    toastIcon.innerHTML = icons[type] || icons.info;
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

/**
 * Show/hide table loading state
 */
function showTableLoading(show) {
    const container = document.getElementById('card-user-body');
    
    if (show) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading users...</p>
            </div>
        `;
    }
}

/**
 * Export users to CSV/Excel
 */
function exportUsers(format) {
    const data = filteredUsers.map(user => ({
        'Username': user.username,
        'First Name': user.first_name,
        'Last Name': user.last_name,
        'Email': user.email,
        'Phone': user.phone || '',
        'Role': user.role_name,
        'Status': user.is_active ? 'Active' : 'Inactive',
        'Created At': formatDate(user.created_at),
        'Last Login': formatDate(user.last_login) || 'Never'
    }));
    
    if (data.length === 0) {
        showToast('warning', 'Warning', 'No data to export');
        return;
    }
    
    // Simple CSV export
    const headers = Object.keys(data[0]);
    const csv = [
        headers.join(','),
        ...data.map(row => headers.map(h => `"${(row[h] || '').toString().replace(/"/g, '""')}"`).join(','))
    ].join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `users_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    
    showToast('success', 'Success', 'Users exported successfully');
}

/**
 * Bulk actions (placeholder implementations)
 */
function bulkActivate() {
    showToast('info', 'Info', `Would activate ${selectedUsers.size} users`);
}

function bulkDeactivate() {
    showToast('info', 'Info', `Would deactivate ${selectedUsers.size} users`);
}

function bulkDelete() {
    showToast('info', 'Info', `Would delete ${selectedUsers.size} users`);
}

/**
 * Utility functions
 */
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function getRoleBadgeColor(roleCode) {
    if (!roleCode) return 'secondary';
    if (roleCode === 'SUPER_ADMIN') return 'primary';
    if (roleCode.includes('ADMIN')) return 'info';
    return 'secondary';
}

function formatDate(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return null;
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
