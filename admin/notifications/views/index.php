<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/head.php'; ?>

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
        </script>
        
        <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?>
            <?php if (NAVBAR_POSITION === 'top'): ?>
                <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php'; ?>
            <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
                <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-double-top.php'; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/sidebar.php'; ?>
        <?php endif; ?>
        
        <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
            <div class="content">
                <?php
                switch (NAVBAR_POSITION) {
                    case 'combo':
                        include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php';
                        break;
                    case 'vertical':
                        include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar.php';
                        break;
                    case 'top':
                    case 'double-top':
                        break;
                }
                ?>

                <!-- ===============================================-->
                <!--    Content-->
                <!-- ===============================================-->
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Notifications</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">Mark all as read</button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <div class="avatar avatar-xxl mb-3">
                                    <div class="avatar-name rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                                        <span class="fas fa-bell fs-4"></span>
                                    </div>
                                </div>
                                <h5 class="text-muted">No notifications</h5>
                                <p class="text-muted small">You're all caught up!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $notificationTitle = trim((string) ($notification['title'] ?? ''));
                                    $notificationMessage = trim((string) ($notification['message'] ?? ''));
                                    if ($notificationTitle === '') {
                                        $notificationTitle = ucwords(str_replace('_', ' ', (string) ($notification['type'] ?? 'Notification')));
                                    }
                                    if ($notificationMessage === '') {
                                        $notificationMessage = 'Notification details are not available.';
                                    }
                                    $notificationColor = in_array(($notification['color'] ?? ''), ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true)
                                        ? $notification['color'] : 'info';
                                    $notificationIcon = preg_match('/^[a-z0-9-]+$/i', (string) ($notification['icon'] ?? ''))
                                        ? $notification['icon'] : 'fa-bell';
                                    $notificationLink = (is_string($notification['link'] ?? null)
                                        && str_starts_with($notification['link'], '/')
                                        && !str_starts_with($notification['link'], '//'))
                                        ? BASE_URL . $notification['link'] : '#';
                                    ?>
                                    <div class="list-group-item list-group-item-action <?php echo !$notification['is_read'] ? 'bg-soft-info' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="avatar avatar-xl me-3 flex-shrink-0">
                                                <div class="avatar-name rounded-circle bg-<?php echo htmlspecialchars($notificationColor, ENT_QUOTES, 'UTF-8'); ?>-subtle text-<?php echo htmlspecialchars($notificationColor, ENT_QUOTES, 'UTF-8'); ?> d-flex align-items-center justify-content-center">
                                                    <span class="fas <?php echo htmlspecialchars($notificationIcon, ENT_QUOTES, 'UTF-8'); ?>"></span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                            <?php echo htmlspecialchars($notificationTitle, ENT_QUOTES, 'UTF-8'); ?>
                                                            <?php if (!$notification['is_read']): ?>
                                                                <span class="badge bg-primary ms-2">New</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($notificationMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <small class="text-muted">
                                                            <span class="fas fa-clock me-1"></span>
                                                            <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <a href="<?php echo htmlspecialchars($notificationLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                        View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/footer.php'; ?>
      </div>
    </main>

    <script>
    async function markAllAsRead() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/notifications/mark-all', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                // Reload page to show updated notifications
                window.location.reload();
            } else {
                alert('Failed to mark all as read');
            }
        } catch (error) {
            console.error('Failed to mark all as read:', error);
            alert('Failed to mark all as read');
        }
    }

    document.addEventListener('tms:notification-created', function() {
        if (document.visibilityState === 'visible') {
            window.location.reload();
        }
    });
    </script>

    <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/scripts.php'; ?>
  </body>
</html>
