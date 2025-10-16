@echo off
echo ================================
echo    Testing User Management Access
echo ================================
echo.

cd filament-app

echo Starting server...
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo Testing routes...
php artisan route:list --name=users

echo.
echo ================================
echo    Access Instructions
echo ================================
echo.
echo 1. Login at: http://127.0.0.1:8000/admin/login
echo    Email: asifremanbd@gmail.com
echo    Password: [your password]
echo.
echo 2. After login, try these URLs:
echo    - Dashboard: http://127.0.0.1:8000/admin
echo    - Users: http://127.0.0.1:8000/admin/users
echo.
echo 3. Look for "Users" in the sidebar menu
echo.
echo Opening login page...
start http://127.0.0.1:8000/admin/login

echo.
pause