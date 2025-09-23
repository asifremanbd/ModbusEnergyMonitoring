# Deployment Guide for Filament App

## Server Details
- **IP**: 165.22.112.94
- **User**: root
- **Password**: 2tDEoBWefYLp.PYyPF

## SSH Key Setup (✅ CONFIGURED)

SSH key authentication is now set up! You can connect without entering the password.

### Quick Connect Commands
```bash
# Connect using SSH alias
ssh deploy-server

# Or connect directly
ssh -i ~/.ssh/id_rsa_deploy root@165.22.112.94
```

### Setup Details (Already Done)
1. ✅ SSH key generated: `~/.ssh/id_rsa_deploy`
2. ✅ Public key copied to server
3. ✅ SSH config created for easy access
4. ✅ Server configured for key authentication

### SSH Config Location
- Windows: `C:\Users\[Username]\.ssh\config`
- Contains alias `deploy-server` for easy connection

## Prerequisites on Ubuntu Server

### 1. Install Required Software
```bash
# Update system
apt update && apt upgrade -y

# Install Nginx
apt install nginx -y

# Install PHP 8.1+ and extensions
apt install php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl php8.1-zip php8.1-mbstring php8.1-gd php8.1-bcmath php8.1-intl -y

# Install MySQL
apt install mysql-server -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install Node.js and npm
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install nodejs -y
```

### 2. Configure MySQL
```bash
# Secure MySQL installation
mysql_secure_installation

# Create database and user
mysql -u root -p
```

```sql
CREATE DATABASE filament_app;
CREATE USER 'filament_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON filament_app.* TO 'filament_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Deployment Steps

### 1. Upload Application Files
```bash
# Create application directory
mkdir -p /var/www/filament-app

# Set proper ownership
chown -R www-data:www-data /var/www/filament-app
```

### 2. Configure Environment
Copy the production environment file and update database credentials.

### 3. Install Dependencies
```bash
cd /var/www/filament-app
composer install --optimize-autoloader --no-dev
npm install && npm run build
```

### 4. Set Permissions
```bash
chown -R www-data:www-data /var/www/filament-app
chmod -R 755 /var/www/filament-app
chmod -R 775 /var/www/filament-app/storage
chmod -R 775 /var/www/filament-app/bootstrap/cache
```

### 5. Run Laravel Commands
```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Next Steps
1. Run the deployment script
2. Configure Nginx
3. Set up SSL certificate
4. Configure firewall