<?php

namespace Tests\Feature;

use App\Livewire\PastReadings;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class PastReadingsComponentTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $gateway;
    protected DataPoint $dataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateway
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
        ]);
        
        // Create test data point
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Test Data Point',
            'group_name' => 'Test Group',
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_render_past_readings_component()
    {
        Livewire::test(PastReadings::class)
            ->assertStatus(200)
            ->assertSee('Past Readings')
            ->assertSee('Success:')
            ->assertSee('Fail:');
    }

    /** @test */
    public function it_displays_correct_success_fail_statistics()
    {
        // Create test readings with different qualities
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(2),
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subMinutes(30),
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'uncertain',
            'read_at' => now()->subMinutes(15),
        ]);

        Livewire::test(PastReadings::class)
            ->assertSet('statistics.success_count', 2)
            ->assertSet('statistics.fail_count', 2)
            ->assertSet('statistics.total_count', 4)
            ->assertSet('statistics.success_rate', 50.0)
            ->assertSee('Success: 2')
            ->assertSee('Fail: 2')
            ->assertSee('50% success rate');
    }

    /** @test */
    public function it_filters_statistics_by_gateway()
    {
        // Create another gateway and data point
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway 2', 'is_active' => true]);
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'is_enabled' => true,
        ]);

        // Create readings for first gateway (2 good, 1 bad)
        Reading::factory()->count(2)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        // Create readings for second gateway (1 good, 2 bad)
        Reading::factory()->create([
            'data_point_id' => $dataPoint2->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->count(2)->create([
            'data_point_id' => $dataPoint2->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        // Test filtering by first gateway
        Livewire::test(PastReadings::class)
            ->call('setFilter', 'gateway', $this->gateway->id)
            ->assertSet('statistics.success_count', 2)
            ->assertSet('statistics.fail_count', 1)
            ->assertSet('statistics.total_count', 3);

        // Test filtering by second gateway
        Livewire::test(PastReadings::class)
            ->call('setFilter', 'gateway', $gateway2->id)
            ->assertSet('statistics.success_count', 1)
            ->assertSet('statistics.fail_count', 2)
            ->assertSet('statistics.total_count', 3);
    }

    /** @test */
    public function it_filters_statistics_by_date_range()
    {
        // Create readings at different times
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subDays(2), // Outside range
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(2), // Within range
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1), // Within range
        ]);

        $component = Livewire::test(PastReadings::class)
            ->set('filters.date_from', now()->subHours(3)->format('Y-m-d H:i'))
            ->set('filters.date_to', now()->format('Y-m-d H:i'))
            ->call('loadStatistics');

        $component->assertSet('statistics.success_count', 1)
            ->assertSet('statistics.fail_count', 1)
            ->assertSet('statistics.total_count', 2);
    }

    /** @test */
    public function it_filters_statistics_by_quality()
    {
        // Create readings with different qualities
        Reading::factory()->count(3)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->count(2)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        // Filter by good quality only
        Livewire::test(PastReadings::class)
            ->call('setFilter', 'quality', 'good')
            ->assertSet('statistics.success_count', 3)
            ->assertSet('statistics.fail_count', 0)
            ->assertSet('statistics.total_count', 3);

        // Filter by bad quality only
        Livewire::test(PastReadings::class)
            ->call('setFilter', 'quality', 'bad')
            ->assertSet('statistics.success_count', 0)
            ->assertSet('statistics.fail_count', 2)
            ->assertSet('statistics.total_count', 2);
    }

    /** @test */
    public function it_caches_statistics_for_performance()
    {
        // Create test readings
        Reading::factory()->count(5)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // First call should compute and cache
        $component->call('loadStatistics');
        
        // Verify cache key exists
        $cacheKey = $component->instance()->getStatisticsCacheKey();
        $this->assertTrue(Cache::has($cacheKey));
        
        // Verify cached data
        $cachedStats = Cache::get($cacheKey);
        $this->assertEquals(5, $cachedStats['success_count']);
        $this->assertEquals(0, $cachedStats['fail_count']);
    }

    /** @test */
    public function it_clears_cache_when_filters_change()
    {
        // Create test readings
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // Load initial statistics (creates cache)
        $component->call('loadStatistics');
        $initialCacheKey = $component->instance()->getStatisticsCacheKey();
        $this->assertTrue(Cache::has($initialCacheKey));
        
        // Change filter (should clear cache and create new one)
        $component->call('setFilter', 'gateway', $this->gateway->id);
        
        // Old cache should be cleared, new cache should exist
        $this->assertFalse(Cache::has($initialCacheKey));
        
        $newCacheKey = $component->instance()->getStatisticsCacheKey();
        $this->assertTrue(Cache::has($newCacheKey));
        $this->assertNotEquals($initialCacheKey, $newCacheKey);
    }

    /** @test */
    public function it_handles_date_range_quick_filters()
    {
        // Create readings at different times
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subMinutes(30), // Within last hour
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(2), // Within last 24h but not last hour
        ]);
        
        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subDays(2), // Outside last 24h
        ]);

        // Test last hour filter
        $component = Livewire::test(PastReadings::class)
            ->call('setDateRange', 'last_hour');
            
        $this->assertEquals(
            now()->subHour()->format('Y-m-d H:i'),
            $component->get('filters.date_from')
        );
        
        // Test last 24h filter
        $component->call('setDateRange', 'last_24h');
        
        $this->assertEquals(
            now()->subDay()->format('Y-m-d H:i'),
            $component->get('filters.date_from')
        );
    }

    /** @test */
    public function it_displays_readings_with_pagination()
    {
        // Create more readings than per page limit
        Reading::factory()->count(60)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        Livewire::test(PastReadings::class)
            ->assertSee('Past Readings')
            ->assertSee($this->gateway->name)
            ->assertSee($this->dataPoint->label);
    }

    /** @test */
    public function it_sorts_readings_by_different_fields()
    {
        // Create readings with different values and times
        $reading1 = Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 10.5,
            'quality' => 'good',
            'read_at' => now()->subHours(2),
        ]);
        
        $reading2 = Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 5.2,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // Test sorting by timestamp (default)
        $component->assertSet('sortField', 'read_at')
            ->assertSet('sortDirection', 'desc');
        
        // Test sorting by value
        $component->call('sortBy', 'scaled_value')
            ->assertSet('sortField', 'scaled_value')
            ->assertSet('sortDirection', 'desc');
        
        // Test toggling sort direction
        $component->call('sortBy', 'scaled_value')
            ->assertSet('sortDirection', 'asc');
    }

    /** @test */
    public function it_handles_empty_state_correctly()
    {
        // Test with no readings
        Livewire::test(PastReadings::class)
            ->assertSee('No readings found')
            ->assertSet('statistics.success_count', 0)
            ->assertSet('statistics.fail_count', 0)
            ->assertSet('statistics.total_count', 0);
    }

    /** @test */
    public function it_updates_available_data_points_when_gateway_filter_changes()
    {
        // Create another gateway and data point
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway 2', 'is_active' => true]);
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'label' => 'Data Point 2',
            'is_enabled' => true,
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // Initially should show all data points
        $this->assertCount(2, $component->get('availableFilters.data_points'));
        
        // Filter by first gateway
        $component->call('setFilter', 'gateway', $this->gateway->id);
        
        // Should only show data points for selected gateway
        $dataPoints = $component->get('availableFilters.data_points');
        $this->assertCount(1, $dataPoints);
        $this->assertEquals($this->dataPoint->id, $dataPoints[0]['id']);
    }

    /** @test */
    public function it_computes_success_rate_correctly_with_edge_cases()
    {
        // Test with zero readings
        $component = Livewire::test(PastReadings::class);
        $component->assertSet('statistics.success_rate', 0);
        
        // Test with only good readings
        Reading::factory()->count(5)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        $component->call('loadStatistics')
            ->assertSet('statistics.success_rate', 100.0);
        
        // Test with only bad readings
        Reading::query()->delete();
        Reading::factory()->count(3)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);
        
        $component->call('loadStatistics')
            ->assertSet('statistics.success_rate', 0.0);
    }

    /** @test */
    public function it_formats_large_numbers_correctly_in_statistics()
    {
        // Create a large number of readings
        Reading::factory()->count(1500)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->count(500)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        Livewire::test(PastReadings::class)
            ->assertSee('Success: 1,500') // Should format with commas
            ->assertSee('Fail: 500');
    }
}