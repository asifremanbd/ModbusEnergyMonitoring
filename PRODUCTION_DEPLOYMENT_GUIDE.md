# Production Deployment Guide - Gateway Form Update

## Overview
This guide covers deploying the Gateway Data Points form updates to the production environment.

## What's Being Deployed
- New database columns: `application`, `unit`, `load_type`
- Updated DataPoint model with new fillable fields
- Enhanced Gateway form with conditional field visibility
- Backward compatible changes with data backfill

## Deployment Options

### Option 1: Automated Deployment (Recommended)

#### For Linux/Ubuntu Production:
```bash
# Make the script executable
chmod +x deploy-gateway-form-update.sh

# Run the deployment
./deploy-gateway-form-update.sh
```

#### For Windows Production:
```cmd
# Run the deployment script
deploy-gateway-form-update.bat
```

### Option 2: Manual Deployment Steps

1. **Pull Latest Changes**
   ```bash
   git pull origin main
   ```

2. **Navigate to Application Directory**
   ```bash
   cd filament-app
   ```

3. **Clear Caches**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   ```

4. **Run Migration**
   ```bash
   php artisan migrate --path=database/migrations/2025_10_16_000000_add_application_unit_load_type_to_data_points_table.php --force
   ```

5. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## Verification Steps

### 1. Database Verification
Check that new columns exist:
```sql
DESCRIBE data_points;
```

Should show:
- `application` varchar(255) NOT NULL DEFAULT 'monitoring'
- `unit` varchar(255) NULL
- `load_type` varchar(255) NULL

### 2. Data Verification
Check that existing data was backfilled:
```sql
SELECT application, unit, load_type FROM data_points LIMIT 5;
```

Should show existing records with `application = 'monitoring'` and `unit = 'kWh'`.

### 3. Application Verification
- Access the admin panel
- Navigate to Gateways → Edit a gateway
- Check the Data Points section
- Verify new fields are present and working:
  - Application dropdown (Monitoring/Automation)
  - Unit dropdown (visible when Application = Monitoring)
  - Load Type dropdown (always visible)

## Rollback Plan

If issues occur, you can rollback the migration:

```bash
cd filament-app
php artisan migrate:rollback --step=1
```

This will remove the new columns and restore the previous state.

## Post-Deployment Testing

1. **Create New Data Point**
   - Set Application to "Monitoring"
   - Verify Unit field appears
   - Select a Unit value
   - Select a Load Type
   - Save and verify data is stored correctly

2. **Test Conditional Logic**
   - Change Application to "Automation"
   - Verify Unit field disappears
   - Change back to "Monitoring"
   - Verify Unit field reappears

3. **Verify Existing Data**
   - Check that existing data points still work
   - Verify all existing fields function normally
   - Test data point preview functionality

## Troubleshooting

### Migration Fails
- Check database connection
- Verify user has ALTER TABLE permissions
- Check for conflicting migrations

### Form Not Loading
- Clear all caches: `php artisan optimize:clear`
- Check PHP error logs
- Verify file permissions

### Fields Not Appearing
- Clear browser cache
- Check browser console for JavaScript errors
- Verify Filament assets are compiled

## Support

If you encounter issues:
1. Check the application logs: `tail -f storage/logs/laravel.log`
2. Verify database connection and permissions
3. Ensure all caches are cleared
4. Test in a staging environment first if possible

## Files Modified
- `filament-app/app/Models/DataPoint.php`
- `filament-app/app/Filament/Resources/GatewayResource.php`
- `filament-app/database/migrations/2025_10_16_000000_add_application_unit_load_type_to_data_points_table.php`

## Success Indicators
✅ Migration runs without errors  
✅ New columns appear in database  
✅ Existing data is backfilled with defaults  
✅ Gateway form loads with new fields  
✅ Conditional visibility works correctly  
✅ All existing functionality preserved