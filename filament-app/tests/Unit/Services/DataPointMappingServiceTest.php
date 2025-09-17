<?php

namespace Tests\Unit\Services;

use App\Models\DataPoint;
use App\Models\Gateway;
use App\Services\DataPointMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DataPointMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DataPointMappingService $service;
    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataPointMappingService();
        $this->gateway = Gateway::factory()->create();
    }

    public function test_creates_data_point_with_valid_config()
    {
        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'modbus_function' => 4,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.5,
        ];

        $dataPoint = $this->service->createDataPoint($this->gateway, $config);

        $this->assertInstanceOf(DataPoint::class, $dataPoint);
        $this->assertEquals($this->gateway->id, $dataPoint->gateway_id);
        $this->assertEquals('Test Group', $dataPoint->group_name);
        $this->assertEquals('Test Point', $dataPoint->label);
        $this->assertEquals(100, $dataPoint->register_address);
        $this->assertEquals(4, $dataPoint->modbus_function);
        $this->assertEquals('float32', $dataPoint->data_type);
        $this->assertEquals('word_swapped', $dataPoint->byte_order);
        $this->assertEquals(1.5, $dataPoint->scale_factor);
        $this->assertTrue($dataPoint->is_enabled);
    }

    public function test_validates_required_fields()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'group_name' is required");

        $this->service->createDataPoint($this->gateway, [
            'label' => 'Test Point',
            'register_address' => 100,
        ]);
    }

    public function test_validates_register_address_range()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'register_address' is required");

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 0, // Invalid - treated as empty
        ];

        $this->service->createDataPoint($this->gateway, $config);
    }

    public function test_validates_register_address_lower_bound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register address must be between 1 and 65535');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => -1, // Invalid
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_validates_register_address_upper_bound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register address must be between 1 and 65535');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 65536, // Invalid
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_validates_modbus_function()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Modbus function must be 3 (Holding) or 4 (Input)');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'modbus_function' => 1, // Invalid
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_validates_data_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data type: invalid_type');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'data_type' => 'invalid_type',
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_validates_byte_order()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid byte order: invalid_order');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'byte_order' => 'invalid_order',
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_validates_scale_factor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scale factor must be a non-zero number');

        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'scale_factor' => 0, // Invalid
        ];

        $this->service->validateDataPointConfig($config);
    }

    public function test_auto_calculates_register_count_based_on_data_type()
    {
        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
            'data_type' => 'float64', // Should require 4 registers
        ];

        $validated = $this->service->validateDataPointConfig($config);
        $this->assertEquals(4, $validated['register_count']);
    }

    public function test_uses_default_values()
    {
        $config = [
            'group_name' => 'Test Group',
            'label' => 'Test Point',
            'register_address' => 100,
        ];

        $validated = $this->service->validateDataPointConfig($config);
        
        $this->assertEquals(4, $validated['modbus_function']); // Input registers
        $this->assertEquals(2, $validated['register_count']); // float32 default
        $this->assertEquals('float32', $validated['data_type']);
        $this->assertEquals('word_swapped', $validated['byte_order']);
        $this->assertEquals(1.0, $validated['scale_factor']);
        $this->assertTrue($validated['is_enabled']);
    }

    public function test_detects_register_conflicts()
    {
        // Create existing data point at registers 100-101
        DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'register_address' => 100,
            'register_count' => 2,
        ]);

        // Check for conflict with overlapping range 101-102
        $conflicts = $this->service->checkRegisterConflicts($this->gateway, 101, 2);
        
        $this->assertCount(1, $conflicts);
    }

    public function test_excludes_data_point_from_conflict_check()
    {
        // Create existing data point
        $existingPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'register_address' => 100,
            'register_count' => 2,
        ]);

        // Check for conflict excluding the existing point itself
        $conflicts = $this->service->checkRegisterConflicts(
            $this->gateway, 
            100, 
            2, 
            $existingPoint->id
        );
        
        $this->assertCount(0, $conflicts);
    }

    public function test_gets_register_range()
    {
        $dataPoint = DataPoint::factory()->create([
            'register_address' => 100,
            'register_count' => 3,
        ]);

        $range = $this->service->getRegisterRange($dataPoint);
        
        $this->assertEquals([
            'start' => 100,
            'end' => 102,
            'count' => 3,
        ], $range);
    }

    public function test_validates_all_supported_data_types()
    {
        $supportedTypes = ['int16', 'uint16', 'int32', 'uint32', 'float32', 'float64'];
        
        foreach ($supportedTypes as $dataType) {
            $config = [
                'group_name' => 'Test Group',
                'label' => 'Test Point',
                'register_address' => 100,
                'data_type' => $dataType,
            ];

            $validated = $this->service->validateDataPointConfig($config);
            $this->assertEquals($dataType, $validated['data_type']);
        }
    }

    public function test_validates_all_supported_byte_orders()
    {
        $supportedOrders = ['big_endian', 'little_endian', 'word_swapped'];
        
        foreach ($supportedOrders as $byteOrder) {
            $config = [
                'group_name' => 'Test Group',
                'label' => 'Test Point',
                'register_address' => 100,
                'byte_order' => $byteOrder,
            ];

            $validated = $this->service->validateDataPointConfig($config);
            $this->assertEquals($byteOrder, $validated['byte_order']);
        }
    }
}