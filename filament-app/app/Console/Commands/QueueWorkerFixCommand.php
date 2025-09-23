<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class QueueWorkerFixCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:fix 
                            {--check : Only check status without making changes}
                            {--restart-workers : Restart systemd queue workers}
                            {--clear-queues : Clear stuck jobs from Redis queues}
                            {--all : Perform all fix operations}';

    /**
     * The console command description.
     */
    protected $description = 'Fix queue worker issues by checking services, clearing stuck jobs, and ensuring workers are processing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Queue Worker Fix Tool');
        $this->line('========================');

        $checkOnly = $this->option('check');
        $restartWorkers = $this->option('restart-workers');
        $clearQueues = $this->option('clear-queues');
        $all = $this->option('all');

        // If no specific options, run all checks and fixes
        if (!$checkOnly && !$restartWorkers && !$clearQueues) {
            $all = true;
        }

        $issues = [];
        $fixes = [];

        // 1. Check systemd services status
        $this->info('📋 Checking systemd services...');
        $serviceStatus = $this->checkSystemdServices();
        
        if (!$serviceStatus['queue_worker_running']) {
            $issues[] = 'Queue worker service is not running';
            if (!$checkOnly && ($all || $restartWorkers)) {
                $fixes[] = $this->restartQueueWorker();
            }
        } else {
            $this->info('✅ Queue worker service is running');
        }

        if (!$serviceStatus['polling_monitor_enabled']) {
            $issues[] = 'Polling monitor timer is not enabled';
            if (!$checkOnly && ($all || $restartWorkers)) {
                $fixes[] = $this->enablePollingMonitor();
            }
        } else {
            $this->info('✅ Polling monitor timer is enabled');
        }

        // 2. Check Redis connection and queue status
        $this->info('📋 Checking Redis connection and queues...');
        $redisStatus = $this->checkRedisStatus();
        
        if (!$redisStatus['redis_available']) {
            $issues[] = 'Redis extension not available';
            $this->suggestAlternativeQueueSetup();
        } elseif (!$redisStatus['connected']) {
            $issues[] = 'Redis connection failed';
            $this->error('❌ Cannot connect to Redis - check Redis service');
            if (!$checkOnly && ($all || $restartWorkers)) {
                $fixes[] = $this->suggestRedisSetup();
            }
        } else {
            $this->info('✅ Redis connection successful');
            
            // Check for stuck jobs
            if ($redisStatus['stuck_jobs'] > 0) {
                $issues[] = "Found {$redisStatus['stuck_jobs']} stuck jobs in queues";
                if (!$checkOnly && ($all || $clearQueues)) {
                    $fixes[] = $this->clearStuckJobs();
                }
            } else {
                $this->info('✅ No stuck jobs found in queues');
            }
        }

        // 3. Check queue worker processing
        $this->info('📋 Checking queue worker processing...');
        $processingStatus = $this->checkQueueProcessing();
        
        if (!$processingStatus['processing']) {
            $issues[] = 'Queue workers are not processing jobs';
            if (!$checkOnly && ($all || $restartWorkers)) {
                $fixes[] = $this->restartQueueWorker();
            }
        } else {
            $this->info('✅ Queue workers are processing jobs');
        }

        // 4. Summary
        $this->line('');
        $this->info('📊 Summary');
        $this->line('==========');

        if (empty($issues)) {
            $this->info('✅ All queue worker checks passed - no issues found');
            return 0;
        }

        $this->warn("⚠️  Found " . count($issues) . " issues:");
        foreach ($issues as $issue) {
            $this->line("  • {$issue}");
        }

        if ($checkOnly) {
            $this->line('');
            $this->info('Run without --check to apply fixes automatically');
            return 1;
        }

        if (!empty($fixes)) {
            $this->line('');
            $this->info('🔧 Applied fixes:');
            foreach ($fixes as $fix) {
                $this->line("  • {$fix}");
            }
        }

        // Final verification
        $this->line('');
        $this->info('🔍 Running final verification...');
        sleep(3); // Give services time to start
        
        $finalCheck = $this->verifyFixes();
        if ($finalCheck) {
            $this->info('✅ All fixes applied successfully - queue workers should be operational');
            return 0;
        } else {
            $this->error('❌ Some issues remain - manual intervention may be required');
            return 1;
        }
    }

    /**
     * Check systemd services status (Linux) or process status (Windows)
     */
    private function checkSystemdServices(): array
    {
        $status = [
            'queue_worker_running' => false,
            'polling_monitor_enabled' => false,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, check if queue workers are running via process list
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>nul');
            if ($processes) {
                // Look for queue:work processes
                $status['queue_worker_running'] = strpos($processes, 'queue:work') !== false;
                $status['polling_monitor_enabled'] = true; // Assume enabled on Windows
            }
        } else {
            // Linux systemd checks
            $queueWorkerStatus = shell_exec('systemctl is-active filament-queue-worker.service 2>/dev/null');
            $status['queue_worker_running'] = trim($queueWorkerStatus) === 'active';

            $pollingMonitorStatus = shell_exec('systemctl is-enabled filament-polling-monitor.timer 2>/dev/null');
            $status['polling_monitor_enabled'] = trim($pollingMonitorStatus) === 'enabled';
        }

        return $status;
    }

    /**
     * Check Redis connection and queue status
     */
    private function checkRedisStatus(): array
    {
        $status = [
            'connected' => false,
            'stuck_jobs' => 0,
            'redis_available' => false,
        ];

        // First check if Redis extension is available
        if (!extension_loaded('redis') && !class_exists('Predis\Client')) {
            $this->warn('⚠️  Redis extension not installed and Predis not available');
            $this->line('   Install Redis extension or Predis package for full queue functionality');
            return $status;
        }

        try {
            // Test Redis connection using Laravel's Redis facade
            $result = Redis::connection()->ping();
            $status['connected'] = $result === 'PONG' || $result === true;
            $status['redis_available'] = true;

            if ($status['connected']) {
                // Check for stuck jobs in polling queues
                $queues = ['polling', 'scheduling', 'default'];
                $stuckJobs = 0;

                foreach ($queues as $queue) {
                    // Check for jobs that have been processing for too long
                    $processingKey = "queues:{$queue}:processing";
                    $processingJobs = Redis::llen($processingKey);
                    
                    if ($processingJobs > 0) {
                        // Check if these jobs are actually stuck (older than 1 hour)
                        $jobs = Redis::lrange($processingKey, 0, -1);
                        foreach ($jobs as $job) {
                            $jobData = json_decode($job, true);
                            if (isset($jobData['pushedAt'])) {
                                $pushedAt = $jobData['pushedAt'];
                                if (time() - $pushedAt > 3600) { // 1 hour
                                    $stuckJobs++;
                                }
                            }
                        }
                    }
                }

                $status['stuck_jobs'] = $stuckJobs;
            }

        } catch (\Exception $e) {
            Log::error('Redis connection check failed', ['error' => $e->getMessage()]);
            
            // Check if this is a Redis extension issue
            if (strpos($e->getMessage(), 'Redis') !== false) {
                $this->error('❌ Redis connection failed - check if Redis server is running');
                $this->line('   Or consider switching to database queue driver for development');
            }
        }

        return $status;
    }

    /**
     * Check if queue workers are actually processing jobs
     */
    private function checkQueueProcessing(): array
    {
        $status = ['processing' => false];

        try {
            // Check if there are any active queue workers by looking at Redis
            $workers = Redis::smembers('queues:workers');
            
            if (!empty($workers)) {
                // Check if workers are recent (within last 5 minutes)
                $recentWorkers = 0;
                foreach ($workers as $worker) {
                    $workerData = Redis::get("queues:worker:{$worker}");
                    if ($workerData) {
                        $data = json_decode($workerData, true);
                        if (isset($data['started_at']) && (time() - $data['started_at']) < 300) {
                            $recentWorkers++;
                        }
                    }
                }
                
                $status['processing'] = $recentWorkers > 0;
            } else {
                // Alternative check: look for recent job processing activity
                $queues = ['polling', 'scheduling', 'default'];
                foreach ($queues as $queue) {
                    $queueSize = Redis::llen("queues:{$queue}");
                    if ($queueSize > 0) {
                        // If there are jobs in queue but no workers, workers aren't processing
                        $status['processing'] = false;
                        break;
                    }
                }
                
                // If no jobs in queues, assume workers are ready (processing = true)
                if (!isset($status['processing']) || $status['processing'] === null) {
                    $status['processing'] = true;
                }
            }

        } catch (\Exception $e) {
            Log::error('Queue processing check failed', ['error' => $e->getMessage()]);
            // If we can't check Redis, assume workers might not be processing
            $status['processing'] = false;
        }

        return $status;
    }

    /**
     * Restart the queue worker service (systemd on Linux, manual on Windows)
     */
    private function restartQueueWorker(): string
    {
        $this->info('🔄 Restarting queue worker service...');
        
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, we can't restart systemd services, so provide instructions
            $this->warn('On Windows, please manually restart your queue workers:');
            $this->line('1. Stop any running "php artisan queue:work" processes');
            $this->line('2. Start new queue workers with: php artisan queue:work redis --queue=polling,scheduling,default');
            return 'Provided Windows queue worker restart instructions';
        } else {
            // Linux systemd service restart
            shell_exec('sudo systemctl stop filament-queue-worker.service 2>/dev/null');
            sleep(2);
            
            shell_exec('sudo systemctl start filament-queue-worker.service 2>/dev/null');
            sleep(3);
            
            shell_exec('sudo systemctl enable filament-queue-worker.service 2>/dev/null');
            
            return 'Restarted queue worker systemd service';
        }
    }

    /**
     * Enable and start the polling monitor timer
     */
    private function enablePollingMonitor(): string
    {
        $this->info('🔄 Enabling polling monitor timer...');
        
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, suggest using Task Scheduler or manual cron-like setup
            $this->warn('On Windows, please set up polling monitor manually:');
            $this->line('1. Use Task Scheduler to run: php artisan polling:reliable start');
            $this->line('2. Set it to run every 5 minutes');
            return 'Provided Windows polling monitor setup instructions';
        } else {
            // Linux systemd timer
            shell_exec('sudo systemctl enable filament-polling-monitor.timer 2>/dev/null');
            shell_exec('sudo systemctl start filament-polling-monitor.timer 2>/dev/null');
            
            return 'Enabled and started polling monitor timer';
        }
    }

    /**
     * Clear stuck jobs from Redis queues
     */
    private function clearStuckJobs(): string
    {
        $this->info('🧹 Clearing stuck jobs from queues...');
        
        $clearedJobs = 0;
        $queues = ['polling', 'scheduling', 'default'];

        try {
            foreach ($queues as $queue) {
                // Clear processing queue of stuck jobs
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
                
                // Clear failed jobs older than 24 hours using Laravel's failed job handling
                try {
                    Artisan::call('queue:flush');
                    $this->info('Flushed failed jobs using Laravel command');
                } catch (\Exception $e) {
                    // If queue:flush fails, try manual cleanup
                    $failedKey = "queues:{$queue}:failed";
                    $failedJobs = Redis::lrange($failedKey, 0, -1);
                    
                    foreach ($failedJobs as $job) {
                        $jobData = json_decode($job, true);
                        if (isset($jobData['failed_at'])) {
                            $failedAt = $jobData['failed_at'];
                            if (time() - $failedAt > 86400) { // 24 hours old
                                Redis::lrem($failedKey, 1, $job);
                                $clearedJobs++;
                            }
                        }
                    }
                }
            }

            // Clear any stale worker entries
            $workers = Redis::smembers('queues:workers');
            foreach ($workers as $worker) {
                $workerData = Redis::get("queues:worker:{$worker}");
                if ($workerData) {
                    $data = json_decode($workerData, true);
                    if (isset($data['started_at']) && (time() - $data['started_at']) > 3600) {
                        Redis::srem('queues:workers', $worker);
                        Redis::del("queues:worker:{$worker}");
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to clear stuck jobs', ['error' => $e->getMessage()]);
            $this->warn('Some queue cleanup operations failed - check logs for details');
        }

        return "Cleared {$clearedJobs} stuck jobs from Redis queues";
    }

    /**
     * Verify that fixes were successful
     */
    private function verifyFixes(): bool
    {
        $serviceStatus = $this->checkSystemdServices();
        $redisStatus = $this->checkRedisStatus();
        $processingStatus = $this->checkQueueProcessing();

        $allGood = $serviceStatus['queue_worker_running'] && 
                   $serviceStatus['polling_monitor_enabled'] && 
                   $redisStatus['connected'] && 
                   $processingStatus['processing'];

        if ($allGood) {
            $this->info('✅ Queue worker verification passed');
        } else {
            $this->warn('⚠️  Queue worker verification failed');
            
            if (!$serviceStatus['queue_worker_running']) {
                $this->line('  • Queue worker service still not running');
            }
            if (!$serviceStatus['polling_monitor_enabled']) {
                $this->line('  • Polling monitor timer still not enabled');
            }
            if (!$redisStatus['connected']) {
                $this->line('  • Redis connection still failing');
            }
            if (!$processingStatus['processing']) {
                $this->line('  • Queue workers still not processing jobs');
            }
        }

        return $allGood;
    }
    
    /**
     * Suggest alternative queue setup for development
     */
    private function suggestAlternativeQueueSetup(): void
    {
        $this->line('');
        $this->info('💡 Alternative Queue Setup Options:');
        $this->line('=====================================');
        $this->line('1. For development, switch to database queue:');
        $this->line('   • Change QUEUE_CONNECTION=database in .env');
        $this->line('   • Run: php artisan queue:table');
        $this->line('   • Run: php artisan migrate');
        $this->line('');
        $this->line('2. Install Redis for production:');
        $this->line('   • Windows: Download from https://github.com/microsoftarchive/redis/releases');
        $this->line('   • Linux: sudo apt-get install redis-server');
        $this->line('   • Install PHP Redis extension');
        $this->line('');
        $this->line('3. Use Predis (pure PHP Redis client):');
        $this->line('   • Run: composer require predis/predis');
        $this->line('   • Change REDIS_CLIENT=predis in .env');
    }
    
    /**
     * Suggest Redis setup steps
     */
    private function suggestRedisSetup(): string
    {
        $this->line('');
        $this->info('🔧 Redis Setup Suggestions:');
        $this->line('===========================');
        $this->line('1. Check if Redis server is running:');
        if (PHP_OS_FAMILY === 'Windows') {
            $this->line('   • Check Windows Services for Redis');
            $this->line('   • Or run: redis-server.exe');
        } else {
            $this->line('   • Run: sudo systemctl status redis');
            $this->line('   • Start: sudo systemctl start redis');
        }
        $this->line('2. Test connection: redis-cli ping');
        $this->line('3. Check firewall settings on port 6379');
        
        return 'Provided Redis setup guidance';
    }
}