@echo off
echo ================================
echo    User Management System
echo ================================
echo.

:menu
echo 1. List all users
echo 2. Create new user
echo 3. Delete user
echo 4. Access Filament Admin Panel
echo 5. Exit
echo.
set /p choice="Choose an option (1-5): "

if "%choice%"=="1" goto list_users
if "%choice%"=="2" goto create_user
if "%choice%"=="3" goto delete_user
if "%choice%"=="4" goto open_admin
if "%choice%"=="5" goto exit
echo Invalid choice. Please try again.
goto menu

:list_users
echo.
echo === Current Users ===
cd filament-app
php list-users.php
cd ..
echo.
pause
goto menu

:create_user
echo.
echo === Create New User ===
set /p name="Enter full name: "
set /p email="Enter email address: "
set /p password="Enter password: "

cd filament-app
php artisan tinker --execute="$user = new App\Models\User(); $user->name = '%name%'; $user->email = '%email%'; $user->password = Hash::make('%password%'); $user->save(); echo 'User created successfully with ID: ' . $user->id;"
cd ..
echo.
pause
goto menu

:delete_user
echo.
echo === Delete User ===
cd filament-app
php list-users.php
cd ..
echo.
set /p user_id="Enter user ID to delete: "
cd filament-app
php artisan tinker --execute="$user = App\Models\User::find(%user_id%); if($user) { $user->delete(); echo 'User deleted successfully.'; } else { echo 'User not found.'; }"
cd ..
echo.
pause
goto menu

:open_admin
echo.
echo Opening Filament Admin Panel...
echo Please navigate to: http://localhost:8000/admin
echo (Make sure your Laravel server is running with: php artisan serve)
start http://localhost:8000/admin
goto menu

:exit
echo.
echo Goodbye!
pause
exit