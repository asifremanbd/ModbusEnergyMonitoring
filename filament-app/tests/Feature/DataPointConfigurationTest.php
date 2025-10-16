<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Services\ModbusPollService;
use App\Services\TeltonikaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Resources\GatewayResource\Pages\CreateGateway;

class DataPointConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user for Filament access
        $this->actingAs(\App\Models\User::factory()->create([
            'email' => 'admin@test.com',
        ]));
    }

    /** @test */
    public function it_can_apply_teltonika_template_to_data_points()
    {
        $component = Livewire::test(CreateGateway::class);
        
        // Set gateway connection details
        $component->fillForm([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'template' => 'teltonika_basic',
        ]);
        
        // Apply template
        $component->callFormComponentAction('template', 'apply_template');
        
        // Check that data points were populated
        $formData = $component->instance()->form->getState();
        $this->assertNotEmpty($formData['data_points']);
        $this->assertCount(4, $formData['data_points']); // Basic template has 4 points
        
        // Verify first data point structure
        $firstPoint = $formData['data_points'][0];
        $this->assertEquals('Basic', $firstPoint['application']);
        $this->assertEquals('Voltage', $firstPoint['label']);
        $this->assertEquals(4, $firstPoint['modbus_function']);
        $this->assertEquals(1, $firstPoint['register_address']);
        $this->assertEquals('float32', $firstPoint['data_type']);
        $this->assertEquals('word_swapped', $firstPoint['byte_order']);
    }

    /** @test */
    public function it_can_enable_and_disable_all_data_points()
    {
        $component = Livewire::test(CreateGateway::class);
        
        // Set up some data points
        $dataPoints = [
            [
                'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                'label' => 'Point 1',
                'modbus_function' => 4,
                'register_address' => 1,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
            [
                'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                'label' => 'Point 2',
                'modbus_function' => 4,
                'register_address' => 3,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
        ];
        
        $component->fillForm(['data_points' => $dataPoints]);
        
        // Disable all points
        $component->callFormComponentAction('data_points', 'disable_all');
        
        $formData = $component->instance()->form->getState();
        foreach ($formData['data_points'] as $point) {
            $this->assertFalse($point['is_enabled']);
        }
        
        // Enable all points
        $component->callFormComponentAction('data_points', 'enable_all');
        
        $formData = $component->instance()->form->getState();
        foreach ($formData['data_points'] as $point) {
            $this->assertTrue($point['is_enabled']);
        }
    }

    /** @test */
    public function it_can_duplicate_a_group_with_register_offset()
    {
        $component = Livewire::test(CreateGateway::class);
        
        // Set up source group
        $dataPoints = [
            [
                'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                'label' => 'Meter_1 Voltage',
                'modbus_function' => 4,
                'register_address' => 1,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
            [
                'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                'label' => 'Meter_1 Current',
                'modbus_function' => 4,
                'register_address' => 3,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
        ];
        
        $component->fillForm(['data_points' => $dataPoints]);
        
        // Duplicate group
        $component->callFormComponentAction('data_points', 'duplicate_group', [
            'source_group' => 'Meter_1',
            'target_group' => 'Meter_2',
            'register_offset' => 10,
        ]);
        
        $formData = $component->instance()->form->getState();
        $this->assertCount(4, $formData['data_points']); // Original 2 + duplicated 2
        
        // Check duplicated points
        $duplicatedPoints = array_filter($formData['data_points'], fn($p) => $p['application'] === 'Meter_2');
        $this->assertCount(2, $duplicatedPoints);
        
        $duplicatedPoint = array_values($duplicatedPoints)[0];
        $this->assertEquals('Meter_2', $duplicatedPoint['application']);
        $this->assertEquals('Meter_2 Voltage', $duplicatedPoint['label']);
        $this->assertEquals(11, $duplicatedPoint['register_address']); // 1 + 10 offset
    }

    /** @test */
    public function it_validates_data_point_configuration()
    {
        $component = Livewire::test(CreateGateway::class);
        
        // Try to create gateway with invalid data point
        $component->fillForm([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'data_points' => [
                [
                    'application' => '',  // Invalid: empty group name
                    'label' => 'Test Point',
                    'modbus_function' => 4,
                    'register_address' => 70000, // Invalid: out of range
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ]);
        
        $component->call('create');
        
        // Should have validation errors
        $component->assertHasFormErrors(['data_points.0.application', 'data_points.0.register_address']);
    }

    /** @test */
    public function it_can_preview_data_point_with_mock_service()
    {
        // Mock the ModbusPollService
        $mockService = $this->createMock(ModbusPollService::class);
        $mockService->method('readRegister')
            ->willReturn(new \App\Services\ReadingResult(
                success: true,
                rawValue: json_encode([1234, 5678]),
                scaledValue: 123.45,
                quality: 'good',
                error: null
            ));
        
        $this->app->instance(ModbusPollService::class, $mockService);
        
        $component = Livewire::test(CreateGateway::class);
        
        // Set gateway connection details
        $component->fillForm([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'data_points' => [
                [
                    'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                    'label' => 'Test Point',
                    'modbus_function' => 4,
                    'register_address' => 1,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ]);
        
        // Preview the data point
        $component->callFormComponentAction('data_points', 'preview', ['item' => 0]);
        
        // Should show success notification
        $component->assertNotified();
    }

    /** @test */
    public function it_creates_gateway_with_data_points_successfully()
    {
        $component = Livewire::test(CreateGateway::class);
        
        $component->fillForm([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
            'data_points' => [
                [
                    'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                    'label' => 'Voltage',
                    'modbus_function' => 4,
                    'register_address' => 1,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                    'label' => 'Current',
                    'modbus_function' => 4,
                    'register_address' => 3,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ]);
        
        $component->call('create');
        
        // Check gateway was created
        $this->assertDatabaseHas('gateways', [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);
        
        // Check data points were created
        $gateway = Gateway::where('name', 'Test Gateway')->first();
        $this->assertCount(2, $gateway->dataPoints);
        
        $dataPoint = $gateway->dataPoints->first();
        $this->assertEquals('Test', $dataPoint->application);
        $this->assertEquals('Voltage', $dataPoint->label);
        $this->assertEquals(4, $dataPoint->modbus_function);
        $this->assertEquals(1, $dataPoint->register_address);
    }

    /** @test */
    public function it_handles_template_application_errors_gracefully()
    {
        // Mock template service to throw exception
        $mockService = $this->createMock(TeltonikaTemplateService::class);
        $mockService->method('getTemplate')
            ->willReturn(null);
        
        $this->app->instance(TeltonikaTemplateService::class, $mockService);
        
        $component = Livewire::test(CreateGateway::class);
        
        $component->fillForm([
            'template' => 'invalid_template',
        ]);
        
        // Apply template should handle error gracefully
        $component->callFormComponentAction('template', 'apply_template');
        
        // Should not crash and form should remain functional
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    /** @test */
    public function it_exports_data_points_configuration()
    {
        $component = Livewire::test(CreateGateway::class);
        
        $dataPoints = [
            [
                'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
                'label' => 'Voltage',
                'modbus_function' => 4,
                'register_address' => 1,
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ],
        ];
        
        $component->fillForm(['data_points' => $dataPoints]);
        
        // Export CSV
        $component->callFormComponentAction('data_points', 'export_csv');
        
        // Should show success notification
        $component->assertNotified();
    }
}