<?php
/**
 * One-time backfill: reallocate existing customer fee balances for accounts
 * already set to COMPANY or WAIVED service fee mode.
 *
 * For COMPANY: remaining fee balance is moved to the company/CEO account.
 * For WAIVED: remaining fee balance is cleared from the customer record.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/ChargeService.php';

try {
    Database::connection()->beginTransaction();

    $affected = 0;
    $rows = Database::fetchAll(
        "SELECT passenger_id, base_charged, base_paid, fee_charged, fee_paid, total_paid,
                service_fee_mode, company_passenger_id, balance
         FROM customer_charges
         WHERE service_fee_mode IN ('COMPANY', 'WAIVED')
           AND fee_charged > fee_paid"
    );

    foreach ($rows as $row) {
        $passengerId      = (int) $row['passenger_id'];
        $baseCharged      = (float) $row['base_charged'];
        $basePaid         = (float) $row['base_paid'];
        $feeCharged       = (float) $row['fee_charged'];
        $feePaid          = (float) $row['fee_paid'];
        $totalPaid        = (float) $row['total_paid'];
        $mode             = $row['service_fee_mode'];
        $companyPassengerId = $row['company_passenger_id'] ? (int) $row['company_passenger_id'] : null;
        $feeBalance       = round($feeCharged - $feePaid, 2);

        if ($feeBalance <= 0) {
            continue;
        }

        if ($mode === 'COMPANY' && $companyPassengerId) {
            ChargeService::addToCustomerCharge($companyPassengerId, $feeBalance, 0, $feeBalance);
        }

        $newFeeCharged   = $feePaid;
        $newFeeBalance   = 0.00;
        $newTotalCharged = $baseCharged + $newFeeCharged;
        $newBalance      = $newTotalCharged - $totalPaid;
        $newBaseBalance  = $baseCharged - $basePaid;
        $newStatus       = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';

        Database::execute(
            "UPDATE customer_charges
             SET fee_charged = :fee_charged,
                 fee_paid = :fee_paid,
                 fee_balance = :fee_balance,
                 base_balance = :base_balance,
                 total_charged = :total_charged,
                 balance = :balance,
                 status = :status,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'fee_charged' => $newFeeCharged,
                'fee_paid' => $feePaid,
                'fee_balance' => $newFeeBalance,
                'base_balance' => $newBaseBalance,
                'total_charged' => $newTotalCharged,
                'balance' => $newBalance,
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        $affected++;
    }

    Database::connection()->commit();

    echo "Backfill completed. {$affected} customer(s) had their service fee balance reallocated.\n";
} catch (Exception $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo "Backfill failed: " . $e->getMessage() . "\n";
    exit(1);
}
