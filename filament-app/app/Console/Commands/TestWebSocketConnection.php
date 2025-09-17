<?php

namespace App\Console\Commands;

use App\Services\WebSocketService;
use Illuminate\Console\Command;

class TestWebSocketConnection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:test';

    /**
     * The console command description.
     */
    protected $description = 'Test WebSocket connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle(WebSocketService $webSocketService): int
    {
        $this->info('Testing WebSocket Configuration...');
        $this->newLine();

        // Test configuration
        $config = $webSocketService->getConfigurationStatus();
        
        $this->info('Configuration Status:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['Broadcast Driver', $config['driver'], $config['driver'] === 'pusher' ? '✓' : '✗'],
                ['Pusher Configured', $config['is_pusher_configured'] ? 'Yes' : 'No', $config['is_pusher_configured'] ? '✓' : '✗'],
                ['App Key', $config['has_app_key'] ? 'Set' : 'Missing', $config['has_app_key'] ? '✓' : '✗'],
                ['App Secret', $config['has_app_secret'] ? 'Set' : 'Missing', $config['has_app_secret'] ? '✓' : '✗'],
                ['App ID', $config['has_app_id'] ? 'Set' : 'Missing', $config['has_app_id'] ? '✓' : '✗'],
                ['Cluster', $config['cluster'] ?? 'Not Set', $config['cluster'] ? '✓' : '✗'],
            ]
        );

        $this->newLine();

        // Test connectivity
        $this->info('Testing Connectivity...');
        $isConnectable = $webSocketService->testConnection();
        
        if ($isConnectable) {
            $this->info('✓ WebSocket configuration appears valid');
        } else {
            $this->error('✗ WebSocket configuration has issues');
        }

        // Show health status
        $this->newLine();
        $health = $webSocketService->getHealthStatus();
        
        $this->info('Health Status:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Connected', $health['connected'] ? 'Yes' : 'No'],
                ['Should Use Fallback', $health['should_use_fallback'] ? 'Yes' : 'No'],
                ['Fallback Interval', $health['fallback_interval'] . 's'],
                ['Recent Attempts', $health['recent_attempts']],
                ['Last Attempt', $health['last_attempt'] ?? 'Never'],
            ]
        );

        // Recommendations
        $this->newLine();
        $this->info('Recommendations:');
        
        if (!$config['is_pusher_configured']) {
            $this->warn('• Configure Pusher credentials in your .env file');
            $this->line('  - PUSHER_APP_ID=your_app_id');
            $this->line('  - PUSHER_APP_KEY=your_app_key');
            $this->line('  - PUSHER_APP_SECRET=your_app_secret');
            $this->line('  - PUSHER_APP_CLUSTER=your_cluster');
        }
        
        if ($config['driver'] !== 'pusher') {
            $this->warn('• Set BROADCAST_DRIVER=pusher in your .env file');
        }
        
        if ($config['is_pusher_configured'] && $isConnectable) {
            $this->info('• WebSocket is properly configured for real-time updates');
            $this->info('• Fallback polling will activate automatically if connection fails');
        }

        return $isConnectable ? Command::SUCCESS : Command::FAILURE;
    }
}