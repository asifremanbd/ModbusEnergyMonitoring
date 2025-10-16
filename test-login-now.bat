@echo off
echo ================================
echo    Database Fixed - Testing Login
echo ================================
echo.

cd filament-app

echo Database connection is now working!
echo Current users:
php list-users.php

echo.
echo Starting Laravel server...
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo ================================
echo    Ready to Login!
echo ================================
echo.
echo Opening login page...
start http://127.0.0.1:8000/admin/login

echo.
echo Login with any of these users:
echo - asifremanbd@gmail.com
echo - admin@teltonika-monitor.local  
echo - admin@example.com
echo - dan@dantemple.co.uk
echo - gerardwackrow@gmail.com
echo.
echo After login, look for "Users" in the sidebar!
echo Or go directly to: http://127.0.0.1:8000/admin/users
echo.
pause