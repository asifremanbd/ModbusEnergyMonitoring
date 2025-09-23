<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class PollingDiagnosticCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polling:diagnose 
                            {--fix : Attempt to automatically fix detected issues}
                            {--detailed : Show detailed diagnostic information}';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose why polling system is not working and provide fix recommendations';

    private array $diagnosticResults = [];
    private array $fixRecommendations = [];
    private bool $canAutoFix = false;

    /**
     * Execute the console command.
     */
    public function handle(ReliablePollingService $pollingService): int
    {
        $this->info('🔍 Running Polling System Diagnostics...');
        $this->line('');

        // Run all diagnostic checks
        $this->checkQueueWorkers();
        $this->checkRedisConnection();
        $this->checkDatabaseConnection();
        $this->checkGatewayConfiguration();
        $this->checkPollingJobs($pollingService);
        $this->checkSystemLocks();
        $this->checkQueueStatus();

        // Display results
        $this->displayResults();

        // Auto-fix if requested and possible
        if ($this->option('fix') && $this->canAutoFix) {
            return $this->attemptAutoFix($pollingService);
        }

        // Return appropriate exit code
        $hasErrors = collect($this->diagnosticResults)->contains('status', 'error');
        return $hasErrors ? 1 : 0;
    }

    /**
     * Check if queue workers (systemd services) are running.
     */
    private function checkQueueWorkers(): void
    {
        $this->info('Checking queue workers...');

        try {
            // Detect operating system
            $isWindows = PHP_OS_FAMILY === 'Windows';
            
            if ($isWindows) {
                // On Windows, check for running PHP processes with queue:work
                $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>nul | findstr /C:"queue:work"');
                $processCount = $processes ? substr_count($processes, 'php.exe') : 0;
                
                if ($processCount > 0) {
                    $this->addResult('queue_workers', 'healthy', "✅ Queue workers running ({$processCount} processes)");
                } else {
                    $this->addResult('queue_workers', 'warning', '⚠️  No queue worker processes found');
                    $this->addRecommendation('Start queue workers manually: php artisan queue:work --daemon');
                    $this->addRecommendation('Or run in background: start /B php artisan queue:work');
                }
            } else {
                // On Linux, check systemd service
                $serviceStatus = shell_exec('systemctl is-active filament-queue-worker.service 2>/dev/null');
                $isActive = trim($serviceStatus) === 'active';

                if ($isActive) {
                    // Check if processes are actually running
                    $processes = shell_exec('pgrep -f "queue:work" | wc -l');
                    $processCount = (int) trim($processes);

                    if ($processCount > 0) {
                        $this->addResult('queue_workers', 'healthy', "✅ Queue workers running ({$processCount} processes)");
                    } else {
                        $this->addResult('queue_workers', 'error', '❌ Systemd service active but no queue worker processes found');
                        $this->addRecommendation('Restart the queue worker service: sudo systemctl restart filament-queue-worker.service');
                    }
                } else {
                    $this->addResult('queue_workers', 'error', '❌ Queue worker systemd service is not active');
                    $this->addRecommendation('Start the queue worker service: sudo systemctl start filament-queue-worker.service');
                    $this->addRecommendation('Enable auto-start: sudo systemctl enable filament-queue-worker.service');
                }
            }
        } catch (\Exception $e) {
            $this->addResult('queue_workers', 'error', '❌ Failed to check queue worker status: ' . $e->getMessage());
            $this->addRecommendation('Check if you have proper permissions to check running processes');
        }
    }

    /**
     * Check Redis connection and queue functionality.
     */
    private function checkRedisConnection(): void
    {
        $this->info('Checking Redis connection...');

        try {
            // Check if Redis is configured for queues
            $queueConnection = config('queue.default');
            $cacheDriver = config('cache.default');
            
            $this->addResult('queue_config', 'info', "📊 Queue connection: {$queueConnection}, Cache driver: {$cacheDriver}");
            
            // Check if Redis extension is available when Redis is configured
            if ($queueConnection === 'redis' && (!class_exists('Redis') || !extension_loaded('redis'))) {
                $this->addResult('redis_config', 'error', '❌ Queue configured for Redis but Redis extension not available');
                $this->addRecommendation('Install Redis PHP extension or change QUEUE_CONNECTION to "database" in .env');
                $this->addRecommendation('For development, you can use: QUEUE_CONNECTION=sync');
                
                // Still try to test cache functionality
                $this->testCacheOperations();
                return;
            }
            
            if ($queueConnection !== 'redis') {
                $this->addResult('redis_config', 'info', "📊 Queue connection is '{$queueConnection}' (not Redis)");
                
                // Still try to test cache functionality
                $this->testCacheOperations();
                return;
            }

            // Test cache operations first (safer)
            $this->testCacheOperations();

            // Try to get queue sizes using Redis facade if available
            if (class_exists('Redis') && extension_loaded('redis')) {
                try {
                    $redis = Redis::connection();
                    $pollingQueueSize = $redis->llen('queues:polling');
                    $defaultQueueSize = $redis->llen('queues:default');
                    
                    $this->addResult('queue_sizes', 'info', "📊 Queue sizes - polling: {$pollingQueueSize}, default: {$defaultQueueSize}");

                    if ($pollingQueueSize > 100) {
                        $this->addResult('queue_backlog', 'warning', "⚠️  Large polling queue backlog ({$pollingQueueSize} jobs)");
                        $this->addRecommendation('Consider clearing stuck jobs or increasing worker capacity');
                    }
                } catch (\Exception $e) {
                    $this->addResult('queue_sizes', 'warning', '⚠️  Could not check queue sizes: ' . $e->getMessage());
                }
            } else {
                $this->addResult('redis_extension', 'warning', '⚠️  Redis PHP extension not available');
                $this->addRecommendation('Install Redis PHP extension for better queue monitoring');
            }

        } catch (\Exception $e) {
            $this->addResult('redis_connection', 'error', '❌ Redis/Cache check failed: ' . $e->getMessage());
            $this->addRecommendation('Check cache and queue configuration in .env file');
        }
    }

    /**
     * Test basic cache operations.
     */
    private function testCacheOperations(): void
    {
        try {
            $testKey = 'polling_diagnostic_test_' . time();
            Cache::put($testKey, 'test_value', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value === 'test_value') {
                $this->addResult('cache_operations', 'healthy', '✅ Cache read/write operations working');
            } else {
                $this->addResult('cache_operations', 'error', '❌ Cache read/write operations failed');
                $this->addRecommendation('Check cache configuration and permissions');
            }
        } catch (\Exception $e) {
            $this->addResult('cache_operations', 'error', '❌ Cache operations failed: ' . $e->getMessage());
            $this->addRecommendation('Check cache driver configuration in .env file');
        }
    }

    /**
     * Check database connection and gateway table.
     */
    private function checkDatabaseConnection(): void
    {
        $this->info('Checking database connection...');

        try {
            // Test database connection
            DB::connection()->getPdo();
            $this->addResult('database_connection', 'healthy', '✅ Database connection successful');

            // Check gateways table
            $gatewayCount = Gateway::count();
            $activeGatewayCount = Gateway::active()->count();
            
            $this->addResult('gateway_data', 'info', "📊 Gateways: {$gatewayCount} total, {$activeGatewayCount} active");

            if ($activeGatewayCount === 0) {
                $this->addResult('no_active_gateways', 'warning', '⚠️  No active gateways found');
                $this->addRecommendation('Enable at least one gateway for polling to work');
            }

        } catch (\Exception $e) {
            $this->addResult('database_connection', 'error', '❌ Database connection failed: ' . $e->getMessage());
            $this->addRecommendation('Check database server status and configuration in .env file');
        }
    }

    /**
     * Check gateway configuration and polling settings.
     */
    private function checkGatewayConfiguration(): void
    {
        $this->info('Checking gateway configuration...');

        try {
            $gateways = Gateway::all();
            $configIssues = [];

            foreach ($gateways as $gateway) {
                $issues = [];

                // Check required fields
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
                $this->addResult('gateway_config', 'healthy', '✅ All gateways have valid configuration');
            } else {
                $this->addResult('gateway_config', 'error', '❌ Gateway configuration issues found');
                foreach ($configIssues as $issue) {
                    $this->addResult('gateway_config_detail', 'error', "  • {$issue}");
                }
                $this->addRecommendation('Fix gateway configuration issues in the admin interface');
            }

        } catch (\Exception $e) {
            $this->addResult('gateway_config', 'error', '❌ Failed to check gateway configuration: ' . $e->getMessage());
        }
    }

    /**
     * Check polling jobs and their execution status.
     */
    private function checkPollingJobs(ReliablePollingService $pollingService): void
    {
        $this->info('Checking polling job status...');

        try {
            $status = $pollingService->getSystemStatus();
            $issues = $pollingService->validatePollingIntegrity();

            $summary = $status['summary'];
            
            if ($summary['system_running']) {
                $this->addResult('polling_system', 'healthy', '✅ Polling system is marked as running');
            } else {
                $this->addResult('polling_system', 'error', '❌ Polling system is not running');
                $this->addRecommendation('Start the polling system: php artisan polling:reliable start');
                $this->canAutoFix = true;
            }

            // Check if active gateways have polling jobs
            $activeGateways = $summary['active_gateways'];
            $activelyPolling = $summary['actively_polling'];

            if ($activeGateways > 0 && $activelyPolling === 0) {
                $this->addResult('polling_jobs', 'error', '❌ Active gateways found but no polling jobs running');
                $this->addRecommendation('Start polling for active gateways: php artisan polling:reliable start');
                $this->canAutoFix = true;
            } elseif ($activelyPolling < $activeGateways) {
                $this->addResult('polling_jobs', 'warning', "⚠️  Only {$activelyPolling}/{$activeGateways} active gateways are polling");
                $this->addRecommendation('Check individual gateway polling status and restart if needed');
            } else {
                $this->addResult('polling_jobs', 'healthy', "✅ All {$activelyPolling} active gateways are polling");
            }

            // Report specific issues
            if (!empty($issues)) {
                $this->addResult('polling_integrity', 'warning', "⚠️  Found " . count($issues) . " polling integrity issues");
                foreach ($issues as $issue) {
                    $this->addResult('polling_issue_detail', 'warning', "  • Gateway {$issue['gateway_id']}: {$issue['message']}");
                }
                $this->addRecommendation('Run polling system audit: php artisan polling:reliable audit');
            }

        } catch (\Exception $e) {
            $this->addResult('polling_jobs', 'error', '❌ Failed to check polling job status: ' . $e->getMessage());
            
            // If it's a Redis-related error, provide specific guidance
            if (strpos($e->getMessage(), 'Redis') !== false || strpos($e->getMessage(), 'redis') !== false) {
                $this->addResult('polling_redis_error', 'error', '❌ Polling system requires Redis but Redis is not available');
                $this->addRecommendation('Install Redis PHP extension or configure polling to use database cache');
                $this->addRecommendation('Temporarily change CACHE_DRIVER to "database" in .env for testing');
            }
        }
    }

    /**
     * Check system locks and cache status.
     */
    private function checkSystemLocks(): void
    {
        $this->info('Checking system locks and cache...');

        try {
            // Check for system-wide polling lock
            $systemLockExists = Cache::has('polling_system_lock');
            if ($systemLockExists) {
                $this->addResult('system_locks', 'warning', '⚠️  System polling lock exists (may indicate stuck process)');
                $this->addRecommendation('Clear system lock if polling is stuck: php artisan cache:forget polling_system_lock');
            } else {
                $this->addResult('system_locks', 'healthy', '✅ No blocking system locks found');
            }

            // Check for gateway-specific locks
            $gateways = Gateway::active()->get();
            $lockedGateways = [];

            foreach ($gateways as $gateway) {
                $lockKey = 'gateway_polling_lock_' . $gateway->id;
                if (Cache::has($lockKey)) {
                    $lockedGateways[] = $gateway->id;
                }
            }

            if (!empty($lockedGateways)) {
                $this->addResult('gateway_locks', 'warning', '⚠️  Found locks on gateways: ' . implode(', ', $lockedGateways));
                $this->addRecommendation('Clear gateway locks if polling is stuck: php artisan polling:reliable audit');
            } else {
                $this->addResult('gateway_locks', 'healthy', '✅ No gateway locks found');
            }

        } catch (\Exception $e) {
            $this->addResult('system_locks', 'error', '❌ Failed to check system locks: ' . $e->getMessage());
            
            // If it's a Redis-related error, provide specific guidance
            if (strpos($e->getMessage(), 'Redis') !== false || strpos($e->getMessage(), 'redis') !== false) {
                $this->addResult('cache_redis_error', 'error', '❌ Cache system requires Redis but Redis is not available');
                $this->addRecommendation('Change CACHE_DRIVER to "file" or "database" in .env file');
            }
        }
    }

    /**
     * Check queue status and failed jobs.
     */
    private function checkQueueStatus(): void
    {
        $this->info('Checking queue status...');

        try {
            // Check failed jobs
            $failedJobsCount = DB::table('failed_jobs')->count();
            
            if ($failedJobsCount > 0) {
                $this->addResult('failed_jobs', 'warning', "⚠️  Found {$failedJobsCount} failed jobs");
                $this->addRecommendation('Review failed jobs: php artisan queue:failed');
                $this->addRecommendation('Clear failed jobs if appropriate: php artisan queue:flush');
            } else {
                $this->addResult('failed_jobs', 'healthy', '✅ No failed jobs found');
            }

            // Test if queue system can accept jobs
            $queueConnection = config('queue.default');
            
            // If queue connection is sync, warn about polling implications
            if ($queueConnection === 'sync') {
                $this->addResult('sync_queue_warning', 'warning', '⚠️  Using sync queue - polling jobs will run immediately and block');
                $this->addRecommendation('Consider using database queue for better polling performance');
            }
            
            // Try to get queue size if possible
            try {
                $queueSize = Queue::size();
                $this->addResult('queue_functionality', 'info', "📊 Queue size: {$queueSize} jobs");
            } catch (\Exception $e) {
                $this->addResult('queue_functionality', 'warning', '⚠️  Could not check queue size: ' . $e->getMessage());
                if (strpos($e->getMessage(), 'Redis') !== false) {
                    $this->addRecommendation('Queue configured for Redis but Redis not available - fix Redis setup or change queue driver');
                }
            }

            // Try to check processing jobs if Redis is available
            if (class_exists('Redis') && extension_loaded('redis')) {
                try {
                    $redis = Redis::connection();
                    $processingKey = 'queues:processing';
                    $processingJobs = $redis->llen($processingKey);
                    
                    $this->addResult('processing_jobs', 'info', "📊 Currently processing: {$processingJobs} jobs");
                } catch (\Exception $e) {
                    $this->addResult('processing_jobs', 'info', '📊 Could not check currently processing jobs: ' . $e->getMessage());
                }
            } else {
                $this->addResult('processing_jobs', 'info', '📊 Could not check currently processing jobs (Redis extension not available)');
            }

        } catch (\Exception $e) {
            $this->addResult('queue_status', 'error', '❌ Failed to check queue status: ' . $e->getMessage());
        }
    }

    /**
     * Display diagnostic results in a formatted table.
     */
    private function displayResults(): void
    {
        $this->line('');
        $this->info('📋 Diagnostic Results:');
        $this->line('======================');

        // Group results by status
        $grouped = collect($this->diagnosticResults)->groupBy('status');

        // Show healthy items first
        if ($grouped->has('healthy')) {
            foreach ($grouped['healthy'] as $result) {
                $this->line($result['message']);
            }
        }

        // Show info items
        if ($grouped->has('info')) {
            foreach ($grouped['info'] as $result) {
                $this->line($result['message']);
            }
        }

        // Show warnings
        if ($grouped->has('warning')) {
            $this->line('');
            foreach ($grouped['warning'] as $result) {
                $this->warn($result['message']);
            }
        }

        // Show errors
        if ($grouped->has('error')) {
            $this->line('');
            foreach ($grouped['error'] as $result) {
                $this->error($result['message']);
            }
        }

        // Show recommendations
        if (!empty($this->fixRecommendations)) {
            $this->line('');
            $this->info('🔧 Recommended Fixes:');
            $this->line('=====================');
            foreach ($this->fixRecommendations as $i => $recommendation) {
                $this->line(($i + 1) . '. ' . $recommendation);
            }
        }

        // Show auto-fix option
        if ($this->canAutoFix && !$this->option('fix')) {
            $this->line('');
            $this->info('💡 Some issues can be automatically fixed. Run with --fix to attempt auto-repair.');
        }
    }

    /**
     * Attempt to automatically fix detected issues.
     */
    private function attemptAutoFix(ReliablePollingService $pollingService): int
    {
        $this->line('');
        $this->info('🔧 Attempting automatic fixes...');

        $fixesApplied = 0;

        // Check if polling system needs to be started
        $systemRunning = collect($this->diagnosticResults)
            ->where('component', 'polling_system')
            ->where('status', 'error')
            ->isNotEmpty();

        if ($systemRunning) {
            $this->info('Starting polling system...');
            if ($pollingService->startReliablePolling()) {
                $this->info('✅ Polling system started successfully');
                $fixesApplied++;
            } else {
                $this->warn('⚠️  Polling system was already running or could not be started');
            }
        }

        // Clear system locks if they exist
        $hasSystemLock = Cache::has('polling_system_lock');
        if ($hasSystemLock) {
            $this->info('Clearing system lock...');
            Cache::forget('polling_system_lock');
            $this->info('✅ System lock cleared');
            $fixesApplied++;
        }

        // Run audit and cleanup
        $this->info('Running system audit and cleanup...');
        $auditResults = $pollingService->auditAndCleanup();
        $totalCleaned = array_sum($auditResults);
        
        if ($totalCleaned > 0) {
            $this->info("✅ Cleaned up {$totalCleaned} items during audit");
            $fixesApplied++;
        }

        $this->line('');
        if ($fixesApplied > 0) {
            $this->info("🎉 Applied {$fixesApplied} automatic fixes. Re-run diagnostics to verify.");
            return 0;
        } else {
            $this->warn('⚠️  No automatic fixes were applied. Manual intervention may be required.');
            return 1;
        }
    }

    /**
     * Add a diagnostic result.
     */
    private function addResult(string $component, string $status, string $message): void
    {
        $this->diagnosticResults[] = [
            'component' => $component,
            'status' => $status,
            'message' => $message,
            'timestamp' => now(),
        ];
    }

    /**
     * Add a fix recommendation.
     */
    private function addRecommendation(string $recommendation): void
    {
        if (!in_array($recommendation, $this->fixRecommendations)) {
            $this->fixRecommendations[] = $recommendation;
        }
    }
}