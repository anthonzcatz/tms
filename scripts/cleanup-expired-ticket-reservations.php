<?php
/**
 * Cron script: clean up expired ticket stock reservations.
 *
 * Abandoned POS reservations (cart timeouts, cancelled checkouts, etc.)
 * reserve physical stock in branch_ticket_stocks.reserved_qty. If they are
 * never explicitly released, the stock remains unavailable for sale even
 * though the physical tickets are still on hand.
 *
 * Schedule this script to run every 1-5 minutes via Windows Task Scheduler
 * or any cron facility.
 *
 * Example Windows Task Scheduler action:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: "C:\xampp\htdocs\TMS\scripts\cleanup-expired-ticket-reservations.php"
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/TicketStockHelper.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$batchSize = 100;

$pdo = Database::connection();

$cleaned = 0;
$failed  = 0;

while (true) {
    // Fetch the next batch of expired reservations. Process in small chunks
    // to avoid long-running transactions and reduce lock contention.
    $reservations = Database::fetchAll(
        "SELECT reservation_id, branch_id, provider_id, variant_id, reserved_qty,
                session_id, pos_order_id
         FROM ticket_stock_reservations
         WHERE expires_at < NOW()
         ORDER BY reservation_id ASC
         LIMIT :limit",
        ['limit' => $batchSize]
    );

    if (empty($reservations)) {
        break;
    }

    foreach ($reservations as $res) {
        $reservationId = (int) $res['reservation_id'];
        $branchId      = (int) $res['branch_id'];
        $providerId    = (int) $res['provider_id'];
        $variantId       = (int) $res['variant_id'];
        $qty             = (int) $res['reserved_qty'];

        try {
            if ($dryRun) {
                echo "[DRY-RUN] Would release reservation #{$reservationId} " .
                     "(branch={$branchId}, provider={$providerId}, variant={$variantId}, qty={$qty})\n";
                $cleaned++;
                continue;
            }

            TicketStockHelper::releaseReservation(
                $branchId,
                $providerId,
                $variantId,
                $qty,
                0, // system / cron user
                [
                    'session_id'   => $res['session_id'] ?? null,
                    'pos_order_id' => $res['pos_order_id'] ?? null,
                ]
            );

            $cleaned++;
        } catch (Exception $e) {
            $failed++;
            error_log("Failed to release expired reservation #{$reservationId}: " . $e->getMessage());
        }
    }

    // If we fetched fewer than batchSize, we've processed everything.
    if (count($reservations) < $batchSize) {
        break;
    }
}

$message = sprintf(
    "Expired ticket reservation cleanup complete. Released: %d, Failed: %d",
    $cleaned,
    $failed
);

echo $message . "\n";
error_log($message);
