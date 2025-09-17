#!/bin/bash

# Complete server setup script
# Run this on your Ubuntu server

set -e

echo "🔧 Setting up Ubuntu server for Laravel Filament app..."

# Update system
echo "📦 Updating system packages..."
apt update && apt upgrade -y

# Install Nginx
echo "🌐 Installing Nginx..."
apt install nginx -y
systemctl start nginx
systemctl enable nginx

# Install PHP 8.1 and extensions
echo "🐘 Installing PHP 8.1 and extensions..."
apt install software-properties-common -y
add-apt-repository ppa:ondrej/php -y
apt update
apt install php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl php8.1-zip php8.1-mbstring php8.1-gd php8.1-bcmath php8.1-intl php8.1-cli -y

# Install MySQL
echo "🗄️ Installing MySQL..."
apt install mysql-server -y
systemctl start mysql
systemctl enable mysql

# Install Composer
echo "🎼 Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Node.js and npm
echo "📦 Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install nodejs -y

# Install additional tools
echo "🛠️ Installing additional tools..."
apt install unzip curl git rsync -y

# Configure PHP-FPM
echo "⚙️ Configuring PHP-FPM..."
systemctl start php8.1-fpm
systemctl enable php8.1-fpm

# Create application directory
echo "📁 Creating application directory..."
mkdir -p /var/www/filament-app
chown -R www-data:www-data /var/www/filament-app

# Configure Nginx
echo "🌐 Configuring Nginx..."
rm -f /etc/nginx/sites-enabled/default

# Create MySQL database and user
echo "🗄️ Setting up MySQL database..."
mysql -e "CREATE DATABASE IF NOT EXISTS filament_app;"
mysql -e "CREATE USER IF NOT EXISTS 'filament_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';"
mysql -e "GRANT ALL PRIVILEGES ON filament_app.* TO 'filament_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Configure firewall
echo "🔥 Configuring firewall..."
ufw allow 22
ufw allow 80
ufw allow 443
ufw --force enable

echo "✅ Server setup completed!"
echo "📝 Next steps:"
echo "1. Upload your application files to /var/www/filament-app"
echo "2. Copy the Nginx configuration"
echo "3. Update the .env file with database credentials"
echo "4. Run Laravel setup commands"