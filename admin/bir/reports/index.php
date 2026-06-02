<?php
/**
 * BIR Reports Controller
 * Generate DSR, Monthly Sales, SLS, Alphalist, and 2550M reports
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/IdEncoder.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';
$reportData = null;

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];

        if ($action === 'delete') {
            $reportId = $_POST['report_id'] ?? null;
            if ($reportId) {
                Database::execute("DELETE FROM bir_reports WHERE report_id = ?", [$reportId]);
                header('Location: ' . BASE_URL . '/admin/bir/reports/?success=' . urlencode('Report deleted successfully!'));
                exit;
            }
        } else {
            $reportType = $_POST['report_type'] ?? '';
            $branchId = $_POST['branch_id'] ?? null;
            $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
            $dateTo = $_POST['date_to'] ?? date('Y-m-d');

            switch ($reportType) {
                case 'DSR':
                    $reportData = generateDSR($branchId, $dateFrom);
                    break;
                case 'Monthly':
                    $reportData = generateMonthlyReport($branchId, $dateFrom, $dateTo);
                    break;
                case 'SLS':
                    $reportData = generateSLS($branchId, $dateFrom, $dateTo);
                    break;
                case 'Alphalist':
                    $reportData = generateAlphalist($branchId, $dateFrom, $dateTo);
                    break;
                case '2550M':
                    $reportData = generate2550M($branchId, $dateFrom, $dateTo);
                    break;
            }

            if ($reportData) {
                // Save report to database
                Database::execute(
                    "INSERT INTO bir_reports (report_type, report_date, report_period_start, report_period_end, branch_id, generated_by, data_json, status)
                     VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'generated')",
                    [$reportType, $dateFrom, $dateTo, $branchId, $user['user_id'], json_encode($reportData)]
                );
                // Redirect to prevent form resubmission on refresh
                header('Location: ' . BASE_URL . '/admin/bir/reports/?success=' . urlencode($reportType . ' report generated successfully!'));
                exit;
            }
        }
    } catch (Exception $e) {
        // Redirect with error message
        header('Location: ' . BASE_URL . '/admin/bir/reports/?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle view report request
$viewReportId = $_GET['view'] ?? null;
if ($viewReportId) {
    $decodedReportId = IdEncoder::decode($viewReportId);
    if ($decodedReportId) {
        $viewReport = Database::fetch(
            "SELECT * FROM bir_reports WHERE report_id = ?",
            [$decodedReportId]
        );
        if ($viewReport) {
            $reportData = json_decode($viewReport['data_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Error decoding report data: ' . json_last_error_msg();
                $reportData = null;
            }
        } else {
            $error = 'Report not found.';
        }
    } else {
        $error = 'Invalid report ID.';
    }
}

// Handle download report request
$downloadReportId = $_GET['download'] ?? null;
$downloadFormat = $_GET['format'] ?? 'json';
if ($downloadReportId) {
    $decodedReportId = IdEncoder::decode($downloadReportId);
    if ($decodedReportId) {
        $downloadReport = Database::fetch(
            "SELECT * FROM bir_reports WHERE report_id = ?",
            [$decodedReportId]
        );
        if ($downloadReport) {
            $reportData = json_decode($downloadReport['data_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Error decoding report data: ' . json_last_error_msg();
            } else {
                // Use the date from the report data if available, otherwise use report_date
                $reportDate = $reportData['date'] ?? $downloadReport['report_date'];
                $reportType = $downloadReport['report_type'];

                switch ($downloadFormat) {
                    case 'csv':
                        $filename = $reportType . '_Report_' . date('Y-m-d', strtotime($reportDate)) . '.csv';
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');

                        // Generate CSV based on report type
                        $output = fopen('php://output', 'w');
                        if ($reportType === 'DSR') {
                            fputcsv($output, ['DSR Report - ' . $reportDate]);
                            fputcsv($output, []);
                            fputcsv($output, ['Summary']);
                            fputcsv($output, ['Total Transactions', $reportData['summary']['total_transactions'] ?? 0]);
                            fputcsv($output, ['Total Sales', $reportData['summary']['total_sales'] ?? 0]);
                            fputcsv($output, ['Total VAT', $reportData['summary']['total_vat'] ?? 0]);
                            fputcsv($output, ['Taxable Sales', $reportData['summary']['taxable_sales'] ?? 0]);
                            fputcsv($output, ['Non-Taxable Sales', $reportData['summary']['non_taxable_sales'] ?? 0]);
                            fputcsv($output, ['Cancelled Count', $reportData['summary']['cancelled_count'] ?? 0]);
                            fputcsv($output, ['Refunded Count', $reportData['summary']['refunded_count'] ?? 0]);
                            fputcsv($output, []);
                            fputcsv($output, ['Payment Breakdown']);
                            fputcsv($output, ['Payment Method', 'Transaction Count', 'Total Amount']);
                            foreach ($reportData['payment_breakdown'] ?? [] as $payment) {
                                fputcsv($output, [
                                    $payment['payment_method'] ?? '',
                                    $payment['transaction_count'] ?? 0,
                                    $payment['total_amount'] ?? 0
                                ]);
                            }
                            fputcsv($output, []);
                            fputcsv($output, ['Hourly Sales']);
                            fputcsv($output, ['Hour', 'Transaction Count', 'Total Sales']);
                            foreach ($reportData['hourly_sales'] ?? [] as $hour) {
                                fputcsv($output, [
                                    $hour['hour'] ?? '',
                                    $hour['transaction_count'] ?? 0,
                                    $hour['total_sales'] ?? 0
                                ]);
                            }
                        } elseif ($reportType === 'Alphalist') {
                            fputcsv($output, ['Alphalist of Purchases - ' . $reportData['period_start'] . ' to ' . $reportData['period_end']]);
                            fputcsv($output, []);
                            fputcsv($output, ['Summary']);
                            fputcsv($output, ['Transaction Count', $reportData['summary']['transaction_count'] ?? 0]);
                            fputcsv($output, ['Total Purchases', $reportData['summary']['total_purchases'] ?? 0]);
                            fputcsv($output, ['Total VAT Input', $reportData['summary']['total_vat_input'] ?? 0]);
                            fputcsv($output, ['Total Non-VAT', $reportData['summary']['total_non_vat'] ?? 0]);
                            fputcsv($output, []);
                            fputcsv($output, ['Purchases']);
                            fputcsv($output, ['Date', 'Transaction Code', 'Type', 'Bank/Account', 'Amount', 'VAT Input', 'Remarks']);
                            foreach ($reportData['purchases'] ?? [] as $purchase) {
                                fputcsv($output, [
                                    $purchase['created_at'] ?? '',
                                    $purchase['txn_code'] ?? '',
                                    $purchase['txn_type'] ?? '',
                                    ($purchase['bank_name'] ?? '') . ' - ' . ($purchase['account_name'] ?? ''),
                                    $purchase['amount'] ?? 0,
                                    round(($purchase['amount'] ?? 0) / 1.12 * 0.12, 2),
                                    $purchase['remarks'] ?? '-'
                                ]);
                            }
                        } elseif ($reportType === 'Monthly') {
                            fputcsv($output, ['Monthly Sales Report - ' . $reportData['period_start'] . ' to ' . $reportData['period_end']]);
                            fputcsv($output, []);
                            fputcsv($output, ['Summary']);
                            fputcsv($output, ['Total Transactions', $reportData['summary']['total_transactions'] ?? 0]);
                            fputcsv($output, ['Total Sales', $reportData['summary']['total_sales'] ?? 0]);
                            fputcsv($output, ['Total VAT', $reportData['summary']['total_vat'] ?? 0]);
                            fputcsv($output, ['Taxable Sales', $reportData['summary']['taxable_sales'] ?? 0]);
                            fputcsv($output, []);
                            fputcsv($output, ['Daily Breakdown']);
                            fputcsv($output, ['Date', 'Transactions', 'Total Sales', 'VAT Amount']);
                            foreach ($reportData['daily_breakdown'] ?? [] as $day) {
                                fputcsv($output, [
                                    $day['date'] ?? '',
                                    $day['transaction_count'] ?? 0,
                                    $day['total_sales'] ?? 0,
                                    $day['vat_amount'] ?? 0
                                ]);
                            }
                        } elseif ($reportType === 'SLS') {
                            fputcsv($output, ['Summary List of Sales - ' . $reportData['period_start'] . ' to ' . $reportData['period_end']]);
                            fputcsv($output, []);
                            fputcsv($output, ['Summary']);
                            fputcsv($output, ['Total Transactions', $reportData['summary']['total_transactions'] ?? 0]);
                            fputcsv($output, ['Total Sales', $reportData['summary']['total_sales'] ?? 0]);
                            fputcsv($output, ['Total VAT', $reportData['summary']['total_vat'] ?? 0]);
                            fputcsv($output, []);
                            fputcsv($output, ['Transactions']);
                            fputcsv($output, ['Date', 'Order Code', 'Branch', 'Total', 'VAT', 'VAT Type', 'Status', 'OR Number']);
                            foreach ($reportData['transactions'] ?? [] as $txn) {
                                fputcsv($output, [
                                    $txn['created_at'] ?? '',
                                    $txn['order_code'] ?? '',
                                    $txn['branch_name'] ?? '',
                                    $txn['grand_total'] ?? 0,
                                    $txn['vat_amount'] ?? 0,
                                    $txn['vat_type'] ?? '',
                                    $txn['status'] ?? '',
                                    $txn['or_full_number'] ?? '-'
                                ]);
                            }
                        } elseif ($reportType === '2550M') {
                            fputcsv($output, ['VAT Return (2550M) - ' . $reportData['period_start'] . ' to ' . $reportData['period_end']]);
                            fputcsv($output, []);
                            fputcsv($output, ['Output VAT', $reportData['output_vat']['output_vat'] ?? 0]);
                            fputcsv($output, []);
                            fputcsv($output, ['VAT Breakdown']);
                            fputcsv($output, ['VAT Type', 'Transactions', 'Total Sales', 'VAT Amount', 'Taxable Amount']);
                            foreach ($reportData['vat_breakdown'] ?? [] as $vat) {
                                fputcsv($output, [
                                    $vat['vat_type'] ?? '',
                                    $vat['transaction_count'] ?? 0,
                                    $vat['total_sales'] ?? 0,
                                    $vat['vat_amount'] ?? 0,
                                    $vat['taxable_amount'] ?? 0
                                ]);
                            }
                        } else {
                            // Generic CSV for unknown report types
                            fputcsv($output, [$reportType . ' Report - ' . $reportDate]);
                            fputcsv($output, []);
                            foreach ($reportData as $key => $value) {
                                if (is_array($value)) {
                                    fputcsv($output, [$key, json_encode($value)]);
                                } else {
                                    fputcsv($output, [$key, $value]);
                                }
                            }
                        }
                        fclose($output);
                        exit;

                    case 'html':
                        $filename = $reportType . '_Report_' . date('Y-m-d', strtotime($reportDate)) . '.html';
                        header('Content-Type: text/html; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');

                        // Generate print-friendly HTML
                        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($reportType) . ' Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .summary { display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0; }
        .summary-item { flex: 1; min-width: 200px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .summary-item h3 { margin: 0 0 10px 0; color: #007bff; }
        .summary-item p { margin: 0; font-size: 24px; font-weight: bold; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($reportType) . ' Report</h1>
    <p><strong>Date:</strong> ' . htmlspecialchars($reportDate) . '</p>';

                        // Add summary cards for DSR
                        if ($reportType === 'DSR' && isset($reportData['summary'])) {
                            echo '<div class="summary">
                                <div class="summary-item">
                                    <h3>Transactions</h3>
                                    <p>' . number_format($reportData['summary']['total_transactions'] ?? 0) . '</p>
                                </div>
                                <div class="summary-item">
                                    <h3>Total Sales</h3>
                                    <p>₱' . number_format($reportData['summary']['total_sales'] ?? 0, 2) . '</p>
                                </div>
                                <div class="summary-item">
                                    <h3>Total VAT</h3>
                                    <p>₱' . number_format($reportData['summary']['total_vat'] ?? 0, 2) . '</p>
                                </div>
                                <div class="summary-item">
                                    <h3>Refunded/Cancelled</h3>
                                    <p>' . number_format(($reportData['summary']['refunded_count'] ?? 0) + ($reportData['summary']['cancelled_count'] ?? 0)) . '</p>
                                </div>
                            </div>';
                        }

                        // Add tables for data
                        if ($reportType === 'DSR' && isset($reportData['hourly_sales'])) {
                            echo '<h2>Hourly Sales</h2>
                            <table>
                                <tr><th>Hour</th><th>Transactions</th><th>Total Sales</th></tr>';
                            foreach ($reportData['hourly_sales'] as $hour) {
                                echo '<tr>
                                    <td>' . htmlspecialchars($hour['hour'] ?? '') . '</td>
                                    <td>' . number_format($hour['transaction_count'] ?? 0) . '</td>
                                    <td>₱' . number_format($hour['total_sales'] ?? 0, 2) . '</td>
                                </tr>';
                            }
                            echo '</table>';
                        }

                        if ($reportType === 'Alphalist' && isset($reportData['purchases'])) {
                            echo '<h2>Purchases</h2>
                            <table>
                                <tr><th>Date</th><th>Transaction Code</th><th>Type</th><th>Bank/Account</th><th>Amount</th><th>VAT Input</th><th>Remarks</th></tr>';
                            foreach ($reportData['purchases'] as $purchase) {
                                echo '<tr>
                                    <td>' . htmlspecialchars($purchase['created_at'] ?? '') . '</td>
                                    <td>' . htmlspecialchars($purchase['txn_code'] ?? '') . '</td>
                                    <td>' . htmlspecialchars($purchase['txn_type'] ?? '') . '</td>
                                    <td>' . htmlspecialchars(($purchase['bank_name'] ?? '') . ' - ' . ($purchase['account_name'] ?? '')) . '</td>
                                    <td>₱' . number_format($purchase['amount'] ?? 0, 2) . '</td>
                                    <td>₱' . number_format((($purchase['amount'] ?? 0) / 1.12 * 0.12), 2) . '</td>
                                    <td>' . htmlspecialchars($purchase['remarks'] ?? '-') . '</td>
                                </tr>';
                            }
                            echo '</table>';
                        }

                        echo '<script>window.print();</script>
</body>
</html>';
                        exit;

                    case 'json':
                    default:
                        $filename = $reportType . '_Report_' . date('Y-m-d', strtotime($reportDate)) . '.json';
                        header('Content-Type: application/json; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Content-Length: ' . strlen(json_encode($reportData, JSON_PRETTY_PRINT)));
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');
                        echo json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        exit;
                }
            }
        } else {
            $error = 'Report not found.';
        }
    } else {
        $error = 'Invalid report ID.';
    }
}

// Fetch generated reports
$reports = Database::fetchAll(
    "SELECT r.*, b.branch_name, u.username as generated_by_name
     FROM bir_reports r
     LEFT JOIN business_branches b ON r.branch_id = b.branch_id
     LEFT JOIN user_accounts u ON r.generated_by = u.user_id
     ORDER BY r.created_at DESC
     LIMIT 50"
);

// Encode report IDs for URL security
foreach ($reports as &$report) {
    $encoded = IdEncoder::encode($report['report_id']);
    $report['encoded_id'] = $encoded;
}
unset($report);

// Fetch branches
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

// Helper functions for report generation
function generateDSR($branchId, $date) {
    $params = [$date];
    $branchFilter = "";
    if ($branchId) {
        $branchFilter = "AND po.branch_id = ?";
        $params[] = $branchId;
    }

    $summary = Database::fetch(
        "SELECT
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(vt.non_taxable_amount), 0) as non_taxable_sales,
            COALESCE(SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_count,
            COALESCE(SUM(CASE WHEN po.status = 'refunded' THEN 1 ELSE 0 END), 0) as refunded_count
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $paymentBreakdown = Database::fetchAll(
        "SELECT
            'Cash' as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_amount
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $hourlySales = Database::fetchAll(
        "SELECT
            HOUR(po.created_at) as hour,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter
         GROUP BY HOUR(po.created_at)
         ORDER BY hour",
        $params
    );

    return [
        'report_type' => 'DSR',
        'date' => $date,
        'summary' => $summary,
        'payment_breakdown' => $paymentBreakdown,
        'hourly_sales' => $hourlySales
    ];
}

function generateMonthlyReport($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = "";
    if ($branchId) {
        $branchFilter = "AND po.branch_id = ?";
        $params[] = $branchId;
    }

    $summary = Database::fetch(
        "SELECT
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(vt.non_taxable_amount), 0) as non_taxable_sales
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $dailyBreakdown = Database::fetchAll(
        "SELECT
            DATE(po.created_at) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as vat_amount
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY DATE(po.created_at)
         ORDER BY date",
        $params
    );

    return [
        'report_type' => 'Monthly',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'daily_breakdown' => $dailyBreakdown
    ];
}

function generateSLS($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = "";
    if ($branchId) {
        $branchFilter = "AND po.branch_id = ?";
        $params[] = $branchId;
    }

    $transactions = Database::fetchAll(
        "SELECT
            po.order_code,
            po.created_at,
            bb.branch_name,
            po.grand_total,
            vt.vat_amount,
            vt.vat_type,
            po.status,
            orn.or_full_number
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
         LEFT JOIN bir_or_numbers orn ON po.or_number_id = orn.or_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter
         ORDER BY po.created_at DESC",
        $params
    );

    $summary = Database::fetch(
        "SELECT
            COUNT(*) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter",
        $params
    );

    return [
        'report_type' => 'SLS',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'transactions' => $transactions
    ];
}

function generate2550M($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = "";
    if ($branchId) {
        $branchFilter = "AND po.branch_id = ?";
        $params[] = $branchId;
    }

    $outputVat = Database::fetch(
        "SELECT COALESCE(SUM(vt.vat_amount), 0) as output_vat
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $vatBreakdown = Database::fetchAll(
        "SELECT
            vt.vat_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as vat_amount,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_amount
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY vt.vat_type",
        $params
    );

    return [
        'report_type' => '2550M',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'output_vat' => $outputVat,
        'vat_breakdown' => $vatBreakdown
    ];
}

function generateAlphalist($branchId, $dateFrom, $dateTo) {
    // Alphalist of Purchases - based on bank transactions (disbursements/expenses)
    $params = [$dateFrom, $dateTo];
    $branchFilter = "";
    if ($branchId) {
        $branchFilter = "AND bt.bank_account_id IN (SELECT bank_account_id FROM bank_accounts WHERE branch_id = ?)";
        $params[] = $branchId;
    }

    // Get all disbursement transactions (purchases/expenses)
    $purchases = Database::fetchAll(
        "SELECT
            bt.bank_txn_id,
            bt.txn_code,
            bt.txn_type,
            bt.amount,
            bt.remarks,
            bt.created_at,
            ba.bank_name,
            ba.account_name,
            ba.account_number,
            u.username as created_by_name
         FROM bank_transactions bt
         LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.bank_account_id
         LEFT JOIN user_accounts u ON bt.created_by = u.user_id
         WHERE bt.txn_type IN ('DISBURSEMENT', 'ADJUSTMENT')
           AND bt.direction = 'OUT'
           AND DATE(bt.created_at) BETWEEN ? AND ?
           $branchFilter
         ORDER BY bt.created_at ASC",
        $params
    );

    // Calculate totals
    $totalPurchases = 0;
    $totalVatInput = 0;
    $totalNonVat = 0;

    foreach ($purchases as $purchase) {
        $totalPurchases += floatval($purchase['amount']);
        // Assume 12% VAT input for disbursements (can be refined with VAT tracking)
        $vatInput = floatval($purchase['amount']) / 1.12 * 0.12;
        $totalVatInput += $vatInput;
        $totalNonVat += floatval($purchase['amount']) - $vatInput;
    }

    return [
        'report_type' => 'Alphalist',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => [
            'total_purchases' => $totalPurchases,
            'total_vat_input' => $totalVatInput,
            'total_non_vat' => $totalNonVat,
            'transaction_count' => count($purchases)
        ],
        'purchases' => $purchases
    ];
}

$viewData = [
    'reports' => $reports,
    'branches' => $branches,
    'message' => $message,
    'error' => $error,
    'reportData' => $reportData,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
