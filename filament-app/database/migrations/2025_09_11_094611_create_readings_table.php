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
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_point_id')->constrained()->onDelete('cascade');
            $table->text('raw_value')->nullable();
            $table->decimal('scaled_value', 15, 6)->nullable();
            $table->enum('quality', ['good', 'bad', 'uncertain'])->default('good');
            $table->timestamp('read_at');
            $table->timestamps();
            
            // Indexes for time-series queries
            $table->index(['data_point_id', 'read_at'], 'idx_point_time');
            $table->index(['read_at'], 'idx_recent_readings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
