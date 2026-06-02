@echo off
REM BIR Automated Backup Script - Windows Task Scheduler Wrapper
REM This script runs the PHP backup script for BIR compliance

echo Starting BIR Automated Backup...
echo.

REM Change to script directory
cd /d "%~dp0"

REM Run PHP backup script
php bir_backup.php

REM Check exit code
if %ERRORLEVEL% EQU 0 (
    echo.
    echo Backup completed successfully.
) else (
    echo.
    echo Backup failed with error code %ERRORLEVEL%.
)

REM Pause to see output (remove if running as scheduled task)
pause
