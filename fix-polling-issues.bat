@echo off
echo ========================================
echo    POLLING SYSTEM QUICK FIX
echo ========================================
echo.

cd /d "D:\ModbusEnergyMonitoring\filament-app"

echo Step 1: Checking current status...
php artisan polling:reliable status

echo.
echo Step 2: Stopping all polling...
php artisan polling:reliable stop

echo.
echo Step 3: Clearing stuck jobs...
php artisan queue:clear
php artisan queue:retry all

echo.
echo Step 4: Running system audit...
php artisan polling:reliable audit

echo.
echo Step 5: Synchronizing gateway states...
php artisan polling:reliable sync

echo.
echo Step 6: Restarting polling system...
php artisan polling:reliable start

echo.
echo Step 7: Final status check...
php artisan polling:reliable status --detailed

echo.
echo ========================================
echo Fix completed! Now start the queue worker:
echo run: start-queue-worker.bat
echo ========================================
pause