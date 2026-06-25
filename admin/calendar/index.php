<?php
/**
 * Calendar Module Controller
 * URL: /admin/calendar/
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();

include __DIR__ . '/views/index.php';
