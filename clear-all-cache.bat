@echo off
echo ================================
echo    Clearing All Laravel Cache
echo ================================
echo.

cd filament-app

echo Clearing application cache...
php artisan cache:clear

echo Clearing configuration cache...
php artisan config:clear

echo Clearing route cache...
php artisan route:clear

echo Clearing view cache...
php artisan view:clear

echo Clearing compiled views...
php artisan view:cache

echo Optimizing autoloader...
composer dump-autoload

echo.
echo ================================
echo    Cache cleared successfully!
echo ================================
echo.
echo Now try accessing the admin panel:
echo http://127.0.0.1:8000/admin
echo.
pause