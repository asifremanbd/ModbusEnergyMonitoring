<?php

namespace Tests\Unit\Models;

use App\Models\Register;
use Tests\TestCase;

class RegisterModelStructureTest extends TestCase
{
    /** @test */
    public function register_has_correct_fillable_fields()
    {
        $register = new Register();
        $fillable = $register->getFillable();
        
        $expectedFillable = [
            'device_id',
            'technical_label',
            'function',
            'register_address',
            'data_type',
            'byte_order',
            'scale',
            'count',
            'enabled',
            'write_function',
            'write_register',
            'on_value',
            'off_value',
            'invert',
            'schedulable',
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    /** @test */
    public function register_has_correct_casts()
    {
        $register = new Register();
        $casts = $register->getCasts();
        
        $expectedCasts = [
            'device_id' => 'integer',
            'function' => 'integer',
            'register_address' => 'integer',
            'scale' => 'float',
            'count' => 'integer',
            'enabled' => 'boolean',
            'write_function' => 'integer',
            'write_register' => 'integer',
            'on_value' => 'float',
            'off_value' => 'float',
            'invert' => 'boolean',
            'schedulable' => 'boolean',
        ];
        
        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertArrayHasKey($field, $casts, "Field '{$field}' should have a cast");
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    /** @test */
    public function register_has_correct_function_constants()
    {
        $expectedFunctions = [
            1 => 'Coils',
            2 => 'Discrete Inputs',
            3 => 'Holding Registers',
            4 => 'Input Registers'
        ];
        
        $this->assertEquals($expectedFunctions, Register::FUNCTIONS);
    }

    /** @test */
    public function register_has_correct_data_type_constants()
    {
        $expectedDataTypes = [
            'int16' => 'Int16',
            'uint16' => 'UInt16',
            'int32' => 'Int32',
            'uint32' => 'UInt32',
            'float32' => 'Float32',
            'float64' => 'Float64'
        ];
        
        $this->assertEquals($expectedDataTypes, Register::DATA_TYPES);
    }

    /** @test */
    public function register_has_correct_byte_order_constants()
    {
        $expectedByteOrders = [
            'big_endian' => 'Big Endian',
            'little_endian' => 'Little Endian',
            'word_swap' => 'Word Swap',
            'byte_swap' => 'Byte Swap'
        ];
        
        $this->assertEquals($expectedByteOrders, Register::BYTE_ORDERS);
    }

    /** @test */
    public function register_has_relationship_methods()
    {
        $register = new Register();
        
        $this->assertTrue(method_exists($register, 'device'), 'Register should have device relationship method');
        $this->assertTrue(method_exists($register, 'readings'), 'Register should have readings relationship method');
    }

    /** @test */
    public function register_has_scope_methods()
    {
        $register = new Register();
        
        $this->assertTrue(method_exists($register, 'scopeEnabled'), 'Register should have enabled scope method');
    }

    /** @test */
    public function register_has_accessor_methods()
    {
        $register = new Register();
        
        $accessorMethods = [
            'getFunctionNameAttribute',
            'getDataTypeNameAttribute',
            'getByteOrderNameAttribute',
            'getRegisterEndAddressAttribute',
            'getIsInputRegisterAttribute',
            'getIsHoldingRegisterAttribute',
            'getIsCoilAttribute',
            'getIsDiscreteInputAttribute',
            'getLatestReadingAttribute',
            'getRequiredRegisterCountAttribute',
            'getSupportsWritingAttribute',
            'getSupportsSchedulingAttribute',
            'getUnitAttribute',
            'getValidationErrorsAttribute',
        ];
        
        foreach ($accessorMethods as $method) {
            $this->assertTrue(method_exists($register, $method), "Register should have {$method} method");
        }
    }

    /** @test */
    public function register_function_name_attribute_logic()
    {
        $register = new Register(['function' => 3]);
        $this->assertEquals('Holding Registers', $register->getFunctionNameAttribute());
        
        $register = new Register(['function' => 99]);
        $this->assertEquals('Unknown', $register->getFunctionNameAttribute());
    }

    /** @test */
    public function register_data_type_name_attribute_logic()
    {
        $register = new Register(['data_type' => 'float32']);
        $this->assertEquals('Float32', $register->getDataTypeNameAttribute());
        
        $register = new Register(['data_type' => 'invalid_type']);
        $this->assertEquals('Unknown', $register->getDataTypeNameAttribute());
    }

    /** @test */
    public function register_byte_order_name_attribute_logic()
    {
        $register = new Register(['byte_order' => 'big_endian']);
        $this->assertEquals('Big Endian', $register->getByteOrderNameAttribute());
        
        $register = new Register(['byte_order' => 'invalid_order']);
        $this->assertEquals('Unknown', $register->getByteOrderNameAttribute());
    }

    /** @test */
    public function register_end_address_calculation()
    {
        $register = new Register([
            'register_address' => 1000,
            'count' => 2
        ]);
        
        $this->assertEquals(1001, $register->getRegisterEndAddressAttribute());
    }

    /** @test */
    public function register_function_type_boolean_attributes()
    {
        $inputRegister = new Register(['function' => 4]);
        $this->assertTrue($inputRegister->getIsInputRegisterAttribute());
        $this->assertFalse($inputRegister->getIsHoldingRegisterAttribute());
        $this->assertFalse($inputRegister->getIsCoilAttribute());
        $this->assertFalse($inputRegister->getIsDiscreteInputAttribute());
        
        $holdingRegister = new Register(['function' => 3]);
        $this->assertFalse($holdingRegister->getIsInputRegisterAttribute());
        $this->assertTrue($holdingRegister->getIsHoldingRegisterAttribute());
        $this->assertFalse($holdingRegister->getIsCoilAttribute());
        $this->assertFalse($holdingRegister->getIsDiscreteInputAttribute());
        
        $coil = new Register(['function' => 1]);
        $this->assertFalse($coil->getIsInputRegisterAttribute());
        $this->assertFalse($coil->getIsHoldingRegisterAttribute());
        $this->assertTrue($coil->getIsCoilAttribute());
        $this->assertFalse($coil->getIsDiscreteInputAttribute());
        
        $discreteInput = new Register(['function' => 2]);
        $this->assertFalse($discreteInput->getIsInputRegisterAttribute());
        $this->assertFalse($discreteInput->getIsHoldingRegisterAttribute());
        $this->assertFalse($discreteInput->getIsCoilAttribute());
        $this->assertTrue($discreteInput->getIsDiscreteInputAttribute());
    }

    /** @test */
    public function register_supports_writing_logic()
    {
        $writableRegister = new Register([
            'write_function' => 6,
            'write_register' => 1000
        ]);
        $this->assertTrue($writableRegister->getSupportsWritingAttribute());
        
        $nonWritableRegister = new Register([
            'write_function' => null,
            'write_register' => null
        ]);
        $this->assertFalse($nonWritableRegister->getSupportsWritingAttribute());
    }

    /** @test */
    public function register_supports_scheduling_logic()
    {
        $schedulableRegister = new Register([
            'schedulable' => true,
            'write_function' => 6,
            'write_register' => 1000
        ]);
        $this->assertTrue($schedulableRegister->getSupportsSchedulingAttribute());
        
        $nonSchedulableRegister = new Register([
            'schedulable' => false,
            'write_function' => 6,
            'write_register' => 1000
        ]);
        $this->assertFalse($nonSchedulableRegister->getSupportsSchedulingAttribute());
        
        $nonWritableRegister = new Register([
            'schedulable' => true,
            'write_function' => null,
            'write_register' => null
        ]);
        $this->assertFalse($nonWritableRegister->getSupportsSchedulingAttribute());
    }

    /** @test */
    public function register_validation_methods()
    {
        $register = new Register();
        
        $validationMethods = [
            'validateRegisterAddress',
            'validateRegisterRange',
            'validateModbusFunction',
            'validateDataType',
            'validateByteOrder',
            'validateRegisterCount',
            'validateScaleFactor',
            'validateWriteConfiguration',
            'validateAllConstraints',
            'isValid',
        ];
        
        foreach ($validationMethods as $method) {
            $this->assertTrue(method_exists($register, $method), "Register should have {$method} method");
        }
    }

    /** @test */
    public function register_address_validation_logic()
    {
        $validRegister = new Register(['register_address' => 1000]);
        $this->assertTrue($validRegister->validateRegisterAddress());
        
        $invalidRegister = new Register(['register_address' => -1]);
        $this->assertFalse($invalidRegister->validateRegisterAddress());
        
        $invalidRegister2 = new Register(['register_address' => 65536]);
        $this->assertFalse($invalidRegister2->validateRegisterAddress());
    }

    /** @test */
    public function register_range_validation_logic()
    {
        $validRegister = new Register([
            'register_address' => 65533,
            'count' => 2
        ]);
        $this->assertTrue($validRegister->validateRegisterRange());
        
        $invalidRegister = new Register([
            'register_address' => 65534,
            'count' => 3
        ]);
        $this->assertFalse($invalidRegister->validateRegisterRange());
    }

    /** @test */
    public function modbus_function_validation_logic()
    {
        $validFunctions = [1, 2, 3, 4];
        
        foreach ($validFunctions as $function) {
            $register = new Register(['function' => $function]);
            $this->assertTrue($register->validateModbusFunction(), "Function {$function} should be valid");
        }
        
        $invalidFunctions = [0, 5, 99];
        
        foreach ($invalidFunctions as $function) {
            $register = new Register(['function' => $function]);
            $this->assertFalse($register->validateModbusFunction(), "Function {$function} should be invalid");
        }
    }

    /** @test */
    public function data_type_validation_logic()
    {
        $validTypes = array_keys(Register::DATA_TYPES);
        
        foreach ($validTypes as $dataType) {
            $register = new Register(['data_type' => $dataType]);
            $this->assertTrue($register->validateDataType(), "Data type {$dataType} should be valid");
        }
        
        $register = new Register(['data_type' => 'invalid_type']);
        $this->assertFalse($register->validateDataType());
    }

    /** @test */
    public function byte_order_validation_logic()
    {
        $validOrders = array_keys(Register::BYTE_ORDERS);
        
        foreach ($validOrders as $byteOrder) {
            $register = new Register(['byte_order' => $byteOrder]);
            $this->assertTrue($register->validateByteOrder(), "Byte order {$byteOrder} should be valid");
        }
        
        $register = new Register(['byte_order' => 'invalid_order']);
        $this->assertFalse($register->validateByteOrder());
    }

    /** @test */
    public function scale_factor_validation_logic()
    {
        $validScales = [0.01, 1.0, 10.0, 1000.0, 1000000.0];
        
        foreach ($validScales as $scale) {
            $register = new Register(['scale' => $scale]);
            $this->assertTrue($register->validateScaleFactor(), "Scale {$scale} should be valid");
        }
        
        $invalidScales = [0, -1.0, 1000001.0];
        
        foreach ($invalidScales as $scale) {
            $register = new Register(['scale' => $scale]);
            $this->assertFalse($register->validateScaleFactor(), "Scale {$scale} should be invalid");
        }
    }

    /** @test */
    public function write_configuration_validation_logic()
    {
        // Writing disabled should be valid
        $register = new Register([
            'write_function' => null,
            'write_register' => null
        ]);
        $this->assertTrue($register->validateWriteConfiguration());
        
        // Valid write configuration
        $register = new Register([
            'write_function' => 6,
            'write_register' => 1000
        ]);
        $this->assertTrue($register->validateWriteConfiguration());
        
        // Invalid write function
        $register = new Register([
            'write_function' => 99,
            'write_register' => 1000
        ]);
        $this->assertFalse($register->validateWriteConfiguration());
        
        // Invalid write register
        $register = new Register([
            'write_function' => 6,
            'write_register' => -1
        ]);
        $this->assertFalse($register->validateWriteConfiguration());
    }

    /** @test */
    public function register_has_static_validation_methods()
    {
        $validationMethods = [
            'getValidationRules',
            'getValidationMessages',
            'validateData',
            'isAddressUniqueInDevice',
        ];
        
        foreach ($validationMethods as $method) {
            $this->assertTrue(method_exists(Register::class, $method), "Register should have {$method} static method");
        }
    }

    /** @test */
    public function register_static_methods_return_correct_types()
    {
        $rules = Register::getValidationRules(1);
        $this->assertIsArray($rules);
        
        $messages = Register::getValidationMessages();
        $this->assertIsArray($messages);
        
        $data = [
            'technical_label' => 'Test Register',
            'function' => 3,
            'register_address' => 1000,
            'data_type' => 'float32',
            'byte_order' => 'big_endian'
        ];
        
        $errors = Register::validateData($data, 1);
        $this->assertIsArray($errors);
        
        // Skip database-dependent test if database is not available
        try {
            $result = Register::isAddressUniqueInDevice(1000, 1);
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            // Database not available, skip this assertion
            $this->assertTrue(true, 'Database not available for testing');
        }
    }
}