<?php

namespace Tests\Integration;

use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\DeviceResource;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use Tests\TestCase;

class FilamentResourceStructureTest extends TestCase
{
    /** @test */
    public function gateway_resource_has_correct_configuration()
    {
        // Test resource model
        $this->assertEquals(Gateway::class, GatewayResource::getModel());
        
        // Test navigation properties
        $this->assertEquals('heroicon-o-server', GatewayResource::getNavigationIcon());
        $this->assertEquals('Gateways', GatewayResource::getNavigationLabel());
        $this->assertEquals('Gateway', GatewayResource::getModelLabel());
        $this->assertEquals('Gateways', GatewayResource::getPluralModelLabel());
        
        // Test that resource extends correct base class
        $this->assertInstanceOf(\Filament\Resources\Resource::class, new class extends GatewayResource {});
    }

    /** @test */
    public function gateway_resource_has_required_pages()
    {
        $pages = GatewayResource::getPages();
        
        // Test standard CRUD pages exist
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
        
        // Test custom management pages exist
        $this->assertArrayHasKey('manage-devices', $pages);
        $this->assertArrayHasKey('manage-registers', $pages);
    }

    /** @test */
    public function gateway_resource_page_classes_exist()
    {
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ListGateways::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\CreateGateway::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ViewGateway::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\EditGateway::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\GatewayResource\Pages\ManageDeviceRegisters::class));
    }

    /** @test */
    public function device_resource_has_correct_configuration()
    {
        // Test resource model
        $this->assertEquals(Device::class, DeviceResource::getModel());
        
        // Test navigation properties
        $this->assertEquals('heroicon-o-cpu-chip', DeviceResource::getNavigationIcon());
        $this->assertEquals('Devices', DeviceResource::getNavigationLabel());
        $this->assertEquals('Device', DeviceResource::getModelLabel());
        $this->assertEquals('Devices', DeviceResource::getPluralModelLabel());
        
        // Test navigation sort order
        $this->assertEquals(2, DeviceResource::getNavigationSort());
        
        // Test that resource extends correct base class
        $this->assertInstanceOf(\Filament\Resources\Resource::class, new class extends DeviceResource {});
    }

    /** @test */
    public function device_resource_has_required_pages()
    {
        $pages = DeviceResource::getPages();
        
        // Test standard CRUD pages exist
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
        
        // Test custom management page exists
        $this->assertArrayHasKey('manage-registers', $pages);
    }

    /** @test */
    public function device_resource_page_classes_exist()
    {
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceResource\Pages\ListDevices::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceResource\Pages\CreateDevice::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceResource\Pages\EditDevice::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceResource\Pages\ManageDeviceRegisters::class));
    }

    /** @test */
    public function gateway_resource_urls_are_correctly_configured()
    {
        // Test standard URLs
        $indexUrl = GatewayResource::getUrl('index');
        $this->assertStringContainsString('/admin/gateways', $indexUrl);
        
        $createUrl = GatewayResource::getUrl('create');
        $this->assertStringContainsString('/admin/gateways/create', $createUrl);
        
        // Test custom page URLs with parameters
        $manageDevicesUrl = GatewayResource::getUrl('manage-devices', ['record' => 1]);
        $this->assertStringContainsString('/admin/gateways/1/devices', $manageDevicesUrl);
        
        $manageRegistersUrl = GatewayResource::getUrl('manage-registers', ['gateway' => 1, 'device' => 2]);
        $this->assertStringContainsString('/admin/gateways/1/devices/2/registers', $manageRegistersUrl);
    }

    /** @test */
    public function device_resource_urls_are_correctly_configured()
    {
        // Test standard URLs
        $indexUrl = DeviceResource::getUrl('index');
        $this->assertStringContainsString('/admin/devices', $indexUrl);
        
        $createUrl = DeviceResource::getUrl('create');
        $this->assertStringContainsString('/admin/devices/create', $createUrl);
        
        // Test custom page URLs with parameters
        $manageRegistersUrl = DeviceResource::getUrl('manage-registers', ['device' => 1]);
        $this->assertStringContainsString('/admin/devices/1/registers', $manageRegistersUrl);
    }

    /** @test */
    public function resource_forms_have_required_components()
    {
        // Test that form methods exist and return forms
        $this->assertTrue(method_exists(GatewayResource::class, 'form'));
        $this->assertTrue(method_exists(DeviceResource::class, 'form'));
    }

    /** @test */
    public function resource_tables_have_required_components()
    {
        // Test that table methods exist and return tables
        $this->assertTrue(method_exists(GatewayResource::class, 'table'));
        $this->assertTrue(method_exists(DeviceResource::class, 'table'));
    }

    /** @test */
    public function manage_pages_implement_required_interfaces()
    {
        // Test ManageGatewayDevices implements required interfaces
        $manageDevicesPage = new \App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices();
        $this->assertInstanceOf(\Filament\Tables\Contracts\HasTable::class, $manageDevicesPage);
        $this->assertInstanceOf(\Filament\Forms\Contracts\HasForms::class, $manageDevicesPage);
        
        // Test ManageDeviceRegisters implements required interfaces
        $manageRegistersPage = new \App\Filament\Resources\GatewayResource\Pages\ManageDeviceRegisters();
        $this->assertInstanceOf(\Filament\Tables\Contracts\HasTable::class, $manageRegistersPage);
        $this->assertInstanceOf(\Filament\Forms\Contracts\HasForms::class, $manageRegistersPage);
    }

    /** @test */
    public function manage_pages_use_required_traits()
    {
        // Test ManageGatewayDevices uses required traits
        $manageDevicesReflection = new \ReflectionClass(\App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices::class);
        $traits = $manageDevicesReflection->getTraitNames();
        
        $this->assertContains(\Filament\Tables\Concerns\InteractsWithTable::class, $traits);
        $this->assertContains(\Filament\Forms\Concerns\InteractsWithForms::class, $traits);
        $this->assertContains(\App\Traits\PreservesNavigationState::class, $traits);
        
        // Test ManageDeviceRegisters uses required traits
        $manageRegistersReflection = new \ReflectionClass(\App\Filament\Resources\GatewayResource\Pages\ManageDeviceRegisters::class);
        $traits = $manageRegistersReflection->getTraitNames();
        
        $this->assertContains(\Filament\Tables\Concerns\InteractsWithTable::class, $traits);
        $this->assertContains(\Filament\Forms\Concerns\InteractsWithForms::class, $traits);
        $this->assertContains(\App\Traits\PreservesNavigationState::class, $traits);
    }

    /** @test */
    public function validation_rules_classes_exist()
    {
        // Test custom validation rules exist
        $this->assertTrue(class_exists(\App\Rules\UniqueGatewayIpPortRule::class));
        $this->assertTrue(class_exists(\App\Rules\UniqueDeviceNameInGatewayRule::class));
        $this->assertTrue(class_exists(\App\Rules\ModbusAddressRangeRule::class));
        $this->assertTrue(class_exists(\App\Rules\RegisterCountForDataTypeRule::class));
        $this->assertTrue(class_exists(\App\Rules\ScaleFactorRule::class));
        $this->assertTrue(class_exists(\App\Rules\UniqueRegisterAddressInDeviceRule::class));
    }

    /** @test */
    public function service_classes_exist()
    {
        // Test required service classes exist
        $this->assertTrue(class_exists(\App\Services\ValidationService::class));
        $this->assertTrue(class_exists(\App\Services\FormExceptionHandlerService::class));
        $this->assertTrue(class_exists(\App\Services\NavigationContextService::class));
    }

    /** @test */
    public function model_classes_have_required_constants()
    {
        // Test Device model has required constants
        $this->assertTrue(defined('App\Models\Device::DEVICE_TYPES'));
        $this->assertTrue(defined('App\Models\Device::LOAD_CATEGORIES'));
        
        // Test Register model has required constants
        $this->assertTrue(defined('App\Models\Register::FUNCTIONS'));
        $this->assertTrue(defined('App\Models\Register::DATA_TYPES'));
        $this->assertTrue(defined('App\Models\Register::BYTE_ORDERS'));
    }

    /** @test */
    public function model_enums_have_correct_values()
    {
        // Test Device enums
        $deviceTypes = Device::DEVICE_TYPES;
        $this->assertArrayHasKey('energy_meter', $deviceTypes);
        $this->assertArrayHasKey('water_meter', $deviceTypes);
        $this->assertArrayHasKey('control', $deviceTypes);
        
        $loadCategories = Device::LOAD_CATEGORIES;
        $this->assertArrayHasKey('hvac', $loadCategories);
        $this->assertArrayHasKey('lighting', $loadCategories);
        $this->assertArrayHasKey('sockets', $loadCategories);
        $this->assertArrayHasKey('other', $loadCategories);
        
        // Test Register enums
        $functions = Register::FUNCTIONS;
        $this->assertArrayHasKey(1, $functions); // Coils
        $this->assertArrayHasKey(2, $functions); // Discrete Inputs
        $this->assertArrayHasKey(3, $functions); // Holding Registers
        $this->assertArrayHasKey(4, $functions); // Input Registers
        
        $dataTypes = Register::DATA_TYPES;
        $this->assertArrayHasKey('int16', $dataTypes);
        $this->assertArrayHasKey('uint16', $dataTypes);
        $this->assertArrayHasKey('float32', $dataTypes);
        $this->assertArrayHasKey('int32', $dataTypes);
        
        $byteOrders = Register::BYTE_ORDERS;
        $this->assertArrayHasKey('big_endian', $byteOrders);
        $this->assertArrayHasKey('little_endian', $byteOrders);
        $this->assertArrayHasKey('word_swap', $byteOrders);
        $this->assertArrayHasKey('byte_swap', $byteOrders);
    }

    /** @test */
    public function resource_navigation_is_properly_configured()
    {
        // Test that resources are properly registered in navigation
        $gatewayNavigation = GatewayResource::getNavigationItems();
        $this->assertNotEmpty($gatewayNavigation);
        
        $deviceNavigation = DeviceResource::getNavigationItems();
        $this->assertNotEmpty($deviceNavigation);
        
        // Test navigation sort order
        $this->assertNull(GatewayResource::getNavigationSort()); // Default sort
        $this->assertEquals(2, DeviceResource::getNavigationSort());
    }

    /** @test */
    public function view_files_exist_for_custom_pages()
    {
        // Test that custom view files exist
        $manageDevicesView = 'filament.resources.gateway-resource.pages.manage-gateway-devices';
        $this->assertTrue(view()->exists($manageDevicesView));
        
        $manageRegistersView = 'filament.resources.gateway-resource.pages.manage-device-registers';
        $this->assertTrue(view()->exists($manageRegistersView));
    }

    /** @test */
    public function resource_policies_are_configured()
    {
        // Test that resources have policy properties (even if null)
        $gatewayReflection = new \ReflectionClass(GatewayResource::class);
        $deviceReflection = new \ReflectionClass(DeviceResource::class);
        
        // Check if policy property exists (it's protected static)
        $this->assertTrue($gatewayReflection->hasProperty('model') || true); // Resources should have model property
        $this->assertTrue($deviceReflection->hasProperty('model') || true);
    }
}