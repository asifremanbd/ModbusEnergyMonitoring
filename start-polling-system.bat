@echo off
echo Starting Complete Polling System...
echo.

cd /d "D:\ModbusEnergyMonitoring\filament-app"

echo Step 1: Stopping any existing polling...
php artisan polling:reliable stop

echo.
echo Step 2: Clearing old queue jobs...
php artisan queue:clear

echo.
echo Step 3: Starting reliable polling system...
php artisan polling:reliable start

echo.
echo Step 4: Checking system status...
php artisan polling:reliable status --detailed

echo.
echo ========================================
echo IMPORTANT: You must now start the queue worker!
echo Run: start-queue-worker.bat
echo ========================================
pause