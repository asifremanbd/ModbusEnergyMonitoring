<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use App\Models\DataPoint;

class TestByteOrders extends Command
{
    protected $signature = 'test:byte-orders {gateway?}';
    protected $description = 'Test different byte orders for Meter_1 Total_kWh';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        // Find Meter_1 data point
        $dataPoint = $gateway->dataPoints()
            ->where('group_name', 'Meter_1')
            ->where('label', 'Total_kWh')
            ->first();

        if (!$dataPoint) {
            $this->error("Meter_1 Total_kWh data point not found!");
            return 1;
        }

        $this->info("Testing different byte orders for Meter_1 Total_kWh (Register 1025)");
        $this->newLine();

        $byteOrders = ['word_swapped', 'big_endian', 'little_endian'];
        $pollService = app(\App\Services\ModbusPollService::class);

        foreach ($byteOrders as $byteOrder) {
            $this->info("Testing byte order: {$byteOrder}");
            
            // Temporarily update the byte order
            $originalByteOrder = $dataPoint->byte_order;
            $dataPoint->update(['byte_order' => $byteOrder]);
            
            try {
                $result = $pollService->readRegister($gateway, $dataPoint);
                
                if ($result->success) {
                    $this->line("  Raw Value: {$result->rawValue}");
                    $this->line("  Scaled Value: {$result->scaledValue}");
                    
                    // Check if this looks like a reasonable kWh value
                    $value = (float) $result->scaledValue;
                    if ($value > 0 && $value < 10000) {
                        $this->line("  → <fg=green>This looks like a reasonable kWh value!</>");
                    } elseif ($value > 10000) {
                        $this->line("  → <fg=yellow>Large value - might need scaling</>");
                    } else {
                        $this->line("  → <fg=red>Very small value - probably incorrect byte order</>");
                    }
                } else {
                    $this->error("  Failed: {$result->error}");
                }
                
            } catch (\Exception $e) {
                $this->error("  Exception: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        // Restore original byte order
        $dataPoint->update(['byte_order' => $originalByteOrder]);
        $this->info("Restored original byte order: {$originalByteOrder}");

        return 0;
    }
}