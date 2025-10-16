<?php

namespace Tests\Integration;

use App\Console\Commands\PollingRepairCommand;
use App\Jobs\PollGatewayJob;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use App\Services\ReliablePollingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Mockery;

class PollingSystemCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $enabledGateway;
    protected Gateway $disabledGateway;
    protected DataPoint $dataPoint1;
    protected DataPoint $dataPoint2;
    protected ModbusPollService $mockPollService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateways
        $this->enabledGateway = Gateway::factory()->create([
            'name' => 'Enabled Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'is_active' => true,
            'poll_interval' => 30,
            'success_count' => 0,
            'failure_count' => 0,
        ]);

        $this->disabledGateway = Gateway::factory()->create([
            'name' => 'Disabled Test Gateway',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'unit_id' => 2,
            'is_active' => false,
            'poll_interval' => 60,
        ]);

        // Create test data points
        $this->dataPoint1 = DataPoint::factory()->create([
            'gateway_id' => $this->enabledGateway->id,
            'application' => 'Test_Group_1',
            'label' => 'Voltage',
            'modbus_function' => 3,
            'register_address' => 1,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 0.1,
            'is_enabled' => true,
        ]);

        $this->dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $this->enabledGateway->id,
            'application' => 'Test_Group_1',
            'label' => 'Current',
            'modbus_function' => 3,
            'register_address' => 3,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 0.01,
            'is_enabled' => true,
        ]);

        // Mock the ModbusPollService
        $this->mockPollService = Mockery::mock(ModbusPollService::class);
        $this->app->instance(ModbusPollService::class, $this->mockPollService);
    }

    /** @test */
    public function test_enabled_gateways_show_live_data_in_admin_interface()
    {
        // Arrange: Mock successful polling that creates readings
        $this->mockPollService->shouldReceive('pollGateway')
            ->with(Mockery::on(function ($gateway) {
                return $gateway->id === $this->enabledGateway->id;
            }))
            ->andReturnUsing(function ($gateway) {
                // Create readings for both data points
                $reading1 = Reading::create([
                    'data_point_id' => $this->dataPoint1->id,
                    'raw_value' => json_encode([16256, 17152]),
                    'scaled_value' => 123.45,
                    'quality' => 'good',
                    'read_at' => now(),
                ]);

                $reading2 = Reading::create([
                    'data_point_id' => $this->dataPoint2->id,
                    'raw_value' => json_encode([8192, 4096]),
                    'scaled_value' => 67.89,
                    'quality' => 'good',
                    'read_at' => now(),
                ]);

                return new \App\Services\PollResult(
                    success: true,
                    readings: [$reading1, $reading2],
                    errors: [],
                    duration: 1.5
                );
            });

        // Act: Run polling job for enabled gateway
        $job = new PollGatewayJob($this->enabledGateway);
        $job->handle($this->mockPollService);

        // Assert: Verify readings were created
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $this->dataPoint1->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);

        $this->assertDatabaseHas('readings', [
            'data_point_id' => $this->dataPoint2->id,
            'scaled_value' => 67.89,
            'quality' => 'good',
        ]);

        // Act: Test live data interface
        $response = $this->get('/admin/live-data');

        // Assert: Verify live data shows in admin interface
        $response->assertStatus(200);
        $response->assertSee('Enabled Test Gateway');
        $response->assertSee('123.45');
        $response->assertSee('67.89');
        $response->assertSee('Voltage');
        $response->assertSee('Current');
        $response->assertSee('good'); // Quality indicator

        // Verify gateway statistics were updated
        $this->enabledGateway->refresh();
        $this->assertEquals(1, $this->enabledGateway->success_count);
        $this->assertEquals(0, $this->enabledGateway->failure_count);
        $this->assertNotNull($this->enabledGateway->last_seen_at);
    }

    /** @test */
    public function test_past_readings_are_collected_and_stored()
    {
        // Arrange: Create historical readings over time
        $timestamps = [
            now()->subMinutes(30),
            now()->subMinutes(25),
            now()->subMinutes(20),
            now()->subMinutes(15),
            now()->subMinutes(10),
            now()->subMinutes(5),
            now(),
        ];

        foreach ($timestamps as $index => $timestamp) {
            Reading::create([
                'data_point_id' => $this->dataPoint1->id,
                'raw_value' => json_encode([16000 + ($index * 100), 17000 + ($index * 100)]),
                'scaled_value' => 120.0 + ($index * 0.5),
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);

            Reading::create([
                'data_point_id' => $this->dataPoint2->id,
                'raw_value' => json_encode([8000 + ($index * 50), 4000 + ($index * 50)]),
                'scaled_value' => 65.0 + ($index * 0.3),
                'quality' => 'good',
                'read_at' => $timestamp,
            ]);
        }

        // Act: Test past readings interface
        $response = $this->get('/admin/past-readings');

        // Assert: Verify past readings interface works
        $response->assertStatus(200);
        $response->assertSee('Enabled Test Gateway');
        $response->assertSee('Voltage');
        $response->assertSee('Current');

        // Verify readings are stored correctly
        $voltageReadings = Reading::where('data_point_id', $this->dataPoint1->id)
            ->orderBy('read_at')
            ->get();
        
        $this->assertCount(7, $voltageReadings);
        
        // Verify trend data (values should increase over time)
        $this->assertEquals(120.0, $voltageReadings->first()->scaled_value);
        $this->assertEquals(123.0, $voltageReadings->last()->scaled_value);

        // Verify current readings are stored correctly
        $currentReadings = Reading::where('data_point_id', $this->dataPoint2->id)
            ->orderBy('read_at')
            ->get();
        
        $this->assertCount(7, $currentReadings);
        $this->assertEquals(65.0, $currentReadings->first()->scaled_value);
        $this->assertEquals(66.8, $currentReadings->last()->scaled_value);

        // Test filtering by gateway in past readings
        $response = $this->get('/admin/past-readings?filters[gateway]=' . $this->enabledGateway->id);
        $response->assertStatus(200);
        $response->assertSee('Enabled Test Gateway');
        $response->assertDontSee('Disabled Test Gateway');
    }

    /** @test */
    public function test_no_duplicates_created_during_normal_operation()
    {
        $timestamp = Carbon::now();

        // Arrange: Mock polling service to create readings
        $this->mockPollService->shouldReceive('pollGateway')
            ->times(3) // Simulate 3 concurrent polling attempts
            ->andReturnUsing(function ($gateway) use ($timestamp) {
                // Each attempt tries to create the same reading
                try {
                    $reading = Reading::create([
                        'data_point_id' => $this->dataPoint1->id,
                        'raw_value' => json_encode([16256, 17152]),
                        'scaled_value' => 123.45,
                        'quality' => 'good',
                        'read_at' => $timestamp,
                    ]);

                    return new \App\Services\PollResult(
                        success: true,
                        readings: [$reading],
                        errors: [],
                        duration: 1.0
                    );
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Handle duplicate gracefully
                    return new \App\Services\PollResult(
                        success: true,
                        readings: [],
                        errors: ['Duplicate reading prevented'],
                        duration: 0.5
                    );
                }
            });

        // Act: Simulate concurrent polling attempts
        $job1 = new PollGatewayJob($this->enabledGateway);
        $job2 = new PollGatewayJob($this->enabledGateway);
        $job3 = new PollGatewayJob($this->enabledGateway);

        $job1->handle($this->mockPollService);
        $job2->handle($this->mockPollService);
        $job3->handle($this->mockPollService);

        // Assert: Only one reading should exist
        $readingCount = Reading::where('data_point_id', $this->dataPoint1->id)
            ->where('read_at', $timestamp)
            ->count();

        $this->assertEquals(1, $readingCount, 'Only one reading should exist, duplicates should be prevented');

        // Verify the reading has correct data
        $reading = Reading::where('data_point_id', $this->dataPoint1->id)
            ->where('read_at', $timestamp)
            ->first();

        $this->assertNotNull($reading);
        $this->assertEquals(123.45, $reading->scaled_value);
        $this->assertEquals('good', $reading->quality);
    }

    /** @test */
    public function test_polling_repair_command_fixes_system_issues()
    {
        // Arrange: Simulate system issues
        Queue::fake();
        
        // Create a gateway that should be polling but isn't
        $brokenGateway = Gateway::factory()->create([
            'name' => 'Broken Gateway',
            'is_active' => true,
            'poll_interval' => 30,
        ]);

        DataPoint::factory()->create([
            'gateway_id' => $brokenGateway->id,
            'is_enabled' => true,
        ]);

        // Act: Run the polling repair command
        $exitCode = Artisan::call('polling:repair', ['--auto-fix' => true]);

        // Assert: Command should succeed
        $this->assertEquals(0, $exitCode);

        // Verify repair command output
        $output = Artisan::output();
        $this->assertStringContainsString('Polling system diagnosis complete', $output);
        $this->assertStringContainsString('Auto-fix applied', $output);

        // Verify polling jobs are scheduled for enabled gateways
        Queue::assertPushed(PollGatewayJob::class, function ($job) use ($brokenGateway) {
            return $job->gateway->id === $brokenGateway->id;
        });
    }

    /** @test */
    public function test_disabled_gateways_do_not_poll()
    {
        // Arrange: Mock polling service - should not be called for disabled gateway
        $this->mockPollService->shouldNotReceive('pollGateway')
            ->with(Mockery::on(function ($gateway) {
                return $gateway->id === $this->disabledGateway->id;
            }));

        // Act: Try to run polling job for disabled gateway
        $job = new PollGatewayJob($this->disabledGateway);
        
        // The job should exit early without calling the poll service
        $result = $job->handle($this->mockPollService);

        // Assert: No readings should be created for disabled gateway
        $readingCount = Reading::whereHas('dataPoint', function ($query) {
            $query->where('gateway_id', $this->disabledGateway->id);
        })->count();

        $this->assertEquals(0, $readingCount);

        // Verify disabled gateway doesn't appear in live data
        $response = $this->get('/admin/live-data');
        $response->assertStatus(200);
        $response->assertDontSee('Disabled Test Gateway');
    }

    /** @test */
    public function test_system_health_endpoints_work_correctly()
    {
        // Act & Assert: Test health check endpoint
        $response = $this->get('/api/polling/health');
        $response->assertStatus(200);
        
        $healthData = $response->json();
        $this->assertArrayHasKey('status', $healthData);
        $this->assertArrayHasKey('gateways', $healthData);
        $this->assertArrayHasKey('queue_workers', $healthData);

        // Test diagnostics endpoint
        $response = $this->get('/api/polling/diagnostics');
        $response->assertStatus(200);
        
        $diagnosticsData = $response->json();
        $this->assertArrayHasKey('components', $diagnosticsData);
        $this->assertArrayHasKey('recommendations', $diagnosticsData);
    }

    /** @test */
    public function test_complete_end_to_end_workflow()
    {
        // This test verifies the complete workflow from gateway creation to data display

        // Step 1: Create and configure gateway
        $gateway = Gateway::factory()->create([
            'name' => 'E2E Test Gateway',
            'is_active' => true,
            'poll_interval' => 10,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'label' => 'E2E Test Point',
            'is_enabled' => true,
        ]);

        // Step 2: Mock successful polling
        $this->mockPollService->shouldReceive('pollGateway')
            ->with(Mockery::on(function ($g) use ($gateway) {
                return $g->id === $gateway->id;
            }))
            ->andReturnUsing(function ($g) use ($dataPoint) {
                $reading = Reading::create([
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => json_encode([12345]),
                    'scaled_value' => 123.45,
                    'quality' => 'good',
                    'read_at' => now(),
                ]);

                return new \App\Services\PollResult(
                    success: true,
                    readings: [$reading],
                    errors: [],
                    duration: 1.0
                );
            });

        // Step 3: Run polling
        $job = new PollGatewayJob($gateway);
        $job->handle($this->mockPollService);

        // Step 4: Verify data appears in live interface
        $response = $this->get('/admin/live-data');
        $response->assertStatus(200);
        $response->assertSee('E2E Test Gateway');
        $response->assertSee('E2E Test Point');
        $response->assertSee('123.45');

        // Step 5: Verify data appears in past readings
        $response = $this->get('/admin/past-readings');
        $response->assertStatus(200);
        $response->assertSee('E2E Test Gateway');
        $response->assertSee('E2E Test Point');

        // Step 6: Verify gateway statistics
        $gateway->refresh();
        $this->assertEquals(1, $gateway->success_count);
        $this->assertEquals(0, $gateway->failure_count);
        $this->assertNotNull($gateway->last_seen_at);

        // Step 7: Verify health endpoints show healthy system
        $response = $this->get('/api/polling/health');
        $response->assertStatus(200);
        $healthData = $response->json();
        $this->assertEquals('healthy', $healthData['status']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}