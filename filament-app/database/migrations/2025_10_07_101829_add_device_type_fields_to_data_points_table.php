<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_points', function (Blueprint $table) {
            // Add new device type and category fields
            $table->enum('device_type', ['energy','water','control'])->default('energy')->index()->after('gateway_id');
            $table->enum('load_category', [
                'mains','ac','sockets','heater','lighting','water','solar','generator','other'
            ])->default('other')->index()->after('device_type');
            $table->string('custom_label')->nullable()->after('load_category');
            
            // Control-only fields
            $table->unsignedTinyInteger('write_function')->nullable()->after('is_enabled');
            $table->unsignedInteger('write_register')->nullable()->after('write_function');
            $table->string('on_value')->nullable()->after('write_register');
            $table->string('off_value')->nullable()->after('on_value');
            $table->boolean('invert')->default(false)->after('off_value');
            $table->boolean('is_schedulable')->default(false)->after('invert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_points', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'load_category', 
                'custom_label',
                'write_function',
                'write_register',
                'on_value',
                'off_value',
                'invert',
                'is_schedulable'
            ]);
        });
    }
};
