<?php
/**
 * BIR Backup & Restore Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$allowedRoles = ['SUPER_ADMIN', 'ADMIN'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_backup') {
            $backupDir = dirname(dirname(dirname(__DIR__))) . '/backups/bir/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

            $filename = 'bir_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupDir . $filename;

            // Get DB credentials from environment variables (support both naming conventions)
            $dbName = env('DB_DATABASE') ?: env('DB_NAME', 'tms_db');
            $dbUser = env('DB_USERNAME') ?: env('DB_USER', 'root');
            $dbPass = env('DB_PASSWORD') ?: env('DB_PASS', '');
            $dbHost = env('DB_HOST', 'localhost');

            // Create backup using mysqldump
            $passwordPart = $dbPass ? '-p' . escapeshellarg($dbPass) : '';
            $command = sprintf('mysqldump -h%s -u%s %s %s > %s 2>&1',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                $passwordPart,
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                $checksum = md5_file($filepath);
                Database::execute(
                    "INSERT INTO bir_backups (backup_date, backup_type, file_path, file_size, checksum, status, created_by)
                     VALUES (CURDATE(), 'full', ?, ?, ?, 'success', ?)",
                    [$filepath, filesize($filepath), $checksum, $user['user_id']]
                );
                header('Location: ' . BASE_URL . '/admin/bir/backups/?success=' . urlencode('Backup created successfully!'));
                exit;
            } else {
                header('Location: ' . BASE_URL . '/admin/bir/backups/?error=' . urlencode('Failed to create backup. Error: ' . implode("\n", $output)));
                exit;
            }
        }

        if ($_POST['action'] === 'verify_backup') {
            $backup = Database::fetch("SELECT file_path, checksum FROM bir_backups WHERE backup_id = ?", [$_POST['backup_id']]);
            if ($backup && file_exists($backup['file_path'])) {
                $currentChecksum = md5_file($backup['file_path']);
                if ($currentChecksum === $backup['checksum']) {
                    Database::execute("UPDATE bir_backups SET status = 'success' WHERE backup_id = ?", [$_POST['backup_id']]);
                    header('Location: ' . BASE_URL . '/admin/bir/backups/?success=' . urlencode('Backup verified successfully! Checksum matches.'));
                    exit;
                } else {
                    Database::execute("UPDATE bir_backups SET status = 'corrupted' WHERE backup_id = ?", [$_POST['backup_id']]);
                    header('Location: ' . BASE_URL . '/admin/bir/backups/?error=' . urlencode('Backup verification failed! Checksum does not match.'));
                    exit;
                }
            } else {
                header('Location: ' . BASE_URL . '/admin/bir/backups/?error=' . urlencode('Backup file not found!'));
                exit;
            }
        }

        if ($_POST['action'] === 'delete_backup') {
            $backup = Database::fetch("SELECT file_path FROM bir_backups WHERE backup_id = ?", [$_POST['backup_id']]);
            if ($backup) {
                if (file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }
                Database::execute("DELETE FROM bir_backups WHERE backup_id = ?", [$_POST['backup_id']]);
                header('Location: ' . BASE_URL . '/admin/bir/backups/?success=' . urlencode('Backup deleted successfully!'));
                exit;
            } else {
                header('Location: ' . BASE_URL . '/admin/bir/backups/?error=' . urlencode('Backup not found!'));
                exit;
            }
        }
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/admin/bir/backups/?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle download
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['backup_id'])) {
    $backup = Database::fetch("SELECT file_path FROM bir_backups WHERE backup_id = ?", [$_GET['backup_id']]);
    if ($backup && file_exists($backup['file_path'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
        header('Content-Length: ' . filesize($backup['file_path']));
        readfile($backup['file_path']);
        exit;
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Backup file not found';
        exit;
    }
}

// Fetch backups with filters
$whereConditions = [];
$params = [];

// Filter by status
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $whereConditions[] = "b.status = ?";
    $params[] = $_GET['status'];
}

// Filter by date range
if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
    $whereConditions[] = "DATE(b.created_at) >= ?";
    $params[] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
    $whereConditions[] = "DATE(b.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$backups = Database::fetchAll(
    "SELECT b.*, u.username as created_by_name, u2.username as restored_by_name
     FROM bir_backups b
     LEFT JOIN user_accounts u ON b.created_by = u.user_id
     LEFT JOIN user_accounts u2 ON b.restored_by = u2.user_id
     $whereClause
     ORDER BY b.backup_date DESC, b.created_at DESC
     LIMIT 50",
    $params
);

$viewData = ['backups' => $backups, 'message' => $message, 'error' => $error, 'userRoleCode' => $userRoleCode];
extract($viewData);
include __DIR__ . '/views/index.php';
