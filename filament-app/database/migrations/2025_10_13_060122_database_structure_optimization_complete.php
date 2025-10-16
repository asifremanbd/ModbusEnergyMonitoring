<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Update Device model field names
        Schema::table('devices', function (Blueprint $table) {
            // Check if columns exist before renaming
            if (Schema::hasColumn('devices', 'name')) {
                $table->renameColumn('name', 'device_name');
            }
            if (Schema::hasColumn('devices', 'is_active')) {
                $table->renameColumn('is_active', 'enabled');
            }
        });

        // Step 2: Create the new registers table with proper field mappings
        if (!Schema::hasTable('registers')) {
            Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->string('technical_label'); // Renamed from 'label'
            $table->unsignedTinyInteger('function'); // Renamed from 'modbus_function'
            $table->unsignedInteger('register_address');
            $table->enum('data_type', [
                'int16', 'uint16', 'int32', 'uint32', 
                'float32', 'float64'
            ])->default('float32');
            $table->enum('byte_order', [
                'big_endian', 'little_endian', 'word_swap', 'byte_swap'
            ])->default('big_endian');
            $table->decimal('scale', 10, 6)->default(1.0); // Renamed from 'scale_factor'
            $table->unsignedTinyInteger('count')->default(1); // Renamed from 'register_count'
            $table->boolean('enabled')->default(true); // Renamed from 'is_enabled'
            
            // Additional fields for write operations (from DataPoint)
            $table->unsignedTinyInteger('write_function')->nullable();
            $table->unsignedInteger('write_register')->nullable();
            $table->decimal('on_value', 10, 6)->nullable();
            $table->decimal('off_value', 10, 6)->nullable();
            $table->boolean('invert')->default(false);
            $table->boolean('schedulable')->default(false); // Renamed from 'is_schedulable'
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['device_id', 'enabled'], 'idx_device_enabled_registers');
            $table->index(['device_id', 'function', 'register_address'], 'idx_device_modbus_address');
            });
        }
        
        // Step 3: Migrate data from data_points to registers
        DB::statement('
            INSERT INTO registers (
                device_id, technical_label, function, register_address, 
                data_type, byte_order, scale, count, enabled,
                write_function, write_register, on_value, off_value, 
                invert, schedulable, created_at, updated_at
            )
            SELECT 
                device_id, 
                COALESCE(label, "Register") as technical_label,
                modbus_function as function,
                register_address,
                data_type,
                CASE 
                    WHEN byte_order = "word_swapped" THEN "word_swap"
                    ELSE byte_order
                END as byte_order,
                scale_factor as scale,
                register_count as count,
                is_enabled as enabled,
                write_function,
                write_register,
                on_value,
                off_value,
                invert,
                COALESCE(is_schedulable, false) as schedulable,
                created_at,
                updated_at
            FROM data_points 
            WHERE device_id IS NOT NULL
        ');

        // Step 4: Add performance indexes to existing tables
        // Note: Gateway indexes already exist from previous migrations

        Schema::table('devices', function (Blueprint $table) {
            // Index for device type queries
            $table->index(['device_type', 'enabled'], 'idx_device_type_enabled');
            // Index for load category queries
            $table->index(['load_category'], 'idx_device_load_category');
            // Composite index for gateway device queries
            $table->index(['gateway_id', 'device_type', 'enabled'], 'idx_gateway_device_type');
        });

        // Step 5: Update readings table to reference registers
        Schema::table('readings', function (Blueprint $table) {
            // Add register_id foreign key
            $table->foreignId('register_id')->nullable()->after('data_point_id')->constrained('registers')->onDelete('cascade');
            
            // Index for register readings
            $table->index(['register_id', 'read_at'], 'idx_register_readings');
        });

        // Step 6: Migrate readings to reference registers
        DB::statement('
            UPDATE readings r
            INNER JOIN registers reg ON reg.device_id = (
                SELECT device_id FROM data_points WHERE id = r.data_point_id
            ) AND reg.register_address = (
                SELECT register_address FROM data_points WHERE id = r.data_point_id
            )
            SET r.register_id = reg.id
            WHERE r.data_point_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove register_id from readings
        Schema::table('readings', function (Blueprint $table) {
            $table->dropForeign(['register_id']);
            $table->dropIndex('idx_register_readings');
            $table->dropColumn('register_id');
        });

        // Remove indexes from devices
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_device_type_enabled');
            $table->dropIndex('idx_device_load_category');
            $table->dropIndex('idx_gateway_device_type');
        });

        // Note: Gateway indexes maintained by original migration

        // Drop registers table
        Schema::dropIfExists('registers');

        // Restore device field names
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'device_name')) {
                $table->renameColumn('device_name', 'name');
            }
            if (Schema::hasColumn('devices', 'enabled')) {
                $table->renameColumn('enabled', 'is_active');
            }
        });
    }
};