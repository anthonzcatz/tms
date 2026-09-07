<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

require_once dirname(dirname(__DIR__)) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
if ((!$user || $user['role_code'] !== 'SUPER_ADMIN')
    && !Auth::can('VIEW_SETTINGS')
    && !Auth::canAccessModule('admin/settings/accommodation-types/')) {
    $message = 'You do not have permission to access the Accommodation Types module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$accommodationTypes = Database::fetchAll(
    "SELECT at.*,
            (SELECT COUNT(*) FROM ticket_transactions tt WHERE tt.accommodation_id = at.accommodation_id) AS ticket_count,
            (SELECT COUNT(*) FROM pos_order_items oi WHERE oi.accommodation_id = at.accommodation_id) AS order_item_count
     FROM accommodation_types at
     ORDER BY at.is_default DESC, at.name ASC"
);

include __DIR__ . '/views/index.php';
