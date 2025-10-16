@echo off
echo ================================
echo    SIMPLE USER MANAGEMENT SOLUTION
echo ================================
echo.

cd filament-app

echo Starting Laravel server...
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo ================================
echo    NEW USER MANAGEMENT PAGE
echo ================================
echo.
echo 1. Login at: http://127.0.0.1:8000/admin/login
echo    Email: asifremanbd@gmail.com
echo.
echo 2. Look for "Manage Users" in the sidebar
echo.
echo 3. Or go directly to: http://127.0.0.1:8000/admin/manage-users
echo.
echo This bypasses all the resource complexity and gives you
echo direct user management with add/edit/delete functionality!
echo.
echo Opening login page...
start http://127.0.0.1:8000/admin/login

echo.
pause