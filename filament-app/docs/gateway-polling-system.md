# Gateway Polling System

## Overview

The Gateway Polling System is a background job-based system that continuously polls Modbus-enabled Teltonika gateways to collect real-time data from configured data points. The system is built using Laravel's queue system with Redis as the backend for reliable and scalable polling operations.

## Architecture

### Components

1. **PollGatewayJob** - Individual gateway polling job
2. **ScheduleGatewayPollingJob** - Master scheduler for all gateways
3. **GatewayPollingService** - Service layer for managing polling operations
4. **Console Commands** - CLI interface for system management

### Queue Configuration

The system uses Redis queues with the following configuration:
- `polling` queue - For individual gateway polling jobs
- `scheduling` queue - For scheduling and coordination jobs
- `default` queue - For general application jobs

## Usage

### Starting the Polling System

```bash
# Start polling for all active gateways
php artisan gateway:start-polling

# Start polling for a specific gateway
php artisan gateway:start-polling --gateway=1
```

### Stopping the Polling System

```bash
# Stop polling for all gateways
php artisan gateway:stop-polling

# Stop polling for a specific gateway
php artisan gateway:stop-polling --gateway=1
```

### Checking System Status

```bash
# Basic status information
php artisan gateway:status

# Detailed gateway information
php artisan gateway:status --detailed
```

### Running Queue Workers

To process the polling jobs, you need to run queue workers:

```bash
# Start workers for all queues
php artisan queue:work

# Start workers for specific queues
php artisan queue:work --queue=polling,scheduling,default

# Start multiple workers for high throughput
php artisan queue:work --queue=polling --sleep=1 --tries=3
```

## Automatic Scheduling

The system includes automatic scheduling via Laravel's task scheduler. Add this to your cron tab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

This will automatically start the polling system every 5 minutes to ensure it's always running.

## Configuration

### Gateway Settings

Each gateway has the following polling-related settings:
- `poll_interval` - Seconds between polls (default: 10)
- `is_active` - Whether the gateway should be polled
- `success_count` - Number of successful polls
- `failure_count` - Number of failed polls

### Data Point Settings

Each data point has settings that affect polling:
- `is_enabled` - Whether the point should be included in polls
- `modbus_function` - Modbus function code (3 or 4)
- `register_address` - Starting register address
- `register_count` - Number of registers to read

## Error Handling

### Automatic Gateway Disabling

Gateways are automatically disabled if:
- Failure rate exceeds 80% with at least 10 total attempts
- This prevents continuous polling of problematic gateways

### Retry Logic

- Each job has 3 retry attempts
- 10-second backoff between retries
- Failed jobs are logged for analysis

### Quality Indicators

Readings include quality indicators:
- `good` - Successful read with valid data
- `bad` - Failed read or communication error
- `uncertain` - Partial success or data validation issues

## Monitoring

### Health Checks

The system provides health monitoring:
- Overall success rate tracking
- Individual gateway performance metrics
- Offline gateway detection
- High failure rate alerts

### Logging

All polling activities are logged with appropriate levels:
- INFO - Normal operations and statistics
- WARNING - Recoverable errors and performance issues
- ERROR - Critical failures requiring attention

## Performance Considerations

### Scaling

- Use multiple queue workers for high throughput
- Consider separate workers for different queue types
- Monitor Redis memory usage with many gateways

### Optimization

- Adjust poll intervals based on data requirements
- Disable unused data points to reduce load
- Use connection pooling for multiple gateways on same device

## Troubleshooting

### Common Issues

1. **Jobs not processing**
   - Check if queue workers are running
   - Verify Redis connection
   - Check queue configuration

2. **High failure rates**
   - Verify network connectivity to gateways
   - Check Modbus configuration (IP, port, unit ID)
   - Review register addresses and data types

3. **Memory issues**
   - Monitor queue worker memory usage
   - Restart workers periodically
   - Optimize job payload size

### Debugging Commands

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs from queue
php artisan queue:clear
```

## API Integration

The polling system integrates with the web interface through:
- Real-time dashboard updates
- Gateway management actions (start/stop/restart)
- Live data display with quality indicators
- System health monitoring