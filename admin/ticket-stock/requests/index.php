<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$allowedBranchIds = PosAccess::allowedBranchIds($user);
$canViewAllTicketStock = $userRoleCode === 'SUPER_ADMIN' || Auth::can('VIEW_ALL_TICKET_STOCK');
if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_TICKET_STOCK_REQUESTS') && !Auth::canAccessModule('admin/ticket-stock/requests/')) {
    $message = 'You do not have permission to access Ticket Stock Requests.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
$userBranchIds = $canViewAllTicketStock || $allowedBranchIds === null ? [] : $allowedBranchIds;
$branchWhere = ["status = 'active'"];
$branchParams = [];
if (!$canViewAllTicketStock) {
    PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'request_branch');
}
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);
$defaultDestinationBranchId = null;
foreach ($branches as $branch) {
    if (in_array((int) $branch['branch_id'], $userBranchIds, true)) {
        $defaultDestinationBranchId = (int) $branch['branch_id'];
        break;
    }
}
$providers = Database::fetchAll(
    "SELECT provider_id, provider_code, provider_name
     FROM ticket_providers
     WHERE status = 'active'
       AND EXISTS (
           SELECT 1
           FROM provider_ticket_variants
           WHERE provider_ticket_variants.provider_id = ticket_providers.provider_id
             AND provider_ticket_variants.is_active = 1
             AND provider_ticket_variants.deleted_at IS NULL
       )
     ORDER BY provider_name"
);
$variants = Database::fetchAll("SELECT variant_id, provider_id, variant_code, variant_name FROM provider_ticket_variants WHERE is_active = 1 AND deleted_at IS NULL ORDER BY variant_name");

include __DIR__ . '/views/index.php';
