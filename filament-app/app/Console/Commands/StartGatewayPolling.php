<?php

namespace App\Console\Commands;

use App\Services\GatewayPollingService;
use Illuminate\Console\Command;

class StartGatewayPolling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateway:start-polling {--gateway= : Start polling for specific gateway ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the gateway polling system';

    /**
     * Execute the console command.
     */
    public function handle(GatewayPollingService $pollingService)
    {
        $gatewayId = $this->option('gateway');
        
        if ($gatewayId) {
            $gateway = \App\Models\Gateway::find($gatewayId);
            
            if (!$gateway) {
                $this->error("Gateway with ID {$gatewayId} not found");
                return 1;
            }
            
            $this->info("Starting polling for gateway: {$gateway->name}");
            $pollingService->startGatewayPolling($gateway);
        } else {
            $this->info('Starting polling for all active gateways...');
            $pollingService->startPolling();
        }
        
        $this->info('Gateway polling started successfully');
        return 0;
    }
}
