@echo off
echo Uploading backup file to production server...
echo.

REM Set variables
set BACKUP_FILE=filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql
set SERVER_IP=165.22.112.94
set USERNAME=root
set PASSWORD=2tDEoBWefYLp.PYyPF

echo File: %BACKUP_FILE%
echo Destination: %USERNAME%@%SERVER_IP%:/root/
echo.

REM Try the upload
echo Starting upload...
scp "%BACKUP_FILE%" %USERNAME%@%SERVER_IP%:/root/

echo.
echo If prompted for password, use: %PASSWORD%
echo.
pause