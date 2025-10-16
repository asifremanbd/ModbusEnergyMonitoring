<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration handles the complete data migration from the current DataPoint structure
     * to the new Device -> Register hierarchical structure.
     */
    public function up(): void
    {
        Log::info('Starting data migration from DataPoints to Device-Register structure');
        
        // Step 1: Create devices from existing DataPoint data
        $this->migrateDataPointsToDevices();
        
        // Step 2: Update DataPoint records to reference new Device records
        $this->updateDataPointsWithDeviceReferences();
        
        // Step 3: Migrate DataPoint data to Register model
        $this->migrateDataPointsToRegisters();
        
        // Step 4: Update readings to reference registers
        $this->updateReadingsToReferenceRegisters();
        
        // Step 5: Verify data integrity
        $this->verifyDataIntegrity();
        
        Log::info('Data migration completed successfully');
    }

    /**
     * Create Device records from existing DataPoint data.
     * Creates one device per datapoint since each represents a separate device.
     */
    private function migrateDataPointsToDevices(): void
    {
        Log::info('Step 1: Creating devices from DataPoint data');
        
        // Get all DataPoints that don't have device_id assigned
        $dataPointsWithoutDevices = DB::table('data_points')
            ->whereNull('device_id')
            ->get();
            
        if ($dataPointsWithoutDevices->isEmpty()) {
            Log::info('No DataPoints without device_id found, skipping device creation');
            return;
        }
        
        $createdDevices = [];
        $deviceCounter = 1;
        
        // Create one device per datapoint since each represents a separate device
        foreach ($dataPointsWithoutDevices as $dataPoint) {
            // Determine device type based on label patterns
            $deviceType = $this->determineDeviceTypeFromLabel($dataPoint->label);
            $loadCategory = $this->determineLoadCategoryFromLabel($dataPoint->label);
            
            // Create device name from label or generate one
            $deviceName = $this->generateDeviceNameFromLabel($dataPoint->label, $deviceCounter);
            
            // Insert device record using current table structure
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
            
            // Store mapping for later use (one datapoint per device)
            $createdDevices[$dataPoint->id] = [
                'device_id' => $deviceId,
                'datapoint_id' => $dataPoint->id,
                'device_name' => $deviceName,
                'device_type' => $deviceType,
            ];
            
            Log::info("Created device: {$deviceName} (ID: {$deviceId}) for DataPoint {$dataPoint->id} on gateway {$dataPoint->gateway_id}");
            $deviceCounter++;
        }
        
        // Store device mappings for next steps
        $this->deviceMappings = $createdDevices;
        
        Log::info('Device creation completed. Created ' . count($createdDevices) . ' devices');
    }
    
    /**
     * Update DataPoint records to reference the newly created Device records.
     */
    private function updateDataPointsWithDeviceReferences(): void
    {
        Log::info('Step 2: Updating DataPoints with device references');
        
        if (empty($this->deviceMappings)) {
            Log::info('No device mappings found, skipping DataPoint updates');
            return;
        }
        
        $updatedCount = 0;
        
        // Update each DataPoint with its corresponding device_id
        foreach ($this->deviceMappings as $dataPointId => $deviceInfo) {
            $deviceId = $deviceInfo['device_id'];
            
            // Update the specific DataPoint to reference its device
            $updated = DB::table('data_points')
                ->where('id', $dataPointId)
                ->update([
                    'device_id' => $deviceId,
                    'updated_at' => now(),
                ]);
                
            $updatedCount += $updated;
            
            Log::info("Updated DataPoint {$dataPointId} to reference device {$deviceId}");
        }
        
        Log::info("DataPoint updates completed. Updated {$updatedCount} records");
    }
    
    /**
     * Migrate DataPoint data to Register model with proper field mappings.
     */
    private function migrateDataPointsToRegisters(): void
    {
        Log::info('Step 3: Migrating DataPoints to Registers');
        
        // Get all DataPoints that now have device_id assigned
        $dataPointsToMigrate = DB::table('data_points')
            ->whereNotNull('device_id')
            ->get();
            
        if ($dataPointsToMigrate->isEmpty()) {
            Log::info('No DataPoints with device_id found for Register migration');
            return;
        }
        
        $migratedCount = 0;
        
        foreach ($dataPointsToMigrate as $dataPoint) {
            // Check if register already exists to avoid duplicates
            $existingRegister = DB::table('registers')
                ->where('device_id', $dataPoint->device_id)
                ->where('register_address', $dataPoint->register_address)
                ->where('function', $dataPoint->modbus_function)
                ->first();
                
            if ($existingRegister) {
                Log::info("Register already exists for device {$dataPoint->device_id}, address {$dataPoint->register_address}");
                continue;
            }
            
            // Map DataPoint fields to Register fields
            $registerData = [
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
            ];
            
            // Insert register record
            $registerId = DB::table('registers')->insertGetId($registerData);
            
            // Store mapping for readings update
            $this->registerMappings[$dataPoint->id] = $registerId;
            
            $migratedCount++;
            
            Log::info("Migrated DataPoint {$dataPoint->id} to Register {$registerId}");
        }
        
        Log::info("Register migration completed. Migrated {$migratedCount} registers");
    }
    
    /**
     * Update readings to reference registers instead of data_points.
     */
    private function updateReadingsToReferenceRegisters(): void
    {
        Log::info('Step 4: Updating readings to reference registers');
        
        if (empty($this->registerMappings)) {
            Log::info('No register mappings found, skipping readings update');
            return;
        }
        
        $updatedCount = 0;
        
        foreach ($this->registerMappings as $dataPointId => $registerId) {
            $updated = DB::table('readings')
                ->where('data_point_id', $dataPointId)
                ->update([
                    'register_id' => $registerId,
                    'updated_at' => now(),
                ]);
                
            $updatedCount += $updated;
        }
        
        Log::info("Readings update completed. Updated {$updatedCount} readings");
    }
    
    /**
     * Verify data integrity after migration.
     */
    private function verifyDataIntegrity(): void
    {
        Log::info('Step 5: Verifying data integrity');
        
        // Count records in each table
        $dataPointsCount = DB::table('data_points')->count();
        $devicesCount = DB::table('devices')->count();
        $registersCount = DB::table('registers')->count();
        $readingsCount = DB::table('readings')->count();
        $readingsWithRegisterCount = DB::table('readings')->whereNotNull('register_id')->count();
        
        Log::info("Data integrity check:");
        Log::info("- DataPoints: {$dataPointsCount}");
        Log::info("- Devices: {$devicesCount}");
        Log::info("- Registers: {$registersCount}");
        Log::info("- Total Readings: {$readingsCount}");
        Log::info("- Readings with register_id: {$readingsWithRegisterCount}");
        
        // Verify all DataPoints have device_id
        $dataPointsWithoutDevice = DB::table('data_points')->whereNull('device_id')->count();
        if ($dataPointsWithoutDevice > 0) {
            Log::warning("Found {$dataPointsWithoutDevice} DataPoints without device_id");
        }
        
        // Verify all devices have at least one register
        $devicesWithoutRegisters = DB::table('devices')
            ->leftJoin('registers', 'devices.id', '=', 'registers.device_id')
            ->whereNull('registers.id')
            ->count();
            
        if ($devicesWithoutRegisters > 0) {
            Log::warning("Found {$devicesWithoutRegisters} devices without registers");
        }
        
        Log::info('Data integrity verification completed');
    }
    
    /**
     * Determine device type based on a single DataPoint label.
     */
    private function determineDeviceTypeFromLabel(?string $label): string
    {
        if (empty($label)) {
            return 'energy';
        }
        
        $label = strtolower($label);
        
        // Check for energy meter patterns
        if (str_contains($label, 'kwh') || str_contains($label, 'energy') || str_contains($label, 'power') || str_contains($label, 'total')) {
            return 'energy';
        }
        
        // Check for water meter patterns
        if (str_contains($label, 'water') || str_contains($label, 'flow') || str_contains($label, 'liter')) {
            return 'water';
        }
        
        // Check for control device patterns
        if (str_contains($label, 'control') || str_contains($label, 'switch') || str_contains($label, 'relay')) {
            return 'control';
        }
        
        // Default to energy meter for unknown types
        return 'energy';
    }
    
    /**
     * Determine load category based on a single DataPoint label.
     */
    private function determineLoadCategoryFromLabel(?string $label): string
    {
        if (empty($label)) {
            return 'other';
        }
        
        $label = strtolower($label);
        
        // Check for specific load category patterns based on the enum values:
        // 'mains', 'ac', 'sockets', 'heater', 'lighting', 'water', 'solar', 'generator', 'other'
        
        if (str_contains($label, 'main') || str_contains($label, 'total')) {
            return 'mains';
        }
        
        if (str_contains($label, 'ac') || str_contains($label, 'air')) {
            return 'ac';
        }
        
        if (str_contains($label, 'socket') || str_contains($label, 'outlet') || str_contains($label, 'plug')) {
            return 'sockets';
        }
        
        if (str_contains($label, 'heat') || str_contains($label, 'radiator')) {
            return 'heater';
        }
        
        if (str_contains($label, 'light') || str_contains($label, 'lamp') || str_contains($label, 'led')) {
            return 'lighting';
        }
        
        if (str_contains($label, 'water') || str_contains($label, 'flow')) {
            return 'water';
        }
        
        if (str_contains($label, 'solar') || str_contains($label, 'pv')) {
            return 'solar';
        }
        
        if (str_contains($label, 'generator') || str_contains($label, 'gen')) {
            return 'generator';
        }
        
        // Default to other
        return 'other';
    }
    
    /**
     * Generate device name from a single DataPoint label.
     */
    private function generateDeviceNameFromLabel(?string $label, int $counter): string
    {
        if (empty($label)) {
            return 'Device_' . $counter;
        }
        
        // Clean up the label to create a meaningful device name
        $cleanLabel = str_replace('_', ' ', $label);
        $cleanLabel = ucwords($cleanLabel);
        
        // If label is just "Total kWh" or similar, make it more descriptive
        if (stripos($label, 'total') !== false) {
            return 'Energy Meter ' . $counter;
        }
        
        return $cleanLabel . ' Device';
    }
    
    /**
     * Map old byte order values to new enum values.
     */
    private function mapByteOrder(?string $oldByteOrder): string
    {
        if (empty($oldByteOrder)) {
            return 'big_endian';
        }
        
        return match(strtolower($oldByteOrder)) {
            'word_swapped' => 'word_swap',
            'little_endian' => 'little_endian',
            'byte_swap' => 'byte_swap',
            'big_endian' => 'big_endian',
            default => 'big_endian',
        };
    }
    
    /**
     * Reverse the migrations.
     * 
     * This provides a rollback mechanism to restore the original structure.
     */
    public function down(): void
    {
        Log::info('Rolling back data migration');
        
        // Step 1: Remove register_id references from readings
        DB::table('readings')->update(['register_id' => null]);
        
        // Step 2: Remove device_id references from data_points
        DB::table('data_points')->update(['device_id' => null]);
        
        // Step 3: Delete all registers (they will be recreated from data_points if needed)
        DB::table('registers')->delete();
        
        // Step 4: Delete all devices (they will be recreated if needed)
        DB::table('devices')->delete();
        
        Log::info('Data migration rollback completed');
    }
    
    // Properties to store mappings between migration steps
    private array $deviceMappings = [];
    private array $registerMappings = [];
};