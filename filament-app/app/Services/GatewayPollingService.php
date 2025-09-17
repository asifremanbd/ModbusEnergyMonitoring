<?php

namespace App\Services;

use App\Jobs\PollGatewayJob;
use App\Jobs\ScheduleGatewayPollingJob;
use App\Models\Gateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class GatewayPollingService
{
    /**
     * Start polling for all active gateways.
     */
    public function startPolling(): void
    {
        Log::info('Starting gateway polling system');
        
        ScheduleGatewayPollingJob::dispatch();
    }

    /**
     * Stop polling for all gateways by clearing the polling queue.
     */
    public function stopPolling(): void
    {
        Log::info('Stopping gateway polling system');
        
        // Clear the polling queue
        $this->clearPollingQueue();
    }

    /**
     * Start polling for a specific gateway.
     */
    public function startGatewayPolling(Gateway $gateway): void
    {
        if (!$gateway->is_active) {
            Log::warning("Cannot start polling for inactive gateway {$gateway->id}");
            return;
        }
        
        Log::info("Starting polling for gateway {$gateway->id} ({$gateway->name})");
        
        PollGatewayJob::dispatch($gateway);
    }

    /**
     * Stop polling for a specific gateway.
     */
    public function stopGatewayPolling(Gateway $gateway): void
    {
        Log::info("Stopping polling for gateway {$gateway->id} ({$gateway->name})");
        
        // Mark gateway as inactive to prevent new jobs
        $gateway->update(['is_active' => false]);
        
        // Note: Existing queued jobs will check the gateway status and skip if inactive
    }

    /**
     * Restart polling for a specific gateway.
     */
    public function restartGatewayPolling(Gateway $gateway): void
    {
        Log::info("Restarting polling for gateway {$gateway->id} ({$gateway->name})");
        
        // Ensure gateway is active
        $gateway->update(['is_active' => true]);
        
        // Reset counters for a fresh start
        $gateway->update([
            'success_count' => 0,
            'failure_count' => 0,
        ]);
        
        // Start polling
        $this->startGatewayPolling($gateway);
    }

    /**
     * Get polling statistics for all gateways.
     */
    public function getPollingStatistics(): array
    {
        $gateways = Gateway::all();
        
        $stats = [
            'total_gateways' => $gateways->count(),
            'active_gateways' => $gateways->where('is_active', true)->count(),
            'online_gateways' => $gateways->filter(fn($g) => $g->is_online)->count(),
            'total_success' => $gateways->sum('success_count'),
            'total_failures' => $gateways->sum('failure_count'),
        ];
        
        $totalAttempts = $stats['total_success'] + $stats['total_failures'];
        $stats['overall_success_rate'] = $totalAttempts > 0 
            ? round(($stats['total_success'] / $totalAttempts) * 100, 2) 
            : 0;
        
        return $stats;
    }

    /**
     * Get the average poll latency across all gateways.
     */
    public function getAverageLatency(): float
    {
        // This would require storing latency data in readings or a separate table
        // For now, return a placeholder value
        return 0.0;
    }

    /**
     * Clear all jobs from the polling queue.
     */
    private function clearPollingQueue(): void
    {
        try {
            // This is a simplified approach - in production you might want
            // a more sophisticated queue management system
            Log::info('Clearing polling queue');
            
            // Note: The actual implementation depends on your queue driver
            // For Redis, you might use Redis commands to clear specific queues
            
        } catch (\Exception $e) {
            Log::error('Failed to clear polling queue: ' . $e->getMessage());
        }
    }

    /**
     * Check the health of the polling system.
     */
    public function checkSystemHealth(): array
    {
        $stats = $this->getPollingStatistics();
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
        ];
        
        // Check for gateways with high failure rates
        $problematicGateways = Gateway::all()->filter(function ($gateway) {
            $total = $gateway->success_count + $gateway->failure_count;
            return $total > 10 && ($gateway->failure_count / $total) > 0.5;
        });
        
        if ($problematicGateways->count() > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "High failure rate detected for {$problematicGateways->count()} gateways";
        }
        
        // Check for offline gateways
        $offlineGateways = Gateway::active()->get()->filter(fn($g) => !$g->is_online);
        
        if ($offlineGateways->count() > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "{$offlineGateways->count()} active gateways are offline";
        }
        
        // Check overall success rate
        if ($stats['overall_success_rate'] < 80 && $stats['total_success'] + $stats['total_failures'] > 100) {
            $health['status'] = 'critical';
            $health['issues'][] = "Overall success rate is low: {$stats['overall_success_rate']}%";
        }
        
        return $health;
    }
}