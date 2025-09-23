# Production Server Quick Commands

## 🔐 SSH Connection
```bash
# Quick connect (recommended)
ssh deploy-server

# Direct connection with key
ssh -i ~/.ssh/id_rsa_deploy root@165.22.112.94

# Or use the batch file
connect-server.bat
```

## 📊 Server Status Commands
```bash
# System information
uname -a                    # System info
df -h                       # Disk usage
free -h                     # Memory usage
htop                        # Process monitor
systemctl status nginx     # Nginx status
systemctl status mysql     # MySQL status

# Application status
cd /var/www/filament-app
php artisan queue:work --daemon    # Start queue worker
php artisan schedule:run           # Run scheduled tasks
tail -f storage/logs/laravel.log   # View logs
```

## 🚀 Deployment Commands
```bash
# Navigate to app directory
cd /var/www/filament-app

# Update application
git pull origin main
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Laravel maintenance
php artisan down                    # Enable maintenance mode
php artisan migrate --force         # Run migrations
php artisan config:cache           # Cache config
php artisan route:cache            # Cache routes
php artisan view:cache             # Cache views
php artisan up                     # Disable maintenance mode

# Set permissions
chown -R www-data:www-data /var/www/filament-app
chmod -R 755 /var/www/filament-app
chmod -R 775 storage bootstrap/cache
```

## 🗄️ Database Commands
```bash
# Connect to MySQL
mysql -u root -p

# Backup database
mysqldump -u filament_user -p filament_app > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore database
mysql -u filament_user -p filament_app < backup_file.sql
```

## 🔧 Service Management
```bash
# Restart services
systemctl restart nginx
systemctl restart php8.1-fpm
systemctl restart mysql

# Check service status
systemctl status nginx
systemctl status php8.1-fpm
systemctl status mysql

# View service logs
journalctl -u nginx -f
journalctl -u php8.1-fpm -f
```

## 📁 Important Directories
- **Application**: `/var/www/filament-app`
- **Nginx Config**: `/etc/nginx/sites-available/`
- **PHP Config**: `/etc/php/8.1/fpm/`
- **Logs**: `/var/log/nginx/` and `/var/www/filament-app/storage/logs/`
- **SSL Certificates**: `/etc/letsencrypt/live/`

## 🔥 Emergency Commands
```bash
# Stop all services
systemctl stop nginx php8.1-fpm mysql

# Start all services
systemctl start mysql php8.1-fpm nginx

# Check what's using port 80/443
netstat -tulpn | grep :80
netstat -tulpn | grep :443

# Clear Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```