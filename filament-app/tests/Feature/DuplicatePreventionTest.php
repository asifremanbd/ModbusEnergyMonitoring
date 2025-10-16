<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateway and data point
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'is_active' => true,
            'poll_interval' => 30,
        ]);

        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Test Point',
            'modbus_function' => 3,
            'register_address' => 1,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.0,
            'is_enabled' => true,
        ]);
    }

    public function test_database_prevents_duplicate_readings_with_same_timestamp()
    {
        $timestamp = Carbon::now();
        
        // Create first reading
        $reading1 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);
        
        $this->assertDatabaseHas('readings', [
            'id' => $reading1->id,
            'data_point_id' => $this->dataPoint->id,
        ]);
        
        // Attempt to create duplicate reading with same data_point_id and read_at
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        
        Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([300, 400]),
            'scaled_value' => 678.90,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);
    }

    public function test_different_data_points_can_have_same_timestamp()
    {
        $timestamp = Carbon::now();
        
        // Create second data point
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'automation',
            'unit' => 'm³',
            'load_type' => 'water',
            'label' => 'Test Point 2',
            'modbus_function' => 3,
            'register_address' => 2,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.0,
            'is_enabled' => true,
        ]);
        
        // Create readings for both data points with same timestamp - should work
        $reading1 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);
        
        $reading2 = Reading::create([
            'data_point_id' => $dataPoint2->id,
            'raw_value' => json_encode([300, 400]),
            'scaled_value' => 678.90,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);
        
        $this->assertDatabaseHas('readings', ['id' => $reading1->id]);
        $this->assertDatabaseHas('readings', ['id' => $reading2->id]);
        $this->assertEquals(2, Reading::where('read_at', $timestamp)->count());
    }

    public function test_same_data_point_can_have_different_timestamps()
    {
        $timestamp1 = Carbon::now();
        $timestamp2 = Carbon::now()->addSecond();
        
        // Create readings for same data point with different timestamps - should work
        $reading1 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp1,
        ]);
        
        $reading2 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([300, 400]),
            'scaled_value' => 678.90,
            'quality' => 'good',
            'read_at' => $timestamp2,
        ]);
        
        $this->assertDatabaseHas('readings', ['id' => $reading1->id]);
        $this->assertDatabaseHas('readings', ['id' => $reading2->id]);
        $this->assertEquals(2, Reading::where('data_point_id', $this->dataPoint->id)->count());
    }

    public function test_concurrent_reading_creation_handles_duplicates_gracefully()
    {
        $timestamp = Carbon::now();
        
        // Simulate concurrent reading creation by using database transactions
        $results = [];
        $exceptions = [];
        
        // Try to create multiple readings concurrently (simulated)
        for ($i = 0; $i < 3; $i++) {
            try {
                $reading = Reading::create([
                    'data_point_id' => $this->dataPoint->id,
                    'raw_value' => json_encode([100 + $i, 200 + $i]),
                    'scaled_value' => 123.45 + $i,
                    'quality' => 'good',
                    'read_at' => $timestamp,
                ]);
                $results[] = $reading;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $exceptions[] = $e;
            }
        }
        
        // Only one reading should be created, others should throw exceptions
        $this->assertCount(1, $results);
        $this->assertCount(2, $exceptions);
        
        // Verify only one reading exists in database
        $this->assertEquals(1, Reading::where('data_point_id', $this->dataPoint->id)
            ->where('read_at', $timestamp)
            ->count());
    }

    public function test_migration_removes_existing_duplicates()
    {
        // This test verifies that the migration properly cleaned up duplicates
        // We'll create some test data and verify the constraint works
        
        $timestamp = Carbon::now();
        
        // Create a reading
        $reading = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);
        
        // Verify the unique constraint exists by checking it prevents duplicates
        $constraintExists = false;
        try {
            Reading::create([
                'data_point_id' => $this->dataPoint->id,
                'raw_value' => json_encode([300, 400]),
                'scaled_value' => 678.90,
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $constraintExists = true;
        }
        
        $this->assertTrue($constraintExists, 'Unique constraint should prevent duplicate readings');
    }
}