<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$user = Auth::user();
if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('VIEW_TICKET_STOCK_REQUESTS') && !Auth::canAccessModule('admin/ticket-stock/requests/')) {
    $message = 'You do not have permission to access Ticket Stock Requests.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
$userBranchIds = array_values(array_filter(array_map('intval', explode(',', (string) (Auth::userBranchId() ?? '')))));
if (($user['role_code'] ?? '') === 'SUPER_ADMIN') {
    $branches = Database::fetchAll("SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name");
} elseif ($userBranchIds) {
    $branchPlaceholders = [];
    $branchParams = [];
    foreach ($userBranchIds as $index => $branchId) {
        $key = 'user_branch_' . $index;
        $branchPlaceholders[] = ':' . $key;
        $branchParams[$key] = $branchId;
    }
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name
         FROM business_branches
         WHERE status = 'active'
           AND branch_id IN (" . implode(',', $branchPlaceholders) . ")
         ORDER BY branch_name",
        $branchParams
    );
} else {
    $branches = [];
}
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

$walletWhere = ["pw.status = 'active'"];
$walletParams = [];
if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !empty($user['branch_id'])) {
    $branchIds = array_values(array_filter(array_map('intval', explode(',', (string) $user['branch_id']))));
    if ($branchIds) {
        $walletPlaceholders = [];
        foreach ($branchIds as $index => $branchId) {
            $key = 'wallet_branch_' . $index;
            $walletPlaceholders[] = ':' . $key;
            $walletParams[$key] = $branchId;
        }
        $walletWhere[] = 'pw.branch_id IN (' . implode(',', $walletPlaceholders) . ')';
    } else {
        $walletWhere[] = '1 = 0';
    }
}
$wallets = Database::fetchAll(
    "SELECT pw.wallet_id, pw.provider_id, pw.branch_id, pw.variant_id,
            pw.current_balance, tp.provider_name,
            parent.provider_name AS parent_provider_name,
            bb.branch_name, pv.variant_name, pv.display_color AS variant_color,
            COALESCE(bts.on_hand_qty, 0) AS on_hand_qty,
            COALESCE(bts.reserved_qty, 0) AS reserved_qty,
            COALESCE(bts.on_hand_qty - bts.reserved_qty, 0) AS available_qty,
            CASE
                WHEN pv.variant_name IS NOT NULL THEN CONCAT(tp.provider_name, ' - ', pv.variant_name, ' - ', bb.branch_name)
                WHEN parent.provider_name IS NOT NULL THEN CONCAT(parent.provider_name, ' - ', bb.branch_name)
                ELSE CONCAT(tp.provider_name, ' - ', bb.branch_name)
            END AS wallet_name
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON tp.provider_id = pw.provider_id
     LEFT JOIN ticket_providers parent ON parent.provider_id = tp.parent_provider_id
     LEFT JOIN business_branches bb ON bb.branch_id = pw.branch_id
     LEFT JOIN provider_ticket_variants pv ON pv.variant_id = pw.variant_id
     LEFT JOIN branch_ticket_stocks bts
       ON bts.branch_id = pw.branch_id
      AND bts.provider_id = pw.provider_id
      AND bts.variant_id = pw.variant_id
     WHERE " . implode(' AND ', $walletWhere) . "
     ORDER BY tp.provider_name, pv.variant_name, bb.branch_name",
    $walletParams
);

// Keep both provider-level and variant-specific wallets available as source identities.
// Stock quantities are still maintained only through branch_ticket_stocks.

include __DIR__ . '/views/index.php';
