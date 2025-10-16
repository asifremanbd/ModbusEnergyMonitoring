@echo off
echo ================================
echo    SIMPLE USER MANAGEMENT
echo    (Bypasses Filament completely)
echo ================================
echo.

cd filament-app

echo Starting Laravel server...
start /B php artisan serve --host=127.0.0.1 --port=8000

echo Waiting for server to start...
timeout /t 3 /nobreak >nul

echo.
echo ================================
echo    DIRECT ACCESS
echo ================================
echo.
echo Opening user management page...
start http://127.0.0.1:8000/user-management

echo.
echo This page:
echo - Shows all 5 users in a table
echo - Allows adding new users
echo - Allows deleting users
echo - Works completely outside of Filament
echo.
echo URL: http://127.0.0.1:8000/user-management
echo.
pause