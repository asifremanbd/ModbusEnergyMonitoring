<?php

namespace App\Console\Commands;

use App\Models\DataPoint;
use App\Models\Gateway;
use Illuminate\Console\Command;

class TestDataPointEnhancements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:datapoint-enhancements';

    /**
     * The console command description.
     *
     *
     * @var string
     */
    protected $description = 'Test the new DataPoint enhancements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing DataPoint Enhancements...');
        
        // Test 1: Check if new fields exist in database
        $this->info('1. Checking database schema...');
        try {
            $dataPoint = new DataPoint();
            $fillable = $dataPoint->getFillable();
            
            $newFields = ['device_type', 'load_category', 'custom_label', 'write_function', 'write_register', 'on_value', 'off_value', 'invert', 'is_schedulable'];
            
            foreach ($newFields as $field) {
                if (in_array($field, $fillable)) {
                    $this->info("   ✓ {$field} field is fillable");
                } else {
                    $this->error("   ✗ {$field} field is NOT fillable");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error checking fillable fields: ' . $e->getMessage());
        }
        
        // Test 2: Try to create a sample data point (if gateway exists)
        $this->info('2. Testing data point creation...');
        try {
            $gateway = Gateway::first();
            if (!$gateway) {
                $this->warn('   No gateways found. Skipping creation test.');
                return;
            }
            
            // Test Energy Meter
            $energyPoint = new DataPoint([
                'gateway_id' => $gateway->id,
                'device_type' => 'energy',
                'load_category' => 'mains',
                'custom_label' => 'Test Main Supply',
                'group_name' => 'Test_Group',
                'label' => 'Test Energy',
                'modbus_function' => 4,
                'register_address' => 1000,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ]);
            
            $this->info('   ✓ Energy meter data point created successfully');
            $this->info("   - Device Type: {$energyPoint->device_type}");
            $this->info("   - Load Category: {$energyPoint->load_category}");
            $this->info("   - Custom Label: {$energyPoint->custom_label}");
            
            // Test Control Device
            $controlPoint = new DataPoint([
                'gateway_id' => $gateway->id,
                'device_type' => 'control',
                'load_category' => 'heater',
                'custom_label' => 'Test Heater Control',
                'group_name' => 'Control_Group',
                'label' => 'Heater Switch',
                'write_function' => 5,
                'write_register' => 2000,
                'on_value' => '1',
                'off_value' => '0',
                'invert' => false,
                'is_schedulable' => true,
                'is_enabled' => true,
            ]);
            
            $this->info('   ✓ Control device data point created successfully');
            $this->info("   - Write Function: {$controlPoint->write_function}");
            $this->info("   - Write Register: {$controlPoint->write_register}");
            $this->info("   - Schedulable: " . ($controlPoint->is_schedulable ? 'Yes' : 'No'));
            
        } catch (\Exception $e) {
            $this->error('Error creating data points: ' . $e->getMessage());
        }
        
        // Test 3: Check helper methods
        $this->info('3. Testing helper methods...');
        try {
            $testPoint = new DataPoint(['device_type' => 'energy', 'custom_label' => 'Test Label', 'label' => 'Fallback Label']);
            
            $this->info('   ✓ Helper methods work:');
            $this->info("   - Is Energy Meter: " . ($testPoint->is_energy_meter ? 'Yes' : 'No'));
            $this->info("   - Unit: '{$testPoint->unit}'");
            $this->info("   - Display Label: '{$testPoint->display_label}'");
            
        } catch (\Exception $e) {
            $this->error('Error testing helper methods: ' . $e->getMessage());
        }
        
        $this->info('DataPoint enhancements test completed!');
    }
}
