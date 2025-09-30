# Production Server Update Commands

Run these commands on your Ubuntu production server to pull the latest changes:

## 1. Navigate to application directory
```bash
cd /var/www/filament-app
```

## 2. Check current status
```bash
git status
git log -1 --oneline
```

## 3. Pull latest changes
```bash
git fetch origin
git pull origin main
```

## 4. Clear application caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 5. Update dependencies (if needed)
```bash
composer install --optimize-autoloader --no-dev
```

## 6. Run migrations (if any new ones)
```bash
php artisan migrate --force
```

## 7. Restart services
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

## 8. Restart polling system (if running)
```bash
sudo systemctl restart filament-queue-worker
sudo systemctl restart filament-polling-monitor.timer
```

## 9. Verify update
```bash
git log -1 --oneline
php artisan --version
```

## 10. Check polling system status
```bash
php artisan polling:reliable status
```

## Quick One-Liner (if you trust the update)
```bash
cd /var/www/ModbusEnergyMonitoring/filament-app && git pull origin main && php artisan cache:clear && php artisan config:clear && sudo systemctl restart php8.3-fpm nginx filament-queue-worker filament-polling-monitor.timer
```