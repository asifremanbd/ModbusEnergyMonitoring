<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\GatewayPollingService;
use Illuminate\Console\Command;

class GatewayPollingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateway:status {--detailed : Show detailed gateway information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show gateway polling system status';

    /**
     * Execute the console command.
     */
    public function handle(GatewayPollingService $pollingService)
    {
        $this->info('Gateway Polling System Status');
        $this->line('================================');
        
        // Overall statistics
        $stats = $pollingService->getPollingStatistics();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Gateways', $stats['total_gateways']],
                ['Active Gateways', $stats['active_gateways']],
                ['Online Gateways', $stats['online_gateways']],
                ['Total Success', number_format($stats['total_success'])],
                ['Total Failures', number_format($stats['total_failures'])],
                ['Success Rate', $stats['overall_success_rate'] . '%'],
            ]
        );
        
        // System health
        $health = $pollingService->checkSystemHealth();
        $this->line('');
        $this->info("System Health: " . strtoupper($health['status']));
        
        if (!empty($health['issues'])) {
            $this->line('Issues:');
            foreach ($health['issues'] as $issue) {
                $this->warn("  - {$issue}");
            }
        }
        
        // Detailed gateway information
        if ($this->option('detailed')) {
            $this->line('');
            $this->info('Gateway Details:');
            
            $gateways = Gateway::all();
            
            $gatewayData = $gateways->map(function ($gateway) {
                $total = $gateway->success_count + $gateway->failure_count;
                $successRate = $total > 0 ? round(($gateway->success_count / $total) * 100, 1) : 0;
                
                return [
                    $gateway->id,
                    $gateway->name,
                    $gateway->ip_address . ':' . $gateway->port,
                    $gateway->is_active ? 'Active' : 'Inactive',
                    $gateway->is_online ? 'Online' : 'Offline',
                    $gateway->poll_interval . 's',
                    $successRate . '%',
                    $gateway->last_seen_at ? $gateway->last_seen_at->diffForHumans() : 'Never',
                ];
            });
            
            $this->table(
                ['ID', 'Name', 'Address', 'Status', 'Online', 'Interval', 'Success Rate', 'Last Seen'],
                $gatewayData->toArray()
            );
        }
        
        return 0;
    }
}
