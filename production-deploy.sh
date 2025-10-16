#!/bin/bash

echo "================================"
echo "   PRODUCTION DEPLOYMENT"
echo "================================"
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Step 1: Pulling latest changes...${NC}"
git pull origin main

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Git pull failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Git pull successful${NC}"
echo

echo -e "${YELLOW}Step 2: Clearing caches...${NC}"
php artisan optimize:clear
php artisan filament:optimize-clear

echo -e "${GREEN}✓ Caches cleared${NC}"
echo

echo -e "${YELLOW}Step 3: Optimizing for production...${NC}"
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${GREEN}✓ Application optimized${NC}"
echo

echo -e "${YELLOW}Step 4: Updating Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader

echo -e "${GREEN}✓ Composer updated${NC}"
echo

echo -e "${YELLOW}Step 5: Verifying deployment...${NC}"

# Check if user routes exist
echo "Checking user management routes..."
php artisan route:list --name=users

# Test database connection
echo "Testing database connection..."
php artisan tinker --execute="echo 'Database connection: OK, Users: ' . App\Models\User::count();"

echo -e "${GREEN}✓ Verification complete${NC}"
echo

echo -e "${YELLOW}Step 6: Setting file permissions...${NC}"
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

echo -e "${GREEN}✓ Permissions set${NC}"
echo

echo "================================"
echo -e "${GREEN}   DEPLOYMENT SUCCESSFUL!${NC}"
echo "================================"
echo
echo "Next steps:"
echo "1. Restart your web server:"
echo "   sudo systemctl restart apache2"
echo "   # or"
echo "   sudo systemctl restart nginx"
echo
echo "2. Test the deployment:"
echo "   https://your-domain.com/admin/login"
echo "   Look for 'Users' in the sidebar"
echo
echo "3. If issues occur, check logs:"
echo "   tail -f storage/logs/laravel.log"
echo