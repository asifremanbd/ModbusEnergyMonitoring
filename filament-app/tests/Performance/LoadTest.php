<?php

namespace Tests\Performance;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use App\Jobs\PollGatewayJob;
use App\Services\ModbusPollService;
use App\Services\GatewayManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use sync queue for load testing
        Queue::fake();
        
        // Create test user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    /** @test */
    public function test_concurrent_gateway_creation_load()
    {
        $concurrentRequests = 20;
        $executionTimes = [];
        
        // Simulate concurrent gateway creation requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $startTime = microtime(true);
            
            $gatewayData = [
                'name' => "Load Test Gateway {$i}",
                'ip_address' => "192.168.1." . (100 + $i),
                'port' => 502,
                'unit_id' => 1,
                'poll_interval' => 10,
                'is_active' => true,
            ];
            
            $response = $this->post('/admin/gateways', $gatewayData);
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;
            
            // Verify gateway was created
            $this->assertDatabaseHas('gateways', [
                'name' => "Load Test Gateway {$i}",
                'ip_address' => "192.168.1." . (100 + $i),
            ]);
        }
        
        $averageTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        
        // Assert performance under load
        $this->assertLessThan(500, $averageTime,
            "Average gateway creation time under load was {$averageTime}ms, expected < 500ms");
        
        $this->assertLessThan(2000, $maxTime,
            "Maximum gateway creation time under load was {$maxTime}ms, expected < 2000ms");
        
        // Verify all gateways were created
        $this->assertEquals($concurrentRequests, Gateway::count());
    }

    /** @test */
    public function test_concurrent_polling_operations_load()
    {
        // Create 50 gateways with data points
        $gateways = Gateway::factory()->count(50)->create([
            'is_active' => true,
            'poll_interval' => 10,
        ]);
        
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(15)->create([
                'gateway_id' => $gateway->id,
                'is_enabled' => true,
            ]);
        }
        
        // Mock Modbus service for all gateways
        $this->mock(ModbusPollService::class, function ($mock) use ($gateways) {
            foreach ($gateways as $gateway) {
                $dataPoints = $gateway->dataPoints;
                
                foreach ($dataPoints as $dataPoint) {
                    $mock->shouldReceive('readRegister')
                        ->with($gateway, $dataPoint)
                        ->andReturn(new \App\Services\ReadingResult(
                            success: rand(0, 9) < 8, // 80% success rate
                            rawValue: '[12345, 67890]',
                            scaledValue: rand(100, 999) / 10,
                            quality: rand(0, 9) < 8 ? 'good' : 'bad',
                            error: null
                        ));
                }
            }
        });
        
        $startTime = microtime(true);
        $initialMemory = memory_get_usage(true);
        
        // Execute polling for all gateways concurrently
        foreach ($gateways as $gateway) {
            $job = new PollGatewayJob($gateway);
            $job->handle();
        }
        
        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        
        $totalTime = ($endTime - $startTime) * 1000;
        $memoryUsed = ($finalMemory - $initialMemory) / 1024 / 1024; // MB
        
        // Assert performance metrics
        $this->assertLessThan(10000, $totalTime,
            "Concurrent polling of 50 gateways took {$totalTime}ms, expected < 10000ms");
        
        $this->assertLessThan(100, $memoryUsed,
            "Memory usage for concurrent polling was {$memoryUsed}MB, expected < 100MB");
        
        // Verify readings were created
        $expectedReadings = 50 * 15; // 50 gateways × 15 data points
        $actualReadings = Reading::count();
        
        $this->assertGreaterThan($expectedReadings * 0.7, $actualReadings,
            "Expected at least 70% successful readings, got {$actualReadings} out of {$expectedReadings}");
    }

    /** @test */
    public function test_concurrent_dashboard_access_load()
    {
        // Create test data for dashboard
        $gateways = Gateway::factory()->count(20)->create([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(rand(1, 30)),
            'success_count' => rand(100, 1000),
            'failure_count' => rand(5, 50),
        ]);
        
        foreach ($gateways as $gateway) {
            $dataPoints = DataPoint::factory()->count(10)->create([
                'gateway_id' => $gateway->id,
            ]);
            
            foreach ($dataPoints as $dataPoint) {
                Reading::factory()->count(50)->create([
                    'data_point_id' => $dataPoint->id,
                    'read_at' => now()->subMinutes(rand(1, 60)),
                ]);
            }
        }
        
        $concurrentUsers = 25;
        $executionTimes = [];
        
        // Simulate concurrent dashboard access
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $startTime = microtime(true);
            
            $response = $this->get('/admin');
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(200);
        }
        
        $averageTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        
        // Assert dashboard performance under load
        $this->assertLessThan(1000, $averageTime,
            "Average dashboard load time under concurrent access was {$averageTime}ms, expected < 1000ms");
        
        $this->assertLessThan(3000, $maxTime,
            "Maximum dashboard load time under concurrent access was {$maxTime}ms, expected < 3000ms");
    }

    /** @test */
    public function test_concurrent_live_data_access_load()
    {
        // Create test data for live data view
        $gateways = Gateway::factory()->count(15)->create();
        
        foreach ($gateways as $gateway) {
            $dataPoints = DataPoint::factory()->count(20)->create([
                'gateway_id' => $gateway->id,
            ]);
            
            foreach ($dataPoints as $dataPoint) {
                Reading::factory()->count(100)->create([
                    'data_point_id' => $dataPoint->id,
                    'read_at' => now()->subMinutes(rand(1, 120)),
                ]);
            }
        }
        
        $concurrentRequests = 30;
        $executionTimes = [];
        
        // Simulate concurrent live data access
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $startTime = microtime(true);
            
            $response = $this->get('/admin/live-data');
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(200);
        }
        
        $averageTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        
        // Assert live data performance under load
        $this->assertLessThan(800, $averageTime,
            "Average live data load time under concurrent access was {$averageTime}ms, expected < 800ms");
        
        $this->assertLessThan(2500, $maxTime,
            "Maximum live data load time under concurrent access was {$maxTime}ms, expected < 2500ms");
    }

    /** @test */
    public function test_concurrent_gateway_management_operations_load()
    {
        // Create gateways for management operations
        $gateways = Gateway::factory()->count(30)->create([
            'is_active' => true,
        ]);
        
        $operations = [];
        $executionTimes = [];
        
        // Define concurrent operations
        foreach ($gateways as $index => $gateway) {
            $operation = match ($index % 4) {
                0 => 'pause',
                1 => 'resume', 
                2 => 'test-connection',
                3 => 'view',
            };
            
            $operations[] = ['gateway' => $gateway, 'operation' => $operation];
        }
        
        // Mock services for operations that need them
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateways) {
            foreach ($gateways as $gateway) {
                $mock->shouldReceive('pausePolling')
                    ->with($gateway)
                    ->andReturnNull();
                    
                $mock->shouldReceive('resumePolling')
                    ->with($gateway)
                    ->andReturnNull();
            }
        });
        
        $this->mock(ModbusPollService::class, function ($mock) use ($gateways) {
            foreach ($gateways as $gateway) {
                $mock->shouldReceive('testConnection')
                    ->with($gateway->ip_address, $gateway->port, $gateway->unit_id)
                    ->andReturn(new \App\Services\ConnectionTest(
                        success: rand(0, 9) < 8,
                        latency: rand(10, 100),
                        testValue: rand(10000, 99999),
                        error: null
                    ));
            }
        });
        
        $startTime = microtime(true);
        
        // Execute concurrent operations
        foreach ($operations as $op) {
            $opStartTime = microtime(true);
            
            $response = match ($op['operation']) {
                'pause' => $this->post("/admin/gateways/{$op['gateway']->id}/pause"),
                'resume' => $this->post("/admin/gateways/{$op['gateway']->id}/resume"),
                'test-connection' => $this->post("/admin/gateways/{$op['gateway']->id}/test-connection"),
                'view' => $this->get("/admin/gateways/{$op['gateway']->id}"),
            };
            
            $opEndTime = microtime(true);
            $executionTimes[] = ($opEndTime - $opStartTime) * 1000;
            
            // Most operations should redirect or return 200
            $this->assertContains($response->getStatusCode(), [200, 302]);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = array_sum($executionTimes) / count($executionTimes);
        
        // Assert performance metrics
        $this->assertLessThan(15000, $totalTime,
            "Total time for concurrent gateway operations was {$totalTime}ms, expected < 15000ms");
        
        $this->assertLessThan(600, $averageTime,
            "Average time per gateway operation was {$averageTime}ms, expected < 600ms");
    }

    /** @test */
    public function test_database_connection_pool_under_load()
    {
        // Create data for database stress testing
        $gateways = Gateway::factory()->count(20)->create();
        
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(10)->create([
                'gateway_id' => $gateway->id,
            ]);
        }
        
        $concurrentQueries = 100;
        $executionTimes = [];
        $errors = 0;
        
        // Execute concurrent database operations
        for ($i = 0; $i < $concurrentQueries; $i++) {
            $startTime = microtime(true);
            
            try {
                // Mix of different query types
                switch ($i % 4) {
                    case 0:
                        // Read operation
                        Gateway::with('dataPoints')->get();
                        break;
                    case 1:
                        // Write operation
                        Reading::create([
                            'data_point_id' => DataPoint::inRandomOrder()->first()->id,
                            'raw_value' => '[12345, 67890]',
                            'scaled_value' => rand(100, 999) / 10,
                            'quality' => 'good',
                            'read_at' => now(),
                        ]);
                        break;
                    case 2:
                        // Aggregate operation
                        Reading::where('read_at', '>=', now()->subHour())
                            ->selectRaw('COUNT(*) as total, AVG(scaled_value) as avg_value')
                            ->first();
                        break;
                    case 3:
                        // Update operation
                        $gateway = Gateway::inRandomOrder()->first();
                        $gateway->update(['last_seen_at' => now()]);
                        break;
                }
                
                $endTime = microtime(true);
                $executionTimes[] = ($endTime - $startTime) * 1000;
                
            } catch (\Exception $e) {
                $errors++;
                $executionTimes[] = 0; // Don't count failed operations in timing
            }
        }
        
        $successfulQueries = $concurrentQueries - $errors;
        $averageTime = array_sum($executionTimes) / max(1, $successfulQueries);
        $errorRate = ($errors / $concurrentQueries) * 100;
        
        // Assert database performance under load
        $this->assertLessThan(5, $errorRate,
            "Database error rate under load was {$errorRate}%, expected < 5%");
        
        $this->assertLessThan(200, $averageTime,
            "Average database query time under load was {$averageTime}ms, expected < 200ms");
        
        $this->assertGreaterThan(80, $successfulQueries,
            "Expected at least 80 successful queries out of {$concurrentQueries}");
    }

    /** @test */
    public function test_memory_usage_under_sustained_load()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = $initialMemory;
        
        // Create base data
        $gateways = Gateway::factory()->count(10)->create();
        
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(15)->create([
                'gateway_id' => $gateway->id,
            ]);
        }
        
        // Simulate sustained load over multiple cycles
        for ($cycle = 0; $cycle < 20; $cycle++) {
            // Simulate polling cycle
            foreach ($gateways as $gateway) {
                $dataPoints = $gateway->dataPoints;
                
                foreach ($dataPoints as $dataPoint) {
                    Reading::create([
                        'data_point_id' => $dataPoint->id,
                        'raw_value' => '[' . rand(10000, 99999) . ', ' . rand(10000, 99999) . ']',
                        'scaled_value' => rand(100, 999) / 10,
                        'quality' => 'good',
                        'read_at' => now(),
                    ]);
                }
            }
            
            // Simulate dashboard access
            $response = $this->get('/admin');
            $response->assertStatus(200);
            
            // Check memory usage
            $currentMemory = memory_get_usage(true);
            $peakMemory = max($peakMemory, $currentMemory);
            
            // Clean up old readings to simulate data retention
            if ($cycle % 5 === 0) {
                Reading::where('read_at', '<', now()->subMinutes(30))->delete();
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB
        $peakIncrease = ($peakMemory - $initialMemory) / 1024 / 1024; // MB
        
        // Assert memory usage is reasonable
        $this->assertLessThan(50, $memoryIncrease,
            "Final memory increase was {$memoryIncrease}MB, expected < 50MB");
        
        $this->assertLessThan(100, $peakIncrease,
            "Peak memory increase was {$peakIncrease}MB, expected < 100MB");
    }

    /** @test */
    public function test_cache_performance_under_load()
    {
        // Create data for cache testing
        $gateways = Gateway::factory()->count(15)->create();
        
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(10)->create([
                'gateway_id' => $gateway->id,
            ]);
        }
        
        $cacheOperations = 200;
        $executionTimes = [];
        
        // Test cache operations under load
        for ($i = 0; $i < $cacheOperations; $i++) {
            $startTime = microtime(true);
            
            $cacheKey = "test_key_{$i}";
            $cacheValue = [
                'gateway_id' => $gateways->random()->id,
                'timestamp' => now()->timestamp,
                'data' => array_fill(0, 100, rand(1, 1000)),
            ];
            
            // Cache operations
            Cache::put($cacheKey, $cacheValue, 300); // 5 minutes
            $retrieved = Cache::get($cacheKey);
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;
            
            // Verify cache operation worked
            $this->assertEquals($cacheValue, $retrieved);
        }
        
        $averageTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);
        
        // Assert cache performance
        $this->assertLessThan(10, $averageTime,
            "Average cache operation time was {$averageTime}ms, expected < 10ms");
        
        $this->assertLessThan(50, $maxTime,
            "Maximum cache operation time was {$maxTime}ms, expected < 50ms");
    }
}