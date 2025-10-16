#!/bin/bash

echo "========================================"
echo "  SYNCING FROM PRODUCTION SERVER"
echo "========================================"
echo
echo "This will:"
echo "1. Download production codebase"
echo "2. Export production database"
echo "3. Import to local database"
echo "4. Update local environment"
echo
read -p "Continue? (y/n): " confirm
if [[ $confirm != [yY] ]]; then
    exit 0
fi

SERVER_IP="165.22.112.94"
SERVER_USER="root"
SERVER_PASSWORD="2tDEoBWefYLp.PYyPF"
PROD_APP_DIR="/var/www/filament-app"
LOCAL_APP_DIR="filament-app"

echo
echo "📦 Step 1: Backing up current local setup..."
if [ -d "filament-app-backup" ]; then
    rm -rf "filament-app-backup"
fi
cp -r "filament-app" "filament-app-backup"
echo "✅ Local backup created in filament-app-backup/"

echo
echo "📥 Step 2: Downloading production codebase..."
sshpass -p "$SERVER_PASSWORD" rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'vendor' --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' -e "ssh -o StrictHostKeyChecking=no" "$SERVER_USER@$SERVER_IP:$PROD_APP_DIR/" "$LOCAL_APP_DIR/"
echo "✅ Production codebase downloaded"

echo
echo "🗄️ Step 3: Exporting production database..."
sshpass -p "$SERVER_PASSWORD" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" "mysqldump -u filament_user -p'your_secure_password' filament_app > /tmp/production_db.sql"
echo "✅ Production database exported"

echo
echo "📥 Step 4: Downloading database dump..."
sshpass -p "$SERVER_PASSWORD" scp -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP:/tmp/production_db.sql" "production_db.sql"
echo "✅ Database dump downloaded"

echo
echo "🗄️ Step 5: Importing to local database..."
cd filament-app
mysql -u root -p laravel_app < ../production_db.sql
echo "✅ Database imported"

echo
echo "🔧 Step 6: Installing dependencies..."
composer install
npm install
echo "✅ Dependencies installed"

echo
echo "🔑 Step 7: Setting up environment..."
cp .env .env.local-backup
cp .env.production .env.temp
# Update database credentials for local environment
sed -i 's/DB_DATABASE=filament_app/DB_DATABASE=laravel_app/' .env.temp
sed -i 's/DB_USERNAME=filament_user/DB_USERNAME=root/' .env.temp
sed -i 's/DB_PASSWORD=your_secure_password/DB_PASSWORD=/' .env.temp
mv .env.temp .env
echo "✅ Environment configured"

echo
echo "🚀 Step 8: Final setup..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
npm run build
echo "✅ Caches cleared and assets built"

echo
echo "========================================"
echo "  SYNC COMPLETE!"
echo "========================================"
echo
echo "Your local environment now matches production:"
echo "✅ Same codebase"
echo "✅ Same database data"
echo "✅ Same configuration"
echo
echo "Backup locations:"
echo "- Code backup: filament-app-backup/"
echo "- Env backup: filament-app/.env.local-backup"
echo
echo "You can now start developing new features!"