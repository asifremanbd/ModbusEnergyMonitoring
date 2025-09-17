<?php

namespace Tests\Performance;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class DatabasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data structure
        $this->createTestDataStructure();
    }

    private function createTestDataStructure(): void
    {
        // Create 10 gateways
        $gateways = Gateway::factory()->count(10)->create();

        // Create 20 data points per gateway (200 total)
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(20)->create([
                'gateway_id' => $gateway->id,
            ]);
        }
    }

    /** @test */
    public function test_time_series_query_performance_with_large_dataset()
    {
        // Create 100,000 readings across all data points over 30 days
        $dataPoints = DataPoint::all();
        $readings = [];
        
        $startDate = now()->subDays(30);
        $totalReadings = 100000;
        $readingsPerBatch = 1000;

        for ($i = 0; $i < $totalReadings; $i += $readingsPerBatch) {
            $batch = [];
            
            for ($j = 0; $j < $readingsPerBatch && ($i + $j) < $totalReadings; $j++) {
                $dataPoint = $dataPoints->random();
                $readTime = $startDate->copy()->addMinutes(rand(0, 43200)); // Random time in 30 days
                
                $batch[] = [
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                    'scaled_value' => rand(100, 999) / 10,
                    'quality' => 'good',
                    'read_at' => $readTime,
                    'created_at' => now(),
                ];
            }
            
            Reading::insert($batch);
        }

        // Test 1: Query recent readings (last 24 hours)
        $startTime = microtime(true);
        
        $recentReadings = Reading::where('read_at', '>=', now()->subDay())
            ->with('dataPoint.gateway')
            ->orderBy('read_at', 'desc')
            ->limit(1000)
            ->get();
            
        $endTime = microtime(true);
        $recentQueryTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(500, $recentQueryTime,
            "Recent readings query took {$recentQueryTime}ms, expected < 500ms");

        // Test 2: Query readings for specific gateway (last 7 days)
        $gateway = Gateway::first();
        
        $startTime = microtime(true);
        
        $gatewayReadings = Reading::whereHas('dataPoint', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })
            ->where('read_at', '>=', now()->subWeek())
            ->orderBy('read_at', 'desc')
            ->get();
            
        $endTime = microtime(true);
        $gatewayQueryTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(300, $gatewayQueryTime,
            "Gateway readings query took {$gatewayQueryTime}ms, expected < 300ms");

        // Test 3: Aggregate query for dashboard KPIs
        $startTime = microtime(true);
        
        $kpiData = DB::table('readings')
            ->join('data_points', 'readings.data_point_id', '=', 'data_points.id')
            ->join('gateways', 'data_points.gateway_id', '=', 'gateways.id')
            ->where('readings.read_at', '>=', now()->subHour())
            ->selectRaw('
                COUNT(DISTINCT gateways.id) as online_gateways,
                COUNT(CASE WHEN readings.quality = "good" THEN 1 END) as successful_reads,
                COUNT(*) as total_reads,
                AVG(CASE WHEN readings.quality = "good" THEN 1.0 ELSE 0.0 END) * 100 as success_rate
            ')
            ->first();
            
        $endTime = microtime(true);
        $kpiQueryTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(200, $kpiQueryTime,
            "KPI aggregate query took {$kpiQueryTime}ms, expected < 200ms");

        $this->assertNotNull($kpiData);
    }

    /** @test */
    public function test_reading_insertion_performance()
    {
        $dataPoint = DataPoint::first();
        $batchSizes = [100, 500, 1000, 2000];
        
        foreach ($batchSizes as $batchSize) {
            $readings = [];
            
            for ($i = 0; $i < $batchSize; $i++) {
                $readings[] = [
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                    'scaled_value' => rand(100, 999) / 10,
                    'quality' => 'good',
                    'read_at' => now()->subMinutes(rand(1, 60)),
                    'created_at' => now(),
                ];
            }
            
            $startTime = microtime(true);
            Reading::insert($readings);
            $endTime = microtime(true);
            
            $insertTime = ($endTime - $startTime) * 1000;
            $timePerRecord = $insertTime / $batchSize;
            
            // Should insert at least 100 records per second (10ms per record max)
            $this->assertLessThan(10, $timePerRecord,
                "Batch insert of {$batchSize} records took {$timePerRecord}ms per record, expected < 10ms");
        }
    }

    /** @test */
    public function test_index_performance_on_time_series_queries()
    {
        // Create 50,000 readings for index testing
        $dataPoints = DataPoint::take(5)->get();
        $readings = [];
        
        for ($i = 0; $i < 50000; $i++) {
            $dataPoint = $dataPoints->random();
            $readings[] = [
                'data_point_id' => $dataPoint->id,
                'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                'scaled_value' => rand(100, 999) / 10,
                'quality' => 'good',
                'read_at' => now()->subDays(rand(1, 30))->subMinutes(rand(0, 1440)),
                'created_at' => now(),
            ];
            
            // Insert in batches to avoid memory issues
            if (count($readings) >= 1000) {
                Reading::insert($readings);
                $readings = [];
            }
        }
        
        if (!empty($readings)) {
            Reading::insert($readings);
        }

        // Test indexed queries
        $testQueries = [
            // Query by data_point_id and read_at (should use composite index)
            function () use ($dataPoints) {
                $dataPoint = $dataPoints->first();
                return Reading::where('data_point_id', $dataPoint->id)
                    ->where('read_at', '>=', now()->subDays(7))
                    ->orderBy('read_at', 'desc')
                    ->limit(100)
                    ->get();
            },
            
            // Query by read_at only (should use read_at index)
            function () {
                return Reading::where('read_at', '>=', now()->subDay())
                    ->orderBy('read_at', 'desc')
                    ->limit(500)
                    ->get();
            },
            
            // Query latest reading per data point
            function () use ($dataPoints) {
                return Reading::whereIn('data_point_id', $dataPoints->pluck('id'))
                    ->whereIn('id', function ($query) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('readings')
                            ->groupBy('data_point_id');
                    })
                    ->get();
            },
        ];

        foreach ($testQueries as $index => $queryFunction) {
            $startTime = microtime(true);
            $result = $queryFunction();
            $endTime = microtime(true);
            
            $queryTime = ($endTime - $startTime) * 1000;
            
            $this->assertLessThan(100, $queryTime,
                "Indexed query {$index} took {$queryTime}ms, expected < 100ms");
            
            $this->assertGreaterThan(0, $result->count());
        }
    }

    /** @test */
    public function test_concurrent_read_write_performance()
    {
        $dataPoint = DataPoint::first();
        
        // Simulate concurrent writes
        $writeStartTime = microtime(true);
        
        for ($i = 0; $i < 10; $i++) {
            $readings = [];
            
            for ($j = 0; $j < 100; $j++) {
                $readings[] = [
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                    'scaled_value' => rand(100, 999) / 10,
                    'quality' => 'good',
                    'read_at' => now()->subMinutes(rand(1, 60)),
                    'created_at' => now(),
                ];
            }
            
            Reading::insert($readings);
            
            // Simulate concurrent read during write
            $readStartTime = microtime(true);
            $recentReadings = Reading::where('data_point_id', $dataPoint->id)
                ->where('read_at', '>=', now()->subHour())
                ->orderBy('read_at', 'desc')
                ->limit(50)
                ->get();
            $readEndTime = microtime(true);
            
            $readTime = ($readEndTime - $readStartTime) * 1000;
            
            // Reads should remain fast even during writes
            $this->assertLessThan(50, $readTime,
                "Concurrent read during write took {$readTime}ms, expected < 50ms");
        }
        
        $writeEndTime = microtime(true);
        $totalWriteTime = ($writeEndTime - $writeStartTime) * 1000;
        
        // Total write time should be reasonable
        $this->assertLessThan(2000, $totalWriteTime,
            "Concurrent writes took {$totalWriteTime}ms, expected < 2000ms");
    }

    /** @test */
    public function test_data_retention_query_performance()
    {
        // Create old readings for retention testing
        $dataPoint = DataPoint::first();
        $oldReadings = [];
        
        // Create 10,000 old readings (older than 90 days)
        for ($i = 0; $i < 10000; $i++) {
            $oldReadings[] = [
                'data_point_id' => $dataPoint->id,
                'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                'scaled_value' => rand(100, 999) / 10,
                'quality' => 'good',
                'read_at' => now()->subDays(rand(91, 365)),
                'created_at' => now(),
            ];
            
            if (count($oldReadings) >= 1000) {
                Reading::insert($oldReadings);
                $oldReadings = [];
            }
        }
        
        if (!empty($oldReadings)) {
            Reading::insert($oldReadings);
        }

        // Test deletion performance for data retention
        $startTime = microtime(true);
        
        $deletedCount = Reading::where('read_at', '<', now()->subDays(90))->delete();
        
        $endTime = microtime(true);
        $deleteTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(1000, $deleteTime,
            "Data retention deletion took {$deleteTime}ms, expected < 1000ms");
        
        $this->assertGreaterThan(0, $deletedCount);
    }

    /** @test */
    public function test_dashboard_aggregation_query_performance()
    {
        // Create realistic data for dashboard testing
        $gateways = Gateway::take(5)->get();
        
        foreach ($gateways as $gateway) {
            $dataPoints = $gateway->dataPoints->take(10);
            
            foreach ($dataPoints as $dataPoint) {
                $readings = [];
                
                // Create 1000 readings per data point over last 24 hours
                for ($i = 0; $i < 1000; $i++) {
                    $readings[] = [
                        'data_point_id' => $dataPoint->id,
                        'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                        'scaled_value' => rand(100, 999) / 10,
                        'quality' => rand(0, 9) < 8 ? 'good' : 'bad', // 80% success rate
                        'read_at' => now()->subMinutes(rand(1, 1440)),
                        'created_at' => now(),
                    ];
                }
                
                Reading::insert($readings);
            }
        }

        // Test dashboard KPI queries
        $startTime = microtime(true);
        
        // Online gateways count
        $onlineGateways = Gateway::whereHas('dataPoints.readings', function ($query) {
                $query->where('read_at', '>=', now()->subMinutes(30));
            })
            ->where('is_active', true)
            ->count();

        // Success rate calculation
        $successRate = Reading::where('read_at', '>=', now()->subHour())
            ->selectRaw('
                COUNT(CASE WHEN quality = "good" THEN 1 END) * 100.0 / COUNT(*) as success_rate
            ')
            ->value('success_rate');

        // Average latency (simulated)
        $avgLatency = Reading::where('read_at', '>=', now()->subHour())
            ->where('quality', 'good')
            ->count() * 0.025; // Simulate 25ms average

        $endTime = microtime(true);
        $dashboardQueryTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(300, $dashboardQueryTime,
            "Dashboard KPI queries took {$dashboardQueryTime}ms, expected < 300ms");

        $this->assertGreaterThanOrEqual(0, $onlineGateways);
        $this->assertGreaterThanOrEqual(0, $successRate);
        $this->assertGreaterThanOrEqual(0, $avgLatency);
    }

    /** @test */
    public function test_live_data_query_performance()
    {
        // Create recent readings for live data testing
        $dataPoints = DataPoint::take(20)->get();
        
        foreach ($dataPoints as $dataPoint) {
            // Create 100 recent readings per data point
            $readings = [];
            
            for ($i = 0; $i < 100; $i++) {
                $readings[] = [
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                    'scaled_value' => rand(100, 999) / 10,
                    'quality' => 'good',
                    'read_at' => now()->subMinutes(rand(1, 60)),
                    'created_at' => now(),
                ];
            }
            
            Reading::insert($readings);
        }

        // Test live data query (latest reading per data point with trend data)
        $startTime = microtime(true);
        
        $liveData = DataPoint::with(['gateway', 'readings' => function ($query) {
                $query->orderBy('read_at', 'desc')->limit(10);
            }])
            ->whereHas('readings', function ($query) {
                $query->where('read_at', '>=', now()->subHour());
            })
            ->get()
            ->map(function ($dataPoint) {
                $latestReading = $dataPoint->readings->first();
                $trendData = $dataPoint->readings->pluck('scaled_value')->toArray();
                
                return [
                    'data_point' => $dataPoint,
                    'latest_value' => $latestReading?->scaled_value,
                    'quality' => $latestReading?->quality,
                    'trend_data' => $trendData,
                    'last_updated' => $latestReading?->read_at,
                ];
            });
        
        $endTime = microtime(true);
        $liveDataQueryTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(200, $liveDataQueryTime,
            "Live data query took {$liveDataQueryTime}ms, expected < 200ms");

        $this->assertGreaterThan(0, $liveData->count());
        
        // Verify data structure
        $firstItem = $liveData->first();
        $this->assertArrayHasKey('latest_value', $firstItem);
        $this->assertArrayHasKey('trend_data', $firstItem);
        $this->assertIsArray($firstItem['trend_data']);
    }
}