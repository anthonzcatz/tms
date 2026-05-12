<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/permissions/assets/css/permissions.css">
</head>
<body>
    <div class="access-denied-container">
        <div class="icon-container">
            <i class="fas fa-lock"></i>
        </div>
        <h1>Access Denied</h1>
        <p>You do not have permission to access this resource. Only SUPER_ADMIN can manage permissions.</p>
        <a href="<?php echo BASE_URL; ?>/admin/dashboard/analytics" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</body>
</html>
