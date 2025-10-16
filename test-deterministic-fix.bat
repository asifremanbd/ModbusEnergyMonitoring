@echo off
echo ================================
echo    DETERMINISTIC FIX COMPLETE
echo ================================
echo.

cd filament-app

echo Starting Laravel server...
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo ================================
echo    ACCEPTANCE TEST
echo ================================
echo.
echo 1. Opening login page...
start http://127.0.0.1:8000/admin/login

echo.
echo 2. Login with: asifremanbd@gmail.com
echo.
echo 3. Expected Results:
echo    - Sidebar shows: Administration > Users
echo    - Users page works: http://127.0.0.1:8000/admin/users
echo    - Table shows all 5 users
echo.
echo 4. If still missing nav, we'll use hard bypass...
echo.
pause