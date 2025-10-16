@echo off
echo ================================
echo    DEPLOY USER MANAGEMENT
echo ================================
echo.

cd filament-app

echo Checking Git status...
git status

echo.
echo Adding user management files to Git...

REM Add the main user management files
git add app/Filament/Resources/TestUserResource.php
git add app/Filament/Resources/TestUserResource/Pages/
git add app/Providers/Filament/AdminPanelProvider.php

REM Add backup standalone user management
git add app/Http/Controllers/UserManagementController.php
git add resources/views/user-management.blade.php
git add routes/web.php

REM Add documentation
git add ../FINAL_USER_MANAGEMENT_SUMMARY.md
git add ../DEPLOYMENT_GUIDE.md

echo.
echo Files added. Committing changes...

git commit -m "feat: Add Filament user management system

- Add TestUserResource with full CRUD operations for users
- Add user creation, editing, and deletion functionality  
- Update AdminPanelProvider for clean navigation
- Add standalone user management as backup option
- Fix database configuration issues (.env DB_DATABASE)
- Clean up duplicate user management attempts

Features:
- Filament-integrated user management in admin panel
- Search, sort, and pagination functionality
- Secure password hashing and validation
- Professional admin interface with proper styling
- Backup standalone user management page at /user-management

Fixes:
- Database connection issues (laravel -> laravel_app)
- Navigation conflicts between manual and auto-discovery
- Complex form configuration causing resource registration failures
- Authentication middleware issues causing 419 errors"

echo.
echo Pushing to repository...
git push origin main

echo.
echo ================================
echo    DEPLOYMENT COMPLETE!
echo ================================
echo.
echo Next steps for PRODUCTION:
echo 1. SSH into your production server
echo 2. Navigate to your app directory
echo 3. Run: git pull origin main
echo 4. Run: php artisan optimize:clear
echo 5. Run: php artisan filament:optimize-clear
echo 6. Run: php artisan optimize
echo 7. Restart web server if needed
echo.
echo Then test at: https://your-domain.com/admin/login
echo.
pause