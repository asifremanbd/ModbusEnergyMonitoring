<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update readings table to reference registers instead of data_points.
     */
    public function up(): void
    {
        Log::info('Starting readings table update to reference registers');
        
        // Step 1: Update readings to reference registers
        $this->updateReadingsToReferenceRegisters();
        
        // Step 2: Verify data integrity
        $this->verifyDataIntegrity();
        
        Log::info('Readings table update completed successfully');
    }

    /**
     * Update readings to reference registers instead of data_points.
     */
    private function updateReadingsToReferenceRegisters(): void
    {
        Log::info('Updating readings to reference registers');
        
        // Get all readings that have data_point_id but no register_id
        $readingsToUpdate = DB::table('readings')
            ->whereNotNull('data_point_id')
            ->whereNull('register_id')
            ->get();
            
        if ($readingsToUpdate->isEmpty()) {
            Log::info('No readings need updating');
            return;
        }
        
        $updatedCount = 0;
        
        foreach ($readingsToUpdate as $reading) {
            // Find the corresponding register for this data_point
            $dataPoint = DB::table('data_points')
                ->where('id', $reading->data_point_id)
                ->first();
                
            if (!$dataPoint || !$dataPoint->device_id) {
                Log::warning("DataPoint {$reading->data_point_id} not found or has no device_id");
                continue;
            }
            
            // Find the register that corresponds to this data_point
            $register = DB::table('registers')
                ->where('device_id', $dataPoint->device_id)
                ->where('register_address', $dataPoint->register_address)
                ->where('function', $dataPoint->modbus_function)
                ->first();
                
            if (!$register) {
                Log::warning("No register found for DataPoint {$reading->data_point_id}");
                continue;
            }
            
            // Update the reading to reference the register (without updating timestamp to avoid constraint issues)
            DB::table('readings')
                ->where('id', $reading->id)
                ->update([
                    'register_id' => $register->id,
                ]);
                
            $updatedCount++;
        }
        
        Log::info("Updated {$updatedCount} readings to reference registers");
    }
    
    /**
     * Verify data integrity after migration.
     */
    private function verifyDataIntegrity(): void
    {
        Log::info('Verifying data integrity');
        
        // Count records
        $dataPointsCount = DB::table('data_points')->count();
        $devicesCount = DB::table('devices')->count();
        $registersCount = DB::table('registers')->count();
        $readingsCount = DB::table('readings')->count();
        $readingsWithRegisterCount = DB::table('readings')->whereNotNull('register_id')->count();
        $dataPointsWithDeviceCount = DB::table('data_points')->whereNotNull('device_id')->count();
        
        Log::info("Data integrity summary:");
        Log::info("- DataPoints: {$dataPointsCount}");
        Log::info("- DataPoints with device_id: {$dataPointsWithDeviceCount}");
        Log::info("- Devices: {$devicesCount}");
        Log::info("- Registers: {$registersCount}");
        Log::info("- Total Readings: {$readingsCount}");
        Log::info("- Readings with register_id: {$readingsWithRegisterCount}");
        
        // Verify relationships
        $orphanedDataPoints = DB::table('data_points')
            ->whereNull('device_id')
            ->count();
            
        $devicesWithoutRegisters = DB::table('devices')
            ->leftJoin('registers', 'devices.id', '=', 'registers.device_id')
            ->whereNull('registers.id')
            ->count();
            
        $readingsWithoutRegister = DB::table('readings')
            ->whereNotNull('data_point_id')
            ->whereNull('register_id')
            ->count();
        
        if ($orphanedDataPoints > 0) {
            Log::warning("Found {$orphanedDataPoints} DataPoints without device_id");
        }
        
        if ($devicesWithoutRegisters > 0) {
            Log::warning("Found {$devicesWithoutRegisters} devices without registers");
        }
        
        if ($readingsWithoutRegister > 0) {
            Log::warning("Found {$readingsWithoutRegister} readings without register_id");
        }
        
        if ($orphanedDataPoints === 0 && $devicesWithoutRegisters === 0 && $readingsWithoutRegister === 0) {
            Log::info('✓ Data integrity verification passed - all relationships are properly established');
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::info('Rolling back readings table updates');
        
        // Remove register_id references from readings
        DB::table('readings')->update(['register_id' => null]);
        
        Log::info('Readings rollback completed');
    }
};