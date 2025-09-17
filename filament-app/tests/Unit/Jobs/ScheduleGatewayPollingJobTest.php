<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PollGatewayJob;
use App\Jobs\ScheduleGatewayPollingJob;
use App\Models\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleGatewayPollingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_schedules_polling_for_all_active_gateways()
    {
        // Arrange
        $activeGateways = Gateway::factory()->count(3)->create([
            'is_active' => true,
        ]);

        $inactiveGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        // Act
        $job = new ScheduleGatewayPollingJob();
        $job->handle();

        // Assert
        Queue::assertPushed(PollGatewayJob::class, 3);
        
        foreach ($activeGateways as $gateway) {
            Queue::assertPushed(PollGatewayJob::class, function ($job) use ($gateway) {
                return $job->gateway->id === $gateway->id;
            });
        }

        // Inactive gateway should not be scheduled
        Queue::assertNotPushed(PollGatewayJob::class, function ($job) use ($inactiveGateway) {
            return $job->gateway->id === $inactiveGateway->id;
        });
    }

    public function test_handles_no_active_gateways()
    {
        // Arrange - no active gateways
        Gateway::factory()->count(2)->create([
            'is_active' => false,
        ]);

        // Act
        $job = new ScheduleGatewayPollingJob();
        $job->handle();

        // Assert
        Queue::assertNotPushed(PollGatewayJob::class);
    }

    public function test_handles_empty_gateway_list()
    {
        // Arrange - no gateways at all

        // Act
        $job = new ScheduleGatewayPollingJob();
        $job->handle();

        // Assert
        Queue::assertNotPushed(PollGatewayJob::class);
    }
}