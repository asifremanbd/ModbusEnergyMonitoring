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
            // Add device_id foreign key
            $table->foreignId('device_id')->nullable()->after('gateway_id')->constrained()->onDelete('cascade');
            
            // Remove device-level fields that will now be on the Device model
            $table->dropColumn(['device_type', 'load_category', 'custom_label', 'group_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_points', function (Blueprint $table) {
            // Add back the device-level fields
            $table->string('device_type')->default('energy')->after('gateway_id');
            $table->string('load_category')->default('other')->after('device_type');
            $table->string('custom_label')->nullable()->after('load_category');
            $table->string('group_name')->default('Meter_1')->after('custom_label');
            
            // Remove device_id foreign key
            $table->dropForeign(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
