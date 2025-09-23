# Reliable Gateway Polling System

This document describes the setup and operation of the reliable gateway polling system that ensures each Modbus gateway is polled exactly once per configured interval without duplicates or missed polls.

## 🎯 System Overview

The reliable polling system consists of:

1. **ReliablePollingService** - Core service that manages polling with duplicate prevention
2. **Enhanced PollGatewayJob** - Laravel queue job that handles individual gateway polling
3. **Persistent Queue Workers** - Systemd services that process jobs reliably
4. **Monitoring & Validation** - Automated health checks and integrity validation

## 🔧 Key Features

- **Duplicate Prevention**: Uses Redis locks to ensure only one polling job per gateway
- **Precise Timing**: Each gateway polls exactly at its configured interval
- **Persistent Workers**: Systemd services ensure workers restart automatically
- **Health Monitoring**: Automatic validation and cleanup of orphaned processes
- **Graceful Failure Handling**: Failed gateways are disabled automatically after threshold
- **Comprehensive Logging**: Full audit trail of all polling activities

## 📋 Installation Steps

### 1. Prerequisites

Ensure these services are running:
```bash
# Check required services
systemctl status redis
systemctl status mysql
systemctl status nginx
```

### 2. Audit Existing System

First, check for any duplicate polling processes:
```bash
sudo ./audit-and-cleanup-polling.sh
```

This will identify and help remove:
- Manual queue workers
- Cron job entries
- Background scripts
- Failed job queues
- Orphaned processes

### 3. Setup Reliable Polling

Run the setup script:
```bash
sudo ./setup-reliable-polling.sh
```

This will:
- Install systemd service files
- Start persistent queue workers
- Configure monitoring timer
- Initialize the polling system

### 4. Verify Installation

Check system status:
```bash
cd /var/www/filament-app
php artisan polling:reliable status --detailed
```

## 🎮 Management Commands

### Start/Stop Polling

```bash
# Start all gateway polling
php artisan polling:reliable start

# Start specific gateway
php artisan polling:reliable start --gateway=1

# Stop all polling
php artisan polling:reliable stop

# Stop specific gateway
php artisan polling:reliable stop --gateway=1
```

### Monitor System

```bash
# Show system status
php artisan polling:reliable status

# Show detailed gateway information
php artisan polling:reliable status --detailed

# Validate polling integrity
php artisan polling:reliable validate

# Run system audit and cleanup
php artisan polling:reliable audit
```

### Service Management

```bash
# Check service status
systemctl status filament-queue-worker
systemctl status filament-polling-monitor.timer

# Restart services
systemctl restart filament-queue-worker
systemctl restart filament-polling-monitor.timer

# View logs
journalctl -u filament-queue-worker -f
journalctl -u filament-polling-monitor.timer -f
```

## 🔍 System Architecture

### Queue Structure

The system uses Redis queues with priority:
1. **polling** - Gateway polling jobs (highest priority)
2. **scheduling** - System scheduling jobs
3. **default** - Other application jobs

### Polling Flow

1. **Initialization**: `ReliablePollingService` starts polling for all active gateways
2. **Job Dispatch**: Each gateway gets a `PollGatewayJob` scheduled at precise intervals
3. **Duplicate Prevention**: Redis locks prevent multiple jobs for same gateway
4. **Execution**: Job polls gateway via `ModbusPollService`
5. **Self-Scheduling**: Successful jobs schedule the next poll automatically
6. **Health Monitoring**: Failed jobs increment failure counters and may disable gateways

### Lock Mechanism

- **System Lock**: Prevents multiple polling system instances
- **Gateway Locks**: Prevents duplicate jobs per gateway
- **Status Tracking**: Redis cache tracks polling state and timing

## 📊 Monitoring & Alerts

### Automated Checks

The system runs automated checks every 5-10 minutes:
- **Integrity Validation**: Ensures all active gateways have polling scheduled
- **Audit & Cleanup**: Removes orphaned locks and stale statuses
- **Health Monitoring**: Tracks success rates and identifies issues

### Key Metrics

Monitor these metrics for system health:
- **Active Polling**: Number of gateways currently being polled
- **Success Rate**: Percentage of successful polls per gateway
- **Queue Length**: Number of pending jobs in each queue
- **Worker Status**: Health of persistent queue workers

### Log Locations

- **Application Logs**: `/var/www/filament-app/storage/logs/laravel.log`
- **Queue Worker Logs**: `journalctl -u filament-queue-worker`
- **System Logs**: `journalctl -u filament-polling-monitor`

## 🚨 Troubleshooting

### Common Issues

#### Queue Workers Not Processing Jobs
```bash
# Check worker status
systemctl status filament-queue-worker

# Restart worker
systemctl restart filament-queue-worker

# Check Redis connection
redis-cli ping
```

#### Gateways Not Polling
```bash
# Check gateway status
php artisan polling:reliable status --detailed

# Validate system integrity
php artisan polling:reliable validate

# Restart specific gateway
php artisan polling:reliable stop --gateway=1
php artisan polling:reliable start --gateway=1
```

#### High Memory Usage
```bash
# Check queue worker memory
ps aux | grep "queue:work"

# Restart worker to clear memory
systemctl restart filament-queue-worker
```

#### Duplicate Readings
```bash
# Run full system audit
php artisan polling:reliable audit

# Check for duplicate processes
sudo ./audit-and-cleanup-polling.sh
```

### Emergency Procedures

#### Stop All Polling Immediately
```bash
# Stop all services
systemctl stop filament-queue-worker
systemctl stop filament-polling-monitor.timer

# Clear all polling
php artisan polling:reliable stop

# Clear Redis queues
redis-cli flushdb
```

#### Reset System Completely
```bash
# Stop services
systemctl stop filament-queue-worker filament-polling-monitor.timer

# Clear application cache
php artisan cache:clear
php artisan queue:clear

# Clear Redis
redis-cli flushdb

# Restart services
systemctl start filament-queue-worker filament-polling-monitor.timer

# Reinitialize polling
php artisan polling:reliable start
```

## 🔒 Security Considerations

- Queue workers run as `www-data` user with minimal privileges
- Service files use security hardening (NoNewPrivileges, PrivateTmp, etc.)
- Redis access is limited to localhost
- All file permissions follow least-privilege principle

## 📈 Performance Tuning

### Queue Worker Configuration

Adjust worker parameters in `/etc/systemd/system/filament-queue-worker.service`:
- `--sleep=3`: Seconds to wait when no jobs available
- `--tries=3`: Maximum retry attempts per job
- `--max-time=3600`: Maximum worker runtime before restart
- `--memory=512`: Memory limit in MB

### Redis Configuration

Optimize Redis for queue performance:
```bash
# In /etc/redis/redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
```

### Gateway Intervals

Balance polling frequency with system load:
- **High Priority**: 10-30 seconds for critical gateways
- **Normal**: 60-300 seconds for regular monitoring
- **Low Priority**: 300+ seconds for status-only gateways

## 📝 Maintenance

### Daily Tasks
- Check system status: `php artisan polling:reliable status`
- Review logs for errors: `journalctl -u filament-queue-worker --since "1 day ago"`

### Weekly Tasks
- Run full audit: `php artisan polling:reliable audit`
- Check Redis memory usage: `redis-cli info memory`
- Review gateway success rates

### Monthly Tasks
- Update system packages
- Review and optimize gateway polling intervals
- Archive old log files
- Test disaster recovery procedures

## 🆘 Support

For issues with the reliable polling system:

1. Check this documentation first
2. Run diagnostic commands: `php artisan polling:reliable validate`
3. Review logs: `journalctl -u filament-queue-worker -f`
4. Contact system administrator with specific error messages

Remember: The system is designed to be self-healing, but manual intervention may be needed for configuration issues or hardware failures.