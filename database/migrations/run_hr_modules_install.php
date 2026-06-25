<?php
/**
 * HR Modules Install Runner
 * Run this from browser or command line to create the companies table and install permissions
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';

$scripts = [
    '001_create_companies_table.sql',
    '../../admin/settings/companies/install_permissions.sql',
    '../../admin/settings/departments/install_permissions.sql',
    '../../admin/settings/sub-departments/install_permissions.sql',
    '../../admin/settings/positions/install_permissions.sql',
    '../../admin/settings/employment-status/install_permissions.sql',
    '../../admin/settings/employees/install_permissions.sql',
];

$results = [];
foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (!file_exists($path)) {
        $results[] = ['script' => $script, 'status' => 'not_found'];
        continue;
    }
    $sql = file_get_contents($path);
    try {
        Database::connection()->exec($sql);
        $results[] = ['script' => $script, 'status' => 'success'];
    } catch (PDOException $e) {
        $results[] = ['script' => $script, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
