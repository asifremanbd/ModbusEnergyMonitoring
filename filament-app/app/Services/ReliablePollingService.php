<?php

namespace App\Services;

use App\Jobs\PollGatewayJob;
use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class ReliablePollingService
{
    private const POLLING_LOCK_PREFIX = 'gateway_polling_lock_';
    private const POLLING_STATUS_PREFIX = 'gateway_polling_status_';
    private const SYSTEM_LOCK_KEY = 'polling_system_lock';
    private const LOCK_TTL = 300; // 5 minutes
    
    /**
     * Start the reliable polling system for all active gateways.
     * This method ensures only one instance of the system runs at a time.
     */
    public function startReliablePolling(): bool
    {
        // Acquire system-wide lock to prevent multiple instances
        $systemLock = Cache::lock(self::SYSTEM_LOCK_KEY, self::LOCK_TTL);
        
        if (!$systemLock->get()) {
            Log::info('Polling system already running, skipping startup');
            return false;
        }
        
        try {
            Log::info('Starting reliable polling system');
            
            $activeGateways = Gateway::active()->get();
            $startedCount = 0;
            
            foreach ($activeGateways as $gateway) {
                if ($this->startGatewayPolling($gateway)) {
                    $startedCount++;
                }
            }
            
            Log::info("Reliable polling system started for {$startedCount}/{$activeGateways->count()} gateways");
            
            // Store system status
            Cache::put('polling_system_status', [
                'started_at' => now(),
                'active_gateways' => $startedCount,
                'total_gateways' => $activeGateways->count(),
            ], now()->addHours(24));
            
            return true;
            
        } finally {
            $systemLock->release();
        }
    }
    
    /**
     * Start polling for a specific gateway with duplicate prevention.
     */
    public function startGatewayPolling(Gateway $gateway): bool
    {
        if (!$gateway->is_active) {
            Log::debug("Gateway {$gateway->id} is inactive, skipping");
            return false;
        }
        
        $lockKey = self::POLLING_LOCK_PREFIX . $gateway->id;
        $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
        
        // Check if gateway is already being polled
        if (Cache::has($statusKey)) {
            $status = Cache::get($statusKey);
            $lastPoll = Carbon::parse($status['last_scheduled']);
            
            // If last poll was scheduled within the poll interval, skip
            if ($lastPoll->addSeconds($gateway->poll_interval)->isFuture()) {
                Log::debug("Gateway {$gateway->id} already has active polling");
                return false;
            }
        }
        
        // Acquire gateway-specific lock
        $lock = Cache::lock($lockKey, 60); // 1 minute lock
        
        if (!$lock->get()) {
            Log::debug("Could not acquire lock for gateway {$gateway->id}");
            return false;
        }
        
        try {
            // Double-check gateway is still active
            $gateway = $gateway->fresh();
            if (!$gateway || !$gateway->is_active) {
                Log::debug("Gateway {$gateway->id} became inactive during lock acquisition");
                return false;
            }
            
            // Dispatch the polling job
            PollGatewayJob::dispatch($gateway);
            
            // Update polling status
            Cache::put($statusKey, [
                'gateway_id' => $gateway->id,
                'last_scheduled' => now(),
                'poll_interval' => $gateway->poll_interval,
                'status' => 'scheduled'
            ], now()->addSeconds($gateway->poll_interval * 2));
            
            Log::info("Started polling for gateway {$gateway->id} ({$gateway->name})");
            return true;
            
        } finally {
            $lock->release();
        }
    }
    
    /**
     * Stop polling for a specific gateway.
     */
    public function stopGatewayPolling(Gateway $gateway): void
    {
        $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
        
        // Clear polling status
        Cache::forget($statusKey);
        
        Log::info("Stopped polling for gateway {$gateway->id} ({$gateway->name})");
    }
    
    /**
     * Stop all polling and clear system locks.
     */
    public function stopAllPolling(): void
    {
        Log::info('Stopping all gateway polling');
        
        $gateways = Gateway::all();
        
        foreach ($gateways as $gateway) {
            $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
            Cache::forget($statusKey);
        }
        
        // Clear system lock
        Cache::forget(self::SYSTEM_LOCK_KEY);
        Cache::forget('polling_system_status');
        
        Log::info('All polling stopped and locks cleared');
    }
    
    /**
     * Get comprehensive polling system status.
     */
    public function getSystemStatus(): array
    {
        $systemStatus = Cache::get('polling_system_status', []);
        $gateways = Gateway::all();
        
        $gatewayStatuses = [];
        $activePolling = 0;
        
        foreach ($gateways as $gateway) {
            $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
            $pollingStatus = Cache::get($statusKey);
            
            $status = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'is_active' => $gateway->is_active,
                'poll_interval' => $gateway->poll_interval,
                'is_polling' => false,
                'last_scheduled' => null,
                'next_poll_due' => null,
            ];
            
            if ($pollingStatus) {
                $lastScheduled = Carbon::parse($pollingStatus['last_scheduled']);
                $nextDue = $lastScheduled->addSeconds($gateway->poll_interval);
                
                $status['is_polling'] = true;
                $status['last_scheduled'] = $lastScheduled;
                $status['next_poll_due'] = $nextDue;
                
                if ($gateway->is_active) {
                    $activePolling++;
                }
            }
            
            $gatewayStatuses[] = $status;
        }
        
        return [
            'system' => $systemStatus,
            'summary' => [
                'total_gateways' => $gateways->count(),
                'active_gateways' => $gateways->where('is_active', true)->count(),
                'actively_polling' => $activePolling,
                'system_running' => !empty($systemStatus),
            ],
            'gateways' => $gatewayStatuses,
        ];
    }
    
    /**
     * Audit and cleanup any orphaned polling jobs or locks.
     */
    public function auditAndCleanup(): array
    {
        Log::info('Starting polling system audit and cleanup');
        
        $cleaned = [
            'orphaned_locks' => 0,
            'stale_statuses' => 0,
            'inactive_polling' => 0,
        ];
        
        $gateways = Gateway::all();
        
        foreach ($gateways as $gateway) {
            $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
            $lockKey = self::POLLING_LOCK_PREFIX . $gateway->id;
            
            $pollingStatus = Cache::get($statusKey);
            
            // Clean up polling status for inactive gateways
            if (!$gateway->is_active && $pollingStatus) {
                Cache::forget($statusKey);
                $cleaned['inactive_polling']++;
                Log::info("Cleaned polling status for inactive gateway {$gateway->id}");
            }
            
            // Clean up stale polling statuses
            if ($pollingStatus) {
                $lastScheduled = Carbon::parse($pollingStatus['last_scheduled']);
                $maxAge = $lastScheduled->addSeconds($gateway->poll_interval * 3);
                
                if ($maxAge->isPast()) {
                    Cache::forget($statusKey);
                    $cleaned['stale_statuses']++;
                    Log::info("Cleaned stale polling status for gateway {$gateway->id}");
                }
            }
            
            // Clean up orphaned locks (shouldn't happen but safety measure)
            if (Cache::has($lockKey)) {
                Cache::forget($lockKey);
                $cleaned['orphaned_locks']++;
                Log::info("Cleaned orphaned lock for gateway {$gateway->id}");
            }
        }
        
        Log::info('Polling system audit completed', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Validate that all active gateways have polling scheduled.
     */
    public function validatePollingIntegrity(): array
    {
        $issues = [];
        $activeGateways = Gateway::active()->get();
        
        foreach ($activeGateways as $gateway) {
            $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
            $pollingStatus = Cache::get($statusKey);
            
            if (!$pollingStatus) {
                $issues[] = [
                    'type' => 'missing_polling',
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'message' => 'Active gateway has no polling scheduled'
                ];
            } else {
                $lastScheduled = Carbon::parse($pollingStatus['last_scheduled']);
                $expectedNext = $lastScheduled->addSeconds($gateway->poll_interval);
                
                // Check if polling is overdue
                if ($expectedNext->isPast()) {
                    $overdue = now()->diffInSeconds($expectedNext);
                    $issues[] = [
                        'type' => 'overdue_polling',
                        'gateway_id' => $gateway->id,
                        'gateway_name' => $gateway->name,
                        'message' => "Polling is {$overdue} seconds overdue"
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Ensure all active gateways have polling jobs scheduled.
     * This fixes the disconnect between is_active flag and actual job scheduling.
     */
    public function ensureActiveGatewaysPolling(): array
    {
        Log::info('Ensuring all active gateways have polling scheduled');
        
        $results = [
            'checked' => 0,
            'started' => 0,
            'already_active' => 0,
            'failed' => 0,
            'stopped_inactive' => 0,
        ];
        
        $allGateways = Gateway::all();
        
        foreach ($allGateways as $gateway) {
            $results['checked']++;
            $statusKey = self::POLLING_STATUS_PREFIX . $gateway->id;
            $pollingStatus = Cache::get($statusKey);
            
            if ($gateway->is_active) {
                // Gateway should be polling
                if (!$pollingStatus) {
                    // Missing polling - start it
                    if ($this->startGatewayPolling($gateway)) {
                        $results['started']++;
                        Log::info("Started missing polling for active gateway {$gateway->id}");
                    } else {
                        $results['failed']++;
                        Log::warning("Failed to start polling for active gateway {$gateway->id}");
                    }
                } else {
                    // Check if polling is current
                    $lastScheduled = Carbon::parse($pollingStatus['last_scheduled']);
                    $expectedNext = $lastScheduled->addSeconds($gateway->poll_interval);
                    
                    if ($expectedNext->isPast()) {
                        // Polling is overdue - restart it
                        $this->stopGatewayPolling($gateway);
                        $gateway->update(['is_active' => true]); // Ensure still active
                        
                        if ($this->startGatewayPolling($gateway)) {
                            $results['started']++;
                            Log::info("Restarted overdue polling for gateway {$gateway->id}");
                        } else {
                            $results['failed']++;
                            Log::warning("Failed to restart polling for gateway {$gateway->id}");
                        }
                    } else {
                        $results['already_active']++;
                    }
                }
            } else {
                // Gateway should not be polling
                if ($pollingStatus) {
                    $this->stopGatewayPolling($gateway);
                    $results['stopped_inactive']++;
                    Log::info("Stopped polling for inactive gateway {$gateway->id}");
                }
            }
        }
        
        Log::info('Gateway polling synchronization completed', $results);
        
        return $results;
    }
    
    /**
     * Check queue worker health and restart if needed.
     */
    public function checkAndFixQueueWorkers(): array
    {
        $results = [
            'queue_workers_checked' => false,
            'queue_workers_restarted' => false,
            'stuck_jobs_cleared' => 0,
            'redis_connected' => false,
            'queue_driver' => config('queue.default'),
        ];
        
        try {
            $queueDriver = config('queue.default');
            
            if ($queueDriver === 'redis') {
                // Check Redis connection
                $pingResult = Redis::connection()->ping();
                $results['redis_connected'] = $pingResult === 'PONG' || $pingResult === true;
                
                if ($results['redis_connected']) {
                    // Check if queue workers are running
                    $workers = Redis::smembers('queues:workers');
                    $activeWorkers = 0;
                    
                    foreach ($workers as $worker) {
                        $workerData = Redis::get("queues:worker:{$worker}");
                        if ($workerData) {
                            $data = json_decode($workerData, true);
                            if (isset($data['started_at']) && (time() - $data['started_at']) < 300) {
                                $activeWorkers++;
                            }
                        }
                    }
                    
                    $results['queue_workers_checked'] = true;
                    
                    // If no active workers, try to restart systemd service
                    if ($activeWorkers === 0) {
                        $this->restartQueueWorkers();
                        $results['queue_workers_restarted'] = true;
                    }
                    
                    // Clear stuck jobs
                    $results['stuck_jobs_cleared'] = $this->clearStuckJobs();
                }
            } elseif ($queueDriver === 'database') {
                // For database queue, check differently
                $results['redis_connected'] = false; // Not using Redis
                $results['queue_workers_checked'] = true;
                
                // Check for stuck jobs in database
                $stuckJobs = \DB::table('jobs')
                    ->where('created_at', '<', now()->subHour())
                    ->count();
                    
                if ($stuckJobs > 0) {
                    \DB::table('jobs')
                        ->where('created_at', '<', now()->subHour())
                        ->delete();
                    $results['stuck_jobs_cleared'] = $stuckJobs;
                }
                
                // Check if workers are needed
                $pendingJobs = \DB::table('jobs')->count();
                if ($pendingJobs > 0) {
                    $runningWorkers = $this->getRunningWorkerCount();
                    if ($runningWorkers === 0) {
                        Log::info('No queue workers running but jobs are pending');
                        $results['queue_workers_restarted'] = false; // Can't auto-restart database workers
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Queue worker health check failed', ['error' => $e->getMessage()]);
        }
        
        return $results;
    }
    
    /**
     * Restart queue workers via systemd (Linux) or provide instructions (Windows).
     */
    private function restartQueueWorkers(): void
    {
        Log::info('Restarting queue workers');
        
        if (PHP_OS_FAMILY === 'Windows') {
            Log::info('Windows detected - queue workers need manual restart');
        } else {
            // Stop and start the systemd service
            shell_exec('sudo systemctl restart filament-queue-worker.service 2>/dev/null');
            
            // Give it time to start
            sleep(3);
        }
    }
    
    /**
     * Clear stuck jobs from Redis queues.
     */
    private function clearStuckJobs(): int
    {
        $clearedJobs = 0;
        $queues = ['polling', 'scheduling', 'default'];
        
        try {
            foreach ($queues as $queue) {
                // Clear processing queue of stuck jobs (older than 1 hour)
                $processingKey = "queues:{$queue}:processing";
                $jobs = Redis::lrange($processingKey, 0, -1);
                
                foreach ($jobs as $job) {
                    $jobData = json_decode($job, true);
                    if (isset($jobData['pushedAt'])) {
                        $pushedAt = $jobData['pushedAt'];
                        if (time() - $pushedAt > 3600) { // 1 hour old
                            Redis::lrem($processingKey, 1, $job);
                            $clearedJobs++;
                        }
                    }
                }
            }
            
            Log::info("Cleared {$clearedJobs} stuck jobs from queues");
            
        } catch (\Exception $e) {
            Log::error('Failed to clear stuck jobs', ['error' => $e->getMessage()]);
        }
        
        return $clearedJobs;
    }
    
    /**
     * Get count of running queue workers.
     */
    private function getRunningWorkerCount(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>nul');
            if ($processes) {
                return substr_count($processes, 'queue:work');
            }
        } else {
            $result = shell_exec("pgrep -f 'queue:work' | wc -l");
            return (int) trim($result);
        }
        
        return 0;
    }
    
    /**
     * Start polling for a specific gateway (alias for observer compatibility).
     */
    public function startPollingForGateway(Gateway $gateway): bool
    {
        return $this->startGatewayPolling($gateway);
    }
    
    /**
     * Stop polling for a specific gateway (alias for observer compatibility).
     */
    public function stopPollingForGateway(Gateway $gateway): void
    {
        $this->stopGatewayPolling($gateway);
    }
}