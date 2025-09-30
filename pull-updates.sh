#!/bin/bash

# Script to pull git updates on Ubuntu production server
# Run this script on your production server

set -e

APP_DIR="/var/www/filament-app"
BACKUP_DIR="/var/backups/filament-app"

echo "🔄 Pulling latest updates from git repository..."

# Check if we're in the right directory
if [ ! -d "$APP_DIR" ]; then
    echo "❌ Application directory $APP_DIR not found"
    exit 1
fi

cd $APP_DIR

echo "📋 Current git status:"
git status

echo "📦 Creating backup before update..."
mkdir -p $BACKUP_DIR
cp -r $APP_DIR $BACKUP_DIR/backup-$(date +%Y%m%d_%H%M%S)

echo "🔄 Fetching latest changes..."
git fetch origin

echo "📊 Showing what will be updated:"
git log HEAD..origin/main --oneline

echo "⬇️ Pulling latest changes..."
git pull origin main

echo "🔧 Running post-update tasks..."

# Clear caches
echo "🧹 Clearing application caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Update composer dependencies if composer.json changed
if git diff HEAD~1 HEAD --name-only | grep -q "composer.json"; then
    echo "📦 Updating composer dependencies..."
    composer install --optimize-autoloader --no-dev
fi

# Run migrations if there are new ones
if git diff HEAD~1 HEAD --name-only | grep -q "database/migrations"; then
    echo "🗄️ Running database migrations..."
    php artisan migrate --force
fi

# Restart services
echo "🔄 Restarting services..."
systemctl restart php8.3-fpm
systemctl restart nginx

# Restart polling system if it exists
if systemctl is-active --quiet filament-queue-worker; then
    echo "🔄 Restarting polling system..."
    systemctl restart filament-queue-worker
fi

if systemctl is-active --quiet filament-polling-monitor.timer; then
    systemctl restart filament-polling-monitor.timer
fi

echo "✅ Update completed successfully!"
echo "📊 Current application status:"
php artisan --version
git log -1 --oneline

echo "🔍 Checking system status..."
if command -v php artisan polling:reliable &> /dev/null; then
    php artisan polling:reliable status
fi