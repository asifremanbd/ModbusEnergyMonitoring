<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a mix of online and offline gateways with realistic data
        $gateways = [
            [
                'name' => 'Building A Main Meter',
                'ip_address' => '192.168.1.100',
                'port' => 502,
                'unit_id' => 1,
                'poll_interval' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Building B Sub Meter',
                'ip_address' => '192.168.1.101',
                'port' => 502,
                'unit_id' => 1,
                'poll_interval' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'Warehouse Power Monitor',
                'ip_address' => '192.168.1.102',
                'port' => 502,
                'unit_id' => 2,
                'poll_interval' => 30,
                'is_active' => false,
            ],
        ];

        foreach ($gateways as $gatewayData) {
            $gateway = \App\Models\Gateway::factory()->create($gatewayData);

            // Create data points for each gateway
            $this->createDataPointsForGateway($gateway);
        }

        // Create additional random gateways
        \App\Models\Gateway::factory(5)
            ->online()
            ->create()
            ->each(function ($gateway) {
                $this->createDataPointsForGateway($gateway);
            });

        // Create some unreliable gateways
        \App\Models\Gateway::factory(2)
            ->unreliable()
            ->create()
            ->each(function ($gateway) {
                $this->createDataPointsForGateway($gateway);
            });
    }

    private function createDataPointsForGateway(\App\Models\Gateway $gateway): void
    {
        // Create typical Teltonika energy meter data points
        $dataPoints = [
            // Meter 1 - Voltage measurements
            ['group_name' => 'Meter_1', 'label' => 'Voltage L1', 'register_address' => 1100, 'scale_factor' => 0.1],
            ['group_name' => 'Meter_1', 'label' => 'Voltage L2', 'register_address' => 1102, 'scale_factor' => 0.1],
            ['group_name' => 'Meter_1', 'label' => 'Voltage L3', 'register_address' => 1104, 'scale_factor' => 0.1],
            
            // Meter 1 - Current measurements
            ['group_name' => 'Meter_1', 'label' => 'Current L1', 'register_address' => 1200, 'scale_factor' => 0.01],
            ['group_name' => 'Meter_1', 'label' => 'Current L2', 'register_address' => 1202, 'scale_factor' => 0.01],
            ['group_name' => 'Meter_1', 'label' => 'Current L3', 'register_address' => 1204, 'scale_factor' => 0.01],
            
            // Meter 1 - Power measurements
            ['group_name' => 'Meter_1', 'label' => 'Active Power L1', 'register_address' => 1000, 'scale_factor' => 1.0],
            ['group_name' => 'Meter_1', 'label' => 'Active Power L2', 'register_address' => 1002, 'scale_factor' => 1.0],
            ['group_name' => 'Meter_1', 'label' => 'Active Power L3', 'register_address' => 1004, 'scale_factor' => 1.0],
            
            // Meter 2 - Key measurements
            ['group_name' => 'Meter_2', 'label' => 'Total Active Power', 'register_address' => 2000, 'scale_factor' => 1.0],
            ['group_name' => 'Meter_2', 'label' => 'Total Energy', 'register_address' => 2100, 'scale_factor' => 0.1],
        ];

        foreach ($dataPoints as $pointData) {
            $dataPoint = \App\Models\DataPoint::factory()
                ->teltonikaEnergy()
                ->create(array_merge([
                    'gateway_id' => $gateway->id,
                ], $pointData));

            // Create some historical readings for each data point
            $this->createReadingsForDataPoint($dataPoint);
        }
    }

    private function createReadingsForDataPoint(\App\Models\DataPoint $dataPoint): void
    {
        // Create recent readings (last hour)
        \App\Models\Reading::factory(10)
            ->recent()
            ->goodQuality()
            ->create([
                'data_point_id' => $dataPoint->id,
            ]);

        // Create some older readings
        \App\Models\Reading::factory(20)
            ->old()
            ->create([
                'data_point_id' => $dataPoint->id,
            ]);

        // Create a few bad quality readings
        \App\Models\Reading::factory(2)
            ->badQuality()
            ->create([
                'data_point_id' => $dataPoint->id,
            ]);
    }
}
