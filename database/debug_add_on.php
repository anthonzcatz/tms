<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$passengerId = (int) ($argv[1] ?? 0);
if (!$passengerId) {
    echo "Usage: php database/debug_add_on.php <passenger_id>\n";
    exit(1);
}

$addOnTotal = Database::fetch(
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
)['total'] ?? 0;

echo "Add-on total for passenger {$passengerId}: " . number_format($addOnTotal, 2) . "\n\n";

echo "Service pos_order_items directly linked:\n";
$direct = Database::fetchAll(
    "SELECT poi.*, p.order_code FROM pos_order_items poi JOIN pos_orders p ON p.order_id = poi.order_id WHERE poi.item_type = 'SERVICE' AND poi.passenger_id = :pid",
    ['pid' => $passengerId]
);
print_r($direct);

echo "\nCharge payments from passenger {$passengerId} and linked orders:\n";
$payments = Database::fetchAll(
    "SELECT tp.*, ticket_oi.order_id
     FROM transaction_payments tp
     JOIN pos_order_items ticket_oi
         ON ticket_oi.reference_id = tp.source_id
        AND ticket_oi.item_type = 'TICKET'
     WHERE tp.source_type = 'TICKET_TRANSACTION'
       AND tp.charged_to_passenger_id = :pid",
    ['pid' => $passengerId]
);
print_r($payments);
