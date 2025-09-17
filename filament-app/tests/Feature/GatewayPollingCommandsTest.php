<?php

namespace Tests\Feature;

use App\Jobs\PollGatewayJob;
use App\Jobs\ScheduleGatewayPollingJob;
use App\Models\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GatewayPollingCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_start_polling_command_starts_all_gateways()
    {
        // Arrange
        Gateway::factory()->count(3)->create(['is_active' => true]);

        // Act
        $this->artisan('gateway:start-polling')
            ->expectsOutput('Starting polling for all active gateways...')
            ->expectsOutput('Gateway polling started successfully')
            ->assertExitCode(0);

        // Assert
        Queue::assertPushed(ScheduleGatewayPollingJob::class);
    }

    public function test_start_polling_command_for_specific_gateway()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => true]);

        // Act
        $this->artisan('gateway:start-polling', ['--gateway' => $gateway->id])
            ->expectsOutput("Starting polling for gateway: {$gateway->name}")
            ->expectsOutput('Gateway polling started successfully')
            ->assertExitCode(0);

        // Assert
        Queue::assertPushed(PollGatewayJob::class, function ($job) use ($gateway) {
            return $job->gateway->id === $gateway->id;
        });
    }

    public function test_start_polling_command_with_invalid_gateway_id()
    {
        // Act
        $this->artisan('gateway:start-polling', ['--gateway' => 999])
            ->expectsOutput('Gateway with ID 999 not found')
            ->assertExitCode(1);

        // Assert
        Queue::assertNotPushed(PollGatewayJob::class);
    }

    public function test_stop_polling_command_stops_all_gateways()
    {
        // Act
        $this->artisan('gateway:stop-polling')
            ->expectsOutput('Stopping polling for all gateways...')
            ->expectsOutput('Gateway polling stopped successfully')
            ->assertExitCode(0);
    }

    public function test_stop_polling_command_for_specific_gateway()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => true]);

        // Act
        $this->artisan('gateway:stop-polling', ['--gateway' => $gateway->id])
            ->expectsOutput("Stopping polling for gateway: {$gateway->name}")
            ->expectsOutput('Gateway polling stopped successfully')
            ->assertExitCode(0);

        // Assert
        $this->assertFalse($gateway->fresh()->is_active);
    }

    public function test_stop_polling_command_with_invalid_gateway_id()
    {
        // Act
        $this->artisan('gateway:stop-polling', ['--gateway' => 999])
            ->expectsOutput('Gateway with ID 999 not found')
            ->assertExitCode(1);
    }

    public function test_status_command_shows_basic_statistics()
    {
        // Arrange
        Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 100,
            'failure_count' => 10,
            'last_seen_at' => now()->subMinutes(1),
        ]);

        Gateway::factory()->create([
            'is_active' => false,
            'success_count' => 50,
            'failure_count' => 50,
        ]);

        // Act & Assert
        $this->artisan('gateway:status')
            ->expectsOutput('Gateway Polling System Status')
            ->expectsOutput('================================')
            ->assertExitCode(0);
    }

    public function test_status_command_with_detailed_flag()
    {
        // Arrange
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true,
            'success_count' => 95,
            'failure_count' => 5,
            'poll_interval' => 10,
            'last_seen_at' => now()->subMinutes(2),
        ]);

        // Act & Assert
        $this->artisan('gateway:status', ['--detailed' => true])
            ->expectsOutput('Gateway Polling System Status')
            ->expectsOutput('Gateway Details:')
            ->assertExitCode(0);
    }

    public function test_status_command_shows_health_warnings()
    {
        // Arrange - gateway with high failure rate
        Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 20,
            'failure_count' => 80, // 80% failure rate
            'last_seen_at' => now()->subMinutes(1),
        ]);

        // Act & Assert
        $this->artisan('gateway:status')
            ->expectsOutput('System Health: WARNING')
            ->assertExitCode(0);
    }

    public function test_status_command_shows_offline_gateway_warning()
    {
        // Arrange - active but offline gateway
        Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 100,
            'failure_count' => 5,
            'last_seen_at' => now()->subHours(2), // Offline
            'poll_interval' => 10,
        ]);

        // Act & Assert
        $this->artisan('gateway:status')
            ->expectsOutput('System Health: WARNING')
            ->assertExitCode(0);
    }
}