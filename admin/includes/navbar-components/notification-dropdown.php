<?php
/**
 * Notification Dropdown Component
 * Renders the notification bell dropdown
 * Usage: include __DIR__ . '/navbar-components/notification-dropdown.php';
 *
 * Parameters (optional):
 * - $dropdownId: Unique ID for the dropdown (default: 'navbarDropdownNotification')
 *
 * Access Control: Only users with VIEW_NOTIFICATIONS permission can see notifications
 */
$dropdownId = $dropdownId ?? 'navbarDropdownNotification';

// Fetch system settings for notification role access
$systemSettings = Database::fetch("SELECT notification_roles FROM system_settings WHERE setting_id = 1");
$notificationRoles = $systemSettings['notification_roles'] ?? null;

// Permission-based access control
$showNotifications = false;
$user = Auth::user();

if ($user) {
    // SUPER_ADMIN always has access
    if ($user['role_code'] === 'SUPER_ADMIN') {
        $showNotifications = true;
    }
    // Check if notification_roles is set and user's role is in the list
    elseif ($notificationRoles) {
        $allowedRoles = array_map('trim', explode(',', $notificationRoles));
        $showNotifications = in_array($user['role_code'], $allowedRoles);
    }
    // Fallback to VIEW_NOTIFICATIONS permission if no system setting
    elseif (Auth::can('VIEW_NOTIFICATIONS')) {
        $showNotifications = true;
    }
}

if (!$showNotifications) {
    return; // Don't render notification dropdown for users without permission
}
?>

<li class="nav-item dropdown d-flex align-items-center">
  <a class="nav-link notification-indicator notification-indicator-primary px-0 fa-icon-wait position-relative" 
     id="<?php echo $dropdownId; ?>" 
     role="button" 
     data-bs-toggle="dropdown" 
     aria-haspopup="true" 
     aria-expanded="false" 
     data-hide-on-body-scroll="data-hide-on-body-scroll">
    <span class="fas fa-bell" data-fa-transform="shrink-6" style="font-size: 33px;"></span>
    <span class="notification-badge badge rounded-pill bg-danger d-none" 
          id="notificationBadge" 
          style="position: absolute; top: 0.5rem; right: 0.125rem; font-size: 0.67rem; font-weight: 700; color: #fff; min-width: 1rem; height: 1rem; padding: 0 0.25rem; line-height: 1rem;">0</span>
  </a>
  <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-menu-notification dropdown-caret-bg" aria-labelledby="<?php echo $dropdownId; ?>">
    <div class="card card-notification shadow-none">
      <div class="card-header">
        <div class="row justify-content-between align-items-center">
          <div class="col-auto">
            <h6 class="card-header-title mb-0">Notifications</h6>
          </div>
          <div class="col-auto ps-0 ps-sm-3">
            <a class="card-link fw-normal" href="#" onclick="markAllAsRead(event)">Mark all as read</a>
          </div>
        </div>
      </div>
      <div class="scrollbar-overlay" style="max-height:19rem">
        <div class="list-group list-group-flush fw-normal fs-10" id="notificationList">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer text-center border-top">
        <a class="card-link d-block" href="<?php echo BASE_URL; ?>/admin/notifications/">View all</a>
      </div>
    </div>
  </div>
</li>

<script>
(function() {
    let notificationDropdown = document.getElementById('<?php echo $dropdownId; ?>');
    let notificationList = document.getElementById('notificationList');
    let notificationBadge = document.getElementById('notificationBadge');
    
    // Fetch notifications from API
    async function fetchNotifications() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/notifications/?limit=10');
            const result = await response.json();
            
            if (result.success) {
                renderNotifications(result.data);
                updateBadge(result.unread_count);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            notificationList.innerHTML = '<div class="text-center py-4 text-muted">Failed to load notifications</div>';
        }
    }
    
    // Render notifications
    function renderNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="text-center py-4">
                    <div class="avatar avatar-xxl mb-3">
                        <div class="avatar-name rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                            <span class="fas fa-bell fs-4"></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">No notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        let lastReadStatus = null;
        
        notifications.forEach(notification => {
            const isRead = notification.is_read;
            const readClass = isRead ? '' : 'notification-unread';
            const timeAgo = getTimeAgo(notification.created_at);
            
            // Add section header if read status changed
            if (lastReadStatus !== isRead) {
                if (!isRead) {
                    html += '<div class="list-group-title border-bottom">NEW</div>';
                } else {
                    html += '<div class="list-group-title border-bottom">EARLIER</div>';
                }
                lastReadStatus = isRead;
            }
            
            html += `
                <div class="list-group-item">
                    <a class="notification notification-flush ${readClass}" href="${notification.link ? '<?php echo BASE_URL; ?>' + notification.link : '#'}" onclick="markAsRead(event, ${notification.notification_id})">
                        <div class="notification-avatar">
                            <div class="avatar avatar-2xl me-3">
                                <div class="avatar-name rounded-circle bg-${notification.color}-subtle text-${notification.color} d-flex align-items-center justify-content-center">
                                    <span class="fas ${notification.icon}"></span>
                                </div>
                            </div>
                        </div>
                        <div class="notification-body">
                            <p class="mb-1"><strong>${notification.title}</strong> ${notification.message}</p>
                            <span class="notification-time"><span class="me-2 fas ${notification.icon} text-${notification.color}"></span>${timeAgo}</span>
                        </div>
                    </a>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
    }
    
    // Update badge count
    function updateBadge(count) {
        if (count > 0) {
            notificationBadge.textContent = count > 99 ? '99+' : count;
            notificationBadge.classList.remove('d-none');
        } else {
            notificationBadge.classList.add('d-none');
        }
    }
    
    // Get time ago string
    function getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'hr ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
        return date.toLocaleDateString();
    }
    
    // Mark notification as read
    window.markAsRead = async function(event, notificationId) {
        if (event) event.preventDefault();
        
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>/api/notifications/${notificationId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_read: true })
            });
            
            if (response.ok) {
                fetchNotifications(); // Refresh notifications
                
                // Navigate to the link if it exists
                const link = event.target.closest('a').getAttribute('href');
                if (link && link !== '#') {
                    window.location.href = link;
                }
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    };
    
    // Mark all as read
    window.markAllAsRead = async function(event) {
        if (event) event.preventDefault();
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/notifications/mark-all', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                fetchNotifications(); // Refresh notifications
            }
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };
    
    // Initial fetch
    fetchNotifications();
    
    // Poll for new notifications every 30 seconds
    setInterval(fetchNotifications, 30000);
    
    // Fetch when dropdown is opened
    if (notificationDropdown) {
        notificationDropdown.addEventListener('click', function() {
            fetchNotifications();
        });
    }
})();
</script>
