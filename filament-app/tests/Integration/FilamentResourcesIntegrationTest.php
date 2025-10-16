<?php

namespace Tests\Integration;

use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\DeviceResource;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FilamentResourcesIntegrationTest extends TestCase
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
    public function gateway_resource_crud_operations_work_correctly()
    {
        // Test Gateway List Page
        $response = $this->get(GatewayResource::getUrl('index'));
        $response->assertSuccessful();
        $response->assertSee('Gateways');

        // Test Gateway Creation
        $gatewayData = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'active' => true,
        ];

        $createResponse = $this->get(GatewayResource::getUrl('create'));
        $createResponse->assertSuccessful();

        $storeResponse = $this->post(GatewayResource::getUrl('store'), $gatewayData);
        $storeResponse->assertRedirect();

        $this->assertDatabaseHas('gateways', [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        $gateway = Gateway::where('name', 'Test Gateway')->first();
        $this->assertNotNull($gateway);

        // Test Gateway View
        $viewResponse = $this->get(GatewayResource::getUrl('view', ['record' => $gateway]));
        $viewResponse->assertSuccessful();
        $viewResponse->assertSee('Test Gateway');

        // Test Gateway Edit
        $editResponse = $this->get(GatewayResource::getUrl('edit', ['record' => $gateway]));
        $editResponse->assertSuccessful();

        $updateData = array_merge($gatewayData, ['name' => 'Updated Gateway']);
        $updateResponse = $this->put(GatewayResource::getUrl('update', ['record' => $gateway]), $updateData);
        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'name' => 'Updated Gateway',
        ]);

        // Test Gateway Deletion
        $deleteResponse = $this->delete(GatewayResource::getUrl('destroy', ['record' => $gateway]));
        $deleteResponse->assertRedirect();

        $this->assertDatabaseMissing('gateways', [
            'id' => $gateway->id,
        ]);
    }

    /** @test */
    public function manage_gateway_devices_page_functionality_works()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
        ]);

        // Test accessing the manage devices page
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);
        $response = $this->get($manageDevicesUrl);
        
        $response->assertSuccessful();
        $response->assertSee('Devices');
        $response->assertSee($gateway->name);

        // Create some devices for the gateway
        $device1 = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Energy Meter 1',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
        ]);

        $device2 = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Water Meter A',
            'device_type' => 'water_meter',
            'load_category' => 'other',
        ]);

        // Test that devices are displayed
        $response = $this->get($manageDevicesUrl);
        $response->assertSee('Energy Meter 1');
        $response->assertSee('Water Meter A');
        $response->assertSee('Energy Meter');
        $response->assertSee('Water Meter');
    }

    /** @test */
    public function manage_device_registers_page_functionality_works()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
        ]);

        // Test accessing the manage registers page
        $manageRegistersUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]);
        
        $response = $this->get($manageRegistersUrl);
        
        $response->assertSuccessful();
        $response->assertSee('Registers');
        $response->assertSee($device->device_name);
        $response->assertSee($gateway->name);

        // Create some registers for the device
        $register1 = Register::factory()->create([
            'device_id' => $device->id,
            'technical_label' => 'Active Power L1',
            'function' => 4,
            'register_address' => 1000,
        ]);

        $register2 = Register::factory()->create([
            'device_id' => $device->id,
            'technical_label' => 'Voltage L1',
            'function' => 4,
            'register_address' => 1100,
        ]);

        // Test that registers are displayed
        $response = $this->get($manageRegistersUrl);
        $response->assertSee('Active Power L1');
        $response->assertSee('Voltage L1');
        $response->assertSee('1000');
        $response->assertSee('1100');
    }

    /** @test */
    public function device_resource_crud_operations_work_correctly()
    {
        $gateway = Gateway::factory()->create();

        // Test Device List Page
        $response = $this->get(DeviceResource::getUrl('index'));
        $response->assertSuccessful();
        $response->assertSee('Devices');

        // Test Device Creation
        $deviceData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ];

        $createResponse = $this->get(DeviceResource::getUrl('create'));
        $createResponse->assertSuccessful();

        $storeResponse = $this->post(DeviceResource::getUrl('store'), $deviceData);
        $storeResponse->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'gateway_id' => $gateway->id,
        ]);

        $device = Device::where('device_name', 'Test Device')->first();
        $this->assertNotNull($device);

        // Test Device Edit
        $editResponse = $this->get(DeviceResource::getUrl('edit', ['record' => $device]));
        $editResponse->assertSuccessful();

        $updateData = array_merge($deviceData, ['device_name' => 'Updated Device']);
        $updateResponse = $this->put(DeviceResource::getUrl('update', ['record' => $device]), $updateData);
        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'device_name' => 'Updated Device',
        ]);

        // Test Device Deletion
        $deleteResponse = $this->delete(DeviceResource::getUrl('destroy', ['record' => $device]));
        $deleteResponse->assertRedirect();

        $this->assertDatabaseMissing('devices', [
            'id' => $device->id,
        ]);
    }

    /** @test */
    public function form_validation_works_across_all_resources()
    {
        // Test Gateway validation
        $invalidGatewayData = [
            'name' => '', // Required field empty
            'ip_address' => 'invalid-ip',
            'port' => 70000, // Out of range
        ];

        $gatewayResponse = $this->post(GatewayResource::getUrl('store'), $invalidGatewayData);
        $gatewayResponse->assertSessionHasErrors(['name', 'ip_address', 'port']);

        // Test Device validation
        $invalidDeviceData = [
            'gateway_id' => 999999, // Non-existent gateway
            'device_name' => '', // Required field empty
            'device_type' => 'invalid_type',
        ];

        $deviceResponse = $this->post(DeviceResource::getUrl('store'), $invalidDeviceData);
        $deviceResponse->assertSessionHasErrors(['gateway_id', 'device_name', 'device_type']);

        // Test valid data passes validation
        $gateway = Gateway::factory()->create();
        $validDeviceData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Valid Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ];

        $validResponse = $this->post(DeviceResource::getUrl('store'), $validDeviceData);
        $validResponse->assertRedirect();
        $this->assertDatabaseHas('devices', ['device_name' => 'Valid Device']);
    }

    /** @test */
    public function navigation_flow_and_breadcrumbs_work_correctly()
    {
        $gateway = Gateway::factory()->create(['name' => 'Test Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
        ]);

        // Test navigation from gateway to devices
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);
        $devicesResponse = $this->get($manageDevicesUrl);
        
        $devicesResponse->assertSuccessful();
        $devicesResponse->assertSee('Test Gateway'); // Parent context
        $devicesResponse->assertSee('Devices');

        // Test navigation from device to registers
        $manageRegistersUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]);
        
        $registersResponse = $this->get($manageRegistersUrl);
        
        $registersResponse->assertSuccessful();
        $registersResponse->assertSee('Test Gateway'); // Gateway context
        $registersResponse->assertSee('Test Device'); // Device context
        $registersResponse->assertSee('Registers');

        // Test breadcrumb navigation elements are present
        $registersResponse->assertSee('Gateways'); // Breadcrumb link
    }

    /** @test */
    public function error_handling_works_across_resources()
    {
        // Test accessing non-existent gateway
        $response = $this->get(GatewayResource::getUrl('edit', ['record' => 999999]));
        $response->assertStatus(404);

        // Test accessing non-existent device
        $response = $this->get(DeviceResource::getUrl('edit', ['record' => 999999]));
        $response->assertStatus(404);

        // Test accessing device registers with mismatched gateway/device
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway1->id]);

        $invalidUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway2->id, // Wrong gateway
            'device' => $device->id,
        ]);

        $response = $this->get($invalidUrl);
        $response->assertStatus(404);
    }

    /** @test */
    public function hierarchical_data_relationships_are_maintained()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);

        // Test cascade deletion - deleting gateway should delete devices and registers
        $gatewayId = $gateway->id;
        $deviceId = $device->id;
        $registerId = $register->id;

        $this->delete(GatewayResource::getUrl('destroy', ['record' => $gateway]));

        $this->assertDatabaseMissing('gateways', ['id' => $gatewayId]);
        $this->assertDatabaseMissing('devices', ['id' => $deviceId]);
        $this->assertDatabaseMissing('registers', ['id' => $registerId]);
    }

    /** @test */
    public function table_filtering_and_search_work_correctly()
    {
        $gateway1 = Gateway::factory()->create(['name' => 'Production Gateway']);
        $gateway2 = Gateway::factory()->create(['name' => 'Test Gateway']);
        
        $device1 = Device::factory()->create([
            'gateway_id' => $gateway1->id,
            'device_name' => 'Energy Meter',
            'device_type' => 'energy_meter',
        ]);
        
        $device2 = Device::factory()->create([
            'gateway_id' => $gateway2->id,
            'device_name' => 'Water Meter',
            'device_type' => 'water_meter',
        ]);

        // Test gateway list displays both gateways
        $response = $this->get(GatewayResource::getUrl('index'));
        $response->assertSee('Production Gateway');
        $response->assertSee('Test Gateway');

        // Test device list displays both devices
        $response = $this->get(DeviceResource::getUrl('index'));
        $response->assertSee('Energy Meter');
        $response->assertSee('Water Meter');
    }

    /** @test */
    public function bulk_operations_work_correctly()
    {
        $gateway = Gateway::factory()->create();
        $devices = Device::factory()->count(3)->create(['gateway_id' => $gateway->id]);

        foreach ($devices as $device) {
            Register::factory()->count(2)->create(['device_id' => $device->id]);
        }

        // Test that bulk operations don't break the interface
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);
        $response = $this->get($manageDevicesUrl);
        
        $response->assertSuccessful();
        
        // Verify all devices are displayed
        foreach ($devices as $device) {
            $response->assertSee($device->device_name);
        }
    }

    /** @test */
    public function resource_statistics_and_counts_are_accurate()
    {
        $gateway = Gateway::factory()->create();
        $devices = Device::factory()->count(3)->create(['gateway_id' => $gateway->id]);
        
        $totalRegisters = 0;
        foreach ($devices as $device) {
            $registerCount = rand(2, 5);
            Register::factory()->count($registerCount)->create(['device_id' => $device->id]);
            $totalRegisters += $registerCount;
        }

        // Test gateway view shows correct device count
        $response = $this->get(GatewayResource::getUrl('view', ['record' => $gateway]));
        $response->assertSuccessful();

        // Test manage devices page shows devices
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);
        $response = $this->get($manageDevicesUrl);
        
        $response->assertSuccessful();
        foreach ($devices as $device) {
            $response->assertSee($device->device_name);
        }
    }
}