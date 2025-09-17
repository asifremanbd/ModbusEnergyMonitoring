<?php

namespace Tests\Unit;

use App\Services\TeltonikaTemplateService;
use App\Services\DataPointMappingService;
use PHPUnit\Framework\TestCase;

class DataPointConfigurationLogicTest extends TestCase
{
    /** @test */
    public function it_provides_correct_template_options()
    {
        $templateService = new TeltonikaTemplateService(new DataPointMappingService());
        
        $templates = $templateService->getAvailableTemplates();
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
        
        // Check that basic template exists
        $basicTemplate = collect($templates)->firstWhere('key', 'teltonika_basic');
        $this->assertNotNull($basicTemplate);
        $this->assertEquals('Teltonika Basic (4 Points)', $basicTemplate['name']);
        $this->assertEquals(4, $basicTemplate['point_count']);
    }

    /** @test */
    public function it_returns_correct_template_structure()
    {
        $templateService = new TeltonikaTemplateService(new DataPointMappingService());
        
        $template = $templateService->getTemplate('teltonika_basic');
        
        $this->assertNotNull($template);
        $this->assertArrayHasKey('name', $template);
        $this->assertArrayHasKey('description', $template);
        $this->assertArrayHasKey('data_points', $template);
        $this->assertCount(4, $template['data_points']);
        
        // Check first data point structure
        $firstPoint = $template['data_points'][0];
        $this->assertArrayHasKey('group_name', $firstPoint);
        $this->assertArrayHasKey('label', $firstPoint);
        $this->assertArrayHasKey('modbus_function', $firstPoint);
        $this->assertArrayHasKey('register_address', $firstPoint);
        $this->assertArrayHasKey('data_type', $firstPoint);
        $this->assertArrayHasKey('byte_order', $firstPoint);
        
        $this->assertEquals('Basic', $firstPoint['group_name']);
        $this->assertEquals(4, $firstPoint['modbus_function']);
        $this->assertEquals('float32', $firstPoint['data_type']);
        $this->assertEquals('word_swapped', $firstPoint['byte_order']);
    }

    /** @test */
    public function it_validates_data_point_configuration_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        // Valid configuration
        $validConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
            'modbus_function' => 4,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 1.0,
            'is_enabled' => true,
        ];
        
        $result = $mappingService->validateDataPointConfig($validConfig);
        
        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['group_name']);
        $this->assertEquals('Test Point', $result['label']);
        $this->assertEquals(1, $result['register_address']);
        $this->assertEquals(4, $result['modbus_function']);
        $this->assertEquals(2, $result['register_count']);
        $this->assertEquals('float32', $result['data_type']);
        $this->assertEquals('word_swapped', $result['byte_order']);
        $this->assertEquals(1.0, $result['scale_factor']);
        $this->assertTrue($result['is_enabled']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_register_address()
    {
        $mappingService = new DataPointMappingService();
        
        $invalidConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 70000, // Invalid: out of range
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Register address must be between 1 and 65535');
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function it_throws_exception_for_missing_required_fields()
    {
        $mappingService = new DataPointMappingService();
        
        $invalidConfig = [
            'label' => 'Test Point',
            // Missing group_name and register_address
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'group_name' is required");
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function it_applies_default_values_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        $minimalConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
        ];
        
        $result = $mappingService->validateDataPointConfig($minimalConfig);
        
        // Check that defaults were applied
        $this->assertEquals(4, $result['modbus_function']); // Default function
        $this->assertEquals(2, $result['register_count']); // Default for float32
        $this->assertEquals('float32', $result['data_type']); // Default data type
        $this->assertEquals('word_swapped', $result['byte_order']); // Default byte order
        $this->assertEquals(1.0, $result['scale_factor']); // Default scale
        $this->assertTrue($result['is_enabled']); // Default enabled
    }

    /** @test */
    public function it_validates_data_types_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        $validDataTypes = ['int16', 'uint16', 'int32', 'uint32', 'float32', 'float64'];
        
        foreach ($validDataTypes as $dataType) {
            $config = [
                'group_name' => 'Test',
                'label' => 'Test Point',
                'register_address' => 1,
                'data_type' => $dataType,
            ];
            
            $result = $mappingService->validateDataPointConfig($config);
            $this->assertEquals($dataType, $result['data_type']);
        }
        
        // Test invalid data type
        $invalidConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
            'data_type' => 'invalid_type',
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data type: invalid_type');
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function it_validates_byte_order_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        $validByteOrders = ['big_endian', 'little_endian', 'word_swapped'];
        
        foreach ($validByteOrders as $byteOrder) {
            $config = [
                'group_name' => 'Test',
                'label' => 'Test Point',
                'register_address' => 1,
                'byte_order' => $byteOrder,
            ];
            
            $result = $mappingService->validateDataPointConfig($config);
            $this->assertEquals($byteOrder, $result['byte_order']);
        }
        
        // Test invalid byte order
        $invalidConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
            'byte_order' => 'invalid_order',
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid byte order: invalid_order');
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function it_validates_modbus_function_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        // Valid functions
        foreach ([3, 4] as $function) {
            $config = [
                'group_name' => 'Test',
                'label' => 'Test Point',
                'register_address' => 1,
                'modbus_function' => $function,
            ];
            
            $result = $mappingService->validateDataPointConfig($config);
            $this->assertEquals($function, $result['modbus_function']);
        }
        
        // Invalid function
        $invalidConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
            'modbus_function' => 1, // Invalid
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Modbus function must be 3 (Holding) or 4 (Input)');
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function it_validates_scale_factor_correctly()
    {
        $mappingService = new DataPointMappingService();
        
        // Valid scale factors
        foreach ([0.1, 1.0, 10.0, 1000.0] as $scale) {
            $config = [
                'group_name' => 'Test',
                'label' => 'Test Point',
                'register_address' => 1,
                'scale_factor' => $scale,
            ];
            
            $result = $mappingService->validateDataPointConfig($config);
            $this->assertEquals($scale, $result['scale_factor']);
        }
        
        // Invalid scale factor (zero)
        $invalidConfig = [
            'group_name' => 'Test',
            'label' => 'Test Point',
            'register_address' => 1,
            'scale_factor' => 0,
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scale factor must be a non-zero number');
        
        $mappingService->validateDataPointConfig($invalidConfig);
    }

    /** @test */
    public function energy_meter_template_has_correct_scaling_factors()
    {
        $templateService = new TeltonikaTemplateService(new DataPointMappingService());
        
        $template = $templateService->getTemplate('teltonika_energy_meter');
        
        $this->assertNotNull($template);
        
        // Check specific power points that should have 1000.0 scaling
        $activePowerPoint = collect($template['data_points'])->firstWhere('label', 'Active Power Total');
        $this->assertNotNull($activePowerPoint);
        $this->assertEquals(1000.0, $activePowerPoint['scale_factor'], "Active Power should have 1000.0 scale factor");
        
        $reactivePowerPoint = collect($template['data_points'])->firstWhere('label', 'Reactive Power Total');
        $this->assertNotNull($reactivePowerPoint);
        $this->assertEquals(1000.0, $reactivePowerPoint['scale_factor'], "Reactive Power should have 1000.0 scale factor");
        
        // Check that Power Factor has 1.0 scaling (not 1000.0)
        $powerFactorPoint = collect($template['data_points'])->firstWhere('label', 'Power Factor Total');
        $this->assertNotNull($powerFactorPoint);
        $this->assertEquals(1.0, $powerFactorPoint['scale_factor'], "Power Factor should have 1.0 scale factor");
        
        // Check energy points
        $energyPoints = array_filter($template['data_points'], function($point) {
            return strpos($point['label'], 'Energy') !== false;
        });
        
        $this->assertNotEmpty($energyPoints);
        
        foreach ($energyPoints as $point) {
            $this->assertEquals(1000.0, $point['scale_factor'], "Energy point '{$point['label']}' should have 1000.0 scale factor");
        }
    }
}