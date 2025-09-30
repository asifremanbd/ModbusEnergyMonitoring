# Production Server Quick Commands

## 🔐 SSH Connection
```bash
# Quick connect (recommended)
ssh deploy-server

# Direct connection with key
ssh -i ~/.ssh/id_rsa_deploy root@165.22.112.94

# Or use the batch file
connect-server.bat

# Troubleshooting connection issues
ssh -v -i ~/.ssh/id_rsa_deploy root@165.22.112.94  # Verbose output
ssh -o ConnectTimeout=30 -i ~/.ssh/id_rsa_deploy root@165.22.112.94  # Longer timeout
```

### 🔧 Connection Troubleshooting
```bash
# Test server connectivity
ping 165.22.112.94

# Test SSH port (Windows PowerShell)
Test-NetConnection -ComputerName 165.22.112.94 -Port 22

# Test alternative SSH ports
Test-NetConnection -ComputerName 165.22.112.94 -Port 2222

# Test web server (should work)
Test-NetConnection -ComputerName 165.22.112.94 -Port 80
Invoke-WebRequest -Uri "http://165.22.112.94" -Method Head

# Check if server is responding on any port
nmap -p 22,80,443,2222 165.22.112.94
```

### ⚠️ Current Status (as of Sept 24, 2025)
- ✅ **Server is ONLINE** - responds to ping and HTTP requests
- ✅ **Web application accessible** at http://165.22.112.94
- ❌ **SSH access BLOCKED** - port 22 is not accessible
- ❌ **HTTPS not configured** - port 443 is closed

### 🚨 SSH Access Issues
**Problem**: SSH connections timeout because port 22 is blocked/disabled
**Possible causes**:
1. Hosting provider disabled SSH for security
2. Firewall blocking SSH access
3. SSH service stopped or misconfigured
4. Server security policy changes

### 💡 Alternative Access Methods
```bash
# 1. Check if hosting provider has web-based terminal
# Visit your hosting provider's control panel

# 2. Try accessing via web interface
# Open browser: http://165.22.112.94

# 3. Contact hosting provider to:
#    - Enable SSH access
#    - Get alternative connection methods
#    - Check server security settings
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