<?php

namespace Tests\Unit;

use App\Livewire\LiveData;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LiveDataComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Voltage L1',
            'data_type' => 'float32',
            'is_enabled' => true,
        ]);
        
        // Create some readings for trend data
        for ($i = 0; $i < 10; $i++) {
            Reading::factory()->create([
                'data_point_id' => $this->dataPoint->id,
                'scaled_value' => 230.0 + ($i * 0.5),
                'quality' => 'good',
                'read_at' => now()->subMinutes(10 - $i),
            ]);
        }
    }

    /** @test */
    public function it_can_render_the_component()
    {
        Livewire::test(LiveData::class)
            ->assertStatus(200)
            ->assertSee('Live Data Readings')
            ->assertSee('Test Gateway')
            ->assertSee('Voltage L1');
    }

    /** @test */
    public function it_loads_available_filters_on_mount()
    {
        $component = Livewire::test(LiveData::class);
        
        $this->assertCount(1, $component->get('availableFilters')['gateways']);
        $this->assertContains('Meter_1', $component->get('availableFilters')['groups']);
        $this->assertContains('float32', $component->get('availableFilters')['tags']);
    }

    /** @test */
    public function it_loads_live_data_with_trend_information()
    {
        $component = Livewire::test(LiveData::class);
        
        $dataPoints = $component->get('dataPoints');
        $this->assertCount(1, $dataPoints);
        
        $dataPoint = $dataPoints[0];
        $this->assertEquals('Test Gateway', $dataPoint['gateway_name']);
        $this->assertEquals('Meter_1', $dataPoint['application']);
        $this->assertEquals('Voltage L1', $dataPoint['label']);
        $this->assertCount(10, $dataPoint['trend_data']);
        $this->assertEquals('up', $dataPoint['status']);
    }

    /** @test */
    public function it_can_filter_by_gateway()
    {
        // Create another gateway with data point
        $gateway2 = Gateway::factory()->create([
            'name' => 'Gateway 2',
            'is_active' => true,
        ]);
        
        DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'is_enabled' => true,
        ]);

        $component = Livewire::test(LiveData::class);
        
        // Initially should see both gateways' data points
        $this->assertCount(2, $component->get('dataPoints'));
        
        // Filter by first gateway
        $component->call('setFilter', 'gateway', $this->gateway->id);
        
        $dataPoints = $component->get('dataPoints');
        $this->assertCount(1, $dataPoints);
        $this->assertEquals('Test Gateway', $dataPoints[0]['gateway_name']);
    }

    /** @test */
    public function it_can_filter_by_group()
    {
        // Create another data point in different group
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Current L1',
            'is_enabled' => true,
        ]);

        $component = Livewire::test(LiveData::class);
        
        // Initially should see both groups
        $this->assertCount(2, $component->get('dataPoints'));
        
        // Filter by Meter_1 group
        $component->call('setFilter', 'application', 'Meter_1');
        
        $dataPoints = $component->get('dataPoints');
        $this->assertCount(1, $dataPoints);
        $this->assertEquals('Meter_1', $dataPoints[0]['application']);
    }

    /** @test */
    public function it_can_filter_by_data_type_tag()
    {
        // Create another data point with different data type
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Status',
            'data_type' => 'uint16',
            'is_enabled' => true,
        ]);

        $component = Livewire::test(LiveData::class);
        
        // Initially should see both data types
        $this->assertCount(2, $component->get('dataPoints'));
        
        // Filter by float32 tag
        $component->call('setFilter', 'tag', 'float32');
        
        $dataPoints = $component->get('dataPoints');
        $this->assertCount(1, $dataPoints);
        $this->assertEquals('float32', $dataPoints[0]['data_type']);
    }

    /** @test */
    public function it_can_clear_individual_filters()
    {
        $component = Livewire::test(LiveData::class);
        
        // Set a filter
        $component->call('setFilter', 'gateway', $this->gateway->id);
        $this->assertEquals($this->gateway->id, $component->get('filters')['gateway']);
        
        // Clear the filter
        $component->call('clearFilter', 'gateway');
        $this->assertNull($component->get('filters')['gateway']);
    }

    /** @test */
    public function it_can_clear_all_filters()
    {
        $component = Livewire::test(LiveData::class);
        
        // Set multiple filters
        $component->call('setFilter', 'gateway', $this->gateway->id);
        $component->call('setFilter', 'application', 'Meter_1');
        
        $this->assertEquals($this->gateway->id, $component->get('filters')['gateway']);
        $this->assertEquals('Meter_1', $component->get('filters')['application']);
        
        // Clear all filters
        $component->call('clearAllFilters');
        
        $filters = $component->get('filters');
        $this->assertNull($filters['gateway']);
        $this->assertNull($filters['application']);
        $this->assertNull($filters['tag']);
    }

    /** @test */
    public function it_can_toggle_density_mode()
    {
        $component = Livewire::test(LiveData::class);
        
        // Initially comfortable
        $this->assertEquals('comfortable', $component->get('density'));
        
        // Toggle to compact
        $component->call('toggleDensity');
        $this->assertEquals('compact', $component->get('density'));
        
        // Toggle back to comfortable
        $component->call('toggleDensity');
        $this->assertEquals('comfortable', $component->get('density'));
    }

    /** @test */
    public function it_shows_correct_data_point_status()
    {
        $component = Livewire::test(LiveData::class);
        
        $dataPoints = $component->get('dataPoints');
        $dataPoint = $dataPoints[0];
        
        // Should be 'up' with good quality and recent reading
        $this->assertEquals('up', $dataPoint['status']);
        
        // Test offline gateway
        $this->gateway->update(['last_seen_at' => now()->subHours(2)]);
        $component->call('loadLiveData');
        
        $dataPoints = $component->get('dataPoints');
        $dataPoint = $dataPoints[0];
        $this->assertEquals('down', $dataPoint['status']);
    }

    /** @test */
    public function it_generates_active_filters_property_correctly()
    {
        $component = Livewire::test(LiveData::class);
        
        // Set some filters
        $component->call('setFilter', 'gateway', $this->gateway->id);
        $component->call('setFilter', 'application', 'Meter_1');
        
        $activeFilters = $component->get('activeFilters');
        
        $this->assertCount(2, $activeFilters);
        
        $gatewayFilter = collect($activeFilters)->firstWhere('type', 'gateway');
        $this->assertEquals('Test Gateway', $gatewayFilter['label']);
        
        $groupFilter = collect($activeFilters)->firstWhere('type', 'application');
        $this->assertEquals('Meter_1', $groupFilter['label']);
    }

    /** @test */
    public function it_only_shows_enabled_data_points()
    {
        // Create disabled data point
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Disabled Point',
            'is_enabled' => false,
        ]);

        $component = Livewire::test(LiveData::class);
        
        $dataPoints = $component->get('dataPoints');
        
        // Should only see the enabled data point
        $this->assertCount(1, $dataPoints);
        $this->assertEquals('Voltage L1', $dataPoints[0]['label']);
    }

    /** @test */
    public function it_only_shows_data_points_from_active_gateways()
    {
        // Create inactive gateway with data point
        $inactiveGateway = Gateway::factory()->create([
            'name' => 'Inactive Gateway',
            'is_active' => false,
        ]);
        
        DataPoint::factory()->create([
            'gateway_id' => $inactiveGateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Inactive Point',
            'is_enabled' => true,
        ]);

        $component = Livewire::test(LiveData::class);
        
        $dataPoints = $component->get('dataPoints');
        
        // Should only see data points from active gateways
        $this->assertCount(1, $dataPoints);
        $this->assertEquals('Test Gateway', $dataPoints[0]['gateway_name']);
    }

    /** @test */
    public function it_refreshes_data_on_events()
    {
        $component = Livewire::test(LiveData::class);
        
        // Create new reading
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 240.0,
            'quality' => 'good',
            'read_at' => now(),
        ]);
        
        // Trigger refresh event
        $component->dispatch('reading-created');
        
        $dataPoints = $component->get('dataPoints');
        $this->assertEquals('240.00', $dataPoints[0]['current_value']);
    }
}