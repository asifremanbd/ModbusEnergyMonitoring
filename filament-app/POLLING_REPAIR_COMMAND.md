# Polling Repair Command

The `polling:repair` command is a comprehensive tool for diagnosing and fixing all polling system issues automatically.

## Usage

### Basic Usage
```bash
# Run full diagnosis and repair
php artisan polling:repair

# Only diagnose without applying fixes
php artisan polling:repair --diagnose-only

# Force all repair operations
php artisan polling:repair --force
```

### Specific Repair Options
```bash
# Restart queue workers only
php artisan polling:repair --restart-workers

# Clear stuck jobs from queues only
php artisan polling:repair --clear-queues

# Restart polling system only
php artisan polling:repair --restart-polling

# Show detailed diagnostic information
php artisan polling:repair --detailed
```

## What It Checks

### System Components
1. **Queue Workers** - Checks if systemd services or processes are running
2. **Redis/Cache Connection** - Tests cache operations and queue connectivity
3. **Database Connection** - Verifies database connectivity and gateway data
4. **Gateway Configuration** - Validates gateway settings (IP, port, intervals)
5. **Polling Jobs** - Checks if polling system is running and jobs are scheduled
6. **System Locks** - Detects stuck locks that prevent polling
7. **Queue Status** - Checks for failed jobs and queue backlogs
8. **Polling Integrity** - Validates schedule consistency between gateways and jobs

### Diagnostic Results
- ✅ **Healthy** - Component is working correctly
- 📊 **Info** - Informational status (queue sizes, gateway counts, etc.)
- ⚠️ **Warning** - Non-critical issues that should be addressed
- ❌ **Error** - Critical issues that prevent polling from working

## What It Fixes

### Automatic Repairs
1. **Queue Worker Issues**
   - Restarts systemd services (Linux)
   - Provides restart instructions (Windows)

2. **Stuck Jobs and Queues**
   - Clears failed jobs from database
   - Removes stuck jobs from Redis queues
   - Cleans up stale worker entries

3. **System Locks**
   - Clears system-wide polling locks
   - Removes gateway-specific locks

4. **Polling System**
   - Stops and restarts the polling system
   - Ensures active gateways have polling scheduled

5. **System Cleanup**
   - Runs audit to clean orphaned data
   - Removes stale polling statuses
   - Clears inactive gateway polling

## Command Flow

### Phase 1: Diagnostics
- Runs comprehensive checks on all system components
- Identifies issues and categorizes them by severity
- Determines which repairs can be applied automatically

### Phase 2: Repairs
- Applies fixes based on detected issues and command options
- Provides platform-specific instructions where needed
- Logs all repair actions for review

### Phase 3: Validation
- Re-runs critical diagnostics to verify fixes worked
- Reports success or remaining issues
- Provides next steps if manual intervention is needed

## Exit Codes

- **0** - Success (no issues found or all issues fixed)
- **1** - Issues found or repairs failed

## Examples

### Healthy System
```bash
$ php artisan polling:repair --diagnose-only
🔧 Polling System Repair Tool
============================

📋 Phase 1: Running comprehensive diagnostics...

📋 Diagnostic Results:
======================
✅ Queue workers running (2 processes)
✅ Cache operations working
✅ Database connection successful
✅ All gateways have valid configuration
✅ Polling system is running
✅ All 3 active gateways are polling
✅ No blocking system locks found
✅ No gateway locks found
✅ No failed jobs found
✅ All polling schedules are consistent

✅ No issues found - system is healthy
```

### System with Issues
```bash
$ php artisan polling:repair
🔧 Polling System Repair Tool
============================

📋 Phase 1: Running comprehensive diagnostics...

📋 Diagnostic Results:
======================
✅ Cache operations working
✅ Database connection successful
✅ All gateways have valid configuration

⚠️  System polling lock exists (may indicate stuck process)
⚠️  Found 5 failed jobs

❌ No queue worker processes found
❌ Polling system is not running
❌ Active gateways found but no polling jobs running

🔧 Phase 2: Applying repairs...
🔄 Restarting queue workers...
🧹 Clearing stuck jobs from queues...
🔓 Clearing system locks...
🔄 Restarting polling system...
🔍 Running system audit and cleanup...
Applied 4 repair operations

🔍 Phase 3: Validating repairs...

📊 Final Summary:
=================
🔧 Repairs Applied:
  • queue_workers: Restarted systemd queue worker service
  • failed_jobs: Flushed all failed jobs
  • system_locks: Cleared 1 system locks
  • polling_system: Successfully restarted polling system
  • system_audit: Cleaned up 3 items during audit

✅ Polling system repair completed successfully!
   All critical issues have been resolved.
```

## Integration with Other Commands

The repair command builds upon and integrates with:
- `polling:diagnose` - Core diagnostic functionality
- `queue:fix` - Queue worker management
- `polling:fix-schedule` - Schedule consistency fixes
- `polling:reliable` - Core polling service operations

## Requirements Satisfied

This command satisfies the following requirements:

- **1.1** - Ensures polling works when enabled by restarting the polling system
- **1.4** - Validates that data appears in interfaces by checking polling integrity
- **3.1** - Maintains persistent workers by restarting queue worker services
- **3.2** - Automatically restarts failed workers through systemd service management

## Platform Support

- **Linux** - Full automation with systemd service management
- **Windows** - Diagnostic capabilities with manual restart instructions
- **Cross-platform** - Cache, database, and polling system repairs work on all platforms