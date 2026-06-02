<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

$sql = file_get_contents(__DIR__ . '/fix_pos_orders_auto_increment.sql');

try {
    Database::connection()->exec($sql);
    echo "Migration executed successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
