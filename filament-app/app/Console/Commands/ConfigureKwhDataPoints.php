<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use App\Models\DataPoint;

class ConfigureKwhDataPoints extends Command
{
    protected $signature = 'configure:kwh-datapoints {gateway?}';
    protected $description = 'Configure the 4 Total kWh data points for a gateway';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            $this->info("Available gateways:");
            Gateway::all()->each(function ($gw) {
                $this->line("  - {$gw->name} ({$gw->ip_address}:{$gw->port})");
            });
            return 1;
        }

        $this->info("Configuring Total kWh data points for gateway: {$gateway->name}");
        $this->info("Connection: {$gateway->ip_address}:{$gateway->port}, Unit ID: {$gateway->unit_id}");
        $this->newLine();

        // Show existing data points
        $existingPoints = $gateway->dataPoints;
        $this->info("Existing data points: " . $existingPoints->count());
        $existingPoints->each(function ($dp) {
            $this->line("  - {$dp->label} (Reg: {$dp->register_address}, Group: {$dp->group_name})");
        });
        $this->newLine();

        // Define the 4 kWh data points based on your testing
        $kwhDataPoints = [
            [
                'group_name' => 'Meter_1',
                'label' => 'Total_kWh',
                'modbus_function' => 3,
                'register_address' => 1025,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
            [
                'group_name' => 'Meter_2',
                'label' => 'Total_kWh',
                'modbus_function' => 3,
                'register_address' => 1033,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
            [
                'group_name' => 'Meter_3',
                'label' => 'Total_kWh',
                'modbus_function' => 3,
                'register_address' => 1035,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
            [
                'group_name' => 'Meter_4',
                'label' => 'Total_kWh',
                'modbus_function' => 3,
                'register_address' => 1037,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
        ];

        // Ask if user wants to clear existing data points
        if ($existingPoints->count() > 0) {
            if ($this->confirm('Do you want to clear existing data points first?')) {
                $existingPoints->each(function ($dp) {
                    $this->line("Deleting: {$dp->label}");
                    $dp->delete();
                });
                $this->info("Cleared existing data points.");
                $this->newLine();
            }
        }

        // Create the new data points
        $this->info("Creating 4 Total kWh data points...");
        $this->newLine();

        foreach ($kwhDataPoints as $pointConfig) {
            try {
                // Check if data point already exists
                $existing = $gateway->dataPoints()
                    ->where('register_address', $pointConfig['register_address'])
                    ->first();

                if ($existing) {
                    $this->warn("Data point for register {$pointConfig['register_address']} already exists. Updating...");
                    $existing->update($pointConfig);
                    $dataPoint = $existing;
                } else {
                    $dataPoint = $gateway->dataPoints()->create($pointConfig);
                }

                $this->info("✓ Created: {$pointConfig['group_name']} - {$pointConfig['label']} (Reg: {$pointConfig['register_address']})");

            } catch (\Exception $e) {
                $this->error("✗ Failed to create {$pointConfig['group_name']} - {$pointConfig['label']}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Configuration completed!");
        
        // Show final summary
        $finalPoints = $gateway->dataPoints()->get();
        $this->info("Final data points count: " . $finalPoints->count());
        $finalPoints->each(function ($dp) {
            $status = $dp->is_enabled ? '✓' : '✗';
            $this->line("  {$status} {$dp->group_name} - {$dp->label} (Reg: {$dp->register_address})");
        });

        return 0;
    }
}