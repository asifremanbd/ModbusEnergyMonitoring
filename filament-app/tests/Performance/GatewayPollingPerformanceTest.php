<?php

namespace Tests\Performance;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Jobs\PollGatewayJob;
use App\Services\ModbusPollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Carbon\Carbon;

class GatewayPollingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use sync queue for performance testing
        Queue::fake();
    }

    /** @test */
    public function test_single_gateway_polling_performance()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        // Create 20 data points (typical for energy meter)
        DataPoint::factory()->count(20)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        $startTime = microtime(true);
        
        // Simulate polling job execution
        $job = new PollGatewayJob($gateway);
        
        // Mock the Modbus service to avoid actual network calls
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway) {
            $dataPoints = $gateway->dataPoints;
            
            foreach ($dataPoints as $dataPoint) {
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: true,
                        rawValue: '[12345, 67890]',
                        scaledValue: 123.45,
                        quality: 'good',
                        error: null
                    ));
            }
        });

        $job->handle();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert polling completes within acceptable time (should be < 1000ms for 20 points)
        $this->assertLessThan(1000, $executionTime, 
            "Single gateway polling took {$executionTime}ms, expected < 1000ms");

        // Verify readings were created
        $this->assertEquals(20, Reading::count());
    }

    /** @test */
    public function test_multiple_gateway_concurrent_polling_performance()
    {
        // Create 10 gateways with 10 data points each
        $gateways = Gateway::factory()->count(10)->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(10)->create([
                'gateway_id' => $gateway->id,
                'is_enabled' => true,
            ]);
        }

        $startTime = microtime(true);

        // Mock the Modbus service for all gateways
        $this->mock(ModbusPollService::class, function ($mock) use ($gateways) {
            foreach ($gateways as $gateway) {
                $dataPoints = $gateway->dataPoints;
                
                foreach ($dataPoints as $dataPoint) {
                    $mock->shouldReceive('readRegister')
                        ->with($gateway, $dataPoint)
                        ->andReturn(new \App\Services\ReadingResult(
                            success: true,
                            rawValue: '[12345, 67890]',
                            scaledValue: rand(100, 999) / 10,
                            quality: 'good',
                            error: null
                        ));
                }
            }
        });

        // Execute polling jobs for all gateways
        foreach ($gateways as $gateway) {
            $job = new PollGatewayJob($gateway);
            $job->handle();
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Assert all gateways polled within acceptable time (should be < 5000ms for 10 gateways)
        $this->assertLessThan(5000, $executionTime,
            "Multiple gateway polling took {$executionTime}ms, expected < 5000ms");

        // Verify all readings were created (10 gateways × 10 points = 100 readings)
        $this->assertEquals(100, Reading::count());
    }

    /** @test */
    public function test_high_frequency_polling_performance()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 1, // High frequency polling
            'is_active' => true,
        ]);

        DataPoint::factory()->count(5)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Mock fast responses
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway) {
            $dataPoints = $gateway->dataPoints;
            
            foreach ($dataPoints as $dataPoint) {
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: true,
                        rawValue: '[12345, 67890]',
                        scaledValue: 123.45,
                        quality: 'good',
                        error: null
                    ));
            }
        });

        $executionTimes = [];

        // Simulate 10 rapid polling cycles
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            
            $job = new PollGatewayJob($gateway);
            $job->handle();
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;
        }

        $averageTime = array_sum($executionTimes) / count($executionTimes);
        $maxTime = max($executionTimes);

        // Assert average polling time is acceptable for high frequency
        $this->assertLessThan(200, $averageTime,
            "Average high-frequency polling time was {$averageTime}ms, expected < 200ms");

        $this->assertLessThan(500, $maxTime,
            "Maximum high-frequency polling time was {$maxTime}ms, expected < 500ms");
    }

    /** @test */
    public function test_polling_with_connection_failures_performance()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        DataPoint::factory()->count(10)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Mock connection failures and retries
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway) {
            $dataPoints = $gateway->dataPoints;
            
            foreach ($dataPoints as $dataPoint) {
                // Simulate 50% failure rate
                $success = rand(0, 1) === 1;
                
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: $success,
                        rawValue: $success ? '[12345, 67890]' : null,
                        scaledValue: $success ? 123.45 : null,
                        quality: $success ? 'good' : 'bad',
                        error: $success ? null : 'Connection timeout'
                    ));
            }
        });

        $startTime = microtime(true);
        
        $job = new PollGatewayJob($gateway);
        $job->handle();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Even with failures, polling should complete within reasonable time
        $this->assertLessThan(2000, $executionTime,
            "Polling with failures took {$executionTime}ms, expected < 2000ms");

        // Verify some readings were created (at least some should succeed)
        $this->assertGreaterThan(0, Reading::count());
    }

    /** @test */
    public function test_memory_usage_during_large_scale_polling()
    {
        // Create 50 gateways with 20 data points each (1000 total points)
        $gateways = Gateway::factory()->count(50)->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(20)->create([
                'gateway_id' => $gateway->id,
                'is_enabled' => true,
            ]);
        }

        $initialMemory = memory_get_usage(true);

        // Mock the service to avoid actual network calls
        $this->mock(ModbusPollService::class, function ($mock) use ($gateways) {
            foreach ($gateways as $gateway) {
                $dataPoints = $gateway->dataPoints;
                
                foreach ($dataPoints as $dataPoint) {
                    $mock->shouldReceive('readRegister')
                        ->with($gateway, $dataPoint)
                        ->andReturn(new \App\Services\ReadingResult(
                            success: true,
                            rawValue: '[12345, 67890]',
                            scaledValue: rand(100, 999) / 10,
                            quality: 'good',
                            error: null
                        ));
                }
            }
        });

        // Process first 10 gateways
        foreach ($gateways->take(10) as $gateway) {
            $job = new PollGatewayJob($gateway);
            $job->handle();
        }

        $midMemory = memory_get_usage(true);
        $memoryIncrease = $midMemory - $initialMemory;

        // Memory increase should be reasonable (< 50MB for 200 data points)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease,
            "Memory usage increased by " . ($memoryIncrease / 1024 / 1024) . "MB, expected < 50MB");

        // Verify readings were created
        $this->assertEquals(200, Reading::count()); // 10 gateways × 20 points
    }

    /** @test */
    public function test_database_write_performance_during_polling()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        // Create 100 data points to test database write performance
        DataPoint::factory()->count(100)->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Mock successful readings
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway) {
            $dataPoints = $gateway->dataPoints;
            
            foreach ($dataPoints as $dataPoint) {
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: true,
                        rawValue: '[12345, 67890]',
                        scaledValue: rand(100, 999) / 10,
                        quality: 'good',
                        error: null
                    ));
            }
        });

        $startTime = microtime(true);
        
        $job = new PollGatewayJob($gateway);
        $job->handle();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Database writes should complete efficiently (< 3000ms for 100 inserts)
        $this->assertLessThan(3000, $executionTime,
            "Database write performance was {$executionTime}ms, expected < 3000ms");

        // Verify all readings were written
        $this->assertEquals(100, Reading::count());
    }

    /** @test */
    public function test_polling_performance_with_different_data_types()
    {
        $gateway = Gateway::factory()->create([
            'poll_interval' => 10,
            'is_active' => true,
        ]);

        $dataTypes = ['int16', 'uint16', 'int32', 'uint32', 'float32', 'float64'];
        
        // Create data points with different data types
        foreach ($dataTypes as $dataType) {
            DataPoint::factory()->count(5)->create([
                'gateway_id' => $gateway->id,
                'data_type' => $dataType,
                'is_enabled' => true,
            ]);
        }

        // Mock responses for different data types
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway) {
            $dataPoints = $gateway->dataPoints;
            
            foreach ($dataPoints as $dataPoint) {
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: true,
                        rawValue: '[12345, 67890]',
                        scaledValue: $this->getTestValueForDataType($dataPoint->data_type),
                        quality: 'good',
                        error: null
                    ));
            }
        });

        $startTime = microtime(true);
        
        $job = new PollGatewayJob($gateway);
        $job->handle();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Data type conversion should not significantly impact performance
        $this->assertLessThan(1000, $executionTime,
            "Mixed data type polling took {$executionTime}ms, expected < 1000ms");

        // Verify readings for all data types
        $this->assertEquals(30, Reading::count()); // 6 types × 5 points
    }

    private function getTestValueForDataType(string $dataType): float
    {
        return match ($dataType) {
            'int16' => rand(-32768, 32767),
            'uint16' => rand(0, 65535),
            'int32' => rand(-2147483648, 2147483647),
            'uint32' => rand(0, 4294967295),
            'float32', 'float64' => rand(100, 999) / 10,
            default => 123.45,
        };
    }
}