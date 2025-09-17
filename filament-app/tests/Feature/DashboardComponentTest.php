<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_dashboard_component_renders_successfully()
    {
        Livewire::test(Dashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard');
    }

    public function test_dashboard_displays_kpi_tiles()
    {
        // Create test data
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now(),
            'success_count' => 95,
            'failure_count' => 5,
        ]);
        
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subMinutes(5),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Online Gateways')
            ->assertSee('Poll Success Rate')
            ->assertSee('Average Latency')
            ->assertSee('1') // Online gateways count
            ->assertSee('95.0%'); // Success rate
    }

    public function test_dashboard_displays_fleet_status_with_gateways()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true,
            'last_seen_at' => now(),
            'success_count' => 90,
            'failure_count' => 10,
        ]);

        DataPoint::factory()->count(3)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Fleet Status')
            ->assertSee('Test Gateway')
            ->assertSee('192.168.1.100:502')
            ->assertSee('Online')
            ->assertSee('90.0%') // Success rate
            ->assertSee('3'); // Data points count
    }

    public function test_dashboard_displays_empty_state_when_no_gateways()
    {
        Livewire::test(Dashboard::class)
            ->assertSee('No gateways configured')
            ->assertSee('Get started by adding your first Teltonika gateway')
            ->assertSee('Add Gateway');
    }

    public function test_dashboard_displays_recent_events()
    {
        // Create a gateway that went offline
        $gateway = Gateway::factory()->create([
            'name' => 'Offline Gateway',
            'last_seen_at' => now()->subHours(2), // Offline
        ]);

        // Create a recently updated gateway
        $updatedGateway = Gateway::factory()->create([
            'name' => 'Updated Gateway',
            'updated_at' => now()->subHours(1),
            'created_at' => now()->subDays(1), // Different from updated_at
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Recent Events')
            ->assertSee('Offline Gateway')
            ->assertSee('Updated Gateway');
    }

    public function test_dashboard_shows_empty_events_when_none_exist()
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Recent Events')
            ->assertSee('No recent events to display');
    }

    public function test_dashboard_calculates_kpis_correctly()
    {
        // Create multiple gateways with different statuses
        $onlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now(),
            'success_count' => 100,
            'failure_count' => 0,
        ]);

        $offlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now()->subHours(2), // Offline
            'success_count' => 50,
            'failure_count' => 50,
        ]);

        // Create data points for both gateways
        DataPoint::factory()->create([
            'gateway_id' => $onlineGateway->id,
            'is_enabled' => true,
        ]);

        DataPoint::factory()->create([
            'gateway_id' => $offlineGateway->id,
            'is_enabled' => true,
        ]);

        // Create readings with different qualities
        Reading::factory()->create([
            'data_point_id' => DataPoint::first()->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        Reading::factory()->create([
            'data_point_id' => DataPoint::skip(1)->first()->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(Dashboard::class);

        // Check that KPIs are calculated
        $kpis = $component->get('kpis');
        
        $this->assertEquals(1, $kpis['online_gateways']['value']); // Only 1 online
        $this->assertEquals(2, $kpis['online_gateways']['total']); // 2 total
        $this->assertEquals(50, $kpis['online_gateways']['percentage']); // 50% online
        $this->assertEquals(50.0, $kpis['poll_success_rate']['value']); // 50% success rate
    }

    public function test_dashboard_refreshes_data_on_events()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $component = Livewire::test(Dashboard::class);

        // Simulate gateway update event
        $component->dispatch('gateway-updated');
        
        // Component should refresh data
        $component->assertSee('Test Gateway');
    }

    public function test_dashboard_handles_sparkline_data()
    {
        $gateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Create readings for sparkline
        for ($i = 0; $i < 5; $i++) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'quality' => 'good',
                'read_at' => now()->subMinutes(10 * $i),
            ]);
        }

        $component = Livewire::test(Dashboard::class);
        $gateways = $component->get('gateways');
        
        $this->assertNotEmpty($gateways[0]['sparkline_data']);
        $this->assertIsArray($gateways[0]['sparkline_data']);
    }

    public function test_dashboard_auto_refreshes_every_30_seconds()
    {
        Livewire::test(Dashboard::class)
            ->assertSee('wire:poll.30s="refreshDashboard"');
    }

    public function test_dashboard_accessibility_features()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('aria-labelledby')
            ->assertSee('aria-hidden="true"')
            ->assertSee('role="img"')
            ->assertSee('role="list"');
    }

    public function test_dashboard_responsive_design_classes()
    {
        Livewire::test(Dashboard::class)
            ->assertSee('grid-cols-1 md:grid-cols-3') // KPI tiles responsive
            ->assertSee('sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4') // Fleet status responsive
            ->assertSee('gap-4 md:gap-6'); // Responsive gaps
    }

    public function test_dashboard_status_indicators()
    {
        // Test different gateway statuses
        $goodGateway = Gateway::factory()->create([
            'success_count' => 100,
            'failure_count' => 0,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $warningGateway = Gateway::factory()->create([
            'success_count' => 85,
            'failure_count' => 15,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $errorGateway = Gateway::factory()->create([
            'success_count' => 50,
            'failure_count' => 50,
            'is_active' => true,
            'last_seen_at' => now()->subHours(2), // Offline
        ]);

        DataPoint::factory()->create(['gateway_id' => $goodGateway->id, 'is_enabled' => true]);
        DataPoint::factory()->create(['gateway_id' => $warningGateway->id, 'is_enabled' => true]);
        DataPoint::factory()->create(['gateway_id' => $errorGateway->id, 'is_enabled' => true]);

        $component = Livewire::test(Dashboard::class);
        $kpis = $component->get('kpis');

        // Check status calculations
        $this->assertContains($kpis['online_gateways']['status'], ['good', 'warning', 'error']);
        $this->assertContains($kpis['poll_success_rate']['status'], ['good', 'warning', 'error']);
        $this->assertContains($kpis['average_latency']['status'], ['good', 'warning', 'error']);
    }
}