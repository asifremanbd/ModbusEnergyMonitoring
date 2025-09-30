@echo off
setlocal enabledelayedexpansion

echo ========================================
echo   Backup Upload to Production Server
echo ========================================
echo.
echo Server: 165.22.112.94
echo User: root
echo File: laravel_backup_2025-09-23_01-49-35.sql
echo.

REM Set the exact file path
set "BACKUP_FILE=filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql"

REM Check if file exists
if not exist "%BACKUP_FILE%" (
    echo ERROR: Backup file not found!
    echo Looking for: %BACKUP_FILE%
    pause
    exit /b 1
)

echo File found. Size:
dir "%BACKUP_FILE%" | find "laravel_backup"
echo.

echo Attempting upload...
echo IMPORTANT: When prompted for password, enter: 2tDEoBWefYLp.PYyPF
echo.

REM Try the upload with verbose output
scp -v "%BACKUP_FILE%" root@165.22.112.94:/root/

if %ERRORLEVEL% EQU 0 (
    echo.
    echo *** SUCCESS: Backup uploaded successfully! ***
) else (
    echo.
    echo *** UPLOAD FAILED ***
    echo.
    echo Try these alternatives:
    echo 1. Use WinSCP GUI application
    echo 2. Install PuTTY and use pscp
    echo 3. Check if server allows root login
)

echo.
pause