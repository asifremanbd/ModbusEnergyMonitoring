<?php

namespace App\Jobs;

use App\Events\GatewayStatusChanged;
use App\Models\Gateway;
use App\Services\ModbusPollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class PollGatewayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Gateway $gateway
    ) {
        $this->onQueue('polling');
    }

    /**
     * Execute the job.
     */
    public function handle(ModbusPollService $pollService): void
    {
        $statusKey = "gateway_polling_status_{$this->gateway->id}";
        
        try {
            // Update status to indicate polling is in progress
            \Illuminate\Support\Facades\Cache::put($statusKey, [
                'gateway_id' => $this->gateway->id,
                'last_poll_started' => now(),
                'poll_interval' => $this->gateway->poll_interval,
                'status' => 'polling'
            ], now()->addMinutes(10));
            
            Log::info("Starting poll for gateway {$this->gateway->id} ({$this->gateway->name})");
            
            // Check if gateway is still active before polling
            $gateway = $this->gateway->fresh();
            if (!$gateway || !$gateway->is_active) {
                Log::info("Gateway {$this->gateway->id} is inactive, skipping poll");
                \Illuminate\Support\Facades\Cache::forget($statusKey);
                return;
            }

            $result = $pollService->pollGateway($gateway);
            
            if ($result->success) {
                Log::info("Successfully polled gateway {$gateway->id}, got " . count($result->readings) . " readings in {$result->duration}s");
                
                // Update success count
                $gateway->increment('success_count');
                
                // Reset failure count on successful poll
                if ($gateway->failure_count > 0) {
                    $gateway->update(['failure_count' => 0]);
                }
            } else {
                Log::warning("Gateway {$gateway->id} poll completed with errors: " . json_encode($result->errors));
                $gateway->increment('failure_count');
            }

            // Update last seen timestamp
            $gateway->update(['last_seen_at' => now()]);

            // Schedule next poll if gateway is still active
            $this->scheduleNextPoll();
            
        } catch (Exception $e) {
            Log::error("Failed to poll gateway {$this->gateway->id}: " . $e->getMessage());
            
            // Clear status on error
            \Illuminate\Support\Facades\Cache::forget($statusKey);
            
            // Increment failure count
            $this->gateway->increment('failure_count');
            
            // Check if we should disable the gateway due to too many failures
            $this->checkGatewayHealth();
            
            throw $e;
        }
    }

    /**
     * Schedule the next poll for this gateway based on its poll interval.
     */
    private function scheduleNextPoll(): void
    {
        $gateway = $this->gateway->fresh();
        
        if ($gateway && $gateway->is_active) {
            // Schedule next job with precise timing
            $nextPollTime = now()->addSeconds($gateway->poll_interval);
            
            // Update polling status to track scheduling
            $statusKey = "gateway_polling_status_{$this->gateway->id}";
            \Illuminate\Support\Facades\Cache::put($statusKey, [
                'gateway_id' => $gateway->id,
                'last_scheduled' => $nextPollTime,
                'poll_interval' => $gateway->poll_interval,
                'status' => 'scheduled'
            ], $nextPollTime->addSeconds($gateway->poll_interval));
            
            PollGatewayJob::dispatch($gateway)->delay($nextPollTime);
            
            Log::info("Scheduled next poll for gateway {$gateway->id} at {$nextPollTime}");
        } else {
            // Clear polling status if gateway is no longer active
            $statusKey = "gateway_polling_status_{$this->gateway->id}";
            \Illuminate\Support\Facades\Cache::forget($statusKey);
            
            Log::info("Gateway {$this->gateway->id} is inactive, stopping polling chain");
        }
    }

    /**
     * Check gateway health and disable if too many consecutive failures.
     */
    private function checkGatewayHealth(): void
    {
        $gateway = $this->gateway->fresh();
        
        if (!$gateway) {
            return;
        }
        
        // Calculate recent failure rate
        $totalAttempts = $gateway->success_count + $gateway->failure_count;
        $failureRate = $totalAttempts > 0 ? ($gateway->failure_count / $totalAttempts) : 0;
        
        // Disable gateway if failure rate is too high (>80%) and we have enough attempts
        if ($failureRate > 0.8 && $totalAttempts >= 10) {
            Log::warning("Disabling gateway {$gateway->id} due to high failure rate: {$failureRate}");
            
            $previousStatus = $gateway->is_active ? 'active' : 'inactive';
            
            $gateway->update([
                'is_active' => false,
            ]);
            
            // Broadcast gateway status change
            GatewayStatusChanged::dispatch($gateway, $previousStatus, 'inactive');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Gateway {$this->gateway->id} polling job failed permanently: " . $exception->getMessage());
        
        // Clear polling status when job fails permanently
        $statusKey = "gateway_polling_status_{$this->gateway->id}";
        \Illuminate\Support\Facades\Cache::forget($statusKey);
        
        // Mark gateway as having issues but don't disable automatically
        $this->gateway->increment('failure_count');
        
        // Check if we should disable the gateway due to too many failures
        $this->checkGatewayHealth();
    }
}