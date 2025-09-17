<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveDataInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $gateway;
    protected $dataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Voltage L1',
            'data_type' => 'float32',
            'is_enabled' => true,
        ]);
        
        // Create readings for testing
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 230.5,
            'quality' => 'good',
            'read_at' => now(),
        ]);
    }

    /** @test */
    public function authenticated_user_can_access_live_data_page()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Live Data Readings');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_live_data_page()
    {
        $response = $this->get('/admin/live-data');

        $response->assertRedirect('/admin/login');
    }

    /** @test */
    public function live_data_page_displays_data_points_with_current_values()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Test Gateway');
        $response->assertSee('Meter_1');
        $response->assertSee('Voltage L1');
        $response->assertSee('230.50');
    }

    /** @test */
    public function live_data_page_shows_gateway_filter_options()
    {
        // Create additional gateway
        $gateway2 = Gateway::factory()->create([
            'name' => 'Gateway 2',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('All Gateways');
        $response->assertSee('Test Gateway');
        $response->assertSee('Gateway 2');
    }

    /** @test */
    public function live_data_page_shows_group_filter_options()
    {
        // Create data point in different group
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_2',
            'label' => 'Current L1',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('All Groups');
        $response->assertSee('Meter_1');
        $response->assertSee('Meter_2');
    }

    /** @test */
    public function live_data_page_shows_data_type_filter_options()
    {
        // Create data point with different data type
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Status',
            'data_type' => 'uint16',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('All Types');
        $response->assertSee('Float32');
        $response->assertSee('Uint16');
    }

    /** @test */
    public function live_data_page_shows_status_indicators()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Up'); // Status should be 'Up' for good quality recent reading
    }

    /** @test */
    public function live_data_page_shows_density_toggle()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Compact View'); // Should show toggle to compact view
    }

    /** @test */
    public function live_data_page_shows_auto_refresh_indicator()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Auto-refreshing every');
    }

    /** @test */
    public function live_data_page_shows_empty_state_when_no_data_points()
    {
        // Delete all data points
        DataPoint::query()->delete();

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('No data points found');
        $response->assertSee('Get started by adding data points to your gateways');
    }

    /** @test */
    public function live_data_page_shows_trend_chart_placeholder()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('sparkline-chart'); // Canvas element for trend chart
    }

    /** @test */
    public function live_data_page_shows_register_and_data_type_info()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Reg: ' . $this->dataPoint->register_address);
        $response->assertSee('Float32');
    }

    /** @test */
    public function live_data_page_shows_last_updated_timestamp()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        // Should show time format and relative time
        $response->assertSeeInOrder([':', ':', 'seconds ago']); // HH:MM:SS format and relative time
    }

    /** @test */
    public function live_data_page_handles_data_points_without_readings()
    {
        // Create data point without readings
        $dataPointNoReadings = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_2',
            'label' => 'No Data Point',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('No Data Point');
        $response->assertSee('N/A'); // Should show N/A for current value
        $response->assertSee('Never'); // Should show Never for last updated
    }

    /** @test */
    public function live_data_page_shows_quality_status_correctly()
    {
        // Create reading with bad quality
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 220.0,
            'quality' => 'bad',
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        // Should still show the most recent reading value
        $response->assertSee('220.00');
    }

    /** @test */
    public function live_data_page_only_shows_enabled_data_points()
    {
        // Create disabled data point
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Disabled Point',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Voltage L1'); // Should see enabled point
        $response->assertDontSee('Disabled Point'); // Should not see disabled point
    }

    /** @test */
    public function live_data_page_only_shows_data_from_active_gateways()
    {
        // Create inactive gateway with data point
        $inactiveGateway = Gateway::factory()->create([
            'name' => 'Inactive Gateway',
            'is_active' => false,
        ]);
        
        DataPoint::factory()->create([
            'gateway_id' => $inactiveGateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Inactive Point',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('Test Gateway'); // Should see active gateway
        $response->assertDontSee('Inactive Gateway'); // Should not see inactive gateway
        $response->assertDontSee('Inactive Point'); // Should not see data from inactive gateway
    }

    /** @test */
    public function live_data_page_has_proper_table_structure()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        
        // Check for table headers
        $response->assertSee('Gateway');
        $response->assertSee('Group');
        $response->assertSee('Data Point');
        $response->assertSee('Current Value');
        $response->assertSee('Status');
        $response->assertSee('Trend (Last 10)');
        $response->assertSee('Last Updated');
    }

    /** @test */
    public function live_data_page_includes_javascript_for_sparklines()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/live-data');

        $response->assertStatus(200);
        $response->assertSee('initializeSparklines');
        $response->assertSee('sparkline-chart');
    }
}