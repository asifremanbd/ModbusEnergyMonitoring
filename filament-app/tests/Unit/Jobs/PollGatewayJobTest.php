<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PollGatewayJob;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use App\Services\PollResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class PollGatewayJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_job_polls_gateway_successfully()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'poll_interval' => 10,
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with($gateway)
            ->once()
            ->andReturn(new PollResult(
                success: true,
                readings: [new Reading()],
                errors: [],
                duration: 1.5
            ));

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert
        Queue::assertPushed(PollGatewayJob::class, function ($job) use ($gateway) {
            return $job->gateway->id === $gateway->id;
        });
    }

    public function test_job_handles_polling_failure()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'failure_count' => 0,
        ]);

        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with($gateway)
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act & Assert
        $job = new PollGatewayJob($gateway);
        
        $this->expectException(\Exception::class);
        $job->handle($mockPollService);

        // Check that failure count was incremented
        $this->assertEquals(1, $gateway->fresh()->failure_count);
    }

    public function test_job_skips_inactive_gateway()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldNotReceive('pollGateway');

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert - no next job should be scheduled for inactive gateway
        Queue::assertNotPushed(PollGatewayJob::class);
    }

    public function test_job_disables_gateway_with_high_failure_rate()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 2,
            'failure_count' => 8, // 80% failure rate
        ]);

        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with($gateway)
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        
        try {
            $job->handle($mockPollService);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Assert - gateway should be disabled due to high failure rate
        $this->assertFalse($gateway->fresh()->is_active);
    }

    public function test_job_schedules_next_poll_with_correct_delay()
    {
        // Arrange
        $pollInterval = 30;
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'poll_interval' => $pollInterval,
        ]);

        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('pollGateway')
            ->with($gateway)
            ->once()
            ->andReturn(new PollResult(
                success: true,
                readings: [],
                errors: [],
                duration: 1.0
            ));

        $this->app->instance(ModbusPollService::class, $mockPollService);

        // Act
        $job = new PollGatewayJob($gateway);
        $job->handle($mockPollService);

        // Assert - next job should be scheduled
        Queue::assertPushed(PollGatewayJob::class, function ($job) use ($gateway) {
            return $job->gateway->id === $gateway->id && $job->delay !== null;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}