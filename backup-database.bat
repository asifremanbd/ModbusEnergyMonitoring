@echo off
echo Creating Laravel database backup...

REM Get current timestamp
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "YY=%dt:~2,2%" & set "YYYY=%dt:~0,4%" & set "MM=%dt:~4,2%" & set "DD=%dt:~6,2%"
set "HH=%dt:~8,2%" & set "Min=%dt:~10,2%" & set "Sec=%dt:~12,2%"
set "timestamp=%YYYY%%MM%%DD%_%HH%%Min%%Sec%"

REM Create backup
mysqldump -u root -p --databases laravel_app --routines --triggers --single-transaction > backup_laravel_app_%timestamp%.sql

echo.
echo Backup created: backup_laravel_app_%timestamp%.sql
echo File location: %CD%\backup_laravel_app_%timestamp%.sql
echo.
pause