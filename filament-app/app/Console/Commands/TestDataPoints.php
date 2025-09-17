<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use App\Services\ModbusPollService;

class TestDataPoints extends Command
{
    protected $signature = 'test:datapoints {gateway?}';
    protected $description = 'Test all data points for a gateway using the ModbusPollService';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        $this->info("Testing data points for gateway: {$gateway->name}");
        $this->info("Connection: {$gateway->ip_address}:{$gateway->port}, Unit ID: {$gateway->unit_id}");
        $this->newLine();

        $pollService = app(ModbusPollService::class);
        
        // Get all enabled data points
        $dataPoints = $gateway->dataPoints()->where('is_enabled', true)->get();
        
        if ($dataPoints->isEmpty()) {
            $this->warn("No enabled data points found for this gateway.");
            return 1;
        }

        $this->info("Found {$dataPoints->count()} enabled data points:");
        $this->newLine();

        foreach ($dataPoints as $dataPoint) {
            $this->info("Testing: {$dataPoint->group_name} - {$dataPoint->label}");
            $this->line("  Register: {$dataPoint->register_address}");
            $this->line("  Function: {$dataPoint->modbus_function}");
            $this->line("  Data Type: {$dataPoint->data_type}");
            $this->line("  Byte Order: {$dataPoint->byte_order}");
            $this->line("  Scale Factor: {$dataPoint->scale_factor}");
            
            try {
                $result = $pollService->readRegister($gateway, $dataPoint);
                
                if ($result->success) {
                    $this->info("  ✓ SUCCESS:");
                    $this->line("    Raw Value: {$result->rawValue}");
                    $this->line("    Scaled Value: {$result->scaledValue}");
                    $this->line("    Quality: {$result->quality}");
                    
                    // If it's a kWh reading, show it prominently
                    if (str_contains(strtolower($dataPoint->label), 'kwh')) {
                        $this->line("    → <fg=green>Energy Reading: {$result->scaledValue} kWh</>");
                    }
                } else {
                    $this->error("  ✗ FAILED:");
                    $this->line("    Error: {$result->error}");
                    if ($result->errorType) {
                        $this->line("    Error Type: {$result->errorType}");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  ✗ EXCEPTION: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        // Also test the full gateway polling
        $this->info("Testing full gateway polling...");
        try {
            $pollResult = $pollService->pollGateway($gateway);
            
            if ($pollResult->success) {
                $this->info("✓ Gateway polling successful!");
                $this->line("  Duration: " . round($pollResult->duration * 1000, 2) . "ms");
                $this->line("  Readings created: " . count($pollResult->readings));
                
                foreach ($pollResult->readings as $reading) {
                    $dp = $reading->dataPoint;
                    $this->line("  - {$dp->group_name} - {$dp->label}: {$reading->scaled_value}");
                }
            } else {
                $this->error("✗ Gateway polling failed!");
                $this->line("  Errors: " . count($pollResult->errors));
                foreach ($pollResult->errors as $error) {
                    $this->line("  - " . $error['error']);
                }
            }
        } catch (\Exception $e) {
            $this->error("✗ Gateway polling exception: " . $e->getMessage());
        }

        return 0;
    }
}