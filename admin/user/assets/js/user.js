/* User Module Scripts */

// Handle profile image upload from profile page
function handleProfileImageUpload(input) {
    if (input.files && input.files[0]) {
        previewProfileImage(input.files[0]);
    }
}

// Profile Image Cropper Variables
let profileImageCropperModal = null;
let profileImageCropCanvas = null;
let profileImagePreviewCanvas = null;
let profileImageOriginalImage = null;
let profileImageScale = 1;
let profileImageOffsetX = 0;
let profileImageOffsetY = 0;
let profileImageRotation = 0;
let profileImageFlipH = 1;
let profileImageFlipV = 1;
let profileImageIsDragging = false;
let profileImageLastX = 0;
let profileImageLastY = 0;
let profileImageCanvasWidth = 1000;
let profileImageCanvasHeight = 1000;

function triggerProfileImageUpload() {
    console.log('triggerProfileImageUpload called');
    
    // Always trigger file upload for new image
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/png,image/jpeg,image/jpg,image/webp';
    fileInput.onchange = function(e) {
        if (e.target.files && e.target.files[0]) {
            previewProfileImage(e.target.files[0]);
        }
    };
    fileInput.click();
}

function previewProfileImage(file) {
    // Validate file type
    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        alert('Please upload a valid image file (PNG, JPG, or WebP).');
        return;
    }
    
    // Validate file size (max 5MB for profile images)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image size must be less than 5MB.');
        return;
    }
    
    // Read file and open cropper
    const reader = new FileReader();
    reader.onload = function(e) {
        openProfileImageCropper(e.target.result);
    };
    reader.readAsDataURL(file);
}

function openProfileImageCropper(imageUrl) {
    console.log('openProfileImageCropper called with URL:', imageUrl);
    
    // Check if modal element exists
    const modalElement = document.getElementById('profileImageCropperModal');
    if (!modalElement) {
        console.error('Modal element not found');
        alert('Profile image cropper modal not found. Please make sure you are on the settings page.');
        return;
    }
    
    profileImageOriginalImage = new Image();
    profileImageOriginalImage.crossOrigin = 'anonymous';
    profileImageOriginalImage.onload = function() {
        console.log('Image loaded');
        
        if (!profileImageCropperModal) {
            profileImageCropperModal = new bootstrap.Modal(modalElement);
        }
        
        profileImageCropCanvas = document.getElementById('profileImageCropCanvas');
        profileImagePreviewCanvas = document.getElementById('profileImagePreviewCanvas');
        
        if (!profileImageCropCanvas || !profileImagePreviewCanvas) {
            console.error('Canvas elements not found');
            alert('Canvas elements not found');
            return;
        }
        
        // Use 1000x1000 for 1:1 ratio - high resolution for profile images
        profileImageCanvasWidth = 1000;
        profileImageCanvasHeight = 1000;
        profileImageCropCanvas.width = profileImageCanvasWidth;
        profileImageCropCanvas.height = profileImageCanvasHeight;
        profileImagePreviewCanvas.width = profileImageCanvasWidth;
        profileImagePreviewCanvas.height = profileImageCanvasHeight;
        
        // Reset state
        profileImageScale = 1;
        profileImageOffsetX = 0;
        profileImageOffsetY = 0;
        profileImageRotation = 0;
        profileImageFlipH = 1;
        profileImageFlipV = 1;
        document.getElementById('profileImageZoomSlider').value = 1;
        
        // Auto-fit image to canvas
        autoFitProfileImage();
        
        drawProfileImageCrop();
        profileImageCropperModal.show();
        
        // Attach canvas events after modal is shown
        setTimeout(function() {
            attachProfileImageCanvasEvents();
        }, 100);
    };
    profileImageOriginalImage.onerror = function() {
        console.error('Failed to load image');
        alert('Failed to load image');
    };
    profileImageOriginalImage.src = imageUrl;
}

function autoFitProfileImage() {
    if (!profileImageOriginalImage) return;
    
    const imgRatio = profileImageOriginalImage.width / profileImageOriginalImage.height;
    const canvasRatio = profileImageCanvasWidth / profileImageCanvasHeight;
    
    // Scale image to COVER the canvas (fill mode - no padding)
    if (imgRatio > canvasRatio) {
        profileImageScale = profileImageCanvasHeight / profileImageOriginalImage.height;
    } else {
        profileImageScale = profileImageCanvasWidth / profileImageOriginalImage.width;
    }
    
    // Clamp to slider range
    const sliderVal = Math.min(Math.max(profileImageScale, 0.1), 3);
    document.getElementById('profileImageZoomSlider').value = sliderVal;
    document.getElementById('profileImageZoomValue').textContent = profileImageScale.toFixed(2) + 'x';
    // Reset offset so image is centered
    profileImageOffsetX = 0;
    profileImageOffsetY = 0;
}

function drawProfileImageCrop() {
    if (!profileImageOriginalImage || !profileImageCropCanvas) return;
    
    const ctx = profileImageCropCanvas.getContext('2d');
    const cw = profileImageCropCanvas.width;
    const ch = profileImageCropCanvas.height;
    
    ctx.clearRect(0, 0, cw, ch);
    
    // No background fill - preserve transparency for PNG output
    
    ctx.save();
    // Translate to center, rotate, scale, then offset by drag
    ctx.translate(cw / 2 + profileImageOffsetX, ch / 2 + profileImageOffsetY);
    ctx.rotate(profileImageRotation * Math.PI / 180);
    ctx.scale(profileImageScale * profileImageFlipH, profileImageScale * profileImageFlipV);
    
    // Draw image centered at origin
    ctx.drawImage(
        profileImageOriginalImage,
        -profileImageOriginalImage.width / 2,
        -profileImageOriginalImage.height / 2,
        profileImageOriginalImage.width,
        profileImageOriginalImage.height
    );
    
    ctx.restore();
    
    // Update preview
    updateProfileImagePreview();
}

function updateProfileImagePreview() {
    if (!profileImagePreviewCanvas || !profileImageCropCanvas) return;
    
    const previewCtx = profileImagePreviewCanvas.getContext('2d');
    previewCtx.clearRect(0, 0, profileImagePreviewCanvas.width, profileImagePreviewCanvas.height);
    
    // Draw cropped area as preview
    previewCtx.drawImage(profileImageCropCanvas, 0, 0, profileImageCropCanvas.width, profileImageCropCanvas.height, 0, 0, profileImagePreviewCanvas.width, profileImagePreviewCanvas.height);
}

function updateProfileImageZoom(value) {
    profileImageScale = parseFloat(value);
    document.getElementById('profileImageZoomValue').textContent = profileImageScale.toFixed(1) + 'x';
    drawProfileImageCrop();
}

function zoomProfileImageIn() {
    profileImageScale = Math.min(profileImageScale + 0.1, 3);
    document.getElementById('profileImageZoomSlider').value = profileImageScale;
    document.getElementById('profileImageZoomValue').textContent = profileImageScale.toFixed(2) + 'x';
    drawProfileImageCrop();
}

function zoomProfileImageOut() {
    profileImageScale = Math.max(profileImageScale - 0.1, 0.1);
    document.getElementById('profileImageZoomSlider').value = profileImageScale;
    document.getElementById('profileImageZoomValue').textContent = profileImageScale.toFixed(2) + 'x';
    drawProfileImageCrop();
}

function rotateProfileImage(degrees) {
    profileImageRotation += degrees;
    // Normalize rotation to 0-360
    if (profileImageRotation >= 360) profileImageRotation -= 360;
    if (profileImageRotation < 0) profileImageRotation += 360;
    document.getElementById('profileImageRotationValue').textContent = profileImageRotation + '°';
    drawProfileImageCrop();
}

function flipProfileImageHorizontal() {
    profileImageFlipH *= -1;
    document.getElementById('profileImageFlipHValue').textContent = profileImageFlipH === -1 ? 'On' : 'Off';
    drawProfileImageCrop();
}

function flipProfileImageVertical() {
    profileImageFlipV *= -1;
    document.getElementById('profileImageFlipVValue').textContent = profileImageFlipV === -1 ? 'On' : 'Off';
    drawProfileImageCrop();
}

function resetProfileImageCrop() {
    autoFitProfileImage();
    profileImageOffsetX = 0;
    profileImageOffsetY = 0;
    profileImageRotation = 0;
    profileImageFlipH = 1;
    profileImageFlipV = 1;
    document.getElementById('profileImageRotationValue').textContent = '0°';
    document.getElementById('profileImageFlipHValue').textContent = 'Off';
    document.getElementById('profileImageFlipVValue').textContent = 'Off';
    drawProfileImageCrop();
}

function applyProfileImageCrop() {
    if (!profileImageCropCanvas) return;
    
    // Convert canvas to blob and upload
    profileImageCropCanvas.toBlob(function(blob) {
        if (!blob) {
            alert('Failed to process image');
            return;
        }
        
        const file = new File([blob], 'profile-image.png', { type: 'image/png' });
        const formData = new FormData();
        formData.append('profile_image', file);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        formData.append('csrf_token', csrfToken);
        
        fetch(`${window.BASE_URL}/api/user/profile-image.php`, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                // Update profile image display
                const profileImg = document.querySelector('.avatar-profile img');
                if (profileImg) {
                    profileImg.src = window.BASE_URL + json.profile_image_url + '?t=' + Date.now();
                } else {
                    // If no img element, create one
                    const avatarContainer = document.querySelector('.avatar-profile');
                    if (avatarContainer) {
                        const initialsDiv = avatarContainer.querySelector('.avatar-name');
                        if (initialsDiv) {
                            initialsDiv.remove();
                        }
                        const img = document.createElement('img');
                        img.className = 'rounded-circle img-thumbnail shadow-sm';
                        img.src = window.BASE_URL + json.profile_image_url + '?t=' + Date.now();
                        img.alt = 'Profile Image';
                        avatarContainer.appendChild(img);
                    }
                }
                profileImageCropperModal?.hide();
                showProfileImageToast('Profile image saved successfully!', 'success');
            } else {
                showProfileImageToast('Failed to save: ' + (json.error || 'Unknown error'), 'danger');
            }
        })
        .catch(err => {
            showProfileImageToast('Upload failed: ' + err.message, 'danger');
        });
    }, 'image/png');
}

function showProfileImageToast(message, type = 'success') {
    const existing = document.getElementById('profileImageToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'profileImageToast';
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

// Canvas drag events
function attachProfileImageCanvasEvents() {
    const canvas = document.getElementById('profileImageCropCanvas');
    if (!canvas) return;
    
    canvas.addEventListener('mousedown', handleProfileImageMouseDown);
    canvas.addEventListener('wheel', handleProfileImageWheel, { passive: false });
    canvas.style.cursor = 'move';
}

function handleProfileImageMouseDown(e) {
    profileImageIsDragging = true;
    profileImageLastX = e.clientX;
    profileImageLastY = e.clientY;
    e.preventDefault();
}

function handleProfileImageMouseMove(e) {
    if (!profileImageIsDragging) return;
    
    const dx = e.clientX - profileImageLastX;
    const dy = e.clientY - profileImageLastY;
    
    profileImageOffsetX += dx;
    profileImageOffsetY += dy;
    
    profileImageLastX = e.clientX;
    profileImageLastY = e.clientY;
    
    drawProfileImageCrop();
}

function handleProfileImageMouseUp() {
    profileImageIsDragging = false;
}

function handleProfileImageWheel(e) {
    e.preventDefault();
    
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    profileImageScale = Math.min(Math.max(profileImageScale + delta, 0.1), 3);
    
    document.getElementById('profileImageZoomSlider').value = profileImageScale;
    document.getElementById('profileImageZoomValue').textContent = profileImageScale.toFixed(2) + 'x';
    
    drawProfileImageCrop();
}

// Global event listeners for drag
document.addEventListener('mousemove', handleProfileImageMouseMove);
document.addEventListener('mouseup', handleProfileImageMouseUp);
