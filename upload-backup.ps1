# Upload latest backup to production server
# PowerShell script for secure file transfer

$serverIP = "165.22.112.94"
$username = "root"
$password = "2tDEoBWefYLp.PYyPF"
$backupFile = "filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql"
$remotePath = "/root/"

Write-Host "Uploading backup file: $backupFile"
Write-Host "To server: $username@$serverIP$remotePath"

# Using PSCP (PuTTY's SCP) if available, otherwise try WinSCP or manual SCP
try {
    # Try with pscp first (part of PuTTY suite)
    if (Get-Command pscp -ErrorAction SilentlyContinue) {
        Write-Host "Using PSCP for transfer..."
        & pscp -pw $password $backupFile "$username@$serverIP`:$remotePath"
    }
    else {
        Write-Host "PSCP not found. Using standard SCP..."
        Write-Host "You may need to enter the password manually: $password"
        & scp $backupFile "$username@$serverIP`:$remotePath"
    }
}
catch {
    Write-Host "Error during transfer: $_"
    Write-Host ""
    Write-Host "Alternative methods:"
    Write-Host "1. Install PuTTY suite for pscp command"
    Write-Host "2. Use WinSCP GUI application"
    Write-Host "3. Manual SCP command: scp `"$backupFile`" $username@$serverIP`:$remotePath"
    Write-Host "   Password: $password"
}