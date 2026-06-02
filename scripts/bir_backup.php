<?php
/**
 * BIR Automated Backup Script
 * 
 * This script performs automated daily backups of the TMS database
 * for BIR compliance requirements.
 * 
 * Usage:
 * - PHP CLI: php scripts/bir_backup.php
 * - Cron Job: 0 2 * * * php /path/to/scripts/bir_backup.php
 * - Windows Task Scheduler: Create task to run this script daily
 * 
 * @version 1.0.0
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from CLI.');
}

// Change to project root
chdir(dirname(__DIR__));

// Load dependencies (bootstrap.php already includes database.php)
require_once 'config/bootstrap.php';

echo "========================================\n";
echo "BIR Automated Backup Script\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // Get database configuration from environment variables (support both naming conventions)
    $dbName = env('DB_DATABASE') ?: env('DB_NAME', 'tms_db');
    $dbUser = env('DB_USERNAME') ?: env('DB_USER', 'root');
    $dbPass = env('DB_PASSWORD') ?: env('DB_PASS', '');
    $dbHost = env('DB_HOST', 'localhost');
    
    // Backup directory
    $backupDir = dirname(__DIR__) . '/backups/bir';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "Created backup directory: $backupDir\n";
    }
    
    // Generate backup filename
    $date = date('Y-m-d');
    $time = date('His');
    $backupFile = $backupDir . "/bir_backup_{$date}_{$time}.sql";
    $compressedFile = $backupFile . '.gz';
    
    echo "Creating backup...\n";
    echo "Database: $dbName\n";
    echo "Host: $dbHost\n";
    echo "Output: $backupFile\n\n";

    // Build mysqldump command with full credentials
    $passwordPart = $dbPass ? '-p' . escapeshellarg($dbPass) : '';
    $mysqldumpCmd = sprintf(
        'mysqldump -h%s -u%s %s %s > %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        $passwordPart,
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    // Execute mysqldump
    $output = [];
    $returnCode = 0;
    exec($mysqldumpCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("mysqldump failed with code $returnCode");
    }
    
    // Compress backup
    echo "Compressing backup...\n";
    $fileSize = filesize($backupFile);
    $gz = gzopen($compressedFile, 'wb9');
    $fp = fopen($backupFile, 'rb');
    
    while (!feof($fp)) {
        gzwrite($gz, fread($fp, 8192));
    }
    
    fclose($fp);
    gzclose($gz);
    
    // Remove uncompressed file
    unlink($backupFile);
    
    $compressedSize = filesize($compressedFile);
    echo "Original size: " . formatBytes($fileSize) . "\n";
    echo "Compressed size: " . formatBytes($compressedSize) . "\n";
    echo "Compression ratio: " . round((1 - $compressedSize / $fileSize) * 100, 2) . "%\n\n";
    
    // Calculate checksum
    $checksum = md5_file($compressedFile);
    echo "MD5 Checksum: $checksum\n\n";
    
    // Get system user (default to 1 for SYSTEM user)
    $systemUserId = 1;
    
    // Save backup record to database
    echo "Saving backup record to database...\n";
    Database::execute(
        "INSERT INTO bir_backups 
         (backup_date, backup_type, file_path, file_size, checksum, status, created_by) 
         VALUES (CURDATE(), 'full', ?, ?, ?, 'success', ?)",
        [$compressedFile, $compressedSize, $checksum, $systemUserId]
    );
    echo "Backup record saved.\n\n";
    
    // Clean up old backups (keep last 30 days)
    echo "Cleaning up old backups (keeping last 30 days)...\n";
    $cutoffDate = date('Y-m-d', strtotime('-30 days'));
    $oldBackups = glob($backupDir . '/bir_backup_*.sql.gz');
    $deletedCount = 0;
    
    foreach ($oldBackups as $oldBackup) {
        if (preg_match('/bir_backup_(\d{4}-\d{2}-\d{2})_/', $oldBackup, $matches)) {
            $backupDate = $matches[1];
            if ($backupDate < $cutoffDate) {
                if (unlink($oldBackup)) {
                    $deletedCount++;
                    echo "Deleted: " . basename($oldBackup) . "\n";
                }
            }
        }
    }
    
    echo "Deleted $deletedCount old backup(s).\n\n";
    
    // Update database for deleted backups
    Database::execute(
        "UPDATE bir_backups SET status = 'deleted' 
         WHERE backup_date < ? AND status = 'success'",
        [$cutoffDate]
    );
    
    echo "========================================\n";
    echo "Backup completed successfully!\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "========================================\n";
    
    // Log failed backup
    try {
        $systemUserId = 1;
        Database::execute(
            "INSERT INTO bir_backups 
             (backup_date, backup_type, file_path, file_size, checksum, status, created_by) 
             VALUES (CURDATE(), 'full', NULL, 0, NULL, 'failed', ?)",
            [$systemUserId]
        );
    } catch (Exception $logError) {
        echo "Failed to log error: " . $logError->getMessage() . "\n";
    }
    
    exit(1);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
