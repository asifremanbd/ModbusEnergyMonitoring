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
        Schema::table('data_points', function (Blueprint $table) {
            $table->string('application')->default('monitoring')->after('label');
            $table->string('unit')->nullable()->after('application');
            $table->string('load_type')->nullable()->after('unit');
        });
        
        // Backfill existing data with defaults
        DB::table('data_points')->update([
            'application' => 'monitoring',
            'unit' => 'kWh'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_points', function (Blueprint $table) {
            $table->dropColumn(['application', 'unit', 'load_type']);
        });
    }
};