#!/bin/bash

# Deployment script for Filament App
# Server: 165.22.112.94

set -e

SERVER_IP="165.22.112.94"
SERVER_USER="root"
SERVER_PASSWORD="2tDEoBWefYLp.PYyPF"
APP_DIR="/var/www/filament-app"
LOCAL_APP_DIR="./filament-app"

echo "🚀 Starting deployment to $SERVER_IP..."

# Function to run commands on remote server
run_remote() {
    sshpass -p "$SERVER_PASSWORD" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" "$1"
}

# Function to copy files to remote server
copy_to_remote() {
    sshpass -p "$SERVER_PASSWORD" rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' -e "ssh -o StrictHostKeyChecking=no" "$1" "$SERVER_USER@$SERVER_IP:$2"
}

echo "📦 Installing required packages on server..."
run_remote "apt update && apt upgrade -y"
run_remote "apt install -y nginx php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl php8.1-zip php8.1-mbstring php8.1-gd php8.1-bcmath php8.1-intl mysql-server unzip curl"

echo "🎼 Installing Composer..."
run_remote "curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer"

echo "📁 Creating application directory..."
run_remote "mkdir -p $APP_DIR"

echo "📤 Uploading application files..."
copy_to_remote "$LOCAL_APP_DIR/" "$APP_DIR/"

echo "🔧 Setting up application..."
run_remote "cd $APP_DIR && composer install --optimize-autoloader --no-dev"

echo "🔑 Setting permissions..."
run_remote "chown -R www-data:www-data $APP_DIR"
run_remote "chmod -R 755 $APP_DIR"
run_remote "chmod -R 775 $APP_DIR/storage"
run_remote "chmod -R 775 $APP_DIR/bootstrap/cache"

echo "✅ Deployment completed!"
echo "🔗 Next steps:"
echo "1. Configure your database on the server"
echo "2. Update the .env file with production settings"
echo "3. Run: php artisan migrate --force"
echo "4. Configure Nginx virtual host"