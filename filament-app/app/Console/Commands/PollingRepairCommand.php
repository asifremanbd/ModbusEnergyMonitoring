<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PollingRepairCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polling:repair 
                            {--diagnose-only : Only run diagnostics without applying fixes}
                            {--restart-workers : Restart queue workers}
                            {--clear-queues : Clear stuck jobs from queues}
                            {--restart-polling : Restart polling for all active gateways}
                            {--force : Force all repair operations}
                            {--detailed : Show detailed diagnostic information}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive polling system repair - diagnose and fix all polling issues';

    private array $diagnosticResults = [];
    private array $repairActions = [];
    private bool $hasErrors = false;
    private bool $hasWarnings = false;

    /**
     * Execute the console command.
     */
    public function handle(ReliablePollingService $pollingService): int
    {
        $this->info('🔧 Polling System Repair Tool');
        $this->line('============================');
        $this->line('');

        $diagnoseOnly = $this->option('diagnose-only');
        $force = $this->option('force');

        // Phase 1: Comprehensive Diagnostics
        $this->info('📋 Phase 1: Running comprehensive diagnostics...');
        $this->runComprehensiveDiagnostics($pollingService);

        // Display diagnostic results
        $this->displayDiagnosticResults();

        if ($diagnoseOnly) {
            $this->info('');
            $this->info('💡 Run without --diagnose-only to apply automatic repairs');
            return $this->hasErrors ? 1 : 0;
        }

        // Phase 2: Apply Repairs
        if ($this->hasErrors || $this->hasWarnings || $force) {
            $this->info('');
            $this->info('🔧 Phase 2: Applying repairs...');
            $this->applyRepairs($pollingService);
        } else {
            $this->info('');
            $this->info('✅ No issues found - system is healthy');
            return 0;
        }

        // Phase 3: Validation
        $this->info('');
        $this->info('🔍 Phase 3: Validating repairs...');
        $validationResult = $this->validateRepairs($pollingService);

        // Final summary
        $this->displayFinalSummary($validationResult);

        return $validationResult ? 0 : 1;
    }

    /**
     * Run comprehensive diagnostics on all system components.
     */
    private function runComprehensiveDiagnostics(ReliablePollingService $pollingService): void
    {
        $this->checkQueueWorkers();
        $this->checkRedisConnection();
        $this->checkDatabaseConnection();
        $this->checkGatewayConfiguration();
        $this->checkPollingJobs($pollingService);
        $this->checkSystemLocks();
        $this->checkQueueStatus();
        $this->checkPollingIntegrity($pollingService);
    }

    /**
     * Check if queue workers are running properly.
     */
    private function checkQueueWorkers(): void
    {
        try {
            $isWindows = PHP_OS_FAMILY === 'Windows';
            
            if ($isWindows) {
                $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>nul | findstr /C:"queue:work"');
                $processCount = $processes ? substr_count($processes, 'php.exe') : 0;
                
                if ($processCount > 0) {
                    $this->addDiagnostic('queue_workers', 'healthy', "✅ Queue workers running ({$processCount} processes)");
                } else {
                    $this->addDiagnostic('queue_workers', 'error', '❌ No queue worker processes found');
                    $this->hasErrors = true;
                }
            } else {
                $serviceStatus = shell_exec('systemctl is-active filament-queue-worker.service 2>/dev/null');
                $isActive = trim($serviceStatus) === 'active';

                if ($isActive) {
                    $processes = shell_exec('pgrep -f "queue:work" | wc -l');
                    $processCount = (int) trim($processes);

                    if ($processCount > 0) {
                        $this->addDiagnostic('queue_workers', 'healthy', "✅ Queue workers running ({$processCount} processes)");
                    } else {
                        $this->addDiagnostic('queue_workers', 'error', '❌ Systemd service active but no processes found');
                        $this->hasErrors = true;
                    }
                } else {
                    $this->addDiagnostic('queue_workers', 'error', '❌ Queue worker systemd service is not active');
                    $this->hasErrors = true;
                }
            }
        } catch (\Exception $e) {
            $this->addDiagnostic('queue_workers', 'error', '❌ Failed to check queue worker status: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check Redis connection and queue functionality.
     */
    private function checkRedisConnection(): void
    {
        try {
            $queueConnection = config('queue.default');
            $cacheDriver = config('cache.default');
            
            $this->addDiagnostic('queue_config', 'info', "📊 Queue: {$queueConnection}, Cache: {$cacheDriver}");
            
            // Test cache operations
            $testKey = 'polling_repair_test_' . time();
            Cache::put($testKey, 'test_value', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value === 'test_value') {
                $this->addDiagnostic('cache_operations', 'healthy', '✅ Cache operations working');
            } else {
                $this->addDiagnostic('cache_operations', 'error', '❌ Cache operations failed');
                $this->hasErrors = true;
            }

            // Check Redis-specific functionality if configured
            if ($queueConnection === 'redis' && class_exists('Redis') && extension_loaded('redis')) {
                $redis = Redis::connection();
                $pollingQueueSize = $redis->llen('queues:polling');
                $defaultQueueSize = $redis->llen('queues:default');
                
                $this->addDiagnostic('queue_sizes', 'info', "📊 Queue sizes - polling: {$pollingQueueSize}, default: {$defaultQueueSize}");

                if ($pollingQueueSize > 100) {
                    $this->addDiagnostic('queue_backlog', 'warning', "⚠️  Large polling queue backlog ({$pollingQueueSize} jobs)");
                    $this->hasWarnings = true;
                }
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('redis_connection', 'error', '❌ Cache/Redis check failed: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check database connection and gateway data.
     */
    private function checkDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
            $this->addDiagnostic('database_connection', 'healthy', '✅ Database connection successful');

            $gatewayCount = Gateway::count();
            $activeGatewayCount = Gateway::active()->count();
            
            $this->addDiagnostic('gateway_data', 'info', "📊 Gateways: {$gatewayCount} total, {$activeGatewayCount} active");

            if ($activeGatewayCount === 0) {
                $this->addDiagnostic('no_active_gateways', 'warning', '⚠️  No active gateways found');
                $this->hasWarnings = true;
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('database_connection', 'error', '❌ Database connection failed: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check gateway configuration validity.
     */
    private function checkGatewayConfiguration(): void
    {
        try {
            $gateways = Gateway::all();
            $configIssues = [];

            foreach ($gateways as $gateway) {
                $issues = [];

                if (empty($gateway->ip_address)) {
                    $issues[] = 'missing IP address';
                }
                if (empty($gateway->port)) {
                    $issues[] = 'missing port';
                }
                if (empty($gateway->poll_interval) || $gateway->poll_interval < 1) {
                    $issues[] = 'invalid poll interval';
                }

                if (!empty($issues)) {
                    $configIssues[] = "Gateway {$gateway->id} ({$gateway->name}): " . implode(', ', $issues);
                }
            }

            if (empty($configIssues)) {
                $this->addDiagnostic('gateway_config', 'healthy', '✅ All gateways have valid configuration');
            } else {
                $this->addDiagnostic('gateway_config', 'error', '❌ Gateway configuration issues found');
                foreach ($configIssues as $issue) {
                    $this->addDiagnostic('gateway_config_detail', 'error', "  • {$issue}");
                }
                $this->hasErrors = true;
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('gateway_config', 'error', '❌ Failed to check gateway configuration: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check polling jobs and system status.
     */
    private function checkPollingJobs(ReliablePollingService $pollingService): void
    {
        try {
            $status = $pollingService->getSystemStatus();
            $summary = $status['summary'];
            
            if ($summary['system_running']) {
                $this->addDiagnostic('polling_system', 'healthy', '✅ Polling system is running');
            } else {
                $this->addDiagnostic('polling_system', 'error', '❌ Polling system is not running');
                $this->hasErrors = true;
            }

            $activeGateways = $summary['active_gateways'];
            $activelyPolling = $summary['actively_polling'];

            if ($activeGateways > 0 && $activelyPolling === 0) {
                $this->addDiagnostic('polling_jobs', 'error', '❌ Active gateways found but no polling jobs running');
                $this->hasErrors = true;
            } elseif ($activelyPolling < $activeGateways) {
                $this->addDiagnostic('polling_jobs', 'warning', "⚠️  Only {$activelyPolling}/{$activeGateways} active gateways are polling");
                $this->hasWarnings = true;
            } else {
                $this->addDiagnostic('polling_jobs', 'healthy', "✅ All {$activelyPolling} active gateways are polling");
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('polling_jobs', 'error', '❌ Failed to check polling job status: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check system locks and cache status.
     */
    private function checkSystemLocks(): void
    {
        try {
            $systemLockExists = Cache::has('polling_system_lock');
            if ($systemLockExists) {
                $this->addDiagnostic('system_locks', 'warning', '⚠️  System polling lock exists (may indicate stuck process)');
                $this->hasWarnings = true;
            } else {
                $this->addDiagnostic('system_locks', 'healthy', '✅ No blocking system locks found');
            }

            $gateways = Gateway::active()->get();
            $lockedGateways = [];

            foreach ($gateways as $gateway) {
                $lockKey = 'gateway_polling_lock_' . $gateway->id;
                if (Cache::has($lockKey)) {
                    $lockedGateways[] = $gateway->id;
                }
            }

            if (!empty($lockedGateways)) {
                $this->addDiagnostic('gateway_locks', 'warning', '⚠️  Found locks on gateways: ' . implode(', ', $lockedGateways));
                $this->hasWarnings = true;
            } else {
                $this->addDiagnostic('gateway_locks', 'healthy', '✅ No gateway locks found');
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('system_locks', 'error', '❌ Failed to check system locks: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check queue status and failed jobs.
     */
    private function checkQueueStatus(): void
    {
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
            
            if ($failedJobsCount > 0) {
                $this->addDiagnostic('failed_jobs', 'warning', "⚠️  Found {$failedJobsCount} failed jobs");
                $this->hasWarnings = true;
            } else {
                $this->addDiagnostic('failed_jobs', 'healthy', '✅ No failed jobs found');
            }

            $queueConnection = config('queue.default');
            
            if ($queueConnection === 'sync') {
                $this->addDiagnostic('sync_queue_warning', 'warning', '⚠️  Using sync queue - polling jobs will run immediately and block');
                $this->hasWarnings = true;
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('queue_status', 'error', '❌ Failed to check queue status: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Check polling integrity and schedule consistency.
     */
    private function checkPollingIntegrity(ReliablePollingService $pollingService): void
    {
        try {
            $issues = $pollingService->validatePollingIntegrity();

            if (empty($issues)) {
                $this->addDiagnostic('polling_integrity', 'healthy', '✅ All polling schedules are consistent');
            } else {
                $this->addDiagnostic('polling_integrity', 'warning', "⚠️  Found " . count($issues) . " polling integrity issues");
                foreach ($issues as $issue) {
                    $this->addDiagnostic('polling_issue_detail', 'warning', "  • Gateway {$issue['gateway_id']}: {$issue['message']}");
                }
                $this->hasWarnings = true;
            }

        } catch (\Exception $e) {
            $this->addDiagnostic('polling_integrity', 'error', '❌ Failed to check polling integrity: ' . $e->getMessage());
            $this->hasErrors = true;
        }
    }

    /**
     * Apply all necessary repairs based on diagnostic results.
     */
    private function applyRepairs(ReliablePollingService $pollingService): void
    {
        $repairsApplied = 0;

        // Repair 1: Restart queue workers if needed
        if ($this->option('restart-workers') || $this->option('force') || $this->hasQueueWorkerIssues()) {
            if ($this->restartQueueWorkers()) {
                $repairsApplied++;
            }
        }

        // Repair 2: Clear stuck jobs and queues
        if ($this->option('clear-queues') || $this->option('force') || $this->hasQueueIssues()) {
            if ($this->clearStuckJobs()) {
                $repairsApplied++;
            }
        }

        // Repair 3: Clear system locks
        if ($this->hasSystemLockIssues()) {
            if ($this->clearSystemLocks()) {
                $repairsApplied++;
            }
        }

        // Repair 4: Restart polling system
        if ($this->option('restart-polling') || $this->option('force') || $this->hasPollingIssues()) {
            if ($this->restartPollingSystem($pollingService)) {
                $repairsApplied++;
            }
        }

        // Repair 5: Run system audit and cleanup
        if ($this->runSystemAudit($pollingService)) {
            $repairsApplied++;
        }

        $this->info("Applied {$repairsApplied} repair operations");
    }

    /**
     * Check if there are queue worker issues.
     */
    private function hasQueueWorkerIssues(): bool
    {
        return collect($this->diagnosticResults)
            ->where('component', 'queue_workers')
            ->where('status', 'error')
            ->isNotEmpty();
    }

    /**
     * Check if there are queue issues.
     */
    private function hasQueueIssues(): bool
    {
        return collect($this->diagnosticResults)
            ->whereIn('component', ['queue_backlog', 'failed_jobs'])
            ->whereIn('status', ['warning', 'error'])
            ->isNotEmpty();
    }

    /**
     * Check if there are system lock issues.
     */
    private function hasSystemLockIssues(): bool
    {
        return collect($this->diagnosticResults)
            ->whereIn('component', ['system_locks', 'gateway_locks'])
            ->where('status', 'warning')
            ->isNotEmpty();
    }

    /**
     * Check if there are polling issues.
     */
    private function hasPollingIssues(): bool
    {
        return collect($this->diagnosticResults)
            ->whereIn('component', ['polling_system', 'polling_jobs', 'polling_integrity'])
            ->whereIn('status', ['warning', 'error'])
            ->isNotEmpty();
    }

    /**
     * Restart queue workers.
     */
    private function restartQueueWorkers(): bool
    {
        $this->line('🔄 Restarting queue workers...');
        
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->warn('On Windows, please manually restart your queue workers:');
                $this->line('1. Stop any running "php artisan queue:work" processes');
                $this->line('2. Start new workers: php artisan queue:work --queue=polling,scheduling,default');
                $this->addRepairAction('queue_workers', 'Provided Windows restart instructions');
            } else {
                shell_exec('sudo systemctl stop filament-queue-worker.service 2>/dev/null');
                sleep(2);
                shell_exec('sudo systemctl start filament-queue-worker.service 2>/dev/null');
                sleep(3);
                shell_exec('sudo systemctl enable filament-queue-worker.service 2>/dev/null');
                
                $this->addRepairAction('queue_workers', 'Restarted systemd queue worker service');
            }
            
            return true;
        } catch (\Exception $e) {
            $this->addRepairAction('queue_workers', 'Failed to restart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear stuck jobs from queues.
     */
    private function clearStuckJobs(): bool
    {
        $this->line('🧹 Clearing stuck jobs from queues...');
        
        try {
            $clearedJobs = 0;
            
            // Clear failed jobs
            Artisan::call('queue:flush');
            $this->addRepairAction('failed_jobs', 'Flushed all failed jobs');
            
            // Clear Redis queues if available
            if (class_exists('Redis') && extension_loaded('redis')) {
                $queues = ['polling', 'scheduling', 'default'];
                
                foreach ($queues as $queue) {
                    $processingKey = "queues:{$queue}:processing";
                    $jobs = Redis::lrange($processingKey, 0, -1);
                    
                    foreach ($jobs as $job) {
                        $jobData = json_decode($job, true);
                        if (isset($jobData['pushedAt']) && (time() - $jobData['pushedAt']) > 3600) {
                            Redis::lrem($processingKey, 1, $job);
                            $clearedJobs++;
                        }
                    }
                }
                
                $this->addRepairAction('stuck_jobs', "Cleared {$clearedJobs} stuck jobs from Redis queues");
            }
            
            return true;
        } catch (\Exception $e) {
            $this->addRepairAction('stuck_jobs', 'Failed to clear: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear system locks.
     */
    private function clearSystemLocks(): bool
    {
        $this->line('🔓 Clearing system locks...');
        
        try {
            $clearedLocks = 0;
            
            // Clear system lock
            if (Cache::has('polling_system_lock')) {
                Cache::forget('polling_system_lock');
                $clearedLocks++;
            }
            
            // Clear gateway locks
            $gateways = Gateway::all();
            foreach ($gateways as $gateway) {
                $lockKey = 'gateway_polling_lock_' . $gateway->id;
                if (Cache::has($lockKey)) {
                    Cache::forget($lockKey);
                    $clearedLocks++;
                }
            }
            
            $this->addRepairAction('system_locks', "Cleared {$clearedLocks} system locks");
            return true;
        } catch (\Exception $e) {
            $this->addRepairAction('system_locks', 'Failed to clear: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart the polling system.
     */
    private function restartPollingSystem(ReliablePollingService $pollingService): bool
    {
        $this->line('🔄 Restarting polling system...');
        
        try {
            // Stop all polling first
            $pollingService->stopAllPolling();
            sleep(2);
            
            // Start reliable polling
            if ($pollingService->startReliablePolling()) {
                $this->addRepairAction('polling_system', 'Successfully restarted polling system');
                return true;
            } else {
                $this->addRepairAction('polling_system', 'Polling system was already running or could not be started');
                return false;
            }
        } catch (\Exception $e) {
            $this->addRepairAction('polling_system', 'Failed to restart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Run system audit and cleanup.
     */
    private function runSystemAudit(ReliablePollingService $pollingService): bool
    {
        $this->line('🔍 Running system audit and cleanup...');
        
        try {
            $auditResults = $pollingService->auditAndCleanup();
            $totalCleaned = array_sum($auditResults);
            
            $this->addRepairAction('system_audit', "Cleaned up {$totalCleaned} items during audit");
            return $totalCleaned > 0;
        } catch (\Exception $e) {
            $this->addRepairAction('system_audit', 'Failed to run audit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that repairs were successful.
     */
    private function validateRepairs(ReliablePollingService $pollingService): bool
    {
        sleep(5); // Give system time to stabilize
        
        // Re-run key diagnostics
        $this->checkQueueWorkers();
        $this->checkPollingJobs($pollingService);
        $this->checkSystemLocks();
        
        // Check if critical errors are resolved
        $criticalErrors = collect($this->diagnosticResults)
            ->where('status', 'error')
            ->whereIn('component', ['queue_workers', 'polling_system', 'database_connection'])
            ->count();
            
        return $criticalErrors === 0;
    }

    /**
     * Display diagnostic results.
     */
    private function displayDiagnosticResults(): void
    {
        $this->line('');
        $this->info('📋 Diagnostic Results:');
        $this->line('======================');

        $grouped = collect($this->diagnosticResults)->groupBy('status');

        foreach (['healthy', 'info', 'warning', 'error'] as $status) {
            if ($grouped->has($status)) {
                if ($status === 'warning') $this->line('');
                if ($status === 'error') $this->line('');
                
                foreach ($grouped[$status] as $result) {
                    switch ($status) {
                        case 'error':
                            $this->error($result['message']);
                            break;
                        case 'warning':
                            $this->warn($result['message']);
                            break;
                        default:
                            $this->line($result['message']);
                    }
                }
            }
        }
    }

    /**
     * Display final summary.
     */
    private function displayFinalSummary(bool $validationResult): void
    {
        $this->line('');
        $this->info('📊 Final Summary:');
        $this->line('=================');

        if (!empty($this->repairActions)) {
            $this->info('🔧 Repairs Applied:');
            foreach ($this->repairActions as $action) {
                $this->line("  • {$action['component']}: {$action['message']}");
            }
            $this->line('');
        }

        if ($validationResult) {
            $this->info('✅ Polling system repair completed successfully!');
            $this->info('   All critical issues have been resolved.');
        } else {
            $this->error('❌ Some issues remain after repair attempt.');
            $this->error('   Manual intervention may be required.');
            $this->line('');
            $this->info('💡 Next steps:');
            $this->line('  1. Check system logs for detailed error information');
            $this->line('  2. Verify queue worker configuration');
            $this->line('  3. Ensure Redis/cache services are running');
            $this->line('  4. Run: php artisan polling:diagnose --detailed');
        }
    }

    /**
     * Add a diagnostic result.
     */
    private function addDiagnostic(string $component, string $status, string $message): void
    {
        $this->diagnosticResults[] = [
            'component' => $component,
            'status' => $status,
            'message' => $message,
            'timestamp' => now(),
        ];
    }

    /**
     * Add a repair action.
     */
    private function addRepairAction(string $component, string $message): void
    {
        $this->repairActions[] = [
            'component' => $component,
            'message' => $message,
            'timestamp' => now(),
        ];
    }
}