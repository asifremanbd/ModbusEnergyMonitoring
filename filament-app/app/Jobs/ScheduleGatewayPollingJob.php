<?php

namespace App\Jobs;

use App\Models\Gateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduleGatewayPollingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('scheduling');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting gateway polling scheduler');
        
        $activeGateways = Gateway::active()->get();
        
        Log::info("Found {$activeGateways->count()} active gateways to schedule");
        
        foreach ($activeGateways as $gateway) {
            // Check if there's already a pending poll job for this gateway
            if (!$this->hasExistingPollJob($gateway)) {
                Log::info("Scheduling poll for gateway {$gateway->id} ({$gateway->name})");
                
                // Dispatch immediately for the first poll, then the job will self-schedule
                PollGatewayJob::dispatch($gateway);
            } else {
                Log::debug("Gateway {$gateway->id} already has a scheduled poll job");
            }
        }
        
        Log::info('Gateway polling scheduler completed');
    }

    /**
     * Check if there's already a pending poll job for the gateway.
     * This is a simple implementation - in production you might want to use
     * a more sophisticated approach with job tracking.
     */
    private function hasExistingPollJob(Gateway $gateway): bool
    {
        // For now, we'll assume no existing jobs and let Laravel's queue handle duplicates
        // In a production environment, you might want to implement job deduplication
        return false;
    }
}