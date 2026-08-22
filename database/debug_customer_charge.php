<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$passengerId = (int) ($argv[1] ?? 0);
if (!$passengerId) {
    echo "Usage: php database/debug_customer_charge.php <passenger_id>\n";
    exit(1);
}

$cc = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid", ['pid' => $passengerId]);
var_export($cc);
echo "\n";
