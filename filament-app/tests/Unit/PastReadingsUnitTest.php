<?php

namespace Tests\Unit;

use App\Livewire\PastReadings;
use PHPUnit\Framework\TestCase;

class PastReadingsUnitTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_past_readings_component()
    {
        $component = new PastReadings();
        
        $this->assertInstanceOf(PastReadings::class, $component);
        $this->assertEquals('read_at', $component->sortField);
        $this->assertEquals('desc', $component->sortDirection);
        $this->assertEquals(50, $component->perPage);
    }

    /** @test */
    public function it_generates_unique_cache_keys_for_different_filters()
    {
        $component = new PastReadings();
        
        // Test with empty filters
        $component->filters = [
            'gateway' => null,
            'application' => null,
            'data_point' => null,
            'quality' => null,
            'date_from' => null,
            'date_to' => null,
        ];
        $cacheKey1 = $component->getStatisticsCacheKey();
        
        // Test with gateway filter
        $component->filters['gateway'] = 1;
        $cacheKey2 = $component->getStatisticsCacheKey();
        
        // Test with quality filter
        $component->filters['quality'] = 'good';
        $cacheKey3 = $component->getStatisticsCacheKey();
        
        $this->assertNotEquals($cacheKey1, $cacheKey2);
        $this->assertNotEquals($cacheKey2, $cacheKey3);
        $this->assertNotEquals($cacheKey1, $cacheKey3);
        
        // All cache keys should start with the same prefix
        $this->assertStringStartsWith('past_readings_stats_', $cacheKey1);
        $this->assertStringStartsWith('past_readings_stats_', $cacheKey2);
        $this->assertStringStartsWith('past_readings_stats_', $cacheKey3);
    }

    /** @test */
    public function it_has_correct_default_filter_structure()
    {
        $component = new PastReadings();
        
        $expectedFilters = [
            'gateway' => null,
            'application' => null,
            'data_point' => null,
            'quality' => null,
            'date_from' => null,
            'date_to' => null,
        ];
        
        $this->assertEquals($expectedFilters, $component->filters);
    }

    /** @test */
    public function it_has_correct_available_filters_structure()
    {
        $component = new PastReadings();
        
        $expectedAvailableFilters = [
            'gateways' => [],
            'groups' => [],
            'data_points' => [],
            'qualities' => ['good', 'bad', 'uncertain'],
        ];
        
        $this->assertEquals($expectedAvailableFilters, $component->availableFilters);
    }

    /** @test */
    public function it_has_correct_default_statistics_structure()
    {
        $component = new PastReadings();
        
        $expectedStatistics = [
            'success_count' => 0,
            'fail_count' => 0,
            'total_count' => 0,
            'success_rate' => 0,
        ];
        
        $this->assertEquals($expectedStatistics, $component->statistics);
    }

    /** @test */
    public function it_has_correct_pagination_defaults()
    {
        $component = new PastReadings();
        
        $this->assertEquals(50, $component->perPage);
        $this->assertEquals('read_at', $component->sortField);
        $this->assertEquals('desc', $component->sortDirection);
    }
}