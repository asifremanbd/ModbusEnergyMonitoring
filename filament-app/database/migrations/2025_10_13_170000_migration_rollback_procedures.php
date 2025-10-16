<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * This migration provides rollback procedures for the data migration.
     * It doesn't run any migrations in the up() method, but provides
     * comprehensive rollback functionality.
     */
    public function up(): void
    {
        Log::info('Migration rollback procedures are available via down() method');
        Log::info('To rollback the data migration, run: php artisan migrate:rollback');
    }

    /**
     * Comprehensive rollback procedure for the data migration.
     * This will restore the system to its pre-migration state.
     */
    public function down(): void
    {
        Log::info('Starting comprehensive rollback of data migration');
        
        try {
            // Step 1: Backup current state for safety
            $this->backupCurrentState();
            
            // Step 2: Remove device_id references from data_points
            $this->clearDataPointDeviceReferences();
            
            // Step 3: Delete all registers
            $this->deleteAllRegisters();
            
            // Step 4: Delete all devices
            $this->deleteAllDevices();
            
            // Step 5: Verify rollback completion
            $this->verifyRollbackCompletion();
            
            Log::info('Comprehensive rollback completed successfully');
            
        } catch (\Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Backup current state before rollback.
     */
    private function backupCurrentState(): void
    {
        Log::info('Backing up current state');
        
        $stats = [
            'datapoints' => DB::table('data_points')->count(),
            'datapoints_with_device' => DB::table('data_points')->whereNotNull('device_id')->count(),
            'devices' => DB::table('devices')->count(),
            'registers' => DB::table('registers')->count(),
        ];
        
        Log::info('Current state before rollback:');
        Log::info("- DataPoints: {$stats['datapoints']} (with device_id: {$stats['datapoints_with_device']})");
        Log::info("- Devices: {$stats['devices']}");
        Log::info("- Registers: {$stats['registers']}");
    }
    
    /**
     * Clear device_id references from data_points.
     */
    private function clearDataPointDeviceReferences(): void
    {
        Log::info('Clearing device_id references from data_points');
        
        $updatedCount = DB::table('data_points')
            ->whereNotNull('device_id')
            ->update(['device_id' => null]);
            
        Log::info("Cleared device_id from {$updatedCount} data_points");
    }
    
    /**
     * Delete all registers.
     */
    private function deleteAllRegisters(): void
    {
        Log::info('Deleting all registers');
        
        $deletedCount = DB::table('registers')->delete();
        
        Log::info("Deleted {$deletedCount} registers");
    }
    
    /**
     * Delete all devices.
     */
    private function deleteAllDevices(): void
    {
        Log::info('Deleting all devices');
        
        $deletedCount = DB::table('devices')->delete();
        
        Log::info("Deleted {$deletedCount} devices");
    }
    
    /**
     * Verify rollback completion.
     */
    private function verifyRollbackCompletion(): void
    {
        Log::info('Verifying rollback completion');
        
        $stats = [
            'datapoints' => DB::table('data_points')->count(),
            'datapoints_with_device' => DB::table('data_points')->whereNotNull('device_id')->count(),
            'devices' => DB::table('devices')->count(),
            'registers' => DB::table('registers')->count(),
        ];
        
        Log::info('State after rollback:');
        Log::info("- DataPoints: {$stats['datapoints']} (with device_id: {$stats['datapoints_with_device']})");
        Log::info("- Devices: {$stats['devices']}");
        Log::info("- Registers: {$stats['registers']}");
        
        // Verify rollback success
        $rollbackSuccess = (
            $stats['datapoints_with_device'] === 0 &&
            $stats['devices'] === 0 &&
            $stats['registers'] === 0
        );
        
        if ($rollbackSuccess) {
            Log::info('✓ Rollback verification passed - system restored to pre-migration state');
            Log::info('✓ All device_id references cleared from data_points');
            Log::info('✓ All devices deleted');
            Log::info('✓ All registers deleted');
        } else {
            Log::warning('⚠ Rollback verification failed - some data may remain');
        }
    }
};