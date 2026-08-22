<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$user = Auth::user();
if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('VIEW_TICKET_STOCK_DISCREPANCIES') && !Auth::canAccessModule('admin/ticket-stock/discrepancies/')) {
    $message = 'You do not have permission to access Ticket Stock Discrepancies.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
include __DIR__ . '/views/index.php';
