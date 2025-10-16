<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\Register;
use App\Models\DataPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function device_has_correct_fillable_fields()
    {
        $device = new Device();
        $fillable = $device->getFillable();
        
        $expectedFillable = [
            'gateway_id',
            'device_name',
            'device_type',
            'load_category',
            'enabled',
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    /** @test */
    public function device_has_correct_casts()
    {
        $device = new Device();
        $casts = $device->getCasts();
        
        $expectedCasts = [
            'gateway_id' => 'integer',
            'enabled' => 'boolean',
        ];
        
        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertArrayHasKey($field, $casts, "Field '{$field}' should have a cast");
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    /** @test */
    public function device_has_correct_device_type_constants()
    {
        $expectedTypes = [
            'energy_meter' => 'Energy Meter',
            'water_meter' => 'Water Meter',
            'control' => 'Control Device',
            'other' => 'Other'
        ];
        
        $this->assertEquals($expectedTypes, Device::DEVICE_TYPES);
    }

    /** @test */
    public function device_has_correct_load_category_constants()
    {
        $expectedCategories = [
            'hvac' => 'HVAC',
            'lighting' => 'Lighting',
            'sockets' => 'Sockets',
            'other' => 'Other'
        ];
        
        $this->assertEquals($expectedCategories, Device::LOAD_CATEGORIES);
    }

    /** @test */
    public function device_belongs_to_gateway()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $device->gateway());
        $this->assertEquals($gateway->id, $device->gateway->id);
    }

    /** @test */
    public function device_has_data_points_relationship()
    {
        $device = Device::factory()->create();
        $dataPoint = DataPoint::factory()->create(['device_id' => $device->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $device->dataPoints());
        $this->assertTrue($device->dataPoints->contains($dataPoint));
    }

    /** @test */
    public function device_has_registers_relationship()
    {
        $device = Device::factory()->create();
        $register = Register::factory()->create(['device_id' => $device->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $device->registers());
        $this->assertTrue($device->registers->contains($register));
    }

    /** @test */
    public function active_scope_returns_only_enabled_devices()
    {
        $enabledDevice = Device::factory()->create(['enabled' => true]);
        $disabledDevice = Device::factory()->create(['enabled' => false]);
        
        $activeDevices = Device::active()->get();
        
        $this->assertTrue($activeDevices->contains($enabledDevice));
        $this->assertFalse($activeDevices->contains($disabledDevice));
    }

    /** @test */
    public function device_type_name_attribute_returns_correct_name()
    {
        $device = Device::factory()->create(['device_type' => 'energy_meter']);
        
        $this->assertEquals('Energy Meter', $device->device_type_name);
    }

    /** @test */
    public function device_type_name_attribute_returns_unknown_for_invalid_type()
    {
        $device = new Device(['device_type' => 'invalid_type']);
        
        $this->assertEquals('Unknown', $device->device_type_name);
    }

    /** @test */
    public function load_category_name_attribute_returns_correct_name()
    {
        $device = Device::factory()->create(['load_category' => 'hvac']);
        
        $this->assertEquals('HVAC', $device->load_category_name);
    }

    /** @test */
    public function load_category_name_attribute_returns_unknown_for_invalid_category()
    {
        $device = new Device(['load_category' => 'invalid_category']);
        
        $this->assertEquals('Unknown', $device->load_category_name);
    }

    /** @test */
    public function device_icon_attribute_returns_correct_icon_path()
    {
        $testCases = [
            'mains' => '/images/icons/electric-meter.png',
            'ac' => '/images/icons/fan(1).png',
            'sockets' => '/images/icons/supply.png',
            'heater' => '/images/icons/radiator.png',
            'lighting' => '/images/icons/supply.png',
            'water' => '/images/icons/faucet(1).png',
            'solar' => '/images/icons/electric-meter.png',
            'generator' => '/images/icons/electric-meter.png',
            'unknown' => '/images/icons/electric-meter.png',
        ];
        
        foreach ($testCases as $category => $expectedIcon) {
            $device = new Device(['load_category' => $category]);
            $this->assertEquals($expectedIcon, $device->device_icon, "Icon for category '{$category}' should be '{$expectedIcon}'");
        }
    }

    /** @test */
    public function is_energy_meter_attribute_returns_true_for_energy_meter()
    {
        $device = Device::factory()->create(['device_type' => 'energy_meter']);
        
        $this->assertTrue($device->is_energy_meter);
    }

    /** @test */
    public function is_energy_meter_attribute_returns_false_for_other_types()
    {
        $device = Device::factory()->create(['device_type' => 'water_meter']);
        
        $this->assertFalse($device->is_energy_meter);
    }

    /** @test */
    public function is_water_meter_attribute_returns_true_for_water_meter()
    {
        $device = Device::factory()->create(['device_type' => 'water_meter']);
        
        $this->assertTrue($device->is_water_meter);
    }

    /** @test */
    public function is_water_meter_attribute_returns_false_for_other_types()
    {
        $device = Device::factory()->create(['device_type' => 'energy_meter']);
        
        $this->assertFalse($device->is_water_meter);
    }

    /** @test */
    public function is_control_device_attribute_returns_true_for_control()
    {
        $device = Device::factory()->create(['device_type' => 'control']);
        
        $this->assertTrue($device->is_control_device);
    }

    /** @test */
    public function is_control_device_attribute_returns_false_for_other_types()
    {
        $device = Device::factory()->create(['device_type' => 'energy_meter']);
        
        $this->assertFalse($device->is_control_device);
    }

    /** @test */
    public function display_name_attribute_returns_device_name_when_set()
    {
        $device = Device::factory()->create(['device_name' => 'Test Device']);
        
        $this->assertEquals('Test Device', $device->display_name);
    }

    /** @test */
    public function display_name_attribute_returns_default_when_name_empty()
    {
        $device = new Device(['device_name' => '']);
        
        $this->assertEquals('Unnamed Device', $device->display_name);
    }

    /** @test */
    public function enabled_data_points_count_attribute_returns_correct_count()
    {
        $device = Device::factory()->create();
        DataPoint::factory()->count(3)->create(['device_id' => $device->id, 'is_enabled' => true]);
        DataPoint::factory()->count(2)->create(['device_id' => $device->id, 'is_enabled' => false]);
        
        $this->assertEquals(3, $device->enabled_data_points_count);
    }

    /** @test */
    public function all_registers_attribute_returns_collection()
    {
        $device = Device::factory()->create();
        Register::factory()->count(3)->create(['device_id' => $device->id]);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $device->all_registers);
        $this->assertEquals(3, $device->all_registers->count());
    }

    /** @test */
    public function registers_count_attribute_returns_correct_count()
    {
        $device = Device::factory()->create();
        Register::factory()->count(5)->create(['device_id' => $device->id]);
        
        $this->assertEquals(5, $device->registers_count);
    }

    /** @test */
    public function enabled_registers_count_attribute_returns_correct_count()
    {
        $device = Device::factory()->create();
        Register::factory()->count(3)->create(['device_id' => $device->id, 'enabled' => true]);
        Register::factory()->count(2)->create(['device_id' => $device->id, 'enabled' => false]);
        
        $this->assertEquals(3, $device->enabled_registers_count);
    }

    /** @test */
    public function disabled_registers_count_attribute_returns_correct_count()
    {
        $device = Device::factory()->create();
        Register::factory()->count(3)->create(['device_id' => $device->id, 'enabled' => true]);
        Register::factory()->count(2)->create(['device_id' => $device->id, 'enabled' => false]);
        
        $this->assertEquals(2, $device->disabled_registers_count);
    }

    /** @test */
    public function statistics_attribute_returns_correct_statistics()
    {
        $device = Device::factory()->create();
        Register::factory()->count(8)->create(['device_id' => $device->id, 'enabled' => true]);
        Register::factory()->count(2)->create(['device_id' => $device->id, 'enabled' => false]);
        
        $statistics = $device->statistics;
        
        $this->assertIsArray($statistics);
        $this->assertEquals(10, $statistics['total_registers']);
        $this->assertEquals(8, $statistics['enabled_registers']);
        $this->assertEquals(2, $statistics['disabled_registers']);
        $this->assertEquals(80.0, $statistics['enabled_percentage']);
    }

    /** @test */
    public function statistics_attribute_handles_zero_registers()
    {
        $device = Device::factory()->create();
        
        $statistics = $device->statistics;
        
        $this->assertIsArray($statistics);
        $this->assertEquals(0, $statistics['total_registers']);
        $this->assertEquals(0, $statistics['enabled_registers']);
        $this->assertEquals(0, $statistics['disabled_registers']);
        $this->assertEquals(0, $statistics['enabled_percentage']);
    }

    /** @test */
    public function get_validation_rules_returns_array()
    {
        $rules = Device::getValidationRules(1);
        
        $this->assertIsArray($rules);
    }

    /** @test */
    public function get_validation_messages_returns_array()
    {
        $messages = Device::getValidationMessages();
        
        $this->assertIsArray($messages);
    }

    /** @test */
    public function validate_data_returns_array()
    {
        $data = [
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac'
        ];
        
        $errors = Device::validateData($data, 1);
        
        $this->assertIsArray($errors);
    }

    /** @test */
    public function is_name_unique_in_gateway_returns_boolean()
    {
        $result = Device::isNameUniqueInGateway('Test Device', 1);
        
        $this->assertIsBool($result);
    }

    /** @test */
    public function get_device_type_options_returns_correct_options()
    {
        $options = Device::getDeviceTypeOptions();
        
        $this->assertEquals(Device::DEVICE_TYPES, $options);
    }

    /** @test */
    public function get_load_category_options_returns_correct_options()
    {
        $options = Device::getLoadCategoryOptions();
        
        $this->assertEquals(Device::LOAD_CATEGORIES, $options);
    }

    /** @test */
    public function device_can_be_created_with_factory()
    {
        $device = Device::factory()->create();
        
        $this->assertInstanceOf(Device::class, $device);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    /** @test */
    public function device_factory_creates_valid_data()
    {
        $device = Device::factory()->create();
        
        $this->assertNotEmpty($device->device_name);
        $this->assertArrayHasKey($device->device_type, Device::DEVICE_TYPES);
        $this->assertArrayHasKey($device->load_category, Device::LOAD_CATEGORIES);
        $this->assertIsBool($device->enabled);
        $this->assertIsInt($device->gateway_id);
    }

    /** @test */
    public function device_factory_states_work_correctly()
    {
        $energyMeter = Device::factory()->energyMeter()->create();
        $this->assertEquals('energy_meter', $energyMeter->device_type);
        
        $waterMeter = Device::factory()->waterMeter()->create();
        $this->assertEquals('water_meter', $waterMeter->device_type);
        
        $controlDevice = Device::factory()->controlDevice()->create();
        $this->assertEquals('control', $controlDevice->device_type);
        
        $disabledDevice = Device::factory()->disabled()->create();
        $this->assertFalse($disabledDevice->enabled);
        
        $hvacDevice = Device::factory()->hvac()->create();
        $this->assertEquals('hvac', $hvacDevice->load_category);
        
        $lightingDevice = Device::factory()->lighting()->create();
        $this->assertEquals('lighting', $lightingDevice->load_category);
        
        $socketsDevice = Device::factory()->sockets()->create();
        $this->assertEquals('sockets', $socketsDevice->load_category);
    }
}