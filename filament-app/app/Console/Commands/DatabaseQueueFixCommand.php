<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DatabaseQueueFixCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:fix-database 
                            {--setup : Set up database queue tables}
                            {--clear : Clear failed and stuck jobs}
                            {--status : Show queue status}';

    /**
     * The console command description.
     */
    protected $description = 'Fix database queue issues and set up database-based queuing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Database Queue Fix Tool');
        $this->line('==========================');

        $setup = $this->option('setup');
        $clear = $this->option('clear');
        $status = $this->option('status');

        // If no options specified, show status and offer setup
        if (!$setup && !$clear && !$status) {
            $status = true;
        }

        if ($status) {
            $this->showQueueStatus();
        }

        if ($setup) {
            return $this->setupDatabaseQueue();
        }

        if ($clear) {
            return $this->clearDatabaseQueue();
        }

        // Suggest next steps
        $this->line('');
        $this->info('💡 Available actions:');
        $this->line('• --setup: Set up database queue tables');
        $this->line('• --clear: Clear failed and stuck jobs');
        $this->line('• --status: Show current queue status');

        return 0;
    }

    /**
     * Show current queue status
     */
    private function showQueueStatus(): void
    {
        $this->info('📊 Current Queue Configuration');
        $this->line('==============================');

        $queueConnection = config('queue.default');
        $this->line("Queue Driver: {$queueConnection}");

        if ($queueConnection === 'database') {
            $this->checkDatabaseQueueStatus();
        } elseif ($queueConnection === 'redis') {
            $this->line('❌ Redis queue configured but may not be working');
            $this->line('   Consider switching to database queue for development');
        } else {
            $this->line("Using {$queueConnection} queue driver");
        }
    }

    /**
     * Check database queue status
     */
    private function checkDatabaseQueueStatus(): void
    {
        try {
            // Check if jobs table exists
            if (!$this->tableExists('jobs')) {
                $this->error('❌ Jobs table does not exist');
                $this->line('   Run with --setup to create queue tables');
                return;
            }

            $this->info('✅ Database queue tables exist');

            // Check job counts
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = $this->tableExists('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            $this->table(
                ['Queue Status', 'Count'],
                [
                    ['Pending Jobs', $pendingJobs],
                    ['Failed Jobs', $failedJobs],
                ]
            );

            if ($pendingJobs > 0) {
                $this->warn("⚠️  {$pendingJobs} jobs waiting to be processed");
                $this->line('   Start a queue worker: php artisan queue:work database');
            }

            if ($failedJobs > 0) {
                $this->warn("⚠️  {$failedJobs} failed jobs");
                $this->line('   Clear with: php artisan queue:fix-database --clear');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error checking database queue status: ' . $e->getMessage());
        }
    }

    /**
     * Set up database queue
     */
    private function setupDatabaseQueue(): int
    {
        $this->info('🔧 Setting up database queue...');

        try {
            // Create jobs table if it doesn't exist
            if (!$this->tableExists('jobs')) {
                $this->info('Creating jobs table...');
                Artisan::call('queue:table');
                $this->info('✅ Jobs table migration created');
            }

            // Create failed jobs table if it doesn't exist
            if (!$this->tableExists('failed_jobs')) {
                $this->info('Creating failed jobs table...');
                Artisan::call('queue:failed-table');
                $this->info('✅ Failed jobs table migration created');
            }

            // Run migrations
            $this->info('Running migrations...');
            Artisan::call('migrate');
            $this->info('✅ Migrations completed');

            // Update .env file suggestion
            $this->line('');
            $this->info('📝 Configuration Update Needed:');
            $this->line('================================');
            $this->line('Add or update in your .env file:');
            $this->line('QUEUE_CONNECTION=database');
            $this->line('');
            $this->line('Then restart your queue workers:');
            $this->line('php artisan queue:work database --queue=polling,scheduling,default');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Failed to set up database queue: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear database queue
     */
    private function clearDatabaseQueue(): int
    {
        $this->info('🧹 Clearing database queue...');

        try {
            $clearedJobs = 0;
            $clearedFailed = 0;

            // Clear stuck jobs (older than 1 hour)
            if ($this->tableExists('jobs')) {
                $stuckJobs = DB::table('jobs')
                    ->where('created_at', '<', now()->subHour())
                    ->count();

                if ($stuckJobs > 0) {
                    DB::table('jobs')
                        ->where('created_at', '<', now()->subHour())
                        ->delete();
                    $clearedJobs = $stuckJobs;
                }
            }

            // Clear failed jobs
            if ($this->tableExists('failed_jobs')) {
                $failedCount = DB::table('failed_jobs')->count();
                if ($failedCount > 0) {
                    DB::table('failed_jobs')->truncate();
                    $clearedFailed = $failedCount;
                }
            }

            $this->table(
                ['Cleanup Action', 'Count'],
                [
                    ['Stuck Jobs Cleared', $clearedJobs],
                    ['Failed Jobs Cleared', $clearedFailed],
                ]
            );

            $this->info('✅ Database queue cleanup completed');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Failed to clear database queue: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check if a table exists
     */
    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }
}