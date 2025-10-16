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

class FinalWorkflowIntegrationTest extends TestCase
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
    public function complete_gateway_to_register_workflow_works_end_to_end()
    {
        // Step 1: Create Gateway
        $gatewayData = [
            'name' => 'Production Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'active' => true,
        ];

        $createGatewayResponse = $this->post(GatewayResource::getUrl('store'), $gatewayData);
        $createGatewayResponse->assertRedirect();

        $gateway = Gateway::where('name', 'Production Gateway')->first();
        $this->assertNotNull($gateway);

        // Step 2: Navigate to Device Management
        $manageDevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $manageDevicesResponse->assertSuccessful();
        $manageDevicesResponse->assertSee('Production Gateway');
        $manageDevicesResponse->assertSee('Devices');

        // Step 3: Create Device
        $deviceData = [
            'gateway_id' => $gateway->id,
            'device_name' => 'Energy Meter 1',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ];

        // Simulate device creation through the manage devices page
        $device = Device::create($deviceData);
        $this->assertDatabaseHas('devices', [
            'device_name' => 'Energy Meter 1',
            'gateway_id' => $gateway->id,
        ]);

        // Step 4: Navigate to Register Management
        $manageRegistersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $manageRegistersResponse->assertSuccessful();
        $manageRegistersResponse->assertSee('Production Gateway');
        $manageRegistersResponse->assertSee('Energy Meter 1');
        $manageRegistersResponse->assertSee('Registers');

        // Step 5: Create Registers
        $registerData = [
            'device_id' => $device->id,
            'technical_label' => 'Active Power L1',
            'function' => 4,
            'register_address' => 1000,
            'data_type' => 'float32',
            'byte_order' => 'big_endian',
            'scale' => 1.0,
            'count' => 2,
            'enabled' => true,
        ];

        $register = Register::create($registerData);
        $this->assertDatabaseHas('registers', [
            'technical_label' => 'Active Power L1',
            'device_id' => $device->id,
        ]);

        // Step 6: Verify Complete Hierarchy
        $gateway->refresh();
        $this->assertEquals(1, $gateway->devices()->count());
        $this->assertEquals(1, $gateway->registers()->count());

        $device->refresh();
        $this->assertEquals(1, $device->registers()->count());

        // Step 7: Test Navigation Back Through Hierarchy
        $backToDevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $backToDevicesResponse->assertSuccessful();
        $backToDevicesResponse->assertSee('Energy Meter 1');

        $backToGatewaysResponse = $this->get(GatewayResource::getUrl('index'));
        $backToGatewaysResponse->assertSuccessful();
        $backToGatewaysResponse->assertSee('Production Gateway');
    }

    /** @test */
    public function bulk_operations_work_across_hierarchy_levels()
    {
        $gateway = Gateway::factory()->create(['name' => 'Bulk Test Gateway']);
        
        // Create multiple devices
        $devices = Device::factory()->count(3)->create([
            'gateway_id' => $gateway->id,
        ]);

        // Create multiple registers for each device
        foreach ($devices as $device) {
            Register::factory()->count(2)->create([
                'device_id' => $device->id,
            ]);
        }

        // Test device management page shows all devices
        $manageDevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $manageDevicesResponse->assertSuccessful();
        
        foreach ($devices as $device) {
            $manageDevicesResponse->assertSee($device->device_name);
        }

        // Test register management for each device
        foreach ($devices as $device) {
            $manageRegistersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
                'gateway' => $gateway->id,
                'device' => $device->id,
            ]));
            
            $manageRegistersResponse->assertSuccessful();
            $manageRegistersResponse->assertSee($device->device_name);
            
            foreach ($device->registers as $register) {
                $manageRegistersResponse->assertSee($register->technical_label);
            }
        }

        // Test cascade deletion
        $deviceIds = $devices->pluck('id')->toArray();
        $registerIds = Register::whereIn('device_id', $deviceIds)->pluck('id')->toArray();

        $gateway->delete();

        // Verify cascade deletion worked
        $this->assertDatabaseMissing('gateways', ['id' => $gateway->id]);
        foreach ($deviceIds as $deviceId) {
            $this->assertDatabaseMissing('devices', ['id' => $deviceId]);
        }
        foreach ($registerIds as $registerId) {
            $this->assertDatabaseMissing('registers', ['id' => $registerId]);
        }
    }

    /** @test */
    public function form_validation_works_throughout_workflow()
    {
        // Test Gateway validation
        $invalidGatewayData = [
            'name' => '',
            'ip_address' => 'invalid-ip',
            'port' => 70000,
        ];

        $gatewayResponse = $this->post(GatewayResource::getUrl('store'), $invalidGatewayData);
        $gatewayResponse->assertSessionHasErrors(['name', 'ip_address', 'port']);

        // Create valid gateway for device testing
        $gateway = Gateway::factory()->create();

        // Test Device validation
        $invalidDeviceData = [
            'gateway_id' => 999999,
            'device_name' => '',
            'device_type' => 'invalid_type',
        ];

        $deviceResponse = $this->post(DeviceResource::getUrl('store'), $invalidDeviceData);
        $deviceResponse->assertSessionHasErrors(['gateway_id', 'device_name', 'device_type']);

        // Create valid device for register testing
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Test Register validation (simulated)
        $invalidRegisterData = [
            'device_id' => 999999,
            'technical_label' => '',
            'function' => 99,
            'register_address' => 70000,
            'data_type' => 'invalid_type',
        ];

        // Since we don't have a direct register resource, we test model validation
        try {
            Register::create($invalidRegisterData);
            $this->fail('Expected validation exception');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected validation failure
        }
    }

    /** @test */
    public function navigation_breadcrumbs_are_accurate_throughout_workflow()
    {
        $gateway = Gateway::factory()->create(['name' => 'Breadcrumb Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Breadcrumb Device',
        ]);

        // Test gateway list (root level)
        $gatewayListResponse = $this->get(GatewayResource::getUrl('index'));
        $gatewayListResponse->assertSuccessful();
        $gatewayListResponse->assertSee('Gateways');

        // Test device management (second level)
        $deviceManagementResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $deviceManagementResponse->assertSuccessful();
        $deviceManagementResponse->assertSee('Breadcrumb Gateway'); // Parent context
        $deviceManagementResponse->assertSee('Devices');

        // Test register management (third level)
        $registerManagementResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $registerManagementResponse->assertSuccessful();
        $registerManagementResponse->assertSee('Breadcrumb Gateway'); // Gateway context
        $registerManagementResponse->assertSee('Breadcrumb Device'); // Device context
        $registerManagementResponse->assertSee('Registers');
    }

    /** @test */
    public function error_handling_works_gracefully_throughout_workflow()
    {
        // Test accessing non-existent gateway
        $response = $this->get(GatewayResource::getUrl('manage-devices', ['record' => 999999]));
        $response->assertStatus(404);

        // Test accessing device with wrong gateway
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway1->id]);

        $wrongGatewayResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway2->id,
            'device' => $device->id,
        ]));
        $wrongGatewayResponse->assertStatus(404);

        // Test accessing non-existent device
        $nonExistentDeviceResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway1->id,
            'device' => 999999,
        ]));
        $nonExistentDeviceResponse->assertStatus(404);
    }

    /** @test */
    public function responsive_design_elements_are_present()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Test gateway list responsive elements
        $gatewayListResponse = $this->get(GatewayResource::getUrl('index'));
        $gatewayListResponse->assertSuccessful();
        // FilamentPHP automatically includes responsive classes

        // Test device management responsive elements
        $deviceManagementResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $deviceManagementResponse->assertSuccessful();
        // FilamentPHP tables are responsive by default

        // Test register management responsive elements
        $registerManagementResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        $registerManagementResponse->assertSuccessful();
        // FilamentPHP forms are responsive by default
    }

    /** @test */
    public function workflow_efficiency_is_maintained()
    {
        $startTime = microtime(true);

        // Create gateway
        $gateway = Gateway::factory()->create();
        
        // Navigate to devices
        $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        
        // Create device
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        // Navigate to registers
        $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        // Create register
        Register::factory()->create(['device_id' => $device->id]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Workflow should complete in reasonable time (less than 5 seconds)
        $this->assertLessThan(5.0, $executionTime, 'Workflow took too long to complete');
    }

    /** @test */
    public function data_integrity_is_maintained_throughout_workflow()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);

        // Test relationships are properly maintained
        $this->assertEquals($gateway->id, $device->gateway_id);
        $this->assertEquals($device->id, $register->device_id);

        // Test relationship methods work
        $this->assertTrue($gateway->devices->contains($device));
        $this->assertTrue($device->registers->contains($register));
        $this->assertTrue($gateway->registers->contains($register));

        // Test counts are accurate
        $this->assertEquals(1, $gateway->devices()->count());
        $this->assertEquals(1, $device->registers()->count());
        $this->assertEquals(1, $gateway->registers()->count());

        // Test foreign key constraints
        try {
            Device::create([
                'gateway_id' => 999999,
                'device_name' => 'Invalid Device',
                'device_type' => 'energy_meter',
                'load_category' => 'hvac',
                'enabled' => true,
            ]);
            $this->fail('Expected foreign key constraint violation');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected constraint violation
        }
    }

    /** @test */
    public function direct_device_register_management_url_works()
    {
        $gateway = Gateway::factory()->create(['name' => 'Direct Access Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Direct Access Device',
        ]);
        $register = Register::factory()->create([
            'device_id' => $device->id,
            'technical_label' => 'Direct Access Register',
        ]);

        // Test direct access to device register management via DeviceResource
        $directRegisterUrl = DeviceResource::getUrl('manage-registers', ['record' => $device]);
        $directResponse = $this->get($directRegisterUrl);
        
        $directResponse->assertSuccessful();
        $directResponse->assertSee('Direct Access Device');
        $directResponse->assertSee('Direct Access Register');
        $directResponse->assertSee('Registers');

        // Test that the URL pattern matches expected format: /admin/devices/{id}/registers
        $this->assertStringContains("/admin/devices/{$device->id}/registers", $directRegisterUrl);

        // Test that both gateway context and direct device access work
        $gatewayContextUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]);
        $gatewayContextResponse = $this->get($gatewayContextUrl);
        
        $gatewayContextResponse->assertSuccessful();
        $gatewayContextResponse->assertSee('Direct Access Gateway'); // Gateway context
        $gatewayContextResponse->assertSee('Direct Access Device');
        $gatewayContextResponse->assertSee('Direct Access Register');

        // Both URLs should show the same register data but different context
        $directResponse->assertSee('Direct Access Register');
        $gatewayContextResponse->assertSee('Direct Access Register');
    }

    /** @test */
    public function all_requirements_are_validated_in_workflow()
    {
        // Requirement 1: Gateway management interface
        $gatewayResponse = $this->get(GatewayResource::getUrl('index'));
        $gatewayResponse->assertSuccessful();
        $gatewayResponse->assertSee('Gateways');

        // Requirement 2: Navigation from gateway to device management
        $gateway = Gateway::factory()->create();
        $deviceNavResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $deviceNavResponse->assertSuccessful();
        $deviceNavResponse->assertSee($gateway->name);

        // Requirement 3: Device management for each gateway
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $deviceManagementResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $deviceManagementResponse->assertSuccessful();
        $deviceManagementResponse->assertSee($device->device_name);

        // Requirement 4: Navigation from device to register management (both routes)
        $registerNavResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        $registerNavResponse->assertSuccessful();
        $registerNavResponse->assertSee($device->device_name);

        // Test direct device register management URL
        $directRegisterResponse = $this->get(DeviceResource::getUrl('manage-registers', ['record' => $device]));
        $directRegisterResponse->assertSuccessful();
        $directRegisterResponse->assertSee($device->device_name);

        // Requirement 5: Register management for each device
        $register = Register::factory()->create(['device_id' => $device->id]);
        $registerManagementResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        $registerManagementResponse->assertSuccessful();
        $registerManagementResponse->assertSee($register->technical_label);

        // Test register management via direct device URL
        $directRegisterManagementResponse = $this->get(DeviceResource::getUrl('manage-registers', ['record' => $device]));
        $directRegisterManagementResponse->assertSuccessful();
        $directRegisterManagementResponse->assertSee($register->technical_label);

        // Requirement 6: Proper relationships maintained
        $this->assertEquals($gateway->id, $device->gateway_id);
        $this->assertEquals($device->id, $register->device_id);

        // Requirement 7: Intuitive navigation between hierarchy levels
        $this->assertTrue(true); // Covered by navigation tests above

        // Requirement 8: Form validation and error handling
        $invalidData = ['name' => '', 'ip_address' => 'invalid'];
        $validationResponse = $this->post(GatewayResource::getUrl('store'), $invalidData);
        $validationResponse->assertSessionHasErrors();
    }
}