<?php

namespace App\Observers;

use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Support\Facades\Log;

class GatewayObserver
{
    protected ReliablePollingService $pollingService;

    public function __construct(ReliablePollingService $pollingService)
    {
        $this->pollingService = $pollingService;
    }

    /**
     * Handle the Gateway "created" event.
     */
    public function created(Gateway $gateway): void
    {
        if ($gateway->is_active) {
            $this->startPollingForGateway($gateway);
        }
    }

    /**
     * Handle the Gateway "updated" event.
     */
    public function updated(Gateway $gateway): void
    {
        // Check if is_active status changed
        if ($gateway->wasChanged('is_active')) {
            if ($gateway->is_active) {
                $this->startPollingForGateway($gateway);
            } else {
                $this->stopPollingForGateway($gateway);
            }
        }
        
        // If poll_interval changed and gateway is active, restart polling
        if ($gateway->wasChanged('poll_interval') && $gateway->is_active) {
            $this->restartPollingForGateway($gateway);
        }
    }

    /**
     * Handle the Gateway "deleted" event.
     */
    public function deleted(Gateway $gateway): void
    {
        $this->stopPollingForGateway($gateway);
    }

    /**
     * Start polling for a specific gateway.
     */
    protected function startPollingForGateway(Gateway $gateway): void
    {
        try {
            $this->pollingService->startPollingForGateway($gateway);
            Log::info("Started polling for gateway: {$gateway->name} (ID: {$gateway->id})");
        } catch (\Exception $e) {
            Log::error("Failed to start polling for gateway {$gateway->name}: " . $e->getMessage());
        }
    }

    /**
     * Stop polling for a specific gateway.
     */
    protected function stopPollingForGateway(Gateway $gateway): void
    {
        try {
            $this->pollingService->stopPollingForGateway($gateway);
            Log::info("Stopped polling for gateway: {$gateway->name} (ID: {$gateway->id})");
        } catch (\Exception $e) {
            Log::error("Failed to stop polling for gateway {$gateway->name}: " . $e->getMessage());
        }
    }

    /**
     * Restart polling for a specific gateway.
     */
    protected function restartPollingForGateway(Gateway $gateway): void
    {
        try {
            $this->pollingService->stopPollingForGateway($gateway);
            $this->pollingService->startPollingForGateway($gateway);
            Log::info("Restarted polling for gateway: {$gateway->name} (ID: {$gateway->id}) with new interval: {$gateway->poll_interval}s");
        } catch (\Exception $e) {
            Log::error("Failed to restart polling for gateway {$gateway->name}: " . $e->getMessage());
        }
    }
}