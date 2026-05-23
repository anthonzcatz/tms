// System Settings Module JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Restore active tab from localStorage
    const activeTab = localStorage.getItem('systemSettingsActiveTab');
    if (activeTab) {
        const tabElement = document.querySelector(`#settings-tab [href="${activeTab}"]`);
        if (tabElement) {
            const tabTrigger = new bootstrap.Tab(tabElement);
            tabTrigger.show();
        }
    }
    
    // Save active tab to localStorage when clicked
    const tabLinks = document.querySelectorAll('#settings-tab .nav-link');
    tabLinks.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            localStorage.setItem('systemSettingsActiveTab', event.target.getAttribute('href'));
        });
    });
    
    // Auto-hide success/error alerts after 5 seconds (but not permanent info notes)
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Skip auto-hide for permanent info notes
        if (alert.classList.contains('alert-info')) {
            return;
        }
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 5000);
    });
    
    // Toggle maintenance mode fields
    const maintenanceModeSwitch = document.getElementById('maintenanceMode');
    const maintenanceFields = document.querySelectorAll('[name="maintenance_message"], [name="maintenance_start"], [name="maintenance_end"]');
    
    if (maintenanceModeSwitch) {
        maintenanceModeSwitch.addEventListener('change', function() {
            maintenanceFields.forEach(field => {
                field.disabled = !this.checked;
                if (!this.checked) {
                    field.value = '';
                }
            });
        });
        
        // Initialize based on current state
        if (!maintenanceModeSwitch.checked) {
            maintenanceFields.forEach(field => {
                field.disabled = true;
            });
        }
    }
});

/* =========================================================
   Security Tab — Device & Session Manager
   ========================================================= */
const BASE_URL = window.BASE_URL;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function fmtDate(str) {
    if (!str) return '<span class="text-muted">—</span>';
    // Display as-is from database (Asia/Manila)
    const date = new Date(str);
    return date.toLocaleString('en-PH', {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
}

const deviceTypeIcon = {desktop:'fa-desktop',laptop:'fa-laptop',tablet:'fa-tablet-alt',mobile:'fa-mobile-alt',other:'fa-question-circle'};
const statusBadge    = {
    approved: '<span class="badge bg-success-subtle text-success">Approved</span>',
    pending:  '<span class="badge bg-warning-subtle text-warning">Pending</span>',
    blocked:  '<span class="badge bg-danger-subtle text-danger">Blocked</span>',
};

// Helper functions for user avatars
function getInitials(name) {
    if (!name) return 'U';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function buildImageUrl(path) {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    return `${window.BASE_URL}${path}`;
}

function getUserAvatar(profileImage, fullname, size = 32) {
    const safeName = (fullname || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    if (profileImage) {
        const imgUrl = buildImageUrl(profileImage);
        return `<img class="rounded-circle" src="${imgUrl}" alt="${safeName}" style="width:${size}px; height:${size}px; object-fit:cover;">`;
    }
    const initials = getInitials(fullname);
    return `<div class="rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold" style="width:${size}px; height:${size}px; font-size:${size/2.5}px;">${initials}</div>`;
}

let deviceActionModal, sessionTerminateModal, currentDeviceAction = null, currentSessionId = null;

// ------ Devices ------
let deviceCurrentPage = 1;
let deviceTotalPages = 1;
let deviceTotalCount = 0;

// ------ Sessions ------
let sessionCurrentPage = 1;
let sessionTotalPages = 1;
let sessionTotalCount = 0;

async function loadDevices(page = 1) {
    const search = document.getElementById('deviceSearch')?.value ?? '';
    const status = document.getElementById('deviceStatusFilter')?.value ?? '';
    const tbody  = document.getElementById('devicesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

    const res  = await fetch(`${BASE_URL}/api/devices/index.php?page=${page}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`, {credentials:'same-origin'});
    const json = await res.json();
    if (!json.success) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${json.error}</td></tr>`; return; }

    const devices = json.data.devices;
    deviceCurrentPage = page;
    deviceTotalCount = json.data.total;
    deviceTotalPages = Math.ceil(deviceTotalCount / 20);

    if (!devices.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No devices found.</td></tr>'; }
    else {
        tbody.innerHTML = devices.map(d => {
            const icon = deviceTypeIcon[d.device_type] ?? 'fa-question-circle';
            const lastUser = d.last_user_fullname || d.last_user_username || '<span class="text-muted">Unknown</span>';
            const location = (d.city && d.country) ? `${d.city}, ${d.country}` : (d.city || d.country || '<span class="text-muted">Unknown</span>');
            const avatar = getUserAvatar(d.last_user_profile_image, lastUser, 32);
            return `<tr>
                <td><code class="fs-11">${d.device_code}</code></td>
                <td><span class="fas ${icon} me-1 text-600"></span>${d.device_name ?? '—'}<br><small class="text-muted">${d.device_type}</small></td>
                <td>${d.ip_address ?? '—'}</td>
                <td>${location}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="me-2">${avatar}</div>
                        <div>
                            <strong>${d.last_user_username ?? '—'}</strong><br>
                            <small class="text-muted">${lastUser}</small>
                        </div>
                    </div>
                </td>
                <td>${fmtDate(d.last_used_at)}</td>
                <td class="text-center">${d.active_sessions > 0 ? `<span class="badge bg-primary-subtle text-primary">${d.active_sessions}</span>` : '0'}</td>
                <td class="text-center">${statusBadge[d.status] ?? d.status}</td>
                <td class="text-end">
                    ${d.status !== 'approved' ? `<button type="button" class="btn btn-sm btn-outline-success me-1" onclick="showDeviceActionModal(${d.device_id},'approved')"><span class="fas fa-check"></span></button>` : ''}
                    ${d.status !== 'blocked'  ? `<button type="button" class="btn btn-sm btn-outline-danger me-1"   onclick="showDeviceActionModal(${d.device_id},'blocked')"><span class="fas fa-ban"></span></button>` : ''}
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showDeviceActionModal(${d.device_id},'delete')"><span class="fas fa-trash-alt"></span></button>
                </td>
            </tr>`;
        }).join('');
    }

    // Update pagination info
    const start = deviceTotalCount > 0 ? ((deviceCurrentPage - 1) * 20) + 1 : 0;
    const end = Math.min(deviceCurrentPage * 20, deviceTotalCount);
    document.getElementById('devicePaginationInfo').textContent = `Showing ${start}-${end} of ${deviceTotalCount} devices`;

    // Update pagination controls
    updateDevicePagination();
}

function updateDevicePagination() {
    const pagination = document.getElementById('devicePagination');
    if (!pagination) return;

    let html = '';

    // Previous button
    html += `<li class="page-item ${deviceCurrentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault(); loadDevices(${deviceCurrentPage - 1});">Previous</a>
    </li>`;

    // Page numbers (show max 5 pages)
    const startPage = Math.max(1, deviceCurrentPage - 2);
    const endPage = Math.min(deviceTotalPages, deviceCurrentPage + 2);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadDevices(1);">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><a class="page-link" href="#" onclick="return false;">...</a></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === deviceCurrentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault(); loadDevices(${i});">${i}</a>
        </li>`;
    }

    if (endPage < deviceTotalPages) {
        if (endPage < deviceTotalPages - 1) {
            html += `<li class="page-item disabled"><a class="page-link" href="#" onclick="return false;">...</a></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadDevices(${deviceTotalPages});">${deviceTotalPages}</a></li>`;
    }

    // Next button
    html += `<li class="page-item ${deviceCurrentPage === deviceTotalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault(); loadDevices(${deviceCurrentPage + 1});">Next</a>
    </li>`;

    pagination.innerHTML = html;
}

function showDeviceActionModal(deviceId, action) {
    if (!deviceActionModal) deviceActionModal = new bootstrap.Modal(document.getElementById('deviceActionModal'));
    currentDeviceAction = {deviceId, action};

    const titleEl = document.getElementById('deviceActionModalTitle');
    const bodyEl  = document.getElementById('deviceActionModalBody');
    const btnEl   = document.getElementById('deviceActionConfirmBtn');

    btnEl.className = 'btn';

    if (action === 'approved') {
        titleEl.textContent = 'Approve Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to approve this device? Once approved, the device will be allowed to log in.</p>';
        btnEl.className = 'btn btn-success';
        btnEl.textContent = 'Approve';
    } else if (action === 'blocked') {
        titleEl.textContent = 'Block Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to block this device? All active sessions from this device will be terminated immediately.</p>';
        btnEl.className = 'btn btn-danger';
        btnEl.textContent = 'Block';
    } else if (action === 'delete') {
        titleEl.textContent = 'Remove Device';
        bodyEl.innerHTML = '<p class="mb-0">Are you sure you want to remove this device? All its sessions will be terminated and the device record will be permanently deleted.</p>';
        btnEl.className = 'btn btn-danger';
        btnEl.textContent = 'Remove';
    }

    deviceActionModal.show();
}

document.getElementById('deviceActionConfirmBtn')?.addEventListener('click', async () => {
    if (!currentDeviceAction) return;
    const {deviceId, action} = currentDeviceAction;

    // Get fresh CSRF token from meta tag
    const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (action === 'delete') {
        const res  = await fetch(`${window.BASE_URL}/api/devices/index.php?id=${deviceId}&_token=${freshToken}`, {
            method: 'DELETE',
            headers: {'X-CSRF-Token': freshToken},
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            deviceActionModal?.hide();
            loadDevices();
        } else {
            alert(json.error);
        }
    } else {
        const res  = await fetch(`${window.BASE_URL}/api/devices/index.php`, {
            method: 'PUT',
            headers: {'Content-Type':'application/json','X-CSRF-Token': freshToken},
            body: JSON.stringify({device_id: deviceId, status: action}),
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            deviceActionModal?.hide();
            loadDevices();
        } else {
            alert(json.error);
        }
    }
});

// ------ Sessions ------
async function loadSessions(page = 1) {
    const activeOnly = document.getElementById('activeOnlyFilter')?.checked ? 1 : 0;
    const tbody      = document.getElementById('sessionsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

    const res  = await fetch(`${BASE_URL}/api/sessions/index.php?page=${page}&active_only=${activeOnly}`, {credentials:'same-origin'});
    const json = await res.json();
    if (!json.success) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${json.error}</td></tr>`; return; }

    const sessions = json.data.sessions;
    sessionCurrentPage = page;
    sessionTotalCount = json.data.total;
    sessionTotalPages = Math.ceil(sessionTotalCount / 20);

    if (!sessions.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No sessions found.</td></tr>'; }
    else {
        tbody.innerHTML = sessions.map(s => {
            const statusDot = s.is_active == 1
                ? '<span class="fas fa-circle text-success fs-11 me-1" title="Active"></span>'
                : '<span class="fas fa-circle text-secondary fs-11 me-1" title="Ended"></span>';
            const avatar = getUserAvatar(s.profile_image, s.fullname || s.username, 32);
            return `<tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="me-2">${avatar}</div>
                        <div>
                            ${statusDot}<strong>${s.username ?? '—'}</strong><br>
                            <small class="text-muted">${s.fullname ?? ''}</small>
                        </div>
                    </div>
                </td>
                <td>${s.device_name ?? '<span class="text-muted">Unknown</span>'}<br><small class="text-muted">${s.device_type ?? ''}</small></td>
                <td>${s.ip_address ?? '—'}</td>
                <td>${fmtDate(s.login_time)}</td>
                <td>${fmtDate(s.last_seen)}</td>
                <td>${fmtDate(s.expires_at)}</td>
                <td class="text-end">
                    ${s.is_active == 1 ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="showSessionTerminateModal(${s.session_id})"><span class="fas fa-sign-out-alt"></span></button>` : '<span class="text-muted small">Ended</span>'}
                </td>
            </tr>`;
        }).join('');
    }

    // Update pagination info
    const start = sessionTotalCount > 0 ? ((sessionCurrentPage - 1) * 20) + 1 : 0;
    const end = Math.min(sessionCurrentPage * 20, sessionTotalCount);
    document.getElementById('sessionPaginationInfo').textContent = `Showing ${start}-${end} of ${sessionTotalCount} sessions`;

    // Update pagination controls
    updateSessionPagination();
}

function updateSessionPagination() {
    const pagination = document.getElementById('sessionPagination');
    if (!pagination) return;

    let html = '';

    // Previous button
    html += `<li class="page-item ${sessionCurrentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault(); loadSessions(${sessionCurrentPage - 1});">Previous</a>
    </li>`;

    // Page numbers (show max 5 pages)
    const startPage = Math.max(1, sessionCurrentPage - 2);
    const endPage = Math.min(sessionTotalPages, sessionCurrentPage + 2);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadSessions(1);">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><a class="page-link" href="#" onclick="return false;">...</a></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === sessionCurrentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault(); loadSessions(${i});">${i}</a>
        </li>`;
    }

    if (endPage < sessionTotalPages) {
        if (endPage < sessionTotalPages - 1) {
            html += `<li class="page-item disabled"><a class="page-link" href="#" onclick="return false;">...</a></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadSessions(${sessionTotalPages});">${sessionTotalPages}</a></li>`;
    }

    // Next button
    html += `<li class="page-item ${sessionCurrentPage === sessionTotalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="event.preventDefault(); loadSessions(${sessionCurrentPage + 1});">Next</a>
    </li>`;

    pagination.innerHTML = html;
}

function showSessionTerminateModal(sessionId) {
    if (!sessionTerminateModal) sessionTerminateModal = new bootstrap.Modal(document.getElementById('sessionTerminateModal'));
    currentSessionId = sessionId;
    sessionTerminateModal.show();
}

document.getElementById('sessionTerminateConfirmBtn')?.addEventListener('click', async () => {
    if (!currentSessionId) return;
    // Get fresh CSRF token from meta tag
    const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res  = await fetch(`${window.BASE_URL}/api/sessions/index.php?id=${currentSessionId}&_token=${freshToken}`, {
        method: 'DELETE',
        headers: {'X-CSRF-Token': freshToken},
        credentials: 'same-origin'
    });
    const json = await res.json();
    if (json.success) {
        sessionTerminateModal?.hide();
        loadSessions();
    } else {
        alert(json.error);
    }
});

// Save Security sub-tab state when changed
document.querySelectorAll('#securitySubTabs button').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function() {
        const targetId = this.getAttribute('data-bs-target');
        localStorage.setItem('securitySubTab', targetId);

        // Load data based on active sub-tab
        if (targetId === '#security-sessions') {
            loadSessions();
        } else if (targetId === '#security-devices') {
            loadDevices();
        }
    });
});

// Auto-load when Security tab is activated
document.getElementById('security-tab')?.addEventListener('shown.bs.tab', function () {
    // Restore saved sub-tab or default to General
    const savedSubTab = localStorage.getItem('securitySubTab') || '#security-general';
    const targetTab = document.querySelector(`#securitySubTabs button[data-bs-target="${savedSubTab}"]`);
    if (targetTab && !targetTab.classList.contains('active')) {
        new bootstrap.Tab(targetTab).show();
    }

    // Load data for the active sub-tab
    if (savedSubTab === '#security-sessions') {
        loadSessions();
    } else if (savedSubTab === '#security-devices') {
        loadDevices();
    }
});

// Initial load check - if Security tab is already active on page load
document.addEventListener('DOMContentLoaded', function() {
    const securityTab = document.getElementById('security-tab');
    if (securityTab && securityTab.classList.contains('active')) {
        // Restore saved sub-tab or default to General
        const savedSubTab = localStorage.getItem('securitySubTab') || '#security-general';
        const targetTab = document.querySelector(`#securitySubTabs button[data-bs-target="${savedSubTab}"]`);
        if (targetTab && !targetTab.classList.contains('active')) {
            new bootstrap.Tab(targetTab).show();
        }

        // Load data for the active sub-tab
        if (savedSubTab === '#security-sessions') {
            loadSessions();
        } else if (savedSubTab === '#security-devices') {
            loadDevices();
        }
    }
});

// Logo upload and preview functions
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please upload a valid image file (PNG, JPG, or WebP).');
            input.value = '';
            return;
        }
        
        // Validate file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Image size must be less than 10MB.');
            input.value = '';
            return;
        }
        
        // Read file and show in cropper modal
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
            
            document.getElementById('logoUploadPlaceholder').classList.add('d-none');
            document.getElementById('logoPreviewContainer').classList.remove('d-none');
            document.getElementById('btnCropLogo')?.classList.remove('d-none');
            document.getElementById('btnRemoveLogo')?.classList.remove('d-none');
            
            // Open cropper modal immediately
            openLogoCropper();
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    document.getElementById('systemLogoUpload').value = '';
    document.getElementById('systemLogoUrl').value = '';
    document.getElementById('logoPreview').src = '';
    
    document.getElementById('logoUploadPlaceholder').classList.remove('d-none');
    document.getElementById('logoPreviewContainer').classList.add('d-none');
    document.getElementById('btnCropLogo')?.classList.add('d-none');
    document.getElementById('btnRemoveLogo')?.classList.add('d-none');
}

// Handle form submission for logo upload
document.querySelector('form[name="settingsForm"]')?.addEventListener('submit', async function(e) {
    const logoInput = document.getElementById('systemLogoUpload');
    
    if (logoInput.files && logoInput.files[0]) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('logo', logoInput.files[0]);
        
        try {
            const res = await fetch(`${BASE_URL}/api/system-settings/upload-logo.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const json = await res.json();
            
            if (json.success) {
                // Set the logo URL in the hidden input
                document.getElementById('systemLogoUrl').value = json.logo_url;
                // Submit the form normally
                this.submit();
            } else {
                alert('Failed to upload logo: ' + (json.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Upload failed: ' + err.message);
        }
    }
});

// Wire up search/filter
document.getElementById('deviceSearch')?.addEventListener('input', debounce(loadDevices, 350));
document.getElementById('deviceStatusFilter')?.addEventListener('change', loadDevices);
document.getElementById('activeOnlyFilter')?.addEventListener('change', loadSessions);

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Logo Cropper Variables
let logoCropperModal = null;
let logoCropCanvas = null;
let logoPreviewCanvas = null;
let logoOriginalImage = null;
let logoScale = 1;
let logoOffsetX = 0;
let logoOffsetY = 0;
let logoRotation = 0;
let logoFlipH = 1;
let logoFlipV = 1;
let logoIsDragging = false;
let logoLastX = 0;
let logoLastY = 0;
let logoCanvasWidth = 500;
let logoCanvasHeight = 300;

function openLogoCropper() {
    const logoUrl = document.getElementById('logoPreview').src;
    if (!logoUrl || logoUrl === '') {
        alert('Please upload a logo first');
        return;
    }
    
    logoOriginalImage = new Image();
    logoOriginalImage.crossOrigin = 'anonymous';
    logoOriginalImage.onload = function() {
        if (!logoCropperModal) {
            logoCropperModal = new bootstrap.Modal(document.getElementById('logoCropperModal'));
        }
        
        logoCropCanvas = document.getElementById('logoCropCanvas');
        logoPreviewCanvas = document.getElementById('logoPreviewCanvas');
        
        // Use high-res internal canvas (500x300) for 5:3 ratio
        logoCanvasWidth = 500;
        logoCanvasHeight = 300;
        logoCropCanvas.width = logoCanvasWidth;
        logoCropCanvas.height = logoCanvasHeight;
        logoPreviewCanvas.width = logoCanvasWidth;
        logoPreviewCanvas.height = logoCanvasHeight;
        
        // Reset state
        logoScale = 1;
        logoOffsetX = 0;
        logoOffsetY = 0;
        logoRotation = 0;
        logoFlipH = 1;
        logoFlipV = 1;
        document.getElementById('logoZoomSlider').value = 1;
        
        // Auto-fit image to canvas
        autoFitImage();
        
        drawLogoCrop();
        logoCropperModal.show();
        
        // Attach canvas events after modal is shown
        setTimeout(function() {
            attachCanvasEvents();
            attachTouchEvents();
        }, 100);
    };
    logoOriginalImage.src = logoUrl;
}

function autoFitImage() {
    if (!logoOriginalImage) return;
    
    const imgRatio = logoOriginalImage.width / logoOriginalImage.height;
    const canvasRatio = logoCanvasWidth / logoCanvasHeight;
    
    // Scale image to COVER the canvas (fill mode - no padding)
    if (imgRatio > canvasRatio) {
        logoScale = logoCanvasHeight / logoOriginalImage.height;
    } else {
        logoScale = logoCanvasWidth / logoOriginalImage.width;
    }
    
    // Clamp to slider range
    const sliderVal = Math.min(Math.max(logoScale, 0.1), 3);
    document.getElementById('logoZoomSlider').value = sliderVal;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    // Reset offset so image is centered
    logoOffsetX = 0;
    logoOffsetY = 0;
}

function drawLogoCrop() {
    if (!logoOriginalImage || !logoCropCanvas) return;
    
    const ctx = logoCropCanvas.getContext('2d');
    const cw = logoCropCanvas.width;
    const ch = logoCropCanvas.height;
    
    ctx.clearRect(0, 0, cw, ch);
    
    // No background fill - preserve transparency for PNG output
    
    ctx.save();
    // Translate to center, rotate, scale, then offset by drag
    ctx.translate(cw / 2 + logoOffsetX, ch / 2 + logoOffsetY);
    ctx.rotate(logoRotation * Math.PI / 180);
    ctx.scale(logoScale * logoFlipH, logoScale * logoFlipV);
    
    // Draw image centered at origin
    ctx.drawImage(
        logoOriginalImage,
        -logoOriginalImage.width / 2,
        -logoOriginalImage.height / 2,
        logoOriginalImage.width,
        logoOriginalImage.height
    );
    
    ctx.restore();
    
    // Update preview
    updateLogoPreview();
}

function updateLogoPreview() {
    if (!logoPreviewCanvas || !logoCropCanvas) return;
    
    const previewCtx = logoPreviewCanvas.getContext('2d');
    previewCtx.clearRect(0, 0, logoPreviewCanvas.width, logoPreviewCanvas.height);
    
    // Draw cropped area as preview
    previewCtx.drawImage(logoCropCanvas, 0, 0, logoCropCanvas.width, logoCropCanvas.height, 0, 0, logoPreviewCanvas.width, logoPreviewCanvas.height);
}

function updateLogoZoom(value) {
    logoScale = parseFloat(value);
    document.getElementById('zoomValue').textContent = logoScale.toFixed(1) + 'x';
    drawLogoCrop();
}

function zoomLogoIn() {
    logoScale = Math.min(logoScale + 0.1, 3);
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    drawLogoCrop();
}

function zoomLogoOut() {
    logoScale = Math.max(logoScale - 0.1, 0.1);
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    drawLogoCrop();
}

function rotateLogoLogo(degrees) {
    logoRotation += degrees;
    // Normalize rotation to 0-360
    if (logoRotation >= 360) logoRotation -= 360;
    if (logoRotation < 0) logoRotation += 360;
    document.getElementById('rotationValue').textContent = logoRotation + '°';
    drawLogoCrop();
}

function flipLogoHorizontal() {
    logoFlipH *= -1;
    document.getElementById('flipHValue').textContent = logoFlipH === -1 ? 'On' : 'Off';
    drawLogoCrop();
}

function flipLogoVertical() {
    logoFlipV *= -1;
    document.getElementById('flipVValue').textContent = logoFlipV === -1 ? 'On' : 'Off';
    drawLogoCrop();
}

function resetLogoCrop() {
    autoFitImage();
    logoOffsetX = 0;
    logoOffsetY = 0;
    logoRotation = 0;
    logoFlipH = 1;
    logoFlipV = 1;
    document.getElementById('rotationValue').textContent = '0°';
    document.getElementById('flipHValue').textContent = 'Off';
    document.getElementById('flipVValue').textContent = 'Off';
    drawLogoCrop();
}

function applyLogoCrop() {
    if (!logoCropCanvas) return;
    
    // Convert canvas to blob and upload
    logoCropCanvas.toBlob(function(blob) {
        if (!blob) {
            alert('Failed to process image');
            return;
        }
        
        const file = new File([blob], 'cropped-logo.png', { type: 'image/png' });
        const formData = new FormData();
        formData.append('logo', file);
        
        fetch(`${BASE_URL}/api/system-settings/upload-logo.php`, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                document.getElementById('systemLogoUrl').value = json.logo_url;
                document.getElementById('logoPreview').src = BASE_URL + json.logo_url + '?t=' + Date.now();
                document.getElementById('logoUploadPlaceholder').classList.add('d-none');
                document.getElementById('logoPreviewContainer').classList.remove('d-none');
                document.getElementById('btnCropLogo')?.classList.remove('d-none');
                document.getElementById('btnRemoveLogo')?.classList.remove('d-none');
                logoCropperModal?.hide();
                showLogoToast('Logo saved successfully!', 'success');
            } else {
                showLogoToast('Failed to save: ' + (json.error || 'Unknown error'), 'danger');
            }
        })
        .catch(err => {
            showLogoToast('Upload failed: ' + err.message, 'danger');
        });
    }, 'image/png');
}

function showLogoToast(message, type = 'success') {
    const existing = document.getElementById('logoToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'logoToast';
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    toast.innerHTML = `
        <span class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></span>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4000);
}

// Canvas drag events - attach after modal is shown
function attachCanvasEvents() {
    const canvas = document.getElementById('logoCropCanvas');
    if (!canvas) return;
    
    // Remove existing listeners to prevent duplicates
    canvas.removeEventListener('mousedown', handleMouseDown);
    canvas.removeEventListener('wheel', handleWheel);
    
    // Add fresh listeners
    canvas.addEventListener('mousedown', handleMouseDown);
    canvas.addEventListener('wheel', handleWheel, { passive: false });
    
    canvas.style.cursor = 'move';
}

function handleMouseDown(e) {
    logoIsDragging = true;
    logoLastX = e.clientX;
    logoLastY = e.clientY;
    logoCropCanvas.style.cursor = 'grabbing';
}

function handleWheel(e) {
    e.preventDefault();
    
    const zoomSpeed = 0.1;
    const delta = e.deltaY > 0 ? -zoomSpeed : zoomSpeed;
    
    logoScale = Math.max(0.1, Math.min(3, logoScale + delta));
    document.getElementById('logoZoomSlider').value = logoScale;
    document.getElementById('zoomValue').textContent = logoScale.toFixed(2) + 'x';
    
    drawLogoCrop();
}

document.addEventListener('mousemove', function(e) {
    if (!logoIsDragging) return;
    
    const deltaX = e.clientX - logoLastX;
    const deltaY = e.clientY - logoLastY;
    
    logoOffsetX += deltaX;
    logoOffsetY += deltaY;
    
    logoLastX = e.clientX;
    logoLastY = e.clientY;
    
    drawLogoCrop();
});

document.addEventListener('mouseup', function() {
    logoIsDragging = false;
    if (logoCropCanvas) logoCropCanvas.style.cursor = 'move';
});

// Touch support for mobile
let logoInitialPinchDistance = 0;
let logoInitialScale = 1;

function attachTouchEvents() {
    const canvas = document.getElementById('logoCropCanvas');
    if (!canvas) return;
    
    canvas.removeEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
}

function handleTouchStart(e) {
    e.preventDefault();
    
    if (e.touches.length === 1) {
        // Single touch - drag
        const touch = e.touches[0];
        logoIsDragging = true;
        logoLastX = touch.clientX;
        logoLastY = touch.clientY;
    } else if (e.touches.length === 2) {
        // Pinch to zoom
        logoIsDragging = false;
        logoInitialPinchDistance = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        logoInitialScale = logoScale;
    }
}

document.addEventListener('touchmove', function(e) {
    if (e.touches.length === 1 && logoIsDragging) {
        // Single touch drag
        e.preventDefault();
        
        const touch = e.touches[0];
        const deltaX = touch.clientX - logoLastX;
        const deltaY = touch.clientY - logoLastY;
        
        logoOffsetX += deltaX;
        logoOffsetY += deltaY;
        
        logoLastX = touch.clientX;
        logoLastY = touch.clientY;
        
        drawLogoCrop();
    } else if (e.touches.length === 2) {
        // Pinch to zoom
        e.preventDefault();
        
        const currentPinchDistance = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        
        const scaleRatio = currentPinchDistance / logoInitialPinchDistance;
        logoScale = Math.max(0.5, Math.min(3, logoInitialScale * scaleRatio));
        document.getElementById('logoZoomSlider').value = logoScale;
        
        drawLogoCrop();
    }
});

document.addEventListener('touchend', function() {
    logoIsDragging = false;
    logoInitialPinchDistance = 0;
});
