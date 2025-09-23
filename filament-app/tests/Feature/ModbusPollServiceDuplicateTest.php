<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use App\Services\ErrorHandlingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ModbusPollServiceDuplicateTest extends TestCase
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
            'group_name' => 'Test Group',
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

    public function test_reading_creation_handles_duplicate_constraint_violation()
    {
        // Mock the current time to ensure consistent timestamps
        $fixedTime = Carbon::create(2025, 9, 23, 12, 0, 0);
        Carbon::setTestNow($fixedTime);
        
        // Create a reading first
        $existingReading = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $fixedTime,
        ]);
        
        // Now try to create another reading with the same timestamp
        // This should be handled gracefully by the service
        try {
            $duplicateReading = Reading::create([
                'data_point_id' => $this->dataPoint->id,
                'raw_value' => json_encode([300, 400]),
                'scaled_value' => 678.90,
                'quality' => 'good',
                'read_at' => $fixedTime,
            ]);
            
            // If we get here, the constraint didn't work
            $this->fail('Expected UniqueConstraintViolationException was not thrown');
            
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // This is expected - the constraint should prevent the duplicate
            $this->assertTrue(true, 'Duplicate constraint violation handled correctly');
        }
        
        // Verify only one reading exists
        $this->assertEquals(1, Reading::where('data_point_id', $this->dataPoint->id)
            ->where('read_at', $fixedTime)
            ->count());
            
        // Verify the original reading is still there
        $this->assertDatabaseHas('readings', [
            'id' => $existingReading->id,
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 123.45,
        ]);
        
        Carbon::setTestNow(); // Reset time
    }

    public function test_concurrent_reading_simulation()
    {
        // Mock the current time to ensure consistent timestamps
        $fixedTime = Carbon::create(2025, 9, 23, 12, 0, 0);
        Carbon::setTestNow($fixedTime);
        
        $successfulCreations = 0;
        $duplicateExceptions = 0;
        
        // Simulate multiple concurrent attempts to create readings
        for ($i = 0; $i < 5; $i++) {
            try {
                $reading = Reading::create([
                    'data_point_id' => $this->dataPoint->id,
                    'raw_value' => json_encode([100 + $i, 200 + $i]),
                    'scaled_value' => 123.45 + $i,
                    'quality' => 'good',
                    'read_at' => $fixedTime,
                ]);
                $successfulCreations++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $duplicateExceptions++;
            }
        }
        
        // Only one should succeed, the rest should be duplicates
        $this->assertEquals(1, $successfulCreations);
        $this->assertEquals(4, $duplicateExceptions);
        
        // Verify only one reading exists in the database
        $this->assertEquals(1, Reading::where('data_point_id', $this->dataPoint->id)
            ->where('read_at', $fixedTime)
            ->count());
        
        Carbon::setTestNow(); // Reset time
    }

    public function test_different_timestamps_allow_multiple_readings()
    {
        $time1 = Carbon::create(2025, 9, 23, 12, 0, 0);
        $time2 = Carbon::create(2025, 9, 23, 12, 0, 1); // 1 second later
        
        // Create readings with different timestamps - should both succeed
        $reading1 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $time1,
        ]);
        
        $reading2 = Reading::create([
            'data_point_id' => $this->dataPoint->id,
            'raw_value' => json_encode([300, 400]),
            'scaled_value' => 678.90,
            'quality' => 'good',
            'read_at' => $time2,
        ]);
        
        // Both should exist
        $this->assertDatabaseHas('readings', ['id' => $reading1->id]);
        $this->assertDatabaseHas('readings', ['id' => $reading2->id]);
        
        // Total count should be 2
        $this->assertEquals(2, Reading::where('data_point_id', $this->dataPoint->id)->count());
    }

    public function test_different_data_points_allow_same_timestamp()
    {
        // Create second data point
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Test Group 2',
            'label' => 'Test Point 2',
            'modbus_function' => 3,
            'register_address' => 2,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.0,
            'is_enabled' => true,
        ]);
        
        $timestamp = Carbon::create(2025, 9, 23, 12, 0, 0);
        
        // Create readings for both data points with same timestamp - should both succeed
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
        
        // Both should exist
        $this->assertDatabaseHas('readings', ['id' => $reading1->id]);
        $this->assertDatabaseHas('readings', ['id' => $reading2->id]);
        
        // Total count should be 2
        $this->assertEquals(2, Reading::where('read_at', $timestamp)->count());
    }
}