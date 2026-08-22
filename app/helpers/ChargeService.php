<?php
/**
 * ChargeService: helpers for splitting customer charges into base and service fee,
 * and for handling per-customer service fee exemptions.
 */
require_once __DIR__ . '/../../config/database.php';

class ChargeService
{
    /**
     * Split a ticket transaction payment amount into base and service fee portions
     * based on the original ticket's base_amount, service_fee and total_amount.
     *
     * @param float $paymentAmount
     * @param float $baseAmount
     * @param float $serviceFee
     * @param float $totalAmount
     * @return array ['base' => float, 'fee' => float]
     */
    public static function splitPayment($paymentAmount, $baseAmount, $serviceFee, $totalAmount)
    {
        $paymentAmount = (float) $paymentAmount;
        $baseAmount = (float) $baseAmount;
        $serviceFee = (float) $serviceFee;
        $totalAmount = (float) $totalAmount;

        if ($paymentAmount <= 0) {
            return ['base' => 0.00, 'fee' => 0.00];
        }

        $preDiscount = $baseAmount + $serviceFee;
        if ($preDiscount <= 0) {
            return ['base' => $paymentAmount, 'fee' => 0.00];
        }

        if ($paymentAmount >= $totalAmount) {
            // Full payment (possibly with discount): split the discounted total proportionally
            $feePortion = round($totalAmount * ($serviceFee / $preDiscount), 2);
            $basePortion = round($totalAmount - $feePortion, 2);

            if ($feePortion > $totalAmount) {
                $feePortion = $totalAmount;
                $basePortion = 0.00;
            }

            return ['base' => $basePortion, 'fee' => $feePortion];
        }

        // Partial payment: split proportionally by service fee ratio
        $feePortion = round($paymentAmount * ($serviceFee / $preDiscount), 2);
        $basePortion = round($paymentAmount - $feePortion, 2);

        // Ensure fee never exceeds payment
        if ($feePortion > $paymentAmount) {
            $feePortion = $paymentAmount;
            $basePortion = 0.00;
        }

        return ['base' => $basePortion, 'fee' => $feePortion];
    }

    /**
     * Get the customer_charges row for a passenger, creating it if needed.
     *
     * @param int $passengerId
     * @param bool $lockForUpdate
     * @return array|null
     */
    public static function getChargeRecord($passengerId, $lockForUpdate = false)
    {
        $sql = "SELECT * FROM customer_charges WHERE passenger_id = :pid";
        if ($lockForUpdate) {
            $sql .= " FOR UPDATE";
        }
        $record = Database::fetch($sql, ['pid' => $passengerId]);

        if (!$record) {
            Database::execute(
                "INSERT INTO customer_charges (passenger_id, total_charged, total_paid, balance, status, last_charge_date)
                 VALUES (:pid, 0, 0, 0, 'CLEAR', :now)",
                ['pid' => $passengerId, 'now' => date('Y-m-d H:i:s')]
            );
            $record = Database::fetch($sql, ['pid' => $passengerId]);
        }

        return $record;
    }

    /**
     * Determine the service fee mode for a passenger on a given transaction date.
     * Returns ['mode' => 'CUSTOMER'|'WAIVED'|'COMPANY', 'company_passenger_id' => int|null]
     *
     * @param int $passengerId
     * @param string $txnDate
     * @return array
     */
    public static function getServiceFeeMode($passengerId, $txnDate = null)
    {
        if ($txnDate === null) {
            $txnDate = date('Y-m-d H:i:s');
        }

        $record = Database::fetch(
            "SELECT service_fee_mode, company_passenger_id, exempt_effective_date
             FROM customer_charges
             WHERE passenger_id = :pid",
            ['pid' => $passengerId]
        );

        $mode = 'CUSTOMER';
        $companyPassengerId = null;

        if ($record) {
            $mode = $record['service_fee_mode'] ?? 'CUSTOMER';
            $companyPassengerId = $record['company_passenger_id'] ?? null;
            $effectiveDate = $record['exempt_effective_date'] ?? null;

            if ($mode !== 'CUSTOMER' && $effectiveDate && $txnDate < $effectiveDate) {
                $mode = 'CUSTOMER';
                $companyPassengerId = null;
            }
        }

        return ['mode' => $mode, 'company_passenger_id' => $companyPassengerId];
    }

    /**
     * Post a ticket CHARGE payment to customer_charges, respecting the customer's
     * service fee exemption setting.
     *
     * @param int $passengerId
     * @param float $paymentAmount
     * @param float $baseAmount
     * @param float $serviceFee
     * @param float $totalAmount
     * @param string|null $txnDate
     * @return void
     */
    public static function postTicketCharge($passengerId, $paymentAmount, $baseAmount, $serviceFee, $totalAmount, $txnDate = null)
    {
        if ($txnDate === null) {
            $txnDate = date('Y-m-d H:i:s');
        }

        $modeData = self::getServiceFeeMode($passengerId, $txnDate);
        $mode = $modeData['mode'];
        $companyPassengerId = $modeData['company_passenger_id'];

        $split = self::splitPayment($paymentAmount, $baseAmount, $serviceFee, $totalAmount);
        $basePortion = $split['base'];
        $feePortion = $split['fee'];

        // Always post the base to the customer
        self::addToCustomerCharge($passengerId, $basePortion, $basePortion, 0);

        if ($mode === 'CUSTOMER') {
            // Customer also pays the fee
            self::addToCustomerCharge($passengerId, $feePortion, 0, $feePortion);
        } elseif ($mode === 'COMPANY' && $companyPassengerId) {
            // Company/CEO pays the fee
            self::addToCustomerCharge($companyPassengerId, $feePortion, 0, $feePortion);
        }
        // WAIVED: fee is not posted to any receivable
    }

    /**
     * Add a charge amount to a customer_charges row (split into base/add-on/fee).
     * For a customer record, baseCharged, addOnCharged and feeCharged are updated.
     * For backwards compatibility, total_charged/balance are also updated.
     *
     * @param int $passengerId
     * @param float $amount
     * @param float $baseAmount
     * @param float $feeAmount
     * @param float $addOnAmount
     * @return void
     */
    public static function addToCustomerCharge($passengerId, $amount, $baseAmount, $feeAmount, $addOnAmount = 0.00)
    {
        $record = self::getChargeRecord($passengerId, true);
        if (!$record) return;

        $newBaseCharged = (float) $record['base_charged'] + (float) $baseAmount;
        $newAddOnCharged = (float) ($record['add_on_charged'] ?? 0) + (float) $addOnAmount;
        $newFeeCharged = (float) $record['fee_charged'] + (float) $feeAmount;
        $newBaseBalance = $newBaseCharged - (float) $record['base_paid'];
        $newAddOnBalance = $newAddOnCharged - (float) ($record['add_on_paid'] ?? 0);
        $newFeeBalance = $newFeeCharged - (float) $record['fee_paid'];
        $newTotalCharged = (float) $record['total_charged'] + (float) $amount;
        $newBalance = $newTotalCharged - (float) $record['total_paid'];

        $status = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';

        Database::execute(
            "UPDATE customer_charges
             SET total_charged = :total_charged,
                 base_charged = :base_charged,
                 add_on_charged = :add_on_charged,
                 fee_charged = :fee_charged,
                 balance = :balance,
                 base_balance = :base_balance,
                 add_on_balance = :add_on_balance,
                 fee_balance = :fee_balance,
                 status = :status,
                 last_charge_date = :last_charge_date,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'total_charged' => $newTotalCharged,
                'base_charged' => $newBaseCharged,
                'add_on_charged' => $newAddOnCharged,
                'fee_charged' => $newFeeCharged,
                'balance' => $newBalance,
                'base_balance' => $newBaseBalance,
                'add_on_balance' => $newAddOnBalance,
                'fee_balance' => $newFeeBalance,
                'status' => $status,
                'last_charge_date' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Apply a payment collection to a customer_charges row, splitting it
     * between base, add-on and fee. Allocation order: base, add-on, fee.
     *
     * @param int $passengerId
     * @param float $amount
     * @return array ['new_balance' => float, 'new_base_balance' => float, 'new_add_on_balance' => float, 'new_fee_balance' => float]
     */
    public static function applyCollection($passengerId, $amount)
    {
        $record = self::getChargeRecord($passengerId, true);
        if (!$record) {
            return ['new_balance' => 0.00, 'new_base_balance' => 0.00, 'new_add_on_balance' => 0.00, 'new_fee_balance' => 0.00];
        }

        $basePaid = (float) $record['base_paid'];
        $addOnPaid = (float) ($record['add_on_paid'] ?? 0);
        $feePaid = (float) $record['fee_paid'];
        $baseCharged = (float) $record['base_charged'];
        $addOnCharged = (float) ($record['add_on_charged'] ?? 0);
        $feeCharged = (float) $record['fee_charged'];
        $totalPaid = (float) $record['total_paid'];

        $remaining = (float) $amount;
        $baseBalance = $baseCharged - $basePaid;
        $addOnBalance = $addOnCharged - $addOnPaid;
        $feeBalance = $feeCharged - $feePaid;

        // 1. Ticket base
        if ($baseBalance > 0 && $remaining > 0) {
            $baseAllocation = min($remaining, $baseBalance);
            $basePaid += $baseAllocation;
            $remaining -= $baseAllocation;
        }

        // 2. Service add-ons
        if ($addOnBalance > 0 && $remaining > 0) {
            $addOnAllocation = min($remaining, $addOnBalance);
            $addOnPaid += $addOnAllocation;
            $remaining -= $addOnAllocation;
        }

        // 3. Service fee
        if ($feeBalance > 0 && $remaining > 0) {
            $feeAllocation = min($remaining, $feeBalance);
            $feePaid += $feeAllocation;
            $remaining -= $feeAllocation;
        }

        // Any overpayment goes back to base (legacy catch-all)
        if ($remaining > 0) {
            $basePaid += $remaining;
        }

        $newTotalPaid = $totalPaid + (float) $amount;
        $newBalance = (float) $record['total_charged'] - $newTotalPaid;
        $newBaseBalance = $baseCharged - $basePaid;
        $newAddOnBalance = $addOnCharged - $addOnPaid;
        $newFeeBalance = $feeCharged - $feePaid;

        $status = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';

        Database::execute(
            "UPDATE customer_charges
             SET total_paid = :total_paid,
                 base_paid = :base_paid,
                 add_on_paid = :add_on_paid,
                 fee_paid = :fee_paid,
                 balance = :balance,
                 base_balance = :base_balance,
                 add_on_balance = :add_on_balance,
                 fee_balance = :fee_balance,
                 status = :status,
                 last_payment_date = :last_payment_date,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'total_paid' => $newTotalPaid,
                'base_paid' => $basePaid,
                'add_on_paid' => $addOnPaid,
                'fee_paid' => $feePaid,
                'balance' => $newBalance,
                'base_balance' => $newBaseBalance,
                'add_on_balance' => $newAddOnBalance,
                'fee_balance' => $newFeeBalance,
                'status' => $status,
                'last_payment_date' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        return [
            'new_balance' => $newBalance,
            'new_base_balance' => $newBaseBalance,
            'new_add_on_balance' => $newAddOnBalance,
            'new_fee_balance' => $newFeeBalance
        ];
    }

    /**
     * Reverse (reduce) a charge amount from a customer_charges row,
     * proportionally allocating the reversal between base and fee balances.
     *
     * @param int $passengerId
     * @param float $amount
     * @return array ['new_balance' => float, 'new_base_balance' => float, 'new_fee_balance' => float]
     */
    public static function reverseCustomerCharge($passengerId, $amount)
    {
        $record = self::getChargeRecord($passengerId, true);
        if (!$record) {
            return ['new_balance' => 0.00, 'new_base_balance' => 0.00, 'new_add_on_balance' => 0.00, 'new_fee_balance' => 0.00];
        }

        $amount = (float) $amount;
        $totalCharged = (float) $record['total_charged'];
        $baseCharged = (float) $record['base_charged'];
        $addOnCharged = (float) ($record['add_on_charged'] ?? 0);
        $feeCharged = (float) $record['fee_charged'];
        $basePaid = (float) $record['base_paid'];
        $addOnPaid = (float) ($record['add_on_paid'] ?? 0);
        $feePaid = (float) $record['fee_paid'];

        $baseBalance = max(0, $baseCharged - $basePaid);
        $addOnBalance = max(0, $addOnCharged - $addOnPaid);
        $feeBalance = max(0, $feeCharged - $feePaid);
        $totalBalance = max(0, $totalCharged - (float) $record['total_paid']);

        if ($amount <= 0) {
            return [
                'new_balance' => $totalBalance,
                'new_base_balance' => $baseBalance,
                'new_add_on_balance' => $addOnBalance,
                'new_fee_balance' => $feeBalance
            ];
        }

        $reversal = min($amount, $totalBalance);

        // Proportional reversal by outstanding balance share
        $feeReversal = 0;
        $addOnReversal = 0;
        if ($totalBalance > 0) {
            if ($feeBalance > 0) {
                $feeReversal = round($reversal * ($feeBalance / $totalBalance), 2);
                $feeReversal = min($feeReversal, $feeBalance);
            }
            if ($addOnBalance > 0) {
                $addOnReversal = round($reversal * ($addOnBalance / $totalBalance), 2);
                $addOnReversal = min($addOnReversal, $addOnBalance);
            }
        }
        $baseReversal = round($reversal - $feeReversal - $addOnReversal, 2);

        // Cap by individual balances if rounding pushed over
        if ($baseReversal > $baseBalance) {
            $baseReversal = $baseBalance;
        }
        if ($addOnReversal > $addOnBalance) {
            $addOnReversal = $addOnBalance;
        }
        if ($feeReversal > $feeBalance) {
            $feeReversal = $feeBalance;
        }

        // Re-derive base reversal to use remaining after add-on/fee caps
        $baseReversal = round($reversal - $addOnReversal - $feeReversal, 2);
        if ($baseReversal > $baseBalance) {
            $baseReversal = $baseBalance;
        }

        $newBaseCharged = max(0, $baseCharged - $baseReversal);
        $newAddOnCharged = max(0, $addOnCharged - $addOnReversal);
        $newFeeCharged = max(0, $feeCharged - $feeReversal);
        $newBaseBalance = $newBaseCharged - $basePaid;
        $newAddOnBalance = $newAddOnCharged - $addOnPaid;
        $newFeeBalance = $newFeeCharged - $feePaid;
        $newTotalCharged = max(0, $totalCharged - $reversal);
        $newBalance = $newTotalCharged - (float) $record['total_paid'];

        $status = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';

        Database::execute(
            "UPDATE customer_charges
             SET total_charged = :total_charged,
                 base_charged = :base_charged,
                 add_on_charged = :add_on_charged,
                 fee_charged = :fee_charged,
                 balance = :balance,
                 base_balance = :base_balance,
                 add_on_balance = :add_on_balance,
                 fee_balance = :fee_balance,
                 status = :status,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'total_charged' => $newTotalCharged,
                'base_charged' => $newBaseCharged,
                'add_on_charged' => $newAddOnCharged,
                'fee_charged' => $newFeeCharged,
                'balance' => $newBalance,
                'base_balance' => $newBaseBalance,
                'add_on_balance' => $newAddOnBalance,
                'fee_balance' => $newFeeBalance,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        return [
            'new_balance' => $newBalance,
            'new_base_balance' => $newBaseBalance,
            'new_add_on_balance' => $newAddOnBalance,
            'new_fee_balance' => $newFeeBalance
        ];
    }

    /**
     * Get the total service add-on amount posted to a customer's account.
     * Used to separate add-ons from ticket base in balance/statement prints.
     *
     * @param int $passengerId
     * @return float
     */
    public static function getAddOnTotalForCustomer($passengerId)
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(total_amount), 0.00) AS total
             FROM (
                 SELECT poi.total_amount
                 FROM pos_order_items poi
                 WHERE poi.item_type = 'SERVICE'
                   AND poi.passenger_id = :pid1
                 UNION ALL
                 SELECT poi.total_amount
                 FROM pos_order_items poi
                 WHERE poi.item_type = 'SERVICE'
                   AND poi.passenger_id IS NULL
                   AND poi.order_id IN (
                       SELECT ticket_oi.order_id
                       FROM transaction_payments tp
                       JOIN pos_order_items ticket_oi
                           ON ticket_oi.reference_id = tp.source_id
                          AND ticket_oi.item_type = 'TICKET'
                       WHERE tp.source_type = 'TICKET_TRANSACTION'
                         AND tp.charged_to_passenger_id = :pid2
                   )
             ) t",
            ['pid1' => $passengerId, 'pid2' => $passengerId]
        );

        return (float) ($row['total'] ?? 0.00);
    }
}
