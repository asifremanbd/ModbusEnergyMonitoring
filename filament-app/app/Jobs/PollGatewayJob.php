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
        try {
            Log::info("Starting poll for gateway {$this->gateway->id} ({$this->gateway->name})");
            
            // Check if gateway is still active before polling
            if (!$this->gateway->fresh()->is_active) {
                Log::info("Gateway {$this->gateway->id} is inactive, skipping poll");
                return;
            }

            $result = $pollService->pollGateway($this->gateway);
            
            if ($result->success) {
                Log::info("Successfully polled gateway {$this->gateway->id}, got " . count($result->readings) . " readings in {$result->duration}s");
            } else {
                Log::warning("Gateway {$this->gateway->id} poll completed with errors: " . json_encode($result->errors));
            }

            // Schedule next poll if gateway is still active
            $this->scheduleNextPoll();
            
        } catch (Exception $e) {
            Log::error("Failed to poll gateway {$this->gateway->id}: " . $e->getMessage());
            
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
            PollGatewayJob::dispatch($gateway)
                ->delay(now()->addSeconds($gateway->poll_interval));
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
        
        // Mark gateway as having issues but don't disable automatically
        $this->gateway->increment('failure_count');
    }
}