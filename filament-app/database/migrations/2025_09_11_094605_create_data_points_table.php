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
        Schema::create('data_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained()->onDelete('cascade');
            $table->string('group_name', 100);
            $table->string('label');
            $table->unsignedTinyInteger('modbus_function');
            $table->unsignedInteger('register_address');
            $table->unsignedTinyInteger('register_count')->default(2);
            $table->enum('data_type', ['int16', 'uint16', 'int32', 'uint32', 'float32', 'float64'])->default('float32');
            $table->enum('byte_order', ['big_endian', 'little_endian', 'word_swapped'])->default('word_swapped');
            $table->decimal('scale_factor', 10, 6)->default(1.0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['gateway_id', 'is_enabled'], 'idx_gateway_enabled');
            $table->index(['gateway_id', 'group_name'], 'idx_group_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_points');
    }
};
