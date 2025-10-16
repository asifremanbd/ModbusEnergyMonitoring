# Manual Production Sync Steps

If the automated scripts don't work, follow these manual steps:

## Prerequisites
- Install `sshpass` for Windows (or use PuTTY/WinSCP)
- Ensure you have MySQL command line tools
- Have SSH access to production server

## Step 1: Backup Current Local Setup
```bash
# Create backup of current code
cp -r filament-app filament-app-backup

# Backup current .env
cp filament-app/.env filament-app/.env.local-backup
```

## Step 2: Download Production Code
```bash
# Using rsync (if available)
rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'vendor' root@165.22.112.94:/var/www/filament-app/ filament-app/

# OR using SCP
scp -r root@165.22.112.94:/var/www/filament-app/* filament-app/
```

## Step 3: Export Production Database
```bash
# SSH into production server
ssh root@165.22.112.94

# Export database
mysqldump -u filament_user -p filament_app > /tmp/production_db.sql

# Exit SSH
exit

# Download database dump
scp root@165.22.112.94:/tmp/production_db.sql production_db.sql
```

## Step 4: Import Database Locally
```bash
# Import to local MySQL
mysql -u root -p laravel_app < production_db.sql
```

## Step 5: Update Local Environment
```bash
cd filament-app

# Copy production env as template
cp .env.production .env

# Update database credentials for local
# Change these lines in .env:
# DB_DATABASE=laravel_app
# DB_USERNAME=root
# DB_PASSWORD=
```

## Step 6: Install Dependencies
```bash
composer install
npm install
npm run build
```

## Step 7: Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

## Verification Steps
1. Check if application loads: `php artisan serve`
2. Verify database connection: `php artisan migrate:status`
3. Test main functionality in browser
4. Check logs for any errors: `tail -f storage/logs/laravel.log`

## Rollback if Needed
```bash
# Restore code
rm -rf filament-app
mv filament-app-backup filament-app

# Restore environment
cp filament-app/.env.local-backup filament-app/.env
```

## Production Server Details
- **IP**: 165.22.112.94
- **User**: root
- **Password**: 2tDEoBWefYLp.PYyPF
- **App Path**: /var/www/filament-app
- **DB Name**: filament_app
- **DB User**: filament_user