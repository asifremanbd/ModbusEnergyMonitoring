# Polling Schedule Fix Documentation

## Overview

The polling schedule fix functionality addresses the disconnect between gateway `is_active` flags and actual polling job execution. This ensures that all active gateways have polling jobs scheduled and running, while inactive gateways do not consume system resources.

## Problem Description

The polling system can get into a state where:
- Gateways show as "active" but have no polling jobs scheduled
- Polling jobs are overdue and not being rescheduled
- Poll intervals have changed but existing jobs use old intervals
- Inactive gateways still have polling jobs running

## Solution Components

### 1. Polling Schedule Fix Command

**Command:** `php artisan polling:fix-schedule`

**Purpose:** Analyzes and fixes polling schedule integrity issues.

**Options:**
- `--dry-run`: Show what would be fixed without making changes
- `--force`: Force restart polling for all active gateways

**Usage Examples:**
```bash
# Check for issues without fixing them
php artisan polling:fix-schedule --dry-run

# Fix all detected issues
php artisan polling:fix-schedule

# Force restart all polling (useful after configuration changes)
php artisan polling:fix-schedule --force
```

### 2. Polling Synchronization Command

**Command:** `php artisan polling:reliable sync`

**Purpose:** Ensures all active gateways have polling jobs and inactive gateways don't.

**Usage:**
```bash
php artisan polling:reliable sync
```

### 3. Enhanced Reliable Polling Service

The `ReliablePollingService` now includes:
- `ensureActiveGatewaysPolling()`: Synchronizes gateway states with polling jobs
- Enhanced validation and integrity checking
- Better error handling and logging

## Issue Types Detected and Fixed

### 1. Missing Polling
- **Problem:** Active gateway has no polling jobs scheduled
- **Detection:** Gateway has `is_active = true` but no polling status in cache
- **Fix:** Start polling for the gateway

### 2. Overdue Polling
- **Problem:** Polling job is overdue and not rescheduling
- **Detection:** Last scheduled time + poll interval is in the past
- **Fix:** Stop and restart polling with correct timing

### 3. Interval Mismatch
- **Problem:** Polling job uses old poll interval after configuration change
- **Detection:** Cached poll interval differs from gateway's current interval
- **Fix:** Restart polling with updated interval

### 4. Unwanted Polling
- **Problem:** Inactive gateway still has polling jobs running
- **Detection:** Gateway has `is_active = false` but polling status exists
- **Fix:** Stop polling and clear status

## Automated Monitoring

The system includes automated monitoring via scheduled tasks:

```php
// Console/Kernel.php
$schedule->command('polling:reliable audit')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

$schedule->command('polling:reliable validate')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

$schedule->command('polling:fix-schedule')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

## Manual Troubleshooting Workflow

### Step 1: Check System Status
```bash
php artisan polling:reliable status --detailed
```

### Step 2: Validate Polling Integrity
```bash
php artisan polling:reliable validate
```

### Step 3: Identify Specific Issues
```bash
php artisan polling:fix-schedule --dry-run
```

### Step 4: Fix Issues
```bash
php artisan polling:fix-schedule
```

### Step 5: Verify Fix
```bash
php artisan polling:reliable validate
php artisan polling:reliable status --detailed
```

## Common Scenarios

### Scenario 1: Gateway Shows Active But No Data
**Symptoms:**
- Gateway appears active in admin interface
- No live data or past readings being collected
- Queue workers are running

**Solution:**
```bash
php artisan polling:fix-schedule --dry-run
php artisan polling:fix-schedule
```

### Scenario 2: Changed Poll Interval Not Taking Effect
**Symptoms:**
- Updated poll interval in admin interface
- Polling continues at old interval

**Solution:**
```bash
php artisan polling:fix-schedule --force
```

### Scenario 3: System Restart Lost Polling Jobs
**Symptoms:**
- After server restart, no polling is happening
- Queue workers are running but no jobs being processed

**Solution:**
```bash
php artisan polling:reliable sync
```

### Scenario 4: Inactive Gateways Still Consuming Resources
**Symptoms:**
- Disabled gateways still showing polling activity
- Unnecessary queue job processing

**Solution:**
```bash
php artisan polling:fix-schedule
```

## Monitoring and Alerts

### Health Check Endpoints
- `/api/polling/health` - Overall system health
- `/api/polling/diagnostics` - Detailed diagnostic results

### Log Monitoring
Monitor these log entries for issues:
- `"Fixed missing polling for gateway"` - Indicates automatic repair
- `"Gateway polling integrity issues detected"` - Requires attention
- `"Failed to fix polling for gateway"` - Manual intervention needed

### Key Metrics to Monitor
- Active gateways vs actively polling count
- Polling integrity validation failures
- Automatic fix success/failure rates

## Best Practices

### 1. Regular Monitoring
- Run `polling:reliable validate` regularly
- Monitor system logs for automatic fixes
- Check health endpoints in monitoring systems

### 2. Configuration Changes
- After changing poll intervals, run `polling:fix-schedule --force`
- After enabling/disabling gateways, run `polling:reliable sync`

### 3. System Maintenance
- Include polling validation in deployment scripts
- Run integrity checks after system updates
- Monitor queue worker health alongside polling health

### 4. Troubleshooting
- Always start with `--dry-run` to understand issues
- Use detailed status output for comprehensive view
- Check both polling status and queue worker status

## Integration with Existing Commands

The new functionality integrates with existing polling commands:

```bash
# Start all polling (existing)
php artisan polling:reliable start

# Stop all polling (existing)
php artisan polling:reliable stop

# Check status (existing, enhanced)
php artisan polling:reliable status --detailed

# Audit and cleanup (existing)
php artisan polling:reliable audit

# Validate integrity (existing, enhanced)
php artisan polling:reliable validate

# Synchronize states (new)
php artisan polling:reliable sync

# Fix schedule issues (new)
php artisan polling:fix-schedule
```

## Error Handling

The system includes comprehensive error handling:
- Graceful handling of Redis connection issues
- Database connectivity problems
- Queue worker failures
- Lock contention scenarios

All errors are logged with appropriate context for debugging.

## Performance Considerations

- Commands use efficient caching and database queries
- Minimal impact on running system during fixes
- Background scheduling prevents overlap conflicts
- Automatic cleanup prevents resource leaks

## Security Considerations

- Commands require appropriate Laravel permissions
- No sensitive data exposed in command output
- Proper logging without credential exposure
- Safe handling of concurrent operations