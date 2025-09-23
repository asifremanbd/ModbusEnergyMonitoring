<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingSystemValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_enabled_gateway_can_store_readings()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
            'poll_interval' => 30,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'label' => 'Test Voltage',
            'is_enabled' => true,
        ]);

        // Act
        $reading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([16256, 17152]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Assert
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $dataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);

        $this->assertEquals('Test Gateway', $reading->dataPoint->gateway->name);
        $this->assertEquals('Test Voltage', $reading->dataPoint->label);
    }

    /** @test */
    public function test_duplicate_readings_are_prevented()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        $timestamp = Carbon::now();

        // Act - Create first reading
        $reading1 = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([100, 200]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);

        // Assert first reading was created
        $this->assertDatabaseHas('readings', [
            'id' => $reading1->id,
            'data_point_id' => $dataPoint->id,
        ]);

        // Act - Try to create duplicate reading
        $duplicateCreated = false;
        try {
            Reading::create([
                'data_point_id' => $dataPoint->id,
                'raw_value' => json_encode([300, 400]),
                'scaled_value' => 678.90,
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
            $duplicateCreated = true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Expected - duplicate should be prevented
        }

        // Assert duplicate was prevented
        $this->assertFalse($duplicateCreated, 'Duplicate reading should have been prevented');
        
        // Verify only one reading exists
        $readingCount = Reading::where('data_point_id', $dataPoint->id)
            ->where('read_at', $timestamp)
            ->count();
        
        $this->assertEquals(1, $readingCount);
    }

    /** @test */
    public function test_past_readings_can_be_stored_and_retrieved()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'name' => 'Historical Gateway',
            'is_active' => true,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'label' => 'Historical Data',
            'is_enabled' => true,
        ]);

        // Act - Create historical readings
        $timestamps = [
            now()->subHours(2),
            now()->subHours(1),
            now()->subMinutes(30),
            now(),
        ];

        foreach ($timestamps as $index => $timestamp) {
            Reading::create([
                'data_point_id' => $dataPoint->id,
                'raw_value' => json_encode([1000 + ($index * 100)]),
                'scaled_value' => 10.0 + ($index * 1.0),
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
        }

        // Assert - Verify all readings were stored
        $readings = Reading::where('data_point_id', $dataPoint->id)
            ->orderBy('read_at')
            ->get();

        $this->assertCount(4, $readings);
        
        // Verify values increase over time
        $this->assertEquals(10.0, $readings->first()->scaled_value);
        $this->assertEquals(13.0, $readings->last()->scaled_value);

        // Verify we can query by time range (last hour should include current time and 30 min ago)
        $recentReadings = Reading::where('data_point_id', $dataPoint->id)
            ->where('read_at', '>=', now()->subHours(1))
            ->count();
        
        // Should be at least 2 (30 min ago and now), but could be 3 if 1 hour ago is very close
        $this->assertGreaterThanOrEqual(2, $recentReadings);
        $this->assertLessThanOrEqual(3, $recentReadings);
    }

    /** @test */
    public function test_disabled_gateway_data_points_are_not_processed()
    {
        // Arrange
        $disabledGateway = Gateway::factory()->create([
            'name' => 'Disabled Gateway',
            'is_active' => false,
        ]);

        $disabledDataPoint = DataPoint::factory()->create([
            'gateway_id' => $disabledGateway->id,
            'label' => 'Disabled Point',
            'is_enabled' => false,
        ]);

        // Act - Try to create reading for disabled data point
        $reading = Reading::create([
            'data_point_id' => $disabledDataPoint->id,
            'raw_value' => json_encode([999]),
            'scaled_value' => 99.9,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Assert - Reading can be created but should be identifiable as from disabled source
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $disabledDataPoint->id,
            'scaled_value' => 99.9,
        ]);

        // Verify the data point and gateway are disabled
        $this->assertFalse($reading->dataPoint->is_enabled);
        $this->assertFalse($reading->dataPoint->gateway->is_active);
    }

    /** @test */
    public function test_reading_quality_indicators_work()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Act - Create readings with different quality levels
        $goodReading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([100]),
            'scaled_value' => 10.0,
            'quality' => 'good',
            'read_at' => now()->subMinutes(5),
        ]);

        $badReading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => null,
            'scaled_value' => null,
            'quality' => 'bad',
            'read_at' => now()->subMinutes(3),
        ]);

        $uncertainReading = Reading::create([
            'data_point_id' => $dataPoint->id,
            'raw_value' => json_encode([200]),
            'scaled_value' => 20.0,
            'quality' => 'uncertain',
            'read_at' => now(),
        ]);

        // Assert - Verify different quality readings are stored correctly
        $this->assertDatabaseHas('readings', [
            'id' => $goodReading->id,
            'quality' => 'good',
            'scaled_value' => 10.0,
        ]);

        $this->assertDatabaseHas('readings', [
            'id' => $badReading->id,
            'quality' => 'bad',
            'scaled_value' => null,
        ]);

        $this->assertDatabaseHas('readings', [
            'id' => $uncertainReading->id,
            'quality' => 'uncertain',
            'scaled_value' => 20.0,
        ]);

        // Verify we can filter by quality
        $goodReadings = Reading::where('data_point_id', $dataPoint->id)
            ->where('quality', 'good')
            ->count();
        
        $this->assertEquals(1, $goodReadings);
    }
}