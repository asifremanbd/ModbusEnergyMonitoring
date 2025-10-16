<?php

namespace Tests\Performance;

use App\Livewire\PastReadings;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PastReadingsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $gateway;
    protected DataPoint $dataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gateway = Gateway::factory()->create([
            'name' => 'Performance Test Gateway',
            'is_active' => true,
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Performance Test Data Point',
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_computes_statistics_efficiently_with_large_dataset()
    {
        // Create a large dataset (10,000 readings)
        $batchSize = 1000;
        $totalReadings = 10000;
        
        for ($i = 0; $i < $totalReadings / $batchSize; $i++) {
            $readings = [];
            for ($j = 0; $j < $batchSize; $j++) {
                $readings[] = [
                    'data_point_id' => $this->dataPoint->id,
                    'raw_value' => rand(1, 100),
                    'scaled_value' => rand(1, 100) / 10,
                    'quality' => ['good', 'bad', 'uncertain'][rand(0, 2)],
                    'read_at' => now()->subMinutes(rand(1, 1440)), // Random time in last 24h
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Reading::insert($readings);
        }

        // Measure statistics computation time
        $startTime = microtime(true);
        $queryCount = DB::getQueryLog();
        DB::enableQueryLog();
        
        $component = Livewire::test(PastReadings::class);
        $component->call('loadStatistics');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $queries = DB::getQueryLog();
        
        // Performance assertions
        $this->assertLessThan(2.0, $executionTime, 'Statistics computation should complete within 2 seconds');
        $this->assertLessThan(10, count($queries), 'Should use minimal database queries');
        
        // Verify statistics are correct
        $statistics = $component->get('statistics');
        $this->assertEquals($totalReadings, $statistics['total_count']);
        $this->assertGreaterThan(0, $statistics['success_count']);
        $this->assertGreaterThan(0, $statistics['fail_count']);
    }

    /** @test */
    public function it_uses_caching_effectively_for_repeated_requests()
    {
        // Create moderate dataset
        Reading::factory()->count(5000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // First request - should compute and cache
        DB::enableQueryLog();
        $startTime = microtime(true);
        
        $component->call('loadStatistics');
        
        $firstRequestTime = microtime(true) - $startTime;
        $firstRequestQueries = count(DB::getQueryLog());
        
        // Second request - should use cache
        DB::flushQueryLog();
        $startTime = microtime(true);
        
        $component->call('loadStatistics');
        
        $secondRequestTime = microtime(true) - $startTime;
        $secondRequestQueries = count(DB::getQueryLog());
        
        // Cache should make second request much faster
        $this->assertLessThan($firstRequestTime * 0.1, $secondRequestTime, 'Cached request should be at least 10x faster');
        $this->assertEquals(0, $secondRequestQueries, 'Cached request should not hit database');
    }

    /** @test */
    public function it_handles_concurrent_statistics_requests_efficiently()
    {
        // Create test data
        Reading::factory()->count(1000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        // Simulate concurrent requests
        $components = [];
        $startTime = microtime(true);
        
        for ($i = 0; $i < 5; $i++) {
            $components[] = Livewire::test(PastReadings::class);
        }
        
        // Load statistics concurrently
        foreach ($components as $component) {
            $component->call('loadStatistics');
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // Should handle concurrent requests efficiently
        $this->assertLessThan(3.0, $totalTime, 'Concurrent requests should complete within 3 seconds');
        
        // All components should have same statistics
        $firstStats = $components[0]->get('statistics');
        foreach ($components as $component) {
            $this->assertEquals($firstStats, $component->get('statistics'));
        }
    }

    /** @test */
    public function it_optimizes_queries_with_filters_applied()
    {
        // Create multiple gateways and data points
        $gateway2 = Gateway::factory()->create(['is_active' => true]);
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'is_enabled' => true,
        ]);

        // Create readings for both gateways
        Reading::factory()->count(2000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->count(2000)->create([
            'data_point_id' => $dataPoint2->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);

        DB::enableQueryLog();
        
        // Test with gateway filter
        $component = Livewire::test(PastReadings::class);
        $component->call('setFilter', 'gateway', $this->gateway->id);
        
        $queries = DB::getQueryLog();
        
        // Should use efficient queries with proper WHERE clauses
        $this->assertLessThan(8, count($queries), 'Should use minimal queries even with filters');
        
        // Verify filter works correctly
        $statistics = $component->get('statistics');
        $this->assertEquals(2000, $statistics['success_count']);
        $this->assertEquals(0, $statistics['fail_count']);
    }

    /** @test */
    public function it_handles_date_range_filters_efficiently()
    {
        // Create readings across different time periods
        Reading::factory()->count(1000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subDays(7), // Old data
        ]);
        
        Reading::factory()->count(500)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(2), // Recent data
        ]);

        DB::enableQueryLog();
        
        // Test with date range filter
        $component = Livewire::test(PastReadings::class);
        $component->set('filters.date_from', now()->subHours(3)->format('Y-m-d H:i'));
        $component->set('filters.date_to', now()->format('Y-m-d H:i'));
        $component->call('loadStatistics');
        
        $queries = DB::getQueryLog();
        
        // Should use indexed date queries
        $this->assertLessThan(6, count($queries), 'Date range queries should be efficient');
        
        // Verify only recent data is counted
        $statistics = $component->get('statistics');
        $this->assertEquals(500, $statistics['total_count']);
    }

    /** @test */
    public function it_maintains_performance_with_complex_filter_combinations()
    {
        // Create complex test scenario
        $gateway2 = Gateway::factory()->create(['is_active' => true]);
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'is_enabled' => true,
        ]);
        
        $dataPoint3 = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'application' => 'automation',
            'unit' => 'm³',
            'load_type' => 'water',
            'is_enabled' => true,
        ]);

        // Create readings with various combinations
        Reading::factory()->count(1000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(rand(1, 24)),
        ]);
        
        Reading::factory()->count(800)->create([
            'data_point_id' => $dataPoint2->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(rand(1, 24)),
        ]);
        
        Reading::factory()->count(600)->create([
            'data_point_id' => $dataPoint3->id,
            'quality' => 'uncertain',
            'read_at' => now()->subHours(rand(1, 24)),
        ]);

        $startTime = microtime(true);
        DB::enableQueryLog();
        
        // Apply multiple filters
        $component = Livewire::test(PastReadings::class);
        $component->call('setFilter', 'gateway', $this->gateway->id);
        $component->call('setFilter', 'application', 'Group B');
        $component->call('setFilter', 'quality', 'uncertain');
        $component->set('filters.date_from', now()->subHours(12)->format('Y-m-d H:i'));
        $component->call('loadStatistics');
        
        $executionTime = microtime(true) - $startTime;
        $queries = DB::getQueryLog();
        
        // Should handle complex filters efficiently
        $this->assertLessThan(1.5, $executionTime, 'Complex filter queries should complete quickly');
        $this->assertLessThan(10, count($queries), 'Should optimize complex filter queries');
    }

    /** @test */
    public function it_clears_cache_efficiently_when_filters_change()
    {
        // Create test data
        Reading::factory()->count(1000)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);

        $component = Livewire::test(PastReadings::class);
        
        // Load initial statistics (creates cache)
        $component->call('loadStatistics');
        
        $startTime = microtime(true);
        
        // Change filter multiple times
        for ($i = 0; $i < 5; $i++) {
            $component->call('setFilter', 'quality', $i % 2 === 0 ? 'good' : 'bad');
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // Cache operations should be fast
        $this->assertLessThan(0.5, $totalTime, 'Cache clearing and recreation should be fast');
    }

    /** @test */
    public function it_handles_memory_efficiently_with_large_datasets()
    {
        // Create large dataset
        $batchSize = 1000;
        $totalReadings = 5000;
        
        for ($i = 0; $i < $totalReadings / $batchSize; $i++) {
            $readings = [];
            for ($j = 0; $j < $batchSize; $j++) {
                $readings[] = [
                    'data_point_id' => $this->dataPoint->id,
                    'raw_value' => rand(1, 100),
                    'scaled_value' => rand(1, 100) / 10,
                    'quality' => 'good',
                    'read_at' => now()->subMinutes(rand(1, 1440)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Reading::insert($readings);
        }

        $memoryBefore = memory_get_usage(true);
        
        $component = Livewire::test(PastReadings::class);
        $component->call('loadStatistics');
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        // Should not use excessive memory (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable');
    }
}