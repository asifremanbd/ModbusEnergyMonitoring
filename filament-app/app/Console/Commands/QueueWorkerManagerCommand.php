<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class QueueWorkerManagerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:manage 
                            {action : Action to perform (start|stop|restart|status|clear)}
                            {--daemon : Run worker as daemon}
                            {--timeout=60 : Worker timeout in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Manage queue workers for polling system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'start':
                return $this->startWorker();
                
            case 'stop':
                return $this->stopWorkers();
                
            case 'restart':
                $this->stopWorkers();
                sleep(2);
                return $this->startWorker();
                
            case 'status':
                return $this->showStatus();
                
            case 'clear':
                return $this->clearQueues();
                
            default:
                $this->error("Unknown action: {$action}");
                $this->line('Available actions: start, stop, restart, status, clear');
                return 1;
        }
    }

    /**
     * Start queue worker
     */
    private function startWorker(): int
    {
        $this->info('🚀 Starting queue worker...');
        
        $queueConnection = config('queue.default');
        $daemon = $this->option('daemon');
        $timeout = $this->option('timeout');
        
        if ($daemon) {
            // Start as background process
            $command = [
                'php',
                'artisan',
                'queue:work',
                $queueConnection,
                '--queue=polling,scheduling,default',
                '--sleep=3',
                '--tries=3',
                "--timeout={$timeout}",
                '--memory=512'
            ];
            
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows background process
                $process = new Process($command);
                $process->setWorkingDirectory(base_path());
                $process->start();
                
                $this->info("✅ Queue worker started as background process (PID: {$process->getPid()})");
                $this->line('Use "queue:manage stop" to stop the worker');
            } else {
                // Linux background process
                $commandString = implode(' ', $command) . ' > /dev/null 2>&1 &';
                shell_exec($commandString);
                $this->info('✅ Queue worker started as background process');
                $this->line('Use "queue:manage stop" to stop the worker');
            }
        } else {
            // Start in foreground (will block)
            $this->info('Starting queue worker in foreground (press Ctrl+C to stop)...');
            $exitCode = Artisan::call('queue:work', [
                'connection' => $queueConnection,
                '--queue' => 'polling,scheduling,default',
                '--sleep' => 3,
                '--tries' => 3,
                '--timeout' => $timeout,
                '--memory' => 512,
            ]);
            
            return $exitCode;
        }
        
        return 0;
    }

    /**
     * Stop queue workers
     */
    private function stopWorkers(): int
    {
        $this->info('🛑 Stopping queue workers...');
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Kill PHP processes running queue:work
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>nul');
            if ($processes) {
                $lines = explode("\n", $processes);
                $killed = 0;
                
                foreach ($lines as $line) {
                    if (strpos($line, 'queue:work') !== false) {
                        // Extract PID and kill process
                        $parts = str_getcsv($line);
                        if (isset($parts[1])) {
                            $pid = $parts[1];
                            shell_exec("taskkill /PID {$pid} /F 2>nul");
                            $killed++;
                        }
                    }
                }
                
                if ($killed > 0) {
                    $this->info("✅ Stopped {$killed} queue worker process(es)");
                } else {
                    $this->info('ℹ️  No queue worker processes found');
                }
            }
        } else {
            // Linux - kill queue:work processes
            $result = shell_exec("pkill -f 'queue:work' 2>/dev/null");
            $this->info('✅ Sent stop signal to queue workers');
        }
        
        return 0;
    }

    /**
     * Show queue and worker status
     */
    private function showStatus(): int
    {
        $this->info('📊 Queue Worker Status');
        $this->line('======================');
        
        $queueConnection = config('queue.default');
        $this->line("Queue Driver: {$queueConnection}");
        
        // Check for running workers
        $runningWorkers = $this->getRunningWorkers();
        $this->line("Running Workers: {$runningWorkers}");
        
        if ($queueConnection === 'database') {
            $this->showDatabaseQueueStatus();
        }
        
        // Show recent job activity
        $this->showRecentActivity();
        
        return 0;
    }

    /**
     * Clear queues
     */
    private function clearQueues(): int
    {
        $this->info('🧹 Clearing queues...');
        
        $queueConnection = config('queue.default');
        
        if ($queueConnection === 'database') {
            return $this->clearDatabaseQueues();
        } else {
            // For other queue drivers, use Laravel's built-in commands
            Artisan::call('queue:clear');
            Artisan::call('queue:flush');
            $this->info('✅ Queues cleared using Laravel commands');
            return 0;
        }
    }

    /**
     * Get count of running queue workers
     */
    private function getRunningWorkers(): int
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
     * Show database queue status
     */
    private function showDatabaseQueueStatus(): void
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $this->table(
                ['Queue', 'Count'],
                [
                    ['Pending Jobs', $pendingJobs],
                    ['Failed Jobs', $failedJobs],
                ]
            );
            
            if ($pendingJobs > 0 && $this->getRunningWorkers() === 0) {
                $this->warn('⚠️  Jobs are pending but no workers are running');
                $this->line('   Start workers with: php artisan queue:manage start --daemon');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error checking database queue: ' . $e->getMessage());
        }
    }

    /**
     * Show recent job activity
     */
    private function showRecentActivity(): void
    {
        try {
            $this->line('');
            $this->info('Recent Job Activity:');
            
            // Show recent jobs from database
            $recentJobs = DB::table('jobs')
                ->select('queue', 'created_at', 'attempts')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            if ($recentJobs->count() > 0) {
                $jobData = $recentJobs->map(function ($job) {
                    return [
                        $job->queue,
                        $job->created_at,
                        $job->attempts,
                    ];
                });
                
                $this->table(
                    ['Queue', 'Created', 'Attempts'],
                    $jobData->toArray()
                );
            } else {
                $this->line('No recent jobs found');
            }
            
        } catch (\Exception $e) {
            $this->line('Could not retrieve recent activity');
        }
    }

    /**
     * Clear database queues
     */
    private function clearDatabaseQueues(): int
    {
        try {
            $clearedJobs = DB::table('jobs')->count();
            $clearedFailed = DB::table('failed_jobs')->count();
            
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();
            
            $this->table(
                ['Cleared', 'Count'],
                [
                    ['Pending Jobs', $clearedJobs],
                    ['Failed Jobs', $clearedFailed],
                ]
            );
            
            $this->info('✅ Database queues cleared');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error clearing database queues: ' . $e->getMessage());
            return 1;
        }
    }
}