<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingSystemCoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $enabledGateway;
    protected Gateway $disabledGateway;
    protected DataPoint $enabledDataPoint;
    protected DataPoint $disabledDataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user for authentication
        $this->user = User::factory()->create();
        
        // Create test gateways
        $this->enabledGateway = Gateway::factory()->create([
            'name' => 'Enabled Test Gateway',
            'is_active' => true,
            'poll_interval' => 30,
        ]);

        $this->disabledGateway = Gateway::factory()->create([
            'name' => 'Disabled Test Gateway',
            'is_active' => false,
            'poll_interval' => 60,
        ]);

        // Create test data points
        $this->enabledDataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->enabledGateway->id,
            'label' => 'Enabled Voltage Reading',
            'is_enabled' => true,
        ]);

        $this->disabledDataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->disabledGateway->id,
            'label' => 'Disabled Current Reading',
            'is_enabled' => false,
        ]);
    }

    /** @test */
    public function test_enabled_gateways_can_store_live_data()
    {
        // Act: Create a live reading for enabled gateway
        $reading = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([16256, 17152]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Assert: Reading was stored correctly
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $this->enabledDataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);

        // Verify the reading is associated with the correct gateway
        $this->assertEquals('Enabled Test Gateway', $reading->dataPoint->gateway->name);
        $this->assertEquals('Enabled Voltage Reading', $reading->dataPoint->label);
        $this->assertTrue($reading->dataPoint->gateway->is_active);
        $this->assertTrue($reading->dataPoint->is_enabled);
    }

    /** @test */
    public function test_past_readings_are_collected_and_stored_correctly()
    {
        // Arrange: Create historical readings over different time periods
        $timestamps = [
            now()->subDays(7),   // 1 week ago
            now()->subDays(3),   // 3 days ago
            now()->subDay(),     // 1 day ago
            now()->subHours(6),  // 6 hours ago
            now()->subHours(2),  // 2 hours ago
            now()->subHour(),    // 1 hour ago
            now()->subMinutes(30), // 30 minutes ago
            now()->subMinutes(15), // 15 minutes ago
            now(),               // Now
        ];

        // Act: Create readings with increasing values over time
        foreach ($timestamps as $index => $timestamp) {
            Reading::create([
                'data_point_id' => $this->enabledDataPoint->id,
                'raw_value' => json_encode([1000 + ($index * 100)]),
                'scaled_value' => 100.0 + ($index * 5.0),
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
        }

        // Assert: All readings are stored
        $allReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->orderBy('read_at')
            ->get();
        
        $this->assertCount(9, $allReadings);
        
        // Verify trend data (values should increase over time)
        $this->assertEquals(100.0, $allReadings->first()->scaled_value);
        $this->assertEquals(140.0, $allReadings->last()->scaled_value);

        // Test time-based queries (6 hours ago, 2 hours ago, 1 hour ago, 30 min ago, 15 min ago, now = 5 readings within 3 hours)
        $recentReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('read_at', '>=', now()->subHours(3))
            ->count();
        
        $this->assertEquals(5, $recentReadings); // Last 5 readings within 3 hours

        $dailyReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('read_at', '>=', now()->subDay())
            ->count();
        
        $this->assertEquals(7, $dailyReadings); // Last 7 readings within 1 day

        // Verify data quality
        $goodQualityReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('quality', 'good')
            ->count();
        
        $this->assertEquals(9, $goodQualityReadings); // All readings have good quality
    }

    /** @test */
    public function test_no_duplicates_created_during_normal_operation()
    {
        $timestamp = Carbon::now();

        // Act: Create first reading
        $reading1 = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([16256]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);

        // Assert: First reading was created
        $this->assertDatabaseHas('readings', [
            'id' => $reading1->id,
            'scaled_value' => 123.45,
        ]);

        // Act: Try to create duplicate reading with same data_point_id and timestamp
        $duplicateCreated = false;
        $duplicateId = null;
        
        try {
            $reading2 = Reading::create([
                'data_point_id' => $this->enabledDataPoint->id,
                'raw_value' => json_encode([16300]),
                'scaled_value' => 124.00,
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
            $duplicateCreated = true;
            $duplicateId = $reading2->id;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Expected - duplicate should be prevented
        }

        // Assert: Duplicate was prevented
        $this->assertFalse($duplicateCreated, 'Duplicate reading should have been prevented');
        $this->assertNull($duplicateId, 'No duplicate ID should exist');
        
        // Verify only one reading exists for this timestamp
        $readingCount = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('read_at', $timestamp)
            ->count();
        
        $this->assertEquals(1, $readingCount);
        
        // Verify the original reading is unchanged
        $originalReading = Reading::find($reading1->id);
        $this->assertEquals(123.45, $originalReading->scaled_value);
        $this->assertEquals('good', $originalReading->quality);
    }

    /** @test */
    public function test_different_data_points_can_have_same_timestamp()
    {
        $timestamp = Carbon::now();

        // Act: Create readings for different data points with same timestamp
        $reading1 = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([16256]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);

        $reading2 = Reading::create([
            'data_point_id' => $this->disabledDataPoint->id,
            'raw_value' => json_encode([8192]),
            'scaled_value' => 67.89,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);

        // Assert: Both readings were created successfully
        $this->assertDatabaseHas('readings', [
            'id' => $reading1->id,
            'data_point_id' => $this->enabledDataPoint->id,
            'scaled_value' => 123.45,
        ]);

        $this->assertDatabaseHas('readings', [
            'id' => $reading2->id,
            'data_point_id' => $this->disabledDataPoint->id,
            'scaled_value' => 67.89,
        ]);

        // Verify both readings have the same timestamp
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $reading1->read_at->format('Y-m-d H:i:s'));
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $reading2->read_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function test_gateway_statistics_are_updated_correctly()
    {
        // Arrange: Create a fresh gateway with known initial values
        $testGateway = Gateway::factory()->create([
            'name' => 'Statistics Test Gateway',
            'is_active' => true,
            'success_count' => 0,
            'failure_count' => 0,
            'last_seen_at' => null,
        ]);
        
        // Verify initial state
        $this->assertEquals(0, $testGateway->success_count);
        $this->assertEquals(0, $testGateway->failure_count);
        $this->assertNull($testGateway->last_seen_at);

        // Act: Simulate successful polling operations
        $testGateway->update([
            'success_count' => 10,
            'failure_count' => 2,
            'last_seen_at' => now(),
        ]);

        // Assert: Statistics are updated correctly
        $testGateway->refresh();
        $this->assertEquals(10, $testGateway->success_count);
        $this->assertEquals(2, $testGateway->failure_count);
        $this->assertNotNull($testGateway->last_seen_at);

        // Verify success rate calculation
        $expectedSuccessRate = (10 / (10 + 2)) * 100; // 83.33%
        $this->assertEquals($expectedSuccessRate, $testGateway->success_rate);
    }

    /** @test */
    public function test_reading_quality_indicators_work_correctly()
    {
        $timestamp = now();

        // Act: Create readings with different quality levels
        $goodReading = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([100]),
            'scaled_value' => 10.0,
            'quality' => 'good',
            'read_at' => $timestamp->copy()->subMinutes(10),
        ]);

        $badReading = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => null,
            'scaled_value' => null,
            'quality' => 'bad',
            'read_at' => $timestamp->copy()->subMinutes(5),
        ]);

        $uncertainReading = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([200]),
            'scaled_value' => 20.0,
            'quality' => 'uncertain',
            'read_at' => $timestamp,
        ]);

        // Assert: All readings are stored with correct quality
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

        // Verify quality-based filtering works
        $goodReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('quality', 'good')
            ->count();
        
        $badReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('quality', 'bad')
            ->count();
        
        $uncertainReadings = Reading::where('data_point_id', $this->enabledDataPoint->id)
            ->where('quality', 'uncertain')
            ->count();

        $this->assertEquals(1, $goodReadings);
        $this->assertEquals(1, $badReadings);
        $this->assertEquals(1, $uncertainReadings);
    }

    /** @test */
    public function test_enabled_vs_disabled_gateway_behavior()
    {
        // Act: Create readings for both enabled and disabled gateways
        $enabledReading = Reading::create([
            'data_point_id' => $this->enabledDataPoint->id,
            'raw_value' => json_encode([100]),
            'scaled_value' => 10.0,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        $disabledReading = Reading::create([
            'data_point_id' => $this->disabledDataPoint->id,
            'raw_value' => json_encode([200]),
            'scaled_value' => 20.0,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Assert: Both readings can be stored (the system allows historical data)
        $this->assertDatabaseHas('readings', [
            'id' => $enabledReading->id,
            'scaled_value' => 10.0,
        ]);

        $this->assertDatabaseHas('readings', [
            'id' => $disabledReading->id,
            'scaled_value' => 20.0,
        ]);

        // Verify gateway states
        $this->assertTrue($enabledReading->dataPoint->gateway->is_active);
        $this->assertFalse($disabledReading->dataPoint->gateway->is_active);

        // Verify data point states
        $this->assertTrue($enabledReading->dataPoint->is_enabled);
        $this->assertFalse($disabledReading->dataPoint->is_enabled);

        // Test querying for active gateways only
        $activeGatewayReadings = Reading::whereHas('dataPoint.gateway', function ($query) {
            $query->where('is_active', true);
        })->count();

        $this->assertEquals(1, $activeGatewayReadings);

        // Test querying for enabled data points only
        $enabledDataPointReadings = Reading::whereHas('dataPoint', function ($query) {
            $query->where('is_enabled', true);
        })->count();

        $this->assertEquals(1, $enabledDataPointReadings);
    }
}