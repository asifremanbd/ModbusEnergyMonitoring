#!/bin/bash

# Commands to run on the Ubuntu server after uploading files
# SSH into your server and run these commands

echo "🔧 Setting up Laravel application on server..."

# Navigate to application directory
cd /var/www/filament-app

# Install PHP dependencies
echo "📦 Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev

# Copy environment file
echo "⚙️ Setting up environment..."
cp .env.example .env

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --force

# Set proper permissions
echo "🔒 Setting file permissions..."
chown -R www-data:www-data /var/www/filament-app
chmod -R 755 /var/www/filament-app
chmod -R 775 /var/www/filament-app/storage
chmod -R 775 /var/www/filament-app/bootstrap/cache

# Create symbolic link for storage
php artisan storage:link

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Cache configuration
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configure Nginx
echo "🌐 Configuring Nginx..."
cp /var/www/filament-app/nginx-config.conf /etc/nginx/sites-available/filament-app
ln -sf /etc/nginx/sites-available/filament-app /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t

# Install Redis if not already installed
echo "📦 Installing Redis..."
apt update
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server

# Setup reliable polling system
echo "🔄 Setting up reliable polling system..."
chmod +x setup-reliable-polling.sh
./setup-reliable-polling.sh

# Restart services
echo "🔄 Restarting services..."
systemctl restart nginx
systemctl restart php8.1-fpm

echo "✅ Application setup completed!"
echo "🌐 Your app should be available at: http://165.22.112.94"
echo "📊 Check polling status with: php artisan polling:reliable status"