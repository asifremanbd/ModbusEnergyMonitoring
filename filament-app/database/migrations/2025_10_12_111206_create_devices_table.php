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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('device_type', ['energy', 'water', 'control'])->default('energy');
            $table->enum('load_category', [
                'mains', 'ac', 'sockets', 'heater', 'lighting', 
                'water', 'solar', 'generator', 'other'
            ])->default('other');
            $table->string('group_name')->default('Meter_1');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['gateway_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
