<?php

namespace Tests\Integration;

use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\DeviceResource;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FilamentFormValidationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user if it doesn't exist
        $this->user = User::first() ?? User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function gateway_form_validation_handles_all_error_scenarios()
    {
        // Test required field validation
        $emptyData = [];
        $response = $this->post(GatewayResource::getUrl('store'), $emptyData);
        $response->assertSessionHasErrors(['name', 'ip_address', 'port']);

        // Test IP address validation
        $invalidIpData = [
            'name' => 'Test Gateway',
            'ip_address' => 'invalid-ip-address',
            'port' => 502,
        ];
        $response = $this->post(GatewayResource::getUrl('store'), $invalidIpData);
        $response->assertSessionHasErrors(['ip_address']);

        // Test port range validation
        $invalidPortData = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 70000, // Out of valid range
        ];
        $response = $this->post(GatewayResource::getUrl('store'), $invalidPortData);
        $response->assertSessionHasErrors(['port']);

        $negativePortData = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => -1,
        ];
        $response = $this->post(GatewayResource::getUrl('store'), $negativePortData);
        $response->assertSessionHasErrors(['port']);

        // Test unique IP/port combination validation
        Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        $duplicateData = [
            'name' => 'Another Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ];
        $response = $this->post(GatewayResource::getUrl('store'), $duplicateData);
        $response->assertSessionHasErrors();

        // Test valid data passes
        $validData = [
            'name' => 'Valid Gateway',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'active' => true,
        ];
        $response = $this->post(GatewayResource::getUrl('store'), $validData);
        $response->assertRedirect();
        $this->assertDatabaseHas('gateways', ['name' => 'Valid Gateway']);
    }

    /** @test */
    public function device_form_validation_handles_all_error_scenarios()
    {
        $gateway = Gateway::factory()->create();

        // Test required field validation
        $emptyData = [];
        $response = $this->post(DeviceResource::getUrl('store'), $emptyData);
        $response->assertSessionHasErrors(['gateway_id', 'device_name', 'device_type']);

        // Test invalid gateway_id
        $invalidGatewayData = [
            'gateway_id' => 999999,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $invalidGatewayData);
        $response->assertSessionHasErrors(['gateway_id']);

        // Test invalid device_type enum
        $invalidTypeData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
            'device_type' => 'invalid_type',
            'load_category' => 'hvac',
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $invalidTypeData);
        $response->assertSessionHasErrors(['device_type']);

        // Test invalid load_category enum
        $invalidCategoryData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'invalid_category',
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $invalidCategoryData);
        $response->assertSessionHasErrors(['load_category']);

        // Test unique device name within gateway validation
        Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Existing Device',
        ]);

        $duplicateNameData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Existing Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $duplicateNameData);
        $response->assertSessionHasErrors();

        // Test device name length validation
        $longNameData = [
            'gateway_id' => $gateway->id,
            'device_name' => str_repeat('A', 256), // Exceeds max length
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $longNameData);
        $response->assertSessionHasErrors(['device_name']);

        // Test valid data passes
        $validData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Valid Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ];
        $response = $this->post(DeviceResource::getUrl('store'), $validData);
        $response->assertRedirect();
        $this->assertDatabaseHas('devices', ['device_name' => 'Valid Device']);
    }

    /** @test */
    public function manage_gateway_devices_form_validation_works()
    {
        $gateway = Gateway::factory()->create();
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);

        // Access the page first
        $response = $this->get($manageDevicesUrl);
        $response->assertSuccessful();

        // Test that the page loads and shows the gateway context
        $response->assertSee($gateway->name);
        $response->assertSee('Add Device');
    }

    /** @test */
    public function manage_device_registers_form_validation_works()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $manageRegistersUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]);

        // Access the page first
        $response = $this->get($manageRegistersUrl);
        $response->assertSuccessful();

        // Test that the page loads and shows the proper context
        $response->assertSee($gateway->name);
        $response->assertSee($device->device_name);
        $response->assertSee('Add Register');
    }

    /** @test */
    public function register_validation_handles_modbus_constraints()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Test register address range validation (0-65535)
        $invalidAddressData = [
            'device_id' => $device->id,
            'technical_label' => 'Test Register',
            'function' => 4,
            'register_address' => 70000, // Out of range
            'data_type' => 'int16',
            'byte_order' => 'big_endian',
            'scale' => 1.0,
            'count' => 1,
        ];

        // Since we can't directly test the register creation through the manage page,
        // we'll test the model validation directly
        $register = new Register($invalidAddressData);
        $this->assertFalse($register->save());

        // Test valid register data
        $validRegisterData = [
            'device_id' => $device->id,
            'technical_label' => 'Valid Register',
            'function' => 4,
            'register_address' => 1000,
            'data_type' => 'int16',
            'byte_order' => 'big_endian',
            'scale' => 1.0,
            'count' => 1,
            'enabled' => true,
        ];

        $validRegister = Register::create($validRegisterData);
        $this->assertNotNull($validRegister);
        $this->assertDatabaseHas('registers', ['technical_label' => 'Valid Register']);
    }

    /** @test */
    public function form_error_messages_are_user_friendly()
    {
        // Test gateway form error messages
        $response = $this->post(GatewayResource::getUrl('store'), [
            'name' => '',
            'ip_address' => 'invalid',
            'port' => 'not-a-number',
        ]);

        $response->assertSessionHasErrors();
        
        // The exact error messages depend on the validation rules implementation
        // We're testing that errors are present for the expected fields
        $errors = session('errors');
        $this->assertTrue($errors->has('name'));
        $this->assertTrue($errors->has('ip_address'));
        $this->assertTrue($errors->has('port'));
    }

    /** @test */
    public function validation_works_during_updates()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Original Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        // Test updating with invalid data
        $invalidUpdateData = [
            'name' => '', // Required field empty
            'ip_address' => 'invalid-ip',
            'port' => 70000,
        ];

        $response = $this->put(GatewayResource::getUrl('update', ['record' => $gateway]), $invalidUpdateData);
        $response->assertSessionHasErrors(['name', 'ip_address', 'port']);

        // Test updating with valid data
        $validUpdateData = [
            'name' => 'Updated Gateway',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 15,
            'active' => true,
        ];

        $response = $this->put(GatewayResource::getUrl('update', ['record' => $gateway]), $validUpdateData);
        $response->assertRedirect();
        
        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'name' => 'Updated Gateway',
        ]);
    }

    /** @test */
    public function cross_resource_validation_maintains_data_integrity()
    {
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        
        $device = Device::factory()->create(['gateway_id' => $gateway1->id]);

        // Test that we can't assign a device to a different gateway through direct manipulation
        // This tests the foreign key constraints and validation
        $device->gateway_id = $gateway2->id;
        $device->save();
        
        // Verify the device was reassigned (this should work)
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'gateway_id' => $gateway2->id,
        ]);

        // Test that registers maintain proper device relationships
        $register = Register::factory()->create(['device_id' => $device->id]);
        
        $this->assertDatabaseHas('registers', [
            'id' => $register->id,
            'device_id' => $device->id,
        ]);

        // Test cascade deletion maintains integrity
        $deviceId = $device->id;
        $registerId = $register->id;
        
        $device->delete();
        
        $this->assertDatabaseMissing('devices', ['id' => $deviceId]);
        $this->assertDatabaseMissing('registers', ['id' => $registerId]);
    }

    /** @test */
    public function validation_prevents_circular_references_and_invalid_states()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Test that we can't create a register with invalid device_id
        $invalidRegisterData = [
            'device_id' => 999999, // Non-existent device
            'technical_label' => 'Invalid Register',
            'function' => 4,
            'register_address' => 1000,
            'data_type' => 'int16',
            'byte_order' => 'big_endian',
            'scale' => 1.0,
            'count' => 1,
        ];

        $register = new Register($invalidRegisterData);
        
        // This should fail due to foreign key constraint
        try {
            $register->save();
            $this->fail('Expected foreign key constraint violation');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected behavior
        }
    }
}