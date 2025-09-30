#!/bin/bash

# Fix Laravel storage and cache permissions
echo "Fixing Laravel permissions..."

# Navigate to the Laravel app directory
cd /var/www/ModbusEnergyMonitoring/filament-app

# Set proper ownership (assuming www-data is your web server user)
sudo chown -R www-data:www-data storage bootstrap/cache

# Set proper permissions for storage and cache directories
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# Ensure log directory exists and has correct permissions
sudo mkdir -p storage/logs
sudo chmod 775 storage/logs
sudo chown www-data:www-data storage/logs

# If laravel.log exists, fix its permissions too
if [ -f storage/logs/laravel.log ]; then
    sudo chmod 664 storage/logs/laravel.log
    sudo chown www-data:www-data storage/logs/laravel.log
fi

echo "Permissions fixed!"
echo "You may need to restart your web server:"
echo "sudo systemctl restart nginx"
echo "sudo systemctl restart php8.1-fpm"  # or your PHP version