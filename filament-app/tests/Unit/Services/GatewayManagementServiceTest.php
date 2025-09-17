<?php

namespace Tests\Unit\Services;

use App\Models\Gateway;
use App\Services\GatewayManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Carbon\Carbon;

class GatewayManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GatewayManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GatewayManagementService();
    }

    /** @test */
    public function it_can_create_a_gateway_with_valid_configuration()
    {
        $config = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
            'is_active' => true,
        ];

        $gateway = $this->service->createGateway($config);

        $this->assertInstanceOf(Gateway::class, $gateway);
        $this->assertEquals('Test Gateway', $gateway->name);
        $this->assertEquals('192.168.1.100', $gateway->ip_address);
        $this->assertEquals(502, $gateway->port);
        $this->assertEquals(1, $gateway->unit_id);
        $this->assertEquals(10, $gateway->poll_interval);
        $this->assertTrue($gateway->is_active);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->expectException(ValidationException::class);
        
        $config = [
            'name' => '',
            'ip_address' => '',
            'port' => null,
            'unit_id' => null,
            'poll_interval' => null,
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_validates_ip_address_format()
    {
        $this->expectException(ValidationException::class);
        
        $config = [
            'name' => 'Test Gateway',
            'ip_address' => 'invalid-ip',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_validates_port_range()
    {
        $this->expectException(ValidationException::class);
        
        $config = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 70000, // Invalid port
            'unit_id' => 1,
            'poll_interval' => 10,
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_validates_unit_id_range()
    {
        $this->expectException(ValidationException::class);
        
        $config = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 300, // Invalid unit ID
            'poll_interval' => 10,
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_validates_poll_interval_range()
    {
        $this->expectException(ValidationException::class);
        
        $config = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 0, // Invalid poll interval
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_prevents_duplicate_gateway_configurations()
    {
        // Create first gateway
        Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);

        $this->expectException(ValidationException::class);
        
        // Try to create duplicate
        $config = [
            'name' => 'Duplicate Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
        ];

        $this->service->createGateway($config);
    }

    /** @test */
    public function it_allows_same_ip_with_different_port_or_unit_id()
    {
        // Create first gateway
        Gateway::factory()->create([
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);

        // Different port should be allowed
        $config1 = [
            'name' => 'Gateway 2',
            'ip_address' => '192.168.1.100',
            'port' => 503,
            'unit_id' => 1,
            'poll_interval' => 10,
        ];

        $gateway1 = $this->service->createGateway($config1);
        $this->assertInstanceOf(Gateway::class, $gateway1);

        // Different unit ID should be allowed
        $config2 = [
            'name' => 'Gateway 3',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 2,
            'poll_interval' => 10,
        ];

        $gateway2 = $this->service->createGateway($config2);
        $this->assertInstanceOf(Gateway::class, $gateway2);
    }

    /** @test */
    public function it_can_update_gateway_configuration()
    {
        $gateway = Gateway::factory()->create([
            'name' => 'Original Name',
            'poll_interval' => 10,
        ]);

        $updateConfig = [
            'name' => 'Updated Name',
            'ip_address' => $gateway->ip_address,
            'port' => $gateway->port,
            'unit_id' => $gateway->unit_id,
            'poll_interval' => 20,
        ];

        $updatedGateway = $this->service->updateGateway($gateway, $updateConfig);

        $this->assertEquals('Updated Name', $updatedGateway->name);
        $this->assertEquals(20, $updatedGateway->poll_interval);
    }

    /** @test */
    public function it_can_pause_and_resume_polling()
    {
        $gateway = Gateway::factory()->create(['is_active' => true]);

        $this->service->pausePolling($gateway);
        $gateway->refresh();
        $this->assertFalse($gateway->is_active);

        $this->service->resumePolling($gateway);
        $gateway->refresh();
        $this->assertTrue($gateway->is_active);
    }

    /** @test */
    public function it_can_delete_gateway()
    {
        $gateway = Gateway::factory()->create();
        $gatewayId = $gateway->id;

        $result = $this->service->deleteGateway($gateway);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('gateways', ['id' => $gatewayId]);
    }

    /** @test */
    public function it_updates_health_status_on_successful_poll()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 5,
            'failure_count' => 2,
            'last_seen_at' => null,
        ]);

        $timestamp = Carbon::now();
        $this->service->updateHealthStatus($gateway, true, $timestamp);

        $gateway->refresh();
        $this->assertEquals(6, $gateway->success_count);
        $this->assertEquals(2, $gateway->failure_count);
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $gateway->last_seen_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_updates_health_status_on_failed_poll()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 5,
            'failure_count' => 2,
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);

        $originalLastSeen = $gateway->last_seen_at;
        $this->service->updateHealthStatus($gateway, false);

        $gateway->refresh();
        $this->assertEquals(5, $gateway->success_count);
        $this->assertEquals(3, $gateway->failure_count);
        $this->assertEquals($originalLastSeen->format('Y-m-d H:i:s'), $gateway->last_seen_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_can_reset_counters()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 10,
            'failure_count' => 5,
        ]);

        $this->service->resetCounters($gateway);

        $gateway->refresh();
        $this->assertEquals(0, $gateway->success_count);
        $this->assertEquals(0, $gateway->failure_count);
    }

    /** @test */
    public function it_provides_gateway_status_information()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 95,
            'failure_count' => 5,
            'last_seen_at' => Carbon::now()->subSeconds(30),
            'poll_interval' => 60,
        ]);

        $status = $this->service->getGatewayStatus($gateway);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('is_online', $status);
        $this->assertArrayHasKey('success_rate', $status);
        $this->assertArrayHasKey('total_polls', $status);
        $this->assertArrayHasKey('last_seen', $status);
        $this->assertArrayHasKey('consecutive_failures', $status);
        $this->assertArrayHasKey('health_status', $status);

        $this->assertTrue($status['is_online']);
        $this->assertEquals(95.0, $status['success_rate']);
        $this->assertEquals(100, $status['total_polls']);
        $this->assertEquals('healthy', $status['health_status']);
    }

    /** @test */
    public function it_determines_correct_health_status()
    {
        // Healthy gateway
        $healthyGateway = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 95,
            'failure_count' => 5,
            'last_seen_at' => Carbon::now()->subSeconds(30),
            'poll_interval' => 60,
        ]);

        $status = $this->service->getGatewayStatus($healthyGateway);
        $this->assertEquals('healthy', $status['health_status']);

        // Warning gateway
        $warningGateway = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 85,
            'failure_count' => 15,
            'last_seen_at' => Carbon::now()->subSeconds(30),
            'poll_interval' => 60,
        ]);

        $status = $this->service->getGatewayStatus($warningGateway);
        $this->assertEquals('warning', $status['health_status']);

        // Critical gateway
        $criticalGateway = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 70,
            'failure_count' => 30,
            'last_seen_at' => Carbon::now()->subSeconds(30),
            'poll_interval' => 60,
        ]);

        $status = $this->service->getGatewayStatus($criticalGateway);
        $this->assertEquals('critical', $status['health_status']);

        // Offline gateway
        $offlineGateway = Gateway::factory()->create([
            'is_active' => true,
            'success_count' => 95,
            'failure_count' => 5,
            'last_seen_at' => Carbon::now()->subMinutes(10),
            'poll_interval' => 60,
        ]);

        $status = $this->service->getGatewayStatus($offlineGateway);
        $this->assertEquals('offline', $status['health_status']);

        // Disabled gateway
        $disabledGateway = Gateway::factory()->create([
            'is_active' => false,
        ]);

        $status = $this->service->getGatewayStatus($disabledGateway);
        $this->assertEquals('disabled', $status['health_status']);
    }

    /** @test */
    public function it_can_get_all_gateways_with_status()
    {
        Gateway::factory()->count(3)->create();

        $gatewaysWithStatus = $this->service->getGatewaysWithStatus();

        $this->assertCount(3, $gatewaysWithStatus);
        
        foreach ($gatewaysWithStatus as $gateway) {
            $this->assertArrayHasKey('status', $gateway);
            $this->assertArrayHasKey('health_status', $gateway['status']);
        }
    }
}