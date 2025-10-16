<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Simple data migration from DataPoints to Device-Register structure.
     */
    public function up(): void
    {
        Log::info('Starting simple data migration from DataPoints to Device-Register structure');
        
        // Step 1: Create devices from DataPoints that don't have device_id
        $this->createDevicesFromDataPoints();
        
        // Step 2: Update DataPoints with device_id references
        $this->updateDataPointsWithDeviceIds();
        
        // Step 3: Create registers from DataPoints
        $this->createRegistersFromDataPoints();
        
        Log::info('Simple data migration completed successfully');
    }

    /**
     * Create Device records from DataPoints without device_id.
     */
    private function createDevicesFromDataPoints(): void
    {
        Log::info('Creating devices from DataPoints');
        
        // Get DataPoints without device_id
        $dataPoints = DB::table('data_points')
            ->whereNull('device_id')
            ->get();
            
        if ($dataPoints->isEmpty()) {
            Log::info('No DataPoints without device_id found');
            return;
        }
        
        $deviceCounter = 1;
        $this->deviceMappings = [];
        
        foreach ($dataPoints as $dataPoint) {
            // Determine device characteristics
            $deviceType = $this->getDeviceType($dataPoint->label);
            $loadCategory = $this->getLoadCategory($dataPoint->label);
            $deviceName = $this->getDeviceName($dataPoint->label, $deviceCounter);
            
            // Create device
            $deviceId = DB::table('devices')->insertGetId([
                'gateway_id' => $dataPoint->gateway_id,
                'name' => $deviceName,
                'device_type' => $deviceType,
                'load_category' => $loadCategory,
                'group_name' => 'Meter_' . $deviceCounter,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->deviceMappings[$dataPoint->id] = $deviceId;
            
            Log::info("Created device {$deviceName} (ID: {$deviceId}) for DataPoint {$dataPoint->id}");
            $deviceCounter++;
        }
        
        Log::info('Created ' . count($this->deviceMappings) . ' devices');
    }
    
    /**
     * Update DataPoints with device_id references.
     */
    private function updateDataPointsWithDeviceIds(): void
    {
        Log::info('Updating DataPoints with device_id references');
        
        if (empty($this->deviceMappings)) {
            Log::info('No device mappings to process');
            return;
        }
        
        foreach ($this->deviceMappings as $dataPointId => $deviceId) {
            DB::table('data_points')
                ->where('id', $dataPointId)
                ->update([
                    'device_id' => $deviceId,
                    'updated_at' => now(),
                ]);
                
            Log::info("Updated DataPoint {$dataPointId} with device_id {$deviceId}");
        }
    }
    
    /**
     * Create Register records from DataPoints.
     */
    private function createRegistersFromDataPoints(): void
    {
        Log::info('Creating registers from DataPoints');
        
        // Get all DataPoints that now have device_id
        $dataPoints = DB::table('data_points')
            ->whereNotNull('device_id')
            ->get();
            
        foreach ($dataPoints as $dataPoint) {
            // Check if register already exists
            $existingRegister = DB::table('registers')
                ->where('device_id', $dataPoint->device_id)
                ->where('register_address', $dataPoint->register_address)
                ->first();
                
            if ($existingRegister) {
                Log::info("Register already exists for device {$dataPoint->device_id}, address {$dataPoint->register_address}");
                continue;
            }
            
            // Create register from DataPoint
            $registerId = DB::table('registers')->insertGetId([
                'device_id' => $dataPoint->device_id,
                'technical_label' => $dataPoint->label ?: 'Register_' . $dataPoint->register_address,
                'function' => $dataPoint->modbus_function,
                'register_address' => $dataPoint->register_address,
                'data_type' => $dataPoint->data_type ?: 'float32',
                'byte_order' => $this->mapByteOrder($dataPoint->byte_order),
                'scale' => $dataPoint->scale_factor ?: 1.0,
                'count' => $dataPoint->register_count ?: 1,
                'enabled' => $dataPoint->is_enabled ?? true,
                'write_function' => $dataPoint->write_function,
                'write_register' => $dataPoint->write_register,
                'on_value' => $dataPoint->on_value,
                'off_value' => $dataPoint->off_value,
                'invert' => $dataPoint->invert ?? false,
                'schedulable' => $dataPoint->is_schedulable ?? false,
                'created_at' => $dataPoint->created_at ?: now(),
                'updated_at' => now(),
            ]);
            
            Log::info("Created register {$registerId} from DataPoint {$dataPoint->id}");
        }
    }
    
    /**
     * Get device type from label.
     */
    private function getDeviceType(?string $label): string
    {
        if (empty($label)) return 'energy';
        
        $label = strtolower($label);
        
        if (str_contains($label, 'water')) return 'water';
        if (str_contains($label, 'control')) return 'control';
        
        return 'energy'; // Default
    }
    
    /**
     * Get load category from label.
     */
    private function getLoadCategory(?string $label): string
    {
        if (empty($label)) return 'other';
        
        $label = strtolower($label);
        
        if (str_contains($label, 'main') || str_contains($label, 'total')) return 'mains';
        if (str_contains($label, 'ac')) return 'ac';
        if (str_contains($label, 'socket')) return 'sockets';
        if (str_contains($label, 'heat')) return 'heater';
        if (str_contains($label, 'light')) return 'lighting';
        if (str_contains($label, 'water')) return 'water';
        if (str_contains($label, 'solar')) return 'solar';
        if (str_contains($label, 'generator')) return 'generator';
        
        return 'other';
    }
    
    /**
     * Get device name from label.
     */
    private function getDeviceName(?string $label, int $counter): string
    {
        if (empty($label)) {
            return 'Device_' . $counter;
        }
        
        // Clean up label for device name
        $name = str_replace('_', ' ', $label);
        $name = ucwords($name);
        
        if (stripos($label, 'total') !== false) {
            return 'Energy Meter ' . $counter;
        }
        
        return $name . ' Device';
    }
    
    /**
     * Map byte order values.
     */
    private function mapByteOrder(?string $byteOrder): string
    {
        if (empty($byteOrder)) return 'big_endian';
        
        return match(strtolower($byteOrder)) {
            'word_swapped' => 'word_swap',
            'little_endian' => 'little_endian',
            'byte_swap' => 'byte_swap',
            default => 'big_endian',
        };
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::info('Rolling back simple data migration');
        
        // Remove device_id from data_points
        DB::table('data_points')->update(['device_id' => null]);
        
        // Delete registers created by this migration
        DB::table('registers')->delete();
        
        // Delete devices created by this migration
        DB::table('devices')->delete();
        
        Log::info('Rollback completed');
    }
    
    private array $deviceMappings = [];
};