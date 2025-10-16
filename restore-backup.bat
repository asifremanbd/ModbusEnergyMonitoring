@echo off
echo Restoring database backup...
echo.

REM Change to the correct directory
cd /d "D:\ModbusEnergyMonitoring\filament-app"

REM Restore the database
mysql -u root laravel_app < database\backup_20251015_065430.sql

if %errorlevel% equ 0 (
    echo.
    echo ✅ Database backup restored successfully!
    echo.
) else (
    echo.
    echo ❌ Error restoring database backup
    echo Error code: %errorlevel%
    echo.
)

pause