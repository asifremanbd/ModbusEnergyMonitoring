<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Complete data migration from DataPoints to Device-Register structure with safety checks.
     */
    public function up(): void
    {
        Log::info('Starting complete data migration with safety checks');
        
        try {
            // Step 1: Create devices from DataPoints
            $this->createDevicesFromDataPoints();
            
            // Step 2: Update DataPoints with device_id references
            $this->updateDataPointsWithDeviceIds();
            
            // Step 3: Create registers from DataPoints
            $this->createRegistersFromDataPoints();
            
            // Step 4: Update readings safely (without triggering constraints)
            $this->updateReadingsSafely();
            
            // Step 5: Verify data integrity
            $this->verifyDataIntegrity();
            
            Log::info('Complete data migration finished successfully');
            
        } catch (\Exception $e) {
            Log::error('Migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create Device records from DataPoints without device_id.
     */
    private function createDevicesFromDataPoints(): void
    {
        Log::info('Step 1: Creating devices from DataPoints');
        
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
            $deviceType = $this->getDeviceType($dataPoint->label);
            $loadCategory = $this->getLoadCategory($dataPoint->label);
            $deviceName = $this->getDeviceName($dataPoint->label, $deviceCounter);
            
            $deviceId = DB::table('devices')->insertGetId([
                'gateway_id' => $dataPoint->gateway_id,
                'device_name' => $deviceName,
                'device_type' => $deviceType,
                'load_category' => $loadCategory,
                'group_name' => 'Meter_' . $deviceCounter,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->deviceMappings[$dataPoint->id] = $deviceId;
            
            Log::info("Created device: {$deviceName} (ID: {$deviceId}) for DataPoint {$dataPoint->id}");
            $deviceCounter++;
        }
        
        Log::info('Created ' . count($this->deviceMappings) . ' devices');
    }
    
    /**
     * Update DataPoints with device_id references.
     */
    private function updateDataPointsWithDeviceIds(): void
    {
        Log::info('Step 2: Updating DataPoints with device_id references');
        
        if (empty($this->deviceMappings)) {
            Log::info('No device mappings to process');
            return;
        }
        
        foreach ($this->deviceMappings as $dataPointId => $deviceId) {
            DB::table('data_points')
                ->where('id', $dataPointId)
                ->update(['device_id' => $deviceId]);
                
            Log::info("Updated DataPoint {$dataPointId} with device_id {$deviceId}");
        }
    }
    
    /**
     * Create Register records from DataPoints.
     */
    private function createRegistersFromDataPoints(): void
    {
        Log::info('Step 3: Creating registers from DataPoints');
        
        $dataPoints = DB::table('data_points')
            ->whereNotNull('device_id')
            ->get();
            
        $this->registerMappings = [];
        
        foreach ($dataPoints as $dataPoint) {
            // Check if register already exists
            $existingRegister = DB::table('registers')
                ->where('device_id', $dataPoint->device_id)
                ->where('register_address', $dataPoint->register_address)
                ->first();
                
            if ($existingRegister) {
                $this->registerMappings[$dataPoint->id] = $existingRegister->id;
                Log::info("Register already exists for DataPoint {$dataPoint->id}, using existing register {$existingRegister->id}");
                continue;
            }
            
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
            
            $this->registerMappings[$dataPoint->id] = $registerId;
            
            Log::info("Created register {$registerId} from DataPoint {$dataPoint->id}");
        }
        
        Log::info('Register creation completed');
    }
    
    /**
     * Update readings to reference registers safely without triggering constraints.
     */
    private function updateReadingsSafely(): void
    {
        Log::info('Step 4: Updating readings to reference registers safely');
        
        if (empty($this->registerMappings)) {
            Log::info('No register mappings available');
            return;
        }
        
        $updatedCount = 0;
        
        // Process readings in batches to avoid memory issues
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $readings = DB::table('readings')
                ->whereNotNull('data_point_id')
                ->whereNull('register_id')
                ->offset($offset)
                ->limit($batchSize)
                ->get();
                
            foreach ($readings as $reading) {
                if (isset($this->registerMappings[$reading->data_point_id])) {
                    $registerId = $this->registerMappings[$reading->data_point_id];
                    
                    // Use raw SQL to avoid triggering Laravel's updated_at behavior
                    DB::statement(
                        'UPDATE readings SET register_id = ? WHERE id = ?',
                        [$registerId, $reading->id]
                    );
                    
                    $updatedCount++;
                }
            }
            
            $offset += $batchSize;
            
        } while ($readings->count() === $batchSize);
        
        Log::info("Updated {$updatedCount} readings to reference registers");
    }
    
    /**
     * Verify data integrity after migration.
     */
    private function verifyDataIntegrity(): void
    {
        Log::info('Step 5: Verifying data integrity');
        
        $stats = [
            'datapoints' => DB::table('data_points')->count(),
            'datapoints_with_device' => DB::table('data_points')->whereNotNull('device_id')->count(),
            'devices' => DB::table('devices')->count(),
            'registers' => DB::table('registers')->count(),
            'readings' => DB::table('readings')->count(),
            'readings_with_register' => DB::table('readings')->whereNotNull('register_id')->count(),
        ];
        
        Log::info("Migration Results:");
        Log::info("- DataPoints: {$stats['datapoints']} (with device_id: {$stats['datapoints_with_device']})");
        Log::info("- Devices: {$stats['devices']}");
        Log::info("- Registers: {$stats['registers']}");
        Log::info("- Readings: {$stats['readings']} (with register_id: {$stats['readings_with_register']})");
        
        // Check for issues
        $issues = [];
        
        if ($stats['datapoints_with_device'] < $stats['datapoints']) {
            $issues[] = ($stats['datapoints'] - $stats['datapoints_with_device']) . ' DataPoints without device_id';
        }
        
        $devicesWithoutRegisters = DB::table('devices')
            ->leftJoin('registers', 'devices.id', '=', 'registers.device_id')
            ->whereNull('registers.id')
            ->count();
            
        if ($devicesWithoutRegisters > 0) {
            $issues[] = "{$devicesWithoutRegisters} devices without registers";
        }
        
        if (empty($issues)) {
            Log::info('✓ Data integrity verification passed - migration completed successfully');
        } else {
            Log::warning('⚠ Data integrity issues found: ' . implode(', ', $issues));
        }
    }
    
    /**
     * Helper methods for determining device characteristics.
     */
    private function getDeviceType(?string $label): string
    {
        if (empty($label)) return 'energy';
        
        $label = strtolower($label);
        
        if (str_contains($label, 'water')) return 'water';
        if (str_contains($label, 'control')) return 'control';
        
        return 'energy';
    }
    
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
    
    private function getDeviceName(?string $label, int $counter): string
    {
        if (empty($label)) {
            return 'Device_' . $counter;
        }
        
        $name = str_replace('_', ' ', $label);
        $name = ucwords($name);
        
        if (stripos($label, 'total') !== false) {
            return 'Energy Meter ' . $counter;
        }
        
        return $name . ' Device';
    }
    
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
     * Reverse the migrations with complete rollback.
     */
    public function down(): void
    {
        Log::info('Rolling back complete data migration');
        
        try {
            // Step 1: Remove register_id from readings
            DB::table('readings')->update(['register_id' => null]);
            Log::info('Cleared register_id from readings');
            
            // Step 2: Remove device_id from data_points
            DB::table('data_points')->update(['device_id' => null]);
            Log::info('Cleared device_id from data_points');
            
            // Step 3: Delete registers
            $deletedRegisters = DB::table('registers')->delete();
            Log::info("Deleted {$deletedRegisters} registers");
            
            // Step 4: Delete devices
            $deletedDevices = DB::table('devices')->delete();
            Log::info("Deleted {$deletedDevices} devices");
            
            Log::info('Complete rollback finished successfully');
            
        } catch (\Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private array $deviceMappings = [];
    private array $registerMappings = [];
};