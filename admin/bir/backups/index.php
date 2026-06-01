<?php
/**
 * BIR Backup & Restore Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();

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
            $backupDir = dirname(dirname(dirname(__DIR__))) . '/storage/backups/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $filename = 'bir_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupDir . $filename;
            
            // Get DB credentials from config
            $dbConfig = require dirname(dirname(dirname(__DIR__))) . '/config/database.php';
            $dbName = $dbConfig['database'] ?? 'tms_db';
            
            // Create backup using mysqldump
            $command = sprintf('mysqldump -u root %s > %s 2>&1', $dbName, escapeshellarg($filepath));
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($filepath)) {
                $checksum = md5_file($filepath);
                Database::execute(
                    "INSERT INTO bir_backups (backup_date, backup_type, file_path, file_size, checksum, status, created_by) 
                     VALUES (CURDATE(), 'full', ?, ?, ?, 'success', ?)",
                    [$filepath, filesize($filepath), $checksum, $user['user_id']]
                );
                $message = 'Backup created successfully!';
            } else {
                $error = 'Failed to create backup. Error: ' . implode("\n", $output);
            }
        }
        
        if ($_POST['action'] === 'verify_backup') {
            $backup = Database::fetch("SELECT file_path, checksum FROM bir_backups WHERE backup_id = ?", [$_POST['backup_id']]);
            if ($backup && file_exists($backup['file_path'])) {
                $currentChecksum = md5_file($backup['file_path']);
                if ($currentChecksum === $backup['checksum']) {
                    Database::execute("UPDATE bir_backups SET status = 'success' WHERE backup_id = ?", [$_POST['backup_id']]);
                    $message = 'Backup verified successfully! Checksum matches.';
                } else {
                    Database::execute("UPDATE bir_backups SET status = 'corrupted' WHERE backup_id = ?", [$_POST['backup_id']]);
                    $error = 'Backup verification failed! Checksum does not match.';
                }
            } else {
                $error = 'Backup file not found!';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch backups
$backups = Database::fetchAll(
    "SELECT b.*, u.fullname as created_by_name, u2.fullname as restored_by_name
     FROM bir_backups b
     LEFT JOIN user_accounts u ON b.created_by = u.user_id
     LEFT JOIN user_accounts u2 ON b.restored_by = u2.user_id
     ORDER BY b.backup_date DESC, b.created_at DESC
     LIMIT 50"
);

$viewData = ['backups' => $backups, 'message' => $message, 'error' => $error, 'userRoleCode' => $userRoleCode];
extract($viewData);
include __DIR__ . '/views/index.php';
