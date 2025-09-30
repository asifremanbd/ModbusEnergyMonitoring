# Secure upload script using PowerShell and SSH
param(
    [string]$BackupFile = "filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql",
    [string]$ServerIP = "165.22.112.94",
    [string]$Username = "root",
    [string]$Password = "2tDEoBWefYLp.PYyPF"
)

Write-Host "=== Backup Upload Script ===" -ForegroundColor Green
Write-Host "File: $BackupFile"
Write-Host "Server: $Username@$ServerIP"
Write-Host ""

# Check if backup file exists
if (-not (Test-Path $BackupFile)) {
    Write-Host "ERROR: Backup file not found: $BackupFile" -ForegroundColor Red
    exit 1
}

$fileSize = (Get-Item $BackupFile).Length
Write-Host "File size: $([math]::Round($fileSize/1MB, 2)) MB"
Write-Host ""

# Try different upload methods
Write-Host "Attempting upload methods..." -ForegroundColor Yellow

# Method 1: Try with explicit SSH options
Write-Host "Method 1: SCP with SSH options"
$scpCmd = "scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=NUL `"$BackupFile`" $Username@$ServerIP`:/root/"
Write-Host "Command: $scpCmd"
Write-Host "Enter password when prompted: $Password"
Write-Host ""

try {
    Invoke-Expression $scpCmd
    if ($LASTEXITCODE -eq 0) {
        Write-Host "SUCCESS: File uploaded successfully!" -ForegroundColor Green
        exit 0
    }
} catch {
    Write-Host "Method 1 failed: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "If upload failed, try these manual commands:" -ForegroundColor Cyan
Write-Host "1. scp `"$BackupFile`" $Username@$ServerIP`:/root/"
Write-Host "2. Password: $Password"
Write-Host ""
Write-Host "Or use WinSCP GUI with these credentials:" -ForegroundColor Cyan
Write-Host "   Host: $ServerIP"
Write-Host "   Username: $Username" 
Write-Host "   Password: $Password"
Write-Host "   Protocol: SFTP"