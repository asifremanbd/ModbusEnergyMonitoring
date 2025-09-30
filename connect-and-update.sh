#!/bin/bash

# Script to connect to production server and pull updates
# This script handles the SSH connection and runs update commands

set -e

SERVER_IP="165.22.112.94"
SERVER_USER="root"
SERVER_PASSWORD="2tDEoBWefYLp.PYyPF"
APP_DIR="/var/www/filament-app"

echo "🔐 Connecting to production server $SERVER_IP..."

# Function to run commands on remote server
run_remote() {
    echo "🔧 Running: $1"
    sshpass -p "$SERVER_PASSWORD" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" "$1"
}

echo "📋 Step 1: Checking current application status..."
run_remote "cd $APP_DIR && pwd && git status --porcelain"

echo "📋 Step 2: Showing current version..."
run_remote "cd $APP_DIR && git log -1 --oneline"

echo "📋 Step 3: Fetching latest changes..."
run_remote "cd $APP_DIR && git fetch origin"

echo "📋 Step 4: Showing what will be updated..."
run_remote "cd $APP_DIR && git log HEAD..origin/main --oneline || echo 'Already up to date'"

echo "📋 Step 5: Creating backup..."
run_remote "mkdir -p /var/backups/filament-app && cp -r $APP_DIR /var/backups/filament-app/backup-\$(date +%Y%m%d_%H%M%S)"

echo "📋 Step 6: Pulling latest changes..."
run_remote "cd $APP_DIR && git pull origin main"

echo "📋 Step 7: Clearing application caches..."
run_remote "cd $APP_DIR && php artisan cache:clear"
run_remote "cd $APP_DIR && php artisan config:clear"
run_remote "cd $APP_DIR && php artisan route:clear"
run_remote "cd $APP_DIR && php artisan view:clear"

echo "📋 Step 8: Checking for composer updates..."
run_remote "cd $APP_DIR && composer install --optimize-autoloader --no-dev --no-interaction"

echo "📋 Step 9: Running database migrations..."
run_remote "cd $APP_DIR && php artisan migrate --force"

echo "📋 Step 10: Setting proper permissions..."
run_remote "chown -R www-data:www-data $APP_DIR"
run_remote "chmod -R 755 $APP_DIR"
run_remote "chmod -R 775 $APP_DIR/storage"
run_remote "chmod -R 775 $APP_DIR/bootstrap/cache"

echo "📋 Step 11: Restarting services..."
run_remote "systemctl restart php8.1-fpm"
run_remote "systemctl restart nginx"

echo "📋 Step 12: Restarting polling system..."
run_remote "systemctl restart filament-queue-worker || echo 'Queue worker not running'"
run_remote "systemctl restart filament-polling-monitor.timer || echo 'Polling monitor not running'"

echo "📋 Step 13: Verifying update..."
run_remote "cd $APP_DIR && git log -1 --oneline"
run_remote "cd $APP_DIR && php artisan --version"

echo "📋 Step 14: Checking system status..."
run_remote "cd $APP_DIR && php artisan polling:reliable status || echo 'Polling system not configured'"

echo "✅ Update completed successfully!"
echo "🌐 Your application should now be running the latest version"
echo "🔗 Check your application at: http://$SERVER_IP"