@echo off
echo ========================================
echo Simple Login Background Deployment
echo ========================================

cd filament-app

echo.
echo [1/3] Clearing application cache...
php artisan cache:clear
php artisan config:clear
php artisan view:clear

echo.
echo [2/3] Verifying background image...
if not exist "public\images\icons\energy-login.jpg" (
    echo ERROR: energy-login.jpg not found in public\images\icons\
    echo Please ensure the background image is present.
    pause
    exit /b 1
)

echo ✓ Background image found: energy-login.jpg

echo.
echo [3/3] Running verification...
php test-simple-login.php

echo.
echo ========================================
echo Deployment Complete!
echo ========================================
echo.
echo The login page now uses energy-login.jpg as background.
echo Visit /admin to see the result.
echo.
pause