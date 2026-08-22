<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once __DIR__ . '/_guard.php';

header('Location: ' . BASE_URL . '/admin/reports/financial/');
exit;
