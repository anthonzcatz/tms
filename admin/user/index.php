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
$allowedViews = ['profile', 'settings', 'activity-logs'];

if (!in_array($view, $allowedViews)) {
    $view = 'profile';
}

// For activity-logs view, prepare paginated data
if ($view === 'activity-logs') {
    $userId = $user['user_id'] ?? 0;

    // Filters
    $filterAction   = trim($_GET['action'] ?? '');
    $filterModule   = trim($_GET['module'] ?? '');
    $filterDateFrom = trim($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
    $filterDateTo   = trim($_GET['date_to'] ?? date('Y-m-d'));

    // Pagination
    $perPage    = 20;
    $page       = max(1, (int) ($_GET['page'] ?? 1));
    $offset     = ($page - 1) * $perPage;

    // Base query
    $where  = "WHERE user_id = :uid AND DATE(created_at) BETWEEN :df AND :dt";
    $params = ['uid' => $userId, 'df' => $filterDateFrom, 'dt' => $filterDateTo];

    if ($filterAction) {
        $where .= " AND action = :action";
        $params['action'] = $filterAction;
    }
    if ($filterModule) {
        $where .= " AND module_name = :module";
        $params['module'] = $filterModule;
    }

    $totalLogs = (int) (Database::fetch(
        "SELECT COUNT(*) as cnt FROM activity_logs $where", $params
    )['cnt'] ?? 0);

    $totalPages = max(1, (int) ceil($totalLogs / $perPage));

    $activityLogs = Database::fetchAll(
        "SELECT * FROM activity_logs $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
        $params
    );

    // Distinct actions and modules for filter dropdowns
    $distinctActions = Database::fetchAll(
        "SELECT DISTINCT action FROM activity_logs WHERE user_id = :uid ORDER BY action",
        ['uid' => $userId]
    );
    $distinctModules = Database::fetchAll(
        "SELECT DISTINCT module_name FROM activity_logs WHERE user_id = :uid AND module_name IS NOT NULL ORDER BY module_name",
        ['uid' => $userId]
    );
}

// Include the appropriate view
include __DIR__ . '/views/' . $view . '.php';
