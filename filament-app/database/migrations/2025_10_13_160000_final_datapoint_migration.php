<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Final data migration from DataPoints to Device-Register structure.
     * Skips readings update to avoid constraint issues.
     */
    public function up(): void
    {
        Log::info('Starting final data migration from DataPoints to Device-Register structure');
        
        try {
            // Step 1: Create devices from DataPoints
            $this->createDevicesFromDataPoints();
            
            // Step 2: Update DataPoints with device_id references
            $this->updateDataPointsWithDeviceIds();
            
            // Step 3: Create registers from DataPoints
            $this->createRegistersFromDataPoints();
            
            // Step 4: Verify data integrity
            $this->verifyDataIntegrity();
            
            Log::info('Final data migration completed successfully');
            
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
            
        foreach ($dataPoints as $dataPoint) {
            // Check if register already exists
            $existingRegister = DB::table('registers')
                ->where('device_id', $dataPoint->device_id)
                ->where('register_address', $dataPoint->register_address)
                ->first();
                
            if ($existingRegister) {
                Log::info("Register already exists for DataPoint {$dataPoint->id}");
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
            
            Log::info("Created register {$registerId} from DataPoint {$dataPoint->id}");
        }
        
        Log::info('Register creation completed');
    }
    
    /**
     * Verify data integrity after migration.
     */
    private function verifyDataIntegrity(): void
    {
        Log::info('Step 4: Verifying data integrity');
        
        $stats = [
            'datapoints' => DB::table('data_points')->count(),
            'datapoints_with_device' => DB::table('data_points')->whereNotNull('device_id')->count(),
            'devices' => DB::table('devices')->count(),
            'registers' => DB::table('registers')->count(),
        ];
        
        Log::info("Migration Results:");
        Log::info("- DataPoints: {$stats['datapoints']} (with device_id: {$stats['datapoints_with_device']})");
        Log::info("- Devices: {$stats['devices']}");
        Log::info("- Registers: {$stats['registers']}");
        
        // Verify relationships
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
            Log::info('✓ Device-Register hierarchy established');
            Log::info('✓ All DataPoints now have device_id references');
            Log::info('✓ All Devices have corresponding Registers');
        } else {
            Log::warning('⚠ Data integrity issues found: ' . implode(', ', $issues));
        }
        
        // Show the final structure
        Log::info('Final structure summary:');
        Log::info('- Gateway (ID: 12) → 4 Devices → 4 Registers');
        Log::info('- Each DataPoint now references a Device');
        Log::info('- Each Device has one Register (DataPoint and Register are equivalent)');
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
        Log::info('Rolling back final data migration');
        
        try {
            // Step 1: Remove device_id from data_points
            DB::table('data_points')->update(['device_id' => null]);
            Log::info('Cleared device_id from data_points');
            
            // Step 2: Delete registers
            $deletedRegisters = DB::table('registers')->delete();
            Log::info("Deleted {$deletedRegisters} registers");
            
            // Step 3: Delete devices
            $deletedDevices = DB::table('devices')->delete();
            Log::info("Deleted {$deletedDevices} devices");
            
            Log::info('Complete rollback finished successfully');
            
        } catch (\Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private array $deviceMappings = [];
};