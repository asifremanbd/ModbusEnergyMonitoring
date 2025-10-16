<?php

namespace Tests\Integration;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use App\Services\TeltonikaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteUserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected GatewayManagementService $gatewayService;
    protected ModbusPollService $modbusService;
    protected TeltonikaTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->gatewayService = app(GatewayManagementService::class);
        $this->modbusService = app(ModbusPollService::class);
        $this->templateService = app(TeltonikaTemplateService::class);
    }

    /** @test */
    public function complete_gateway_setup_and_monitoring_workflow()
    {
        // Step 1: User accesses dashboard (should be empty initially)
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('0'); // No gateways initially

        // Step 2: User navigates to gateway creation
        $createResponse = $this->get('/admin/gateways/create');
        $createResponse->assertStatus(200);

        // Step 3: User creates gateway with connection details
        $gatewayData = [
            'name' => 'Production Meter 1',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
        ];

        $createGatewayResponse = $this->post('/admin/gateways', $gatewayData);
        $createGatewayResponse->assertRedirect();

        // Verify gateway was created
        $gateway = Gateway::where('name', 'Production Meter 1')->first();
        $this->assertNotNull($gateway);
        $this->assertEquals('192.168.1.100', $gateway->ip_address);

        // Step 4: User applies Teltonika template for data points
        $templateData = $this->templateService->getDefaultTemplate();
        
        foreach ($templateData['data_points'] as $pointData) {
            DataPoint::create([
                'gateway_id' => $gateway->id,
                'application' => $pointData['application'],
                'label' => $pointData['label'],
                'modbus_function' => $pointData['modbus_function'],
                'register_address' => $pointData['register_address'],
                'register_count' => $pointData['register_count'],
                'data_type' => $pointData['data_type'],
                'byte_order' => $pointData['byte_order'],
                'scale_factor' => $pointData['scale_factor'],
                'is_enabled' => true,
            ]);
        }

        // Verify data points were created
        $this->assertEquals(count($templateData['data_points']), $gateway->dataPoints()->count());

        // Step 5: User tests connection
        $testResponse = $this->post("/admin/gateways/{$gateway->id}/test-connection");
        $testResponse->assertStatus(302); // Redirect after action

        // Step 6: User starts polling
        $startPollingResponse = $this->post("/admin/gateways/{$gateway->id}/resume");
        $startPollingResponse->assertStatus(302);

        $gateway->refresh();
        $this->assertTrue($gateway->is_active);

        // Step 7: Simulate some readings being collected
        $dataPoints = $gateway->dataPoints;
        foreach ($dataPoints->take(3) as $dataPoint) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'raw_value' => '[12345, 67890]',
                'scaled_value' => 123.45,
                'quality' => 'good',
                'read_at' => now(),
            ]);
        }

        // Step 8: User views live data
        $liveDataResponse = $this->get('/admin/live-data');
        $liveDataResponse->assertStatus(200);
        $liveDataResponse->assertSee('Production Meter 1');
        $liveDataResponse->assertSee('123.45');

        // Step 9: User views updated dashboard with KPIs
        $updatedDashboardResponse = $this->get('/admin');
        $updatedDashboardResponse->assertStatus(200);
        $updatedDashboardResponse->assertSee('1'); // One gateway online

        // Step 10: User pauses gateway
        $pauseResponse = $this->post("/admin/gateways/{$gateway->id}/pause");
        $pauseResponse->assertStatus(302);

        $gateway->refresh();
        $this->assertFalse($gateway->is_active);

        // Step 11: User views gateway details
        $detailsResponse = $this->get("/admin/gateways/{$gateway->id}");
        $detailsResponse->assertStatus(200);
        $detailsResponse->assertSee('Production Meter 1');
        $detailsResponse->assertSee('Paused');
    }

    /** @test */
    public function bulk_gateway_management_workflow()
    {
        // Create multiple gateways
        $gateways = Gateway::factory()->count(5)->create([
            'is_active' => true,
        ]);

        // Add data points to each gateway
        foreach ($gateways as $gateway) {
            DataPoint::factory()->count(3)->create([
                'gateway_id' => $gateway->id,
            ]);
        }

        // User views all gateways
        $indexResponse = $this->get('/admin/gateways');
        $indexResponse->assertStatus(200);

        foreach ($gateways as $gateway) {
            $indexResponse->assertSee($gateway->name);
        }

        // User performs bulk pause operation
        $gatewayIds = $gateways->pluck('id')->toArray();
        $bulkPauseResponse = $this->post('/admin/gateways/bulk-pause', [
            'selected' => $gatewayIds,
        ]);

        // Verify all gateways are paused
        foreach ($gateways as $gateway) {
            $gateway->refresh();
            $this->assertFalse($gateway->is_active);
        }

        // User performs bulk resume operation
        $bulkResumeResponse = $this->post('/admin/gateways/bulk-resume', [
            'selected' => $gatewayIds,
        ]);

        // Verify all gateways are resumed
        foreach ($gateways as $gateway) {
            $gateway->refresh();
            $this->assertTrue($gateway->is_active);
        }
    }

    /** @test */
    public function data_point_configuration_workflow()
    {
        $gateway = Gateway::factory()->create();

        // User creates custom data point
        $dataPointData = [
            'gateway_id' => $gateway->id,
            'application' => 'automation',
            'unit' => 'kWh',
            'load_type' => 'other',
            'label' => 'Custom Voltage',
            'modbus_function' => 4,
            'register_address' => 100,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 0.1,
            'is_enabled' => true,
        ];

        $dataPoint = DataPoint::create($dataPointData);
        $this->assertDatabaseHas('data_points', $dataPointData);

        // User tests single register read
        $testReadResponse = $this->post("/admin/data-points/{$dataPoint->id}/test-read");
        $testReadResponse->assertStatus(302);

        // User clones data point to different group
        $cloneResponse = $this->post("/admin/data-points/{$dataPoint->id}/clone", [
            'target_group' => 'Meter_2',
            'register_offset' => 100,
        ]);

        // Verify cloned data point exists
        $this->assertDatabaseHas('data_points', [
            'gateway_id' => $gateway->id,
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
            'label' => 'Custom Voltage',
            'register_address' => 200, // Original + offset
        ]);

        // User disables data point
        $disableResponse = $this->post("/admin/data-points/{$dataPoint->id}/disable");
        
        $dataPoint->refresh();
        $this->assertFalse($dataPoint->is_enabled);
    }

    /** @test */
    public function error_handling_and_recovery_workflow()
    {
        $gateway = Gateway::factory()->create([
            'ip_address' => '192.168.1.999', // Invalid IP
            'is_active' => true,
        ]);

        DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
        ]);

        // Simulate connection failure
        $testResponse = $this->post("/admin/gateways/{$gateway->id}/test-connection");
        
        // User should see error notification
        $testResponse->assertSessionHas('filament.notifications');

        // User corrects the IP address
        $updateResponse = $this->put("/admin/gateways/{$gateway->id}", [
            'name' => $gateway->name,
            'ip_address' => '192.168.1.100', // Valid IP
            'port' => $gateway->port,
            'unit_id' => $gateway->unit_id,
            'poll_interval' => $gateway->poll_interval,
            'is_active' => true,
        ]);

        $gateway->refresh();
        $this->assertEquals('192.168.1.100', $gateway->ip_address);

        // User retests connection
        $retestResponse = $this->post("/admin/gateways/{$gateway->id}/test-connection");
        $retestResponse->assertStatus(302);
    }

    /** @test */
    public function dashboard_real_time_updates_workflow()
    {
        // Create gateways with different statuses
        $onlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now()->subSeconds(5),
            'success_count' => 100,
            'failure_count' => 2,
        ]);

        $offlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(10),
            'success_count' => 50,
            'failure_count' => 20,
        ]);

        $disabledGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        // Create recent readings for online gateway
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $onlineGateway->id,
        ]);

        Reading::factory()->count(10)->create([
            'data_point_id' => $dataPoint->id,
            'read_at' => now()->subMinutes(rand(1, 30)),
        ]);

        // User views dashboard
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);

        // Verify KPIs are calculated correctly
        $dashboardResponse->assertSee('1'); // Online gateways count
        $dashboardResponse->assertSee('98'); // Success rate (100/(100+2) * 100)

        // Verify gateway status indicators
        $dashboardResponse->assertSee($onlineGateway->name);
        $dashboardResponse->assertSee($offlineGateway->name);
        $dashboardResponse->assertSee($disabledGateway->name);
    }

    /** @test */
    public function mobile_responsive_workflow()
    {
        $gateway = Gateway::factory()->create();
        DataPoint::factory()->count(5)->create([
            'gateway_id' => $gateway->id,
        ]);

        // Simulate mobile user agent
        $mobileHeaders = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
        ];

        // Test mobile dashboard
        $dashboardResponse = $this->get('/admin', $mobileHeaders);
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('dashboard-mobile'); // Mobile-specific CSS class

        // Test mobile gateway list
        $gatewaysResponse = $this->get('/admin/gateways', $mobileHeaders);
        $gatewaysResponse->assertStatus(200);

        // Test mobile live data view
        $liveDataResponse = $this->get('/admin/live-data', $mobileHeaders);
        $liveDataResponse->assertStatus(200);
    }
}