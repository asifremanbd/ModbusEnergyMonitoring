<?php

namespace App\Console\Commands;

use App\Services\GatewayPollingService;
use Illuminate\Console\Command;

class StopGatewayPolling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateway:stop-polling {--gateway= : Stop polling for specific gateway ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the gateway polling system';

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
            
            $this->info("Stopping polling for gateway: {$gateway->name}");
            $pollingService->stopGatewayPolling($gateway);
        } else {
            $this->info('Stopping polling for all gateways...');
            $pollingService->stopPolling();
        }
        
        $this->info('Gateway polling stopped successfully');
        return 0;
    }
}
