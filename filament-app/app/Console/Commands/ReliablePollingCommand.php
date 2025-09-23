<?php

namespace App\Console\Commands;

use App\Services\ReliablePollingService;
use Illuminate\Console\Command;

class ReliablePollingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polling:reliable 
                            {action : Action to perform (start|stop|status|audit|validate|fix-workers|sync)}
                            {--gateway= : Specific gateway ID for start/stop actions}
                            {--detailed : Show detailed information for status}';

    /**
     * The console command description.
     */
    protected $description = 'Manage the reliable gateway polling system';

    /**
     * Execute the console command.
     */
    public function handle(ReliablePollingService $pollingService): int
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'start':
                return $this->handleStart($pollingService);
                
            case 'stop':
                return $this->handleStop($pollingService);
                
            case 'status':
                return $this->handleStatus($pollingService);
                
            case 'audit':
                return $this->handleAudit($pollingService);
                
            case 'validate':
                return $this->handleValidate($pollingService);
                
            case 'fix-workers':
                return $this->handleFixWorkers($pollingService);
                
            case 'sync':
                return $this->handleSync($pollingService);
                
            default:
                $this->error("Unknown action: {$action}");
                $this->line('Available actions: start, stop, status, audit, validate, fix-workers, sync');
                return 1;
        }
    }
    
    private function handleStart(ReliablePollingService $pollingService): int
    {
        $gatewayId = $this->option('gateway');
        
        if ($gatewayId) {
            $gateway = \App\Models\Gateway::find($gatewayId);
            
            if (!$gateway) {
                $this->error("Gateway with ID {$gatewayId} not found");
                return 1;
            }
            
            $this->info("Starting reliable polling for gateway: {$gateway->name}");
            
            if ($pollingService->startGatewayPolling($gateway)) {
                $this->info('✅ Gateway polling started successfully');
                return 0;
            } else {
                $this->warn('⚠️  Gateway polling was already active or could not be started');
                return 1;
            }
        } else {
            $this->info('🚀 Starting reliable polling system for all active gateways...');
            
            if ($pollingService->startReliablePolling()) {
                $this->info('✅ Reliable polling system started successfully');
                return 0;
            } else {
                $this->warn('⚠️  Polling system was already running');
                return 1;
            }
        }
    }
    
    private function handleStop(ReliablePollingService $pollingService): int
    {
        $gatewayId = $this->option('gateway');
        
        if ($gatewayId) {
            $gateway = \App\Models\Gateway::find($gatewayId);
            
            if (!$gateway) {
                $this->error("Gateway with ID {$gatewayId} not found");
                return 1;
            }
            
            $this->info("Stopping polling for gateway: {$gateway->name}");
            $pollingService->stopGatewayPolling($gateway);
            $this->info('✅ Gateway polling stopped');
        } else {
            $this->info('🛑 Stopping all gateway polling...');
            $pollingService->stopAllPolling();
            $this->info('✅ All polling stopped');
        }
        
        return 0;
    }
    
    private function handleStatus(ReliablePollingService $pollingService): int
    {
        $status = $pollingService->getSystemStatus();
        
        $this->info('📊 Reliable Polling System Status');
        $this->line('=====================================');
        
        // System summary
        $summary = $status['summary'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['System Running', $summary['system_running'] ? '✅ Yes' : '❌ No'],
                ['Total Gateways', $summary['total_gateways']],
                ['Active Gateways', $summary['active_gateways']],
                ['Actively Polling', $summary['actively_polling']],
            ]
        );
        
        // System info
        if (!empty($status['system'])) {
            $this->line('');
            $this->info('System Information:');
            $systemInfo = $status['system'];
            $this->line("Started: {$systemInfo['started_at']}");
            $this->line("Active Gateways: {$systemInfo['active_gateways']}/{$systemInfo['total_gateways']}");
        }
        
        // Gateway details
        if ($this->option('detailed') && !empty($status['gateways'])) {
            $this->line('');
            $this->info('Gateway Details:');
            
            $gatewayData = collect($status['gateways'])->map(function ($gateway) {
                return [
                    $gateway['id'],
                    $gateway['name'],
                    $gateway['is_active'] ? '✅ Active' : '❌ Inactive',
                    $gateway['poll_interval'] . 's',
                    $gateway['is_polling'] ? '🔄 Polling' : '⏸️  Stopped',
                    $gateway['last_scheduled'] ? $gateway['last_scheduled']->format('H:i:s') : 'Never',
                    $gateway['next_poll_due'] ? $gateway['next_poll_due']->format('H:i:s') : 'N/A',
                ];
            });
            
            $this->table(
                ['ID', 'Name', 'Status', 'Interval', 'Polling', 'Last Scheduled', 'Next Due'],
                $gatewayData->toArray()
            );
        }
        
        return 0;
    }
    
    private function handleAudit(ReliablePollingService $pollingService): int
    {
        $this->info('🔍 Running polling system audit...');
        
        $results = $pollingService->auditAndCleanup();
        
        $this->table(
            ['Cleanup Type', 'Count'],
            [
                ['Orphaned Locks', $results['orphaned_locks']],
                ['Stale Statuses', $results['stale_statuses']],
                ['Inactive Polling', $results['inactive_polling']],
            ]
        );
        
        $total = array_sum($results);
        
        if ($total > 0) {
            $this->info("✅ Audit completed. Cleaned up {$total} items.");
        } else {
            $this->info('✅ Audit completed. No cleanup needed.');
        }
        
        return 0;
    }
    
    private function handleValidate(ReliablePollingService $pollingService): int
    {
        $this->info('🔍 Validating polling system integrity...');
        
        $issues = $pollingService->validatePollingIntegrity();
        
        if (empty($issues)) {
            $this->info('✅ All active gateways have proper polling scheduled');
            return 0;
        }
        
        $this->warn("⚠️  Found {count($issues)} polling integrity issues:");
        
        foreach ($issues as $issue) {
            $icon = $issue['type'] === 'missing_polling' ? '❌' : '⏰';
            $this->line("{$icon} Gateway {$issue['gateway_id']} ({$issue['gateway_name']}): {$issue['message']}");
        }
        
        return 1;
    }
    
    private function handleFixWorkers(ReliablePollingService $pollingService): int
    {
        $this->info('🔧 Checking and fixing queue workers...');
        
        $results = $pollingService->checkAndFixQueueWorkers();
        $queueDriver = $results['queue_driver'] ?? 'unknown';
        
        $this->line("Queue Driver: {$queueDriver}");
        
        if ($queueDriver === 'database') {
            $this->table(
                ['Check', 'Result'],
                [
                    ['Queue Driver', '✅ Database'],
                    ['Queue Workers Checked', $results['queue_workers_checked'] ? '✅ Yes' : '❌ No'],
                    ['Stuck Jobs Cleared', $results['stuck_jobs_cleared']],
                ]
            );
            
            if ($results['queue_workers_checked']) {
                $this->info('✅ Database queue worker check completed');
                $this->line('💡 Use "php artisan queue:manage status" for detailed queue status');
                $this->line('💡 Use "php artisan queue:manage start --daemon" to start workers');
                return 0;
            }
        } else {
            $this->table(
                ['Check', 'Result'],
                [
                    ['Redis Connection', $results['redis_connected'] ? '✅ Connected' : '❌ Failed'],
                    ['Queue Workers Checked', $results['queue_workers_checked'] ? '✅ Yes' : '❌ No'],
                    ['Queue Workers Restarted', $results['queue_workers_restarted'] ? '🔄 Yes' : '⏸️  Not needed'],
                    ['Stuck Jobs Cleared', $results['stuck_jobs_cleared']],
                ]
            );
            
            if ($results['redis_connected'] && $results['queue_workers_checked']) {
                $this->info('✅ Redis queue worker fix completed successfully');
                return 0;
            }
        }
        
        $this->error('❌ Queue worker fix encountered issues');
        return 1;
    }
    
    private function handleSync(ReliablePollingService $pollingService): int
    {
        $this->info('🔄 Synchronizing gateway polling states...');
        
        $results = $pollingService->ensureActiveGatewaysPolling();
        
        $this->table(
            ['Action', 'Count'],
            [
                ['Gateways Checked', $results['checked']],
                ['Polling Started', $results['started']],
                ['Already Active', $results['already_active']],
                ['Failed to Start', $results['failed']],
                ['Stopped Inactive', $results['stopped_inactive']],
            ]
        );
        
        $totalFixed = $results['started'] + $results['stopped_inactive'];
        
        if ($totalFixed > 0) {
            $this->info("✅ Synchronized {$totalFixed} gateway(s) successfully");
        } else {
            $this->info('✅ All gateways are already properly synchronized');
        }
        
        if ($results['failed'] > 0) {
            $this->warn("⚠️  Failed to fix {$results['failed']} gateway(s)");
            return 1;
        }
        
        return 0;
    }
}