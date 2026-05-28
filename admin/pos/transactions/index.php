<?php
/**
 * POS Transactions Report Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();
$user         = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Fetch system settings for view
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'TMS', ENT_QUOTES, 'UTF-8');
$systemLogo = $systemSettings['system_logo'] ?? null;

// Validate logo URL
if ($systemLogo) {
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null;
    }
}

include __DIR__ . '/views/index.php';
