@echo off
echo Setting up production-like local environment...

cd filament-app

echo Backing up current .env file...
copy .env .env.backup

echo Copying production environment...
copy .env.production .env

echo Installing production dependencies...
composer install --optimize-autoloader --no-dev

echo Clearing all caches...
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo Caching configuration for production...
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo Building assets for production...
npm run build

echo Setting up production database...
php artisan migrate --force

echo Seeding production data...
php artisan db:seed --force

echo Production-like environment setup complete!
echo.
echo To revert back to development:
echo   1. Copy .env.backup to .env
echo   2. Run: composer install
echo   3. Run: php artisan config:clear

pause