<?php

namespace Tests\Feature;

use App\Jobs\PollGatewayJob;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class PollingSystemEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $testGateway;
    protected DataPoint $testDataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateway and data point
        $this->testGateway = Gateway::factory()->create([
            'name' => 'End-to-End Test Gateway',
            'ip_address' => '192.168.1.100',
            'is_active' => true,
            'poll_interval' => 30,
        ]);

        $this->testDataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->testGateway->id,
            'label' => 'Test Voltage Reading',
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function test_enabled_gateways_show_live_data_in_admin_interface()
    {
        // Arrange: Create some live readings
        Reading::create([
            'data_point_id' => $this->testDataPoint->id,
            'raw_value' => json_encode([16256, 17152]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Act: Access the live data page
        $response = $this->get('/admin/live-data');

        // Assert: Verify the page loads and shows our data
        $response->assertStatus(200);
        $response->assertSee('End-to-End Test Gateway');
        $response->assertSee('Test Voltage Reading');
        $response->assertSee('123.45');
    }

    /** @test */
    public function test_past_readings_are_collected_and_stored()
    {
        // Arrange: Create historical readings
        $timestamps = [
            now()->subHours(2),
            now()->subHour(),
            now()->subMinutes(30),
            now()->subMinutes(15),
            now(),
        ];

        foreach ($timestamps as $index => $timestamp) {
            Reading::create([
                'data_point_id' => $this->testDataPoint->id,
                'raw_value' => json_encode([1000 + ($index * 100)]),
                'scaled_value' => 100.0 + ($index * 5.0),
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
        }

        // Act: Access the past readings page
        $response = $this->get('/admin/past-readings');

        // Assert: Verify the page loads and shows historical data
        $response->assertStatus(200);
        $response->assertSee('End-to-End Test Gateway');
        $response->assertSee('Test Voltage Reading');
        
        // Verify all readings are stored
        $storedReadings = Reading::where('data_point_id', $this->testDataPoint->id)
            ->orderBy('read_at')
            ->get();
        
        $this->assertCount(5, $storedReadings);
        $this->assertEquals(100.0, $storedReadings->first()->scaled_value);
        $this->assertEquals(120.0, $storedReadings->last()->scaled_value);
    }

    /** @test */
    public function test_no_duplicates_created_during_normal_operation()
    {
        $timestamp = Carbon::now();

        // Act: Try to create multiple readings with the same timestamp
        $reading1 = Reading::create([
            'data_point_id' => $this->testDataPoint->id,
            'raw_value' => json_encode([16256]),
            'scaled_value' => 123.45,
            'quality' => 'good',
            'read_at' => $timestamp,
        ]);

        // Try to create a duplicate - should fail
        $duplicateCreated = false;
        try {
            Reading::create([
                'data_point_id' => $this->testDataPoint->id,
                'raw_value' => json_encode([16300]),
                'scaled_value' => 124.00,
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
            $duplicateCreated = true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Expected - duplicate should be prevented
        }

        // Assert: Duplicate was prevented
        $this->assertFalse($duplicateCreated, 'Duplicate reading should have been prevented');
        
        // Verify only one reading exists
        $readingCount = Reading::where('data_point_id', $this->testDataPoint->id)
            ->where('read_at', $timestamp)
            ->count();
        
        $this->assertEquals(1, $readingCount);
        
        // Verify the original reading is still there
        $this->assertDatabaseHas('readings', [
            'id' => $reading1->id,
            'scaled_value' => 123.45,
        ]);
    }

    /** @test */
    public function test_polling_job_creates_readings_correctly()
    {
        // Arrange: Mock the ModbusPollService
        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with(Mockery::on(function ($gateway) {
                return $gateway->id === $this->testGateway->id;
            }))
            ->once()
            ->andReturnUsing(function ($gateway) {
                // Simulate successful polling
                $reading = Reading::create([
                    'data_point_id' => $this->testDataPoint->id,
                    'raw_value' => json_encode([16256, 17152]),
                    'scaled_value' => 123.45,
                    'quality' => 'good',
                    'read_at' => now(),
                ]);

                return new \App\Services\PollResult(
                    success: true,
                    readings: [$reading],
                    errors: [],
                    duration: 1.2
                );
            });

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act: Run the polling job
        $job = new PollGatewayJob($this->testGateway);
        $job->handle($mockPollService);

        // Assert: Reading was created
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $this->testDataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);

        // Verify gateway statistics were updated
        $this->testGateway->refresh();
        $this->assertEquals(1, $this->testGateway->success_count);
        $this->assertEquals(0, $this->testGateway->failure_count);
        $this->assertNotNull($this->testGateway->last_seen_at);
    }

    /** @test */
    public function test_disabled_gateways_do_not_show_in_live_data()
    {
        // Arrange: Create a disabled gateway
        $disabledGateway = Gateway::factory()->create([
            'name' => 'Disabled Gateway',
            'is_active' => false,
        ]);

        $disabledDataPoint = DataPoint::factory()->create([
            'gateway_id' => $disabledGateway->id,
            'label' => 'Disabled Point',
            'is_enabled' => true,
        ]);

        // Create readings for both gateways
        Reading::create([
            'data_point_id' => $this->testDataPoint->id,
            'raw_value' => json_encode([100]),
            'scaled_value' => 10.0,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        Reading::create([
            'data_point_id' => $disabledDataPoint->id,
            'raw_value' => json_encode([200]),
            'scaled_value' => 20.0,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Act: Access live data page
        $response = $this->get('/admin/live-data');

        // Assert: Only active gateway shows up
        $response->assertStatus(200);
        $response->assertSee('End-to-End Test Gateway');
        $response->assertDontSee('Disabled Gateway');
    }

    /** @test */
    public function test_health_endpoints_are_accessible()
    {
        // Act & Assert: Test health check endpoint
        $response = $this->get('/api/polling/health');
        $response->assertStatus(200);
        
        $healthData = $response->json();
        $this->assertArrayHasKey('status', $healthData);

        // Test diagnostics endpoint
        $response = $this->get('/api/polling/diagnostics');
        $response->assertStatus(200);
        
        $diagnosticsData = $response->json();
        $this->assertArrayHasKey('components', $diagnosticsData);
    }

    /** @test */
    public function test_polling_repair_command_exists_and_runs()
    {
        // Act: Run the polling repair command
        $exitCode = Artisan::call('polling:repair', ['--help' => true]);

        // Assert: Command exists and shows help
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('polling:repair', $output);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}