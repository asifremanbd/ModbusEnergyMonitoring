@echo off
echo 🚀 Quick Deploy to Ubuntu Server
echo.

REM Install sshpass if not available (you might need to install it manually)
echo 📦 Checking dependencies...

REM Create a temporary deployment package
echo 📦 Creating deployment package...
if exist "deployment-temp" rmdir /s /q "deployment-temp"
mkdir "deployment-temp"

REM Copy application files (excluding unnecessary files)
echo 📁 Copying application files...
xcopy "filament-app" "deployment-temp\filament-app" /E /I /H /Y /EXCLUDE:exclude-files.txt

REM Create exclude file for rsync-like behavior
echo node_modules > exclude-files.txt
echo .git >> exclude-files.txt
echo storage\logs\* >> exclude-files.txt
echo storage\framework\cache\* >> exclude-files.txt
echo storage\framework\sessions\* >> exclude-files.txt
echo storage\framework\views\* >> exclude-files.txt

echo.
echo ⚠️  MANUAL STEPS REQUIRED:
echo.
echo 1. Upload the 'deployment-temp\filament-app' folder to your server
echo 2. Run the server setup commands
echo 3. Configure the database
echo.
echo 💡 Use WinSCP, FileZilla, or similar tool to upload files
echo    Server: 165.22.112.94
echo    User: root
echo    Password: 2tDEoBWefYLp.PYyPF
echo.
pause