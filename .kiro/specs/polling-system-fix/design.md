# Design Document

## Overview

The current polling system has a comprehensive architecture but is experiencing critical runtime issues where gateways show as "polling enabled" but no background jobs are actually running. The design focuses on diagnosing and fixing the disconnect between the polling configuration and actual job execution, ensuring reliable data collection and preventing duplicates.

## Architecture

### Current System Components

The existing system has these key components:
- **ReliablePollingService**: Core orchestration with Redis-based locking
- **PollGatewayJob**: Self-scheduling queue jobs for individual gateways
- **ReliablePollingCommand**: CLI management interface
- **PollingHealthController**: HTTP health check endpoints
- **Systemd Services**: Persistent queue workers and monitoring

### Root Cause Analysis

Based on the symptoms (polling enabled but no data collection), the likely issues are:

1. **Queue Worker Problems**: Systemd services may not be running or processing jobs
2. **Redis Connection Issues**: Cache/queue backend connectivity problems
3. **Job Dispatch Failures**: Jobs being created but not reaching the queue
4. **Database Connectivity**: Gateway status checks failing silently
5. **Lock Contention**: Redis locks preventing job execution

## Components and Interfaces

### 1. Diagnostic System

**Purpose**: Identify why polling jobs aren't running despite being enabled

**Components**:
- **SystemHealthChecker**: Validates all system dependencies
- **QueueDiagnostics**: Checks queue worker status and job processing
- **DatabaseValidator**: Verifies gateway and data point configurations
- **RedisValidator**: Tests cache and queue connectivity

**Interface**:
```php
interface DiagnosticInterface
{
    public function diagnose(): DiagnosticResult;
    public function getRecommendations(): array;
    public function canAutoFix(): bool;
    public function autoFix(): FixResult;
}
```

### 2. Queue System Repair

**Purpose**: Fix queue worker and job processing issues

**Components**:
- **QueueWorkerManager**: Start/stop/restart queue workers
- **JobQueueCleaner**: Clear failed/stuck jobs
- **SystemdServiceManager**: Manage persistent workers

**Key Operations**:
- Restart systemd queue workers
- Clear Redis job queues
- Validate job processing pipeline
- Test job dispatch and execution

### 3. Polling State Synchronizer

**Purpose**: Ensure gateway polling state matches actual job execution

**Components**:
- **GatewayStateValidator**: Check gateway polling configuration
- **JobScheduleValidator**: Verify scheduled jobs match enabled gateways
- **StateReconciler**: Fix mismatches between config and execution

**Reconciliation Logic**:
```php
foreach ($enabledGateways as $gateway) {
    $hasScheduledJob = $this->checkScheduledJob($gateway);
    $isWorkerRunning = $this->checkWorkerStatus();
    
    if (!$hasScheduledJob && $isWorkerRunning) {
        $this->schedulePollingJob($gateway);
    }
}
```

### 4. Duplicate Prevention Enhancement

**Purpose**: Strengthen existing duplicate prevention mechanisms

**Components**:
- **ReadingDeduplicator**: Database-level duplicate detection
- **TimestampValidator**: Ensure reading timestamps are unique per gateway
- **LockManager**: Enhanced Redis locking with better error handling

**Database Constraints**:
```sql
ALTER TABLE readings ADD CONSTRAINT unique_gateway_timestamp 
UNIQUE (gateway_id, read_at);
```

## Data Models

### Diagnostic Result Model
```php
class DiagnosticResult
{
    public string $component;
    public string $status; // 'healthy', 'warning', 'error'
    public string $message;
    public array $details;
    public array $recommendations;
    public bool $canAutoFix;
}
```

### System Health Model
```php
class SystemHealth
{
    public bool $queueWorkersRunning;
    public bool $redisConnected;
    public bool $databaseConnected;
    public int $enabledGateways;
    public int $activePollingJobs;
    public array $issues;
}
```

## Error Handling

### 1. Queue Worker Failures
- **Detection**: Check systemd service status
- **Recovery**: Restart services automatically
- **Logging**: Detailed failure reasons in system logs

### 2. Redis Connection Issues
- **Detection**: Test Redis ping and queue operations
- **Recovery**: Reconnect with exponential backoff
- **Fallback**: Use database-based job scheduling if Redis unavailable

### 3. Job Dispatch Failures
- **Detection**: Monitor job creation vs queue size
- **Recovery**: Clear stuck jobs and restart dispatch
- **Prevention**: Validate job payload before dispatch

### 4. Database Lock Contention
- **Detection**: Monitor query execution times
- **Recovery**: Implement proper transaction isolation
- **Prevention**: Use advisory locks for critical sections

## Testing Strategy

### 1. Integration Tests
- **Queue System**: Test job dispatch and processing end-to-end
- **Polling Flow**: Verify complete polling cycle from schedule to data storage
- **Error Recovery**: Test automatic recovery from various failure scenarios

### 2. System Tests
- **Service Management**: Test systemd service start/stop/restart
- **Health Checks**: Validate diagnostic endpoints return accurate status
- **Load Testing**: Ensure system handles multiple concurrent gateways

### 3. Diagnostic Tests
- **Component Validation**: Test each diagnostic component independently
- **Auto-Fix Verification**: Ensure auto-fix operations work correctly
- **State Reconciliation**: Test gateway state synchronization

## Implementation Approach

### Phase 1: Diagnostic Implementation
1. Create comprehensive system diagnostic tools
2. Implement health check improvements
3. Add detailed logging and monitoring

### Phase 2: Queue System Repair
1. Fix queue worker management
2. Implement job queue cleanup
3. Enhance systemd service reliability

### Phase 3: State Synchronization
1. Build gateway state validation
2. Implement automatic state reconciliation
3. Add polling job verification

### Phase 4: Duplicate Prevention
1. Add database constraints for uniqueness
2. Enhance Redis locking mechanisms
3. Implement reading deduplication

## Monitoring and Observability

### Health Check Endpoints
- `/api/polling/health` - Overall system health
- `/api/polling/diagnostics` - Detailed diagnostic results
- `/api/polling/queue-status` - Queue worker and job status

### Logging Strategy
- **System Events**: Service starts/stops, configuration changes
- **Polling Events**: Job dispatch, execution, completion
- **Error Events**: Failures, recoveries, diagnostic issues

### Metrics Collection
- Active polling jobs count
- Queue processing rate
- Error rates by component
- Gateway polling success rates

## Security Considerations

### Service Isolation
- Queue workers run with minimal privileges
- Redis access restricted to localhost
- Database connections use dedicated polling user

### Data Protection
- Sensitive gateway credentials encrypted at rest
- Polling data transmission over secure channels
- Audit trail for all system modifications

## Performance Optimization

### Queue Processing
- Dedicated Redis queues for polling jobs
- Configurable worker concurrency
- Job batching for efficiency

### Database Operations
- Optimized queries for gateway status checks
- Proper indexing on polling-related tables
- Connection pooling for high throughput

### Memory Management
- Automatic worker restarts to prevent memory leaks
- Efficient Redis key management
- Garbage collection for old diagnostic data