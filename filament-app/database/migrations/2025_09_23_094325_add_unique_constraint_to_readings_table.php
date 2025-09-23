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
        // First, remove duplicate readings keeping only the latest one for each data_point_id + read_at combination
        DB::statement("
            DELETE r1 FROM readings r1
            INNER JOIN readings r2 
            WHERE r1.data_point_id = r2.data_point_id 
            AND r1.read_at = r2.read_at 
            AND r1.id < r2.id
        ");
        
        Schema::table('readings', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate readings for the same data point at the same timestamp
            $table->unique(['data_point_id', 'read_at'], 'unique_datapoint_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('unique_datapoint_timestamp');
        });
    }
};
