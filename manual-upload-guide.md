# Manual Backup Upload Guide

## Issue Identified
The SSH connection works fine, but SCP/SFTP is failing after authentication. This is likely due to:
1. Server-side SCP/SFTP configuration issues
2. Windows OpenSSH compatibility problems
3. Possible server restrictions on file transfers

## File to Upload
- **File**: `filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql`
- **Size**: 2.14 MB
- **Date**: September 23, 2025

## Server Details
- **IP**: 165.22.112.94
- **User**: root
- **Password**: 2tDEoBWefYLp.PYyPF

## Recommended Solutions

### Option 1: Use WinSCP (Recommended)
1. Download and install WinSCP from https://winscp.net/
2. Create new connection:
   - Protocol: SFTP
   - Host: 165.22.112.94
   - Username: root
   - Password: 2tDEoBWefYLp.PYyPF
3. Navigate to `/root/` on server
4. Upload the backup file

### Option 2: Use PuTTY PSCP
1. Download PuTTY suite from https://putty.org/
2. Use command: `pscp -pw 2tDEoBWefYLp.PYyPF "filament-app\storage\app\database-backups\laravel_backup_2025-09-23_01-49-35.sql" root@165.22.112.94:/root/`

### Option 3: Check Server Configuration
SSH to server and check:
```bash
ssh root@165.22.112.94
# Check if SFTP subsystem is enabled
grep -i sftp /etc/ssh/sshd_config
# Check if SCP is allowed
which scp
```

### Option 4: Alternative Upload via SSH
If direct file transfer fails, you could:
1. SSH to server: `ssh root@165.22.112.94`
2. Use `wget` or `curl` to download from a temporary web location
3. Or use base64 encoding for small files

## Next Steps
1. Try WinSCP first (most reliable)
2. If that fails, check server SFTP/SCP configuration
3. Consider using SSH keys instead of password authentication