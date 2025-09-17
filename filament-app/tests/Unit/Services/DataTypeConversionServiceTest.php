<?php

namespace Tests\Unit\Services;

use App\Services\DataTypeConversionService;
use InvalidArgumentException;
use Tests\TestCase;

class DataTypeConversionServiceTest extends TestCase
{
    private DataTypeConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataTypeConversionService();
    }

    public function test_converts_int16_positive_value()
    {
        $result = $this->service->convertRawData([1234], 'int16');
        $this->assertEquals(1234, $result);
    }

    public function test_converts_int16_negative_value()
    {
        // 65535 in unsigned 16-bit = -1 in signed 16-bit
        $result = $this->service->convertRawData([65535], 'int16');
        $this->assertEquals(-1, $result);
    }

    public function test_converts_uint16_value()
    {
        $result = $this->service->convertRawData([65535], 'uint16');
        $this->assertEquals(65535, $result);
    }

    public function test_converts_int32_big_endian()
    {
        // 0x12345678 = 305419896
        $result = $this->service->convertRawData([0x1234, 0x5678], 'int32', 'big_endian');
        $this->assertEquals(305419896, $result);
    }

    public function test_converts_int32_little_endian()
    {
        // Little endian: low word first
        $result = $this->service->convertRawData([0x5678, 0x1234], 'int32', 'little_endian');
        $this->assertEquals(305419896, $result);
    }

    public function test_converts_int32_word_swapped()
    {
        // Word swapped is same as little endian for 32-bit
        $result = $this->service->convertRawData([0x5678, 0x1234], 'int32', 'word_swapped');
        $this->assertEquals(305419896, $result);
    }

    public function test_converts_uint32_value()
    {
        $result = $this->service->convertRawData([0xFFFF, 0xFFFF], 'uint32', 'big_endian');
        $this->assertEquals(4294967295, $result);
    }

    public function test_converts_float32_value()
    {
        // Test round-trip conversion to ensure consistency
        $originalValue = 123.456;
        
        $registers = $this->service->convertToRawRegisters($originalValue, 'float32', 'word_swapped');
        $result = $this->service->convertRawData($registers, 'float32', 'word_swapped');
        
        $this->assertEqualsWithDelta($originalValue, $result, 0.001);
    }

    public function test_converts_float64_value()
    {
        // Test with registers representing a double value
        $registers = [0x1234, 0x5678, 0x9ABC, 0xDEF0];
        $result = $this->service->convertRawData($registers, 'float64', 'big_endian');
        $this->assertIsFloat($result);
    }

    public function test_throws_exception_for_empty_registers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Registers array cannot be empty');
        
        $this->service->convertRawData([], 'int16');
    }

    public function test_throws_exception_for_unsupported_data_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported data type: invalid_type');
        
        $this->service->convertRawData([1234], 'invalid_type');
    }

    public function test_throws_exception_for_insufficient_registers_int32()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Int32 requires at least 2 registers');
        
        $this->service->convertRawData([1234], 'int32');
    }

    public function test_throws_exception_for_insufficient_registers_float64()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Float64 requires at least 4 registers');
        
        $this->service->convertRawData([1234, 5678], 'float64');
    }

    public function test_throws_exception_for_unsupported_byte_order()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported byte order: invalid_order');
        
        $this->service->convertRawData([1234, 5678], 'int32', 'invalid_order');
    }

    public function test_applies_scaling_factor()
    {
        $result = $this->service->applyScaling(100, 2.5);
        $this->assertEquals(250.0, $result);
    }

    public function test_applies_negative_scaling_factor()
    {
        $result = $this->service->applyScaling(100, -0.5);
        $this->assertEquals(-50.0, $result);
    }

    public function test_gets_required_register_count()
    {
        $this->assertEquals(1, $this->service->getRequiredRegisterCount('int16'));
        $this->assertEquals(1, $this->service->getRequiredRegisterCount('uint16'));
        $this->assertEquals(2, $this->service->getRequiredRegisterCount('int32'));
        $this->assertEquals(2, $this->service->getRequiredRegisterCount('uint32'));
        $this->assertEquals(2, $this->service->getRequiredRegisterCount('float32'));
        $this->assertEquals(4, $this->service->getRequiredRegisterCount('float64'));
    }

    public function test_throws_exception_for_unsupported_register_count_data_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported data type: invalid_type');
        
        $this->service->getRequiredRegisterCount('invalid_type');
    }

    public function test_validates_register_count_sufficient()
    {
        $this->assertTrue($this->service->validateRegisterCount([1, 2], 'int32'));
        $this->assertTrue($this->service->validateRegisterCount([1, 2, 3, 4], 'float64'));
    }

    public function test_validates_register_count_insufficient()
    {
        $this->assertFalse($this->service->validateRegisterCount([1], 'int32'));
        $this->assertFalse($this->service->validateRegisterCount([1, 2], 'float64'));
    }

    public function test_converts_value_to_raw_registers_and_back()
    {
        // Test round-trip conversion for various data types
        $testCases = [
            ['value' => 1234, 'type' => 'int16'],
            ['value' => 65535, 'type' => 'uint16'],
            ['value' => 305419896, 'type' => 'int32', 'byte_order' => 'big_endian'],
            ['value' => 4294967295, 'type' => 'uint32', 'byte_order' => 'big_endian'],
        ];

        foreach ($testCases as $case) {
            $byteOrder = $case['byte_order'] ?? 'word_swapped';
            
            $registers = $this->service->convertToRawRegisters(
                $case['value'], 
                $case['type'], 
                $byteOrder
            );
            
            $converted = $this->service->convertRawData(
                $registers, 
                $case['type'], 
                $byteOrder
            );
            
            $this->assertEquals($case['value'], $converted, 
                "Round-trip failed for {$case['type']} with value {$case['value']}");
        }
    }

    public function test_converts_float32_round_trip()
    {
        $originalValue = 3.14159;
        
        $registers = $this->service->convertToRawRegisters(
            $originalValue, 
            'float32', 
            'word_swapped'
        );
        
        $converted = $this->service->convertRawData(
            $registers, 
            'float32', 
            'word_swapped'
        );
        
        $this->assertEqualsWithDelta($originalValue, $converted, 0.0001);
    }

    public function test_handles_negative_int32_values()
    {
        $negativeValue = -123456;
        
        $registers = $this->service->convertToRawRegisters(
            $negativeValue, 
            'int32', 
            'big_endian'
        );
        
        $converted = $this->service->convertRawData(
            $registers, 
            'int32', 
            'big_endian'
        );
        
        $this->assertEquals($negativeValue, $converted);
    }

    public function test_all_byte_orders_for_int32()
    {
        $value = 0x12345678;
        $byteOrders = ['big_endian', 'little_endian', 'word_swapped'];
        
        foreach ($byteOrders as $byteOrder) {
            $registers = $this->service->convertToRawRegisters($value, 'int32', $byteOrder);
            $converted = $this->service->convertRawData($registers, 'int32', $byteOrder);
            
            $this->assertEquals($value, $converted, 
                "Failed for byte order: {$byteOrder}");
        }
    }

    public function test_register_conversion_produces_correct_count()
    {
        $testCases = [
            ['type' => 'int16', 'expected_count' => 1],
            ['type' => 'uint16', 'expected_count' => 1],
            ['type' => 'int32', 'expected_count' => 2],
            ['type' => 'uint32', 'expected_count' => 2],
            ['type' => 'float32', 'expected_count' => 2],
            ['type' => 'float64', 'expected_count' => 4],
        ];

        foreach ($testCases as $case) {
            $registers = $this->service->convertToRawRegisters(123, $case['type']);
            $this->assertCount($case['expected_count'], $registers, 
                "Wrong register count for {$case['type']}");
        }
    }
}