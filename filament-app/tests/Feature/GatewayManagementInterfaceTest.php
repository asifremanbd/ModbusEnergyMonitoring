<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\User;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class GatewayManagementInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function can_view_gateways_index_page()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
        ]);

        $response = $this->get('/admin/gateways');

        $response->assertStatus(200);
        $response->assertSee('Test Gateway');
        $response->assertSee('192.168.1.100:502');
    }

    /** @test */
    public function can_create_gateway_through_wizard()
    {
        $response = $this->get('/admin/gateways/create');
        $response->assertStatus(200);

        // Test the wizard form submission
        $gatewayData = [
            'name' => 'New Test Gateway',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
        ];

        $response = $this->post('/admin/gateways', $gatewayData);

        $this->assertDatabaseHas('gateways', [
            'name' => 'New Test Gateway',
            'ip_address' => '192.168.1.101',
            'port' => 502,
        ]);
    }

    /** @test */
    public function can_create_gateway_with_data_points()
    {
        $gatewayData = [
            'name' => 'Gateway with Points',
            'ip_address' => '192.168.1.102',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
            'data_points' => [
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Voltage',
                    'modbus_function' => 4,
                    'register_address' => 1,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ];

        $response = $this->post('/admin/gateways', $gatewayData);

        $gateway = Gateway::where('name', 'Gateway with Points')->first();
        $this->assertNotNull($gateway);
        
        $this->assertDatabaseHas('data_points', [
            'gateway_id' => $gateway->id,
            'label' => 'Voltage',
            'register_address' => 1,
        ]);
    }

    /** @test */
    public function can_view_gateway_details()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Detail Gateway',
            'success_count' => 100,
            'failure_count' => 5,
        ]);

        DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'label' => 'Test Point',
        ]);

        $response = $this->get("/admin/gateways/{$gateway->id}");

        $response->assertStatus(200);
        $response->assertSee('Detail Gateway');
        $response->assertSee('Test Point');
        $response->assertSee('100'); // Success count
        $response->assertSee('5');   // Failure count
    }

    /** @test */
    public function can_edit_gateway()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Original Name',
            'ip_address' => '192.168.1.100',
        ]);

        $response = $this->get("/admin/gateways/{$gateway->id}/edit");
        $response->assertStatus(200);

        $updateData = [
            'name' => 'Updated Name',
            'ip_address' => '192.168.1.200',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 15,
            'is_active' => true,
        ];

        $response = $this->put("/admin/gateways/{$gateway->id}", $updateData);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'name' => 'Updated Name',
            'ip_address' => '192.168.1.200',
            'poll_interval' => 15,
        ]);
    }

    /** @test */
    public function can_delete_gateway()
    {
        $gateway = Gateway::factory()->create();
        DataPoint::factory()->create(['gateway_id' => $gateway->id]);

        $response = $this->delete("/admin/gateways/{$gateway->id}");

        $this->assertDatabaseMissing('gateways', ['id' => $gateway->id]);
        $this->assertDatabaseMissing('data_points', ['gateway_id' => $gateway->id]);
    }

    /** @test */
    public function can_pause_gateway_polling()
    {
        $gateway = Gateway::factory()->create(['is_active' => true]);

        // Mock the service to avoid actual Modbus calls
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway) {
            $mock->shouldReceive('pausePolling')
                ->once()
                ->with($gateway)
                ->andReturnNull();
        });

        $response = $this->post("/admin/gateways/{$gateway->id}/pause");

        $gateway->refresh();
        $this->assertFalse($gateway->is_active);
    }

    /** @test */
    public function can_resume_gateway_polling()
    {
        $gateway = Gateway::factory()->create(['is_active' => false]);

        // Mock the service to avoid actual Modbus calls
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway) {
            $mock->shouldReceive('resumePolling')
                ->once()
                ->with($gateway)
                ->andReturnNull();
        });

        $response = $this->post("/admin/gateways/{$gateway->id}/resume");

        $gateway->refresh();
        $this->assertTrue($gateway->is_active);
    }

    /** @test */
    public function can_test_gateway_connection()
    {
        $gateway = Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);

        // Mock the ModbusPollService to simulate successful connection
        $this->mock(ModbusPollService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->with('192.168.1.100', 502, 1)
                ->andReturn(new \App\Services\ConnectionTest(
                    success: true,
                    latency: 25.5,
                    testValue: 12345,
                    error: null
                ));
        });

        $response = $this->post("/admin/gateways/{$gateway->id}/test-connection");

        // Should receive success notification
        $response->assertSessionHas('filament.notifications');
    }

    /** @test */
    public function validates_gateway_configuration()
    {
        $response = $this->post('/admin/gateways', [
            'name' => '', // Required field missing
            'ip_address' => 'invalid-ip',
            'port' => 70000, // Out of range
            'unit_id' => 0, // Out of range
            'poll_interval' => 0, // Out of range
        ]);

        $response->assertSessionHasErrors([
            'name',
            'ip_address',
            'port',
            'unit_id',
            'poll_interval',
        ]);
    }

    /** @test */
    public function prevents_duplicate_gateway_configurations()
    {
        Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);

        $response = $this->post('/admin/gateways', [
            'name' => 'Duplicate Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
        ]);

        $response->assertSessionHasErrors(['ip_address']);
    }

    /** @test */
    public function can_bulk_pause_gateways()
    {
        $gateway1 = Gateway::factory()->create(['is_active' => true]);
        $gateway2 = Gateway::factory()->create(['is_active' => true]);
        $gateway3 = Gateway::factory()->create(['is_active' => false]);

        // Mock the service
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway1, $gateway2) {
            $mock->shouldReceive('pausePolling')
                ->twice()
                ->andReturnNull();
        });

        $response = $this->post('/admin/gateways/bulk-pause', [
            'selected' => [$gateway1->id, $gateway2->id, $gateway3->id],
        ]);

        // Should only pause the active gateways
        $gateway1->refresh();
        $gateway2->refresh();
        $gateway3->refresh();

        $this->assertFalse($gateway1->is_active);
        $this->assertFalse($gateway2->is_active);
        $this->assertFalse($gateway3->is_active); // Was already inactive
    }

    /** @test */
    public function can_bulk_resume_gateways()
    {
        $gateway1 = Gateway::factory()->create(['is_active' => false]);
        $gateway2 = Gateway::factory()->create(['is_active' => false]);
        $gateway3 = Gateway::factory()->create(['is_active' => true]);

        // Mock the service
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway1, $gateway2) {
            $mock->shouldReceive('resumePolling')
                ->twice()
                ->andReturnNull();
        });

        $response = $this->post('/admin/gateways/bulk-resume', [
            'selected' => [$gateway1->id, $gateway2->id, $gateway3->id],
        ]);

        // Should only resume the inactive gateways
        $gateway1->refresh();
        $gateway2->refresh();
        $gateway3->refresh();

        $this->assertTrue($gateway1->is_active);
        $this->assertTrue($gateway2->is_active);
        $this->assertTrue($gateway3->is_active); // Was already active
    }

    /** @test */
    public function displays_gateway_status_correctly()
    {
        $onlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now()->subSeconds(5),
            'poll_interval' => 10,
        ]);

        $offlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(5),
            'poll_interval' => 10,
        ]);

        $disabledGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->get('/admin/gateways');

        $response->assertStatus(200);
        
        // Check that status badges are displayed correctly
        $response->assertSee('online');
        $response->assertSee('offline');
        $response->assertSee('disabled');
    }

    /** @test */
    public function can_filter_gateways_by_status()
    {
        Gateway::factory()->create(['name' => 'Active Gateway', 'is_active' => true]);
        Gateway::factory()->create(['name' => 'Inactive Gateway', 'is_active' => false]);

        // Test filtering by active status
        $response = $this->get('/admin/gateways?tableFilters[is_active][value]=1');
        $response->assertSee('Active Gateway');
        $response->assertDontSee('Inactive Gateway');

        // Test filtering by inactive status
        $response = $this->get('/admin/gateways?tableFilters[is_active][value]=0');
        $response->assertSee('Inactive Gateway');
        $response->assertDontSee('Active Gateway');
    }

    /** @test */
    public function can_search_gateways_by_name_and_ip()
    {
        Gateway::factory()->create([
            'name' => 'Production Gateway',
            'ip_address' => '192.168.1.100',
        ]);
        
        Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.200',
        ]);

        // Search by name
        $response = $this->get('/admin/gateways?tableSearch=Production');
        $response->assertSee('Production Gateway');
        $response->assertDontSee('Test Gateway');

        // Search by IP
        $response = $this->get('/admin/gateways?tableSearch=192.168.1.200');
        $response->assertSee('Test Gateway');
        $response->assertDontSee('Production Gateway');
    }
}