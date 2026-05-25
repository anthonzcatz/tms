<?php
/**
 * Accounts Receivable Summary API
 * Returns outstanding charge summary for the dashboard widget
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Summary stats — only customers with current balance
    $stats = Database::fetch(
        "SELECT
            COUNT(CASE WHEN balance > 0 THEN 1 END)             AS total_customers,
            COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) AS total_outstanding,
            COUNT(CASE WHEN balance > 0 THEN 1 END)             AS outstanding_count,
            COUNT(CASE WHEN balance <= 0 THEN 1 END)            AS clear_count
         FROM customer_charges"
    );

    // Recent charge activity (last 7 days)
    $recentActivity = Database::fetch(
        "SELECT
            COALESCE(SUM(tp.amount), 0) AS charged_7d,
            COUNT(DISTINCT tp.charged_to_passenger_id) AS customers_7d
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         WHERE pm.tracks_credit = 1
         AND tp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    // Recent payments collected (last 7 days)
    $recentPayments = Database::fetch(
        "SELECT COALESCE(SUM(amount_paid), 0) AS collected_7d
         FROM charge_payments
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND confirmation_status IN ('NOT_REQUIRED', 'CONFIRMED')"
    );

    echo json_encode([
        'success' => true,
        'data'    => [
            'summary'          => [
                'total_outstanding' => floatval($stats['total_outstanding'] ?? 0),
                'total_customers'   => intval($stats['total_customers'] ?? 0),
                'outstanding_count' => intval($stats['outstanding_count'] ?? 0),
                'overdue_count'     => 0,
                'clear_count'       => intval($stats['clear_count'] ?? 0),
                'charged_7d'        => floatval($recentActivity['charged_7d'] ?? 0),
                'collected_7d'      => floatval($recentPayments['collected_7d'] ?? 0),
            ],
        ],
    ]);

} catch (Exception $e) {
    error_log('Receivables Widget Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
