#!/bin/bash

echo "========================================"
echo "   Gateway Form Update Deployment"
echo "========================================"
echo

echo "[INFO] Updating gateway form to lightweight single-step..."

# Clear application cache
echo "[STEP 1] Clearing application cache..."
cd filament-app
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Optimize for production
echo "[STEP 2] Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo
echo "[SUCCESS] Gateway form has been updated!"
echo
echo "Changes made:"
echo "- Create form: Simplified to essential fields only"
echo "- Edit form: Full functionality with data points"
echo "- Removed complex wizard from create process"
echo "- Added connection test functionality"
echo "- Updated field descriptions and defaults"
echo
echo "The create form now includes:"
echo "- Name (required)"
echo "- IP Address (required)"
echo "- Port (default: 502)"
echo "- Unit ID (default: 1)"
echo "- Poll Interval (default: 120 seconds)"
echo "- Active toggle (default: ON)"
echo "- Connection test button"
echo
echo "Data points can be configured after creation via the edit form."
echo