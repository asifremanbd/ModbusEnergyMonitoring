@echo off
title Polling System Monitor
color 0A

:monitor
cls
echo ========================================
echo    MODBUS POLLING SYSTEM MONITOR
echo ========================================
echo.
echo [%date% %time%] Checking system status...
echo.

cd /d "D:\ModbusEnergyMonitoring\filament-app"

echo --- SYSTEM STATUS ---
php artisan polling:reliable status --detailed

echo.
echo --- QUEUE STATUS ---
php artisan tinker --execute="echo 'Jobs in queue: ' . DB::table('jobs')->count(); echo PHP_EOL . 'Failed jobs: ' . DB::table('failed_jobs')->count();"

echo.
echo --- QUEUE WORKERS ---
tasklist /FI "IMAGENAME eq php.exe" /FO TABLE | findstr "php.exe"

echo.
echo ========================================
echo Next check in 30 seconds... (Ctrl+C to stop)
echo ========================================

timeout /t 30 /nobreak >nul
goto monitor