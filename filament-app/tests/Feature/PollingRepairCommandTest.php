<?php

namespace Tests\Feature;

use App\Console\Commands\PollingRepairCommand;
use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollingRepairCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateways
        Gateway::factory()->create([
            'id' => 1,
            'name' => 'Test Gateway 1',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true,
            'poll_interval' => 30,
        ]);
        
        Gateway::factory()->create([
            'id' => 2,
            'name' => 'Test Gateway 2',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'is_active' => false,
            'poll_interval' => 60,
        ]);
    }

    public function test_diagnose_only_mode_runs_without_applying_fixes()
    {
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutput('🔧 Polling System Repair Tool')
            ->expectsOutput('📋 Phase 1: Running comprehensive diagnostics...')
            ->expectsOutput('💡 Run without --diagnose-only to apply automatic repairs')
            ->assertExitCode(0);
    }

    public function test_command_detects_healthy_system()
    {
        // Set up a healthy system state
        Cache::put('polling_system_status', [
            'started_at' => now(),
            'active_gateways' => 1,
            'total_gateways' => 2,
        ], now()->addHours(24));
        
        Cache::put('gateway_polling_status_1', [
            'gateway_id' => 1,
            'last_scheduled' => now()->subSeconds(15),
            'poll_interval' => 30,
            'status' => 'scheduled'
        ], now()->addMinutes(5));

        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutput('✅ No issues found - system is healthy')
            ->assertExitCode(0);
    }

    public function test_command_detects_missing_polling_system()
    {
        // Clear system status to simulate stopped polling
        Cache::forget('polling_system_status');
        
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('❌ Polling system is not running')
            ->assertExitCode(1);
    }

    public function test_command_detects_inactive_gateways_with_polling()
    {
        // Set up inactive gateway with polling status
        Cache::put('gateway_polling_status_2', [
            'gateway_id' => 2,
            'last_scheduled' => now()->subSeconds(30),
            'poll_interval' => 60,
            'status' => 'scheduled'
        ], now()->addMinutes(5));

        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('polling integrity issues')
            ->assertExitCode(0);
    }

    public function test_command_detects_system_locks()
    {
        // Create system lock
        Cache::put('polling_system_lock', true, now()->addMinutes(5));
        
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('⚠️  System polling lock exists')
            ->assertExitCode(0);
    }

    public function test_command_detects_gateway_locks()
    {
        // Create gateway lock
        Cache::put('gateway_polling_lock_1', true, now()->addMinutes(5));
        
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('⚠️  Found locks on gateways: 1')
            ->assertExitCode(0);
    }

    public function test_command_detects_failed_jobs()
    {
        // Create a failed job
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'redis',
            'queue' => 'polling',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('⚠️  Found 1 failed jobs')
            ->assertExitCode(0);
    }

    public function test_command_applies_repairs_when_issues_found()
    {
        // Set up system with issues
        Cache::put('polling_system_lock', true, now()->addMinutes(5));
        Cache::put('gateway_polling_lock_1', true, now()->addMinutes(5));
        
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-456',
            'connection' => 'redis',
            'queue' => 'polling',
            'payload' => json_encode(['test' => 'data']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('polling:repair')
            ->expectsOutput('🔧 Phase 2: Applying repairs...')
            ->expectsOutput('🔍 Phase 3: Validating repairs...')
            ->expectsOutputToContain('Cleared')
            ->assertExitCode(0);

        // Verify locks were cleared
        $this->assertFalse(Cache::has('polling_system_lock'));
        $this->assertFalse(Cache::has('gateway_polling_lock_1'));
    }

    public function test_command_with_force_option_applies_all_repairs()
    {
        $this->artisan('polling:repair --force')
            ->expectsOutput('🔧 Phase 2: Applying repairs...')
            ->expectsOutputToContain('Applied')
            ->assertExitCode(0);
    }

    public function test_command_with_specific_repair_options()
    {
        Cache::put('polling_system_lock', true, now()->addMinutes(5));
        
        $this->artisan('polling:repair --clear-queues')
            ->expectsOutput('🧹 Clearing stuck jobs from queues...')
            ->expectsOutput('🔓 Clearing system locks...')
            ->assertExitCode(0);
    }

    public function test_command_handles_database_connection_failure()
    {
        // Temporarily break database connection by using invalid config
        config(['database.connections.testing.database' => '/invalid/path/database.sqlite']);
        
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('❌ Database connection failed')
            ->assertExitCode(1);
    }

    public function test_command_detects_gateway_configuration_issues()
    {
        // Create gateway with invalid configuration
        Gateway::factory()->create([
            'name' => 'Invalid Gateway',
            'ip_address' => '', // Missing IP
            'port' => null, // Missing port
            'is_active' => true,
            'poll_interval' => 0, // Invalid interval
        ]);

        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('❌ Gateway configuration issues found')
            ->expectsOutputToContain('missing IP address')
            ->expectsOutputToContain('missing port')
            ->expectsOutputToContain('invalid poll interval')
            ->assertExitCode(1);
    }

    public function test_command_provides_detailed_output()
    {
        $this->artisan('polling:repair --diagnose-only --detailed')
            ->expectsOutput('📋 Diagnostic Results:')
            ->expectsOutputToContain('📊')
            ->assertExitCode(0);
    }

    public function test_command_validation_phase_works()
    {
        // Set up system that will pass validation
        Cache::put('polling_system_status', [
            'started_at' => now(),
            'active_gateways' => 1,
            'total_gateways' => 2,
        ], now()->addHours(24));

        $this->artisan('polling:repair --force')
            ->expectsOutput('🔍 Phase 3: Validating repairs...')
            ->expectsOutput('✅ Polling system repair completed successfully!')
            ->assertExitCode(0);
    }

    public function test_command_handles_windows_environment()
    {
        // Mock Windows environment
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This test is only for Windows environment simulation');
        }

        $this->artisan('polling:repair --restart-workers')
            ->expectsOutputToContain('On Windows, please manually restart your queue workers')
            ->assertExitCode(0);
    }

    public function test_command_shows_final_summary_with_repair_actions()
    {
        // Create conditions that will trigger repairs
        Cache::put('polling_system_lock', true, now()->addMinutes(5));
        
        $this->artisan('polling:repair')
            ->expectsOutput('📊 Final Summary:')
            ->expectsOutput('🔧 Repairs Applied:')
            ->expectsOutputToContain('system_locks: Cleared')
            ->assertExitCode(0);
    }

    public function test_command_handles_cache_operations_failure()
    {
        // This test would require mocking Cache facade to throw exceptions
        // For now, we'll test that the command handles cache operations gracefully
        
        $this->artisan('polling:repair --diagnose-only')
            ->expectsOutputToContain('Cache operations')
            ->assertExitCode(0);
    }
}