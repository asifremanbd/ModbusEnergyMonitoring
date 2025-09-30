# Polling System Troubleshooting Guide

## 🚨 Quick Fix for 504 Timeouts and Polling Issues

Your polling system is stopping after 5-10 minutes and showing 504 Gateway Timeout errors. This is typically caused by:

1. **Nginx timeout settings too low**
2. **PHP-FPM timeout restrictions**
3. **Resource exhaustion (memory/CPU)**
4. **Queue worker crashes**
5. **Network connectivity issues**

## 🔧 Immediate Actions

### Step 1: Run Diagnostics
```bash
# Make scripts executable
chmod +x diagnose-polling-issues.sh fix-polling-timeouts.sh

# Run diagnostics to identify issues
sudo ./diagnose-polling-issues.sh
```

### Step 2: Apply Comprehensive Fix
```bash
# Run the comprehensive fix script
sudo ./fix-polling-timeouts.sh
```

### Step 3: Monitor Results
```bash
# Wait 5 minutes, then check status
cd filament-app
php artisan polling:reliable status

# Monitor queue worker logs
journalctl -u filament-queue-worker -f
```

## 🔍 Manual Troubleshooting Steps

### Check Service Status
```bash
# Check all critical services
systemctl status nginx php8.1-fpm redis mysql filament-queue-worker

# Restart any failed services
sudo systemctl restart [service-name]
```

### Check System Resources
```bash
# Memory usage
free -h

# Disk usage
df -h

# CPU load
htop
```

### Check Queue Status
```bash
# Redis queue lengths
redis-cli llen "queues:polling"
redis-cli llen "queues:default"
redis-cli llen "queues:failed"

# Clear stuck queues if needed
php artisan queue:clear redis
```

### Check Polling System
```bash
cd /var/www/filament-app

# Stop polling
php artisan polling:reliable stop

# Run system audit
php artisan polling:reliable audit

# Restart polling
php artisan polling:reliable start

# Check status
php artisan polling:reliable status --detailed
```

## 🛠️ Configuration Changes Made

### Nginx Timeouts (nginx-config.conf)
- `fastcgi_read_timeout: 300s` (was default 60s)
- `fastcgi_send_timeout: 300s`
- `proxy_read_timeout: 300s`
- `client_body_timeout: 300s`

### PHP-FPM Settings
- `max_execution_time: 300` (was 30s)
- `memory_limit: 512M`
- `request_terminate_timeout: 300`

### Queue Worker Optimization
- `--timeout=300` (job timeout)
- `--memory=256` (memory limit per worker)
- `--sleep=5` (reduced CPU usage)
- `--max-time=1800` (worker restart interval)

## 📊 Monitoring Commands

### Real-time Monitoring
```bash
# Watch queue worker activity
journalctl -u filament-queue-worker -f

# Monitor polling status
watch -n 30 'php artisan polling:reliable status'

# Check system resources
watch -n 5 'free -h && df -h /'
```

### Health Check Script
```bash
# Run comprehensive health check
/usr/local/bin/check-polling-health.sh
```

## 🚨 Emergency Procedures

### Complete System Reset
```bash
# Stop all services
sudo systemctl stop filament-queue-worker filament-polling-monitor.timer

# Clear all queues and cache
cd /var/www/filament-app
php artisan queue:clear redis
php artisan cache:clear
redis-cli flushdb

# Restart services
sudo systemctl start filament-queue-worker filament-polling-monitor.timer

# Restart polling
php artisan polling:reliable start
```

### If Polling Still Fails
```bash
# Check gateway connectivity
ping [gateway-ip]

# Test Modbus connection manually
# (Use your specific gateway testing method)

# Reduce polling frequency temporarily
# Edit gateway settings in the admin panel to increase intervals

# Check for network issues
netstat -tuln | grep :502  # Modbus port
```

## 🔧 Common Issues and Solutions

### Issue: Queue Worker Keeps Crashing
**Solution:**
```bash
# Check memory usage
ps aux | grep "queue:work"

# Restart with lower memory limit
sudo systemctl edit filament-queue-worker
# Add: Environment=MEMORY_LIMIT=128
```

### Issue: Redis Connection Errors
**Solution:**
```bash
# Check Redis status
redis-cli ping

# Restart Redis
sudo systemctl restart redis

# Check Redis memory
redis-cli info memory
```

### Issue: High CPU Usage
**Solution:**
```bash
# Increase sleep time in queue worker
# Edit /etc/systemd/system/filament-queue-worker.service
# Change --sleep=5 to --sleep=10

sudo systemctl daemon-reload
sudo systemctl restart filament-queue-worker
```

### Issue: Gateways Not Responding
**Solution:**
```bash
# Check network connectivity
ping [gateway-ip]

# Check Modbus port
telnet [gateway-ip] 502

# Verify gateway configuration in admin panel
# Increase timeout values for problematic gateways
```

## 📈 Performance Optimization

### For High-Traffic Systems
1. **Increase PHP-FPM workers:**
   ```bash
   # Edit /etc/php/8.1/fpm/pool.d/www.conf
   pm.max_children = 30
   pm.start_servers = 6
   pm.min_spare_servers = 4
   pm.max_spare_servers = 10
   ```

2. **Optimize Redis:**
   ```bash
   # Edit /etc/redis/redis.conf
   maxmemory 1gb
   maxmemory-policy allkeys-lru
   ```

3. **Add multiple queue workers:**
   ```bash
   # Create additional worker services
   cp /etc/systemd/system/filament-queue-worker.service /etc/systemd/system/filament-queue-worker-2.service
   systemctl enable filament-queue-worker-2
   systemctl start filament-queue-worker-2
   ```

## 📞 Support Checklist

When reporting issues, include:
- [ ] Output of `diagnose-polling-issues.sh`
- [ ] Recent logs: `journalctl -u filament-queue-worker --since "1 hour ago"`
- [ ] System resources: `free -h && df -h`
- [ ] Polling status: `php artisan polling:reliable status --detailed`
- [ ] Gateway connectivity test results
- [ ] Any error messages from the web interface

## 🎯 Expected Results

After applying the fixes:
- ✅ No more 504 Gateway Timeout errors
- ✅ Polling continues reliably for hours/days
- ✅ Queue workers remain stable
- ✅ System resources within normal limits
- ✅ All gateways polling at configured intervals

**Wait 10-15 minutes after applying fixes before testing to allow all services to stabilize.**