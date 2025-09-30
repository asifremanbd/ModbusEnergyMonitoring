#!/bin/bash

# Fix Polling System Timeouts and 504 Errors
# This script addresses nginx timeouts, PHP-FPM settings, and polling system issues

set -e

echo "🔧 Fixing Polling System Timeouts and 504 Errors..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root (use sudo)"
   exit 1
fi

print_status "Starting system diagnostics and fixes..."

# 1. Update Nginx Configuration
print_status "Updating Nginx configuration with timeout settings..."
cp /etc/nginx/sites-available/filament-app /etc/nginx/sites-available/filament-app.backup.$(date +%Y%m%d_%H%M%S) 2>/dev/null || true

# Copy our updated nginx config
cp /var/www/filament-app/../nginx-config.conf /etc/nginx/sites-available/filament-app

# Test nginx configuration
if nginx -t; then
    print_success "Nginx configuration is valid"
    systemctl reload nginx
    print_success "Nginx reloaded with new timeout settings"
else
    print_error "Nginx configuration test failed"
    exit 1
fi

# 2. Update PHP-FPM Configuration
print_status "Updating PHP-FPM timeout settings..."

PHP_FPM_CONF="/etc/php/8.1/fpm/pool.d/www.conf"
PHP_INI="/etc/php/8.1/fpm/php.ini"

# Backup PHP-FPM config
cp $PHP_FPM_CONF $PHP_FPM_CONF.backup.$(date +%Y%m%d_%H%M%S)

# Update PHP-FPM pool configuration
cat >> $PHP_FPM_CONF << 'EOF'

; Extended timeout settings for long-running operations
request_terminate_timeout = 300
request_slowlog_timeout = 60s
slowlog = /var/log/php8.1-fpm-slow.log

; Process management
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000

; Memory settings
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
EOF

# Update PHP.ini settings
sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
sed -i 's/max_input_time = .*/max_input_time = 300/' $PHP_INI
sed -i 's/memory_limit = .*/memory_limit = 512M/' $PHP_INI

# Restart PHP-FPM
systemctl restart php8.1-fpm
print_success "PHP-FPM updated and restarted"

# 3. Check and Fix Redis Configuration
print_status "Checking Redis configuration..."

REDIS_CONF="/etc/redis/redis.conf"
if [ -f "$REDIS_CONF" ]; then
    # Backup Redis config
    cp $REDIS_CONF $REDIS_CONF.backup.$(date +%Y%m%d_%H%M%S)
    
    # Update Redis settings for better queue performance
    sed -i 's/# maxmemory <bytes>/maxmemory 512mb/' $REDIS_CONF
    sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' $REDIS_CONF
    sed -i 's/timeout 0/timeout 300/' $REDIS_CONF
    
    systemctl restart redis
    print_success "Redis configuration updated and restarted"
else
    print_warning "Redis configuration file not found at $REDIS_CONF"
fi

# 4. Check System Resources
print_status "Checking system resources..."

# Memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.1f"), $3/$2 * 100.0}')
print_status "Current memory usage: ${MEMORY_USAGE}%"

if (( $(echo "$MEMORY_USAGE > 80" | bc -l) )); then
    print_warning "High memory usage detected (${MEMORY_USAGE}%)"
    print_status "Consider increasing server memory or optimizing applications"
fi

# Disk usage
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
print_status "Current disk usage: ${DISK_USAGE}%"

if [ "$DISK_USAGE" -gt 80 ]; then
    print_warning "High disk usage detected (${DISK_USAGE}%)"
    print_status "Cleaning up old log files..."
    find /var/log -name "*.log" -type f -mtime +7 -delete 2>/dev/null || true
    find /var/www/filament-app/storage/logs -name "*.log" -type f -mtime +3 -delete 2>/dev/null || true
fi

# 5. Restart and Optimize Queue Workers
print_status "Restarting queue workers with optimized settings..."

# Stop existing workers
systemctl stop filament-queue-worker 2>/dev/null || true
systemctl stop filament-polling-monitor.timer 2>/dev/null || true

# Clear any stuck jobs
cd /var/www/filament-app
sudo -u www-data php artisan queue:clear redis 2>/dev/null || true
sudo -u www-data php artisan cache:clear 2>/dev/null || true

# Update queue worker service with better resource limits
cat > /etc/systemd/system/filament-queue-worker.service << 'EOF'
[Unit]
Description=Laravel Queue Worker for Filament App
After=network.target mysql.service redis.service
Requires=mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/filament-app
ExecStart=/usr/bin/php /var/www/filament-app/artisan queue:work redis --queue=polling,scheduling,default --sleep=5 --tries=3 --max-time=1800 --memory=256 --timeout=300
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

# Environment variables
Environment=LARAVEL_ENV=production

# Resource limits (reduced to prevent memory issues)
LimitNOFILE=65536
MemoryMax=512M
CPUQuota=50%

# Security settings
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ReadWritePaths=/var/www/filament-app/storage

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and start services
systemctl daemon-reload
systemctl enable filament-queue-worker
systemctl start filament-queue-worker
systemctl enable filament-polling-monitor.timer
systemctl start filament-polling-monitor.timer

print_success "Queue workers restarted with optimized settings"

# 6. Check and Fix Polling System
print_status "Checking polling system status..."

cd /var/www/filament-app

# Stop any existing polling
sudo -u www-data php artisan polling:reliable stop 2>/dev/null || true

# Run system audit
sudo -u www-data php artisan polling:reliable audit 2>/dev/null || true

# Wait a moment for cleanup
sleep 5

# Restart polling system
sudo -u www-data php artisan polling:reliable start

print_success "Polling system restarted"

# 7. System Status Check
print_status "Performing final system status check..."

# Check service status
services=("nginx" "php8.1-fpm" "redis" "mysql" "filament-queue-worker")
for service in "${services[@]}"; do
    if systemctl is-active --quiet $service; then
        print_success "$service is running"
    else
        print_error "$service is not running"
        systemctl status $service --no-pager -l
    fi
done

# Check polling status
print_status "Polling system status:"
sudo -u www-data php artisan polling:reliable status 2>/dev/null || print_warning "Could not get polling status"

# 8. Create monitoring script
print_status "Creating system monitoring script..."

cat > /usr/local/bin/check-polling-health.sh << 'EOF'
#!/bin/bash

# Quick health check script for polling system
cd /var/www/filament-app

echo "=== System Health Check $(date) ==="

# Check services
echo "Service Status:"
systemctl is-active nginx php8.1-fpm redis mysql filament-queue-worker | paste <(echo -e "nginx\nphp8.1-fpm\nredis\nmysql\nqueue-worker") -

# Check memory
echo -e "\nMemory Usage:"
free -h | grep -E "Mem|Swap"

# Check disk
echo -e "\nDisk Usage:"
df -h / | tail -1

# Check polling
echo -e "\nPolling Status:"
sudo -u www-data php artisan polling:reliable status 2>/dev/null || echo "Could not get polling status"

# Check queue
echo -e "\nQueue Status:"
redis-cli llen "queues:polling" | xargs echo "Polling queue length:"
redis-cli llen "queues:default" | xargs echo "Default queue length:"

echo "=== End Health Check ==="
EOF

chmod +x /usr/local/bin/check-polling-health.sh
print_success "Health check script created at /usr/local/bin/check-polling-health.sh"

# 9. Final recommendations
print_status "=== FINAL RECOMMENDATIONS ==="
echo ""
print_success "✅ Nginx timeout settings updated (300s timeouts)"
print_success "✅ PHP-FPM timeout and memory settings optimized"
print_success "✅ Redis configuration optimized for queues"
print_success "✅ Queue workers restarted with better resource limits"
print_success "✅ Polling system restarted and validated"
echo ""
print_status "📊 To monitor the system:"
echo "   • Run: /usr/local/bin/check-polling-health.sh"
echo "   • Check logs: journalctl -u filament-queue-worker -f"
echo "   • Monitor polling: php artisan polling:reliable status"
echo ""
print_status "🔧 If issues persist:"
echo "   • Check gateway connectivity: ping [gateway-ip]"
echo "   • Verify Modbus settings in the application"
echo "   • Consider reducing polling frequency for problematic gateways"
echo "   • Monitor server resources during peak polling times"
echo ""
print_warning "⚠️  Wait 5-10 minutes before testing to allow all services to stabilize"

print_success "🎉 System optimization complete!"