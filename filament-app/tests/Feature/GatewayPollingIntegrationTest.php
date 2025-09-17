<?php

namespace Tests\Feature;

use App\Jobs\PollGatewayJob;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use App\Services\GatewayPollingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class GatewayPollingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_polling_workflow_with_successful_reading()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'poll_interval' => 10,
            'success_count' => 0,
            'failure_count' => 0,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'register_address' => 1,
            'register_count' => 2,
            'data_type' => 'float32',
            'scale_factor' => 1.0,
        ]);

        // Mock successful Modbus communication
        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with(Mockery::on(function ($arg) use ($gateway) {
                return $arg->id === $gateway->id;
            }))
            ->once()
            ->andReturnUsing(function ($gateway) use ($dataPoint) {
                // Simulate creating a reading
                $reading = Reading::create([
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => json_encode([16256, 17152]), // Example register values
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

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert
        // Check that reading was created
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $dataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);

        // Check that gateway counters were updated
        $gateway->refresh();
        $this->assertEquals(1, $gateway->success_count);
        $this->assertEquals(0, $gateway->failure_count);
        $this->assertNotNull($gateway->last_seen_at);

        // Check that next poll was scheduled
        Queue::assertPushed(PollGatewayJob::class);
    }

    public function test_polling_workflow_with_multiple_data_points()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'poll_interval' => 15,
        ]);

        $dataPoints = DataPoint::factory()->count(3)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Mock successful polling for all data points
        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->once()
            ->andReturnUsing(function ($gateway) use ($dataPoints) {
                $readings = [];
                foreach ($dataPoints as $dataPoint) {
                    $readings[] = Reading::create([
                        'data_point_id' => $dataPoint->id,
                        'raw_value' => json_encode([rand(1000, 9999)]),
                        'scaled_value' => rand(10, 100) + (rand(0, 99) / 100),
                        'quality' => 'good',
                        'read_at' => now(),
                    ]);
                }

                return new \App\Services\PollResult(
                    success: true,
                    readings: $readings,
                    errors: [],
                    duration: 2.1
                );
            });

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert
        // Check that readings were created for all data points
        foreach ($dataPoints as $dataPoint) {
            $this->assertDatabaseHas('readings', [
                'data_point_id' => $dataPoint->id,
                'quality' => 'good',
            ]);
        }

        // Check success count
        $this->assertEquals(1, $gateway->fresh()->success_count);
    }

    public function test_polling_workflow_with_partial_failures()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
        ]);

        $dataPoint1 = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Mock polling with partial success
        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->once()
            ->andReturnUsing(function ($gateway) use ($dataPoint1, $dataPoint2) {
                // Create one successful reading
                $reading = Reading::create([
                    'data_point_id' => $dataPoint1->id,
                    'raw_value' => json_encode([12345]),
                    'scaled_value' => 123.45,
                    'quality' => 'good',
                    'read_at' => now(),
                ]);

                // Create one failed reading
                Reading::create([
                    'data_point_id' => $dataPoint2->id,
                    'raw_value' => null,
                    'scaled_value' => null,
                    'quality' => 'bad',
                    'read_at' => now(),
                ]);

                return new \App\Services\PollResult(
                    success: false,
                    readings: [$reading],
                    errors: [
                        ['data_point_id' => $dataPoint2->id, 'error' => 'Register read timeout']
                    ],
                    duration: 3.0
                );
            });

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert
        // Check successful reading
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $dataPoint1->id,
            'quality' => 'good',
        ]);

        // Check failed reading
        $this->assertDatabaseHas('readings', [
            'data_point_id' => $dataPoint2->id,
            'quality' => 'bad',
        ]);

        // Check that failure count was incremented due to errors
        $this->assertEquals(1, $gateway->fresh()->failure_count);
    }

    public function test_polling_service_integration()
    {
        // Arrange
        $activeGateways = Gateway::factory()->count(2)->create([
            'is_active' => true,
        ]);

        $inactiveGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        Queue::fake();
        $pollingService = new GatewayPollingService();

        // Act
        $pollingService->startPolling();

        // Assert
        Queue::assertPushed(\App\Jobs\ScheduleGatewayPollingJob::class);
    }

    public function test_gateway_statistics_calculation()
    {
        // Arrange
        $gateway1 = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 80,
            'failure_count' => 20,
            'last_seen_at' => now()->subMinutes(2),
            'poll_interval' => 10,
        ]);

        $gateway2 = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 90,
            'failure_count' => 10,
            'last_seen_at' => now()->subMinutes(1),
            'poll_interval' => 15,
        ]);

        $gateway3 = Gateway::factory()->create([
            'is_active' => false,
            'success_count' => 50,
            'failure_count' => 50,
            'last_seen_at' => now()->subHours(1),
        ]);

        $pollingService = new GatewayPollingService();

        // Act
        $stats = $pollingService->getPollingStatistics();

        // Assert
        $this->assertEquals(3, $stats['total_gateways']);
        $this->assertEquals(2, $stats['active_gateways']);
        $this->assertEquals(2, $stats['online_gateways']); // gateway1 and gateway2 are online
        $this->assertEquals(220, $stats['total_success']); // 80 + 90 + 50
        $this->assertEquals(80, $stats['total_failures']); // 20 + 10 + 50
        $this->assertEquals(73.33, $stats['overall_success_rate']); // 220/(220+80)*100
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}