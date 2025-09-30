@echo off
echo Creating SFTP batch commands...

REM Create SFTP command file
echo put "filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql" /root/ > sftp_commands.txt
echo quit >> sftp_commands.txt

echo.
echo SFTP commands created. Now connecting...
echo Password when prompted: 2tDEoBWefYLp.PYyPF
echo.

sftp -b sftp_commands.txt root@165.22.112.94

echo.
echo Upload attempt completed.
pause