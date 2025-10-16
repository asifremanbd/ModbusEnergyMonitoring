<?php

namespace Tests\Unit;

use App\Models\Register;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterModelTest extends TestCase
{
    use RefreshDatabase;

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
    public function register_belongs_to_device()
    {
        $device = Device::factory()->create();
        $register = Register::factory()->create(['device_id' => $device->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $register->device());
        $this->assertEquals($device->id, $register->device->id);
    }

    /** @test */
    public function register_has_readings_relationship()
    {
        $register = Register::factory()->create();
        $reading = Reading::factory()->create(['register_id' => $register->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $register->readings());
        $this->assertTrue($register->readings->contains($reading));
    }

    /** @test */
    public function enabled_scope_returns_only_enabled_registers()
    {
        $enabledRegister = Register::factory()->create(['enabled' => true]);
        $disabledRegister = Register::factory()->create(['enabled' => false]);
        
        $enabledRegisters = Register::enabled()->get();
        
        $this->assertTrue($enabledRegisters->contains($enabledRegister));
        $this->assertFalse($enabledRegisters->contains($disabledRegister));
    }

    /** @test */
    public function function_name_attribute_returns_correct_name()
    {
        $register = Register::factory()->create(['function' => 3]);
        
        $this->assertEquals('Holding Registers', $register->function_name);
    }

    /** @test */
    public function function_name_attribute_returns_unknown_for_invalid_function()
    {
        $register = new Register(['function' => 99]);
        
        $this->assertEquals('Unknown', $register->function_name);
    }

    /** @test */
    public function data_type_name_attribute_returns_correct_name()
    {
        $register = Register::factory()->create(['data_type' => 'float32']);
        
        $this->assertEquals('Float32', $register->data_type_name);
    }

    /** @test */
    public function data_type_name_attribute_returns_unknown_for_invalid_type()
    {
        $register = new Register(['data_type' => 'invalid_type']);
        
        $this->assertEquals('Unknown', $register->data_type_name);
    }

    /** @test */
    public function byte_order_name_attribute_returns_correct_name()
    {
        $register = Register::factory()->create(['byte_order' => 'big_endian']);
        
        $this->assertEquals('Big Endian', $register->byte_order_name);
    }

    /** @test */
    public function byte_order_name_attribute_returns_unknown_for_invalid_order()
    {
        $register = new Register(['byte_order' => 'invalid_order']);
        
        $this->assertEquals('Unknown', $register->byte_order_name);
    }

    /** @test */
    public function register_end_address_attribute_calculates_correctly()
    {
        $register = Register::factory()->create([
            'register_address' => 1000,
            'count' => 2
        ]);
        
        $this->assertEquals(1001, $register->register_end_address);
    }

    /** @test */
    public function is_input_register_attribute_returns_true_for_function_4()
    {
        $register = Register::factory()->create(['function' => 4]);
        
        $this->assertTrue($register->is_input_register);
    }

    /** @test */
    public function is_input_register_attribute_returns_false_for_other_functions()
    {
        $register = Register::factory()->create(['function' => 3]);
        
        $this->assertFalse($register->is_input_register);
    }

    /** @test */
    public function is_holding_register_attribute_returns_true_for_function_3()
    {
        $register = Register::factory()->create(['function' => 3]);
        
        $this->assertTrue($register->is_holding_register);
    }

    /** @test */
    public function is_holding_register_attribute_returns_false_for_other_functions()
    {
        $register = Register::factory()->create(['function' => 4]);
        
        $this->assertFalse($register->is_holding_register);
    }

    /** @test */
    public function is_coil_attribute_returns_true_for_function_1()
    {
        $register = Register::factory()->create(['function' => 1]);
        
        $this->assertTrue($register->is_coil);
    }

    /** @test */
    public function is_coil_attribute_returns_false_for_other_functions()
    {
        $register = Register::factory()->create(['function' => 3]);
        
        $this->assertFalse($register->is_coil);
    }

    /** @test */
    public function is_discrete_input_attribute_returns_true_for_function_2()
    {
        $register = Register::factory()->create(['function' => 2]);
        
        $this->assertTrue($register->is_discrete_input);
    }

    /** @test */
    public function is_discrete_input_attribute_returns_false_for_other_functions()
    {
        $register = Register::factory()->create(['function' => 3]);
        
        $this->assertFalse($register->is_discrete_input);
    }

    /** @test */
    public function latest_reading_attribute_returns_most_recent_reading()
    {
        $register = Register::factory()->create();
        $oldReading = Reading::factory()->create([
            'register_id' => $register->id,
            'read_at' => now()->subHours(2)
        ]);
        $newReading = Reading::factory()->create([
            'register_id' => $register->id,
            'read_at' => now()->subHour()
        ]);
        
        $this->assertEquals($newReading->id, $register->latest_reading->id);
    }

    /** @test */
    public function supports_writing_attribute_returns_true_when_write_fields_set()
    {
        $register = Register::factory()->create([
            'write_function' => 6,
            'write_register' => 1000
        ]);
        
        $this->assertTrue($register->supports_writing);
    }

    /** @test */
    public function supports_writing_attribute_returns_false_when_write_fields_null()
    {
        $register = Register::factory()->create([
            'write_function' => null,
            'write_register' => null
        ]);
        
        $this->assertFalse($register->supports_writing);
    }

    /** @test */
    public function supports_scheduling_attribute_returns_true_when_schedulable_and_writable()
    {
        $register = Register::factory()->create([
            'schedulable' => true,
            'write_function' => 6,
            'write_register' => 1000
        ]);
        
        $this->assertTrue($register->supports_scheduling);
    }

    /** @test */
    public function supports_scheduling_attribute_returns_false_when_not_schedulable()
    {
        $register = Register::factory()->create([
            'schedulable' => false,
            'write_function' => 6,
            'write_register' => 1000
        ]);
        
        $this->assertFalse($register->supports_scheduling);
    }

    /** @test */
    public function supports_scheduling_attribute_returns_false_when_not_writable()
    {
        $register = Register::factory()->create([
            'schedulable' => true,
            'write_function' => null,
            'write_register' => null
        ]);
        
        $this->assertFalse($register->supports_scheduling);
    }

    /** @test */
    public function unit_attribute_returns_correct_unit_based_on_device_type()
    {
        $gateway = Gateway::factory()->create();
        
        $energyDevice = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_type' => 'energy'
        ]);
        $energyRegister = Register::factory()->create(['device_id' => $energyDevice->id]);
        $this->assertEquals('kWh', $energyRegister->unit);
        
        $waterDevice = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_type' => 'water'
        ]);
        $waterRegister = Register::factory()->create(['device_id' => $waterDevice->id]);
        $this->assertEquals('m³', $waterRegister->unit);
        
        $controlDevice = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_type' => 'control'
        ]);
        $controlRegister = Register::factory()->create(['device_id' => $controlDevice->id]);
        $this->assertEquals('state', $controlRegister->unit);
        
        $otherDevice = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_type' => 'other'
        ]);
        $otherRegister = Register::factory()->create(['device_id' => $otherDevice->id]);
        $this->assertEquals('units', $otherRegister->unit);
    }

    /** @test */
    public function validate_register_address_returns_true_for_valid_address()
    {
        $register = Register::factory()->create(['register_address' => 1000]);
        
        $this->assertTrue($register->validateRegisterAddress());
    }

    /** @test */
    public function validate_register_address_returns_false_for_negative_address()
    {
        $register = new Register(['register_address' => -1]);
        
        $this->assertFalse($register->validateRegisterAddress());
    }

    /** @test */
    public function validate_register_address_returns_false_for_address_above_limit()
    {
        $register = new Register(['register_address' => 65536]);
        
        $this->assertFalse($register->validateRegisterAddress());
    }

    /** @test */
    public function validate_register_range_returns_true_for_valid_range()
    {
        $register = Register::factory()->create([
            'register_address' => 65533,
            'count' => 2
        ]);
        
        $this->assertTrue($register->validateRegisterRange());
    }

    /** @test */
    public function validate_register_range_returns_false_for_range_exceeding_limit()
    {
        $register = new Register([
            'register_address' => 65534,
            'count' => 3
        ]);
        
        $this->assertFalse($register->validateRegisterRange());
    }

    /** @test */
    public function validate_modbus_function_returns_true_for_valid_functions()
    {
        $validFunctions = [1, 2, 3, 4];
        
        foreach ($validFunctions as $function) {
            $register = new Register(['function' => $function]);
            $this->assertTrue($register->validateModbusFunction(), "Function {$function} should be valid");
        }
    }

    /** @test */
    public function validate_modbus_function_returns_false_for_invalid_functions()
    {
        $invalidFunctions = [0, 5, 99];
        
        foreach ($invalidFunctions as $function) {
            $register = new Register(['function' => $function]);
            $this->assertFalse($register->validateModbusFunction(), "Function {$function} should be invalid");
        }
    }

    /** @test */
    public function validate_data_type_returns_true_for_valid_types()
    {
        $validTypes = array_keys(Register::DATA_TYPES);
        
        foreach ($validTypes as $dataType) {
            $register = new Register(['data_type' => $dataType]);
            $this->assertTrue($register->validateDataType(), "Data type {$dataType} should be valid");
        }
    }

    /** @test */
    public function validate_data_type_returns_false_for_invalid_types()
    {
        $register = new Register(['data_type' => 'invalid_type']);
        
        $this->assertFalse($register->validateDataType());
    }

    /** @test */
    public function validate_byte_order_returns_true_for_valid_orders()
    {
        $validOrders = array_keys(Register::BYTE_ORDERS);
        
        foreach ($validOrders as $byteOrder) {
            $register = new Register(['byte_order' => $byteOrder]);
            $this->assertTrue($register->validateByteOrder(), "Byte order {$byteOrder} should be valid");
        }
    }

    /** @test */
    public function validate_byte_order_returns_false_for_invalid_orders()
    {
        $register = new Register(['byte_order' => 'invalid_order']);
        
        $this->assertFalse($register->validateByteOrder());
    }

    /** @test */
    public function validate_scale_factor_returns_true_for_valid_scale()
    {
        $validScales = [0.01, 1.0, 10.0, 1000.0, 1000000.0];
        
        foreach ($validScales as $scale) {
            $register = new Register(['scale' => $scale]);
            $this->assertTrue($register->validateScaleFactor(), "Scale {$scale} should be valid");
        }
    }

    /** @test */
    public function validate_scale_factor_returns_false_for_invalid_scale()
    {
        $invalidScales = [0, -1.0, 1000001.0];
        
        foreach ($invalidScales as $scale) {
            $register = new Register(['scale' => $scale]);
            $this->assertFalse($register->validateScaleFactor(), "Scale {$scale} should be invalid");
        }
    }

    /** @test */
    public function validate_write_configuration_returns_true_when_writing_disabled()
    {
        $register = new Register([
            'write_function' => null,
            'write_register' => null
        ]);
        
        $this->assertTrue($register->validateWriteConfiguration());
    }

    /** @test */
    public function validate_write_configuration_returns_true_for_valid_write_config()
    {
        $register = new Register([
            'write_function' => 6,
            'write_register' => 1000
        ]);
        
        $this->assertTrue($register->validateWriteConfiguration());
    }

    /** @test */
    public function validate_write_configuration_returns_false_for_invalid_write_function()
    {
        $register = new Register([
            'write_function' => 99,
            'write_register' => 1000
        ]);
        
        $this->assertFalse($register->validateWriteConfiguration());
    }

    /** @test */
    public function validate_write_configuration_returns_false_for_invalid_write_register()
    {
        $register = new Register([
            'write_function' => 6,
            'write_register' => -1
        ]);
        
        $this->assertFalse($register->validateWriteConfiguration());
    }

    /** @test */
    public function validate_all_constraints_returns_empty_array_for_valid_register()
    {
        $register = Register::factory()->create([
            'register_address' => 1000,
            'function' => 3,
            'data_type' => 'float32',
            'byte_order' => 'big_endian',
            'count' => 2,
            'scale' => 1.0
        ]);
        
        $errors = $register->validateAllConstraints();
        
        $this->assertEmpty($errors);
    }

    /** @test */
    public function validate_all_constraints_returns_errors_for_invalid_register()
    {
        $register = new Register([
            'register_address' => -1,
            'function' => 99,
            'data_type' => 'invalid',
            'byte_order' => 'invalid',
            'count' => 1,
            'scale' => 0
        ]);
        
        $errors = $register->validateAllConstraints();
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Register address must be between 0 and 65535', $errors);
        $this->assertContains('Invalid Modbus function. Must be 1, 2, 3, or 4', $errors);
        $this->assertContains('Unsupported data type', $errors);
        $this->assertContains('Unsupported byte order', $errors);
        $this->assertContains('Scale factor must be between 0 and 1,000,000', $errors);
    }

    /** @test */
    public function is_valid_returns_true_for_valid_register()
    {
        $register = Register::factory()->create();
        
        $this->assertTrue($register->isValid());
    }

    /** @test */
    public function is_valid_returns_false_for_invalid_register()
    {
        $register = new Register([
            'register_address' => -1,
            'function' => 99
        ]);
        
        $this->assertFalse($register->isValid());
    }

    /** @test */
    public function validation_errors_attribute_returns_empty_string_for_valid_register()
    {
        $register = Register::factory()->create();
        
        $this->assertEquals('', $register->validation_errors);
    }

    /** @test */
    public function validation_errors_attribute_returns_formatted_errors_for_invalid_register()
    {
        $register = new Register([
            'register_address' => -1,
            'function' => 99
        ]);
        
        $errors = $register->validation_errors;
        
        $this->assertNotEmpty($errors);
        $this->assertStringContains('Register address must be between 0 and 65535', $errors);
        $this->assertStringContains('Invalid Modbus function', $errors);
    }

    /** @test */
    public function register_can_be_created_with_factory()
    {
        $register = Register::factory()->create();
        
        $this->assertInstanceOf(Register::class, $register);
        $this->assertDatabaseHas('registers', ['id' => $register->id]);
    }

    /** @test */
    public function register_factory_creates_valid_data()
    {
        $register = Register::factory()->create();
        
        $this->assertNotEmpty($register->technical_label);
        $this->assertContains($register->function, [1, 2, 3, 4]);
        $this->assertArrayHasKey($register->data_type, Register::DATA_TYPES);
        $this->assertArrayHasKey($register->byte_order, Register::BYTE_ORDERS);
        $this->assertIsInt($register->register_address);
        $this->assertIsFloat($register->scale);
        $this->assertIsInt($register->count);
        $this->assertIsBool($register->enabled);
    }

    /** @test */
    public function register_factory_states_work_correctly()
    {
        $coil = Register::factory()->coil()->create();
        $this->assertEquals(1, $coil->function);
        
        $discreteInput = Register::factory()->discreteInput()->create();
        $this->assertEquals(2, $discreteInput->function);
        
        $holdingRegister = Register::factory()->holdingRegister()->create();
        $this->assertEquals(3, $holdingRegister->function);
        
        $inputRegister = Register::factory()->inputRegister()->create();
        $this->assertEquals(4, $inputRegister->function);
        
        $float32Register = Register::factory()->float32()->create();
        $this->assertEquals('float32', $float32Register->data_type);
        $this->assertEquals(2, $float32Register->count);
        
        $disabledRegister = Register::factory()->disabled()->create();
        $this->assertFalse($disabledRegister->enabled);
        
        $writableRegister = Register::factory()->writable()->create();
        $this->assertNotNull($writableRegister->write_function);
        $this->assertNotNull($writableRegister->write_register);
        
        $schedulableRegister = Register::factory()->schedulable()->create();
        $this->assertTrue($schedulableRegister->schedulable);
    }
}