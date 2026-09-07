<?php

final class PosTransactionReporting
{
    public static function orderFrom(
        string $orderAlias = 'po',
        string $refundAlias = 'refund_summary',
        string $voidAlias = 'void_summary'
    ): string {
        return "FROM pos_orders {$orderAlias}
            LEFT JOIN (
                SELECT
                    oi_refund.order_id,
                    COALESCE(SUM(COALESCE(
                        NULLIF(tr_refund.refund_amount, 0),
                        NULLIF(tc_refund.refund_amount, 0),
                        NULLIF(tc_refund.gross_refund_amount, 0),
                        0
                    )), 0) AS refund_amount
                FROM pos_order_items oi_refund
                INNER JOIN ticket_transactions tt_refund
                    ON tt_refund.transaction_id = oi_refund.reference_id
                INNER JOIN ticket_cancellations tc_refund
                    ON tc_refund.transaction_id = tt_refund.transaction_id
                LEFT JOIN ticket_refunds tr_refund
                    ON tr_refund.cancellation_id = tc_refund.cancellation_id
                WHERE oi_refund.item_type = 'TICKET'
                  AND tc_refund.operation_type = 'REFUND'
                  AND tc_refund.status = 'completed'
                  AND COALESCE(tc_refund.processed_at, tr_refund.processed_at) IS NOT NULL
                GROUP BY oi_refund.order_id
            ) {$refundAlias}
                ON {$refundAlias}.order_id = {$orderAlias}.order_id
            LEFT JOIN (
                SELECT
                    oi_service_void.order_id,
                    COALESCE(SUM(COALESCE(st_service_void.total_amount, oi_service_void.total_amount, 0)), 0) AS service_amount
                FROM pos_order_items oi_service_void
                LEFT JOIN service_transactions st_service_void
                    ON st_service_void.service_txn_id = oi_service_void.reference_id
                   AND oi_service_void.item_type = 'SERVICE'
                LEFT JOIN service_types st_service_void_type
                    ON st_service_void_type.service_type_id = COALESCE(
                        oi_service_void.service_type_id,
                        st_service_void.service_type_id
                    )
                WHERE oi_service_void.item_type = 'SERVICE'
                  AND (st_service_void_type.code = 'PRINT_FEE'
                       OR TRIM(UPPER(st_service_void_type.name)) = 'PRINT FEE')
                  AND EXISTS (
                      SELECT 1
                      FROM pos_order_items oi_ticket_service_void
                      JOIN ticket_transactions tt_ticket_service_void
                        ON tt_ticket_service_void.transaction_id = oi_ticket_service_void.reference_id
                      JOIN ticket_cancellations tc_ticket_service_void
                        ON tc_ticket_service_void.transaction_id = tt_ticket_service_void.transaction_id
                      WHERE oi_ticket_service_void.order_id = oi_service_void.order_id
                        AND oi_ticket_service_void.item_type = 'TICKET'
                        AND tt_ticket_service_void.status IN ('cancelled', 'refunded')
                        AND tc_ticket_service_void.operation_type = 'VOID'
                        AND tc_ticket_service_void.status IN ('approved', 'completed')
                        AND COALESCE(tc_ticket_service_void.reason_category, 'OTHER')
                            NOT IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                  )
                GROUP BY oi_service_void.order_id
            ) service_void_summary
                ON service_void_summary.order_id = {$orderAlias}.order_id
            LEFT JOIN (
                SELECT
                    oi_void.order_id,
                    COALESCE(SUM(
                        COALESCE(tt_void.total_amount, 0)
                        + CASE
                            WHEN tc_void.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                            THEN COALESCE(NULLIF(tc_void.lost_sales_void_fee, 0), tc_void.void_fee, 0)
                            ELSE 0
                          END
                        - CASE
                            WHEN tc_void.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                            WHEN tc_void.responsibility IN ('CUSTOMER', 'CASHIER')
                              OR tc_void.reason_category IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                                THEN COALESCE(tc_void.void_fee, 0) + COALESCE(tc_void.void_service_fee, 0)
                            ELSE 0
                          END
                    ), 0) AS void_adjustment
                FROM pos_order_items oi_void
                INNER JOIN ticket_transactions tt_void
                    ON tt_void.transaction_id = oi_void.reference_id
                INNER JOIN ticket_cancellations tc_void
                    ON tc_void.transaction_id = tt_void.transaction_id
                WHERE oi_void.item_type = 'TICKET'
                  AND tc_void.operation_type = 'VOID'
                  AND tc_void.status = 'completed'
                GROUP BY oi_void.order_id
            ) {$voidAlias}
                ON {$voidAlias}.order_id = {$orderAlias}.order_id";
    }

    public static function grossExpression(string $orderAlias = 'po'): string
    {
        return "COALESCE({$orderAlias}.original_grand_total, {$orderAlias}.grand_total)";
    }

    public static function refundExpression(
        string $orderAlias = 'po',
        string $refundAlias = 'refund_summary'
    ): string {
        return "GREATEST(
            COALESCE({$orderAlias}.total_refunded_amount, 0),
            COALESCE({$refundAlias}.refund_amount, 0)
        )";
    }

    public static function netExpression(
        string $orderAlias = 'po',
        string $refundAlias = 'refund_summary',
        string $voidAlias = 'void_summary'
    ): string {
        $gross = self::grossExpression($orderAlias);
        $refund = self::refundExpression($orderAlias, $refundAlias);
        return "GREATEST(
            0,
            {$gross} - {$refund}
                - COALESCE({$voidAlias}.void_adjustment, 0)
                - COALESCE(service_void_summary.service_amount, 0)
        )";
    }

    public static function saleStatusCondition(string $orderAlias = 'po'): string
    {
        return "{$orderAlias}.status IN ('completed', 'refunded')";
    }
}
