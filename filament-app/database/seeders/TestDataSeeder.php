<?php

namespace Database\Seeders;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Get existing gateway
        $gateway = Gateway::first();

        // Create test data points
        $dataPoints = [
            [
                'gateway_id' => $gateway->id,
                'group_name' => 'Energy',
                'label' => 'Total kWh',
                'modbus_function' => 4,
                'register_address' => 1000,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'big_endian',
                'scale_factor' => 1.0,
                'is_enabled' => true
            ],
            [
                'gateway_id' => $gateway->id,
                'group_name' => 'Power',
                'label' => 'Current Power',
                'modbus_function' => 4,
                'register_address' => 1002,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'big_endian',
                'scale_factor' => 1.0,
                'is_enabled' => true
            ],
            [
                'gateway_id' => $gateway->id,
                'group_name' => 'Voltage',
                'label' => 'Line Voltage',
                'modbus_function' => 4,
                'register_address' => 1004,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'big_endian',
                'scale_factor' => 1.0,
                'is_enabled' => true
            ]
        ];

        foreach ($dataPoints as $dpData) {
            $dataPoint = DataPoint::create($dpData);
            
            // Create sample readings for the last 24 hours
            for ($i = 0; $i < 24; $i++) {
                $timestamp = now()->subHours($i);
                
                Reading::create([
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => json_encode([rand(1000, 9999), rand(1000, 9999)]),
                    'scaled_value' => rand(100, 500) + (rand(0, 99) / 100),
                    'quality' => rand(0, 10) > 1 ? 'good' : 'bad', // 90% good quality
                    'read_at' => $timestamp
                ]);
            }
        }

        echo "Test data created successfully!\n";
        echo "Gateway: {$gateway->name}\n";
        echo "Data Points: " . DataPoint::count() . "\n";
        echo "Readings: " . Reading::count() . "\n";
    }
}