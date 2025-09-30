@echo off
echo Uploading latest backup to production server...
echo Server: 165.22.112.94
echo User: root
echo Password: 2tDEoBWefYLp.PYyPF
echo.

set BACKUP_FILE=filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql
set SERVER=root@165.22.112.94
set REMOTE_PATH=/root/

echo Attempting file transfer...
echo You may need to enter the password: 2tDEoBWefYLp.PYyPF
echo.

scp "%BACKUP_FILE%" %SERVER%:%REMOTE_PATH%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo SUCCESS: Backup uploaded successfully!
) else (
    echo.
    echo FAILED: Upload encountered an error.
    echo.
    echo Manual command to try:
    echo scp "%BACKUP_FILE%" %SERVER%:%REMOTE_PATH%
    echo Password: 2tDEoBWefYLp.PYyPF
)

pause