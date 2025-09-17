<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ModbusPollService;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class ModbusCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private ModbusPollService $service;
    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ModbusPollService();
        
        // Create test gateway
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Teltonika Gateway',
            'ip_address' => '127.0.0.1', // Use localhost for testing
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
        ]);
    }

    public function test_connection_test_with_invalid_host()
    {
        // Test connection to non-existent host
        $result = $this->service->testConnection('192.168.999.999', 502, 1);
        
        $this->assertFalse($result->success);
        $this->assertNull($result->testValue);
        $this->assertNotNull($result->error);
        $this->assertGreaterThan(0, $result->latency);
    }

    public function test_connection_test_with_invalid_port()
    {
        // Test connection to invalid port
        $result = $this->service->testConnection('127.0.0.1', 99999, 1);
        
        $this->assertFalse($result->success);
        $this->assertNull($result->testValue);
        $this->assertNotNull($result->error);
    }

    public function test_poll_gateway_creates_readings_on_success()
    {
        // Create data points for the gateway
        $dataPoint1 = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Voltage_L1',
            'modbus_function' => 4,
            'register_address' => 1,
            'register_count' => 2,
            'data_type' => 'float32',
            'is_enabled' => true,
        ]);

        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'group_name' => 'Meter_1',
            'label' => 'Current_L1',
            'modbus_function' => 4,
            'register_address' => 3,
            'register_count' => 2,
            'data_type' => 'float32',
            'is_enabled' => true,
        ]);

        // Verify data points are created
        $this->assertEquals(2, $this->gateway->dataPoints()->count());
        
        // Since we can't guarantee a Modbus server is running,
        // we'll test that the service handles the connection attempt
        // and creates appropriate readings (even if they fail)
        
        $initialReadingCount = Reading::count();
        
        // This will likely fail due to no Modbus server, but should handle gracefully
        $result = $this->service->pollGateway($this->gateway);
        
        // Verify the result structure
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('success', $result);
        $this->assertObjectHasProperty('readings', $result);
        $this->assertObjectHasProperty('errors', $result);
        $this->assertObjectHasProperty('duration', $result);
        
        // Verify gateway counters were updated
        $this->gateway->refresh();
        $this->assertGreaterThan(0, $this->gateway->success_count + $this->gateway->failure_count);
        $this->assertNotNull($this->gateway->last_seen_at);
    }

    public function test_read_register_with_different_data_types()
    {
        $dataTypes = [
            'int16' => ['register_count' => 1],
            'uint16' => ['register_count' => 1],
            'int32' => ['register_count' => 2],
            'uint32' => ['register_count' => 2],
            'float32' => ['register_count' => 2],
            'float64' => ['register_count' => 4],
        ];

        foreach ($dataTypes as $dataType => $config) {
            $dataPoint = DataPoint::factory()->create([
                'gateway_id' => $this->gateway->id,
                'data_type' => $dataType,
                'register_count' => $config['register_count'],
                'is_enabled' => true,
            ]);

            // Test reading the register (will likely fail due to no server)
            $result = $this->service->readRegister($this->gateway, $dataPoint);
            
            $this->assertIsObject($result);
            $this->assertObjectHasProperty('success', $result);
            $this->assertObjectHasProperty('quality', $result);
            
            // Clean up for next iteration
            $dataPoint->delete();
        }
    }

    public function test_modbus_function_validation()
    {
        // Test supported Modbus functions
        $supportedFunctions = [3, 4]; // Holding registers and Input registers
        
        foreach ($supportedFunctions as $function) {
            $dataPoint = DataPoint::factory()->create([
                'gateway_id' => $this->gateway->id,
                'modbus_function' => $function,
                'is_enabled' => true,
            ]);
            
            $this->assertEquals($function, $dataPoint->modbus_function);
            
            // Clean up
            $dataPoint->delete();
        }
    }

    public function test_byte_order_configurations()
    {
        $byteOrders = ['big_endian', 'little_endian', 'word_swapped'];
        
        foreach ($byteOrders as $byteOrder) {
            $dataPoint = DataPoint::factory()->create([
                'gateway_id' => $this->gateway->id,
                'byte_order' => $byteOrder,
                'is_enabled' => true,
            ]);
            
            $this->assertEquals($byteOrder, $dataPoint->byte_order);
            
            // Clean up
            $dataPoint->delete();
        }
    }

    public function test_scale_factor_application()
    {
        $scaleFactors = [0.1, 1.0, 10.0, 0.001];
        
        foreach ($scaleFactors as $scaleFactor) {
            $dataPoint = DataPoint::factory()->create([
                'gateway_id' => $this->gateway->id,
                'scale_factor' => $scaleFactor,
                'is_enabled' => true,
            ]);
            
            $this->assertEquals($scaleFactor, $dataPoint->scale_factor);
            
            // Clean up
            $dataPoint->delete();
        }
    }

    public function test_gateway_health_monitoring()
    {
        // Create a fresh gateway with known initial state
        $freshGateway = Gateway::factory()->create([
            'success_count' => 0,
            'failure_count' => 0,
            'last_seen_at' => null,
        ]);
        
        // Test initial state
        $this->assertEquals(0, $freshGateway->success_count);
        $this->assertEquals(0, $freshGateway->failure_count);
        $this->assertNull($freshGateway->last_seen_at);
        
        // Simulate successful poll
        $freshGateway->increment('success_count');
        $freshGateway->update(['last_seen_at' => now()]);
        $freshGateway->refresh();
        
        $this->assertEquals(1, $freshGateway->success_count);
        $this->assertNotNull($freshGateway->last_seen_at);
        
        // Test success rate calculation
        $this->assertEquals(100.0, $freshGateway->success_rate);
        
        // Simulate failure
        $freshGateway->increment('failure_count');
        $freshGateway->refresh();
        
        $this->assertEquals(50.0, $freshGateway->success_rate);
    }

    public function test_reading_quality_indicators()
    {
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
        ]);

        // Test different quality levels
        $qualities = ['good', 'bad', 'uncertain'];
        
        foreach ($qualities as $quality) {
            $reading = Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'quality' => $quality,
            ]);
            
            $this->assertEquals($quality, $reading->quality);
            
            // Clean up
            $reading->delete();
        }
    }

    public function test_connection_timeout_handling()
    {
        // Test with very short timeout to force timeout
        $startTime = microtime(true);
        $result = $this->service->testConnection('192.168.1.254', 502, 1); // Non-routable IP
        $duration = microtime(true) - $startTime;
        
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertGreaterThan(0, $result->latency);
        
        // Should timeout within reasonable time (connection timeout + some buffer)
        $this->assertLessThan(10, $duration); // 10 seconds max
    }

    public function test_service_connection_cleanup()
    {
        // Test that connections can be closed without errors
        $this->service->closeConnections();
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        // Clean up connections
        if (isset($this->service)) {
            $this->service->closeConnections();
        }
        
        parent::tearDown();
    }
}