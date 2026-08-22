<?php
/**
 * Backfill: split service add-ons into separate customer_charges columns.
 *
 * Existing rows may have add-on amounts hidden in total_charged/balance
 * without separate add_on_charged/add_on_balance tracking. This script
 * recalculates ticket base, add-on and fee splits per customer.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/helpers/ChargeService.php';
require_once __DIR__ . '/../config/database.php';

try {
    Database::connection()->beginTransaction();

    $affected = 0;
    $rows = Database::fetchAll(
        "SELECT passenger_id, total_charged, total_paid, base_charged, base_paid,
                fee_charged, fee_paid, base_balance, fee_balance, balance
         FROM customer_charges"
    );

    foreach ($rows as $row) {
        $passengerId = (int) $row['passenger_id'];
        $totalCharged = (float) $row['total_charged'];
        $totalPaid    = (float) $row['total_paid'];
        $feeCharged   = (float) $row['fee_charged'];
        $feePaid      = (float) $row['fee_paid'];
        $baseBalance  = (float) $row['base_balance'];
        $feeBalance   = (float) $row['fee_balance'];
        $balance      = (float) $row['balance'];

        $addOnTotal = ChargeService::getAddOnTotalForCustomer($passengerId);

        // Fallback: if service pos_order_items were not recorded (old transactions),
        // the unallocated amount in balance is the add-on.
        $addOnFromBalance = round($balance - $baseBalance - $feeBalance, 2);
        if ($addOnTotal <= 0 && $addOnFromBalance > 0) {
            $addOnTotal = $addOnFromBalance;
        }

        // Ticket base is whatever remains after fee and add-on.
        // For COMPANY mode, fee_charged is 0 in the customer record.
        $ticketBase = round($totalCharged - $feeCharged - $addOnTotal, 2);
        if ($ticketBase < 0) {
            // Defensive: if data is inconsistent, do not go negative.
            $ticketBase = max(0, round($totalCharged - $feeCharged, 2));
            $addOnTotal = max(0, round($totalCharged - $feeCharged - $ticketBase, 2));
        }
        
        // Base paid currently includes base + add-on payments (legacy combined).
        // Derive add-on portion paid after ticket base is fully paid.
        $basePaidCombined = (float) $row['base_paid'];
        $addOnPaid = max(0.00, round($basePaidCombined - $ticketBase, 2));
        $addOnPaid = min($addOnPaid, $addOnTotal);
        $basePaid  = round($basePaidCombined - $addOnPaid, 2);

        $newBaseCharged = $ticketBase;
        $newAddOnCharged = $addOnTotal;
        $newBaseBalance = round($newBaseCharged - $basePaid, 2);
        $newAddOnBalance = round($newAddOnCharged - $addOnPaid, 2);
        $newFeeBalance = round($feeCharged - $feePaid, 2);
        $newBalance = round($totalCharged - $totalPaid, 2);

        Database::execute(
            "UPDATE customer_charges
             SET base_charged = :base_charged,
                 add_on_charged = :add_on_charged,
                 base_paid = :base_paid,
                 add_on_paid = :add_on_paid,
                 base_balance = :base_balance,
                 add_on_balance = :add_on_balance,
                 fee_balance = :fee_balance,
                 balance = :balance,
                 status = :status,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'base_charged' => $newBaseCharged,
                'add_on_charged' => $newAddOnCharged,
                'base_paid' => $basePaid,
                'add_on_paid' => $addOnPaid,
                'base_balance' => $newBaseBalance,
                'add_on_balance' => $newAddOnBalance,
                'fee_balance' => $newFeeBalance,
                'balance' => $newBalance,
                'status' => $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR',
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        if ($addOnTotal > 0 || (float) $row['base_charged'] != $newBaseCharged) {
            $affected++;
        }
    }

    Database::connection()->commit();
    echo "Backfill completed. {$affected} customer(s) updated.\n";
} catch (Exception $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo "Backfill failed: " . $e->getMessage() . "\n";
    exit(1);
}
