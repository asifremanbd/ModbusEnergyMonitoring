<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Services\NavigationContextService;
use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices;
use App\Filament\Resources\GatewayResource\Pages\ManageDeviceRegisters;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NavigationEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $gateway;
    protected Device $device;
    protected Register $register;
    protected NavigationContextService $navigationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->navigationService = app(NavigationContextService::class);
        
        // Create test data
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true,
        ]);
        
        $this->device = Device::factory()->create([
            'gateway_id' => $this->gateway->id,
            'device_name' => 'Test Device',
            'device_type' => 'energy_meter',
            'load_category' => 'hvac',
            'enabled' => true,
        ]);
        
        $this->register = Register::factory()->create([
            'device_id' => $this->device->id,
            'technical_label' => 'Test Register',
            'function' => 4,
            'register_address' => 1025,
            'data_type' => 'float32',
            'enabled' => true,
        ]);
    }

    public function test_breadcrumb_generation_for_devices_level()
    {
        $breadcrumbs = $this->navigationService->generateBreadcrumbs('devices', $this->gateway);
        
        $this->assertArrayHasKey(GatewayResource::getUrl('index'), $breadcrumbs);
        $this->assertEquals('Gateways', $breadcrumbs[GatewayResource::getUrl('index')]);
        $this->assertArrayHasKey('#', $breadcrumbs);
        $this->assertEquals('Devices - Test Gateway', $breadcrumbs['#']);
    }

    public function test_breadcrumb_generation_for_registers_level()
    {
        $breadcrumbs = $this->navigationService->generateBreadcrumbs('registers', $this->gateway, $this->device);
        
        $this->assertArrayHasKey(GatewayResource::getUrl('index'), $breadcrumbs);
        $this->assertEquals('Gateways', $breadcrumbs[GatewayResource::getUrl('index')]);
        
        $devicesUrl = ManageGatewayDevices::getUrl(['record' => $this->gateway->id]);
        $this->assertArrayHasKey($devicesUrl, $breadcrumbs);
        $this->assertEquals('Devices - Test Gateway', $breadcrumbs[$devicesUrl]);
        
        $this->assertArrayHasKey('#', $breadcrumbs);
        $this->assertEquals('Registers - Test Device', $breadcrumbs['#']);
    }

    public function test_navigation_context_generation_for_devices()
    {
        $context = $this->navigationService->generateNavigationContext('devices', $this->gateway);
        
        $this->assertEquals('devices', $context['level']);
        $this->assertArrayHasKey('gateway', $context);
        $this->assertEquals($this->gateway->id, $context['gateway']['id']);
        $this->assertEquals('Test Gateway', $context['gateway']['name']);
        $this->assertEquals('192.168.1.100', $context['gateway']['ip_address']);
        $this->assertEquals(502, $context['gateway']['port']);
        $this->assertTrue($context['gateway']['is_active']);
    }

    public function test_navigation_context_generation_for_registers()
    {
        $context = $this->navigationService->generateNavigationContext('registers', $this->gateway, $this->device);
        
        $this->assertEquals('registers', $context['level']);
        $this->assertArrayHasKey('gateway', $context);
        $this->assertArrayHasKey('device', $context);
        
        $this->assertEquals($this->device->id, $context['device']['id']);
        $this->assertEquals('Test Device', $context['device']['device_name']);
        $this->assertEquals('energy_meter', $context['device']['device_type']);
        $this->assertEquals('hvac', $context['device']['load_category']);
        $this->assertTrue($context['device']['enabled']);
    }

    public function test_page_title_generation()
    {
        $devicesTitle = $this->navigationService->generatePageTitle('devices', $this->gateway);
        $this->assertEquals('Manage Devices - Test Gateway', $devicesTitle);
        
        $registersTitle = $this->navigationService->generatePageTitle('registers', $this->gateway, $this->device);
        $this->assertEquals('Manage Registers - Test Device', $registersTitle);
    }

    public function test_page_subheading_generation()
    {
        $devicesSubheading = $this->navigationService->generatePageSubheading('devices', $this->gateway);
        $this->assertStringContains('Gateway: 192.168.1.100:502', $devicesSubheading);
        $this->assertStringContains('Devices: 1', $devicesSubheading);
        
        $registersSubheading = $this->navigationService->generatePageSubheading('registers', $this->gateway, $this->device);
        $this->assertStringContains('Gateway: Test Gateway (192.168.1.100:502)', $registersSubheading);
        $this->assertStringContains('Device: Test Device (Energy Meter)', $registersSubheading);
        $this->assertStringContains('Registers: 1', $registersSubheading);
    }

    public function test_navigation_urls_generation()
    {
        $devicesUrls = $this->navigationService->getNavigationUrls('devices', $this->gateway);
        $this->assertEquals(GatewayResource::getUrl('index'), $devicesUrls['parent']);
        $this->assertEquals('Gateways', $devicesUrls['parent_label']);
        
        $registersUrls = $this->navigationService->getNavigationUrls('registers', $this->gateway, $this->device);
        $this->assertEquals(ManageGatewayDevices::getUrl(['record' => $this->gateway->id]), $registersUrls['parent']);
        $this->assertEquals('Devices - Test Gateway', $registersUrls['parent_label']);
        $this->assertEquals(GatewayResource::getUrl('index'), $registersUrls['grandparent']);
        $this->assertEquals('Gateways', $registersUrls['grandparent_label']);
    }

    public function test_status_info_generation()
    {
        $status = $this->navigationService->generateStatusInfo($this->gateway, $this->device);
        
        $this->assertArrayHasKey('gateway_status', $status);
        $this->assertTrue($status['gateway_status']['is_active']);
        $this->assertEquals('inactive', $status['gateway_status']['connection_status']); // No last_seen_at set
        
        $this->assertArrayHasKey('device_status', $status);
        $this->assertTrue($status['device_status']['enabled']);
        $this->assertEquals('Energy Meter', $status['device_status']['type']);
        $this->assertEquals('HVAC', $status['device_status']['category']);
    }

    public function test_gateway_connection_status_determination()
    {
        // Test inactive gateway
        $this->gateway->update(['is_active' => false]);
        $status = $this->navigationService->generateStatusInfo($this->gateway);
        $this->assertEquals('inactive', $status['gateway_status']['connection_status']);
        
        // Test never connected gateway
        $this->gateway->update(['is_active' => true, 'last_seen_at' => null]);
        $status = $this->navigationService->generateStatusInfo($this->gateway);
        $this->assertEquals('never_connected', $status['gateway_status']['connection_status']);
        
        // Test online gateway
        $this->gateway->update([
            'is_active' => true,
            'last_seen_at' => now(),
            'poll_interval' => 10
        ]);
        $status = $this->navigationService->generateStatusInfo($this->gateway);
        $this->assertEquals('online', $status['gateway_status']['connection_status']);
        
        // Test offline gateway
        $this->gateway->update([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(5),
            'poll_interval' => 10
        ]);
        $status = $this->navigationService->generateStatusInfo($this->gateway);
        $this->assertEquals('offline', $status['gateway_status']['connection_status']);
    }
}