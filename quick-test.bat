@echo off
echo ================================
echo    Quick User Management Test
echo ================================
echo.

echo Starting Laravel server...
cd filament-app
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo Opening pages in browser...
echo.
echo 1. Admin Login Page:
start http://127.0.0.1:8000/admin/login
echo    http://127.0.0.1:8000/admin/login

echo.
echo 2. Admin Dashboard (after login):
echo    http://127.0.0.1:8000/admin

echo.
echo 3. Users Management Page (after login):
echo    http://127.0.0.1:8000/admin/users

echo.
echo ================================
echo Login with one of these users:
echo ================================
php list-users.php

echo.
echo ================================
echo Instructions:
echo ================================
echo 1. Login using any of the above credentials
echo 2. Look for "Users" in the sidebar menu
echo 3. If you don't see "Users", try refreshing the page
echo.
pause