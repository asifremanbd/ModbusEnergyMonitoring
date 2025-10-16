<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\GatewayStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip database-dependent tests if database is not available
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available for testing');
        }
    }

    private function isDatabaseAvailable(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /** @test */
    public function gateway_has_correct_fillable_fields()
    {
        $gateway = new Gateway();
        $fillable = $gateway->getFillable();
        
        $expectedFillable = [
            'name',
            'ip_address',
            'port',
            'unit_id',
            'poll_interval',
            'is_active',
            'last_seen_at',
            'success_count',
            'failure_count',
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    /** @test */
    public function gateway_has_correct_casts()
    {
        $gateway = new Gateway();
        $casts = $gateway->getCasts();
        
        $expectedCasts = [
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'poll_interval' => 'integer',
            'port' => 'integer',
            'unit_id' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
        ];
        
        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertArrayHasKey($field, $casts, "Field '{$field}' should have a cast");
            $this->assertEquals($expectedCast, $casts[$field], "Field '{$field}' should be cast to '{$expectedCast}'");
        }
    }

    /** @test */
    public function gateway_has_data_points_relationship()
    {
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $gateway->dataPoints());
        $this->assertTrue($gateway->dataPoints->contains($dataPoint));
    }

    /** @test */
    public function gateway_has_devices_relationship()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $gateway->devices());
        $this->assertTrue($gateway->devices->contains($device));
    }

    /** @test */
    public function gateway_has_registers_through_devices_relationship()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class, $gateway->registers());
        $this->assertTrue($gateway->registers->contains($register));
    }

    /** @test */
    public function gateway_has_readings_through_data_points_relationship()
    {
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        $reading = Reading::factory()->create(['data_point_id' => $dataPoint->id]);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class, $gateway->readings());
        $this->assertTrue($gateway->readings->contains($reading));
    }

    /** @test */
    public function active_scope_returns_only_active_gateways()
    {
        $activeGateway = Gateway::factory()->create(['is_active' => true]);
        $inactiveGateway = Gateway::factory()->create(['is_active' => false]);
        
        $activeGateways = Gateway::active()->get();
        
        $this->assertTrue($activeGateways->contains($activeGateway));
        $this->assertFalse($activeGateways->contains($inactiveGateway));
    }

    /** @test */
    public function success_rate_attribute_calculates_correctly()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 80,
            'failure_count' => 20
        ]);
        
        $this->assertEquals(80.0, $gateway->success_rate);
    }

    /** @test */
    public function success_rate_attribute_returns_zero_when_no_attempts()
    {
        $gateway = Gateway::factory()->create([
            'success_count' => 0,
            'failure_count' => 0
        ]);
        
        $this->assertEquals(0.0, $gateway->success_rate);
    }

    /** @test */
    public function enhanced_status_attribute_uses_gateway_status_service()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('computeStatus')
            ->with($gateway)
            ->once()
            ->andReturn('online');
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertEquals('online', $gateway->enhanced_status);
    }

    /** @test */
    public function recent_error_rate_attribute_uses_gateway_status_service()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('getRecentErrorRate')
            ->with($gateway)
            ->once()
            ->andReturn(15.5);
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertEquals(15.5, $gateway->recent_error_rate);
    }

    /** @test */
    public function is_online_attribute_returns_true_when_status_is_online()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('computeStatus')
            ->with($gateway)
            ->once()
            ->andReturn(GatewayStatusService::STATUS_ONLINE);
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertTrue($gateway->is_online);
    }

    /** @test */
    public function is_online_attribute_returns_false_when_status_is_not_online()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('computeStatus')
            ->with($gateway)
            ->once()
            ->andReturn('offline');
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertFalse($gateway->is_online);
    }

    /** @test */
    public function device_count_attribute_returns_correct_count()
    {
        $gateway = Gateway::factory()->create();
        Device::factory()->count(3)->create(['gateway_id' => $gateway->id]);
        
        $this->assertEquals(3, $gateway->device_count);
    }

    /** @test */
    public function register_count_attribute_returns_correct_count()
    {
        $gateway = Gateway::factory()->create();
        $device1 = Device::factory()->create(['gateway_id' => $gateway->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        Register::factory()->count(2)->create(['device_id' => $device1->id]);
        Register::factory()->count(3)->create(['device_id' => $device2->id]);
        
        $this->assertEquals(5, $gateway->register_count);
    }

    /** @test */
    public function enabled_device_count_attribute_returns_correct_count()
    {
        $gateway = Gateway::factory()->create();
        Device::factory()->count(2)->create(['gateway_id' => $gateway->id, 'enabled' => true]);
        Device::factory()->count(1)->create(['gateway_id' => $gateway->id, 'enabled' => false]);
        
        $this->assertEquals(2, $gateway->enabled_device_count);
    }

    /** @test */
    public function enabled_register_count_attribute_returns_correct_count()
    {
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        Register::factory()->count(3)->create(['device_id' => $device->id, 'enabled' => true]);
        Register::factory()->count(2)->create(['device_id' => $device->id, 'enabled' => false]);
        
        $this->assertEquals(3, $gateway->enabled_register_count);
    }

    /** @test */
    public function status_badge_color_attribute_uses_gateway_status_service()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('computeStatus')
            ->with($gateway)
            ->once()
            ->andReturn('online');
        $mockService->shouldReceive('getStatusBadgeColor')
            ->with('online')
            ->once()
            ->andReturn('success');
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertEquals('success', $gateway->status_badge_color);
    }

    /** @test */
    public function status_label_attribute_uses_gateway_status_service()
    {
        $gateway = Gateway::factory()->create();
        
        $mockService = $this->mock(GatewayStatusService::class);
        $mockService->shouldReceive('computeStatus')
            ->with($gateway)
            ->once()
            ->andReturn('online');
        $mockService->shouldReceive('getStatusLabel')
            ->with('online')
            ->once()
            ->andReturn('Online');
        
        $this->app->instance(GatewayStatusService::class, $mockService);
        
        $this->assertEquals('Online', $gateway->status_label);
    }

    /** @test */
    public function is_recently_seen_attribute_returns_true_when_seen_within_threshold()
    {
        $gateway = Gateway::factory()->create([
            'last_seen_at' => now()->subSeconds(30),
            'poll_interval' => 60
        ]);
        
        $this->assertTrue($gateway->is_recently_seen);
    }

    /** @test */
    public function is_recently_seen_attribute_returns_false_when_not_seen_within_threshold()
    {
        $gateway = Gateway::factory()->create([
            'last_seen_at' => now()->subSeconds(300), // 5 minutes ago
            'poll_interval' => 60 // 3x = 180 seconds threshold
        ]);
        
        $this->assertFalse($gateway->is_recently_seen);
    }

    /** @test */
    public function is_recently_seen_attribute_returns_false_when_never_seen()
    {
        $gateway = Gateway::factory()->create([
            'last_seen_at' => null,
            'poll_interval' => 60
        ]);
        
        $this->assertFalse($gateway->is_recently_seen);
    }

    /** @test */
    public function time_since_last_seen_attribute_returns_human_readable_time()
    {
        $gateway = Gateway::factory()->create([
            'last_seen_at' => now()->subMinutes(5)
        ]);
        
        $this->assertStringContains('5 minutes ago', $gateway->time_since_last_seen);
    }

    /** @test */
    public function time_since_last_seen_attribute_returns_null_when_never_seen()
    {
        $gateway = Gateway::factory()->create([
            'last_seen_at' => null
        ]);
        
        $this->assertNull($gateway->time_since_last_seen);
    }

    /** @test */
    public function get_validation_rules_returns_array()
    {
        $rules = Gateway::getValidationRules();
        
        $this->assertIsArray($rules);
    }

    /** @test */
    public function get_validation_messages_returns_array()
    {
        $messages = Gateway::getValidationMessages();
        
        $this->assertIsArray($messages);
    }

    /** @test */
    public function validate_data_returns_array()
    {
        $data = [
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502
        ];
        
        $errors = Gateway::validateData($data);
        
        $this->assertIsArray($errors);
    }

    /** @test */
    public function is_ip_port_unique_returns_boolean()
    {
        $result = Gateway::isIpPortUnique('192.168.1.100', 502);
        
        $this->assertIsBool($result);
    }

    /** @test */
    public function gateway_can_be_created_with_factory()
    {
        $gateway = Gateway::factory()->create();
        
        $this->assertInstanceOf(Gateway::class, $gateway);
        $this->assertDatabaseHas('gateways', ['id' => $gateway->id]);
    }

    /** @test */
    public function gateway_factory_creates_valid_data()
    {
        $gateway = Gateway::factory()->create();
        
        $this->assertNotEmpty($gateway->name);
        $this->assertNotEmpty($gateway->ip_address);
        $this->assertIsInt($gateway->port);
        $this->assertIsBool($gateway->is_active);
        $this->assertIsInt($gateway->poll_interval);
    }
}