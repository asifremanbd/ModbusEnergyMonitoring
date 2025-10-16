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

class FilamentNavigationIntegrationTest extends TestCase
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
    public function navigation_flow_from_gateways_to_devices_works()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Production Gateway',
            'ip_address' => '192.168.1.100',
        ]);

        // Start at gateway list
        $gatewayListResponse = $this->get(GatewayResource::getUrl('index'));
        $gatewayListResponse->assertSuccessful();
        $gatewayListResponse->assertSee('Production Gateway');

        // Navigate to gateway view
        $gatewayViewResponse = $this->get(GatewayResource::getUrl('view', ['record' => $gateway]));
        $gatewayViewResponse->assertSuccessful();
        $gatewayViewResponse->assertSee('Production Gateway');

        // Navigate to manage devices
        $manageDevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $manageDevicesResponse->assertSuccessful();
        $manageDevicesResponse->assertSee('Devices');
        $manageDevicesResponse->assertSee('Production Gateway'); // Parent context
    }

    /** @test */
    public function navigation_flow_from_devices_to_registers_works()
    {
        $gateway = Gateway::factory()->create(['name' => 'Test Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Energy Meter 1',
        ]);

        // Navigate to manage device registers
        $manageRegistersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));

        $manageRegistersResponse->assertSuccessful();
        $manageRegistersResponse->assertSee('Registers');
        $manageRegistersResponse->assertSee('Test Gateway'); // Gateway context
        $manageRegistersResponse->assertSee('Energy Meter 1'); // Device context
    }

    /** @test */
    public function breadcrumb_navigation_displays_correct_hierarchy()
    {
        $gateway = Gateway::factory()->create(['name' => 'Main Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Meter Device',
        ]);

        // Test breadcrumbs on device management page
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();
        
        // Should show gateway context
        $devicesResponse->assertSee('Main Gateway');
        $devicesResponse->assertSee('Devices');

        // Test breadcrumbs on register management page
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $registersResponse->assertSuccessful();
        
        // Should show full hierarchy
        $registersResponse->assertSee('Main Gateway');
        $registersResponse->assertSee('Meter Device');
        $registersResponse->assertSee('Registers');
    }

    /** @test */
    public function navigation_preserves_context_across_levels()
    {
        $gateway = Gateway::factory()->create(['name' => 'Context Gateway']);
        $device1 = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Device One',
        ]);
        $device2 = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Device Two',
        ]);

        // Navigate to devices page
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();
        $devicesResponse->assertSee('Device One');
        $devicesResponse->assertSee('Device Two');

        // Navigate to registers for device1
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device1->id,
        ]));
        
        $registersResponse->assertSuccessful();
        $registersResponse->assertSee('Context Gateway'); // Gateway context preserved
        $registersResponse->assertSee('Device One'); // Correct device context
        $registersResponse->assertDontSee('Device Two'); // Other device not shown in context
    }

    /** @test */
    public function navigation_handles_invalid_relationships_gracefully()
    {
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway1->id]);

        // Try to access device registers with wrong gateway
        $invalidResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway2->id, // Wrong gateway
            'device' => $device->id,
        ]));

        $invalidResponse->assertStatus(404);
    }

    /** @test */
    public function navigation_urls_are_correctly_generated()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Test gateway URLs
        $gatewayIndexUrl = GatewayResource::getUrl('index');
        $this->assertStringContains('/admin/gateways', $gatewayIndexUrl);

        $gatewayViewUrl = GatewayResource::getUrl('view', ['record' => $gateway]);
        $this->assertStringContains("/admin/gateways/{$gateway->id}", $gatewayViewUrl);

        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => $gateway]);
        $this->assertStringContains("/admin/gateways/{$gateway->id}/devices", $manageDevicesUrl);

        $manageRegistersUrl = GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]);
        $this->assertStringContains("/admin/gateways/{$gateway->id}/devices/{$device->id}/registers", $manageRegistersUrl);

        // Test device URLs
        $deviceIndexUrl = DeviceResource::getUrl('index');
        $this->assertStringContains('/admin/devices', $deviceIndexUrl);
    }

    /** @test */
    public function navigation_state_is_preserved_during_operations()
    {
        $gateway = Gateway::factory()->create();
        $devices = Device::factory()->count(5)->create(['gateway_id' => $gateway->id]);

        // Access devices page
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();

        // Verify all devices are shown
        foreach ($devices as $device) {
            $devicesResponse->assertSee($device->device_name);
        }

        // Navigate to registers for one device
        $firstDevice = $devices->first();
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $firstDevice->id,
        ]));

        $registersResponse->assertSuccessful();
        $registersResponse->assertSee($firstDevice->device_name);
    }

    /** @test */
    public function navigation_works_with_multiple_gateways_and_devices()
    {
        $gateway1 = Gateway::factory()->create(['name' => 'Gateway One']);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway Two']);
        
        $device1 = Device::factory()->create([
            'gateway_id' => $gateway1->id,
            'device_name' => 'Device A',
        ]);
        
        $device2 = Device::factory()->create([
            'gateway_id' => $gateway2->id,
            'device_name' => 'Device B',
        ]);

        // Test navigation to gateway1 devices
        $gateway1DevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway1]));
        $gateway1DevicesResponse->assertSuccessful();
        $gateway1DevicesResponse->assertSee('Gateway One');
        $gateway1DevicesResponse->assertSee('Device A');
        $gateway1DevicesResponse->assertDontSee('Device B'); // Should not see other gateway's devices

        // Test navigation to gateway2 devices
        $gateway2DevicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway2]));
        $gateway2DevicesResponse->assertSuccessful();
        $gateway2DevicesResponse->assertSee('Gateway Two');
        $gateway2DevicesResponse->assertSee('Device B');
        $gateway2DevicesResponse->assertDontSee('Device A'); // Should not see other gateway's devices
    }

    /** @test */
    public function navigation_handles_empty_states_gracefully()
    {
        $gateway = Gateway::factory()->create(['name' => 'Empty Gateway']);

        // Navigate to devices page with no devices
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();
        $devicesResponse->assertSee('Empty Gateway');
        $devicesResponse->assertSee('Devices');
        // Should show empty state or no devices message

        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Navigate to registers page with no registers
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $registersResponse->assertSuccessful();
        $registersResponse->assertSee('Empty Gateway');
        $registersResponse->assertSee($device->device_name);
        $registersResponse->assertSee('Registers');
    }

    /** @test */
    public function navigation_titles_and_headings_are_contextual()
    {
        $gateway = Gateway::factory()->create(['name' => 'Production System']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Main Meter',
        ]);

        // Test device management page title
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();
        
        // Should contain contextual information
        $devicesResponse->assertSee('Production System');
        $devicesResponse->assertSee('Devices');

        // Test register management page title
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $registersResponse->assertSuccessful();
        
        // Should contain full context
        $registersResponse->assertSee('Production System');
        $registersResponse->assertSee('Main Meter');
        $registersResponse->assertSee('Registers');
    }

    /** @test */
    public function back_navigation_works_correctly()
    {
        $gateway = Gateway::factory()->create(['name' => 'Test Gateway']);
        $device = Device::factory()->create([
            'gateway_id' => $gateway->id,
            'device_name' => 'Test Device',
        ]);

        // Navigate to registers page
        $registersResponse = $this->get(GatewayResource::getUrl('manage-registers', [
            'gateway' => $gateway->id,
            'device' => $device->id,
        ]));
        
        $registersResponse->assertSuccessful();
        
        // Should have navigation elements to go back
        $registersResponse->assertSee('Test Gateway');
        $registersResponse->assertSee('Test Device');
        
        // Test that we can navigate back to devices
        $devicesResponse = $this->get(GatewayResource::getUrl('manage-devices', ['record' => $gateway]));
        $devicesResponse->assertSuccessful();
        $devicesResponse->assertSee('Test Device');
        
        // Test that we can navigate back to gateway list
        $gatewayListResponse = $this->get(GatewayResource::getUrl('index'));
        $gatewayListResponse->assertSuccessful();
        $gatewayListResponse->assertSee('Test Gateway');
    }
}