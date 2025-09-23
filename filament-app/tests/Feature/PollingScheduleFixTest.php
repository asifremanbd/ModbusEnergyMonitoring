<?php

namespace Tests\Feature;

use App\Console\Commands\FixPollingScheduleCommand;
use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollingScheduleFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_identifies_active_gateways_without_polling()
    {
        // Create an active gateway without polling status
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Test Gateway',
            'poll_interval' => 60,
        ]);

        $command = new FixPollingScheduleCommand();
        $pollingService = app(ReliablePollingService::class);

        // Run dry-run to identify issues
        $this->artisan('polling:fix-schedule --dry-run')
            ->expectsOutput('🔍 Analyzing polling schedule integrity...')
            ->expectsOutput('❌ Missing Polling (1 gateways):')
            ->expectsOutput("  - Gateway {$gateway->id} (Test Gateway): Active gateway has no polling jobs scheduled")
            ->assertExitCode(1);
    }

    /** @test */
    public function it_fixes_missing_polling_for_active_gateways()
    {
        // Create an active gateway without polling status
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Test Gateway',
            'poll_interval' => 60,
        ]);

        // Verify no polling status exists
        $statusKey = "gateway_polling_status_{$gateway->id}";
        $this->assertNull(Cache::get($statusKey));

        // Run the fix command
        $this->artisan('polling:fix-schedule')
            ->expectsOutput('🔍 Analyzing polling schedule integrity...')
            ->expectsOutput('🔧 Fix Results:')
            ->expectsOutput('✅ Successfully fixed 1 gateway(s)')
            ->assertExitCode(0);

        // Verify polling status was created
        $pollingStatus = Cache::get($statusKey);
        $this->assertNotNull($pollingStatus);
        $this->assertEquals($gateway->id, $pollingStatus['gateway_id']);
        $this->assertEquals($gateway->poll_interval, $pollingStatus['poll_interval']);
    }

    /** @test */
    public function it_identifies_overdue_polling()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Overdue Gateway',
            'poll_interval' => 60,
        ]);

        // Set up overdue polling status
        $statusKey = "gateway_polling_status_{$gateway->id}";
        Cache::put($statusKey, [
            'gateway_id' => $gateway->id,
            'last_scheduled' => now()->subMinutes(5), // 5 minutes ago, but interval is 1 minute
            'poll_interval' => $gateway->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        $this->artisan('polling:fix-schedule --dry-run')
            ->expectsOutput('⏰ Overdue Polling (1 gateways):')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_identifies_interval_mismatch()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Mismatch Gateway',
            'poll_interval' => 120, // Changed to 2 minutes
        ]);

        // Set up polling status with old interval
        $statusKey = "gateway_polling_status_{$gateway->id}";
        Cache::put($statusKey, [
            'gateway_id' => $gateway->id,
            'last_scheduled' => now(),
            'poll_interval' => 60, // Old interval was 1 minute
            'status' => 'scheduled'
        ], now()->addHour());

        $this->artisan('polling:fix-schedule --dry-run')
            ->expectsOutput('🔄 Interval Mismatch (1 gateways):')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_identifies_unwanted_polling_for_inactive_gateways()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => false,
            'name' => 'Inactive Gateway',
            'poll_interval' => 60,
        ]);

        // Set up polling status for inactive gateway
        $statusKey = "gateway_polling_status_{$gateway->id}";
        Cache::put($statusKey, [
            'gateway_id' => $gateway->id,
            'last_scheduled' => now(),
            'poll_interval' => $gateway->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        $this->artisan('polling:fix-schedule --dry-run')
            ->expectsOutput('🛑 Unwanted Polling (1 gateways):')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_stops_polling_for_inactive_gateways()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => false,
            'name' => 'Inactive Gateway',
            'poll_interval' => 60,
        ]);

        // Set up polling status for inactive gateway
        $statusKey = "gateway_polling_status_{$gateway->id}";
        Cache::put($statusKey, [
            'gateway_id' => $gateway->id,
            'last_scheduled' => now(),
            'poll_interval' => $gateway->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        // Run the fix command
        $this->artisan('polling:fix-schedule')
            ->expectsOutput('✅ Successfully fixed 1 gateway(s)')
            ->assertExitCode(0);

        // Verify polling status was removed
        $this->assertNull(Cache::get($statusKey));
    }

    /** @test */
    public function it_reports_no_issues_when_all_gateways_are_properly_configured()
    {
        $activeGateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Active Gateway',
            'poll_interval' => 60,
        ]);

        $inactiveGateway = Gateway::factory()->create([
            'is_active' => false,
            'name' => 'Inactive Gateway',
            'poll_interval' => 60,
        ]);

        // Set up proper polling status for active gateway
        $statusKey = "gateway_polling_status_{$activeGateway->id}";
        Cache::put($statusKey, [
            'gateway_id' => $activeGateway->id,
            'last_scheduled' => now()->addSeconds($activeGateway->poll_interval), // Future scheduled time
            'poll_interval' => $activeGateway->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        // No polling status for inactive gateway (correct)

        $this->artisan('polling:fix-schedule --dry-run')
            ->expectsOutput('✅ No polling schedule issues found - all gateways are properly configured')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_command_ensures_all_active_gateways_are_polling()
    {
        // Create multiple gateways in different states
        $activeWithoutPolling = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Active Without Polling',
        ]);

        $activeWithPolling = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Active With Polling',
        ]);

        $inactiveWithPolling = Gateway::factory()->create([
            'is_active' => false,
            'name' => 'Inactive With Polling',
        ]);

        // Set up polling for the active gateway that should have it
        $statusKey = "gateway_polling_status_{$activeWithPolling->id}";
        Cache::put($statusKey, [
            'gateway_id' => $activeWithPolling->id,
            'last_scheduled' => now()->addMinutes(1),
            'poll_interval' => $activeWithPolling->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        // Set up polling for the inactive gateway (should be removed)
        $inactiveStatusKey = "gateway_polling_status_{$inactiveWithPolling->id}";
        Cache::put($inactiveStatusKey, [
            'gateway_id' => $inactiveWithPolling->id,
            'last_scheduled' => now()->addMinutes(1),
            'poll_interval' => $inactiveWithPolling->poll_interval,
            'status' => 'scheduled'
        ], now()->addHour());

        $this->artisan('polling:reliable sync')
            ->expectsOutput('🔄 Synchronizing gateway polling states...')
            ->expectsOutput('✅ Synchronized 2 gateway(s) successfully')
            ->assertExitCode(0);

        // Verify active gateway without polling now has polling
        $this->assertNotNull(Cache::get("gateway_polling_status_{$activeWithoutPolling->id}"));

        // Verify active gateway with polling still has polling
        $this->assertNotNull(Cache::get($statusKey));

        // Verify inactive gateway no longer has polling
        $this->assertNull(Cache::get($inactiveStatusKey));
    }

    /** @test */
    public function validation_passes_after_successful_fix()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'name' => 'Test Gateway',
        ]);

        // Initially should fail validation
        $this->artisan('polling:reliable validate')
            ->expectsOutput('⚠️  Found 1 polling integrity issues:')
            ->assertExitCode(1);

        // Fix the issues
        $this->artisan('polling:fix-schedule')
            ->assertExitCode(0);

        // Now validation should pass
        $this->artisan('polling:reliable validate')
            ->expectsOutput('✅ All active gateways have proper polling scheduled')
            ->assertExitCode(0);
    }
}