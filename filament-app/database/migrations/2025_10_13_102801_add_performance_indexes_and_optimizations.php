<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add performance optimizations and indexing for the modular management system.
     */
    public function up(): void
    {
        // Add indexes for Gateway table
        Schema::table('gateways', function (Blueprint $table) {
            // Index for active gateway queries
            if (!$this->indexExists('gateways', 'idx_gateways_active')) {
                $table->index(['is_active'], 'idx_gateways_active');
            }
            
            // Index for IP/port uniqueness checks (performance optimization)
            if (!$this->indexExists('gateways', 'idx_gateways_ip_port')) {
                $table->index(['ip_address', 'port'], 'idx_gateways_ip_port');
            }
            
            // Index for last_seen_at queries (status calculations)
            if (!$this->indexExists('gateways', 'idx_gateways_last_seen')) {
                $table->index(['last_seen_at'], 'idx_gateways_last_seen');
            }
            
            // Composite index for active gateways with recent activity
            if (!$this->indexExists('gateways', 'idx_gateways_active_recent')) {
                $table->index(['is_active', 'last_seen_at'], 'idx_gateways_active_recent');
            }
        });

        // Add indexes for Device table
        Schema::table('devices', function (Blueprint $table) {
            // Index for gateway-device relationship queries
            if (!$this->indexExists('devices', 'idx_devices_gateway_enabled')) {
                $table->index(['gateway_id', 'enabled'], 'idx_devices_gateway_enabled');
            }
            
            // Index for device type filtering
            if (!$this->indexExists('devices', 'idx_devices_type_category')) {
                $table->index(['device_type', 'load_category'], 'idx_devices_type_category');
            }
            
            // Index for device name searches within gateway
            if (!$this->indexExists('devices', 'idx_devices_gateway_name')) {
                $table->index(['gateway_id', 'device_name'], 'idx_devices_gateway_name');
            }
            
            // Composite index for enabled devices by type
            if (!$this->indexExists('devices', 'idx_devices_enabled_type')) {
                $table->index(['enabled', 'device_type'], 'idx_devices_enabled_type');
            }
        });

        // Add indexes for Register table
        Schema::table('registers', function (Blueprint $table) {
            // Index for device-register relationship queries
            if (!$this->indexExists('registers', 'idx_registers_device_enabled')) {
                $table->index(['device_id', 'enabled'], 'idx_registers_device_enabled');
            }
            
            // Index for Modbus function queries
            if (!$this->indexExists('registers', 'idx_registers_function')) {
                $table->index(['function'], 'idx_registers_function');
            }
            
            // Index for register address uniqueness within device
            if (!$this->indexExists('registers', 'idx_registers_device_address')) {
                $table->index(['device_id', 'register_address'], 'idx_registers_device_address');
            }
            
            // Index for data type queries
            if (!$this->indexExists('registers', 'idx_registers_data_type')) {
                $table->index(['data_type'], 'idx_registers_data_type');
            }
            
            // Composite index for enabled registers by function
            if (!$this->indexExists('registers', 'idx_registers_enabled_function')) {
                $table->index(['enabled', 'function'], 'idx_registers_enabled_function');
            }
        });

        // Add indexes for Readings table (if it exists)
        if (Schema::hasTable('readings')) {
            Schema::table('readings', function (Blueprint $table) {
                // Index for register readings queries
                if (!$this->indexExists('readings', 'idx_readings_register_time')) {
                    $table->index(['register_id', 'read_at'], 'idx_readings_register_time');
                }
                
                // Index for time-based queries
                if (!$this->indexExists('readings', 'idx_readings_read_at')) {
                    $table->index(['read_at'], 'idx_readings_read_at');
                }
                
                // Index for data_point_id (legacy support)
                if (Schema::hasColumn('readings', 'data_point_id') && !$this->indexExists('readings', 'idx_readings_datapoint_time')) {
                    $table->index(['data_point_id', 'read_at'], 'idx_readings_datapoint_time');
                }
            });
        }

        // Add indexes for DataPoints table (legacy support)
        if (Schema::hasTable('data_points')) {
            Schema::table('data_points', function (Blueprint $table) {
                // Index for gateway relationship
                if (!$this->indexExists('data_points', 'idx_datapoints_gateway')) {
                    $table->index(['gateway_id'], 'idx_datapoints_gateway');
                }
                
                // Index for device relationship
                if (Schema::hasColumn('data_points', 'device_id') && !$this->indexExists('data_points', 'idx_datapoints_device')) {
                    $table->index(['device_id'], 'idx_datapoints_device');
                }
                
                // Index for enabled data points
                if (Schema::hasColumn('data_points', 'is_enabled') && !$this->indexExists('data_points', 'idx_datapoints_enabled')) {
                    $table->index(['is_enabled'], 'idx_datapoints_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from DataPoints table
        if (Schema::hasTable('data_points')) {
            Schema::table('data_points', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_datapoints_gateway');
                $this->dropIndexIfExists($table, 'idx_datapoints_device');
                $this->dropIndexIfExists($table, 'idx_datapoints_enabled');
            });
        }

        // Remove indexes from Readings table
        if (Schema::hasTable('readings')) {
            Schema::table('readings', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_readings_register_time');
                $this->dropIndexIfExists($table, 'idx_readings_read_at');
                $this->dropIndexIfExists($table, 'idx_readings_datapoint_time');
            });
        }

        // Remove indexes from Register table
        Schema::table('registers', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'idx_registers_device_enabled');
            $this->dropIndexIfExists($table, 'idx_registers_function');
            $this->dropIndexIfExists($table, 'idx_registers_device_address');
            $this->dropIndexIfExists($table, 'idx_registers_data_type');
            $this->dropIndexIfExists($table, 'idx_registers_enabled_function');
        });

        // Remove indexes from Device table
        Schema::table('devices', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'idx_devices_gateway_enabled');
            $this->dropIndexIfExists($table, 'idx_devices_type_category');
            $this->dropIndexIfExists($table, 'idx_devices_gateway_name');
            $this->dropIndexIfExists($table, 'idx_devices_enabled_type');
        });

        // Remove indexes from Gateway table
        Schema::table('gateways', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'idx_gateways_active');
            $this->dropIndexIfExists($table, 'idx_gateways_ip_port');
            $this->dropIndexIfExists($table, 'idx_gateways_last_seen');
            $this->dropIndexIfExists($table, 'idx_gateways_active_recent');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Index doesn't exist, ignore the error
        }
    }
};