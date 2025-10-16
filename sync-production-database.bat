@echo off
echo Syncing database from production server...

echo Step 1: Creating database backup on production server...
ssh -i ~/.ssh/id_rsa_deploy root@165.22.112.94 "cd /var/www/ModbusEnergyMonitoring/filament-app && php artisan db:show --database=mysql"

echo Step 2: Exporting production database...
ssh -i ~/.ssh/id_rsa_deploy root@165.22.112.94 "cd /var/www/ModbusEnergyMonitoring/filament-app && mysqldump -u filament_user -p'your_secure_password' filament_app > production_backup.sql"

echo Step 3: Downloading database backup...
scp -i ~/.ssh/id_rsa_deploy root@165.22.112.94:/var/www/ModbusEnergyMonitoring/filament-app/production_backup.sql ./filament-app/

echo Step 4: Resetting local database...
cd filament-app
php artisan migrate:reset --force

echo Step 5: Running only the production migrations...
php artisan migrate --path=database/migrations/2014_10_12_000000_create_users_table.php --force
php artisan migrate --path=database/migrations/2014_10_12_100000_create_password_reset_tokens_table.php --force
php artisan migrate --path=database/migrations/2019_08_19_000000_create_failed_jobs_table.php --force
php artisan migrate --path=database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php --force
php artisan migrate --path=database/migrations/2025_09_11_094559_create_gateways_table.php --force
php artisan migrate --path=database/migrations/2025_09_11_094605_create_data_points_table.php --force
php artisan migrate --path=database/migrations/2025_09_11_094611_create_readings_table.php --force
php artisan migrate --path=database/migrations/2025_09_11_103649_create_jobs_table.php --force
php artisan migrate --path=database/migrations/2025_09_23_094325_add_unique_constraint_to_readings_table.php --force

echo Step 6: Importing production data...
mysql -u root -p laravel_app < production_backup.sql

echo Database sync complete!
echo Your local environment now matches production structure and data.
pause