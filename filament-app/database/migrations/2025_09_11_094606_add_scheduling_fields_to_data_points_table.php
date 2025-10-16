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
            $table->boolean('schedule_enabled')->default(false)->after('is_enabled');
            $table->json('schedule_days')->nullable()->after('schedule_enabled');
            $table->time('schedule_start_time')->nullable()->after('schedule_days');
            $table->time('schedule_end_time')->nullable()->after('schedule_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function reverse(): void
    {
        Schema::table('data_points', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_enabled',
                'schedule_days',
                'schedule_start_time',
                'schedule_end_time'
            ]);
        });
    }
};