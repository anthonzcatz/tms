<?php
/**
 * User Module Controller
 * Follows pattern 2.A from MODULE_CREATION_GUIDE.md (directly under admin/)
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

// $userProfile is populated globally by navbar-context.php (included via navbar/sidebar)
// It contains: fullname, first_name, last_name, email, position, department, location, profile_image, etc.
$user = Auth::user();

// Determine which view to show
$view = $_GET['view'] ?? 'profile';
$allowedViews = ['profile', 'settings'];

if (!in_array($view, $allowedViews)) {
    $view = 'profile';
}

// Include the appropriate view
include __DIR__ . '/views/' . $view . '.php';
