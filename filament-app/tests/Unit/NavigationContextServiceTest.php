<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\NavigationContextService;
use App\Models\Gateway;
use App\Models\Device;
use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices;

class NavigationContextServiceTest extends TestCase
{
    protected NavigationContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NavigationContextService();
    }

    public function test_generates_correct_page_titles()
    {
        $gateway = new Gateway(['name' => 'Test Gateway']);
        $device = new Device(['device_name' => 'Test Device']);

        $devicesTitle = $this->service->generatePageTitle('devices', $gateway);
        $this->assertEquals('Manage Devices - Test Gateway', $devicesTitle);

        $registersTitle = $this->service->generatePageTitle('registers', $gateway, $device);
        $this->assertEquals('Manage Registers - Test Device', $registersTitle);

        $defaultTitle = $this->service->generatePageTitle('unknown');
        $this->assertEquals('Gateway Management', $defaultTitle);
    }

    public function test_generates_breadcrumbs_for_devices_level()
    {
        $gateway = new Gateway(['name' => 'Test Gateway']);
        
        $breadcrumbs = $this->service->generateBreadcrumbs('devices', $gateway);
        
        $this->assertArrayHasKey(GatewayResource::getUrl('index'), $breadcrumbs);
        $this->assertEquals('Gateways', $breadcrumbs[GatewayResource::getUrl('index')]);
        $this->assertArrayHasKey('#', $breadcrumbs);
        $this->assertEquals('Devices - Test Gateway', $breadcrumbs['#']);
    }

    public function test_generates_breadcrumbs_for_registers_level()
    {
        $gateway = new Gateway(['id' => 1, 'name' => 'Test Gateway']);
        $device = new Device(['device_name' => 'Test Device']);
        
        $breadcrumbs = $this->service->generateBreadcrumbs('registers', $gateway, $device);
        
        $this->assertArrayHasKey(GatewayResource::getUrl('index'), $breadcrumbs);
        $this->assertEquals('Gateways', $breadcrumbs[GatewayResource::getUrl('index')]);
        
        $devicesUrl = ManageGatewayDevices::getUrl(['record' => 1]);
        $this->assertArrayHasKey($devicesUrl, $breadcrumbs);
        $this->assertEquals('Devices - Test Gateway', $breadcrumbs[$devicesUrl]);
        
        $this->assertArrayHasKey('#', $breadcrumbs);
        $this->assertEquals('Registers - Test Device', $breadcrumbs['#']);
    }

    public function test_generates_navigation_context()
    {
        $gateway = new Gateway([
            'id' => 1,
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true
        ]);
        
        $device = new Device([
            'id' => 1,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true
        ]);

        $context = $this->service->generateNavigationContext('registers', $gateway, $device);
        
        $this->assertEquals('registers', $context['level']);
        $this->assertArrayHasKey('timestamp', $context);
        $this->assertArrayHasKey('gateway', $context);
        $this->assertArrayHasKey('device', $context);
        
        $this->assertEquals(1, $context['gateway']['id']);
        $this->assertEquals('Test Gateway', $context['gateway']['name']);
        $this->assertEquals('192.168.1.100', $context['gateway']['ip_address']);
        $this->assertEquals(502, $context['gateway']['port']);
        $this->assertTrue($context['gateway']['is_active']);
        
        $this->assertEquals(1, $context['device']['id']);
        $this->assertEquals('Test Device', $context['device']['device_name']);
        $this->assertEquals('energy_meter', $context['device']['device_type']);
        $this->assertEquals('hvac', $context['device']['load_category']);
        $this->assertTrue($context['device']['enabled']);
    }

    public function test_determines_gateway_connection_status()
    {
        // Test inactive gateway
        $inactiveGateway = new Gateway(['is_active' => false]);
        $status = $this->service->generateStatusInfo($inactiveGateway);
        $this->assertEquals('inactive', $status['gateway_status']['connection_status']);

        // Test never connected gateway
        $neverConnectedGateway = new Gateway(['is_active' => true, 'last_seen_at' => null]);
        $status = $this->service->generateStatusInfo($neverConnectedGateway);
        $this->assertEquals('never_connected', $status['gateway_status']['connection_status']);

        // Test online gateway
        $onlineGateway = new Gateway([
            'is_active' => true,
            'last_seen_at' => now(),
            'poll_interval' => 10
        ]);
        $status = $this->service->generateStatusInfo($onlineGateway);
        $this->assertEquals('online', $status['gateway_status']['connection_status']);

        // Test offline gateway
        $offlineGateway = new Gateway([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(5),
            'poll_interval' => 10
        ]);
        $status = $this->service->generateStatusInfo($offlineGateway);
        $this->assertEquals('offline', $status['gateway_status']['connection_status']);
    }
}