<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$user = Auth::user();
if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('VIEW_TICKET_STOCK_MOVEMENTS') && !Auth::canAccessModule('admin/ticket-stock/movements/')) {
    $message = 'You do not have permission to access Ticket Stock Movements.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
$branches = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name");
$providers = Database::fetchAll("SELECT provider_id, provider_name FROM ticket_providers WHERE status = 'active' ORDER BY provider_name");
$movementDate = date('Y-m-d');
include __DIR__ . '/views/index.php';
